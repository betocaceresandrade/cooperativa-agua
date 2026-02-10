<?php
/**
 * Sidebar del Sistema
 */
$menuItems = [
    ['url' => '/dashboard.php', 'icon' => 'house', 'label' => 'Inicio', 'page' => 'dashboard'],
    ['url' => '/modules/socios/', 'icon' => 'people', 'label' => 'Socios', 'page' => 'socios'],
    ['url' => '/modules/consumos/', 'icon' => 'droplet', 'label' => 'Consumo', 'page' => 'consumos'],
    ['url' => '/modules/cobros/', 'icon' => 'cash-coin', 'label' => 'Otros Ingresos', 'page' => 'cobros'],
    ['url' => '/modules/ingresos/', 'icon' => 'receipt', 'label' => 'Historial Ingresos', 'page' => 'ingresos'],
    ['url' => '/modules/notificaciones/', 'icon' => 'envelope-paper', 'label' => 'Notificaciones', 'page' => 'notificaciones'],
    ['url' => '/modules/gastos/', 'icon' => 'cart-dash', 'label' => 'Gastos', 'page' => 'gastos'],
    ['url' => '/modules/fondos-rendir/', 'icon' => 'box-arrow-up-right', 'label' => 'Salidas de Caja', 'page' => 'fondos-rendir'],
    ['url' => '/modules/caja/', 'icon' => 'safe2', 'label' => 'Caja', 'page' => 'caja'],
    ['url' => '/modules/caja/tesoreria.php', 'icon' => 'clipboard-data', 'label' => 'Tesorería', 'page' => 'tesoreria'],
    ['url' => '/modules/reportes/', 'icon' => 'file-earmark-bar-graph', 'label' => 'Reportes', 'page' => 'reportes'],
    ['url' => '/modules/configuracion/', 'icon' => 'gear', 'label' => 'Configuración', 'page' => 'configuracion'],
    ['url' => '/modules/backup/', 'icon' => 'database', 'label' => 'Respaldo', 'page' => 'backup'],
];

// Detectar sección activa
$currentPath = $_SERVER['REQUEST_URI'];
?>
<aside class="sidebar">
    <div class="sidebar-header">
        <a href="<?= BASE_URL ?>/dashboard.php" class="d-flex align-items-center text-decoration-none">
            <img src="<?= defined("LOGO_BASE64") ? LOGO_BASE64 : BASE_URL."/assets/img/logo.png" ?>" alt="Logo" class="sidebar-logo" onerror="this.style.display='none'">
            <div class="sidebar-brand">
                <span class="brand-text">Virgen de las Nieves</span>
                <small class="brand-subtitle">Cooperativa de Agua</small>
            </div>
        </a>
    </div>

    <nav class="sidebar-nav">
        <ul class="nav flex-column">
            <?php foreach ($menuItems as $item): ?>
            <?php
                $isActive = strpos($currentPath, $item['url']) !== false ||
                           (isset($currentPage) && $currentPage === $item['page']);
            ?>
            <li class="nav-item">
                <a href="<?= BASE_URL . $item['url'] ?>" class="nav-link <?= $isActive ? 'active' : '' ?>">
                    <i class="bi bi-<?= $item['icon'] ?>"></i>
                    <span><?= $item['label'] ?></span>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>
    </nav>

    <div class="sidebar-footer">
        <small class="text-muted">v<?= APP_VERSION ?> por <a href="https://boliviaimpuestos.com" target="_blank" style="color:#079FEA;text-decoration:none;">boliviaimpuestos.com</a></small>
    </div>
</aside>
