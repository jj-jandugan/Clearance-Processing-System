<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'organization') {
    header("Location: ../index.php");
    exit;
}

require_once "../classes/Organization.php";
$org_id = $_SESSION['ref_id'];
$orgObj = new Organization();
$pending_requests = $orgObj->getPendingRequests($org_id);
$message = "";

function getOrgNameById($orgObj, $org_id) {
    return "WMSU Student Council"; 
}
$organization_name = $orgObj->getOrgNameById($org_id);

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    $signature_id = $_POST['signature_id'];
    $action = $_POST['action']; 
    $remarks = $_POST['remarks'] ?? NULL;

    if ($orgObj->signClearance($signature_id, $action, $remarks)) {
        $message = "Clearance request for Signature ID #{$signature_id} was " . ucfirst(strtolower($action)) . " successfully!";
        $pending_requests = $orgObj->getPendingRequests($org_id); 
    } else {
        $message = "Failed to process request.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pending Sign-offs</title>
    <link rel="stylesheet" href="../assets/css/style.css"> 
</head>
<body>
<div class="sidebar">
    <div class="sidebar-profile">
        <div class="profile-icon">🏛️</div>
        <div class="profile-name" style="font-weight: 700; margin-bottom: 5px;"><?= htmlspecialchars($organization_name) ?></div>
        <small>Organization</small>
    </div>
    <a href="dashboard.php">Dashboard</a>
    <a href="requirement.php">Set Requirements</a>
    <a href="pending.php" class="active">Pending Request</a> <a href="history.php">History</a>
</div>

<div class="main-content">
    
    <div class="page-header">
        <div class="logo-text">CCS Clearance - Organization View</div>
        <a href="../index.php" class="log-out-btn">LOG OUT</a>
    </div>
    
    <div class="page-content-wrapper">
        
        <h1>Pending Clearance Requests</h1>
        <hr>
        
        <?php if ($message): ?>
            <div class="alert-warning" style="background-color: #d4edda; border-color: #c3e6cb; color: #155724;">
                <span style="font-size: 1.5em;">✅</span> <?= $message ?>
            </div>
        <?php endif; ?>

        <table>
            <thead>
                <tr>
                    <th style="width: 25%;">Student Name (ID)</th>
                    <th style="width: 10%;">Clearance ID</th>
                    <th style="width: 15%;">Date Uploaded</th>
                    <th style="width: 15%;">Uploaded File</th>
                    <th style="width: 35%;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($pending_requests): ?>
                    <?php foreach ($pending_requests as $request): ?>
                        <tr>
                            <td style="text-align: left;"><?= htmlspecialchars($request['student_name']) ?> (<?= htmlspecialchars($request['school_id']) ?>)</td>
                            <td><?= htmlspecialchars($request['clearance_id']) ?></td>
                            <td><?= htmlspecialchars($request['date_uploaded'] ?? 'N/A') ?></td> 
                            <td>
                                <?php if ($request['uploaded_file']): ?>
                                    <a href="../uploads/<?= htmlspecialchars($request['uploaded_file']) ?>" target="_blank" 
                                       class="view-upload-btn" style="background-color: #17a2b8;">View File</a>
                                <?php else: ?>
                                    N/A
                                <?php endif; ?>
                            </td>
                            <td>
                                <form action="" method="post" style="display:flex; gap: 5px; align-items: center; justify-content: center;">
                                    <input type="hidden" name="signature_id" value="<?= $request['signature_id'] ?>">
                                    
                                    <input type="text" name="remarks" placeholder="Optional Remarks" 
                                           style="padding: 6px; border: 1px solid #ccc; border-radius: 4px; flex-grow: 1; max-width: 150px;">
                                    
                                    <button type="submit" name="action" value="Approved" 
                                            class="submit-modal-btn" style="padding: 6px 12px; font-weight: 500; width: auto; font-size: 0.9em;">
                                        Approve
                                    </button>
                                    
                                    <button type="submit" name="action" value="Rejected" 
                                            class="cancel-modal-btn" style="padding: 6px 12px; font-weight: 500; width: auto; font-size: 0.9em;">
                                        Reject
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" style="text-align:center;">No pending clearance request for your organization.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

    </div> </div> 
</body>
</html>