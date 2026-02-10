<?php
/**
 * Ver Detalle de Socio
 */

require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/config/config.php';
require_once dirname(dirname(__DIR__)) . '/includes/functions.php';
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
requireLogin();

$id = intval($_GET['id'] ?? 0);

$socio = fetchOne("SELECT * FROM socios WHERE id = ?", [$id]);

if (!$socio) {
    setFlash('error', 'Socio no encontrado');
    redirect('/modules/socios/');
}

// Fecha actual para cálculo de deuda
$mes_actual = intval(date('n'));
$anio_actual = intval(date('Y'));
$meses_pasados = $mes_actual - 1;

// Obtener acciones con deuda calculada dinámicamente
$acciones = fetchAll(
    "SELECT a.*, t.nombre as tarifa_nombre, t.monto as tarifa_monto, z.nombre as zona_nombre
     FROM acciones a
     JOIN tipos_tarifa t ON a.tipo_tarifa_id = t.id
     LEFT JOIN zonas z ON a.zona_id = z.id
     WHERE a.socio_id = ?
     ORDER BY a.numero_accion",
    [$id]
);

// Calcular deuda para cada acción
$deudaTotal = 0;
$totalMesesPendientes = 0;
foreach ($acciones as &$accion) {
    if ($accion['estado'] === 'ACTIVO' && $meses_pasados > 0) {
        $mesesPagados = fetchOne(
            "SELECT COUNT(*) as pagados FROM consumos_anuales 
             WHERE accion_id = ? AND anio = ? AND mes < ? AND estado IN ('pagado','no_cobrable')",
            [$accion['id'], $anio_actual, $mes_actual]
        );
        $accion['meses_pendientes'] = max(0, $meses_pasados - ($mesesPagados['pagados'] ?? 0));
        $accion['deuda_accion'] = $accion['meses_pendientes'] * $accion['tarifa_monto'];
    } else {
        $accion['meses_pendientes'] = 0;
        $accion['deuda_accion'] = 0;
    }
    $deudaTotal += $accion['deuda_accion'];
    $totalMesesPendientes += $accion['meses_pendientes'];
}
unset($accion);

// Historial de pagos de consumos
$pagosConsumos = fetchAll(
    "SELECT p.*, a.numero_accion, 'consumo' as tipo,
            (SELECT COUNT(*) FROM consumos_anuales ca WHERE ca.pago_id = p.id) as num_meses
     FROM pagos p
     LEFT JOIN acciones a ON p.accion_id = a.id
     WHERE p.socio_id = ? AND p.anulado = 0
     ORDER BY p.fecha_pago DESC
     LIMIT 10",
    [$id]
);

// Otros ingresos del socio
$otrosIngresos = fetchAll(
    "SELECT oi.*, a.numero_accion, t.nombre as tipo_nombre, 'otro' as tipo
     FROM otros_ingresos oi
     LEFT JOIN acciones a ON oi.accion_id = a.id
     LEFT JOIN tipos_otros_ingresos t ON oi.tipo_id = t.id
     WHERE oi.socio_id = ? AND oi.estado = 'pagado'
     ORDER BY oi.fecha DESC
     LIMIT 10",
    [$id]
);

$pageTitle = 'Detalle de Socio';
require_once dirname(dirname(__DIR__)) . '/includes/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/modules/socios/">Socios</a></li>
                <li class="breadcrumb-item active"><?= htmlspecialchars($socio['numero_socio']) ?></li>
            </ol>
        </nav>
        <h1 class="page-title"><?= htmlspecialchars($socio['nombre']) ?></h1>
    </div>
    <div class="btn-group">
        <a href="<?= BASE_URL ?>/modules/socios/editar.php?id=<?= $id ?>" class="btn btn-outline-primary">
            <i class="bi bi-pencil me-1"></i> Editar
        </a>
        <a href="<?= BASE_URL ?>/modules/cobros/registrar_otro.php?socio_id=<?= $id ?>" class="btn btn-outline-success">
            <i class="bi bi-plus-circle me-1"></i> Otro Ingreso
        </a>
        <?php if (count($acciones) == 0): ?>
        <a href="<?= BASE_URL ?>/modules/socios/eliminar.php?id=<?= $id ?>" class="btn btn-outline-danger">
            <i class="bi bi-trash me-1"></i> Eliminar
        </a>
        <?php endif; ?>
    </div>
</div>

