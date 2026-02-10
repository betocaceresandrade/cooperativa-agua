<?php
/**
 * Búsqueda AJAX de Socios
 */

require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/config/config.php';
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_once dirname(dirname(__DIR__)) . '/includes/functions.php';

requireLogin();

$query = sanitize($_GET['q'] ?? '');

if (strlen($query) < 2) {
    echo '<div class="text-muted p-3">Ingrese al menos 2 caracteres</div>';
    exit;
}

$socios = fetchAll(
    "SELECT s.*, z.nombre as zona_nombre,
            (SELECT COUNT(*) FROM servicios sv
             JOIN acciones a ON sv.accion_id = a.id
             WHERE a.socio_id = s.id AND sv.estado = 'pendiente') as meses_pendientes,
            (SELECT COALESCE(SUM(sv.monto), 0) FROM servicios sv
             JOIN acciones a ON sv.accion_id = a.id
             WHERE a.socio_id = s.id AND sv.estado = 'pendiente') as deuda_total
     FROM socios s
     LEFT JOIN zonas z ON s.zona_id = z.id
     WHERE s.estado = 'activo'
     AND (s.nombre LIKE ? OR s.numero_socio LIKE ?)
     ORDER BY s.numero_socio
     LIMIT 10",
    ['%' . $query . '%', '%' . $query . '%']
);

if (empty($socios)) {
    echo '<div class="text-muted p-3">No se encontraron socios</div>';
    exit;
}
?>

<div class="list-group list-group-flush">
    <?php foreach ($socios as $socio): ?>
    <a href="javascript:void(0)" onclick="seleccionarSocio(<?= $socio['id'] ?>)"
       class="list-group-item list-group-item-action">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <strong><?= htmlspecialchars($socio['numero_socio']) ?></strong>
                - <?= htmlspecialchars($socio['nombre']) ?>
                <?php if ($socio['zona_nombre']): ?>
                <small class="text-muted">(<?= htmlspecialchars($socio['zona_nombre']) ?>)</small>
                <?php endif; ?>
            </div>
            <div class="text-end">
                <?php if ($socio['deuda_total'] > 0): ?>
                <span class="badge bg-danger"><?= $socio['meses_pendientes'] ?> mes(es)</span>
                <span class="text-danger fw-bold"><?= formatMoney($socio['deuda_total']) ?></span>
                <?php else: ?>
                <span class="badge bg-success">Al día</span>
                <?php endif; ?>
            </div>
        </div>
    </a>
    <?php endforeach; ?>
</div>
