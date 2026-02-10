<?php
/**
 * Crear Salida de Caja (Adelanto, Entrega o Depósito)
 */

require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/config/config.php';
require_once dirname(dirname(__DIR__)) . '/includes/functions.php';
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
requireLogin();

$tipo = $_GET['tipo'] ?? $_POST['tipo'] ?? 'adelanto';
$saldos = getSaldosCaja();
$errors = [];

// Configuración por tipo
$tipoConfig = [
    'adelanto' => [
        'titulo' => 'Adelanto (Fondo a Rendir)',
        'subtitulo' => 'Dinero entregado a una persona que debe rendir cuentas con facturas',
        'icono' => 'cash-stack',
        'color' => 'warning',
        'campo_destino' => 'Beneficiario',
        'placeholder_destino' => 'Ej: Sr. Juan Pérez, Plomero',
        'requiere_rendicion' => 1
    ],
    'entrega' => [
        'titulo' => 'Entrega a Autoridad',
        'subtitulo' => 'Dinero entregado al tesorero, presidente u otra autoridad',
        'icono' => 'box-arrow-right',
        'color' => 'info',
        'campo_destino' => 'Entregado a',
        'placeholder_destino' => 'Ej: Tesorero General, Presidente',
        'requiere_rendicion' => 0
    ],
    'deposito' => [
        'titulo' => 'Depósito Bancario',
        'subtitulo' => 'Depósito de dinero en cuenta bancaria',
        'icono' => 'bank',
        'color' => 'success',
        'campo_destino' => 'Banco',
        'placeholder_destino' => 'Ej: Banco Unión, BNB',
        'requiere_rendicion' => 0
    ]
];

$config = $tipoConfig[$tipo] ?? $tipoConfig['adelanto'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fecha = $_POST['fecha'] ?? date('Y-m-d');
    $beneficiario = sanitize($_POST['beneficiario'] ?? '');
    $concepto = sanitize($_POST['concepto'] ?? '');
    $monto = floatval($_POST['monto'] ?? 0);
    $metodo_pago = $_POST['metodo_pago'] ?? 'efectivo';
    $notas = sanitize($_POST['notas'] ?? '');
    $requiere_rendicion = $config['requiere_rendicion'];

    // Validaciones
    if (empty($beneficiario)) {
        $errors[] = 'Ingrese el destino del dinero';
    }
    if ($monto <= 0) {
        $errors[] = 'El monto debe ser mayor a 0';
    }
    
    // Verificar saldo
    if ($metodo_pago === 'efectivo' && $monto > $saldos['efectivo']) {
        $errors[] = 'Saldo en efectivo insuficiente (Disponible: ' . formatMoney($saldos['efectivo']) . ')';
    }
    if ($metodo_pago === 'qr' && $monto > $saldos['qr']) {
        $errors[] = 'Saldo en QR insuficiente (Disponible: ' . formatMoney($saldos['qr']) . ')';
    }

    if (empty($errors)) {
        try {
            // Estado inicial según tipo
            $estado = $requiere_rendicion ? 'pendiente' : 'rendido';
            $monto_rendido = $requiere_rendicion ? 0 : $monto;

            // Insertar
            $id = insert(
                "INSERT INTO fondos_rendir (tipo, requiere_rendicion, fecha_entrega, beneficiario, concepto, monto, metodo_pago, estado, monto_rendido, notas)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [$tipo, $requiere_rendicion, $fecha, $beneficiario, $concepto, $monto, $metodo_pago, $estado, $monto_rendido, $notas]
            );

            // Registrar movimiento de caja
            $conceptoMov = match($tipo) {
                'adelanto' => "Adelanto a: $beneficiario",
                'entrega' => "Entrega a: $beneficiario",
                'deposito' => "Depósito: $beneficiario" . ($concepto ? " - $concepto" : ''),
                default => "Salida: $beneficiario"
            };
            
            registrarMovimientoCaja('egreso', $conceptoMov, $monto, $metodo_pago, 'fondo_rendir', $id);

            $msgExito = match($tipo) {
                'adelanto' => 'Adelanto registrado. Pendiente de rendición.',
                'entrega' => 'Entrega registrada correctamente.',
                'deposito' => 'Depósito registrado correctamente.',
                default => 'Registro guardado.'
            };
            
            setFlash('success', $msgExito);
            redirect('/modules/fondos-rendir/');

        } catch (Exception $e) {
            $errors[] = 'Error al guardar: ' . $e->getMessage();
        }
    }
}

