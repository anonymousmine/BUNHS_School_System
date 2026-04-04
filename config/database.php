<?php
/**
 * Railway-Integrated Database Configuration
 * This replaces local_db_config.php for Railway deployment
 * Uses Railway's built-in MySQL service with proper environment detection
 */

// ── Railway Environment Detection ───────────────────────────────────────
$is_railway = isset($_SERVER['RAILWAY_ENVIRONMENT']) || 
               isset($_SERVER['RAILWAY_SERVICE_NAME']) ||
               (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && !empty($_SERVER['HTTP_X_FORWARDED_FOR']));

if ($is_railway) {
    // Railway environment - use Railway MySQL service variables
    $db_host = getenv('MYSQLHOST') ?: getenv('DB_HOST');
    $db_user = getenv('MYSQLUSER') ?: getenv('DB_USER');
    $db_password = getenv('MYSQLPASSWORD') ?: getenv('DB_PASSWORD');
    $db_name = getenv('MYSQLDATABASE') ?: getenv('DB_NAME') ?: 'bunhs_db_important';
    $db_port = getenv('MYSQLPORT') ?: getenv('DB_PORT') ?: '3306';
    
    // Fallback to other Railway variable formats
    if (!$db_host || $db_host === 'localhost') {
        $db_host = getenv('MYSQL_HOST') ?: getenv('DB_HOST');
        $db_user = getenv('MYSQL_USER') ?: getenv('DB_USER');
        $db_password = getenv('MYSQL_PASSWORD') ?: getenv('DB_PASSWORD');
        $db_name = getenv('MYSQL_DATABASE') ?: getenv('DB_NAME') ?: 'bunhs_db_important';
        $db_port = getenv('MYSQL_PORT') ?: getenv('DB_PORT') ?: '3306';
    }
    
    error_log("[RAILWAY DB] Using Railway MySQL service: {$db_host}:{$db_port}/{$db_name}");
    
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

// ── Debug Information ─────────────────────────────────────────────────────
error_log("[DB CONFIG] Environment: " . ($is_railway ? 'Railway' : 'Local'));
error_log("[DB CONFIG] Host: {$db_host}");
error_log("[DB CONFIG] User: {$db_user}");
error_log("[DB CONFIG] Database: {$db_name}");
error_log("[DB CONFIG] Port: {$db_port}");

?>