<div class="row">
    <div class="col-lg-4">
        <!-- Datos del Socio -->
        <div class="card mb-4">
            <div class="card-header"><i class="bi bi-person me-2"></i>Datos del Socio</div>
            <div class="card-body">
                <table class="table table-sm table-borderless mb-0">
                    <tr><td class="text-muted" width="40%">N° Socio</td><td><strong><?= htmlspecialchars($socio['numero_socio']) ?></strong></td></tr>
                    <?php if (!empty($socio['persona_encargada'])): ?>
                    <tr><td class="text-muted">Encargado</td><td><?= htmlspecialchars($socio['persona_encargada']) ?></td></tr>
                    <?php endif; ?>
                    <tr><td class="text-muted">Celular</td><td><?= htmlspecialchars($socio['celular'] ?? '-') ?></td></tr>
                    <tr><td class="text-muted">Acciones</td><td><span class="badge bg-primary"><?= count($acciones) ?></span></td></tr>
                </table>
                <?php if (!empty($socio['notas'])): ?>
                <hr><small class="text-muted"><strong>Notas:</strong><br><?= nl2br(htmlspecialchars($socio['notas'])) ?></small>
                <?php endif; ?>
            </div>
        </div>

        <!-- Resumen de Deuda -->
        <?php if ($deudaTotal > 0): ?>
        <div class="card mb-4 border-danger">
            <div class="card-body text-center">
                <div class="text-muted mb-1">Deuda Total</div>
                <div class="fs-2 fw-bold text-danger"><?= formatMoney($deudaTotal) ?></div>
                <small class="text-muted"><?= $totalMesesPendientes ?> mes(es) vencido(s)</small>
            </div>
        </div>
        <?php elseif ($meses_pasados > 0): ?>
        <div class="card mb-4 border-success">
            <div class="card-body text-center">
                <i class="bi bi-check-circle text-success fs-1"></i>
                <div class="text-success fw-bold">Al día</div>
            </div>
        </div>
        <?php else: ?>
        <div class="card mb-4 border-info">
            <div class="card-body text-center text-muted">
                <i class="bi bi-info-circle fs-1"></i>
                <div>Sin deuda aún (<?= getNombreMes($mes_actual) ?> <?= $anio_actual ?>)</div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="col-lg-8">
        <!-- Acciones -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-droplet me-2"></i>Acciones (<?= count($acciones) ?>)</span>
                <a href="<?= BASE_URL ?>/modules/socios/accion_crear.php?socio_id=<?= $id ?>" class="btn btn-sm btn-primary">
                    <i class="bi bi-plus"></i> Nueva Acción
                </a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($acciones)): ?>
                <div class="text-center py-4 text-muted">
                    <p>Este socio no tiene acciones</p>
                    <a href="<?= BASE_URL ?>/modules/socios/accion_crear.php?socio_id=<?= $id ?>" class="btn btn-primary">
                        <i class="bi bi-plus"></i> Crear primera acción
                    </a>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Acción</th>
                                <th>Zona</th>
                                <th>Tarifa</th>
                                <th class="text-center">Estado</th>
                                <th class="text-center">Meses</th>
                                <th class="text-end">Deuda</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($acciones as $acc):
                                $estadoClass = match($acc['estado']) {
                                    'ACTIVO' => 'success', 'CORTADO' => 'danger',
                                    'BAJA' => 'secondary', 'SIN INST.' => 'info', default => 'secondary'
                                };
                            ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($acc['numero_accion']) ?></strong></td>
                                <td><?= htmlspecialchars($acc['zona_nombre'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($acc['tarifa_nombre']) ?><br><small class="text-muted"><?= formatMoney($acc['tarifa_monto']) ?>/mes</small></td>
                                <td class="text-center"><span class="badge bg-<?= $estadoClass ?>"><?= $acc['estado'] ?></span></td>
                                <td class="text-center">
                                    <?php if ($acc['meses_pendientes'] > 0): ?>
                                    <span class="badge bg-danger"><?= $acc['meses_pendientes'] ?></span>
                                    <?php else: ?>
                                    <span class="badge bg-success">0</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end <?= $acc['deuda_accion'] > 0 ? 'text-danger fw-bold' : 'text-success' ?>"><?= formatMoney($acc['deuda_accion']) ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?= BASE_URL ?>/modules/socios/accion_editar.php?id=<?= $acc['id'] ?>" class="btn btn-outline-primary" title="Editar"><i class="bi bi-pencil"></i></a>
                                        <a href="<?= BASE_URL ?>/modules/consumos/cobrar.php?accion_id=<?= $acc['id'] ?>" class="btn btn-<?= $acc['meses_pendientes'] > 0 ? 'warning' : 'outline-success' ?>" title="Cobrar"><i class="bi bi-cash"></i></a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Historial de Pagos -->
        <div class="card mb-4">
            <div class="card-header"><i class="bi bi-cash-coin me-2"></i>Pagos de Consumos</div>
            <div class="card-body p-0">
                <?php if (empty($pagosConsumos)): ?>
                <div class="text-center py-3 text-muted">No hay pagos de consumos</div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0">
                        <thead><tr><th>Recibo</th><th>Fecha</th><th>Acción</th><th class="text-center">Meses</th><th class="text-end">Monto</th></tr></thead>
                        <tbody>
                            <?php foreach ($pagosConsumos as $pago): ?>
                            <tr>
                                <td><strong>#<?= htmlspecialchars($pago['numero_recibo']) ?></strong></td>
                                <td><?= formatDate($pago['fecha_pago'], 'd/m/Y') ?></td>
                                <td><?= htmlspecialchars($pago['numero_accion'] ?? '-') ?></td>
                                <td class="text-center"><span class="badge bg-secondary"><?= $pago['num_meses'] ?></span></td>
                                <td class="text-end fw-bold"><?= formatMoney($pago['monto_total']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Otros Ingresos -->
        <div class="card">
            <div class="card-header"><i class="bi bi-plus-circle me-2"></i>Otros Ingresos</div>
            <div class="card-body p-0">
                <?php if (empty($otrosIngresos)): ?>
                <div class="text-center py-3 text-muted">No hay otros ingresos registrados</div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0">
                        <thead><tr><th>Fecha</th><th>Tipo</th><th>Concepto</th><th>Acción</th><th class="text-end">Monto</th></tr></thead>
                        <tbody>
                            <?php foreach ($otrosIngresos as $ing): ?>
                            <tr>
                                <td><?= formatDate($ing['fecha'], 'd/m/Y') ?></td>
                                <td><span class="badge bg-info"><?= htmlspecialchars($ing['tipo_nombre'] ?? 'Otro') ?></span></td>
                                <td><?= htmlspecialchars($ing['concepto']) ?></td>
                                <td><?= htmlspecialchars($ing['numero_accion'] ?? '-') ?></td>
                                <td class="text-end fw-bold text-success"><?= formatMoney($ing['monto']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
