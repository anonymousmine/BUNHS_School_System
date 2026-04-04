<?php
/**
 * Simple Railway Database Configuration
 * Direct approach - if we're on Railway domain, use Railway config
 */

// Simple detection - if we're on railway.app domain, we're on Railway
$is_railway = (strpos($_SERVER['HTTP_HOST'] ?? '', 'railway.app') !== false);

error_log("[SIMPLE ENV] HTTP_HOST: " . ($_SERVER['HTTP_HOST'] ?? 'not set'));
error_log("[SIMPLE ENV] Is Railway: " . ($is_railway ? 'YES' : 'NO'));

if ($is_railway) {
    // Railway environment - use DB_* variables (they're already set correctly)
    $db_host = getenv('DB_HOST') ?: 'containers-us-west-XXX.railway.app';
    $db_user = getenv('DB_USER') ?: 'bunhs_user';
    $db_password = getenv('DB_PASSWORD') ?: '';
    $db_name = getenv('DB_NAME') ?: 'bunhs_db_important';
    $db_port = getenv('DB_PORT') ?: '3306';
    
    error_log("[RAILWAY DB] Using Railway DB config: {$db_host}:{$db_port}/{$db_name}");
    
} else {
    // Local development - use XAMPP
    $db_host = 'localhost';
    $db_user = 'root';
    $db_password = '';
    $db_name = 'bunhs_db_important';
    $db_port = '3306';
    
    error_log("[LOCAL DB] Using local XAMPP MySQL");
}

// ── Set Environment Variables for Compatibility ─────────────────────────────
putenv('DB_HOST=' . $db_host);
putenv('DB_USER=' . $db_user);
putenv('DB_PASSWORD=' . $db_password);
putenv('DB_NAME=' . $db_name);
putenv('DB_PORT=' . $db_port);

$_ENV['DB_HOST'] = $db_host;
$_ENV['DB_USER'] = $db_user;
$_ENV['DB_PASSWORD'] = $db_password;
$_ENV['DB_NAME'] = $db_name;
$_ENV['DB_PORT'] = $db_port;

$_SERVER['DB_HOST'] = $db_host;
$_SERVER['DB_USER'] = $db_user;
$_SERVER['DB_PASSWORD'] = $db_password;
$_SERVER['DB_NAME'] = $db_name;
$_SERVER['DB_PORT'] = $db_port;

// ── Database Constants ─────────────────────────────────────────────────────
define('DB_HOST', $db_host);
define('DB_USER', $db_user);
define('DB_PASSWORD', $db_password);
define('DB_NAME', $db_name);
define('DB_PORT', $db_port);

error_log("[FINAL CONFIG] Host: " . DB_HOST . ", User: " . DB_USER . ", DB: " . DB_NAME);

?>
