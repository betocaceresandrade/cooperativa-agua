<?php
/**
 * Módulo de Otros Ingresos - Búsqueda de Acción
 * Para registrar reconexiones, multas, instalaciones, etc.
 */

require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/config/config.php';
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_once dirname(dirname(__DIR__)) . '/includes/functions.php';

requireLogin();

// Procesar búsqueda AJAX
if (isset($_GET['ajax']) && $_GET['ajax'] === 'buscar') {
    header('Content-Type: text/html; charset=utf-8');
    $query = sanitize($_GET['q'] ?? '');

    if (strlen($query) >= 2) {
        $acciones = fetchAll(
            "SELECT a.*, s.nombre as socio_nombre, s.numero_socio, z.nombre as zona_nombre
             FROM acciones a
             JOIN socios s ON a.socio_id = s.id
             LEFT JOIN zonas z ON a.zona_id = z.id
             WHERE a.estado IN ('ACTIVO', 'CORTADO', 'SIN INST.')
             AND (a.numero_accion LIKE ? OR s.nombre LIKE ? OR s.numero_socio LIKE ?)
             ORDER BY a.numero_accion
             LIMIT 15",
            ['%' . $query . '%', '%' . $query . '%', '%' . $query . '%']
        );

        if (empty($acciones)) {
            echo '<div class="text-muted p-3 text-center">No se encontraron resultados</div>';
        } else {
            echo '<div class="list-group list-group-flush">';
            foreach ($acciones as $a) {
                // Ir a registrar otro ingreso, no a consumos
                $url = BASE_URL . '/modules/cobros/registrar_otro.php?accion_id=' . $a['id'];
                echo '<a href="' . $url . '" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">';
                echo '<div>';
                echo '<strong>' . htmlspecialchars($a['numero_accion']) . '</strong> - ';
                echo htmlspecialchars($a['socio_nombre']);
                if ($a['zona_nombre']) {
                    echo ' <small class="text-muted">(' . htmlspecialchars($a['zona_nombre']) . ')</small>';
                }
                echo '</div>';
                echo '<span class="badge bg-' . ($a['estado'] === 'ACTIVO' ? 'success' : ($a['estado'] === 'CORTADO' ? 'danger' : 'secondary')) . '">' . $a['estado'] . '</span>';
                echo '</a>';
            }
            echo '</div>';
        }
    }
    exit;
}

$pageTitle = 'Otros Ingresos';
require_once dirname(dirname(__DIR__)) . '/includes/header.php';

$zonas = getZonas();
$tiposOtrosIngresos = fetchAll("SELECT * FROM tipos_otros_ingresos WHERE activo = 1 ORDER BY nombre");
?>

<div class="page-header">
    <h1 class="page-title"><i class="bi bi-cash-coin me-2"></i>Otros Ingresos</h1>
    <p class="text-muted">
        Busque una acción para registrar reconexiones, multas u otros cobros
        <a href="<?= BASE_URL ?>/ayuda.php#ingresos" class="ms-2 text-info" title="Ver ayuda"><i class="bi bi-question-circle-fill"></i></a>
    </p>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-body">
                <!-- Búsqueda Dinámica -->
                <div class="mb-4">
                    <label class="form-label">Buscar Acción o Socio</label>
                    <div class="input-group input-group-lg">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" class="form-control" id="buscar-accion"
                               placeholder="Ingrese nombre, N° socio o N° acción..."
                               autocomplete="off">
                    </div>
                    <small class="text-muted">Escriba al menos 2 caracteres para buscar</small>
                </div>

                <!-- Resultados de búsqueda -->
                <div id="resultados-busqueda" class="border rounded mb-4" style="display: none; max-height: 400px; overflow-y: auto;"></div>

                <hr>

                <!-- Búsqueda por zona -->
                <h6 class="mb-3"><i class="bi bi-geo-alt me-2"></i>O seleccione por zona:</h6>
                <div class="row g-2">
                    <?php foreach ($zonas as $zona): ?>
                    <div class="col-md-4 col-6">
                        <a href="?zona_id=<?= $zona['id'] ?>" class="btn btn-outline-primary w-100 btn-sm">
                            <?= htmlspecialchars($zona['nombre']) ?>
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>

                <?php if (isset($_GET['zona_id'])): ?>
                <?php
                    $accionesZona = fetchAll(
                        "SELECT a.*, s.nombre as socio_nombre, s.numero_socio
                         FROM acciones a
                         JOIN socios s ON a.socio_id = s.id
                         WHERE a.zona_id = ? AND a.estado IN ('ACTIVO', 'CORTADO', 'SIN INST.')
                         ORDER BY a.numero_accion",
                        [intval($_GET['zona_id'])]
                    );
                    $zonaSeleccionada = fetchOne("SELECT nombre FROM zonas WHERE id = ?", [intval($_GET['zona_id'])]);
                ?>
                <hr>
                <h6 class="mb-3">Acciones de <?= htmlspecialchars($zonaSeleccionada['nombre'] ?? 'la zona') ?>:</h6>
                <div class="table-responsive">
                    <table class="table table-hover table-sm">
                        <thead>
                            <tr>
                                <th>Acción</th>
                                <th>Socio</th>
                                <th class="text-center">Estado</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($accionesZona as $a): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($a['numero_accion']) ?></strong></td>
                                <td><?= htmlspecialchars($a['socio_nombre']) ?></td>
                                <td class="text-center">
                                    <span class="badge bg-<?= $a['estado'] === 'ACTIVO' ? 'success' : ($a['estado'] === 'CORTADO' ? 'danger' : 'secondary') ?>">
                                        <?= $a['estado'] ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="<?= BASE_URL ?>/modules/cobros/registrar_otro.php?accion_id=<?= $a['id'] ?>"
                                       class="btn btn-sm btn-success">
                                        <i class="bi bi-plus-circle"></i> Registrar
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <!-- Tipos de Otros Ingresos Disponibles -->
        <div class="card">
            <div class="card-header">
                <i class="bi bi-list-check me-2"></i>Conceptos Disponibles
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <?php foreach ($tiposOtrosIngresos as $tipo): ?>
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <span><?= htmlspecialchars($tipo['nombre']) ?></span>
                        <?php if ($tipo['monto_default']): ?>
                        <span class="badge bg-primary"><?= formatMoney($tipo['monto_default']) ?></span>
                        <?php else: ?>
                        <span class="badge bg-secondary">Variable</span>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

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

        searchTimeout = setTimeout(function() {
            $.get('{$baseUrl}/modules/cobros/', {ajax: 'buscar', q: query}, function(data) {
                resultados.html(data).show();
            });
        }, 300);
    });

    $(document).on('click', function(e) {
        if (!$(e.target).closest('#buscar-accion, #resultados-busqueda').length) {
            resultados.hide();
        }
    });

    input.on('focus', function() {
        if (resultados.html().trim() !== '' && $(this).val().length >= 2) {
            resultados.show();
        }
    });
});
</script>
HTML;

require_once dirname(dirname(__DIR__)) . '/includes/footer.php';
?>
