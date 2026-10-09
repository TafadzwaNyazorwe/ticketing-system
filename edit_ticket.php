<?php
$pageTitle = 'Edit Ticket';
require_once __DIR__ . '/config/config.php';
require_login();
$me = current_user();
$isAdmin = is_admin();

$id = (int)($_GET['id'] ?? 0);
$stmt = db()->prepare("SELECT * FROM tickets WHERE id = ?");
$stmt->execute([$id]);
$ticket = $stmt->fetch();

if (!$ticket) { flash('error', 'Ticket not found.'); redirect('tickets.php'); }
if (!$isAdmin && (int)$ticket['created_by'] !== (int)$me['id']) {
    flash('error', 'You can only edit tickets you created.');
    redirect('tickets.php');
}

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

    if ($subject === '') $errors[] = 'Subject is required.';
    if ($message === '') $errors[] = 'Message / description is required.';

    if (!$errors) {
        try {
            $attachmentPath = $ticket['attachment'];
            if (!empty($_FILES['attachment']['name'])) {
                $attachmentPath = handle_upload($_FILES['attachment'], UPLOAD_DIR);
            }
            $stmt = db()->prepare("UPDATE tickets SET subject=?, message=?, branch_id=?, category_id=?, priority_id=?, attachment=? WHERE id=?");
            $stmt->execute([$subject, $message, $branchId, $categoryId, $priorityId, $attachmentPath, $id]);
            log_activity((int)$me['id'], 'ticket_updated', "Updated ticket {$ticket['ticket_no']}");
            flash('success', 'Ticket updated successfully.');
            redirect('view_ticket.php?id=' . $id);
        } catch (RuntimeException $e) {
            $errors[] = $e->getMessage();
        }
    }
} else {
    $_POST = $ticket;
}

require_once __DIR__ . '/includes/header.php';
?>

<section class="card form-card">
    <div class="card-head"><h3><i class="fa-solid fa-pen"></i> Edit Ticket — <?= e($ticket['ticket_no']) ?></h3></div>
    <div class="card-body">
        <?php if ($errors): ?>
        <div class="flash flash-error"><i class="fa-solid fa-circle-exclamation"></i><span><?= implode('<br>', array_map('e', $errors)) ?></span></div>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data" class="stack-form">
            <?= csrf_field() ?>
            <div class="form-field">
                <label>Subject <span class="req">*</span></label>
                <input type="text" name="subject" required value="<?= e($_POST['subject']) ?>">
            </div>
            <div class="form-field">
                <label>Message / Description <span class="req">*</span></label>
                <textarea name="message" rows="5" required><?= e($_POST['message']) ?></textarea>
            </div>
            <div class="form-row form-row-3">
                <div class="form-field">
                    <label>Branch</label>
                    <select name="branch_id">
                        <option value="">Select branch…</option>
                        <?php foreach ($branches as $b): ?>
                        <option value="<?= (int)$b['id'] ?>" <?= (string)$ticket['branch_id'] === (string)$b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-field">
                    <label>Category</label>
                    <select name="category_id">
                        <option value="">Select category…</option>
                        <?php foreach ($categories as $c): ?>
                        <option value="<?= (int)$c['id'] ?>" <?= (string)$ticket['category_id'] === (string)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-field">
                    <label>Priority</label>
                    <select name="priority_id">
                        <option value="">Select priority…</option>
                        <?php foreach ($priorities as $p): ?>
                        <option value="<?= (int)$p['id'] ?>" <?= (string)$ticket['priority_id'] === (string)$p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-field">
                <label>Replace Attachment (optional)</label>
                <input type="file" name="attachment">
                <?php if ($ticket['attachment']): ?>
                <span class="field-hint">Current file: <a href="<?= BASE_URL . '/' . e($ticket['attachment']) ?>" target="_blank">view attachment</a></span>
                <?php endif; ?>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save Changes</button>
                <a href="<?= BASE_URL ?>/view_ticket.php?id=<?= $id ?>" class="btn btn-outline">Cancel</a>
            </div>
        </form>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
