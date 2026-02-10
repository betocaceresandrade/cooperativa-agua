<?php
/**
 * Funciones Auxiliares del Sistema
 */

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/config.php';

// =====================================================
// FUNCIONES DE SOCIOS
// =====================================================

/**
 * Obtener todos los socios
 */
function getSocios($filtros = []) {
    $sql = "SELECT s.*, z.nombre as zona_nombre
            FROM socios s
            LEFT JOIN zonas z ON s.zona_id = z.id
            WHERE 1=1";
    $params = [];

    if (!empty($filtros['estado'])) {
        $sql .= " AND s.estado = ?";
        $params[] = $filtros['estado'];
    }

    if (!empty($filtros['zona_id'])) {
        $sql .= " AND s.zona_id = ?";
        $params[] = $filtros['zona_id'];
    }

    if (!empty($filtros['buscar'])) {
        $sql .= " AND (s.nombre LIKE ? OR s.numero_socio LIKE ?)";
        $params[] = '%' . $filtros['buscar'] . '%';
        $params[] = '%' . $filtros['buscar'] . '%';
    }

    $sql .= " ORDER BY s.numero_socio ASC";

    return fetchAll($sql, $params);
}

/**
 * Obtener socio por ID
 */
function getSocioById($id) {
    return fetchOne(
        "SELECT s.*, z.nombre as zona_nombre
         FROM socios s
         LEFT JOIN zonas z ON s.zona_id = z.id
         WHERE s.id = ?",
        [$id]
    );
}

/**
 * Obtener siguiente número de socio
 */
function getNextNumeroSocio() {
    $result = fetchOne("SELECT MAX(CAST(numero_socio AS UNSIGNED)) as max_num FROM socios");
    $next = ($result['max_num'] ?? 0) + 1;
    return str_pad($next, 4, '0', STR_PAD_LEFT);
}

// =====================================================
// FUNCIONES DE ACCIONES
// =====================================================

/**
 * Obtener acciones de un socio
 */
function getAccionesSocio($socio_id) {
    return fetchAll(
        "SELECT a.*, t.nombre as tarifa_nombre, t.monto as tarifa_monto
         FROM acciones a
         JOIN tipos_tarifa t ON a.tipo_tarifa_id = t.id
         WHERE a.socio_id = ?
         ORDER BY a.numero_accion",
        [$socio_id]
    );
}

/**
 * Obtener acción por ID
 */
function getAccionById($id) {
    return fetchOne(
        "SELECT a.*, t.nombre as tarifa_nombre, t.monto as tarifa_monto,
                s.nombre as socio_nombre, s.numero_socio
         FROM acciones a
         JOIN tipos_tarifa t ON a.tipo_tarifa_id = t.id
         JOIN socios s ON a.socio_id = s.id
         WHERE a.id = ?",
        [$id]
    );
}

// =====================================================
// FUNCIONES DE SERVICIOS
// =====================================================

/**
 * Generar servicios mensuales para un periodo
 */
function generarServiciosMensuales($periodo) {
    $pdo = getConnection();
    $tipo_servicio = fetchOne("SELECT id FROM tipos_servicio WHERE es_mensual = 1 LIMIT 1");

    if (!$tipo_servicio) {
        return ['error' => 'No existe tipo de servicio mensual configurado'];
    }

    // Obtener acciones activas que no tienen servicio generado para este periodo
    $acciones = fetchAll(
        "SELECT a.id, t.monto
         FROM acciones a
         JOIN tipos_tarifa t ON a.tipo_tarifa_id = t.id
         JOIN socios s ON a.socio_id = s.id
         WHERE a.estado = 'activa'
         AND s.estado = 'activo'
         AND a.id NOT IN (
             SELECT accion_id FROM servicios
             WHERE periodo = ? AND tipo_servicio_id = ?
         )",
        [$periodo, $tipo_servicio['id']]
    );

    $generados = 0;
    foreach ($acciones as $accion) {
        insert(
            "INSERT INTO servicios (accion_id, tipo_servicio_id, periodo, monto, fecha_generacion, estado)
             VALUES (?, ?, ?, ?, CURDATE(), 'pendiente')",
            [$accion['id'], $tipo_servicio['id'], $periodo, $accion['monto']]
        );
        $generados++;
    }

    return ['generados' => $generados, 'periodo' => $periodo];
}

