<?php
session_start();
$tab = $_GET['tab'] ?? 'enquiry';
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin_login.php");
    exit;
}
?>

<?php
// Connect to both databases
$formConn = new mysqli("localhost", "root", "", "form_data");
$userConn = new mysqli("localhost", "root", "", "user_auth");
$studyConn = new mysqli("localhost", "root", "", "study_materials");

if ($formConn->connect_error) {
    die("Form DB connection failed: " . $formConn->connect_error);
}
if ($userConn->connect_error) {
    die("User DB connection failed: " . $userConn->connect_error);
}
if ($studyConn->connect_error) die("Study Materials DB connection failed: " . $studyConn->connect_error);

// Handle AJAX requests: toggle active status or fetch user details
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'toggle_status' && isset($_POST['user_id'])) {
        $userId = (int)$_POST['user_id'];

        // Get current status
        $res = $userConn->query("SELECT is_active FROM user_accounts WHERE id = $userId");
        if ($res && $res->num_rows > 0) {
            $row = $res->fetch_assoc();
            $newStatus = $row['is_active'] ? 0 : 1;

            // Update status
            $userConn->query("UPDATE user_accounts SET is_active = $newStatus WHERE id = $userId");

            echo json_encode(['success' => true, 'newStatus' => $newStatus]);
        } else {
            echo json_encode(['success' => false, 'message' => 'User not found']);
        }
        exit;
    }

    if ($_POST['action'] === 'get_user_details' && isset($_POST['user_id'])) {
        $userId = (int)$_POST['user_id'];

        $res = $userConn->query("SELECT id, email, full_name, phone, dob, gender, city, is_active FROM user_accounts WHERE id = $userId");
        if ($res && $res->num_rows > 0) {
            $user = $res->fetch_assoc();
            echo json_encode(['success' => true, 'user' => $user]);
        } else {
            echo json_encode(['success' => false, 'message' => 'User not found']);
        }
        exit;
    }

    // Study Materials AJAX handlers
    if ($_POST['action'] === 'add_subject') {
        $name = $studyConn->real_escape_string($_POST['subject_name']);
        $studyConn->query("INSERT INTO subjects (name) VALUES ('$name')");
        echo json_encode(['success' => true, 'id' => $studyConn->insert_id]);
        exit;
    }
    
    if ($_POST['action'] === 'add_topic') {
        $subjectId = (int)$_POST['subject_id'];
        $name = $studyConn->real_escape_string($_POST['topic_name']);
        $studyConn->query("INSERT INTO topics (subject_id, name) VALUES ($subjectId, '$name')");
        echo json_encode(['success' => true, 'id' => $studyConn->insert_id]);
        exit;
    }
    
    if ($_POST['action'] === 'get_subjects') {
        $result = $studyConn->query("SELECT * FROM subjects ORDER BY name");
        $subjects = [];
        while ($row = $result->fetch_assoc()) $subjects[] = $row;
        echo json_encode(['success' => true, 'subjects' => $subjects]);
        exit;
    }
    
    if ($_POST['action'] === 'get_topics') {
        $subjectId = (int)$_POST['subject_id'];
        $result = $studyConn->query("SELECT * FROM topics WHERE subject_id = $subjectId ORDER BY name");
        $topics = [];
        while ($row = $result->fetch_assoc()) $topics[] = $row;
        echo json_encode(['success' => true, 'topics' => $topics]);
        exit;
    }
    
    if ($_POST['action'] === 'get_materials') {
        $topicId = (int)$_POST['topic_id'];
        $result = $studyConn->query("SELECT * FROM materials WHERE topic_id = $topicId ORDER BY title");
        $materials = [];
        while ($row = $result->fetch_assoc()) $materials[] = $row;
        echo json_encode(['success' => true, 'materials' => $materials]);
        exit;
    }
    
    if ($_POST['action'] === 'delete_material') {
        $materialId = (int)$_POST['material_id'];
        $result = $studyConn->query("SELECT file_path FROM materials WHERE id = $materialId");
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            if (file_exists($row['file_path'])) {
                unlink($row['file_path']);
            }
        }
        $studyConn->query("DELETE FROM materials WHERE id = $materialId");
        echo json_encode(['success' => true]);
        exit;
    }
    
    if ($_POST['action'] === 'delete_topic') {
        $topicId = (int)$_POST['topic_id'];
        
        // First delete all materials for this topic
        $materials = $studyConn->query("SELECT file_path FROM materials WHERE topic_id = $topicId");
        while ($material = $materials->fetch_assoc()) {
            if (file_exists($material['file_path'])) {
                unlink($material['file_path']);
            }
        }
        $studyConn->query("DELETE FROM materials WHERE topic_id = $topicId");
        
        // Then delete the topic
        $studyConn->query("DELETE FROM topics WHERE id = $topicId");
        echo json_encode(['success' => true]);
        exit;
    }
    
    if ($_POST['action'] === 'delete_subject') {
        $subjectId = (int)$_POST['subject_id'];
        
        // First get all topics for this subject
        $topics = $studyConn->query("SELECT id FROM topics WHERE subject_id = $subjectId");
        while ($topic = $topics->fetch_assoc()) {
            // Delete all materials for each topic
            $materials = $studyConn->query("SELECT file_path FROM materials WHERE topic_id = {$topic['id']}");
            while ($material = $materials->fetch_assoc()) {
                if (file_exists($material['file_path'])) {
                    unlink($material['file_path']);
                }
            }
            $studyConn->query("DELETE FROM materials WHERE topic_id = {$topic['id']}");
        }
        
        // Then delete all topics for this subject
        $studyConn->query("DELETE FROM topics WHERE subject_id = $subjectId");
        
        // Finally delete the subject
        $studyConn->query("DELETE FROM subjects WHERE id = $subjectId");
        echo json_encode(['success' => true]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}

// Fetch form submissions for Enquiry tab (show all, latest first)
$formSql = "SELECT * FROM users ORDER BY id DESC";
$formResult = $formConn->query($formSql);

// Fetch all users for Users tab (latest first)
$userSql = "SELECT id, full_name, is_active FROM user_accounts ORDER BY id DESC";
$userResult = $userConn->query($userSql);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin Panel</title>
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            background-color: #f4f6f8;
            padding: 30px;
        }
        .container {
            max-width: 1100px;
            margin: auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 0 12px rgba(0, 0, 0, 0.1);
        }
        h2 {
            text-align: center;
            margin-bottom: 25px;
        }
        /* Tabs styling */
        .tabs {
            display: flex;
            margin-bottom: 20px;
            border-bottom: 2px solid #007BFF;
        }
        .tab {
            padding: 12px 25px;
            cursor: pointer;
            border: 1px solid #007BFF;
            border-bottom: none;
            background-color: #e9f0ff;
            color: #007BFF;
            font-weight: 600;
            margin-right: 10px;
            border-radius: 6px 6px 0 0;
            user-select: none;
            text-decoration: none;
        }
        .tab.active {
            background-color: white;
            color: black;
            border-bottom: 2px solid white;
        }
        /* Table styling */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th, td {
            text-align: left;
            padding: 10px;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #007BFF;
            color: white;
        }
        tr:hover {
            background-color: #f1f1f1;
        }
        /* Buttons */
        button {
            padding: 6px 12px;
            font-size: 14px;
            background-color: #007BFF;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-right: 6px;
            user-select: none;
        }
        button:hover {
            background-color: #0056b3;
        }
        .btn-toggle {
            background-color: #28a745;
        }
        .btn-toggle.inactive {
            background-color: #dc3545;
        }
        .btn-toggle {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            background-color: #007bff;
            color: white;
            text-decoration: none;
            cursor: pointer;
            font-size: 14px;
            display: inline-block;
            margin: 0 5px;
        }
        .btn-toggle:hover {
            background-color: #0056b3;
        }
        .btn-toggle.inactive {
            background-color: #6c757d;
        }
        /* Search input */
        form {
            text-align: right;
            margin-bottom: 10px;
        }
        input[type="number"] {
            padding: 8px;
            width: 180px;
            font-size: 14px;
            border-radius: 4px;
            border: 1px solid #ccc;
        }
        /* Modal styles */
        .modal {
            display: none; 
            position: fixed; 
            z-index: 9999; 
            left: 0;
            top: 0;
            width: 100%; 
            height: 100%;
            overflow: auto; 
            background-color: rgba(0,0,0,0.5); 
        }
        .modal-content {
            background-color: #fff;
            margin: 10% auto;
            padding: 20px;
            border-radius: 8px;
            width: 400px;
            max-width: 90%;
            box-shadow: 0 5px 15px rgba(0,0,0,.5);
            position: relative;
        }
        .modal-close {
            position: absolute;
            right: 15px;
            top: 10px;
            font-size: 24px;
            font-weight: bold;
            color: #333;
            cursor: pointer;
        }
        .modal-content h3 {
            margin-top: 0;
            margin-bottom: 15px;
        }
        .modal-content p {
            margin: 6px 0;
        }
        .no-results {
            text-align: center;
            color: #888;
            margin-top: 20px;
        }
        .tab-content {
            display: none;
        }
        .test-box {
            margin-bottom: 20px;
            border: 1px solid #ccc;
            padding: 15px;
            border-radius: 6px;
            cursor: pointer;
        }
        .test-box:hover {
            background-color: #f5f5f5;
        }
        .test-details {
            margin-top: 10px;
            display: none;
        }
        .question-item {
            margin-bottom: 10px;
            padding: 10px;
            background-color: #f9f9f9;
            border-radius: 4px;
        }
        .edit-question-btn {
            background-color: #ffc107;
            color: #212529;
        }
        .edit-question-btn:hover {
            background-color: #e0a800;
        }
        .test-list, .student-list {
            max-height: 500px;
            overflow-y: auto;
            border: 1px solid #eee;
            padding: 10px;
            margin-bottom: 20px;
        }
        .test-item:hover, .student-item:hover {
            background-color: #f5f5f5;
        }
        .answer-item {
            background-color: #f9f9f9;
            margin-bottom: 15px;
            padding: 15px;
            border-radius: 5px;
        }
        /* Results tab specific styles */
        .results-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        .view-btn {
            background-color: #17a2b8;
        }
        .view-btn:hover {
            background-color: #138496;
        }
        .report-btn {
            background-color: #6f42c1;
        }
        .report-btn:hover {
            background-color: #5a36a8;
        }
        .certificate-btn {
            background-color: #20c997;
        }
        .certificate-btn:hover {
            background-color: #17a589;
        }
        .certificate-btn.disabled {
            background-color: #6c757d;
            cursor: not-allowed;
        }
        .grade-A {
            color: #28a745;
            font-weight: bold;
        }
        .grade-B {
            color: #17a2b8;
            font-weight: bold;
        }
        .grade-C {
            color: #ffc107;
            font-weight: bold;
        }
        .grade-F {
            color: #dc3545;
            font-weight: bold;
        }
        .back-btn {
            margin-bottom: 15px;
            padding: 5px 10px;
            background-color: #6c757d;
            color: white;
        }
        .back-btn:hover {
            background-color: #5a6268;
        }
        /* Exam Report specific styles */
        .report-container {
            margin-top: 20px;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            background-color: #f8f9fa;
        }
        .report-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #ddd;
        }
        .report-stats {
            display: flex;
            justify-content: space-around;
            margin-bottom: 15px;
            text-align: center;
        }
        .stat-item {
            flex: 1;
        }
        .stat-value {
            font-size: 20px;
            font-weight: bold;
        }
        .stat-label {
            color: #6c757d;
            font-size: 14px;
        }
        .report-table {
            width: 100%;
            border-collapse: collapse;
        }
        .report-table th {
            background-color: #6c757d;
            color: white;
            padding: 10px;
            text-align: left;
        }
        .report-table td {
            padding: 10px;
            border-bottom: 1px solid #ddd;
        }
        .report-table tr:nth-child(even) {
            background-color: #f2f2f2;
        }
        /* Certificate preview styles */
        .certificate-container {
            border: 2px solid #007BFF;
            padding: 30px;
            margin: 20px auto;
            max-width: 800px;
            background-color: #f8f9fa;
            text-align: center;
        }
        .certificate-title {
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 20px;
            color: #007BFF;
        }
        .certificate-text {
            font-size: 18px;
            margin: 15px 0;
        }
        .certificate-name {
            font-size: 24px;
            font-weight: bold;
            margin: 20px 0;
            color: #28a745;
        }
        .certificate-score {
            font-size: 20px;
            margin: 15px 0;
        }
        .certificate-grade {
            font-size: 22px;
            font-weight: bold;
            margin: 20px 0;
        }
        .certificate-footer {
            margin-top: 30px;
            font-style: italic;
        }
        .no-certificate {
            color: #dc3545;
            font-weight: bold;
            margin: 20px 0;
        }
        /* Loading and status messages */
        .loading-message, .no-items, .error-message {
            padding: 15px;
            text-align: center;
            color: #666;
        }

        .error-message {
            color: #dc3545;
        }

        /* Toast notifications */
        .toast {
            position: fixed;
            bottom: 20px;
            right: 20px;
            padding: 12px 20px;
            background: #333;
            color: white;
            border-radius: 4px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            z-index: 1000;
            animation: slide-in 0.3s ease-out;
        }

        .toast-success {
            background: #28a745;
        }

        .toast-error {
            background: #dc3545;
        }

        .toast-info {
            background: #17a2b8;
        }

        .toast.fade-out {
            animation: fade-out 0.5s ease-out;
        }

        @keyframes slide-in {
            from { transform: translateX(100%); }
            to { transform: translateX(0); }
        }

        @keyframes fade-out {
            from { opacity: 1; }
            to { opacity: 0; }
        }
        /* Checkbox styles */
        .checkbox-container {
            display: block;
            position: relative;
            padding-left: 35px;
            margin-bottom: 12px;
            cursor: pointer;
            font-size: 16px;
            -webkit-user-select: none;
            -moz-user-select: none;
            -ms-user-select: none;
            user-select: none;
            text-align: left;
        }
        .checkbox-container input {
            position: absolute;
            opacity: 0;
            cursor: pointer;
            height: 0;
            width: 0;
        }
        .checkmark {
            position: absolute;
            top: 0;
            left: 0;
            height: 25px;
            width: 25px;
            background-color: #eee;
            border-radius: 4px;
        }
        .checkbox-container:hover input ~ .checkmark {
            background-color: #ccc;
        }
        .checkbox-container input:checked ~ .checkmark {
            background-color: #2196F3;
        }
        .checkmark:after {
            content: "";
            position: absolute;
            display: none;
        }
        .checkbox-container input:checked ~ .checkmark:after {
            display: block;
        }
        .checkbox-container .checkmark:after {
            left: 9px;
            top: 5px;
            width: 5px;
            height: 10px;
            border: solid white;
            border-width: 0 3px 3px 0;
            -webkit-transform: rotate(45deg);
            -ms-transform: rotate(45deg);
            transform: rotate(45deg);
        }
        .study-materials-container { 
            display: flex; 
            gap: 20px; 
            margin-top: 20px; 
        }
        .study-materials-sidebar { 
            width: 250px; 
            background: #f8f9fa; 
            border-radius: 8px; 
            padding: 15px; 
            box-shadow: 0 0 5px rgba(0,0,0,0.1); 
        }
        .study-materials-content { 
            flex: 1; 
            background: #fff; 
            border-radius: 8px; 
            padding: 20px; 
            box-shadow: 0 0 5px rgba(0,0,0,0.1); 
        }
        .subject-item, .topic-item { 
            padding: 8px 12px; 
            margin-bottom: 5px; 
            border-radius: 4px; 
            cursor: pointer; 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
        }
        .subject-item:hover, .topic-item:hover { 
            background: #e9ecef; 
        }
        .subject-item.active, .topic-item.active { 
            background: #007bff; 
            color: white; 
        }
        .delete-btn { 
            background: #dc3545; 
            color: white; 
            border: none; 
            border-radius: 4px; 
            padding: 2px 6px; 
            font-size: 12px; 
            cursor: pointer; 
        }
        .delete-btn:hover { 
            background: #c82333; 
        }
        .add-btn { 
            background: #28a745; 
            color: white; 
            border: none; 
            border-radius: 4px; 
            padding: 5px 10px; 
            margin-top: 10px; 
            cursor: pointer; 
        }
        .add-btn:hover { 
            background: #218838; 
        }
        .material-item { 
            padding: 15px; 
            border: 1px solid #ddd; 
            border-radius: 5px; 
            margin-bottom: 15px; 
        }
        .material-actions { 
            margin-top: 10px; 
        }
        .upload-form { 
            margin-top: 20px; 
            padding: 15px; 
            background: #f8f9fa; 
            border-radius: 5px; 
        }
    </style>
