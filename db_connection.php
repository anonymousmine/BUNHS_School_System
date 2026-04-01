<?php

/**
 * BUNHS School System - Enhanced Database Connection for Railway
 * This version includes additional debugging and Railway-specific fixes
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

// ── Enhanced DB connect with Railway-specific handling ───────────────────────────
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
    
    // Railway-specific connection attempts
    $connection_attempts = [
        // Standard connection
        ['host' => $host, 'port' => $port, 'db' => $dbname],
        // Try without database first (Railway sometimes needs this)
        ['host' => $host, 'port' => $port, 'db' => null],
        // Try with socket path (some Railway setups)
        ['host' => null, 'socket' => '/tmp/mysql.sock', 'db' => $dbname],
    ];

    foreach ($connection_attempts as $attempt) {
        $conn = false;
        $error = '';
        
        try {
            if (isset($attempt['socket'])) {
                // Socket connection
                $conn = @mysqli_connect(null, $user, $pass, $attempt['db'], null, $attempt['socket']);
            } else {
                // TCP connection
                $conn = @mysqli_connect($attempt['host'], $user, $pass, $attempt['db'], $attempt['port']);
            }

            if ($conn) {
                // If we connected without database, try to select it
                if ($attempt['db'] === null) {
                    if (!mysqli_select_db($conn, $dbname)) {
                        $error = mysqli_error($conn);
                        mysqli_close($conn);
                        $conn = false;
                    }
                }
                
                if ($conn) {
                    // Set charset + error reporting
                    mysqli_set_charset($conn, 'utf8mb4');
                    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
                    
                    // Log successful connection
                    error_log('DB Connected successfully: ' . ($attempt['host'] ?? 'socket') . ':' . ($attempt['port'] ?? 'socket') . '/' . $dbname);
                    
                    return $conn;
                }
            } else {
                $error = mysqli_connect_error();
            }
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
        
        error_log('Connection attempt failed: ' . $error);
    }

    // All attempts failed
    error_log('All DB connection attempts failed. Last error: ' . $error);
    http_response_code(503);
    die('Connection failed: Database unavailable. Please check configuration and try again later.');
}
}

// ── MAIN CONNECTION ──────────────────────────────────────────────────────────
$host    = getenv('DB_HOST')    ?: 'localhost';
$db_user = getenv('DB_USER')    ?: 'root';
$db_pass = getenv('DB_PASSWORD') ?: '';
$db_name = getenv('DB_NAME')    ?: 'bunhs_db_important';
$db_port = getenv('DB_PORT')    ?: 3306;

// Railway-specific: Sometimes DB_HOST includes port
if (strpos($host, ':') !== false) {
    list($host, $port_from_host) = explode(':', $host, 2);
    if (is_numeric($port_from_host)) {
        $db_port = (int)$port_from_host;
    }
}

$conn = safe_db_connect($host, $db_user, $db_pass, $db_name, $db_port);

// ── Optional: Log success (remove in high-traffic prod) ─────────────────────
if (getenv('APP_DEBUG') === 'true') {
    error_log('DB Connected: ' . $host . ':' . $db_port . '/' . $db_name);
}

} // End of DB_CONNECTION_LOADED check
?>
