<?php
/**
 * Gestión de Acciones (Conexiones de Agua)
 */

// Incluir funciones ANTES del header para poder hacer redirects
require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/config/config.php';
require_once dirname(dirname(__DIR__)) . '/includes/functions.php';
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
requireLogin();

$tarifas = getTiposTarifa();
$zonas = getZonas();
$errors = [];
$accion = null;
$socio = null;

// Editar acción existente
if (isset($_GET['id'])) {
    $accion = getAccionById(intval($_GET['id']));
    if (!$accion) {
        setFlash('error', 'Acción no encontrada');
        redirect('/modules/socios/');
    }
    $socio = getSocioById($accion['socio_id']);
}
// Nueva acción para un socio
elseif (isset($_GET['socio_id'])) {
    $socio = getSocioById(intval($_GET['socio_id']));
    if (!$socio) {
        setFlash('error', 'Socio no encontrado');
        redirect('/modules/socios/');
    }
}
else {
    redirect('/modules/socios/');
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['ceder_accion'])) {
    $numero_accion = sanitize($_POST['numero_accion'] ?? '');
    $tipo_tarifa_id = intval($_POST['tipo_tarifa_id'] ?? 0);
    $zona_id = !empty($_POST['zona_id']) ? intval($_POST['zona_id']) : null;
    $direccion_conexion = sanitize($_POST['direccion_conexion'] ?? '');
    $estado = $_POST['estado'] ?? 'ACTIVO';
    $fecha_instalacion = null;
    if (!empty($_POST['fecha_instalacion'])) {
        $fecha_raw = $_POST['fecha_instalacion'];
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $fecha_raw, $m)) {
            $fecha_instalacion = $m[3] . '-' . $m[2] . '-' . $m[1];
        } else {
            $fecha_instalacion = $fecha_raw;
        }
    }
    $notas = sanitize($_POST['notas'] ?? '');

    // Validaciones
    if ($tipo_tarifa_id <= 0) {
        $errors[] = 'Seleccione un tipo de tarifa';
    }

    if (empty($errors)) {
        try {
            $pdo = getConnection();
            $pdo->beginTransaction();
            
            if ($accion) {
                // Verificar si cambió la tarifa
                $tarifaCambio = ($accion['tipo_tarifa_id'] != $tipo_tarifa_id);
                
                // Actualizar la acción
                update(
                    "UPDATE acciones SET
                        numero_accion = ?, tipo_tarifa_id = ?, zona_id = ?, direccion_conexion = ?,
                        estado = ?, fecha_instalacion = ?, notas = ?
                     WHERE id = ?",
                    [$numero_accion, $tipo_tarifa_id, $zona_id, $direccion_conexion,
                     $estado, $fecha_instalacion, $notas, $accion['id']]
                );
                
                // Si cambió la tarifa, actualizar meses PENDIENTES con el nuevo monto
                if ($tarifaCambio) {
                    // Obtener el monto de la nueva tarifa
                    $nuevaTarifa = fetchOne("SELECT monto FROM tipos_tarifa WHERE id = ?", [$tipo_tarifa_id]);
                    
                    if ($nuevaTarifa) {
                        // Actualizar todos los consumos pendientes de esta acción
                        $actualizados = update(
                            "UPDATE consumos_anuales 
                             SET monto = ? 
                             WHERE accion_id = ? AND estado = 'pendiente'",
                            [$nuevaTarifa['monto'], $accion['id']]
                        );
                        
                        // Contar cuántos se actualizaron para el mensaje
                        $cantidadActualizada = fetchOne(
                            "SELECT COUNT(*) as total FROM consumos_anuales 
                             WHERE accion_id = ? AND estado = 'pendiente'",
                            [$accion['id']]
                        );
                        
                        if ($cantidadActualizada['total'] > 0) {
                            setFlash('success', 'Acción actualizada. Se actualizaron ' . $cantidadActualizada['total'] . ' mes(es) pendiente(s) a la nueva tarifa de ' . formatMoney($nuevaTarifa['monto']));
                        } else {
                            setFlash('success', 'Acción actualizada correctamente');
                        }
                    }
                } else {
                    setFlash('success', 'Acción actualizada correctamente');
                }
            } else {
                // Crear nueva acción
                insert(
                    "INSERT INTO acciones (socio_id, numero_accion, tipo_tarifa_id, zona_id, direccion_conexion, estado, fecha_instalacion, notas)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                    [$socio['id'], $numero_accion, $tipo_tarifa_id, $zona_id, $direccion_conexion, $estado, $fecha_instalacion, $notas]
                );
                setFlash('success', 'Acción creada correctamente');
            }
            
            $pdo->commit();
            redirect('/modules/socios/ver.php?id=' . $socio['id']);

        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'Error al guardar: ' . $e->getMessage();
        }
    }
}

