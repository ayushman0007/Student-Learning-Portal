<?php
require 'db.php';

if (!isset($_GET['id'])) {
    echo "No user ID specified.";
    exit;
}

$userId = intval($_GET['id']);

// Fetch user details
$userQuery = $conn->prepare("SELECT * FROM user_accounts WHERE id = ?");
$userQuery->bind_param("i", $userId);
$userQuery->execute();
$userResult = $userQuery->get_result();

if ($userResult->num_rows === 0) {
    echo "User not found.";
    exit;
}

$user = $userResult->fetch_assoc();

// Fetch documents
$docsQuery = $conn->prepare("SELECT * FROM documents WHERE user_id = ? ORDER BY uploaded_at DESC");
$docsQuery->bind_param("i", $userId);
$docsQuery->execute();
$docsResult = $docsQuery->get_result();
?>

<!DOCTYPE html>
<html>
<head>
    <title>View User</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f4f4;
            margin: 0;
            padding: 20px;
        }

        .container {
            max-width: 700px;
            margin: auto;
            background: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 0 12px rgba(0,0,0,0.1);
        }

        h2 {
            text-align: center;
            margin-bottom: 20px;
        }

        p {
            margin: 10px 0;
        }

        ul {
            margin-top: 15px;
        }

        a {
            color: #007bff;
            text-decoration: none;
        }

        a:hover {
            text-decoration: underline;
        }

        .back-link {
            margin-top: 20px;
            display: block;
            text-align: center;
        }

    </style>
</head>
<body>
<div class="container">
    <h2>User Details</h2>

    <p><strong>Name:</strong> <?= htmlspecialchars($user["full_name"]) ?></p>
    <p><strong>Email:</strong> <?= htmlspecialchars($user["email"]) ?></p>
    <p><strong>Phone:</strong> <?= htmlspecialchars($user["phone"]) ?></p>
    <p><strong>Gender:</strong> <?= htmlspecialchars($user["gender"]) ?></p>
    <p><strong>DOB:</strong> <?= htmlspecialchars($user["dob"]) ?></p>
    <p><strong>City:</strong> <?= htmlspecialchars($user["city"]) ?></p>

    <h3>Uploaded Documents:</h3>
    <?php if ($docsResult->num_rows > 0): ?>
        <ul>
            <?php while ($doc = $docsResult->fetch_assoc()): ?>
                <li>
                    <a href="<?= htmlspecialchars($doc['file_path']) ?>" target="_blank"><?= htmlspecialchars($doc['file_name']) ?></a>
                    (<?= $doc['uploaded_at'] ?>)
                </li>
            <?php endwhile; ?>
        </ul>
    <?php else: ?>
        <p>No documents uploaded.</p>
    <?php endif; ?>

    <a href="admin.php" class="back-link">← Back to Admin Panel</a>
</div>
</body>
</html>
