<?php
session_start();
require_once "../classes/Database.php"; 
require_once "../classes/Account.php";

$accountObj = new Account();
$error = $success = "";
$fName = $mName = $lName = $email = $school_id = "";
$dept_id = $course = $year_level = $section_id = "";
$confirm_password = "";


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $school_id = trim($_POST['school_id']);
    $fName = trim($_POST['fname']);
    $mName = trim($_POST['mname']);
    $lName = trim($_POST['lname']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);
    $dept_id = trim($_POST['department']);
    $course = trim($_POST['course']);
    $year_level = trim($_POST['level']);
    $section_id = trim($_POST['section']);


    $db = new Database();
    $conn = $db->connect();
    $adviser_id = null;

    $adviser_stmt = $conn->prepare("
        SELECT faculty_id 
        FROM faculty 
        WHERE course_assigned = :course AND position = 'Adviser' 
        LIMIT 1
    ");
    $adviser_stmt->bindParam(':course', $course, PDO::PARAM_STR);
    $adviser_stmt->execute();
    $adviser_id = $adviser_stmt->fetchColumn();
    
    if (empty($school_id) || empty($fName) || empty($lName) || empty($email) || empty($password) || empty($confirm_password) || empty($dept_id) || empty($course) || empty($year_level) || empty($section_id)) {
        $error = "All fields except middle name are required!";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match!";
    } elseif (!$adviser_id) {
        $error = "No adviser is currently assigned for the selected course (" . htmlspecialchars($course) . "). Please contact the administration.";
    } else {
        $accountObj->email = $email;
        $accountObj->password = $password;
        $accountObj->role = "student";
        $accountObj->ref_id = $school_id;

        $result = $accountObj->register($fName, $mName, $lName, $dept_id, null, null, $course, $year_level, $section_id, $adviser_id); 
        
        if ($result === true) {
           $success = "✅ Registration successful!";
           
           $school_id = $fName = $mName = $lName = $email = $confirm_password = $dept_id = $course = $year_level = $section_id = "";
           
        } else {
            if (is_string($result)) {
                if (strpos($result, 'registered') !== false || strpos($result, 'Internal Error') !== false) {
                    $error = "❌ " . $result;
                } else {
                    $error = "SYSTEM ERROR: " . $result . " Please check your database foreign keys (account_id, department_id).";
                }
            } else {
                $error = "Registration failed.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Student Registration</title>
<!-- Link the login style sheet -->
<link rel="stylesheet" href="../assets/css/login_style.css">
</head>
<body onload="updateCourseOptions()">
    <div class="login-card">
        
        <!-- Header area, using dynamic form title -->
        <h1>Create a Student Account</h1>

        <form method="POST">
            
            <p class="error"><?= htmlspecialchars($error)?></p>
            <p class="success"><?= htmlspecialchars($success)?></p>
            
            <!-- Student ID -->
            <div class="input-group">
                <label for="school_id">Student ID:</label>
                <input type="text" name="school_id" id="school_id" value="<?= htmlspecialchars($school_id) ?>" required>
            </div>

            <!-- Name Fields -->
            <div class="input-group">
                <label for="fname">Full Name:</label>
                <input type="text" name="fname" id="fname" placeholder="First Name" value="<?= htmlspecialchars($fName) ?>" required>
                <input type="text" name="mname" id="mname" placeholder="Middle Name" value="<?= htmlspecialchars($mName) ?>">
                <input type="text" name="lname" id="lname" placeholder="Last Name" value="<?= htmlspecialchars($lName) ?>" required>
            </div>

            <!-- Email & Security -->
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
            
            <!-- Academic Selection Fields -->
            <div class="input-group">
                <label for="department">Department:</label>
                <select name="department" id="department" onchange="updateCourseOptions()" required>
                    <option value="">Select Department</option>
                    <option value="1" <?= ($dept_id == "1") ? "selected" : ""?>>BSCS Department</option>
                    <option value="2" <?= ($dept_id == "2") ? "selected" : ""?>>BSIT Department</option>
                </select>
            </div>

            <div class="input-group">
                <label for="course">Course:</label>
                <select name="course" id="course" required>
                    <option value="">Select Department First</option>
                </select>
            </div>

            <div class="input-group">
                <label for="level">Level:</label>
                <select name="level" id="level" required>
                    <option value="">Select Level</option>
                    <option value="1" <?= ($year_level == "1") ? "selected" : ""?>>1st Year</option>
                    <option value="2" <?= ($year_level == "2") ? "selected" : ""?>>2nd Year</option>
                    <option value="3" <?= ($year_level == "3") ? "selected" : ""?>>3rd Year</option>
                    <option value="4" <?= ($year_level == "4") ? "selected" : ""?>>4th Year</option>
                </select>
            </div>

            <div class="input-group">
                <label for="section">Section:</label>
                <select name="section" id="section" required>
                    <option value="">Select Section</option>
                    <option value="A" <?= ($section_id == "A") ? "selected" : ""?>>A</option>
                    <option value="B" <?= ($section_id == "B") ? "selected" : ""?>>B</option>
                </select>
            </div>
            
            <input type="submit" value="Register Account">
        </form>

        <div class="footer-links" style="justify-content: center; margin-top: 20px;">
            <a href="../index.php" style="color: var(--white-text);">Back to Login</a>
        </div>
    </div>

<script>
    const courseData = {
        "1": [ // Department ID 1 (BSCS Department)
            { value: "cs", text: "CS" },
            { value: "act", text: "ACT" }
        ],
        "2": [ // Department ID 2 (BSIT Department)
            { value: "it", text: "IT" },
            { value: "appdev", text: "APPDEV" }
        ]
    };

    function updateCourseOptions() {
        const deptSelect = document.getElementById("department");
        const courseSelect = document.getElementById("course");
        const selectedDeptId = deptSelect.value;
        const courses = courseData[selectedDeptId] || [];

        // Clear existing options
        courseSelect.innerHTML = '';

        // Add default/placeholder option
        let defaultOption = document.createElement("option");
        defaultOption.value = "";
        defaultOption.text = selectedDeptId ? "Select Course" : "Select Department First";
        courseSelect.appendChild(defaultOption);

        // Populate new options
        courses.forEach(course => {
            let option = document.createElement("option");
            option.value = course.value;
            option.text = course.text;

            // Retain selection if page reloaded after error
            if ("<?= htmlspecialchars($course) ?>" === course.value) {
                option.selected = true;
            }

            courseSelect.appendChild(option);
        });
    }
    updateCourseOptions();
</script>

</body>
</html>