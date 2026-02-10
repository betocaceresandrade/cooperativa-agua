<?php
/**
 * Módulo de Caja
 */

$pageTitle = 'Caja';
require_once dirname(dirname(__DIR__)) . '/includes/header.php';
require_once dirname(dirname(__DIR__)) . '/includes/functions.php';

$saldos = getSaldosCaja();

// Filtros
$fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
$fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-d');
$tipo = $_GET['tipo'] ?? '';
$metodo = $_GET['metodo'] ?? '';

$sql = "SELECT * FROM movimientos_caja WHERE DATE(fecha) BETWEEN ? AND ?";
$params = [$fecha_inicio, $fecha_fin];

if ($tipo) {
    $sql .= " AND tipo = ?";
    $params[] = $tipo;
}

if ($metodo) {
    $sql .= " AND metodo = ?";
    $params[] = $metodo;
}

$sql .= " ORDER BY fecha DESC, id DESC LIMIT 200";
$movimientos = fetchAll($sql, $params);

// Totales del período
$totalesPeriodo = fetchOne(
    "SELECT
        SUM(CASE WHEN tipo = 'ingreso' THEN monto ELSE 0 END) as ingresos,
        SUM(CASE WHEN tipo = 'egreso' THEN monto ELSE 0 END) as egresos
     FROM movimientos_caja
     WHERE DATE(fecha) BETWEEN ? AND ?",
    [$fecha_inicio, $fecha_fin]
);
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1 class="page-title">Control de Caja</h1>
        <p class="page-subtitle">Movimientos de efectivo y QR <a href="<?= BASE_URL ?>/ayuda.php#caja" class="ms-2 text-info" title="Ver ayuda"><i class="bi bi-question-circle-fill"></i></a></p>
    </div>
    <div class="btn-group"><a href="<?= BASE_URL ?>/modules/caja/tesoreria.php" class="btn btn-outline-info"><i class="bi bi-clipboard-data me-1"></i>Tesorería</a><a href="<?= BASE_URL ?>/modules/fondos-rendir/" class="btn btn-primary">
        <i class="bi bi-box-arrow-up-right me-1"></i>Salidas de Caja</a></div>

</div>

<!-- Saldos Actuales -->
<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="card bg-success text-white">
            <div class="card-body text-center">
                <i class="bi bi-cash-stack fs-1 mb-2"></i>
                <div class="fs-2 fw-bold"><?= formatMoney($saldos['efectivo']) ?></div>
                <div>Saldo en Efectivo</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-primary text-white">
            <div class="card-body text-center">
                <i class="bi bi-qr-code fs-1 mb-2"></i>
                <div class="fs-2 fw-bold"><?= formatMoney($saldos['qr']) ?></div>
                <div>Saldo en QR</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-info text-white">
            <div class="card-body text-center">
                <i class="bi bi-wallet2 fs-1 mb-2"></i>
                <div class="fs-2 fw-bold"><?= formatMoney($saldos['efectivo'] + $saldos['qr']) ?></div>
                <div>Saldo Total</div>
            </div>
        </div>
    </div>
</div>

<!-- Filtros -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-2">
                <label class="form-label">Desde</label>
                <input type="date" class="form-control" name="fecha_inicio" value="<?= htmlspecialchars($fecha_inicio) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Hasta</label>
                <input type="date" class="form-control" name="fecha_fin" value="<?= htmlspecialchars($fecha_fin) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Tipo</label>
                <select class="form-select" name="tipo">
                    <option value="">Todos</option>
                    <option value="ingreso" <?= $tipo === 'ingreso' ? 'selected' : '' ?>>Ingresos</option>
                    <option value="egreso" <?= $tipo === 'egreso' ? 'selected' : '' ?>>Egresos</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Método</label>
                <select class="form-select" name="metodo">
                    <option value="">Todos</option>
                    <option value="efectivo" <?= $metodo === 'efectivo' ? 'selected' : '' ?>>Efectivo</option>
                    <option value="qr" <?= $metodo === 'qr' ? 'selected' : '' ?>>QR</option>
                </select>
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-search"></i> Filtrar
                </button>
                <a href="<?= BASE_URL ?>/modules/caja/" class="btn btn-outline-secondary">
                    <i class="bi bi-x-circle"></i>

            </div>
        </form>
    </div>
</div>

<!-- Resumen del Período -->
<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card border-success">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <small class="text-muted">Ingresos del Período</small>
                    <div class="fs-4 fw-bold text-success"><?= formatMoney($totalesPeriodo['ingresos'] ?? 0) ?></div>
                </div>
                <i class="bi bi-arrow-down-circle text-success fs-1"></i>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card border-danger">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <small class="text-muted">Egresos del Período</small>
                    <div class="fs-4 fw-bold text-danger"><?= formatMoney($totalesPeriodo['egresos'] ?? 0) ?></div>
                </div>
                <i class="bi bi-arrow-up-circle text-danger fs-1"></i>
            </div>
        </div>
    </div>
</div>

<!-- Movimientos -->
<div class="card">
    <div class="card-header">
        <i class="bi bi-list-ul me-2"></i>Movimientos
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Fecha/Hora</th>
                        <th>Concepto</th>
                        <th class="text-center">Tipo</th>
                        <th class="text-center">Método</th>
                        <th class="text-end">Monto</th>
                        <th class="text-end">Saldo Efectivo</th>
                        <th class="text-end">Saldo QR</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($movimientos as $mov): ?>
                    <tr>
                        <td><?= formatDate($mov['fecha'], 'd/m/Y H:i') ?></td>
                        <td><?= htmlspecialchars($mov['concepto']) ?></td>
                        <td class="text-center">
                            <?php if ($mov['tipo'] === 'ingreso'): ?>
                            <span class="badge bg-success">Ingreso</span>
                            <?php else: ?>
                            <span class="badge bg-danger">Egreso</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php if ($mov['metodo'] === 'efectivo'): ?>
                            <span class="badge bg-secondary">Efectivo</span>
                            <?php else: ?>
                            <span class="badge bg-primary">QR</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end <?= $mov['tipo'] === 'ingreso' ? 'text-success' : 'text-danger' ?> fw-bold">
                            <?= $mov['tipo'] === 'ingreso' ? '+' : '-' ?><?= formatMoney($mov['monto']) ?>
                        </td>
                        <td class="text-end"><?= formatMoney($mov['saldo_efectivo']) ?></td>
                        <td class="text-end"><?= formatMoney($mov['saldo_qr']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
