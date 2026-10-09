<?php
require_once __DIR__ . '/config/config.php';
require_login();
$me = current_user();
$isAdmin = is_admin();

$where = [$isAdmin ? '1=1' : 't.assigned_to = :uid'];
$params = $isAdmin ? [] : ['uid' => $me['id']];

$q = trim($_GET['q'] ?? '');
if ($q !== '') { $where[] = "(t.ticket_no LIKE :q OR t.subject LIKE :q)"; $params['q'] = "%{$q}%"; }
foreach (['status' => 't.status', 'branch_id' => 't.branch_id', 'category_id' => 't.category_id', 'priority_id' => 't.priority_id'] as $key => $col) {
    if (!empty($_GET[$key])) { $where[] = "$col = :$key"; $params[$key] = $_GET[$key]; }
}
$whereSql = implode(' AND ', $where);

$sql = "SELECT t.ticket_no, t.subject, b.name AS branch, c.name AS category, p.name AS priority,
               t.status, u.full_name AS assigned_to, cu.full_name AS owner, t.created_at, t.due_at, t.resolved_at
        FROM tickets t
        LEFT JOIN branches b ON b.id = t.branch_id
        LEFT JOIN categories c ON c.id = t.category_id
        LEFT JOIN priorities p ON p.id = t.priority_id
        LEFT JOIN users u ON u.id = t.assigned_to
        LEFT JOIN users cu ON cu.id = t.created_by
        WHERE $whereSql
        ORDER BY t.created_at DESC";
$stmt = db()->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=tickets_export_' . date('Y-m-d_His') . '.csv');

$out = fopen('php://output', 'w');
fputcsv($out, ['Ticket No', 'Subject', 'Branch', 'Category', 'Priority', 'Status', 'Assigned To', 'Owner', 'Created At', 'Due At', 'Resolved At'], ',', '"', '\\');
foreach ($rows as $r) {
    fputcsv($out, [
        $r['ticket_no'], $r['subject'], $r['branch'], $r['category'], $r['priority'],
        $r['status'], $r['assigned_to'], $r['owner'], $r['created_at'], $r['due_at'], $r['resolved_at'],
    ], ',', '"', '\\');
}
fclose($out);
log_activity((int)$me['id'], 'export_tickets', 'Exported tickets CSV');
exit;