</head>
<body>

<a href="admin_logout.php" style="float:right;">Logout</a>

<div class="container">
<h2>Admin Panel</h2>

<!-- Tabs -->
<div class="tabs">
    <div class="tab <?php echo $tab === 'enquiry' ? 'active' : ''; ?>" data-tab="enquiry">Enquiry</div>
    <div class="tab <?php echo $tab === 'users' ? 'active' : ''; ?>" data-tab="users">Users</div>
    <div class="tab <?php echo $tab === 'tests' ? 'active' : ''; ?>" data-tab="tests">Tests</div>
    <div class="tab <?php echo $tab === 'results' ? 'active' : ''; ?>" data-tab="results">Test Results</div>
    <div class="tab <?= $tab === 'study_materials' ? 'active' : '' ?>" data-tab="study_materials">Study Materials</div> 
</div>
    
<!-- Enquiry Tab -->
<div id="enquiry" class="tab-content" style="<?php echo $tab === 'enquiry' ? 'display:block;' : 'display:none;'; ?>">
<form method="GET" action="admin.php">
    <input type="number" name="search" placeholder="Search by ID"
           value="<?php echo isset($_GET['search']) ? (int)$_GET['search'] : ''; ?>" required>
    <input type="hidden" name="tab" value="enquiry">
    <button type="submit">Search</button>
