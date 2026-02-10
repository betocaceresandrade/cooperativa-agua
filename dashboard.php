<?php
/**
 * Dashboard Principal (Inicio)
 * Cooperativa de Agua Potable "Virgen de las Nieves"
 */

$pageTitle = 'Inicio';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/functions.php';

// Obtener datos para KPIs
$saldos = getSaldosCaja();
$year = date('Y');
$month = date('m');
$ingresosMes = getIngresosMes($year, $month);

// Cuentas por cobrar basado en consumos_anuales
$cuentasPorCobrar = fetchOne(
    "SELECT COALESCE(SUM(monto), 0) as total FROM consumos_anuales WHERE estado = 'pendiente'"
)['total'] ?? 0;

// Datos para gráficos (últimos 6 meses)
$chartLabels = [];
$chartIngresos = [];
$chartGastos = [];

for ($i = 5; $i >= 0; $i--) {
    $fecha = strtotime("-$i months");
    $y = date('Y', $fecha);
    $m = date('m', $fecha);
    $chartLabels[] = getNombreMes(intval($m)) . ' ' . $y;
    $chartIngresos[] = getIngresosMes($y, $m);
    $chartGastos[] = getGastosMes($y, $m);
}

// Estadísticas de acciones por estado
$estadisticas = fetchAll(
    "SELECT estado, COUNT(*) as total FROM acciones GROUP BY estado"
);
$estadosCounts = [];
foreach ($estadisticas as $e) {
    $estadosCounts[$e['estado']] = $e['total'];
}

$totalActivos = $estadosCounts['ACTIVO'] ?? 0;
$totalCortados = $estadosCounts['CORTADO'] ?? 0;
$totalBaja = $estadosCounts['BAJA'] ?? 0;
$totalSinInst = $estadosCounts['SIN INST.'] ?? 0;

// Deudores con más meses
$deudoresRecientes = fetchAll(
    "SELECT a.id, a.numero_accion, s.nombre as socio_nombre, s.numero_socio, z.nombre as zona_nombre,
            COUNT(ca.id) as meses_deuda, SUM(ca.monto) as total_deuda
     FROM acciones a
     JOIN socios s ON a.socio_id = s.id
     LEFT JOIN zonas z ON a.zona_id = z.id
     JOIN consumos_anuales ca ON ca.accion_id = a.id AND ca.estado = 'pendiente'
     WHERE a.estado = 'ACTIVO'
     GROUP BY a.id
     ORDER BY meses_deuda DESC, total_deuda DESC
     LIMIT 5"
);
?>

<!-- Boton flotante de ayuda -->
<a href="<?= BASE_URL ?>/ayuda.php" class="btn btn-info position-fixed d-flex align-items-center" style="bottom: 25px; right: 25px; z-index: 1050; border-radius: 50px; padding: 12px 20px; box-shadow: 0 4px 15px rgba(7,159,234,0.4); font-weight: 500;" title="Centro de Ayuda">
    <i class="bi bi-question-circle me-2"></i> Como usar
</a>

<div class="page-header d-flex justify-content-between align-items-center">
        <i class="bi bi-question-circle me-1"></i> Ayuda
    </a>
    <div>
        <h1 class="page-title">Inicio</h1>
        <p class="page-subtitle">Bienvenido al Sistema de Gestión</p>
    </div>
    <div class="text-muted">
        <i class="bi bi-calendar3"></i>
        <?= date('d/m/Y') ?>
    </div>
</div>

<!-- KPIs -->
<div class="row g-4 mb-4">
    <div class="col-md-6 col-lg-3">
        <div class="kpi-card">
            <div class="d-flex align-items-center">
                <div class="kpi-icon bg-primary-light me-3">
                    <i class="bi bi-cash-stack"></i>
                </div>
                <div>
                    <div class="kpi-value"><?= formatMoney($saldos['efectivo']) ?></div>
                    <div class="kpi-label">Saldo Efectivo</div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-lg-3">
        <div class="kpi-card">
            <div class="d-flex align-items-center">
                <div class="kpi-icon bg-success-light me-3">
                    <i class="bi bi-qr-code"></i>
                </div>
                <div>
                    <div class="kpi-value"><?= formatMoney($saldos['qr']) ?></div>
                    <div class="kpi-label">Saldo QR</div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-lg-3">
        <div class="kpi-card">
            <div class="d-flex align-items-center">
                <div class="kpi-icon bg-warning-light me-3">
                    <i class="bi bi-graph-up-arrow"></i>
                </div>
                <div>
                    <div class="kpi-value"><?= formatMoney($ingresosMes) ?></div>
                    <div class="kpi-label">Ingresos del Mes</div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-lg-3">
        <div class="kpi-card">
            <div class="d-flex align-items-center">
                <div class="kpi-icon bg-danger-light me-3">
                    <i class="bi bi-exclamation-triangle"></i>
                </div>
                <div>
                    <div class="kpi-value"><?= formatMoney($cuentasPorCobrar) ?></div>
                    <div class="kpi-label">Cuentas por Cobrar</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Acciones Rápidas -->
