<?php
/**
 * Comprobante de Ingreso (Recibo)
 * Tamaño: Media carta - LETRA GRANDE para tercera edad
 */

require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/config/config.php';
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_once dirname(dirname(__DIR__)) . '/includes/functions.php';

requireLogin();

$pago_id = intval($_GET['id'] ?? 0);
$pago = getPagoById($pago_id);

if (!$pago) {
    setFlash('error', 'Pago no encontrado');
    redirect('/modules/cobros/');
}

// Obtener detalles del pago
$detalles = getPagoDetalles($pago_id);

// Obtener otros ingresos asociados al pago
$otros_ingresos = fetchAll(
    "SELECT oi.*, toi.nombre as tipo_nombre FROM otros_ingresos oi 
     LEFT JOIN tipos_otros_ingresos toi ON oi.tipo_id = toi.id 
     WHERE oi.recibo_id = ?",
    [$pago_id]
);

// Obtener consumos (meses) pagados
$consumos_pagados = fetchAll(
    "SELECT mes, anio, monto FROM consumos_anuales WHERE pago_id = ? ORDER BY anio, mes",
    [$pago_id]
);


// Obtener información de la acción si existe
$accion = null;
if ($pago['accion_id']) {
    $accion = fetchOne(
        "SELECT a.*, z.nombre as zona_nombre
         FROM acciones a
         LEFT JOIN zonas z ON a.zona_id = z.id
         WHERE a.id = ?",
        [$pago['accion_id']]
    );
}

$config = getConfig();

// Función para convertir número a literal en español
function numeroALetras($numero) {
    $numero = round($numero, 2);
    $entero = floor($numero);
    $decimales = round(($numero - $entero) * 100);

    $unidades = ['', 'uno', 'dos', 'tres', 'cuatro', 'cinco', 'seis', 'siete', 'ocho', 'nueve', 'diez',
                 'once', 'doce', 'trece', 'catorce', 'quince', 'dieciséis', 'diecisiete', 'dieciocho', 'diecinueve', 'veinte',
                 'veintiuno', 'veintidós', 'veintitrés', 'veinticuatro', 'veinticinco', 'veintiséis', 'veintisiete', 'veintiocho', 'veintinueve'];
    $decenas = ['', '', 'veinte', 'treinta', 'cuarenta', 'cincuenta', 'sesenta', 'setenta', 'ochenta', 'noventa'];
    $centenas = ['', 'ciento', 'doscientos', 'trescientos', 'cuatrocientos', 'quinientos', 'seiscientos', 'setecientos', 'ochocientos', 'novecientos'];

    if ($entero == 0) return 'cero';
    if ($entero == 100) return 'cien';

    $resultado = '';

    if ($entero >= 1000) {
        $miles = floor($entero / 1000);
        if ($miles == 1) {
            $resultado .= 'mil ';
        } else {
            $resultado .= numeroALetras($miles) . ' mil ';
        }
        $entero = $entero % 1000;
    }

    if ($entero >= 100) {
        $resultado .= $centenas[floor($entero / 100)] . ' ';
        $entero = $entero % 100;
    }

    if ($entero > 0) {
        if ($entero < 30) {
            $resultado .= $unidades[$entero];
        } else {
            $resultado .= $decenas[floor($entero / 10)];
            if ($entero % 10 > 0) {
                $resultado .= ' y ' . $unidades[$entero % 10];
            }
        }
    }

    $resultado = trim($resultado);
    $resultado .= ' ' . ($decimales > 0 ? $decimales . '/100' : '00/100') . ' Bolivianos';

    return ucfirst($resultado);
}