</form>

        <?php 
        $search = isset($_GET['search']) ? (int)$_GET['search'] : 0;
        $filteredResult = null;
        if ($search > 0) {
            $searchSql = "SELECT * FROM users WHERE id = $search";
            $filteredResult = $formConn->query($searchSql);
        }
        $displayResult = $filteredResult ?? $formResult;
        ?>

        <?php if ($displayResult && $displayResult->num_rows > 0): ?>
            <table>
                <tr>
                    <th>ID</th>
                    <th>Full Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>DOB</th>
                    <th>Gender</th>
                    <th>Address</th>
                    <th>City</th>
                </tr>
                <?php while ($row = $displayResult->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $row['id']; ?></td>
                        <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['email']); ?></td>
                        <td><?php echo htmlspecialchars($row['phone']); ?></td>
                        <td><?php echo htmlspecialchars($row['dob']); ?></td>
                        <td><?php echo htmlspecialchars($row['gender']); ?></td>
                        <td><?php echo htmlspecialchars($row['address']); ?></td>
                        <td><?php echo htmlspecialchars($row['city']); ?></td>
                    </tr>
                <?php endwhile; ?>
            </table>
        <?php else: ?>
            <p class="no-results">No entry found for the given ID.</p>
        <?php endif; ?>
</div>

<!-- Users Tab -->
<div id="users" class="tab-content" style="<?php echo $tab === 'users' ? 'display:block;' : 'display:none;'; ?>">
    <?php if ($userResult && $userResult->num_rows > 0): ?>
        <table>
            <tr>
                <th>ID</th>
                <th>Full Name</th>
                <th>Actions</th>
            </tr>
            <?php while ($user = $userResult->fetch_assoc()): ?>
                <tr data-user-id="<?php echo $user['id']; ?>">
                    <td><?php echo $user['id']; ?></td>
                    <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                    <td>
                        <button class="view-user-btn" data-id="<?php echo $user['id']; ?>">View User</button>
                        <button class="toggle-status-btn <?php echo ($user['is_active'] ? 'btn-toggle' : 'btn-toggle inactive'); ?>" data-id="<?php echo $user['id']; ?>">
                            <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                        </button>
                    </td>
                </tr>
            <?php endwhile; ?>
        </table>
    <?php else: ?>
        <p class="no-results">No users found.</p>
    <?php endif; ?>
</div>

<!-- Tests Tab -->
<div id="tests" class="tab-content" style="<?php echo $tab === 'tests' ? 'display:block;' : 'display:none;'; ?>">
    <h3>Create New Test</h3>
<form id="createTestForm">
    <input type="text" name="test_name" id="test_name" placeholder="Enter test name" required
        style="width: 100%; padding: 10px; margin-bottom: 15px; border-radius: 5px; border: 1px solid #ccc; text-align: left;">
    
    <div style="margin-bottom: 15px;">
        <label style="display: block; margin-bottom: 5px; text-align: left;">Start Date & Time:</label>
        <input type="datetime-local" name="start_datetime" id="start_datetime" required
            style="width: 100%; padding: 10px; border-radius: 5px; border: 1px solid #ccc;">
    </div>
    
    <div style="margin-bottom: 15px;">
        <label style="display: block; margin-bottom: 5px; text-align: left;">End Date & Time:</label>
        <input type="datetime-local" name="end_datetime" id="end_datetime" required
            style="width: 100%; padding: 10px; border-radius: 5px; border: 1px solid #ccc;">
    </div>
    
    <div style="margin-bottom: 15px;">
        <label style="display: block; margin-bottom: 5px; text-align: left;">Duration (minutes):</label>
        <input type="number" name="duration" id="duration" min="1" value="60" required
            style="width: 100%; padding: 10px; border-radius: 5px; border: 1px solid #ccc;">
    </div>
    
    <div style="margin-bottom: 15px; text-align: left;">
        <label class="checkbox-container">Send test notification to all users
            <input type="checkbox" id="send_notification" name="send_notification" checked>
            <span class="checkmark"></span>
        </label>
    </div>
    
    <button id="createTestBtn" type="submit"
        style="padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer;">
        Create Test
    </button>
</form>

    <h3>Existing Tests</h3>
    <div id="testsList">
        <!-- Tests will be loaded here via JavaScript -->
    </div>
</div>

<!-- Test Results Tab -->
<div id="results" class="tab-content" style="<?php echo $tab === 'results' ? 'display:block;' : 'display:none;'; ?>">
    <div class="results-header">
        <h3>Test Results</h3>
        <button onclick="loadTestResults()">Refresh</button>
    </div>
    <div id="resultsContainer">Loading...</div>
    <div id="examReportContainer" style="display:none;">
        <button class="back-btn" onclick="hideExamReport()">&larr; Back to Results</button>
        <div id="examReportContent"></div>
        <button id="downloadPdfBtn" style="margin-top: 20px; padding: 10px 15px; background-color: #dc3545; color: white; border: none; border-radius: 4px; cursor: pointer;">
            Download as PDF
        </button>
    </div>
</div>

<!-- Study Materials Tab -->
<div id="study_materials" class="tab-content" style="<?= $tab === 'study_materials' ? 'display:block;' : 'display:none;' ?>">
    <h3>Study Materials Management</h3>
    <div class="study-materials-container">
        <div class="study-materials-sidebar">
            <h4>Subjects</h4>
            <div id="subjectsList"></div>
            <button id="addSubjectBtn" class="add-btn">+ Add Subject</button>
        </div>
        <div class="study-materials-content">
            <div id="topicsSection" style="display: none;">
                <h4 id="selectedSubjectTitle"></h4>
                <div id="topicsList"></div>
                <button id="addTopicBtn" class="add-btn">+ Add Topic</button>
            </div>
            <div id="materialsSection" style="display: none;">
                <h4 id="selectedTopicTitle"></h4>
                <div id="materialsList"></div>
                <div class="upload-form">
                    <h5>Add New Material</h5>
                    <form id="uploadMaterialForm" enctype="multipart/form-data" action="upload_material.php" method="POST">
                        <input type="hidden" id="currentTopicId" name="topic_id">
                       <div style="text-align: left;">
  <label>Title:</label>
  <input type="text" name="title" required style="width: 100%; padding: 8px; margin-bottom: 10px; text-align: left;">
</div>
<div style="text-align: left;">
  <label>Description:</label>
  <textarea name="description" style="width: 100%; padding: 8px; margin-bottom: 10px; min-height: 80px; text-align: left;"></textarea>
</div>
<div style="text-align: left;">
  <label>File:</label>
  <input type="file" name="material_file" required style="margin-bottom: 10px;">
</div>
<div style="text-align: left;">
  <button type="submit" class="add-btn">Upload Material</button>
</div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal for View User -->
<div id="userModal" class="modal">
    <div class="modal-content">
        <span class="modal-close">&times;</span>
        <h3>User Details</h3>
        <div id="userDetailsContent">
            <!-- Filled by JS -->
        </div>
    </div>
