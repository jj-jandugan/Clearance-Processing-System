<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'faculty') {
    header("Location: ../index.php");
    exit;
}

require_once "../classes/Faculty.php";
require_once "../classes/Database.php";

$faculty_id = $_SESSION['ref_id'];
$facultyObj = new Faculty();
$db = new Database(); 

$details = $facultyObj->getFacultyDetails($faculty_id);
if (!$details) {
    $faculty_name = "Faculty";
    $position = "";
} else {
    $faculty_name = $details['fName'] . ' ' . $details['lName'];
    $position = $details['position'];
}

$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_value = '';
$filter_options = [];

if (strtolower($position) === 'adviser') {
    $filter_options = $facultyObj->getAssignedClassGroups($faculty_id); 
    $filter_value = isset($_GET['section_filter']) ? trim($_GET['section_filter']) : '';
    $filter_name = 'section_filter';
} else {
    $filter_options = $db->getAllDepartments(); 
    $filter_value = isset($_GET['department_filter']) ? trim($_GET['department_filter']) : '';
    $filter_name = 'department_filter';
}

$signed_history = $facultyObj->getHistoryRequests($faculty_id, $search_term, $filter_value);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($position) ?> History</title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="sidebar">
    <div class="sidebar-profile">
        <div class="profile-icon">👨‍🏫</div>
        <div class="profile-name" style="font-weight:700; margin-bottom:5px;"><?= htmlspecialchars($faculty_name) ?></div>
        <small><?= htmlspecialchars($position) ?></small>
    </div>
    <a href="dashboard.php">Dashboard</a>
    <?php if (strtolower($position) === 'adviser'): ?>
        <a href="section.php">Student List</a>
    <?php endif; ?>
    <a href="requirements.php">Clearance Requirements</a>
    <a href="pending.php">Pending Request</a>
    <a href="history.php" class="active">History</a>
</div>

<div class="main-content">
    <div class="page-header">
        <div class="logo-text">CCS Clearance - History</div>
        <a href="../index.php" class="log-out-btn">LOG OUT</a>
    </div>

    <div class="page-content-wrapper">
        <h1>Clearance Sign-off History</h1>
        <hr>

        <div class="search-container" style="margin-bottom:20px;">
            <form method="GET" action="history.php" style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                <input type="text" name="search" placeholder="Search Student Name or ID..."
                       value="<?= htmlspecialchars($search_term) ?>"
                       style="padding:8px 10px; border:1px solid #ccc; border-radius:5px; width:250px; font-size:0.95em;">

                <?php if (strtolower($position) === 'adviser'): ?>
                    <select name="section_filter" style="padding:8px 10px; border:1px solid #ccc; border-radius:5px; font-size:0.95em;">
                        <option value="">Filter by Section</option>
                        <?php foreach ($filter_options as $opt):
                            $section = $opt['class_group'];
                            if (!$section) continue;
                        ?>
                            <option value="<?= htmlspecialchars($section) ?>" <?= ($filter_value === $section) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($section) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php else: ?>
                    <select name="department_filter" style="padding:8px 10px; border:1px solid #ccc; border-radius:5px; font-size:0.95em;">
                        <option value="">Filter by Department</option>
                        <?php foreach ($filter_options as $opt): ?>
                            <option value="<?= htmlspecialchars($opt['department_id']) ?>" <?= ($filter_value == $opt['department_id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($opt['dept_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>

                <button type="submit" class="submit-modal-btn" style="background-color: var(--color-logout-btn); padding:8px 15px; font-size:0.95em;">
                    Apply Filter
                </button>

                <?php if ($search_term || $filter_value): ?>
                    <a href="history.php" class="log-out-btn" style="background-color:#aaa; padding:8px 15px; font-size:0.95em; text-decoration:none; color:#fff;">
                        Clear Filter
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <table>
            <thead>
                <tr>
                    <th style="width:15%;">Clearance ID</th>
                    <th style="width:30%;">Student Name (ID)</th>
                    <th style="width:15%;">Date Signed</th>
                    <th style="width:15%;">Status</th>
                    <th style="width:25%;">Remarks</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($signed_history)): ?>
                    <?php foreach ($signed_history as $request):
                        $status_class = strtolower($request['signed_status']);
                        if ($status_class === 'cancelled') $status_class = 'rejected';
                    ?>
                        <tr>
                            <td><?= htmlspecialchars($request['clearance_id']) ?></td>
                            <td style="text-align:left;"><?= htmlspecialchars($request['student_name']) ?> (<?= htmlspecialchars($request['school_id'] ?? $request['student_id']) ?>)</td>
                            <td><?= htmlspecialchars(date('M d, Y H:i', strtotime($request['signed_date']))) ?></td>
                            <td class="status-<?= $status_class ?>"><?= htmlspecialchars($request['signed_status']) ?></td>
                            <td><?= htmlspecialchars($request['remarks']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" style="text-align:center;">No history records found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

    </div> 
</div> 
</body>
</html>
