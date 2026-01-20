<?php
$host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'bunhs_db_important';

$conn = mysqli_connect($host, $db_user, $db_pass, $db_name);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
?>
