<?php
$pageTitle = 'Ticket Details';
require_once __DIR__ . '/config/config.php';
require_login();
$me = current_user();
$isAdmin = is_admin();

$id = (int)($_GET['id'] ?? 0);
$sql = "SELECT t.*, b.name AS branch_name, p.name AS priority_name, p.color AS priority_color,
               c.name AS category_name, u.full_name AS assigned_name, u.email AS assigned_email,
               cu.full_name AS creator_name
        FROM tickets t
        LEFT JOIN branches b ON b.id = t.branch_id
        LEFT JOIN priorities p ON p.id = t.priority_id
        LEFT JOIN categories c ON c.id = t.category_id
        LEFT JOIN users u ON u.id = t.assigned_to
        LEFT JOIN users cu ON cu.id = t.created_by
        WHERE t.id = ?";
$stmt = db()->prepare($sql);
$stmt->execute([$id]);
$ticket = $stmt->fetch();

if (!$ticket) { flash('error', 'Ticket not found.'); redirect('tickets.php'); }
if (!$isAdmin && (int)$ticket['assigned_to'] !== (int)$me['id'] && (int)$ticket['created_by'] !== (int)$me['id']) {
    flash('error', 'You do not have access to that ticket.');
    redirect('tickets.php');
}

$agents = db()->query("SELECT id, full_name FROM users WHERE role='agent' AND status='active' ORDER BY full_name")->fetchAll();

