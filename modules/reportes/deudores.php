<?php
/**
 * Reporte: Deudores por Acción
 * Calcula deuda dinámicamente: meses pasados sin registro de pago
 */

$pageTitle = 'Reporte de Deudores';
require_once dirname(dirname(__DIR__)) . '/includes/header.php';
require_once dirname(dirname(__DIR__)) . '/includes/functions.php';

$zonas = getZonas();

// Filtros
$buscar = trim($_GET['buscar'] ?? '');
$zona_id = $_GET['zona_id'] ?? '';
$meses_minimo = intval($_GET['meses_minimo'] ?? 1);
$ordenar = $_GET['ordenar'] ?? 'deuda';

// Fecha actual
$anio_actual = intval(date('Y'));
$mes_actual = intval(date('n'));

// Calcular meses pasados del año actual (sin incluir el mes actual)
$meses_pasados_anio = $mes_actual - 1; // ej: si es Feb, solo Ene cuenta (1 mes)

// Para cada acción, calcular:
// - Meses que debería haber pagado = meses_pasados_anio
// - Meses que efectivamente pagó = registros con estado='pagado' para meses < mes_actual
// - Deuda = diferencia * tarifa

$sql = "SELECT a.id as accion_id, a.numero_accion, a.estado as estado_accion,
               s.id as socio_id, s.nombre as socio_nombre, s.numero_socio, s.celular,
               z.id as zona_id, z.nombre as zona_nombre,
               t.monto as tarifa_monto,
               ? as meses_esperados,
               (SELECT COUNT(*) FROM consumos_anuales ca 
                WHERE ca.accion_id = a.id AND ca.anio = ? AND ca.mes < ? 
                AND ca.estado IN ('pagado', 'no_cobrable')) as meses_cubiertos
        FROM acciones a
        JOIN socios s ON a.socio_id = s.id
        LEFT JOIN zonas z ON a.zona_id = z.id
        JOIN tipos_tarifa t ON a.tipo_tarifa_id = t.id
        WHERE a.estado = 'ACTIVO'";

$params = [$meses_pasados_anio, $anio_actual, $mes_actual];

if (!empty($buscar)) {
    $sql .= " AND (a.numero_accion LIKE ? OR s.nombre LIKE ? OR s.numero_socio LIKE ?)";
    $params[] = '%' . $buscar . '%';
    $params[] = '%' . $buscar . '%';
    $params[] = '%' . $buscar . '%';
}

if (!empty($zona_id)) {
    $sql .= " AND a.zona_id = ?";
    $params[] = $zona_id;
}

$acciones_raw = fetchAll($sql, $params);

// Calcular deuda para cada acción
$deudores = [];
foreach ($acciones_raw as $a) {
    $meses_deuda = $a['meses_esperados'] - $a['meses_cubiertos'];
    if ($meses_deuda >= $meses_minimo) {
        $a['meses_deuda'] = $meses_deuda;
        $a['total_deuda'] = $meses_deuda * $a['tarifa_monto'];
        $deudores[] = $a;
    }
}

// Ordenar
usort($deudores, function($a, $b) use ($ordenar) {
    return match($ordenar) {
        'meses' => $b['meses_deuda'] <=> $a['meses_deuda'],
        'nombre' => strcmp($a['socio_nombre'], $b['socio_nombre']),
        'accion' => strcmp($a['numero_accion'], $b['numero_accion']),
        'zona' => strcmp($a['zona_nombre'] ?? '', $b['zona_nombre'] ?? ''),
        default => $b['total_deuda'] <=> $a['total_deuda']
    };
});

// Totales
$totalDeuda = array_sum(array_column($deudores, 'total_deuda'));
$totalMeses = array_sum(array_column($deudores, 'meses_deuda'));

// Por zona
$deudaPorZona = [];
foreach ($deudores as $d) {
    $zona = $d['zona_nombre'] ?? 'Sin zona';
    if (!isset($deudaPorZona[$zona])) {
        $deudaPorZona[$zona] = ['cantidad' => 0, 'deuda' => 0, 'meses' => 0];
    }
    $deudaPorZona[$zona]['cantidad']++;
    $deudaPorZona[$zona]['deuda'] += $d['total_deuda'];
    $deudaPorZona[$zona]['meses'] += $d['meses_deuda'];
}
arsort($deudaPorZona);
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/modules/reportes/">Reportes</a></li>
                <li class="breadcrumb-item active">Deudores</li>
            </ol>
        </nav>
        <h1 class="page-title">Reporte de Deudores</h1>
        <p class="text-muted">Meses vencidos sin pago al <?= date('d/m/Y') ?> (hasta <?= getNombreMes($mes_actual - 1) ?: 'Dic ' . ($anio_actual-1) ?> <?= $mes_actual > 1 ? $anio_actual : '' ?>)</p>
    </div>
    <button onclick="window.print()" class="btn btn-outline-primary no-print">
        <i class="bi bi-printer me-1"></i> Imprimir
    </button>
