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
$message  = trim($_POST['message'] ?? '');
$isInternal = $isAdmin && !empty($_POST['is_internal_note']) ? 1 : 0;
$followUpDate = !empty($_POST['follow_up_date']) ? $_POST['follow_up_date'] : null;

if (!$ticketId || $message === '') {
    echo json_encode(['success' => false, 'message' => 'A message is required.']); exit;
}

$stmt = db()->prepare("SELECT * FROM tickets WHERE id = ?");
$stmt->execute([$ticketId]);
$ticket = $stmt->fetch();
if (!$ticket) { echo json_encode(['success' => false, 'message' => 'Ticket not found.']); exit; }

if (!$isAdmin && (int)$ticket['assigned_to'] !== (int)$me['id'] && (int)$ticket['created_by'] !== (int)$me['id']) {
    echo json_encode(['success' => false, 'message' => 'You do not have access to this ticket.']); exit;
}

try {
    $attachmentPath = null;
    if (!empty($_FILES['attachment']['name'])) {
        $attachmentPath = handle_upload($_FILES['attachment'], UPLOAD_DIR);
    }

    $stmt = db()->prepare("INSERT INTO ticket_followups (ticket_id, user_id, message, is_internal_note, attachment, follow_up_date) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$ticketId, $me['id'], $message, $isInternal, $attachmentPath, $followUpDate]);

    db()->prepare("UPDATE tickets SET updated_at = NOW() WHERE id = ?")->execute([$ticketId]);
    log_activity((int)$me['id'], 'followup_added', "Follow-up added to {$ticket['ticket_no']}");

    // Notify: owner and assigned agent (except the author), unless internal note
    if (!$isInternal) {
        $recipients = [];
        if ($ticket['created_by'] && $ticket['created_by'] != $me['id']) $recipients[] = $ticket['created_by'];
        if ($ticket['assigned_to'] && $ticket['assigned_to'] != $me['id']) $recipients[] = $ticket['assigned_to'];
        $recipients = array_unique($recipients);

        foreach ($recipients as $rid) {
            $rstmt = db()->prepare("SELECT * FROM users WHERE id = ? AND status = 'active'");
            $rstmt->execute([$rid]);
            $recipient = $rstmt->fetch();
            if ($recipient) notify_followup_added($ticket, $me, $recipient);
        }
    }

    echo json_encode(['success' => true]);
} catch (RuntimeException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
