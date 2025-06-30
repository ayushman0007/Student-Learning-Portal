<?php
require 'db.php';
$errors = [];
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $full_name = trim($_POST["full_name"]);
    $email = trim($_POST["email"]);
    $password = password_hash($_POST["password"], PASSWORD_DEFAULT);
    $phone = trim($_POST["phone"]);
    $gender = $_POST["gender"];
    $dob = $_POST["dob"];
    $address = trim($_POST["address"]);

    $stmt = $conn->prepare("INSERT INTO user_accounts (full_name, email, password, phone, gender, dob, address) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssss", $full_name, $email, $password, $phone, $gender, $dob, $address);
    if ($stmt->execute()) {
        $success = "Signup successful. <a href='login.php'>Login here</a>";
    } else {
        $errors[] = "Email already registered or error occurred.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Signup</title>
    <style>
        <?php include 'style.css'; ?>
    </style>
</head>
<body>
    <div class="container">
        <h2>Signup</h2>
        <?php if (!empty($errors)): ?>
            <div class="error"><?php echo implode("<br>", $errors); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="success"><?php echo $success; ?></div>
        <?php endif; ?>
        <form method="POST">
            <input type="text" name="full_name" placeholder="Full Name" required>
            <input type="email" name="email" placeholder="Email" required>
            <input type="password" name="password" placeholder="Password" required>
            <input type="text" name="phone" placeholder="Phone">
            <select name="gender" required>
                <option value="">Gender</option>
                <option>Male</option>
                <option>Female</option>
            </select>
            <input type="date" name="dob" required>
            <textarea name="address" placeholder="Address" required></textarea>
            <button type="submit">Sign Up</button>
        </form>
        <p>Already registered? <a href="login.php">Login</a></p>
    </div>
</body>
</html>
