<?php
/**
 * Reporte: Recaudación Mensual
 */

$pageTitle = 'Recaudación Mensual';
require_once dirname(dirname(__DIR__)) . '/includes/header.php';
require_once dirname(dirname(__DIR__)) . '/includes/functions.php';

// Últimos 12 meses
$meses = [];
for ($i = 11; $i >= 0; $i--) {
    $fecha = strtotime("-$i months");
    $periodo = date('Y-m', $fecha);

    $datos = fetchOne(
        "SELECT
            COUNT(*) as num_pagos,
            COALESCE(SUM(monto_total), 0) as total,
            COALESCE(SUM(CASE WHEN metodo_pago = 'efectivo' THEN monto_total ELSE 0 END), 0) as efectivo,
            COALESCE(SUM(CASE WHEN metodo_pago = 'qr' THEN monto_total ELSE 0 END), 0) as qr
         FROM pagos
         WHERE DATE_FORMAT(fecha_pago, '%Y-%m') = ? AND anulado = 0",
        [$periodo]
    );

    $meses[] = [
        'periodo' => $periodo,
        'nombre' => formatPeriodo($periodo),
        'num_pagos' => intval($datos['num_pagos']),
        'total' => floatval($datos['total']),
        'efectivo' => floatval($datos['efectivo']),
        'qr' => floatval($datos['qr'])
    ];
}

$totalGeneral = array_sum(array_column($meses, 'total'));
$totalEfectivo = array_sum(array_column($meses, 'efectivo'));
$totalQR = array_sum(array_column($meses, 'qr'));
$promedio = $totalGeneral / 12;
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/modules/reportes/">Reportes</a></li>
                <li class="breadcrumb-item active">Recaudación</li>
            </ol>
        </nav>
        <h1 class="page-title">Recaudación Mensual</h1>
        <p class="page-subtitle">Últimos 12 meses</p>
    </div>
    <button onclick="window.print()" class="btn btn-outline-primary no-print">
        <i class="bi bi-printer me-1"></i> Imprimir
    </button>
</div>

<!-- Resumen -->
<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="card bg-primary text-white">
            <div class="card-body text-center">
                <div class="fs-2 fw-bold"><?= formatMoney($totalGeneral) ?></div>
                <div>Total 12 meses</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-info text-white">
            <div class="card-body text-center">
                <div class="fs-2 fw-bold"><?= formatMoney($promedio) ?></div>
                <div>Promedio Mensual</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body text-center">
                <div class="fs-2 fw-bold text-success"><?= formatMoney($totalEfectivo) ?></div>
                <div class="text-muted">En Efectivo</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body text-center">
                <div class="fs-2 fw-bold text-primary"><?= formatMoney($totalQR) ?></div>
                <div class="text-muted">Por QR</div>
            </div>
        </div>
    </div>
</div>

<!-- Gráfico -->
<div class="card mb-4">
    <div class="card-header">
        <i class="bi bi-graph-up me-2"></i>Evolución de Recaudación
    </div>
    <div class="card-body">
        <canvas id="chartRecaudacion" height="100"></canvas>
    </div>
</div>

<!-- Tabla detallada -->
<div class="card">
    <div class="card-header">
        <i class="bi bi-table me-2"></i>Detalle por Mes
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Período</th>
                        <th class="text-center">N° Pagos</th>
                        <th class="text-end">Efectivo</th>
                        <th class="text-end">QR</th>
                        <th class="text-end">Total</th>
                        <th class="text-center">Variación</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($meses as $i => $mes): ?>
                    <?php
                        $variacion = 0;
                        if ($i > 0 && $meses[$i-1]['total'] > 0) {
                            $variacion = (($mes['total'] - $meses[$i-1]['total']) / $meses[$i-1]['total']) * 100;
                        }
                    ?>
                    <tr>
                        <td><strong><?= $mes['nombre'] ?></strong></td>
                        <td class="text-center"><?= $mes['num_pagos'] ?></td>
                        <td class="text-end text-success"><?= formatMoney($mes['efectivo']) ?></td>
                        <td class="text-end text-primary"><?= formatMoney($mes['qr']) ?></td>
                        <td class="text-end fw-bold"><?= formatMoney($mes['total']) ?></td>
                        <td class="text-center">
                            <?php if ($i > 0 && $variacion != 0): ?>
                            <span class="<?= $variacion > 0 ? 'text-success' : 'text-danger' ?>">
                                <i class="bi bi-<?= $variacion > 0 ? 'arrow-up' : 'arrow-down' ?>"></i>
                                <?= number_format(abs($variacion), 1) ?>%
                            </span>
                            <?php else: ?>
                            -
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="table-primary">
                        <th>TOTAL</th>
                        <th class="text-center"><?= array_sum(array_column($meses, 'num_pagos')) ?></th>
                        <th class="text-end"><?= formatMoney($totalEfectivo) ?></th>
                        <th class="text-end"><?= formatMoney($totalQR) ?></th>
                        <th class="text-end"><?= formatMoney($totalGeneral) ?></th>
                        <th></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<?php
$labels = json_encode(array_column($meses, 'nombre'));
$dataEfectivo = json_encode(array_column($meses, 'efectivo'));
$dataQR = json_encode(array_column($meses, 'qr'));

$pageScripts = <<<HTML
<script>
new Chart(document.getElementById('chartRecaudacion'), {
    type: 'bar',
    data: {
        labels: $labels,
        datasets: [{
            label: 'Efectivo',
            data: $dataEfectivo,
            backgroundColor: 'rgba(40, 167, 69, 0.7)'
        }, {
            label: 'QR',
            data: $dataQR,
            backgroundColor: 'rgba(7, 159, 234, 0.7)'
        }]
    },
    options: {
        responsive: true,
        scales: {
            x: { stacked: true },
            y: {
                stacked: true,
                beginAtZero: true,
                ticks: { callback: v => 'Bs. ' + v }
            }
        }
    }
});
</script>
HTML;

require_once dirname(dirname(__DIR__)) . '/includes/footer.php';
?>
