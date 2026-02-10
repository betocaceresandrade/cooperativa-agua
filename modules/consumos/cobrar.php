<?php
/**
 * Cobrar Consumos por Acción
 * Los meses NO se pre-generan. Solo existen registros cuando se pagan o eximen.
 * La deuda se calcula dinámicamente basándose en meses pasados sin pago.
 */

require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/config/config.php';
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_once dirname(dirname(__DIR__)) . '/includes/functions.php';

requireLogin();

$accion_id = intval($_GET['accion_id'] ?? 0);
$anio = intval($_GET['anio'] ?? date('Y'));

$accion = getAccionCompleta($accion_id);

if (!$accion) {
    setFlash('error', 'Acción no encontrada');
    redirect('/modules/consumos/');
}

$mes_actual = intval(date('n'));
$anio_actual = intval(date('Y'));

// Procesar EXIMIR mes
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eximir_mes'])) {
    $mes_eximir = intval($_POST['mes_eximir']);
    $anio_eximir = intval($_POST['anio_eximir']);
    $motivo = trim($_POST['motivo_eximir'] ?? 'Sin especificar');
    
    // Verificar si ya existe un registro
    $existe = fetchOne(
        "SELECT id, estado FROM consumos_anuales WHERE accion_id = ? AND anio = ? AND mes = ?",
        [$accion_id, $anio_eximir, $mes_eximir]
    );
    
    if ($existe && $existe['estado'] === 'pagado') {
        setFlash('error', 'No se puede eximir un mes ya pagado');
    } elseif ($existe) {
        // Actualizar registro existente
        update(
            "UPDATE consumos_anuales SET estado = 'no_cobrable', motivo_no_cobrable = ? WHERE id = ?",
            [$motivo, $existe['id']]
        );
        setFlash('success', 'Mes eximido correctamente');
    } else {
        // Crear nuevo registro como no_cobrable
        insert(
            "INSERT INTO consumos_anuales (accion_id, anio, mes, monto, estado, motivo_no_cobrable)
             VALUES (?, ?, ?, ?, 'no_cobrable', ?)",
            [$accion_id, $anio_eximir, $mes_eximir, $accion['tarifa_monto'], $motivo]
        );
        setFlash('success', 'Mes eximido correctamente');
    }
    redirect("/modules/consumos/cobrar.php?accion_id=$accion_id&anio=$anio");
}

// Procesar REVERTIR exención
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['revertir_exencion'])) {
    $mes_revertir = intval($_POST['mes_revertir']);
    $anio_revertir = intval($_POST['anio_revertir']);
    
    // Eliminar el registro (la deuda se recalcula dinámicamente)
    update(
        "DELETE FROM consumos_anuales WHERE accion_id = ? AND anio = ? AND mes = ? AND estado = 'no_cobrable'",
        [$accion_id, $anio_revertir, $mes_revertir]
    );
    setFlash('success', 'Exención revertida');
    redirect("/modules/consumos/cobrar.php?accion_id=$accion_id&anio=$anio");
}

