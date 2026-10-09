<?php
require_once __DIR__ . '/config/config.php';

if (is_logged_in()) {
    redirect('dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Please enter both your username/email and password.';
    } else {
        $user = attempt_login($username, $password);
        if ($user) {
            login_user($user);
            redirect($user['must_change_password'] ? 'profile.php?force_change=1' : 'dashboard.php');
        } else {
            $error = 'Invalid credentials, or your account is inactive. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sign In · <?= e(get_setting('site_name', SITE_NAME)) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body class="auth-body">
    <div class="auth-wrap">
        <div class="auth-panel-brand">
            <!-- TODO: Add a hero / illustration image here, e.g. -->
            <!-- <img src="<?= BASE_URL ?>/assets/images/login-illustration.png" alt=""> -->
            <div class="brand-mark brand-mark-lg"><i class="fa-solid fa-headset"></i></div>
            <h2><?= e(get_setting('site_name', SITE_NAME)) ?></h2>
            <p>A formal, centralized support desk for logging, tracking and resolving issues across every branch — with real-time analytics, follow-ups and instant email notifications.</p>
            <ul class="auth-feature-list">
                <li><i class="fa-solid fa-check"></i> Multi-branch ticket tracking</li>
                <li><i class="fa-solid fa-check"></i> Role-based access for agents &amp; admins</li>
                <li><i class="fa-solid fa-check"></i> Live analytics &amp; SLA monitoring</li>
                <li><i class="fa-solid fa-check"></i> Automated email notifications</li>
            </ul>
        </div>

        <div class="auth-panel-form">
            <form method="post" class="auth-card" autocomplete="off">
                <h1>Welcome Back</h1>
                <p class="auth-subtitle">Sign in to manage tickets and track support performance.</p>

                <?php if ($error): ?>
                <div class="flash flash-error"><i class="fa-solid fa-circle-exclamation"></i> <span><?= e($error) ?></span></div>
                <?php endif; ?>

                <?= csrf_field() ?>

                <label class="field-label">Username or Email</label>
                <div class="input-icon">
                    <i class="fa-regular fa-user"></i>
                    <input type="text" name="username" placeholder="e.g. admin" required autofocus>
                </div>

                <label class="field-label">Password</label>
                <div class="input-icon">
                    <i class="fa-solid fa-lock"></i>
                    <input type="password" name="password" placeholder="••••••••" required>
                </div>

                <div class="auth-row">
                    <label class="checkbox-inline"><input type="checkbox" name="remember"> Remember me</label>
                    <a href="<?= BASE_URL ?>/forgot_password.php">Forgot password?</a>
                </div>

                <button type="submit" class="btn btn-primary btn-block">
                    <i class="fa-solid fa-right-to-bracket"></i> Sign In
                </button>

                <p class="auth-footnote">Default admin login: <strong>admin</strong> / <strong>Admin@123</strong> (change immediately after setup)</p>
            </form>
        </div>
    </div>
</body>
</html>
