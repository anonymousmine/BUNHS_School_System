<?php
// Railway MySQL Integration Test
header('Content-Type: application/json');

// Load unified database configuration
require_once 'config/database.php';

// Test basic PHP functionality
$test_data = [
    'timestamp' => date('Y-m-d H:i:s'),
    'php_version' => PHP_VERSION,
    'environment' => [
        'RAILWAY_ENVIRONMENT' => $_SERVER['RAILWAY_ENVIRONMENT'] ?? 'not set',
        'RAILWAY_SERVICE_NAME' => $_SERVER['RAILWAY_SERVICE_NAME'] ?? 'not set',
        'HTTP_X_FORWARDED_FOR' => $_SERVER['HTTP_X_FORWARDED_FOR'] ?? 'not set',
    ],
    'database_config' => [
        'DB_HOST' => DB_HOST,
        'DB_USER' => DB_USER,
        'DB_NAME' => DB_NAME,
        'DB_PORT' => DB_PORT,
        'DB_PASSWORD_SET' => !empty(DB_PASSWORD),
    ],
    'railway_mysql_vars' => [
        'RAILWAY_MYSQL_HOST' => $_ENV['RAILWAY_MYSQL_HOST'] ?? 'not set',
        'RAILWAY_MYSQL_USER' => $_ENV['RAILWAY_MYSQL_USER'] ?? 'not set',
        'RAILWAY_MYSQL_DATABASE' => $_ENV['RAILWAY_MYSQL_DATABASE'] ?? 'not set',
    ],
    'database_connection_test' => null,
    'tables_found' => [],
];

// Test database connection
try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME, DB_PORT);
    
    if ($conn->connect_error) {
        $test_data['database_connection_test'] = [
            'success' => false,
            'error' => $conn->connect_error
        ];
    } else {
        $test_data['database_connection_test'] = [
            'success' => true,
            'message' => 'Database connection successful'
        ];
        
        // Test if tables exist
        $tables = ['admin', 'sub_admin', 'email_subscribers', 'document_requests'];
        foreach ($tables as $table) {
            $result = $conn->query("SHOW TABLES LIKE '$table'");
            $test_data['tables_found'][$table] = $result->num_rows > 0;
        }
        
        $conn->close();
    }
} catch (Exception $e) {
    $test_data['database_connection_test'] = [
        'success' => false,
        'error' => $e->getMessage()
    ];
}

echo json_encode($test_data, JSON_PRETTY_PRINT);
?>
