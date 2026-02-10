<?php
/**
 * Comprobante de Egreso - Impresión
 * Tamaño: Media carta (2 copias)
 */

require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/config/config.php';
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_once dirname(dirname(__DIR__)) . '/includes/functions.php';

requireLogin();

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    die('ID de gasto no especificado');
}

$gasto = fetchOne(
    "SELECT g.*, c.nombre as categoria_nombre
     FROM gastos g
     JOIN categorias_gasto c ON g.categoria_id = c.id
     WHERE g.id = ?",
    [$id]
);

if (!$gasto) {
    die('Gasto no encontrado');
}

$config = getConfig();

// Función para convertir número a letras
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

$monto_literal = numeroALetras($gasto['monto']);
$numero_comprobante = str_pad($gasto['id'], 6, '0', STR_PAD_LEFT);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comprobante de Egreso #<?= $numero_comprobante ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        @media print {
            .no-print { display: none !important; }
            body { margin: 0; padding: 0; }
            .comprobante { page-break-after: always; }
        }

        @page {
            size: letter;
            margin: 10mm;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 14px;
            background: #f0f0f0;
            margin: 0;
            padding: 10px;
        }

        .comprobante-container {
            max-width: 216mm;
            margin: 0 auto;
            background: white;
        }

        .comprobante {
            border: 2px solid #dc3545;
            border-radius: 6px;
            padding: 12px;
            margin-bottom: 15px;
            height: 125mm;
            max-height: 125mm;
            overflow: hidden;
            box-sizing: border-box;
        }

        .comp-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #dc3545;
            padding-bottom: 6px;
            margin-bottom: 8px;
        }

        .comp-logo {
            display: flex;
            align-items: center;
        }

        .comp-logo img {
            width: 35px;
            height: 35px;
            margin-right: 8px;
        }

        .comp-title {
            color: #dc3545;
            font-size: 14px;
            font-weight: bold;
            margin: 0;
        }

        .comp-subtitle {
            color: #666;
            font-size: 12px;
            margin: 0;
        }

        .comp-number {
            background: #dc3545;
            color: white;
            padding: 4px 10px;
            border-radius: 10px;
            font-weight: bold;
            font-size: 14px;
            text-align: center;
        }

        .comp-number small {
            display: block;
            font-size: 12px;
            font-weight: normal;
        }

        .comp-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4px;
            margin-bottom: 8px;
        }

        .comp-info-item {
            display: flex;
            justify-content: space-between;
            padding: 3px 0;
            border-bottom: 1px dotted #ddd;
        }

        .comp-info-label {
            color: #666;
            font-size: 12px;
        }

        .comp-info-value {
            font-weight: bold;
            font-size: 13px;
        }

        .comp-concepto {
            background: #f8f9fa;
            border: 1px solid #eee;
            border-radius: 4px;
            padding: 8px;
            margin-bottom: 8px;
        }

        .comp-concepto-label {
            font-size: 11px;
            color: #666;
            text-transform: uppercase;
            margin-bottom: 2px;
        }

        .comp-concepto-text {
            font-size: 14px;
        }

        .comp-total {
            background: #dc3545;
            color: white;
            padding: 8px 10px;
            border-radius: 4px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 4px;
        }

        .comp-total-label {
            font-size: 14px;
        }

        .comp-total-value {
            font-size: 20px;
            font-weight: bold;
        }

        .comp-literal {
            font-size: 12px;
            font-style: italic;
            color: #333;
            background: #f8f9fa;
            padding: 3px 6px;
            border-radius: 3px;
            margin-bottom: 6px;
        }

        .comp-metodo {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
        }

        .metodo-efectivo {
            background: #28a745;
            color: white;
        }

        .metodo-qr {
            background: #007bff;
            color: white;
        }

        .comp-footer {
            margin-top: 10px;
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            color: #666;
        }

        .comp-firmas {
            display: flex;
            justify-content: space-around;
            margin-top: 15px;
        }

        .comp-firma {
            text-align: center;
            width: 80px;
        }

        .comp-firma-linea {
            border-top: 1px solid #333;
            padding-top: 3px;
            font-size: 11px;
        }

        .separator {
            border-top: 2px dashed #ccc;
            margin: 10px 0;
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
            font-size: 12px;
        }

        .btn-print {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
        }

        .btn {
            padding: 10px 20px;
            font-size: 14px;
            cursor: pointer;
            border: none;
            border-radius: 5px;
            margin: 5px;
            text-decoration: none;
            display: inline-block;
        }

        .btn-primary {
            background: #dc3545;
            color: white;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }
    </style>
