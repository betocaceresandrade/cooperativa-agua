<?php
/**
 * Lista de Gastos (Comprobantes de Egreso)
 */

$pageTitle = 'Gastos';
require_once dirname(dirname(__DIR__)) . '/includes/header.php';
require_once dirname(dirname(__DIR__)) . '/includes/functions.php';

$categorias = getCategoriasGasto();

// Filtros
$mes = $_GET['mes'] ?? date('Y-m');
$categoria_id = $_GET['categoria_id'] ?? '';
$buscar = trim($_GET['buscar'] ?? '');

$sql = "SELECT g.*, c.nombre as categoria_nombre
        FROM gastos g
        JOIN categorias_gasto c ON g.categoria_id = c.id
        WHERE DATE_FORMAT(g.fecha, '%Y-%m') = ?";
$params = [$mes];

if ($categoria_id) {
    $sql .= " AND g.categoria_id = ?";
    $params[] = $categoria_id;
}

if (!empty($buscar)) {
    $sql .= " AND (g.concepto LIKE ? OR g.numero_recibo_proveedor LIKE ?)";
    $params[] = '%' . $buscar . '%';
    $params[] = '%' . $buscar . '%';
}

$sql .= " ORDER BY g.fecha DESC, g.id DESC";
$gastos = fetchAll($sql, $params);

// Totales del mes
$totales = fetchOne(
    "SELECT
        SUM(CASE WHEN metodo_pago = 'efectivo' THEN monto ELSE 0 END) as efectivo,
        SUM(CASE WHEN metodo_pago = 'qr' THEN monto ELSE 0 END) as qr,
        SUM(monto) as total
     FROM gastos
     WHERE DATE_FORMAT(fecha, '%Y-%m') = ?",
    [$mes]
);
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1 class="page-title">Registro de Gastos</h1>
        <p class="page-subtitle">Comprobantes de Egreso <a href="<?= BASE_URL ?>/ayuda.php#gastos" class="ms-2 text-info" title="Ver ayuda"><i class="bi bi-question-circle-fill"></i></a></p>
    </div>
    <a href="<?= BASE_URL ?>/modules/gastos/crear.php" class="btn btn-primary">
        <i class="bi bi-plus-circle me-1"></i> Nuevo Gasto
    </a>
</div>

<!-- Resumen del Mes -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card bg-danger text-white">
            <div class="card-body text-center">
                <div class="fs-3 fw-bold"><?= formatMoney($totales['total'] ?? 0) ?></div>
                <small>Total Gastos del Mes</small>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-body text-center">
                <div class="fs-3 fw-bold text-success"><?= formatMoney($totales['efectivo'] ?? 0) ?></div>
                <small class="text-muted">En Efectivo</small>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-body text-center">
                <div class="fs-3 fw-bold text-primary"><?= formatMoney($totales['qr'] ?? 0) ?></div>
                <small class="text-muted">Por QR</small>
            </div>
        </div>
    </div>
</div>

<!-- Filtros -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Buscar</label>
                <input type="text" class="form-control" name="buscar"
                       placeholder="Concepto o N° recibo..."
                       value="<?= htmlspecialchars($buscar) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Mes</label>
                <input type="month" class="form-control" name="mes" value="<?= htmlspecialchars($mes) ?>">
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
            <div class="col-md-4">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-search"></i> Filtrar
                </button>
                <a href="<?= BASE_URL ?>/modules/gastos/" class="btn btn-outline-secondary">
                    <i class="bi bi-x-circle"></i>
                </a>
                <button type="button" onclick="window.print()" class="btn btn-outline-primary">
                    <i class="bi bi-printer"></i> Imprimir
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Lista de Gastos -->
<div class="card">
    <div class="card-header">
        <i class="bi bi-list-ul me-2"></i>Gastos del Mes (<?= count($gastos) ?>)
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Categoría</th>
                        <th>Concepto</th>
                        <th>N° Recibo Prov.</th>
                        <th class="text-center">Método</th>
                        <th class="text-end">Monto</th>
                        <th class="text-center no-print">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($gastos)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">
                            No hay gastos registrados en este período
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($gastos as $gasto): ?>
                    <tr>
                        <td><?= formatDate($gasto['fecha']) ?></td>
                        <td>
                            <span class="badge bg-secondary"><?= htmlspecialchars($gasto['categoria_nombre']) ?></span>
                        </td>
                        <td>
                            <?= htmlspecialchars($gasto['concepto']) ?>
                            <?php if ($gasto['fondo_rendir_id']): ?>
                            <br><small class="text-muted">(Fondo a rendir)</small>
                            <?php endif; ?>
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
                        <td class="text-end text-danger fw-bold"><?= formatMoney($gasto['monto']) ?></td>
                        <td class="text-center no-print">
                            <a href="<?= BASE_URL ?>/modules/gastos/imprimir.php?id=<?= $gasto['id'] ?>"
                               class="btn btn-sm btn-outline-primary" title="Imprimir Comprobante" target="_blank">
                                <i class="bi bi-printer"></i>
                            </a>
                            <a href="<?= BASE_URL ?>/modules/gastos/crear.php?id=<?= $gasto['id'] ?>"
                               class="btn btn-sm btn-outline-secondary" title="Editar">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <a href="<?= BASE_URL ?>/modules/gastos/eliminar.php?id=<?= $gasto['id'] ?>" class="btn btn-sm btn-outline-danger" title="Eliminar" onclick="return confirm('¿Eliminar este gasto?')"><i class="bi bi-trash"></i></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr class="table-danger">
                        <th colspan="5">TOTAL DEL MES</th>
                        <th class="text-end"><?= formatMoney($totales['total'] ?? 0) ?></th>
                        <th class="no-print"></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<style>
@media print {
    .no-print { display: none !important; }
    .card { border: 1px solid #ddd !important; }
}
</style>

<?php require_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
