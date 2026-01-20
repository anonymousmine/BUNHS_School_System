<?php
include 'db_connection.php';

$sql = "ALTER TABLE `sub-admin` ADD COLUMN phone VARCHAR(15) NULL";

if (mysqli_query($conn, $sql)) {
    echo "Column 'phone' added successfully to sub-admin table.";
} else {
    echo "Error adding column: " . mysqli_error($conn);
}

mysqli_close($conn);
