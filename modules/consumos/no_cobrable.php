<?php
/**
 * Marcar mes como no cobrable
 */

require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_once dirname(dirname(__DIR__)) . '/includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

$consumo_id = intval($_POST['consumo_id'] ?? 0);
$motivo = trim($_POST['motivo'] ?? '');

if (!$consumo_id) {
    echo json_encode(['success' => false, 'error' => 'ID de consumo inválido']);
    exit;
}

if (empty($motivo)) {
    echo json_encode(['success' => false, 'error' => 'Debe indicar el motivo']);
    exit;
}

// Verificar que el consumo existe y está pendiente
$consumo = fetchOne("SELECT * FROM consumos_anuales WHERE id = ? AND estado = 'pendiente'", [$consumo_id]);

if (!$consumo) {
    echo json_encode(['success' => false, 'error' => 'Consumo no encontrado o no está pendiente']);
    exit;
}

// Marcar como no cobrable
$resultado = marcarMesNoCobrable($consumo_id, $motivo);

if ($resultado) {
    echo json_encode(['success' => true, 'message' => 'Mes marcado como no cobrable']);
} else {
    echo json_encode(['success' => false, 'error' => 'Error al actualizar']);
}
