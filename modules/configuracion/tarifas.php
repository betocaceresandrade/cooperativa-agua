<?php
/**
 * Configuración: Tarifas
 * CRUD de tipos de tarifa (categorías de servicio)
 */

$pageTitle = 'Configurar Tarifas';
require_once dirname(dirname(__DIR__)) . '/includes/header.php';
require_once dirname(dirname(__DIR__)) . '/includes/functions.php';

$mensaje = '';
$tipo_mensaje = '';

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    $id = intval($_POST['id'] ?? 0);
    $nombre = trim($_POST['nombre'] ?? '');
    $monto = floatval($_POST['monto'] ?? 0);
    $descripcion = trim($_POST['descripcion'] ?? '');
    $activo = isset($_POST['activo']) ? 1 : 0;

    if (empty($nombre)) {
        $mensaje = 'El nombre es obligatorio';
        $tipo_mensaje = 'danger';
    } elseif ($monto <= 0) {
        $mensaje = 'El monto debe ser mayor a cero';
        $tipo_mensaje = 'danger';
    } else {
        try {
            if ($accion === 'crear') {
                insert(
                    "INSERT INTO tipos_tarifa (nombre, monto, descripcion, activo) VALUES (?, ?, ?, ?)",
                    [$nombre, $monto, $descripcion, $activo]
                );
                $mensaje = 'Tarifa creada correctamente';
                $tipo_mensaje = 'success';
            } elseif ($accion === 'editar' && $id > 0) {
                update(
                    "UPDATE tipos_tarifa SET nombre = ?, monto = ?, descripcion = ?, activo = ? WHERE id = ?",
                    [$nombre, $monto, $descripcion, $activo, $id]
                );
                $mensaje = 'Tarifa actualizada correctamente';
                $tipo_mensaje = 'success';
            } elseif ($accion === 'eliminar' && $id > 0) {
                // Verificar si hay acciones usando esta tarifa
                $enUso = fetchOne("SELECT COUNT(*) as total FROM acciones WHERE tipo_tarifa_id = ?", [$id]);
                if ($enUso['total'] > 0) {
                    $mensaje = 'No se puede eliminar: hay ' . $enUso['total'] . ' acción(es) usando esta tarifa';
                    $tipo_mensaje = 'warning';
                } else {
                    update("DELETE FROM tipos_tarifa WHERE id = ?", [$id]);
                    $mensaje = 'Tarifa eliminada correctamente';
                    $tipo_mensaje = 'success';
                }
            }
        } catch (Exception $e) {
            $mensaje = 'Error: ' . $e->getMessage();
            $tipo_mensaje = 'danger';
        }
    }
}

// Obtener tarifas con conteo de acciones
$tarifas = fetchAll(
    "SELECT t.*,
            (SELECT COUNT(*) FROM acciones WHERE tipo_tarifa_id = t.id) as num_acciones
     FROM tipos_tarifa t
     ORDER BY t.monto, t.nombre"
);

$totalRecaudacion = 0;
foreach ($tarifas as $t) {
    $totalRecaudacion += $t['monto'] * $t['num_acciones'];
}
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/modules/configuracion/">Configuración</a></li>
                <li class="breadcrumb-item active">Tarifas</li>
            </ol>
        </nav>
        <h1 class="page-title"><i class="bi bi-currency-dollar me-2"></i>Tarifas</h1>
    </div>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalTarifa" onclick="limpiarFormulario()">
        <i class="bi bi-plus-lg me-1"></i> Nueva Tarifa
    </button>
</div>

