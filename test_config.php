<?php
// Quick configuration test for Railway
header('Content-Type: application/json');

// Load database configuration
require_once 'config/database.php';

$test_results = [
    'timestamp' => date('Y-m-d H:i:s'),
    'environment_detected' => isset($_SERVER['RAILWAY_ENVIRONMENT']) ? 'Railway' : 'Local',
    'variables_found' => [
        'MYSQLHOST' => getenv('MYSQLHOST') ?: 'NOT SET',
        'MYSQLUSER' => getenv('MYSQLUSER') ?: 'NOT SET',
        'MYSQLDATABASE' => getenv('MYSQLDATABASE') ?: 'NOT SET',
        'MYSQLPASSWORD' => getenv('MYSQLPASSWORD') ? 'SET' : 'NOT SET',
        'DB_HOST' => getenv('DB_HOST') ?: 'NOT SET',
        'DB_USER' => getenv('DB_USER') ?: 'NOT SET',
        'DB_NAME' => getenv('DB_NAME') ?: 'NOT SET',
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
    $test_results['issue'] = 'Still connecting to localhost - check environment variables';
}

echo json_encode($test_results, JSON_PRETTY_PRINT);
?>
