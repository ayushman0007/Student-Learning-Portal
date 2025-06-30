<?php
$mysqli = new mysqli("localhost", "root", "", "user_auth");

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

$username = 'admin'; // Change this if you want
$password = 'admin123'; // Change this to your real password

$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

$stmt = $mysqli->prepare("INSERT INTO admin_users (username, password) VALUES (?, ?)");
$stmt->bind_param("ss", $username, $hashedPassword);

if ($stmt->execute()) {
    echo "Admin user inserted successfully.";
} else {
    echo "Error: " . $stmt->error;
}

$stmt->close();
$mysqli->close();
?>
