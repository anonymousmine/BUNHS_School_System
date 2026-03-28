<?php

/**
 * BUNHS School System - Database Connection (Railway-safe)
 * Added mysqli extension check + fallback for deployment safety
 */

// Prevent multiple inclusions
if (!defined('DB_CONNECTION_LOADED')) {
    define('DB_CONNECTION_LOADED', true);

// ── Safe mysqli check ─────────────────────────────────────────────────────────
if (!function_exists('check_mysqli_loaded')) {
function check_mysqli_loaded()
{
    if (!function_exists('mysqli_connect')) {
        error_log('FATAL: mysqli extension not loaded. Check php -m | grep mysqli');
        http_response_code(500);
        die('Database Error: MySQLi extension required. Contact administrator.');
    }
    return true;
}
}

// ── Safe DB connect with validation ───────────────────────────────────────────
if (!function_exists('safe_db_connect')) {
function safe_db_connect($host, $user, $pass, $dbname, $port = null)
{
    check_mysqli_loaded();

    if (empty($host) || empty($user) || empty($dbname)) {
        error_log('DB config missing: HOST=' . ($host ?? 'MISSING') . ', USER=' . ($user ?? 'MISSING') . ', DBNAME=' . ($dbname ?? 'MISSING'));
        http_response_code(500);
        die('Database Error: Missing configuration. Check environment variables.');
    }

    $port = $port ?: 3306;
    $conn = @mysqli_connect($host, $user, $pass, $dbname, $port);

    if (!$conn) {
        $error = mysqli_connect_error();
        error_log('DB Connection failed: ' . $error);
        http_response_code(500);
        die('Connection failed: Database unavailable. Please try again later.');
    }

    // Set charset + error reporting
    mysqli_set_charset($conn, 'utf8mb4');
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    return $conn;
}
}

// ── MAIN CONNECTION ──────────────────────────────────────────────────────────
$host    = getenv('DB_HOST')    ?: 'localhost';
$db_user = getenv('DB_USER')    ?: 'root';
$db_pass = getenv('DB_PASSWORD') ?: '';
$db_name = getenv('DB_NAME')    ?: 'bunhs_db_important';
$db_port = getenv('DB_PORT')    ?: null;

$conn = safe_db_connect($host, $db_user, $db_pass, $db_name, $db_port);

// ── Optional: Log success (remove in high-traffic prod) ─────────────────────
if (getenv('APP_DEBUG') === 'true') {
    error_log('DB Connected: ' . $host . ':' . ($db_port ?: 3306) . '/' . $db_name);
}

} // End of DB_CONNECTION_LOADED check
