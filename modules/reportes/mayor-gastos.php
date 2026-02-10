<?php
/**
 * Mayor Contable de Gastos
 * Libro de gastos estilo contable con saldos acumulados
 */

$pageTitle = 'Mayor de Gastos';
require_once dirname(dirname(__DIR__)) . '/includes/header.php';
require_once dirname(dirname(__DIR__)) . '/includes/functions.php';

$categorias = getCategoriasGasto();

// Filtros
$fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-01-01');
$fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-d');
$categoria_id = $_GET['categoria_id'] ?? '';

// Construir consulta
$sql = "SELECT g.*, c.nombre as categoria_nombre, c.id as cat_id
        FROM gastos g
        JOIN categorias_gasto c ON g.categoria_id = c.id
        WHERE g.fecha BETWEEN ? AND ?";
$params = [$fecha_inicio, $fecha_fin];

if (!empty($categoria_id)) {
    $sql .= " AND g.categoria_id = ?";
    $params[] = $categoria_id;
}

$sql .= " ORDER BY g.fecha ASC, g.id ASC";
$gastos = fetchAll($sql, $params);

// Calcular saldo anterior (gastos antes del período)
$sqlAnterior = "SELECT COALESCE(SUM(monto), 0) as total FROM gastos WHERE fecha < ?";
$paramsAnterior = [$fecha_inicio];
if (!empty($categoria_id)) {
    $sqlAnterior .= " AND categoria_id = ?";
    $paramsAnterior[] = $categoria_id;
}
$saldoAnterior = fetchOne($sqlAnterior, $paramsAnterior)['total'] ?? 0;

// Formatear período para título
$periodoTexto = formatDate($fecha_inicio) . ' al ' . formatDate($fecha_fin);
$config = getConfig();

// Calcular totales
$totalGastos = array_sum(array_column($gastos, 'monto'));
$saldoFinal = $saldoAnterior + $totalGastos;
?>

