<?php
/**
 * Configuración: General
 * Parámetros generales del sistema
 */

$pageTitle = 'Configuración General';
require_once dirname(dirname(__DIR__)) . '/includes/header.php';
require_once dirname(dirname(__DIR__)) . '/includes/functions.php';

$mensaje = '';
$tipo_mensaje = '';

// Directorio de logos
$logoDir = dirname(dirname(__DIR__)) . '/assets/img/';

// Obtener configuración actual
$config = fetchOne("SELECT * FROM configuracion LIMIT 1");

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre_cooperativa = trim($_POST['nombre_cooperativa'] ?? '');
    $direccion = trim($_POST['direccion'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    
    // Procesar subida de logo
    $logo_actual = $config['logo'] ?? 'logo.png';
    
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['image/png', 'image/jpeg', 'image/gif', 'image/webp'];
        $fileType = mime_content_type($_FILES['logo']['tmp_name']);
        
        if (in_array($fileType, $allowed)) {
            // Generar nombre único
            $ext = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
            $newName = 'logo_' . time() . '.' . $ext;
            $destPath = $logoDir . $newName;
            
            if (move_uploaded_file($_FILES['logo']['tmp_name'], $destPath)) {
                // Eliminar logo anterior si no es el default
                if ($logo_actual && $logo_actual !== 'logo.png' && file_exists($logoDir . $logo_actual)) {
                    unlink($logoDir . $logo_actual);
                }
                $logo_actual = $newName;
            } else {
                $mensaje = 'Error al subir el logo';
                $tipo_mensaje = 'danger';
            }
        } else {
            $mensaje = 'Formato de imagen no permitido. Use PNG, JPG, GIF o WebP';
            $tipo_mensaje = 'danger';
        }
    }

    if (empty($nombre_cooperativa)) {
        $mensaje = 'El nombre de la cooperativa es obligatorio';
        $tipo_mensaje = 'danger';
    } elseif ($tipo_mensaje !== 'danger') {
        try {
            if ($config) {
                update(
                    "UPDATE configuracion SET nombre_cooperativa = ?, direccion = ?, telefono = ?, logo = ? WHERE id = ?",
                    [$nombre_cooperativa, $direccion, $telefono, $logo_actual, $config['id']]
                );
            } else {
                insert(
                    "INSERT INTO configuracion (nombre_cooperativa, direccion, telefono, logo) VALUES (?, ?, ?, ?)",
                    [$nombre_cooperativa, $direccion, $telefono, $logo_actual]
                );
            }
            $mensaje = 'Configuración guardada correctamente';
            $tipo_mensaje = 'success';

            // Recargar configuración
            $config = fetchOne("SELECT * FROM configuracion LIMIT 1");
        } catch (Exception $e) {
            $mensaje = 'Error: ' . $e->getMessage();
            $tipo_mensaje = 'danger';
        }
    }
}

// Estadísticas del sistema
$stats = [
    'socios' => fetchOne("SELECT COUNT(*) as total FROM socios")['total'] ?? 0,
    'acciones' => fetchOne("SELECT COUNT(*) as total FROM acciones")['total'] ?? 0,
    'zonas' => fetchOne("SELECT COUNT(*) as total FROM zonas")['total'] ?? 0,
    'pagos' => fetchOne("SELECT COUNT(*) as total FROM pagos WHERE anulado = 0")['total'] ?? 0,
];

// Correlativo actual
$correlativo = ($config['correlativo_recibo'] ?? 0) + 1;
$logoPath = BASE_URL . '/assets/img/' . ($config['logo'] ?? 'logo.png');
?>

<div class="page-header">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/modules/configuracion/">Configuración</a></li>
            <li class="breadcrumb-item active">General</li>
        </ol>
    </nav>
    <h1 class="page-title"><i class="bi bi-sliders me-2"></i>Configuración General</h1>
</div>

