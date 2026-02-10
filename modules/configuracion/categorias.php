<?php
/**
 * Configuración: Categorías de Gasto
 * CRUD de categorías para clasificar egresos
 */

$pageTitle = 'Configurar Categorías de Gasto';
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
                    "INSERT INTO categorias_gasto (nombre, descripcion, activo) VALUES (?, ?, ?)",
                    [$nombre, $descripcion, $activo]
                );
                $mensaje = 'Categoría creada correctamente';
                $tipo_mensaje = 'success';
            } elseif ($accion === 'editar' && $id > 0) {
                update(
                    "UPDATE categorias_gasto SET nombre = ?, descripcion = ?, activo = ? WHERE id = ?",
                    [$nombre, $descripcion, $activo, $id]
                );
                $mensaje = 'Categoría actualizada correctamente';
                $tipo_mensaje = 'success';
            } elseif ($accion === 'eliminar' && $id > 0) {
                // Verificar si hay gastos usando esta categoría
                $enUso = fetchOne("SELECT COUNT(*) as total FROM gastos WHERE categoria_id = ?", [$id]);
                if ($enUso['total'] > 0) {
                    $mensaje = 'No se puede eliminar: hay ' . $enUso['total'] . ' gasto(s) usando esta categoría';
                    $tipo_mensaje = 'warning';
                } else {
                    update("DELETE FROM categorias_gasto WHERE id = ?", [$id]);
                    $mensaje = 'Categoría eliminada correctamente';
                    $tipo_mensaje = 'success';
                }
            }
        } catch (Exception $e) {
            $mensaje = 'Error: ' . $e->getMessage();
            $tipo_mensaje = 'danger';
        }
    }
}

// Obtener categorías con conteo y suma de gastos
$categorias = fetchAll(
    "SELECT c.*,
            (SELECT COUNT(*) FROM gastos WHERE categoria_id = c.id) as num_gastos,
            (SELECT COALESCE(SUM(monto), 0) FROM gastos WHERE categoria_id = c.id) as total_gastos
     FROM categorias_gasto c
     ORDER BY c.nombre"
);
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/modules/configuracion/">Configuración</a></li>
                <li class="breadcrumb-item active">Categorías de Gasto</li>
            </ol>
        </nav>
        <h1 class="page-title"><i class="bi bi-tags me-2"></i>Categorías de Gasto</h1>
    </div>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCategoria" onclick="limpiarFormulario()">
        <i class="bi bi-plus-lg me-1"></i> Nueva Categoría
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
                        <th class="text-center">N° Gastos</th>
                        <th class="text-end">Total Gastado</th>
                        <th class="text-center">Estado</th>
                        <th width="120"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($categorias)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-4 text-muted">
                            No hay categorías registradas
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($categorias as $cat): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($cat['nombre']) ?></strong></td>
                        <td><?= htmlspecialchars($cat['descripcion'] ?? '-') ?></td>
                        <td class="text-center">
                            <span class="badge bg-secondary"><?= $cat['num_gastos'] ?></span>
                        </td>
                        <td class="text-end text-danger">
                            <?= formatMoney($cat['total_gastos']) ?>
                        </td>
                        <td class="text-center">
                            <?php if ($cat['activo']): ?>
                            <span class="badge bg-success">Activo</span>
                            <?php else: ?>
                            <span class="badge bg-secondary">Inactivo</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button type="button" class="btn btn-sm btn-outline-primary"
                                    onclick="editarCategoria(<?= htmlspecialchars(json_encode($cat)) ?>)">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <?php if ($cat['num_gastos'] == 0): ?>
                            <button type="button" class="btn btn-sm btn-outline-danger"
                                    onclick="eliminarCategoria(<?= $cat['id'] ?>, '<?= htmlspecialchars($cat['nombre']) ?>')">
                                <i class="bi bi-trash"></i>
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr class="table-light">
                        <th colspan="2">TOTAL</th>
                        <th class="text-center"><?= array_sum(array_column($categorias, 'num_gastos')) ?></th>
                        <th class="text-end"><?= formatMoney(array_sum(array_column($categorias, 'total_gastos'))) ?></th>
                        <th colspan="2"></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<!-- Modal Categoría -->
<div class="modal fade" id="modalCategoria" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="accion" id="accion" value="crear">
                <input type="hidden" name="id" id="categoria_id" value="0">

                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitulo">Nueva Categoría</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nombre <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="nombre" id="nombre" required
                               placeholder="Ej: Materiales">
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
                    <p>¿Eliminar la categoría <strong id="eliminar_nombre"></strong>?</p>
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
    document.getElementById('categoria_id').value = '0';
    document.getElementById('nombre').value = '';
    document.getElementById('descripcion').value = '';
    document.getElementById('activo').checked = true;
    document.getElementById('modalTitulo').textContent = 'Nueva Categoría';
}

function editarCategoria(cat) {
    document.getElementById('accion').value = 'editar';
    document.getElementById('categoria_id').value = cat.id;
    document.getElementById('nombre').value = cat.nombre;
    document.getElementById('descripcion').value = cat.descripcion || '';
    document.getElementById('activo').checked = cat.activo == 1;
    document.getElementById('modalTitulo').textContent = 'Editar Categoría';
    new bootstrap.Modal(document.getElementById('modalCategoria')).show();
}

function eliminarCategoria(id, nombre) {
    document.getElementById('eliminar_id').value = id;
    document.getElementById('eliminar_nombre').textContent = nombre;
    new bootstrap.Modal(document.getElementById('modalEliminar')).show();
}
</script>
HTML;

require_once dirname(dirname(__DIR__)) . '/includes/footer.php';
?>
