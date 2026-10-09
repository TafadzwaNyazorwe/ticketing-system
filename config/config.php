<?php
/**
 * =====================================================================
 * PURPLE HELPDESK :: CORE CONFIGURATION
 * Edit the values below to match your hosting environment.
 * =====================================================================
 */

// ---- Environment -----------------------------------------------------
error_reporting(E_ALL);
ini_set('display_errors', '0'); // set to '1' temporarily while debugging locally
date_default_timezone_set('Africa/Harare');

// ---- Database ----------------------------------------------------------
define('DB_HOST', 'localhost');
define('DB_NAME', 'ticket');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// ---- Site ---------------------------------------------------------------
define('SITE_NAME', 'Purple Helpdesk');
define('COMPANY_NAME', 'Your Company Name');

// Base URL of the application (no trailing slash), e.g. https://intranet.yourcompany.com/ticketing-system
define('BASE_URL', 'http://localhost/ticketing-system');

// Absolute filesystem path to this project (no trailing slash)
define('BASE_PATH', dirname(__DIR__));

// Folder (relative to BASE_URL) where ticket / avatar uploads are stored
define('UPLOAD_DIR', 'uploads/tickets/');
define('AVATAR_DIR', 'assets/images/avatars/');
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5 MB

// ---- Security -------------------------------------------------------------
// Change this to a long random string unique to your installation.
define('APP_SECRET', 'change-this-to-a-long-random-string');
define('SESSION_NAME', 'purple_helpdesk_sid');

// ---- Session ---------------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}

// ---- Core includes -----------------------------------------------------
require_once BASE_PATH . '/includes/db.php';
require_once BASE_PATH . '/includes/functions.php';
require_once BASE_PATH . '/includes/auth.php';
