<?php
/**
 * Historial de Ingresos (Pagos/Recibos)
 * Lista todos los pagos realizados con opciones de reimprimir y anular
 */

$pageTitle = 'Historial de Ingresos';
require_once dirname(dirname(__DIR__)) . '/includes/header.php';
require_once dirname(dirname(__DIR__)) . '/includes/functions.php';

// Filtros
$mes = $_GET['mes'] ?? date('Y-m');
$buscar = trim($_GET['buscar'] ?? '');
$metodo = $_GET['metodo'] ?? '';
$estado = $_GET['estado'] ?? '';

// Construir consulta
$sql = "SELECT p.*, s.nombre as socio_nombre, s.numero_socio, a.numero_accion,
               (SELECT COUNT(*) FROM pago_consumos pc WHERE pc.pago_id = p.id) as num_meses
        FROM pagos p
        JOIN socios s ON p.socio_id = s.id
        LEFT JOIN acciones a ON p.accion_id = a.id
        WHERE DATE_FORMAT(p.fecha_pago, '%Y-%m') = ?";
$params = [$mes];

if (!empty($buscar)) {
    $sql .= " AND (s.nombre LIKE ? OR s.numero_socio LIKE ? OR p.numero_recibo LIKE ? OR a.numero_accion LIKE ?)";
    $params[] = '%' . $buscar . '%';
    $params[] = '%' . $buscar . '%';
    $params[] = '%' . $buscar . '%';
    $params[] = '%' . $buscar . '%';
}

if (!empty($metodo)) {
    $sql .= " AND p.metodo_pago = ?";
    $params[] = $metodo;
}

if ($estado === 'anulado') {
    $sql .= " AND p.anulado = 1";
} elseif ($estado === 'vigente') {
    $sql .= " AND (p.anulado = 0 OR p.anulado IS NULL)";
}

$sql .= " ORDER BY p.fecha_pago DESC, p.id DESC";
$pagos = fetchAll($sql, $params);

// Totales del mes (solo vigentes)
$totales = fetchOne(
    "SELECT
        SUM(CASE WHEN metodo_pago = 'efectivo' AND (anulado = 0 OR anulado IS NULL) THEN monto_total ELSE 0 END) as efectivo,
        SUM(CASE WHEN metodo_pago = 'qr' AND (anulado = 0 OR anulado IS NULL) THEN monto_total ELSE 0 END) as qr,
        SUM(CASE WHEN (anulado = 0 OR anulado IS NULL) THEN monto_total ELSE 0 END) as total,
        COUNT(CASE WHEN (anulado = 0 OR anulado IS NULL) THEN 1 END) as num_pagos,
        COUNT(CASE WHEN anulado = 1 THEN 1 END) as num_anulados
     FROM pagos
     WHERE DATE_FORMAT(fecha_pago, '%Y-%m') = ?",
    [$mes]
);
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1 class="page-title"><i class="bi bi-receipt me-2"></i>Historial de Ingresos</h1>
        <p class="page-subtitle">Comprobantes de Ingreso - Recibos de Pago <a href="<?= BASE_URL ?>/ayuda.php#ingresos" class="ms-2 text-info" title="Ver ayuda"><i class="bi bi-question-circle-fill"></i></a></p>
    </div>
</div>

