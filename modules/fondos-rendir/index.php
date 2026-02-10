<?php
/**
 * Salidas de Caja (Fondos a Rendir, Entregas y Depósitos)
 * Módulo unificado
 */

require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/config/config.php';
require_once dirname(dirname(__DIR__)) . '/includes/functions.php';
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
requireLogin();

// Filtro por tipo
$filtro_tipo = $_GET['tipo'] ?? '';

// Query base
$sql = "SELECT f.*,
         (SELECT COUNT(*) FROM gastos WHERE fondo_rendir_id = f.id) as num_gastos
         FROM fondos_rendir f
         WHERE 1=1";
$params = [];

if (!empty($filtro_tipo)) {
    $sql .= " AND f.tipo = ?";
    $params[] = $filtro_tipo;
}

$sql .= " ORDER BY f.estado ASC, f.fecha_entrega DESC";
$fondos = fetchAll($sql, $params);

// Totales por tipo
$totalesTipo = fetchAll(
    "SELECT tipo,
            COUNT(*) as cantidad,
            SUM(monto) as total_monto,
            SUM(CASE WHEN estado IN ('pendiente','parcial') THEN monto - monto_rendido ELSE 0 END) as por_rendir
     FROM fondos_rendir
     GROUP BY tipo"
);
$totalesPorTipo = [];
foreach ($totalesTipo as $t) {
    $totalesPorTipo[$t['tipo']] = $t;
}

// Totales generales de pendientes
$totales = fetchOne(
    "SELECT
        SUM(CASE WHEN estado = 'pendiente' AND requiere_rendicion = 1 THEN monto - monto_rendido ELSE 0 END) as pendiente,
        SUM(CASE WHEN estado = 'parcial' AND requiere_rendicion = 1 THEN monto - monto_rendido ELSE 0 END) as parcial,
        COUNT(CASE WHEN estado = 'pendiente' AND requiere_rendicion = 1 THEN 1 END) as num_pendientes,
        COUNT(CASE WHEN estado = 'parcial' AND requiere_rendicion = 1 THEN 1 END) as num_parciales
     FROM fondos_rendir"
);

$pageTitle = 'Salidas de Caja';
require_once dirname(dirname(__DIR__)) . '/includes/header.php';

// Labels por tipo
$tipoLabels = [
    'adelanto' => ['nombre' => 'Adelanto', 'icono' => 'cash-stack', 'color' => 'warning'],
    'entrega' => ['nombre' => 'Entrega', 'icono' => 'box-arrow-right', 'color' => 'info'],
    'deposito' => ['nombre' => 'Depósito', 'icono' => 'bank', 'color' => 'success']
];
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1 class="page-title"><i class="bi bi-wallet2 me-2"></i>Salidas de Caja</h1>
        <p class="text-muted">Adelantos, entregas y depósitos</p>
    </div>
    <div class="btn-group">
        <button type="button" class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown">
            <i class="bi bi-plus-circle me-1"></i> Nueva Salida
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="<?= BASE_URL ?>/modules/fondos-rendir/crear.php?tipo=adelanto">
                <i class="bi bi-cash-stack me-2 text-warning"></i>Adelanto (Fondo a Rendir)
            </a></li>
            <li><a class="dropdown-item" href="<?= BASE_URL ?>/modules/fondos-rendir/crear.php?tipo=entrega">
                <i class="bi bi-box-arrow-right me-2 text-info"></i>Entrega a Autoridad
            </a></li>
            <li><a class="dropdown-item" href="<?= BASE_URL ?>/modules/fondos-rendir/crear.php?tipo=deposito">
                <i class="bi bi-bank me-2 text-success"></i>Depósito Bancario
            </a></li>
        </ul>
    </div>
</div>

<!-- Resumen por Tipo -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card border-warning">
            <div class="card-body text-center">
                <i class="bi bi-cash-stack text-warning fs-4"></i>
                <div class="fs-4 fw-bold"><?= formatMoney($totalesPorTipo['adelanto']['por_rendir'] ?? 0) ?></div>
                <small class="text-muted">Adelantos por Rendir</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-info">
            <div class="card-body text-center">
                <i class="bi bi-box-arrow-right text-info fs-4"></i>
                <div class="fs-4 fw-bold"><?= formatMoney($totalesPorTipo['entrega']['total_monto'] ?? 0) ?></div>
                <small class="text-muted">Entregas a Autoridades</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-success">
            <div class="card-body text-center">
                <i class="bi bi-bank text-success fs-4"></i>
                <div class="fs-4 fw-bold"><?= formatMoney($totalesPorTipo['deposito']['total_monto'] ?? 0) ?></div>
                <small class="text-muted">Depósitos Bancarios</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-danger text-white">
            <div class="card-body text-center">
                <i class="bi bi-exclamation-triangle fs-4"></i>
                <div class="fs-4 fw-bold"><?= formatMoney(($totales['pendiente'] ?? 0) + ($totales['parcial'] ?? 0)) ?></div>
                <small>Total por Rendir</small>
            </div>
        </div>
    </div>
