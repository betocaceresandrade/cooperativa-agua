<?php
/**
 * Reporte: Listado de Socios y Acciones
 * Con filtro unificado (zona, socio, acción, CI)
 * Vista por Acción o por Socio
 */

$pageTitle = 'Reporte de Socios';
require_once dirname(dirname(__DIR__)) . '/includes/header.php';
require_once dirname(dirname(__DIR__)) . '/includes/functions.php';

$zonas = getZonas();

// Filtros
$buscar = trim($_GET['buscar'] ?? '');
$zona_id = $_GET['zona_id'] ?? '';
$estado = $_GET['estado'] ?? '';
$vista = $_GET['vista'] ?? 'acciones'; // 'acciones' o 'socios'

if ($vista === 'acciones') {
    // Vista por Acción - muestra cada acción como una fila
    $sql = "SELECT a.id as accion_id, a.numero_accion, a.estado as estado_accion,
                   s.id as socio_id, s.nombre as socio_nombre, s.numero_socio,
                   s.ci, s.celular, s.direccion,
                   z.id as zona_id, z.nombre as zona_nombre,
                   t.nombre as tarifa_nombre, t.monto as tarifa_monto,
                   (SELECT COUNT(*) FROM consumos_anuales ca
                    WHERE ca.accion_id = a.id AND ca.estado = 'pendiente' AND (ca.anio < YEAR(CURDATE()) OR (ca.anio = YEAR(CURDATE()) AND ca.mes < MONTH(CURDATE())))) as meses_pendientes,
                   (SELECT COALESCE(SUM(ca.monto), 0) FROM consumos_anuales ca
                    WHERE ca.accion_id = a.id AND ca.estado = 'pendiente' AND (ca.anio < YEAR(CURDATE()) OR (ca.anio = YEAR(CURDATE()) AND ca.mes < MONTH(CURDATE())))) as deuda
            FROM acciones a
            JOIN socios s ON a.socio_id = s.id
            LEFT JOIN zonas z ON a.zona_id = z.id
            LEFT JOIN tipos_tarifa t ON a.tipo_tarifa_id = t.id
            WHERE 1=1";

    $params = [];

    if (!empty($buscar)) {
        $sql .= " AND (a.numero_accion LIKE ? OR s.nombre LIKE ? OR s.numero_socio LIKE ? OR s.ci LIKE ?)";
        $params[] = '%' . $buscar . '%';
        $params[] = '%' . $buscar . '%';
        $params[] = '%' . $buscar . '%';
        $params[] = '%' . $buscar . '%';
    }

    if (!empty($zona_id)) {
        $sql .= " AND a.zona_id = ?";
        $params[] = $zona_id;
    }

    if (!empty($estado)) {
        $sql .= " AND a.estado = ?";
        $params[] = $estado;
    }

    $sql .= " ORDER BY a.numero_accion";

    $registros = fetchAll($sql, $params);
    $totalTarifas = array_sum(array_column($registros, 'tarifa_monto'));
    $totalDeuda = array_sum(array_column($registros, 'deuda'));

} else {
    // Vista por Socio - agrupa acciones
    $sql = "SELECT s.id as socio_id, s.nombre as socio_nombre, s.numero_socio,
                   s.ci, s.celular, s.direccion, s.estado as estado_socio,
                   COUNT(a.id) as num_acciones,
                   GROUP_CONCAT(a.numero_accion ORDER BY a.numero_accion SEPARATOR ', ') as acciones_lista,
                   COALESCE(SUM(t.monto), 0) as tarifa_total,
                   (SELECT COALESCE(SUM(ca.monto), 0) FROM consumos_anuales ca
                    JOIN acciones a2 ON ca.accion_id = a2.id
                    WHERE a2.socio_id = s.id AND ca.estado = 'pendiente' AND (ca.anio < YEAR(CURDATE()) OR (ca.anio = YEAR(CURDATE()) AND ca.mes < MONTH(CURDATE())))) as deuda_total
            FROM socios s
            LEFT JOIN acciones a ON a.socio_id = s.id
            LEFT JOIN tipos_tarifa t ON a.tipo_tarifa_id = t.id
            WHERE 1=1";

    $params = [];

    if (!empty($buscar)) {
        $sql .= " AND (s.nombre LIKE ? OR s.numero_socio LIKE ? OR s.ci LIKE ?
                       OR a.numero_accion LIKE ?)";
        $params[] = '%' . $buscar . '%';
        $params[] = '%' . $buscar . '%';
        $params[] = '%' . $buscar . '%';
        $params[] = '%' . $buscar . '%';
    }

    if (!empty($zona_id)) {
        $sql .= " AND a.zona_id = ?";
        $params[] = $zona_id;
    }

    if (!empty($estado)) {
        $sql .= " AND s.estado = ?";
        $params[] = $estado;
    }

    $sql .= " GROUP BY s.id ORDER BY s.numero_socio";

    $registros = fetchAll($sql, $params);
    $totalTarifas = array_sum(array_column($registros, 'tarifa_total'));
    $totalDeuda = array_sum(array_column($registros, 'deuda_total'));
}

