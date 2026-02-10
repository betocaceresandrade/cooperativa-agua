<?php
/**
 * Notificaciones de Deuda
 * Selección de acciones con deuda para generar notificaciones
 */

$pageTitle = 'Notificaciones de Deuda';
require_once dirname(dirname(__DIR__)) . '/includes/header.php';
require_once dirname(dirname(__DIR__)) . '/includes/functions.php';

$zonas = getZonas();

// Filtros
$zona_id = $_GET['zona_id'] ?? '';
$meses_minimo = intval($_GET['meses_minimo'] ?? 1);
$buscar = trim($_GET['buscar'] ?? '');

// Obtener acciones con deuda (nuevo modelo basado en consumos_anuales)
$sql = "SELECT a.id as accion_id, a.numero_accion, a.estado as estado_accion,
               s.id as socio_id, s.nombre as socio_nombre, s.numero_socio, s.celular,
               z.nombre as zona_nombre,
               t.monto as tarifa_monto,
               (MONTH(CURDATE()) - 1) as meses_transcurridos,
               COUNT(ca.id) as meses_cubiertos,
               ((MONTH(CURDATE()) - 1) - COUNT(ca.id)) as meses_deuda,
               ((MONTH(CURDATE()) - 1) - COUNT(ca.id)) * t.monto as total_deuda
        FROM acciones a
        JOIN socios s ON a.socio_id = s.id
        LEFT JOIN zonas z ON a.zona_id = z.id
        LEFT JOIN tipos_tarifa t ON a.tipo_tarifa_id = t.id
        LEFT JOIN consumos_anuales ca ON ca.accion_id = a.id 
            AND ca.anio = YEAR(CURDATE()) 
            AND ca.mes < MONTH(CURDATE())
            AND ca.estado IN ('pagado','no_cobrable')
        WHERE a.estado IN ('ACTIVO', 'CORTADO')";

$params = [];

if (!empty($zona_id)) {
    $sql .= " AND a.zona_id = ?";
    $params[] = $zona_id;
}

if (!empty($buscar)) {
    $sql .= " AND (a.numero_accion LIKE ? OR s.nombre LIKE ? OR s.numero_socio LIKE ?)";
    $params[] = '%' . $buscar . '%';
    $params[] = '%' . $buscar . '%';
    $params[] = '%' . $buscar . '%';
}

$sql .= " GROUP BY a.id";

if ($meses_minimo >= 1) {
    $sql .= " HAVING meses_deuda >= ?";
    $params[] = $meses_minimo;
}

$sql .= " ORDER BY meses_deuda DESC, a.numero_accion";

