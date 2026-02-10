<?php
/**
 * Generar Notificaciones de Deuda para Impresión
 * 4 por página tamaño carta - TIRAS HORIZONTALES
 * Letra grande para personas de tercera edad
 */

require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/config/config.php';
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_once dirname(dirname(__DIR__)) . '/includes/functions.php';

requireLogin();

$acciones_ids = $_POST['acciones'] ?? [];

if (empty($acciones_ids)) {
    setFlash('error', 'No se seleccionaron acciones');
    redirect('/modules/notificaciones/');
}

$placeholders = implode(',', array_fill(0, count($acciones_ids), '?'));
$acciones = fetchAll(
    "SELECT a.id as accion_id, a.numero_accion,
            s.nombre as socio_nombre, s.numero_socio,
            z.nombre as zona_nombre,
            COALESCE(SUM(ca.monto), 0) as total_deuda,
            COUNT(ca.id) as num_meses
     FROM acciones a
     JOIN socios s ON a.socio_id = s.id
     LEFT JOIN zonas z ON a.zona_id = z.id
     LEFT JOIN consumos_anuales ca ON ca.accion_id = a.id AND ca.estado = 'pendiente' AND (ca.anio < YEAR(CURDATE()) OR (ca.anio = YEAR(CURDATE()) AND ca.mes < MONTH(CURDATE())))
     WHERE a.id IN ($placeholders)
     GROUP BY a.id
     ORDER BY a.numero_accion",
    array_map('intval', $acciones_ids)
);

foreach ($acciones as &$accion) {
    $accion['meses'] = fetchAll(
        "SELECT mes, anio, monto FROM consumos_anuales
         WHERE accion_id = ? AND estado = 'pendiente'
         ORDER BY anio, mes",
        [$accion['accion_id']]
    );
}
unset($accion);

