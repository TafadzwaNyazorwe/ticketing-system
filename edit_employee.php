<?php
$pageTitle = 'Edit Employee';
require_once __DIR__ . '/config/config.php';
require_admin();

$id = (int)($_GET['id'] ?? 0);
$stmt = db()->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$id]);
$employee = $stmt->fetch();
if (!$employee) { flash('error', 'Employee not found.'); redirect('employees.php'); }

$branches = db()->query("SELECT * FROM branches WHERE status='active' ORDER BY name")->fetchAll();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $fullName = trim($_POST['full_name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $role     = $_POST['role'] ?? 'agent';
    $department = trim($_POST['department'] ?? '');
    $branchId = $_POST['branch_id'] ?: null;
    $status   = $_POST['status'] ?? 'active';
    $newPassword = $_POST['new_password'] ?? '';

    if ($fullName === '') $errors[] = 'Full name is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email address is required.';

    if (!$errors) {
        $dupe = db()->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $dupe->execute([$email, $id]);
        if ($dupe->fetch()) $errors[] = 'That email is already used by another account.';
    }

    if (!$errors) {
        $stmt = db()->prepare("UPDATE users SET full_name=?, email=?, phone=?, role=?, department=?, branch_id=?, status=? WHERE id=?");
        $stmt->execute([$fullName, $email, $phone, $role, $department, $branchId, $status, $id]);

        if ($newPassword !== '') {
            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            db()->prepare("UPDATE users SET password_hash = ?, must_change_password = 1 WHERE id = ?")->execute([$hash, $id]);
        }

        log_activity((int)current_user()['id'], 'employee_updated', "Updated employee {$fullName}");
        flash('success', 'Employee updated successfully.');
        redirect('employees.php');
    }
} else {
    $_POST = $employee;
}

require_once __DIR__ . '/includes/header.php';
?>

<section class="card form-card">
    <div class="card-head"><h3><i class="fa-solid fa-pen"></i> Edit Employee — <?= e($employee['full_name']) ?></h3></div>
    <div class="card-body">
        <?php if ($errors): ?>
        <div class="flash flash-error"><i class="fa-solid fa-circle-exclamation"></i><span><?= implode('<br>', array_map('e', $errors)) ?></span></div>
        <?php endif; ?>

        <form method="post" class="stack-form">
            <?= csrf_field() ?>
            <div class="form-row">
                <div class="form-field">
                    <label>Full Name <span class="req">*</span></label>
                    <input type="text" name="full_name" required value="<?= e($_POST['full_name']) ?>">
                </div>
                <div class="form-field">
                    <label>Username</label>
                    <input type="text" value="<?= e($employee['username']) ?>" disabled>
                </div>
            </div>
            <div class="form-row">
                <div class="form-field">
                    <label>Email Address <span class="req">*</span></label>
                    <input type="email" name="email" required value="<?= e($_POST['email']) ?>">
                </div>
                <div class="form-field">
                    <label>Phone</label>
                    <input type="text" name="phone" value="<?= e($_POST['phone']) ?>">
                </div>
            </div>
            <div class="form-row form-row-3">
                <div class="form-field">
                    <label>Role</label>
                    <select name="role">
                        <option value="agent" <?= $employee['role'] === 'agent' ? 'selected' : '' ?>>Agent</option>
                        <option value="admin" <?= $employee['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                    </select>
                </div>
                <div class="form-field">
                    <label>Department</label>
                    <input type="text" name="department" value="<?= e($_POST['department']) ?>">
                </div>
                <div class="form-field">
                    <label>Branch</label>
                    <select name="branch_id">
                        <option value="">Select branch…</option>
                        <?php foreach ($branches as $b): ?>
                        <option value="<?= (int)$b['id'] ?>" <?= (string)$employee['branch_id'] === (string)$b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-field">
                    <label>Status</label>
                    <select name="status">
                        <option value="active" <?= $employee['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= $employee['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
                <div class="form-field">
                    <label>Reset Password (optional)</label>
                    <input type="text" name="new_password" placeholder="Leave blank to keep current password">
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save Changes</button>
                <a href="<?= BASE_URL ?>/employees.php" class="btn btn-outline">Cancel</a>
            </div>
        </form>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
