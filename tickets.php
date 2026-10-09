<?php
$pageTitle = 'View Tickets';
require_once __DIR__ . '/config/config.php';
require_login();
$me = current_user();
$isAdmin = is_admin();

// ---- Filters
$q          = trim($_GET['q'] ?? '');
$status     = $_GET['status'] ?? '';
$branchId   = $_GET['branch_id'] ?? '';
$categoryId = $_GET['category_id'] ?? '';
$priorityId = $_GET['priority_id'] ?? '';
$assignedTo = $_GET['assigned_to'] ?? '';
$dateFrom   = $_GET['date_from'] ?? '';
$dateTo     = $_GET['date_to'] ?? '';

$where = [$isAdmin ? '1=1' : 't.assigned_to = :uid'];
$params = $isAdmin ? [] : ['uid' => $me['id']];

if ($q !== '') {
    $where[] = "(t.ticket_no LIKE :q OR t.subject LIKE :q OR t.message LIKE :q)";
    $params['q'] = "%{$q}%";
}
if ($status !== '')     { $where[] = 't.status = :status'; $params['status'] = $status; }
if ($branchId !== '')   { $where[] = 't.branch_id = :branch_id'; $params['branch_id'] = $branchId; }
if ($categoryId !== '') { $where[] = 't.category_id = :category_id'; $params['category_id'] = $categoryId; }
if ($priorityId !== '') { $where[] = 't.priority_id = :priority_id'; $params['priority_id'] = $priorityId; }
if ($assignedTo !== '' && $isAdmin) { $where[] = 't.assigned_to = :assigned_to'; $params['assigned_to'] = $assignedTo; }
if ($dateFrom !== '')   { $where[] = 'DATE(t.created_at) >= :date_from'; $params['date_from'] = $dateFrom; }
if ($dateTo !== '')     { $where[] = 'DATE(t.created_at) <= :date_to'; $params['date_to'] = $dateTo; }

$whereSql = implode(' AND ', $where);

// ---- Pagination
$perPage = 15;
$page = max(1, (int)($_GET['page'] ?? 1));
$countStmt = db()->prepare("SELECT COUNT(*) c FROM tickets t WHERE $whereSql");
$countStmt->execute($params);
$total = (int)$countStmt->fetch()['c'];
$totalPages = max(1, (int)ceil($total / $perPage));
$offset = ($page - 1) * $perPage;

$sql = "SELECT t.*, b.name AS branch_name, p.name AS priority_name, p.color AS priority_color,
               c.name AS category_name, u.full_name AS assigned_name, cu.full_name AS creator_name
        FROM tickets t
        LEFT JOIN branches b ON b.id = t.branch_id
        LEFT JOIN priorities p ON p.id = t.priority_id
        LEFT JOIN categories c ON c.id = t.category_id
        LEFT JOIN users u ON u.id = t.assigned_to
        LEFT JOIN users cu ON cu.id = t.created_by
        WHERE $whereSql
        ORDER BY t.created_at DESC
        LIMIT $perPage OFFSET $offset";
$stmt = db()->prepare($sql);
$stmt->execute($params);
$tickets = $stmt->fetchAll();

