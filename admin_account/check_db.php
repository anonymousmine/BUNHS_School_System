<?php
require_once '../db_connection.php';

echo "<h2>Admin Table Structure:</h2>";
$result = $conn->query("DESCRIBE admin");
if ($result) {
    echo "<table border='1'><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        foreach ($row as $value) {
            echo "<td>" . htmlspecialchars($value ?? 'NULL') . "</td>";
        }
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "Error: " . $conn->error;
}

echo "<h2>Sub_Admin Table Structure:</h2>";
$result = $conn->query("DESCRIBE sub_admin");
if ($result) {
    echo "<table border='1'><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        foreach ($row as $value) {
            echo "<td>" . htmlspecialchars($value ?? 'NULL') . "</td>";
        }
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "Error: " . $conn->error;
}

echo "<h2>Sample Admin Data:</h2>";
$result = $conn->query("SELECT * FROM admin LIMIT 1");
if ($result) {
    $row = $result->fetch_assoc();
    echo "<pre>";
    print_r($row);
    echo "</pre>";
} else {
    echo "Error: " . $conn->error;
}

echo "<h2>Sample Sub_Admin Data:</h2>";
$result = $conn->query("SELECT * FROM sub_admin LIMIT 1");
if ($result) {
    $row = $result->fetch_assoc();
    echo "<pre>";
    print_r($row);
    echo "</pre>";
} else {
    echo "Error: " . $conn->error;
}
?>
