<?php

/**
 * validation_helper.php - Enhanced Input Validation and Sanitization
 * Provides comprehensive input validation, sanitization, and security checks
 * for the BUNHS School System admin panel
 * 
 * Enhanced with:
 * - Settings-specific validation rules
 * - Role-aware validation
 * - CSRF token validation
 * - File upload security
 * - Comprehensive security checks
 */

if (!defined('BUNHS_ADMIN_ACCOUNT')) {
    define('BUNHS_ADMIN_ACCOUNT', true);
}

class ValidationHelper {
    
    /**
     * Allowed settings keys with their validation rules
     */
    private static $settings_rules = [
        'theme' => ['type' => 'enum', 'values' => ['light', 'dark', 'system']],
        'language' => ['type' => 'enum', 'values' => ['en', 'tl']],
        'timezone' => ['type' => 'enum', 'values' => ['Asia/Manila', 'UTC', 'America/New_York', 'Europe/London']],
        'date_format' => ['type' => 'enum', 'values' => ['MMM DD, YYYY', 'MM/DD/YYYY', 'DD/MM/YYYY', 'YYYY-MM-DD']],
        'email_notifications' => ['type' => 'boolean'],
        'in_app_notifications' => ['type' => 'boolean'],
        'push_notifications' => ['type' => 'boolean'],
        'compact_mode' => ['type' => 'boolean'],
        'session_timeout' => ['type' => 'integer', 'min' => 300, 'max' => 86400],
        'max_login_attempts' => ['type' => 'integer', 'min' => 3, 'max' => 10],
        'two_factor_auth' => ['type' => 'boolean'],
        'maintenance_mode' => ['type' => 'boolean'],
        'debug_mode' => ['type' => 'boolean'],
        'backup_enabled' => ['type' => 'boolean'],
        'backup_frequency' => ['type' => 'enum', 'values' => ['daily', 'weekly', 'monthly']],
        'max_file_size' => ['type' => 'integer', 'min' => 1024, 'max' => 104857600], // 1KB to 100MB
        'allowed_file_types' => ['type' => 'string', 'max_length' => 255],
        'school_name' => ['type' => 'string', 'min_length' => 1, 'max_length' => 200],
        'school_address' => ['type' => 'string', 'max_length' => 500],
        'school_phone' => ['type' => 'phone'],
        'school_email' => ['type' => 'email'],
        'school_website' => ['type' => 'url'],
        'smtp_host' => ['type' => 'string', 'max_length' => 255],
        'smtp_port' => ['type' => 'integer', 'min' => 1, 'max' => 65535],
        'smtp_username' => ['type' => 'string', 'max_length' => 100],
        'smtp_password' => ['type' => 'string', 'max_length' => 255],
        'smtp_encryption' => ['type' => 'enum', 'values' => ['none', 'tls', 'ssl']],
        'cache_enabled' => ['type' => 'boolean'],
        'cache_lifetime' => ['type' => 'integer', 'min' => 60, 'max' => 86400],
        'log_level' => ['type' => 'enum', 'values' => ['error', 'warning', 'info', 'debug']],
        'api_rate_limit' => ['type' => 'integer', 'min' => 10, 'max' => 1000]
    ];
    
    /**
     * Validate and sanitize email address
     * 
     * @param string $email Email to validate
     * @return array ['valid' => bool, 'sanitized' => string, 'error' => string]
     */
    public static function validateEmail($email) {
        $sanitized = filter_var(trim($email), FILTER_SANITIZE_EMAIL);
        $valid = filter_var($sanitized, FILTER_VALIDATE_EMAIL) !== false;
        
        return [
            'valid' => $valid,
            'sanitized' => $sanitized,
            'error' => $valid ? '' : 'Invalid email format'
        ];
    }
    
    /**
     * Validate and sanitize username
     * 
     * @param string $username Username to validate
     * @return array ['valid' => bool, 'sanitized' => string, 'error' => string]
     */
    public static function validateUsername($username) {
        $sanitized = preg_replace('/[^a-zA-Z0-9_\-]/', '', trim($username));
        $valid = preg_match('/^[a-zA-Z0-9_\-]{3,20}$/', $sanitized);
        
        return [
            'valid' => $valid,
            'sanitized' => $sanitized,
            'error' => $valid ? '' : 'Username must be 3-20 characters and contain only letters, numbers, underscores, and hyphens'
        ];
    }
    
