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


$requirements = $orgObj->getRequirements($org_id) ?? ""; 

$message = "";
$message_type = ""; 


if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_requirements'])) {
    $new_requirements = trim($_POST['requirements']);
    if ($orgObj->updateRequirements($org_id, $new_requirements)) {
        $requirements = $new_requirements;
        $message = "Requirements updated successfully!";
        $message_type = "success";
    } else {
        $message = "Failed to update requirements.";
        $message_type = "error";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set Requirements</title>
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
    <a href="requirement.php" class="active">Set Requirements</a> 
    <a href="pending.php">Pending Request</a>
    <a href="history.php">History</a>
</div>

<div class="main-content">
    
    <div class="page-header">
        <div class="logo-text">CCS Clearance - Set Requirements</div>
        <a href="../index.php" class="log-out-btn">LOG OUT</a>
    </div>
    
    <div class="page-content-wrapper">
        
        <h1>Set Clearance Requirements</h1>
        <hr>
        
        <?php if ($message): ?>
            <?php 
            $is_error = $message_type === 'error';
            $alert_bg = $is_error ? '#f8d7da' : '#d4edda';
            $alert_color = $is_error ? '#721c24' : '#155724';
            ?>
            <div class="alert-warning" style="background-color: <?= $alert_bg ?>; color: <?= $alert_color ?>; border-color: #c3e6cb;">
                <span style="font-size: 1.5em;"><?= $is_error ? '⚠️' : '✅' ?></span> <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <div class="form-container" style="background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.05);">
            <form action="" method="post">
                <h2 style="margin-top: 0; font-size: 1.4em;">Define Clearance Requirements</h2>

                <label for="requirements" style="display: block; font-weight: 600; margin-bottom: 8px; color: var(--color-text-dark);">
                    Requirements for Student Clearance:
                </label>
                <textarea name="requirements" id="requirements" rows="15" 
                          style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 5px; font-size: 1em; resize: vertical;" 
                          required><?= htmlspecialchars($requirements) ?></textarea>
                
                <p style="font-size: 0.9em; color: #666; margin-top: 10px;">
                    This text will be shown to students before they upload their document for your approval.
                </p>
                <br>
                
                <button type="submit" name="update_requirements" class="submit-modal-btn" style="width: 100%; max-width: 250px; padding: 12px; font-size: 1em;">
                    Update Requirements
                </button>
            </form>
        </div>

    </div> 
</div> 
</body>
</html>