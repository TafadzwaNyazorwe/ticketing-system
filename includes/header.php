<?php
/**
 * Shared HTML head + top bar.
 * Expects $pageTitle to optionally be set before including this file.
 */
require_login();
$me = current_user();
$pageTitle = $pageTitle ?? 'Dashboard';
$unread = unread_notification_count((int)$me['id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle) ?> · <?= e(get_setting('site_name', SITE_NAME)) ?></title>

<!-- Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

<!-- Icons -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<!-- Charts -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.4/chart.umd.min.js"></script>

<!-- App styles -->
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">

<!-- TODO: Add your favicon here, e.g. -->
<!-- <link rel="icon" href="<?= BASE_URL ?>/assets/images/logo/favicon.png"> -->
</head>
<body class="app-body">

<div class="app-shell">
    <?php include __DIR__ . '/sidebar.php'; ?>

    <div class="app-main">
        <header class="topbar">
            <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle navigation">
                <i class="fa-solid fa-bars"></i>
            </button>

            <h1 class="topbar-title"><?= e($pageTitle) ?></h1>

            <div class="topbar-actions">
                <form class="topbar-search" action="<?= BASE_URL ?>/tickets.php" method="get">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" name="q" placeholder="Search tickets, branches, agents…" value="<?= e($_GET['q'] ?? '') ?>">
                </form>

                <div class="topbar-icon-btn" id="notifBtn">
                    <i class="fa-regular fa-bell"></i>
                    <?php if ($unread > 0): ?><span class="notif-dot"><?= $unread > 9 ? '9+' : $unread ?></span><?php endif; ?>
                    <div class="notif-dropdown" id="notifDropdown">
                        <div class="notif-dropdown-head">
                            <span>Notifications</span>
                            <a href="<?= BASE_URL ?>/notifications.php">View all</a>
                        </div>
                        <div class="notif-dropdown-body" id="notifList">
                            <div class="notif-empty">Loading…</div>
                        </div>
                    </div>
                </div>

                <div class="topbar-profile" id="profileBtn">
                    <div class="avatar-circle">
                        <?php if (!empty($me['avatar'])): ?>
                            <img src="<?= BASE_URL . '/' . e($me['avatar']) ?>" alt="<?= e($me['full_name']) ?>">
                        <?php else: ?>
                            <?= e(strtoupper(substr($me['full_name'], 0, 1))) ?>
                        <?php endif; ?>
                    </div>
                    <div class="profile-meta">
                        <span class="profile-name"><?= e($me['full_name']) ?></span>
                        <span class="profile-role"><?= e(ucfirst($me['role'])) ?></span>
                    </div>
                    <i class="fa-solid fa-chevron-down"></i>

                    <div class="profile-dropdown" id="profileDropdown">
                        <a href="<?= BASE_URL ?>/profile.php"><i class="fa-regular fa-user"></i> My Profile</a>
                        <?php if (is_admin()): ?>
                        <a href="<?= BASE_URL ?>/settings.php"><i class="fa-solid fa-sliders"></i> Settings</a>
                        <?php endif; ?>
                        <a href="<?= BASE_URL ?>/logout.php" class="danger"><i class="fa-solid fa-right-from-bracket"></i> Log Out</a>
                    </div>
                </div>
            </div>
        </header>

        <?php $flashes = get_flashes(); if ($flashes): ?>
        <div class="flash-stack">
            <?php foreach ($flashes as $f): ?>
            <div class="flash flash-<?= e($f['type']) ?>">
                <i class="fa-solid <?= $f['type'] === 'success' ? 'fa-circle-check' : ($f['type'] === 'error' ? 'fa-circle-exclamation' : 'fa-circle-info') ?>"></i>
                <span><?= e($f['message']) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <main class="page-content">
