<?php
/**
 * Railway Database Configuration
 * This file should ONLY exist on Railway deployment
 * It will override local_db_config.php for Railway environment
 */

// Railway environment variables (set in Railway dashboard)
$db_host = getenv('DB_HOST') ?: 'localhost';
$db_user = getenv('DB_USER') ?: 'root';
$db_password = getenv('DB_PASSWORD') ?: '';
$db_name = getenv('DB_NAME') ?: 'bunhs_db_important';
$db_port = getenv('DB_PORT') ?: '3306';

// Set as environment variables for compatibility
putenv('DB_HOST=' . $db_host);
putenv('DB_USER=' . $db_user);
putenv('DB_PASSWORD=' . $db_password);
putenv('DB_NAME=' . $db_name);
putenv('DB_PORT=' . $db_port);

// Also set as $_ENV and $_SERVER for completeness
$_ENV['DB_HOST'] = $db_host;
$_ENV['DB_USER'] = $db_user;
$_ENV['DB_PASSWORD'] = $db_password;
$_ENV['DB_NAME'] = $db_name;
$_ENV['DB_PORT'] = $db_port;

$_SERVER['DB_HOST'] = $db_host;
$_SERVER['DB_USER'] = $db_user;
$_SERVER['DB_PASSWORD'] = $db_password;
$_SERVER['DB_NAME'] = $db_name;
$_SERVER['DB_PORT'] = $db_port;

// Debug logging for Railway
error_log("[RAILWAY DB] Host: {$db_host}, User: {$db_user}, DB: {$db_name}, Port: {$db_port}");

?>
