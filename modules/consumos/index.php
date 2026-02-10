<?php
/**
 * Lista de Consumos - Buscar acción para cobrar
 * Búsqueda dinámica con AJAX
 */

require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/config/config.php';
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_once dirname(dirname(__DIR__)) . '/includes/functions.php';

requireLogin();

// Procesar búsqueda AJAX - DEBE estar ANTES de cualquier output
if (isset($_GET['ajax']) && $_GET['ajax'] === 'buscar') {
    header('Content-Type: text/html; charset=utf-8');
    $query = sanitize($_GET['q'] ?? '');

    if (strlen($query) >= 2) {
        $acciones = fetchAll(
            "SELECT a.*, t.nombre as tarifa_nombre, t.monto as tarifa_monto,
                    s.nombre as socio_nombre, s.numero_socio,
                    z.nombre as zona_nombre,
                    (SELECT COUNT(*) FROM consumos_anuales ca WHERE ca.accion_id = a.id AND ca.estado = 'pendiente') as meses_pendientes,
                    (SELECT COALESCE(SUM(monto), 0) FROM consumos_anuales ca WHERE ca.accion_id = a.id AND ca.estado = 'pendiente') as total_deuda
             FROM acciones a
             JOIN tipos_tarifa t ON a.tipo_tarifa_id = t.id
             JOIN socios s ON a.socio_id = s.id
             LEFT JOIN zonas z ON a.zona_id = z.id
             WHERE (a.numero_accion LIKE ? OR s.nombre LIKE ? OR s.numero_socio LIKE ?)
             ORDER BY a.numero_accion
             LIMIT 15",
            ['%' . $query . '%', '%' . $query . '%', '%' . $query . '%']
        );

        if (empty($acciones)) {
            echo '<div class="text-muted p-3 text-center">No se encontraron resultados</div>';
        } else {
            echo '<div class="list-group list-group-flush">';
            foreach ($acciones as $a) {
                $url = BASE_URL . '/modules/consumos/cobrar.php?accion_id=' . $a['id'];
                $estadoClass = match($a['estado']) {
                    'ACTIVO' => 'success',
                    'CORTADO' => 'danger',
                    'BAJA' => 'secondary',
                    'SIN INST.' => 'info',
                    default => 'secondary'
                };
                echo '<a href="' . $url . '" class="list-group-item list-group-item-action">';
                echo '<div class="d-flex justify-content-between align-items-center">';
                echo '<div>';
                echo '<strong>' . htmlspecialchars($a['numero_accion']) . '</strong>';
                echo ' <span class="badge bg-' . $estadoClass . ' badge-sm">' . $a['estado'] . '</span>';
                echo '<br>';
                echo '<span class="text-dark">' . htmlspecialchars($a['socio_nombre']) . '</span>';
                if ($a['zona_nombre']) {
                    echo ' <small class="text-muted">(' . htmlspecialchars($a['zona_nombre']) . ')</small>';
                }
                echo '</div>';
                echo '<div class="text-end">';
                if ($a['meses_pendientes'] > 0) {
                    echo '<span class="badge bg-warning text-dark">' . $a['meses_pendientes'] . ' mes(es)</span><br>';
                    echo '<strong class="text-danger">' . formatMoney($a['total_deuda']) . '</strong>';
                } else {
                    echo '<span class="badge bg-success">Al día</span>';
                }
                echo '</div>';
                echo '</div>';
                echo '</a>';
            }
            echo '</div>';
        }
    }
    exit;
}

// Página normal
$pageTitle = 'Consumo Mensual';
require_once dirname(dirname(__DIR__)) . '/includes/header.php';

$zonas = getZonas();
?>

<div class="page-header">
    <h1 class="page-title"><i class="bi bi-droplet me-2"></i>Consumo Mensual</h1>
        <p class="page-subtitle">Buscar acciones para cobrar <a href="<?= BASE_URL ?>/ayuda.php#consumos" class="ms-2 text-info" title="Ver ayuda"><i class="bi bi-question-circle-fill"></i></a></p>
    <p class="text-muted">Busque una acción para ver y cobrar los meses de consumo</p>
</div>

<!-- Buscador Dinámico -->
<div class="card mb-4">
    <div class="card-body">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <label class="form-label fw-bold">Buscar Acción o Socio</label>
                <div class="input-group input-group-lg">
                    <span class="input-group-text bg-primary text-white">
                        <i class="bi bi-search"></i>
                    </span>
                    <input type="text" class="form-control form-control-lg" id="buscar-accion"
                           placeholder="Nombre, N° socio o N° acción..."
                           autocomplete="off" autofocus>
                </div>
                <small class="text-muted">Escriba al menos 2 caracteres para buscar - los resultados aparecen automáticamente</small>
            </div>
        </div>

        <!-- Resultados de búsqueda dinámica -->
        <div class="row justify-content-center mt-2">
            <div class="col-lg-8">
                <div id="resultados-busqueda" class="border rounded shadow-sm" style="display: none; max-height: 450px; overflow-y: auto;"></div>
            </div>
        </div>
    </div>
</div>