</div>

<!-- Add/Edit Question: -->
<div id="questionModal" class="modal">
    <div class="modal-content" style="width: 600px;">
        <span class="modal-close">&times;</span>
        <h3 id="modalTitle">Add Question</h3>
        <form id="questionForm" style="text-align: left;">
            <input type="hidden" id="modalTestId" name="test_id">
            <input type="hidden" id="modalQuestionId" name="question_id">
            
            <div style="margin-bottom: 15px;">
                <label for="modalQuestion" style="display: block; font-weight: bold; margin-bottom: 6px; text-align: left;">Question:</label>
                <textarea name="question" id="modalQuestion" required
                    style="width: 100%; padding: 10px; border-radius: 4px; border: 1px solid #ccc; box-sizing: border-box; min-height: 80px;"></textarea>
            </div>

            <div style="margin-bottom: 10px; text-align: left;">
                <label for="modalOption1" style="display: block; font-weight: bold; margin-bottom: 6px;">Option 1:</label>
                <input type="text" name="option1" id="modalOption1" required
                    style="width: 100%; padding: 10px; border-radius: 4px; border: 1px solid #ccc; box-sizing: border-box;"></textarea>
            </div>

            <div style="margin-bottom: 10px; text-align: left;">
                <label for="modalOption2" style="display: block; font-weight: bold; margin-bottom: 6px;">Option 2:</label>
                <input type="text" name="option2" id="modalOption2" required
                    style="width: 100%; padding: 10px; border-radius: 4px; border: 1px solid #ccc; box-sizing: border-box;"></textarea>
            </div>

            <div style="margin-bottom: 10px; text-align: left;">
                <label for="modalOption3" style="display: block; font-weight: bold; margin-bottom: 6px;">Option 3:</label>
                <input type="text" name="option3" id="modalOption3" required
                    style="width: 100%; padding: 10px; border-radius: 4px; border: 1px solid #ccc; box-sizing: border-box;"></textarea>
            </div>

            <div style="margin-bottom: 10px; text-align: left;">
                <label for="modalOption4" style="display: block; font-weight: bold; margin-bottom: 6px;">Option 4:</label>
                <input type="text" name="option4" id="modalOption4" required
                    style="width: 100%; padding: 10px; border-radius: 4px; border: 1px solid #ccc; box-sizing: border-box;"></textarea>
            </div>

            <div style="margin-bottom: 15px; text-align: left;">
                <label for="modalCorrect" style="display: block; font-weight: bold; margin-bottom: 6px;">Correct Option (1–4):</label>
                <select name="correct" id="modalCorrect" required
                    style="width: 100%; padding: 10px; border-radius: 4px; border: 1px solid #ccc; box-sizing: border-box;">
                    <option value="">-- Select Correct Option --</option>
                    <option value="1">Option 1</option>
                    <option value="2">Option 2</option>
                    <option value="3">Option 3</option>
                    <option value="4">Option 4</option>
                </select>
            </div>

            <div style="margin-bottom: 15px; text-align: left;">
                <label for="modalWeightage" style="display: block; font-weight: bold; margin-bottom: 6px;">Weightage (Marks):</label>
                <input type="number" name="weightage" id="modalWeightage" min="1" value="1" required
                    style="width: 100%; padding: 10px; border-radius: 4px; border: 1px solid #ccc; box-sizing: border-box;">
            </div>

            <div style="text-align: left;">
                <button type="submit" id="submitQuestionBtn"
                    style="padding: 10px 20px; background: green; color: white; border: none; border-radius: 4px; cursor: pointer;">
                    Save Question
                </button>
                <button type="button" class="closeModal"
                    style="padding: 10px 20px; background: #dc3545; color: white; border: none; border-radius: 4px; cursor: pointer; margin-left: 10px;">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script>
// Initialize jsPDF
const { jsPDF } = window.jspdf;

document.addEventListener('DOMContentLoaded', function() {
    // Tab switching
    const tabs = document.querySelectorAll('.tab');
    const tabContents = document.querySelectorAll('.tab-content');
    
    tabs.forEach(tab => {
        tab.addEventListener('click', function() {
            const tabName = this.getAttribute('data-tab');
            
            // Update URL
            const url = new URL(window.location);
            url.searchParams.set('tab', tabName);
            window.history.replaceState(null, '', url);
            
            // Update active tab
            tabs.forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            
            // Show correct content
            tabContents.forEach(content => {
                content.style.display = content.id === tabName ? 'block' : 'none';
            });
            
            // Load content if needed
            if (tabName === 'results') {
                loadTestResults();
            } else if (tabName === 'tests') {
                loadTests();
            } else if (tabName === 'study_materials') {
                loadSubjects();
            }
        });
    });
    
    // View User button click
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('view-user-btn')) {
            const userId = e.target.getAttribute('data-id');
            
            fetch('admin.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=get_user_details&user_id=' + encodeURIComponent(userId)
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const u = data.user;
                    document.getElementById('userDetailsContent').innerHTML = `
                        <p><strong>ID:</strong> ${u.id}</p>
                        <p><strong>Full Name:</strong> ${escapeHtml(u.full_name)}</p>
                        <p><strong>Email:</strong> ${escapeHtml(u.email)}</p>
                        <p><strong>Phone:</strong> ${escapeHtml(u.phone)}</p>
                        <p><strong>DOB:</strong> ${escapeHtml(u.dob)}</p>
                        <p><strong>Gender:</strong> ${escapeHtml(u.gender)}</p>
                        <p><strong>City:</strong> ${escapeHtml(u.city)}</p>
                        <p><strong>Status:</strong> ${u.is_active == 1 ? 'Active' : 'Inactive'}</p>
                    `;
                    document.getElementById('userModal').style.display = 'block';
                } else {
                    alert('User details not found');
                }
            });
        }
    });
    
    // Toggle Active/Inactive button click
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('toggle-status-btn')) {
            const userId = e.target.getAttribute('data-id');
            
            fetch('admin.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=toggle_status&user_id=' + encodeURIComponent(userId)
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    if(data.newStatus == 1){
                        e.target.textContent = 'Active';
                        e.target.classList.remove('inactive');
                        e.target.classList.add('btn-toggle');
                    } else {
                        e.target.textContent = 'Inactive';
                        e.target.classList.remove('btn-toggle');
                        e.target.classList.add('inactive');
                    }
                } else {
                    alert('Failed to update status: ' + (data.message || 'Unknown error'));
                }
            });
        }
    });
    
    // Modal close buttons
    document.querySelectorAll('.modal-close, .closeModal').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.modal').forEach(modal => {
                modal.style.display = 'none';
            });
        });
    });
    
    // Close modal when clicking outside
    window.addEventListener('click', function(e) {
        if (e.target.classList.contains('modal')) {
            e.target.style.display = 'none';
        }
    });
    
    // Create Test Form
    document.getElementById('createTestForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const btn = document.getElementById('createTestBtn');
        btn.disabled = true;
        btn.textContent = 'Creating...';

        try {
            // Collect form data
            const formData = {
                test_name: document.getElementById('test_name').value.trim(),
                start_datetime: document.getElementById('start_datetime').value,
                end_datetime: document.getElementById('end_datetime').value,
                duration: parseInt(document.getElementById('duration').value),
                send_notification: document.getElementById('send_notification').checked
            };

            // Validate client-side
            if (!formData.test_name || !formData.start_datetime || !formData.end_datetime || !formData.duration) {
                throw new Error('All fields are required');
            }

            const response = await fetch('create_test.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(formData)
            });

            // Check if response is JSON
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                const text = await response.text();
                throw new Error(`Invalid response: ${text}`);
            }

            const result = await response.json();
            
            if (!response.ok || !result.success) {
                throw new Error(result.error || 'Failed to create test');
            }

            alert('Test created successfully!' + (result.notification_sent ? ' Notifications sent to all users.' : ''));
            document.getElementById('createTestForm').reset();
            loadTests();
        } catch (error) {
            console.error('Error:', error);
            alert('Error: ' + error.message);
        } finally {
            btn.disabled = false;
            btn.textContent = 'Create Test';
        }
    });
    
    // PDF Download button
    document.getElementById('downloadPdfBtn')?.addEventListener('click', function() {
        generateExamReportPdf();
    });
    
    // Load tests initially if on tests tab
    if (document.getElementById('tests').style.display === 'block') {
        loadTests();
    }
    
    // Load results initially if on results tab
    if (document.getElementById('results').style.display === 'block') {
        loadTestResults();
    }
    
    // Initialize study materials management
    initStudyMaterials();
});