$followupStmt = db()->prepare("SELECT f.*, u.full_name, u.avatar, u.role
                                FROM ticket_followups f LEFT JOIN users u ON u.id = f.user_id
                                WHERE f.ticket_id = ? ORDER BY f.created_at ASC");
$followupStmt->execute([$id]);
$followups = $followupStmt->fetchAll();

$historyStmt = db()->prepare("SELECT h.*, u.full_name FROM ticket_status_history h
                               LEFT JOIN users u ON u.id = h.changed_by
                               WHERE h.ticket_id = ? ORDER BY h.created_at DESC");
$historyStmt->execute([$id]);
$history = $historyStmt->fetchAll();

$overdue = is_ticket_overdue($ticket);

require_once __DIR__ . '/includes/header.php';
?>

<section class="ticket-header-card card">
    <div class="ticket-header-top">
        <div>
            <span class="ticket-no-big"><?= e($ticket['ticket_no']) ?></span>
            <h2 class="ticket-subject"><?= e($ticket['subject']) ?></h2>
            <div class="ticket-meta-pills">
                <span class="badge <?= status_badge_class($ticket['status']) ?>" id="statusBadge"><?= e($ticket['status']) ?></span>
                <?php if ($overdue): ?><span class="badge badge-overdue"><i class="fa-solid fa-triangle-exclamation"></i> Overdue</span><?php endif; ?>
                <span class="pill"><span class="priority-dot" style="background:<?= e($ticket['priority_color'] ?? '#999') ?>"></span><?= e($ticket['priority_name'] ?? 'No priority') ?></span>
                <span class="pill"><i class="fa-solid fa-code-branch"></i> <?= e($ticket['branch_name'] ?? '—') ?></span>
                <span class="pill"><i class="fa-solid fa-tag"></i> <?= e($ticket['category_name'] ?? '—') ?></span>
            </div>
        </div>
        <div class="ticket-header-actions">
            <a href="<?= BASE_URL ?>/tickets.php" class="btn btn-outline btn-sm"><i class="fa-solid fa-arrow-left"></i> Back</a>
            <?php if ($isAdmin || $ticket['created_by'] == $me['id']): ?>
            <a href="<?= BASE_URL ?>/edit_ticket.php?id=<?= $id ?>" class="btn btn-outline btn-sm"><i class="fa-solid fa-pen"></i> Edit</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="ticket-info-grid">
        <div><span class="info-label">Owner</span><span class="info-value"><?= e($ticket['creator_name'] ?? '—') ?></span></div>
        <div><span class="info-label">Assigned To</span><span class="info-value" id="assignedLabel"><?= e($ticket['assigned_name'] ?? 'Unassigned') ?></span></div>
        <div><span class="info-label">Logged</span><span class="info-value"><?= format_date($ticket['created_at']) ?></span></div>
        <div><span class="info-label">SLA Due</span><span class="info-value <?= $overdue ? 'text-danger' : '' ?>"><?= $ticket['due_at'] ? format_date($ticket['due_at']) : 'No SLA set' ?></span></div>
        <div><span class="info-label">Resolved</span><span class="info-value"><?= $ticket['resolved_at'] ? format_date($ticket['resolved_at']) : '—' ?></span></div>
        <div><span class="info-label">Last Updated</span><span class="info-value"><?= time_ago($ticket['updated_at']) ?></span></div>
    </div>

    <div class="ticket-message-block">
        <span class="info-label">Message</span>
        <p><?= nl2br(e($ticket['message'])) ?></p>
        <?php if ($ticket['attachment']): ?>
        <a href="<?= BASE_URL . '/' . e($ticket['attachment']) ?>" target="_blank" class="attachment-chip"><i class="fa-solid fa-paperclip"></i> View Attachment</a>
        <?php endif; ?>
    </div>
</section>

<section class="grid-2col grid-2col-wide">
    <div class="card">
        <div class="card-head"><h3><i class="fa-solid fa-comments"></i> Follow-ups &amp; Conversation</h3></div>
        <div class="card-body">
            <div class="followup-thread" id="followupThread">
                <?php if (!$followups): ?>
                <p class="empty-row">No follow-ups yet. Add the first update below.</p>
                <?php endif; ?>
                <?php foreach ($followups as $f): ?>
                <div class="followup-item <?= $f['is_internal_note'] ? 'followup-internal' : '' ?>">
                    <div class="avatar-circle avatar-sm"><?= e(strtoupper(substr($f['full_name'] ?? '?', 0, 1))) ?></div>
                    <div class="followup-body">
                        <div class="followup-head">
                            <strong><?= e($f['full_name'] ?? 'Unknown user') ?></strong>
                            <?php if ($f['role'] === 'admin'): ?><span class="tag-mini">Admin</span><?php endif; ?>
                            <?php if ($f['is_internal_note']): ?><span class="tag-mini tag-warning">Internal Note</span><?php endif; ?>
                            <span class="followup-time"><?= time_ago($f['created_at']) ?></span>
                        </div>
                        <p><?= nl2br(e($f['message'])) ?></p>
                        <?php if ($f['attachment']): ?>
                        <a href="<?= BASE_URL . '/' . e($f['attachment']) ?>" target="_blank" class="attachment-chip"><i class="fa-solid fa-paperclip"></i> Attachment</a>
                        <?php endif; ?>
                        <?php if ($f['follow_up_date']): ?>
                        <span class="followup-schedule"><i class="fa-regular fa-calendar"></i> Next follow-up: <?= format_date($f['follow_up_date'], 'd M Y') ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <form id="followupForm" class="followup-form" enctype="multipart/form-data">
                <input type="hidden" name="ticket_id" value="<?= $id ?>">
                <?= csrf_field() ?>
                <textarea name="message" rows="3" placeholder="Write a follow-up, update, or reply…" required></textarea>
                <div class="followup-form-row">
                    <label class="checkbox-inline"><input type="checkbox" name="follow_up_date_toggle" id="fuDateToggle"> Schedule next follow-up</label>
                    <input type="date" name="follow_up_date" id="fuDate" disabled>
                    <?php if ($isAdmin): ?>
                    <label class="checkbox-inline"><input type="checkbox" name="is_internal_note" value="1"> Internal note (not emailed to requester)</label>
                    <?php endif; ?>
                </div>
                <div class="followup-form-row">
                    <input type="file" name="attachment">
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-paper-plane"></i> Add Follow-up</button>
                </div>
            </form>
        </div>
    </div>

    <div class="stack-cards">
        <div class="card">
            <div class="card-head"><h3><i class="fa-solid fa-sliders"></i> Manage Ticket</h3></div>
            <div class="card-body">
                <div class="form-field">
                    <label>Status</label>
                    <select id="statusSelect" <?= (!$isAdmin && $ticket['assigned_to'] != $me['id']) ? 'disabled' : '' ?>>
                        <?php foreach (['Open','In Progress','On Hold','Resolved','Closed'] as $s): ?>
                        <option value="<?= e($s) ?>" <?= $ticket['status'] === $s ? 'selected' : '' ?>><?= e($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if ($isAdmin): ?>
                <div class="form-field">
                    <label>Assigned Agent</label>
                    <select id="assignSelect">
                        <option value="">Unassigned</option>
                        <?php foreach ($agents as $a): ?>
                        <option value="<?= (int)$a['id'] ?>" <?= (string)$ticket['assigned_to'] === (string)$a['id'] ? 'selected' : '' ?>><?= e($a['full_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <p class="field-hint" id="manageHint">Changes save instantly and notify by email.</p>
            </div>
        </div>

        <div class="card">
            <div class="card-head"><h3><i class="fa-solid fa-timeline"></i> Status History</h3></div>
            <div class="card-body">
                <ul class="timeline">
                    <?php foreach ($history as $h): ?>
                    <li>
                        <span class="timeline-dot"></span>
                        <div>
                            <strong><?= e($h['old_status'] ? $h['old_status'] . ' → ' . $h['new_status'] : $h['new_status']) ?></strong>
                            <p><?= e($h['full_name'] ?? 'System') ?> · <?= time_ago($h['created_at']) ?></p>
                        </div>
                    </li>
                    <?php endforeach; ?>
                    <?php if (!$history): ?><p class="empty-row">No history recorded.</p><?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</section>

<script>
const TICKET_ID = <?= (int)$id ?>;
const CSRF = '<?= e(csrf_token()) ?>';
const BASE = '<?= BASE_URL ?>';

document.getElementById('fuDateToggle')?.addEventListener('change', function () {
    document.getElementById('fuDate').disabled = !this.checked;
});

document.getElementById('followupForm').addEventListener('submit', function (e) {
    e.preventDefault();
    const fd = new FormData(this);
    fetch(BASE + '/ajax/add_followup.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.success) { window.location.reload(); }
            else { alert(res.message || 'Could not add follow-up.'); }
        })
        .catch(() => alert('Network error while adding follow-up.'));
});

document.getElementById('statusSelect')?.addEventListener('change', function () {
    const status = this.value;
    fetch(BASE + '/ajax/update_status.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `ticket_id=${TICKET_ID}&status=${encodeURIComponent(status)}&csrf_token=${encodeURIComponent(CSRF)}`
    }).then(r => r.json()).then(res => {
        if (res.success) { window.location.reload(); } else { alert(res.message || 'Could not update status.'); }
    });
});

document.getElementById('assignSelect')?.addEventListener('change', function () {
    const agentId = this.value;
    fetch(BASE + '/ajax/assign_ticket.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `ticket_id=${TICKET_ID}&agent_id=${encodeURIComponent(agentId)}&csrf_token=${encodeURIComponent(CSRF)}`
    }).then(r => r.json()).then(res => {
        if (res.success) { window.location.reload(); } else { alert(res.message || 'Could not assign ticket.'); }
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
