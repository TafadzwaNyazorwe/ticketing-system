<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/mailer.php';
header('Content-Type: application/json');

if (!is_logged_in()) { echo json_encode(['success' => false, 'message' => 'Not authenticated.']); exit; }

$me = current_user();
$isAdmin = is_admin();

$token = $_POST['csrf_token'] ?? '';
if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    echo json_encode(['success' => false, 'message' => 'Invalid session token, please refresh the page.']); exit;
}

$ticketId = (int)($_POST['ticket_id'] ?? 0);
$newStatus = $_POST['status'] ?? '';
$validStatuses = ['Open', 'In Progress', 'On Hold', 'Resolved', 'Closed'];

if (!$ticketId || !in_array($newStatus, $validStatuses, true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']); exit;
}

$stmt = db()->prepare("SELECT * FROM tickets WHERE id = ?");
$stmt->execute([$ticketId]);
$ticket = $stmt->fetch();
if (!$ticket) { echo json_encode(['success' => false, 'message' => 'Ticket not found.']); exit; }

if (!$isAdmin && (int)$ticket['assigned_to'] !== (int)$me['id']) {
    echo json_encode(['success' => false, 'message' => 'Only the assigned agent or an admin can change status.']); exit;
}

$oldStatus = $ticket['status'];
if ($oldStatus === $newStatus) { echo json_encode(['success' => true]); exit; }

$resolvedAt = $ticket['resolved_at'];
$closedAt = $ticket['closed_at'];
if ($newStatus === 'Resolved' && !$resolvedAt) $resolvedAt = date('Y-m-d H:i:s');
if ($newStatus === 'Closed' && !$closedAt) $closedAt = date('Y-m-d H:i:s');

$stmt = db()->prepare("UPDATE tickets SET status=?, resolved_at=?, closed_at=? WHERE id=?");
$stmt->execute([$newStatus, $resolvedAt, $closedAt, $ticketId]);

record_status_change($ticketId, $oldStatus, $newStatus, (int)$me['id']);
log_activity((int)$me['id'], 'status_changed', "{$ticket['ticket_no']}: {$oldStatus} -> {$newStatus}");

// Notify owner (if not the one making the change)
if ($ticket['created_by'] && $ticket['created_by'] != $me['id']) {
    $rstmt = db()->prepare("SELECT * FROM users WHERE id = ? AND status = 'active'");
    $rstmt->execute([$ticket['created_by']]);
    $owner = $rstmt->fetch();
    if ($owner) notify_status_changed($ticket, $oldStatus, $newStatus, $owner);
}

echo json_encode(['success' => true]);
