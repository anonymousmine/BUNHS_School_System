<?php

/**
 * auth_helper.php - Centralized Authentication Helper
 * Provides unified authentication and authorization functions
 * for both admin and sub-admin accounts
 */

if (!defined('BUNHS_ADMIN_ACCOUNT')) {
    define('BUNHS_ADMIN_ACCOUNT', true);
}

class AuthHelper {
    
    /**
     * Validate current user session
     * @param mysqli|null $conn Database connection
     * @param string|null $required_role Specific role permission required
     * @return array Validation result with validity status and reason
     */
    public static function validateSession($conn = null, $required_role = null) {
        // Check if session exists
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type'])) {
            return ['valid' => false, 'reason' => 'no_session', 'redirect' => '../index.php'];
        }
        
        $user_type = $_SESSION['user_type'];
        $user_id = $_SESSION['user_id'];
        
        // Validate user type
        if (!in_array($user_type, ['admin', 'sub-admin'])) {
            session_destroy();
            return ['valid' => false, 'reason' => 'invalid_role', 'redirect' => '../index.php'];
        }
        
        // Validate user still exists and is active in database
        if ($conn && $conn->ping()) {
            try {
                $table = $user_type === 'admin' ? 'admin' : 'sub_admin';
                $status_condition = $user_type === 'admin' ? '1' : 'status = "approved"';
                
                // Use correct column names based on table
                if ($user_type === 'admin') {
                    $query = "SELECT id, username FROM $table WHERE id = ? LIMIT 1";
                } else {
                    $query = "SELECT id, username, email, status FROM $table WHERE id = ? AND $status_condition LIMIT 1";
                }
                
                $stmt = $conn->prepare($query);
                if (!$stmt) {
                    throw new Exception("Database query preparation failed");
                }
                
                $stmt->bind_param('i', $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows === 0) {
                    $stmt->close();
                    session_destroy();
                    return ['valid' => false, 'reason' => 'user_not_found', 'redirect' => '../index.php'];
                }
                
                $user_data = $result->fetch_assoc();
                $stmt->close();
                
                // Store user data in session for quick access
                $_SESSION['user_data'] = $user_data;
                
                // Check role-specific permissions if required
                if ($required_role && $user_type === 'sub-admin') {
                    return self::validateRolePermission($conn, $user_id, $required_role);
                }
                
                // Update last activity
                $_SESSION['last_activity'] = time();
                
                return ['valid' => true, 'user_data' => $user_data];
                
            } catch (Exception $e) {
                error_log("Auth validation error: " . $e->getMessage());
                return ['valid' => false, 'reason' => 'validation_error', 'redirect' => '../index.php'];
            }
        } else {
            // If no database connection, skip database validation but still check session
            return ['valid' => true, 'user_data' => []];
        }
    }
    
    /**
     * Validate if sub-admin has specific role permission
     * @param mysqli $conn Database connection
     * @param int $user_id User ID
     * @param string $required_permission Required permission
     * @return array Validation result
     */
    private static function validateRolePermission($conn, $user_id, $required_permission) {
        try {
            $query = "SELECT role, permissions FROM sub_admin WHERE id = ? LIMIT 1";
            $stmt = $conn->prepare($query);
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                $stmt->close();
                return ['valid' => false, 'reason' => 'role_not_found'];
            }
            
            $role_data = $result->fetch_assoc();
            $stmt->close();
            
            // Store role data in session
            $_SESSION['subadmin_role'] = $role_data['role'];
            $_SESSION['subadmin_permissions'] = $role_data['permissions'];
            
            // Check if user has required permission
            if (PermissionManager::hasPermission($user_id, $required_permission)) {
                return ['valid' => true];
            } else {
                return ['valid' => false, 'reason' => 'insufficient_permissions'];
            }
            
        } catch (Exception $e) {
            error_log("Role validation error: " . $e->getMessage());
            return ['valid' => false, 'reason' => 'role_validation_error'];
        }
    }
    
    /**
     * Require authentication - redirect if not authenticated
     * @param mysqli|null $conn Database connection
     * @param string|null $required_role Specific role permission required
     * @return array User data if authenticated
     */
    public static function requireAuth($conn = null, $required_role = null) {
        $validation = self::validateSession($conn, $required_role);
        
        if (!$validation['valid']) {
            if (isset($validation['redirect'])) {
                header('Location: ' . $validation['redirect']);
                exit;
            } else {
                http_response_code(403);
                echo json_encode(['error' => $validation['reason']]);
                exit;
            }
        }
        
        return $validation['user_data'] ?? [];
    }
    
    /**
     * Check if current user is admin
     * @return bool
     */
    public static function isAdmin() {
        return ($_SESSION['user_type'] ?? '') === 'admin';
    }
    
    /**
     * Check if current user is sub-admin
     * @return bool
     */
    public static function isSubAdmin() {
        return ($_SESSION['user_type'] ?? '') === 'sub-admin';
    }
    
    /**
     * Get current user role
     * @return string
     */
    public static function getCurrentRole() {
        if (self::isAdmin()) {
            return 'admin';
        } elseif (self::isSubAdmin()) {
            return $_SESSION['subadmin_role'] ?? 'sub_admin';
        }
        return 'guest';
    }
    
    /**
     * Get current user ID
     * @return int
     */
    public static function getCurrentUserId() {
        return (int)($_SESSION['user_id'] ?? 0);
    }
    
    /**
     * Logout user and destroy session
     * @param mysqli|null $conn Database connection (optional)
     */
    public static function logout($conn = null) {
        // Log logout activity if user was authenticated and database is available
        if (isset($_SESSION['user_id']) && $conn) {
            self::logActivity($conn, $_SESSION['user_id'], 'logout', 'User logged out');
        }
        
        session_destroy();
        header('Location: ../index.php');
        exit;
    }
    
    /**
     * Log user activity
     * @param mysqli $conn Database connection
     * @param int $user_id User ID
     * @param string $action Action performed
     * @param string $description Action description
     */
    public static function logActivity($conn, $user_id, $action, $description) {
        if (!$conn || !$conn->ping()) {
            return;
        }
        
        try {
            $query = "INSERT INTO admin_logs (admin_id, action, description, created_at) VALUES (?, ?, ?, NOW())";
            $stmt = $conn->prepare($query);
            if ($stmt) {
                $stmt->bind_param('iss', $user_id, $action, $description);
                $stmt->execute();
                $stmt->close();
            }
        } catch (Exception $e) {
            error_log("Activity logging error: " . $e->getMessage());
        }
    }
    
    /**
     * Check session timeout
     * @return bool True if session is still valid
     */
    public static function checkSessionTimeout() {
        $timeout = $_SESSION['session_timeout'] ?? 3600; // Default 1 hour
        $last_activity = $_SESSION['last_activity'] ?? 0;
        
        if (time() - $last_activity > $timeout) {
            self::logout();
            return false;
        }
        
        // Update last activity
        $_SESSION['last_activity'] = time();
        return true;
    }
    
    /**
     * Regenerate session ID for security
     */
    public static function regenerateSession() {
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    }
}

// Auto-check session timeout on every include
if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['user_id'])) {
    AuthHelper::checkSessionTimeout();
}
