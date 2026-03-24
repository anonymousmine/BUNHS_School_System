<?php

/**
 * database_helper.php - Enhanced Database Helper Class
 * Provides centralized database operations with proper error handling,
 * logging, and connection management
 */

if (!defined('BUNHS_ADMIN_ACCOUNT')) {
    define('BUNHS_ADMIN_ACCOUNT', true);
}

class DatabaseHelper {
    private $conn;
    private $last_error;
    private $query_log = [];
    private $log_queries = false;
    
    public function __construct($conn, $log_queries = false) {
        $this->conn = $conn;
        $this->log_queries = $log_queries;
        
        // Verify connection is valid
        if (!$conn || !$conn->ping()) {
            throw new Exception("Database connection is not valid");
        }
    }
    
    /**
     * Execute a prepared statement with proper error handling
     * @param string $sql SQL query with placeholders
     * @param array $params Parameters for binding
     * @param string $types Parameter types (i, s, d, b)
     * @return mysqli_stmt Prepared statement object
     * @throws Exception On database error
     */
    public function executeQuery($sql, $params = [], $types = '') {
        $start_time = microtime(true);
        
        try {
            $stmt = $this->conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Query preparation failed: " . $this->conn->error);
            }
            
            if (!empty($params)) {
                if (empty($types)) {
                    $types = $this->inferParameterTypes($params);
                }
                
                $bind_result = $stmt->bind_param($types, ...$params);
                if (!$bind_result) {
                    throw new Exception("Parameter binding failed: " . $stmt->error);
                }
            }
            
            $execute_result = $stmt->execute();
            if (!$execute_result) {
                throw new Exception("Query execution failed: " . $stmt->error);
            }
            
            // Log query if enabled
            if ($this->log_queries) {
                $execution_time = microtime(true) - $start_time;
                $this->logQuery($sql, $params, $execution_time);
            }
            
            return $stmt;
            
        } catch (Exception $e) {
            $this->last_error = $e->getMessage();
            $this->logError($e->getMessage(), $sql, $params);
            throw $e;
        }
    }
    
    /**
     * Fetch multiple rows as associative array
     * @param string $sql SQL query
     * @param array $params Query parameters
     * @param string $types Parameter types
     * @return array Array of result rows
     */
    public function fetchAll($sql, $params = [], $types = '') {
        $stmt = $this->executeQuery($sql, $params, $types);
        $result = $stmt->get_result();
        
        if (!$result) {
            $stmt->close();
            throw new Exception("Failed to get result set: " . $stmt->error);
        }
        
        $data = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        return $data;
    }
    
    /**
     * Fetch single row as associative array
     * @param string $sql SQL query
     * @param array $params Query parameters
     * @param string $types Parameter types
     * @return array|null Single result row or null
     */
    public function fetchOne($sql, $params = [], $types = '') {
        $stmt = $this->executeQuery($sql, $params, $types);
        $result = $stmt->get_result();
        
        if (!$result) {
            $stmt->close();
            throw new Exception("Failed to get result set: " . $stmt->error);
        }
        
        $data = $result->fetch_assoc();
        $stmt->close();
        
        return $data;
    }
    
    /**
     * Fetch single value from first column of first row
     * @param string $sql SQL query
     * @param array $params Query parameters
     * @param string $types Parameter types
     * @return mixed Single value
     */
    public function fetchValue($sql, $params = [], $types = '') {
        $row = $this->fetchOne($sql, $params, $types);
        return $row ? array_values($row)[0] : null;
    }
    
    /**
     * Insert record and return last insert ID
     * @param string $table Table name
     * @param array $data Associative array of column => value pairs
     * @return int Last insert ID
     */
    public function insert($table, $data) {
        $columns = array_keys($data);
        $values = array_values($data);
        
        $placeholders = str_repeat('?,', count($values) - 1) . '?';
        $sql = "INSERT INTO `$table` (`" . implode('`, `', $columns) . "`) VALUES ($placeholders)";
        
        $types = $this->inferParameterTypes($values);
        
        $stmt = $this->executeQuery($sql, $values, $types);
        $insert_id = $stmt->insert_id;
        $stmt->close();
        
        return $insert_id;
    }
    
    /**
     * Update record
     * @param string $table Table name
     * @param array $data Associative array of column => value pairs
     * @param string $where WHERE clause
     * @param array $where_params WHERE clause parameters
     * @param string $where_types WHERE parameter types
     * @return int Number of affected rows
     */
    public function update($table, $data, $where, $where_params = [], $where_types = '') {
        $set_clauses = [];
        $values = [];
        
        foreach ($data as $column => $value) {
            $set_clauses[] = "`$column` = ?";
            $values[] = $value;
        }
        
        $sql = "UPDATE `$table` SET " . implode(', ', $set_clauses) . " WHERE $where";
        
        $all_params = array_merge($values, $where_params);
        $types = $this->inferParameterTypes($values) . $where_types;
        
        $stmt = $this->executeQuery($sql, $all_params, $types);
        $affected_rows = $stmt->affected_rows;
        $stmt->close();
        
        return $affected_rows;
    }
    
    /**
     * Delete record
     * @param string $table Table name
     * @param string $where WHERE clause
     * @param array $params WHERE clause parameters
     * @param string $types Parameter types
     * @return int Number of affected rows
     */
    public function delete($table, $where, $params = [], $types = '') {
        $sql = "DELETE FROM `$table` WHERE $where";
        
        $stmt = $this->executeQuery($sql, $params, $types);
        $affected_rows = $stmt->affected_rows;
        $stmt->close();
        
        return $affected_rows;
    }
    
    /**
     * Check if record exists
     * @param string $table Table name
     * @param string $where WHERE clause
     * @param array $params WHERE clause parameters
     * @param string $types Parameter types
     * @return bool True if record exists
     */
    public function exists($table, $where, $params = [], $types = '') {
        $sql = "SELECT 1 FROM `$table` WHERE $where LIMIT 1";
        $result = $this->fetchValue($sql, $params, $types);
        return $result !== null;
    }
    
    /**
     * Get count of records
     * @param string $table Table name
     * @param string $where WHERE clause (optional)
     * @param array $params WHERE clause parameters
     * @param string $types Parameter types
     * @return int Record count
     */
    public function count($table, $where = '1', $params = [], $types = '') {
        $sql = "SELECT COUNT(*) FROM `$table` WHERE $where";
        return (int)$this->fetchValue($sql, $params, $types);
    }
    
    /**
     * Begin transaction
     */
    public function beginTransaction() {
        if (!$this->conn->begin_transaction()) {
            throw new Exception("Failed to begin transaction: " . $this->conn->error);
        }
    }
    
    /**
     * Commit transaction
     */
    public function commit() {
        if (!$this->conn->commit()) {
            throw new Exception("Failed to commit transaction: " . $this->conn->error);
        }
    }
    
    /**
     * Rollback transaction
     */
    public function rollback() {
        if (!$this->conn->rollback()) {
            throw new Exception("Failed to rollback transaction: " . $this->conn->error);
        }
    }
    
    /**
     * Get last database error
     * @return string Last error message
     */
    public function getLastError() {
        return $this->last_error;
    }
    
    /**
     * Get query log (if logging enabled)
     * @return array Query log entries
     */
    public function getQueryLog() {
        return $this->query_log;
    }
    
    /**
     * Clear query log
     */
    public function clearQueryLog() {
        $this->query_log = [];
    }
    
    /**
     * Infer parameter types from values
     * @param array $params Parameter values
     * @return string Type string for bind_param
     */
    private function inferParameterTypes($params) {
        $types = '';
        
        foreach ($params as $param) {
            if (is_int($param)) {
                $types .= 'i';
            } elseif (is_float($param)) {
                $types .= 'd';
            } elseif (is_bool($param)) {
                $types .= 'i'; // Convert boolean to integer
            } else {
                $types .= 's'; // Default to string
            }
        }
        
        return $types;
    }
    
    /**
     * Log database error
     * @param string $error Error message
     * @param string $sql SQL query
     * @param array $params Query parameters
     */
    private function logError($error, $sql, $params) {
        $log_entry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'error' => $error,
            'sql' => $sql,
            'params' => $params,
            'user_id' => $_SESSION['user_id'] ?? 'unknown',
            'user_type' => $_SESSION['user_type'] ?? 'unknown',
            'page' => $_SERVER['PHP_SELF'] ?? 'unknown',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ];
        
        // Log to file
        $log_file = __DIR__ . '/../logs/database_errors.log';
        $log_dir = dirname($log_file);
        
        if (!is_dir($log_dir)) {
            @mkdir($log_dir, 0755, true);
        }
        
        file_put_contents($log_file, json_encode($log_entry) . "\n", FILE_APPEND | LOCK_EX);
        
        // Also log to PHP error log for critical errors
        error_log("Database Error: $error in $sql on page " . ($_SERVER['PHP_SELF'] ?? 'unknown'));
    }
    
    /**
     * Log query for debugging
     * @param string $sql SQL query
     * @param array $params Query parameters
     * @param float $execution_time Query execution time
     */
    private function logQuery($sql, $params, $execution_time) {
        $this->query_log[] = [
            'timestamp' => date('Y-m-d H:i:s'),
            'sql' => $sql,
            'params' => $params,
            'execution_time' => round($execution_time * 1000, 2) . 'ms',
            'page' => $_SERVER['PHP_SELF'] ?? 'unknown'
        ];
        
        // Keep only last 100 queries to prevent memory issues
        if (count($this->query_log) > 100) {
            $this->query_log = array_slice($this->query_log, -100);
        }
    }
    
    /**
     * Escape string for use in SQL (fallback for dynamic queries)
     * @param string $string String to escape
     * @return string Escaped string
     */
    public function escape($string) {
        return $this->conn->real_escape_string($string);
    }
    
    /**
     * Get database connection
     * @return mysqli Database connection
     */
    public function getConnection() {
        return $this->conn;
    }
    
    /**
     * Check if connection is alive
     * @return bool True if connection is alive
     */
    public function isConnected() {
        return $this->conn && $this->conn->ping();
    }
    
    /**
     * Reconnect if connection is lost
     * @return bool True if reconnection successful
     */
    public function reconnect() {
        try {
            if (!$this->isConnected()) {
                // Attempt to reconnect (you may need to implement this based on your connection setup)
                return false;
            }
            return true;
        } catch (Exception $e) {
            $this->logError("Reconnection failed: " . $e->getMessage(), "", []);
            return false;
        }
    }
    
    /**
     * Get table status information
     * @param string $table Table name
     * @return array Table status
     */
    public function getTableStatus($table) {
        $sql = "SHOW TABLE STATUS LIKE ?";
        $result = $this->fetchAll($sql, [$table], 's');
        return $result[0] ?? [];
    }
    
    /**
     * Optimize table
     * @param string $table Table name
     * @return bool True if optimization successful
     */
    public function optimizeTable($table) {
        try {
            $sql = "OPTIMIZE TABLE `$table`";
            $this->executeQuery($sql);
            return true;
        } catch (Exception $e) {
            $this->logError("Table optimization failed: " . $e->getMessage(), $sql, []);
            return false;
        }
    }
    
    /**
     * Backup table data to array
     * @param string $table Table name
     * @param string|null $where WHERE clause (optional)
     * @param array $params WHERE parameters
     * @param string $types Parameter types
     * @return array Table data
     */
    public function backupTable($table, $where = null, $params = [], $types = '') {
        $sql = "SELECT * FROM `$table`";
        if ($where) {
            $sql .= " WHERE $where";
        }
        
        return $this->fetchAll($sql, $params, $types);
    }
    
    /**
     * Close database connection
     */
    public function close() {
        if ($this->conn) {
            $this->conn->close();
        }
    }
    
    /**
     * Destructor - ensure connection is closed
     */
    public function __destruct() {
        $this->close();
    }
}