$monto_literal = numeroALetras($pago['monto_total']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comprobante #<?= htmlspecialchars($pago['numero_recibo']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        @media print {
            .no-print { display: none !important; }
            body { margin: 0; padding: 0; }
            .recibo { page-break-after: always; }
        }

        @page {
            size: letter;
            margin: 8mm;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 14px;
            background: #f0f0f0;
        }

        .recibo-container {
            max-width: 216mm;
            margin: 10px auto;
            background: white;
        }

        .recibo {
            border: 2px solid #079FEA;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 15px;
            height: 130mm;
            max-height: 130mm;
            overflow: hidden;
            box-sizing: border-box;
        }

        .recibo-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #079FEA;
            padding-bottom: 6px;
            margin-bottom: 8px;
        }

        .recibo-logo {
            display: flex;
            align-items: center;
        }

        .recibo-logo img {
            width: 45px;
            height: 45px;
            margin-right: 10px;
        }

        .recibo-title {
            color: #079FEA;
            font-size: 15px;
            font-weight: bold;
            margin: 0;
        }

        .recibo-subtitle {
            color: #666;
            font-size: 12px;
            margin: 0;
        }

        .recibo-number {
            background: #079FEA;
            color: white;
            padding: 6px 14px;
            border-radius: 12px;
            font-weight: bold;
            font-size: 14px;
        }

        .recibo-info {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 6px;
            margin-bottom: 8px;
        }

        .recibo-info-item {
            display: flex;
            flex-direction: column;
        }

        .recibo-info-label {
            color: #666;
            font-size: 12px;
            text-transform: uppercase;
        }

        .recibo-info-value {
            font-weight: bold;
            font-size: 15px;
        }

        .recibo-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .recibo-table th {
            background: #e8f6fd;
            color: #079FEA;
            padding: 5px 6px;
            text-align: left;
            font-size: 13px;
            text-transform: uppercase;
        }

        .recibo-table td {
            padding: 5px 6px;
            border-bottom: 1px solid #eee;
        }

        .recibo-table .text-end {
            text-align: right;
        }

        .recibo-total {
            background: #079FEA;
            color: white;
            padding: 8px 12px;
            border-radius: 6px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 6px;
            font-size: 15px;
        }

        .recibo-total-value {
            font-size: 20px;
            font-weight: bold;
        }

        .monto-literal {
            font-size: 12px;
            font-style: italic;
            color: #333;
            margin-bottom: 6px;
            padding: 4px 6px;
            background: #f8f9fa;
            border-radius: 4px;
        }

        .meses-cobrados {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            border-radius: 4px;
            padding: 6px 10px;
            margin-top: 6px;
            margin-bottom: 6px;
        }

        .meses-cobrados-title {
            font-size: 12px;
            font-weight: bold;
            color: #155724;
            margin-bottom: 4px;
        }

        .meses-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
        }

        .mes-badge {
            background: #28a745;
            color: white;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: bold;
        }

        .recibo-footer {
            margin-top: 8px;
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            color: #666;
        }

        .recibo-metodo {
            background: #f8f9fa;
            padding: 3px 8px;
            border-radius: 4px;
            display: inline-block;
            font-size: 12px;
        }

        .separator {
            border-top: 2px dashed #ccc;
            margin: 15px 0;
            position: relative;
        }

        .separator::before {
            content: 'CORTAR AQUI';
            position: absolute;
            top: -8px;
            left: 50%;
            transform: translateX(-50%);
            background: white;
            padding: 0 10px;
            color: #999;
            font-size: 10px;
        }

        .btn-print {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
        }

        .notas-recibo {
            font-size: 12px;
            margin-top: 4px;
        }

        .fecha-recibo {
            font-size: 13px;
            margin-top: 3px;
        }
    </style>
</head>
<body>

<div class="no-print btn-print">
    <button onclick="window.print()" class="btn btn-primary btn-lg">
        <i class="bi bi-printer me-2"></i> Imprimir
    </button>
    <a href="<?= BASE_URL ?>/modules/socios/ver.php?id=<?= $pago['socio_id'] ?>" class="btn btn-secondary btn-lg">
        <i class="bi bi-arrow-left me-2"></i> Volver
    </a>
</div>

