<?php
/**
 * Exonerar Servicio (AJAX)
 */

require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/config/config.php';
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_once dirname(dirname(__DIR__)) . '/includes/functions.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Método no permitido'], 405);
}

$servicio_id = intval($_POST['servicio_id'] ?? 0);
$motivo = sanitize($_POST['motivo'] ?? 'Sin especificar');

if ($servicio_id <= 0) {
    jsonResponse(['success' => false, 'error' => 'ID de servicio inválido']);
}

// Verificar que el servicio existe y está pendiente
$servicio = fetchOne("SELECT * FROM servicios WHERE id = ? AND estado = 'pendiente'", [$servicio_id]);

if (!$servicio) {
    jsonResponse(['success' => false, 'error' => 'Servicio no encontrado o ya procesado']);
}

// Exonerar
$user = getCurrentUser();
$resultado = exonerarServicio($servicio_id, $motivo, $user['nombre']);

if ($resultado) {
    jsonResponse(['success' => true, 'message' => 'Servicio exonerado correctamente']);
} else {
    jsonResponse(['success' => false, 'error' => 'Error al exonerar el servicio']);
}
