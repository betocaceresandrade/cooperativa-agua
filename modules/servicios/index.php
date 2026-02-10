<?php
/**
 * Lista de Servicios
 */

$pageTitle = 'Servicios';
require_once dirname(dirname(__DIR__)) . '/includes/header.php';
require_once dirname(dirname(__DIR__)) . '/includes/functions.php';

// Filtros
$periodo = $_GET['periodo'] ?? date('Y-m');
$estado = $_GET['estado'] ?? '';

$sql = "SELECT s.*, ts.nombre as tipo_nombre, a.numero_accion,
               so.nombre as socio_nombre, so.numero_socio
        FROM servicios s
        JOIN tipos_servicio ts ON s.tipo_servicio_id = ts.id
        JOIN acciones a ON s.accion_id = a.id
        JOIN socios so ON a.socio_id = so.id
        WHERE 1=1";
$params = [];

if ($periodo) {
    $sql .= " AND s.periodo = ?";
    $params[] = $periodo;
}

if ($estado) {
    $sql .= " AND s.estado = ?";
    $params[] = $estado;
}

$sql .= " ORDER BY so.numero_socio, s.periodo";
$servicios = fetchAll($sql, $params);

// Estadísticas del periodo
$stats = fetchOne(
    "SELECT
        COUNT(*) as total,
        SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
        SUM(CASE WHEN estado = 'pagado' THEN 1 ELSE 0 END) as pagados,
        SUM(CASE WHEN estado = 'anulado' THEN 1 ELSE 0 END) as anulados,
        SUM(CASE WHEN estado = 'pendiente' THEN monto ELSE 0 END) as monto_pendiente,
        SUM(CASE WHEN estado = 'pagado' THEN monto ELSE 0 END) as monto_pagado
     FROM servicios WHERE periodo = ?",
    [$periodo]
);
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1 class="page-title">Gestión de Servicios</h1>
        <p class="page-subtitle">Servicios mensuales y ocasionales</p>
    </div>
    <a href="<?= BASE_URL ?>/modules/servicios/generar.php" class="btn btn-primary">
        <i class="bi bi-calendar-plus me-1"></i> Generar Servicios
    </a>
</div>

<!-- Estadísticas -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card bg-primary text-white">
            <div class="card-body text-center">
                <div class="fs-3 fw-bold"><?= $stats['total'] ?? 0 ?></div>
                <small>Total Servicios</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-danger text-white">
            <div class="card-body text-center">
                <div class="fs-3 fw-bold"><?= $stats['pendientes'] ?? 0 ?></div>
                <small>Pendientes (<?= formatMoney($stats['monto_pendiente'] ?? 0) ?>)</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-success text-white">
            <div class="card-body text-center">
                <div class="fs-3 fw-bold"><?= $stats['pagados'] ?? 0 ?></div>
                <small>Pagados (<?= formatMoney($stats['monto_pagado'] ?? 0) ?>)</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-secondary text-white">
            <div class="card-body text-center">
                <div class="fs-3 fw-bold"><?= $stats['anulados'] ?? 0 ?></div>
                <small>Anulados</small>
            </div>
        </div>
    </div>
</div>

<!-- Filtros -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Periodo</label>
                <input type="month" class="form-control" name="periodo"
                       value="<?= htmlspecialchars($periodo) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Estado</label>
                <select class="form-select" name="estado">
                    <option value="">Todos</option>
                    <option value="pendiente" <?= $estado === 'pendiente' ? 'selected' : '' ?>>Pendientes</option>
                    <option value="pagado" <?= $estado === 'pagado' ? 'selected' : '' ?>>Pagados</option>
                    <option value="anulado" <?= $estado === 'anulado' ? 'selected' : '' ?>>Anulados</option>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-search"></i> Filtrar
                </button>
                <a href="<?= BASE_URL ?>/modules/servicios/" class="btn btn-outline-secondary">
                    <i class="bi bi-x-circle"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Lista de Servicios -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover datatable mb-0">
                <thead>
                    <tr>
                        <th>N° Socio</th>
                        <th>Nombre</th>
                        <th>Periodo</th>
                        <th>Tipo</th>
                        <th class="text-end">Monto</th>
                        <th class="text-center">Estado</th>
                        <th class="text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($servicios as $servicio): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($servicio['numero_socio']) ?></strong></td>
                        <td>
                            <a href="<?= BASE_URL ?>/modules/socios/ver.php?id=<?= $servicio['socio_id'] ?? '' ?>">
                                <?= htmlspecialchars($servicio['socio_nombre']) ?>
                            </a>
                        </td>
                        <td><?= formatPeriodo($servicio['periodo']) ?></td>
                        <td><?= htmlspecialchars($servicio['tipo_nombre']) ?></td>
                        <td class="text-end"><?= formatMoney($servicio['monto']) ?></td>
                        <td class="text-center">
                            <span class="badge-estado badge-<?= $servicio['estado'] ?>">
                                <?= ucfirst($servicio['estado']) ?>
                            </span>
                        </td>
                        <td class="text-center">
                            <?php if ($servicio['estado'] === 'pendiente'): ?>
                            <button type="button" class="btn btn-sm btn-outline-warning"
                                    onclick="exonerarServicio(<?= $servicio['id'] ?>)"
                                    title="Exonerar">
                                <i class="bi bi-x-circle"></i>
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
