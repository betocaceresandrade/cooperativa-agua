<?php
/**
 * Configuración: Zonas
 * CRUD de zonas geográficas
 */

$pageTitle = 'Configurar Zonas';
require_once dirname(dirname(__DIR__)) . '/includes/header.php';
require_once dirname(dirname(__DIR__)) . '/includes/functions.php';

$mensaje = '';
$tipo_mensaje = '';

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    $id = intval($_POST['id'] ?? 0);
    $nombre = trim($_POST['nombre'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $activo = isset($_POST['activo']) ? 1 : 0;

    if (empty($nombre)) {
        $mensaje = 'El nombre es obligatorio';
        $tipo_mensaje = 'danger';
    } else {
        try {
            if ($accion === 'crear') {
                insert(
                    "INSERT INTO zonas (nombre, descripcion, activo) VALUES (?, ?, ?)",
                    [$nombre, $descripcion, $activo]
                );
                $mensaje = 'Zona creada correctamente';
                $tipo_mensaje = 'success';
            } elseif ($accion === 'editar' && $id > 0) {
                update(
                    "UPDATE zonas SET nombre = ?, descripcion = ?, activo = ? WHERE id = ?",
                    [$nombre, $descripcion, $activo, $id]
                );
                $mensaje = 'Zona actualizada correctamente';
                $tipo_mensaje = 'success';
            } elseif ($accion === 'eliminar' && $id > 0) {
                // Verificar si hay acciones usando esta zona
                $enUso = fetchOne("SELECT COUNT(*) as total FROM acciones WHERE zona_id = ?", [$id]);
                if ($enUso['total'] > 0) {
                    $mensaje = 'No se puede eliminar: hay ' . $enUso['total'] . ' acción(es) usando esta zona';
                    $tipo_mensaje = 'warning';
                } else {
                    update("DELETE FROM zonas WHERE id = ?", [$id]);
                    $mensaje = 'Zona eliminada correctamente';
                    $tipo_mensaje = 'success';
                }
            }
        } catch (Exception $e) {
            $mensaje = 'Error: ' . $e->getMessage();
            $tipo_mensaje = 'danger';
        }
    }
}

// Obtener zonas con conteo de acciones
$zonas = fetchAll(
    "SELECT z.*,
            (SELECT COUNT(*) FROM acciones WHERE zona_id = z.id) as num_acciones
     FROM zonas z
     ORDER BY z.nombre"
);
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/modules/configuracion/">Configuración</a></li>
                <li class="breadcrumb-item active">Zonas</li>
            </ol>
        </nav>
        <h1 class="page-title"><i class="bi bi-geo-alt me-2"></i>Zonas</h1>
    </div>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalZona" onclick="limpiarFormulario()">
        <i class="bi bi-plus-lg me-1"></i> Nueva Zona
    </button>
</div>

<?php if ($mensaje): ?>
<div class="alert alert-<?= $tipo_mensaje ?> alert-dismissible fade show" role="alert">
    <?= htmlspecialchars($mensaje) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Descripción</th>
                        <th class="text-center">Acciones</th>
                        <th class="text-center">Estado</th>
                        <th width="120"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($zonas)): ?>
                    <tr>
                        <td colspan="5" class="text-center py-4 text-muted">
                            No hay zonas registradas
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($zonas as $zona): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($zona['nombre']) ?></strong></td>
                        <td><?= htmlspecialchars($zona['descripcion'] ?? '-') ?></td>
                        <td class="text-center">
                            <span class="badge bg-primary"><?= $zona['num_acciones'] ?></span>
                        </td>
                        <td class="text-center">
                            <?php if ($zona['activo']): ?>
                            <span class="badge bg-success">Activo</span>
                            <?php else: ?>
                            <span class="badge bg-secondary">Inactivo</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button type="button" class="btn btn-sm btn-outline-primary"
                                    onclick="editarZona(<?= htmlspecialchars(json_encode($zona)) ?>)">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <?php if ($zona['num_acciones'] == 0): ?>
                            <button type="button" class="btn btn-sm btn-outline-danger"
                                    onclick="eliminarZona(<?= $zona['id'] ?>, '<?= htmlspecialchars($zona['nombre']) ?>')">
                                <i class="bi bi-trash"></i>
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Zona -->
<div class="modal fade" id="modalZona" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="accion" id="accion" value="crear">
                <input type="hidden" name="id" id="zona_id" value="0">

                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitulo">Nueva Zona</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nombre <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="nombre" id="nombre" required
                               placeholder="Ej: Calle Bolívar">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Descripción</label>
                        <textarea class="form-control" name="descripcion" id="descripcion" rows="2"
                                  placeholder="Descripción opcional"></textarea>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" name="activo" id="activo" checked>
                        <label class="form-check-label" for="activo">Activo</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Eliminar -->
<div class="modal fade" id="modalEliminar" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="accion" value="eliminar">
                <input type="hidden" name="id" id="eliminar_id">

                <div class="modal-header">
                    <h5 class="modal-title">Confirmar</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>¿Eliminar la zona <strong id="eliminar_nombre"></strong>?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">No</button>
                    <button type="submit" class="btn btn-danger">Sí, eliminar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$pageScripts = <<<'HTML'
<script>
function limpiarFormulario() {
    document.getElementById('accion').value = 'crear';
    document.getElementById('zona_id').value = '0';
    document.getElementById('nombre').value = '';
    document.getElementById('descripcion').value = '';
    document.getElementById('activo').checked = true;
    document.getElementById('modalTitulo').textContent = 'Nueva Zona';
}

function editarZona(zona) {
    document.getElementById('accion').value = 'editar';
    document.getElementById('zona_id').value = zona.id;
    document.getElementById('nombre').value = zona.nombre;
    document.getElementById('descripcion').value = zona.descripcion || '';
    document.getElementById('activo').checked = zona.activo == 1;
    document.getElementById('modalTitulo').textContent = 'Editar Zona';
    new bootstrap.Modal(document.getElementById('modalZona')).show();
}

function eliminarZona(id, nombre) {
    document.getElementById('eliminar_id').value = id;
    document.getElementById('eliminar_nombre').textContent = nombre;
    new bootstrap.Modal(document.getElementById('modalEliminar')).show();
}
</script>
HTML;

require_once dirname(dirname(__DIR__)) . '/includes/footer.php';
?>
