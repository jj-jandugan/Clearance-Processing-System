<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'organization') {
    header("Location: ../index.php");
    exit;
}

require_once "../classes/Organization.php";
$org_id = $_SESSION['ref_id'];
$orgObj = new Organization();

function getOrgNameById($orgObj, $org_id) {
    return "WMSU Student Council"; 
}

$organization_name = $orgObj->getOrgNameById($org_id);


$org_name = "Organization " . $org_id; 

$search_term = $_GET['search'] ?? '';
$signed_history = $orgObj->getHistoryRequests($org_id, $search_term);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Organization Sign-off History</title>
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
    <a href="pending.php">Pending Request</a>
    <a href="history.php" class="active">History</a> </div>

<div class="main-content">
    
    <div class="page-header">
        <div class="logo-text">CCS Clearance - History</div>
        <a href="../index.php" class="log-out-btn">LOG OUT</a>
    </div>
    
    <div class="page-content-wrapper">
        
        <h1>Organization Clearance History</h1>
        <hr>
        
        <h2>Completed Requests</h2>
        
        <div class="search-container">
            <form method="GET" action="history.php" style="display: flex; gap: 10px; align-items: center;">
                <input type="text" name="search" placeholder="Search Student Name or ID..." 
                       value="<?= htmlspecialchars($search_term) ?>" 
                       style="padding: 8px 10px; border: 1px solid #ccc; border-radius: 5px; width: 300px; font-size: 0.9em;">
                
                <button type="submit" class="submit-modal-btn" style="background-color: var(--color-logout-btn); padding: 8px 15px; width: auto; font-size: 0.9em;">
                    Search
                </button>
                
                <?php if ($search_term): ?>
                    <a href="history.php" class="log-out-btn" style="background-color: #aaa; margin-left: 0; padding: 8px 15px; font-size: 0.9em;">Clear Search</a>
                <?php endif; ?>
            </form>
            
            <?php if ($search_term): ?>
                <p style="margin-top: 10px; font-weight: 600;">Showing results for: "<?= htmlspecialchars($search_term) ?>"</p>
            <?php endif; ?>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th style="width: 15%;">Clearance ID</th>
                    <th style="width: 30%;">Student Name (ID)</th>
                    <th style="width: 15%;">Date Signed</th>
                    <th style="width: 15%;">Status</th>
                    <th style="width: 25%;">Remarks</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($signed_history)): ?>
                    <?php foreach ($signed_history as $request): 
                        $status_for_class = strtolower($request['signed_status']);
                        if ($status_for_class === 'cancelled') {
                             $status_for_class = 'rejected';
                        }
                    ?>
                        <tr>
                            <td><?= htmlspecialchars($request['clearance_id']) ?></td>
                            <td style="text-align: left;"><?= htmlspecialchars($request['student_name']) ?> (<?= htmlspecialchars($request['school_id'] ?? $request['student_id']) ?>)</td>
                            <td><?= htmlspecialchars(date('M d, Y H:i', strtotime($request['signed_date']))) ?></td>
                            <td class="status-<?= $status_for_class ?>"><?= htmlspecialchars($request['signed_status']) ?></td>
                            <td><?= htmlspecialchars($request['remarks']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" style="text-align:center;">No history records found<?= $search_term ? " matching the search term." : "." ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

    </div> </div> 
</body>
</html>