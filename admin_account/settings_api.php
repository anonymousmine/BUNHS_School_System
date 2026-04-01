<?php

/**
 * settings_api.php — Enhanced School Settings API
 * Stores/retrieves settings from MySQL (school_settings table).
 * Also keeps JSON file as secondary cache.
 * 
 * Enhanced with:
 * - Role-based access control
 * - CSRF protection
 * - Input validation and sanitization
 * - Comprehensive error handling
 * - Security logging
 */

session_start();
require_once '../db_connection.php';

// Load helper classes
require_once __DIR__ . '/helpers/auth_helper.php';
require_once __DIR__ . '/helpers/permission_manager.php';
require_once __DIR__ . '/helpers/database_helper.php';
require_once __DIR__ . '/helpers/validation_helper.php';

// Enhanced authentication and authorization
try {
    $user_data = AuthHelper::requireAuth($conn);
    $user_type = $_SESSION['user_type'];
    $user_role = AuthHelper::getCurrentRole();
    
    // Initialize database helper
    $db = new DatabaseHelper($conn, false);
    
} catch (Exception $e) {
    error_log("Settings API auth error: " . $e->getMessage());
    http_response_code(403);
    echo json_encode(['error' => 'Authentication failed']);
    exit;
}

// JSON cache path (optional secondary storage)
$configPath = __DIR__ . '/config/settings.json';

/**
 * Load settings from JSON file
 */
function loadSettings(string $path): array
{
    if (file_exists($path)) {
        $decoded = json_decode(file_get_contents($path), true);
        return is_array($decoded) ? $decoded : [];
    }
    return [];
}

/**
 * Save settings to JSON file
 */
function saveSettings(string $path, array $data): bool
{
    @mkdir(dirname($path), 0775, true);
    return file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT)) !== false;
}

/**
 * Load settings from database
 */
function loadDbSettings(DatabaseHelper $db): array
{
    try {
        $results = $db->fetchAll("SELECT setting_key, setting_value FROM school_settings");
        $out = [];
        foreach ($results as $row) {
            $out[$row['setting_key']] = $row['setting_value'];
        }
        return $out;
    } catch (Exception $e) {
        error_log("Failed to load settings from database: " . $e->getMessage());
        return [];
    }
}

/**
 * Save a single setting to database
 */
function saveDbSetting(DatabaseHelper $db, string $key, string $value): bool
{
    try {
        $sql = "INSERT INTO school_settings (setting_key, setting_value) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)";
        $db->executeQuery($sql, [$key, $value], 'ss');
        return true;
    } catch (Exception $e) {
        error_log("Failed to save setting '$key': " . $e->getMessage());
        return false;
    }
}

/**
 * Log settings changes for audit
 */
function logSettingsChange($action, $data, $user_id) {
    $log_entry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'user_id' => $user_id,
        'action' => $action,
        'data' => $data,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ];
    
    $log_file = __DIR__ . '/../logs/settings_changes.log';
    $log_dir = dirname($log_file);
    
    if (!is_dir($log_dir)) {
        @mkdir($log_dir, 0755, true);
    }
    
    file_put_contents($log_file, json_encode($log_entry) . "\n", FILE_APPEND | LOCK_EX);
}

header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

