<?php
/**
 * Resumen de Tesorería
 * Vista consolidada de la situación financiera
 */

require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/config/config.php';
require_once dirname(dirname(__DIR__)) . '/includes/functions.php';
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
requireLogin();

// =====================================================
// OBTENER DATOS
// =====================================================

// 1. Saldos de caja (disponible)
$saldos = getSaldosCaja();
$totalDisponible = $saldos['efectivo'] + $saldos['qr'];

// 2. Adelantos pendientes de rendición
$adelantosPendientes = fetchAll(
    "SELECT id, fecha_entrega, beneficiario, concepto, monto, monto_rendido, (monto - monto_rendido) as pendiente
     FROM fondos_rendir
     WHERE tipo = 'adelanto' AND requiere_rendicion = 1 AND estado IN ('pendiente', 'parcial')
     ORDER BY fecha_entrega ASC"
);
$totalAdelantos = array_sum(array_column($adelantosPendientes, 'pendiente'));

// 3. Entregas a autoridades
$entregasAutoridad = fetchOne(
    "SELECT COUNT(*) as cantidad, COALESCE(SUM(monto), 0) as total FROM fondos_rendir WHERE tipo = 'entrega'"
);

// 4. Depósitos bancarios
$depositosBanco = fetchOne(
    "SELECT COUNT(*) as cantidad, COALESCE(SUM(monto), 0) as total FROM fondos_rendir WHERE tipo = 'deposito'"
);

// 5. Deuda de socios (calculada dinámicamente)
$mes_actual = intval(date('n'));
$anio_actual = intval(date('Y'));
$meses_pasados = $mes_actual - 1; // Meses vencidos del año actual

if ($meses_pasados > 0) {
    // Calcular deuda: acciones activas con meses pasados sin pago
    $deudaSocios = fetchOne(
        "SELECT COUNT(*) as acciones_con_deuda,
                SUM(meses_deuda) as meses_pendientes,
                SUM(total_deuda) as total
         FROM (
             SELECT a.id,
                    GREATEST(0, ? - (SELECT COUNT(*) FROM consumos_anuales ca 
                                     WHERE ca.accion_id = a.id AND ca.anio = ? AND ca.mes < ? 
                                     AND ca.estado IN ('pagado','no_cobrable'))) as meses_deuda,
                    GREATEST(0, ? - (SELECT COUNT(*) FROM consumos_anuales ca 
                                     WHERE ca.accion_id = a.id AND ca.anio = ? AND ca.mes < ? 
                                     AND ca.estado IN ('pagado','no_cobrable'))) * t.monto as total_deuda
             FROM acciones a
             JOIN tipos_tarifa t ON a.tipo_tarifa_id = t.id
             WHERE a.estado = 'ACTIVO'
             HAVING meses_deuda > 0
         ) sub",
        [$meses_pasados, $anio_actual, $mes_actual, $meses_pasados, $anio_actual, $mes_actual]
    );
} else {
    $deudaSocios = ['acciones_con_deuda' => 0, 'meses_pendientes' => 0, 'total' => 0];
}

// 6. Resumen de ingresos y gastos del período
$resumenPeriodo = fetchOne(
    "SELECT
        (SELECT COALESCE(SUM(monto_total), 0) FROM pagos WHERE YEAR(fecha_pago) = ? AND anulado = 0) as ingresos,
        (SELECT COALESCE(SUM(monto), 0) FROM gastos WHERE YEAR(fecha) = ?) as gastos",
    [$anio_actual, $anio_actual]
);
$resultadoPeriodo = ($resumenPeriodo['ingresos'] ?? 0) - ($resumenPeriodo['gastos'] ?? 0);

// Posición total
$posicionTotal = $totalDisponible + $totalAdelantos + ($deudaSocios['total'] ?? 0);

// Últimos movimientos
$ultimosMovimientos = fetchAll("SELECT * FROM movimientos_caja ORDER BY id DESC LIMIT 10");

