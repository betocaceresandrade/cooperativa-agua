<?php
/**
 * Generar Servicios Mensuales
 */

require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/config/config.php';
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_once dirname(dirname(__DIR__)) . '/includes/functions.php';

// Procesar solicitud AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isAjax()) {
    $periodo = sanitize($_POST['periodo'] ?? '');

    if (empty($periodo) || !preg_match('/^\d{4}-\d{2}$/', $periodo)) {
        jsonResponse(['success' => false, 'error' => 'Periodo inválido']);
    }

    $resultado = generarServiciosMensuales($periodo);

    if (isset($resultado['error'])) {
        jsonResponse(['success' => false, 'error' => $resultado['error']]);
    }

    jsonResponse([
        'success' => true,
        'generados' => $resultado['generados'],
        'periodo' => formatPeriodo($resultado['periodo'])
    ]);
}

$pageTitle = 'Generar Servicios';
require_once dirname(dirname(__DIR__)) . '/includes/header.php';

// Obtener estadísticas de generación
$ultimosGenerados = fetchAll(
    "SELECT periodo, COUNT(*) as total, DATE(MAX(created_at)) as fecha_generacion
     FROM servicios
     WHERE tipo_servicio_id = (SELECT id FROM tipos_servicio WHERE es_mensual = 1 LIMIT 1)
     GROUP BY periodo
     ORDER BY periodo DESC
     LIMIT 12"
);

$periodoActual = date('Y-m');
$periodoSiguiente = date('Y-m', strtotime('+1 month'));

// Verificar si ya existe el periodo actual
$existeActual = fetchOne(
    "SELECT COUNT(*) as total FROM servicios WHERE periodo = ?",
    [$periodoActual]
)['total'];
?>

<div class="page-header">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/modules/servicios/">Servicios</a></li>
            <li class="breadcrumb-item active">Generar</li>
        </ol>
    </nav>
    <h1 class="page-title">Generar Servicios Mensuales</h1>
    <p class="page-subtitle">Crear cargos mensuales para todos los socios activos</p>
</div>

<div class="row">
    <div class="col-lg-6">
        <div class="card mb-4">
            <div class="card-header">
                <i class="bi bi-calendar-plus me-2"></i>Generar Nuevo Periodo
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label">Seleccione el Periodo</label>
                    <input type="month" class="form-control form-control-lg" id="periodo"
                           value="<?= $existeActual ? $periodoSiguiente : $periodoActual ?>">
                </div>

                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>
                    Se generará un cargo por cada acción activa de cada socio activo,
                    según su tipo de tarifa configurado.
                </div>

                <button type="button" class="btn btn-primary btn-lg w-100" onclick="generarServiciosMes($('#periodo').val())">
                    <i class="bi bi-play-circle me-2"></i>
                    Generar Servicios del Mes
                </button>

                <div id="resultado" class="mt-3" style="display: none;"></div>
            </div>
        </div>

        <!-- Acciones rápidas -->
        <div class="card">
            <div class="card-header">
                <i class="bi bi-lightning me-2"></i>Generación Rápida
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <button type="button" class="btn btn-outline-primary"
                            onclick="generarServiciosMes('<?= $periodoActual ?>')"
                            <?= $existeActual ? 'disabled' : '' ?>>
                        <i class="bi bi-calendar-check me-2"></i>
                        <?= formatPeriodo($periodoActual) ?>
                        <?= $existeActual ? '(Ya generado)' : '' ?>
                    </button>
                    <button type="button" class="btn btn-outline-primary"
                            onclick="generarServiciosMes('<?= $periodoSiguiente ?>')">
                        <i class="bi bi-calendar-plus me-2"></i>
                        <?= formatPeriodo($periodoSiguiente) ?>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-clock-history me-2"></i>Últimos Periodos Generados
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Periodo</th>
                                <th class="text-center">Servicios</th>
                                <th>Fecha Generación</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($ultimosGenerados)): ?>
                            <tr>
                                <td colspan="3" class="text-center text-muted py-4">
                                    No hay servicios generados
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($ultimosGenerados as $gen): ?>
                            <tr>
                                <td>
                                    <strong><?= formatPeriodo($gen['periodo']) ?></strong>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-primary"><?= $gen['total'] ?></span>
                                </td>
                                <td><?= formatDate($gen['fecha_generacion']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Estadísticas -->
        <div class="card mt-4">
            <div class="card-header">
                <i class="bi bi-bar-chart me-2"></i>Estadísticas
            </div>
            <div class="card-body">
                <?php
                $totalAcciones = fetchOne("SELECT COUNT(*) as total FROM acciones WHERE estado = 'activa'")['total'];
                $totalSocios = fetchOne("SELECT COUNT(*) as total FROM socios WHERE estado = 'activo'")['total'];
                $tarifaPromedio = fetchOne(
                    "SELECT AVG(t.monto) as promedio
                     FROM acciones a
                     JOIN tipos_tarifa t ON a.tipo_tarifa_id = t.id
                     WHERE a.estado = 'activa'"
                )['promedio'];
                ?>
                <div class="row text-center">
                    <div class="col-4">
                        <div class="fs-3 fw-bold text-primary"><?= $totalSocios ?></div>
                        <small class="text-muted">Socios Activos</small>
                    </div>
                    <div class="col-4">
                        <div class="fs-3 fw-bold text-primary"><?= $totalAcciones ?></div>
                        <small class="text-muted">Acciones Activas</small>
                    </div>
                    <div class="col-4">
                        <div class="fs-3 fw-bold text-primary"><?= formatMoney($tarifaPromedio ?? 0) ?></div>
                        <small class="text-muted">Tarifa Promedio</small>
                    </div>
                </div>
                <hr>
                <p class="text-muted mb-0 text-center">
                    <strong>Estimado mensual:</strong>
                    <?= formatMoney($totalAcciones * ($tarifaPromedio ?? 0)) ?>
                </p>
            </div>
        </div>
    </div>
</div>

<?php require_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
