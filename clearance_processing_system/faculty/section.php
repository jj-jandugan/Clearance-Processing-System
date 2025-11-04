<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'faculty') {
    header("Location: ../index.php");
    exit;
}

require_once "../classes/Faculty.php";
require_once "../classes/Clearance.php"; 

$faculty_id = $_SESSION['ref_id'];
$facultyObj = new Faculty();
$details = $facultyObj->getFacultyDetails($faculty_id);
$position = $details['position'];
$faculty_name = $details['fName'] . ' ' . $details['lName'];

if ($position != 'Adviser') {
    header("Location: dashboard.php");
    exit;
}

$clearanceObj = new Clearance();

$all_students_for_adviser = $facultyObj->getAssignedStudents($faculty_id, $search_term = ''); 

$sections_grouped = [];

foreach ($all_students_for_adviser as $student) {
    $key = $student['course'] . $student['year_level'] . $student['section_id'];
    $label = strtoupper($student['course']) . " - " . $student['year_level'] . $student['section_id'];

    $status_data = $clearanceObj->getClearanceStatusByStudentId($student['student_id']);
    $clearance_id = $status_data[0]['clearance_id'] ?? null;
    $status_text = 'Not Started';

    if ($clearance_id) {
        $finalStatus = 'Completed'; 
        foreach ($status_data as $s) {
             if ($s['signed_status'] == 'Pending') {
                 $finalStatus = 'Pending';
                 break;
             }
             if ($s['signed_status'] == 'Rejected' || $s['signed_status'] == 'Cancelled') {
                 $finalStatus = 'Issues';
                 break;
             }
        }
        $status_text = $finalStatus;
    }
    
    if (!isset($sections_grouped[$key])) {
        $sections_grouped[$key] = [
            'label' => $label,
            'count' => 0,
            'students' => []
        ];
    }
    $sections_grouped[$key]['count']++;
    $sections_grouped[$key]['students'][] = $student;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Adviser Student Sections</title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="sidebar">
    <div class="sidebar-profile">
        <div class="profile-icon">👨‍🏫</div>
        <div class="profile-name" style="font-weight: 700; margin-bottom: 5px;"><?= htmlspecialchars($faculty_name) ?></div>
        <small><?= htmlspecialchars($position) ?></small>
    </div>
    <a href="dashboard.php">Dashboard</a>
    <a href="sections.php" class="active">Student List</a> <a href="requirements.php">Clearance Requirements</a>
    <a href="pending.php">Pending Request</a>
    <a href="history.php">History</a>
</div>

<div class="main-content">
    
    <div class="page-header">
        <div class="logo-text">CCS Clearance - Student Sections</div>
        <a href="../index.php" class="log-out-btn">LOG OUT</a>
    </div>
    
    <div class="page-content-wrapper">

        <h1>Advisee Sections</h1>
        <hr>

        <h2>Select a Section to View Students</h2>
        
        <div class="section-cards">
            <?php if (!empty($sections_grouped)): ?>
                <?php foreach ($sections_grouped as $key => $section): ?>
                    <a href="student_list.php?section=<?= urlencode($key) ?>" class="section-card">
                        <h3><?= htmlspecialchars($section['label']) ?></h3>
                        <p><?= $section['count'] ?> Students</p>
                    </a>
                <?php endforeach; ?>
            <?php else: ?>
                <p>You have no students assigned as an Adviser.</p>
            <?php endif; ?>
        </div>

    </div> </div> </body>
</html>