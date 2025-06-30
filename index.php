<?php
session_start();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer-master/src/Exception.php';
require 'PHPMailer-master/src/PHPMailer.php';
require 'PHPMailer-master/src/SMTP.php';

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "form_data";

// Connect to database
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Generate CAPTCHA code and store in session if not already set
if (empty($_SESSION['captcha_code'])) {
    $_SESSION['captcha_code'] = substr(str_shuffle("ABCDEFGHJKLMNPQRSTUVWXYZ23456789"), 0, 6);
}

$errors = [];
$success_message = "";
$full_name = $email = $phone = $dob = $gender = $address = $city = $captcha_input = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Sanitize input
    function clean($data) {
        return htmlspecialchars(stripslashes(trim($data)));
    }

    $full_name = clean($_POST['full_name'] ?? '');
    $email = clean($_POST['email'] ?? '');
    $phone = clean($_POST['phone'] ?? '');
    $dob = $_POST['dob'] ?? '';
    $gender = $_POST['gender'] ?? '';
    $address = clean($_POST['address'] ?? '');
    $city = clean($_POST['city'] ?? '');
    $captcha_input = $_POST['captcha'] ?? '';

    // Validate CAPTCHA (case-sensitive)
    if (!isset($_SESSION['captcha_code']) || $captcha_input !== $_SESSION['captcha_code']) {
        $errors[] = "Incorrect CAPTCHA code.";
    }

    // Validate fields
    if (!preg_match("/^[a-zA-Z\s]+$/", $full_name)) {
        $errors[] = "Full name must contain only letters and spaces.";
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }
    if (!preg_match("/^[0-9]{10}$/", $phone)) {
        $errors[] = "Phone number must be exactly 10 digits.";
    }
    if (empty($dob)) {
        $errors[] = "Date of birth is required.";
    }
    if (empty($gender)) {
        $errors[] = "Gender is required.";
    }
    if (empty($address)) {
        $errors[] = "Address is required.";
    }
    if (empty($city)) {
        $errors[] = "City is required.";
    }

    if (empty($errors)) {
        // Insert into database with prepared statement
        $stmt = $conn->prepare("INSERT INTO users (full_name, email, phone, dob, gender, address, city) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssss", $full_name, $email, $phone, $dob, $gender, $address, $city);

        if ($stmt->execute()) {
            // Send email with PHPMailer
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'singhayushman2025@gmail.com';  // your Gmail
                $mail->Password = 'liebazipypcajiqa';  // your Gmail app password
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = 587;

                $mail->setFrom('singhayushman2025@gmail.com', 'Form Notifier');
                $mail->addAddress('singhayushman2025@gmail.com');  // where to receive the form submissions

                // Add Reply-To header only if email is valid
                if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $mail->addReplyTo($email, $full_name);
                }

                $mail->Subject = 'New Form Submission Received';
                $mail->Body = "A new user has submitted the form:\n\n"
                    . "Full Name: $full_name\n"
                    . "Email: $email\n"
                    . "Phone: $phone\n"
                    . "Date of Birth: $dob\n"
                    . "Gender: $gender\n"
                    . "Address: $address\n"
                    . "City: $city\n";

                $mail->send();

                $success_message = "Form submitted and email sent successfully!";
                // Clear form values and regenerate CAPTCHA
                $full_name = $email = $phone = $dob = $gender = $address = $city = $captcha_input = "";
                $_SESSION['captcha_code'] = substr(str_shuffle("ABCDEFGHJKLMNPQRSTUVWXYZ23456789"), 0, 6);

            } catch (Exception $e) {
                $errors[] = "Email could not be sent. Mailer Error: {$mail->ErrorInfo}";
            }
        } else {
            $errors[] = "Database error: " . $stmt->error;
        }
        $stmt->close();
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <title>User Registration Form</title>
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            background-color: #f4f6f8;
            padding: 40px;
        }

        .container {
            width: 450px;
            margin: auto;
            background-color: #fff;
            padding: 30px 40px;
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
        }

        h2 {
            text-align: center;
            color: #333;
            margin-bottom: 25px;
        }

        label {
            display: block;
            margin-bottom: 6px;
            font-weight: 500;
        }

        input[type="text"],
        input[type="email"],
        input[type="date"],
        select,
        textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 6px;
            margin-bottom: 15px;
            font-size: 15px;
        }

        textarea {
            resize: vertical;
            min-height: 60px;
        }

        .error-box {
            background-color: #ffe6e6;
            color: #d8000c;
            border: 1px solid #d8000c;
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 6px;
        }

        .success-box {
            background-color: #e6ffea;
            color: #006400;
            border: 1px solid #006400;
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 6px;
            text-align: center;
            font-weight: 600;
        }

        button {
            width: 100%;
            background-color: #007BFF;
            color: white;
            padding: 12px;
            font-size: 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }

        button:hover {
            background-color: #0056b3;
        }

        .captcha-box {
            font-weight: bold;
            font-size: 18px;
            letter-spacing: 3px;
            background: #eee;
            display: inline-block;
            padding: 8px 12px;
            margin: 10px 0;
            user-select: none;
        }
    </style>
</head>

<body>

    <div class="container">
        <h2>User Registration Form</h2>

        <?php if (!empty($errors)): ?>
            <div class="error-box">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($success_message): ?>
            <div class="success-box"><?= $success_message ?></div>
        <?php endif; ?>

        <form action="" method="POST" novalidate>
            <label for="full_name">Full Name:</label>
            <input type="text" name="full_name" id="full_name" value="<?= htmlspecialchars($full_name) ?>">

            <label for="email">Email:</label>
            <input type="text" name="email" id="email" value="<?= htmlspecialchars($email) ?>">

            <label for="phone">Phone:</label>
            <input type="text" name="phone" id="phone" value="<?= htmlspecialchars($phone) ?>">

            <label for="dob">Date of Birth:</label>
            <input type="date" name="dob" id="dob" value="<?= htmlspecialchars($dob) ?>">

            <label for="gender">Gender:</label>
            <select name="gender" id="gender">
                <option value="">Select</option>
                <option value="Male" <?= ($gender === 'Male') ? 'selected' : '' ?>>Male</option>
                <option value="Female" <?= ($gender === 'Female') ? 'selected' : '' ?>>Female</option>
            </select>

            <label for="address">Address:</label>
            <textarea name="address" id="address"><?= htmlspecialchars($address) ?></textarea>

            <label for="city">City:</label>
            <input type="text" name="city" id="city" value="<?= htmlspecialchars($city) ?>">

            <label for="captcha">Enter the code shown below:</label><br>
            <div class="captcha-box"><?= $_SESSION['captcha_code'] ?></div><br>
            <input type="text" name="captcha" id="captcha" required placeholder="Enter CAPTCHA code" value="<?= htmlspecialchars($captcha_input) ?>"><br><br>

            <button type="submit">Submit</button>
        </form>
    </div>

</body>

</html>
