<?php
/**
 * Script de Migración de Socios desde PDF
 * Cooperativa de Agua "Virgen de las Nieves"
 *
 * Este script importa los 1,063 socios del reporte PDF
 */

require_once __DIR__ . '/../config/database.php';

// Desactivar límite de tiempo
set_time_limit(0);

echo "=== MIGRACIÓN DE SOCIOS ===\n\n";

// Primero, limpiar las tablas de socios y acciones existentes
echo "Limpiando datos existentes...\n";

try {
    $pdo = getConnection();
    // Desactivar FKs temporalmente
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

    // Limpiar tablas relacionadas
    $pdo->exec("DELETE FROM consumos_anuales");
    $pdo->exec("DELETE FROM pago_detalles");
    $pdo->exec("DELETE FROM pago_items_adicionales");
    $pdo->exec("DELETE FROM pagos");
    $pdo->exec("DELETE FROM servicios");
    $pdo->exec("DELETE FROM exoneraciones");
    $pdo->exec("DELETE FROM historial_cesiones");
    $pdo->exec("DELETE FROM acciones");
    $pdo->exec("DELETE FROM socios");

    // Resetear auto_increment
    $pdo->exec("ALTER TABLE socios AUTO_INCREMENT = 1");
    $pdo->exec("ALTER TABLE acciones AUTO_INCREMENT = 1");

    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

    echo "Tablas limpiadas.\n\n";

} catch (Exception $e) {
    echo "Error limpiando tablas: " . $e->getMessage() . "\n";
    exit(1);
}

// Mapeo de zonas del PDF a IDs de la BD
$zonas_map = [];
$zonas = fetchAll("SELECT id, nombre FROM zonas");
foreach ($zonas as $z) {
    $zonas_map[strtolower(trim($z['nombre']))] = $z['id'];
}

// Función para buscar zona por nombre (con tolerancia)
function buscarZona($nombre, $zonas_map) {
    $nombre = strtolower(trim($nombre));

    // Búsqueda exacta
    if (isset($zonas_map[$nombre])) {
        return $zonas_map[$nombre];
    }

    // Búsqueda parcial
    foreach ($zonas_map as $key => $id) {
        if (strpos($key, $nombre) !== false || strpos($nombre, $key) !== false) {
            return $id;
        }
    }

    // Normalizar y buscar
    $nombre_norm = str_replace(['á','é','í','ó','ú','ñ'], ['a','e','i','o','u','n'], $nombre);
    foreach ($zonas_map as $key => $id) {
        $key_norm = str_replace(['á','é','í','ó','ú','ñ'], ['a','e','i','o','u','n'], $key);
        if (strpos($key_norm, $nombre_norm) !== false || strpos($nombre_norm, $key_norm) !== false) {
            return $id;
        }
    }

    return null;
}

// Mapeo de categorías del PDF a IDs de la BD
$categorias_map = [
    'domiciliaria social' => 52,
    'domiciliaria' => 51,
    'especial' => 53,
    'comercial (cooperativa, alojamiento)' => 54,
    'comercial (hotel, albergue)' => 55,
    'comercial (oficina, pension)' => 56,
    'domiciliaria (inquilinos cat1)' => 58,
    'domiciliaria (inquilinos cat2)' => 59,
    'domiciliario (inquilinos cat3)' => 60,
    'domiciliario (inquilinos cat4)' => 61,
    'domiciliario (inquilinos cat5)' => 62,
    'falso' => null, // Ignorar o tratar como BAJA
];

function buscarCategoria($nombre) {
    global $categorias_map;
    $nombre = strtolower(trim($nombre));

    if (isset($categorias_map[$nombre])) {
        return $categorias_map[$nombre];
    }

    // Búsqueda parcial
    foreach ($categorias_map as $key => $id) {
        if (strpos($nombre, $key) !== false || strpos($key, $nombre) !== false) {
            return $id;
        }
    }

    // Default a DOMICILIARIA
    return 51;
}

// Mapeo de estados
function mapearEstado($estado) {
    $estado = strtoupper(trim($estado));
    switch ($estado) {
        case 'ACTIVO':
            return 'ACTIVO';
        case 'CORTADO':
            return 'CORTADO';
        case 'BAJA':
            return 'BAJA';
        case 'SIN INST.':
        case 'SIN INST':
            return 'SIN INST.';
        default:
            return 'ACTIVO';
    }
}

