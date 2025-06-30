<?php
// upload_material.php (fixed filename)
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header("HTTP/1.1 403 Forbidden");
    exit;
}

// Use the same connection as in admin.php
$studyConn = new mysqli("localhost", "root", "", "study_materials");
if ($studyConn->connect_error) {
    die(json_encode(['success' => false, 'message' => 'Database connection failed']));
}

$uploadDir = 'study_materials/';
if (!file_exists($uploadDir)) {
    if (!mkdir($uploadDir, 0777, true)) {
        die(json_encode(['success' => false, 'message' => 'Failed to create upload directory']));
    }
}

header('Content-Type: application/json');

try {
    if (!isset($_POST['topic_id']) || !isset($_FILES['material_file'])) {
        throw new Exception('Missing required fields');
    }

    $topicId = (int)$_POST['topic_id'];
    $title = trim($_POST['title']);
    $description = trim($_POST['description'] ?? '');
    
    $file = $_FILES['material_file'];
    $allowedTypes = [
        'application/pdf', 
        'application/msword', 
        'application/vnd.ms-powerpoint', 
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'image/jpeg', 
        'image/png', 
        'video/mp4', 
        'video/quicktime'
    ];
    
    if (!in_array($file['type'], $allowedTypes)) {
        throw new Exception('Invalid file type. Only PDF, Word, PowerPoint, images, and videos are allowed.');
    }
    
    if ($file['size'] > 50 * 1024 * 1024) {
        throw new Exception('File size too large (max 50MB)');
    }
    
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '.' . $extension;
    $filepath = $uploadDir . $filename;
    
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        throw new Exception('Failed to upload file. Please try again.');
    }
    
    $stmt = $studyConn->prepare("INSERT INTO materials (topic_id, title, description, file_path, file_name) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issss", $topicId, $title, $description, $filepath, $file['name']);
    if (!$stmt->execute()) {
        throw new Exception('Failed to save file information to database');
    }
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    // Clean up if file was uploaded but DB failed
    if (isset($filepath) && file_exists($filepath)) {
        unlink($filepath);
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>