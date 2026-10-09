<?php
require_once __DIR__ . '/../config/config.php';
require_login();
header('Content-Type: application/json');

$me = current_user();
$isAdmin = is_admin();
$scopeWhere = $isAdmin ? "1=1" : "assigned_to = :uid";
$params = $isAdmin ? [] : ['uid' => $me['id']];

// ---- Status breakdown
$statuses = ['Open', 'In Progress', 'On Hold', 'Resolved', 'Closed'];
$statusValues = [];
foreach ($statuses as $s) {
    $stmt = db()->prepare("SELECT COUNT(*) c FROM tickets WHERE ($scopeWhere) AND status = :s");
    $stmt->execute($params + ['s' => $s]);
    $statusValues[] = (int)$stmt->fetch()['c'];
}

// ---- 14 day trend
$trendLabels = [];
$trendValues = [];
for ($i = 13; $i >= 0; $i--) {
    $day = date('Y-m-d', strtotime("-{$i} days"));
    $trendLabels[] = date('d M', strtotime($day));
    $stmt = db()->prepare("SELECT COUNT(*) c FROM tickets WHERE ($scopeWhere) AND DATE(created_at) = :d");
    $stmt->execute($params + ['d' => $day]);
    $trendValues[] = (int)$stmt->fetch()['c'];
}

// ---- By branch
$stmt = db()->prepare("SELECT b.name, COUNT(t.id) c FROM branches b
                        LEFT JOIN tickets t ON t.branch_id = b.id AND ($scopeWhere)
                        WHERE b.status = 'active'
                        GROUP BY b.id ORDER BY c DESC LIMIT 8");
$stmt->execute($params);
$branchRows = $stmt->fetchAll();

echo json_encode([
    'status' => ['labels' => $statuses, 'values' => $statusValues],
    'trend'  => ['labels' => $trendLabels, 'values' => $trendValues],
    'branch' => [
        'labels' => array_column($branchRows, 'name'),
        'values' => array_map('intval', array_column($branchRows, 'c')),
    ],
]);
