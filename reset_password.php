<?php
require_once __DIR__ . '/config/config.php';

if (is_logged_in()) redirect('dashboard.php');

$token = $_GET['token'] ?? '';
$uid   = (int)($_GET['uid'] ?? 0);
$error = '';
$success = false;

function valid_reset_row(int $uid, string $token): ?array
{
    $stmt = db()->prepare("SELECT * FROM password_resets WHERE user_id = ? AND used = 0 AND expires_at >= NOW() ORDER BY id DESC LIMIT 1");
    $stmt->execute([$uid]);
    $row = $stmt->fetch();
    if ($row && hash_equals($row['token'], hash('sha256', $token))) {
        return $row;
    }
    return null;
}

$validRow = ($token && $uid) ? valid_reset_row($uid, $token) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $validRow = valid_reset_row($uid, $token);
    $p1 = $_POST['password'] ?? '';
    $p2 = $_POST['password_confirm'] ?? '';

    if (!$validRow) {
        $error = 'This reset link is invalid or has expired. Please request a new one.';
    } elseif (strlen($p1) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } elseif ($p1 !== $p2) {
        $error = 'Passwords do not match.';
    } else {
        $hash = password_hash($p1, PASSWORD_DEFAULT);
        db()->prepare("UPDATE users SET password_hash = ?, must_change_password = 0 WHERE id = ?")->execute([$hash, $uid]);
        db()->prepare("UPDATE password_resets SET used = 1 WHERE id = ?")->execute([$validRow['id']]);
        log_activity($uid, 'password_reset', 'Password reset via email link');
        $success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reset Password · <?= e(get_setting('site_name', SITE_NAME)) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body class="auth-body">
    <div class="auth-wrap auth-wrap-single">
        <div class="auth-panel-form">
            <form method="post" class="auth-card">
                <h1>Reset Password</h1>

                <?php if ($success): ?>
                    <div class="flash flash-success"><i class="fa-solid fa-circle-check"></i> <span>Your password has been reset successfully.</span></div>
                    <a href="<?= BASE_URL ?>/login.php" class="btn btn-primary btn-block"><i class="fa-solid fa-right-to-bracket"></i> Go to Sign In</a>
                <?php elseif (!$validRow): ?>
                    <div class="flash flash-error"><i class="fa-solid fa-circle-exclamation"></i> <span><?= e($error ?: 'This reset link is invalid or has expired.') ?></span></div>
                    <a href="<?= BASE_URL ?>/forgot_password.php" class="btn btn-primary btn-block">Request New Link</a>
                <?php else: ?>
                    <p class="auth-subtitle">Choose a new password for your account.</p>
                    <?php if ($error): ?>
                    <div class="flash flash-error"><i class="fa-solid fa-circle-exclamation"></i> <span><?= e($error) ?></span></div>
                    <?php endif; ?>
                    <?= csrf_field() ?>
                    <label class="field-label">New Password</label>
                    <div class="input-icon"><i class="fa-solid fa-lock"></i><input type="password" name="password" required minlength="8"></div>
                    <label class="field-label">Confirm New Password</label>
                    <div class="input-icon"><i class="fa-solid fa-lock"></i><input type="password" name="password_confirm" required minlength="8"></div>
                    <button type="submit" class="btn btn-primary btn-block"><i class="fa-solid fa-rotate"></i> Reset Password</button>
                <?php endif; ?>
            </form>
        </div>
    </div>
</body>
</html>