function loadTests() {
    document.getElementById('testsList').innerHTML = 'Loading tests...';
    
    fetch('test_api.php?action=get_tests')
    .then(res => res.json())
    .then(data => {
        if (!data.success || !data.tests) {
            document.getElementById('testsList').innerHTML = 'Failed to load tests.';
            return;
        }
        
        if (data.tests.length === 0) {
            document.getElementById('testsList').innerHTML = '<p>No tests created yet.</p>';
            return;
        }
        
        let html = '';
        data.tests.forEach(test => {
            html += `
                <div class="test-box" onclick="toggleTestDetails(${test.id})">
                    <div style="font-weight: bold; margin-bottom: 5px;">${escapeHtml(test.test_name)}</div>
                    <div class="test-details" id="testDetails-${test.id}">
                        <button class="add-question-btn" data-test-id="${test.id}" 
                            style="margin-bottom: 10px; padding: 5px 10px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer;">
                            + Add Question
                        </button>
                        <div><strong>Questions:</strong></div>
                        <div id="questionsList-${test.id}">
                            ${test.questions.length > 0 ? 
                                test.questions.map(q => `
                                    <div class="question-item">
                                        <div>${escapeHtml(q.question)}</div>
                                        <div style="margin-top: 5px;">
                                            <button class="edit-question-btn" data-question-id="${q.id}">Edit</button>
                                        </div>
                                    </div>
                                `).join('') : 
                                '<p style="color:#888;">No questions added yet.</p>'
                            }
                        </div>
                    </div>
                </div>
            `;
        });
        
        document.getElementById('testsList').innerHTML = html;
        
        // Attach event listeners to all "Add Question" buttons
        document.querySelectorAll('.add-question-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                const testId = this.getAttribute('data-test-id');
                openQuestionModal(testId);
            });
        });
        
        // Attach event listeners to all "Edit Question" buttons
        document.querySelectorAll('.edit-question-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                const questionId = this.getAttribute('data-question-id');
                editQuestion(questionId);
            });
        });
    })
    .catch(err => {
        console.error('Error loading tests:', err);
        document.getElementById('testsList').innerHTML = 'Error loading tests.';
    });
}

function toggleTestDetails(testId) {
    const detailsDiv = document.getElementById(`testDetails-${testId}`);
    if (detailsDiv.style.display === 'block') {
        detailsDiv.style.display = 'none';
    } else {
        detailsDiv.style.display = 'block';
    }
}

function openQuestionModal(testId, questionId = null) {
    const modal = document.getElementById('questionModal');
    const form = document.getElementById('questionForm');
    const title = document.getElementById('modalTitle');
    
    if (questionId) {
        title.textContent = 'Edit Question';
        document.getElementById('modalQuestionId').value = questionId;
        
        // Fetch question details
        fetch(`test_api.php?action=get_questions_full&test_id=${testId}`)
        .then(res => res.json())
        .then(questions => {
            const question = questions.find(q => q.id == questionId);
            if (question) {
                document.getElementById('modalQuestion').value = question.question;
                document.getElementById('modalOption1').value = question.option1;
                document.getElementById('modalOption2').value = question.option2;
                document.getElementById('modalOption3').value = question.option3;
                document.getElementById('modalOption4').value = question.option4;
                document.getElementById('modalCorrect').value = question.correct_option;
                document.getElementById('modalWeightage').value = question.weightage || 1;
            }
        });
    } else {
        title.textContent = 'Add Question';
        form.reset();
        document.getElementById('modalQuestionId').value = '';
    }
    
    document.getElementById('modalTestId').value = testId;
    modal.style.display = 'block';
}

function editQuestion(questionId) {
    // Find which test this question belongs to
    fetch('test_api.php?action=get_tests')
    .then(res => res.json())
    .then(data => {
        if (data.success && data.tests) {
            for (const test of data.tests) {
                const question = test.questions.find(q => q.id == questionId);
                if (question) {
                    openQuestionModal(test.id, questionId);
                    return;
                }
            }
        }
        alert('Question not found');
    });
}

// Question Form Submission
document.getElementById('questionForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const btn = document.getElementById('submitQuestionBtn');
    const isEdit = !!formData.get('question_id');
    
    btn.disabled = true;
    btn.textContent = 'Saving...';
    
    const action = isEdit ? 'edit_question' : 'add_question';
    formData.append('action', action);
    
    fetch('test_api.php', {
        method: 'POST',
        body: formData
    })
    .then(res => {
        if (!res.ok) {
            throw new Error('Network response was not ok');
        }
        return res.json();
    })
    .then(data => {
        if (data.success) {
            alert('Question ' + (isEdit ? 'updated' : 'added') + ' successfully!');
            document.getElementById('questionModal').style.display = 'none';
            loadTests();
        } else {
            throw new Error(data.message || 'Unknown error occurred');
        }
    })
    .catch(err => {
        console.error('Error:', err);
        alert('Error: ' + err.message);
    })
    .finally(() => {
        btn.disabled = false;
        btn.textContent = 'Save Question';
    });
});

let currentTestId = null;
let currentUserId = null;

function loadTestResults() {
    const resultsContainer = document.getElementById('resultsContainer');
    resultsContainer.innerHTML = '<div class="loading">Loading tests...</div>';
    
    fetch('test_api.php?action=get_all_tests')
    .then(response => {
        // First check if the response is JSON
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            return response.text().then(text => {
                throw new Error(`Invalid JSON response: ${text}`);
            });
        }
        return response.json();
    })
    .then(data => {
        console.log('API Response:', data); // Debug log
        
        if (!data.success) {
            throw new Error(data.message || 'Server returned unsuccessful response');
        }
        
        if (!data.tests || data.tests.length === 0) {
            resultsContainer.innerHTML = `
                <div class="no-tests">
                    <p>No tests found in the database.</p>
                </div>
            `;
            return;
        }
        
        // Build tests list HTML
        let html = `
            <table class="tests-table" style="width: 100%;">
                <thead>
                    <tr>
                        <th>Test Name</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
        `;
        
        data.tests.forEach(test => {
            const testName = test.test_name || test.name || 'Unnamed Test';
            html += `
                <tr>
                    <td>${escapeHtml(testName)}</td>
                    <td>
                        <button class="view-btn" onclick="loadTestStudents(${test.id}, '${escapeHtml(testName)}')">
                            View Results
                        </button>
                        <button class="report-btn" onclick="loadExamReport(${test.id}, '${escapeHtml(testName)}')">
                            Exam Report
                        </button>
                        <button class="certificate-btn" onclick="showCertificateOptions(${test.id}, '${escapeHtml(testName)}')">
                            Certificates
                        </button>
                    </td>
                </tr>
            `;
        });
        
        html += `
                </tbody>
            </table>
        `;
        resultsContainer.innerHTML = html;
    })
    .catch(error => {
        console.error('Error loading tests:', error);
        resultsContainer.innerHTML = `
            <div class="error-message">
                <p>Failed to load tests. Please try again.</p>
                <p><small>Error: ${escapeHtml(error.message)}</small></p>
            </div>
        `;
    });
}

