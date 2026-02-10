<?php
/**
 * Módulo de Backup
 */

@ini_set("max_execution_time", "300");
@ini_set("memory_limit", "256M");

require_once dirname(dirname(__DIR__)) . "/config/database.php";
require_once dirname(dirname(__DIR__)) . "/config/config.php";
require_once dirname(dirname(__DIR__)) . "/includes/auth.php";
require_once dirname(dirname(__DIR__)) . "/includes/functions.php";

requireLogin();

// Generar backup - ANTES de enviar cualquier HTML
if (isset($_POST["generar_backup"])) {
    try {
        $filename = "backup_cooperativa_" . date("Y-m-d_H-i-s") . ".sql";
        $pdo = getConnection();
        $tables = fetchAll("SHOW TABLES");

        $sql = "-- Backup Cooperativa de Agua\n";
        $sql .= "-- Fecha: " . date("Y-m-d H:i:s") . "\n";
        $sql .= "-- Base de datos: " . DB_NAME . "\n\n";
        $sql .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

        foreach ($tables as $table) {
            $tableName = array_values($table)[0];
            $createTable = fetchOne("SHOW CREATE TABLE `$tableName`");
            $sql .= "DROP TABLE IF EXISTS `$tableName`;\n";
            $sql .= $createTable["Create Table"] . ";\n\n";

            $rows = fetchAll("SELECT * FROM `$tableName`");
            if (!empty($rows)) {
                $columns = array_keys($rows[0]);
                $sql .= "INSERT INTO `$tableName` (`" . implode("`, `", $columns) . "`) VALUES\n";
                $values = [];
                foreach ($rows as $row) {
                    $rowValues = [];
                    foreach ($row as $value) {
                        if ($value === null) {
                            $rowValues[] = "NULL";
                        } else {
                            $rowValues[] = $pdo->quote($value);
                        }
                    }
                    $values[] = "(" . implode(", ", $rowValues) . ")";
                }
                $sql .= implode(",\n", $values) . ";\n\n";
            }
        }
        $sql .= "SET FOREIGN_KEY_CHECKS = 1;\n";

        while (ob_get_level()) { ob_end_clean(); }

        header("Content-Type: application/sql");
        header("Content-Disposition: attachment; filename=\"" . $filename . "\"");
        header("Content-Length: " . strlen($sql));
        header("Cache-Control: no-cache, must-revalidate");
        echo $sql;
        exit;
    } catch (Exception $e) {
        $backup_error = "Error al generar backup: " . $e->getMessage();
    }
}

$pageTitle = "Backup";
require_once dirname(dirname(__DIR__)) . "/includes/header.php";

$message = "";
$error = $backup_error ?? "";

if (isset($_POST["restaurar_backup"]) && isset($_FILES["backup_file"])) {
    $file = $_FILES["backup_file"];
    if ($file["error"] !== UPLOAD_ERR_OK) {
        $error = "Error al subir el archivo";
    } elseif (pathinfo($file["name"], PATHINFO_EXTENSION) !== "sql") {
        $error = "El archivo debe ser .sql";
    } else {
        try {
            $sql = file_get_contents($file["tmp_name"]);
            $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            if ($mysqli->connect_error) throw new Exception($mysqli->connect_error);
            $mysqli->set_charset(DB_CHARSET);
            if ($mysqli->multi_query($sql)) {
                do { if ($result = $mysqli->store_result()) $result->free(); } 
                while ($mysqli->more_results() && $mysqli->next_result());
                if ($mysqli->error) throw new Exception($mysqli->error);
                $message = "Backup restaurado correctamente";
            } else { throw new Exception($mysqli->error); }
            $mysqli->close();
        } catch (Exception $e) { $error = "Error al restaurar: " . $e->getMessage(); }
    }
}

