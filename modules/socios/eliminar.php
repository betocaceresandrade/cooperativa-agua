<?php
/**
 * Eliminar Socio
 * Solo permite eliminar si no tiene acciones asociadas
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

// Verificar si tiene acciones
$numAcciones = fetchOne("SELECT COUNT(*) as total FROM acciones WHERE socio_id = ?", [$id]);
$tieneAcciones = ($numAcciones['total'] ?? 0) > 0;

if ($tieneAcciones) {
    setFlash('error', 'No se puede eliminar el socio porque tiene acciones asociadas. Primero elimine o transfiera las acciones.');
    redirect('/modules/socios/ver.php?id=' . $id);
}

// Procesar eliminación
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirmar'])) {
    try {
        // Eliminar otros ingresos del socio (sin acción)
        execute("DELETE FROM otros_ingresos WHERE socio_id = ? AND accion_id IS NULL", [$id]);
        
        // Eliminar el socio
        execute("DELETE FROM socios WHERE id = ?", [$id]);
        
        setFlash('success', 'Socio eliminado correctamente');
        redirect('/modules/socios/');
        
    } catch (Exception $e) {
        setFlash('error', 'Error al eliminar: ' . $e->getMessage());
        redirect('/modules/socios/ver.php?id=' . $id);
    }
}

$pageTitle = 'Eliminar Socio';
require_once dirname(dirname(__DIR__)) . '/includes/header.php';
?>

<div class="page-header">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/modules/socios/">Socios</a></li>
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/modules/socios/ver.php?id=<?= $id ?>"><?= htmlspecialchars($socio['numero_socio']) ?></a></li>
            <li class="breadcrumb-item active">Eliminar</li>
        </ol>
    </nav>
    <h1 class="page-title">Eliminar Socio</h1>
</div>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card border-danger">
            <div class="card-header bg-danger text-white">
                <i class="bi bi-exclamation-triangle me-2"></i>Confirmar Eliminación
            </div>
            <div class="card-body text-center">
                <p class="mb-3">¿Está seguro que desea eliminar el siguiente socio?</p>
                
                <div class="bg-light p-3 rounded mb-4">
                    <h5 class="mb-1"><?= htmlspecialchars($socio['nombre']) ?></h5>
                    <p class="text-muted mb-0">N° Socio: <?= htmlspecialchars($socio['numero_socio']) ?></p>
                </div>
                
                <p class="text-danger"><strong>Esta acción no se puede deshacer.</strong></p>
                
                <form method="POST" class="d-inline">
                    <input type="hidden" name="confirmar" value="1">
                    <button type="submit" class="btn btn-danger me-2">
                        <i class="bi bi-trash me-1"></i> Sí, Eliminar
                    </button>
                    <a href="<?= BASE_URL ?>/modules/socios/ver.php?id=<?= $id ?>" class="btn btn-secondary">
                        <i class="bi bi-x-circle me-1"></i> Cancelar
                    </a>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
