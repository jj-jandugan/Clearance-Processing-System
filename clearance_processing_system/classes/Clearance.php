<?php
require_once "Database.php";

class Clearance extends Database {
    protected $conn;

    public function __construct() {
        $this->conn = $this->connect();
    }

    public function getConnection() {
        return $this->conn;
    }

    public function getClearanceFinalStatus($clearance_id) {
        $rejected_sql = "SELECT COUNT(*) FROM clearance_signature 
                         WHERE clearance_id = :cid AND signed_status IN ('Rejected', 'Cancelled')";
        $rej_stmt = $this->conn->prepare($rejected_sql);
        $rej_stmt->bindParam(':cid', $clearance_id);
        $rej_stmt->execute();
        if ($rej_stmt->fetchColumn() > 0) {
            return 'REJECTED';
        }

        $sql = "SELECT 
                    (SELECT COUNT(*) FROM clearance_signature WHERE clearance_id = :cid1) AS total_signatures,
                    (SELECT COUNT(*) FROM clearance_signature WHERE clearance_id = :cid2 AND signed_status = 'Approved') AS approved_signatures";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':cid1', $clearance_id);
        $stmt->bindParam(':cid2', $clearance_id);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result && $result['total_signatures'] > 0 && ($result['total_signatures'] == $result['approved_signatures'])) {
            return 'CLEARED';
        }
        
