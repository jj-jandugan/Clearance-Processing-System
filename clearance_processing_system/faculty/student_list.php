<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'faculty') {
    header("Location: ../index.php");
    exit;
}

require_once "../classes/Faculty.php";
require_once "../classes/Clearance.php"; 

$faculty_id = $_SESSION['ref_id'];
$facultyObj = new Faculty();
$details = $facultyObj->getFacultyDetails($faculty_id);
$position = $details['position'];
$faculty_name = $details['fName'] . ' ' . $details['lName'];

if ($position != 'Adviser') {
    header("Location: dashboard.php");
    exit;
}

$clearanceObj = new Clearance();

$search_term = $_GET['search'] ?? '';
$selected_section = $_GET['section'] ?? null;


if (!$selected_section) {
    header("Location: sections.php");
    exit;
}

$all_students_for_adviser = $facultyObj->getAssignedStudents($faculty_id, $search_term); 

$students_in_selected_section = [];

$current_section_label = "Student List"; 

foreach ($all_students_for_adviser as $student) {
    $student_section_key = $student['course'] . $student['year_level'] . $student['section_id'];

    if ($student_section_key === $selected_section) {
        if ($current_section_label === "Student List") {
            $current_section_label = strtoupper($student['course']) . " - " . $student['year_level'] . $student['section_id'];
        }

        $status_data = $clearanceObj->getClearanceStatusByStudentId($student['student_id']);
        $clearance_id = $status_data[0]['clearance_id'] ?? null;
        $status_text = 'Not Started';

        if ($clearance_id) {
            $finalStatus = 'Completed'; 
            foreach ($status_data as $s) {
                 if ($s['signed_status'] == 'Pending') {
                     $finalStatus = 'Pending';
                     break;
                 }
                 if ($s['signed_status'] == 'Rejected' || $s['signed_status'] == 'Cancelled') {
                     $finalStatus = 'Issues';
                     break;
                 }
            }
            $status_text = $finalStatus;
        }
        
        $student['clearance_status'] = $status_text;
        $student['clearance_id'] = $clearance_id;

        $students_in_selected_section[] = $student;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Adviser Student List - <?= htmlspecialchars($current_section_label) ?></title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="sidebar">
    <div class="sidebar-profile">
        <div class="profile-icon">👨‍🏫</div>
        <div class="profile-name" style="font-weight: 700; margin-bottom: 5px;"><?= htmlspecialchars($faculty_name) ?></div>
        <small><?= htmlspecialchars($position) ?></small>
    </div>
    <a href="dashboard.php">Dashboard</a>
    <a href="sections.php" class="active">Student List</a> <a href="requirements.php">Clearance Requirements</a>
    <a href="pending.php">Pending Request</a>
    <a href="history.php">History</a>
</div>

<div class="main-content">
    
    <div class="page-header">
        <div class="logo-text">CCS Clearance - <?= htmlspecialchars($current_section_label) ?></div>
        <a href="../index.php" class="log-out-btn">LOG OUT</a>
    </div>
    
    <div class="page-content-wrapper">

        <h1>Advisee List: <?= htmlspecialchars($current_section_label) ?></h1>
        <hr>
        
        <p><a href="section.php" style="font-weight: 600;">&larr; Back to Sections</a></p>

        <div class="search-container" style="margin-bottom: 15px;">
            <form method="GET" action="student_list.php" style="display: flex; gap: 10px; align-items: center;">
                <input type="hidden" name="section" value="<?= htmlspecialchars($selected_section) ?>">
                
                <input type="text" name="search" placeholder="Search Student Name or ID..." 
                       value="<?= htmlspecialchars($search_term) ?>" 
                       style="padding: 8px 10px; border: 1px solid #ccc; border-radius: 5px; width: 300px; font-size: 0.9em;">
                
                <button type="submit" class="submit-modal-btn" style="background-color: var(--color-logout-btn); padding: 8px 15px; width: auto; font-size: 0.9em;">
                    Search
                </button>
                
                <?php if ($search_term): ?>
                    <a href="student_list.php?section=<?= urlencode($selected_section) ?>" 
                       class="log-out-btn" style="background-color: #aaa; margin-left: 0; padding: 8px 15px; font-size: 0.9em;">Clear Search</a>
                <?php endif; ?>
            </form>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th style="width: 35%;">Student Name (School ID)</th>
                    <th style="width: 20%;">Course/Section</th>
                    <th style="width: 25%;">Clearance Status</th>
                    <th style="width: 20%;">Student History</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!empty($students_in_selected_section)): ?>
                <?php foreach ($students_in_selected_section as $student): ?>
                    <tr>
                        <td style="text-align: left;"><?= htmlspecialchars($student['lName'] . ', ' . $student['fName'] . (!empty($student['mName']) ? ' ' . strtoupper($student['mName'][0]) . '.' : '')) ?> (<?= htmlspecialchars($student['school_id']) ?>)</td>


                        <td><?= htmlspecialchars(strtoupper($student['course'])) ?> / <?= htmlspecialchars($student['year_level']) ?><?= htmlspecialchars($student['section_id']) ?></td>
                        <td>
                            <span class="status-<?= str_replace(' ', '', $student['clearance_status']) ?>">
                                <?= htmlspecialchars($student['clearance_status']) ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($student['clearance_id']): ?>
                                 <button onclick="openModal(<?= $student['clearance_id'] ?>)" class="view-upload-btn">View History</button>
                            <?php else: ?>
                                N/A
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="4" style="text-align:center;">No students found in this section<?= $search_term ? " matching the search term." : "." ?></td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>

<div id="modal" class="modal">
    <div class="modal-content" style="max-width: 600px; text-align: left;">
        <span class="close" onclick="closeModal()" style="position: absolute; right: 15px; top: 10px; font-size: 1.5em; cursor: pointer;">&times;</span>
        <h3 style="margin-top: 5px;">Student Clearance History</h3>
        <div id="modal-body" style="overflow-x: auto;">
            </div>
    </div>
</div>

<script>
function openModal(clearanceId) {
    const modal = document.getElementById('modal');
    const body = document.getElementById('modal-body');
    body.innerHTML = 'Loading...';

    fetch(`student_history.php?clearance_id=${clearanceId}`)
        .then(res => res.json())
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
                        <td style="text-align: left;">${signer}</td>
                        <td class="${statusClass}">${h.signed_status}</td>
                        <td>${h.signed_date ? h.signed_date.substring(0, 10) : 'N/A'}</td>
                        <td style="text-align: left;">${h.remarks || '-'}</td>
                    </tr>`;
                });
                html += '</tbody></table>';
                body.innerHTML = html;
            }
            modal.style.display = 'block';
        })
        .catch(error => {
            console.error('Error fetching data:', error);
            body.innerHTML = '<p>Error fetching data. Check server logs (student_history.php) and database connection.</p>';
            modal.style.display = 'block';
        });
}

function closeModal() {
    document.getElementById('modal').style.display = 'none';
}

window.onclick = function(event) {
    if (event.target == document.getElementById('modal')) closeModal();
}
</script>

    </div> </div> </body>
</html>