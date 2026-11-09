<?php
declare(strict_types=1);

/*
 * Local configuration. For deployment, use environment variables or a secret manager.
 */
define('APP_NAME', 'FoodRescue Bangladesh');
// Auto-detect the URL path of the public/ folder so it works from any XAMPP folder name.
$__base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
define('BASE_URL', $__base === '.' ? '' : $__base);
unset($__base);
define('APP_TIMEZONE', 'Asia/Dhaka');
date_default_timezone_set(APP_TIMEZONE);

// Baseline browser security headers; deployments should additionally enforce HTTPS.
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(self)');
}

define('DB_HOST', getenv('FOODRESCUE_DB_HOST') ?: '127.0.0.1');
define('DB_NAME', getenv('FOODRESCUE_DB_NAME') ?: 'foodrescue_bd');
define('DB_USER', getenv('FOODRESCUE_DB_USER') ?: 'root');
define('DB_PASS', getenv('FOODRESCUE_DB_PASS') !== false ? (string)getenv('FOODRESCUE_DB_PASS') : '');

define('UPLOAD_DIR', dirname(__DIR__) . '/public/uploads');
define('DOC_DIR', dirname(__DIR__) . '/private_docs'); // verification documents, never served directly
define('MAX_UPLOAD_BYTES', 3 * 1024 * 1024);

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params([
        'httponly' => true,
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || strtolower((string)getenv('FOODRESCUE_FORCE_SECURE_COOKIE')) === 'true',
        'samesite' => 'Lax',
        'path' => '/',
    ]);
    session_start();
}
