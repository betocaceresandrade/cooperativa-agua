<?php
/**
 * Rendir Fondo
 * Permite registrar gastos contra un fondo entregado
 */

require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/config/config.php';
require_once dirname(dirname(__DIR__)) . '/includes/functions.php';
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
requireLogin();

$id = intval($_GET['id'] ?? 0);
$fondo = fetchOne("SELECT * FROM fondos_rendir WHERE id = ?", [$id]);

if (!$fondo) {
    setFlash('error', 'Fondo no encontrado');
    redirect('/modules/fondos-rendir/');
}

// Si ya está rendido, redirigir a ver
if ($fondo['estado'] === 'rendido') {
    redirect('/modules/fondos-rendir/ver.php?id=' . $id);
}

$categorias = getCategoriasGasto();
$pendiente = $fondo['monto'] - $fondo['monto_rendido'];
$errors = [];

// Agregar gasto a la rendición
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['agregar_gasto'])) {
    $categoria_id = intval($_POST['categoria_id'] ?? 0);
    $fecha = $_POST['fecha'] ?? date('Y-m-d');
    $concepto = sanitize($_POST['concepto'] ?? '');
    $monto = floatval($_POST['monto'] ?? 0);
    $numero_recibo = sanitize($_POST['numero_recibo'] ?? '');

    if ($categoria_id <= 0) {
        $errors[] = 'Seleccione una categoría';
    }
    if (empty($concepto)) {
        $errors[] = 'Ingrese el concepto';
    }
    if ($monto <= 0) {
        $errors[] = 'El monto debe ser mayor a 0';
    }

    if (empty($errors)) {
        try {
            // Registrar gasto asociado al fondo
            insert(
                "INSERT INTO gastos (categoria_id, fecha, concepto, monto, numero_recibo_proveedor, metodo_pago, fondo_rendir_id)
                 VALUES (?, ?, ?, ?, ?, 'efectivo', ?)",
                [$categoria_id, $fecha, $concepto, $monto, $numero_recibo ?: null, $id]
            );

            // Actualizar monto rendido
            $nuevoRendido = $fondo['monto_rendido'] + $monto;
            $nuevoEstado = $nuevoRendido >= $fondo['monto'] ? 'rendido' : 'parcial';
            
            update(
                "UPDATE fondos_rendir SET monto_rendido = ?, estado = ? WHERE id = ?",
                [$nuevoRendido, $nuevoEstado, $id]
            );

            setFlash('success', 'Gasto agregado a la rendición');
            redirect('/modules/fondos-rendir/rendir.php?id=' . $id);

        } catch (Exception $e) {
            $errors[] = 'Error al guardar: ' . $e->getMessage();
        }
    }
}

// Cerrar rendición
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cerrar_rendicion'])) {
    update("UPDATE fondos_rendir SET estado = 'rendido', fecha_rendicion = CURDATE() WHERE id = ?", [$id]);
    setFlash('success', 'Rendición cerrada correctamente');
    redirect('/modules/fondos-rendir/');
}

// Recargar datos después de posibles cambios
$fondo = fetchOne("SELECT * FROM fondos_rendir WHERE id = ?", [$id]);
$pendiente = $fondo['monto'] - $fondo['monto_rendido'];
$porcentaje = $fondo['monto'] > 0 ? min(100, ($fondo['monto_rendido'] / $fondo['monto']) * 100) : 0;

$gastosDelFondo = fetchAll(
    "SELECT g.*, c.nombre as categoria_nombre
     FROM gastos g
     JOIN categorias_gasto c ON g.categoria_id = c.id
     WHERE g.fondo_rendir_id = ?
     ORDER BY g.fecha DESC, g.id DESC",
    [$id]
);

$pageTitle = 'Rendir Fondo';
require_once dirname(dirname(__DIR__)) . '/includes/header.php';
?>

