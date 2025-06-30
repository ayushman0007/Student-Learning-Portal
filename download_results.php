<?php
session_start();
require 'db.php';
require __DIR__ . '/vendor/autoload.php'; // This loads TCPDF and other Composer dependencies

if (!isset($_SESSION["user"])) {
    header("Location: login.php");
    exit;
}

if (isset($_GET['test_id'])) {
    $test_id = (int)$_GET['test_id'];
    $uid = (int)$_SESSION['user']['id'];
    
    // Get test details
    $test = $conn->query("SELECT * FROM tests WHERE id = $test_id")->fetch_assoc();
    
    // Get user's answers
    $user_answers = $conn->query("
        SELECT ua.question_id, ua.selected_option, q.correct_option, q.question, 
               q.option1, q.option2, q.option3, q.option4
        FROM user_answers ua
        JOIN questions q ON ua.question_id = q.id
        WHERE ua.user_id = $uid AND ua.test_id = $test_id
    ");

    $total_questions = $user_answers->num_rows;
    $correct_answers = 0;
    $results = [];
    
    while ($answer = $user_answers->fetch_assoc()) {
        $is_correct = ($answer['selected_option'] == $answer['correct_option']);
        if ($is_correct) $correct_answers++;
        $results[] = $answer;
    }

    $score_percentage = $total_questions > 0 ? round(($correct_answers / $total_questions) * 100) : 0;
    
    // Calculate grade
    if ($score_percentage >= 90) {
        $grade = 'A';
        $gpa = 4.0;
        $grade_color = '#4CAF50'; // Green
    } elseif ($score_percentage >= 80) {
        $grade = 'B';
        $gpa = 3.0;
        $grade_color = '#2196F3'; // Blue
    } else {
        $grade = 'F';
        $gpa = 0.0;
        $grade_color = '#f44336'; // Red
    }

    // Create new PDF document
    $pdf = new \TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

    // Set document information
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor('Test System');
    $pdf->SetTitle('Test Results - ' . $test['test_name']);
    $pdf->SetSubject('Test Results');

    // Set default header data
    $pdf->SetHeaderData('', 0, 'Test Results', 'Generated on ' . date('M j, Y H:i'));

    // Set header and footer fonts
    $pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
    $pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));

    // Set default monospaced font
    $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

    // Set margins
    $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
    $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
    $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

    // Set auto page breaks
    $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

    // Set image scale factor
    $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

    // Add a page
    $pdf->AddPage();

    // Set font
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, $test['test_name'], 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 10, 'Test Results for ' . $_SESSION['user']['full_name'], 0, 1, 'C');
    $pdf->Ln(10);

    // Test summary
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, 'Test Summary', 0, 1);
    $pdf->SetFont('helvetica', '', 12);

    // Summary table
    $html = '<table border="0.5" cellpadding="4">
        <tr>
            <th width="40%" bgcolor="#f2f2f2">Metric</th>
            <th width="60%">Value</th>
        </tr>
        <tr>
            <td bgcolor="#f2f2f2">Score</td>
            <td>' . $correct_answers . ' out of ' . $total_questions . ' (' . $score_percentage . '%)</td>
        </tr>
        <tr>
            <td bgcolor="#f2f2f2">Grade</td>
            <td><span style="color:' . $grade_color . ';font-weight:bold;">' . $grade . '</span></td>
        </tr>
        <tr>
            <td bgcolor="#f2f2f2">GPA</td>
            <td>' . $gpa . '</td>
        </tr>
        <tr>
            <td bgcolor="#f2f2f2">Date</td>
            <td>' . date('M j, Y H:i') . '</td>
        </tr>
    </table>';

    $pdf->writeHTML($html, true, false, true, false, '');
    $pdf->Ln(10);

    // Detailed results
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, 'Question Details', 0, 1);
    $pdf->SetFont('helvetica', '', 12);

    foreach ($results as $index => $result) {
        $question_num = $index + 1;
        $is_correct = ($result['selected_option'] == $result['correct_option']);
        
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 10, 'Question ' . $question_num . ': ' . ($is_correct ? '✓ Correct' : '✗ Incorrect'), 0, 1);
        $pdf->SetFont('helvetica', '', 12);
        
        // Question text
        $pdf->MultiCell(0, 0, $result['question'], 0, 'L');
        $pdf->Ln(5);
        
        // Options
        for ($i = 1; $i <= 4; $i++) {
            if (!empty($result['option' . $i])) {
                $option_style = '';
                if ($i == $result['selected_option']) {
                    $option_style = 'background-color: ' . ($is_correct ? '#dff0d8' : '#f2dede') . ';';
                }
                if ($i == $result['correct_option']) {
                    $option_style = 'background-color: #dff0d8;';
                }
                
                $html = '<div style="' . $option_style . 'padding:3px;margin-bottom:2px;">';
                $html .= '<strong>Option ' . $i . ':</strong> ' . $result['option' . $i];
                if ($i == $result['selected_option']) {
                    $html .= ' <em>(Your answer)</em>';
                }
                if ($i == $result['correct_option']) {
                    $html .= ' <em>(Correct answer)</em>';
                }
                $html .= '</div>';
                
                $pdf->writeHTML($html, true, false, true, false, '');
            }
        }
        
        $pdf->Ln(8);
    }

    // Close and output PDF document
    $pdf->Output('Test_Results_' . $test_id . '.pdf', 'D');
    exit;
}

header("Location: profile.php?tab=test");
exit;
?>