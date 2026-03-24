<?php

/**
 * CSRF Protection Helper Class
 * Provides comprehensive CSRF token generation and validation
 * 
 * @author BUNHS School System
 * @version 1.0
 */

class CSRFProtection {
    private static $tokenName = 'csrf_token';
    private static $tokenTimeName = 'csrf_token_time';
    private static $maxAge = 3600; // 1 hour
    
    /**
     * Generate a new CSRF token
     * 
     * @return string The generated token
     */
    public static function generateToken() {
        if (empty($_SESSION[self::$tokenName]) || 
            empty($_SESSION[self::$tokenTimeName]) || 
            time() - $_SESSION[self::$tokenTimeName] > self::$maxAge) {
            
            $_SESSION[self::$tokenName] = bin2hex(random_bytes(32));
            $_SESSION[self::$tokenTimeName] = time();
        }
        
        return $_SESSION[self::$tokenName];
    }
    
    /**
     * Validate a CSRF token
     * 
     * @param string $token The token to validate
     * @param int $maxAge Maximum age in seconds (optional)
     * @return bool True if valid, false otherwise
     */
    public static function validateToken($token, $maxAge = null) {
        $maxAge = $maxAge ?? self::$maxAge;
        
        // Check if token exists in session
        if (!isset($_SESSION[self::$tokenName]) || !isset($_SESSION[self::$tokenTimeName])) {
            return false;
        }
        
        // Check token age
        if (time() - $_SESSION[self::$tokenTimeName] > $maxAge) {
            self::clearToken();
            return false;
        }
        
        // Validate token using hash_equals for timing attack protection
        return hash_equals($_SESSION[self::$tokenName], $token);
    }
    
    /**
     * Regenerate the CSRF token
     * 
     * @return string The new token
     */
    public static function regenerateToken() {
        self::clearToken();
        return self::generateToken();
    }
    
    /**
     * Clear the CSRF token from session
     */
    public static function clearToken() {
        unset($_SESSION[self::$tokenName], $_SESSION[self::$tokenTimeName]);
    }
    
    /**
     * Get the current token without generating a new one
     * 
     * @return string|null The current token or null if not set
     */
    public static function getCurrentToken() {
        return $_SESSION[self::$tokenName] ?? null;
    }
    
    /**
     * Check if token exists and is not expired
     * 
     * @return bool True if token exists and is valid
     */
    public static function hasValidToken() {
        if (!isset($_SESSION[self::$tokenName]) || !isset($_SESSION[self::$tokenTimeName])) {
            return false;
        }
        
        return time() - $_SESSION[self::$tokenTimeName] <= self::$maxAge;
    }
    
    /**
     * Get HTML hidden input field for CSRF token
     * 
     * @return string HTML input field
     */
    public static function getHiddenField() {
        $token = self::generateToken();
        return '<input type="hidden" name="' . self::$tokenName . '" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }
    
    /**
     * Get meta tag for CSRF token (for AJAX requests)
     * 
     * @return string HTML meta tag
     */
    public static function getMetaTag() {
        $token = self::generateToken();
        return '<meta name="csrf-token" content="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }
    
    /**
     * Validate token from POST request
     * 
     * @return bool True if valid, false otherwise
     */
    public static function validatePostRequest() {
        $token = $_POST[self::$tokenName] ?? '';
        return self::validateToken($token);
    }
    
    /**
     * Validate token from request (POST or GET)
     * 
     * @return bool True if valid, false otherwise
     */
    public static function validateRequest() {
        $token = $_POST[self::$tokenName] ?? $_GET[self::$tokenName] ?? '';
        return self::validateToken($token);
    }
    
    /**
     * Set custom token name (for multiple forms)
     * 
     * @param string $name Custom token name
     */
    public static function setTokenName($name) {
        self::$tokenName = $name;
        self::$tokenTimeName = $name . '_time';
    }
    
    /**
     * Reset token name to default
     */
    public static function resetTokenName() {
        self::$tokenName = 'csrf_token';
        self::$tokenTimeName = 'csrf_token_time';
    }
    
    /**
     * Get token age in seconds
     * 
     * @return int Token age in seconds
     */
    public static function getTokenAge() {
        if (!isset($_SESSION[self::$tokenTimeName])) {
            return -1;
        }
        
        return time() - $_SESSION[self::$tokenTimeName];
    }
    
    /**
     * Check if token is about to expire (within 5 minutes)
     * 
     * @return bool True if token expires within 5 minutes
     */
    public static function isTokenExpiringSoon() {
        return self::getTokenAge() > (self::$maxAge - 300);
    }
}

// Auto-generate token when class is loaded
if (session_status() === PHP_SESSION_ACTIVE) {
    CSRFProtection::generateToken();
}
