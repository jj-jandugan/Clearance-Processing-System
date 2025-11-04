<?php
session_start();
require_once "../classes/Clearance.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'student') {
    header("Location: ../index.php");
    exit;
}

$student_id = $_SESSION['ref_id'];
$clearanceObj = new Clearance();

$all_history = $clearanceObj->getStudentSignatureHistory($student_id);

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
    <title>Clearance History</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="sidebar">
    <div class="sidebar-profile">
        <div class="profile-icon"><img src="../assets/img/profile.png" alt="Profile"></div>
        <?= htmlspecialchars($student_name) ?> 
    </div>
    <a href="dashboard.php">Dashboard</a>
    <a href="request.php">Request</a>
    <a href="status.php">Status</a>
    <a href="history.php" class="active">History</a>
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
        <h2>Clearance History</h2>
        <hr>

        <table>
            <thead>
                <tr>
                    <th>Clearance ID</th>
                    <th>Signed By</th>
                    <th>Date Requested</th>
                    <th>Date Signed</th>
                    <th>Status</th>
                    <th>Remarks / Action</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($all_history): ?>
                <?php foreach ($all_history as $h): ?>
                    <tr>
                        <td><?= htmlspecialchars($h['clearance_id']) ?></td>

                        <td><?= htmlspecialchars($h['signer_name']) ?></td>

                        <td><?= htmlspecialchars($h['date_requested']) ?></td>
                        
                        <td><?= $h['signed_date'] ? htmlspecialchars($h['signed_date']) : 'N/A' ?></td>

                        <td class="status-<?= strtolower(htmlspecialchars($h['signed_status'])) ?>">
                            <?= htmlspecialchars($h['signed_status']) ?>
                        </td>

                      <td>
                             <?php if ($h['signed_status'] === 'Approved' && $h['is_fully_approved']): ?>
                            <a href="view_certificate.php?clearance_id=<?= urlencode($h['clearance_id']) ?>">View Certificate</a>
                                 <?php elseif ($h['signed_status'] === 'Rejected' || $h['signed_status'] === 'Cancelled'): ?>

                            <?php elseif ($h['signed_status'] === 'Rejected' || $h['signed_status'] === 'Cancelled'): ?>
                                <?= !empty($h['remarks']) ? nl2br(htmlspecialchars($h['remarks'])) : '<i>No remarks</i>' ?>

                            <?php else: ?>
                                <a href="status.php">View Status</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>

            <?php else: ?>
                <tr><td colspan="6" style="text-align:center;">No clearance history available.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>