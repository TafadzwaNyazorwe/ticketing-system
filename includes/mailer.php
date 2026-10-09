<?php
/**
 * =====================================================================
 * PURPLE HELPDESK :: EMAIL / PHPMAILER WRAPPER
 * All outgoing notification emails are sent through send_mail().
 * Requires: composer require phpmailer/phpmailer
 * =====================================================================
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (file_exists(BASE_PATH . '/vendor/autoload.php')) {
    require_once BASE_PATH . '/vendor/autoload.php';
} elseif (file_exists(BASE_PATH . '/libs/PHPMailer/src/PHPMailer.php')) {
    // Manual (non-Composer) fallback include
    require_once BASE_PATH . '/libs/PHPMailer/src/Exception.php';
    require_once BASE_PATH . '/libs/PHPMailer/src/PHPMailer.php';
    require_once BASE_PATH . '/libs/PHPMailer/src/SMTP.php';
}

/**
 * Send an HTML email using the SMTP settings in config/mail_config.php.
 * Returns true on success, false on failure (never throws — failures are logged
 * and never should break the page the user is on).
 */
function send_mail(string $toEmail, string $toName, string $subject, string $htmlBody): bool
{
    $cfg = require BASE_PATH . '/config/mail_config.php';

    if (empty($cfg['enabled'])) {
        return false;
    }
    if (!class_exists(PHPMailer::class)) {
        error_log('PHPMailer not installed. Run "composer install" in the project root.');
        return false;
    }

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = $cfg['host'];
        $mail->SMTPAuth   = $cfg['smtp_auth'];
        $mail->Username   = $cfg['username'];
        $mail->Password   = $cfg['password'];
        $mail->SMTPSecure = $cfg['encryption'] === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $cfg['port'];
        $mail->SMTPDebug  = $cfg['debug_level'] ?? 0;

        $mail->setFrom($cfg['from_email'], $cfg['from_name']);
        $mail->addAddress($toEmail, $toName);
        if (!empty($cfg['bcc'])) {
            $mail->addBCC($cfg['bcc']);
        }

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = email_template($subject, $htmlBody);
        $mail->AltBody = strip_tags($htmlBody);

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('Mailer Error: ' . $mail->ErrorInfo);
        return false;
    }
}

/**
 * Wraps email content in a simple formal purple-branded HTML shell.
 */
function email_template(string $title, string $bodyHtml): string
{
    $site = e(get_setting('site_name', SITE_NAME));
    $url  = BASE_URL;
    return <<<HTML
    <div style="font-family: 'Segoe UI', Arial, sans-serif; background:#f4f1fb; padding:32px;">
      <div style="max-width:560px; margin:0 auto; background:#ffffff; border-radius:10px; overflow:hidden; box-shadow:0 2px 10px rgba(76,29,149,0.08);">
        <div style="background:linear-gradient(135deg,#5B21B6,#7C3AED); padding:24px 32px;">
          <h1 style="color:#fff; font-size:20px; margin:0; letter-spacing:.3px;">{$site}</h1>
        </div>
        <div style="padding:28px 32px; color:#2E2350; font-size:14px; line-height:1.6;">
          {$bodyHtml}
        </div>
        <div style="padding:16px 32px; background:#f4f1fb; color:#8B7FA8; font-size:12px;">
          This is an automated message from {$site}. Please do not reply directly to this email.<br>
          <a href="{$url}" style="color:#7C3AED;">{$url}</a>
        </div>
      </div>
    </div>
    HTML;
}

// ---------------------------------------------------------------------
// Notification templates — one function per event, called from
// tickets/employees/ajax pages after a DB change succeeds.
// ---------------------------------------------------------------------

function notify_ticket_created(array $ticket, array $creator): void
{
    $body = "<p>Hello,</p>
        <p>A new support ticket has been logged in <strong>" . e(SITE_NAME) . "</strong>.</p>
        <table style='width:100%; border-collapse:collapse; margin:16px 0;'>
          <tr><td style='padding:6px 0; color:#8B7FA8;'>Ticket No.</td><td style='padding:6px 0;'><strong>" . e($ticket['ticket_no']) . "</strong></td></tr>
          <tr><td style='padding:6px 0; color:#8B7FA8;'>Subject</td><td style='padding:6px 0;'>" . e($ticket['subject']) . "</td></tr>
          <tr><td style='padding:6px 0; color:#8B7FA8;'>Raised by</td><td style='padding:6px 0;'>" . e($creator['full_name']) . "</td></tr>
        </table>
        <p><a href='" . BASE_URL . "/view_ticket.php?id=" . (int)$ticket['id'] . "' style='background:#7C3AED;color:#fff;padding:10px 18px;border-radius:6px;text-decoration:none;'>View Ticket</a></p>";

    // Notify all admins
    $admins = db()->query("SELECT * FROM users WHERE role = 'admin' AND status = 'active'")->fetchAll();
    foreach ($admins as $admin) {
        send_mail($admin['email'], $admin['full_name'], 'New Ticket Logged: ' . $ticket['ticket_no'], $body);
        create_notification((int)$admin['id'], 'New ticket ' . $ticket['ticket_no'], $ticket['subject'], (int)$ticket['id']);
    }
}

