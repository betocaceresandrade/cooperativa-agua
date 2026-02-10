<?php
/**
 * Script de Instalación
 * Cooperativa de Agua Potable "Virgen de las Nieves"
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

$step = isset($_GET['step']) ? intval($_GET['step']) : 1;
$error = '';
$success = '';

// Configuración por defecto
$config = [
    'db_host' => 'localhost',
    'db_name' => 'cooperativa_agua',
    'db_user' => 'root',
    'db_pass' => ''
];

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step === 1) {
        // Verificar requisitos
        $step = 2;
    } elseif ($step === 2) {
        // Configurar base de datos
        $config['db_host'] = $_POST['db_host'] ?? 'localhost';
        $config['db_name'] = $_POST['db_name'] ?? 'cooperativa_agua';
        $config['db_user'] = $_POST['db_user'] ?? 'root';
        $config['db_pass'] = $_POST['db_pass'] ?? '';

        try {
            // Probar conexión
            $dsn = "mysql:host={$config['db_host']};charset=utf8mb4";
            $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);

            // Crear base de datos si no existe
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$config['db_name']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `{$config['db_name']}`");

            // Leer y ejecutar schema
            $schemaPath = __DIR__ . '/sql/schema.sql';
            if (!file_exists($schemaPath)) {
                throw new Exception('No se encontró el archivo schema.sql');
            }

            $sql = file_get_contents($schemaPath);
            $pdo->exec($sql);

            // Actualizar archivo de configuración
            $configContent = "<?php
/**
 * Configuración de Base de Datos
 * Cooperativa de Agua Potable \"Virgen de las Nieves\"
 */

define('DB_HOST', '{$config['db_host']}');
define('DB_NAME', '{$config['db_name']}');
define('DB_USER', '{$config['db_user']}');
define('DB_PASS', '{$config['db_pass']}');
define('DB_CHARSET', 'utf8mb4');

/**
 * Obtener conexión PDO
 */