<div class="page-header d-flex justify-content-between align-items-center no-print">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/modules/reportes/">Reportes</a></li>
                <li class="breadcrumb-item active">Mayor de Gastos</li>
            </ol>
        </nav>
        <h1 class="page-title">Mayor Contable de Gastos</h1>
        <p class="page-subtitle">Libro diario de egresos con saldos acumulados</p>
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
                <input type="date" class="form-control" name="fecha_inicio"
                       value="<?= htmlspecialchars($fecha_inicio) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Hasta</label>
                <input type="date" class="form-control" name="fecha_fin"
                       value="<?= htmlspecialchars($fecha_fin) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Categoria</label>
                <select class="form-select" name="categoria_id">
                    <option value="">Todas las categorias</option>
                    <?php foreach ($categorias as $cat): ?>
                    <option value="<?= $cat['id'] ?>" <?= $categoria_id == $cat['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat['nombre']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-search me-1"></i> Generar
                </button>
                <a href="<?= BASE_URL ?>/modules/reportes/mayor-gastos.php" class="btn btn-outline-secondary">
                    <i class="bi bi-x-circle"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Encabezado para impresion -->
<div class="print-header text-center mb-4">
    <h2 style="margin-bottom: 5px;"><?= htmlspecialchars($config['nombre_cooperativa']) ?></h2>
    <p style="margin-bottom: 3px;"><?= htmlspecialchars($config['direccion'] ?? 'Irupana - Sud Yungas - La Paz') ?></p>
    <h3 style="margin-top: 15px; border-top: 2px solid #333; border-bottom: 2px solid #333; padding: 8px 0;">
        MAYOR CONTABLE DE GASTOS
    </h3>
    <p><strong>Periodo:</strong> <?= $periodoTexto ?></p>
    <?php if (!empty($categoria_id)): 
        $catSeleccionada = array_filter($categorias, fn($c) => $c['id'] == $categoria_id);
        $catNombre = !empty($catSeleccionada) ? reset($catSeleccionada)['nombre'] : '';
    ?>
    <p><strong>Categoria:</strong> <?= htmlspecialchars($catNombre) ?></p>
    <?php endif; ?>
</div>

<!-- Libro Mayor -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-bordered table-hover mb-0 mayor-table">
                <thead>
                    <tr class="table-dark">
                        <th width="50" class="text-center">N</th>
                        <th width="100">FECHA</th>
                        <th width="100">N DOC.</th>
                        <th>CATEGORIA</th>
                        <th>CONCEPTO / DETALLE</th>
                        <th width="120" class="text-end">DEBE (Bs)</th>
                        <th width="120" class="text-end">HABER (Bs)</th>
                        <th width="130" class="text-end">SALDO (Bs)</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Saldo Anterior -->
                    <tr class="table-secondary">
                        <td colspan="5" class="text-end"><strong>SALDO ANTERIOR (al <?= formatDate($fecha_inicio) ?>)</strong></td>
                        <td class="text-end">-</td>
                        <td class="text-end">-</td>
                        <td class="text-end"><strong><?= formatMoney($saldoAnterior) ?></strong></td>
                    </tr>

                    <?php 
                    $saldoAcumulado = $saldoAnterior;
                    $numero = 1;
                    $mesActual = '';
                    $subtotalMes = 0;
                    
                    foreach ($gastos as $index => $gasto): 
                        $mesGasto = date('Y-m', strtotime($gasto['fecha']));
                        
                        // Subtotal del mes anterior si cambio de mes
                        if ($mesActual != '' && $mesActual != $mesGasto):
                            $nombreMes = getNombreMes(date('n', strtotime($mesActual . '-01'))) . ' ' . date('Y', strtotime($mesActual . '-01'));
                    ?>
                    <tr class="table-info subtotal-mes">
                        <td colspan="5" class="text-end"><em>Subtotal <?= $nombreMes ?>:</em></td>
                        <td class="text-end"><strong><?= formatMoney($subtotalMes) ?></strong></td>
                        <td class="text-end">-</td>
                        <td class="text-end">-</td>
                    </tr>
                    <?php 
                            $subtotalMes = 0;
                        endif;
                        
                        if ($mesActual != $mesGasto) {
                            $mesActual = $mesGasto;
                        }
                        
                        $saldoAcumulado += $gasto['monto'];
                        $subtotalMes += $gasto['monto'];
                    ?>
                    <tr>
                        <td class="text-center"><?= $numero++ ?></td>
                        <td><?= formatDate($gasto['fecha']) ?></td>
                        <td>
                            <?php if ($gasto['numero_recibo_proveedor']): ?>
                            <code><?= htmlspecialchars($gasto['numero_recibo_proveedor']) ?></code>
                            <?php else: ?>
                            <span class="text-muted">S/N</span>
                            <?php endif; ?>
                        </td>
                        <td><small><?= htmlspecialchars($gasto['categoria_nombre']) ?></small></td>
                        <td>
                            <?= htmlspecialchars($gasto['concepto']) ?>
                            <?php if ($gasto['notas']): ?>
                            <br><small class="text-muted"><?= htmlspecialchars($gasto['notas']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td class="text-end text-danger"><?= formatMoney($gasto['monto']) ?></td>
                        <td class="text-end">-</td>
                        <td class="text-end"><?= formatMoney($saldoAcumulado) ?></td>
                    </tr>
                    <?php endforeach; ?>

                    <?php if (!empty($gastos) && $mesActual != ''): 
                        $nombreMes = getNombreMes(date('n', strtotime($mesActual . '-01'))) . ' ' . date('Y', strtotime($mesActual . '-01'));
                    ?>
                    <tr class="table-info subtotal-mes">
                        <td colspan="5" class="text-end"><em>Subtotal <?= $nombreMes ?>:</em></td>
                        <td class="text-end"><strong><?= formatMoney($subtotalMes) ?></strong></td>
                        <td class="text-end">-</td>
                        <td class="text-end">-</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr class="table-warning">
                        <td colspan="5" class="text-end"><strong>TOTAL GASTOS DEL PERIODO:</strong></td>
                        <td class="text-end"><strong><?= formatMoney($totalGastos) ?></strong></td>
                        <td class="text-end">-</td>
                        <td class="text-end">-</td>
                    </tr>
                    <tr class="table-dark">
                        <td colspan="5" class="text-end"><strong>SALDO ACUMULADO FINAL:</strong></td>
                        <td class="text-end">-</td>
                        <td class="text-end">-</td>
                        <td class="text-end"><strong class="fs-5"><?= formatMoney($saldoFinal) ?></strong></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<!-- Resumen por Categoria -->
<?php if (empty($categoria_id)): 
    $resumenCat = [];
    foreach ($gastos as $g) {
        $cat = $g['categoria_nombre'];
        if (!isset($resumenCat[$cat])) {
            $resumenCat[$cat] = ['total' => 0, 'cantidad' => 0];
        }
        $resumenCat[$cat]['total'] += $g['monto'];
        $resumenCat[$cat]['cantidad']++;
    }
    arsort($resumenCat);
?>
<div class="card mt-4">
    <div class="card-header">
        <i class="bi bi-bar-chart me-2"></i>Resumen por Categoria
    </div>
    <div class="card-body p-0">
        <table class="table table-sm mb-0">
            <thead>
                <tr>
                    <th>Categoria</th>
                    <th class="text-center">Cant.</th>
                    <th class="text-end">Total</th>
                    <th class="text-center">%</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($resumenCat as $cat => $datos): ?>
                <tr>
                    <td><?= htmlspecialchars($cat) ?></td>
                    <td class="text-center"><?= $datos['cantidad'] ?></td>
                    <td class="text-end"><?= formatMoney($datos['total']) ?></td>
                    <td class="text-center">
                        <?= $totalGastos > 0 ? number_format(($datos['total'] / $totalGastos) * 100, 1) : 0 ?>%
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="table-secondary">
                    <th>TOTAL</th>
                    <th class="text-center"><?= count($gastos) ?></th>
                    <th class="text-end"><?= formatMoney($totalGastos) ?></th>
                    <th class="text-center">100%</th>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Firmas para impresion -->
<div class="print-footer mt-5">
    <div class="row">
        <div class="col-4 text-center">
            <div class="firma-linea"></div>
            <p>Elaborado por</p>
        </div>
        <div class="col-4 text-center">
            <div class="firma-linea"></div>
            <p>Revisado por</p>
        </div>
        <div class="col-4 text-center">
            <div class="firma-linea"></div>
            <p>Aprobado por</p>
        </div>
    </div>
    <div class="text-center mt-3">
        <small>Generado el <?= date('d/m/Y H:i') ?></small>
    </div>
</div>

<style>
.mayor-table {
    font-size: 13px;
}

.mayor-table th, .mayor-table td {
    vertical-align: middle;
}

.subtotal-mes td {
    font-style: italic;
}

.firma-linea {
    border-top: 1px solid #333;
    margin-top: 60px;
    padding-top: 5px;
}

@media print {
    .no-print { display: none !important; }
    
    .print-header { display: block !important; }
    .print-footer { display: block !important; }
    
    body { font-size: 11px; }
    
    .mayor-table { font-size: 10px; }
    .mayor-table th, .mayor-table td { padding: 4px 6px !important; }
    
    .card {
        border: 1px solid #333 !important;
        box-shadow: none !important;
    }
    
    .card-header {
        background-color: #f0f0f0 !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    
    .table-dark {
        background-color: #333 !important;
        color: white !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    
    .table-dark th, .table-dark td {
        color: white !important;
    }
    
    .table-secondary, .table-info, .table-warning {
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    
    .page-header { display: none; }
    
    @page {
        size: letter landscape;
        margin: 10mm;
    }
}

@media screen {
    .print-header { display: none; }
    .print-footer { display: none; }
}
</style>

<?php require_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
