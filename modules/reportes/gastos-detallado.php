<?php
/**
 * Reporte: Gastos Detallado para Asambleas
 * Rendición de cuentas con desglose por categoría
 */

$pageTitle = 'Reporte de Gastos Detallado';
require_once dirname(dirname(__DIR__)) . '/includes/header.php';
require_once dirname(dirname(__DIR__)) . '/includes/functions.php';

$categorias = getCategoriasGasto();

// Filtros
$fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
$fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-d');
$categoria_id = $_GET['categoria_id'] ?? '';
$metodo_pago = $_GET['metodo_pago'] ?? '';

// Construir consulta
$sql = "SELECT g.*, c.nombre as categoria_nombre
        FROM gastos g
        JOIN categorias_gasto c ON g.categoria_id = c.id
        WHERE g.fecha BETWEEN ? AND ?";
$params = [$fecha_inicio, $fecha_fin];

if (!empty($categoria_id)) {
    $sql .= " AND g.categoria_id = ?";
    $params[] = $categoria_id;
}

if (!empty($metodo_pago)) {
    $sql .= " AND g.metodo_pago = ?";
    $params[] = $metodo_pago;
}

$sql .= " ORDER BY c.nombre, g.fecha, g.id";
$gastos = fetchAll($sql, $params);

// Agrupar por categoría
$gastosPorCategoria = [];
$totalGeneral = 0;
$totalEfectivo = 0;
$totalQR = 0;

foreach ($gastos as $gasto) {
    $cat = $gasto['categoria_nombre'];
    if (!isset($gastosPorCategoria[$cat])) {
        $gastosPorCategoria[$cat] = [
            'gastos' => [],
            'subtotal' => 0,
            'efectivo' => 0,
            'qr' => 0
        ];
    }
    $gastosPorCategoria[$cat]['gastos'][] = $gasto;
    $gastosPorCategoria[$cat]['subtotal'] += $gasto['monto'];

    if ($gasto['metodo_pago'] === 'efectivo') {
        $gastosPorCategoria[$cat]['efectivo'] += $gasto['monto'];
        $totalEfectivo += $gasto['monto'];
    } else {
        $gastosPorCategoria[$cat]['qr'] += $gasto['monto'];
        $totalQR += $gasto['monto'];
    }

    $totalGeneral += $gasto['monto'];
}

// Ordenar categorías por subtotal descendente
uasort($gastosPorCategoria, function($a, $b) {
    return $b['subtotal'] <=> $a['subtotal'];
});

// Formatear período para título
$periodoTexto = formatDate($fecha_inicio) . ' al ' . formatDate($fecha_fin);

$config = getConfig();
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/modules/reportes/">Reportes</a></li>
                <li class="breadcrumb-item active">Gastos Detallado</li>
            </ol>
        </nav>
        <h1 class="page-title">Reporte de Gastos Detallado</h1>
        <p class="page-subtitle">Para rendición de cuentas en asamblea</p>
    </div>
    <button onclick="window.print()" class="btn btn-primary no-print">
        <i class="bi bi-printer me-1"></i> Imprimir
    </button>
</div>

