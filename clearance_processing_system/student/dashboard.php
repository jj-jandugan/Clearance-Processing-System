<?php
session_start();
require_once "../classes/Clearance.php";
require_once "../classes/Database.php";
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'student') {
    header("Location: ../index.php");
    exit;
}
$student_id = $_SESSION['ref_id'];
$clearanceObj = new Clearance();
$summary = $clearanceObj->getStudentDashboardSummary($student_id);
$recent_requests = $clearanceObj->getRecentClearanceRequests($student_id, 10);

$student_details_query = "SELECT CONCAT(fName, ' ', lName) as full_name FROM student WHERE student_id = :sid";
$db = new Database();
$conn = $db->connect();
$student_stmt = $conn->prepare($student_details_query);
$student_stmt->bindParam(':sid', $student_id, PDO::PARAM_INT);
$student_stmt->execute();
$student_name = $student_stmt->fetchColumn() ?? 'Student Name'; 
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Student Dashboard</title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="sidebar">
    <div class="sidebar-profile">
        <div class="profile-icon"><img src="../assets/img/profile.png" alt="Profile"></div>
        <?= htmlspecialchars($student_name) ?> 
    </div>
    <a href="dashboard.php" class="active">Dashboard</a>
    <a href="request.php">Request</a>
    <a href="status.php">Status</a>
    <a href="history.php">History</a>
</div>

<div class="main-content">
    <div class="page-header">
        <div class="logo-text">
            <img src="../assets/img/css_logo.png" alt="CCS Logo" style="height:40px;">
            CCS Clearance
        </div>
        <a href="../index.php" class="log-out-btn">LOG OUT</a>
    </div>

    <div class="page-content-wrapper">
        
        <h2>Summary of Clearance Actions</h2>
        
        <div class="card-container">
            
            <div class="card pending" onclick="window.location.href='status.php'" style="cursor: pointer;">
                <h3>Pending Request</h3>
                <p><?= $summary['Pending'] ?? 0 ?></p>
            </div>
            
            <div class="card approved" onclick="window.location.href='history.php'" style="cursor: pointer;">
                <h3>Approved Request</h3>
                <p><?= $summary['Approved'] ?? 0 ?></p>
            </div>
            
            <div class="card rejected" onclick="window.location.href='history.php'" style="cursor: pointer;">
                <h3>Rejected</h3>
                <p><?= $summary['Rejected'] ?? 0 ?></p>
            </div>

            <div class="card cancelled" onclick="window.location.href='history.php'" style="cursor: pointer;">
                <h3>Cancelled</h3>
                <p><?= $summary['Cancelled'] ?? 0 ?></p>
            </div>
        </div>

        <div class="recent-requests-section">
            <h2>Recent Clearance Request</h2>
            <table>
                <thead>
                    <tr>
                        <th>Signer</th>
                        <th>Date Requested</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!empty($recent_requests)): ?>
                    <?php foreach ($recent_requests as $r): 
                        $status_class = strtolower(htmlspecialchars($r['signer_status'] ?? $r['status']));
                        if ($status_class === 'rejected' || $status_class === 'cancelled') {
                            $status_class = 'rejected';
                        }
                    ?>
                        <tr>
                            <td><?= htmlspecialchars($r['signer_name'] ?? ($r['signer_type'] ?? '-')) ?></td>
                            <td><?= htmlspecialchars(date('m-d-Y', strtotime($r['date_requested']))) ?></td>
                            <td class="status-<?= $status_class ?>">
                                <?= htmlspecialchars($r['signer_status'] ?? $r['status']) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="3" style="text-align:center;">No recent requests found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>