function loadTestStudents(testId, testName = '') {
    currentTestId = testId;
    document.getElementById('resultsContainer').innerHTML = 'Loading students...';
    
    fetch(`test_api.php?action=get_test_students_with_scores&test_id=${testId}`)
    .then(res => {
        if (!res.ok) {
            throw new Error('Network response was not ok');
        }
        return res.json();
    })
    .then(data => {
        console.log('Students data:', data); // Debug log
        
        if (!data.success) {
            throw new Error(data.message || 'Failed to load students');
        }
        
        if (!data.students || data.students.length === 0) {
            document.getElementById('resultsContainer').innerHTML = `
                <div>
                    <button class="back-btn" onclick="loadTestResults()">&larr; Back to Tests</button>
                    <p>No students took this test.</p>
                </div>
            `;
            return;
        }
        
        // Build students list HTML
        let html = `
            <div>
                <button class="back-btn" onclick="loadTestResults()">&larr; Back to Tests</button>
                <h3>${escapeHtml(testName || 'Test Results')}</h3>
                <table style="width: 100%;">
                    <thead>
                        <tr>
                            <th>Student Name</th>
                            <th>Score</th>
                            <th>Grade</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
        `;
        
        data.students.forEach(student => {
            // Calculate percentage and grade
            const percentage = student.max_score > 0 ? Math.round((student.score / student.max_score) * 100) : 0;
            let grade = 'F';
            if (percentage >= 90) grade = 'A';
            else if (percentage >= 80) grade = 'B';
            else if (percentage >= 70) grade = 'C';
            
            const canGenerate = grade !== 'F';
            
            html += `
                <tr>
                    <td>${escapeHtml(student.full_name)}</td>
                    <td>${student.score}/${student.max_score} (${percentage}%)</td>
                    <td class="grade-${grade}">${grade}</td>
                    <td>
                        <button class="view-btn" onclick="loadStudentResults(${student.id}, '${escapeHtml(student.full_name)}')">
                            View Details
                        </button>
                        <button class="certificate-btn ${canGenerate ? '' : 'disabled'}" 
                            onclick="${canGenerate ? `generateCertificate(${testId}, ${student.id}, '${escapeHtml(student.full_name)}', '${escapeHtml(testName)}', ${student.score}, ${student.max_score}, ${percentage}, '${grade}')` : 'alert(\'No certificate available for F grade\')'}">
                            ${canGenerate ? 'Generate Certificate' : 'No Certificate'}
                        </button>
                    </td>
                </tr>
            `;
        });
        
        html += `
                    </tbody>
                </table>
            </div>
        `;
        document.getElementById('resultsContainer').innerHTML = html;
    })
    .catch(err => {
        console.error('Error loading students:', err);
        document.getElementById('resultsContainer').innerHTML = `
            <div>
                <button class="back-btn" onclick="loadTestResults()">&larr; Back to Tests</button>
                <p>Error loading students: ${escapeHtml(err.message)}</p>
            </div>
        `;
    });
}

function loadStudentResults(userId, studentName = '') {
    currentUserId = userId;
    document.getElementById('resultsContainer').innerHTML = 'Loading results...';
    
    fetch(`test_api.php?action=get_student_results&test_id=${currentTestId}&user_id=${userId}`)
    .then(res => {
        if (!res.ok) {
            throw new Error('Network response was not ok');
        }
        return res.json();
    })
    .then(data => {
        console.log('Results data:', data); // Debug log
        
        if (!data.success) {
            throw new Error(data.message || 'Failed to load results');
        }
        
        if (!data.answers || data.answers.length === 0) {
            document.getElementById('resultsContainer').innerHTML = `
                <div>
                    <button class="back-btn" onclick="loadTestStudents(${currentTestId})">&larr; Back to Students</button>
                    <p>No results found for this student.</p>
                </div>
            `;
            return;
        }
        
        // Calculate percentage and grade
        const percentage = data.max_score > 0 ? (data.score / data.max_score) * 100 : 0;
        let grade = 'F';
        if (percentage >= 90) grade = 'A';
        else if (percentage >= 80) grade = 'B';
        else if (percentage >= 70) grade = 'C';
        
        // Build results HTML
        let html = `
            <div>
                <button class="back-btn" onclick="loadTestStudents(${currentTestId})">&larr; Back to Students</button>
                <h3>${escapeHtml(data.test.test_name || data.test.name || 'Test')} - ${escapeHtml(studentName || data.user.full_name || 'Student')}</h3>
                <h4>Score: ${data.score}/${data.max_score} (${Math.round(percentage)}%)</h4>
                <h4 class="grade-${grade}">Grade: ${grade}</h4>
                <div class="answers-container">
        `;
        
        data.answers.forEach((answer, index) => {
            const isCorrect = answer.selected_option == answer.correct_option;
            html += `
                <div class="answer-item" style="margin-bottom: 20px; padding: 15px; border: 1px solid #ddd; border-radius: 4px;">
                    <p><strong>Question ${index + 1}:</strong> ${escapeHtml(answer.question)}</p>
                    <p><strong>Weightage:</strong> ${answer.weightage} marks</p>
                    <div style="margin-left: 20px;">
                        ${[1, 2, 3, 4].map(i => {
                            const isSelected = answer.selected_option == i;
                            const isRight = answer.correct_option == i;
                            let style = '';
                            if (isSelected && isRight) style = 'color: green; font-weight: bold;';
                            else if (isSelected) style = 'color: red; font-weight: bold;';
                            else if (isRight) style = 'color: green;';
                            return `<p style="${style}">Option ${i}: ${escapeHtml(answer['option'+i])}</p>`;
                        }).join('')}
                    </div>
                    <p>${isCorrect ? '✅ Correct' : '❌ Incorrect'}</p>
                </div>
            `;
        });
        
        html += '</div></div>';
        document.getElementById('resultsContainer').innerHTML = html;
    })
    .catch(err => {
        console.error('Error loading results:', err);
        document.getElementById('resultsContainer').innerHTML = `
            <div>
                <button class="back-btn" onclick="loadTestStudents(${currentTestId})">&larr; Back to Students</button>
                <p>Error loading results: ${escapeHtml(err.message)}</p>
            </div>
        `;
    });
}

function loadExamReport(testId, testName = '') {
    document.getElementById('resultsContainer').style.display = 'none';
    document.getElementById('examReportContainer').style.display = 'block';
    document.getElementById('examReportContent').innerHTML = 'Loading exam report...';
    
    fetch(`test_api.php?action=get_test_report&test_id=${testId}`)
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(data => {
        console.log('Exam report data:', data); // Debug log
        
        if (!data.success) {
            throw new Error(data.message || 'Failed to load exam report');
        }
        
        if (!data.students || data.students.length === 0) {
            document.getElementById('examReportContent').innerHTML = `
                <div class="no-results">
                    <p>No students took this test yet.</p>
                </div>
            `;
            return;
        }
        
        // Calculate test statistics
        let totalStudents = data.students.length;
        let totalPassed = 0;
        let totalFailed = 0;
        let avgScore = 0;
        
        data.students.forEach(student => {
            const percentage = student.max_score > 0 ? (student.score / student.max_score) * 100 : 0;
            avgScore += percentage;
            if (percentage >= 70) {
                totalPassed++;
            } else {
                totalFailed++;
            }
        });
        
        avgScore = totalStudents > 0 ? (avgScore / totalStudents) : 0;
        
        // Format test dates
        const startDate = data.test.start_datetime ? new Date(data.test.start_datetime).toLocaleString() : 'N/A';
        const endDate = data.test.end_datetime ? new Date(data.test.end_datetime).toLocaleString() : 'N/A';
        
        // Build report HTML
        let html = `
            <div class="report-container">
                <div class="report-header">
                    <h3>${escapeHtml(testName || 'Exam Report')}</h3>
                </div>
                
                <div class="report-stats">
                    <div class="stat-item">
                        <div class="stat-value">${totalStudents}</div>
                        <div class="stat-label">Students</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value">${totalPassed}</div>
                        <div class="stat-label">Passed</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value">${totalFailed}</div>
                        <div class="stat-label">Failed</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value">${Math.round(avgScore)}%</div>
                        <div class="stat-label">Avg Score</div>
                    </div>
                </div>
                
                <p><strong>Test Period:</strong> ${startDate} to ${endDate}</p>
                
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>Student Name</th>
                            <th>Score</th>
                            <th>Percentage</th>
                            <th>Grade</th>
                        </tr>
                    </thead>
                    <tbody>
        `;
        
        data.students.forEach(student => {
            const percentage = student.max_score > 0 ? (student.score / student.max_score) * 100 : 0;
            let grade = 'F';
            if (percentage >= 90) grade = 'A';
            else if (percentage >= 80) grade = 'B';
            else if (percentage >= 70) grade = 'C';
            
            html += `
                <tr>
                    <td>${escapeHtml(student.full_name)}</td>
                    <td>${student.score}/${student.max_score}</td>
                    <td>${Math.round(percentage)}%</td>
                    <td class="grade-${grade}">${grade}</td>
                </tr>
            `;
        });
        
        html += `
                    </tbody>
                </table>
            </div>
        `;
        
        document.getElementById('examReportContent').innerHTML = html;
    })
    .catch(error => {
        console.error('Error loading exam report:', error);
        document.getElementById('examReportContent').innerHTML = `
            <div class="error-message">
                <p>Failed to load exam report. Please try again.</p>
                <p><small>Error: ${escapeHtml(error.message)}</small></p>
            </div>
        `;
    });
}

