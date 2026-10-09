<?php
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');

if (!is_logged_in()) { echo json_encode(['items' => []]); exit; }
$me = current_user();

$stmt = db()->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
$stmt->execute([$me['id']]);
$items = $stmt->fetchAll();

$out = array_map(function ($n) {
    return [
        'id' => (int)$n['id'],
        'title' => $n['title'],
        'message' => $n['message'],
        'ticket_id' => $n['ticket_id'],
        'is_read' => (bool)$n['is_read'],
        'time' => time_ago($n['created_at']),
    ];
}, $items);

echo json_encode(['items' => $out]);
