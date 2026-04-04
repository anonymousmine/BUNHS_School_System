<?php
// Railway deployment test and cache buster
header('Content-Type: application/json');

// Test basic PHP functionality
$test_data = [
    'timestamp' => date('Y-m-d H:i:s'),
    'php_version' => PHP_VERSION,
    'environment' => [
        'RAILWAY_ENVIRONMENT' => $_SERVER['RAILWAY_ENVIRONMENT'] ?? 'not set',
        'RAILWAY_SERVICE_NAME' => $_SERVER['RAILWAY_SERVICE_NAME'] ?? 'not set',
        'HTTP_X_FORWARDED_FOR' => $_SERVER['HTTP_X_FORWARDED_FOR'] ?? 'not set',
    ],
    'files_exist' => [
        'local_db_config.php' => file_exists(__DIR__ . '/local_db_config.php'),
        'railway_db_config.php' => file_exists(__DIR__ . '/railway_db_config.php'),
    ],
    'config_loaded' => [
        'DB_HOST' => $_ENV['DB_HOST'] ?? 'not set',
        'DB_USER' => $_ENV['DB_USER'] ?? 'not set',
        'DB_NAME' => $_ENV['DB_NAME'] ?? 'not set',
    ]
];

echo json_encode($test_data, JSON_PRETTY_PRINT);
?>