$branches = db()->query("SELECT * FROM branches WHERE status='active' ORDER BY name")->fetchAll();
$categories = db()->query("SELECT * FROM categories WHERE status='active' ORDER BY name")->fetchAll();
$priorities = db()->query("SELECT * FROM priorities ORDER BY sort_order")->fetchAll();
$agents = db()->query("SELECT id, full_name FROM users WHERE role='agent' AND status='active' ORDER BY full_name")->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<section class="card filter-card">
    <form method="get" class="filter-form">
        <div class="filter-group filter-search">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" name="q" placeholder="Search ticket no., subject, message…" value="<?= e($q) ?>">
        </div>
        <select name="status">
            <option value="">All Statuses</option>
            <?php foreach (['Open','In Progress','On Hold','Resolved','Closed'] as $s): ?>
            <option value="<?= e($s) ?>" <?= $status === $s ? 'selected' : '' ?>><?= e($s) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="branch_id">
            <option value="">All Branches</option>
            <?php foreach ($branches as $b): ?>
            <option value="<?= (int)$b['id'] ?>" <?= (string)$branchId === (string)$b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="category_id">
            <option value="">All Categories</option>
            <?php foreach ($categories as $c): ?>
            <option value="<?= (int)$c['id'] ?>" <?= (string)$categoryId === (string)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="priority_id">
            <option value="">All Priorities</option>
            <?php foreach ($priorities as $p): ?>
            <option value="<?= (int)$p['id'] ?>" <?= (string)$priorityId === (string)$p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <?php if ($isAdmin): ?>
        <select name="assigned_to">
            <option value="">All Agents</option>
            <?php foreach ($agents as $a): ?>
            <option value="<?= (int)$a['id'] ?>" <?= (string)$assignedTo === (string)$a['id'] ? 'selected' : '' ?>><?= e($a['full_name']) ?></option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>
        <input type="date" name="date_from" value="<?= e($dateFrom) ?>" title="From date">
        <input type="date" name="date_to" value="<?= e($dateTo) ?>" title="To date">
        <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-filter"></i> Filter</button>
        <a href="<?= BASE_URL ?>/tickets.php" class="btn btn-outline btn-sm">Reset</a>
        <a href="<?= BASE_URL ?>/export_tickets.php?<?= e(http_build_query($_GET)) ?>" class="btn btn-outline btn-sm"><i class="fa-solid fa-file-csv"></i> Export CSV</a>
    </form>
</section>

<section class="card">
    <div class="card-head">
        <h3><i class="fa-solid fa-ticket"></i> Tickets <span class="count-pill"><?= $total ?></span></h3>
        <a href="<?= BASE_URL ?>/add_ticket.php" class="btn btn-primary btn-sm"><i class="fa-solid fa-circle-plus"></i> Add Ticket</a>
    </div>
    <div class="card-body table-wrap">
        <table class="data-table">
            <thead><tr>
                <th>Ticket No.</th><th>Subject</th><th>Branch</th><th>Category</th><th>Priority</th>
                <th>Assigned To</th><th>Owner</th><th>Status</th><th>Logged</th><th></th>
            </tr></thead>
            <tbody>
                <?php if (!$tickets): ?>
                <tr><td colspan="10" class="empty-row">No tickets match your filters.</td></tr>
                <?php endif; ?>
                <?php foreach ($tickets as $t): $overdue = is_ticket_overdue($t); ?>
                <tr class="<?= $overdue ? 'row-overdue' : '' ?>">
                    <td><a href="<?= BASE_URL ?>/view_ticket.php?id=<?= (int)$t['id'] ?>" class="ticket-no-link"><?= e($t['ticket_no']) ?></a></td>
                    <td class="truncate-cell"><?= e($t['subject']) ?></td>
                    <td><?= e($t['branch_name'] ?? '—') ?></td>
                    <td><?= e($t['category_name'] ?? '—') ?></td>
                    <td><span class="priority-dot" style="background:<?= e($t['priority_color'] ?? '#999') ?>"></span><?= e($t['priority_name'] ?? '—') ?></td>
                    <td><?= e($t['assigned_name'] ?? 'Unassigned') ?></td>
                    <td><?= e($t['creator_name'] ?? '—') ?></td>
                    <td>
                        <span class="badge <?= status_badge_class($t['status']) ?>"><?= e($t['status']) ?></span>
                        <?php if ($overdue): ?><span class="badge badge-overdue">Overdue</span><?php endif; ?>
                    </td>
                    <td><?= time_ago($t['created_at']) ?></td>
                    <td class="row-actions">
                        <a href="<?= BASE_URL ?>/view_ticket.php?id=<?= (int)$t['id'] ?>" class="icon-link" title="View"><i class="fa-solid fa-eye"></i></a>
                        <?php if ($isAdmin || $t['created_by'] == $me['id']): ?>
                        <a href="<?= BASE_URL ?>/edit_ticket.php?id=<?= (int)$t['id'] ?>" class="icon-link" title="Edit"><i class="fa-solid fa-pen"></i></a>
                        <?php endif; ?>
                        <?php if ($isAdmin): ?>
                        <a href="<?= BASE_URL ?>/delete_ticket.php?id=<?= (int)$t['id'] ?>" class="icon-link icon-danger" title="Delete" onclick="return confirm('Delete this ticket permanently? This cannot be undone.');"><i class="fa-solid fa-trash"></i></a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?= paginate_links($page, $totalPages, $_GET) ?>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