function notify_ticket_assigned(array $ticket, array $agent, array $assignedBy): void
{
    $body = "<p>Hello " . e($agent['full_name']) . ",</p>
        <p>You have been assigned a support ticket by <strong>" . e($assignedBy['full_name']) . "</strong>.</p>
        <table style='width:100%; border-collapse:collapse; margin:16px 0;'>
          <tr><td style='padding:6px 0; color:#8B7FA8;'>Ticket No.</td><td style='padding:6px 0;'><strong>" . e($ticket['ticket_no']) . "</strong></td></tr>
          <tr><td style='padding:6px 0; color:#8B7FA8;'>Subject</td><td style='padding:6px 0;'>" . e($ticket['subject']) . "</td></tr>
          <tr><td style='padding:6px 0; color:#8B7FA8;'>Priority</td><td style='padding:6px 0;'>" . e($ticket['priority_name'] ?? '') . "</td></tr>
        </table>
        <p><a href='" . BASE_URL . "/view_ticket.php?id=" . (int)$ticket['id'] . "' style='background:#7C3AED;color:#fff;padding:10px 18px;border-radius:6px;text-decoration:none;'>View Ticket</a></p>";

    send_mail($agent['email'], $agent['full_name'], 'Ticket Assigned To You: ' . $ticket['ticket_no'], $body);
    create_notification((int)$agent['id'], 'Ticket assigned: ' . $ticket['ticket_no'], $ticket['subject'], (int)$ticket['id']);
}

function notify_status_changed(array $ticket, string $oldStatus, string $newStatus, array $recipient): void
{
    $body = "<p>Hello " . e($recipient['full_name']) . ",</p>
        <p>The status of ticket <strong>" . e($ticket['ticket_no']) . "</strong> has changed.</p>
        <p style='font-size:16px;'>" . e($oldStatus) . " &rarr; <strong style='color:#7C3AED;'>" . e($newStatus) . "</strong></p>
        <p><a href='" . BASE_URL . "/view_ticket.php?id=" . (int)$ticket['id'] . "' style='background:#7C3AED;color:#fff;padding:10px 18px;border-radius:6px;text-decoration:none;'>View Ticket</a></p>";

    send_mail($recipient['email'], $recipient['full_name'], 'Ticket Status Updated: ' . $ticket['ticket_no'], $body);
    create_notification((int)$recipient['id'], 'Status updated: ' . $ticket['ticket_no'], "{$oldStatus} → {$newStatus}", (int)$ticket['id']);
}

function notify_followup_added(array $ticket, array $followUpAuthor, array $recipient): void
{
    $body = "<p>Hello " . e($recipient['full_name']) . ",</p>
        <p><strong>" . e($followUpAuthor['full_name']) . "</strong> added a follow-up on ticket <strong>" . e($ticket['ticket_no']) . "</strong>.</p>
        <p><a href='" . BASE_URL . "/view_ticket.php?id=" . (int)$ticket['id'] . "' style='background:#7C3AED;color:#fff;padding:10px 18px;border-radius:6px;text-decoration:none;'>View Ticket &amp; Reply</a></p>";

    send_mail($recipient['email'], $recipient['full_name'], 'New Follow-up on ' . $ticket['ticket_no'], $body);
    create_notification((int)$recipient['id'], 'Follow-up on ' . $ticket['ticket_no'], $followUpAuthor['full_name'] . ' replied', (int)$ticket['id']);
}

function notify_welcome_employee(array $user, string $plainPassword): void
{
    $body = "<p>Hello " . e($user['full_name']) . ",</p>
        <p>An account has been created for you on <strong>" . e(SITE_NAME) . "</strong>.</p>
        <table style='width:100%; border-collapse:collapse; margin:16px 0;'>
          <tr><td style='padding:6px 0; color:#8B7FA8;'>Username</td><td style='padding:6px 0;'><strong>" . e($user['username']) . "</strong></td></tr>
          <tr><td style='padding:6px 0; color:#8B7FA8;'>Temporary Password</td><td style='padding:6px 0;'><strong>" . e($plainPassword) . "</strong></td></tr>
        </table>
        <p>Please log in and change your password immediately.</p>
        <p><a href='" . BASE_URL . "/login.php' style='background:#7C3AED;color:#fff;padding:10px 18px;border-radius:6px;text-decoration:none;'>Log In</a></p>";

    send_mail($user['email'], $user['full_name'], 'Welcome to ' . SITE_NAME, $body);
}

function notify_password_reset(array $user, string $resetLink): void
{
    $body = "<p>Hello " . e($user['full_name']) . ",</p>
        <p>We received a request to reset your password. Click below to choose a new one. This link expires in 60 minutes.</p>
        <p><a href='" . e($resetLink) . "' style='background:#7C3AED;color:#fff;padding:10px 18px;border-radius:6px;text-decoration:none;'>Reset Password</a></p>
        <p>If you did not request this, you can safely ignore this email.</p>";

    send_mail($user['email'], $user['full_name'], 'Password Reset Request', $body);
}
