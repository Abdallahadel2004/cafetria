<?php
/**
 * _sidebar.php — reusable admin sidebar
 *
 * Include at the top of every admin page AFTER session_start().
 * Set $activePage before including:
 *   $activePage = 'dashboard' | 'orders' | 'products' | 'users' | 'manual' | 'checks'
 */
$nav = [
    'dashboard' => ['icon' => 'dashboard',       'label' => 'Dashboard',    'href' => 'admin-dashboard.php'],
    'orders'    => ['icon' => 'receipt_long',    'label' => 'Orders',       'href' => 'admin-orders.php'],
    'products'  => ['icon' => 'restaurant_menu', 'label' => 'Products',     'href' => 'admin-products.php'],
    'users'     => ['icon' => 'group',           'label' => 'Users',        'href' => 'admin-users.php'],
    'manual'    => ['icon' => 'point_of_sale',   'label' => 'Manual Order', 'href' => 'admin-manual-order.php'],
    'checks'    => ['icon' => 'analytics',       'label' => 'Checks',       'href' => 'admin-checks.php'],
];

$userName     = htmlspecialchars($_SESSION['user_name'] ?? 'Admin');
$userRole     = htmlspecialchars($_SESSION['role']      ?? 'Administrator');
$userInitials = strtoupper(substr($_SESSION['user_name'] ?? 'AD', 0, 2));
?>
<aside class="sidebar">
    <div class="sidebar-brand">
        <h1>☕ Cafetria</h1>
        <p>Admin Control Panel</p>
    </div>

    <nav class="sidebar-nav">
        <span class="nav-label">Overview</span>
        <a href="admin-dashboard.php" class="nav-item <?= $activePage === 'dashboard' ? 'active' : '' ?>">
            <span class="material-symbols-outlined">dashboard</span>Dashboard
        </a>

        <span class="nav-label">Management</span>
        <?php foreach (['orders','products','users','manual','checks'] as $key): ?>
        <a href="<?= $nav[$key]['href'] ?>" class="nav-item <?= $activePage === $key ? 'active' : '' ?>">
            <span class="material-symbols-outlined"><?= $nav[$key]['icon'] ?></span>
            <?= $nav[$key]['label'] ?>
        </a>
        <?php endforeach; ?>
    </nav>

    <div class="sidebar-user">
        <div class="user-avatar"><?= $userInitials ?></div>
        <div class="user-info">
            <p><?= $userName ?></p>
            <span><?= $userRole ?></span>
        </div>
    </div>
</aside>
