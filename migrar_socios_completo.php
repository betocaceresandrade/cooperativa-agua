<?php
/**
 * Script de migración completa de socios desde PDF
 * Cooperativa de Agua - Irupana
 * Fecha: 4 Feb 2026
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Conexión a la BD
$host = 'localhost';
$dbname = 'cooperativa_agua';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

// Leer archivo de texto
$archivo = '/tmp/socios_cooperativa.txt';
$contenido = file_get_contents($archivo);
$lineas = explode("\n", $contenido);

echo "=== MIGRACIÓN DE SOCIOS ===\n";
echo "Líneas en archivo: " . count($lineas) . "\n\n";

// Función para obtener o crear zona
function getOrCreateZona($pdo, $nombreZona) {
    $nombreZona = trim($nombreZona);
    if (empty($nombreZona)) return null;

    // Normalizar nombre de zona
    $nombreZona = ucwords(strtolower($nombreZona));

    $stmt = $pdo->prepare("SELECT id FROM zonas WHERE LOWER(nombre) = LOWER(?)");
    $stmt->execute([$nombreZona]);
    $zona = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($zona) {
        return $zona['id'];
    }

    // Crear nueva zona
    $stmt = $pdo->prepare("INSERT INTO zonas (nombre, descripcion, activo) VALUES (?, ?, 1)");
    $stmt->execute([$nombreZona, $nombreZona]);
    return $pdo->lastInsertId();
}

// Función para obtener tipo de tarifa
function getTipoTarifa($pdo, $categoria) {
    $categoria = trim(strtoupper($categoria));

    $mapeo = [
        'DOMICILIARIA' => 'DOMICILIARIA',
        'DOMICILIARIA SOCIAL' => 'DOMICILIARIA SOCIAL',
        'ESPECIAL' => 'ESPECIAL',
        'COMERCIAL' => 'COMERCIAL (OFICINA, PENSION)',
        'FALSO' => 'DOMICILIARIA', // Default para FALSO
    ];

    $buscar = $mapeo[$categoria] ?? 'DOMICILIARIA';

    $stmt = $pdo->prepare("SELECT id FROM tipos_tarifa WHERE UPPER(nombre) LIKE ?");
    $stmt->execute(['%' . $buscar . '%']);
    $tarifa = $stmt->fetch(PDO::FETCH_ASSOC);

    return $tarifa ? $tarifa['id'] : 51; // 51 = DOMICILIARIA por defecto
}

// Función para normalizar número de acción (mantener espacios como "98 - S")
function normalizarNumeroAccion($num) {
    $num = trim($num);
    // Si tiene guión pero no espacios, agregar espacios
    if (preg_match('/^(\d+)-([A-Za-z]\d?)$/', $num, $m)) {
        return $m[1] . ' - ' . strtoupper($m[2]);
    }
    // Si ya tiene espacios, normalizar
    if (preg_match('/^(\d+)\s*-\s*([A-Za-z]\d?)$/i', $num, $m)) {
        return $m[1] . ' - ' . strtoupper($m[2]);
    }
    return strtoupper($num);
}

// Función para generar número de socio único
function generarNumeroSocio($pdo, $nombre) {
    // Buscar si ya existe un socio con este nombre
    $stmt = $pdo->prepare("SELECT id, numero_socio FROM socios WHERE LOWER(nombre) = LOWER(?)");
    $stmt->execute([trim($nombre)]);
    $existente = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existente) {
        return ['id' => $existente['id'], 'numero_socio' => $existente['numero_socio'], 'nuevo' => false];
    }

    // Generar nuevo número
    $stmt = $pdo->query("SELECT MAX(CAST(numero_socio AS UNSIGNED)) as max_num FROM socios WHERE numero_socio REGEXP '^[0-9]+$'");
    $max = $stmt->fetch(PDO::FETCH_ASSOC);
    $nuevoNum = str_pad(($max['max_num'] ?? 0) + 1, 4, '0', STR_PAD_LEFT);

    return ['id' => null, 'numero_socio' => $nuevoNum, 'nuevo' => true];
}

// Limpiar datos existentes (opcional - comentar si no se quiere limpiar)
echo "Limpiando datos existentes...\n";
$pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
$pdo->exec("DELETE FROM consumos_anuales");
$pdo->exec("DELETE FROM pago_consumos");
$pdo->exec("DELETE FROM pago_detalles");
$pdo->exec("DELETE FROM servicios");
$pdo->exec("DELETE FROM pagos");
$pdo->exec("DELETE FROM acciones");
$pdo->exec("DELETE FROM socios");
$pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
echo "Datos limpiados.\n\n";

// Parsear líneas
$registros = [];
$registro_actual = null;
$en_datos = false;

// Patrones para detectar líneas de datos
$patron_accion = '/^\s*(\d+\s*-\s*[A-Za-z]\d?)\s+(.+)$/';

foreach ($lineas as $num_linea => $linea) {
    $linea_limpia = trim($linea);

    // Ignorar líneas vacías, encabezados y pies de página
    if (empty($linea_limpia)) continue;
    if (strpos($linea_limpia, 'COOPERATIVA DE SERVICIOS') !== false) continue;
    if (strpos($linea_limpia, 'REPORTE DE SOCIOS') !== false) continue;
    if (strpos($linea_limpia, 'TOTAL SOCIOS') !== false) continue;
    if (strpos($linea_limpia, 'VIRGEN DE LAS NIEVES') !== false) continue;
    if (strpos($linea_limpia, 'IRUPANA') !== false) continue;
    if (preg_match('/^\d+$/', $linea_limpia)) continue; // Solo números (página)
    if ($linea_limpia === 'N               NOMBRE                        CI                ZONA              CATEGORIAS         ESTADO           OBS') continue;
    if (strpos($linea_limpia, 'N               NOMBRE') !== false) continue;

    // Detectar línea con número de acción al inicio
    if (preg_match('/^\s*(\d+\s*-\s*[A-Za-z]\d?)\s+/', $linea, $match)) {
        // Nueva acción encontrada
        $numero_accion = normalizarNumeroAccion($match[1]);

        // Extraer el resto de la línea
        $resto = substr($linea, strlen($match[0]));

        // Parsear usando posiciones fijas (aproximadas del formato del PDF)
        // El formato es: NOMBRE (variable) | CI | ZONA | CATEGORIAS | ESTADO | OBS

        // Intentar extraer con regex más flexible
        $partes = preg_split('/\s{2,}/', trim($resto));

        if (count($partes) >= 3) {
            $nombre = trim($partes[0]);
            $ci = isset($partes[1]) ? trim($partes[1]) : 'NINGUNO';

            // Buscar zona, categoría y estado en el resto
            $zona = '';
            $categoria = 'DOMICILIARIA';
            $estado = 'ACTIVO';
            $obs = '';

            // Buscar patrones conocidos
            foreach ($partes as $idx => $parte) {
                $parte_upper = strtoupper(trim($parte));

                // Detectar estado
                if (in_array($parte_upper, ['ACTIVO', 'BAJA', 'CORTADO', 'SIN INST.', 'SIN INST'])) {
                    $estado = $parte_upper === 'SIN INST' ? 'SIN INST.' : $parte_upper;
                }
                // Detectar categoría
                elseif (strpos($parte_upper, 'DOMICILIARIA') !== false || $parte_upper === 'FALSO' || strpos($parte_upper, 'COMERCIAL') !== false || strpos($parte_upper, 'ESPECIAL') !== false) {
                    $categoria = $parte_upper;
                }
                // Detectar zona (contiene palabras clave)
                elseif (preg_match('/(calle|zona|urbanizacion|plaza|final|capani|churiaca|merizalde|pampasi|caritas|pabon|graneros|belmonte|bolivar|molina|cardenas|lanza|lizon|machacamarca)/i', $parte)) {
                    $zona = trim($parte);
                }
            }

            // Si no se encontró zona, buscar en toda la línea
            if (empty($zona)) {
                if (preg_match('/(Zona\s+\w+|Calle\s+[\w\s]+|Urbanizacion\s+[\w\s]+|Plaza\s+[\w\s]+|Capani\s*\d*|Churiaca|Merizalde|Pampasi)/i', $resto, $zona_match)) {
                    $zona = trim($zona_match[0]);
                }
            }

            // Limpiar CI
            $ci_limpio = trim($ci);
            $ci_limpio = preg_replace('/\s+/', ' ', $ci_limpio); // Normalizar espacios
            if ($ci_limpio === 'NINGUNO' || $ci_limpio === '0' || empty($ci_limpio)) {
                $ci_limpio = null;
            } else {
                // Limpiar sufijos y mantener solo lo relevante
                $ci_limpio = preg_replace('/\s*(LP|QR|lp|qr)$/', ' $1', $ci_limpio);
                $ci_limpio = substr($ci_limpio, 0, 50); // Truncar si es muy largo
            }

            $registros[] = [
                'numero_accion' => $numero_accion,
                'nombre' => $nombre,
                'ci' => $ci_limpio,
                'zona' => $zona,
                'categoria' => $categoria,
                'estado' => $estado,
                'obs' => $obs
            ];
        }
    }
}

echo "Registros parseados: " . count($registros) . "\n\n";

// Mostrar primeros 10 para verificar
echo "=== PRIMEROS 10 REGISTROS ===\n";
for ($i = 0; $i < min(10, count($registros)); $i++) {
    $r = $registros[$i];
    echo "{$r['numero_accion']} | {$r['nombre']} | CI: {$r['ci']} | {$r['zona']} | {$r['categoria']} | {$r['estado']}\n";
}
echo "\n";

// Insertar en BD
echo "=== INSERTANDO EN BASE DE DATOS ===\n";

$socios_insertados = 0;
$acciones_insertadas = 0;
$socios_cache = []; // Cache de socios por nombre

$stmt_socio = $pdo->prepare("INSERT INTO socios (numero_socio, nombre, ci, estado, created_at) VALUES (?, ?, ?, 'ACTIVO', NOW())");
$stmt_accion = $pdo->prepare("INSERT INTO acciones (socio_id, numero_accion, tipo_tarifa_id, zona_id, estado, created_at) VALUES (?, ?, ?, ?, ?, NOW())");

foreach ($registros as $idx => $reg) {
    $nombre_normalizado = strtolower(trim($reg['nombre']));

    // Verificar si el socio ya existe (por nombre)
    if (isset($socios_cache[$nombre_normalizado])) {
        $socio_id = $socios_cache[$nombre_normalizado];
    } else {
        // Buscar en BD
        $stmt_buscar = $pdo->prepare("SELECT id FROM socios WHERE LOWER(nombre) = ?");
        $stmt_buscar->execute([$nombre_normalizado]);
        $socio_existente = $stmt_buscar->fetch(PDO::FETCH_ASSOC);

        if ($socio_existente) {
            $socio_id = $socio_existente['id'];
        } else {
            // Crear nuevo socio
            $numero_socio = str_pad($socios_insertados + 1, 4, '0', STR_PAD_LEFT);
            $stmt_socio->execute([
                $numero_socio,
                $reg['nombre'],
                $reg['ci']
            ]);
            $socio_id = $pdo->lastInsertId();
            $socios_insertados++;
        }
        $socios_cache[$nombre_normalizado] = $socio_id;
    }

    // Obtener zona_id
    $zona_id = getOrCreateZona($pdo, $reg['zona']);

    // Obtener tipo_tarifa_id
    $tarifa_id = getTipoTarifa($pdo, $reg['categoria']);

    // Normalizar estado
    $estado = strtoupper($reg['estado']);
    if ($estado === 'SIN INST') $estado = 'SIN INST.';

    // Verificar si la acción ya existe
    $stmt_check = $pdo->prepare("SELECT id FROM acciones WHERE numero_accion = ?");
    $stmt_check->execute([$reg['numero_accion']]);
    if (!$stmt_check->fetch()) {
        // Insertar acción
        $stmt_accion->execute([
            $socio_id,
            $reg['numero_accion'],
            $tarifa_id,
            $zona_id,
            $estado
        ]);
        $acciones_insertadas++;
    }

    // Mostrar progreso cada 100 registros
    if (($idx + 1) % 100 === 0) {
        echo "Procesados: " . ($idx + 1) . " registros\n";
    }
}

echo "\n=== MIGRACIÓN COMPLETADA ===\n";
echo "Socios insertados: $socios_insertados\n";
echo "Acciones insertadas: $acciones_insertadas\n";

// Verificar resultados
echo "\n=== VERIFICACIÓN ===\n";
$stmt = $pdo->query("SELECT COUNT(*) as total FROM socios");
echo "Total socios en BD: " . $stmt->fetch()['total'] . "\n";

$stmt = $pdo->query("SELECT COUNT(*) as total FROM acciones");
echo "Total acciones en BD: " . $stmt->fetch()['total'] . "\n";

$stmt = $pdo->query("SELECT COUNT(*) as total FROM zonas WHERE activo = 1");
echo "Total zonas: " . $stmt->fetch()['total'] . "\n";

// Mostrar socios con múltiples acciones
echo "\n=== SOCIOS CON MÚLTIPLES ACCIONES ===\n";
$stmt = $pdo->query("
    SELECT s.nombre, COUNT(a.id) as num_acciones, GROUP_CONCAT(a.numero_accion SEPARATOR ', ') as acciones
    FROM socios s
    INNER JOIN acciones a ON s.id = a.socio_id
    GROUP BY s.id
    HAVING num_acciones > 1
    ORDER BY num_acciones DESC
    LIMIT 10
");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "{$row['nombre']}: {$row['num_acciones']} acciones ({$row['acciones']})\n";
}

echo "\n=== ZONAS CREADAS ===\n";
$stmt = $pdo->query("SELECT nombre, (SELECT COUNT(*) FROM acciones WHERE zona_id = zonas.id) as num_acciones FROM zonas WHERE activo = 1 ORDER BY nombre");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "{$row['nombre']}: {$row['num_acciones']} acciones\n";
}
