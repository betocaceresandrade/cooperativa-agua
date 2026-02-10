<?php
/**
 * Editar Fondo a Rendir
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

$errors = [];

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $beneficiario = sanitize($_POST['beneficiario'] ?? '');
    $concepto = sanitize($_POST['concepto'] ?? '');
    $monto = floatval($_POST['monto'] ?? 0);
    $fecha_entrega = $_POST['fecha_entrega'] ?? '';

    // Validaciones
    if (empty($beneficiario)) {
        $errors[] = 'El beneficiario es requerido';
    }
    if ($monto <= 0) {
        $errors[] = 'El monto debe ser mayor a 0';
    }
    if ($monto < $fondo['monto_rendido']) {
        $errors[] = 'El monto no puede ser menor al ya rendido (' . formatMoney($fondo['monto_rendido']) . ')';
    }
    if (empty($fecha_entrega)) {
        $errors[] = 'La fecha de entrega es requerida';
    }

    if (empty($errors)) {
        try {
            // Determinar estado automáticamente
            if ($fondo['monto_rendido'] >= $monto) {
                $estado = 'rendido';
            } elseif ($fondo['monto_rendido'] > 0) {
                $estado = 'parcial';
            } else {
                $estado = 'pendiente';
            }

            update(
                "UPDATE fondos_rendir SET
                    beneficiario = ?, concepto = ?, monto = ?,
                    fecha_entrega = ?, estado = ?
                 WHERE id = ?",
                [$beneficiario, $concepto, $monto, $fecha_entrega, $estado, $id]
            );

            setFlash('success', 'Fondo actualizado correctamente');
            redirect('/modules/fondos-rendir/ver.php?id=' . $id);

        } catch (Exception $e) {
            $errors[] = 'Error al actualizar: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Editar Fondo a Rendir';
require_once dirname(dirname(__DIR__)) . '/includes/header.php';
?>

<div class="page-header">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/modules/fondos-rendir/">Fondos a Rendir</a></li>
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/modules/fondos-rendir/ver.php?id=<?= $id ?>">#<?= $id ?></a></li>
            <li class="breadcrumb-item active">Editar</li>
        </ol>
    </nav>
    <h1 class="page-title">Editar Fondo a Rendir</h1>
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
        <div class="card">
            <div class="card-header">
                <i class="bi bi-pencil me-2"></i>Datos del Fondo
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Beneficiario <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="beneficiario"
                                   value="<?= htmlspecialchars($_POST['beneficiario'] ?? $fondo['beneficiario']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Fecha de Entrega <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="fecha_entrega"
                                   value="<?= htmlspecialchars($_POST['fecha_entrega'] ?? $fondo['fecha_entrega']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Monto Total <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">Bs</span>
                                <input type="number" step="0.01" class="form-control" name="monto"
                                       value="<?= $_POST['monto'] ?? $fondo['monto'] ?>"
                                       min="<?= $fondo['monto_rendido'] ?>" required>
                            </div>
                            <?php if ($fondo['monto_rendido'] > 0): ?>
                            <small class="text-muted">Mínimo: <?= formatMoney($fondo['monto_rendido']) ?> (ya rendido)</small>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Estado Actual</label>
                            <div class="form-control bg-light">
                                <?php if ($fondo['estado'] === 'pendiente'): ?>
                                    <span class="badge bg-warning text-dark">Pendiente</span>
                                <?php elseif ($fondo['estado'] === 'parcial'): ?>
                                    <span class="badge bg-info">Parcialmente Rendido</span>
                                <?php else: ?>
                                    <span class="badge bg-success">Rendido</span>
                                <?php endif; ?>
                                <small class="text-muted ms-2">(se actualiza automáticamente)</small>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Concepto / Propósito</label>
                            <textarea class="form-control" name="concepto" rows="3"><?= htmlspecialchars($_POST['concepto'] ?? $fondo['concepto']) ?></textarea>
                        </div>
                        <div class="col-12">
                            <hr>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-circle me-1"></i> Guardar Cambios
                            </button>
                            <a href="<?= BASE_URL ?>/modules/fondos-rendir/ver.php?id=<?= $id ?>" class="btn btn-outline-secondary">
                                Cancelar
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">Información de Rendición</div>
            <div class="card-body">
                <table class="table table-sm table-borderless">
                    <tr>
                        <td class="text-muted">Monto Original:</td>
                        <td><strong><?= formatMoney($fondo['monto']) ?></strong></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Monto Rendido:</td>
                        <td><strong class="text-success"><?= formatMoney($fondo['monto_rendido']) ?></strong></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Saldo Pendiente:</td>
                        <td><strong class="text-danger"><?= formatMoney($fondo['monto'] - $fondo['monto_rendido']) ?></strong></td>
                    </tr>
                </table>

                <?php 
                $porcentaje = $fondo['monto'] > 0 ? ($fondo['monto_rendido'] / $fondo['monto']) * 100 : 0;
                ?>
                <div class="progress mb-2" style="height: 10px;">
                    <div class="progress-bar bg-success" style="width: <?= min($porcentaje, 100) ?>%"></div>
                </div>
                <small class="text-muted"><?= number_format($porcentaje, 1) ?>% rendido</small>
            </div>
        </div>

        <?php if ($fondo['monto_rendido'] > 0): ?>
        <div class="alert alert-info mt-3">
            <i class="bi bi-info-circle me-2"></i>
            <strong>Nota:</strong> El monto total no puede ser menor al ya rendido.
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
