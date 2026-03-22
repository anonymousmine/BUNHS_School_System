<?php

/**
 * User DB Connection - Uses safe root connection with mysqli check
 */
$root_db = __DIR__ . '/../db_connection.php';
if (file_exists($root_db)) {
    require_once $root_db;
} else {
    error_log('Root db_connection.php missing: ' . $root_db);
    http_response_code(500);
    die('Database configuration unavailable. Contact administrator.');
}
