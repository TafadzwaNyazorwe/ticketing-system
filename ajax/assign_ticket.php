<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/mailer.php';
header('Content-Type: application/json');

if (!is_logged_in() || !is_admin()) { echo json_encode(['success' => false, 'message' => 'Only admins can assign tickets.']); exit; }

$me = current_user();

$token = $_POST['csrf_token'] ?? '';
if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    echo json_encode(['success' => false, 'message' => 'Invalid session token, please refresh the page.']); exit;
}

$ticketId = (int)($_POST['ticket_id'] ?? 0);
$agentId  = $_POST['agent_id'] !== '' ? (int)$_POST['agent_id'] : null;

$stmt = db()->prepare("SELECT * FROM tickets WHERE id = ?");
$stmt->execute([$ticketId]);
$ticket = $stmt->fetch();
if (!$ticket) { echo json_encode(['success' => false, 'message' => 'Ticket not found.']); exit; }

db()->prepare("UPDATE tickets SET assigned_to = ? WHERE id = ?")->execute([$agentId, $ticketId]);
log_activity((int)$me['id'], 'ticket_assigned', "{$ticket['ticket_no']} assigned to user #" . ($agentId ?? 'none'));

if ($agentId) {
    $astmt = db()->prepare("SELECT * FROM users WHERE id = ? AND status = 'active'");
    $astmt->execute([$agentId]);
    $agent = $astmt->fetch();
    if ($agent) notify_ticket_assigned($ticket, $agent, $me);
}

echo json_encode(['success' => true]);
