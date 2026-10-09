<?php
require_once __DIR__ . '/config/config.php';
require_admin();

$id = (int)($_GET['id'] ?? 0);
$me = current_user();

if ($id === (int)$me['id']) {
    flash('error', 'You cannot deactivate your own account.');
    redirect('employees.php');
}

$stmt = db()->prepare("SELECT full_name FROM users WHERE id = ?");
$stmt->execute([$id]);
$emp = $stmt->fetch();

if ($emp) {
    db()->prepare("UPDATE users SET status = 'inactive' WHERE id = ?")->execute([$id]);
    db()->prepare("UPDATE tickets SET assigned_to = NULL WHERE assigned_to = ?")->execute([$id]);
    log_activity((int)$me['id'], 'employee_deactivated', "Deactivated employee {$emp['full_name']}");
    flash('success', "{$emp['full_name']} has been deactivated and unassigned from open tickets.");
} else {
    flash('error', 'Employee not found.');
}

redirect('employees.php');
