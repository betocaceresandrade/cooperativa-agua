<?php
/**
 * Anular un Pago
 * Elimina los registros de consumos_anuales asociados (la deuda se recalcula dinámicamente)
 */

require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/config/config.php';
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_once dirname(dirname(__DIR__)) . '/includes/functions.php';

requireLogin();

$pago_id = intval($_GET['id'] ?? 0);

if (!$pago_id) {
    setFlash('error', 'Pago no especificado');
    redirect('/modules/ingresos/');
}

$pago = fetchOne("SELECT * FROM pagos WHERE id = ?", [$pago_id]);

if (!$pago) {
    setFlash('error', 'Pago no encontrado');
    redirect('/modules/ingresos/');
}

if ($pago['anulado']) {
    setFlash('error', 'Este pago ya fue anulado anteriormente');
    redirect('/modules/ingresos/');
}

$pdo = getConnection();

try {
    $pdo->beginTransaction();

    // 1. Marcar el pago como anulado
    $pdo->prepare(
        "UPDATE pagos SET anulado = 1, fecha_anulacion = NOW(), usuario_anulacion_id = ? WHERE id = ?"
    )->execute([getCurrentUser()['id'], $pago_id]);

    // 2. ELIMINAR los consumos_anuales asociados a este pago
    // (Con la lógica dinámica, la deuda se recalcula automáticamente al no existir el registro)
    $stmt = $pdo->prepare("DELETE FROM consumos_anuales WHERE pago_id = ?");
    $stmt->execute([$pago_id]);
    $mesesEliminados = $stmt->rowCount();

    // 3. Eliminar registros de pago_consumos si existen (compatibilidad)
    $pdo->prepare("DELETE FROM pago_consumos WHERE pago_id = ?")->execute([$pago_id]);

    // 4. Revertir otros_ingresos a pendiente
    $pdo->prepare(
        "UPDATE otros_ingresos SET estado = 'pendiente', pago_id = NULL WHERE pago_id = ?"
    )->execute([$pago_id]);

    // 5. Registrar movimiento de caja negativo (reversión)
    $pdo->prepare(
        "INSERT INTO movimientos_caja (tipo, concepto, monto, metodo, referencia_tipo, referencia_id, fecha)
         VALUES ('egreso', ?, ?, ?, 'anulacion', ?, NOW())"
    )->execute([
        'ANULACIÓN Recibo #' . $pago['numero_recibo'],
        $pago['monto_total'],
        $pago['metodo_pago'],
        $pago_id
    ]);

    $pdo->commit();

    $msg = 'Pago #' . $pago['numero_recibo'] . ' anulado correctamente.';
    if ($mesesEliminados > 0) {
        $msg .= " Se liberaron $mesesEliminados mes(es).";
    }
    setFlash('success', $msg);

} catch (Exception $e) {
    $pdo->rollBack();
    setFlash('error', 'Error al anular el pago: ' . $e->getMessage());
}

// Redirigir
$referer = $_SERVER['HTTP_REFERER'] ?? '';
if (strpos($referer, 'socios/ver.php') !== false) {
    redirect('/modules/socios/ver.php?id=' . $pago['socio_id']);
} else {
    redirect('/modules/ingresos/');
}
