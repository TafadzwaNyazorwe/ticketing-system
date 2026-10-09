<?php
$pageTitle = 'My Profile';
require_once __DIR__ . '/config/config.php';
require_login();
$me = current_user();

$errors = [];
$success = '';
$forceChange = !empty($_GET['force_change']) || !empty($me['must_change_password']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? 'update_profile';

    if ($action === 'update_profile') {
        $fullName = trim($_POST['full_name'] ?? '');
        $phone    = trim($_POST['phone'] ?? '');
        $department = trim($_POST['department'] ?? '');

        if ($fullName === '') $errors[] = 'Full name is required.';

        if (!$errors) {
            try {
                $avatarPath = $me['avatar'];
                if (!empty($_FILES['avatar']['name'])) {
                    $avatarPath = handle_upload($_FILES['avatar'], AVATAR_DIR, ['jpg','jpeg','png']);
                }
                $stmt = db()->prepare("UPDATE users SET full_name=?, phone=?, department=?, avatar=? WHERE id=?");
                $stmt->execute([$fullName, $phone, $department, $avatarPath, $me['id']]);
                log_activity((int)$me['id'], 'profile_updated', 'Updated own profile');
                flash('success', 'Profile updated successfully.');
                redirect('profile.php');
            } catch (RuntimeException $e) {
                $errors[] = $e->getMessage();
            }
        }
    } elseif ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new1 = $_POST['new_password'] ?? '';
        $new2 = $_POST['confirm_password'] ?? '';

        if (!password_verify($current, $me['password_hash'])) {
            $errors[] = 'Your current password is incorrect.';
        } elseif (strlen($new1) < 8) {
            $errors[] = 'New password must be at least 8 characters.';
        } elseif ($new1 !== $new2) {
            $errors[] = 'New passwords do not match.';
        }

        if (!$errors) {
            $hash = password_hash($new1, PASSWORD_DEFAULT);
            db()->prepare("UPDATE users SET password_hash = ?, must_change_password = 0 WHERE id = ?")->execute([$hash, $me['id']]);
            log_activity((int)$me['id'], 'password_changed', 'Changed own password');
            flash('success', 'Password changed successfully.');
            redirect('profile.php');
        }
    }
}

// Agent performance summary
$stmt = db()->prepare("SELECT
    (SELECT COUNT(*) FROM tickets WHERE assigned_to = ?) AS total,
    (SELECT COUNT(*) FROM tickets WHERE assigned_to = ? AND status IN ('Resolved','Closed')) AS resolved,
    (SELECT COUNT(*) FROM tickets WHERE assigned_to = ? AND status NOT IN ('Resolved','Closed') AND due_at < NOW()) AS overdue");
$stmt->execute([$me['id'], $me['id'], $me['id']]);
$perf = $stmt->fetch();

require_once __DIR__ . '/includes/header.php';
?>

<section class="grid-2col grid-2col-wide">
    <div class="card">
        <div class="card-head"><h3><i class="fa-regular fa-user"></i> Profile Information</h3></div>
        <div class="card-body">
            <?php if ($errors): ?>
            <div class="flash flash-error"><i class="fa-solid fa-circle-exclamation"></i><span><?= implode('<br>', array_map('e', $errors)) ?></span></div>
            <?php endif; ?>
            <?php if ($forceChange): ?>
            <div class="flash flash-warning"><i class="fa-solid fa-triangle-exclamation"></i><span>For security, please set a new password before continuing.</span></div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data" class="stack-form">
                <input type="hidden" name="action" value="update_profile">
                <?= csrf_field() ?>
                <div class="profile-avatar-row">
                    <div class="avatar-circle avatar-lg">
                        <?php if ($me['avatar']): ?><img src="<?= BASE_URL . '/' . e($me['avatar']) ?>" alt=""><?php else: ?><?= e(strtoupper(substr($me['full_name'],0,1))) ?><?php endif; ?>
                    </div>
                    <div class="form-field">
                        <label>Profile Photo</label>
                        <input type="file" name="avatar">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-field">
                        <label>Full Name</label>
                        <input type="text" name="full_name" value="<?= e($me['full_name']) ?>" required>
                    </div>
                    <div class="form-field">
                        <label>Employee No.</label>
                        <input type="text" value="<?= e($me['employee_no'] ?? '—') ?>" disabled>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-field">
                        <label>Email</label>
                        <input type="email" value="<?= e($me['email']) ?>" disabled>
                    </div>
                    <div class="form-field">
                        <label>Phone</label>
                        <input type="text" name="phone" value="<?= e($me['phone']) ?>">
                    </div>
                </div>
                <div class="form-field">
                    <label>Department</label>
                    <input type="text" name="department" value="<?= e($me['department']) ?>">
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save Profile</button>
                </div>
            </form>
        </div>
    </div>

    <div class="stack-cards">
        <div class="card">
            <div class="card-head"><h3><i class="fa-solid fa-lock"></i> Change Password</h3></div>
            <div class="card-body">
                <form method="post" class="stack-form">
                    <input type="hidden" name="action" value="change_password">
                    <?= csrf_field() ?>
                    <div class="form-field"><label>Current Password</label><input type="password" name="current_password" required></div>
                    <div class="form-field"><label>New Password</label><input type="password" name="new_password" required minlength="8"></div>
                    <div class="form-field"><label>Confirm New Password</label><input type="password" name="confirm_password" required minlength="8"></div>
                    <div class="form-actions"><button type="submit" class="btn btn-primary"><i class="fa-solid fa-rotate"></i> Update Password</button></div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-head"><h3><i class="fa-solid fa-chart-simple"></i> My Performance</h3></div>
            <div class="card-body">
                <div class="mini-stat-row"><span>Total Assigned</span><strong><?= (int)$perf['total'] ?></strong></div>
                <div class="mini-stat-row"><span>Resolved / Closed</span><strong><?= (int)$perf['resolved'] ?></strong></div>
                <div class="mini-stat-row"><span>Currently Overdue</span><strong class="text-danger"><?= (int)$perf['overdue'] ?></strong></div>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
