<?php
require 'db.php';

if (!isset($_GET['take_test'])) {
    header("Location: profile.php?tab=test");
    exit;
}

$test_id = (int)$_GET['take_test'];
$uid = (int)$_SESSION['user']['id'];

// Verify test is still available
$current_time = date('Y-m-d H:i:s');
$test = $conn->query("
    SELECT *, 
    DATE_ADD(start_datetime, INTERVAL duration_minutes MINUTE) AS end_time 
    FROM tests 
    WHERE id = $test_id
    AND start_datetime <= '$current_time'
    AND '$current_time' <= LEAST(end_datetime, DATE_ADD(start_datetime, INTERVAL duration_minutes MINUTE))
")->fetch_assoc();

if (!$test) {
    header("Location: profile.php?tab=test");
    exit;
}

// Check if already submitted
$submitted = $conn->query("SELECT 1 FROM user_answers WHERE user_id=$uid AND test_id=$test_id LIMIT 1")->num_rows > 0;
if ($submitted) {
    header("Location: profile.php?tab=test");
    exit;
}

$questions = $conn->query("SELECT * FROM questions WHERE test_id=$test_id ORDER BY id");
$duration_seconds = $test['duration_minutes'] * 60;
?>

<!DOCTYPE html>
<html>
<head>
    <title>Take Test</title>
    <style>
    /* Your existing styles */
    </style>
</head>
<body>
    <div class="timer">Time Remaining: <span id="test-timer"><?= floor($duration_seconds/60) ?>:<?= sprintf("%02d", $duration_seconds%60) ?></span></div>
    
    <form id="test-form" method="POST" action="submit_test.php">
        <input type="hidden" name="test_id" value="<?= $test_id ?>">
        <input type="hidden" name="start_time" value="<?= time() ?>">
        
        <?php while ($q = $questions->fetch_assoc()): ?>
            <div class="question">
                <p><strong>Question <?= $q['id'] ?>:</strong> <?= htmlspecialchars($q['question']) ?></p>
                <div class="options">
                    <?php for ($i = 1; $i <= 4; $i++): ?>
                        <?php if (!empty($q["option$i"])): ?>
                            <label>
                                <input type="radio" name="answers[<?= $q['id'] ?>]" value="<?= $i ?>" required>
                                <?= htmlspecialchars($q["option$i"]) ?>
                            </label>
                        <?php endif; ?>
                    <?php endfor; ?>
                </div>
            </div>
        <?php endwhile; ?>
        
        <button type="submit" class="submit-btn">Submit Test</button>
    </form>

    <script>
    // Timer functionality
    let timeLeft = <?= $duration_seconds ?>;
    const timerElement = document.getElementById('test-timer');
    const form = document.getElementById('test-form');
    
    const timer = setInterval(() => {
        timeLeft--;
        const mins = Math.floor(timeLeft / 60);
        const secs = timeLeft % 60;
        timerElement.textContent = `${mins}:${secs < 10 ? '0' : ''}${secs}`;
        
        if (timeLeft <= 0) {
            clearInterval(timer);
            form.submit();
        }
    }, 1000);
    </script>
</body>
</html>