function generateExamReportPdf() {
    const reportContent = document.getElementById('examReportContent');
    const pdfBtn = document.getElementById('downloadPdfBtn');
    
    pdfBtn.disabled = true;
    pdfBtn.textContent = 'Generating PDF...';
    
    // Use html2canvas to capture the report content
    html2canvas(reportContent).then(canvas => {
        // Create PDF
        const pdf = new jsPDF('p', 'mm', 'a4');
        const imgData = canvas.toDataURL('image/png');
        const imgWidth = 210; // A4 width in mm
        const pageHeight = 295; // A4 height in mm
        const imgHeight = canvas.height * imgWidth / canvas.width;
        
        let heightLeft = imgHeight;
        let position = 0;
        
        // Add first page
        pdf.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
        heightLeft -= pageHeight;
        
        // Add additional pages if needed
        while (heightLeft >= 0) {
            position = heightLeft - imgHeight;
            pdf.addPage();
            pdf.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
            heightLeft -= pageHeight;
        }
        
        // Save the PDF
        pdf.save('exam_report.pdf');
        
        pdfBtn.disabled = false;
        pdfBtn.textContent = 'Download as PDF';
    }).catch(err => {
        console.error('Error generating PDF:', err);
        alert('Failed to generate PDF: ' + err.message);
        pdfBtn.disabled = false;
        pdfBtn.textContent = 'Download as PDF';
    });
}

function hideExamReport() {
    document.getElementById('examReportContainer').style.display = 'none';
    document.getElementById('resultsContainer').style.display = 'block';
}

function showCertificateOptions(testId, testName) {
    document.getElementById('resultsContainer').innerHTML = 'Loading students...';
    
    fetch(`test_api.php?action=get_test_students_with_scores&test_id=${testId}`)
    .then(res => res.json())
    .then(data => {
        if (!data.success || !data.students || data.students.length === 0) {
            document.getElementById('resultsContainer').innerHTML = `
                <div>
                    <button class="back-btn" onclick="loadTestResults()">&larr; Back to Tests</button>
                    <p>No students took this test.</p>
                </div>
            `;
            return;
        }
        
        let html = `
            <div>
                <button class="back-btn" onclick="loadTestResults()">&larr; Back to Tests</button>
                <h3>Generate Certificates for: ${escapeHtml(testName)}</h3>
                <table style="width: 100%; margin-top: 20px;">
                    <thead>
                        <tr>
                            <th>Student Name</th>
                            <th>Score</th>
                            <th>Grade</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
        `;
        
        data.students.forEach(student => {
            const percentage = student.max_score > 0 ? Math.round((student.score / student.max_score) * 100) : 0;
            let grade = 'F';
            if (percentage >= 90) grade = 'A';
            else if (percentage >= 80) grade = 'B';
            else if (percentage >= 70) grade = 'C';
            
            const canGenerate = grade !== 'F';
            
            html += `
                <tr>
                    <td>${escapeHtml(student.full_name)}</td>
                    <td>${student.score}/${student.max_score} (${percentage}%)</td>
                    <td class="grade-${grade}">${grade}</td>
                    <td>
                        <button class="certificate-btn ${canGenerate ? '' : 'disabled'}" 
                            onclick="${canGenerate ? `generateCertificate(${testId}, ${student.id}, '${escapeHtml(student.full_name)}', '${escapeHtml(testName)}', ${student.score}, ${student.max_score}, ${percentage}, '${grade}')` : 'alert(\'No certificate available for F grade\')'}">
                            ${canGenerate ? 'Generate Certificate' : 'No Certificate'}
                        </button>
                    </td>
                </tr>
            `;
        });
        
        html += `</tbody></table></div>`;
        document.getElementById('resultsContainer').innerHTML = html;
    })
    .catch(err => {
        console.error('Error:', err);
        document.getElementById('resultsContainer').innerHTML = `
            <div>
                <button class="back-btn" onclick="loadTestResults()">&larr; Back to Tests</button>
                <p>Error loading students: ${escapeHtml(err.message)}</p>
            </div>
        `;
    });
}

function generateCertificate(testId, userId, studentName, testName, score, maxScore, percentage, grade) {
    // Open certificate in new tab for printing/download
    const url = `test_api.php?action=generate_certificate&test_id=${testId}&user_id=${userId}&student_name=${encodeURIComponent(studentName)}&test_name=${encodeURIComponent(testName)}&score=${score}&max_score=${maxScore}&percentage=${percentage}&grade=${grade}`;
    window.open(url, '_blank');
}

function initStudyMaterials() {
    // Load subjects if on study materials tab
    if (document.querySelector('.tab[data-tab="study_materials"].active')) {
        loadSubjects();
    }

    // Tab click handler
    document.querySelector('.tab[data-tab="study_materials"]').addEventListener('click', function() {
        loadSubjects();
    });

    // Add subject button
    document.getElementById('addSubjectBtn').addEventListener('click', function() {
        const subjectName = prompt('Enter subject name:');
        if (subjectName && subjectName.trim()) {
            addSubject(subjectName.trim());
        }
    });

    // Add topic button
    document.getElementById('addTopicBtn').addEventListener('click', function() {
        const subjectId = this.getAttribute('data-subject-id');
        if (!subjectId) {
            showToast('Please select a subject first', 'error');
            return;
        }
        
        const topicName = prompt('Enter topic name:');
        if (topicName && topicName.trim()) {
            addTopic(subjectId, topicName.trim());
        }
    });

    // Upload material form
    const uploadForm = document.getElementById('uploadMaterialForm');
    if (uploadForm) {
        uploadForm.addEventListener('submit', function(e) {
            e.preventDefault();
            uploadMaterial(this);
        });
    }

    // Event delegation for delete buttons
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('delete-btn')) {
            e.stopPropagation();
            handleDeleteClick(e.target);
        }
    });
}

// Subject functions
function loadSubjects() {
    showLoading('subjectsList', 'Loading subjects...');
    
    fetch('admin.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=get_subjects'
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        if (!data || !data.success) {
            throw new Error(data?.message || 'Invalid data received from server');
        }
        
        renderSubjects(data.subjects);
    })
    .catch(error => {
        console.error('Error loading subjects:', error);
        showError('subjectsList', 'Failed to load subjects. Please try again.');
        showToast(error.message, 'error');
    });
}

function renderSubjects(subjects) {
    const subjectsList = document.getElementById('subjectsList');
    subjectsList.innerHTML = '';
    
    if (!subjects || subjects.length === 0) {
        subjectsList.innerHTML = '<p class="no-items">No subjects found</p>';
        return;
    }
    
    subjects.forEach(subject => {
        const item = document.createElement('div');
        item.className = 'subject-item';
        item.innerHTML = `
            <span onclick="loadTopics(${subject.id}, '${escapeHtml(subject.name)}', event)">${escapeHtml(subject.name)}</span>
            <button class="delete-btn" data-type="subject" data-id="${subject.id}">Delete</button>
        `;
        subjectsList.appendChild(item);
    });
}

function addSubject(name) {
    showLoading('subjectsList', 'Adding subject...');
    
    fetch('admin.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=add_subject&subject_name=${encodeURIComponent(name)}`
    })
    .then(handleJsonResponse)
    .then(data => {
        if (data.success) {
            loadSubjects();
            showToast('Subject added successfully!', 'success');
        } else {
            throw new Error(data.message || 'Failed to add subject');
        }
    })
    .catch(error => {
        console.error('Error adding subject:', error);
        showToast(error.message, 'error');
        loadSubjects(); // Refresh the list
    });
}

// Topic functions
function loadTopics(subjectId, subjectName, event) {
    if (event) event.stopPropagation();
    
    showLoading('topicsList', 'Loading topics...');
    
    // Update UI state
    updateActiveState('subject', subjectName);
    document.getElementById('topicsSection').style.display = 'block';
    document.getElementById('selectedSubjectTitle').textContent = subjectName;
    document.getElementById('addTopicBtn').setAttribute('data-subject-id', subjectId);
    document.getElementById('materialsSection').style.display = 'none';
    
    fetch('admin.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=get_topics&subject_id=${subjectId}`
    })
    .then(handleJsonResponse)
    .then(data => {
        if (data.success) {
            renderTopics(data.topics, subjectName);
        } else {
            throw new Error(data.message || 'Failed to load topics');
        }
    })
    .catch(error => {
        console.error('Error loading topics:', error);
        showError('topicsList', 'Failed to load topics. Please try again.');
        showToast(error.message, 'error');
    });
}

