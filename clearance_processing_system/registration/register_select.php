<?php
session_start();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select Registration Type</title>

    <link rel="stylesheet" href="../assets/css/login_style.css">
</head>
<body>

<div class="reg-card">
    <h3 class="fw-bold">Create Your Account</h3>

    <a href="student.php" class="reg-btn reg-btn-student">
        Register as Student
    </a>

    <a href="faculty.php" class="reg-btn reg-btn-faculty">
        Register as Faculty
    </a>

    <a href="orgs.php" class="reg-btn reg-btn-organization">
        Register as Organization
    </a>

    <div class="footer-links">
        <small>Already have an account?</small>
        <a href="../index.php">Login</a>
    </div>
</div>

</body>
</html>