$config = getConfig();
$fechaActual = date('d') . ' de ' . getNombreMes(date('n')) . ' de ' . date('Y');
$mesesCortos = ['ENE', 'FEB', 'MAR', 'ABR', 'MAY', 'JUN', 'JUL', 'AGO', 'SEP', 'OCT', 'NOV', 'DIC'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Notificaciones de Deuda</title>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { margin: 0; padding: 0; }
        }

        @page {
            size: letter portrait;
            margin: 4mm;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 15px;
            background: #f0f0f0;
        }

        .page {
            width: 216mm;
            height: 279mm;
            padding: 2mm;
            margin: 10px auto;
            background: white;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            display: flex;
            flex-direction: column;
            gap: 2mm;
        }

        @media print {
            .page {
                width: 100%;
                height: 100%;
                margin: 0;
                box-shadow: none;
                page-break-after: always;
            }
        }

        .notificacion {
            flex: 1;
            border: 2px solid #333;
            padding: 10px 15px;
            display: flex;
            flex-direction: row;
            align-items: stretch;
            gap: 15px;
            min-height: 67mm;
        }

        .notif-izquierda {
            flex: 0 0 190px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            border-right: 2px dashed #079FEA;
            padding-right: 15px;
        }

        .notif-header {
            text-align: center;
            margin-bottom: 10px;
        }

        .notif-header h1 {
            color: #079FEA;
            font-size: 14px;
            margin-bottom: 3px;
            text-transform: uppercase;
            line-height: 1.2;
        }

        .notif-header p {
            font-size: 12px;
            color: #666;
        }

        .notif-titulo {
            text-align: center;
            font-weight: bold;
            font-size: 14px;
            color: #c00;
            background: #fee;
            padding: 6px;
            border-radius: 4px;
            margin-bottom: 10px;
        }

        .notif-total {
            background: #079FEA;
            color: white;
            padding: 10px;
            border-radius: 5px;
            text-align: center;
            font-weight: bold;
        }

        .notif-total .label {
            font-size: 13px;
            display: block;
        }

        .notif-total .monto {
            font-size: 24px;
            display: block;
        }

        .notif-centro {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .notif-datos {
            font-size: 15px;
            margin-bottom: 10px;
        }

        .notif-datos .dato {
            margin-bottom: 6px;
        }

        .notif-datos .label {
            color: #666;
            font-size: 13px;
        }

        .notif-datos .valor {
            font-weight: bold;
            font-size: 17px;
        }

        .meses-container {
            margin-top: 10px;
        }

        .meses-titulo {
            font-size: 13px;
            color: #666;
            margin-bottom: 6px;
        }

        .meses-grid {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }

        .mes-cuadro {
            width: 42px;
            height: 30px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 12px;
            background: #e8f5e9;
            color: #2e7d32;
            font-weight: bold;
        }

        .mes-cuadro.pendiente {
            background: #dc3545;
            color: white;
            border-color: #dc3545;
            font-weight: bold;
        }

        .notif-derecha {
            flex: 0 0 210px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            border-left: 2px dashed #ffc107;
            padding-left: 15px;
            font-size: 13px;
        }

        .notif-mensaje {
            background: #fff3cd;
            padding: 10px;
            border-radius: 4px;
            border-left: 4px solid #ffc107;
            line-height: 1.5;
            font-size: 13px;
        }

        .notif-mensaje strong {
            color: #c00;
        }

        .notif-footer {
            margin-top: 10px;
            text-align: center;
            font-size: 12px;
            color: #666;
        }

        .notif-footer strong {
            display: block;
            color: #333;
            font-size: 13px;
        }

        .notificacion-vacia {
            border: 2px dashed #ddd;
            opacity: 0.3;
        }

        .btn-print {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
            display: flex;
            gap: 10px;
        }

        .btn-print button, .btn-print a {
            padding: 12px 25px;
            background: #079FEA;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            cursor: pointer;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-print button:hover, .btn-print a:hover {
            background: #0689cc;
        }

        .btn-print a.secondary {
            background: #6c757d;
        }
    </style>
</head>
<body>

<div class="btn-print no-print">
    <button onclick="window.print()">Imprimir</button>
    <a href="<?= BASE_URL ?>/modules/notificaciones/" class="secondary">Volver</a>
</div>

<?php
$contador = 0;
$totalAcciones = count($acciones);
$anioActual = date('Y');

foreach ($acciones as $index => $accion):
    if ($contador % 4 === 0):
?>
<div class="page">
<?php endif; ?>

    <div class="notificacion">
        <div class="notif-izquierda">
            <div class="notif-header">
                <h1><?= htmlspecialchars($config['nombre_cooperativa']) ?></h1>
                <p>Irupana - Sud Yungas - La Paz</p>
            </div>
            <div class="notif-titulo">
                NOTIFICACION DE DEUDA
            </div>
            <div class="notif-total">
                <span class="label">TOTAL ADEUDADO</span>
                <span class="monto"><?= formatMoney($accion['total_deuda']) ?></span>
            </div>
        </div>

        <div class="notif-centro">
            <div class="notif-datos">
                <div class="dato">
                    <span class="label">SOCIO(A):</span>
                    <span class="valor"><?= htmlspecialchars($accion['socio_nombre']) ?></span>
                </div>
                <div class="dato">
                    <span class="label">ACCION N:</span>
                    <span class="valor"><?= htmlspecialchars($accion['numero_accion']) ?></span>
                </div>
                <div class="dato">
                    <span class="label">ZONA:</span>
                    <span class="valor"><?= htmlspecialchars($accion['zona_nombre'] ?? 'Sin zona') ?></span>
                </div>
            </div>

            <div class="meses-container">
                <div class="meses-titulo">MESES <?= $anioActual ?> (ROJO = Pendiente)</div>
                <div class="meses-grid">
                    <?php
                    $mesesPendientes = [];
                    foreach ($accion['meses'] as $m) {
                        $key = $m['anio'] . '-' . $m['mes'];
                        $mesesPendientes[$key] = true;
                    }
                    for ($mes = 1; $mes <= 12; $mes++):
                        $key = $anioActual . '-' . $mes;
                        $esPendiente = isset($mesesPendientes[$key]);
                    ?>
                    <div class="mes-cuadro <?= $esPendiente ? 'pendiente' : '' ?>">
                        <?= $mesesCortos[$mes - 1] ?>
                    </div>
                    <?php endfor; ?>
                </div>
            </div>
        </div>

        <div class="notif-derecha">
            <div class="notif-mensaje">
                <strong>AVISO IMPORTANTE:</strong><br>
                Deuda por servicio de agua potable. Pase por oficinas a cancelar.<br><br>
                <strong>EN CASO DE INCUMPLIMIENTO SE PROCEDERA AL CORTE SIN RECLAMO ALGUNO.</strong><br><br>
                <em>"Evite el corte y pago por reconexion"</em>
            </div>
            <div class="notif-footer">
                <strong>LA ADMINISTRACION</strong>
                Irupana, <?= $fechaActual ?>
            </div>
        </div>
    </div>

<?php
    $contador++;
    if ($contador % 4 === 0 || $index === $totalAcciones - 1):
        $espaciosRestantes = (4 - ($contador % 4)) % 4;
        if ($espaciosRestantes > 0 && $index === $totalAcciones - 1):
            for ($i = 0; $i < $espaciosRestantes; $i++):
?>
    <div class="notificacion notificacion-vacia"></div>
<?php
            endfor;
        endif;
?>
</div>
<?php
    endif;
endforeach;
?>

</body>
</html>
