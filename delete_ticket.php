<?php
require_once __DIR__ . '/config/config.php';
require_admin();

$id = (int)($_GET['id'] ?? 0);
$stmt = db()->prepare("SELECT ticket_no FROM tickets WHERE id = ?");
$stmt->execute([$id]);
$ticket = $stmt->fetch();

if ($ticket) {
    db()->prepare("DELETE FROM tickets WHERE id = ?")->execute([$id]);
    log_activity((int)current_user()['id'], 'ticket_deleted', "Deleted ticket {$ticket['ticket_no']}");
    flash('success', "Ticket {$ticket['ticket_no']} was deleted.");
} else {
    flash('error', 'Ticket not found.');
}

redirect('tickets.php');
