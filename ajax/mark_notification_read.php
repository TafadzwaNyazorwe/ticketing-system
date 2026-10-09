<?php
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');

if (!is_logged_in()) { echo json_encode(['success' => false]); exit; }
$me = current_user();

$id = $_POST['id'] ?? null;

if ($id === 'all') {
    db()->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?")->execute([$me['id']]);
} else {
    $id = (int)$id;
    db()->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?")->execute([$id, $me['id']]);
}

echo json_encode(['success' => true]);