/**
 * Obtener servicios pendientes de una acción
 */
function getServiciosPendientes($accion_id) {
    return fetchAll(
        "SELECT s.*, ts.nombre as tipo_nombre
         FROM servicios s
         JOIN tipos_servicio ts ON s.tipo_servicio_id = ts.id
         WHERE s.accion_id = ?
         AND s.estado = 'pendiente'
         ORDER BY s.periodo ASC",
        [$accion_id]
    );
}

/**
 * Obtener servicios pendientes de un socio (todas sus acciones)
 */
function getServiciosPendientesSocio($socio_id) {
    return fetchAll(
        "SELECT s.*, ts.nombre as tipo_nombre, a.numero_accion, a.direccion_conexion
         FROM servicios s
         JOIN tipos_servicio ts ON s.tipo_servicio_id = ts.id
         JOIN acciones a ON s.accion_id = a.id
         WHERE a.socio_id = ?
         AND s.estado = 'pendiente'
         ORDER BY a.numero_accion, s.periodo ASC",
        [$socio_id]
    );
}

// =====================================================
// FUNCIONES DE PAGOS
// =====================================================

/**
 * Registrar un pago completo
 */
function registrarPago($socio_id, $servicios_ids, $items_adicionales, $metodo_pago, $notas = '') {
    $pdo = getConnection();

    try {
        $pdo->beginTransaction();

        // Calcular total de servicios
        $total_servicios = 0;
        if (!empty($servicios_ids)) {
            $placeholders = implode(',', array_fill(0, count($servicios_ids), '?'));
            $servicios = fetchAll(
                "SELECT id, monto FROM servicios WHERE id IN ($placeholders) AND estado = 'pendiente'",
                $servicios_ids
            );
            foreach ($servicios as $serv) {
                $total_servicios += $serv['monto'];
            }
        }

        // Calcular total de items adicionales
        $total_items = 0;
        foreach ($items_adicionales as $item) {
            $total_items += floatval($item['monto']);
        }

        $monto_total = $total_servicios + $total_items;

        // Generar número de recibo
        $numero_recibo = generarNumeroRecibo();

        // Insertar pago
        $pago_id = insert(
            "INSERT INTO pagos (numero_recibo, socio_id, fecha_pago, monto_total, metodo_pago, notas)
             VALUES (?, ?, NOW(), ?, ?, ?)",
            [$numero_recibo, $socio_id, $monto_total, $metodo_pago, $notas]
        );

        // Insertar detalles de servicios pagados
        foreach ($servicios_ids as $servicio_id) {
            $servicio = fetchOne("SELECT monto FROM servicios WHERE id = ?", [$servicio_id]);
            if ($servicio) {
                insert(
                    "INSERT INTO pago_detalles (pago_id, servicio_id, monto) VALUES (?, ?, ?)",
                    [$pago_id, $servicio_id, $servicio['monto']]
                );
                // Marcar servicio como pagado
                update("UPDATE servicios SET estado = 'pagado' WHERE id = ?", [$servicio_id]);
            }
        }

        // Insertar items adicionales
        foreach ($items_adicionales as $item) {
            if (!empty($item['nombre']) && floatval($item['monto']) > 0) {
                insert(
                    "INSERT INTO pago_items_adicionales (pago_id, nombre_item, monto) VALUES (?, ?, ?)",
                    [$pago_id, $item['nombre'], $item['monto']]
                );
            }
        }

        // Registrar movimiento de caja
        registrarMovimientoCaja('ingreso', "Pago recibo #$numero_recibo", $monto_total, $metodo_pago, 'pago', $pago_id);

        $pdo->commit();

        return ['success' => true, 'pago_id' => $pago_id, 'numero_recibo' => $numero_recibo];

    } catch (Exception $e) {
        $pdo->rollBack();
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Obtener pago por ID
 */
function getPagoById($id) {
    return fetchOne(
        "SELECT p.*, s.nombre as socio_nombre, s.numero_socio
         FROM pagos p
         JOIN socios s ON p.socio_id = s.id
         WHERE p.id = ?",
        [$id]
    );
}

/**
 * Obtener detalles de un pago
 */
function getPagoDetalles($pago_id) {
    $servicios = fetchAll(
        "SELECT pd.*, s.periodo, ts.nombre as tipo_nombre
         FROM pago_detalles pd
         JOIN servicios s ON pd.servicio_id = s.id
         JOIN tipos_servicio ts ON s.tipo_servicio_id = ts.id
         WHERE pd.pago_id = ?",
        [$pago_id]
    );

    $items = fetchAll(
        "SELECT * FROM pago_items_adicionales WHERE pago_id = ?",
        [$pago_id]
    );

    return ['servicios' => $servicios, 'items' => $items];
}

// =====================================================
// FUNCIONES DE CAJA
// =====================================================

/**
 * Registrar movimiento de caja
 */
function registrarMovimientoCaja($tipo, $concepto, $monto, $metodo, $ref_tipo = null, $ref_id = null) {
    // Obtener saldos actuales
    $saldos = getSaldosCaja();

    if ($tipo === 'ingreso') {
        if ($metodo === 'efectivo') {
            $saldos['efectivo'] += $monto;
        } else {
            $saldos['qr'] += $monto;
        }
    } else {
        if ($metodo === 'efectivo') {
            $saldos['efectivo'] -= $monto;
        } else {
            $saldos['qr'] -= $monto;
        }
    }

    return insert(
        "INSERT INTO movimientos_caja (tipo, concepto, monto, metodo, referencia_tipo, referencia_id, saldo_efectivo, saldo_qr)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
        [$tipo, $concepto, $monto, $metodo, $ref_tipo, $ref_id, $saldos['efectivo'], $saldos['qr']]
    );
}

/**
 * Obtener saldos actuales de caja
 */
function getSaldosCaja() {
    $ultimo = fetchOne("SELECT saldo_efectivo, saldo_qr FROM movimientos_caja ORDER BY id DESC LIMIT 1");

    if ($ultimo) {
        return [
            'efectivo' => floatval($ultimo['saldo_efectivo']),
            'qr' => floatval($ultimo['saldo_qr'])
        ];
    }

    return ['efectivo' => 0, 'qr' => 0];
}

// =====================================================
// FUNCIONES DE GASTOS
// =====================================================

/**
 * Registrar gasto
 */
function registrarGasto($categoria_id, $fecha, $concepto, $monto, $metodo_pago, $fondo_id = null, $notas = '') {
    $gasto_id = insert(
        "INSERT INTO gastos (categoria_id, fecha, concepto, monto, metodo_pago, fondo_rendir_id, notas)
         VALUES (?, ?, ?, ?, ?, ?, ?)",
        [$categoria_id, $fecha, $concepto, $monto, $metodo_pago, $fondo_id, $notas]
    );

    // Si no es de un fondo a rendir, registrar movimiento de caja
    if (!$fondo_id) {
        registrarMovimientoCaja('egreso', $concepto, $monto, $metodo_pago, 'gasto', $gasto_id);
    } else {
        // Actualizar monto rendido del fondo
        update(
            "UPDATE fondos_rendir SET monto_rendido = monto_rendido + ? WHERE id = ?",
            [$monto, $fondo_id]
        );
    }

    return $gasto_id;
}

// =====================================================
// FUNCIONES DE REPORTES
// =====================================================

/**
 * Obtener deudores
 */
function getDeudores($filtros = []) {
    $sql = "SELECT s.id, s.numero_socio, s.nombre, s.celular, z.nombre as zona,
                   COUNT(sv.id) as meses_deuda,
                   SUM(sv.monto) as total_deuda,
                   MIN(sv.periodo) as desde_periodo
            FROM socios s
            LEFT JOIN zonas z ON s.zona_id = z.id
            JOIN acciones a ON a.socio_id = s.id
            JOIN servicios sv ON sv.accion_id = a.id AND sv.estado = 'pendiente'
            WHERE s.estado = 'activo'";
    $params = [];

    if (!empty($filtros['zona_id'])) {
        $sql .= " AND s.zona_id = ?";
        $params[] = $filtros['zona_id'];
    }

    if (!empty($filtros['meses_minimo'])) {
        $sql .= " GROUP BY s.id HAVING meses_deuda >= ?";
        $params[] = $filtros['meses_minimo'];
    } else {
        $sql .= " GROUP BY s.id";
    }

    $sql .= " ORDER BY total_deuda DESC";

    return fetchAll($sql, $params);
}

/**
 * Obtener ingresos del mes
 */
function getIngresosMes($year, $month) {
    $periodo = sprintf('%04d-%02d', $year, $month);
    $result = fetchOne(
        "SELECT COALESCE(SUM(monto_total), 0) as total
         FROM pagos
         WHERE DATE_FORMAT(fecha_pago, '%Y-%m') = ?
         AND anulado = 0",
        [$periodo]
    );
    return floatval($result['total']);
}

/**
 * Obtener gastos del mes
 */
function getGastosMes($year, $month) {
    $inicio = sprintf('%04d-%02d-01', $year, $month);
    $fin = date('Y-m-t', strtotime($inicio));
    $result = fetchOne(
        "SELECT COALESCE(SUM(monto), 0) as total
         FROM gastos
         WHERE fecha BETWEEN ? AND ?",
        [$inicio, $fin]
    );
    return floatval($result['total']);
}

/**
 * Obtener total cuentas por cobrar
 */
function getTotalCuentasPorCobrar() {
    $result = fetchOne(
        "SELECT COALESCE(SUM(monto), 0) as total
         FROM servicios
         WHERE estado = 'pendiente'"
    );
    return floatval($result['total']);
}

// =====================================================
// FUNCIONES AUXILIARES
// =====================================================

/**
 * Obtener zonas activas
 */
function getZonas() {
    return fetchAll("SELECT * FROM zonas WHERE activo = 1 ORDER BY nombre");
}

/**
 * Obtener tipos de tarifa activos
 */
function getTiposTarifa() {
    return fetchAll("SELECT * FROM tipos_tarifa WHERE activo = 1 ORDER BY nombre");
}

/**
 * Obtener categorías de gasto activas
 */
function getCategoriasGasto() {
    return fetchAll("SELECT * FROM categorias_gasto WHERE activo = 1 ORDER BY nombre");
}

/**
 * Obtener items adicionales activos
 */
function getItemsAdicionales() {
    return fetchAll("SELECT * FROM items_adicionales WHERE activo = 1 ORDER BY nombre");
}

/**
 * Exonerar servicio
 */
function exonerarServicio($servicio_id, $motivo, $autorizado_por) {
    $pdo = getConnection();
    try {
        $pdo->beginTransaction();

        // Registrar exoneración
        insert(
            "INSERT INTO exoneraciones (servicio_id, fecha_exoneracion, motivo, autorizado_por)
             VALUES (?, CURDATE(), ?, ?)",
            [$servicio_id, $motivo, $autorizado_por]
        );

        // Anular servicio
        update("UPDATE servicios SET estado = 'anulado' WHERE id = ?", [$servicio_id]);

        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        return false;
    }
}

// =====================================================
// FUNCIONES DE CONSUMOS ANUALES (12 MESES)
// =====================================================

/**
 * Obtener consumos de una acción para un año
 */
function getConsumosAccion($accion_id, $anio = null) {
    if (!$anio) $anio = date('Y');

    return fetchAll(
        "SELECT * FROM consumos_anuales
         WHERE accion_id = ? AND anio = ?
         ORDER BY mes ASC",
        [$accion_id, $anio]
    );
}

/**
 * Inicializar los 12 meses de consumo para una acción
 */
function inicializarConsumosAnuales($accion_id, $anio = null) {
    if (!$anio) $anio = date('Y');

    // Obtener tarifa de la acción
    $accion = fetchOne(
        "SELECT a.*, t.monto as tarifa_monto
         FROM acciones a
         JOIN tipos_tarifa t ON a.tipo_tarifa_id = t.id
         WHERE a.id = ?",
        [$accion_id]
    );

    if (!$accion) return false;

    $monto = $accion['tarifa_monto'];

    // Verificar cuáles meses ya existen
    $existentes = fetchAll(
        "SELECT mes FROM consumos_anuales WHERE accion_id = ? AND anio = ?",
        [$accion_id, $anio]
    );
    $meses_existentes = array_column($existentes, 'mes');

    // Crear los meses que faltan
    $creados = 0;
    for ($mes = 1; $mes <= 12; $mes++) {
        if (!in_array($mes, $meses_existentes)) {
            insert(
                "INSERT INTO consumos_anuales (accion_id, anio, mes, monto, estado)
                 VALUES (?, ?, ?, ?, 'pendiente')",
                [$accion_id, $anio, $mes, $monto]
            );
            $creados++;
        }
    }

    return $creados;
}

/**
 * Obtener consumos de una acción con información completa
 */
function getConsumosAccionCompleto($accion_id, $anio = null) {
    if (!$anio) $anio = date('Y');

    // Asegurar que existan los 12 meses
    inicializarConsumosAnuales($accion_id, $anio);

    return fetchAll(
        "SELECT ca.*, p.numero_recibo, p.fecha_pago
         FROM consumos_anuales ca
         LEFT JOIN pagos p ON ca.pago_id = p.id
         WHERE ca.accion_id = ? AND ca.anio = ?
         ORDER BY ca.mes ASC",
        [$accion_id, $anio]
    );
}

/**
 * Obtener resumen de deuda de una acción
 */
function getResumenDeudaAccion($accion_id) {
    $result = fetchOne(
        "SELECT COUNT(*) as meses_pendientes, COALESCE(SUM(monto), 0) as total_deuda
         FROM consumos_anuales
         WHERE accion_id = ? AND estado = 'pendiente'",
        [$accion_id]
    );
    return $result;
}

/**
 * Marcar mes como no cobrable
 */
function marcarMesNoCobrable($consumo_id, $motivo) {
    return update(
        "UPDATE consumos_anuales
         SET estado = 'no_cobrable', motivo_no_cobrable = ?
         WHERE id = ?",
        [$motivo, $consumo_id]
    );
}

/**
 * Registrar pago de consumos (meses)
 */
function registrarPagoConsumos($accion_id, $consumos_ids, $otros_ingresos_ids, $metodo_pago, $notas = '') {
    $pdo = getConnection();

    try {
        $pdo->beginTransaction();

        // Obtener la acción y el socio
        $accion = fetchOne(
            "SELECT a.*, s.id as socio_id, s.nombre as socio_nombre
             FROM acciones a
             JOIN socios s ON a.socio_id = s.id
             WHERE a.id = ?",
            [$accion_id]
        );

        if (!$accion) {
            throw new Exception("Acción no encontrada");
        }

        // Calcular total de consumos
        $total_consumos = 0;
        $meses_pagados = [];
        if (!empty($consumos_ids)) {
            $placeholders = implode(',', array_fill(0, count($consumos_ids), '?'));
            $consumos = fetchAll(
                "SELECT id, mes, anio, monto FROM consumos_anuales
                 WHERE id IN ($placeholders) AND estado = 'pendiente'",
                $consumos_ids
            );
            foreach ($consumos as $c) {
                $total_consumos += $c['monto'];
                $meses_pagados[] = getNombreMes($c['mes']);
            }
        }

        // Calcular total de otros ingresos
        $total_otros = 0;
        if (!empty($otros_ingresos_ids)) {
            $placeholders = implode(',', array_fill(0, count($otros_ingresos_ids), '?'));
            $otros = fetchAll(
                "SELECT id, monto FROM otros_ingresos
                 WHERE id IN ($placeholders) AND estado = 'pendiente'",
                $otros_ingresos_ids
            );
            foreach ($otros as $o) {
                $total_otros += $o['monto'];
            }
        }

        $monto_total = $total_consumos + $total_otros;

        if ($monto_total <= 0) {
            throw new Exception("No hay montos a cobrar");
        }

        // Generar número de recibo
        $numero_recibo = generarNumeroRecibo();

        // Insertar pago
        $pago_id = insert(
            "INSERT INTO pagos (numero_recibo, socio_id, accion_id, fecha_pago, monto_total, metodo_pago, notas)
             VALUES (?, ?, ?, NOW(), ?, ?, ?)",
            [$numero_recibo, $accion['socio_id'], $accion_id, $monto_total, $metodo_pago, $notas]
        );

        // Actualizar consumos como pagados
        foreach ($consumos_ids as $consumo_id) {
            $consumo = fetchOne("SELECT monto FROM consumos_anuales WHERE id = ?", [$consumo_id]);
            if ($consumo) {
                // Insertar en pago_consumos
                insert(
                    "INSERT INTO pago_consumos (pago_id, consumo_anual_id, monto) VALUES (?, ?, ?)",
                    [$pago_id, $consumo_id, $consumo['monto']]
                );
                // Actualizar estado
                update(
                    "UPDATE consumos_anuales SET estado = 'pagado', pago_id = ?, fecha_pago = CURDATE()
                     WHERE id = ?",
                    [$pago_id, $consumo_id]
                );
            }
        }

        // Actualizar otros ingresos como pagados
        foreach ($otros_ingresos_ids as $otro_id) {
            update(
                "UPDATE otros_ingresos SET estado = 'pagado', pago_id = ? WHERE id = ?",
                [$pago_id, $otro_id]
            );
        }

        // Registrar movimiento de caja
        $concepto = "Pago #$numero_recibo - Acción {$accion['numero_accion']}";
        if (!empty($meses_pagados)) {
            $concepto .= " (" . implode(', ', $meses_pagados) . ")";
        }
        registrarMovimientoCaja('ingreso', $concepto, $monto_total, $metodo_pago, 'pago', $pago_id);

        $pdo->commit();

        return [
            'success' => true,
            'pago_id' => $pago_id,
            'numero_recibo' => $numero_recibo,
            'meses_pagados' => $meses_pagados
        ];

    } catch (Exception $e) {
        $pdo->rollBack();
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Obtener nombre del mes en español
 */
function getNombreMes($mes) {
    $meses = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
        5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
        9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
    ];
    return $meses[$mes] ?? '';
}

/**
 * Obtener nombre corto del mes
 */
function getNombreMesCorto($mes) {
    $meses = [
        1 => 'Ene', 2 => 'Feb', 3 => 'Mar', 4 => 'Abr',
        5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Ago',
        9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dic'
    ];
    return $meses[$mes] ?? '';
}

/**
 * Obtener otros ingresos pendientes de una acción
 */
function getOtrosIngresosPendientes($accion_id) {
    return fetchAll(
        "SELECT oi.*, toi.nombre as tipo_nombre
         FROM otros_ingresos oi
         JOIN tipos_otros_ingresos toi ON oi.tipo_id = toi.id
         WHERE oi.accion_id = ? AND oi.estado = 'pendiente'
         ORDER BY oi.fecha DESC",
        [$accion_id]
    );
}

/**
 * Obtener acción completa con zona y tarifa
 */
function getAccionCompleta($accion_id) {
    return fetchOne(
        "SELECT a.*, t.nombre as tarifa_nombre, t.monto as tarifa_monto,
                s.id as socio_id, s.nombre as socio_nombre, s.numero_socio, s.ci, s.celular,
                z.nombre as zona_nombre
         FROM acciones a
         JOIN tipos_tarifa t ON a.tipo_tarifa_id = t.id
         JOIN socios s ON a.socio_id = s.id
         LEFT JOIN zonas z ON a.zona_id = z.id
         WHERE a.id = ?",
        [$accion_id]
    );
}
