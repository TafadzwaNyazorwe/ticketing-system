<?php
$current = basename($_SERVER['SCRIPT_NAME']);
function nav_active(string $file, string $current): string
{
    return $file === $current ? ' active' : '';
}
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div class="brand-mark">
            <!-- TODO: Replace with your company logo image, e.g. -->
            <!-- <img src="<?= BASE_URL ?>/assets/images/logo/logo.png" alt="Logo"> -->
            <i class="fa-solid fa-headset"></i>
        </div>
        <div class="brand-text">
            <span class="brand-title"><?= e(get_setting('site_name', SITE_NAME)) ?></span>
            <span class="brand-sub">Support &amp; Ticketing</span>
        </div>
    </div>

    <nav class="sidebar-nav">
        <span class="nav-label">Main</span>
        <a href="<?= BASE_URL ?>/dashboard.php" class="nav-link<?= nav_active('dashboard.php', $current) ?>">
            <i class="fa-solid fa-gauge-high"></i> <span>Dashboard</span>
        </a>
        <a href="<?= BASE_URL ?>/tickets.php" class="nav-link<?= nav_active('tickets.php', $current) ?>">
            <i class="fa-solid fa-ticket"></i> <span>View Tickets</span>
        </a>
        <a href="<?= BASE_URL ?>/add_ticket.php" class="nav-link<?= nav_active('add_ticket.php', $current) ?>">
            <i class="fa-solid fa-circle-plus"></i> <span>Add Ticket</span>
        </a>
        <a href="<?= BASE_URL ?>/notifications.php" class="nav-link<?= nav_active('notifications.php', $current) ?>">
            <i class="fa-regular fa-bell"></i> <span>Notifications</span>
        </a>

        <?php if (is_admin()): ?>
        <span class="nav-label">Management</span>
        <a href="<?= BASE_URL ?>/employees.php" class="nav-link<?= nav_active('employees.php', $current) ?>">
            <i class="fa-solid fa-users"></i> <span>Employees</span>
        </a>
        <a href="<?= BASE_URL ?>/add_employee.php" class="nav-link<?= nav_active('add_employee.php', $current) ?>">
            <i class="fa-solid fa-user-plus"></i> <span>Add Employee</span>
        </a>
        <a href="<?= BASE_URL ?>/analytics.php" class="nav-link<?= nav_active('analytics.php', $current) ?>">
            <i class="fa-solid fa-chart-line"></i> <span>Analytics</span>
        </a>
        <a href="<?= BASE_URL ?>/settings.php" class="nav-link<?= nav_active('settings.php', $current) ?>">
            <i class="fa-solid fa-sliders"></i> <span>Settings</span>
        </a>
        <?php endif; ?>

        <span class="nav-label">Account</span>
        <a href="<?= BASE_URL ?>/profile.php" class="nav-link<?= nav_active('profile.php', $current) ?>">
            <i class="fa-regular fa-user"></i> <span>Profile</span>
        </a>
        <a href="<?= BASE_URL ?>/logout.php" class="nav-link nav-logout">
            <i class="fa-solid fa-right-from-bracket"></i> <span>Log Out</span>
        </a>
    </nav>

    <div class="sidebar-footer">
        <div class="sla-mini">
            <i class="fa-solid fa-shield-halved"></i>
            <span><?= e(COMPANY_NAME) ?></span>
        </div>
    </div>
</aside>
