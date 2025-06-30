<?php
ob_start();
session_start();
date_default_timezone_set('Asia/Kolkata');
echo "<!-- Timezone: " . date_default_timezone_get() . " -->";

if (!isset($_SESSION["user"])) {
    header("Location: login.php");
    ob_end_flush();
    exit;
}

require 'db.php';
if (!$conn) {
    die("Database connection failed");
}

$user = $_SESSION["user"];
$tab = isset($_GET['tab']) ? htmlspecialchars($_GET['tab']) : 'profile';

$allowedTabs = ['profile', 'documents', 'test', 'study_materials', 'forum'];
if (!in_array($tab, $allowedTabs)) {
    $tab = 'profile';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Profile</title>
    <style>
        :root {
            --primary-color: #007bff;
            --primary-hover: #0056b3;
            --success-color: #28a745;
            --success-hover: #218838;
            --danger-color: #dc3545;
            --danger-hover: #c82333;
            --border-radius: 8px;
            --box-shadow: 0 0 12px rgba(0,0,0,0.1);
            --transition: all 0.3s ease;
        }

        /* Grade display styles */
        .grade-display {
            margin: 20px auto;
            padding: 15px;
            background: #f8f9fa;
            border-radius: var(--border-radius);
            text-align: center;
            max-width: 300px;
            border-left: 4px solid;
        }

        .grade-A {
            border-left-color: #4CAF50;
            background-color: #e8f5e9;
        }

        .grade-B {
            border-left-color: #2196F3;
            background-color: #e3f2fd;
        }

        .grade-F {
            border-left-color: #f44336;
            background-color: #ffebee;
        }

        .grade-value {
            font-size: 24px;
            font-weight: bold;
            margin: 5px 0;
        }

        .grade-A .grade-value {
            color: #2E7D32;
        }

        .grade-B .grade-value {
            color: #1565C0;
        }

        .grade-F .grade-value {
            color: #C62828;
        }

        .grade-description {
            font-size: 16px;
            color: #495057;
        }

        .gpa-value {
            font-size: 18px;
            font-weight: bold;
            margin: 5px 0;
            color: inherit;
        }

        /* Filter and action button styles */
        .test-filters {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .filter-btn {
            padding: 8px 16px;
            background: #e0e0e0;
            border: none;
            border-radius: 20px;
            cursor: pointer;
            transition: var(--transition);
            font-size: 14px;
        }

        .filter-btn.active {
            background: var(--primary-color);
            color: white;
        }

        .filter-btn:hover {
            background: #d0d0d0;
        }

        .filter-btn.active:hover {
            background: var(--primary-hover);
        }

        .result-actions {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-top: 20px;
            flex-wrap: wrap;
        }

        .details-button {
            padding: 10px 20px;
            background: var(--primary-color);
            color: white;
            text-decoration: none;
            border-radius: var(--border-radius);
            transition: var(--transition);
            font-size: 14px;
        }

        .download-button {
            padding: 10px 20px;
            background: var(--success-color);
            color: white;
            text-decoration: none;
            border-radius: var(--border-radius);
            transition: var(--transition);
            font-size: 14px;
        }

        .back-button {
            padding: 10px 20px;
            background: #6c757d;
            color: white;
            text-decoration: none;
            border-radius: var(--border-radius);
            transition: var(--transition);
            font-size: 14px;
        }

        .details-button:hover, 
        .download-button:hover, 
        .back-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }

        /* Skipped test styles */
        .test-card.skipped {
            border-top: 4px solid #FF9800;
        }

        .skipped-marker {
            margin-left: 5px;
            color: #FF9800;
        }

        .skipped-button {
            padding: 8px 16px;
            background: #FF9800;
            color: white;
            border-radius: var(--border-radius);
            font-size: 14px;
            display: inline-block;
        }

        /* Base styles */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8f9fa;
            margin: 0;
            padding: 0;
            color: #333;
            line-height: 1.6;
        }

        .container {
            width: 85%;
            max-width: 1200px;
            margin: 30px auto;
            background: white;
            padding: 30px;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
        }

        h2 {
            text-align: center;
            margin-bottom: 30px;
            color: #2c3e50;
        }

        .tabs {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 25px;
        }

        .tabs button {
            padding: 12px 25px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            font-size: 16px;
            cursor: pointer;
            transition: var(--transition);
            flex: 1;
            min-width: 120px;
            max-width: 200px;
        }

        .tabs button:hover {
            background-color: var(--primary-hover);
            transform: translateY(-2px);
        }

        .tabs button.active {
            background-color: var(--primary-hover);
            font-weight: bold;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .tab-content {
            display: none;
            animation: fadeIn 0.5s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .tab-content.active {
            display: block;
        }

        .profile-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .profile-info p {
            margin: 0;
            padding: 12px;
            background: #f8f9fa;
            border-radius: var(--border-radius);
        }

        .profile-info strong {
            display: inline-block;
            width: 80px;
            color: #495057;
        }

        .logout-btn {
            display: inline-block;
            padding: 10px 20px;
            background-color: var(--danger-color);
            color: white;
            text-decoration: none;
            border-radius: var(--border-radius);
            font-weight: bold;
            transition: var(--transition);
            text-align: center;
            margin-top: 20px;
        }

        .logout-btn:hover {
            background-color: var(--danger-hover);
            text-decoration: none;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .container {
                width: 95%;
                padding: 20px;
            }
            
            .tabs {
                flex-direction: column;
                align-items: center;
            }
            
            .tabs button {
                width: 100%;
                max-width: none;
            }

            .result-actions {
                flex-direction: column;
                align-items: center;
            }
            
            .details-button,
            .download-button,
            .back-button {
                width: 100%;
                text-align: center;
            }
        }

        /* Document upload section */
        .upload-form {
            background: #f8f9fa;
            padding: 20px;
            border-radius: var(--border-radius);
            margin-bottom: 30px;
        }

        .file-input-wrapper {
            margin: 15px 0;
        }

        .file-input-wrapper input[type="file"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: var(--border-radius);
        }

        .submit-btn {
            background-color: var(--success-color);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-size: 16px;
            transition: var(--transition);
        }

        .submit-btn:hover {
            background-color: var(--success-hover);
        }

        .documents-list {
            list-style: none;
            padding: 0;
        }

        .documents-list li {
            padding: 12px;
            margin-bottom: 10px;
            background: white;
            border: 1px solid #eee;
            border-radius: var(--border-radius);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .documents-list a {
            color: var(--primary-color);
            text-decoration: none;
            transition: var(--transition);
        }

        .documents-list a:hover {
            text-decoration: underline;
            color: var(--primary-hover);
        }

        .document-date {
            color: #6c757d;
            font-size: 0.9em;
        }

        /* Test results styles */
        .test-results-container {
            background: white;
            border-radius: var(--border-radius);
            padding: 25px;
            box-shadow: var(--box-shadow);
            max-width: 800px;
            margin: 0 auto;
        }
        
        .results-header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        
        .score-display {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 30px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        
        .score-circle {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: conic-gradient(#4CAF50 var(--percentage), #f5f5f5 0);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 20px;
            color: #333;
        }
        
        .score-details {
            text-align: left;
        }
        
        .questions-review {
            margin-top: 30px;
        }
        
        .question-review {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: var(--border-radius);
        }
        
        .question-review.correct {
            background-color: #f0fff0;
            border-left: 4px solid #4CAF50;
        }
        
        .question-review.incorrect {
            background-color: #fff0f0;
            border-left: 4px solid #f44336;
        }
        
        .option-review {
            padding: 8px 12px;
            margin: 5px 0;
            border-radius: 4px;
            position: relative;
        }
        
        .option-review.selected {
            background-color: #e3f2fd;
        }
        
        .option-review.correct-answer {
            background-color: #e8f5e9;
        }
        
        .answer-marker {
            margin-left: 10px;
            font-weight: bold;
        }
        
        .answer-marker:before {
            content: 'Your answer: ';
            color: #666;
            font-weight: normal;
        }

        /* Test grid styles */
        .test-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
            margin-top: 25px;
        }
        
        .test-card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            overflow: hidden;
            border: 1px solid #e0e0e0;
            transition: var(--transition);
        }
        
        .test-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .test-card.available {
            border-top: 4px solid #4CAF50;
        }
        
        .test-card.upcoming {
            border-top: 4px solid #FFC107;
        }
        
        .test-card.expired {
            border-top: 4px solid #9E9E9E;
        }
        
        .test-card.attempted {
            position: relative;
        }
        
        .test-card.attempted:after {
            content: '✓';
            position: absolute;
            top: 10px;
            right: 10px;
            background: #4CAF50;
            color: white;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }
        
        .test-card-header {
            padding: 15px 20px;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .test-title {
            margin: 0;
            font-size: 18px;
            color: #333;
        }
        
        .test-status-badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .test-status-badge.available {
            background: #E8F5E9;
            color: #2E7D32;
        }
        
        .test-status-badge.upcoming {
            background: #FFF8E1;
            color: #FF8F00;
        }
        
        .test-status-badge.expired {
            background: #F5F5F5;
            color: #616161;
        }
        
        .test-card-body {
            padding: 15px 20px;
        }
        
        .test-meta {
            margin-bottom: 15px;
        }
        
        .meta-item {
            display: flex;
            margin-bottom: 8px;
        }
        
        .meta-label {
            font-weight: 500;
            color: #666;
            min-width: 80px;
        }
        
        .meta-value {
            color: #333;
        }
        
        .test-actions {
            display: flex;
            justify-content: flex-end;
        }
        
        .primary-button {
            background: var(--success-color);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            transition: var(--transition);
        }
        
        .primary-button:hover {
            background: var(--success-hover);
            transform: translateY(-2px);
        }
        
        .secondary-button {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            transition: var(--transition);
        }
        
        .secondary-button:hover {
            background: var(--primary-hover);
            transform: translateY(-2px);
        }
        
        .disabled-button {
            background: #E0E0E0;
            color: #9E9E9E;
            border: none;
            padding: 8px 16px;
            border-radius: var(--border-radius);
            font-size: 14px;
            cursor: not-allowed;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            max-width: 500px;
            margin: 30px auto;
        }
        
        .empty-icon {
            font-size: 48px;
            color: #BDBDBD;
            margin-bottom: 15px;
        }
                .study-materials-container { margin-top: 20px; }
        .subject-item, .topic-item { padding: 10px; margin-bottom: 5px; background: #f8f9fa; border-radius: 4px; cursor: pointer; }
        .subject-item:hover, .topic-item:hover { background: #e9ecef; }
        .material-item { padding: 15px; border: 1px solid #ddd; border-radius: 5px; margin-bottom: 15px; }
        .material-item h5 { margin-top: 0; }
        .material-link { display: inline-block; margin-top: 10px; padding: 5px 10px; background: #007bff; color: white; border-radius: 4px; text-decoration: none; }
        .material-link:hover { background: #0056b3; }

        /* Forum styles */
        .forum-container {
            margin-top: 20px;
        }

        .forum-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .create-thread-btn {
            padding: 10px 20px;
            background-color: var(--primary-color);
            color: white;
            text-decoration: none;
            border-radius: var(--border-radius);
            transition: var(--transition);
        }

        .create-thread-btn:hover {
            background-color: var(--primary-hover);
        }

        .thread-list {
            list-style: none;
            padding: 0;
        }

        .thread-item {
            background: white;
            border-radius: var(--border-radius);
            padding: 15px;
            margin-bottom: 15px;
            box-shadow: var(--box-shadow);
        }

        .thread-title {
            font-size: 18px;
            margin: 0 0 10px 0;
            color: var(--primary-color);
        }

        .thread-meta {
            display: flex;
            justify-content: space-between;
            color: #6c757d;
            font-size: 14px;
            margin-bottom: 10px;
        }

        .thread-author {
            font-weight: bold;
        }

        .thread-stats {
            display: flex;
            gap: 15px;
        }

        .thread-excerpt {
            margin-bottom: 10px;
        }

        .thread-actions {
            display: flex;
            gap: 10px;
        }

        .thread-action-btn {
            padding: 5px 10px;
            background: #f8f9fa;
            border-radius: 4px;
            text-decoration: none;
            color: #333;
            font-size: 14px;
            transition: var(--transition);
        }

        .thread-action-btn:hover {
            background: #e9ecef;
        }

        .thread-form {
            background: white;
            border-radius: var(--border-radius);
            padding: 20px;
            box-shadow: var(--box-shadow);
            margin-bottom: 30px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }

        .form-group input[type="text"],
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: var(--border-radius);
        }

        .form-group textarea {
            min-height: 150px;
        }

        .post-list {
            list-style: none;
            padding: 0;
        }

        .post-item {
            background: white;
            border-radius: var(--border-radius);
            padding: 15px;
            margin-bottom: 15px;
            box-shadow: var(--box-shadow);
        }

        .post-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }

        .post-author {
            font-weight: bold;
        }

        .post-date {
            color: #6c757d;
            font-size: 14px;
        }

        .post-content {
            margin-bottom: 10px;
        }

        .reply-form {
            margin-top: 20px;
            background: #f8f9fa;
            padding: 15px;
            border-radius: var(--border-radius);
        }
    </style>
</head>
<body>
<div class="container">
    <h2>User Dashboard</h2>

    <div class="tabs">
        <button onclick="location.href='profile.php?tab=profile'" class="<?= $tab == 'profile' ? 'active' : '' ?>">Profile Details</button>
        <button onclick="location.href='profile.php?tab=documents'" class="<?= $tab == 'documents' ? 'active' : '' ?>">Documents</button>
        <button onclick="location.href='profile.php?tab=test'" class="<?= $tab == 'test' ? 'active' : '' ?>">Tests</button>
        <button onclick="location.href='profile.php?tab=study_materials'" class="<?= $tab == 'study_materials' ? 'active' : '' ?>">Study Materials</button>
        <button onclick="location.href='profile.php?tab=forum'" class="<?= $tab == 'forum' ? 'active' : '' ?>">Discussion Forum</button>
    </div>

    <!-- Profile Section -->
    <?php if ($tab === 'profile'): ?>
    <div class="tab-content <?= $tab == 'profile' ? 'active' : '' ?>">
        <div class="profile-info">
            <p><strong>Name:</strong> <?= htmlspecialchars($user["full_name"] ?? 'Not provided'); ?></p>
            <p><strong>Email:</strong> <?= htmlspecialchars($user["email"] ?? 'Not provided'); ?></p>
            <p><strong>Phone:</strong> <?= htmlspecialchars($user["phone"] ?? 'Not provided'); ?></p>
            <p><strong>Gender:</strong> <?= htmlspecialchars($user["gender"] ?? 'Not provided'); ?></p>
            <p><strong>DOB:</strong> <?= htmlspecialchars($user["dob"] ?? 'Not provided'); ?></p>
            <p><strong>City:</strong> <?= htmlspecialchars($user["city"] ?? 'Not provided'); ?></p>
        </div>
        <a href="logout.php" class="logout-btn">Logout</a>
    </div>
    <?php endif; ?>

    <!-- Test Section -->
    <?php if ($tab === 'test'): ?>
    <div id="test-section" class="section">
        <h2 class="section-title">Available Tests</h2>
        
        <!-- Filter buttons - only show in main test view -->
        <?php if (!isset($_GET['view_results'])): ?>
        <div class="test-filters">
            <button class="filter-btn active" data-filter="all">All Tests</button>
            <button class="filter-btn" data-filter="available">Available</button>
            <button class="filter-btn" data-filter="attempted">Attempted</button>
            <button class="filter-btn" data-filter="skipped">Skipped</button>
        </div>
        <?php endif; ?>
        
        <?php
        $uid = (int)$_SESSION['user']['id'];
        $current_time = date('Y-m-d H:i:s');

        // Handle test result viewing
        if (isset($_GET['view_results'])) {
            $test_id = (int)$_GET['view_results'];
            
            // Get test details
            $test = $conn->query("SELECT * FROM tests WHERE id = $test_id")->fetch_assoc();
            
            // Get user's answers
            $user_answers = $conn->query("
                SELECT ua.question_id, ua.selected_option, q.correct_option
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
                $results[] = [
                    'question_id' => $answer['question_id'],
                    'selected' => $answer['selected_option'],
                    'correct' => $answer['correct_option'],
                    'is_correct' => $is_correct
                ];
            }

            $score_percentage = $total_questions > 0 ? round(($correct_answers / $total_questions) * 100) : 0;
            
            // Calculate both grade and GPA
            if ($score_percentage >= 90) {
                $grade = 'A';
                $grade_class = 'grade-A';
                $grade_description = 'Excellent';
                $gpa = 4.0;
            } elseif ($score_percentage >= 80) {
                $grade = 'B';
                $grade_class = 'grade-B';
                $grade_description = 'Good';
                $gpa = 3.0;
            } else {
                $grade = 'F';
                $grade_class = 'grade-F';
                $grade_description = 'Needs Improvement';
                $gpa = 0.0;
            }
            
            // Check if we should show detailed results
            $show_details = isset($_GET['show_details']);
            ?>
            
            <div class="test-results-container">
                <div class="results-header">
                    <h3>Test Results: <?= htmlspecialchars($test['test_name']) ?></h3>
                    <div class="score-display">
                        <div class="score-circle" style="--percentage: <?= $score_percentage ?>%">
                            <span><?= $score_percentage ?>%</span>
                        </div>
                        <div class="score-details">
                            <p><strong><?= $correct_answers ?></strong> correct out of <strong><?= $total_questions ?></strong></p>
                            <?php
                            $submission_time = $conn->query("
                                SELECT MAX(answered_at) as last_submission 
                                FROM user_answers 
                                WHERE user_id = $uid AND test_id = $test_id
                            ")->fetch_assoc()['last_submission'];
                            ?>
                            <p>Submitted on: <?= $submission_time ? date('M j, Y H:i', strtotime($submission_time)) : 'Not recorded' ?></p>
                        </div>
                    </div>
                    
                    <!-- Grade Display -->
                    <div class="grade-display <?= $grade_class ?>">
                        <div class="grade-value">Grade: <?= $grade ?></div>
                        <div class="gpa-value">GPA: <?= $gpa ?></div>
                        <div class="grade-description"><?= $grade_description ?></div>
                    </div>
                    
                    <!-- Action buttons -->
                    <div class="result-actions">
                        <?php if (!$show_details): ?>
                            <a href="?tab=test&view_results=<?= $test_id ?>&show_details=1" class="details-button">
                                Show Detailed Results
                            </a>
                        <?php endif; ?>
                        <a href="download_results.php?test_id=<?= $test_id ?>" class="download-button" target="_blank">
                            Download Results
                        </a>
                        <a href="?tab=test" class="back-button">Back to Tests</a>
                    </div>
                </div>
                
                <?php if ($show_details): ?>
                <div class="questions-review">
                    <?php 
                    $questions = $conn->query("SELECT * FROM questions WHERE test_id = $test_id ORDER BY id");
                    while ($q = $questions->fetch_assoc()):
                        $user_answer = null;
                        foreach ($results as $result) {
                            if ($result['question_id'] == $q['id']) {
                                $user_answer = $result;
                                break;
                            }
                        }
                    ?>
                        <div class="question-review <?= $user_answer['is_correct'] ? 'correct' : 'incorrect' ?>">
                            <div class="question-text">
                                <strong>Question <?= $q['id'] ?>:</strong> <?= htmlspecialchars($q['question']) ?>
                            </div>
                            <div class="options-list">
                                <?php for ($i = 1; $i <= 4; $i++): ?>
                                    <?php if (!empty($q["option$i"])): ?>
                                        <div class="option-review 
                                            <?= $user_answer['selected'] == $i ? 'selected' : '' ?>
                                            <?= $q['correct_option'] == $i ? 'correct-answer' : '' ?>">
                                            <?= htmlspecialchars($q["option$i"]) ?>
                                            <?php if ($user_answer['selected'] == $i): ?>
                                                <span class="answer-marker">
                                                    <?= $user_answer['is_correct'] ? '✓' : '✗' ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                <?php endfor; ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
                <?php endif; ?>
            </div>
            
            <script>
            // Filter functionality
            document.addEventListener('DOMContentLoaded', function() {
                const filterButtons = document.querySelectorAll('.filter-btn');
                
                filterButtons.forEach(button => {
                    button.addEventListener('click', function() {
                        // Update active state
                        filterButtons.forEach(btn => btn.classList.remove('active'));
                        this.classList.add('active');
                        
                        const filter = this.dataset.filter;
                        const testCards = document.querySelectorAll('.test-card');
                        
                        testCards.forEach(card => {
                            if (filter === 'all') {
                                card.style.display = 'block';
                            } else {
                                const matchesFilter = 
                                    (filter === 'available' && card.classList.contains('available') && !card.classList.contains('attempted') && !card.classList.contains('skipped')) ||
                                    (filter === 'attempted' && card.classList.contains('attempted')) ||
                                    (filter === 'skipped' && card.classList.contains('skipped'));
                                
                                card.style.display = matchesFilter ? 'block' : 'none';
                            }
                        });
                    });
                });
            });
            </script>
            <?php
            exit;
        }

        // Check if loading test questions
        if (isset($_GET['load_test'])) {
            $test_id = (int)$_GET['load_test'];
            
            // Verify test is available
            $test = $conn->query("
                SELECT t.*, 
                (SELECT 1 FROM user_answers 
                 WHERE user_id = $uid AND test_id = $test_id LIMIT 1) AS attempted
                FROM tests t
                WHERE t.id = $test_id
                AND t.start_datetime <= '$current_time'
                AND '$current_time' <= LEAST(t.end_datetime, 
                    DATE_ADD(t.start_datetime, INTERVAL t.duration_minutes MINUTE))
            ")->fetch_assoc();

            if (!$test) {
                header("Location: ?tab=test");
                exit;
            }

            if ($test['attempted']) {
                header("Location: ?tab=test&view_results=$test_id");
                exit;
            }

            $questions = $conn->query("SELECT * FROM questions WHERE test_id = $test_id ORDER BY id");
            $duration_seconds = $test['duration_minutes'] * 60;
            
            if ($questions->num_rows > 0): ?>
                <div class="test-interface-container">
                    <form id='test-form' method='POST' action='submit_answer.php' onsubmit="return confirmSubmission()">
                        <input type='hidden' name='test_id' value='<?= $test_id ?>'>
                        <input type='hidden' name='start_time' value='<?= time() ?>'>
                        
                        <div class='timer-display-container'>
                            <div class='time-remaining'>Time Remaining:</div>
                            <div id='test-timer' class='countdown-timer' data-duration='<?= $duration_seconds ?>'>
                                <?= floor($duration_seconds/60) ?>:<?= sprintf("%02d", $duration_seconds%60) ?>
                            </div>
                        </div>
                        
                        <div class='questions-container'>
                            <?php while ($q = $questions->fetch_assoc()): ?>
                                <div class='question-card'>
                                    <div class='question-header'>
                                        <span class='question-number'>Question <?= $q['id'] ?></span>
                                    </div>
                                    <div class='question-content'>
                                        <?= htmlspecialchars($q['question']) ?>
                                    </div>
                                    <div class='options-grid'>
                                        <?php for ($i = 1; $i <= 4; $i++): ?>
                                            <?php if (!empty($q["option$i"])): ?>
                                                <label class='option-item'>
                                                    <input type='radio' name='answers[<?= $q['id'] ?>]' value='<?= $i ?>' required>
                                                    <span class='option-text'><?= htmlspecialchars($q["option$i"]) ?></span>
                                                </label>
                                            <?php endif; ?>
                                        <?php endfor; ?>
                                </div>
                            <?php endwhile; ?>
                        </div>
                        
                        <div class='submit-button-container'>
                            <button type='submit' id='submit-btn' class='primary-button'>
                                <span class='button-text'>Submit Test</span>
                                <span class='button-icon'>✓</span>
                            </button>
                        </div>
                    </form>
                </div>

                <script>
                // Timer functionality
                document.addEventListener('DOMContentLoaded', function() {
                    const timerElement = document.getElementById('test-timer');
                    let timeLeft = parseInt(timerElement.dataset.duration);
                    
                    const timer = setInterval(function() {
                        timeLeft--;
                        
                        const minutes = Math.floor(timeLeft / 60);
                        const seconds = timeLeft % 60;
                        
                        // Format with leading zero for seconds
                        const formattedTime = minutes + ":" + (seconds < 10 ? "0" : "") + seconds;
                        timerElement.textContent = formattedTime;
                        
                        // Change color when less than 1 minute remains
                        if (timeLeft <= 60) {
                            timerElement.style.color = '#e74c3c';
                        }
                        
                        if (timeLeft <= 0) {
                            clearInterval(timer);
                            timerElement.textContent = "Time's up!";
                            document.getElementById('test-form').submit();
                        }
                    }, 1000);
                });
                
                // Prevent multiple submissions
                function confirmSubmission() {
                    const submitBtn = document.getElementById('submit-btn');
                    
                    if (submitBtn.disabled) {
                        return false;
                    }
                    
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<span class="button-text">Submitting...</span>';
                    return true;
                }
                </script>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon">?</div>
                    <h3>No Questions Found</h3>
                    <p>This test doesn't have any questions yet.</p>
                    <a href="?tab=test" class="back-button">Back to Tests</a>
                </div>
            <?php endif; 
            exit;
        }

        // Display all tests (available and unavailable)
        try {
            $tests = $conn->query("
                SELECT t.*, 
                (SELECT 1 FROM user_answers WHERE user_id = $uid AND test_id = t.id LIMIT 1) AS attempted,
                CASE 
                    WHEN '$current_time' < t.start_datetime THEN 'upcoming'
                    WHEN '$current_time' > LEAST(t.end_datetime, DATE_ADD(t.start_datetime, INTERVAL t.duration_minutes MINUTE)) THEN 'expired'
                    ELSE 'available'
                END AS status,
                CASE
                    WHEN '$current_time' > LEAST(t.end_datetime, DATE_ADD(t.start_datetime, INTERVAL t.duration_minutes MINUTE)) AND 
                         NOT EXISTS (SELECT 1 FROM user_answers WHERE user_id = $uid AND test_id = t.id) THEN 1
                    ELSE 0
                END AS skipped
                FROM tests t
                ORDER BY t.start_datetime DESC
            ");
            ?>
            
            <div class="test-grid">
                <?php while ($test = $tests->fetch_assoc()): 
                    $test_id = (int)$test['id'];
                    $status = $test['status'];
                    $attempted = $test['attempted'];
                    $skipped = $test['skipped'];
                    $start_time = date('M j, Y H:i', strtotime($test['start_datetime']));
                    $end_time = date('M j, Y H:i', strtotime($test['end_datetime']));
                    $duration = $test['duration_minutes'];
                ?>
                    <div class='test-card <?= $status ?> <?= $attempted ? 'attempted' : '' ?> <?= $skipped ? 'skipped' : '' ?>'>
                        <div class='test-card-header'>
                            <h3 class='test-title'><?= htmlspecialchars($test['test_name']) ?></h3>
                            <div class='test-status-badge <?= $status ?>'>
                                <?= ucfirst($status) ?>
                                <?php if ($attempted): ?>
                                    <span class="attempted-marker">✓</span>
                                <?php elseif ($skipped): ?>
                                    <span class="skipped-marker">✗</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class='test-card-body'>
                            <div class='test-meta'>
                                <div class='meta-item'>
                                    <span class='meta-label'>Starts:</span>
                                    <span class='meta-value'><?= $start_time ?></span>
                                </div>
                                <div class='meta-item'>
                                    <span class='meta-label'>Ends:</span>
                                    <span class='meta-value'><?= $end_time ?></span>
                                </div>
                                <div class='meta-item'>
                                    <span class='meta-label'>Duration:</span>
                                    <span class='meta-value'><?= $duration ?> minutes</span>
                                </div>
                            </div>
                            
                            <div class='test-actions'>
                                <?php if ($status === 'available' && !$attempted): ?>
                                    <a href="?tab=test&load_test=<?= $test_id ?>" class='primary-button'>
                                        Start Test
                                    </a>
                                <?php elseif ($attempted): ?>
                                    <a href="?tab=test&view_results=<?= $test_id ?>" class='secondary-button'>
                                        View Results
                                    </a>
                                <?php elseif ($skipped): ?>
                                    <span class='skipped-button'>Skipped</span>
                                <?php elseif ($status === 'upcoming'): ?>
                                    <button class='disabled-button' disabled>
                                        Starts Soon
                                    </button>
                                <?php else: ?>
                                    <button class='disabled-button' disabled>
                                        Test Closed
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
            
            <script>
            // Filter functionality
            document.addEventListener('DOMContentLoaded', function() {
                const filterButtons = document.querySelectorAll('.filter-btn');
                
                filterButtons.forEach(button => {
                    button.addEventListener('click', function() {
                        // Update active state
                        filterButtons.forEach(btn => btn.classList.remove('active'));
                        this.classList.add('active');
                        
                        const filter = this.dataset.filter;
                        const testCards = document.querySelectorAll('.test-card');
                        
                        testCards.forEach(card => {
                            if (filter === 'all') {
                                card.style.display = 'block';
                            } else {
                                const matchesFilter = 
                                    (filter === 'available' && card.classList.contains('available') && !card.classList.contains('attempted') && !card.classList.contains('skipped')) ||
                                    (filter === 'attempted' && card.classList.contains('attempted')) ||
                                    (filter === 'skipped' && card.classList.contains('skipped'));
                                
                                card.style.display = matchesFilter ? 'block' : 'none';
                            }
                        });
                    });
                });
            });
            </script>
            <?php
        } catch (Exception $e) {
            echo "<div class='error-message'>Error loading tests. Please try again later.</div>";
        }
        ?>
    </div>
    <?php endif; ?>

    <!-- Document Upload Section -->
    <?php if ($tab === 'documents'): ?>
    <div class="tab-content <?= $tab == 'documents' ? 'active' : '' ?>">
        <form action="upload.php" method="post" enctype="multipart/form-data" class="upload-form">
            <h3>Upload New Document</h3>
            <div class="file-input-wrapper">
                <label>Select a document to upload (PDF or Image):</label>
                <input type="file" name="document" accept=".pdf,.jpg,.jpeg,.png" required>
            </div>
            <input type="submit" name="upload" value="Upload Document" class="submit-btn">
        </form>

        <h3>Uploaded Documents</h3>
        <?php
        $uid = (int)$user['id'];
        $query = $conn->query("SELECT * FROM documents WHERE user_id = $uid ORDER BY uploaded_at DESC");
        
        if ($query->num_rows > 0): ?>
            <ul class="documents-list">
            <?php while ($doc = $query->fetch_assoc()):
                $filePath = htmlspecialchars($doc['file_path']);
                $fileName = htmlspecialchars($doc['file_name']);
                $uploadDate = date('M j, Y H:i', strtotime($doc['uploaded_at']));
            ?>
                <li>
                    <a href="<?= $filePath ?>" target="_blank"><?= $fileName ?></a>
                    <span class="document-date"><?= $uploadDate ?></span>
                </li>
            <?php endwhile; ?>
            </ul>
        <?php else: ?>
            <p>No documents uploaded yet.</p>
        <?php endif; ?>
        
        <a href="logout.php" class="logout-btn">Logout</a>
    </div>
    <?php endif; ?>

<?php if ($tab === 'study_materials'): ?>
<div class="tab-content active">
    <h3>Study Materials</h3>
    <div class="study-materials-container">
        <?php
        // Connect to study_materials database
        $studyConn = new mysqli("localhost", "root", "", "study_materials");
        if ($studyConn->connect_error) {
            die("Study Materials DB connection failed: " . $studyConn->connect_error);
        }

        $subjects = $studyConn->query("SELECT * FROM subjects ORDER BY name");
        if ($subjects->num_rows > 0):
            while ($subject = $subjects->fetch_assoc()):
                $topics = $studyConn->query("SELECT * FROM topics WHERE subject_id = {$subject['id']} ORDER BY name");
                if ($topics->num_rows > 0):
        ?>
                    <div class="subject-item" onclick="toggleTopics(<?= $subject['id'] ?>)">
                        <strong><?= htmlspecialchars($subject['name']) ?></strong>
                    </div>
                    <div id="topics-<?= $subject['id'] ?>" style="display: none; margin-left: 20px;">
                        <?php while ($topic = $topics->fetch_assoc()):
                            $materials = $studyConn->query("SELECT * FROM materials WHERE topic_id = {$topic['id']} ORDER BY title");
                            if ($materials->num_rows > 0):
                        ?>
                                <div class="topic-item" onclick="toggleMaterials(<?= $topic['id'] ?>)">
                                    <?= htmlspecialchars($topic['name']) ?>
                                </div>
                                <div id="materials-<?= $topic['id'] ?>" style="display: none; margin-left: 20px;">
                                    <?php while ($material = $materials->fetch_assoc()): ?>
                                        <div class="material-item">
                                            <h5><?= htmlspecialchars($material['title']) ?></h5>
                                            <?php if ($material['description']): ?>
                                                <p><?= htmlspecialchars($material['description']) ?></p>
                                            <?php endif; ?>
                                            <a href="<?= htmlspecialchars($material['file_path']) ?>" class="material-link" target="_blank">
                                                Download <?= htmlspecialchars($material['file_name']) ?>
                                            </a>
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                        <?php endif; endwhile; ?>
                    </div>
        <?php endif; endwhile; else: ?>
            <p>No study materials available yet.</p>
        <?php endif; 
        $studyConn->close();
        ?>
    </div>
</div>
<?php endif; ?>

<!-- Discussion Forum Section -->
<?php if ($tab === 'forum'): ?>
<div class="tab-content <?= $tab == 'forum' ? 'active' : '' ?>">
    <div class="forum-container">
        <?php
        // Check if we're viewing a specific thread
        if (isset($_GET['thread_id'])) {
            $thread_id = (int)$_GET['thread_id'];
            $uid = (int)$_SESSION['user']['id'];
            
            // Get thread details
            $thread = $conn->query("
                SELECT t.*, u.full_name as author_name 
                FROM forum_threads t
                JOIN user_accounts u ON t.user_id = u.id
                WHERE t.id = $thread_id
            ")->fetch_assoc();
            
            if (!$thread) {
                echo "<div class='empty-state'>Thread not found.</div>";
                echo "<a href='?tab=forum' class='back-button'>Back to Forum</a>";
            } else {
                // Handle new post submission
                if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post_content'])) {
                    $content = $conn->real_escape_string($_POST['post_content']);
                    $conn->query("
                        INSERT INTO forum_posts (thread_id, user_id, content, created_at)
                        VALUES ($thread_id, $uid, '$content', NOW())
                    ");
                }
                
                // Get all posts for this thread
                $posts = $conn->query("
                    SELECT p.*, u.full_name as author_name 
                    FROM forum_posts p
                    JOIN user_accounts u ON p.user_id = u.id
                    WHERE p.thread_id = $thread_id
                    ORDER BY p.created_at ASC
                ");
                ?>
                
                <h3><?= htmlspecialchars($thread['title']) ?></h3>
                <div class="thread-meta">
                    <span class="thread-author">Started by <?= htmlspecialchars($thread['author_name']) ?></span>
                    <span class="thread-date"><?= date('M j, Y H:i', strtotime($thread['created_at'])) ?></span>
                </div>
                
                <div class="post-list">
                    <?php while ($post = $posts->fetch_assoc()): ?>
                        <div class="post-item">
                            <div class="post-header">
                                <span class="post-author"><?= htmlspecialchars($post['author_name']) ?></span>
                                <span class="post-date"><?= date('M j, Y H:i', strtotime($post['created_at'])) ?></span>
                            </div>
                            <div class="post-content">
                                <?= nl2br(htmlspecialchars($post['content'])) ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
                
                <div class="reply-form">
                    <h4>Post a Reply</h4>
                    <form method="POST">
                        <div class="form-group">
                            <textarea name="post_content" required placeholder="Write your reply here..." class="form-control"></textarea>
                        </div>
                        <button type="submit" class="primary-button">Post Reply</button>
                    </form>
                </div>
                
                <a href="?tab=forum" class="back-button">Back to Forum</a>
                <?php
            }
        } else {
            // Main forum view - list of threads
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['thread_title']) && isset($_POST['thread_content'])) {
                // Handle new thread creation
                $title = $conn->real_escape_string($_POST['thread_title']);
                $content = $conn->real_escape_string($_POST['thread_content']);
                $uid = (int)$_SESSION['user']['id'];
                
                $conn->query("
                    INSERT INTO forum_threads (user_id, title, content, created_at)
                    VALUES ($uid, '$title', '$content', NOW())
                ");
                
                $new_thread_id = $conn->insert_id;
                header("Location: ?tab=forum&thread_id=$new_thread_id");
                exit;
            }
            
            // Show create thread form if requested
            if (isset($_GET['new_thread'])) {
                ?>
                <div class="thread-form">
                    <h3>Create New Thread</h3>
                    <form method="POST">
                        <div class="form-group">
                            <label for="thread_title">Title</label>
                            <input type="text" name="thread_title" required class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="thread_content">Content</label>
                            <textarea name="thread_content" required class="form-control"></textarea>
                        </div>
                        <button type="submit" class="primary-button">Create Thread</button>
                        <a href="?tab=forum" class="secondary-button">Cancel</a>
                    </form>
                </div>
                <?php
            } else {
                // List all threads
                ?>
                <div class="forum-header">
                    <h3>Discussion Forum</h3>
                    <a href="?tab=forum&new_thread=1" class="create-thread-btn">Create New Thread</a>
                </div>
                
                <div class="thread-list">
                    <?php
                    $threads = $conn->query("
                        SELECT t.*, u.full_name as author_name, 
                        (SELECT COUNT(*) FROM forum_posts p WHERE p.thread_id = t.id) as post_count
                        FROM forum_threads t
                        JOIN user_accounts u ON t.user_id = u.id
                        ORDER BY t.created_at DESC
                    ");
                    
                    if ($threads->num_rows > 0):
                        while ($thread = $threads->fetch_assoc()):
                            $last_post = $conn->query("
                                SELECT p.created_at, u.full_name as author_name
                                FROM forum_posts p
                                JOIN user_accounts u ON p.user_id = u.id
                                WHERE p.thread_id = {$thread['id']}
                                ORDER BY p.created_at DESC
                                LIMIT 1
                            ")->fetch_assoc();
                            ?>
                            <div class="thread-item">
                                <h4 class="thread-title">
                                    <a href="?tab=forum&thread_id=<?= $thread['id'] ?>"><?= htmlspecialchars($thread['title']) ?></a>
                                </h4>
                                <div class="thread-meta">
                                    <span class="thread-author">Posted by <?= htmlspecialchars($thread['author_name']) ?></span>
                                    <span class="thread-date"><?= date('M j, Y H:i', strtotime($thread['created_at'])) ?></span>
                                </div>
                                <div class="thread-excerpt">
                                    <?= nl2br(htmlspecialchars(substr($thread['content'], 0, 200))) ?>...
                                </div>
                                <div class="thread-stats">
                                    <span><?= $thread['post_count'] ?> replies</span>
                                    <?php if ($last_post): ?>
                                        <span>Last reply by <?= htmlspecialchars($last_post['author_name']) ?> on <?= date('M j, Y H:i', strtotime($last_post['created_at'])) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endwhile;
                    else: ?>
                        <div class="empty-state">
                            <div class="empty-icon">💬</div>
                            <h3>No Threads Yet</h3>
                            <p>Be the first to start a discussion!</p>
                            <a href="?tab=forum&new_thread=1" class="primary-button">Create Thread</a>
                        </div>
                    <?php endif; ?>
                </div>
                <?php
            }
        }
        ?>
    </div>
</div>
<?php endif; ?>
</div>

<script>
function toggleTopics(subjectId) {
    const topicsDiv = document.getElementById(`topics-${subjectId}`);
    topicsDiv.style.display = topicsDiv.style.display === 'none' ? 'block' : 'none';
}

function toggleMaterials(topicId) {
    const materialsDiv = document.getElementById(`materials-${topicId}`);
    materialsDiv.style.display = materialsDiv.style.display === 'none' ? 'block' : 'none';
}
</script>
    
</div>
</body>
</html>