$stats = []; $tables = fetchAll("SHOW TABLE STATUS"); $totalSize = 0; $totalRows = 0;
foreach ($tables as $table) {
    $size = ($table["Data_length"] ?? 0) + ($table["Index_length"] ?? 0);
    $totalSize += $size; $totalRows += $table["Rows"] ?? 0;
    $stats[] = ["name" => $table["Name"], "rows" => $table["Rows"] ?? 0, "size" => $size];
}
?>

<div class="page-header">
    <h1 class="page-title">Backup y Restauración</h1>
    <p class="page-subtitle">Respaldo y recuperación de la base de datos</p>
</div>

<?php if ($message): ?>
<div class="alert alert-success"><i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($message) ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger"><i class="bi bi-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-6 mb-4">
        <div class="card h-100">
            <div class="card-header bg-success text-white"><i class="bi bi-download me-2"></i>Generar Backup</div>
            <div class="card-body">
                <div class="text-center mb-4"><i class="bi bi-database-down text-success" style="font-size: 4rem;"></i></div>
                <p>Descargue una copia completa de la base de datos:</p>
                <ul><li>Estructura de tablas</li><li>Todos los datos</li><li>Configuración</li></ul>
                <form method="POST"><button type="submit" name="generar_backup" class="btn btn-success btn-lg w-100"><i class="bi bi-download me-2"></i>Descargar Backup (.sql)</button></form>
            </div>
            <div class="card-footer bg-light"><small class="text-muted"><i class="bi bi-info-circle me-1"></i>Hacer backup al menos una vez por semana</small></div>
        </div>
    </div>
    <div class="col-lg-6 mb-4">
        <div class="card h-100">
            <div class="card-header bg-warning text-dark"><i class="bi bi-upload me-2"></i>Restaurar Backup</div>
            <div class="card-body">
                <div class="text-center mb-4"><i class="bi bi-database-up text-warning" style="font-size: 4rem;"></i></div>
                <div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i><strong>Advertencia:</strong> Restaurar reemplazará TODOS los datos actuales.</div>
                <form method="POST" enctype="multipart/form-data" onsubmit="return confirm(String.fromCharCode(191)+String.fromCharCode(69)+String.fromCharCode(115)+String.fromCharCode(116)+String.fromCharCode(225)+String.fromCharCode(32)+String.fromCharCode(115)+String.fromCharCode(101)+String.fromCharCode(103)+String.fromCharCode(117)+String.fromCharCode(114)+String.fromCharCode(111)+String.fromCharCode(63));">
                    <div class="mb-3"><label class="form-label">Archivo de backup (.sql)</label><input type="file" class="form-control" name="backup_file" accept=".sql" required></div>
                    <button type="submit" name="restaurar_backup" class="btn btn-warning btn-lg w-100"><i class="bi bi-upload me-2"></i>Restaurar Backup</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><i class="bi bi-database me-2"></i>Estadísticas de la Base de Datos</div>
    <div class="card-body">
        <div class="row mb-4">
            <div class="col-md-4 text-center"><div class="fs-3 fw-bold text-primary"><?= count($stats) ?></div><div class="text-muted">Tablas</div></div>
            <div class="col-md-4 text-center"><div class="fs-3 fw-bold text-primary"><?= number_format($totalRows) ?></div><div class="text-muted">Registros</div></div>
            <div class="col-md-4 text-center"><div class="fs-3 fw-bold text-primary"><?= number_format($totalSize / 1024, 2) ?> KB</div><div class="text-muted">Tamaño</div></div>
        </div>
        <div class="table-responsive"><table class="table table-sm"><thead><tr><th>Tabla</th><th class="text-end">Registros</th><th class="text-end">Tamaño</th></tr></thead><tbody>
        <?php foreach ($stats as $s): ?><tr><td><?= htmlspecialchars($s["name"]) ?></td><td class="text-end"><?= number_format($s["rows"]) ?></td><td class="text-end"><?= number_format($s["size"] / 1024, 2) ?> KB</td></tr><?php endforeach; ?>
        </tbody></table></div>
    </div>
</div>
<?php require_once dirname(dirname(__DIR__)) . "/includes/footer.php"; ?>
