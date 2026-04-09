<?php
// Local database configuration for testing
// These settings override the Railway environment detection

// Putenv sets environment variables that getenv() can read
putenv('DB_HOST=localhost');
putenv('DB_USER=root');
putenv('DB_PASSWORD=');
putenv('DB_NAME=bunhs_db_important');
putenv('DB_PORT=3306');
putenv('LOCAL_DB_SETUP=true');

// Also set as $_ENV and $_SERVER for completeness
$_ENV['DB_HOST'] = 'localhost';
$_ENV['DB_USER'] = 'root';
$_ENV['DB_PASSWORD'] = '';
$_ENV['DB_NAME'] = 'bunhs_db_important';
$_ENV['DB_PORT'] = '3306';
$_ENV['LOCAL_DB_SETUP'] = 'true';

$_SERVER['DB_HOST'] = 'localhost';
$_SERVER['DB_USER'] = 'root';
$_SERVER['DB_PASSWORD'] = '';
$_SERVER['DB_NAME'] = 'bunhs_db_important';
$_SERVER['DB_PORT'] = '3306';
$_SERVER['LOCAL_DB_SETUP'] = 'true';

echo "<!-- Local DB Config Loaded -->";
?>
