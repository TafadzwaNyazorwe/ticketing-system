<?php
/**
 * General purpose helper functions used across the whole application.
 */

// ---------------------------------------------------------------------
// Output / security helpers
// ---------------------------------------------------------------------
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_verify(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!$token || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(419);
        die('Your session has expired or the request could not be verified. Please go back and try again.');
    }
}

function redirect(string $path): void
{
    header('Location: ' . BASE_URL . '/' . ltrim($path, '/'));
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function get_flashes(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

// ---------------------------------------------------------------------
// Formatting helpers
// ---------------------------------------------------------------------
function format_date(?string $datetime, string $format = 'd M Y, H:i'): string
{
    if (!$datetime) return '—';
    return date($format, strtotime($datetime));
}

function time_ago(?string $datetime): string
{
    if (!$datetime) return '—';
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return 'just now';
    $units = [
        31536000 => 'year', 2592000 => 'month', 604800 => 'week',
        86400 => 'day', 3600 => 'hour', 60 => 'minute',
    ];
    foreach ($units as $secs => $label) {
        $count = floor($diff / $secs);
        if ($count >= 1) return $count . ' ' . $label . ($count > 1 ? 's' : '') . ' ago';
    }
    return 'just now';
}

function generate_ticket_no(): string
{
    $year = date('Y');
    $stmt = db()->query("SELECT MAX(id) AS max_id FROM tickets");
    $next = (int)($stmt->fetch()['max_id'] ?? 0) + 1;
    return 'TCK-' . $year . '-' . str_pad((string)$next, 5, '0', STR_PAD_LEFT);
}

function generate_employee_no(): string
{
    $stmt = db()->query("SELECT MAX(id) AS max_id FROM users");
    $next = (int)($stmt->fetch()['max_id'] ?? 0) + 1;
    return 'EMP-' . str_pad((string)$next, 4, '0', STR_PAD_LEFT);
}

function status_badge_class(string $status): string
{
    return [
        'Open'        => 'badge-open',
        'In Progress' => 'badge-progress',
        'On Hold'     => 'badge-hold',
        'Resolved'    => 'badge-resolved',
        'Closed'      => 'badge-closed',
    ][$status] ?? 'badge-open';
}

function is_ticket_overdue(array $ticket): bool
{
    if (!$ticket['due_at']) return false;
    if (in_array($ticket['status'], ['Resolved', 'Closed'], true)) return false;
    return strtotime($ticket['due_at']) < time();
}

// ---------------------------------------------------------------------
// Settings
// ---------------------------------------------------------------------
function get_setting(string $key, $default = null)
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        $stmt = db()->query("SELECT setting_key, setting_value FROM settings");
        foreach ($stmt->fetchAll() as $row) {
            $cache[$row['setting_key']] = $row['setting_value'];
        }
    }
    return $cache[$key] ?? $default;
}

// ---------------------------------------------------------------------
// Activity log
// ---------------------------------------------------------------------
function log_activity(?int $userId, string $action, string $details = ''): void
{
    $stmt = db()->prepare("INSERT INTO activity_log (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
    $stmt->execute([$userId, $action, $details, $_SERVER['REMOTE_ADDR'] ?? null]);
}

// ---------------------------------------------------------------------
// In-app notifications
// ---------------------------------------------------------------------
function create_notification(int $userId, string $title, string $message = '', ?int $ticketId = null): void
{
    $stmt = db()->prepare("INSERT INTO notifications (user_id, ticket_id, title, message) VALUES (?, ?, ?, ?)");
    $stmt->execute([$userId, $ticketId, $title, $message]);
}

function unread_notification_count(int $userId): int
{
    $stmt = db()->prepare("SELECT COUNT(*) AS c FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$userId]);
    return (int)$stmt->fetch()['c'];
}

// ---------------------------------------------------------------------
// Status history
// ---------------------------------------------------------------------
function record_status_change(int $ticketId, ?string $oldStatus, string $newStatus, ?int $changedBy, string $note = ''): void
{
    $stmt = db()->prepare("INSERT INTO ticket_status_history (ticket_id, old_status, new_status, changed_by, note) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$ticketId, $oldStatus, $newStatus, $changedBy, $note]);
}

// ---------------------------------------------------------------------
// Pagination
// ---------------------------------------------------------------------
function paginate_links(int $currentPage, int $totalPages, array $queryParams = []): string
{
    if ($totalPages <= 1) return '';
    $html = '<nav class="pagination"><ul>';
    for ($i = 1; $i <= $totalPages; $i++) {
        $queryParams['page'] = $i;
        $url = '?' . http_build_query($queryParams);
        $active = $i === $currentPage ? ' class="active"' : '';
        $html .= "<li{$active}><a href=\"" . e($url) . "\">{$i}</a></li>";
    }
    $html .= '</ul></nav>';
    return $html;
}

// ---------------------------------------------------------------------
// File upload helper
// ---------------------------------------------------------------------
function handle_upload(array $file, string $destinationDir, array $allowedExt = ['jpg','jpeg','png','pdf','docx','xlsx','zip']): ?string
{
    if (empty($file['name']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('File upload failed (error code ' . $file['error'] . ').');
    }
    if ($file['size'] > MAX_UPLOAD_SIZE) {
        throw new RuntimeException('File is too large. Maximum allowed size is 5MB.');
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) {
        throw new RuntimeException('File type not allowed: .' . $ext);
    }
    $filename = uniqid('file_', true) . '.' . $ext;
    $fullDir = BASE_PATH . '/' . rtrim($destinationDir, '/') . '/';
    if (!is_dir($fullDir)) {
        mkdir($fullDir, 0755, true);
    }
    if (!move_uploaded_file($file['tmp_name'], $fullDir . $filename)) {
        throw new RuntimeException('Could not save uploaded file.');
    }
    return rtrim($destinationDir, '/') . '/' . $filename;
}

function random_password(int $length = 10): string
{
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $password;
}
