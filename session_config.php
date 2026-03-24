<?php

/**
 * session_config.php - Enhanced Session Management
 * Include this ONCE at the top of every page BEFORE session_start().
 * Ensures all pages share identical session cookie settings so the
 * session persists correctly across the admin_account/ folder.
 * 
 * Enhanced with:
 * - Automatic session regeneration
 * - Configurable timeout management
 * - Enhanced security settings
 * - Activity tracking
 */

if (session_status() === PHP_SESSION_NONE) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);

    session_set_cookie_params([
        'lifetime' => 0,                        // until browser closes
        'path'     => '/',                       // accessible from all paths on this host
        'domain'   => '',                        // current domain only
        'secure'   => $isHttps,                  // HTTPS only in production, HTTP ok on localhost
        'httponly' => true,                      // no JS access to cookie
        'samesite' => 'Strict',                  // Strict for better security
    ]);

    session_start();
    
    // Enhanced session security measures
    if (!isset($_SESSION['session_initialized'])) {
        $_SESSION['session_initialized'] = true;
        $_SESSION['created_at'] = time();
        $_SESSION['last_regeneration'] = time();
        $_SESSION['last_activity'] = time();
        $_SESSION['session_timeout'] = 3600; // Default 1 hour
        $_SESSION['max_session_lifetime'] = 86400; // 24 hours max
        $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? '';
        $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
    }
    
    // Regenerate session ID periodically and on critical actions
    $regenerate_interval = 1800; // 30 minutes
    if (time() - $_SESSION['last_regeneration'] > $regenerate_interval) {
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
        
        // Log session regeneration for security monitoring
        if (isset($_SESSION['user_id'])) {
            error_log("Session regenerated for user ID: " . $_SESSION['user_id'] . " from IP: " . $_SERVER['REMOTE_ADDR']);
        }
    }
    
    // Check for session hijacking attempts
    $current_ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $current_user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    if ($_SESSION['ip_address'] !== $current_ip || $_SESSION['user_agent'] !== $current_user_agent) {
        // Potential session hijacking - destroy session
        error_log("Session hijacking attempt detected. Expected IP: " . $_SESSION['ip_address'] . ", Actual: " . $current_ip);
        session_destroy();
        header('Location: ../index.php?error=session_hijack');
        exit;
    }
    
    // Check session timeout
    $timeout = $_SESSION['session_timeout'] ?? 3600;
    $last_activity = $_SESSION['last_activity'] ?? 0;
    
    if (time() - $last_activity > $timeout) {
        // Session expired due to inactivity
        session_destroy();
        header('Location: ../index.php?error=session_timeout');
        exit;
    }
    
    // Check maximum session lifetime
    $session_age = time() - $_SESSION['created_at'];
    if ($session_age > $_SESSION['max_session_lifetime']) {
        // Session expired due to age limit
        session_destroy();
        header('Location: ../index.php?error=session_expired');
        exit;
    }
    
    // Update last activity timestamp
    $_SESSION['last_activity'] = time();
    
    // Generate CSRF token if not set
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_created'] = time();
    }
    
    // Regenerate CSRF token periodically (every hour)
    if (time() - $_SESSION['csrf_token_created'] > 3600) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_created'] = time();
    }
    
    // Session cleanup - remove old session data
    if (rand(1, 100) === 1) { // 1% chance to run cleanup
        cleanupOldSessions();
    }
}

/**
 * Update session timeout
 * @param int $timeout Timeout in seconds
 */
function setSessionTimeout($timeout) {
    $_SESSION['session_timeout'] = max(300, min($timeout, 86400)); // Between 5 minutes and 24 hours
}

/**
 * Force session regeneration (use after login, role changes, etc.)
 */
function forceSessionRegeneration() {
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $_SESSION['csrf_token_created'] = time();
}

/**
 * Validate CSRF token
 * @param string $token Token to validate
 * @return bool True if token is valid
 */
function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Get current CSRF token
 * @return string CSRF token
 */
function getCSRFToken() {
    return $_SESSION['csrf_token'] ?? '';
}

/**
 * Check if user session is still valid
 * @return bool True if session is valid
 */
function isSessionValid() {
    $timeout = $_SESSION['session_timeout'] ?? 3600;
    $last_activity = $_SESSION['last_activity'] ?? 0;
    
    return (time() - $last_activity) <= $timeout;
}

/**
 * Get session information for debugging
 * @return array Session information
 */
function getSessionInfo() {
    return [
        'session_id' => session_id(),
        'created_at' => $_SESSION['created_at'] ?? null,
        'last_activity' => $_SESSION['last_activity'] ?? null,
        'last_regeneration' => $_SESSION['last_regeneration'] ?? null,
        'timeout' => $_SESSION['session_timeout'] ?? null,
        'max_lifetime' => $_SESSION['max_session_lifetime'] ?? null,
        'ip_address' => $_SESSION['ip_address'] ?? null,
        'user_agent' => $_SESSION['user_agent'] ?? null,
        'time_remaining' => ($_SESSION['session_timeout'] ?? 3600) - (time() - ($_SESSION['last_activity'] ?? 0))
    ];
}

/**
 * Clean up old session files
 * This is a simple cleanup - in production, you might want to use a more sophisticated approach
 */
function cleanupOldSessions() {
    $session_path = session_save_path();
    if (!is_dir($session_path)) {
        return;
    }
    
    $max_lifetime = 86400; // 24 hours
    $now = time();
    
    foreach (glob($session_path . '/sess_*') as $file) {
        if (is_file($file) && ($now - filemtime($file)) > $max_lifetime) {
            @unlink($file);
        }
    }
}

/**
 * Set session configuration for different user types
 * @param string $user_type User type (admin, sub-admin, etc.)
 */
function setSessionConfigForUserType($user_type) {
    switch ($user_type) {
        case 'admin':
            // Admins get longer sessions
            setSessionTimeout(7200); // 2 hours
            $_SESSION['max_session_lifetime'] = 28800; // 8 hours max
            break;
            
        case 'sub-admin':
            // Sub-admins get standard sessions
            setSessionTimeout(3600); // 1 hour
            $_SESSION['max_session_lifetime'] = 14400; // 4 hours max
            break;
            
        default:
            // Default settings
            setSessionTimeout(1800); // 30 minutes
            $_SESSION['max_session_lifetime'] = 7200; // 2 hours max
            break;
    }
}

/**
 * Log session events for security monitoring
 * @param string $event Event type
 * @param array $data Additional event data
 */
function logSessionEvent($event, $data = []) {
    $log_entry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'event' => $event,
        'session_id' => session_id(),
        'user_id' => $_SESSION['user_id'] ?? 'unknown',
        'user_type' => $_SESSION['user_type'] ?? 'unknown',
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'data' => $data
    ];
    
    $log_file = __DIR__ . '/logs/session_events.log';
    $log_dir = dirname($log_file);
    
    if (!is_dir($log_dir)) {
        @mkdir($log_dir, 0755, true);
    }
    
    file_put_contents($log_file, json_encode($log_entry) . "\n", FILE_APPEND | LOCK_EX);
}
