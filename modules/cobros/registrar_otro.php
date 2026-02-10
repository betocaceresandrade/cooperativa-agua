<?php
/**
 * Registrar Otro Ingreso (Reconexión, Multa, Cuotas, etc.)
 * Permite registrar por accion_id O por socio_id
 */

require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/config/config.php';
require_once dirname(dirname(__DIR__)) . '/includes/functions.php';
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
requireLogin();

$accion_id = intval($_GET['accion_id'] ?? 0);
$socio_id = intval($_GET['socio_id'] ?? 0);

$accion = null;
$socio = null;

// Obtener datos según el parámetro recibido
if ($accion_id > 0) {
    $accion = fetchOne(
        "SELECT a.*, s.id as socio_id, s.nombre as socio_nombre, s.numero_socio, z.nombre as zona_nombre
         FROM acciones a
         JOIN socios s ON a.socio_id = s.id
         LEFT JOIN zonas z ON a.zona_id = z.id
         WHERE a.id = ?",
        [$accion_id]
    );
    if ($accion) {
        $socio_id = $accion['socio_id'];
    }
} elseif ($socio_id > 0) {
    $socio = fetchOne("SELECT * FROM socios WHERE id = ?", [$socio_id]);
}

if (!$accion && !$socio) {
    setFlash('error', 'Debe especificar un socio o acción');
    redirect('/modules/cobros/');
}

// Si hay socio pero no acción, obtener lista de acciones del socio
$acciones_socio = [];
if ($socio && !$accion) {
    $acciones_socio = fetchAll(
        "SELECT a.*, t.nombre as tarifa_nombre 
         FROM acciones a 
         JOIN tipos_tarifa t ON a.tipo_tarifa_id = t.id 
         WHERE a.socio_id = ? 
         ORDER BY a.numero_accion",
        [$socio_id]
    );
}

$tiposOtrosIngresos = fetchAll("SELECT * FROM tipos_otros_ingresos WHERE activo = 1 ORDER BY nombre");
$errors = [];

