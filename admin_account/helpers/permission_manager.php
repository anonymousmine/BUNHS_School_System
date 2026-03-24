<?php

/**
 * permission_manager.php - Centralized Permission Management System
 * Handles role-based access control for admin and sub-admin accounts
 */

if (!defined('BUNHS_ADMIN_ACCOUNT')) {
    define('BUNHS_ADMIN_ACCOUNT', true);
}

class PermissionManager {
    
    /**
     * Permission mapping for different roles
     * Format: 'role' => ['permission1', 'permission2', ...]
     * Use '*' for all permissions
     */
    private static $permissions = [
        'super_sub_admin' => ['*'], // All permissions
        'student_admin' => [
            'students.view', 'students.create', 'students.edit', 'students.delete',
            'students.export', 'students.import', 'students.search'
        ],
        'teacher_admin' => [
            'teachers.view', 'teachers.create', 'teachers.edit', 'teachers.delete',
            'teachers.export', 'teachers.import', 'teachers.search', 'teachers.assign'
        ],
        'news_admin' => [
            'news.view', 'news.create', 'news.edit', 'news.delete',
            'news.publish', 'news.archive', 'news.feature'
        ],
        'announcement_admin' => [
            'announcements.view', 'announcements.create', 'announcements.edit',
            'announcements.publish', 'announcements.archive', 'announcements.emergency'
        ],
        'club_admin' => [
            'clubs.view', 'clubs.create', 'clubs.edit', 'clubs.delete',
            'clubs.members', 'clubs.activities', 'clubs.reports'
        ],
        'forms_admin' => [
            'forms.view', 'forms.process', 'forms.approve', 'forms.reject',
            'forms.export', 'forms.archive', 'forms.templates'
        ],
        'finance_admin' => [
            'finance.view', 'finance.create', 'finance.edit', 'finance.delete',
            'finance.reports', 'finance.export', 'finance.audit'
        ]
    ];
    
    /**
     * Module-to-permission mapping for navigation filtering
     */
    private static $module_permissions = [
        'students.php' => 'students.view',
        'teachers.php' => 'teachers.view',
        'news.php' => 'news.view',
        'create_new.php' => 'news.create',
        'create_announcement.php' => 'announcements.create',
        'clubs.php' => 'clubs.view',
        'forms.php' => 'forms.view',
        'finance.php' => 'finance.view',
        'admins.php' => 'system.admin',
        'settings.php' => 'system.settings',
        'reports.php' => 'system.reports',
        'student_logs.php' => 'system.logs'
    ];
    
    /**
     * Settings section permissions
     */
    private static $settings_permissions = [
        'appearance' => null, // Available to all authenticated users
        'locale' => null,
        'security' => 'system.security',
        'system' => 'system.settings',
        'database' => 'system.database',
        'school' => 'system.school',
        'admin' => 'system.admin',
        'finance' => 'finance.view',
        'files' => 'system.files',
        'clubs' => 'clubs.view',
        'overview' => 'system.reports'
    ];
    