<div class="row g-4 mb-4">
    <div class="col-md-4">
        <a href="<?= BASE_URL ?>/modules/consumos/" class="quick-action">
            <i class="bi bi-droplet"></i>
            <div>
                <strong>Cobrar Consumo</strong>
                <small class="d-block text-muted">Buscar acción para cobrar</small>
            </div>
        </a>
    </div>
    <div class="col-md-4">
        <a href="<?= BASE_URL ?>/modules/socios/" class="quick-action">
            <i class="bi bi-people"></i>
            <div>
                <strong>Ver Socios</strong>
                <small class="d-block text-muted">Gestionar socios y acciones</small>
            </div>
        </a>
    </div>
    <div class="col-md-4">
        <a href="<?= BASE_URL ?>/modules/notificaciones/" class="quick-action">
            <i class="bi bi-envelope-paper"></i>
            <div>
                <strong>Imprimir Notificaciones</strong>
                <small class="d-block text-muted">Avisos de deuda</small>
            </div>
        </a>
    </div>
</div>

<!-- Historial de Movimientos -->
<div class="row g-4 mb-4">
    <div class="col-md-6">
        <a href="<?= BASE_URL ?>/modules/ingresos/" class="quick-action bg-success bg-opacity-10 border-success">
            <i class="bi bi-receipt text-success"></i>
            <div>
                <strong class="text-success">Historial de Ingresos</strong>
                <small class="d-block text-muted">Ver y reimprimir recibos de pago</small>
            </div>
        </a>
    </div>
    <div class="col-md-6">
        <a href="<?= BASE_URL ?>/modules/gastos/" class="quick-action bg-danger bg-opacity-10 border-danger">
            <i class="bi bi-cart-dash text-danger"></i>
            <div>
                <strong class="text-danger">Historial de Gastos</strong>
                <small class="d-block text-muted">Ver, editar e imprimir comprobantes</small>
            </div>
        </a>
    </div>
</div>

<div class="row g-4">
    <!-- Gráfico de Ingresos vs Gastos -->
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-bar-chart me-2"></i>Ingresos vs Gastos</span>
                <small class="text-muted">Últimos 6 meses</small>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="chartIngresosGastos"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Estadísticas de Acciones -->
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-pie-chart me-2"></i>Estado de Acciones
            </div>
            <div class="card-body">
                <div class="chart-container" style="height: 200px;">
                    <canvas id="chartAcciones"></canvas>
                </div>
                <div class="row text-center mt-3">
                    <div class="col-6">
                        <div class="fs-4 fw-bold text-success"><?= $totalActivos ?></div>
                        <small class="text-muted">Activos</small>
                    </div>
                    <div class="col-6">
                        <div class="fs-4 fw-bold text-danger"><?= $totalCortados ?></div>
                        <small class="text-muted">Cortados</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Deudores Recientes -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-exclamation-circle me-2"></i>Principales Deudores</span>
                <a href="<?= BASE_URL ?>/modules/reportes/deudores.php" class="btn btn-sm btn-outline-primary">
                    Ver todos <i class="bi bi-arrow-right"></i>
                </a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Acción</th>
                                <th>Socio</th>
                                <th>Zona</th>
                                <th class="text-center">Meses</th>
                                <th class="text-end">Deuda Total</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($deudoresRecientes)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    <i class="bi bi-check-circle fs-1 d-block mb-2 text-success"></i>
                                    No hay deudores pendientes
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($deudoresRecientes as $deudor): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($deudor['numero_accion']) ?></strong></td>
                                <td><?= htmlspecialchars($deudor['socio_nombre']) ?></td>
                                <td><?= htmlspecialchars($deudor['zona_nombre'] ?? '-') ?></td>
                                <td class="text-center">
                                    <span class="badge bg-danger"><?= $deudor['meses_deuda'] ?></span>
                                </td>
                                <td class="text-end fw-bold text-danger">
                                    <?= formatMoney($deudor['total_deuda']) ?>
                                </td>
                                <td class="text-center">
                                    <a href="<?= BASE_URL ?>/modules/consumos/cobrar.php?accion_id=<?= $deudor['id'] ?>"
                                       class="btn btn-sm btn-primary">
                                        <i class="bi bi-cash"></i> Cobrar
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$jsonLabels = json_encode($chartLabels);
$jsonIngresos = json_encode($chartIngresos);
$jsonGastos = json_encode($chartGastos);

$pageScripts = <<<HTML
<script>
// Gráfico de Ingresos vs Gastos
const ctxIG = document.getElementById('chartIngresosGastos').getContext('2d');
new Chart(ctxIG, {
    type: 'bar',
    data: {
        labels: {$jsonLabels},
        datasets: [{
            label: 'Ingresos',
            data: {$jsonIngresos},
            backgroundColor: 'rgba(40, 167, 69, 0.7)',
            borderColor: 'rgba(40, 167, 69, 1)',
            borderWidth: 1
        }, {
            label: 'Gastos',
            data: {$jsonGastos},
            backgroundColor: 'rgba(220, 53, 69, 0.7)',
            borderColor: 'rgba(220, 53, 69, 1)',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'top' }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) { return 'Bs. ' + value; }
                }
            }
        }
    }
});

// Gráfico de Acciones
const ctxA = document.getElementById('chartAcciones').getContext('2d');
new Chart(ctxA, {
    type: 'doughnut',
    data: {
        labels: ['Activos', 'Cortados', 'Baja', 'Sin Inst.'],
        datasets: [{
            data: [{$totalActivos}, {$totalCortados}, {$totalBaja}, {$totalSinInst}],
            backgroundColor: [
                'rgba(40, 167, 69, 0.8)',
                'rgba(220, 53, 69, 0.8)',
                'rgba(108, 117, 125, 0.8)',
                'rgba(23, 162, 184, 0.8)'
            ],
            borderWidth: 0
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'bottom' }
        }
    }
});
</script>
HTML;

require_once __DIR__ . '/includes/footer.php';
?>
