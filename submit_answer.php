<?php
session_start();
require 'db.php';

// Enable error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Verify user is logged in
if (!isset($_SESSION['user']['id'])) {
    die(json_encode(['success' => false, 'message' => 'User not logged in']));
}

$user_id = (int)$_SESSION['user']['id'];
$test_id = (int)$_POST['test_id'];
$start_time = isset($_POST['start_time']) ? (int)$_POST['start_time'] : time();

// Get test details with proper validation
$test_stmt = $conn->prepare("
    SELECT 
        duration_minutes,
        DATE_ADD(start_datetime, INTERVAL duration_minutes MINUTE) AS calculated_end
    FROM tests 
    WHERE id = ?
");
$test_stmt->bind_param("i", $test_id);
$test_stmt->execute();
$test = $test_stmt->get_result()->fetch_assoc();

if (!$test) {
    die(json_encode(['success' => false, 'message' => 'Test not found']));
}

// Calculate maximum allowed time (start time + duration + 5 minutes grace period)
$max_allowed_time = $start_time + ($test['duration_minutes'] * 60) + 300;

// Validate submission time
if (time() > $max_allowed_time) {
    die(json_encode(['success' => false, 'message' => 'Test time has expired']));
}

// Prepare statement for inserting answers
$insert_stmt = $conn->prepare("
    INSERT INTO user_answers 
    (user_id, test_id, question_id, selected_option, answered_at) 
    VALUES (?, ?, ?, ?, NOW())
");

// Process each answer
$success = true;
$errors = [];
foreach ($_POST['answers'] as $question_id => $answer_value) {
    $question_id = (int)$question_id;
    $answer_value = (int)$answer_value;
    
    $insert_stmt->bind_param("iiii", $user_id, $test_id, $question_id, $answer_value);
    
    if (!$insert_stmt->execute()) {
        $success = false;
        $errors[] = "Failed to save answer for question $question_id";
        error_log("Failed to save answer: " . $conn->error);
    }
}

if ($success) {
    echo json_encode([
        'success' => true, 
        'message' => 'Test submitted successfully!',
        'redirect' => 'profile.php?tab=test'
    ]);
} else {
    echo json_encode([
        'success' => false, 
        'message' => 'Some answers were not saved',
        'errors' => $errors
    ]);
}

// Close connections
$insert_stmt->close();
$test_stmt->close();
$conn->close();
?>