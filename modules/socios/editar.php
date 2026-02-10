<?php
/**
 * Editar Socio - Simplificado
 * Solo datos básicos del socio. Estado/zona/dirección pertenecen a las acciones.
 */

require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/config/config.php';
require_once dirname(dirname(__DIR__)) . '/includes/functions.php';
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
requireLogin();

$id = intval($_GET['id'] ?? 0);
$socio = getSocioById($id);

if (!$socio) {
    setFlash('error', 'Socio no encontrado');
    redirect('/modules/socios/');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $numero_socio = sanitize($_POST['numero_socio'] ?? '');
    $nombre = sanitize($_POST['nombre'] ?? '');
    $persona_encargada = sanitize($_POST['persona_encargada'] ?? '');
    $celular = sanitize($_POST['celular'] ?? '');
    $notas = sanitize($_POST['notas'] ?? '');

    // Validaciones
    if (empty($numero_socio)) {
        $errors[] = 'El número de socio es requerido';
    } else {
        $existe = fetchOne(
            "SELECT id FROM socios WHERE numero_socio = ? AND id != ?",
            [$numero_socio, $id]
        );
        if ($existe) {
            $errors[] = 'El número de socio ya está registrado por otro socio';
        }
    }

    if (empty($nombre)) {
        $errors[] = 'El nombre es requerido';
    }

    if (empty($errors)) {
        try {
            update(
                "UPDATE socios SET numero_socio = ?, nombre = ?, persona_encargada = ?, celular = ?, notas = ? WHERE id = ?",
                [$numero_socio, $nombre, $persona_encargada, $celular, $notas, $id]
            );

            setFlash('success', 'Socio actualizado correctamente');
            redirect('/modules/socios/ver.php?id=' . $id);

        } catch (Exception $e) {
            $errors[] = 'Error al actualizar: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Editar Socio';
require_once dirname(dirname(__DIR__)) . '/includes/header.php';
?>

<div class="page-header">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/modules/socios/">Socios</a></li>
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/modules/socios/ver.php?id=<?= $id ?>"><?= htmlspecialchars($socio['numero_socio']) ?></a></li>
            <li class="breadcrumb-item active">Editar</li>
        </ol>
    </nav>
    <h1 class="page-title">Editar Socio: <?= htmlspecialchars($socio['nombre']) ?></h1>
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

<form method="POST" action="">
    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-person me-2"></i>Datos del Socio
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Número de Socio <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="numero_socio"
                                   value="<?= htmlspecialchars($_POST['numero_socio'] ?? $socio['numero_socio']) ?>" required>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Nombre Completo <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="nombre"
                                   value="<?= htmlspecialchars($_POST['nombre'] ?? $socio['nombre']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Persona Encargada</label>
                            <input type="text" class="form-control" name="persona_encargada"
                                   value="<?= htmlspecialchars($_POST['persona_encargada'] ?? $socio['persona_encargada']) ?>"
                                   placeholder="Si es diferente al titular">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Celular</label>
                            <input type="text" class="form-control" name="celular"
                                   value="<?= htmlspecialchars($_POST['celular'] ?? $socio['celular']) ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notas</label>
                            <textarea class="form-control" name="notas" rows="2"><?= htmlspecialchars($_POST['notas'] ?? $socio['notas']) ?></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card sticky-top" style="top: 80px;">
                <div class="card-body">
                    <button type="submit" class="btn btn-primary w-100 mb-2">
                        <i class="bi bi-check-circle me-1"></i> Guardar Cambios
                    </button>
                    <a href="<?= BASE_URL ?>/modules/socios/ver.php?id=<?= $id ?>" class="btn btn-outline-secondary w-100">
                        <i class="bi bi-x-circle me-1"></i> Cancelar
                    </a>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header">Información</div>
                <div class="card-body">
                    <small class="text-muted">
                        <p class="mb-2"><strong>Creado:</strong> <?= formatDate($socio['created_at'], 'd/m/Y H:i') ?></p>
                        <p class="mb-0"><strong>Actualizado:</strong> <?= formatDate($socio['updated_at'], 'd/m/Y H:i') ?></p>
                    </small>
                </div>
            </div>
        </div>
    </div>
</form>

<?php require_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
