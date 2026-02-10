<?php
/**
 * Reporte: Estado de Resultados
 * Formato formal estilo estado financiero
 */

$pageTitle = 'Estado de Resultados';
require_once dirname(dirname(__DIR__)) . '/includes/header.php';
require_once dirname(dirname(__DIR__)) . '/includes/functions.php';

$config = getConfig();

// Período por defecto: mes actual
$mes_inicio = $_GET['mes_inicio'] ?? date('Y-m');
$mes_fin = $_GET['mes_fin'] ?? date('Y-m');

$fecha_inicio = $mes_inicio . '-01';
$fecha_fin = date('Y-m-t', strtotime($mes_fin . '-01'));

// ==================== INGRESOS ====================

// Ingresos por consumo de agua (de pagos de consumos_anuales)
$ingresoConsumo = fetchOne(
    "SELECT COALESCE(SUM(ca.monto), 0) as total
     FROM pago_consumos pc
     JOIN consumos_anuales ca ON pc.consumo_anual_id = ca.id
     JOIN pagos p ON pc.pago_id = p.id
     WHERE p.fecha_pago BETWEEN ? AND ? AND (p.anulado = 0 OR p.anulado IS NULL)",
    [$fecha_inicio, $fecha_fin . ' 23:59:59']
)['total'] ?? 0;

// Otros ingresos (reconexiones, multas, etc.)
$otrosIngresos = fetchAll(
    "SELECT toi.nombre, COALESCE(SUM(oi.monto), 0) as total
     FROM otros_ingresos oi
     JOIN tipos_otros_ingresos toi ON oi.tipo_id = toi.id
     JOIN pagos p ON oi.pago_id = p.id
     WHERE oi.estado = 'pagado'
     AND p.fecha_pago BETWEEN ? AND ?
     AND (p.anulado = 0 OR p.anulado IS NULL)
     GROUP BY oi.tipo_id
     ORDER BY total DESC",
    [$fecha_inicio, $fecha_fin . ' 23:59:59']
);

$totalOtrosIngresos = array_sum(array_column($otrosIngresos, 'total'));
$totalIngresos = $ingresoConsumo + $totalOtrosIngresos;

// ==================== GASTOS ====================

// Gastos por categoría con detalle
$categorias = fetchAll(
    "SELECT c.id, c.nombre
     FROM categorias_gasto c
     ORDER BY c.nombre"
);

$gastosDetalle = [];
$totalGastos = 0;

foreach ($categorias as $cat) {
    $gastosCat = fetchAll(
        "SELECT g.fecha, g.concepto, g.monto, g.numero_recibo_proveedor, g.metodo_pago
         FROM gastos g
         WHERE g.categoria_id = ? AND g.fecha BETWEEN ? AND ?
         ORDER BY g.fecha",
        [$cat['id'], $fecha_inicio, $fecha_fin]
    );

    if (!empty($gastosCat)) {
        $subtotal = array_sum(array_column($gastosCat, 'monto'));
        $gastosDetalle[] = [
            'categoria' => $cat['nombre'],
            'items' => $gastosCat,
            'subtotal' => $subtotal
        ];
        $totalGastos += $subtotal;
    }
}

// ==================== FONDOS/SALIDAS DE CAJA ====================

// Fondos entregados (adelantos y entregas)
$fondosEntregados = fetchAll(
    "SELECT f.*, 
            CASE f.tipo 
                WHEN 'adelanto' THEN 'Adelanto' 
                WHEN 'entrega' THEN 'Entrega' 
                WHEN 'deposito' THEN 'Depósito' 
            END as tipo_texto
     FROM fondos_rendir f
     WHERE f.fecha_entrega BETWEEN ? AND ?
     ORDER BY f.fecha_entrega",
    [$fecha_inicio, $fecha_fin]
);

$totalFondosEntregados = array_sum(array_column($fondosEntregados, 'monto'));
$totalFondosRendidos = array_sum(array_column($fondosEntregados, 'monto_rendido'));
$totalFondosPendientes = $totalFondosEntregados - $totalFondosRendidos;

