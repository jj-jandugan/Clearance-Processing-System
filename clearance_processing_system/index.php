<?php
session_start();
require_once "classes/Account.php";

$accountObj = new Account();

$email = "";
$password = "";
$error = "";

// Allowable time discrepancy in seconds (e.g., 5 minutes)
define('MAX_TIME_DIFFERENCE', 300);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim(htmlspecialchars($_POST["email"]));
    $password = $_POST["password"];

    if (empty($email) || empty($password)) {
        $error = "Both email and password are required.";
    } else {
        $accountObj->email = $email;
        $accountObj->password = $password;

        $user = $accountObj->login();

        if ($user === 'TIME_TRAVEL_ERROR') {
            $error = "Time Discrepancy Error: Cannot log in. Your system time is set before the account's creation date. Please check your clock.";
        } elseif ($user) {
            // Check time difference between server and account creation
            $serverTime = time();
            $accountCreation = strtotime($user['created_at'] ?? date('Y-m-d H:i:s'));
            $timeDiff = $serverTime - $accountCreation;

            if ($timeDiff < -MAX_TIME_DIFFERENCE) {
                // Account creation is in the future (more than allowed discrepancy)
                $error = "Time Discrepancy Error: Your system clock is too far behind the server. Cannot log in.";
            } else {
                // Normal login
                $_SESSION['account_id'] = $user['account_id'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['ref_id'] = $accountObj->ref_id;

                // Redirect based on role
                switch ($user['role']) {
                    case 'student':
                        header("Location: student/dashboard.php");
                        break;
                    case 'faculty':
                        header("Location: faculty/dashboard.php"); 
                        break;
                    case 'organization':
                        header("Location: organization/dashboard.php");
                        break;
                    case 'admin':
                        header("Location: admin/dashboard.php");
                        break;
                    default:
                        $error = "Invalid role assigned.";
                }
                exit;
            }
        } else {
            $error = "Invalid email or password.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
<link rel="stylesheet" href="assets/css/login_style.css">
</head>
<body>
    <div class="login-card">
        <div class="logo-container">

            <img src="assets/img/wmsu_logo.png" alt="WMSU Logo" >
            <img src="assets/img/css_logo.png" alt="CCS Logo">
        </div>
    <h1>CCS Clearance System</h1>

    <form action="" method="post">
        <label for="email">Email:</label>
        <input type="email" name="email" id="email" value="<?= htmlspecialchars($email) ?>" required>
        <br><br>

        <label for="password">Password:</label>
        <input type="password" name="password" id="password" required>
        <br><br>

        <?php if ($error): ?>
            <p style="color: red;"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <input type="submit" value="Login">
    </form>
    
    <div class="footer-links">
            
            <a href="registration/register_select.php">Create an Account</a>
            
            <a href="#">Forgot Password?</a>
    </div>
</body>
</html>
