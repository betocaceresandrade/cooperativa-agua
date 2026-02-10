<?php
/**
 * Crear Nuevo Socio - Simplificado
 * Solo datos básicos del socio. Las acciones se crean por separado.
 */

require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/config/config.php';
require_once dirname(dirname(__DIR__)) . '/includes/functions.php';
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
requireLogin();

$nextNumero = getNextNumeroSocio();
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
        $existe = fetchOne("SELECT id FROM socios WHERE numero_socio = ?", [$numero_socio]);
        if ($existe) {
            $errors[] = 'El número de socio ya está registrado';
        }
    }

    if (empty($nombre)) {
        $errors[] = 'El nombre es requerido';
    }

    if (empty($errors)) {
        try {
            $socio_id = insert(
                "INSERT INTO socios (numero_socio, nombre, persona_encargada, celular, notas, fecha_alta)
                 VALUES (?, ?, ?, ?, ?, CURDATE())",
                [$numero_socio, $nombre, $persona_encargada, $celular, $notas]
            );

            setFlash('success', 'Socio creado correctamente. Ahora puede agregar acciones de agua.');
            redirect('/modules/socios/ver.php?id=' . $socio_id);

        } catch (Exception $e) {
            $errors[] = 'Error al guardar: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Nuevo Socio';
require_once dirname(dirname(__DIR__)) . '/includes/header.php';
?>

<div class="page-header">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/modules/socios/">Socios</a></li>
            <li class="breadcrumb-item active">Nuevo</li>
        </ol>
    </nav>
    <h1 class="page-title">Registrar Nuevo Socio</h1>
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
                                   value="<?= htmlspecialchars($_POST['numero_socio'] ?? $nextNumero) ?>" required>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Nombre Completo <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="nombre"
                                   value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Persona Encargada</label>
                            <input type="text" class="form-control" name="persona_encargada"
                                   value="<?= htmlspecialchars($_POST['persona_encargada'] ?? '') ?>"
                                   placeholder="Si es diferente al titular">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Celular</label>
                            <input type="text" class="form-control" name="celular"
                                   value="<?= htmlspecialchars($_POST['celular'] ?? '') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notas</label>
                            <textarea class="form-control" name="notas" rows="2"
                                      placeholder="Observaciones adicionales"><?= htmlspecialchars($_POST['notas'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card sticky-top" style="top: 80px;">
                <div class="card-body">
                    <button type="submit" class="btn btn-primary w-100 mb-2">
                        <i class="bi bi-check-circle me-1"></i> Guardar Socio
                    </button>
                    <a href="<?= BASE_URL ?>/modules/socios/" class="btn btn-outline-secondary w-100">
                        <i class="bi bi-x-circle me-1"></i> Cancelar
                    </a>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header">
                    <i class="bi bi-info-circle me-1"></i> Información
                </div>
                <div class="card-body">
                    <small class="text-muted">
                        <p class="mb-2"><strong>Socio:</strong> Persona o entidad que pertenece a la cooperativa.</p>
                        <p class="mb-0"><strong>Siguiente paso:</strong> Después de crear el socio, podrá agregar sus acciones de agua desde la vista del socio.</p>
                    </small>
                </div>
            </div>
        </div>
    </div>
</form>

<?php require_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