// Procesar pago
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registrar_pago'])) {
    $meses_a_pagar = $_POST['meses'] ?? [];
    $metodo_pago = $_POST['metodo_pago'] ?? 'efectivo';
    $notas = trim($_POST['notas'] ?? '');

    if (empty($meses_a_pagar)) {
        setFlash('error', 'Seleccione al menos un mes para cobrar');
    } else {
        $pdo = getConnection();
        try {
            $pdo->beginTransaction();

            $total_consumos = 0;
            $meses_cobrados = [];

            foreach ($meses_a_pagar as $mes_key) {
                list($anio_mes, $mes_num) = explode('-', $mes_key);
                $anio_mes = intval($anio_mes);
                $mes_num = intval($mes_num);
                $monto = $accion['tarifa_monto'];

                $existe = fetchOne(
                    "SELECT id FROM consumos_anuales WHERE accion_id = ? AND anio = ? AND mes = ? AND estado IN ('pagado','no_cobrable')",
                    [$accion_id, $anio_mes, $mes_num]
                );
                
                if (!$existe) {
                    $total_consumos += $monto;
                    $meses_cobrados[] = getNombreMes($mes_num) . ' ' . $anio_mes;
                }
            }

            if ($total_consumos <= 0) {
                throw new Exception("No hay montos a cobrar");
            }

            $numero_recibo = generarNumeroRecibo();

            $pago_id = insert(
                "INSERT INTO pagos (numero_recibo, socio_id, accion_id, fecha_pago, monto_total, metodo_pago, notas)
                 VALUES (?, ?, ?, NOW(), ?, ?, ?)",
                [$numero_recibo, $accion['socio_id'], $accion_id, $total_consumos, $metodo_pago, $notas]
            );

            foreach ($meses_a_pagar as $mes_key) {
                list($anio_mes, $mes_num) = explode('-', $mes_key);
                $anio_mes = intval($anio_mes);
                $mes_num = intval($mes_num);
                $monto = $accion['tarifa_monto'];

                $existe = fetchOne(
                    "SELECT id FROM consumos_anuales WHERE accion_id = ? AND anio = ? AND mes = ?",
                    [$accion_id, $anio_mes, $mes_num]
                );

                if (!$existe) {
                    insert(
                        "INSERT INTO consumos_anuales (accion_id, anio, mes, monto, estado, pago_id, fecha_pago)
                         VALUES (?, ?, ?, ?, 'pagado', ?, CURDATE())",
                        [$accion_id, $anio_mes, $mes_num, $monto, $pago_id]
                    );
                }
            }

            $concepto = "Pago #$numero_recibo - Acción {$accion['numero_accion']}";
            if (!empty($meses_cobrados)) {
                $concepto .= " (" . implode(', ', array_slice($meses_cobrados, 0, 3));
                if (count($meses_cobrados) > 3) $concepto .= "...";
                $concepto .= ")";
            }
            registrarMovimientoCaja('ingreso', $concepto, $total_consumos, $metodo_pago, 'pago', $pago_id);

            $pdo->commit();
            setFlash('success', "Pago registrado. Recibo #$numero_recibo");
            redirect('/modules/recibos/imprimir.php?id=' . $pago_id);

        } catch (Exception $e) {
            $pdo->rollBack();
            setFlash('error', 'Error: ' . $e->getMessage());
        }
    }
}

$pageTitle = 'Cobrar Consumo';
require_once dirname(dirname(__DIR__)) . '/includes/header.php';

// Obtener meses registrados
$registros_existentes = fetchAll(
    "SELECT mes, estado, fecha_pago, motivo_no_cobrable FROM consumos_anuales 
     WHERE accion_id = ? AND anio = ?",
    [$accion_id, $anio]
);
$meses_registrados = [];
foreach ($registros_existentes as $r) {
    $meses_registrados[$r['mes']] = $r;
}

// Construir los 12 meses
$meses = [];
$total_pendiente = 0;
$meses_pendientes = 0;

for ($m = 1; $m <= 12; $m++) {
    $mes_info = [
        'mes' => $m,
        'nombre' => getNombreMes($m),
        'monto' => $accion['tarifa_monto'],
        'estado' => 'futuro',
        'fecha_pago' => null,
        'motivo_no_cobrable' => null
    ];

    if (isset($meses_registrados[$m])) {
        $mes_info['estado'] = $meses_registrados[$m]['estado'];
        $mes_info['fecha_pago'] = $meses_registrados[$m]['fecha_pago'];
        $mes_info['motivo_no_cobrable'] = $meses_registrados[$m]['motivo_no_cobrable'];
    } else {
        if ($anio < $anio_actual) {
            $mes_info['estado'] = 'pendiente';
            $total_pendiente += $mes_info['monto'];
            $meses_pendientes++;
        } elseif ($anio == $anio_actual) {
            if ($m < $mes_actual) {
                $mes_info['estado'] = 'pendiente';
                $total_pendiente += $mes_info['monto'];
                $meses_pendientes++;
            } elseif ($m == $mes_actual) {
                $mes_info['estado'] = 'actual';
            } else {
                $mes_info['estado'] = 'futuro';
            }
        } else {
            $mes_info['estado'] = 'futuro';
        }
    }

    $meses[] = $mes_info;
}