// Resultado del período (ingresos - gastos - fondos pendientes)
$resultado = $totalIngresos - $totalGastos;
$resultadoConFondos = $resultado - $totalFondosPendientes;

// Formatear período para mostrar
$periodoTexto = ($mes_inicio === $mes_fin)
    ? formatPeriodo($mes_inicio)
    : formatPeriodo($mes_inicio) . ' al ' . formatPeriodo($mes_fin);
?>

<style>
@media print {
    .no-print { display: none !important; }
    body { font-size: 10px; }
    .estado-resultados {
        max-width: 100%;
        border: none !important;
        box-shadow: none !important;
    }
    .page-header { margin-bottom: 10px !important; }
}

.estado-resultados {
    background: white;
    border: 1px solid #ddd;
    border-radius: 8px;
    overflow: hidden;
}

.er-header {
    background: linear-gradient(135deg, #079FEA 0%, #0056b3 100%);
    color: white;
    padding: 20px;
    text-align: center;
}

.er-header h2 {
    margin: 0;
    font-size: 14px;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.er-header h1 {
    margin: 10px 0 5px;
    font-size: 20px;
}

.er-header .periodo {
    font-size: 12px;
    opacity: 0.9;
}

.er-body {
    padding: 20px;
}

.er-section {
    margin-bottom: 20px;
}

.er-section-title {
    background: #f8f9fa;
    padding: 8px 12px;
    font-weight: bold;
    font-size: 12px;
    text-transform: uppercase;
    border-left: 4px solid #079FEA;
    margin-bottom: 10px;
}

.er-section-title.ingresos { border-left-color: #28a745; }
.er-section-title.gastos { border-left-color: #dc3545; }

.er-table {
    width: 100%;
    font-size: 11px;
    border-collapse: collapse;
}

.er-table td, .er-table th {
    padding: 6px 8px;
    border-bottom: 1px solid #eee;
}

.er-table .item-row td {
    padding-left: 25px;
}

.er-table .categoria-row {
    background: #f8f9fa;
    font-weight: bold;
}

.er-table .subtotal-row {
    border-top: 1px solid #ddd;
    font-weight: bold;
}

.er-table .total-section {
    background: #e9ecef;
    font-weight: bold;
    font-size: 12px;
}

.er-table .gran-total {
    font-size: 14px;
    font-weight: bold;
}

.er-table .resultado-positivo {
    background: #d4edda;
    color: #155724;
}

.er-table .resultado-negativo {
    background: #f8d7da;
    color: #721c24;
}

.er-table .text-end { text-align: right; }
.er-table .text-muted { color: #6c757d; }

.detalle-gasto {
    margin-bottom: 15px;
    border: 1px solid #eee;
    border-radius: 4px;
    overflow: hidden;
}

.detalle-gasto-header {
    background: #fff3cd;
    padding: 8px 12px;
    font-weight: bold;
    display: flex;
    justify-content: space-between;
    border-bottom: 1px solid #eee;
}

.detalle-gasto-body {
    padding: 0;
}

.detalle-gasto-table {
    width: 100%;
    font-size: 10px;
    border-collapse: collapse;
}

.detalle-gasto-table th {
    background: #f8f9fa;
    padding: 5px 8px;
    text-align: left;
    font-size: 9px;
    text-transform: uppercase;
}

.detalle-gasto-table td {
    padding: 5px 8px;
    border-bottom: 1px solid #f0f0f0;
}

.detalle-gasto-table tr:last-child td {
    border-bottom: none;
}

.er-footer {
    background: #f8f9fa;
    padding: 15px 20px;
    font-size: 9px;
    color: #666;
    border-top: 1px solid #ddd;
}

.er-firmas {
    display: flex;
    justify-content: space-around;
    margin-top: 40px;
    padding-top: 20px;
}

.er-firma {
    text-align: center;
    width: 200px;
}

.er-firma-linea {
    border-top: 1px solid #333;
    padding-top: 5px;
    font-size: 10px;
}
</style>

<div class="page-header d-flex justify-content-between align-items-center no-print">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/modules/reportes/">Reportes</a></li>
                <li class="breadcrumb-item active">Estado de Resultados</li>
            </ol>
        </nav>
        <h1 class="page-title">Estado de Resultados</h1>
    </div>
    <button onclick="window.print()" class="btn btn-primary">
        <i class="bi bi-printer me-1"></i> Imprimir
    </button>
</div>

<!-- Filtros -->
<div class="card mb-4 no-print">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Desde</label>
                <input type="month" class="form-control" name="mes_inicio" value="<?= htmlspecialchars($mes_inicio) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Hasta</label>
                <input type="month" class="form-control" name="mes_fin" value="<?= htmlspecialchars($mes_fin) ?>">
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-search"></i> Generar Reporte
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Estado de Resultados Formal -->
<div class="estado-resultados">
    <div class="er-header">
        <h2><?= htmlspecialchars($config['nombre_cooperativa']) ?></h2>
        <h1>ESTADO DE RESULTADOS</h1>
        <div class="periodo">Período: <?= $periodoTexto ?></div>
    </div>

    <div class="er-body">
        <!-- INGRESOS -->
        <div class="er-section">
            <div class="er-section-title ingresos">
                <i class="bi bi-plus-circle me-2"></i>INGRESOS
            </div>
            <table class="er-table">
                <tr>
                    <td style="padding-left: 15px;">Consumo de Agua</td>
                    <td class="text-end" width="150"><?= formatMoney($ingresoConsumo) ?></td>
                </tr>
                <?php if (!empty($otrosIngresos)): ?>
                    <?php foreach ($otrosIngresos as $oi): ?>
                    <tr>
                        <td style="padding-left: 15px;"><?= htmlspecialchars($oi['nombre']) ?></td>
                        <td class="text-end"><?= formatMoney($oi['total']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                <tr class="total-section">
                    <td><strong>TOTAL INGRESOS</strong></td>
                    <td class="text-end"><strong><?= formatMoney($totalIngresos) ?></strong></td>
                </tr>
            </table>
        </div>

        <!-- GASTOS -->
        <div class="er-section">
            <div class="er-section-title gastos">
                <i class="bi bi-dash-circle me-2"></i>GASTOS
            </div>

            <?php if (empty($gastosDetalle)): ?>
            <p class="text-muted text-center py-3">No hay gastos registrados en este período</p>
            <?php else: ?>

            <!-- Resumen por categoría -->
            <table class="er-table mb-3">
                <?php foreach ($gastosDetalle as $gd): ?>
                <tr>
                    <td style="padding-left: 15px;"><?= htmlspecialchars($gd['categoria']) ?></td>
                    <td class="text-end" width="150"><?= formatMoney($gd['subtotal']) ?></td>
                </tr>
                <?php endforeach; ?>
                <tr class="total-section">
                    <td><strong>TOTAL GASTOS</strong></td>
                    <td class="text-end"><strong><?= formatMoney($totalGastos) ?></strong></td>
                </tr>
            </table>

            <!-- Detalle de gastos por categoría -->
            <div class="mt-4 mb-3">
                <h6 class="text-muted"><i class="bi bi-list-ul me-2"></i>DETALLE DE GASTOS POR CATEGORÍA</h6>
            </div>

            <?php foreach ($gastosDetalle as $gd): ?>
            <div class="detalle-gasto">
                <div class="detalle-gasto-header">
                    <span><?= htmlspecialchars($gd['categoria']) ?></span>
                    <span><?= formatMoney($gd['subtotal']) ?></span>
                </div>
                <div class="detalle-gasto-body">
                    <table class="detalle-gasto-table">
                        <thead>
                            <tr>
                                <th width="80">Fecha</th>
                                <th>Concepto</th>
                                <th width="100">N° Doc.</th>
                                <th width="60">Método</th>
                                <th width="90" class="text-end">Monto</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($gd['items'] as $item): ?>
                            <tr>
                                <td><?= formatDate($item['fecha'], 'd/m/Y') ?></td>
                                <td><?= htmlspecialchars($item['concepto']) ?></td>
                                <td><?= htmlspecialchars($item['numero_recibo_proveedor'] ?: '-') ?></td>
                                <td>
                                    <span class="badge bg-<?= $item['metodo_pago'] === 'efectivo' ? 'success' : 'primary' ?>" style="font-size: 9px;">
                                        <?= $item['metodo_pago'] === 'efectivo' ? 'Efect.' : 'QR' ?>
                                    </span>
                                </td>
                                <td class="text-end"><?= formatMoney($item['monto']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endforeach; ?>

            <?php endif; ?>
        </div>

        <!-- FONDOS/SALIDAS DE CAJA -->
        <?php if (!empty($fondosEntregados)): ?>
        <div class="er-section">
            <div class="er-section-title" style="border-left-color: #6c757d;">
                <i class="bi bi-box-arrow-up-right me-2"></i>SALIDAS DE CAJA / FONDOS A RENDIR
            </div>
            
            <table class="er-table">
                <thead>
                    <tr style="background: #f8f9fa;">
                        <th>Fecha</th>
                        <th>Beneficiario</th>
                        <th>Concepto</th>
                        <th>Tipo</th>
                        <th class="text-end">Entregado</th>
                        <th class="text-end">Rendido</th>
                        <th class="text-end">Pendiente</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($fondosEntregados as $fondo): ?>
                    <tr>
                        <td><?= formatDate($fondo["fecha_entrega"], "d/m/Y") ?></td>
                        <td><?= htmlspecialchars($fondo["beneficiario"]) ?></td>
                        <td><?= htmlspecialchars($fondo["concepto"] ?: "-") ?></td>
                        <td><span class="badge bg-secondary"><?= $fondo["tipo_texto"] ?></span></td>
                        <td class="text-end"><?= formatMoney($fondo["monto"]) ?></td>
                        <td class="text-end text-success"><?= formatMoney($fondo["monto_rendido"]) ?></td>
                        <td class="text-end text-warning"><?= formatMoney($fondo["monto"] - $fondo["monto_rendido"]) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="total-section">
                        <td colspan="4"><strong>TOTALES</strong></td>
                        <td class="text-end"><strong><?= formatMoney($totalFondosEntregados) ?></strong></td>
                        <td class="text-end text-success"><strong><?= formatMoney($totalFondosRendidos) ?></strong></td>
                        <td class="text-end text-warning"><strong><?= formatMoney($totalFondosPendientes) ?></strong></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <?php endif; ?>

        <!-- RESULTADO -->
        <div class="er-section">
            <table class="er-table">
                <tr class="gran-total <?= $resultado >= 0 ? 'resultado-positivo' : 'resultado-negativo' ?>">
                    <td>
                        <strong><?= $resultado >= 0 ? 'SUPERÁVIT DEL PERÍODO' : 'DÉFICIT DEL PERÍODO' ?></strong>
                    </td>
                    <td class="text-end" width="150">
                        <strong style="font-size: 16px;"><?= formatMoney(abs($resultado)) ?></strong>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Firmas -->
        <div class="er-firmas">
            <div class="er-firma">
                <div class="er-firma-linea">Elaborado por</div>
            </div>
            <div class="er-firma">
                <div class="er-firma-linea">Revisado por</div>
            </div>
            <div class="er-firma">
                <div class="er-firma-linea">Aprobado por</div>
            </div>
        </div>
    </div>

    <div class="er-footer">
        <div class="d-flex justify-content-between">
            <span>Generado el <?= date('d/m/Y H:i') ?></span>
            <span><?= htmlspecialchars($config['nombre_cooperativa']) ?> - <?= htmlspecialchars($config['direccion'] ?? '') ?></span>
        </div>
    </div>
</div>

<?php require_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