</div>

<!-- Filtros -->
<div class="card mb-4">
    <div class="card-body py-2">
        <div class="d-flex gap-2 flex-wrap">
            <a href="?" class="btn btn-sm <?= empty($filtro_tipo) ? 'btn-secondary' : 'btn-outline-secondary' ?>">
                Todos
            </a>
            <a href="?tipo=adelanto" class="btn btn-sm <?= $filtro_tipo === 'adelanto' ? 'btn-warning' : 'btn-outline-warning' ?>">
                <i class="bi bi-cash-stack me-1"></i>Adelantos
            </a>
            <a href="?tipo=entrega" class="btn btn-sm <?= $filtro_tipo === 'entrega' ? 'btn-info' : 'btn-outline-info' ?>">
                <i class="bi bi-box-arrow-right me-1"></i>Entregas
            </a>
            <a href="?tipo=deposito" class="btn btn-sm <?= $filtro_tipo === 'deposito' ? 'btn-success' : 'btn-outline-success' ?>">
                <i class="bi bi-bank me-1"></i>Depósitos
            </a>
        </div>
    </div>
</div>

<!-- Lista -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Tipo</th>
                        <th>Beneficiario / Destino</th>
                        <th>Concepto</th>
                        <th class="text-end">Monto</th>
                        <th class="text-end">Rendido</th>
                        <th class="text-center">Estado</th>
                        <th class="text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($fondos)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-4 text-muted">
                            No hay registros
                        </td>
                    </tr>
                    <?php endif; ?>
                    <?php foreach ($fondos as $fondo): ?>
                    <?php 
                        $pendiente = $fondo['monto'] - $fondo['monto_rendido'];
                        $tipoInfo = $tipoLabels[$fondo['tipo']] ?? $tipoLabels['adelanto'];
                    ?>
                    <tr>
                        <td><?= formatDate($fondo['fecha_entrega']) ?></td>
                        <td>
                            <span class="badge bg-<?= $tipoInfo['color'] ?>">
                                <i class="bi bi-<?= $tipoInfo['icono'] ?> me-1"></i><?= $tipoInfo['nombre'] ?>
                            </span>
                        </td>
                        <td><strong><?= htmlspecialchars($fondo['beneficiario']) ?></strong></td>
                        <td><?= htmlspecialchars($fondo['concepto'] ?? '-') ?></td>
                        <td class="text-end"><?= formatMoney($fondo['monto']) ?></td>
                        <td class="text-end">
                            <?php if ($fondo['requiere_rendicion']): ?>
                                <span class="<?= $fondo['monto_rendido'] > 0 ? 'text-success' : 'text-muted' ?>"><?= formatMoney($fondo['monto_rendido']) ?></span>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php if (!$fondo['requiere_rendicion']): ?>
                                <span class="badge bg-success">Completado</span>
                            <?php elseif ($fondo['estado'] === 'pendiente'): ?>
                                <span class="badge bg-warning text-dark">Pendiente</span>
                            <?php elseif ($fondo['estado'] === 'parcial'): ?>
                                <span class="badge bg-info">Parcial</span>
                            <?php else: ?>
                                <span class="badge bg-success">Rendido</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <div class="btn-group btn-group-sm">
                                <a href="<?= BASE_URL ?>/modules/fondos-rendir/ver.php?id=<?= $fondo['id'] ?>"
                                   class="btn btn-outline-secondary" title="Ver">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <?php if ($fondo['requiere_rendicion'] && $fondo['estado'] !== 'rendido'): ?>
                                <a href="<?= BASE_URL ?>/modules/fondos-rendir/rendir.php?id=<?= $fondo['id'] ?>"
                                   class="btn btn-primary" title="Rendir">
                                    <i class="bi bi-receipt"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