function getConnection() {
    static \$pdo = null;

    if (\$pdo === null) {
        try {
            \$dsn = \"mysql:host=\" . DB_HOST . \";dbname=\" . DB_NAME . \";charset=\" . DB_CHARSET;
            \$options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            \$pdo = new PDO(\$dsn, DB_USER, DB_PASS, \$options);
        } catch (PDOException \$e) {
            die(\"Error de conexión: \" . \$e->getMessage());
        }
    }

    return \$pdo;
}

function query(\$sql, \$params = []) {
    \$pdo = getConnection();
    \$stmt = \$pdo->prepare(\$sql);
    \$stmt->execute(\$params);
    return \$stmt;
}

function fetchOne(\$sql, \$params = []) {
    return query(\$sql, \$params)->fetch();
}

function fetchAll(\$sql, \$params = []) {
    return query(\$sql, \$params)->fetchAll();
}

function insert(\$sql, \$params = []) {
    query(\$sql, \$params);
    return getConnection()->lastInsertId();
}

function update(\$sql, \$params = []) {
    return query(\$sql, \$params)->rowCount();
}
";
            file_put_contents(__DIR__ . '/config/database.php', $configContent);

            $step = 3;

        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

// Verificar requisitos
$requirements = [
    'PHP 7.4+' => version_compare(PHP_VERSION, '7.4.0', '>='),
    'PDO MySQL' => extension_loaded('pdo_mysql'),
    'JSON' => extension_loaded('json'),
    'Session' => extension_loaded('session'),
    'Config Writable' => is_writable(__DIR__ . '/config'),
];
$allRequirements = !in_array(false, $requirements);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalación - Cooperativa de Agua</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #079FEA 0%, #0576b9 100%); min-height: 100vh; }
        .install-container { max-width: 600px; margin: 50px auto; }
        .card { border-radius: 20px; box-shadow: 0 10px 40px rgba(0,0,0,0.2); }
        .card-header { background: #079FEA; color: white; border-radius: 20px 20px 0 0 !important; }
        .step { display: flex; justify-content: center; gap: 10px; margin-bottom: 30px; }
        .step-item { width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center;
                     justify-content: center; font-weight: bold; background: #e9ecef; color: #6c757d; }
        .step-item.active { background: #079FEA; color: white; }
        .step-item.completed { background: #28a745; color: white; }
        .btn-primary { background: #079FEA; border-color: #079FEA; }
        .btn-primary:hover { background: #0689cc; border-color: #0689cc; }
    </style>
</head>
<body>
    <div class="install-container">
        <div class="text-center text-white mb-4">
            <h2><i class="bi bi-droplet-fill me-2"></i>Cooperativa de Agua</h2>
            <p>Virgen de las Nieves - Irupana</p>
        </div>

        <div class="card">
            <div class="card-header text-center py-3">
                <h4 class="mb-0"><i class="bi bi-gear me-2"></i>Instalación del Sistema</h4>
            </div>
            <div class="card-body p-4">
                <!-- Pasos -->
                <div class="step">
                    <div class="step-item <?= $step >= 1 ? ($step > 1 ? 'completed' : 'active') : '' ?>">1</div>
                    <div class="step-item <?= $step >= 2 ? ($step > 2 ? 'completed' : 'active') : '' ?>">2</div>
                    <div class="step-item <?= $step >= 3 ? 'completed' : '' ?>">3</div>
                </div>

                <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
                </div>
                <?php endif; ?>

                <?php if ($step === 1): ?>
                <!-- Paso 1: Requisitos -->
                <h5 class="mb-3">Verificación de Requisitos</h5>

                <ul class="list-group mb-4">
                    <?php foreach ($requirements as $name => $ok): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <?= $name ?>
                        <?php if ($ok): ?>
                        <span class="badge bg-success"><i class="bi bi-check"></i></span>
                        <?php else: ?>
                        <span class="badge bg-danger"><i class="bi bi-x"></i></span>
                        <?php endif; ?>
                    </li>
                    <?php endforeach; ?>
                </ul>

                <?php if ($allRequirements): ?>
                <form method="POST">
                    <button type="submit" class="btn btn-primary w-100">
                        Continuar <i class="bi bi-arrow-right ms-2"></i>
                    </button>
                </form>
                <?php else: ?>
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    Corrija los requisitos marcados en rojo antes de continuar.
                </div>
                <?php endif; ?>

                <?php elseif ($step === 2): ?>
                <!-- Paso 2: Configuración BD -->
                <h5 class="mb-3">Configuración de Base de Datos</h5>

                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Servidor MySQL</label>
                        <input type="text" class="form-control" name="db_host" value="localhost" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nombre de Base de Datos</label>
                        <input type="text" class="form-control" name="db_name" value="cooperativa_agua" required>
                        <small class="text-muted">Se creará si no existe</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Usuario MySQL</label>
                        <input type="text" class="form-control" name="db_user" value="root" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Contraseña MySQL</label>
                        <input type="password" class="form-control" name="db_pass">
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        Instalar <i class="bi bi-arrow-right ms-2"></i>
                    </button>
                </form>

                <?php elseif ($step === 3): ?>
                <!-- Paso 3: Completado -->
                <div class="text-center">
                    <i class="bi bi-check-circle text-success" style="font-size: 4rem;"></i>
                    <h4 class="mt-3 text-success">Instalación Completada</h4>
                    <p class="text-muted">El sistema ha sido instalado correctamente.</p>

                    <div class="alert alert-info text-start">
                        <strong>Credenciales de acceso:</strong><br>
                        Usuario: <code>cajera</code><br>
                        Contraseña: <code>password</code>
                    </div>

                    <div class="alert alert-warning text-start">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <strong>Importante:</strong> Por seguridad, elimine o renombre el archivo
                        <code>install.php</code> después de la instalación.
                    </div>

                    <a href="index.php" class="btn btn-primary btn-lg">
                        <i class="bi bi-box-arrow-in-right me-2"></i>Ingresar al Sistema
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="text-center text-white mt-4">
            <small>Sistema de Gestión Cooperativa de Agua v1.0</small>
        </div>
    </div>
</body>
</html>