$pageTitle = $config['titulo'];
require_once dirname(dirname(__DIR__)) . '/includes/header.php';
?>

<div class="page-header">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/modules/fondos-rendir/">Salidas de Caja</a></li>
            <li class="breadcrumb-item active"><?= $config['titulo'] ?></li>
        </ol>
    </nav>
    <h1 class="page-title">
        <i class="bi bi-<?= $config['icono'] ?> text-<?= $config['color'] ?> me-2"></i>
        <?= $config['titulo'] ?>
    </h1>
    <p class="text-muted"><?= $config['subtitulo'] ?></p>
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
            <div class="card-body">
                <form method="POST" action="">
                    <input type="hidden" name="tipo" value="<?= htmlspecialchars($tipo) ?>">
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Fecha <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="fecha"
                                   value="<?= htmlspecialchars($_POST['fecha'] ?? date('Y-m-d')) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Monto <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">Bs.</span>
                                <input type="number" step="0.01" min="0.01" class="form-control" name="monto"
                                       value="<?= htmlspecialchars($_POST['monto'] ?? '') ?>" required>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label"><?= $config['campo_destino'] ?> <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="beneficiario"
                                   value="<?= htmlspecialchars($_POST['beneficiario'] ?? '') ?>"
                                   placeholder="<?= $config['placeholder_destino'] ?>" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label"><?= $tipo === 'deposito' ? 'N° Comprobante / Referencia' : 'Concepto' ?></label>
                            <input type="text" class="form-control" name="concepto"
                                   value="<?= htmlspecialchars($_POST['concepto'] ?? '') ?>"
                                   placeholder="<?= $tipo === 'deposito' ? 'Número de depósito o transferencia' : 'Descripción del uso del dinero' ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Origen del Dinero</label>
                            <div class="btn-group w-100" role="group">
                                <input type="radio" class="btn-check" name="metodo_pago" value="efectivo" id="efectivo" checked>
                                <label class="btn btn-outline-success" for="efectivo">
                                    <i class="bi bi-cash me-1"></i> Efectivo
                                </label>
                                <input type="radio" class="btn-check" name="metodo_pago" value="qr" id="qr">
                                <label class="btn btn-outline-primary" for="qr">
                                    <i class="bi bi-qr-code me-1"></i> QR
                                </label>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notas</label>
                            <textarea class="form-control" name="notas" rows="2"><?= htmlspecialchars($_POST['notas'] ?? '') ?></textarea>
                        </div>
                        <div class="col-12">
                            <hr>
                            <button type="submit" class="btn btn-<?= $config['color'] ?>">
                                <i class="bi bi-check-circle me-1"></i> Registrar
                            </button>
                            <a href="<?= BASE_URL ?>/modules/fondos-rendir/" class="btn btn-outline-secondary">
                                Cancelar
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <!-- Saldos -->
        <div class="card">
            <div class="card-header">
                <i class="bi bi-wallet2 me-2"></i>Saldos Disponibles
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-3">
                    <span><i class="bi bi-cash text-success me-2"></i>Efectivo</span>
                    <strong class="text-success"><?= formatMoney($saldos['efectivo']) ?></strong>
                </div>
                <div class="d-flex justify-content-between">
                    <span><i class="bi bi-qr-code text-primary me-2"></i>QR</span>
                    <strong class="text-primary"><?= formatMoney($saldos['qr']) ?></strong>
                </div>
            </div>
        </div>

        <?php if ($config['requiere_rendicion']): ?>
        <div class="alert alert-warning mt-3">
            <i class="bi bi-info-circle me-2"></i>
            <strong>Requiere Rendición:</strong> Este adelanto quedará pendiente hasta que se presenten los comprobantes de gasto.
        </div>
        <?php else: ?>
        <div class="alert alert-info mt-3">
            <i class="bi bi-check-circle me-2"></i>
            <strong>Sin Rendición:</strong> Este registro se marcará como completado automáticamente.
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
