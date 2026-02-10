<?php
/**
 * Script de migración v2 - Mejorado
 * Cooperativa de Agua - Irupana
 * Fecha: 4 Feb 2026
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

$pdo = new PDO("mysql:host=localhost;dbname=cooperativa_agua;charset=utf8mb4", 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$archivo = '/tmp/socios_cooperativa.txt';
$contenido = file_get_contents($archivo);
$lineas = explode("\n", $contenido);

echo "=== MIGRACIÓN DE SOCIOS v2 ===\n";

// Lista de zonas válidas conocidas
$zonas_validas = [
    'zona pampasi', 'pampasi', 'final pampasi',
    'calle caritas la paz', 'caritas la paz',
    'calle rafael pabon', 'rafael pabon',
    'calle angel del prado', 'angel del prado',
    'calle la paz',
    'calle machacamarca', 'machacamarca', 'final machacamarca', 'final machamarca',
    'calle graneros', 'graneros',
    'plaza victorio lanza', 'victorio lanza',
    'calle lizon', 'lizon',
    'calle max garcia', 'max garcia',
    'calle bolivar', 'bolivar',
    'calle felipe molina', 'felipe molina',
    'calle guzman', 'guzman',
    'calle merizalde', 'merizalde',
    'calle cochabamba', 'cochabamba',
    'calle sucre', 'sucre',
    'calle alcazar', 'alcazar',
    'calle agustin aspiazu', 'agustin aspiazu',
    'calle nicanor belmonte', 'nicanor belmonte',
    'calle pacheco', 'pacheco',
    'calle capani', 'capani', 'capani 2',
    'calle sascuya', 'sascuya',
    'calle heroes del chaco', 'heroes del chaco',
    'calle cardenas', 'cardenas',
    'zona churiaca', 'churiaca', 'zona churiaca - sascuya',
    'urbanizacion nueva vida', 'nueva vida',
    'urbanizacion 25 de julio', '25 de julio', 'urbanizacion 25 de julio - 2',
    'urbanizacion villa lourdes', 'villa lourdes',
    'avenida del estudiante',
];

// Normalizar zona
function normalizarZona($zona) {
    global $zonas_validas;
    if ($zona === null) return null;
    $zona = trim(strtolower($zona));

    // Si empieza con número, probablemente es CI mal parseado
    if (preg_match('/^\d/', $zona)) {
        return null;
    }

    // Buscar coincidencia
    foreach ($zonas_validas as $z) {
        if (strpos($zona, $z) !== false || strpos($z, $zona) !== false) {
            // Normalizar a forma canónica
            $z = ucwords($z);
            // Agregar "Calle" si no tiene prefijo
            if (!preg_match('/^(Zona|Calle|Plaza|Urbanizacion|Avenida|Final)/i', $z)) {
                $z = "Calle " . $z;
            }
            return $z;
        }
    }

    return ucwords($zona);
}

// Lista de categorías válidas
$categorias_validas = ['DOMICILIARIA', 'DOMICILIARIA SOCIAL', 'ESPECIAL', 'COMERCIAL', 'FALSO'];

// Lista de estados válidos
$estados_validos = ['ACTIVO', 'BAJA', 'CORTADO', 'SIN INST.'];

// Parsear registros
$registros = [];

foreach ($lineas as $linea) {
    // Detectar línea con número de acción
    if (!preg_match('/^\s*(\d+\s*-\s*[A-Za-z]\d?)\s+/', $linea, $match)) {
        continue;
    }

    $numero_accion = trim($match[1]);
    // Normalizar: "1-A" -> "1 - A", "10 - A" queda igual
    $numero_accion = preg_replace('/^(\d+)\s*-\s*([A-Za-z]\d?)$/i', '$1 - $2', $numero_accion);
    $numero_accion = strtoupper($numero_accion);

    $resto = substr($linea, strlen($match[0]));

    // Dividir por múltiples espacios
    $partes = preg_split('/\s{2,}/', trim($resto));

    // Primera parte siempre es el nombre
    $nombre = trim($partes[0] ?? '');
    if (empty($nombre)) continue;

    // Buscar CI, zona, categoría, estado
    $ci = null;
    $zona = null;
    $categoria = 'DOMICILIARIA';
    $estado = 'ACTIVO';

    for ($i = 1; $i < count($partes); $i++) {
        $parte = trim($partes[$i]);
        $parte_upper = strtoupper($parte);

        // ¿Es estado?
        if (in_array($parte_upper, $estados_validos) || $parte_upper === 'SIN INST') {
            $estado = $parte_upper === 'SIN INST' ? 'SIN INST.' : $parte_upper;
            continue;
        }

        // ¿Es categoría?
        $es_categoria = false;
        foreach ($categorias_validas as $cat) {
            if (strpos($parte_upper, $cat) !== false) {
                $categoria = $parte_upper;
                $es_categoria = true;
                break;
            }
        }
        if ($es_categoria) continue;

        // ¿Es CI? (número o "NINGUNO")
        if ($parte_upper === 'NINGUNO' || preg_match('/^\d{5,}/', $parte)) {
            if ($parte_upper !== 'NINGUNO') {
                $ci = preg_replace('/\s+/', ' ', $parte);
            }
            continue;
        }

        // ¿Es zona? (contiene palabras clave de zona)
        if (preg_match('/(zona|calle|urbanizacion|plaza|final|capani|churiaca|pampasi|merizalde|bolivar|molina|graneros|lizon|machacamarca|belmonte|sucre|cochabamba|guzman|alcazar|aspiazu|pacheco|sascuya|lanza|pabon|caritas|heroes|cardenas|nueva vida|villa lourdes|estudiante)/i', $parte)) {
            $zona = $parte;
            continue;
        }
    }

    // Normalizar zona
    $zona = normalizarZona($zona);

    $registros[] = [
        'numero_accion' => $numero_accion,
        'nombre' => $nombre,
        'ci' => $ci ? substr($ci, 0, 50) : null,
        'zona' => $zona,
        'categoria' => $categoria,
        'estado' => $estado
    ];
}

echo "Registros parseados: " . count($registros) . "\n\n";

// Limpiar BD
echo "Limpiando BD...\n";
$pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
$pdo->exec("DELETE FROM consumos_anuales");
$pdo->exec("DELETE FROM pago_consumos");
$pdo->exec("DELETE FROM pago_detalles");
$pdo->exec("DELETE FROM servicios");
$pdo->exec("DELETE FROM pagos");
$pdo->exec("DELETE FROM acciones");
$pdo->exec("DELETE FROM socios");
$pdo->exec("DELETE FROM zonas WHERE id > 0");
$pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

// Crear zonas limpias
$zonas_unicas = [];
foreach ($registros as $r) {
    if ($r['zona'] && !isset($zonas_unicas[$r['zona']])) {
        $zonas_unicas[$r['zona']] = true;
    }
}

$stmt_zona = $pdo->prepare("INSERT INTO zonas (nombre, descripcion, activo) VALUES (?, ?, 1)");
$zonas_ids = [];
foreach (array_keys($zonas_unicas) as $z) {
    $stmt_zona->execute([$z, $z]);
    $zonas_ids[$z] = $pdo->lastInsertId();
}
echo "Zonas creadas: " . count($zonas_ids) . "\n";

// Función para obtener tarifa
function getTarifa($pdo, $cat) {
    static $cache = [];
    if (isset($cache[$cat])) return $cache[$cat];

    if (strpos($cat, 'SOCIAL') !== false) {
        $nombre = 'DOMICILIARIA SOCIAL';
    } elseif (strpos($cat, 'ESPECIAL') !== false) {
        $nombre = 'ESPECIAL';
    } elseif (strpos($cat, 'COMERCIAL') !== false) {
        $nombre = 'COMERCIAL';
    } else {
        $nombre = 'DOMICILIARIA';
    }

    $stmt = $pdo->prepare("SELECT id FROM tipos_tarifa WHERE nombre LIKE ?");
    $stmt->execute(['%' . $nombre . '%']);
    $r = $stmt->fetch();
    $cache[$cat] = $r ? $r['id'] : 51;
    return $cache[$cat];
}

// Insertar socios y acciones
$socios_cache = [];
$stmt_socio = $pdo->prepare("INSERT INTO socios (numero_socio, nombre, ci, estado, created_at) VALUES (?, ?, ?, 'ACTIVO', NOW())");
$stmt_accion = $pdo->prepare("INSERT INTO acciones (socio_id, numero_accion, tipo_tarifa_id, zona_id, estado, created_at) VALUES (?, ?, ?, ?, ?, NOW())");

$num_socio = 0;
$acciones_ok = 0;

foreach ($registros as $r) {
    $nombre_key = strtolower(trim($r['nombre']));

    if (!isset($socios_cache[$nombre_key])) {
        $num_socio++;
        $stmt_socio->execute([
            str_pad($num_socio, 4, '0', STR_PAD_LEFT),
            $r['nombre'],
            $r['ci']
        ]);
        $socios_cache[$nombre_key] = $pdo->lastInsertId();
    }

    $socio_id = $socios_cache[$nombre_key];
    $zona_id = $r['zona'] ? ($zonas_ids[$r['zona']] ?? null) : null;
    $tarifa_id = getTarifa($pdo, $r['categoria']);

    $stmt_accion->execute([
        $socio_id,
        $r['numero_accion'],
        $tarifa_id,
        $zona_id,
        $r['estado']
    ]);
    $acciones_ok++;
}

echo "\n=== RESULTADO ===\n";
echo "Socios: $num_socio\n";
echo "Acciones: $acciones_ok\n";

// Verificar
$stmt = $pdo->query("SELECT COUNT(*) as t FROM socios");
echo "Socios en BD: " . $stmt->fetch()['t'] . "\n";

$stmt = $pdo->query("SELECT COUNT(*) as t FROM acciones");
echo "Acciones en BD: " . $stmt->fetch()['t'] . "\n";

$stmt = $pdo->query("SELECT COUNT(*) as t FROM zonas WHERE activo=1");
echo "Zonas en BD: " . $stmt->fetch()['t'] . "\n";

// Mostrar zonas
echo "\n=== ZONAS ===\n";
$stmt = $pdo->query("SELECT z.nombre, COUNT(a.id) as n FROM zonas z LEFT JOIN acciones a ON z.id = a.zona_id WHERE z.activo=1 GROUP BY z.id ORDER BY n DESC LIMIT 20");
while ($row = $stmt->fetch()) {
    echo "{$row['n']}\t{$row['nombre']}\n";
}

// Socios con múltiples acciones
echo "\n=== SOCIOS CON MÚLTIPLES ACCIONES ===\n";
$stmt = $pdo->query("
    SELECT s.nombre, COUNT(a.id) as n, GROUP_CONCAT(a.numero_accion ORDER BY a.numero_accion SEPARATOR ', ') as acc
    FROM socios s JOIN acciones a ON s.id = a.socio_id
    GROUP BY s.id HAVING n > 1 ORDER BY n DESC LIMIT 10
");
while ($row = $stmt->fetch()) {
    echo "{$row['n']}\t{$row['nombre']}: {$row['acc']}\n";
}

// Estados
echo "\n=== ESTADOS ===\n";
$stmt = $pdo->query("SELECT estado, COUNT(*) as n FROM acciones GROUP BY estado");
while ($row = $stmt->fetch()) {
    echo "{$row['estado']}: {$row['n']}\n";
}