$pageTitle = 'Tesorería';
$currentPage = 'tesoreria';
require_once dirname(dirname(__DIR__)) . '/includes/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1 class="page-title"><i class="bi bi-clipboard-data me-2"></i>Resumen de Tesorería</h1>
        <p class="text-muted">Situación financiera al <?= date('d/m/Y H:i') ?></p>
    </div>
    <button onclick="window.print()" class="btn btn-outline-primary no-print">
        <i class="bi bi-printer me-1"></i> Imprimir
    </button>
</div>

<!-- Resumen Principal -->
<div class="row g-4 mb-4">
    <div class="col-md-6 col-lg-3">
        <div class="card border-success h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="text-muted mb-1">DISPONIBLE</h6>
                        <h3 class="text-success mb-0"><?= formatMoney($totalDisponible) ?></h3>
                    </div>
                    <div class="bg-success bg-opacity-10 p-2 rounded">
                        <i class="bi bi-wallet2 text-success fs-4"></i>
                    </div>
                </div>
                <hr class="my-2">
                <div class="small">
                    <div class="d-flex justify-content-between"><span><i class="bi bi-cash text-success"></i> Efectivo</span><strong><?= formatMoney($saldos['efectivo']) ?></strong></div>
                    <div class="d-flex justify-content-between"><span><i class="bi bi-qr-code text-primary"></i> QR</span><strong><?= formatMoney($saldos['qr']) ?></strong></div>
                </div>
            </div>
            <div class="card-footer bg-transparent">
                <a href="<?= BASE_URL ?>/modules/caja/" class="text-decoration-none small">Ver movimientos <i class="bi bi-arrow-right"></i></a>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-lg-3">
        <div class="card border-warning h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="text-muted mb-1">POR RENDIR</h6>
                        <h3 class="text-warning mb-0"><?= formatMoney($totalAdelantos) ?></h3>
                    </div>
                    <div class="bg-warning bg-opacity-10 p-2 rounded">
                        <i class="bi bi-hourglass-split text-warning fs-4"></i>
                    </div>
                </div>
                <hr class="my-2">
                <div class="small text-muted"><?= count($adelantosPendientes) ?> adelanto(s) pendiente(s)</div>
            </div>
            <div class="card-footer bg-transparent">
                <a href="<?= BASE_URL ?>/modules/fondos-rendir/?tipo=adelanto" class="text-decoration-none small">Ver adelantos <i class="bi bi-arrow-right"></i></a>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-lg-3">
        <div class="card border-danger h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="text-muted mb-1">DEUDA SOCIOS</h6>
                        <h3 class="text-danger mb-0"><?= formatMoney($deudaSocios['total'] ?? 0) ?></h3>
                    </div>
                    <div class="bg-danger bg-opacity-10 p-2 rounded">
                        <i class="bi bi-people text-danger fs-4"></i>
                    </div>
                </div>
                <hr class="my-2">
                <div class="small text-muted"><?= $deudaSocios['acciones_con_deuda'] ?? 0 ?> acciones · <?= $deudaSocios['meses_pendientes'] ?? 0 ?> meses vencidos</div>
            </div>
            <div class="card-footer bg-transparent">
                <a href="<?= BASE_URL ?>/modules/reportes/deudores.php" class="text-decoration-none small">Ver deudores <i class="bi bi-arrow-right"></i></a>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-lg-3">
        <div class="card bg-primary text-white h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="text-white-50 mb-1">POSICIÓN TOTAL</h6>
                        <h3 class="mb-0"><?= formatMoney($posicionTotal) ?></h3>
                    </div>
                    <div class="bg-white bg-opacity-25 p-2 rounded">
                        <i class="bi bi-graph-up-arrow fs-4"></i>
                    </div>
                </div>
                <hr class="my-2 border-white-50">
                <div class="small text-white-50">Disponible + Por rendir + Por cobrar</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <?php if (!empty($adelantosPendientes)): ?>
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between">
                <span><i class="bi bi-hourglass-split text-warning me-2"></i>Adelantos Pendientes</span>
                <span class="badge bg-warning text-dark"><?= count($adelantosPendientes) ?></span>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Fecha</th><th>Beneficiario</th><th>Concepto</th><th class="text-end">Pendiente</th><th class="no-print"></th></tr></thead>
                    <tbody>
                        <?php foreach ($adelantosPendientes as $adel): ?>
                        <tr>
                            <td><?= formatDate($adel['fecha_entrega']) ?></td>
                            <td><strong><?= htmlspecialchars($adel['beneficiario']) ?></strong></td>
                            <td><?= htmlspecialchars($adel['concepto'] ?? '-') ?></td>
                            <td class="text-end text-danger fw-bold"><?= formatMoney($adel['pendiente']) ?></td>
                            <td class="no-print"><a href="<?= BASE_URL ?>/modules/fondos-rendir/rendir.php?id=<?= $adel['id'] ?>" class="btn btn-sm btn-warning"><i class="bi bi-receipt"></i></a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header"><i class="bi bi-clock-history me-2"></i>Últimos Movimientos</div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Fecha</th><th>Concepto</th><th>Método</th><th class="text-end">Monto</th></tr></thead>
                    <tbody>
                        <?php foreach ($ultimosMovimientos as $mov): ?>
                        <tr>
                            <td class="small"><?= date('d/m H:i', strtotime($mov['fecha'])) ?></td>
                            <td class="small"><?= htmlspecialchars($mov['concepto']) ?></td>
                            <td><span class="badge bg-<?= $mov['metodo'] === 'efectivo' ? 'success' : 'primary' ?>"><?= ucfirst($mov['metodo']) ?></span></td>
                            <td class="text-end <?= $mov['tipo'] === 'ingreso' ? 'text-success' : 'text-danger' ?>"><?= $mov['tipo'] === 'ingreso' ? '+' : '-' ?><?= formatMoney($mov['monto']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-header"><i class="bi bi-calculator me-2"></i>Resultado <?= $anio_actual ?></div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2"><span class="text-success"><i class="bi bi-arrow-down-circle me-1"></i>Ingresos</span><strong class="text-success"><?= formatMoney($resumenPeriodo['ingresos'] ?? 0) ?></strong></div>
                <div class="d-flex justify-content-between mb-2"><span class="text-danger"><i class="bi bi-arrow-up-circle me-1"></i>Gastos</span><strong class="text-danger"><?= formatMoney($resumenPeriodo['gastos'] ?? 0) ?></strong></div>
                <hr>
                <div class="d-flex justify-content-between"><span class="fw-bold">Resultado</span><strong class="<?= $resultadoPeriodo >= 0 ? 'text-success' : 'text-danger' ?> fs-5"><?= formatMoney($resultadoPeriodo) ?></strong></div>
            </div>
        </div>

        <div class="card no-print">
            <div class="card-header"><i class="bi bi-lightning me-2"></i>Acciones Rápidas</div>
            <div class="card-body">
                <a href="<?= BASE_URL ?>/modules/consumos/" class="btn btn-success w-100 mb-2"><i class="bi bi-cash me-1"></i> Cobrar Consumo</a>
                <a href="<?= BASE_URL ?>/modules/gastos/crear.php" class="btn btn-outline-danger w-100 mb-2"><i class="bi bi-cart-dash me-1"></i> Registrar Gasto</a>
                <a href="<?= BASE_URL ?>/modules/fondos-rendir/crear.php?tipo=adelanto" class="btn btn-outline-warning w-100"><i class="bi bi-cash-stack me-1"></i> Entregar Adelanto</a>
            </div>
        </div>
    </div>
</div>

<style>@media print { .no-print { display: none !important; } .sidebar { display: none !important; } .main-content { margin-left: 0 !important; } }</style>

<?php require_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