$anios_disponibles = [$anio_actual - 1, $anio_actual, $anio_actual + 1];
?>

<div class="page-header">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-1">
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/modules/socios/ver.php?id=<?= $accion['socio_id'] ?>"><?= htmlspecialchars($accion['socio_nombre']) ?></a></li>
            <li class="breadcrumb-item active">Cobrar</li>
        </ol>
    </nav>
    <h1 class="page-title">
        <i class="bi bi-cash-stack me-2"></i>Cobrar - Acción <?= htmlspecialchars($accion['numero_accion']) ?>
    </h1>
</div>

<div class="row">
    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-header"><i class="bi bi-droplet me-2"></i>Datos de la Acción</div>
            <div class="card-body">
                <table class="table table-sm table-borderless mb-0">
                    <tr><td class="text-muted">Acción N°</td><td><strong><?= htmlspecialchars($accion['numero_accion']) ?></strong></td></tr>
                    <tr><td class="text-muted">Socio</td><td><?= htmlspecialchars($accion['socio_nombre']) ?></td></tr>
                    <tr><td class="text-muted">Zona</td><td><?= htmlspecialchars($accion['zona_nombre'] ?? '-') ?></td></tr>
                    <tr><td class="text-muted">Tarifa</td><td><?= htmlspecialchars($accion['tarifa_nombre']) ?> - <strong class="text-primary"><?= formatMoney($accion['tarifa_monto']) ?>/mes</strong></td></tr>
                </table>
            </div>
        </div>

        <div class="card mb-4 <?= $meses_pendientes > 0 ? 'border-danger' : 'border-success' ?>">
            <div class="card-body text-center">
                <div class="text-muted mb-1">Deuda <?= $anio ?></div>
                <div class="fs-2 fw-bold <?= $meses_pendientes > 0 ? 'text-danger' : 'text-success' ?>"><?= formatMoney($total_pendiente) ?></div>
                <small class="text-muted"><?= $meses_pendientes ?> mes(es) vencido(s)</small>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header"><i class="bi bi-calendar me-2"></i>Año</div>
            <div class="card-body">
                <div class="btn-group w-100">
                    <?php foreach ($anios_disponibles as $a): ?>
                    <a href="?accion_id=<?= $accion_id ?>&anio=<?= $a ?>" class="btn btn-<?= $a == $anio ? 'primary' : 'outline-secondary' ?>"><?= $a ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <form method="POST" id="formCobro">
            <input type="hidden" name="registrar_pago" value="1">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between">
                    <span><i class="bi bi-calendar3 me-2"></i>Consumo <?= $anio ?></span>
                    <div>
                        <button type="button" class="btn btn-sm btn-outline-warning" id="btnSeleccionarDeuda">Seleccionar deuda</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="btnLimpiar">Limpiar</button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-2">
                        <?php foreach ($meses as $mes): ?>
                        <?php
                            $es_pagado = $mes['estado'] === 'pagado';
                            $es_no_cobrable = $mes['estado'] === 'no_cobrable';
                            $es_pendiente = $mes['estado'] === 'pendiente';
                            $es_actual = $mes['estado'] === 'actual';
                            $es_futuro = $mes['estado'] === 'futuro';
                            $puede_cobrar = $es_pendiente || $es_actual || $es_futuro;
                            $puede_eximir = !$es_pagado && !$es_no_cobrable;

                            if ($es_pagado) { $clase = 'border-success bg-success bg-opacity-10'; }
                            elseif ($es_no_cobrable) { $clase = 'border-secondary bg-secondary bg-opacity-10'; }
                            elseif ($es_pendiente) { $clase = 'border-danger'; }
                            elseif ($es_actual) { $clase = 'border-warning'; }
                            else { $clase = 'border-light'; }
                        ?>
                        <div class="col-6 col-md-4 col-lg-3">
                            <div class="card h-100 <?= $clase ?> mes-card" data-estado="<?= $mes['estado'] ?>" data-monto="<?= $mes['monto'] ?>">
                                <div class="card-body text-center p-2 position-relative">
                                    <?php if ($puede_cobrar): ?>
                                    <input type="checkbox" class="form-check-input position-absolute top-0 end-0 m-1 mes-check"
                                           name="meses[]" value="<?= $anio ?>-<?= $mes['mes'] ?>" data-deuda="<?= $es_pendiente ? '1' : '0' ?>">
                                    <?php endif; ?>

                                    <?php if ($puede_eximir): ?>
                                    <a href="#" class="btn-eximir position-absolute top-0 start-0 m-1" 
                                       data-mes="<?= $mes['mes'] ?>" data-anio="<?= $anio ?>" data-nombre="<?= $mes['nombre'] ?>"
                                       title="Eximir este mes"><i class="bi bi-x-circle text-secondary" style="font-size: 0.9rem;"></i></a>
                                    <?php endif; ?>

                                    <div class="fw-bold mb-1"><?= $mes['nombre'] ?></div>

                                    <?php if ($es_pagado): ?>
                                        <span class="badge bg-success"><i class="bi bi-check-circle"></i> Pagado</span>
                                        <?php if ($mes['fecha_pago']): ?><div class="small text-muted"><?= formatDate($mes['fecha_pago'], 'd/m/Y') ?></div><?php endif; ?>
                                    <?php elseif ($es_no_cobrable): ?>
                                        <span class="badge bg-secondary" title="<?= htmlspecialchars($mes['motivo_no_cobrable']) ?>">Eximido</span>
                                        <div class="small text-muted"><?= htmlspecialchars($mes['motivo_no_cobrable']) ?></div>
                                        <a href="#" class="btn-revertir small text-primary" data-mes="<?= $mes['mes'] ?>" data-anio="<?= $anio ?>">Revertir</a>
                                    <?php elseif ($es_pendiente): ?>
                                        <div class="fs-5 text-danger fw-bold"><?= formatMoney($mes['monto']) ?></div>
                                        <span class="badge bg-danger">Vencido</span>
                                    <?php elseif ($es_actual): ?>
                                        <div class="fs-5 text-warning fw-bold"><?= formatMoney($mes['monto']) ?></div>
                                        <span class="badge bg-warning text-dark">Mes actual</span>
                                    <?php else: ?>
                                        <div class="fs-6 text-muted"><?= formatMoney($mes['monto']) ?></div>
                                        <span class="badge bg-light text-dark">Futuro</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><i class="bi bi-receipt me-2"></i>Registrar Pago</div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Método de pago</label>
                                <div class="btn-group w-100">
                                    <input type="radio" class="btn-check" name="metodo_pago" value="efectivo" id="efectivo" checked>
                                    <label class="btn btn-outline-success" for="efectivo"><i class="bi bi-cash me-1"></i> Efectivo</label>
                                    <input type="radio" class="btn-check" name="metodo_pago" value="qr" id="qr">
                                    <label class="btn btn-outline-primary" for="qr"><i class="bi bi-qr-code me-1"></i> QR</label>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Notas (opcional)</label>
                                <textarea name="notas" class="form-control" rows="2"></textarea>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>Meses seleccionados:</span>
                                        <span id="countMeses">0</span>
                                    </div>
                                    <hr>
                                    <div class="d-flex justify-content-between fs-4">
                                        <strong>TOTAL:</strong>
                                        <strong class="text-primary" id="totalPagar">Bs 0.00</strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="mt-3 d-flex justify-content-between">
                        <a href="<?= BASE_URL ?>/modules/socios/ver.php?id=<?= $accion['socio_id'] ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Volver</a>
                        <button type="submit" class="btn btn-primary btn-lg" id="btnPagar" disabled>
                            <i class="bi bi-check-circle me-1"></i> Registrar Pago
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Modal Eximir -->
<div class="modal fade" id="modalEximir" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="eximir_mes" value="1">
                <input type="hidden" name="mes_eximir" id="mes_eximir">
                <input type="hidden" name="anio_eximir" id="anio_eximir">
                <div class="modal-header bg-secondary text-white py-2">
                    <h6 class="modal-title"><i class="bi bi-x-circle me-1"></i> Eximir Mes</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2">Eximir <strong id="mes_nombre_modal"></strong> del cobro:</p>
                    <input type="text" class="form-control" name="motivo_eximir" placeholder="Motivo (opcional)" maxlength="100">
                    <small class="text-muted">Este mes no generará deuda ni se podrá cobrar.</small>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-sm btn-secondary">Eximir</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Form oculto para revertir -->