// Procesar registro
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipo_id = intval($_POST['tipo_id'] ?? 0);
    $monto = floatval($_POST['monto'] ?? 0);
    $concepto = sanitize($_POST['concepto'] ?? '');
    $fecha = $_POST['fecha'] ?? date('Y-m-d');
    $metodo_pago = $_POST['metodo_pago'] ?? 'efectivo';
    $notas = sanitize($_POST['notas'] ?? '');
    $generar_recibo = isset($_POST['generar_recibo']);
    $accion_sel = intval($_POST['accion_id'] ?? 0);

    // Si se seleccionó una acción del dropdown
    if ($accion_sel > 0) {
        $accion_id = $accion_sel;
        $accion = fetchOne(
            "SELECT a.*, s.id as socio_id, s.nombre as socio_nombre, s.numero_socio
             FROM acciones a JOIN socios s ON a.socio_id = s.id WHERE a.id = ?",
            [$accion_id]
        );
        $socio_id = $accion['socio_id'] ?? $socio_id;
    }

    // Validaciones
    if ($tipo_id <= 0) {
        $errors[] = 'Seleccione un tipo de ingreso';
    }
    if ($monto <= 0) {
        $errors[] = 'El monto debe ser mayor a 0';
    }

    if (empty($errors)) {
        try {
            $pdo = getConnection();
            $pdo->beginTransaction();

            // Obtener nombre del tipo
            $tipo = fetchOne("SELECT nombre FROM tipos_otros_ingresos WHERE id = ?", [$tipo_id]);
            $conceptoFinal = $concepto ?: $tipo['nombre'];

            // Nombre del socio para descripción
            $nombreSocio = $accion['socio_nombre'] ?? $socio['nombre'] ?? '';
            $numAccion = $accion['numero_accion'] ?? '';

            // Generar recibo si se solicitó
            $recibo_id = null;
            $numero_recibo = null;
            if ($generar_recibo) {
                $config = getConfig();
                $correlativo = intval($config['correlativo_recibo'] ?? 0) + 1;
                $numero_recibo = str_pad($correlativo, 6, '0', STR_PAD_LEFT);

                $recibo_id = insert(
                    "INSERT INTO pagos (numero_recibo, socio_id, accion_id, fecha_pago, monto_total, metodo_pago, notas)
                     VALUES (?, ?, ?, ?, ?, ?, ?)",
                    [$numero_recibo, $socio_id, $accion_id ?: null, $fecha, $monto, $metodo_pago, $conceptoFinal]
                );

                update("UPDATE configuracion SET correlativo_recibo = ?", [$correlativo]);
            }

            // Registrar otro ingreso
            $ingreso_id = insert(
                "INSERT INTO otros_ingresos (accion_id, socio_id, tipo_id, fecha, monto, concepto, metodo_pago, notas, recibo_id, estado)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pagado')",
                [$accion_id ?: null, $socio_id, $tipo_id, $fecha, $monto, $conceptoFinal, $metodo_pago, $notas, $recibo_id]
            );

            // Registrar movimiento de caja
            $descripcionMov = $conceptoFinal . ' - ' . $nombreSocio;
            if ($numAccion) $descripcionMov .= ' (' . $numAccion . ')';
            
            registrarMovimientoCaja(
                'ingreso',
                $descripcionMov,
                $monto,
                $metodo_pago,
                'otro_ingreso',
                $ingreso_id
            );

            // Si es reconexión y la acción está CORTADA, cambiar a ACTIVO
            if ($accion && in_array($tipo_id, [6, 9]) && $accion['estado'] === 'CORTADO') {
                update("UPDATE acciones SET estado = 'ACTIVO' WHERE id = ?", [$accion_id]);
            }

            $pdo->commit();

            setFlash('success', 'Ingreso registrado correctamente' . ($numero_recibo ? ". Recibo N° $numero_recibo" : ''));

            if ($recibo_id) {
                redirect('/modules/recibos/imprimir.php?id=' . $recibo_id);
            } else {
                redirect('/modules/socios/ver.php?id=' . $socio_id);
            }

        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'Error al guardar: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Registrar Otro Ingreso';
require_once dirname(dirname(__DIR__)) . '/includes/header.php';

$nombreMostrar = $accion ? $accion['socio_nombre'] : $socio['nombre'];
$numMostrar = $accion ? $accion['numero_accion'] : $socio['numero_socio'];
?>

<div class="page-header">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/modules/socios/">Socios</a></li>
            <?php if ($socio): ?>
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/modules/socios/ver.php?id=<?= $socio_id ?>"><?= htmlspecialchars($socio['numero_socio']) ?></a></li>
            <?php endif; ?>
            <li class="breadcrumb-item active">Otro Ingreso</li>
        </ol>
    </nav>
    <h1 class="page-title">Registrar Otro Ingreso</h1>
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
        <!-- Info del Socio -->
        <div class="card mb-4">
            <div class="card-header">
                <i class="bi bi-person me-2"></i>Datos del Socio
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <small class="text-muted"><?= $accion ? 'N° Acción' : 'N° Socio' ?></small>
                        <div class="fw-bold fs-5"><?= htmlspecialchars($numMostrar) ?></div>
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted">Nombre</small>
                        <div class="fw-bold"><?= htmlspecialchars($nombreMostrar) ?></div>
                    </div>
                    <?php if ($accion): ?>
                    <div class="col-md-3">
                        <small class="text-muted">Estado</small>
                        <div>
                            <span class="badge bg-<?= $accion['estado'] === 'ACTIVO' ? 'success' : 'danger' ?>"><?= $accion['estado'] ?></span>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Formulario -->
        <div class="card">
            <div class="card-header bg-success text-white">
                <i class="bi bi-cash-coin me-2"></i>Datos del Ingreso
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <div class="row g-3">
                        <?php if (!empty($acciones_socio)): ?>
                        <div class="col-12">
                            <label class="form-label">Acción (opcional)</label>
                            <select class="form-select" name="accion_id">
                                <option value="">Sin acción específica</option>
                                <?php foreach ($acciones_socio as $acc): ?>
                                <option value="<?= $acc['id'] ?>"><?= htmlspecialchars($acc['numero_accion']) ?> - <?= $acc['tarifa_nombre'] ?> (<?= $acc['estado'] ?>)</option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Seleccione si el ingreso corresponde a una acción específica</small>
                        </div>
                        <?php endif; ?>
                        
                        <div class="col-md-8">
                            <label class="form-label">Tipo de Ingreso <span class="text-danger">*</span></label>
                            <select class="form-select" name="tipo_id" id="tipo_id" required>
                                <option value="">Seleccionar...</option>
                                <?php foreach ($tiposOtrosIngresos as $tipo): ?>
                                <option value="<?= $tipo['id'] ?>" 
                                        data-monto="<?= $tipo['monto_default'] ?? '' ?>"
                                        <?= ($_POST['tipo_id'] ?? '') == $tipo['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($tipo['nombre']) ?>
                                    <?php if ($tipo['monto_default']): ?>
                                    (<?= formatMoney($tipo['monto_default']) ?>)
                                    <?php endif; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Fecha</label>
                            <input type="date" class="form-control" name="fecha" value="<?= htmlspecialchars($_POST['fecha'] ?? date('Y-m-d')) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Monto <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">Bs</span>
                                <input type="number" step="0.01" min="0.01" class="form-control" name="monto" id="monto"
                                       value="<?= htmlspecialchars($_POST['monto'] ?? '') ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Método de Pago</label>
                            <select class="form-select" name="metodo_pago">
                                <option value="efectivo" <?= ($_POST['metodo_pago'] ?? '') === 'efectivo' ? 'selected' : '' ?>>Efectivo</option>
                                <option value="qr" <?= ($_POST['metodo_pago'] ?? '') === 'qr' ? 'selected' : '' ?>>QR / Transferencia</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Concepto / Detalle</label>
                            <input type="text" class="form-control" name="concepto"
                                   value="<?= htmlspecialchars($_POST['concepto'] ?? '') ?>"
                                   placeholder="Opcional - Se usará el nombre del tipo si está vacío">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notas</label>
                            <textarea class="form-control" name="notas" rows="2"><?= htmlspecialchars($_POST['notas'] ?? '') ?></textarea>
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="generar_recibo" name="generar_recibo" checked>
                                <label class="form-check-label" for="generar_recibo">
                                    <i class="bi bi-receipt me-1"></i> Generar recibo imprimible
                                </label>
                            </div>
                        </div>
                        <div class="col-12">
                            <hr>
                            <button type="submit" class="btn btn-success">
                                <i class="bi bi-check-circle me-1"></i> Registrar Ingreso
                            </button>
                            <a href="<?= BASE_URL ?>/modules/socios/ver.php?id=<?= $socio_id ?>" class="btn btn-outline-secondary">
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
            <div class="card-header">
                <i class="bi bi-info-circle me-2"></i>Tipos de Ingreso
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush" style="max-height: 300px; overflow-y: auto;">
                    <?php foreach ($tiposOtrosIngresos as $t): ?>
                    <div class="list-group-item d-flex justify-content-between">
                        <span><?= htmlspecialchars($t['nombre']) ?></span>
                        <?php if ($t['monto_default']): ?>
                        <span class="text-success"><?= formatMoney($t['monto_default']) ?></span>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$pageScripts = <<<HTML
<script>
$('#tipo_id').on('change', function() {
    var monto = $(this).find(':selected').data('monto');
    if (monto) {
        $('#monto').val(monto);
    }
});
</script>
HTML;

require_once dirname(dirname(__DIR__)) . '/includes/footer.php';
?>