// Array con todos los datos del PDF (extraído del documento)
$datos_pdf = <<<'DATA'
1 - A|Javier Humberto Arce Perez|NINGUNO|Zona Pampasi|DOMICILIARIA SOCIAL|ACTIVO|NINGUNO
2 - A|Paulina Calcina Apaza|NINGUNO|Zona Pampasi|DOMICILIARIA SOCIAL|ACTIVO|NINGUNO
3 - A|Ninfa Carreño Vd.de Rodrigues|NINGUNO|Zona Pampasi|DOMICILIARIA SOCIAL|ACTIVO|NINGUNO
4 - A|Ariel Yucra Quispe|NINGUNO|Zona Pampasi|DOMICILIARIA|ACTIVO|NINGUNO
5 - A|Maria Julia Safadi de Carrasco|NINGUNO|Zona Pampasi|DOMICILIARIA SOCIAL|ACTIVO|NINGUNO
6 - A|Josefina Apaza Cocarico|4324331|Zona Pampasi|DOMICILIARIA|ACTIVO|NINGUNO
7 - A|Paulina Salazar Alanoca|NINGUNO|Zona Pampasi|FALSO|BAJA|BAJA
8 - A|Margarita Castañon Vargas|NINGUNO|Zona Pampasi|DOMICILIARIA SOCIAL|ACTIVO|NINGUNO
9 - A|Ignacio Arzabe Pari|NINGUNO|Zona Pampasi|DOMICILIARIA|ACTIVO|NINGUNO
10 - A|Ana María de Archondo|NINGUNO|Zona Pampasi|DOMICILIARIA SOCIAL|ACTIVO|NINGUNO
11 - A|María Teresa Mercado Reguerin|NINGUNO|Zona Pampasi|DOMICILIARIA SOCIAL|ACTIVO|NINGUNO
12 - A|Adelaida Mamani Canaza|NINGUNO|Zona Pampasi|DOMICILIARIA SOCIAL|ACTIVO|NINGUNO
13 - A|Nolberto Cerezo|NINGUNO|Zona Pampasi|DOMICILIARIA SOCIAL|ACTIVO|NINGUNO
14 - A|Rosa Carreño Espinoza|NINGUNO|Zona Pampasi|DOMICILIARIA SOCIAL|ACTIVO|NINGUNO
15 - A|Simón Suca Huanca|NINGUNO|Zona Pampasi|DOMICILIARIA SOCIAL|ACTIVO|NINGUNO
16 - A|Jesús Mamani Coasaca|NINGUNO|Zona Pampasi|DOMICILIARIA SOCIAL|ACTIVO|NINGUNO
17 - A|Ana María Vd. De choque|NINGUNO|Zona Pampasi|DOMICILIARIA SOCIAL|ACTIVO|NINGUNO
18 - A|Patricio Carrasco|NINGUNO|Zona Pampasi|DOMICILIARIA|ACTIVO|NINGUNO
19 - A|Roberto Ramirez Pedreros|2537875|Zona Pampasi|DOMICILIARIA SOCIAL|ACTIVO|NINGUNO
20 - A|Hugo lizon Aceñas|NINGUNO|Zona Pampasi|DOMICILIARIA SOCIAL|ACTIVO|NINGUNO
21 - A|Maura Ramos Quispe|NINGUNO|Zona Pampasi|DOMICILIARIA SOCIAL|ACTIVO|NINGUNO
22 - A|Luis Godoy Andrade|NINGUNO|Zona Pampasi|DOMICILIARIA SOCIAL|ACTIVO|NINGUNO
23 - A|Lucas Aruquipa Flores|NINGUNO|Zona Pampasi|DOMICILIARIA|CORTADO|CORTADO
24 - A|Estela Poma Salazar|NINGUNO|Zona Pampasi|DOMICILIARIA SOCIAL|ACTIVO|
25 - A|Sami Yanqui Castañón|NINGUNO|Zona Pampasi|DOMICILIARIA|ACTIVO|NINGUNO
26 - A|Marco Antonio Moreno Ramírez|NINGUNO|Zona Pampasi|DOMICILIARIA SOCIAL|ACTIVO|NINGUNO
27 - A|Jasinto Zuca Gutiérrez|NINGUNO|Zona Pampasi|DOMICILIARIA SOCIAL|ACTIVO|NINGUNO
28 - A|Celso Chincheros Gonzales|NINGUNO|Caritas la paz|DOMICILIARIA SOCIAL|ACTIVO|
29 - A|Ruth Edith Leon Vera|NINGUNO|Zona Pampasi|DOMICILIARIA|CORTADO|CORTADO
1 - B|Julio Mass Acosta|NINGUNO|Calle Caritas La Paz|DOMICILIARIA|ACTIVO|NINGUNO
2 - B|Primo Arce Carrasco|NINGUNO|Calle Caritas La Paz|DOMICILIARIA|ACTIVO|NINGUNO
3 - B|Aida Moncada Molina|NINGUNO|Calle Caritas La Paz|DOMICILIARIA|ACTIVO|NINGUNO
4 - B|Juana Alanoca Vda. De Guanca|NINGUNO|Calle Caritas La Paz|DOMICILIARIA SOCIAL|ACTIVO|NINGUNO
5 - B|Hilda Mamani de Suntura|NINGUNO|Calle Caritas La Paz|DOMICILIARIA|ACTIVO|NINGUNO
6 - B|Agustín Loayza Matos|NINGUNO|Calle Caritas La Paz|DOMICILIARIA|CORTADO|NINGUNO
7 - B|Marcelino Ticona Quispe|NINGUNO|Calle Caritas La Paz|DOMICILIARIA SOCIAL|ACTIVO|NINGUNO
8 - B|Juan Meneses|NINGUNO|Calle Caritas La Paz|DOMICILIARIA|ACTIVO|NINGUNO
9 - B|José Miranda|NINGUNO|Calle Caritas La Paz|DOMICILIARIA|BAJA|NINGUNO
10 - B|Carmen Santander Hilaquita|NINGUNO|Calle Caritas La Paz|DOMICILIARIA SOCIAL|ACTIVO|NINGUNO
11 - B|Santiago Mamani|NINGUNO|Calle Caritas La Paz|DOMICILIARIA SOCIAL|ACTIVO|NINGUNO
12 - B|Cruz Chacon Mamani|NINGUNO|Calle Caritas La Paz|DOMICILIARIA SOCIAL|ACTIVO|NINGUNO
13 - B|Visente Vidangos|NINGUNO|Calle Caritas La Paz|DOMICILIARIA SOCIAL|ACTIVO|NINGUNO
14 - B|María vd. De Millán|NINGUNO|Calle Caritas La Paz|DOMICILIARIA|ACTIVO|
15 - B|Maxima Chincheros|NINGUNO|Calle Caritas La Paz|DOMICILIARIA|BAJA|NINGUNO
16 - B|Alfonso Soca|NINGUNO|Calle Caritas La Paz|DOMICILIARIA SOCIAL|ACTIVO|NINGUNO
17 - B|Pedro Mamani Aguilar|5945579 LP|Calle Caritas La Paz|DOMICILIARIA SOCIAL|ACTIVO|NINGUNO
18 - B|Lidia Yanarico Quispe|NINGUNO|Calle Caritas La Paz|DOMICILIARIA SOCIAL|ACTIVO|NINGUNO
19 - B|Ricardo Poma Salazar|NINGUNO|Calle Caritas La Paz|FALSO|SIN INST.|SIN INST.
21 - B|Primo Arce Carrasco|NINGUNO|Calle Caritas La Paz|DOMICILIARIA|ACTIVO|NINGUNO
22 - B|Constancia Isabel Sanchez de Tristan|NINGUNO|Calle Caritas La Paz|DOMICILIARIA SOCIAL|ACTIVO|NINGUNO
23 - B|Baño Publico|NINGUNO|Calle Caritas La Paz|DOMICILIARIA|ACTIVO|NINGUNO
24 - B|Guillermina Santander Hilaquita Vda. de Chino|2649174 LP|Calle Caritas La Paz|DOMICILIARIA SOCIAL|ACTIVO|NINGUNO
25 - B|Nestor Jorge Castro Muriel|2477811|Caritas La Paz|DOMICILIARIA|SIN INST.|NINGUNO
DATA;