</head>
<body>

<div class="no-print btn-print">
    <button onclick="window.print()" class="btn btn-primary">
        <i class="bi bi-printer me-2"></i> Imprimir
    </button>
    <a href="<?= BASE_URL ?>/modules/gastos/" class="btn btn-secondary">
        <i class="bi bi-arrow-left me-2"></i> Volver
    </a>
</div>

<div class="comprobante-container">
    <?php for ($i = 0; $i < 2; $i++): // Imprimir 2 comprobantes ?>

    <div class="comprobante">
        <div class="comp-header">
            <div class="comp-logo">
                <img src="<?= defined("LOGO_BASE64") ? LOGO_BASE64 : BASE_URL."/assets/img/logo.png" ?>" alt="Logo"
                     onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><circle cx=%2250%22 cy=%2250%22 r=%2245%22 fill=%22%23dc3545%22/><text x=%2250%22 y=%2265%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2240%22>VN</text></svg>'">
                <div>
                    <p class="comp-title"><?= htmlspecialchars($config['nombre_cooperativa']) ?></p>
                    <p class="comp-subtitle"><?= htmlspecialchars($config['direccion'] ?? 'Irupana - Sud Yungas - La Paz') ?></p>
                </div>
            </div>
            <div class="text-end">
                <div class="comp-number">
                    COMPROBANTE DE EGRESO
                    <small>N° <?= $numero_comprobante ?></small>
                </div>
                <div style="font-size: 13px; margin-top: 4px;"><?= formatDate($gasto['fecha'], 'd/m/Y') ?></div>
            </div>
        </div>

        <div class="comp-info">
            <div class="comp-info-item">
                <span class="comp-info-label">Categoría:</span>
                <span class="comp-info-value"><?= htmlspecialchars($gasto['categoria_nombre']) ?></span>
            </div>
            <div class="comp-info-item">
                <span class="comp-info-label">Método:</span>
                <span class="comp-metodo <?= $gasto['metodo_pago'] === 'efectivo' ? 'metodo-efectivo' : 'metodo-qr' ?>">
                    <?= $gasto['metodo_pago'] === 'efectivo' ? 'EFECTIVO' : 'QR' ?>
                </span>
            </div>
            <?php if ($gasto['numero_recibo_proveedor']): ?>
            <div class="comp-info-item" style="grid-column: span 2;">
                <span class="comp-info-label">N° Recibo/Factura Proveedor:</span>
                <span class="comp-info-value"><?= htmlspecialchars($gasto['numero_recibo_proveedor']) ?></span>
            </div>
            <?php endif; ?>
        </div>

        <div class="comp-concepto">
            <div class="comp-concepto-label">Concepto:</div>
            <div class="comp-concepto-text"><?= htmlspecialchars($gasto['concepto']) ?></div>
            <?php if ($gasto['notas']): ?>
            <div style="margin-top: 4px; font-size: 12px; color: #666;">
                <strong>Notas:</strong> <?= htmlspecialchars($gasto['notas']) ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="comp-total">
            <span class="comp-total-label">TOTAL EGRESO</span>
            <span class="comp-total-value"><?= formatMoney($gasto['monto']) ?></span>
        </div>

        <div class="comp-literal">
            <strong>Son:</strong> <?= $monto_literal ?>
        </div>

        <div class="comp-firmas">
            <div class="comp-firma">
                <div class="comp-firma-linea">Entregado por</div>
            </div>
            <div class="comp-firma">
                <div class="comp-firma-linea">Recibido por</div>
            </div>
            <div class="comp-firma">
                <div class="comp-firma-linea">Autorizado</div>
            </div>
        </div>

        <div class="comp-footer">
            <span><?= htmlspecialchars($config['nombre_cooperativa']) ?></span>
            <span><?= $i === 0 ? 'ORIGINAL' : 'COPIA' ?></span>
        </div>
    </div>

    <?php if ($i === 0): ?>
    <div class="separator no-print"></div>
    <?php endif; ?>

    <?php endfor; ?>
</div>

</body>
</html>