</div>

<?php if ($meses_pasados_anio == 0): ?>
<div class="alert alert-info">
    <i class="bi bi-info-circle me-2"></i>
    Aún no hay meses vencidos en <?= $anio_actual ?>. El primer mes con deuda será <strong>Enero <?= $anio_actual ?></strong> a partir de <strong>Febrero <?= $anio_actual ?></strong>.
</div>
<?php endif; ?>

<!-- Filtros -->
<div class="card mb-4 no-print">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label">Buscar</label>
                <input type="text" class="form-control" name="buscar" placeholder="Nombre, N° socio o acción..." value="<?= htmlspecialchars($buscar) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Zona</label>
                <select class="form-select" name="zona_id">
                    <option value="">Todas</option>
                    <?php foreach ($zonas as $z): ?>
                    <option value="<?= $z['id'] ?>" <?= $zona_id == $z['id'] ? 'selected' : '' ?>><?= htmlspecialchars($z['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Meses mínimo</label>
                <select class="form-select" name="meses_minimo">
                    <option value="1" <?= $meses_minimo == 1 ? 'selected' : '' ?>>1+</option>
                    <option value="2" <?= $meses_minimo == 2 ? 'selected' : '' ?>>2+</option>
                    <option value="3" <?= $meses_minimo == 3 ? 'selected' : '' ?>>3+</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Ordenar</label>
                <select class="form-select" name="ordenar">
                    <option value="deuda" <?= $ordenar === 'deuda' ? 'selected' : '' ?>>Mayor deuda</option>
                    <option value="meses" <?= $ordenar === 'meses' ? 'selected' : '' ?>>Más meses</option>
                    <option value="nombre" <?= $ordenar === 'nombre' ? 'selected' : '' ?>>Nombre</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i> Filtrar</button>
            </div>
        </form>
    </div>
</div>

<!-- Resumen -->
<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="card bg-danger text-white">
            <div class="card-body text-center">
                <div class="fs-2 fw-bold"><?= count($deudores) ?></div>
                <div>Acciones con Deuda</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-warning text-dark">
            <div class="card-body text-center">
                <div class="fs-2 fw-bold"><?= $totalMeses ?></div>
                <div>Meses Vencidos</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-info text-white">
            <div class="card-body text-center">
                <div class="fs-2 fw-bold"><?= formatMoney($totalDeuda) ?></div>
                <div>Deuda Total</div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-list-ul me-2"></i>Listado de Deudores (<?= count($deudores) ?> acciones)
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Acción</th>
                                <th>Socio</th>
                                <th>Zona</th>
                                <th>Celular</th>
                                <th class="text-center">Meses</th>
                                <th class="text-end">Deuda</th>
                                <th class="no-print"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($deudores)): ?>
                            <tr><td colspan="7" class="text-center py-4 text-muted">No hay deudores</td></tr>
                            <?php endif; ?>
                            <?php foreach ($deudores as $d): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($d['numero_accion']) ?></strong></td>
                                <td>
                                    <a href="<?= BASE_URL ?>/modules/socios/ver.php?id=<?= $d['socio_id'] ?>"><?= htmlspecialchars($d['socio_nombre']) ?></a>
                                    <br><small class="text-muted">N° <?= htmlspecialchars($d['numero_socio']) ?></small>
                                </td>
                                <td><?= htmlspecialchars($d['zona_nombre'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($d['celular'] ?? '-') ?></td>
                                <td class="text-center">
                                    <span class="badge bg-danger"><?= $d['meses_deuda'] ?></span>
                                </td>
                                <td class="text-end text-danger fw-bold"><?= formatMoney($d['total_deuda']) ?></td>
                                <td class="no-print">
                                    <a href="<?= BASE_URL ?>/modules/consumos/cobrar.php?accion_id=<?= $d['accion_id'] ?>" class="btn btn-sm btn-warning"><i class="bi bi-cash"></i></a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <?php if (!empty($deudores)): ?>
                        <tfoot>
                            <tr class="table-danger">
                                <td colspan="4"><strong>TOTAL</strong></td>
                                <td class="text-center"><strong><?= $totalMeses ?></strong></td>
                                <td class="text-end"><strong><?= formatMoney($totalDeuda) ?></strong></td>
                                <td class="no-print"></td>
                            </tr>
                        </tfoot>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><i class="bi bi-geo-alt me-2"></i>Deuda por Zona</div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <?php foreach ($deudaPorZona as $zona => $datos): ?>
                    <div class="list-group-item d-flex justify-content-between">
                        <div>
                            <strong><?= htmlspecialchars($zona) ?></strong>
                            <br><small class="text-muted"><?= $datos['cantidad'] ?> acción(es)</small>
                        </div>
                        <span class="badge bg-danger"><?= formatMoney($datos['deuda']) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>@media print { .no-print { display: none !important; } }</style>

<?php require_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