function renderTopics(topics, subjectName) {
    const topicsList = document.getElementById('topicsList');
    topicsList.innerHTML = '';
    
    if (!topics || topics.length === 0) {
        topicsList.innerHTML = '<p class="no-items">No topics found</p>';
        return;
    }
    
    topics.forEach(topic => {
        const item = document.createElement('div');
        item.className = 'topic-item';
        item.innerHTML = `
            <span onclick="loadMaterials(${topic.id}, '${escapeHtml(topic.name)}', '${escapeHtml(subjectName)}', event)">${escapeHtml(topic.name)}</span>
            <button class="delete-btn" data-type="topic" data-id="${topic.id}">Delete</button>
        `;
        topicsList.appendChild(item);
    });
}

function addTopic(subjectId, name) {
    showLoading('topicsList', 'Adding topic...');
    
    fetch('admin.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=add_topic&subject_id=${subjectId}&topic_name=${encodeURIComponent(name)}`
    })
    .then(handleJsonResponse)
    .then(data => {
        if (data.success) {
            const subjectName = document.getElementById('selectedSubjectTitle').textContent;
            loadTopics(subjectId, subjectName);
            showToast('Topic added successfully!', 'success');
        } else {
            throw new Error(data.message || 'Failed to add topic');
        }
    })
    .catch(error => {
        console.error('Error adding topic:', error);
        showToast(error.message, 'error');
        const subjectName = document.getElementById('selectedSubjectTitle').textContent;
        loadTopics(subjectId, subjectName); // Refresh the list
    });
}

// Material functions
function loadMaterials(topicId, topicName, subjectName, event) {
    if (event) event.stopPropagation();
    
    showLoading('materialsList', 'Loading materials...');
    
    // Update UI state
    updateActiveState('topic', topicName);
    document.getElementById('materialsSection').style.display = 'block';
    document.getElementById('selectedTopicTitle').textContent = `${subjectName} > ${topicName}`;
    document.getElementById('currentTopicId').value = topicId;
    
    fetch('admin.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=get_materials&topic_id=${topicId}`
    })
    .then(handleJsonResponse)
    .then(data => {
        if (data.success) {
            renderMaterials(data.materials);
        } else {
            throw new Error(data.message || 'Failed to load materials');
        }
    })
    .catch(error => {
        console.error('Error loading materials:', error);
        showError('materialsList', 'Failed to load materials. Please try again.');
        showToast(error.message, 'error');
    });
}

function renderMaterials(materials) {
    const materialsList = document.getElementById('materialsList');
    materialsList.innerHTML = '';
    
    if (!materials || materials.length === 0) {
        materialsList.innerHTML = '<p class="no-items">No materials found</p>';
        return;
    }
    
    materials.forEach(material => {
        const item = document.createElement('div');
        item.className = 'material-item';
        item.innerHTML = `
            <h5>${escapeHtml(material.title)}</h5>
            ${material.description ? `<p>${escapeHtml(material.description)}</p>` : ''}
            <p><a href="${material.file_path}" target="_blank" rel="noopener noreferrer">${material.file_name}</a></p>
            <div class="material-actions">
                <button class="delete-btn" data-type="material" data-id="${material.id}">Delete</button>
            </div>
        `;
        materialsList.appendChild(item);
    });
}

function uploadMaterial(form) {
    const topicId = document.getElementById('currentTopicId').value;
    if (!topicId) {
        showToast('Please select a topic first', 'error');
        return;
    }
    
    showLoading('materialsList', 'Uploading material...');
    
    fetch('upload_material.php', {
        method: 'POST',
        body: new FormData(form)
    })
    .then(handleJsonResponse)
    .then(data => {
        if (data.success) {
            const topicName = document.querySelector('.topic-item.active span').textContent;
            const subjectName = document.getElementById('selectedSubjectTitle').textContent;
            loadMaterials(topicId, topicName, subjectName);
            form.reset();
            showToast('Material uploaded successfully!', 'success');
        } else {
            throw new Error(data.message || 'Upload failed');
        }
    })
    .catch(error => {
        console.error('Error uploading material:', error);
        showToast(error.message, 'error');
        const topicName = document.querySelector('.topic-item.active span').textContent;
        const subjectName = document.getElementById('selectedSubjectTitle').textContent;
        loadMaterials(topicId, topicName, subjectName); // Refresh the list
    });
}

// Delete functions
function handleDeleteClick(button) {
    const type = button.getAttribute('data-type');
    const id = button.getAttribute('data-id');
    
    if (!type || !id) return;
    
    const confirmMessage = {
        'subject': 'Are you sure you want to delete this subject and all its contents?',
        'topic': 'Are you sure you want to delete this topic and all its materials?',
        'material': 'Are you sure you want to delete this material?'
    }[type];
    
    if (confirm(confirmMessage)) {
        switch(type) {
            case 'subject': deleteSubject(id); break;
            case 'topic': deleteTopic(id); break;
            case 'material': deleteMaterial(id); break;
        }
    }
}

function deleteSubject(id) {
    showLoading('subjectsList', 'Deleting subject...');
    
    fetch('admin.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=delete_subject&subject_id=${id}`
    })
    .then(handleJsonResponse)
    .then(data => {
        if (data.success) {
            loadSubjects();
            document.getElementById('topicsSection').style.display = 'none';
            document.getElementById('materialsSection').style.display = 'none';
            showToast('Subject deleted successfully!', 'success');
        } else {
            throw new Error(data.message || 'Failed to delete subject');
        }
    })
    .catch(error => {
        console.error('Error deleting subject:', error);
        showToast(error.message, 'error');
        loadSubjects(); // Refresh the list
    });
}

function deleteTopic(id) {
    const subjectId = document.getElementById('addTopicBtn').getAttribute('data-subject-id');
    const subjectName = document.getElementById('selectedSubjectTitle').textContent;
    
    showLoading('topicsList', 'Deleting topic...');
    
    fetch('admin.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=delete_topic&topic_id=${id}`
    })
    .then(handleJsonResponse)
    .then(data => {
        if (data.success) {
            loadTopics(subjectId, subjectName);
            document.getElementById('materialsSection').style.display = 'none';
            showToast('Topic deleted successfully!', 'success');
        } else {
            throw new Error(data.message || 'Failed to delete topic');
        }
    })
    .catch(error => {
        console.error('Error deleting topic:', error);
        showToast(error.message, 'error');
        loadTopics(subjectId, subjectName); // Refresh the list
    });
}

function deleteMaterial(id) {
    const topicId = document.getElementById('currentTopicId').value;
    const topicName = document.querySelector('.topic-item.active span').textContent;
    const subjectName = document.getElementById('selectedSubjectTitle').textContent;
    
    showLoading('materialsList', 'Deleting material...');
    
    fetch('admin.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=delete_material&material_id=${id}`
    })
    .then(handleJsonResponse)
    .then(data => {
        if (data.success) {
            loadMaterials(topicId, topicName, subjectName);
            showToast('Material deleted successfully!', 'success');
        } else {
            throw new Error(data.message || 'Failed to delete material');
        }
    })
    .catch(error => {
        console.error('Error deleting material:', error);
        showToast(error.message, 'error');
        loadMaterials(topicId, topicName, subjectName); // Refresh the list
    });
}

// Utility functions
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function handleJsonResponse(response) {
    if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
    }
    return response.json();
}

function showLoading(elementId, message) {
    const element = document.getElementById(elementId);
    if (element) {
        element.innerHTML = `<div class="loading-message">${message}</div>`;
    }
}

function showError(elementId, message) {
    const element = document.getElementById(elementId);
    if (element) {
        element.innerHTML = `<p class="error-message">${message}</p>`;
    }
}

function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.textContent = message;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.classList.add('fade-out');
        setTimeout(() => toast.remove(), 500);
    }, 3000);
}

function updateActiveState(type, name) {
    const selector = type === 'subject' ? '.subject-item' : '.topic-item';
    document.querySelectorAll(selector).forEach(el => el.classList.remove('active'));
    
    const items = document.querySelectorAll(selector);
    for (let item of items) {
        if (item.querySelector('span').textContent === name) {
            item.classList.add('active');
            break;
        }
    }
}
</script>

</body>
</html>