// Procesar cesión de acción
if (isset($_POST['ceder_accion']) && $accion) {
    $nombre_nuevo = sanitize($_POST['nombre_nuevo'] ?? '');
    $motivo_cesion = sanitize($_POST['motivo_cesion'] ?? '');

    if (empty($nombre_nuevo)) {
        $errors[] = 'Ingrese el nombre del nuevo titular';
    } else {
        try {
            $pdo = getConnection();
            $pdo->beginTransaction();

            insert(
                "INSERT INTO historial_cesiones (accion_id, nombre_anterior, nombre_nuevo, fecha_cesion, motivo)
                 VALUES (?, ?, ?, CURDATE(), ?)",
                [$accion['id'], $socio['nombre'], $nombre_nuevo, $motivo_cesion]
            );

            update("UPDATE socios SET nombre = ? WHERE id = ?", [$nombre_nuevo, $socio['id']]);

            $pdo->commit();
            setFlash('success', 'Cesión de acción registrada correctamente');
            redirect('/modules/socios/ver.php?id=' . $socio['id']);

        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'Error al registrar cesión: ' . $e->getMessage();
        }
    }
}

// Obtener historial de cesiones
$cesiones = [];
if ($accion) {
    $cesiones = fetchAll(
        "SELECT * FROM historial_cesiones WHERE accion_id = ? ORDER BY fecha_cesion DESC",
        [$accion['id']]
    );
}

// AHORA incluir el header (después de procesar formularios)
$pageTitle = 'Gestión de Acciones';
require_once dirname(dirname(__DIR__)) . '/includes/header.php';
?>

