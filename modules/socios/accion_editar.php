<?php
/**
 * Editar Acción
 */

require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/config/config.php';
require_once dirname(dirname(__DIR__)) . '/includes/functions.php';
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
requireLogin();

$id = intval($_GET['id'] ?? 0);
$accion = fetchOne(
    "SELECT a.*, s.nombre as socio_nombre, s.numero_socio
     FROM acciones a
     JOIN socios s ON a.socio_id = s.id
     WHERE a.id = ?",
    [$id]
);

if (!$accion) {
    setFlash('error', 'Acción no encontrada');
    redirect('/modules/socios/');
}

$zonas = getZonas();
$tarifas = getTiposTarifa();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $numero_accion = sanitize($_POST['numero_accion'] ?? '');
    $tipo_tarifa_id = intval($_POST['tipo_tarifa_id'] ?? 0);
    $zona_id = !empty($_POST['zona_id']) ? intval($_POST['zona_id']) : null;
    $direccion_conexion = sanitize($_POST['direccion_conexion'] ?? '');
    $estado = $_POST['estado'] ?? 'ACTIVO';
    $fecha_instalacion = !empty($_POST['fecha_instalacion']) ? $_POST['fecha_instalacion'] : null;

    if (empty($numero_accion)) {
        $errors[] = 'El número de acción es requerido';
    }
    if ($tipo_tarifa_id <= 0) {
        $errors[] = 'Seleccione un tipo de tarifa';
    }

    if (empty($errors)) {
        try {
            update(
                "UPDATE acciones SET numero_accion = ?, tipo_tarifa_id = ?, zona_id = ?, 
                 direccion_conexion = ?, estado = ?, fecha_instalacion = ? WHERE id = ?",
                [$numero_accion, $tipo_tarifa_id, $zona_id, $direccion_conexion, $estado, $fecha_instalacion, $id]
            );

            setFlash('success', 'Acción actualizada correctamente');
            redirect('/modules/socios/ver.php?id=' . $accion['socio_id']);
        } catch (Exception $e) {
            $errors[] = 'Error al guardar: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Editar Acción';
require_once dirname(dirname(__DIR__)) . '/includes/header.php';
?>

<div class="page-header">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/modules/socios/">Socios</a></li>
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/modules/socios/ver.php?id=<?= $accion['socio_id'] ?>"><?= htmlspecialchars($accion['numero_socio']) ?></a></li>
            <li class="breadcrumb-item active">Editar Acción</li>
        </ol>
    </nav>
    <h1 class="page-title">Editar Acción <?= htmlspecialchars($accion['numero_accion']) ?></h1>
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
            <div class="card-header"><i class="bi bi-droplet me-2"></i>Datos de la Acción</div>
            <div class="card-body">
                <form method="POST">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Número de Acción <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="numero_accion"
                                   value="<?= htmlspecialchars($_POST['numero_accion'] ?? $accion['numero_accion']) ?>" required>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Tipo de Tarifa <span class="text-danger">*</span></label>
                            <select class="form-select" name="tipo_tarifa_id" required>
                                <option value="">Seleccionar...</option>
                                <?php foreach ($tarifas as $tarifa): ?>
                                <option value="<?= $tarifa['id'] ?>" <?= ($_POST['tipo_tarifa_id'] ?? $accion['tipo_tarifa_id']) == $tarifa['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($tarifa['nombre']) ?> - <?= formatMoney($tarifa['monto']) ?>/mes
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Zona</label>
                            <select class="form-select" name="zona_id">
                                <option value="">Seleccionar...</option>
                                <?php foreach ($zonas as $zona): ?>
                                <option value="<?= $zona['id'] ?>" <?= ($_POST['zona_id'] ?? $accion['zona_id']) == $zona['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($zona['nombre']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Estado</label>
                            <select class="form-select" name="estado">
                                <option value="ACTIVO" <?= ($_POST['estado'] ?? $accion['estado']) === 'ACTIVO' ? 'selected' : '' ?>>ACTIVO</option>
                                <option value="SIN INST." <?= ($_POST['estado'] ?? $accion['estado']) === 'SIN INST.' ? 'selected' : '' ?>>SIN INST.</option>
                                <option value="CORTADO" <?= ($_POST['estado'] ?? $accion['estado']) === 'CORTADO' ? 'selected' : '' ?>>CORTADO</option>
                                <option value="BAJA" <?= ($_POST['estado'] ?? $accion['estado']) === 'BAJA' ? 'selected' : '' ?>>BAJA</option>
                            </select>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Dirección de Conexión</label>
                            <input type="text" class="form-control" name="direccion_conexion"
                                   value="<?= htmlspecialchars($_POST['direccion_conexion'] ?? $accion['direccion_conexion']) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Fecha de Instalación</label>
                            <input type="date" class="form-control" name="fecha_instalacion"
                                   value="<?= htmlspecialchars($_POST['fecha_instalacion'] ?? $accion['fecha_instalacion']) ?>">
                        </div>
                        <div class="col-12">
                            <hr>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-circle me-1"></i> Guardar Cambios
                            </button>
                            <a href="<?= BASE_URL ?>/modules/socios/ver.php?id=<?= $accion['socio_id'] ?>" class="btn btn-outline-secondary">
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
