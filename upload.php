<?php
session_start();
if (!isset($_SESSION["user"])) {
    header("Location: login.php");
    exit;
}

require 'db.php';
$user_id = $_SESSION["user"]["id"];

if (isset($_POST['upload']) && isset($_FILES['document'])) {
    $file = $_FILES['document'];
    $fileName = basename($file['name']);
    $uploadDir = "uploads/";
    $targetFile = $uploadDir . time() . "_" . $fileName;
    $fileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));

    // Allowed types
    $allowedTypes = ['pdf', 'jpg', 'jpeg', 'png'];
    if (in_array($fileType, $allowedTypes)) {
        if (move_uploaded_file($file['tmp_name'], $targetFile)) {
            $stmt = $conn->prepare("INSERT INTO documents (user_id, file_name, file_path) VALUES (?, ?, ?)");
            $stmt->bind_param("iss", $user_id, $fileName, $targetFile);
            $stmt->execute();
            $stmt->close();
            header("Location: profile.php?tab=documents");
            exit;
        } else {
            echo "Upload failed!";
        }
    } else {
        echo "Only PDF, JPG, JPEG, PNG files are allowed.";
    }
}
?>