<div class="page-header">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/modules/socios/">Socios</a></li>
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/modules/socios/ver.php?id=<?= $socio['id'] ?>"><?= htmlspecialchars($socio['numero_socio']) ?></a></li>
            <li class="breadcrumb-item active"><?= $accion ? 'Editar Acción' : 'Nueva Acción' ?></li>
        </ol>
    </nav>
    <h1 class="page-title"><?= $accion ? 'Editar Acción' : 'Nueva Acción' ?></h1>
    <p class="page-subtitle">Socio: <?= htmlspecialchars($socio['nombre']) ?></p>
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
        <div class="card mb-4">
            <div class="card-header">
                <i class="bi bi-droplet me-2"></i>Datos de la Conexión
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Número de Acción</label>
                            <input type="text" class="form-control" name="numero_accion"
                                   value="<?= htmlspecialchars($_POST['numero_accion'] ?? ($accion['numero_accion'] ?? '')) ?>"
                                   placeholder="Ej: <?= $socio['numero_socio'] ?>-01">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Tipo de Tarifa <span class="text-danger">*</span></label>
                            <select class="form-select" name="tipo_tarifa_id" required>
                                <option value="">Seleccionar...</option>
                                <?php foreach ($tarifas as $tarifa): ?>
                                <option value="<?= $tarifa['id'] ?>"
                                    <?= ($_POST['tipo_tarifa_id'] ?? ($accion['tipo_tarifa_id'] ?? '')) == $tarifa['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($tarifa['nombre']) ?> - <?= formatMoney($tarifa['monto']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($accion): ?>
                            <small class="text-muted">
                                <i class="bi bi-info-circle"></i> Al cambiar la tarifa, los meses pendientes se actualizarán al nuevo monto.
                            </small>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Zona</label>
                            <select class="form-select" name="zona_id">
                                <option value="">Seleccionar...</option>
                                <?php foreach ($zonas as $zona): ?>
                                <option value="<?= $zona['id'] ?>"
                                    <?= ($_POST['zona_id'] ?? ($accion['zona_id'] ?? '')) == $zona['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($zona['nombre']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Dirección de la Conexión</label>
                            <input type="text" class="form-control" name="direccion_conexion"
                                   value="<?= htmlspecialchars($_POST['direccion_conexion'] ?? ($accion['direccion_conexion'] ?? '')) ?>"
                                   placeholder="Si es diferente a la del socio">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Estado</label>
                            <select class="form-select" name="estado">
                                <option value="ACTIVO" <?= ($_POST['estado'] ?? ($accion['estado'] ?? '')) === 'ACTIVO' ? 'selected' : '' ?>>Activo</option>
                                <option value="CORTADO" <?= ($_POST['estado'] ?? ($accion['estado'] ?? '')) === 'CORTADO' ? 'selected' : '' ?>>Cortado</option>
                                <option value="SIN INST." <?= ($_POST['estado'] ?? ($accion['estado'] ?? '')) === 'SIN INST.' ? 'selected' : '' ?>>Sin Instalación</option>
                                <option value="BAJA" <?= ($_POST['estado'] ?? ($accion['estado'] ?? '')) === 'BAJA' ? 'selected' : '' ?>>Baja</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Fecha de Instalación</label>
                            <input type="date" class="form-control" name="fecha_instalacion"
                                   value="<?= htmlspecialchars($_POST['fecha_instalacion'] ?? ($accion['fecha_instalacion'] ?? '')) ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Observación</label>
                            <textarea class="form-control" name="notas" rows="2"><?= htmlspecialchars($_POST['notas'] ?? ($accion['notas'] ?? '')) ?></textarea>
                        </div>
                        <div class="col-12">
                            <hr>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-circle me-1"></i> <?= $accion ? 'Guardar Cambios' : 'Crear Acción' ?>
                            </button>
                            <a href="<?= BASE_URL ?>/modules/socios/ver.php?id=<?= $socio['id'] ?>" class="btn btn-outline-secondary">
                                Cancelar
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($accion): ?>
        <div class="card">
            <div class="card-header">
                <i class="bi bi-arrow-left-right me-2"></i>Cesión de Acción
            </div>
            <div class="card-body">
                <p class="text-muted mb-3">
                    Transferir esta acción a un nuevo titular. El socio actual (<?= htmlspecialchars($socio['nombre']) ?>)
                    será reemplazado por el nuevo nombre.
                </p>
                <form method="POST" action="" onsubmit="return confirm('¿Está seguro de realizar esta cesión?');">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Nuevo Titular <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="nombre_nuevo"
                                   placeholder="Nombre completo del nuevo titular" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Motivo de Cesión</label>
                            <input type="text" class="form-control" name="motivo_cesion"
                                   placeholder="Venta, herencia, etc.">
                        </div>
                        <div class="col-12">
                            <button type="submit" name="ceder_accion" value="1" class="btn btn-warning">
                                <i class="bi bi-arrow-left-right me-1"></i> Registrar Cesión
                            </button>
                        </div>
                    </div>
                </form>

                <?php if (!empty($cesiones)): ?>
                <hr>
                <h6>Historial de Cesiones</h6>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Titular Anterior</th>
                                <th>Nuevo Titular</th>
                                <th>Motivo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cesiones as $cesion): ?>
                            <tr>
                                <td><?= formatDate($cesion['fecha_cesion']) ?></td>
                                <td><?= htmlspecialchars($cesion['nombre_anterior']) ?></td>
                                <td><?= htmlspecialchars($cesion['nombre_nuevo']) ?></td>
                                <td><?= htmlspecialchars($cesion['motivo'] ?? '-') ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">Información del Socio</div>
            <div class="card-body">
                <table class="table table-sm table-borderless">
                    <tr>
                        <td class="text-muted">N° Socio</td>
                        <td><strong><?= htmlspecialchars($socio['numero_socio']) ?></strong></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Nombre</td>
                        <td><?= htmlspecialchars($socio['nombre']) ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Dirección</td>
                        <td><?= htmlspecialchars($socio['direccion'] ?? '-') ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