        $status_sql = "SELECT status FROM clearance WHERE clearance_id = :cid3";
        $status_stmt = $this->conn->prepare($status_sql);
        $status_stmt->bindParam(':cid3', $clearance_id);
        $status_stmt->execute();
        return $status_stmt->fetchColumn() ?? 'PENDING'; 
    }

    public function getStudentDashboardSummary($student_id) {
    $summary = ['Pending' => 0, 'Approved' => 0, 'Rejected' => 0, 'Cancelled' => 0];

    $sql = "
        SELECT cs.signed_status AS status, COUNT(*) AS total
        FROM clearance_signature cs
        JOIN clearance c ON cs.clearance_id = c.clearance_id
        WHERE c.student_id = :student_id
        AND c.date_requested <= NOW() /* TIME TRAVEL CHECK */
        GROUP BY cs.signed_status
    ";

    $stmt = $this->conn->prepare($sql);
    $stmt->execute([':student_id' => $student_id]);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $summary[$row['status']] = $row['total'];
    }

    return $summary;
}

    public function getRecentClearanceRequests($student_id, $limit = 10) {
        $sql = "SELECT 
                        c.clearance_id,
                        c.date_requested,
                        c.status,
                        cs.signature_id,
                        cs.signer_type,
                        CASE 
                            WHEN cs.signer_type = 'Organization' THEN o.org_name
                            WHEN cs.signer_type = 'Faculty' THEN CONCAT(f.fName, ' ', f.lName)
                        END AS signer_name,
                        cs.signed_status AS signer_status,
                        cs.signed_date
                    FROM clearance c
                    LEFT JOIN clearance_signature cs ON c.clearance_id = cs.clearance_id
                    LEFT JOIN organization o ON cs.signer_type = 'Organization' AND cs.signer_ref_id = o.org_id
                    LEFT JOIN faculty f ON cs.signer_type = 'Faculty' AND cs.signer_ref_id = f.faculty_id
                    WHERE c.student_id = :student_id
                    AND c.date_requested <= NOW() /* TIME TRAVEL CHECK */
                    ORDER BY c.date_requested DESC, cs.sign_order ASC
                    LIMIT :limit";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':student_id', $student_id);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getStudentHistory($student_id, $status = null) {
    $sql = "SELECT 
                c.clearance_id,
                c.date_requested,
                c.date_completed,
                c.status,
                COALESCE(MAX(cs.remarks), c.remarks) AS remarks
            FROM clearance c
            LEFT JOIN clearance_signature cs ON c.clearance_id = cs.clearance_id
            WHERE c.student_id = :student_id
            AND c.date_requested <= NOW() ";
    
    if ($status) {
        $sql .= " AND c.status = :status";
    }

    $sql .= " GROUP BY c.clearance_id, c.date_requested, c.date_completed, c.status, c.remarks ORDER BY c.date_requested DESC"; 

    $stmt = $this->conn->prepare($sql);
    $stmt->bindParam(':student_id', $student_id);
    if ($status) $stmt->bindParam(':status', $status);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

    public function getClearanceStatus($clearance_id) {
        $query = "
            SELECT 
                cs.signature_id,
                cs.clearance_id,
                cs.signer_type,
                cs.signer_ref_id,
                CASE 
                    WHEN cs.signer_type = 'Organization' THEN o.org_name
                    WHEN cs.signer_type = 'Faculty' THEN CONCAT(f.fName, ' ', f.lName)
                END AS signer_name,
                cs.signed_status,
                cs.signed_date,
                cs.uploaded_file,
                cs.remarks,
                cs.sign_order
            FROM clearance_signature cs
            JOIN clearance c ON cs.clearance_id = c.clearance_id /* Join needed to filter by request date */
            LEFT JOIN organization o ON cs.signer_type = 'Organization' AND cs.signer_ref_id = o.org_id
            LEFT JOIN faculty f ON cs.signer_type = 'Faculty' AND cs.signer_ref_id = f.faculty_id
            WHERE cs.clearance_id = :clearance_id
            AND c.date_requested <= NOW() /* TIME TRAVEL CHECK */
            ORDER BY cs.sign_order ASC, cs.signature_id ASC
        ";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([':clearance_id' => $clearance_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function cancelClearance($clearance_id) {
        try {
            $this->conn->beginTransaction();
            $stmt1 = $this->conn->prepare("
                UPDATE clearance 
                SET status = 'Rejected', date_completed = NOW(), remarks = 'Rejected by Admin/System'
                WHERE clearance_id = :clearance_id
            ");
            $stmt1->execute([':clearance_id' => $clearance_id]);

            $stmt2 = $this->conn->prepare("
                UPDATE clearance_signature 
                SET signed_status = 'Rejected' 
                WHERE clearance_id = :clearance_id AND signed_status = 'Pending'
            ");
            $stmt2->execute([':clearance_id' => $clearance_id]);

            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }

    public function getSignaturesByClearance($clearance_id) {
        $sql = "SELECT cs.* FROM clearance_signature cs
                JOIN clearance c ON cs.clearance_id = c.clearance_id
                WHERE cs.clearance_id = :clearance_id
                AND c.date_requested <= NOW()";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':clearance_id', $clearance_id);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createClearanceRequest($student_id, $remarks = null) {
        $sql = "INSERT INTO clearance (student_id, status, remarks) 
                 VALUES (:student_id, 'Pending', :remarks)";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':student_id' => $student_id,
            ':remarks' => $remarks
        ]);
        return $this->conn->lastInsertId();
    }

    public function submitSignatureUpload($clearance_id, $signer_type, $signer_ref_id, $uploaded_file, $sign_order = 1) {
        $checkSql = "SELECT signature_id, signed_status FROM clearance_signature 
                     WHERE clearance_id = :clearance_id 
                     AND signer_type = :signer_type 
                     AND signer_ref_id = :signer_ref_id
                     LIMIT 1";
        $checkStmt = $this->conn->prepare($checkSql);
        $checkStmt->execute([
            ':clearance_id' => $clearance_id,
            ':signer_type' => $signer_type,
            ':signer_ref_id' => $signer_ref_id
        ]);
        $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            if (in_array($existing['signed_status'], ['Rejected', 'Cancelled'])) {
                $sql = "UPDATE clearance_signature
                        SET signed_status = 'Pending',
                            uploaded_file = :uploaded_file,
                            signed_date = NOW(),
                            remarks = NULL,
                            sign_order = :sign_order
                        WHERE signature_id = :signature_id";
                $stmt = $this->conn->prepare($sql);
                return $stmt->execute([
                    ':uploaded_file' => $uploaded_file,
                    ':sign_order' => $sign_order,
                    ':signature_id' => $existing['signature_id']
                ]);
            } else {
                return false;
            }
        } else {
            $sql = "INSERT INTO clearance_signature
                    (clearance_id, signer_type, signer_ref_id, sign_order, signed_status, uploaded_file, signed_date)
                    VALUES (:clearance_id, :signer_type, :signer_ref_id, :sign_order, 'Pending', :uploaded_file, NOW())";
            $stmt = $this->conn->prepare($sql);
            return $stmt->execute([
                ':clearance_id' => $clearance_id,
                ':signer_type' => $signer_type,
                ':signer_ref_id' => $signer_ref_id,
                ':sign_order' => $sign_order,
                ':uploaded_file' => $uploaded_file
            ]);
        }
    }
    
    public function cancelSignature($signature_id) {
        $sql = "UPDATE clearance_signature 
                     SET signed_status = 'Cancelled', signed_date = NOW(), remarks = 'Request cancelled by student'
                     WHERE signature_id = :signature_id AND signed_status = 'Pending'";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([':signature_id' => $signature_id]);
    }

    public function getStudentSignatureHistory($student_id) {
    $sql = "SELECT 
                 c.clearance_id,
                 c.date_requested,
                 cs.signed_status,
                 cs.signed_date,
                 cs.remarks,
                 CASE 
                     WHEN cs.signer_type = 'organization' THEN o.org_name
                     ELSE CONCAT(f.fName, ' ', f.lName) 
                 END AS signer_name,
                 (
                     SELECT COUNT(*) 
                     FROM clearance_signature 
                     WHERE clearance_id = c.clearance_id 
                     AND signed_status = 'Approved'
                 ) = (
                     SELECT COUNT(*) 
                     FROM clearance_signature 
                     WHERE clearance_id = c.clearance_id
                 ) AS is_fully_approved
             FROM clearance c
             JOIN clearance_signature cs ON c.clearance_id = cs.clearance_id
             LEFT JOIN organization o ON cs.signer_type = 'organization' AND cs.signer_ref_id = o.org_id
             LEFT JOIN faculty f ON cs.signer_type = 'Faculty' AND cs.signer_ref_id = f.faculty_id
             WHERE c.student_id = :student_id
             AND cs.signed_status IN ('Approved', 'Rejected', 'Cancelled') 
             AND c.date_requested <= NOW() 
             ORDER BY c.clearance_id DESC, cs.sign_order ASC";

    $stmt = $this->conn->prepare($sql);
    $stmt->execute([':student_id' => $student_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function cancelFullClearance($clearance_id) {
        try {
            $this->conn->beginTransaction();
            $stmt1 = $this->conn->prepare("
                UPDATE clearance 
                SET status = 'Cancelled', date_completed = NOW(), remarks = 'Cancelled by Student'
                WHERE clearance_id = :clearance_id
            ");
            $stmt1->execute([':clearance_id' => $clearance_id]);

            $stmt2 = $this->conn->prepare("
                UPDATE clearance_signature 
                SET signed_status = 'Cancelled', signed_date = NOW(), remarks = 'Full clearance cancelled by student'
                WHERE clearance_id = :clearance_id AND signed_status = 'Pending'
            ");
            $stmt2->execute([':clearance_id' => $clearance_id]);

            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }

    public function getStudentsByAdviserId($faculty_id) {
        $sql = "
            SELECT 
                s.student_id,
                s.school_id,
                CONCAT(s.fName, ' ', s.lName) AS student_name,
                s.course,
                s.year_level,
                s.section_id
            FROM student s
            WHERE s.adviser_id = :faculty_id
            ORDER BY s.course, s.year_level, s.section_id, s.lName
        ";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':faculty_id' => $faculty_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getStudentDetailsBySchoolId($school_id) {
        $sql = "
            SELECT 
                s.student_id,
                s.school_id,
                CONCAT(s.fName, ' ', s.lName) AS student_name,
                s.course,
                s.year_level,
                s.section_id,
                s.adviser_id
            FROM student s
            WHERE s.school_id = :school_id
            LIMIT 1
        ";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':school_id' => $school_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

public function getClearanceStatusByStudentId($student_id) {
    $sql = "SELECT cs.*, c.clearance_id
            FROM clearance_signature cs
            JOIN clearance c ON cs.clearance_id = c.clearance_id
            WHERE c.student_id = :student_id
            AND c.date_requested <= NOW() 
            ORDER BY cs.signature_id ASC";
    $stmt = $this->conn->prepare($sql);
    $stmt->bindParam(':student_id', $student_id);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC); 
}

public function getCertificateData($clearance_id) {
    
    $final_status = $this->getClearanceFinalStatus($clearance_id); 
    
    if ($final_status !== 'CLEARED') {
        return ['clearance_status' => $final_status]; 
    }

    $sql = "
        SELECT 
            s.fName, s.lName, s.mName, s.school_id, 
            s.course, s.year_level, s.section_id,
            c.date_completed,
            
            -- Get Dean's info from faculty table (assumes only one Dean)
            (SELECT CONCAT(f.fName, ' ', f.lName) FROM faculty f WHERE f.position = 'Dean' LIMIT 1) AS dean_name, 
            (SELECT position FROM faculty f WHERE f.position = 'Dean' LIMIT 1) AS dean_title
            
        FROM clearance c
        JOIN student s ON c.student_id = s.student_id
        WHERE c.clearance_id = :cid";

    $stmt = $this->conn->prepare($sql);
    $stmt->bindParam(':cid', $clearance_id);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result) {
        $student_name = $result['lName'] . ', ' . $result['fName'] . ' ' . (isset($result['mName']) && !empty($result['mName']) ? strtoupper($result['mName'][0]) . '.' : '');
        
        return [
            'student_name' => $student_name,
            'school_id' => $result['school_id'],
            'course_section' => strtoupper($result['course']) . ' ' . $result['year_level'] . $result['section_id'],
            'date_issued' => $result['date_completed'] ?? date('Y-m-d'), 
            'dean_name' => $result['dean_name'] ?? 'Dean of CCS (Verify faculty table)', 
            'dean_title' => $result['dean_title'] ?? 'Dean, College of Computer Studies',
            'clearance_status' => 'CLEARED'
        ];
    }
    return ['clearance_status' => 'NOT_FOUND'];
}


}