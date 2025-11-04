<?php

require_once "Database.php";

class Admin extends Database {
    
    public function __construct() {
        $this->connect();
    }

    /**
     * Resets all clearance and signature data for a new academic term.
     * WARNING: This permanently deletes all records in the clearance and clearance_signature tables.
     */
    public function resetAllClearanceData() {
        try {
            $this->conn->beginTransaction();
            
            // Delete all records from clearance_signature table
            $stmt1 = $this->conn->prepare("DELETE FROM clearance_signature");
            $stmt1->execute();
            
            // Delete all records from clearance table
            $stmt2 = $this->conn->prepare("DELETE FROM clearance");
            $stmt2->execute();
            
            $this->conn->commit();
            return true;
        } catch (PDOException $e) {
            $this->conn->rollBack();
            error_log("Error resetting clearance data: " . $e->getMessage());
            return false;
        }
    }


    /**
     * Retrieves counts of users and entities for the Admin Dashboard.
     */
    public function getSystemSummary() { 
        $summary = [
            'students' => 0, 
            'faculty' => 0, 
            'organizations' => 0, 
            'departments' => 0
        ];
        
        try {
            // Count from reference tables for accuracy
            $summary['students'] = $this->conn->query("SELECT COUNT(*) FROM student")->fetchColumn();
            $summary['faculty'] = $this->conn->query("SELECT COUNT(*) FROM faculty")->fetchColumn();
            $summary['organizations'] = $this->conn->query("SELECT COUNT(*) FROM organization")->fetchColumn();
            $summary['departments'] = $this->conn->query("SELECT COUNT(*) FROM department")->fetchColumn();
        } catch (PDOException $e) {
            error_log("Error fetching system summary: " . $e->getMessage());
        }

        return $summary;
    }

    // --- Account Management ---
    /**
     * Retrieves all accounts from the system (Admin, Student, Faculty, Org).
     */
    public function getAllAccounts() {
        $sql = "SELECT account_id, email, role, created_at FROM account ORDER BY created_at DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Retrieves all faculty accounts with their details for management.
     */
    public function getAllFacultyAccounts() {
        $sql = "SELECT f.faculty_id, f.fName, f.lName, f.position, f.department_id, f.course_assigned, a.email
                FROM faculty f
                JOIN account a ON f.account_id = a.account_id
                ORDER BY f.lName, f.fName";
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching all faculty: " . $e->getMessage());
            return [];
        }
    }

    /**
     * UPDATES THE FACULTY MEMBER'S POSITION, DEPARTMENT, AND COURSE ASSIGNMENT.
     */
    public function updateFacultyPosition($faculty_id, $new_position, $new_dept_id = null, $new_course_assigned = null) {
        $sql = "UPDATE faculty 
                SET position = :pos, 
                    department_id = :dept_id, 
                    course_assigned = :course
                WHERE faculty_id = :fid";
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':pos', $new_position);
            $stmt->bindParam(':fid', $faculty_id, PDO::PARAM_INT);
            
            // Handle null/empty inputs to set DB column to NULL
            $dept_to_bind = (empty($new_dept_id) || $new_dept_id == 'null') ? null : $new_dept_id;
            $course_to_bind = empty($new_course_assigned) ? null : $new_course_assigned;

            // Use bindValue with PDO::PARAM_NULL for proper NULL insertion
            $stmt->bindValue(':dept_id', $dept_to_bind, $dept_to_bind === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindValue(':course', $course_to_bind, $course_to_bind === null ? PDO::PARAM_NULL : PDO::PARAM_STR);

            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error updating faculty position: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Creates a new faculty account and corresponding faculty record.
     */
    public function createFacultyAccount($email, $password, $fName, $lName, $position, $dept_id) { 
         $hashed_password = password_hash($password, PASSWORD_DEFAULT);
         $this->conn->beginTransaction();
         try {
             // 1. Create account entry
             $sql_acc = "INSERT INTO account (email, password, role) VALUES (:email, :pass, 'faculty')";
             $stmt_acc = $this->conn->prepare($sql_acc);
             $stmt_acc->bindParam(':email', $email);
             $stmt_acc->bindParam(':pass', $hashed_password);
             $stmt_acc->execute();
             $account_id = $this->conn->lastInsertId();

             // 2. Create faculty entry
             $sql_fac = "INSERT INTO faculty (account_id, fName, lName, position, department_id) VALUES (:aid, :fn, :ln, :pos, :dept)";
             $stmt_fac = $this->conn->prepare($sql_fac);
             $stmt_fac->bindParam(':aid', $account_id);
             $stmt_fac->bindParam(':fn', $fName);
             $stmt_fac->bindParam(':ln', $lName);
             $stmt_fac->bindParam(':pos', $position);
             
             // Handle optional department ID
             $dept_to_bind = (empty($dept_id) || $dept_id == 'null') ? null : $dept_id;
             $stmt_fac->bindValue(':dept', $dept_to_bind, $dept_to_bind === null ? PDO::PARAM_NULL : PDO::PARAM_INT);

             $stmt_fac->execute();

             $this->conn->commit();
             return true;
         } catch (PDOException $e) {
             $this->conn->rollBack();
             error_log("Error creating faculty account: " . $e->getMessage());
             return false;
         }
    }
    
    // --- Department Management ---
    public function getAllDepartments() {
        $sql = "SELECT * FROM department ORDER BY dept_name";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function addDepartment($dept_name) {
        $sql = "INSERT INTO department (dept_name) VALUES (:name)";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':name', $dept_name);
        return $stmt->execute();
    }

    // --- Organization Management ---
    public function getAllOrganizations() {
        $sql = "SELECT org_id, org_name, org_type, requirements FROM organization ORDER BY org_name";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function createOrganizationAccount($email, $password, $org_name, $org_type) { 
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $this->conn->beginTransaction();
        try {
            $sql_acc = "INSERT INTO account (email, password, role) VALUES (:email, :pass, 'organization')";
            $stmt_acc = $this->conn->prepare($sql_acc);
            $stmt_acc->bindParam(':email', $email);
            $stmt_acc->bindParam(':pass', $hashed_password);
            $stmt_acc->execute();
            $account_id = $this->conn->lastInsertId();

            $sql_org = "INSERT INTO organization (account_id, org_name, org_type) VALUES (:aid, :oname, :otype)";
            $stmt_org = $this->conn->prepare($sql_org);
            $stmt_org->bindParam(':aid', $account_id);
            $stmt_org->bindParam(':oname', $org_name);
            $stmt_org->bindParam(':otype', $org_type);
            $stmt_org->execute();

            $this->conn->commit();
            return true;
        } catch (PDOException $e) {
            $this->conn->rollBack();
            error_log("Error creating organization account: " . $e->getMessage());
            return false;
        }
    }
}