<!-- Filtros -->
<div class="card mb-4 no-print">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-2">
                <label class="form-label">Desde</label>
                <input type="date" class="form-control" name="fecha_inicio"
                       value="<?= htmlspecialchars($fecha_inicio) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Hasta</label>
                <input type="date" class="form-control" name="fecha_fin"
                       value="<?= htmlspecialchars($fecha_fin) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Categoría</label>
                <select class="form-select" name="categoria_id">
                    <option value="">Todas</option>
                    <?php foreach ($categorias as $cat): ?>
                    <option value="<?= $cat['id'] ?>" <?= $categoria_id == $cat['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat['nombre']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Método Pago</label>
                <select class="form-select" name="metodo_pago">
                    <option value="">Todos</option>
                    <option value="efectivo" <?= $metodo_pago === 'efectivo' ? 'selected' : '' ?>>Efectivo</option>
                    <option value="qr" <?= $metodo_pago === 'qr' ? 'selected' : '' ?>>QR</option>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-search"></i> Generar
                </button>
                <a href="<?= BASE_URL ?>/modules/reportes/gastos-detallado.php" class="btn btn-outline-secondary">
                    <i class="bi bi-x-circle"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Encabezado para impresión -->
<div class="print-header d-none d-print-block text-center mb-4">
    <h2><?= htmlspecialchars($config['nombre_cooperativa']) ?></h2>
    <p class="mb-1"><?= htmlspecialchars($config['direccion'] ?? 'Irupana - Sud Yungas - La Paz') ?></p>
    <h3 class="mt-3">INFORME DE GASTOS</h3>
    <p><strong>Período:</strong> <?= $periodoTexto ?></p>
</div>

<!-- Resumen General -->
<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="card bg-danger text-white">
            <div class="card-body text-center">
                <div class="fs-3 fw-bold"><?= formatMoney($totalGeneral) ?></div>
                <div>Total Gastos</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-success text-white">
            <div class="card-body text-center">
                <div class="fs-3 fw-bold"><?= formatMoney($totalEfectivo) ?></div>
                <div>En Efectivo</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-primary text-white">
            <div class="card-body text-center">
                <div class="fs-3 fw-bold"><?= formatMoney($totalQR) ?></div>
                <div>Por QR</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-secondary text-white">
            <div class="card-body text-center">
                <div class="fs-3 fw-bold"><?= count($gastos) ?></div>
                <div>Comprobantes</div>
            </div>
        </div>
    </div>
</div>

<!-- Resumen por Categoría -->
<div class="card mb-4">
    <div class="card-header">
        <i class="bi bi-pie-chart me-2"></i>Resumen por Categoría
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Categoría</th>
                        <th class="text-center">Cantidad</th>
                        <th class="text-end">Efectivo</th>
                        <th class="text-end">QR</th>
                        <th class="text-end">Subtotal</th>
                        <th class="text-center">%</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($gastosPorCategoria as $categoria => $datos): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($categoria) ?></strong></td>
                        <td class="text-center"><?= count($datos['gastos']) ?></td>
                        <td class="text-end text-success"><?= formatMoney($datos['efectivo']) ?></td>
                        <td class="text-end text-primary"><?= formatMoney($datos['qr']) ?></td>
                        <td class="text-end fw-bold text-danger"><?= formatMoney($datos['subtotal']) ?></td>
                        <td class="text-center">
                            <span class="badge bg-secondary">
                                <?= $totalGeneral > 0 ? number_format(($datos['subtotal'] / $totalGeneral) * 100, 1) : 0 ?>%
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="table-danger">
                        <th>TOTAL</th>
                        <th class="text-center"><?= count($gastos) ?></th>
                        <th class="text-end"><?= formatMoney($totalEfectivo) ?></th>
                        <th class="text-end"><?= formatMoney($totalQR) ?></th>
                        <th class="text-end"><?= formatMoney($totalGeneral) ?></th>
                        <th class="text-center">100%</th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<!-- Detalle por Categoría -->
<?php foreach ($gastosPorCategoria as $categoria => $datos): ?>
<div class="card mb-4">
    <div class="card-header bg-secondary text-white d-flex justify-content-between align-items-center">
        <span>
            <i class="bi bi-tag me-2"></i><?= htmlspecialchars($categoria) ?>
            <span class="badge bg-light text-dark ms-2"><?= count($datos['gastos']) ?> gastos</span>
        </span>
        <span class="fw-bold"><?= formatMoney($datos['subtotal']) ?></span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead>
                    <tr>
                        <th width="100">Fecha</th>
                        <th>Concepto</th>
                        <th>N° Recibo Prov.</th>
                        <th class="text-center" width="90">Método</th>
                        <th class="text-end" width="120">Monto</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($datos['gastos'] as $gasto): ?>
                    <tr>
                        <td><?= formatDate($gasto['fecha']) ?></td>
                        <td>
                            <?= htmlspecialchars($gasto['concepto']) ?>
                            <?php if ($gasto['notas']): ?>
                            <br><small class="text-muted"><?= htmlspecialchars($gasto['notas']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($gasto['numero_recibo_proveedor']): ?>
                            <code><?= htmlspecialchars($gasto['numero_recibo_proveedor']) ?></code>
                            <?php else: ?>
                            <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php if ($gasto['metodo_pago'] === 'efectivo'): ?>
                            <span class="badge bg-success">Efectivo</span>
                            <?php else: ?>
                            <span class="badge bg-primary">QR</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end text-danger"><?= formatMoney($gasto['monto']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="table-light">
                        <th colspan="4" class="text-end">Subtotal <?= htmlspecialchars($categoria) ?>:</th>
                        <th class="text-end text-danger"><?= formatMoney($datos['subtotal']) ?></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
<?php endforeach; ?>

<!-- Total General (para impresión) -->
<div class="card bg-danger text-white">
    <div class="card-body">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h4 class="mb-0">TOTAL GENERAL DE GASTOS</h4>
                <small>Período: <?= $periodoTexto ?></small>
            </div>
            <div class="col-md-6 text-end">
                <div class="fs-2 fw-bold"><?= formatMoney($totalGeneral) ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Pie de página para impresión -->
<div class="print-footer d-none d-print-block mt-5">
    <div class="row">
        <div class="col-6 text-center">
            <div style="border-top: 1px solid #333; padding-top: 5px; margin-top: 50px;">
                Elaborado por
            </div>
        </div>
        <div class="col-6 text-center">
            <div style="border-top: 1px solid #333; padding-top: 5px; margin-top: 50px;">
                Aprobado por
            </div>
        </div>
    </div>
    <div class="text-center mt-4">
        <small class="text-muted">Generado el <?= date('d/m/Y H:i') ?></small>
    </div>
</div>

<style>
@media print {
    .no-print { display: none !important; }
    .card {
        border: 1px solid #ddd !important;
        break-inside: avoid;
    }
    .card-header {
        background-color: #f8f9fa !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    .bg-danger, .bg-success, .bg-primary, .bg-secondary {
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    .table-danger {
        background-color: #f8d7da !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    body {
        font-size: 11px;
    }
    .page-header {
        display: none;
    }
    .print-header {
        display: block !important;
    }
}

@media screen {
    .print-header, .print-footer {
        display: none;
    }
}
</style>

<?php require_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
