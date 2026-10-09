<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/mailer.php';

if (is_logged_in()) redirect('dashboard.php');

$message = '';
$messageType = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $email = trim($_POST['email'] ?? '');

    if ($email !== '') {
        $stmt = db()->prepare("SELECT * FROM users WHERE email = ? AND status = 'active'");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // Always show the same message (don't reveal whether an email exists)
        $message = 'If that email address is registered, a password reset link has been sent.';
        $messageType = 'success';

        if ($user) {
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+60 minutes'));
            $stmt = db()->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)");
            $stmt->execute([$user['id'], hash('sha256', $token), $expires]);

            $link = BASE_URL . '/reset_password.php?token=' . $token . '&uid=' . $user['id'];
            notify_password_reset($user, $link);
            log_activity((int)$user['id'], 'password_reset_requested', 'Password reset link requested');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Forgot Password · <?= e(get_setting('site_name', SITE_NAME)) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body class="auth-body">
    <div class="auth-wrap auth-wrap-single">
        <div class="auth-panel-form">
            <form method="post" class="auth-card">
                <h1>Forgot Password</h1>
                <p class="auth-subtitle">Enter your work email and we'll send you a secure reset link.</p>

                <?php if ($message): ?>
                <div class="flash flash-<?= e($messageType) ?>"><i class="fa-solid fa-circle-info"></i> <span><?= e($message) ?></span></div>
                <?php endif; ?>

                <?= csrf_field() ?>
                <label class="field-label">Email Address</label>
                <div class="input-icon">
                    <i class="fa-regular fa-envelope"></i>
                    <input type="email" name="email" placeholder="you@company.com" required autofocus>
                </div>

                <button type="submit" class="btn btn-primary btn-block">
                    <i class="fa-solid fa-paper-plane"></i> Send Reset Link
                </button>

                <p class="auth-footnote"><a href="<?= BASE_URL ?>/login.php"><i class="fa-solid fa-arrow-left"></i> Back to Sign In</a></p>
            </form>
        </div>
    </div>
</body>
</html>
