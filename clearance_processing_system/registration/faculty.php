<?php
session_start();
require_once "../classes/Account.php";

$accountObj = new Account();

$error = $success = "";
$fName = $mName = $lName = $email = $position = "";
$dept_id = $course_assigned = "";
$confirm_password = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $fName = trim($_POST['fname'] ?? '');
    $mName = trim($_POST['mname'] ?? '');
    $lName = trim($_POST['lname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $position = trim($_POST['position'] ?? '');
    $dept_id = trim($_POST['department'] ?? '');
    $course_assigned = trim($_POST['course_assigned'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');

    $department_required = !in_array($position, ["SA Coordinator", "Dean"]);
    $course_required = ($position == "Adviser");

    if (empty($fName) || empty($lName) || empty($email) || empty($password) || empty($confirm_password) || empty($position) || ($department_required && empty($dept_id))) {
        $error = "All required fields must be filled!";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match!";
    } elseif ($course_required && empty($course_assigned)) {
        $error = "Advisers must be assigned a course!";
    } else {
        $accountObj->email = $email;
        $accountObj->password = $password;
        $accountObj->role = "faculty";

        $dept_id_db = $department_required ? $dept_id : NULL;
        $course_db = $course_required ? $course_assigned : NULL;

        $result = $accountObj->register($fName, $mName, $lName, $dept_id_db, $position, null, $course_db, null, null, null); 
        
        if ($result === true) {
            $success = "✅ Faculty registered successfully!";
            $fName = $mName = $lName = $email = $position = $dept_id = $course_assigned = $confirm_password = "";
        } else {
            if (is_string($result)) {
                $error = "❌ " . $result;
            } else {
                $error = "❌ Registration failed due to an unknown error.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<title>Faculty Registration</title>
<link rel="stylesheet" href="../assets/css/login_style.css">
</head>
<body onload="toggleFields()">
<div class="login-card">

<h2>Faculty Registration</h2>

<form method="POST">
    <p class="error"><?= htmlspecialchars($error) ?></p>
    <p class="success"><?= $success ?></p>

    <div class="input-group">
        <label for="fname">First Name:</label>
        <input type="text" name="fname" id="fname" value="<?= htmlspecialchars($fName) ?>" required>
    </div>

    <div class="input-group">
        <label for="mname">Middle Name:</label>
        <input type="text" name="mname" id="mname" value="<?= htmlspecialchars($mName) ?>">
    </div>

    <div class="input-group">
        <label for="lname">Last Name:</label>
        <input type="text" name="lname" id="lname" value="<?= htmlspecialchars($lName) ?>" required>
    </div>

    <div class="input-group">
        <label for="position">Position:</label>
        <select name="position" id="position" onchange="toggleFields()" required>
            <option value="">Select Position</option>
            <option value="SA Coordinator" <?= ($position == "SA Coordinator") ? "selected" : "" ?>>Student Affairs Coordinator</option>
            <option value="Adviser" <?= ($position == "Adviser") ? "selected" : "" ?>>Adviser</option>
            <option value="Department Head" <?= ($position == "Department Head") ? "selected" : "" ?>>Department Head</option>
            <option value="Dean" <?= ($position == "Dean") ? "selected" : "" ?>>Dean</option>
        </select>
    </div>

    <div class="input-group">
        <label for="department">Department:</label>
        <select name="department" id="department" onchange="toggleFields()" required>
            <option value="">Select Department</option>
            <option value="1" <?= ($dept_id == "1") ? "selected" : "" ?>>BSCS</option>
            <option value="2" <?= ($dept_id == "2") ? "selected" : "" ?>>BSIT</option>
        </select>
    </div>

    <div class="input-group">
        <label for="course_assigned">Course Assigned (Adviser Only):</label>
        <select name="course_assigned" id="course_assigned">
            <option value="">Select Course</option>
        </select>
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

<script>
    const courseData = {
        "1": [
            { value: "cs", text: "CS" },
            { value: "act", text: "ACT" }
        ],
        "2": [
            { value: "it", text: "IT" },
            { value: "appdev", text: "APPDEV" },
            { value: "networking", text: "NETWORKING" }
        ]
    };

    function updateCourseOptions() {
        const deptSelect = document.getElementById("department");
        const courseSelect = document.getElementById("course_assigned");
        const selectedDeptId = deptSelect.value;
        const courses = courseData[selectedDeptId] || [];

        const currentCourse = courseSelect.value;
        
        courseSelect.innerHTML = '';
        
        let defaultOption = document.createElement("option");
        defaultOption.value = "";
        defaultOption.text = "Select Course";
        courseSelect.appendChild(defaultOption);

        courses.forEach(course => {
            let option = document.createElement("option");
            option.value = course.value;
            option.text = course.text;

            if (currentCourse === course.value || "<?= htmlspecialchars($course_assigned) ?>" === course.value) {
                 option.selected = true;
            }

            courseSelect.appendChild(option);
        });

        if (!selectedDeptId) {
            defaultOption.text = "Select Department First";
        }
    }

    function toggleFields() {
        var position = document.getElementById("position").value;
        var deptSelect = document.getElementById("department");
        var courseSelect = document.getElementById("course_assigned");

        var departmentRequired = !["SA Coordinator", "Dean"].includes(position);
        var courseRequired = position === "Adviser";

        deptSelect.disabled = !departmentRequired;
        
        var shouldBeDisabled = !courseRequired || !deptSelect.value;
        
        courseSelect.disabled = shouldBeDisabled;

        if (!courseRequired) {
             if(position !== "Department Head" && position !== "Adviser") {
                 deptSelect.value = ''; 
             }
             courseSelect.value = '';
        }

        updateCourseOptions();
    }
    
    document.addEventListener('DOMContentLoaded', (event) => {
        toggleFields();
    });
</script>

</body>
</html>