    /**
     * Validate password strength
     * 
     * @param string $password Password to validate
     * @param int $minLength Minimum password length
     * @return array ['valid' => bool, 'errors' => array]
     */
    public static function validatePassword($password, $minLength = 8) {
        $errors = [];
        
        if (strlen($password) < $minLength) {
            $errors[] = "Password must be at least {$minLength} characters long.";
        }
        
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = "Password must contain at least one uppercase letter.";
        }
        
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = "Password must contain at least one lowercase letter.";
        }
        
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = "Password must contain at least one number.";
        }
        
        if (!preg_match('/[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?]/', $password)) {
            $errors[] = "Password must contain at least one special character.";
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * Validate and sanitize phone number
     * 
     * @param string $phone Phone number to validate
     * @return array ['valid' => bool, 'sanitized' => string, 'error' => string]
     */
    public static function validatePhone($phone) {
        $sanitized = preg_replace('/[^0-9+\s\-\(\)]/', '', trim($phone));
        $valid = preg_match('/^[\+]?[0-9\s\-\(\)]{7,20}$/', $sanitized);
        
        return [
            'valid' => $valid,
            'sanitized' => $sanitized,
            'error' => $valid ? '' : 'Invalid phone number format'
        ];
    }
    
    /**
     * Validate and sanitize integer
     * 
     * @param mixed $value Value to validate
     * @param int $min Minimum value (optional)
     * @param int $max Maximum value (optional)
     * @return array ['valid' => bool, 'sanitized' => int, 'error' => string]
     */
    public static function validateInt($value, $min = null, $max = null) {
        $sanitized = filter_var($value, FILTER_SANITIZE_NUMBER_INT);
        $valid = filter_var($sanitized, FILTER_VALIDATE_INT) !== false;
        
        if ($valid) {
            $int_value = (int) $sanitized;
            if ($min !== null && $int_value < $min) {
                $valid = false;
                $error = "Value must be at least {$min}";
            } elseif ($max !== null && $int_value > $max) {
                $valid = false;
                $error = "Value must be at most {$max}";
            } else {
                $error = '';
            }
        } else {
            $error = 'Invalid integer value';
        }
        
        return [
            'valid' => $valid,
            'sanitized' => $valid ? (int) $sanitized : 0,
            'error' => $error ?? ''
        ];
    }
    
    /**
     * Validate and sanitize float/decimal
     * 
     * @param mixed $value Value to validate
     * @param float $min Minimum value (optional)
     * @param float $max Maximum value (optional)
     * @return array ['valid' => bool, 'sanitized' => float, 'error' => string]
     */
    public static function validateFloat($value, $min = null, $max = null) {
        $sanitized = filter_var($value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        $valid = filter_var($sanitized, FILTER_VALIDATE_FLOAT) !== false;
        
        if ($valid) {
            $float_value = (float) $sanitized;
            if ($min !== null && $float_value < $min) {
                $valid = false;
                $error = "Value must be at least {$min}";
            } elseif ($max !== null && $float_value > $max) {
                $valid = false;
                $error = "Value must be at most {$max}";
            } else {
                $error = '';
            }
        } else {
            $error = 'Invalid decimal value';
        }
        
        return [
            'valid' => $valid,
            'sanitized' => $valid ? (float) $sanitized : 0.0,
            'error' => $error ?? ''
        ];
    }
    
    /**
     * Validate date string
     * 
     * @param string $date Date string to validate
     * @param string $format Expected date format (default: Y-m-d)
     * @return array ['valid' => bool, 'sanitized' => string, 'error' => string]
     */
    public static function validateDate($date, $format = 'Y-m-d') {
        $sanitized = trim($date);
        $dateObj = DateTime::createFromFormat($format, $sanitized);
        $valid = $dateObj && $dateObj->format($format) === $sanitized;
        
        return [
            'valid' => $valid,
            'sanitized' => $sanitized,
            'error' => $valid ? '' : "Invalid date format. Expected: {$format}"
        ];
    }
    
    /**
     * Validate and sanitize text input
     * 
     * @param string $text Text to validate
     * @param int $minLength Minimum length (optional)
     * @param int $maxLength Maximum length (optional)
     * @param bool $allowHTML Allow HTML tags (default: false)
     * @return array ['valid' => bool, 'sanitized' => string, 'error' => string]
     */
    public static function validateText($text, $minLength = null, $maxLength = null, $allowHTML = false) {
        $sanitized = trim($text);
        
        if (!$allowHTML) {
            $sanitized = htmlspecialchars($sanitized, ENT_QUOTES, 'UTF-8');
        } else {
            // Allow only specific HTML tags
            $sanitized = strip_tags($sanitized, '<p><br><strong><em><u><ol><ul><li><a><h1><h2><h3><h4><h5><h6>');
        }
        
        $length = strlen($sanitized);
        $valid = true;
        $error = '';
        
        if ($minLength !== null && $length < $minLength) {
            $valid = false;
            $error = "Text must be at least {$minLength} characters long.";
        }
        
        if ($maxLength !== null && $length > $maxLength) {
            $valid = false;
            $error = "Text must not exceed {$maxLength} characters.";
        }
        
        return [
            'valid' => $valid,
            'sanitized' => $sanitized,
            'error' => $error
        ];
    }
    
    /**
     * Validate URL
     * 
     * @param string $url URL to validate
     * @return array ['valid' => bool, 'sanitized' => string, 'error' => string]
     */
    public static function validateURL($url) {
        $sanitized = filter_var(trim($url), FILTER_SANITIZE_URL);
        $valid = filter_var($sanitized, FILTER_VALIDATE_URL) !== false;
        
        return [
            'valid' => $valid,
            'sanitized' => $sanitized,
            'error' => $valid ? '' : 'Invalid URL format'
        ];
    }
    
    /**
     * Validate boolean value
     * 
     * @param mixed $value Value to validate
     * @return array ['valid' => bool, 'sanitized' => bool, 'error' => string]
     */
    public static function validateBoolean($value) {
        if (is_bool($value)) {
            return ['valid' => true, 'sanitized' => $value, 'error' => ''];
        }
        
        $sanitized = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        
        if ($sanitized === null) {
            // Try string values
            $lower_value = strtolower(trim($value));
            if (in_array($lower_value, ['true', '1', 'yes', 'on'])) {
                $sanitized = true;
            } elseif (in_array($lower_value, ['false', '0', 'no', 'off'])) {
                $sanitized = false;
            } else {
                return ['valid' => false, 'sanitized' => false, 'error' => 'Invalid boolean value'];
            }
        }
        
        return ['valid' => true, 'sanitized' => $sanitized, 'error' => ''];
    }
    
    /**
     * Validate enum value
     * 
     * @param string $value Value to validate
     * @param array $allowedValues Allowed values
     * @return array ['valid' => bool, 'sanitized' => string, 'error' => string]
     */
    public static function validateEnum($value, $allowedValues) {
        $sanitized = trim($value);
        $valid = in_array($sanitized, $allowedValues);
        
        return [
            'valid' => $valid,
            'sanitized' => $sanitized,
            'error' => $valid ? '' : 'Invalid value. Allowed: ' . implode(', ', $allowedValues)
        ];
    }
    
    /**
     * Validate settings data with role-aware restrictions
     * 
     * @param array $data Settings data to validate
     * @param string $userRole User role (admin, sub-admin)
     * @return array ['valid' => bool, 'validated' => array, 'errors' => array]
     */
    public static function validateSettingsData($data, $userRole = 'admin') {
        $validated = [];
        $errors = [];
        
        // Define role-restricted settings
        $restricted_settings = [
            'sub-admin' => [
                'maintenance_mode', 'debug_mode', 'backup_enabled', 
                'cache_enabled', 'log_level', 'api_rate_limit'
            ]
        ];
        
        foreach ($data as $key => $value) {
            // Check if setting exists in rules
            if (!isset(self::$settings_rules[$key])) {
                $errors[$key] = "Unknown setting key: $key";
                continue;
            }
            
            // Check role restrictions
            if ($userRole !== 'admin' && in_array($key, $restricted_settings[$userRole] ?? [])) {
                $errors[$key] = "You don't have permission to modify this setting";
                continue;
            }
            
            $rule = self::$settings_rules[$key];
            $result = self::validateByRule($key, $value, $rule);
            
            if ($result['valid']) {
                $validated[$key] = $result['sanitized'];
            } else {
                $errors[$key] = $result['error'];
            }
        }
        
        return [
            'valid' => empty($errors),
            'validated' => $validated,
            'errors' => $errors
        ];
    }
    
    /**
     * Validate value by rule
     * 
     * @param string $key Setting key
     * @param mixed $value Value to validate
     * @param array $rule Validation rule
     * @return array ['valid' => bool, 'sanitized' => mixed, 'error' => string]
     */
    private static function validateByRule($key, $value, $rule) {
        switch ($rule['type']) {
            case 'email':
                return self::validateEmail($value);
                
            case 'phone':
                return self::validatePhone($value);
                
            case 'url':
                return self::validateURL($value);
                
            case 'integer':
                $min = $rule['min'] ?? null;
                $max = $rule['max'] ?? null;
                return self::validateInt($value, $min, $max);
                
            case 'float':
                $min = $rule['min'] ?? null;
                $max = $rule['max'] ?? null;
                return self::validateFloat($value, $min, $max);
                
            case 'boolean':
                return self::validateBoolean($value);
                
            case 'enum':
                return self::validateEnum($value, $rule['values']);
                
            case 'string':
                $min_length = $rule['min_length'] ?? null;
                $max_length = $rule['max_length'] ?? null;
                return self::validateText($value, $min_length, $max_length);
                
            default:
                return ['valid' => false, 'sanitized' => null, 'error' => 'Unknown validation rule'];
        }
    }
    
    /**
     * Validate file upload
     * 
     * @param array $file $_FILES array item
     * @param array $allowedTypes Allowed MIME types
     * @param int $maxSize Maximum file size in bytes
     * @return array ['valid' => bool, 'error' => string]
     */
    public static function validateFile($file, $allowedTypes = [], $maxSize = 5242880) {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return [
                'valid' => false,
                'error' => self::getUploadErrorMessage($file['error'])
            ];
        }
        
        // Check file size
        if ($file['size'] > $maxSize) {
            return [
                'valid' => false,
                'error' => 'File size exceeds maximum allowed size'
            ];
        }
        
        // Check file type
        if (!empty($allowedTypes)) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            
            if (!in_array($mimeType, $allowedTypes)) {
                return [
                    'valid' => false,
                    'error' => 'File type not allowed'
                ];
            }
        }
        
        return ['valid' => true, 'error' => ''];
    }
    
    /**
     * Get upload error message
     * 
     * @param int $errorCode Upload error code
     * @return string Error message
     */
    private static function getUploadErrorMessage($errorCode) {
        switch ($errorCode) {
            case UPLOAD_ERR_INI_SIZE:
                return 'The uploaded file exceeds the upload_max_filesize directive in php.ini';
            case UPLOAD_ERR_FORM_SIZE:
                return 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form';
            case UPLOAD_ERR_PARTIAL:
                return 'The uploaded file was only partially uploaded';
            case UPLOAD_ERR_NO_FILE:
                return 'No file was uploaded';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'Missing a temporary folder';
            case UPLOAD_ERR_CANT_WRITE:
                return 'Failed to write file to disk';
            case UPLOAD_ERR_EXTENSION:
                return 'A PHP extension stopped the file upload';
            default:
                return 'Unknown upload error';
        }
    }
    
    /**
     * Validate CSRF token
     * 
     * @param string $token Token to validate
     * @return bool True if token is valid
     */
    public static function validateCSRFToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Check for SQL injection patterns
     * 
     * @param string $input Input to check
     * @return bool True if suspicious patterns found
     */
    public static function containsSQLInjection($input) {
        $patterns = [
            '/(\s|^)(SELECT|INSERT|UPDATE|DELETE|DROP|CREATE|ALTER|EXEC|UNION|SCRIPT)(\s|$)/i',
            '/(\s|^)(OR|AND)\s+\d+\s*=\s*\d+/i',
            '/(\s|^)(OR|AND)\s+["\']?\w+["\']?\s*=\s*["\']?\w+["\']?/i',
            '/--/',
            '/\/\*/',
            '/\*\//',
            '/;/'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check for XSS patterns
     * 
     * @param string $input Input to check
     * @return bool True if suspicious patterns found
     */
    public static function containsXSS($input) {
        $patterns = [
            '/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/i',
            '/<iframe\b[^<]*(?:(?!<\/iframe>)<[^<]*)*<\/iframe>/i',
            '/<object\b[^<]*(?:(?!<\/object>)<[^<]*)*<\/object>/i',
            '/<embed\b[^<]*(?:(?!<\/embed>)<[^<]*)*<\/embed>/i',
            '/javascript:/i',
            '/vbscript:/i',
            '/onload\s*=/i',
            '/onerror\s*=/i',
            '/onclick\s*=/i',
            '/onmouseover\s*=/i'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Comprehensive security check
     * 
     * @param string $input Input to check
     * @return array ['safe' => bool, 'threats' => array]
     */
    public static function securityCheck($input) {
        $threats = [];
        
        if (self::containsSQLInjection($input)) {
            $threats[] = 'SQL Injection';
        }
        
        if (self::containsXSS($input)) {
            $threats[] = 'XSS';
        }
        
        return [
            'safe' => empty($threats),
            'threats' => $threats
        ];
    }
    
    /**
     * Sanitize input array recursively
     * 
     * @param array $data Input data
     * @return array Sanitized data
     */
    public static function sanitizeInput($data) {
        if (is_array($data)) {
            return array_map([self::class, 'sanitizeInput'], $data);
        }
        
        return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Get allowed file types based on setting
     * 
     * @param string $fileTypesString Comma-separated file types
     * @return array MIME types array
     */
    public static function getAllowedMimeTypes($fileTypesString) {
        $type_map = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'txt' => 'text/plain'
        ];
        
        $types = array_map('trim', explode(',', strtolower($fileTypesString)));
        $mime_types = [];
        
        foreach ($types as $type) {
            if (isset($type_map[$type])) {
                $mime_types[] = $type_map[$type];
            }
        }
        
        return $mime_types;
    }
}
