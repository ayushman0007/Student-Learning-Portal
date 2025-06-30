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

$destination_email = "singhayushman2025@gmail.com";

// Connect to database
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

function clean($data) {
    return htmlspecialchars(stripslashes(trim($data)));
}

$full_name = clean($_POST['full_name']);
$email = clean($_POST['email']);
$phone = clean($_POST['phone']);
$dob = $_POST['dob'];
$gender = $_POST['gender'];
$address = clean($_POST['address']);
$city = clean($_POST['city']);
$user_captcha = trim($_POST['captcha']);

$errors = [];

if (!isset($_SESSION['captcha_code']) || $user_captcha !== $_SESSION['captcha_code']) {
    $errors[] = "Incorrect CAPTCHA code.";
}

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

if (!empty($errors)) {
    echo "<h3>Validation Errors:</h3><ul>";
    foreach ($errors as $error) {
        echo "<li>$error</li>";
    }
    echo "</ul><br><a href='index.php'>Go back to the form</a>";
    exit();
}

// Insert into database
$sql = "INSERT INTO users (full_name, email, phone, dob, gender, address, city)
        VALUES ('$full_name', '$email', '$phone', '$dob', '$gender', '$address', '$city')";

if ($conn->query($sql) === TRUE) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'singhayushman2025@gmail.com';
        $mail->Password   = 'liebazipypcajiqa'; // Your app password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('singhayushman2025@gmail.com', 'Form Notification');
        $mail->addAddress($destination_email);
        $mail->addReplyTo($email, $full_name);

        $mail->Subject = 'New Form Submission Received';
        $mail->Body    =
            "Full Name: $full_name\n" .
            "Email: $email\n" .
            "Phone: $phone\n" .
            "Date of Birth: $dob\n" .
            "Gender: $gender\n" .
            "Address: $address\n" .
            "City: $city\n";

        $mail->send();
        echo "Form submitted successfully! Email sent.";
    } catch (Exception $e) {
        echo "Form submitted but email failed. Error: {$mail->ErrorInfo}";
    }
} else {
    echo "Database Error: " . $conn->error;
}

$conn->close();
?>