<div class="recibo-container">
    <?php for ($i = 0; $i < 2; $i++): // Imprimir 2 comprobantes ?>

    <div class="recibo">
        <div class="recibo-header">
            <div class="recibo-logo">
                <img src="<?= defined("LOGO_BASE64") ? LOGO_BASE64 : BASE_URL."/assets/img/logo.png" ?>" alt="Logo"
                     onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><circle cx=%2250%22 cy=%2250%22 r=%2245%22 fill=%22%23079FEA%22/><text x=%2250%22 y=%2265%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2240%22>VN</text></svg>'">
                <div>
                    <p class="recibo-title"><?= htmlspecialchars($config['nombre_cooperativa']) ?></p>
                    <p class="recibo-subtitle"><?= htmlspecialchars($config['direccion']) ?></p>
                    <?php if ($config['telefono']): ?>
                    <p class="recibo-subtitle">Tel: <?= htmlspecialchars($config['telefono']) ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="text-end">
                <div class="recibo-number">COMPROBANTE N° <?= htmlspecialchars($pago['numero_recibo']) ?></div>
                <div class="fecha-recibo"><?= formatDate($pago['fecha_pago'], 'd/m/Y H:i') ?></div>
            </div>
        </div>

        <div class="recibo-info">
            <?php if ($accion): ?>
            <div class="recibo-info-item">
                <div class="recibo-info-label">Acción N°</div>
                <div class="recibo-info-value"><?= htmlspecialchars($accion['numero_accion']) ?></div>
            </div>
            <?php else: ?>
            <div class="recibo-info-item">
                <div class="recibo-info-label">Socio N°</div>
                <div class="recibo-info-value"><?= htmlspecialchars($pago['numero_socio']) ?></div>
            </div>
            <?php endif; ?>

            <div class="recibo-info-item">
                <div class="recibo-info-label">Nombre</div>
                <div class="recibo-info-value"><?= htmlspecialchars($pago['socio_nombre']) ?></div>
            </div>

            <?php if ($accion && $accion['zona_nombre']): ?>
            <div class="recibo-info-item">
                <div class="recibo-info-label">Zona</div>
                <div class="recibo-info-value"><?= htmlspecialchars($accion['zona_nombre']) ?></div>
            </div>
            <?php endif; ?>
        </div>

        <table class="recibo-table">
            <thead>
                <tr>
                    <th>Descripción</th>
                    <th>Periodo</th>
                    <th class="text-end">Monto</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($consumos_pagados)):
                    $total_consumos = array_sum(array_column($consumos_pagados, 'monto'));
                    $num_meses = count($consumos_pagados);
                    $primer = $consumos_pagados[0];
                    $ultimo = $consumos_pagados[$num_meses - 1];

                    if ($num_meses == 1) {
                        $periodo_str = getNombreMesCorto($primer['mes']) . ' ' . $primer['anio'];
                    } elseif ($primer['anio'] == $ultimo['anio']) {
                        $periodo_str = getNombreMesCorto($primer['mes']) . '-' . getNombreMesCorto($ultimo['mes']) . ' ' . $primer['anio'];
                    } else {
                        $periodo_str = getNombreMesCorto($primer['mes']) . '/' . substr($primer['anio'], 2) . '-' . getNombreMesCorto($ultimo['mes']) . '/' . substr($ultimo['anio'], 2);
                    }
                ?>
                <tr>
                    <td>Consumo de agua (<?= $num_meses ?> mes<?= $num_meses > 1 ? 'es' : '' ?>)</td>
                    <td><?= $periodo_str ?></td>
                    <td class="text-end"><?= formatMoney($total_consumos) ?></td>
                </tr>
                <?php endif; ?>

                <?php foreach ($detalles['servicios'] as $servicio): ?>
                <tr>
                    <td><?= htmlspecialchars($servicio['tipo_nombre']) ?></td>
                    <td><?= isset($servicio['periodo']) ? formatPeriodo($servicio['periodo']) : '-' ?></td>
                    <td class="text-end"><?= formatMoney($servicio['monto']) ?></td>
                </tr>
                <?php endforeach; ?>

                <?php foreach ($detalles['items'] as $item): ?>
                <tr>
                    <td colspan="2"><?= htmlspecialchars($item['nombre_item']) ?></td>
                    <td class="text-end"><?= formatMoney($item['monto']) ?></td>
                </tr>
                <?php endforeach; ?>

                <?php foreach ($otros_ingresos as $otro): ?>
                <tr>
                    <td><?= htmlspecialchars($otro["tipo_nombre"] ?: $otro["concepto"]) ?></td>
                    <td>-</td>
                    <td class="text-end"><?= formatMoney($otro["monto"]) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="recibo-total">
            <div>TOTAL PAGADO</div>
            <div class="recibo-total-value"><?= formatMoney($pago['monto_total']) ?></div>
        </div>

        <div class="monto-literal">
            <strong>Son:</strong> <?= $monto_literal ?>
        </div>

        <?php if (!empty($consumos_pagados)): ?>
        <div class="meses-cobrados">
            <div class="meses-cobrados-title">MESES COBRADOS:</div>
            <div class="meses-grid">
                <?php foreach ($consumos_pagados as $consumo): ?>
                <span class="mes-badge"><?= getNombreMesCorto($consumo['mes']) ?> <?= substr($consumo['anio'], 2) ?></span>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="recibo-metodo">
            <strong>Método:</strong>
            <?= $pago['metodo_pago'] === 'efectivo' ? 'Efectivo' : 'QR / Transferencia' ?>
        </div>

        <?php if ($pago['notas']): ?>
        <div class="notas-recibo">
            <strong>Notas:</strong> <?= htmlspecialchars($pago['notas']) ?>
        </div>
        <?php endif; ?>

        <div class="recibo-footer">
            <div>
                <strong>Cooperativa "Virgen de las Nieves"</strong><br>
                Irupana, Sud Yungas, La Paz
            </div>
            <div class="text-end">
                _______________________<br>
                Firma/Sello
            </div>
        </div>
    </div>

    <?php if ($i === 0): ?>
    <div class="separator no-print"></div>
    <?php endif; ?>

    <?php endfor; ?>
</div>

</body>
</html>