    /**
     * Check if user has specific permission
     * @param int $user_id User ID
     * @param string $permission Permission to check
     * @return bool True if user has permission
     */
    public static function hasPermission($user_id, $permission) {
        $user_type = $_SESSION['user_type'] ?? '';
        
        // Admins have all permissions
        if ($user_type === 'admin') {
            return true;
        }
        
        // Get sub-admin role and permissions
        $role = $_SESSION['subadmin_role'] ?? '';
        $user_permissions = self::$permissions[$role] ?? [];
        
        // Check for wildcard permission
        if (in_array('*', $user_permissions)) {
            return true;
        }
        
        // Check for specific permission
        if (in_array($permission, $user_permissions)) {
            return true;
        }
        
        // Check for parent permissions (e.g., 'students' matches 'students.view')
        $permission_parts = explode('.', $permission);
        for ($i = count($permission_parts) - 1; $i > 0; $i--) {
            $parent_permission = implode('.', array_slice($permission_parts, 0, $i));
            if (in_array($parent_permission, $user_permissions)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if user can access specific module/page
     * @param string $module Module filename (e.g., 'students.php')
     * @param int|null $user_id User ID (optional, uses current session)
     * @return bool True if user can access module
     */
    public static function canAccessModule($module, $user_id = null) {
        $user_id = $user_id ?? $_SESSION['user_id'] ?? 0;
        $required_permission = self::$module_permissions[$module] ?? null;
        
        if ($required_permission === null) {
            return true; // No specific permission required
        }
        
        return self::hasPermission($user_id, $required_permission);
    }
    
    /**
     * Filter menu items based on user permissions
     * @param array $menu_items Array of menu items with 'permission' key
     * @return array Filtered menu items
     */
    public static function filterMenuItems($menu_items) {
        $user_type = $_SESSION['user_type'] ?? '';
        
        if ($user_type === 'admin') {
            return $menu_items; // Admins see everything
        }
        
        return array_filter($menu_items, function($item) {
            $permission = $item['permission'] ?? null;
            if ($permission === null) {
                return true; // No permission required
            }
            
            return self::hasPermission($_SESSION['user_id'] ?? 0, $permission);
        });
    }
    
    /**
     * Get user's role permissions
     * @param string|null $role Role name (optional, uses current session)
     * @return array Array of permissions
     */
    public static function getRolePermissions($role = null) {
        $role = $role ?? $_SESSION['subadmin_role'] ?? '';
        return self::$permissions[$role] ?? [];
    }
    
    /**
     * Check if user can access settings section
     * @param string $section Settings section ID
     * @return bool True if user can access section
     */
    public static function canAccessSettingsSection($section) {
        $required_permission = self::$settings_permissions[$section] ?? null;
        
        if ($required_permission === null) {
            return true; // No specific permission required
        }
        
        return self::hasPermission($_SESSION['user_id'] ?? 0, $required_permission);
    }
    
    /**
     * Get available settings sections for current user
     * @return array Array of accessible settings sections
     */
    public static function getAvailableSettingsSections() {
        $all_sections = array_keys(self::$settings_permissions);
        $available_sections = [];
        
        foreach ($all_sections as $section) {
            if (self::canAccessSettingsSection($section)) {
                $available_sections[] = $section;
            }
        }
        
        return $available_sections;
    }
    
    /**
     * Get role label for display
     * @param string $role Role name
     * @return string Human-readable role label
     */
    public static function getRoleLabel($role) {
        $labels = [
            'super_sub_admin' => 'Super Sub-Admin',
            'student_admin' => 'Student Admin',
            'teacher_admin' => 'Teacher Admin',
            'news_admin' => 'News Admin',
            'announcement_admin' => 'Announcement Admin',
            'club_admin' => 'Club Admin',
            'forms_admin' => 'Forms Admin',
            'finance_admin' => 'Finance Admin'
        ];
        
        return $labels[$role] ?? ucfirst(str_replace('_', ' ', $role));
    }
    
    /**
     * Get all available roles
     * @return array Array of role definitions
     */
    public static function getAllRoles() {
        $roles = [];
        
        foreach (self::$permissions as $role => $permissions) {
            $roles[] = [
                'name' => $role,
                'label' => self::getRoleLabel($role),
                'permissions' => $permissions,
                'is_super' => in_array('*', $permissions)
            ];
        }
        
        return $roles;
    }
    
    /**
     * Validate if role exists
     * @param string $role Role name
     * @return bool True if role exists
     */
    public static function isValidRole($role) {
        return array_key_exists($role, self::$permissions);
    }
    
    /**
     * Get permission description for display
     * @param string $permission Permission name
     * @return string Human-readable permission description
     */
    public static function getPermissionDescription($permission) {
        $descriptions = [
            'students.view' => 'View student records',
            'students.create' => 'Create new student records',
            'students.edit' => 'Edit student information',
            'students.delete' => 'Delete student records',
            'students.export' => 'Export student data',
            'students.import' => 'Import student data',
            'students.search' => 'Search student records',
            
            'teachers.view' => 'View teacher records',
            'teachers.create' => 'Create new teacher records',
            'teachers.edit' => 'Edit teacher information',
            'teachers.delete' => 'Delete teacher records',
            'teachers.export' => 'Export teacher data',
            'teachers.import' => 'Import teacher data',
            'teachers.search' => 'Search teacher records',
            'teachers.assign' => 'Assign teachers to classes',
            
            'news.view' => 'View news articles',
            'news.create' => 'Create news articles',
            'news.edit' => 'Edit news articles',
            'news.delete' => 'Delete news articles',
            'news.publish' => 'Publish news articles',
            'news.archive' => 'Archive news articles',
            'news.feature' => 'Feature news articles',
            
            'announcements.view' => 'View announcements',
            'announcements.create' => 'Create announcements',
            'announcements.edit' => 'Edit announcements',
            'announcements.publish' => 'Publish announcements',
            'announcements.archive' => 'Archive announcements',
            'announcements.emergency' => 'Create emergency announcements',
            
            'clubs.view' => 'View club information',
            'clubs.create' => 'Create new clubs',
            'clubs.edit' => 'Edit club information',
            'clubs.delete' => 'Delete clubs',
            'clubs.members' => 'Manage club members',
            'clubs.activities' => 'Manage club activities',
            'clubs.reports' => 'Generate club reports',
            
            'forms.view' => 'View form submissions',
            'forms.process' => 'Process form requests',
            'forms.approve' => 'Approve form requests',
            'forms.reject' => 'Reject form requests',
            'forms.export' => 'Export form data',
            'forms.archive' => 'Archive form records',
            'forms.templates' => 'Manage form templates',
            
            'finance.view' => 'View financial records',
            'finance.create' => 'Create financial records',
            'finance.edit' => 'Edit financial records',
            'finance.delete' => 'Delete financial records',
            'finance.reports' => 'Generate financial reports',
            'finance.export' => 'Export financial data',
            'finance.audit' => 'Audit financial transactions',
            
            'system.settings' => 'Manage system settings',
            'system.security' => 'Manage security settings',
            'system.admin' => 'Manage admin accounts',
            'system.database' => 'Manage database settings',
            'system.school' => 'Manage school information',
            'system.files' => 'Manage file storage',
            'system.reports' => 'View system reports',
            'system.logs' => 'View system logs'
        ];
        
        return $descriptions[$permission] ?? $permission;
    }
    
    /**
     * Check if user has any of the specified permissions
     * @param array $permissions Array of permissions to check
     * @param int|null $user_id User ID (optional)
     * @return bool True if user has any of the permissions
     */
    public static function hasAnyPermission($permissions, $user_id = null) {
        $user_id = $user_id ?? $_SESSION['user_id'] ?? 0;
        
        foreach ($permissions as $permission) {
            if (self::hasPermission($user_id, $permission)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if user has all specified permissions
     * @param array $permissions Array of permissions to check
     * @param int|null $user_id User ID (optional)
     * @return bool True if user has all permissions
     */
    public static function hasAllPermissions($permissions, $user_id = null) {
        $user_id = $user_id ?? $_SESSION['user_id'] ?? 0;
        
        foreach ($permissions as $permission) {
            if (!self::hasPermission($user_id, $permission)) {
                return false;
            }
        }
        
        return true;
    }
}
