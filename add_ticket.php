<?php
$pageTitle = 'Add Ticket';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/mailer.php';
require_login();
$me = current_user();

$branches   = db()->query("SELECT * FROM branches WHERE status='active' ORDER BY name")->fetchAll();
$categories = db()->query("SELECT * FROM categories WHERE status='active' ORDER BY name")->fetchAll();
$priorities = db()->query("SELECT * FROM priorities ORDER BY sort_order")->fetchAll();
$agents     = db()->query("SELECT id, full_name FROM users WHERE role='agent' AND status='active' ORDER BY full_name")->fetchAll();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $subject    = trim($_POST['subject'] ?? '');
    $message    = trim($_POST['message'] ?? '');
    $branchId   = $_POST['branch_id'] ?: null;
    $categoryId = $_POST['category_id'] ?: null;
    $priorityId = $_POST['priority_id'] ?: null;
    $assignedTo = $_POST['assigned_to'] ?: null;

    if ($subject === '') $errors[] = 'Subject is required.';
    if ($message === '') $errors[] = 'Message / description is required.';

    if (!$errors) {
        try {
            $attachmentPath = null;
            if (!empty($_FILES['attachment']['name'])) {
                $attachmentPath = handle_upload($_FILES['attachment'], UPLOAD_DIR);
            }

            $dueAt = null;
            if ($priorityId) {
                $pr = db()->prepare("SELECT sla_hours FROM priorities WHERE id = ?");
                $pr->execute([$priorityId]);
                $slaHours = $pr->fetch()['sla_hours'] ?? null;
                if ($slaHours) $dueAt = date('Y-m-d H:i:s', strtotime("+{$slaHours} hours"));
            }

            $ticketNo = generate_ticket_no();

            $stmt = db()->prepare("INSERT INTO tickets
                (ticket_no, subject, message, branch_id, category_id, priority_id, status, assigned_to, created_by, attachment, due_at)
                VALUES (?, ?, ?, ?, ?, ?, 'Open', ?, ?, ?, ?)");
            $stmt->execute([$ticketNo, $subject, $message, $branchId, $categoryId, $priorityId, $assignedTo, $me['id'], $attachmentPath, $dueAt]);
            $ticketId = (int)db()->lastInsertId();

            record_status_change($ticketId, null, 'Open', (int)$me['id'], 'Ticket created');
            log_activity((int)$me['id'], 'ticket_created', "Created ticket {$ticketNo}");

            // ---- Notifications
            $stmt = db()->prepare("SELECT t.*, p.name AS priority_name FROM tickets t LEFT JOIN priorities p ON p.id = t.priority_id WHERE t.id = ?");
            $stmt->execute([$ticketId]);
            $ticketFull = $stmt->fetch();

            notify_ticket_created($ticketFull, $me);

            if ($assignedTo) {
                $agentStmt = db()->prepare("SELECT * FROM users WHERE id = ?");
                $agentStmt->execute([$assignedTo]);
                $agent = $agentStmt->fetch();
                if ($agent) notify_ticket_assigned($ticketFull, $agent, $me);
            }

            flash('success', "Ticket {$ticketNo} was logged successfully.");
            redirect('view_ticket.php?id=' . $ticketId);
        } catch (RuntimeException $e) {
            $errors[] = $e->getMessage();
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<section class="card form-card">
    <div class="card-head"><h3><i class="fa-solid fa-circle-plus"></i> Log a New Ticket</h3></div>
    <div class="card-body">
        <?php if ($errors): ?>
        <div class="flash flash-error">
            <i class="fa-solid fa-circle-exclamation"></i>
            <span><?= implode('<br>', array_map('e', $errors)) ?></span>
        </div>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data" class="stack-form">
            <?= csrf_field() ?>

            <div class="form-row">
                <div class="form-field">
                    <label>Subject <span class="req">*</span></label>
                    <input type="text" name="subject" required placeholder="Short summary of the issue" value="<?= e($_POST['subject'] ?? '') ?>">
                </div>
            </div>

            <div class="form-field">
                <label>Message / Description <span class="req">*</span></label>
                <textarea name="message" rows="5" required placeholder="Describe the issue in detail…"><?= e($_POST['message'] ?? '') ?></textarea>
            </div>

            <div class="form-row form-row-3">
                <div class="form-field">
                    <label>Branch</label>
                    <select name="branch_id">
                        <option value="">Select branch…</option>
                        <?php foreach ($branches as $b): ?>
                        <option value="<?= (int)$b['id'] ?>"><?= e($b['name']) ?> (<?= e($b['code']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-field">
                    <label>Category</label>
                    <select name="category_id">
                        <option value="">Select category…</option>
                        <?php foreach ($categories as $c): ?>
                        <option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-field">
                    <label>Priority</label>
                    <select name="priority_id">
                        <option value="">Select priority…</option>
                        <?php foreach ($priorities as $p): ?>
                        <option value="<?= (int)$p['id'] ?>"><?= e($p['name']) ?> (SLA <?= (int)$p['sla_hours'] ?>h)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-field">
                    <label>Assign To (optional)</label>
                    <select name="assigned_to">
                        <option value="">Leave unassigned</option>
                        <?php foreach ($agents as $a): ?>
                        <option value="<?= (int)$a['id'] ?>"><?= e($a['full_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-field">
                    <label>Attachment (optional)</label>
                    <input type="file" name="attachment">
                    <span class="field-hint">JPG, PNG, PDF, DOCX, XLSX, ZIP — max 5MB</span>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-paper-plane"></i> Submit Ticket</button>
                <a href="<?= BASE_URL ?>/tickets.php" class="btn btn-outline">Cancel</a>
            </div>
        </form>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
