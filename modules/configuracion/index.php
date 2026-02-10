<?php
/**
 * Módulo de Configuración
 * Administración de catálogos del sistema
 */

$pageTitle = 'Configuración';
require_once dirname(dirname(__DIR__)) . '/includes/header.php';
require_once dirname(dirname(__DIR__)) . '/includes/functions.php';

// Contar registros para mostrar en las tarjetas
$conteos = [
    'zonas' => fetchOne("SELECT COUNT(*) as total FROM zonas")['total'] ?? 0,
    'tarifas' => fetchOne("SELECT COUNT(*) as total FROM tipos_tarifa")['total'] ?? 0,
    'categorias' => fetchOne("SELECT COUNT(*) as total FROM categorias_gasto")['total'] ?? 0,
    'otros_ingresos' => fetchOne("SELECT COUNT(*) as total FROM items_adicionales")['total'] ?? 0,
];
?>

<div class="page-header">
    <h1 class="page-title"><i class="bi bi-gear me-2"></i>Configuración</h1>
    <p class="page-subtitle">Administración de catálogos y parámetros del sistema <a href="<?= BASE_URL ?>/ayuda.php#configuracion" class="ms-2 text-info" title="Ver ayuda"><i class="bi bi-question-circle-fill"></i></a></p>
</div>

<div class="row g-4">
    <!-- Zonas -->
    <div class="col-md-6 col-lg-4">
        <a href="<?= BASE_URL ?>/modules/configuracion/zonas.php" class="card text-decoration-none h-100">
            <div class="card-body">
                <div class="d-flex align-items-center mb-3">
                    <div class="bg-primary bg-opacity-10 rounded-3 p-3 me-3">
                        <i class="bi bi-geo-alt text-primary fs-3"></i>
                    </div>
                    <div>
                        <h5 class="mb-0">Zonas</h5>
                        <small class="text-muted"><?= $conteos['zonas'] ?> registros</small>
                    </div>
                </div>
                <p class="text-muted mb-0">Sectores geográficos donde se ubican las acciones</p>
            </div>
        </a>
    </div>

    <!-- Tarifas -->
    <div class="col-md-6 col-lg-4">
        <a href="<?= BASE_URL ?>/modules/configuracion/tarifas.php" class="card text-decoration-none h-100">
            <div class="card-body">
                <div class="d-flex align-items-center mb-3">
                    <div class="bg-success bg-opacity-10 rounded-3 p-3 me-3">
                        <i class="bi bi-currency-dollar text-success fs-3"></i>
                    </div>
                    <div>
                        <h5 class="mb-0">Tarifas</h5>
                        <small class="text-muted"><?= $conteos['tarifas'] ?> registros</small>
                    </div>
                </div>
                <p class="text-muted mb-0">Categorías de servicio y montos mensuales</p>
            </div>
        </a>
    </div>

    <!-- Categorías de Gasto -->
    <div class="col-md-6 col-lg-4">
        <a href="<?= BASE_URL ?>/modules/configuracion/categorias.php" class="card text-decoration-none h-100">
            <div class="card-body">
                <div class="d-flex align-items-center mb-3">
                    <div class="bg-danger bg-opacity-10 rounded-3 p-3 me-3">
                        <i class="bi bi-tags text-danger fs-3"></i>
                    </div>
                    <div>
                        <h5 class="mb-0">Categorías de Gasto</h5>
                        <small class="text-muted"><?= $conteos['categorias'] ?> registros</small>
                    </div>
                </div>
                <p class="text-muted mb-0">Clasificación de egresos para reportes</p>
            </div>
        </a>
    </div>

    <!-- Otros Ingresos -->
    <div class="col-md-6 col-lg-4">
        <a href="<?= BASE_URL ?>/modules/configuracion/otros-ingresos.php" class="card text-decoration-none h-100">
            <div class="card-body">
                <div class="d-flex align-items-center mb-3">
                    <div class="bg-warning bg-opacity-10 rounded-3 p-3 me-3">
                        <i class="bi bi-cash-coin text-warning fs-3"></i>
                    </div>
                    <div>
                        <h5 class="mb-0">Otros Ingresos</h5>
                        <small class="text-muted"><?= $conteos['otros_ingresos'] ?> registros</small>
                    </div>
                </div>
                <p class="text-muted mb-0">Reconexiones, multas, instalaciones y otros conceptos</p>
            </div>
        </a>
    </div>

    <!-- Configuración General -->
    <div class="col-md-6 col-lg-4">
        <a href="<?= BASE_URL ?>/modules/configuracion/general.php" class="card text-decoration-none h-100">
            <div class="card-body">
                <div class="d-flex align-items-center mb-3">
                    <div class="bg-info bg-opacity-10 rounded-3 p-3 me-3">
                        <i class="bi bi-sliders text-info fs-3"></i>
                    </div>
                    <div>
                        <h5 class="mb-0">General</h5>
                        <small class="text-muted">Parámetros del sistema</small>
                    </div>
                </div>
                <p class="text-muted mb-0">Nombre, dirección, teléfono y otros datos de la cooperativa</p>
            </div>
        </a>
    </div>
</div>

<?php require_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