switch ($action) {
    // ── Load: merge DB + JSON cache with role filtering ────────────────────────
    case 'load':
        try {
            $dbSettings = loadDbSettings($db);
            $fileSettings = loadSettings($configPath);
            $all_settings = array_merge($fileSettings, $dbSettings);
            
            // Filter settings based on user role
            $filtered_settings = [];
            foreach ($all_settings as $key => $value) {
                if (PermissionManager::canAccessSettingsSection(str_replace('_settings', '', $key))) {
                    $filtered_settings[$key] = $value;
                }
            }
            
            echo json_encode([
                'success' => true,
                'settings' => $filtered_settings,
                'user_role' => $user_role,
                'permissions' => PermissionManager::getRolePermissions()
            ]);
            
        } catch (Exception $e) {
            error_log("Settings load error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Failed to load settings']);
        }
        break;

    // ── Save: persist to both DB and JSON with validation ─────────────────
    case 'save':
        try {
            // Validate CSRF token
            if (!ValidationHelper::validateCSRFToken($input['csrf_token'] ?? '')) {
                http_response_code(403);
                echo json_encode(['error' => 'Invalid CSRF token']);
                break;
            }
            
            // Validate and sanitize input data
            $newData = $input['data'] ?? $input ?? [];
            unset($newData['action'], $newData['csrf_token']);
            
            $validation = ValidationHelper::validateSettingsData($newData, $user_type);
            if (!$validation['valid']) {
                http_response_code(400);
                echo json_encode([
                    'error' => 'Validation failed',
                    'validation_errors' => $validation['errors']
                ]);
                break;
            }
            
            $validated_data = $validation['validated'];
            $errors = [];
            
            // Save each setting
            foreach ($validated_data as $key => $value) {
                if (!saveDbSetting($db, $key, $value)) {
                    $errors[] = $key;
                }
            }
            
            // Also update JSON cache
            if (empty($errors)) {
                $existing = loadSettings($configPath);
                $merged = array_merge($existing, $validated_data);
                saveSettings($configPath, $merged);
                
                // Log the change
                logSettingsChange('save', $validated_data, $_SESSION['user_id']);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Settings saved successfully'
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'error' => 'Failed to save some settings',
                    'failed_keys' => $errors
                ]);
            }
            
        } catch (Exception $e) {
            error_log("Settings save error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Failed to save settings']);
        }
        break;

    // ── Reset: reload defaults (admin only) ──────
    case 'reset':
        try {
            // Only admins can reset settings
            if (!AuthHelper::isAdmin()) {
                http_response_code(403);
                echo json_encode(['error' => 'Insufficient permissions']);
                break;
            }
            
            // Validate CSRF token
            if (!ValidationHelper::validateCSRFToken($input['csrf_token'] ?? '')) {
                http_response_code(403);
                echo json_encode(['error' => 'Invalid CSRF token']);
                break;
            }
            
            if (file_exists($configPath)) {
                $defaults = json_decode(file_get_contents($configPath), true);
                if (is_array($defaults)) {
                    foreach ($defaults as $key => $value) {
                        saveDbSetting($db, $key, $value);
                    }
                    
                    // Log the reset
                    logSettingsChange('reset', $defaults, $_SESSION['user_id']);
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Settings reset to defaults'
                    ]);
                } else {
                    http_response_code(500);
                    echo json_encode(['error' => 'Invalid JSON in config file']);
                }
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'No config file found to reset from']);
            }
            
        } catch (Exception $e) {
            error_log("Settings reset error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Failed to reset settings']);
        }
        break;

    // ── Get single key ─────────────────────────────────────
    case 'get':
        try {
            $key = trim($_GET['key'] ?? '');
            if ($key === '') {
                http_response_code(400);
                echo json_encode(['error' => 'Missing key parameter']);
                break;
            }
            
            // Check permission for this setting
            if (!PermissionManager::canAccessSettingsSection(str_replace('_settings', '', $key))) {
                http_response_code(403);
                echo json_encode(['error' => 'Insufficient permissions']);
                break;
            }
            
            $result = $db->fetchOne("SELECT setting_value FROM school_settings WHERE setting_key = ? LIMIT 1", [$key], 's');
            
            echo json_encode([
                'success' => true,
                'key' => $key,
                'value' => $result ? $result['setting_value'] : null
            ]);
            
        } catch (Exception $e) {
            error_log("Settings get error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Failed to get setting']);
        }
        break;

    // ── Get user permissions ─────────────────────────────────────
    case 'permissions':
        try {
            echo json_encode([
                'success' => true,
                'user_type' => $user_type,
                'user_role' => $user_role,
                'permissions' => PermissionManager::getRolePermissions(),
                'available_sections' => PermissionManager::getAvailableSettingsSections()
            ]);
        } catch (Exception $e) {
            error_log("Permissions get error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Failed to get permissions']);
        }
        break;

    // ── Validate setting value ─────────────────────────────────────
    case 'validate':
        try {
            $key = trim($_GET['key'] ?? '');
            $value = $_GET['value'] ?? '';
            
            if ($key === '' || $value === '') {
                http_response_code(400);
                echo json_encode(['error' => 'Missing key or value parameter']);
                break;
            }
            
            // Check permission for this setting
            if (!PermissionManager::canAccessSettingsSection(str_replace('_settings', '', $key))) {
                http_response_code(403);
                echo json_encode(['error' => 'Insufficient permissions']);
                break;
            }
            
            $validation = ValidationHelper::validateSettingsData([$key => $value], $user_type);
            
            echo json_encode([
                'success' => true,
                'valid' => $validation['valid'],
                'errors' => $validation['errors'],
                'sanitized' => $validation['validated']
            ]);
            
        } catch (Exception $e) {
            error_log("Settings validation error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Failed to validate setting']);
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
}
