<?php
session_start();
require_once "../classes/Database.php";
require_once "../classes/Clearance.php";
require_once "../classes/Account.php"; 

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'student') {
    header("Location: ../index.php");
    exit();
}

$student_id = $_SESSION['ref_id'];
$db = new Database();
$conn = $db->connect();
$clearanceObj = new Clearance();

$message = "";
$messageType = "";

$student_details_query = "SELECT adviser_id, department_id, CONCAT(fName, ' ', lName) as full_name FROM student WHERE student_id = :sid";
$student_stmt = $conn->prepare($student_details_query);
$student_stmt->bindParam(':sid', $student_id, PDO::PARAM_INT);
$student_stmt->execute();
$student_details = $student_stmt->fetch(PDO::FETCH_ASSOC);
$adviser_id = $student_details['adviser_id'] ?? null;
$student_dept_id = $student_details['department_id'] ?? null; 
$student_name = $student_details['full_name'] ?? 'Student Name'; 

$org_query = "SELECT org_id, org_name, requirements FROM organization ORDER BY org_name";
$org_stmt = $conn->query($org_query);
$org_result = $org_stmt->fetchAll(PDO::FETCH_ASSOC);

$faculty_query = "
    SELECT 
        faculty_id, 
        CONCAT(fName, ' ', lName) AS faculty_name, 
        position, 
        requirements,
        department_id
    FROM 
        faculty 
    WHERE 
        -- Always include Dean, SA Coordinator, and general non-Department Head/Adviser roles
        (position IN ('Dean', 'SA Coordinator') OR (position != 'Department Head' AND position != 'Adviser'))
        
        -- Include the ASSIGNED Adviser only
        OR (position = 'Adviser' AND faculty_id = :adviser_id)
        
        -- Include the Department Head ONLY if their department matches the student's department
        OR (position = 'Department Head' AND department_id = :student_dept_id)
    ORDER BY 
        FIELD(position, 'Dean', 'Department Head', 'Adviser', 'SA Coordinator'), lName, fName";

$faculty_stmt = $conn->prepare($faculty_query);

if ($adviser_id !== null) {
    $faculty_stmt->bindParam(':adviser_id', $adviser_id, PDO::PARAM_INT);
} else {
    $faculty_stmt->bindValue(':adviser_id', null, PDO::PARAM_NULL);
}
if ($student_dept_id !== null) {
    $faculty_stmt->bindParam(':student_dept_id', $student_dept_id, PDO::PARAM_INT);
} else {
    $faculty_stmt->bindValue(':student_dept_id', null, PDO::PARAM_NULL);
}

$faculty_stmt->execute();
$faculty_members = $faculty_stmt->fetchAll(PDO::FETCH_ASSOC);


$current_clearance = $clearanceObj->getStudentHistory($student_id, 'Pending')[0] ?? null;
$currentSignatures = [];
$clearance_id = null;

if ($current_clearance) {
    $clearance_id = $current_clearance['clearance_id'];
    $currentSignatures = $clearanceObj->getSignaturesByClearance($clearance_id);
}

function getSignerStatus($type, $ref_id, $signatures) {
    foreach ($signatures as $sig) {
        if ($sig['signer_type'] == $type && $sig['signer_ref_id'] == $ref_id) {
            return $sig['signed_status'];
        }
    }
    return 'New';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_clearance'])) {
    $signer_type = trim($_POST['signer_type']);
    $signer_ref_id = trim($_POST['signer_ref_id']);
    $uploaded_file = null;
    $target_dir = "../uploads/";

    if (!$clearance_id) {
        $clearance_id = $clearanceObj->createClearanceRequest($student_id, null);
    }

    if ($clearance_id) {
        if (isset($_FILES['requirement_file']) && $_FILES['requirement_file']['error'] == 0) {
            if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);

            $file_extension = pathinfo($_FILES["requirement_file"]["name"], PATHINFO_EXTENSION);
            $new_filename = $student_id . "_" . $clearance_id . "_" . $signer_ref_id . "_" . time() . "." . $file_extension;
            $target_file = $target_dir . $new_filename;

            if (move_uploaded_file($_FILES["requirement_file"]["tmp_name"], $target_file)) {
                $uploaded_file = $new_filename;
            } else {
                $message = "❌ Error uploading file.";
                $messageType = "error";
            }
        }

        $sign_order = 1;
        if ($signer_type === 'Faculty') {
            foreach ($faculty_members as $member) {
                if ($member['faculty_id'] == $signer_ref_id) {
                    $pos = $member['position'] ?? '';
                    $order_map = ['SA Coordinator'=>2, 'Adviser'=>3, 'Department Head'=>4, 'Dean'=>5]; 
                    $sign_order = $order_map[$pos] ?? 1;
                    break;
                }
            }
        }

        $ok = $clearanceObj->submitSignatureUpload($clearance_id, $signer_type, $signer_ref_id, $uploaded_file, $sign_order);
        
        if ($ok) {
            header("Location: request.php?msg=" . urlencode(" Request submitted.") . "&type=success");
            exit;
        } else {
            if ($uploaded_file && file_exists($target_file)) unlink($target_file);
            $message = "❌ Could not submit request. A Pending or Approved request already exists for this signer.";
            $messageType = "error";
        }
    } else {
        $message = "❌ Could not create or find a clearance record.";
        $messageType = "error";
    }
}

