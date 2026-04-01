<?php

/**
 * BUNHS School System - Railway-Optimized Database Connection
 * Enhanced with multiple fallback strategies and detailed logging
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

// ── Railway-optimized DB connect with multiple strategies ───────────────────────
if (!function_exists('safe_db_connect')) {
function safe_db_connect($host, $user, $pass, $dbname, $port = null)
{
    check_mysqli_loaded();

    // Log connection attempt
    error_log("Attempting DB connection: host=$host, user=$user, db=$dbname, port=$port");

    if (empty($host) || empty($user) || empty($dbname)) {
        error_log('DB config missing: HOST=' . ($host ?? 'MISSING') . ', USER=' . ($user ?? 'MISSING') . ', DBNAME=' . ($dbname ?? 'MISSING'));
        http_response_code(500);
        die('Database Error: Missing configuration. Check environment variables.');
    }

    $port = $port ?: 3306;
    
    // Railway-specific connection strategies
    $strategies = [
        // Strategy 1: Standard TCP connection
        function() use ($host, $user, $pass, $dbname, $port) {
            error_log("Strategy 1: Standard TCP connection to $host:$port");
            $conn = @mysqli_connect($host, $user, $pass, $dbname, $port);
            if ($conn) {
                mysqli_set_charset($conn, 'utf8mb4');
                mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
                error_log("Strategy 1: SUCCESS - Connected to $host:$port/$dbname");
                return $conn;
            }
            error_log("Strategy 1: FAILED - " . mysqli_connect_error());
            return false;
        },
        
        // Strategy 2: Connect to server first, then select database
        function() use ($host, $user, $pass, $dbname, $port) {
            error_log("Strategy 2: Server-first connection to $host:$port");
            $conn = @mysqli_connect($host, $user, $pass, '', $port);
            if ($conn) {
                if (mysqli_select_db($conn, $dbname)) {
                    mysqli_set_charset($conn, 'utf8mb4');
                    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
                    error_log("Strategy 2: SUCCESS - Connected and selected $dbname");
                    return $conn;
                } else {
                    error_log("Strategy 2: FAILED - Could not select database $dbname");
                    mysqli_close($conn);
                }
            } else {
                error_log("Strategy 2: FAILED - " . mysqli_connect_error());
            }
            return false;
        },
        
        // Strategy 3: Try different port (Railway sometimes uses different ports)
        function() use ($host, $user, $pass, $dbname) {
            $alt_ports = [3306, 3307, 5432, 6379];
            foreach ($alt_ports as $alt_port) {
                error_log("Strategy 3: Trying port $alt_port");
                $conn = @mysqli_connect($host, $user, $pass, $dbname, $alt_port);
                if ($conn) {
                    mysqli_set_charset($conn, 'utf8mb4');
                    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
                    error_log("Strategy 3: SUCCESS - Connected on port $alt_port");
                    return $conn;
                }
            }
            error_log("Strategy 3: FAILED - All ports failed");
            return false;
        },
        
        // Strategy 4: Fallback to localhost (for local testing)
        function() use ($dbname) {
            error_log("Strategy 4: Localhost fallback");
            $conn = @mysqli_connect('localhost', 'root', '', $dbname);
            if ($conn) {
                mysqli_set_charset($conn, 'utf8mb4');
                mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
                error_log("Strategy 4: SUCCESS - Localhost connection");
                return $conn;
            }
            error_log("Strategy 4: FAILED - Localhost failed");
            return false;
        }
    ];

    // Try each strategy
    foreach ($strategies as $index => $strategy) {
        $conn = $strategy();
        if ($conn) {
            error_log("DB Connection successful using strategy " . ($index + 1));
            return $conn;
        }
    }

    // All strategies failed
    error_log('All DB connection strategies failed');
    http_response_code(503);
    die('Connection failed: Database unavailable after multiple connection attempts. Please check configuration and try again later.');
}
}

// ── MAIN CONNECTION ──────────────────────────────────────────────────────────
$host    = getenv('DB_HOST')    ?: 'localhost';
$db_user = getenv('DB_USER')    ?: 'root';
$db_pass = getenv('DB_PASSWORD') ?: '';
$db_name = getenv('DB_NAME')    ?: 'bunhs_db_important';
$db_port = getenv('DB_PORT')    ?: null;

// Check if environment variables are properly set (not using defaults)
$using_defaults = ($host === 'localhost' && $db_user === 'root' && empty($db_pass));

if ($using_defaults) {
    error_log('WARNING: Using default database settings - likely Railway environment variables not set');
    error_log('TREATING AS BYPASS MODE - App will load without database connection');
    
    // Create a mock connection for bypass mode
    class MockDBConnection {
        public function query($sql) {
            error_log("MOCK DB Query (bypass): $sql");
            return new MockResult();
        }
        
        public function prepare($sql) {
            error_log("MOCK DB Prepare (bypass): $sql");
            return new MockStatement();
        }
        
        public function real_escape_string($string) {
            return addslashes($string);
        }
        
        public function close() {
            // Do nothing
        }
        
        public function error() {
            return 'Database connection bypassed - Set Railway environment variables';
        }
        
        public function errno() {
            return 0;
        }
        
        public function insert_id() {
            return 0;
        }
        
        public function affected_rows() {
            return 0;
        }
    }
    
    class MockResult {
        public $num_rows = 0;
        public $field_count = 0;
        
        public function fetch_assoc() {
            return null;
        }
        
        public function fetch_row() {
            return null;
        }
        
        public function fetch_all() {
            return [];
        }
        
        public function free() {
            // Do nothing
        }
    }
    
    class MockStatement {
        public function bind_param($types, ...$params) {
            return true;
        }
        
        public function execute() {
            return true;
        }
        
        public function get_result() {
            return new MockResult();
        }
        
        public function fetch_assoc() {
            return null;
        }
        
        public function fetch($mode = null) {
            return null;
        }
        
        public function fetchAll($mode = null) {
            return [];
        }
        
        public function close() {
            return true;
        }
    }
    
    $conn = new MockDBConnection();
    
} else {
    // Railway-specific: Parse host if it includes port
    if (strpos($host, ':') !== false) {
        list($host, $port_from_host) = explode(':', $host, 2);
        if (is_numeric($port_from_host)) {
            $db_port = (int)$port_from_host;
        }
    }

    // Log final connection parameters
    error_log("Final DB parameters: host=$host, user=$db_user, db=$db_name, port=$db_port");

    $conn = safe_db_connect($host, $db_user, $db_pass, $db_name, $db_port);
}

// ── Optional: Log success (remove in high-traffic prod) ─────────────────────
if (getenv('APP_DEBUG') === 'true') {
    error_log('DB Connected successfully: ' . $host . ':' . ($db_port ?: 3306) . '/' . $db_name);
}

} // End of DB_CONNECTION_LOADED check
?>
