<?php
$pageTitle = 'Employees';
require_once __DIR__ . '/config/config.php';
require_admin();

$q = trim($_GET['q'] ?? '');
$where = ['1=1'];
$params = [];
if ($q !== '') { $where[] = "(u.full_name LIKE :q OR u.username LIKE :q OR u.email LIKE :q)"; $params['q'] = "%{$q}%"; }
$whereSql = implode(' AND ', $where);

$sql = "SELECT u.*, b.name AS branch_name,
        (SELECT COUNT(*) FROM tickets t WHERE t.assigned_to = u.id) AS ticket_count,
        (SELECT COUNT(*) FROM tickets t WHERE t.assigned_to = u.id AND t.status IN ('Resolved','Closed')) AS resolved_count
        FROM users u
        LEFT JOIN branches b ON b.id = u.branch_id
        WHERE $whereSql
        ORDER BY u.full_name";
$stmt = db()->prepare($sql);
$stmt->execute($params);
$employees = $stmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<section class="card">
    <div class="card-head">
        <h3><i class="fa-solid fa-users"></i> Employees / Agents <span class="count-pill"><?= count($employees) ?></span></h3>
        <div class="card-head-actions">
            <form method="get" class="inline-search">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" name="q" placeholder="Search employees…" value="<?= e($q) ?>">
            </form>
            <a href="<?= BASE_URL ?>/add_employee.php" class="btn btn-primary btn-sm"><i class="fa-solid fa-user-plus"></i> Add Employee</a>
        </div>
    </div>
    <div class="card-body table-wrap">
        <table class="data-table">
            <thead><tr>
                <th>Employee</th><th>Role</th><th>Branch</th><th>Assigned Tickets</th><th>Resolved</th><th>Status</th><th>Last Login</th><th></th>
            </tr></thead>
            <tbody>
                <?php if (!$employees): ?>
                <tr><td colspan="8" class="empty-row">No employees found.</td></tr>
                <?php endif; ?>
                <?php foreach ($employees as $emp): ?>
                <tr>
                    <td>
                        <div class="user-cell">
                            <div class="avatar-circle avatar-sm">
                                <?php if ($emp['avatar']): ?><img src="<?= BASE_URL . '/' . e($emp['avatar']) ?>" alt=""><?php else: ?><?= e(strtoupper(substr($emp['full_name'],0,1))) ?><?php endif; ?>
                            </div>
                            <div>
                                <strong><?= e($emp['full_name']) ?></strong>
                                <span><?= e($emp['email']) ?></span>
                            </div>
                        </div>
                    </td>
                    <td><span class="tag-mini <?= $emp['role'] === 'admin' ? 'tag-purple' : '' ?>"><?= e(ucfirst($emp['role'])) ?></span></td>
                    <td><?= e($emp['branch_name'] ?? '—') ?></td>
                    <td><?= (int)$emp['ticket_count'] ?></td>
                    <td><?= (int)$emp['resolved_count'] ?></td>
                    <td><span class="badge <?= $emp['status'] === 'active' ? 'badge-resolved' : 'badge-closed' ?>"><?= e(ucfirst($emp['status'])) ?></span></td>
                    <td><?= $emp['last_login'] ? time_ago($emp['last_login']) : 'Never' ?></td>
                    <td class="row-actions">
                        <a href="<?= BASE_URL ?>/edit_employee.php?id=<?= (int)$emp['id'] ?>" class="icon-link" title="Edit"><i class="fa-solid fa-pen"></i></a>
                        <?php if ((int)$emp['id'] !== (int)current_user()['id']): ?>
                        <a href="<?= BASE_URL ?>/delete_employee.php?id=<?= (int)$emp['id'] ?>" class="icon-link icon-danger" title="Deactivate" onclick="return confirm('Deactivate this employee? They will no longer be able to log in.');"><i class="fa-solid fa-user-slash"></i></a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
