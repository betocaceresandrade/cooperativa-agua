<?php
/**
 * Eliminar Gasto
 */
require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/config/config.php';
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_once dirname(dirname(__DIR__)) . '/includes/functions.php';

requireLogin();

$id = intval($_GET['id'] ?? 0);

if (!$id) {
    setFlash('error', 'ID de gasto no válido');
    redirect('/modules/gastos/');
}

// Obtener el gasto
$gasto = fetchOne("SELECT * FROM gastos WHERE id = ?", [$id]);

if (!$gasto) {
    setFlash('error', 'Gasto no encontrado');
    redirect('/modules/gastos/');
}

try {
    $pdo = getConnection();
    $pdo->beginTransaction();
    
    // Revertir el movimiento de caja
    $movimiento = fetchOne(
        "SELECT * FROM movimientos_caja WHERE referencia_tipo = 'gasto' AND referencia_id = ?",
        [$id]
    );
    
    if ($movimiento) {
        // Eliminar el movimiento de caja
        update("DELETE FROM movimientos_caja WHERE id = ?", [$movimiento['id']]);
    }
    
    // Eliminar el gasto
    update("DELETE FROM gastos WHERE id = ?", [$id]);
    
    $pdo->commit();
    
    setFlash('success', 'Gasto eliminado correctamente');
} catch (Exception $e) {
    $pdo->rollBack();
    setFlash('error', 'Error al eliminar: ' . $e->getMessage());
}

redirect('/modules/gastos/');