$deudores = fetchAll($sql, $params);
if (empty($deudores)) { error_log("SQL: " . $sql); error_log("Params: " . print_r($params, true)); }
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1 class="page-title"><i class="bi bi-envelope-paper me-2"></i>Notificaciones de Deuda</h1>
        <p class="page-subtitle">Generar avisos para socios con deuda <a href="<?= BASE_URL ?>/ayuda.php#notificaciones" class="ms-2 text-info" title="Ver ayuda"><i class="bi bi-question-circle-fill"></i></a></p>
        <p class="text-muted">Generar avisos de cobro para acciones con meses pendientes</p>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <!-- Filtros -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label">Buscar socio o acción</label>
                        <input type="text" class="form-control" name="buscar"
                               placeholder="Nombre, N° socio o acción..."
                               value="<?= htmlspecialchars($buscar) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Zona</label>
                        <select class="form-select" name="zona_id">
                            <option value="">Todas las zonas</option>
                            <?php foreach ($zonas as $zona): ?>
                            <option value="<?= $zona['id'] ?>" <?= $zona_id == $zona['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($zona['nombre']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Meses mínimo</label>
                        <select class="form-select" name="meses_minimo">
                            <option value="1" <?= $meses_minimo == 1 ? 'selected' : '' ?>>1 mes o más</option>
                            <option value="2" <?= $meses_minimo == 2 ? 'selected' : '' ?>>2 meses o más</option>
                            <option value="3" <?= $meses_minimo == 3 ? 'selected' : '' ?>>3 meses o más</option>
                            <option value="6" <?= $meses_minimo == 6 ? 'selected' : '' ?>>6 meses o más</option>
                            <option value="12" <?= $meses_minimo == 12 ? 'selected' : '' ?>>12 meses o más</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-search"></i> Filtrar
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Lista de Deudores por Acción -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-list-check me-2"></i>Acciones con deuda (<?= count($deudores) ?>)</span>
                <div class="btn-group btn-group-sm">
                    <button type="button" class="btn btn-outline-primary" onclick="toggleAllDeudores(true)">
                        Seleccionar todos
                    </button>
                    <button type="button" class="btn btn-outline-secondary" onclick="toggleAllDeudores(false)">
                        Ninguno
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                <form id="form-notificaciones" method="POST" action="<?= BASE_URL ?>/modules/notificaciones/generar.php" target="_blank">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th width="40">
                                        <input type="checkbox" class="form-check-input" id="check-all"
                                               onchange="toggleAllDeudores(this.checked)">
                                    </th>
                                    <th>Acción</th>
                                    <th>Socio</th>
                                    <th>Zona</th>
                                    <th class="text-center">Meses</th>
                                    <th class="text-end">Deuda</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($deudores)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">
                                        <i class="bi bi-check-circle fs-1 text-success"></i>
                                        <p class="mt-2">No hay acciones con deuda según los filtros</p>
                                    </td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($deudores as $deudor): ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" class="form-check-input deudor-check"
                                               name="acciones[]" value="<?= $deudor['accion_id'] ?>">
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars($deudor['numero_accion']) ?></strong>
                                        <?php if ($deudor['estado_accion'] === 'CORTADO'): ?>
                                        <span class="badge bg-danger ms-1">Cortado</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="<?= BASE_URL ?>/modules/socios/ver.php?id=<?= $deudor['socio_id'] ?>">
                                            <?= htmlspecialchars($deudor['socio_nombre']) ?>
                                        </a>
                                    </td>
                                    <td><?= htmlspecialchars($deudor['zona_nombre'] ?? '-') ?></td>
                                    <td class="text-center">
                                        <span class="badge bg-<?= $deudor['meses_deuda'] >= 3 ? 'danger' : 'warning' ?> text-<?= $deudor['meses_deuda'] >= 3 ? 'white' : 'dark' ?>">
                                            <?= $deudor['meses_deuda'] ?> mes(es)
                                        </span>
                                    </td>
                                    <td class="text-end text-danger fw-bold">
                                        <?= formatMoney($deudor['total_deuda']) ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <!-- Panel de Generación -->
        <div class="card sticky-top" style="top: 80px;">
            <div class="card-header">
                <i class="bi bi-printer me-2"></i>Generar Notificaciones
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label">Seleccionados:</label>
                    <div class="fs-3 fw-bold text-primary" id="contador-seleccionados">0</div>
                </div>

                <button type="button" class="btn btn-primary w-100 mb-2" onclick="generarNotificaciones()">
                    <i class="bi bi-printer me-2"></i>
                    Generar e Imprimir
                </button>

                <div class="alert alert-info mt-3 mb-0">
                    <small>
                        <i class="bi bi-info-circle me-1"></i>
                        Se imprimen 4 notificaciones por página tamaño carta horizontal.
                        Incluyen zona, número de acción y cuadritos de meses.
                    </small>
                </div>
            </div>
        </div>

        <!-- Estadísticas -->
        <div class="card mt-4">
            <div class="card-header">
                <i class="bi bi-bar-chart me-2"></i>Resumen
            </div>
            <div class="card-body">
                <?php
                $totalDeuda = array_sum(array_column($deudores, 'total_deuda'));
                $totalMeses = array_sum(array_column($deudores, 'meses_deuda'));
                ?>
                <div class="row text-center">
                    <div class="col-6">
                        <div class="fs-3 fw-bold text-primary"><?= count($deudores) ?></div>
                        <small class="text-muted">Acciones con deuda</small>
                    </div>
                    <div class="col-6">
                        <div class="fs-3 fw-bold text-danger"><?= formatMoney($totalDeuda) ?></div>
                        <small class="text-muted">Deuda Total</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$pageScripts = <<<HTML
<script>
function toggleAllDeudores(checked) {
    document.querySelectorAll('.deudor-check').forEach(el => el.checked = checked);
    document.getElementById('check-all').checked = checked;
    actualizarContador();
}

function actualizarContador() {
    const count = document.querySelectorAll('.deudor-check:checked').length;
    document.getElementById('contador-seleccionados').textContent = count;
}

document.querySelectorAll('.deudor-check').forEach(el => {
    el.addEventListener('change', actualizarContador);
});

function generarNotificaciones() {
    const seleccionados = document.querySelectorAll('.deudor-check:checked').length;
    if (seleccionados === 0) {
        alert('Seleccione al menos una acción');
        return;
    }

    document.getElementById('form-notificaciones').submit();
}
</script>
HTML;

require_once dirname(dirname(__DIR__)) . '/includes/footer.php';
?>