<!-- Resumen del Mes -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card bg-success text-white">
            <div class="card-body text-center py-3">
                <div class="fs-3 fw-bold"><?= formatMoney($totales['total'] ?? 0) ?></div>
                <small>Total Ingresos del Mes</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body text-center py-3">
                <div class="fs-4 fw-bold text-success"><?= formatMoney($totales['efectivo'] ?? 0) ?></div>
                <small class="text-muted">En Efectivo</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body text-center py-3">
                <div class="fs-4 fw-bold text-primary"><?= formatMoney($totales['qr'] ?? 0) ?></div>
                <small class="text-muted">Por QR</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body text-center py-3">
                <div class="fs-4 fw-bold"><?= $totales['num_pagos'] ?? 0 ?></div>
                <small class="text-muted">Recibos Emitidos</small>
                <?php if (($totales['num_anulados'] ?? 0) > 0): ?>
                <br><small class="text-danger">(<?= $totales['num_anulados'] ?> anulados)</small>
                <?php endif; ?>
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
                       placeholder="Socio, N° recibo, acción..."
                       value="<?= htmlspecialchars($buscar) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Mes</label>
                <input type="month" class="form-control" name="mes" value="<?= htmlspecialchars($mes) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Método</label>
                <select class="form-select" name="metodo">
                    <option value="">Todos</option>
                    <option value="efectivo" <?= $metodo === 'efectivo' ? 'selected' : '' ?>>Efectivo</option>
                    <option value="qr" <?= $metodo === 'qr' ? 'selected' : '' ?>>QR</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Estado</label>
                <select class="form-select" name="estado">
                    <option value="">Todos</option>
                    <option value="vigente" <?= $estado === 'vigente' ? 'selected' : '' ?>>Vigentes</option>
                    <option value="anulado" <?= $estado === 'anulado' ? 'selected' : '' ?>>Anulados</option>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-search"></i> Filtrar
                </button>
                <a href="<?= BASE_URL ?>/modules/ingresos/" class="btn btn-outline-secondary">
                    <i class="bi bi-x-circle"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Lista de Pagos -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-list-ul me-2"></i>Pagos del Mes (<?= count($pagos) ?>)</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>N° Recibo</th>
                        <th>Fecha</th>
                        <th>Socio</th>
                        <th>Acción</th>
                        <th class="text-center">Meses</th>
                        <th class="text-center">Método</th>
                        <th class="text-end">Monto</th>
                        <th class="text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($pagos)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-4 text-muted">
                            No hay pagos registrados en este período
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($pagos as $pago): ?>
                    <tr class="<?= ($pago['anulado'] ?? 0) ? 'table-danger text-decoration-line-through' : '' ?>">
                        <td>
                            <strong>#<?= htmlspecialchars($pago['numero_recibo']) ?></strong>
                            <?php if ($pago['anulado'] ?? 0): ?>
                            <br><span class="badge bg-danger">ANULADO</span>
                            <?php endif; ?>
                        </td>
                        <td><?= formatDate($pago['fecha_pago'], 'd/m/Y H:i') ?></td>
                        <td>
                            <a href="<?= BASE_URL ?>/modules/socios/ver.php?id=<?= $pago['socio_id'] ?>" class="text-decoration-none">
                                <?= htmlspecialchars($pago['socio_nombre']) ?>
                            </a>
                            <br><small class="text-muted">#<?= htmlspecialchars($pago['numero_socio']) ?></small>
                        </td>
                        <td><?= htmlspecialchars($pago['numero_accion'] ?? '-') ?></td>
                        <td class="text-center">
                            <?php if ($pago['num_meses'] > 0): ?>
                            <span class="badge bg-secondary"><?= $pago['num_meses'] ?></span>
                            <?php else: ?>
                            <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php if ($pago['metodo_pago'] === 'efectivo'): ?>
                            <span class="badge bg-success">Efectivo</span>
                            <?php else: ?>
                            <span class="badge bg-primary">QR</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end fw-bold <?= ($pago['anulado'] ?? 0) ? '' : 'text-success' ?>">
                            <?= formatMoney($pago['monto_total']) ?>
                        </td>
                        <td class="text-center">
                            <div class="btn-group btn-group-sm">
                                <a href="<?= BASE_URL ?>/modules/recibos/imprimir.php?id=<?= $pago['id'] ?>"
                                   class="btn btn-outline-primary" target="_blank" title="Reimprimir">
                                    <i class="bi bi-printer"></i>
                                </a>
                                <?php if (!($pago['anulado'] ?? 0)): ?>
                                <a href="<?= BASE_URL ?>/modules/ingresos/anular.php?id=<?= $pago['id'] ?>"
                                   class="btn btn-outline-danger" title="Anular"
                                   onclick="return confirm('¿Está seguro de anular este pago #<?= $pago['numero_recibo'] ?>?\n\nEsta acción revertirá los meses pagados a estado pendiente.')">
                                    <i class="bi bi-x-circle"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <?php if (!empty($pagos)): ?>
                <tfoot>
                    <tr class="table-success">
                        <th colspan="6">TOTAL VIGENTE DEL MES</th>
                        <th class="text-end"><?= formatMoney($totales['total'] ?? 0) ?></th>
                        <th></th>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>

<?php require_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
