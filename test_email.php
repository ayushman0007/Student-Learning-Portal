<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer-master/src/Exception.php';
require 'PHPMailer-master/src/PHPMailer.php';
require 'PHPMailer-master/src/SMTP.php';

$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'singhayushman2025@gmail.com';
    $mail->Password   = 'liebazipypcajiqa'; // Your app password
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    $mail->setFrom('singhayushman2025@gmail.com', 'Test Mail');
    $mail->addAddress('singhayushman2025@gmail.com');

    $mail->Subject = 'Test Email';
    $mail->Body    = 'This is a test email sent using PHPMailer.';

    $mail->send();
    echo "Test email sent successfully.";
} catch (Exception $e) {
    echo "Mailer Error: {$mail->ErrorInfo}";
}
?>
