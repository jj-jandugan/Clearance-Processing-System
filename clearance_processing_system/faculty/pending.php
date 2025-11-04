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

$classGroups = [];
$filter_options = [];
if (strtolower($position) === 'adviser' || $position === 'Adviser') {
    $filter_options = $facultyObj->getAssignedClassGroups($faculty_id);
} else {
    $filter_options = $db->getAllDepartments();
}

$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
$selected_class_group = isset($_GET['class_group']) ? trim($_GET['class_group']) : '';
$filter_value = $selected_class_group ?: (isset($_GET['filter']) ? trim($_GET['filter']) : '');
$filter_name = 'section_filter'; 

$pending_requests = $facultyObj->getPendingRequests($faculty_id, $search_term, $filter_value, $filter_name);

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    $signature_id = $_POST['signature_id'] ?? null;
    $clearance_id = $_POST['clearance_id'] ?? null;
    $sign_order = $_POST['sign_order'] ?? null;
    $action = $_POST['action'] ?? null;
    $remarks = trim($_POST['remarks'] ?? '');

    if (!$signature_id || !$clearance_id || !$sign_order || !$action) {
        $message = "Missing form data.";
    } else {
        if (!$facultyObj->checkPrerequisites($clearance_id, $sign_order)) {
            $message = "Cannot sign yet! Not all previous signers have approved this request.";
        } else {
            $ok = $facultyObj->signClearance($signature_id, $action, $remarks ?: null);
            if ($ok) {
                $redirect_url = "pending.php?success=1";
                if ($search_term) $redirect_url .= "&search=" . urlencode($search_term);
                if ($selected_class_group) $redirect_url .= "&class_group=" . urlencode($selected_class_group);
                elseif (!empty($filter_value)) $redirect_url .= "&filter=" . urlencode($filter_value);
                header("Location: " . $redirect_url);
                exit;
            } else {
                $message = "Failed to process request.";
            }
        }
    }
}