<div class="page-header">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/modules/fondos-rendir/">Salidas de Caja</a></li>
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/modules/fondos-rendir/ver.php?id=<?= $id ?>">#<?= $id ?></a></li>
            <li class="breadcrumb-item active">Rendir</li>
        </ol>
    </nav>
    <h1 class="page-title">Rendir Fondo - <?= htmlspecialchars($fondo['beneficiario']) ?></h1>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
    <ul class="mb-0">
        <?php foreach ($errors as $error): ?>
        <li><?= htmlspecialchars($error) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-8">
        <!-- Info del Fondo -->
        <div class="card mb-4">
            <div class="card-header">
                <i class="bi bi-wallet2 me-2"></i>Información del Fondo
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <small class="text-muted">Fecha Entrega</small>
                        <div class="fw-bold"><?= formatDate($fondo['fecha_entrega']) ?></div>
                    </div>
                    <div class="col-md-5">
                        <small class="text-muted">Beneficiario</small>
                        <div class="fw-bold"><?= htmlspecialchars($fondo['beneficiario']) ?></div>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted">Concepto</small>
                        <div><?= htmlspecialchars($fondo['concepto'] ?? '-') ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Agregar Gasto -->
        <div class="card mb-4 border-primary">
            <div class="card-header bg-primary text-white">
                <i class="bi bi-plus-circle me-2"></i>Agregar Gasto a la Rendición
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Categoría <span class="text-danger">*</span></label>
                            <select class="form-select" name="categoria_id" required>
                                <option value="">Seleccionar...</option>
                                <?php foreach ($categorias as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= ($_POST['categoria_id'] ?? '') == $cat['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['nombre']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Fecha</label>
                            <input type="date" class="form-control" name="fecha" 
                                   value="<?= $_POST['fecha'] ?? date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">N° Recibo</label>
                            <input type="text" class="form-control" name="numero_recibo" 
                                   value="<?= htmlspecialchars($_POST['numero_recibo'] ?? '') ?>"
                                   placeholder="Opcional">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Concepto <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="concepto" 
                                   value="<?= htmlspecialchars($_POST['concepto'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Monto <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">Bs</span>
                                <input type="number" step="0.01" min="0.01"
                                       class="form-control" name="monto" 
                                       value="<?= $_POST['monto'] ?? '' ?>" required>
                            </div>
                        </div>
                        <div class="col-12">
                            <button type="submit" name="agregar_gasto" value="1" class="btn btn-primary">
                                <i class="bi bi-plus me-1"></i> Agregar Gasto
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Gastos Registrados -->
        <div class="card">
            <div class="card-header">
                <i class="bi bi-list-ul me-2"></i>Gastos Registrados (<?= count($gastosDelFondo) ?>)
            </div>
            <div class="card-body p-0">
                <?php if (empty($gastosDelFondo)): ?>
                <div class="text-center py-4 text-muted">
                    <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                    No hay gastos registrados en esta rendición
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>N° Recibo</th>
                                <th>Categoría</th>
                                <th>Concepto</th>
                                <th class="text-end">Monto</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($gastosDelFondo as $gasto): ?>
                            <tr>
                                <td><?= formatDate($gasto['fecha']) ?></td>
                                <td><?= htmlspecialchars($gasto['numero_recibo_proveedor'] ?? '-') ?></td>
                                <td><span class="badge bg-secondary"><?= htmlspecialchars($gasto['categoria_nombre']) ?></span></td>
                                <td><?= htmlspecialchars($gasto['concepto']) ?></td>
                                <td class="text-end"><?= formatMoney($gasto['monto']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="table-light">
                                <td colspan="4" class="text-end"><strong>Total Rendido:</strong></td>
                                <td class="text-end"><strong class="text-success"><?= formatMoney($fondo['monto_rendido']) ?></strong></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <!-- Resumen -->
        <div class="card sticky-top" style="top: 80px;">
            <div class="card-header">
                <i class="bi bi-calculator me-2"></i>Resumen
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2">
                    <span>Monto Entregado:</span>
                    <strong><?= formatMoney($fondo['monto']) ?></strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>Monto Rendido:</span>
                    <strong class="text-success"><?= formatMoney($fondo['monto_rendido']) ?></strong>
                </div>
                <hr>
                <div class="d-flex justify-content-between mb-3">
                    <span>Saldo Pendiente:</span>
                    <?php if ($pendiente > 0): ?>
                    <strong class="text-danger fs-4"><?= formatMoney($pendiente) ?></strong>
                    <?php elseif ($pendiente < 0): ?>
                    <strong class="text-warning fs-4">-<?= formatMoney(abs($pendiente)) ?></strong>
                    <?php else: ?>
                    <strong class="text-success fs-4">Bs 0.00</strong>
                    <?php endif; ?>
                </div>

                <!-- Barra de progreso -->
                <div class="progress mb-3" style="height: 20px;">
                    <div class="progress-bar <?= $pendiente <= 0 ? 'bg-success' : 'bg-primary' ?>" style="width: <?= $porcentaje ?>%">
                        <?= number_format($porcentaje, 0) ?>%
                    </div>
                </div>

                <?php if ($pendiente < 0): ?>
                <div class="alert alert-warning small mb-3">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    Se gastó más de lo entregado. Se debe reponer <?= formatMoney(abs($pendiente)) ?>.
                </div>
                <?php elseif ($pendiente > 0): ?>
                <div class="alert alert-info small mb-3">
                    <i class="bi bi-info-circle me-1"></i>
                    Queda un saldo de <?= formatMoney($pendiente) ?> por rendir o devolver.
                </div>
                <?php endif; ?>

                <form method="POST" action="" onsubmit="return confirm('¿Cerrar la rendición?');">
                    <button type="submit" name="cerrar_rendicion" value="1" class="btn btn-success w-100 mb-2">
                        <i class="bi bi-check-circle me-1"></i> Cerrar Rendición
                    </button>
                </form>

                <a href="<?= BASE_URL ?>/modules/fondos-rendir/" class="btn btn-outline-secondary w-100">
                    Volver
                </a>
            </div>
        </div>
    </div>
</div>

<?php require_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
