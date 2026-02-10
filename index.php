<?php
/**
 * Página de Login
 * Cooperativa de Agua Potable "Virgen de las Nieves"
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

// Si ya está logueado, redirigir al dashboard
if (isLoggedIn()) {
    redirect('/dashboard.php');
}

$error = '';

// Procesar login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Complete todos los campos';
    } elseif (attemptLogin($username, $password)) {
        redirect('/dashboard.php');
    } else {
        $error = 'Usuario o contraseña incorrectos';
    }
}

// Mensaje de sesión expirada
if (isset($_GET['error']) && $_GET['error'] === 'session') {
    $error = 'Su sesión ha expirado. Ingrese nuevamente.';
}

$config = getConfig();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - <?= htmlspecialchars($config['nombre_cooperativa']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body>
    <div class="login-container">
        <div class="login-card text-center">
            <img src="<?= BASE_URL ?>/assets/img/logo.png" alt="Logo" class="login-logo"
                 onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><circle cx=%2250%22 cy=%2250%22 r=%2245%22 fill=%22%23079FEA%22/><text x=%2250%22 y=%2265%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2240%22>VN</text></svg>'">

            <h1 class="login-title"><?= htmlspecialchars($config['nombre_cooperativa']) ?></h1>
            <p class="login-subtitle">Sistema de Gestión</p>

            <?php if ($error): ?>
            <div class="alert alert-danger py-2">
                <i class="bi bi-exclamation-circle me-1"></i>
                <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="mb-3 text-start">
                    <label class="form-label">Usuario</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-person"></i></span>
                        <input type="text" class="form-control" name="username"
                               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                               placeholder="Ingrese su usuario" required autofocus>
                    </div>
                </div>

                <div class="mb-4 text-start">
                    <label class="form-label">Contraseña</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input type="password" class="form-control" name="password"
                               placeholder="Ingrese su contraseña" required>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-rounded w-100 py-2">
                    <i class="bi bi-box-arrow-in-right me-2"></i>
                    Ingresar
                </button>
            </form>

            <div class="mt-4 text-muted small">
                <i class="bi bi-geo-alt"></i> Irupana, Sud Yungas, La Paz
            </div>
        </div>
    </div>
</body>
</html>