if (isset($_GET['success']) && $_GET['success'] == 1) {
    $message = "Request processed successfully!";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title><?= htmlspecialchars($position) ?> Pending Sign-offs</title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="sidebar">
    <div class="sidebar-profile">
        <div class="profile-icon">
            <!-- replace emoji with actual img if you want -->
            <img src="../assets/img/profile.png" alt="profile" style="width:100%; height:100%; object-fit:cover;">
        </div>
        <div class="profile-name" style="font-weight:700; margin-bottom:5px;"><?= htmlspecialchars($faculty_name) ?></div>
        <small><?= htmlspecialchars($position) ?></small>
    </div>

    <a href="dashboard.php">Dashboard</a>
    <?php if (strtolower($position) === 'adviser' || $position === 'Adviser'): ?>
        <a href="section.php">Student List</a>
    <?php endif; ?>
    <a href="requirements.php">Clearance Requirements</a>
    <a href="pending.php" class="active">Pending Request</a>
    <a href="history.php">History</a>
</div>

<div class="main-content">
    <div class="page-header">
        <div class="logo-text">CCS Clearance - Pending</div>
        <a href="../index.php" class="log-out-btn">LOG OUT</a>
    </div>

    <div class="page-content-wrapper">
        <h1><?= htmlspecialchars($position) ?> Pending Request</h1>
        <hr>

        <?php if ($message): 
            $is_error = stripos($message, 'cannot') !== false || stripos($message, 'failed') !== false;
            $alert_bg = $is_error ? '#f8d7da' : '#d4edda';
            $alert_color = $is_error ? '#721c24' : '#155724';
        ?>
            <div class="alert-warning" style="background-color: <?= $alert_bg ?>; color: <?= $alert_color ?>; border-color: #c3e6cb;">
                <span style="font-size:1.2em;"><?= $is_error ? '⚠️' : '✅' ?></span>
                &nbsp;<?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <div class="search-container" style="margin-bottom:20px;">
            <form method="GET" action="pending.php" style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                <input type="text" name="search" placeholder="Search Student Name or ID..." 
                       value="<?= htmlspecialchars($search_term) ?>"
                       style="padding:8px 10px; border:1px solid #ccc; border-radius:5px; width:250px; font-size:0.95em;">

                <?php if (strtolower($position) === 'adviser' || $position === 'Adviser'): ?>
                    <select name="class_group" style="padding:8px 10px; border:1px solid #ccc; border-radius:5px; font-size:0.95em;">
                        <option value="">Filter by Section</option>
                        <?php foreach ($filter_options as $opt): 
                            // Each row expected to have ['class_group'] like 'CS2A'
                            $cg = $opt['class_group'] ?? ( (isset($opt['course']) && isset($opt['year_level']) && isset($opt['section_id'])) ? ($opt['course'] . $opt['year_level'] . $opt['section_id']) : '' );
                            if (!$cg) continue;
                        ?>
                            <option value="<?= htmlspecialchars($cg) ?>" <?= ($cg === $selected_class_group) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cg) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php else: ?>
                    <select name="filter" style="padding:8px 10px; border:1px solid #ccc; border-radius:5px; font-size:0.95em;">
                        <option value="">Filter by Department</option>
                        <?php foreach ($filter_options as $opt): ?>
                            <option value="<?= htmlspecialchars($opt['department_id']) ?>" <?= (isset($_GET['filter']) && $_GET['filter'] == $opt['department_id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($opt['dept_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>

                <button type="submit" class="submit-modal-btn" style="background-color: var(--color-logout-btn); padding:8px 15px; font-size:0.95em;">
                    Apply Filter
                </button>

                <?php if ($search_term || $selected_class_group || (isset($_GET['filter']) && $_GET['filter'])): ?>
                    <a href="pending.php" class="log-out-btn" style="background-color:#aaa; padding:8px 15px; font-size:0.95em; text-decoration:none; color:#fff;">
                        Clear Filter
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <h2>Pending Requests</h2>

        <table>
            <thead>
                <tr>
                    <th style="width:30%;">Student Name (School ID)</th>
                    <th style="width:10%;">Clearance ID</th>
                    <?php if (strtolower($position) !== 'adviser' && $position !== 'Adviser'): ?>
                        <th style="width:10%;">Dept ID</th>
                    <?php endif; ?>
                    <th style="width:10%;">Uploaded File</th>
                    <th style="width:10%;">Student History</th>
                    <th style="width:30%;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($pending_requests)): ?>
                    <?php foreach ($pending_requests as $request): ?>
                        <tr>
                            <td style="text-align:left;">
                                <?= htmlspecialchars($request['student_name']) ?> (<?= htmlspecialchars($request['school_id']) ?>)
                            </td>
                            <td><?= htmlspecialchars($request['clearance_id']) ?></td>

                            <?php if (strtolower($position) !== 'adviser' && $position !== 'Adviser'): ?>
                                <td><?= htmlspecialchars($request['department_id']) ?></td>
                            <?php endif; ?>

                            <td>
                                <?php if (!empty($request['uploaded_file'])): ?>
                                    <a href="../uploads/<?= htmlspecialchars($request['uploaded_file']) ?>" target="_blank" class="view-upload-btn" style="background-color:#17a2b8;">View File</a>
                                <?php else: ?>
                                    N/A
                                <?php endif; ?>
                            </td>

                            <td>
                                <button onclick="openModal(<?= (int)$request['clearance_id'] ?>)" class="view-upload-btn">View History</button>
                            </td>

                            <td>
                                <form method="POST" action="" style="display:flex; gap:6px; align-items:center; justify-content:center;">
                                    <input type="hidden" name="signature_id" value="<?= htmlspecialchars($request['signature_id']) ?>">
                                    <input type="hidden" name="clearance_id" value="<?= htmlspecialchars($request['clearance_id']) ?>">
                                    <input type="hidden" name="sign_order" value="<?= htmlspecialchars($request['sign_order']) ?>">

                                    <input type="text" name="remarks" placeholder="Optional Remarks" 
                                           style="padding:6px; border:1px solid #ccc; border-radius:4px; flex-grow:1; max-width:180px;">

                                    <button type="submit" name="action" value="Approved" class="submit-modal-btn" style="padding:6px 12px; font-weight:600;">Approve</button>

                                    <button type="submit" name="action" value="Rejected" class="cancel-modal-btn" style="padding:6px 12px; font-weight:600;">Reject</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="<?= (strtolower($position) === 'adviser' || $position === 'Adviser') ? 6 : 6 ?>" style="text-align:center;">
                            No pending clearance sign-offs found.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

    </div> <!-- page-content-wrapper -->
</div> <!-- main-content -->

<!-- Modal for history -->
<div id="modal" class="modal">
    <div class="modal-content" style="max-width:600px; text-align:left;">
        <span class="close" onclick="closeModal()" style="position:absolute; right:15px; top:10px; font-size:1.5em; cursor:pointer;">&times;</span>
        <h3 style="margin-top:5px;">Student Clearance History</h3>
        <div id="modal-body" style="overflow-x:auto; max-height:60vh;"></div>
    </div>
</div>

<script>
function openModal(clearanceId) {
    const modal = document.getElementById('modal');
    const body = document.getElementById('modal-body');
    body.innerHTML = 'Loading...';

    fetch(`student_history.php?clearance_id=${encodeURIComponent(clearanceId)}`)
        .then(res => {
            if (!res.ok) throw new Error('Network response was not ok');
            return res.json();
        })
        .then(data => {
            if (!data || data.length === 0) {
                body.innerHTML = '<p>No signatures yet.</p>';
            } else {
                let html = '<table width="100%"><thead><tr><th>Signer</th><th>Status</th><th>Date</th><th>Remarks</th></tr></thead><tbody>';
                data.forEach(h => {
                    const signer = h.signer_name || (h.signer_type === 'Organization' ? 'Organization' : 'Faculty');
                    let statusClass = 'status-pending';
                    if (h.signed_status === 'Approved') statusClass = 'status-approved';
                    else if (h.signed_status === 'Rejected' || h.signed_status === 'Cancelled') statusClass = 'status-rejected';
                    html += `<tr>
                                <td style="text-align:left;">${signer}</td>
                                <td class="${statusClass}">${h.signed_status}</td>
                                <td>${h.signed_date ? h.signed_date.substring(0,10) : 'N/A'}</td>
                                <td style="text-align:left;">${h.remarks ? h.remarks : '-'}</td>
                             </tr>`;
                });
                html += '</tbody></table>';
                body.innerHTML = html;
            }
            modal.style.display = 'block';
        })
        .catch(err => {
            console.error(err);
            body.innerHTML = '<p>Error fetching data. Check server logs.</p>';
            modal.style.display = 'block';
        });
}

function closeModal() {
    document.getElementById('modal').style.display = 'none';
}

window.onclick = function(event) {
    const modal = document.getElementById('modal');
    if (event.target === modal) closeModal();
};
</script>

</body>
</html>
