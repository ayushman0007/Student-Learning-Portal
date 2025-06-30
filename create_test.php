<?php
require 'db.php';
require 'PHPMailer-master/src/Exception.php';
require 'PHPMailer-master/src/PHPMailer.php';
require 'PHPMailer-master/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Clear any previous output
while (ob_get_level()) ob_end_clean();

// Set proper headers
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

// Disable error display
ini_set('display_errors', 0);
error_reporting(E_ALL);

function sendJsonResponse($data) {
    if (ob_get_length()) ob_clean();
    echo json_encode($data);
    exit;
}

try {
    // Get raw POST data
    $input = json_decode(file_get_contents('php://input'), true);
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Only POST method is allowed", 405);
    }
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Invalid JSON input", 400);
    }

    // Validate required fields
    $required = ['test_name', 'start_datetime', 'end_datetime', 'duration'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            throw new Exception("Missing required field: $field", 400);
        }
    }

    // Insert test into database
    $stmt = $conn->prepare("INSERT INTO tests (test_name, start_datetime, end_datetime, duration_minutes) VALUES (?, ?, ?, ?)");
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error, 500);
    }
    
    $start = date('Y-m-d H:i:s', strtotime($input['start_datetime']));
    $end = date('Y-m-d H:i:s', strtotime($input['end_datetime']));
    $duration = (int)$input['duration'];
    
    $stmt->bind_param("sssi", $input['test_name'], $start, $end, $duration);
    
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error, 500);
    }

    $testId = $stmt->insert_id;
    $notificationSent = false;
    
    // Send notifications if requested
    if (!empty($input['send_notification']) && $input['send_notification']) {
        $notificationSent = sendTestNotifications($testId, $input['test_name'], $start, $end);
    }

    sendJsonResponse([
        'success' => true,
        'test_id' => $testId,
        'notification_sent' => $notificationSent,
        'message' => 'Test created successfully' . ($notificationSent ? ' and notifications sent' : '')
    ]);
    
} catch (Exception $e) {
    sendJsonResponse([
        'success' => false,
        'error' => $e->getMessage(),
        'error_code' => $e->getCode()
    ]);
}

function sendTestNotifications($testId, $testName, $startDate, $endDate) {
    // Connect to user database
    $userConn = new mysqli("localhost", "root", "", "user_auth");
    if ($userConn->connect_error) {
        error_log("User DB connection failed: " . $userConn->connect_error);
        return false;
    }
    
    // Get all active users
    $result = $userConn->query("SELECT email, full_name FROM user_accounts WHERE is_active = 1");
    if (!$result || $result->num_rows === 0) {
        $userConn->close();
        return false;
    }
    
    $sentCount = 0;
    $testLink = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . 
                "://$_SERVER[HTTP_HOST]/myform/take_test.php?test_id=$testId";
    
    while ($user = $result->fetch_assoc()) {
        try {
            $mail = new PHPMailer(true);
            
            // SMTP Configuration
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'singhayushman2025@gmail.com'; // Your Gmail
            $mail->Password   = 'liebazipypcajiqa'; // Your App Password
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;
            
            // Email content
            $mail->setFrom('singhayushman2025@gmail.com', 'Online Exam System');
            $mail->addAddress($user['email'], $user['full_name']);
            
            $mail->Subject = "New Test Available: $testName";
            $mail->isHTML(true);
            $mail->Body = "
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                        .header { background-color: #007BFF; color: white; padding: 15px; text-align: center; }
                        .content { padding: 20px; background-color: #f9f9f9; }
                        .button {
                            display: inline-block;
                            padding: 10px 20px;
                            background-color: #28a745;
                            color: white;
                            text-decoration: none;
                            border-radius: 4px;
                            margin: 15px 0;
                        }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'>
                            <h2>New Test Notification</h2>
                        </div>
                        <div class='content'>
                            <p>Hello {$user['full_name']},</p>
                            <p>A new test <strong>$testName</strong> has been created and is now available for you to take.</p>
                            
                            <h3>Test Details:</h3>
                            <ul>
                                <li><strong>Start Date:</strong> " . date('F j, Y, g:i a', strtotime($startDate)) . "</li>
                                <li><strong>End Date:</strong> " . date('F j, Y, g:i a', strtotime($endDate)) . "</li>
                            </ul>
                            
                            <p>Click the button below to access the test:</p>
                            <p><a href='$testLink' class='button'>Take Test Now</a></p>
                            
                            <p>Or copy and paste this link into your browser:<br>
                            <small>$testLink</small></p>
                        </div>
                    </div>
                </body>
                </html>
            ";
            
            if ($mail->send()) {
                $sentCount++;
                error_log("Email sent to: {$user['email']}");
            } else {
                error_log("Failed to send to: {$user['email']} - Error: {$mail->ErrorInfo}");
            }
        } catch (Exception $e) {
            error_log("Mailer Error for {$user['email']}: {$mail->ErrorInfo}");
        }
    }
    
    $userConn->close();
    return $sentCount > 0;
}
?>