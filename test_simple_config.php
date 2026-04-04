<?php
// Test simple database configuration
header('Content-Type: application/json');

// Load simple database configuration
require_once 'config/simple_database.php';

$test_results = [
    'timestamp' => date('Y-m-d H:i:s'),
    'http_host' => $_SERVER['HTTP_HOST'] ?? 'not set',
    'is_railway_domain' => (strpos($_SERVER['HTTP_HOST'] ?? '', 'railway.app') !== false),
    'environment_detected' => (strpos($_SERVER['HTTP_HOST'] ?? '', 'railway.app') !== false) ? 'Railway' : 'Local',
    'variables_found' => [
        'DB_HOST' => getenv('DB_HOST') ?: 'NOT SET',
        'DB_USER' => getenv('DB_USER') ?: 'NOT SET',
        'DB_NAME' => getenv('DB_NAME') ?: 'NOT SET',
        'DB_PASSWORD' => getenv('DB_PASSWORD') ? 'SET' : 'NOT SET',
    ],
    'final_config' => [
        'DB_HOST' => DB_HOST,
        'DB_USER' => DB_USER,
        'DB_NAME' => DB_NAME,
        'DB_PORT' => DB_PORT,
        'DB_PASSWORD_SET' => !empty(DB_PASSWORD),
    ],
    'issue_detected' => false
];

// Check for localhost issue
if (DB_HOST === 'localhost') {
    $test_results['issue_detected'] = true;
    $test_results['issue'] = 'Still connecting to localhost';
}

echo json_encode($test_results, JSON_PRETTY_PRINT);
?>