<?php if ($mensaje): ?>
<div class="alert alert-<?= $tipo_mensaje ?> alert-dismissible fade show" role="alert">
    <?= htmlspecialchars($mensaje) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-building me-2"></i>Datos de la Cooperativa
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">Nombre de la Cooperativa <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="nombre_cooperativa" required
                                   value="<?= htmlspecialchars($config['nombre_cooperativa'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Teléfono</label>
                            <input type="text" class="form-control" name="telefono"
                                   value="<?= htmlspecialchars($config['telefono'] ?? '') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Dirección</label>
                            <textarea class="form-control" name="direccion" rows="2"><?= htmlspecialchars($config['direccion'] ?? '') ?></textarea>
                        </div>
                        <div class="col-12">
                            <hr>
                            <label class="form-label">Logo de la Cooperativa</label>
                            <div class="row align-items-center">
                                <div class="col-auto">
                                    <img src="<?= $logoPath ?>" alt="Logo" style="max-height: 80px; max-width: 200px;" class="border rounded p-1" id="logoPreview">
                                </div>
                                <div class="col">
                                    <input type="file" class="form-control" name="logo" accept="image/png,image/jpeg,image/gif,image/webp" id="logoInput">
                                    <small class="text-muted">PNG, JPG, GIF o WebP. Máx 2MB. Recomendado: 200x80px</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <hr>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg me-1"></i> Guardar Cambios
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Información del Sistema -->
        <div class="card mt-4">
            <div class="card-header">
                <i class="bi bi-info-circle me-2"></i>Información del Sistema
            </div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4">Próximo N° de Recibo:</dt>
                    <dd class="col-sm-8"><span class="badge bg-primary fs-6"><?= $correlativo ?></span></dd>

                    <dt class="col-sm-4">Versión del Sistema:</dt>
                    <dd class="col-sm-8"><?= APP_VERSION ?? '1.0.0' ?></dd>

                    <dt class="col-sm-4">Fecha del Servidor:</dt>
                    <dd class="col-sm-8"><?= date('d/m/Y H:i:s') ?></dd>
                </dl>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <!-- Estadísticas -->
        <div class="card">
            <div class="card-header">
                <i class="bi bi-bar-chart me-2"></i>Estadísticas
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-people text-primary me-2"></i>Socios</span>
                        <span class="badge bg-primary rounded-pill"><?= number_format($stats['socios']) ?></span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-droplet text-info me-2"></i>Acciones</span>
                        <span class="badge bg-info rounded-pill"><?= number_format($stats['acciones']) ?></span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-geo-alt text-success me-2"></i>Zonas</span>
                        <span class="badge bg-success rounded-pill"><?= number_format($stats['zonas']) ?></span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-receipt text-warning me-2"></i>Pagos Registrados</span>
                        <span class="badge bg-warning rounded-pill"><?= number_format($stats['pagos']) ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Accesos Rápidos -->
        <div class="card mt-4">
            <div class="card-header">
                <i class="bi bi-lightning me-2"></i>Accesos Rápidos
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="<?= BASE_URL ?>/modules/configuracion/zonas.php" class="btn btn-outline-primary">
                        <i class="bi bi-geo-alt me-2"></i>Administrar Zonas
                    </a>
                    <a href="<?= BASE_URL ?>/modules/configuracion/tarifas.php" class="btn btn-outline-success">
                        <i class="bi bi-currency-dollar me-2"></i>Administrar Tarifas
                    </a>
                    <a href="<?= BASE_URL ?>/modules/configuracion/categorias.php" class="btn btn-outline-danger">
                        <i class="bi bi-tags me-2"></i>Categorías de Gasto
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$pageScripts = <<<HTML
<script>
document.getElementById('logoInput').addEventListener('change', function(e) {
    if (e.target.files && e.target.files[0]) {
        var reader = new FileReader();
        reader.onload = function(ev) {
            document.getElementById('logoPreview').src = ev.target.result;
        }
        reader.readAsDataURL(e.target.files[0]);
    }
});
</script>
HTML;

require_once dirname(dirname(__DIR__)) . '/includes/footer.php';
?>
