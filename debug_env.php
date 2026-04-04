<?php
// Debug environment detection
header('Content-Type: application/json');

$debug_info = [
    'timestamp' => date('Y-m-d H:i:s'),
    'server_vars' => [
        'RAILWAY_ENVIRONMENT' => $_SERVER['RAILWAY_ENVIRONMENT'] ?? 'not set',
        'RAILWAY_SERVICE_NAME' => $_SERVER['RAILWAY_SERVICE_NAME'] ?? 'not set',
        'HTTP_X_FORWARDED_FOR' => $_SERVER['HTTP_X_FORWARDED_FOR'] ?? 'not set',
        'HTTP_HOST' => $_SERVER['HTTP_HOST'] ?? 'not set',
    ],
    'env_vars' => [
        'RAILWAY_ENVIRONMENT' => getenv('RAILWAY_ENVIRONMENT') ?? 'not set',
        'RAILWAY_SERVICE_NAME' => getenv('RAILWAY_SERVICE_NAME') ?? 'not set',
    ],
    'detection_checks' => [
        'isset_RAILWAY_ENVIRONMENT' => isset($_SERVER['RAILWAY_ENVIRONMENT']),
        'isset_RAILWAY_SERVICE_NAME' => isset($_SERVER['RAILWAY_SERVICE_NAME']),
        'isset_HTTP_X_FORWARDED_FOR' => isset($_SERVER['HTTP_X_FORWARDED_FOR']) && !empty($_SERVER['HTTP_X_FORWARDED_FOR']),
        'getenv_RAILWAY_ENVIRONMENT' => getenv('RAILWAY_ENVIRONMENT') !== false,
        'getenv_RAILWAY_SERVICE_NAME' => getenv('RAILWAY_SERVICE_NAME') !== false,
        'strpos_railway_app' => strpos($_SERVER['HTTP_HOST'] ?? '', 'railway.app') !== false,
    ],
    'should_be_railway' => false
];

// Calculate the same logic as our config
$is_railway = isset($_SERVER['RAILWAY_ENVIRONMENT']) || 
               isset($_SERVER['RAILWAY_SERVICE_NAME']) ||
               (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) ||
               (getenv('RAILWAY_ENVIRONMENT') !== false) ||
               (getenv('RAILWAY_SERVICE_NAME') !== false) ||
               (strpos($_SERVER['HTTP_HOST'] ?? '', 'railway.app') !== false);

$debug_info['should_be_railway'] = $is_railway;
$debug_info['final_environment'] = $is_railway ? 'Railway' : 'Local';

echo json_encode($debug_info, JSON_PRETTY_PRINT);
?>