<!-- Búsqueda por zona -->
<div class="card">
    <div class="card-header">
        <i class="bi bi-geo-alt me-2"></i>Buscar por Zona
    </div>
    <div class="card-body">
        <div class="row g-2">
            <?php foreach ($zonas as $zona): ?>
            <div class="col-md-3 col-6">
                <a href="?zona_id=<?= $zona['id'] ?>" class="btn btn-outline-primary w-100">
                    <?= htmlspecialchars($zona['nombre']) ?>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php if (isset($_GET['zona_id'])): ?>
<?php
    $zona_id = intval($_GET['zona_id']);
    $zonaInfo = fetchOne("SELECT nombre FROM zonas WHERE id = ?", [$zona_id]);
    $acciones = fetchAll(
        "SELECT a.*, t.nombre as tarifa_nombre, t.monto as tarifa_monto,
                s.nombre as socio_nombre, s.numero_socio,
                z.nombre as zona_nombre,
                (SELECT COUNT(*) FROM consumos_anuales ca WHERE ca.accion_id = a.id AND ca.estado = 'pendiente') as meses_pendientes,
                (SELECT COALESCE(SUM(monto), 0) FROM consumos_anuales ca WHERE ca.accion_id = a.id AND ca.estado = 'pendiente') as total_deuda
         FROM acciones a
         JOIN tipos_tarifa t ON a.tipo_tarifa_id = t.id
         JOIN socios s ON a.socio_id = s.id
         LEFT JOIN zonas z ON a.zona_id = z.id
         WHERE a.zona_id = ?
         ORDER BY a.numero_accion",
        [$zona_id]
    );
?>
<div class="card mt-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-list me-2"></i>Acciones de <?= htmlspecialchars($zonaInfo['nombre'] ?? 'la zona') ?> (<?= count($acciones) ?>)</span>
        <a href="<?= BASE_URL ?>/modules/consumos/" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-x"></i> Limpiar
        </a>
    </div>
    <?php if (empty($acciones)): ?>
    <div class="card-body text-center py-5 text-muted">
        <i class="bi bi-search fs-1"></i>
        <p class="mt-2">No hay acciones en esta zona</p>
    </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Acción</th>
                    <th>Socio</th>
                    <th>Tarifa</th>
                    <th class="text-center">Estado</th>
                    <th class="text-center">Meses Pend.</th>
                    <th class="text-end">Deuda</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($acciones as $a): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($a['numero_accion']) ?></strong></td>
                    <td><?= htmlspecialchars($a['socio_nombre']) ?></td>
                    <td><?= htmlspecialchars($a['tarifa_nombre']) ?></td>
                    <td class="text-center">
                        <?php
                        $estadoClass = match($a['estado']) {
                            'ACTIVO' => 'success',
                            'CORTADO' => 'danger',
                            'BAJA' => 'secondary',
                            'SIN INST.' => 'info',
                            default => 'secondary'
                        };
                        ?>
                        <span class="badge bg-<?= $estadoClass ?>"><?= $a['estado'] ?></span>
                    </td>
                    <td class="text-center">
                        <?php if ($a['meses_pendientes'] > 0): ?>
                        <span class="badge bg-warning text-dark"><?= $a['meses_pendientes'] ?></span>
                        <?php else: ?>
                        <span class="badge bg-success">0</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end">
                        <?php if ($a['total_deuda'] > 0): ?>
                        <strong class="text-danger"><?= formatMoney($a['total_deuda']) ?></strong>
                        <?php else: ?>
                        <span class="text-success">Bs 0.00</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="<?= BASE_URL ?>/modules/consumos/cobrar.php?accion_id=<?= $a['id'] ?>"
                           class="btn btn-sm btn-<?= $a['meses_pendientes'] > 0 ? 'warning' : 'outline-success' ?>">
                            <i class="bi bi-cash me-1"></i> Cobrar
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php
$baseUrl = BASE_URL;
$pageScripts = <<<HTML
<script>
$(document).ready(function() {
    let searchTimeout;
    const input = $('#buscar-accion');
    const resultados = $('#resultados-busqueda');

    input.on('keyup', function() {
        const query = $(this).val();
        clearTimeout(searchTimeout);

        if (query.length < 2) {
            resultados.hide().empty();
            return;
        }

        // Mostrar indicador de carga
        resultados.html('<div class="text-center p-3"><div class="spinner-border spinner-border-sm text-primary"></div> Buscando...</div>').show();

        searchTimeout = setTimeout(function() {
            $.get('{$baseUrl}/modules/consumos/', {ajax: 'buscar', q: query}, function(data) {
                resultados.html(data).show();
            });
        }, 300);
    });

    // Ocultar resultados al hacer click fuera
    $(document).on('click', function(e) {
        if (!$(e.target).closest('#buscar-accion, #resultados-busqueda').length) {
            resultados.hide();
        }
    });

    // Mostrar resultados al enfocar si hay contenido
    input.on('focus', function() {
        if (resultados.html().trim() !== '' && $(this).val().length >= 2) {
            resultados.show();
        }
    });

    // Enter para ir al primer resultado
    input.on('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            const firstResult = resultados.find('a').first();
            if (firstResult.length) {
                window.location.href = firstResult.attr('href');
            }
        }
    });
});
</script>
HTML;

require_once dirname(dirname(__DIR__)) . '/includes/footer.php';
?>