if (isset($_GET['msg']) && isset($_GET['type'])) {
    $message = htmlspecialchars($_GET['msg']);
    $messageType = htmlspecialchars($_GET['type']);
}

function getOrgLogo($orgName) {
    $name = strtolower($orgName);
    if (strpos($name, 'phicss') !== false) return '../assets/img/phicss.png';
    if (strpos($name, 'csc') !== false) return '../assets/img/csc.png';
    if (strpos($name, 'venom') !== false) return '../assets/img/venom.png';
    if (strpos($name, 'gender') !== false) return '../assets/img/gender.png';
    return '../assets/img/wmsu_logo.png'; 
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Request Clearance</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="sidebar">
    <div class="sidebar-profile">
        <div class="profile-icon"><img src="../assets/img/profile.png" alt="Profile"></div>
        <div class="profile-name" style="font-weight: 700; margin-bottom: 5px;"><?= htmlspecialchars($student_name) ?></div>

    </div>
    <a href="dashboard.php">Dashboard</a>
    <a href="request.php" class="active">Request</a>
    <a href="status.php">Status</a>
    <a href="history.php">History</a>
</div>

<div class="main-content">
    <div class="page-header">
        <div class="logo-text">
            CCS Clearance - Request
        </div>
        <a href="../index.php" class="log-out-btn">LOG OUT</a>
    </div>

    <div class="page-content-wrapper">
        <div class="alert-warning">
            <span>!</span> Select the organization or faculty where you want to request a clearance.
        </div>
        
        <?php if ($message): 
            $is_error = $messageType == 'error';
            $alert_bg = $is_error ? '#f8d7da' : '#d4edda';
            $alert_color = $is_error ? '#721c24' : '#155724';
        ?>
            <div class="alert-warning" style="background-color: <?= $alert_bg ?>; color: <?= $alert_color ?>; border-color: #c3e6cb; margin-bottom: 20px;">
                <span style="font-size: 1.5em;"><?= $is_error ? '⚠️' : '✅' ?></span> <?= $message ?>
            </div>
        <?php endif; ?>

        <div class="signer-grid">
            <?php foreach ($org_result as $org): 
                $status = getSignerStatus('Organization', $org['org_id'], $currentSignatures);
                $org_name = htmlspecialchars($org['org_name']);
                $logo_url = getOrgLogo($org_name);
                
                // Escape requirements for JS safely.
                $js_safe_req = addslashes(preg_replace('/\s+/', ' ', $org['requirements']));
                
                $badge_status_class = strtolower($status);
                
                // --- DISABLING LOGIC ---
                $is_disabled = ($status === 'Approved' || $status === 'Pending');
                $card_class = $is_disabled ? 'signer-card-org status-approved-card' : 'signer-card-org';
                $onclick_handler = $is_disabled ? '' : "openModal('Organization',{$org['org_id']}, '{$js_safe_req}', '".addslashes($org_name)."')";
            ?>
                <div class="<?= $card_class ?>" 
                     onclick="<?= $onclick_handler ?>"
                     style="<?= $is_disabled ? 'cursor: default; opacity: 0.6;' : '' ?>">
                    
                    <div class="logo-small" style="background-image: url('<?= $logo_url ?>');"></div>

                    <h3><?= $org_name ?></h3>
                    <p style="color: #ccc; margin-top: 5px;">Organization</p>
                    <div class="status-badge status-<?= $badge_status_class ?>">
                        <?= htmlspecialchars($status) ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php foreach ($faculty_members as $faculty): 
                $status = getSignerStatus('Faculty', $faculty['faculty_id'], $currentSignatures);
                $display_name = htmlspecialchars($faculty['faculty_name']);
                $display_position = htmlspecialchars($faculty['position']);
                
                // Escape requirements for JS safely
                $js_safe_req = addslashes(preg_replace('/\s+/', ' ', $faculty['requirements'] ?? 'No requirements set.'));
                
                $badge_status_class = strtolower($status);
                
                // --- DISABLING LOGIC ---
                $is_disabled = ($status === 'Approved' || $status === 'Pending');
                $card_class = $is_disabled ? 'signer-card-faculty status-approved-card' : 'signer-card-faculty';
                $onclick_handler = $is_disabled ? '' : "openModal('Faculty',{$faculty['faculty_id']}, '{$js_safe_req}', '".addslashes($display_name)."')";
            ?>
                <div class="<?= $card_class ?>" 
                     onclick="<?= $onclick_handler ?>"
                     style="<?= $is_disabled ? 'cursor: default; opacity: 0.6;' : '' ?>">
                    
                    <div class="logo-small" style="background-image: url('../assets/img/wmsu_logo.png');"></div>

                    <h3><?= $display_name ?></h3>
                    <p><?= $display_position ?></p>
                     <div class="status-badge status-<?= $badge_status_class ?>">
                        <?= htmlspecialchars($status) ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div id="modal" class="modal">
            <div class="modal-content">
                <span style="position:absolute; right:14px; top:10px; cursor:pointer; font-size: 20px;" onclick="closeModal()">✕</span>
                <h2>Request Signature</h2>
                <p>Signer: <strong id="mName"></strong></p>
                <p>
                    <span style="font-weight: 600; display: block; margin-top: 10px; margin-bottom: 5px;">Requirements:</span>
                    <span id="mReq" style="font-size: 0.9em; color: #555; display: block; border: 1px solid #eee; padding: 10px; border-radius: 4px; background: #f9f9f9; text-align: left;"></span>
                </p>
                
                <form method="post" action="request.php" enctype="multipart/form-data">
                    <input type="hidden" name="signer_type" id="in_type">
                    <input type="hidden" name="signer_ref_id" id="in_ref">
                    <input type="hidden" name="request_clearance" value="1">
                    
                    <div class="file-upload-box">
                        <div class="upload-icon">
                             <img src="../assets/img/upload_icon.png" alt="Upload" style="height: 40px;"> 
                        </div>
                        <p style="margin-bottom: 5px; font-size: 1em;">Select your file or drag and drop</p>
                        <p style="font-size: 0.8em; color: #666; margin-top: 0;">png, pdf, jpg, docx</p>
                        
                        <label for="requirement_file" class="browse-button">Browse</label>
                        <input type="file" name="requirement_file" id="requirement_file" >
                    </div>
                    
                    <div class="modal-actions">
                        <button type="button" onclick="closeModal()" class="cancel-modal-btn">Cancel</button>
                        <button type="submit" class="submit-modal-btn">Submit</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Function to update the file input visibility when a file is selected (UX improvement)
document.getElementById('requirement_file').addEventListener('change', function() {
    const fileName = this.files.length > 0 ? this.files[0].name : "Select your file or drag and drop";
    const uploadBox = this.closest('.file-upload-box');
    const pTag = uploadBox.querySelector('p:first-of-type');
    
    pTag.textContent = fileName;
});


function openModal(type, ref, req, name) {
    document.getElementById('mName').innerText = name;
    
    // FIX: Safely display plain text and replace line breaks with HTML <br> tags.
    const reqSpan = document.getElementById('mReq');
    
    // The replace function converts escaped newlines from the PHP string back to <br> tags.
    reqSpan.innerHTML = req.replace(/\\r\\n|\r\n|\n|\r/g, '<br>');
    
    document.getElementById('in_type').value = type;
    document.getElementById('in_ref').value = ref;
    document.getElementById('modal').style.display = 'block';

    // Reset file input display text on modal open
    const fileInput = document.getElementById('requirement_file');
    fileInput.value = ''; // Clear actual file
    const uploadBox = fileInput.closest('.file-upload-box');
    const pTag = uploadBox.querySelector('p:first-of-type');
    pTag.textContent = "Select your file or drag and drop";
}
function closeModal() { document.getElementById('modal').style.display = 'none'; }
</script>
</body>
</html>