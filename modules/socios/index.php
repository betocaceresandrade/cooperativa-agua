<?php
/**
 * Lista de Socios con cálculo dinámico de deuda
 */

$pageTitle = 'Socios';
require_once dirname(dirname(__DIR__)) . '/includes/header.php';
require_once dirname(dirname(__DIR__)) . '/includes/functions.php';

// Cálculo de meses pasados (deuda solo por meses que YA pasaron)
$mes_actual = intval(date('n'));
$anio_actual = intval(date('Y'));
$meses_pasados = $mes_actual - 1; // Enero=0, Feb=1, etc.

// Filtros
$buscar = trim($_GET['buscar'] ?? '');
$zona_id = intval($_GET['zona_id'] ?? 0);
$estado = $_GET['estado'] ?? '';
$solo_deudores = isset($_GET['deudores']);

// Construir consulta con deuda dinámica
$sql = "SELECT s.*,
               (SELECT COUNT(*) FROM acciones a WHERE a.socio_id = s.id) as num_acciones,
               (SELECT GROUP_CONCAT(DISTINCT a.estado SEPARATOR ', ') FROM acciones a WHERE a.socio_id = s.id) as estados_acciones";

// Si hay meses pasados, calcular deuda
if ($meses_pasados > 0) {
    $sql .= ",
               (SELECT COALESCE(SUM(
                   GREATEST(0, ? - (SELECT COUNT(*) FROM consumos_anuales ca 
                                   WHERE ca.accion_id = a.id AND ca.anio = ? AND ca.mes < ?
                                   AND ca.estado IN ('pagado','no_cobrable'))) * t.monto
               ), 0)
               FROM acciones a 
               JOIN tipos_tarifa t ON a.tipo_tarifa_id = t.id 
               WHERE a.socio_id = s.id AND a.estado = 'ACTIVO') as deuda_total";
} else {
    $sql .= ", 0 as deuda_total";
}

$sql .= " FROM socios s WHERE 1=1";
$params = [];

if ($meses_pasados > 0) {
    $params[] = $meses_pasados;
    $params[] = $anio_actual;
    $params[] = $mes_actual;
}

if (!empty($buscar)) {
    $sql .= " AND (s.nombre LIKE ? OR s.numero_socio LIKE ? OR s.ci LIKE ?)";
    $params[] = '%' . $buscar . '%';
    $params[] = '%' . $buscar . '%';
    $params[] = '%' . $buscar . '%';
}

if ($zona_id > 0) {
    $sql .= " AND s.id IN (SELECT socio_id FROM acciones WHERE zona_id = ?)";
    $params[] = $zona_id;
}

if (!empty($estado)) {
    $sql .= " AND s.id IN (SELECT socio_id FROM acciones WHERE estado = ?)";
    $params[] = $estado;
}

$sql .= " ORDER BY s.numero_socio ASC LIMIT 200";
$socios = fetchAll($sql, $params);

// Filtrar solo deudores si se solicita
if ($solo_deudores) {
    $socios = array_filter($socios, fn($s) => $s['deuda_total'] > 0);
}

$zonas = getZonas();

// Calcular totales
$total_deuda = array_sum(array_column($socios, 'deuda_total'));
$num_deudores = count(array_filter($socios, fn($s) => $s['deuda_total'] > 0));
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1 class="page-title">Gestión de Socios</h1>
        <p class="page-subtitle">Administrar socios y sus acciones de agua</p>
    </div>
    <a href="<?= BASE_URL ?>/modules/socios/crear.php" class="btn btn-primary">
        <i class="bi bi-plus-circle me-1"></i> Nuevo Socio
    </a>
</div>


<!-- Filtros -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Buscar</label>
                <input type="text" class="form-control" name="buscar"
                       value="<?= htmlspecialchars($buscar) ?>"
                       placeholder="Nombre, N° socio o CI">
            </div>
            <div class="col-md-3">
                <label class="form-label">Zona</label>
                <select class="form-select" name="zona_id">
                    <option value="">Todas las zonas</option>
                    <?php foreach ($zonas as $zona): ?>
                    <option value="<?= $zona['id'] ?>" <?= $zona_id == $zona['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($zona['nombre']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Estado</label>
                <select class="form-select" name="estado">
                    <option value="">Todos</option>
                    <option value="ACTIVO" <?= $estado === 'ACTIVO' ? 'selected' : '' ?>>Activos</option>
                    <option value="CORTADO" <?= $estado === 'CORTADO' ? 'selected' : '' ?>>Cortados</option>
                    <option value="BAJA" <?= $estado === 'BAJA' ? 'selected' : '' ?>>Baja</option>
                    <option value="SIN INST." <?= $estado === 'SIN INST.' ? 'selected' : '' ?>>Sin Inst.</option>
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-primary me-2">
                    <i class="bi bi-search"></i> Filtrar
                </button>
                <a href="<?= BASE_URL ?>/modules/socios/" class="btn btn-outline-secondary">
                    <i class="bi bi-x-circle"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Lista de Socios -->
<div class="card">
    <div class="card-header">
        <i class="bi bi-people me-2"></i>Socios (<?= count($socios) ?>)
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>N° Socio</th>
                        <th>Nombre</th>
                        <th>Celular</th>
                        <th class="text-center">Acciones</th>
                        <th class="text-center">Estados</th>
                        <th class="text-end">Deuda</th>
                        <th class="text-center">Operaciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($socios)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">
                            <?php if (!empty($buscar) || $zona_id > 0 || !empty($estado)): ?>
                            No se encontraron socios con los filtros aplicados
                            <?php else: ?>
                            Ingrese un término de búsqueda o seleccione filtros
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($socios as $socio): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($socio['numero_socio']) ?></strong></td>
                        <td>
                            <a href="<?= BASE_URL ?>/modules/socios/ver.php?id=<?= $socio['id'] ?>" class="text-decoration-none">
                                <?= htmlspecialchars($socio['nombre']) ?>
                            </a>
                            <?php if (!empty($socio['persona_encargada'])): ?>
                            <br><small class="text-muted">Enc: <?= htmlspecialchars($socio['persona_encargada']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($socio['celular'] ?? '-') ?></td>
                        <td class="text-center">
                            <span class="badge bg-primary"><?= $socio['num_acciones'] ?></span>
                        </td>
                        <td class="text-center">
                            <?php
                            $estados = explode(', ', $socio['estados_acciones'] ?? '');
                            foreach ($estados as $est):
                                $est = trim($est);
                                if (empty($est)) continue;
                                $colorClass = match($est) {
                                    'ACTIVO' => 'success',
                                    'CORTADO' => 'danger',
                                    'BAJA' => 'secondary',
                                    'SIN INST.' => 'info',
                                    default => 'secondary'
                                };
                            ?>
                            <span class="badge bg-<?= $colorClass ?>"><?= htmlspecialchars($est) ?></span>
                            <?php endforeach; ?>
                        </td>
                        <td class="text-end">
                            <?php if ($socio['deuda_total'] > 0): ?>
                            <span class="text-danger fw-bold">Bs. <?= number_format($socio['deuda_total'], 2) ?></span>
                            <?php else: ?>
                            <span class="text-success">Al día</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <div class="btn-group btn-group-sm">
                                <a href="<?= BASE_URL ?>/modules/socios/ver.php?id=<?= $socio['id'] ?>"
                                   class="btn btn-outline-primary" title="Ver detalle">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="<?= BASE_URL ?>/modules/socios/editar.php?id=<?= $socio['id'] ?>"
                                   class="btn btn-outline-secondary" title="Editar">
                                    <i class="bi bi-pencil"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
