<?php
session_start();
require 'db.php';
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = $_POST["email"];
    $password = $_POST["password"];

    $stmt = $conn->prepare("SELECT * FROM user_accounts WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($user = $result->fetch_assoc()) {
        if ($user["is_active"] == 0) {
            $error = "Your account is inactive. Please contact the admin.";
        } elseif (password_verify($password, $user["password"])) {
            $_SESSION["user"] = $user;
            header("Location: profile.php");
            exit;
        } else {
            $error = "Invalid password.";
        }
    } else {
        $error = "Email not registered.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Login</title>
    <style><?php include 'style.css'; ?></style>
</head>
<body>
    <div class="container">
        <h2>Login</h2>
        <?php if ($error): ?><div class="error"><?php echo $error; ?></div><?php endif; ?>
        <form method="POST">
            <input type="email" name="email" placeholder="Email" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit">Login</button>
        </form>
        <p>Not registered? <a href="signup.php">Signup</a></p>
    </div>
</body>
</html>
