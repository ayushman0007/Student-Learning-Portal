<?php
// Load TCPDF from the correct path
require __DIR__ . '/vendor/autoload.php';

// Database connection
$conn = new mysqli("localhost", "root", "", "user_auth");
if ($conn->connect_error) {
    die(json_encode(['success' => false, 'message' => 'Database connection failed']));
}

// Set headers and clean output
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// Disable error display
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Function to send consistent JSON responses
function sendJsonResponse($data) {
    if (ob_get_length()) ob_clean();
    echo json_encode($data);
    exit;
}

try {
    // Get the action parameter
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    
    if (!$action) {
        throw new Exception("No action specified", 400);
    }

    // Handle different actions
    switch ($action) {
        case 'add_question':
        case 'edit_question':
            // Validate required fields
            $required = ['test_id', 'question', 'option1', 'option2', 'option3', 'option4', 'correct', 'weightage'];
            foreach ($required as $field) {
                if (empty($_POST[$field])) {
                    throw new Exception("Missing required field: $field", 400);
                }
            }

            // Sanitize inputs
            $test_id = (int)$_POST['test_id'];
            $question = trim($_POST['question']);
            $option1 = trim($_POST['option1']);
            $option2 = trim($_POST['option2']);
            $option3 = trim($_POST['option3']);
            $option4 = trim($_POST['option4']);
            $correct = (int)$_POST['correct'];
            $weightage = (int)$_POST['weightage'];
            $question_id = !empty($_POST['question_id']) ? (int)$_POST['question_id'] : null;

            // Validate correct option
            if ($correct < 1 || $correct > 4) {
                throw new Exception("Correct option must be between 1 and 4", 400);
            }

            // For add_question, check for duplicates
            if ($action === 'add_question') {
                $check = $conn->prepare("SELECT id FROM questions WHERE test_id = ? AND question = ?");
                $check->bind_param("is", $test_id, $question);
                $check->execute();
                $check->store_result();
                if ($check->num_rows > 0) {
                    throw new Exception("Question already exists for this test", 400);
                }
            }

            // Prepare the query
            if ($action === 'add_question') {
                $stmt = $conn->prepare("INSERT INTO questions 
                    (test_id, question, option1, option2, option3, option4, correct_option, weightage) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                if (!$stmt) throw new Exception("Prepare failed: " . $conn->error, 500);
                $stmt->bind_param("isssssii", $test_id, $question, $option1, $option2, $option3, $option4, $correct, $weightage);
            } else {
                if (!$question_id) throw new Exception("Question ID required for editing", 400);
                $stmt = $conn->prepare("UPDATE questions SET 
                    question = ?, option1 = ?, option2 = ?, option3 = ?, option4 = ?, 
                    correct_option = ?, weightage = ? WHERE id = ?");
                if (!$stmt) throw new Exception("Prepare failed: " . $conn->error, 500);
                $stmt->bind_param("sssssiii", $question, $option1, $option2, $option3, $option4, $correct, $weightage, $question_id);
            }

            // Execute the query
            if (!$stmt->execute()) {
                throw new Exception("Execute failed: " . $stmt->error, 500);
            }

            // Get the question ID if it was an insert
            $question_id = $action === 'add_question' ? $stmt->insert_id : $question_id;

            sendJsonResponse([
                'success' => true,
                'message' => 'Question ' . ($action === 'add_question' ? 'added' : 'updated') . ' successfully',
                'question_id' => $question_id
            ]);
            break;

        // Keep all your existing cases below exactly as they are
        case 'get_questions':
            $test_id = intval($_GET['test_id'] ?? 0);
            $stmt = $conn->prepare("SELECT id, question FROM questions WHERE test_id = ?");
            $stmt->bind_param("i", $test_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $questions = [];
            while ($row = $result->fetch_assoc()) {
                $questions[] = $row;
            }
            sendJsonResponse($questions);
            break;

        case 'fetch_test_results':
            $sql = "SELECT ua.user_id, ua.test_id, u.full_name as user_name, t.test_name 
                    FROM user_answers ua 
                    JOIN user_accounts u ON ua.user_id = u.id 
                    JOIN tests t ON ua.test_id = t.id 
                    GROUP BY ua.user_id, ua.test_id";
            $result = $conn->query($sql);
            $data = [];
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            sendJsonResponse($data);
            break;

        case 'view_answers':
            $user_id = intval($_GET['user_id'] ?? 0);
            $test_id = intval($_GET['test_id'] ?? 0);
            $sql = "SELECT q.question, q.option1, q.option2, q.option3, q.option4, a.selected_option 
                    FROM questions q 
                    JOIN user_answers a ON q.id = a.question_id 
                    WHERE a.user_id = $user_id AND a.test_id = $test_id";
            $result = $conn->query($sql);
            $data = [];
            while ($row = $result->fetch_assoc()) {
                $data[] = [
                    'question' => $row['question'],
                    'options' => [$row['option1'], $row['option2'], $row['option3'], $row['option4']],
                    'selected_option' => $row['selected_option']
                ];
            }
            sendJsonResponse($data);
            break;

        case 'create_test':
            $test_name = trim($_POST['test_name'] ?? '');
            if (!$test_name) {
                throw new Exception("Test name is required", 400);
            }
            $stmt = $conn->prepare("INSERT INTO tests (test_name) VALUES (?)");
            $stmt->bind_param("s", $test_name);
            if ($stmt->execute()) {
                sendJsonResponse(['success' => true, 'test_id' => $stmt->insert_id]);
            } else {
                throw new Exception("DB insert failed", 500);
            }
            break;

        case 'get_tests':
            $tests = [];
            $res = $conn->query("SELECT id, test_name, start_datetime, end_datetime, duration_minutes FROM tests ORDER BY id DESC");
            while ($row = $res->fetch_assoc()) {
                $questionsRes = $conn->prepare("SELECT id, question FROM questions WHERE test_id=?");
                $questionsRes->bind_param("i", $row['id']);
                $questionsRes->execute();
                $questionsResult = $questionsRes->get_result();
                $questions = [];
                while ($q = $questionsResult->fetch_assoc()) {
                    $questions[] = $q;
                }
                $row['questions'] = $questions;
                $tests[] = $row;
            }
            sendJsonResponse(['success' => true, 'tests' => $tests]);
            break;

        case 'get_questions_full':
            $test_id = intval($_GET['test_id'] ?? 0);
            $stmt = $conn->prepare("SELECT * FROM questions WHERE test_id = ?");
            $stmt->bind_param("i", $test_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $questions = [];
            while ($row = $result->fetch_assoc()) $questions[] = $row;
            sendJsonResponse($questions);
            break;

        case 'score_user_test':
            $user_id = intval($_GET['user_id'] ?? 0);
            $test_id = intval($_GET['test_id'] ?? 0);
            $sql = "SELECT SUM(q.weightage) AS score
                    FROM user_answers ua
                    JOIN questions q ON ua.question_id = q.id
                    WHERE ua.test_id = ? AND ua.user_id = ? AND ua.selected_option = q.correct_option";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $test_id, $user_id);
            $stmt->execute();
            $res = $stmt->get_result()->fetch_assoc();
            sendJsonResponse(["score" => $res['score'] ?? 0]);
            break;

        case 'get_all_tests':
            try {
                if ($conn->connect_error) {
                    throw new Exception("Database connection failed: " . $conn->connect_error);
                }
                $result = $conn->query("SELECT id, test_name FROM tests ORDER BY id DESC");
                if ($result === false) {
                    throw new Exception("Query failed: " . $conn->error);
                }
                $tests = [];
                while ($row = $result->fetch_assoc()) {
                    $tests[] = $row;
                }
                sendJsonResponse(['success' => true, 'tests' => $tests]);
            } catch (Exception $e) {
                http_response_code(500);
                sendJsonResponse(['success' => false, 'message' => $e->getMessage()]);
            }
            break;

        case 'get_test_students':
            $test_id = intval($_GET['test_id'] ?? 0);
            if (!$test_id) {
                throw new Exception("Test ID required", 400);
            }
            $sql = "SELECT DISTINCT u.id, u.full_name 
                    FROM user_answers ua
                    JOIN user_accounts u ON ua.user_id = u.id
                    WHERE ua.test_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $test_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $students = $result->fetch_all(MYSQLI_ASSOC);
            sendJsonResponse(['success' => true, 'students' => $students]);
            break;

        case 'get_test_students_with_scores':
            $test_id = intval($_GET['test_id'] ?? 0);
            if (!$test_id) {
                throw new Exception("Test ID required", 400);
            }
            $sql = "SELECT u.id, u.full_name,
                    SUM(CASE WHEN ua.selected_option = q.correct_option THEN q.weightage ELSE 0 END) as score,
                    SUM(q.weightage) as max_score
                    FROM user_answers ua
                    JOIN user_accounts u ON ua.user_id = u.id
                    JOIN questions q ON ua.question_id = q.id
                    WHERE ua.test_id = ?
                    GROUP BY u.id
                    ORDER BY u.full_name";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $test_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $students = $result->fetch_all(MYSQLI_ASSOC);
            sendJsonResponse(['success' => true, 'students' => $students]);
            break;

        case 'get_student_results':
            $test_id = intval($_GET['test_id'] ?? 0);
            $user_id = intval($_GET['user_id'] ?? 0);
            if (!$test_id || !$user_id) {
                throw new Exception("Test ID and User ID required", 400);
            }
            // Get test details
            $test_stmt = $conn->prepare("SELECT test_name FROM tests WHERE id = ?");
            $test_stmt->bind_param("i", $test_id);
            $test_stmt->execute();
            $test = $test_stmt->get_result()->fetch_assoc();
            // Get student details
            $user_stmt = $conn->prepare("SELECT full_name FROM user_accounts WHERE id = ?");
            $user_stmt->bind_param("i", $user_id);
            $user_stmt->execute();
            $user = $user_stmt->get_result()->fetch_assoc();
            // Get answers and calculate score
            $answers_stmt = $conn->prepare("
                SELECT q.question, q.option1, q.option2, q.option3, q.option4, 
                       q.correct_option, ua.selected_option, q.weightage
                FROM user_answers ua
                JOIN questions q ON ua.question_id = q.id
                WHERE ua.test_id = ? AND ua.user_id = ?
            ");
            $answers_stmt->bind_param("ii", $test_id, $user_id);
            $answers_stmt->execute();
            $answers = $answers_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            // Calculate total score
            $total_score = 0;
            $max_score = 0;
            foreach ($answers as $answer) {
                $max_score += $answer['weightage'];
                if ($answer['selected_option'] == $answer['correct_option']) {
                    $total_score += $answer['weightage'];
                }
            }
            sendJsonResponse([
                'success' => true,
                'test' => $test,
                'user' => $user,
                'answers' => $answers,
                'score' => $total_score,
                'max_score' => $max_score
            ]);
            break;

        case 'get_test_report':
            $test_id = intval($_GET['test_id'] ?? 0);
            if (!$test_id) {
                throw new Exception("Test ID required", 400);
            }
            try {
                // Get test details
                $test_stmt = $conn->prepare("SELECT id, test_name, start_datetime, end_datetime FROM tests WHERE id = ?");
                $test_stmt->bind_param("i", $test_id);
                $test_stmt->execute();
                $test_result = $test_stmt->get_result();
                if ($test_result->num_rows === 0) {
                    throw new Exception("Test not found", 404);
                }
                $test = $test_result->fetch_assoc();
                // Get all students who took this test with their scores
                $students_stmt = $conn->prepare("
                    SELECT u.id, u.full_name,
                    SUM(CASE WHEN ua.selected_option = q.correct_option THEN q.weightage ELSE 0 END) as score,
                    SUM(q.weightage) as max_score
                    FROM user_answers ua
                    JOIN user_accounts u ON ua.user_id = u.id
                    JOIN questions q ON ua.question_id = q.id
                    WHERE ua.test_id = ?
                    GROUP BY u.id
                    ORDER BY u.full_name
                ");
                $students_stmt->bind_param("i", $test_id);
                $students_stmt->execute();
                $students_result = $students_stmt->get_result();
                $students = $students_result->fetch_all(MYSQLI_ASSOC);
                // Calculate overall statistics
                $total_students = count($students);
                $passed_students = 0;
                $avg_score = 0;
                foreach ($students as $student) {
                    $percentage = ($student['max_score'] > 0) ? ($student['score'] / $student['max_score']) * 100 : 0;
                    $avg_score += $percentage;
                    if ($percentage >= 70) {
                        $passed_students++;
                    }
                }
                $avg_score = ($total_students > 0) ? $avg_score / $total_students : 0;
                sendJsonResponse([
                    'success' => true,
                    'test' => $test,
                    'students' => $students,
                    'stats' => [
                        'total_students' => $total_students,
                        'passed_students' => $passed_students,
                        'failed_students' => $total_students - $passed_students,
                        'avg_score' => $avg_score
                    ]
                ]);
            } catch (Exception $e) {
                sendJsonResponse(['success' => false, 'message' => 'Error generating report: ' . $e->getMessage()]);
            }
            break;

        case 'generate_certificate':
            $test_id = intval($_GET['test_id'] ?? 0);
            $user_id = intval($_GET['user_id'] ?? 0);
            $student_name = $_GET['student_name'] ?? 'Student';
            $test_name = $_GET['test_name'] ?? 'Test';
            $score = intval($_GET['score'] ?? 0);
            $max_score = intval($_GET['max_score'] ?? 1);
            $percentage = intval($_GET['percentage'] ?? 0);
            $grade = $_GET['grade'] ?? 'F';
            
            // Check if grade is F - no certificate
            if ($grade === 'F') {
                // Create a simple PDF indicating no certificate
                $pdf = new TCPDF();
                $pdf->AddPage();
                $pdf->SetFont('helvetica', 'B', 16);
                $pdf->Cell(0, 10, 'Certificate Not Available', 0, 1, 'C');
                $pdf->SetFont('helvetica', '', 12);
                $pdf->Cell(0, 10, 'Student: ' . $student_name, 0, 1, 'C');
                $pdf->Cell(0, 10, 'Test: ' . $test_name, 0, 1, 'C');
                $pdf->Cell(0, 10, 'Score: ' . $score . '/' . $max_score . ' (' . $percentage . '%)', 0, 1, 'C');
                $pdf->Cell(0, 10, 'Grade: F - Certificate not awarded', 0, 1, 'C');
                $pdf->Output('No_Certificate_' . $student_name . '.pdf', 'D');
                exit;
            }
            
            // Create certificate PDF for passing grades
            $pdf = new TCPDF('L', PDF_UNIT, 'A4', true, 'UTF-8', false);
            $pdf->SetCreator(PDF_CREATOR);
            $pdf->SetAuthor('Your Organization');
            $pdf->SetTitle('Certificate of Achievement');
            $pdf->SetSubject('Certificate');
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            $pdf->AddPage();
            
            // Certificate border
            $pdf->SetLineWidth(1.5);
            $pdf->Rect(10, 10, 277, 190);
            
            // Add decorative elements (replace with your logo path)
            if (file_exists('images/logo.png')) {
                $pdf->Image('images/logo.png', 30, 20, 30, 30, '', '', '', false, 300, '', false, false, 0);
                $pdf->Image('images/logo.png', 237, 20, 30, 30, '', '', '', false, 300, '', false, false, 0);
            }
            
            // Certificate content
            $pdf->SetFont('helvetica', 'B', 24);
            $pdf->SetXY(0, 30);
            $pdf->Cell(0, 10, 'CERTIFICATE OF ACHIEVEMENT', 0, 1, 'C');
            
            $pdf->SetFont('helvetica', '', 14);
            $pdf->SetXY(0, 45);
            $pdf->Cell(0, 10, 'This is to certify that', 0, 1, 'C');
            
            $pdf->SetFont('helvetica', 'B', 20);
            $pdf->SetXY(0, 60);
            $pdf->Cell(0, 10, $student_name, 0, 1, 'C');
            
            $pdf->SetFont('helvetica', '', 14);
            $pdf->SetXY(0, 80);
            $pdf->Cell(0, 10, 'has successfully completed the assessment for', 0, 1, 'C');
            
            $pdf->SetFont('helvetica', 'B', 18);
            $pdf->SetXY(0, 95);
            $pdf->Cell(0, 10, $test_name, 0, 1, 'C');
            
            $pdf->SetFont('helvetica', '', 14);
            $pdf->SetXY(0, 115);
            $pdf->Cell(0, 10, 'with a score of ' . $score . ' out of ' . $max_score . ' (' . $percentage . '%)', 0, 1, 'C');
            
            // Grade display with color based on grade
            if ($grade === 'A') $pdf->SetTextColor(40, 167, 69); // Green
            elseif ($grade === 'B') $pdf->SetTextColor(23, 162, 184); // Teal
            elseif ($grade === 'C') $pdf->SetTextColor(255, 193, 7); // Yellow
            else $pdf->SetTextColor(0, 0, 0); // Black
            
            $pdf->SetFont('helvetica', 'B', 16);
            $pdf->SetXY(0, 135);
            $pdf->Cell(0, 10, 'Grade: ' . $grade, 0, 1, 'C');
            $pdf->SetTextColor(0, 0, 0); // Reset to black
            
            // Date and signature
            $pdf->SetFont('helvetica', 'I', 12);
            $pdf->SetXY(0, 160);
            $pdf->Cell(0, 10, 'Date: ' . date('F j, Y'), 0, 1, 'C');
            
            $pdf->SetXY(0, 180);
            $pdf->Cell(0, 10, 'Authorized Signature', 0, 1, 'C');
            
            // Output PDF
            $pdf->Output('Certificate_' . $student_name . '_' . $test_name . '.pdf', 'D');
            exit;

        default:
            throw new Exception("Invalid action", 400);
    }

} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    sendJsonResponse([
        'success' => false,
        'error' => $e->getMessage(),
        'error_code' => $e->getCode()
    ]);
}