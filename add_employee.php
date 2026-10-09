<?php
$pageTitle = 'Add Employee';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/mailer.php';
require_admin();

$branches = db()->query("SELECT * FROM branches WHERE status='active' ORDER BY name")->fetchAll();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $fullName = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $role     = $_POST['role'] ?? 'agent';
    $department = trim($_POST['department'] ?? '');
    $branchId = $_POST['branch_id'] ?: null;

    if ($fullName === '') $errors[] = 'Full name is required.';
    if ($username === '') $errors[] = 'Username is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email address is required.';
    if (!in_array($role, ['admin', 'agent'], true)) $errors[] = 'Invalid role.';

    if (!$errors) {
        $dupe = db()->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $dupe->execute([$username, $email]);
        if ($dupe->fetch()) $errors[] = 'That username or email is already in use.';
    }

    if (!$errors) {
        $tempPassword = random_password(10);
        $hash = password_hash($tempPassword, PASSWORD_DEFAULT);
        $employeeNo = generate_employee_no();

        $stmt = db()->prepare("INSERT INTO users (employee_no, full_name, username, email, phone, password_hash, role, department, branch_id, must_change_password)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
        $stmt->execute([$employeeNo, $fullName, $username, $email, $phone, $hash, $role, $department, $branchId]);
        $newId = (int)db()->lastInsertId();

        log_activity((int)current_user()['id'], 'employee_created', "Created employee {$fullName} ({$username})");

        $newUser = ['full_name' => $fullName, 'username' => $username, 'email' => $email];
        notify_welcome_employee($newUser, $tempPassword);

        flash('success', "Employee {$fullName} was added. A welcome email with login details has been sent.");
        redirect('employees.php');
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<section class="card form-card">
    <div class="card-head"><h3><i class="fa-solid fa-user-plus"></i> Add New Employee</h3></div>
    <div class="card-body">
        <?php if ($errors): ?>
        <div class="flash flash-error"><i class="fa-solid fa-circle-exclamation"></i><span><?= implode('<br>', array_map('e', $errors)) ?></span></div>
        <?php endif; ?>

        <form method="post" class="stack-form">
            <?= csrf_field() ?>
            <div class="form-row">
                <div class="form-field">
                    <label>Full Name <span class="req">*</span></label>
                    <input type="text" name="full_name" required value="<?= e($_POST['full_name'] ?? '') ?>">
                </div>
                <div class="form-field">
                    <label>Username <span class="req">*</span></label>
                    <input type="text" name="username" required value="<?= e($_POST['username'] ?? '') ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-field">
                    <label>Email Address <span class="req">*</span></label>
                    <input type="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>">
                </div>
                <div class="form-field">
                    <label>Phone</label>
                    <input type="text" name="phone" value="<?= e($_POST['phone'] ?? '') ?>">
                </div>
            </div>
            <div class="form-row form-row-3">
                <div class="form-field">
                    <label>Role <span class="req">*</span></label>
                    <select name="role">
                        <option value="agent">Agent (handles assigned tickets)</option>
                        <option value="admin">Admin (full access)</option>
                    </select>
                </div>
                <div class="form-field">
                    <label>Department</label>
                    <input type="text" name="department" placeholder="e.g. IT Support" value="<?= e($_POST['department'] ?? '') ?>">
                </div>
                <div class="form-field">
                    <label>Branch</label>
                    <select name="branch_id">
                        <option value="">Select branch…</option>
                        <?php foreach ($branches as $b): ?>
                        <option value="<?= (int)$b['id'] ?>"><?= e($b['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <p class="field-hint"><i class="fa-solid fa-circle-info"></i> A temporary password will be generated automatically and emailed to the employee along with their username.</p>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-user-plus"></i> Create Employee</button>
                <a href="<?= BASE_URL ?>/employees.php" class="btn btn-outline">Cancel</a>
            </div>
        </form>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
