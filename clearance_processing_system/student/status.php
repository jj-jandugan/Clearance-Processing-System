<?php
session_start();
require_once "../classes/Clearance.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'student') {
    header("Location: ../index.php");
    exit;
}

$student_id = $_SESSION['ref_id'];
$clearanceObj = new Clearance();

$current_pending = $clearanceObj->getStudentHistory($student_id, 'Pending')[0] ?? null;
$clearance_status = [];
if ($current_pending) {
    $clearance_id = $current_pending['clearance_id'];
    $clearance_status = $clearanceObj->getClearanceStatus($clearance_id);
}


$student_details_query = "SELECT CONCAT(fName, ' ', lName) as full_name FROM student WHERE student_id = :sid";
$db = new Database();
$conn = $db->connect();
$student_stmt = $conn->prepare($student_details_query);
$student_stmt->bindParam(':sid', $student_id, PDO::PARAM_INT);
$student_stmt->execute();
$student_name = $student_stmt->fetchColumn() ?? 'Student Name'; 


if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['cancel_signature'])) {
    $signature_id = (int)$_POST['signature_id'];
    if ($signature_id) {
        $clearanceObj->cancelSignature($signature_id);
        header("Location: status.php?msg=" . urlencode("✅ Single request successfully cancelled. You can now re-request the form.") . "&type=success");
        exit;
    }
}


$message = "";
$messageType = "";
if (isset($_GET['msg']) && isset($_GET['type'])) {
    $message = htmlspecialchars($_GET['msg']);
    $messageType = htmlspecialchars($_GET['type']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Clearance Status</title>

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
    <a href="status.php" class="active">Status</a>
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
        
        <h1>Clearance Status</h1>
        <hr>
        
        <?php if ($message): ?>
            <p class="<?= htmlspecialchars($messageType) ?>" style="color:<?= $messageType == 'success' ? 'green' : 'red' ?>;"><?= $message ?></p>
        <?php endif; ?>

        <?php if ($current_pending): ?>
            <h2>Current Pending Clearance (ID: <?= htmlspecialchars($current_pending['clearance_id']) ?>)</h2>

            <table>
                <thead>
                    <tr>
                        <th>Signer</th>
                        <th>Date Requested</th>
                        <th>Status</th>
                        <th>Uploaded File</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($clearance_status as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['signer_name'] ?? $row['signer_type']) ?></td>
                            <td><?= htmlspecialchars($row['signed_date'] ?? $current_pending['date_requested']) ?></td>
                            <td class="status-<?= strtolower(htmlspecialchars($row['signed_status'])) ?>"><?= htmlspecialchars($row['signed_status']) ?></td>
                            <td>
                                <?php if (!empty($row['uploaded_file'])): ?>
                                    <a href="../uploads/<?= htmlspecialchars($row['uploaded_file']) ?>" target="_blank">View Upload</a>
                                <?php else: ?>
                                    No file
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($row['signed_status'] === 'Pending'): ?>
                                    <form method="post" onsubmit="return confirm('⚠️ Are you sure you want to cancel the request for this signer? This allows you to re-request it immediately on the New Request page.');" style="display:inline;">
                                        <input type="hidden" name="signature_id" value="<?= (int)$row['signature_id'] ?>">
                                        <button type="submit" name="cancel_signature" class="cancel-btn">Cancel</button>
                                    </form>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
        <?php else: ?>
            <p>You do not have a currently pending clearance request.</p>
            <p>Go to <a href="request.php">New Request</a> to start one.</p>
        <?php endif; ?>
    </div>
</div>
</body>
</html>