<?php if ($mensaje): ?>
<div class="alert alert-<?= $tipo_mensaje ?> alert-dismissible fade show" role="alert">
    <?= htmlspecialchars($mensaje) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Resumen -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card bg-primary text-white">
            <div class="card-body text-center">
                <div class="fs-3 fw-bold"><?= count($tarifas) ?></div>
                <div>Tipos de Tarifa</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-success text-white">
            <div class="card-body text-center">
                <div class="fs-3 fw-bold"><?= array_sum(array_column($tarifas, 'num_acciones')) ?></div>
                <div>Acciones Asignadas</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-info text-white">
            <div class="card-body text-center">
                <div class="fs-3 fw-bold"><?= formatMoney($totalRecaudacion) ?></div>
                <div>Recaudación Mensual Potencial</div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th class="text-end">Monto</th>
                        <th>Descripción</th>
                        <th class="text-center">Acciones</th>
                        <th class="text-end">Subtotal Mensual</th>
                        <th class="text-center">Estado</th>
                        <th width="120"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($tarifas)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">
                            No hay tarifas registradas
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($tarifas as $tarifa): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($tarifa['nombre']) ?></strong></td>
                        <td class="text-end"><?= formatMoney($tarifa['monto']) ?></td>
                        <td><?= htmlspecialchars($tarifa['descripcion'] ?? '-') ?></td>
                        <td class="text-center">
                            <span class="badge bg-primary"><?= $tarifa['num_acciones'] ?></span>
                        </td>
                        <td class="text-end fw-bold">
                            <?= formatMoney($tarifa['monto'] * $tarifa['num_acciones']) ?>
                        </td>
                        <td class="text-center">
                            <?php if ($tarifa['activo']): ?>
                            <span class="badge bg-success">Activo</span>
                            <?php else: ?>
                            <span class="badge bg-secondary">Inactivo</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button type="button" class="btn btn-sm btn-outline-primary"
                                    onclick="editarTarifa(<?= htmlspecialchars(json_encode($tarifa)) ?>)">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <?php if ($tarifa['num_acciones'] == 0): ?>
                            <button type="button" class="btn btn-sm btn-outline-danger"
                                    onclick="eliminarTarifa(<?= $tarifa['id'] ?>, '<?= htmlspecialchars($tarifa['nombre']) ?>')">
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
                        <th colspan="4">TOTAL</th>
                        <th class="text-end"><?= formatMoney($totalRecaudacion) ?></th>
                        <th colspan="2"></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<!-- Modal Tarifa -->
<div class="modal fade" id="modalTarifa" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="accion" id="accion" value="crear">
                <input type="hidden" name="id" id="tarifa_id" value="0">

                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitulo">Nueva Tarifa</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nombre <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="nombre" id="nombre" required
                               placeholder="Ej: Domiciliaria">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Monto mensual (Bs.) <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" name="monto" id="monto" required
                               step="0.50" min="0.01" placeholder="12.50">
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
                    <p>¿Eliminar la tarifa <strong id="eliminar_nombre"></strong>?</p>
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
    document.getElementById('tarifa_id').value = '0';
    document.getElementById('nombre').value = '';
    document.getElementById('monto').value = '';
    document.getElementById('descripcion').value = '';
    document.getElementById('activo').checked = true;
    document.getElementById('modalTitulo').textContent = 'Nueva Tarifa';
}

function editarTarifa(tarifa) {
    document.getElementById('accion').value = 'editar';
    document.getElementById('tarifa_id').value = tarifa.id;
    document.getElementById('nombre').value = tarifa.nombre;
    document.getElementById('monto').value = tarifa.monto;
    document.getElementById('descripcion').value = tarifa.descripcion || '';
    document.getElementById('activo').checked = tarifa.activo == 1;
    document.getElementById('modalTitulo').textContent = 'Editar Tarifa';
    new bootstrap.Modal(document.getElementById('modalTarifa')).show();
}

function eliminarTarifa(id, nombre) {
    document.getElementById('eliminar_id').value = id;
    document.getElementById('eliminar_nombre').textContent = nombre;
    new bootstrap.Modal(document.getElementById('modalEliminar')).show();
}
</script>
HTML;

require_once dirname(dirname(__DIR__)) . '/includes/footer.php';
?>
