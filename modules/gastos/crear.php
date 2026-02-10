<?php
/**
 * Crear/Editar Gasto (Comprobante de Egreso)
 * Los gastos de fondos a rendir se registran desde el módulo de rendición
 */

require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/config/config.php';
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_once dirname(dirname(__DIR__)) . '/includes/functions.php';

requireLogin();

$errors = [];
$gasto = null;
$esEdicion = false;

$id = intval($_GET['id'] ?? 0);
if ($id > 0) {
    $gasto = fetchOne("SELECT * FROM gastos WHERE id = ?", [$id]);
    if ($gasto) {
        $esEdicion = true;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $categoria_id = intval($_POST['categoria_id'] ?? 0);
    $fecha = $_POST['fecha'] ?? date('Y-m-d');
    $concepto = sanitize($_POST['concepto'] ?? '');
    $monto = floatval($_POST['monto'] ?? 0);
    $numero_recibo_proveedor = sanitize($_POST['numero_recibo_proveedor'] ?? '');
    $metodo_pago = $_POST['metodo_pago'] ?? 'efectivo';
    $notas = sanitize($_POST['notas'] ?? '');

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
            if ($esEdicion) {
                update(
                    "UPDATE gastos SET categoria_id = ?, fecha = ?, concepto = ?, monto = ?,
                     numero_recibo_proveedor = ?, metodo_pago = ?, notas = ? WHERE id = ?",
                    [$categoria_id, $fecha, $concepto, $monto, $numero_recibo_proveedor,
                     $metodo_pago, $notas, $gasto['id']]
                );
                setFlash('success', 'Gasto actualizado correctamente');
            } else {
                $gasto_id = insert(
                    "INSERT INTO gastos (categoria_id, fecha, concepto, monto, numero_recibo_proveedor, metodo_pago, notas)
                     VALUES (?, ?, ?, ?, ?, ?, ?)",
                    [$categoria_id, $fecha, $concepto, $monto, $numero_recibo_proveedor, $metodo_pago, $notas]
                );

                registrarMovimientoCaja('egreso', $concepto, $monto, $metodo_pago, 'gasto', $gasto_id);
                setFlash('success', 'Gasto registrado correctamente');
            }

            redirect('/modules/gastos/');

        } catch (Exception $e) {
            $errors[] = 'Error al guardar: ' . $e->getMessage();
        }
    }
}

$pageTitle = $esEdicion ? 'Editar Gasto' : 'Nuevo Gasto';
require_once dirname(dirname(__DIR__)) . '/includes/header.php';

$categorias = getCategoriasGasto();
?>

<div class="page-header">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/modules/gastos/">Gastos</a></li>
            <li class="breadcrumb-item active"><?= $esEdicion ? 'Editar' : 'Nuevo' ?></li>
        </ol>
    </nav>
    <h1 class="page-title"><?= $esEdicion ? 'Editar Gasto' : 'Registrar Gasto' ?></h1>
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

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body">
                <form method="POST" action="">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Categoría <span class="text-danger">*</span></label>
                            <select class="form-select" name="categoria_id" required>
                                <option value="">Seleccionar...</option>
                                <?php foreach ($categorias as $cat): ?>
                                <option value="<?= $cat['id'] ?>"
                                    <?= ($_POST['categoria_id'] ?? ($gasto['categoria_id'] ?? '')) == $cat['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['nombre']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Fecha <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="fecha"
                                   value="<?= htmlspecialchars($_POST['fecha'] ?? ($gasto['fecha'] ?? date('Y-m-d'))) ?>" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Concepto <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="concepto"
                                   value="<?= htmlspecialchars($_POST['concepto'] ?? ($gasto['concepto'] ?? '')) ?>"
                                   placeholder="Descripción del gasto" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Monto <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">Bs</span>
                                <input type="number" step="0.01" min="0.01" class="form-control" name="monto"
                                       value="<?= htmlspecialchars($_POST['monto'] ?? ($gasto['monto'] ?? '')) ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">N° Recibo/Factura</label>
                            <input type="text" class="form-control" name="numero_recibo_proveedor"
                                   value="<?= htmlspecialchars($_POST['numero_recibo_proveedor'] ?? ($gasto['numero_recibo_proveedor'] ?? '')) ?>"
                                   placeholder="Del proveedor">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Método de Pago</label>
                            <select class="form-select" name="metodo_pago">
                                <option value="efectivo" <?= ($_POST['metodo_pago'] ?? ($gasto['metodo_pago'] ?? '')) === 'efectivo' ? 'selected' : '' ?>>Efectivo</option>
                                <option value="qr" <?= ($_POST['metodo_pago'] ?? ($gasto['metodo_pago'] ?? '')) === 'qr' ? 'selected' : '' ?>>QR / Transferencia</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Notas</label>
                            <input type="text" class="form-control" name="notas"
                                   value="<?= htmlspecialchars($_POST['notas'] ?? ($gasto['notas'] ?? '')) ?>"
                                   placeholder="Observaciones">
                        </div>
                        <div class="col-12">
                            <hr>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-circle me-1"></i>
                                <?= $esEdicion ? 'Guardar Cambios' : 'Registrar Gasto' ?>
                            </button>
                            <a href="<?= BASE_URL ?>/modules/gastos/" class="btn btn-outline-secondary">
                                Cancelar
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
