<?php
/**
 * Ver Detalle de Fondo a Rendir
 */

require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/config/config.php';
require_once dirname(dirname(__DIR__)) . '/includes/functions.php';
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
requireLogin();

$id = intval($_GET['id'] ?? 0);
$fondo = fetchOne("SELECT * FROM fondos_rendir WHERE id = ?", [$id]);

if (!$fondo) {
    setFlash('error', 'Fondo no encontrado');
    redirect('/modules/fondos-rendir/');
}

$gastosDelFondo = fetchAll(
    "SELECT g.*, c.nombre as categoria_nombre
     FROM gastos g
     JOIN categorias_gasto c ON g.categoria_id = c.id
     WHERE g.fondo_rendir_id = ?
     ORDER BY g.fecha DESC",
    [$id]
);

$pendiente = $fondo['monto'] - $fondo['monto_rendido'];
$porcentaje = $fondo['monto'] > 0 ? ($fondo['monto_rendido'] / $fondo['monto']) * 100 : 0;

$pageTitle = 'Detalle de Fondo';
require_once dirname(dirname(__DIR__)) . '/includes/header.php';
?>

<div class="page-header">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/modules/fondos-rendir/">Fondos a Rendir</a></li>
            <li class="breadcrumb-item active">Detalle #<?= $id ?></li>
        </ol>
    </nav>
    <div class="d-flex justify-content-between align-items-center">
        <h1 class="page-title">Fondo a Rendir #<?= $id ?></h1>
        <div>
            <a href="<?= BASE_URL ?>/modules/fondos-rendir/editar.php?id=<?= $id ?>" class="btn btn-outline-primary">
                <i class="bi bi-pencil me-1"></i> Editar
            </a>
            <?php if ($pendiente > 0): ?>
            <a href="<?= BASE_URL ?>/modules/fondos-rendir/rendir.php?id=<?= $id ?>" class="btn btn-primary">
                <i class="bi bi-receipt me-1"></i> Continuar Rendición
            </a>
            <?php endif; ?>
            <a href="<?= BASE_URL ?>/modules/fondos-rendir/" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Volver
            </a>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-5">
        <!-- Información del Fondo -->
        <div class="card mb-4">
            <div class="card-header">
                <i class="bi bi-wallet2 me-2"></i>Información del Fondo
            </div>
            <div class="card-body">
                <table class="table table-borderless mb-0">
                    <tr>
                        <td class="text-muted" width="40%">Beneficiario:</td>
                        <td><strong><?= htmlspecialchars($fondo['beneficiario']) ?></strong></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Concepto:</td>
                        <td><?= htmlspecialchars($fondo['concepto'] ?? '-') ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Fecha Entrega:</td>
                        <td><?= formatDate($fondo['fecha_entrega']) ?></td>
                    </tr>
                    <?php if ($fondo['fecha_rendicion']): ?>
                    <tr>
                        <td class="text-muted">Fecha Rendición:</td>
                        <td><?= formatDate($fondo['fecha_rendicion']) ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <td class="text-muted">Estado:</td>
                        <td>
                            <?php if ($fondo['estado'] === 'pendiente'): ?>
                                <span class="badge bg-warning text-dark">Pendiente</span>
                            <?php elseif ($fondo['estado'] === 'parcial'): ?>
                                <span class="badge bg-info">Parcialmente Rendido</span>
                            <?php else: ?>
                                <span class="badge bg-success">Rendido</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Resumen de Montos -->
        <div class="card mb-4">
            <div class="card-header">
                <i class="bi bi-calculator me-2"></i>Resumen de Montos
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-4">
                        <div class="border rounded p-3">
                            <small class="text-muted d-block">Monto Total</small>
                            <strong class="fs-5 text-primary"><?= formatMoney($fondo['monto']) ?></strong>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="border rounded p-3">
                            <small class="text-muted d-block">Rendido</small>
                            <strong class="fs-5 text-success"><?= formatMoney($fondo['monto_rendido']) ?></strong>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="border rounded p-3 <?= $pendiente > 0 ? 'bg-warning bg-opacity-10' : 'bg-success bg-opacity-10' ?>">
                            <small class="text-muted d-block">Pendiente</small>
                            <strong class="fs-5 <?= $pendiente > 0 ? 'text-danger' : 'text-success' ?>"><?= formatMoney($pendiente) ?></strong>
                        </div>
                    </div>
                </div>

                <!-- Barra de progreso -->
                <div class="mt-3">
                    <div class="d-flex justify-content-between mb-1">
                        <small>Progreso de Rendición</small>
                        <small><?= number_format($porcentaje, 1) ?>%</small>
                    </div>
                    <div class="progress" style="height: 10px;">
                        <div class="progress-bar <?= $porcentaje >= 100 ? 'bg-success' : 'bg-info' ?>"
                             role="progressbar"
                             style="width: <?= min($porcentaje, 100) ?>%"></div>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($pendiente > 0): ?>
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle me-2"></i>
            <strong>Fondo con saldo pendiente.</strong><br>
            Quedan <strong><?= formatMoney($pendiente) ?></strong> por rendir.
            <a href="<?= BASE_URL ?>/modules/fondos-rendir/rendir.php?id=<?= $id ?>" class="alert-link">
                Continuar rendición
            </a>
        </div>
        <?php else: ?>
        <div class="alert alert-success">
            <i class="bi bi-check-circle me-2"></i>
            <strong>Fondo completamente rendido.</strong>
        </div>
        <?php endif; ?>
    </div>

    <div class="col-lg-7">
        <!-- Historial de Gastos -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-list-ul me-2"></i>Gastos Registrados (<?= count($gastosDelFondo) ?>)</span>
                <?php if ($pendiente > 0): ?>
                <a href="<?= BASE_URL ?>/modules/fondos-rendir/rendir.php?id=<?= $id ?>" class="btn btn-sm btn-primary">
                    <i class="bi bi-plus me-1"></i> Agregar Gasto
                </a>
                <?php endif; ?>
            </div>
            <div class="card-body p-0">
                <?php if (empty($gastosDelFondo)): ?>
                <div class="p-4 text-center text-muted">
                    <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                    No hay gastos registrados aún
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Fecha</th>
                                <th>Concepto</th>
                                <th>Categoría</th>
                                <th class="text-end">Monto</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($gastosDelFondo as $gasto): ?>
                            <tr>
                                <td><?= formatDate($gasto['fecha']) ?></td>
                                <td><?= htmlspecialchars($gasto['concepto']) ?></td>
                                <td><span class="badge bg-secondary"><?= htmlspecialchars($gasto['categoria_nombre']) ?></span></td>
                                <td class="text-end"><strong><?= formatMoney($gasto['monto']) ?></strong></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <td colspan="3" class="text-end"><strong>Total Rendido:</strong></td>
                                <td class="text-end"><strong class="text-success"><?= formatMoney($fondo['monto_rendido']) ?></strong></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
