<?php
/**
 * Centro de Ayuda - Guía de Usuario
 * Cooperativa de Agua Potable "Virgen de las Nieves"
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();

// Obtener datos reales para ejemplos
$ejemploSocio = fetchOne(
    "SELECT s.*, a.numero_accion, t.nombre as tarifa_nombre, z.nombre as zona_nombre
     FROM socios s
     JOIN acciones a ON a.socio_id = s.id
     JOIN tipos_tarifa t ON a.tipo_tarifa_id = t.id
     LEFT JOIN zonas z ON a.zona_id = z.id
     WHERE s.nombre NOT LIKE '%prueba%' AND s.nombre NOT LIKE '%test%'
     LIMIT 1"
);

$ejemploPago = fetchOne(
    "SELECT p.*, s.nombre as socio_nombre, a.numero_accion
     FROM pagos p
     JOIN socios s ON p.socio_id = s.id
     LEFT JOIN acciones a ON p.accion_id = a.id
     ORDER BY p.fecha_pago DESC
     LIMIT 1"
);

$ejemploGasto = fetchOne(
    "SELECT g.*, c.nombre as categoria_nombre
     FROM gastos g
     JOIN categorias_gasto c ON g.categoria_id = c.id
     ORDER BY g.fecha DESC
     LIMIT 1"
);

$ejemploFondo = fetchOne(
    "SELECT * FROM fondos_rendir ORDER BY id DESC LIMIT 1"
);

$stats = fetchOne(
    "SELECT 
        (SELECT COUNT(*) FROM socios) as total_socios,
        (SELECT COUNT(*) FROM acciones) as total_acciones,
        (SELECT COUNT(*) FROM pagos) as total_pagos,
        (SELECT COUNT(*) FROM gastos) as total_gastos"
);

$saldos = getSaldosCaja();

$pageTitle = 'Centro de Ayuda';
require_once __DIR__ . '/includes/header.php';
?>

<style>
.help-container {
    display: flex;
    gap: 20px;
}

.help-sidebar {
    width: 280px;
    flex-shrink: 0;
    position: sticky;
    top: 20px;
    height: fit-content;
}

.help-content {
    flex: 1;
    min-width: 0;
}

.help-nav {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    overflow: hidden;
}

.help-nav-header {
    background: linear-gradient(135deg, #079FEA 0%, #0056b3 100%);
    color: white;
    padding: 20px;
    text-align: center;
}

.help-nav-header i {
    font-size: 2.5rem;
    margin-bottom: 10px;
    display: block;
}

.help-nav-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.help-nav-list li a {
    display: flex;
    align-items: center;
    padding: 12px 18px;
    text-decoration: none;
    color: #333;
    border-bottom: 1px solid #f0f0f0;
    transition: all 0.2s;
    font-size: 0.95rem;
}

.help-nav-list li a:hover {
    background: #f8f9fa;
    padding-left: 22px;
}

.help-nav-list li a.active {
    background: #e8f4fc;
    color: #079FEA;
    border-left: 4px solid #079FEA;
    font-weight: 600;
}

.help-nav-list li a i {
    margin-right: 10px;
    width: 18px;
    text-align: center;
}

.help-section {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    padding: 30px;
    margin-bottom: 20px;
}

.help-section h2 {
    color: #079FEA;
    border-bottom: 2px solid #079FEA;
    padding-bottom: 10px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.help-section h3 {
    color: #333;
    margin-top: 25px;
    margin-bottom: 15px;
}

.help-step {
    display: flex;
    align-items: flex-start;
    margin-bottom: 20px;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 8px;
    border-left: 4px solid #079FEA;
}

.help-step-number {
    background: #079FEA;
    color: white;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    margin-right: 15px;
    flex-shrink: 0;
}

.help-step-content {
    flex: 1;
}

.help-step-content h4 {
    margin: 0 0 8px 0;
    color: #333;
}

.help-example {
    background: #e8f5e9;
    border: 1px solid #c8e6c9;
    border-radius: 8px;
    padding: 15px;
    margin: 15px 0;
}

.help-example-header {
    font-weight: 600;
    color: #2e7d32;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.help-tip {
    background: #fff3cd;
    border: 1px solid #ffc107;
    border-radius: 8px;
    padding: 15px;
    margin: 15px 0;
}

.help-tip-header {
    font-weight: 600;
    color: #856404;
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.help-warning {
    background: #f8d7da;
    border: 1px solid #f5c6cb;
    border-radius: 8px;
    padding: 15px;
    margin: 15px 0;
}

.help-warning-header {
    font-weight: 600;
    color: #721c24;
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.help-shortcut {
    display: inline-flex;
    align-items: center;
    background: #e9ecef;
    padding: 8px 15px;
    border-radius: 20px;
    margin: 5px;
    text-decoration: none;
    color: #333;
    transition: all 0.2s;
}

.help-shortcut:hover {
    background: #079FEA;
    color: white;
}

.help-shortcut i {
    margin-right: 8px;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 15px;
    margin: 20px 0;
}

.stat-card {
    background: linear-gradient(135deg, #079FEA 0%, #0056b3 100%);
    color: white;
    padding: 20px;
    border-radius: 10px;
    text-align: center;
}

.stat-card .number {
    font-size: 2rem;
    font-weight: bold;
}

.stat-card .label {
    opacity: 0.9;
    font-size: 0.9rem;
}

@media (max-width: 992px) {
    .help-container {
        flex-direction: column;
    }
    .help-sidebar {
        width: 100%;
        position: static;
    }
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

.btn-back-top {
    position: fixed;
    bottom: 30px;
    right: 30px;
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: #079FEA;
    color: white;
    border: none;
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    transition: all 0.3s;
    z-index: 100;
}

.btn-back-top:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 20px rgba(0,0,0,0.3);
}
</style>

<div class="page-header">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/dashboard.php">Inicio</a></li>
            <li class="breadcrumb-item active">Centro de Ayuda</li>
        </ol>
    </nav>
    <h1 class="page-title"><i class="bi bi-question-circle me-2"></i>Como usar esta aplicacion</h1>
    <p class="page-subtitle">Guia completa para administrar la cooperativa</p>
</div>

<div class="help-container">
    <!-- Sidebar de Navegacion -->
    <div class="help-sidebar">
        <div class="help-nav">
            <div class="help-nav-header">
                <i class="bi bi-book"></i>
                <strong>Guia de Usuario</strong>
            </div>
            <ul class="help-nav-list">
                <li><a href="#inicio" class="active"><i class="bi bi-house"></i> Introduccion</a></li>
                <li><a href="#socios"><i class="bi bi-people"></i> Socios y Acciones</a></li>
                <li><a href="#consumos"><i class="bi bi-droplet"></i> Cobro de Consumos</a></li>
                <li><a href="#ingresos"><i class="bi bi-cash-stack"></i> Otros Ingresos</a></li>
                <li><a href="#gastos"><i class="bi bi-cart-dash"></i> Registro de Gastos</a></li>
                <li><a href="#fondos"><i class="bi bi-wallet2"></i> Fondos a Rendir</a></li>
                <li><a href="#caja"><i class="bi bi-safe"></i> Caja</a></li>
                <li><a href="#notificaciones"><i class="bi bi-envelope"></i> Notificaciones</a></li>
                <li><a href="#reportes"><i class="bi bi-graph-up"></i> Reportes</a></li>
                <li><a href="#configuracion"><i class="bi bi-gear"></i> Configuracion</a></li>
                <li><a href="#backup"><i class="bi bi-cloud-download"></i> Respaldo</a></li>
            </ul>
        </div>
    </div>

    <!-- Contenido Principal -->
    <div class="help-content">
        
        <!-- INTRODUCCION -->
        <div class="help-section" id="inicio">
            <h2><i class="bi bi-house"></i> Bienvenido al Sistema de Gestion</h2>
            
            <p>Este sistema fue creado para facilitar la administracion de la <strong>Cooperativa de Agua Potable "Virgen de las Nieves"</strong>. Aqui podras gestionar socios, cobrar consumos de agua, registrar gastos y generar reportes.</p>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="number"><?= number_format($stats['total_socios']) ?></div>
                    <div class="label">Socios</div>
                </div>
                <div class="stat-card">
                    <div class="number"><?= number_format($stats['total_acciones']) ?></div>
                    <div class="label">Acciones</div>
                </div>
                <div class="stat-card">
                    <div class="number"><?= number_format($stats['total_pagos']) ?></div>
                    <div class="label">Pagos</div>
                </div>
                <div class="stat-card">
                    <div class="number"><?= number_format($stats['total_gastos']) ?></div>
                    <div class="label">Gastos</div>
                </div>
            </div>
            
            <h3>Accesos Rapidos</h3>
            <div>
                <a href="<?= BASE_URL ?>/modules/consumos/" class="help-shortcut"><i class="bi bi-droplet"></i> Cobrar Consumo</a>
                <a href="<?= BASE_URL ?>/modules/socios/" class="help-shortcut"><i class="bi bi-people"></i> Ver Socios</a>
                <a href="<?= BASE_URL ?>/modules/gastos/crear.php" class="help-shortcut"><i class="bi bi-plus-circle"></i> Nuevo Gasto</a>
                <a href="<?= BASE_URL ?>/modules/fondos-rendir/" class="help-shortcut"><i class="bi bi-wallet2"></i> Fondos a Rendir</a>
                <a href="<?= BASE_URL ?>/modules/notificaciones/" class="help-shortcut"><i class="bi bi-envelope"></i> Notificaciones</a>
                <a href="<?= BASE_URL ?>/modules/reportes/" class="help-shortcut"><i class="bi bi-graph-up"></i> Reportes</a>
            </div>
        </div>

        <!-- SOCIOS Y ACCIONES -->
        <div class="help-section" id="socios">
            <h2><i class="bi bi-people"></i> Socios y Acciones</h2>
            
            <p>Los <strong>socios</strong> son las personas que pertenecen a la cooperativa. Cada socio puede tener una o mas <strong>acciones</strong> (conexiones de agua). La accion es lo que se cobra mensualmente.</p>
            
            <h3>Buscar un Socio</h3>
            <div class="help-step">
                <div class="help-step-number">1</div>
                <div class="help-step-content">
                    <h4>Ir a la seccion de Socios</h4>
                    <p>Desde el menu lateral, haz clic en <strong>"Socios"</strong> o desde Inicio en <strong>"Ver Socios"</strong>.</p>
                </div>
            </div>
            <div class="help-step">
                <div class="help-step-number">2</div>
                <div class="help-step-content">
                    <h4>Usar el buscador</h4>
                    <p>Escribe el nombre, numero de socio o numero de accion en la barra de busqueda. Tambien puedes filtrar por zona o estado.</p>
                </div>
            </div>
            
            <?php if ($ejemploSocio): ?>
            <div class="help-example">
                <div class="help-example-header"><i class="bi bi-check-circle"></i> Ejemplo Real del Sistema</div>
                <p>El socio <strong><?= htmlspecialchars($ejemploSocio['nombre']) ?></strong> (N° <?= htmlspecialchars($ejemploSocio['numero_socio']) ?>) tiene la accion <strong><?= htmlspecialchars($ejemploSocio['numero_accion']) ?></strong> con tarifa <strong><?= htmlspecialchars($ejemploSocio['tarifa_nombre']) ?></strong><?= $ejemploSocio['zona_nombre'] ? ' en la ' . htmlspecialchars($ejemploSocio['zona_nombre']) : '' ?>.</p>
            </div>
            <?php endif; ?>
            
            <h3>Registrar Nuevo Socio</h3>
            <div class="help-step">
                <div class="help-step-number">1</div>
                <div class="help-step-content">
                    <h4>Clic en "Nuevo Socio"</h4>
                    <p>En la pagina de Socios, haz clic en el boton verde <strong>"+ Nuevo Socio"</strong>.</p>
                </div>
            </div>
            <div class="help-step">
                <div class="help-step-number">2</div>
                <div class="help-step-content">
                    <h4>Llenar los datos</h4>
                    <p>Completa el numero de socio, nombre completo, celular y direccion. El sistema sugiere automaticamente el siguiente numero disponible.</p>
                </div>
            </div>
            <div class="help-step">
                <div class="help-step-number">3</div>
                <div class="help-step-content">
                    <h4>Crear la primera accion</h4>
                    <p>Marca la casilla "Crear primera conexion de agua" y selecciona el <strong>tipo de tarifa</strong> (ej: Domiciliaria, Comercial).</p>
                </div>
            </div>
            
            <div class="help-tip">
                <div class="help-tip-header"><i class="bi bi-lightbulb"></i> Consejo</div>
                <p>Si un socio tiene varias conexiones (por ejemplo, una casa y un negocio), puedes agregar mas acciones desde su perfil haciendo clic en el icono de lapiz junto a sus acciones.</p>
            </div>
            
            <h3>Editar una Accion</h3>
            <p>Desde el perfil del socio, haz clic en el icono <i class="bi bi-pencil"></i> junto a la accion para modificar:</p>
            <ul>
                <li><strong>Tarifa:</strong> Al cambiar la tarifa, los meses pendientes se actualizan automaticamente al nuevo monto.</li>
                <li><strong>Zona:</strong> Para organizar por sectores geograficos.</li>
                <li><strong>Estado:</strong> Activo, Cortado, Sin Instalacion o Baja.</li>
                <li><strong>Observacion:</strong> Notas importantes sobre la conexion.</li>
            </ul>
            
            <h3>Cesion de Accion (Cambio de Titular)</h3>
            <p>Cuando un socio vende o transfiere su accion a otra persona:</p>
            <div class="help-step">
                <div class="help-step-number">1</div>
                <div class="help-step-content">
                    <h4>Editar la accion</h4>
                    <p>Desde el perfil del socio, haz clic en el lapiz de la accion.</p>
                </div>
            </div>
            <div class="help-step">
                <div class="help-step-number">2</div>
                <div class="help-step-content">
                    <h4>Ir a "Cesion de Accion"</h4>
                    <p>En la parte inferior de la pagina, ingresa el nombre del nuevo titular y el motivo (venta, herencia, etc.).</p>
                </div>
            </div>
            <p>El sistema guarda un historial de todas las cesiones para futuras consultas.</p>
        </div>

        <!-- COBRO DE CONSUMOS -->
        <div class="help-section" id="consumos">
            <h2><i class="bi bi-droplet"></i> Cobro de Consumos de Agua</h2>
            
            <p>El cobro de consumos es la actividad principal del sistema. Cada accion genera un monto mensual segun su tarifa.</p>
            
            <h3>Como Cobrar</h3>
            <div class="help-step">
                <div class="help-step-number">1</div>
                <div class="help-step-content">
                    <h4>Buscar la accion</h4>
                    <p>Ve a <strong>"Cobrar Consumo"</strong> desde el menu o Inicio. Busca por numero de accion, nombre del socio o numero de socio.</p>
                </div>
            </div>
            <div class="help-step">
                <div class="help-step-number">2</div>
                <div class="help-step-content">
                    <h4>Seleccionar los meses a cobrar</h4>
                    <p>El sistema muestra los meses pendientes en <span class="badge bg-danger">rojo</span>. Marca los meses que el socio va a pagar.</p>
                </div>
            </div>
            <div class="help-step">
                <div class="help-step-number">3</div>
                <div class="help-step-content">
                    <h4>Elegir metodo de pago</h4>
                    <p>Selecciona si el pago es en <strong>Efectivo</strong> o por <strong>QR/Transferencia</strong>.</p>
                </div>
            </div>
            <div class="help-step">
                <div class="help-step-number">4</div>
                <div class="help-step-content">
                    <h4>Confirmar y generar recibo</h4>
                    <p>Haz clic en "Procesar Pago". El sistema genera automaticamente un recibo que puedes imprimir.</p>
                </div>
            </div>
            
            <?php if ($ejemploPago): ?>
            <div class="help-example">
                <div class="help-example-header"><i class="bi bi-check-circle"></i> Ultimo Pago Registrado</div>
                <p>Recibo <strong>#<?= htmlspecialchars($ejemploPago['numero_recibo']) ?></strong> - <?= htmlspecialchars($ejemploPago['socio_nombre']) ?> pago <strong><?= formatMoney($ejemploPago['monto_total']) ?></strong> por la accion <?= htmlspecialchars($ejemploPago['numero_accion'] ?? 'N/A') ?> el <?= formatDate($ejemploPago['fecha_pago'], 'd/m/Y') ?>.</p>
            </div>
            <?php endif; ?>
            
            <div class="help-tip">
                <div class="help-tip-header"><i class="bi bi-lightbulb"></i> Consejo</div>
                <p>Puedes cobrar varios meses a la vez. El sistema calcula automaticamente el total y genera un solo recibo.</p>
            </div>
            
            <div class="help-warning">
                <div class="help-warning-header"><i class="bi bi-exclamation-triangle"></i> Importante</div>
                <p>Si necesitas anular un pago por error, ve a <strong>Historial de Ingresos</strong> y usa el boton de anular. Esto devuelve los meses a estado pendiente.</p>
            </div>
        </div>

        <!-- OTROS INGRESOS -->
        <div class="help-section" id="ingresos">
            <h2><i class="bi bi-cash-stack"></i> Otros Ingresos</h2>
            
            <p>Ademas del consumo de agua, la cooperativa puede tener otros ingresos como:</p>
            <ul>
                <li><strong>Reconexiones:</strong> Cuando se reconecta un servicio cortado.</li>
                <li><strong>Derechos de conexion:</strong> Para nuevas instalaciones.</li>
                <li><strong>Multas:</strong> Por infracciones al reglamento.</li>
                <li><strong>Venta de materiales:</strong> Accesorios, medidores, etc.</li>
            </ul>
            
            <h3>Registrar Otro Ingreso</h3>
            <p>Al momento de cobrar un consumo, puedes agregar servicios adicionales en la seccion <strong>"Agregar Servicios Adicionales"</strong>. Selecciona el tipo de servicio, el monto y se sumara al recibo.</p>
            
            <h3>Ver Historial de Ingresos</h3>
            <p>En <strong>Historial de Ingresos</strong> puedes ver todos los pagos recibidos, filtrar por fecha, reimprimir recibos o anular pagos erroneos.</p>
        </div>

        <!-- GASTOS -->
        <div class="help-section" id="gastos">
            <h2><i class="bi bi-cart-dash"></i> Registro de Gastos</h2>
            
            <p>Todos los gastos de la cooperativa deben registrarse para llevar un control adecuado y poder rendir cuentas en las asambleas.</p>
            
            <h3>Registrar un Gasto</h3>
            <div class="help-step">
                <div class="help-step-number">1</div>
                <div class="help-step-content">
                    <h4>Ir a Gastos > Nuevo Gasto</h4>
                    <p>Desde el menu lateral o desde Inicio en "Historial de Gastos", haz clic en <strong>"+ Nuevo Gasto"</strong>.</p>
                </div>
            </div>
            <div class="help-step">
                <div class="help-step-number">2</div>
                <div class="help-step-content">
                    <h4>Seleccionar categoria</h4>
                    <p>Elige la categoria correspondiente: Materiales, Transporte, Servicios, Administrativo, etc.</p>
                </div>
            </div>
            <div class="help-step">
                <div class="help-step-number">3</div>
                <div class="help-step-content">
                    <h4>Completar los datos</h4>
                    <p>Ingresa el concepto (descripcion del gasto), el monto, fecha, numero de factura/recibo del proveedor (si tiene) y el metodo de pago.</p>
                </div>
            </div>
            
            <?php if ($ejemploGasto): ?>
            <div class="help-example">
                <div class="help-example-header"><i class="bi bi-check-circle"></i> Ejemplo de Gasto</div>
                <p>Categoria: <strong><?= htmlspecialchars($ejemploGasto['categoria_nombre']) ?></strong> - "<?= htmlspecialchars($ejemploGasto['concepto']) ?>" por <strong><?= formatMoney($ejemploGasto['monto']) ?></strong> registrado el <?= formatDate($ejemploGasto['fecha'], 'd/m/Y') ?>.</p>
            </div>
            <?php endif; ?>
            
            <div class="help-tip">
                <div class="help-tip-header"><i class="bi bi-lightbulb"></i> Consejo</div>
                <p>Siempre guarda los comprobantes fisicos. El numero de recibo del proveedor te ayudara a encontrar el documento si lo necesitas.</p>
            </div>
        </div>

        <!-- FONDOS A RENDIR -->
        <div class="help-section" id="fondos">
            <h2><i class="bi bi-wallet2"></i> Fondos a Rendir</h2>
            
            <p>Los <strong>Fondos a Rendir</strong> son adelantos de dinero que se entregan a una persona para realizar compras o pagos. Luego esa persona debe <strong>rendir</strong> (justificar) como gasto el dinero utilizado.</p>
            
            <h3>Cuando usar Fondos a Rendir?</h3>
            <ul>
                <li>Cuando el plomero necesita dinero para comprar materiales.</li>
                <li>Cuando alguien viaja a comprar repuestos.</li>
                <li>Para pagos de servicios que requieren efectivo.</li>
            </ul>
            
            <h3>Entregar un Fondo</h3>
            <div class="help-step">
                <div class="help-step-number">1</div>
                <div class="help-step-content">
                    <h4>Ir a Fondos a Rendir > Entregar Fondo</h4>
                    <p>Haz clic en el boton <strong>"Entregar Fondo"</strong>.</p>
                </div>
            </div>
            <div class="help-step">
                <div class="help-step-number">2</div>
                <div class="help-step-content">
                    <h4>Completar los datos</h4>
                    <p>Ingresa el nombre del <strong>beneficiario</strong> (quien recibe el dinero), el <strong>concepto</strong> (para que es) y el <strong>monto</strong>.</p>
                </div>
            </div>
            <div class="help-step">
                <div class="help-step-number">3</div>
                <div class="help-step-content">
                    <h4>El fondo queda "Pendiente"</h4>
                    <p>El dinero sale de caja y el fondo aparece en estado <span class="badge bg-warning text-dark">Pendiente</span>.</p>
                </div>
            </div>
            
            <?php if ($ejemploFondo): ?>
            <div class="help-example">
                <div class="help-example-header"><i class="bi bi-check-circle"></i> Ejemplo Real</div>
                <p>Se entrego <strong><?= formatMoney($ejemploFondo['monto']) ?></strong> a <strong><?= htmlspecialchars($ejemploFondo['beneficiario']) ?></strong> para "<?= htmlspecialchars($ejemploFondo['concepto']) ?>" el <?= formatDate($ejemploFondo['fecha_entrega']) ?>. Estado: <span class="badge bg-<?= $ejemploFondo['estado'] === 'rendido' ? 'success' : 'warning' ?>"><?= ucfirst($ejemploFondo['estado']) ?></span></p>
            </div>
            <?php endif; ?>
            
            <h3>Rendir un Fondo (Justificar el gasto)</h3>
            <p>Cuando la persona trae los comprobantes de lo que compro:</p>
            <div class="help-step">
                <div class="help-step-number">1</div>
                <div class="help-step-content">
                    <h4>Clic en "Rendir"</h4>
                    <p>En la lista de fondos pendientes, haz clic en el boton <strong>"Rendir"</strong> del fondo correspondiente.</p>
                </div>
            </div>
            <div class="help-step">
                <div class="help-step-number">2</div>
                <div class="help-step-content">
                    <h4>Registrar los gastos</h4>
                    <p>Agrega cada gasto con su categoria, concepto y monto. Puedes agregar varios gastos para un mismo fondo.</p>
                </div>
            </div>
            <div class="help-step">
                <div class="help-step-number">3</div>
                <div class="help-step-content">
                    <h4>Devolucion de sobrante</h4>
                    <p>Si sobro dinero, el sistema lo registra automaticamente como devolucion a caja.</p>
                </div>
            </div>
            
            <div class="help-tip">
                <div class="help-tip-header"><i class="bi bi-lightbulb"></i> Consejo</div>
                <p>Un fondo puede quedar en estado <span class="badge bg-info">Parcial</span> si se rindio solo una parte. Puedes seguir agregando gastos hasta completar el monto.</p>
            </div>
        </div>

        <!-- CAJA -->
        <div class="help-section" id="caja">
            <h2><i class="bi bi-safe"></i> Control de Caja</h2>
            
            <p>La seccion de Caja muestra todos los movimientos de dinero: ingresos y egresos, separados por metodo de pago (Efectivo y QR).</p>
            
            <h3>Saldos Actuales</h3>
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="card border-success">
                        <div class="card-body text-center">
                            <i class="bi bi-cash-stack text-success fs-2"></i>
                            <div class="fs-4 fw-bold text-success"><?= formatMoney($saldos['efectivo']) ?></div>
                            <small>Efectivo</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-primary">
                        <div class="card-body text-center">
                            <i class="bi bi-qr-code text-primary fs-2"></i>
                            <div class="fs-4 fw-bold text-primary"><?= formatMoney($saldos['qr']) ?></div>
                            <small>QR / Banco</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-info">
                        <div class="card-body text-center">
                            <i class="bi bi-wallet2 text-info fs-2"></i>
                            <div class="fs-4 fw-bold text-info"><?= formatMoney($saldos['efectivo'] + $saldos['qr']) ?></div>
                            <small>Total</small>
                        </div>
                    </div>
                </div>
            </div>
            
            <h3>Registrar Entrega de Dinero</h3>
            <p>Cuando se entrega dinero al tesorero, presidente o se deposita al banco:</p>
            <div class="help-step">
                <div class="help-step-number">1</div>
                <div class="help-step-content">
                    <h4>Clic en "Registrar Entrega"</h4>
                    <p>En la pagina de Caja, haz clic en el boton azul <strong>"Registrar Entrega"</strong>.</p>
                </div>
            </div>
            <div class="help-step">
                <div class="help-step-number">2</div>
                <div class="help-step-content">
                    <h4>Completar los datos</h4>
                    <ul>
                        <li><strong>Entregado a:</strong> Nombre de quien recibe (ej: "Tesorero Juan Perez", "Banco Union")</li>
                        <li><strong>Numero de documento:</strong> Recibo o comprobante (opcional)</li>
                        <li><strong>Monto:</strong> Cantidad entregada</li>
                        <li><strong>Origen:</strong> Si sale de Efectivo o de QR</li>
                    </ul>
                </div>
            </div>
            <div class="help-step">
                <div class="help-step-number">3</div>
                <div class="help-step-content">
                    <h4>El saldo se actualiza</h4>
                    <p>El monto se descuenta del saldo correspondiente (efectivo o QR) y queda registrado en los movimientos.</p>
                </div>
            </div>
            
            <div class="help-tip">
                <div class="help-tip-header"><i class="bi bi-lightbulb"></i> Para que sirve?</div>
                <p>Esta funcion es util cuando el encargado de cobros entrega la recaudacion al tesorero. Asi queda registro de cuanto dinero se entrego y a quien.</p>
            </div>
            
            <h3>Ver Movimientos</h3>
            <p>En la tabla de movimientos puedes ver:</p>
            <ul>
                <li>Fecha y hora de cada transaccion</li>
                <li>Concepto (pago de socio, gasto, entrega, etc.)</li>
                <li>Si es ingreso o egreso</li>
                <li>Metodo (efectivo o QR)</li>
                <li>Saldo acumulado despues de cada movimiento</li>
            </ul>
        </div>

        <!-- NOTIFICACIONES -->
        <div class="help-section" id="notificaciones">
            <h2><i class="bi bi-envelope"></i> Notificaciones de Deuda</h2>
            
            <p>Las notificaciones son avisos impresos que se entregan a los socios con deudas pendientes, antes de proceder al corte del servicio.</p>
            
            <h3>Generar Notificaciones</h3>
            <div class="help-step">
                <div class="help-step-number">1</div>
                <div class="help-step-content">
                    <h4>Filtrar deudores</h4>
                    <p>En la seccion de Notificaciones, puedes filtrar por zona, cantidad minima de meses de deuda, etc.</p>
                </div>
            </div>
            <div class="help-step">
                <div class="help-step-number">2</div>
                <div class="help-step-content">
                    <h4>Seleccionar acciones</h4>
                    <p>Marca las acciones a las que quieres enviar notificacion. Puedes usar "Seleccionar todos" para marcar todas las visibles.</p>
                </div>
            </div>
            <div class="help-step">
                <div class="help-step-number">3</div>
                <div class="help-step-content">
                    <h4>Imprimir</h4>
                    <p>Haz clic en "Generar Notificaciones". Se abre una pagina lista para imprimir con 4 notificaciones por hoja carta.</p>
                </div>
            </div>
            
            <div class="help-tip">
                <div class="help-tip-header"><i class="bi bi-lightbulb"></i> Consejo</div>
                <p>Las notificaciones estan disenadas con letra grande para que sean legibles por personas mayores. Se imprimen 4 por pagina en formato de tiras horizontales.</p>
            </div>
        </div>

        <!-- REPORTES -->
        <div class="help-section" id="reportes">
            <h2><i class="bi bi-graph-up"></i> Reportes</h2>
            
            <p>Los reportes te permiten analizar la situacion financiera de la cooperativa y preparar informes para las asambleas.</p>
            
            <h3>Reportes Disponibles</h3>
            <ul>
                <li><strong>Estado de Resultados:</strong> Muestra ingresos vs gastos por periodo. Ideal para ver si hay ganancia o perdida.</li>
                <li><strong>Deudores:</strong> Lista de todas las acciones con meses pendientes, ordenadas por monto de deuda.</li>
                <li><strong>Recaudacion Mensual:</strong> Comparativo de cuanto se cobro cada mes.</li>
                <li><strong>Mayor de Gastos:</strong> Libro contable con todos los gastos detallados y saldos acumulados. Perfecto para rendicion de cuentas.</li>
                <li><strong>Gastos Detallado:</strong> Gastos agrupados por categoria con porcentajes.</li>
                <li><strong>Socios y Acciones:</strong> Padron completo de socios con sus conexiones.</li>
            </ul>
            
            <div class="help-tip">
                <div class="help-tip-header"><i class="bi bi-lightbulb"></i> Consejo</div>
                <p>Todos los reportes tienen opcion de imprimir. Usa el boton "Imprimir" para generar documentos listos para presentar en asamblea.</p>
            </div>
        </div>

        <!-- CONFIGURACION -->
        <div class="help-section" id="configuracion">
            <h2><i class="bi bi-gear"></i> Configuracion</h2>
            
            <p>En la seccion de Configuracion puedes administrar:</p>
            <ul>
                <li><strong>Datos de la Cooperativa:</strong> Nombre, direccion, telefono (aparecen en los recibos).</li>
                <li><strong>Tarifas:</strong> Crear o modificar tipos de tarifa (Domiciliaria, Comercial, etc.) con sus montos.</li>
                <li><strong>Zonas:</strong> Agregar o editar las zonas/sectores de cobertura.</li>
                <li><strong>Categorias de Gasto:</strong> Tipos de gastos para clasificar egresos.</li>
                <li><strong>Tipos de Servicio:</strong> Servicios adicionales como reconexion, multas, etc.</li>
                <li><strong>Usuarios:</strong> Crear cuentas de acceso para otros operadores.</li>
            </ul>
            
            <div class="help-warning">
                <div class="help-warning-header"><i class="bi bi-exclamation-triangle"></i> Precaucion</div>
                <p>Ten cuidado al modificar tarifas existentes. Al cambiar el monto de una tarifa tipo (ej: Domiciliaria de 10 a 12 Bs), debes ir a cada accion que use esa tarifa y guardar para que se actualicen los meses pendientes.</p>
            </div>
        </div>

        <!-- BACKUP -->
        <div class="help-section" id="backup">
            <h2><i class="bi bi-cloud-download"></i> Respaldo (Backup)</h2>
            
            <p>El respaldo es una copia de seguridad de toda la informacion del sistema. Es <strong>MUY IMPORTANTE</strong> hacer respaldos periodicamente.</p>
            
            <h3>Generar Respaldo</h3>
            <div class="help-step">
                <div class="help-step-number">1</div>
                <div class="help-step-content">
                    <h4>Ir a Configuracion > Backup</h4>
                    <p>Desde el menu lateral, ve a <strong>Configuracion</strong> y luego <strong>Backup</strong>.</p>
                </div>
            </div>
            <div class="help-step">
                <div class="help-step-number">2</div>
                <div class="help-step-content">
                    <h4>Clic en "Descargar Backup"</h4>
                    <p>Se genera un archivo <code>.sql</code> con todos los datos y se descarga a tu computadora.</p>
                </div>
            </div>
            <div class="help-step">
                <div class="help-step-number">3</div>
                <div class="help-step-content">
                    <h4>Guardar en lugar seguro</h4>
                    <p>Guarda el archivo en una memoria USB, disco duro externo, Google Drive, etc. <strong>NO solo en la computadora.</strong></p>
                </div>
            </div>
            
            <div class="help-warning">
                <div class="help-warning-header"><i class="bi bi-exclamation-triangle"></i> Muy Importante</div>
                <p>Haz backup <strong>al menos una vez por semana</strong>, o despues de registrar muchos pagos. Si la computadora falla o el servidor tiene problemas, el backup es la unica forma de recuperar la informacion.</p>
            </div>
            
            <h3>Restaurar Respaldo</h3>
            <p>Si necesitas recuperar datos desde un backup:</p>
            <ol>
                <li>Ve a Backup y sube el archivo <code>.sql</code></li>
                <li>El sistema <strong>reemplazara TODOS</strong> los datos actuales con los del backup</li>
                <li>Esto es util si hubo un error grave o si se perdieron datos</li>
            </ol>
            
            <div class="help-warning">
                <div class="help-warning-header"><i class="bi bi-exclamation-triangle"></i> Advertencia</div>
                <p>Restaurar un backup borra los datos actuales. Solo hazlo si estas seguro y si el backup es reciente.</p>
            </div>
        </div>

        <!-- CONTACTO -->
        <div class="help-section">
            <h2><i class="bi bi-headset"></i> Necesitas mas ayuda?</h2>
            <p>Si tienes dudas adicionales o encuentras algun problema con el sistema, contacta al administrador.</p>
            <div class="text-center mt-4">
                <a href="<?= BASE_URL ?>/dashboard.php" class="btn btn-primary btn-lg">
                    <i class="bi bi-house me-2"></i> Volver al Inicio
                </a>
            </div>
        </div>
    </div>
</div>

<button class="btn-back-top" onclick="window.scrollTo({top: 0, behavior: 'smooth'})" title="Volver arriba">
    <i class="bi bi-chevron-up"></i>
</button>

<script>
// Navegacion suave y resaltado de seccion activa
document.querySelectorAll('.help-nav-list a').forEach(link => {
    link.addEventListener('click', function(e) {
        e.preventDefault();
        const targetId = this.getAttribute('href').substring(1);
        const targetElement = document.getElementById(targetId);
        
        if (targetElement) {
            targetElement.scrollIntoView({ behavior: 'smooth', block: 'start' });
            
            // Actualizar enlace activo
            document.querySelectorAll('.help-nav-list a').forEach(l => l.classList.remove('active'));
            this.classList.add('active');
        }
    });
});

// Detectar seccion visible al hacer scroll
window.addEventListener('scroll', function() {
    const sections = document.querySelectorAll('.help-section[id]');
    let currentSection = 'inicio';
    
    sections.forEach(section => {
        const sectionTop = section.offsetTop - 100;
        if (window.scrollY >= sectionTop) {
            currentSection = section.getAttribute('id');
        }
    });
    
    document.querySelectorAll('.help-nav-list a').forEach(link => {
        link.classList.remove('active');
        if (link.getAttribute('href') === '#' + currentSection) {
            link.classList.add('active');
        }
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