// Contar por estado
$estadisticas = fetchAll(
    "SELECT estado, COUNT(*) as cantidad FROM acciones GROUP BY estado"
);
$conteoEstados = [];
foreach ($estadisticas as $e) {
    $conteoEstados[$e['estado']] = $e['cantidad'];
}
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/modules/reportes/">Reportes</a></li>
                <li class="breadcrumb-item active">Socios</li>
            </ol>
        </nav>
        <h1 class="page-title">Reporte de Socios y Acciones</h1>
    </div>
    <button onclick="window.print()" class="btn btn-outline-primary no-print">
        <i class="bi bi-printer me-1"></i> Imprimir
    </button>
</div>

<!-- Filtros con buscador unificado -->
<div class="card mb-4 no-print">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label">Buscar (socio, acción, N° socio, CI)</label>
                <input type="text" class="form-control" name="buscar"
                       placeholder="Buscar por nombre, N° socio, CI o N° acción..."
                       value="<?= htmlspecialchars($buscar) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Zona</label>
                <select class="form-select" name="zona_id">
                    <option value="">Todas</option>
                    <?php foreach ($zonas as $z): ?>
                    <option value="<?= $z['id'] ?>" <?= $zona_id == $z['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($z['nombre']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Estado</label>
                <select class="form-select" name="estado">
                    <option value="">Todos</option>
                    <option value="ACTIVO" <?= $estado === 'ACTIVO' ? 'selected' : '' ?>>Activo</option>
                    <option value="CORTADO" <?= $estado === 'CORTADO' ? 'selected' : '' ?>>Cortado</option>
                    <option value="BAJA" <?= $estado === 'BAJA' ? 'selected' : '' ?>>Baja</option>
                    <option value="SIN INST." <?= $estado === 'SIN INST.' ? 'selected' : '' ?>>Sin Inst.</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Vista</label>
                <select class="form-select" name="vista">
                    <option value="acciones" <?= $vista === 'acciones' ? 'selected' : '' ?>>Por Acción</option>
                    <option value="socios" <?= $vista === 'socios' ? 'selected' : '' ?>>Por Socio</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-search"></i> Filtrar
                </button>
                <a href="<?= BASE_URL ?>/modules/reportes/socios.php" class="btn btn-outline-secondary">
                    <i class="bi bi-x-circle"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Resumen -->
<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="card bg-primary text-white">
            <div class="card-body text-center">
                <div class="fs-2 fw-bold"><?= count($registros) ?></div>
                <div><?= $vista === 'acciones' ? 'Acciones' : 'Socios' ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-success text-white">
            <div class="card-body text-center">
                <div class="fs-2 fw-bold"><?= $conteoEstados['ACTIVO'] ?? 0 ?></div>
                <div>Activos</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-warning text-dark">
            <div class="card-body text-center">
                <div class="fs-2 fw-bold"><?= formatMoney($totalTarifas) ?></div>
                <div>Tarifa Mensual</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-danger text-white">
            <div class="card-body text-center">
                <div class="fs-2 fw-bold"><?= formatMoney($totalDeuda) ?></div>
                <div>Deuda Total</div>
            </div>
        </div>
    </div>
</div>

<!-- Lista -->
<div class="card">
    <div class="card-header">
        <i class="bi bi-<?= $vista === 'acciones' ? 'water' : 'people' ?> me-2"></i>
        <?= $vista === 'acciones' ? 'Listado por Acción' : 'Padrón de Socios' ?>
        (<?= count($registros) ?>)
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <?php if ($vista === 'acciones'): ?>
            <!-- Vista por Acción -->
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Acción</th>
                        <th>Socio</th>
                        <th>CI</th>
                        <th>Celular</th>
                        <th>Zona</th>
                        <th>Tarifa</th>
                        <th class="text-center">Meses Pend.</th>
                        <th class="text-end">Deuda</th>
                        <th class="text-center">Estado</th>
                        <th class="no-print"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($registros as $r): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($r['numero_accion']) ?></strong></td>
                        <td>
                            <a href="<?= BASE_URL ?>/modules/socios/ver.php?id=<?= $r['socio_id'] ?>" class="text-decoration-none">
                                <?= htmlspecialchars($r['socio_nombre']) ?>
                            </a>
                            <br><small class="text-muted">N° <?= htmlspecialchars($r['numero_socio']) ?></small>
                        </td>
                        <td><?= htmlspecialchars($r['ci'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($r['celular'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($r['zona_nombre'] ?? '-') ?></td>
                        <td>
                            <?= formatMoney($r['tarifa_monto'] ?? 0) ?>
                            <?php if ($r['tarifa_nombre']): ?>
                            <br><small class="text-muted"><?= htmlspecialchars($r['tarifa_nombre']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php if ($r['meses_pendientes'] > 0): ?>
                            <span class="badge <?= $r['meses_pendientes'] >= 3 ? 'bg-danger' : 'bg-warning text-dark' ?>">
                                <?= $r['meses_pendientes'] ?>
                            </span>
                            <?php else: ?>
                            <span class="badge bg-success">0</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end <?= $r['deuda'] > 0 ? 'text-danger fw-bold' : '' ?>">
                            <?= formatMoney($r['deuda']) ?>
                        </td>
                        <td class="text-center">
                            <?php
                            $badgeClass = match($r['estado_accion']) {
                                'ACTIVO' => 'bg-success',
                                'CORTADO' => 'bg-danger',
                                'BAJA' => 'bg-secondary',
                                'SIN INST.' => 'bg-info',
                                default => 'bg-secondary'
                            };
                            ?>
                            <span class="badge <?= $badgeClass ?>">
                                <?= htmlspecialchars($r['estado_accion']) ?>
                            </span>
                        </td>
                        <td class="no-print">
                            <a href="<?= BASE_URL ?>/modules/consumos/cobrar.php?accion_id=<?= $r['accion_id'] ?>"
                               class="btn btn-sm btn-outline-primary" title="Ver Consumos">
                                <i class="bi bi-calendar3"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="table-primary">
                        <td colspan="5"><strong>TOTAL</strong></td>
                        <td><strong><?= formatMoney($totalTarifas) ?></strong></td>
                        <td></td>
                        <td class="text-end"><strong><?= formatMoney($totalDeuda) ?></strong></td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            </table>
            <?php else: ?>
            <!-- Vista por Socio -->
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>N° Socio</th>
                        <th>Nombre</th>
                        <th>CI</th>
                        <th>Celular</th>
                        <th>Acciones</th>
                        <th class="text-center">Cant.</th>
                        <th class="text-end">Tarifa</th>
                        <th class="text-end">Deuda</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($registros as $r): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($r['numero_socio']) ?></strong></td>
                        <td>
                            <a href="<?= BASE_URL ?>/modules/socios/ver.php?id=<?= $r['socio_id'] ?>" class="text-decoration-none">
                                <?= htmlspecialchars($r['socio_nombre']) ?>
                            </a>
                        </td>
                        <td><?= htmlspecialchars($r['ci'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($r['celular'] ?? '-') ?></td>
                        <td>
                            <small><?= htmlspecialchars($r['acciones_lista'] ?? '-') ?></small>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-primary"><?= $r['num_acciones'] ?></span>
                        </td>
                        <td class="text-end"><?= formatMoney($r['tarifa_total']) ?></td>
                        <td class="text-end <?= $r['deuda_total'] > 0 ? 'text-danger fw-bold' : '' ?>">
                            <?= formatMoney($r['deuda_total']) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="table-primary">
                        <td colspan="5"><strong>TOTAL</strong></td>
                        <td class="text-center"><strong><?= array_sum(array_column($registros, 'num_acciones')) ?></strong></td>
                        <td class="text-end"><strong><?= formatMoney($totalTarifas) ?></strong></td>
                        <td class="text-end"><strong><?= formatMoney($totalDeuda) ?></strong></td>
                    </tr>
                </tfoot>
            </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Resumen por Estado (solo en vista acciones) -->
<?php if ($vista === 'acciones'): ?>
<div class="row mt-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-pie-chart me-2"></i>Acciones por Estado
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <?php
                    $estados = [
                        'ACTIVO' => ['color' => 'success', 'icono' => 'check-circle'],
                        'CORTADO' => ['color' => 'danger', 'icono' => 'x-circle'],
                        'BAJA' => ['color' => 'secondary', 'icono' => 'dash-circle'],
                        'SIN INST.' => ['color' => 'info', 'icono' => 'hourglass']
                    ];
                    foreach ($estados as $est => $config):
                        $cantidad = $conteoEstados[$est] ?? 0;
                    ?>
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <span>
                            <i class="bi bi-<?= $config['icono'] ?> text-<?= $config['color'] ?> me-2"></i>
                            <?= $est ?>
                        </span>
                        <span class="badge bg-<?= $config['color'] ?>"><?= $cantidad ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<style>
@media print {
    .no-print { display: none !important; }
    .card { border: 1px solid #ddd !important; }
}
</style>

<?php require_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
