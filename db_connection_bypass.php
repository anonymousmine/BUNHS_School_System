<?php

/**
 * Temporary Database Connection Bypass for Railway Setup
 * This allows the app to load without database connection for initial configuration
 */

// Prevent multiple inclusions
if (!defined('DB_CONNECTION_LOADED')) {
    define('DB_CONNECTION_LOADED', true);

// ── Check if we're in bypass mode ─────────────────────────────────────────────────
$bypass_mode = getenv('DB_BYPASS') === 'true' || !getenv('DB_HOST') || getenv('DB_HOST') === 'localhost';

if ($bypass_mode) {
    error_log('DATABASE BYPASS MODE: App running without database connection');
    
    // Create a mock connection object with basic methods
    class MockDBConnection {
        public function query($sql) {
            error_log("MOCK DB Query: $sql");
            return false; // Simulate no results
        }
        
        public function prepare($sql) {
            error_log("MOCK DB Prepare: $sql");
            return new MockStatement();
        }
        
        public function real_escape_string($string) {
            return addslashes($string);
        }
        
        public function close() {
            // Do nothing
        }
        
        public function error() {
            return 'Database connection bypassed';
        }
        
        public function errno() {
            return 0;
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
            return false;
        }
        
        public function close() {
            return true;
        }
    }
    
    $conn = new MockDBConnection();
    
} else {
    // Normal database connection
    require_once __DIR__ . '/db_connection_original.php';
}

} // End of DB_CONNECTION_LOADED check
?>
