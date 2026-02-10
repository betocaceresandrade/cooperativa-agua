<?php
/**
 * Módulo de Reportes
 */

$pageTitle = 'Reportes';
require_once dirname(dirname(__DIR__)) . '/includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title">Reportes</h1>
    <p class="page-subtitle">Informes y estadísticas de la cooperativa <a href="<?= BASE_URL ?>/ayuda.php#reportes" class="ms-2 text-info" title="Ver ayuda"><i class="bi bi-question-circle-fill"></i></a></p>
</div>

<div class="row g-4">
    <div class="col-md-6 col-lg-4">
        <a href="<?= BASE_URL ?>/modules/reportes/estado-resultados.php" class="card text-decoration-none h-100">
            <div class="card-body text-center">
                <i class="bi bi-file-earmark-bar-graph text-primary fs-1 mb-3"></i>
                <h5>Estado de Resultados</h5>
                <p class="text-muted mb-0">Ingresos vs Gastos por período</p>
            </div>
        </a>
    </div>

    <div class="col-md-6 col-lg-4">
        <a href="<?= BASE_URL ?>/modules/reportes/deudores.php" class="card text-decoration-none h-100">
            <div class="card-body text-center">
                <i class="bi bi-exclamation-triangle text-danger fs-1 mb-3"></i>
                <h5>Deudores</h5>
                <p class="text-muted mb-0">Acciones con meses pendientes</p>
            </div>
        </a>
    </div>

    <div class="col-md-6 col-lg-4">
        <a href="<?= BASE_URL ?>/modules/reportes/recaudacion.php" class="card text-decoration-none h-100">
            <div class="card-body text-center">
                <i class="bi bi-graph-up text-success fs-1 mb-3"></i>
                <h5>Recaudación Mensual</h5>
                <p class="text-muted mb-0">Comparativo de cobros por mes</p>
            </div>
        </a>
    </div>

    <div class="col-md-6 col-lg-4">
        <a href="<?= BASE_URL ?>/modules/reportes/mayor-gastos.php" class="card text-decoration-none h-100 border-danger">
            <div class="card-body text-center">
                <i class="bi bi-journal-text text-danger fs-1 mb-3"></i>
                <h5>Mayor de Gastos</h5>
                <p class="text-muted mb-0">Libro diario estilo contable</p>
            </div>
        </a>
    </div>

    <div class="col-md-6 col-lg-4">
        <a href="<?= BASE_URL ?>/modules/reportes/gastos-detallado.php" class="card text-decoration-none h-100">
            <div class="card-body text-center">
                <i class="bi bi-receipt text-warning fs-1 mb-3"></i>
                <h5>Gastos Detallado</h5>
                <p class="text-muted mb-0">Rendición para asambleas</p>
            </div>
        </a>
    </div>

    <div class="col-md-6 col-lg-4">
        <a href="<?= BASE_URL ?>/modules/caja/" class="card text-decoration-none h-100">
            <div class="card-body text-center">
                <i class="bi bi-cash-stack text-info fs-1 mb-3"></i>
                <h5>Movimientos de Caja</h5>
                <p class="text-muted mb-0">Detalle de ingresos y egresos</p>
            </div>
        </a>
    </div>

    <div class="col-md-6 col-lg-4">
        <a href="<?= BASE_URL ?>/modules/reportes/socios.php" class="card text-decoration-none h-100">
            <div class="card-body text-center">
                <i class="bi bi-people text-secondary fs-1 mb-3"></i>
                <h5>Socios y Acciones</h5>
                <p class="text-muted mb-0">Padrón de socios con sus acciones</p>
            </div>
        </a>
    </div>

    <div class="col-md-6 col-lg-4">
        <a href="<?= BASE_URL ?>/modules/notificaciones/" class="card text-decoration-none h-100">
            <div class="card-body text-center">
                <i class="bi bi-envelope-paper text-warning fs-1 mb-3"></i>
                <h5>Notificaciones</h5>
                <p class="text-muted mb-0">Generar avisos de deuda</p>
            </div>
        </a>
    </div>
</div>

<?php require_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
