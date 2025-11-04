<?php
session_start();
require_once "../classes/Account.php";

$accountObj = new Account();

$error = $success = "";
$org_name = $email = $password = $confirm_password = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $org_name = trim($_POST['org_name']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);

    if (empty($org_name) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = "All fields are required!";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match!";
    } else {
        $accountObj->email = $email;
        $accountObj->password = $password;
        $accountObj->role = "organization";
        $accountObj->ref_id = null;
        $result = $accountObj->register(null, null, null, null, null, $org_name); 

        if ($result === true) {
            $success = "✅ Registration successful!";
            $org_name = $email = $confirm_password = "";
        } elseif (is_string($result)) {
            $error = "❌ " . $result;
        } else {
            $error = "❌ Email already registered!";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Organization Registration</title>
<link rel="stylesheet" href="../assets/css/login_style.css">
</head>
<body>

<div class="login-card">
    
    <h1>Create an Organization Account</h1>

    <?php if ($error): ?>
        <p class="error"><?= htmlspecialchars($error)?></p>
    <?php endif; ?>
    <?php if ($success): ?>
        <p class="success"><?= $success?></p>
    <?php endif; ?>

    <form method="POST">
        <div class="input-group">
            <label for="org_name">Organization Name:</label>
            <input type="text" name="org_name" id="org_name" value="<?= htmlspecialchars($org_name) ?>" required>
        </div>

        <div class="input-group">
            <label for="email">Email:</label>
            <input type="email" name="email" id="email" value="<?= htmlspecialchars($email) ?>" required>
        </div>

        <div class="input-group">
            <label for="password">Password:</label>
            <input type="password" name="password" id="password" required>
        </div>

        <div class="input-group">
            <label for="confirm_password">Confirm Password:</label>
            <input type="password" name="confirm_password" id="confirm_password" required>
        </div>

        <input type="submit" value="Register">
    </form>

    <div class="footer-links" style="justify-content: center; margin-top: 20px;">
        <a href="../index.php" style="color: var(--white-text);">Back to Login</a>
    </div>
</div>

</body>
</html>
