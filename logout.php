<?php
/**
 * Cerrar Sesión
 */

require_once __DIR__ . '/includes/auth.php';

logout();
header('Location: ' . BASE_URL . '/index.php');
exit;