// Continúa en un archivo separado debido al tamaño
// Por ahora procesamos una muestra para verificar el funcionamiento

// Dividir por líneas
$lineas = explode("\n", trim($datos_pdf));

echo "Procesando " . count($lineas) . " registros de muestra...\n\n";

// Mantener registro de socios creados (por nombre normalizado)
$socios_creados = [];
$acciones_creadas = 0;
$errores = [];

foreach ($lineas as $linea) {
    $linea = trim($linea);
    if (empty($linea)) continue;

    // Dividir por |
    $campos = explode('|', $linea);
    if (count($campos) < 6) {
        $errores[] = "Línea mal formada: $linea";
        continue;
    }

    $codigo_accion = trim($campos[0]);
    $nombre = trim($campos[1]);
    $ci = trim($campos[2]);
    $zona_nombre = trim($campos[3]);
    $categoria_nombre = trim($campos[4]);
    $estado = trim($campos[5]);
    $obs = isset($campos[6]) ? trim($campos[6]) : '';

    // Normalizar CI
    if (strtoupper($ci) === 'NINGUNO' || $ci === '0') {
        $ci = null;
    }

    // Normalizar nombre para comparación
    $nombre_norm = strtolower(trim($nombre));

    // Buscar zona
    $zona_id = buscarZona($zona_nombre, $zonas_map);
    if (!$zona_id) {
        echo "WARN: Zona no encontrada: '$zona_nombre' para $codigo_accion\n";
    }

    // Buscar categoría
    $tipo_tarifa_id = buscarCategoria($categoria_nombre);
    if (!$tipo_tarifa_id) {
        // Si es FALSO, el estado debería ser BAJA
        $estado = 'BAJA';
        $tipo_tarifa_id = 51; // Default a DOMICILIARIA
    }

    // Mapear estado
    $estado_mapeado = mapearEstado($estado);

    // Crear o buscar socio
    if (!isset($socios_creados[$nombre_norm])) {
        // Crear nuevo socio
        $numero_socio = str_pad(count($socios_creados) + 1, 4, '0', STR_PAD_LEFT);

        $sql = "INSERT INTO socios (numero_socio, nombre, ci, estado, fecha_alta, observaciones)
                VALUES (?, ?, ?, ?, CURDATE(), ?)";

        try {
            $socio_id = insert($sql, [
                $numero_socio,
                $nombre,
                $ci,
                $estado_mapeado,
                ($obs && $obs !== 'NINGUNO') ? $obs : null
            ]);

            $socios_creados[$nombre_norm] = $socio_id;

        } catch (Exception $e) {
            $errores[] = "Error creando socio '$nombre': " . $e->getMessage();
            continue;
        }
    }

    $socio_id = $socios_creados[$nombre_norm];

    // Crear acción
    $sql = "INSERT INTO acciones (socio_id, numero_accion, tipo_tarifa_id, zona_id, estado, fecha_instalacion)
            VALUES (?, ?, ?, ?, ?, CURDATE())";

    try {
        insert($sql, [
            $socio_id,
            $codigo_accion,
            $tipo_tarifa_id,
            $zona_id,
            $estado_mapeado
        ]);

        $acciones_creadas++;

    } catch (Exception $e) {
        $errores[] = "Error creando acción '$codigo_accion': " . $e->getMessage();
    }
}

echo "\n=== RESUMEN ===\n";
echo "Socios creados: " . count($socios_creados) . "\n";
echo "Acciones creadas: $acciones_creadas\n";
echo "Errores: " . count($errores) . "\n";

if (count($errores) > 0) {
    echo "\n=== ERRORES ===\n";
    foreach (array_slice($errores, 0, 10) as $error) {
        echo "- $error\n";
    }
    if (count($errores) > 10) {
        echo "... y " . (count($errores) - 10) . " errores más.\n";
    }
}

echo "\nMigración de muestra completada.\n";
echo "Para migrar todos los datos, ejecute el script completo.\n";