<form method="POST" id="formRevertir" style="display:none;">
    <input type="hidden" name="revertir_exencion" value="1">
    <input type="hidden" name="mes_revertir" id="mes_revertir">
    <input type="hidden" name="anio_revertir" id="anio_revertir">
</form>

<style>
.mes-card { cursor: pointer; transition: all 0.2s; }
.mes-card:hover { transform: translateY(-2px); box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
.mes-card.selected { border-color: #0d6efd !important; background-color: rgba(13, 110, 253, 0.1) !important; }
.mes-check { transform: scale(1.3); }
.btn-eximir { opacity: 0.5; transition: opacity 0.2s; }
.btn-eximir:hover { opacity: 1; }
.btn-revertir { text-decoration: none; }
.btn-revertir:hover { text-decoration: underline; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const checks = document.querySelectorAll('.mes-check');
    const cards = document.querySelectorAll('.mes-card');
    const btnPagar = document.getElementById('btnPagar');
    const modalEximir = new bootstrap.Modal(document.getElementById('modalEximir'));

    function actualizar() {
        let total = 0, count = 0;
        checks.forEach(c => {
            if (c.checked) {
                total += parseFloat(c.closest('.mes-card').dataset.monto);
                count++;
            }
        });
        document.getElementById('totalPagar').textContent = 'Bs ' + total.toFixed(2);
        document.getElementById('countMeses').textContent = count;
        btnPagar.disabled = total <= 0;
        cards.forEach(card => {
            const check = card.querySelector('.mes-check');
            card.classList.toggle('selected', check && check.checked);
        });
    }

    cards.forEach(card => {
        card.addEventListener('click', function(e) {
            if (e.target.type === 'checkbox' || e.target.closest('.btn-eximir') || e.target.closest('.btn-revertir')) return;
            const check = card.querySelector('.mes-check');
            if (check) { check.checked = !check.checked; actualizar(); }
        });
    });

    checks.forEach(c => c.addEventListener('change', actualizar));

    document.getElementById('btnSeleccionarDeuda').addEventListener('click', function() {
        checks.forEach(c => { if (c.dataset.deuda === '1') c.checked = true; });
        actualizar();
    });

    document.getElementById('btnLimpiar').addEventListener('click', function() {
        checks.forEach(c => c.checked = false);
        actualizar();
    });

    // Eximir mes
    document.querySelectorAll('.btn-eximir').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            document.getElementById('mes_eximir').value = this.dataset.mes;
            document.getElementById('anio_eximir').value = this.dataset.anio;
            document.getElementById('mes_nombre_modal').textContent = this.dataset.nombre + ' ' + this.dataset.anio;
            modalEximir.show();
        });
    });

    // Revertir exención
    document.querySelectorAll('.btn-revertir').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            if (confirm('¿Revertir la exención de este mes?')) {
                document.getElementById('mes_revertir').value = this.dataset.mes;
                document.getElementById('anio_revertir').value = this.dataset.anio;
                document.getElementById('formRevertir').submit();
            }
        });
    });
});
</script>

<?php require_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
