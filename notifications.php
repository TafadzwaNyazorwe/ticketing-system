<?php
$pageTitle = 'Notifications';
require_once __DIR__ . '/config/config.php';
require_login();
$me = current_user();

if (isset($_GET['mark_all'])) {
    db()->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?")->execute([$me['id']]);
    redirect('notifications.php');
}

$stmt = db()->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 100");
$stmt->execute([$me['id']]);
$notifications = $stmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<section class="card">
    <div class="card-head">
        <h3><i class="fa-regular fa-bell"></i> All Notifications</h3>
        <a href="?mark_all=1" class="btn btn-outline btn-sm"><i class="fa-solid fa-check-double"></i> Mark all as read</a>
    </div>
    <div class="card-body">
        <div class="notif-full-list">
            <?php if (!$notifications): ?>
            <p class="empty-row">You have no notifications yet.</p>
            <?php endif; ?>
            <?php foreach ($notifications as $n): ?>
            <a href="<?= $n['ticket_id'] ? BASE_URL . '/view_ticket.php?id=' . (int)$n['ticket_id'] : '#' ?>" class="notif-full-item <?= $n['is_read'] ? '' : 'unread' ?>">
                <div class="notif-icon"><i class="fa-solid fa-ticket"></i></div>
                <div class="notif-full-body">
                    <strong><?= e($n['title']) ?></strong>
                    <p><?= e($n['message']) ?></p>
                    <span><?= time_ago($n['created_at']) ?></span>
                </div>
                <?php if (!$n['is_read']): ?><span class="unread-dot"></span><?php endif; ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
