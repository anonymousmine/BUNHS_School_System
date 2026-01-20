<?php
include 'db_connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {
    $email = trim($_POST['email']);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo 'invalid';
        exit();
    }

    $stmt = $conn->prepare("SELECT id FROM `sub_admin` WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        echo 'exists';
    } else {
        echo 'available';
    }

    $stmt->close();
    $conn->close();
} else {
    echo 'invalid';
}
