<?php
/**
 * Configuración: Otros Ingresos
 * CRUD de items adicionales (reconexiones, multas, instalaciones, etc.)
 */

$pageTitle = 'Configurar Otros Ingresos';
require_once dirname(dirname(__DIR__)) . '/includes/header.php';
require_once dirname(dirname(__DIR__)) . '/includes/functions.php';

$mensaje = '';
$tipo_mensaje = '';

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    $id = intval($_POST['id'] ?? 0);
    $nombre = trim($_POST['nombre'] ?? '');
    $monto_default = !empty($_POST['monto_default']) ? floatval($_POST['monto_default']) : null;
    $descripcion = trim($_POST['descripcion'] ?? '');
    $activo = isset($_POST['activo']) ? 1 : 0;

    if (empty($nombre)) {
        $mensaje = 'El nombre es obligatorio';
        $tipo_mensaje = 'danger';
    } else {
        try {
            if ($accion === 'crear') {
                insert(
                    "INSERT INTO items_adicionales (nombre, monto_default, descripcion, activo) VALUES (?, ?, ?, ?)",
                    [$nombre, $monto_default, $descripcion, $activo]
                );
                $mensaje = 'Concepto creado correctamente';
                $tipo_mensaje = 'success';
            } elseif ($accion === 'editar' && $id > 0) {
                update(
                    "UPDATE items_adicionales SET nombre = ?, monto_default = ?, descripcion = ?, activo = ? WHERE id = ?",
                    [$nombre, $monto_default, $descripcion, $activo, $id]
                );
                $mensaje = 'Concepto actualizado correctamente';
                $tipo_mensaje = 'success';
            } elseif ($accion === 'eliminar' && $id > 0) {
                // Verificar si hay pagos usando este item
                $enUso = fetchOne("SELECT COUNT(*) as total FROM pago_items_adicionales WHERE nombre_item = (SELECT nombre FROM items_adicionales WHERE id = ?)", [$id]);
                if ($enUso && $enUso['total'] > 0) {
                    $mensaje = 'No se puede eliminar: hay ' . $enUso['total'] . ' pago(s) con este concepto';
                    $tipo_mensaje = 'warning';
                } else {
                    update("DELETE FROM items_adicionales WHERE id = ?", [$id]);
                    $mensaje = 'Concepto eliminado correctamente';
                    $tipo_mensaje = 'success';
                }
            }
        } catch (Exception $e) {
            $mensaje = 'Error: ' . $e->getMessage();
            $tipo_mensaje = 'danger';
        }
    }
}

// Obtener items adicionales
$items = fetchAll(
    "SELECT * FROM items_adicionales ORDER BY nombre"
);
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/modules/configuracion/">Configuración</a></li>
                <li class="breadcrumb-item active">Otros Ingresos</li>
            </ol>
        </nav>
        <h1 class="page-title"><i class="bi bi-cash-coin me-2"></i>Otros Ingresos</h1>
        <p class="text-muted">Conceptos adicionales: reconexiones, multas, instalaciones, etc.</p>
    </div>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalItem" onclick="limpiarFormulario()">
        <i class="bi bi-plus-lg me-1"></i> Nuevo Concepto
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
                        <th class="text-end">Monto Sugerido</th>
                        <th>Descripción</th>
                        <th class="text-center">Estado</th>
                        <th width="120"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($items)): ?>
                    <tr>
                        <td colspan="5" class="text-center py-4 text-muted">
                            No hay conceptos registrados
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($items as $item): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($item['nombre']) ?></strong></td>
                        <td class="text-end">
                            <?php if ($item['monto_default']): ?>
                            <?= formatMoney($item['monto_default']) ?>
                            <?php else: ?>
                            <span class="text-muted">Variable</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($item['descripcion'] ?? '-') ?></td>
                        <td class="text-center">
                            <?php if ($item['activo']): ?>
                            <span class="badge bg-success">Activo</span>
                            <?php else: ?>
                            <span class="badge bg-secondary">Inactivo</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button type="button" class="btn btn-sm btn-outline-primary"
                                    onclick="editarItem(<?= htmlspecialchars(json_encode($item)) ?>)">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-danger"
                                    onclick="eliminarItem(<?= $item['id'] ?>, '<?= htmlspecialchars($item['nombre']) ?>')">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="alert alert-info mt-4">
    <i class="bi bi-info-circle me-2"></i>
    <strong>Nota:</strong> Estos conceptos aparecerán como opciones adicionales al momento de cobrar consumos.
    El monto sugerido se puede modificar al momento del cobro.
</div>

<!-- Modal Item -->
<div class="modal fade" id="modalItem" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="accion" id="accion" value="crear">
                <input type="hidden" name="id" id="item_id" value="0">

                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitulo">Nuevo Concepto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nombre <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="nombre" id="nombre" required
                               placeholder="Ej: Reconexión">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Monto sugerido (Bs.)</label>
                        <input type="number" class="form-control" name="monto_default" id="monto_default"
                               step="0.50" min="0" placeholder="Dejar vacío si es variable">
                        <small class="text-muted">Se puede modificar al momento del cobro</small>
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
                    <p>¿Eliminar el concepto <strong id="eliminar_nombre"></strong>?</p>
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
    document.getElementById('item_id').value = '0';
    document.getElementById('nombre').value = '';
    document.getElementById('monto_default').value = '';
    document.getElementById('descripcion').value = '';
    document.getElementById('activo').checked = true;
    document.getElementById('modalTitulo').textContent = 'Nuevo Concepto';
}

function editarItem(item) {
    document.getElementById('accion').value = 'editar';
    document.getElementById('item_id').value = item.id;
    document.getElementById('nombre').value = item.nombre;
    document.getElementById('monto_default').value = item.monto_default || '';
    document.getElementById('descripcion').value = item.descripcion || '';
    document.getElementById('activo').checked = item.activo == 1;
    document.getElementById('modalTitulo').textContent = 'Editar Concepto';
    new bootstrap.Modal(document.getElementById('modalItem')).show();
}

function eliminarItem(id, nombre) {
    document.getElementById('eliminar_id').value = id;
    document.getElementById('eliminar_nombre').textContent = nombre;
    new bootstrap.Modal(document.getElementById('modalEliminar')).show();
}
</script>
HTML;

require_once dirname(dirname(__DIR__)) . '/includes/footer.php';
?>
