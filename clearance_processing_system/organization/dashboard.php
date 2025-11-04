<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'organization') {
    header("Location: ../index.php");
    exit;
}

require_once "../classes/Organization.php"; 
$org_id = $_SESSION['ref_id'];

$orgObj = new Organization();

$organization_name = $orgObj->getOrgNameById($org_id);



$summary = $orgObj->getDashboardSummary($org_id); 
$recent_requests = $orgObj->getRecentRequests($org_id, 5);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Organization Dashboard</title>
    <link rel="stylesheet" href="../assets/css/style.css"> 
</head>
<body>
<div class="sidebar">
    <div class="sidebar-profile">
        <div class="profile-icon">🏛️</div>
        <div class="profile-name" style="font-weight: 700; margin-bottom: 5px;"><?= htmlspecialchars($organization_name) ?></div> 
        <small>Ref ID: <?= htmlspecialchars($org_id) ?></small>
    </div>
    <a href="dashboard.php" class="active">Dashboard</a>
    <a href="requirement.php">Set Requirements</a>
    <a href="pending.php">Pending Request</a>
    <a href="history.php">History</a>
</div>

<div class="main-content">
    
    <div class="page-header">
        <div class="logo-text">CCS Clearance - Organization View</div>
        <a href="../index.php" class="log-out-btn">LOG OUT</a>
    </div>
    
    <div class="page-content-wrapper">
        
        <h1>Organization Dashboard</h1>
        <hr>

        <div class="card-container" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
            
            <div class="card pending" onclick="window.location.href='pending.php'" style="cursor: pointer;">
                <h3>Pending</h3>
                <p><?= htmlspecialchars($summary['Pending'] ?? 0) ?></p>
            </div>
            
            <div class="card approved" onclick="window.location.href='history.php'" style="cursor: pointer;">
                <h3>Approved</h3>
                <p><?= htmlspecialchars($summary['Approved'] ?? 0) ?></p>
            </div>
            
            <div class="card rejected" onclick="window.location.href='history.php'" style="cursor: pointer;">
                <h3>Rejected</h3> 
                <p><?= htmlspecialchars($summary['Rejected'] ?? 0) ?></p>
            </div>

            <div class="card cancelled" onclick="window.location.href='history.php'" style="cursor: pointer;">
                <h3>Cancelled</h3> 
                <p><?= htmlspecialchars($summary['Cancelled'] ?? 0) ?></p>
            </div>
        </div>
        
        <div class="recent-requests-section">
            <h2>Recent Sign-offs (Last 7 Days)</h2>

            <table>
                <thead>
                    <tr>
                        <th>Clearance ID</th>
                        <th>Student Name (ID)</th>
                        <th>Date Signed</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($recent_requests)): ?>
                        <?php foreach ($recent_requests as $request): 
                            $status_class = strtolower(htmlspecialchars($request['signed_status']));
                            if ($status_class === 'cancelled') {
                                $status_class = 'rejected'; 
                            }
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($request['clearance_id']) ?></td>
                                <td><?= htmlspecialchars($request['student_name']) ?> (<?= htmlspecialchars($request['school_id']) ?>)</td>
                                <td><?= htmlspecialchars(date('M d, Y H:i', strtotime($request['signed_date'])))?></td>
                                <td class="status-<?= $status_class ?>">
                                    <?= htmlspecialchars($request['signed_status']) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" style="text-align:center;">No recent sign-offs found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div> 
</div> 
</body>
</html>