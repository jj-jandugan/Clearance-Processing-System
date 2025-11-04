<?php

require_once "Database.php";

class Account extends Database {
    public $email;
    public $password;
    public $role;
    public $ref_id;

    public function __construct() {
        $this->connect(); 
    }

    public function register($fName, $mName = null, $lName = null, $dept_id = null, $position = null, $org_name = null, $course = null, $year_level = null, $section_id = null, $adviser_id = null)
    {
        if (!$this->conn) {
             return "Internal Error: Database connection object is missing."; 
        }
        
        try {
            $this->conn->beginTransaction();

            $checkEmail = $this->conn->prepare("SELECT email FROM account WHERE email = :email");
            $checkEmail->bindParam(":email", $this->email);
            $checkEmail->execute();

            if ($checkEmail->rowCount() > 0) {
                $this->conn->rollBack();
                return "Email address is already registered."; 
            }

            if ($this->role == "student") {
                 $checkRefId = $this->conn->prepare("SELECT school_id FROM student WHERE school_id = :sid");
                 $checkRefId->bindParam(":sid", $this->ref_id);
                 $checkRefId->execute();
                 
                 if ($checkRefId->rowCount() > 0) {
                     $this->conn->rollBack();
                     return "School ID is already registered."; 
                 }
            }


            $hashedPassword = password_hash($this->password, PASSWORD_DEFAULT);

            $sql = "INSERT INTO account (email, password, role) VALUES (:email, :password, :role)";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':email', $this->email);
            $stmt->bindParam(':password', $hashedPassword);
            $stmt->bindParam(':role', $this->role);
            $stmt->execute();

            $account_id = $this->conn->lastInsertId();

            if ($this->role == "student") {

                $sql_ref = "INSERT INTO student 
                            (school_id, account_id, fName, mName, lName, department_id, course, year_level, section_id, adviser_id)
                            VALUES 
                            (:sid, :aid, :fn, :mn, :ln, :dept, :course, :lvl, :sec, :adv)";

                $stmt_ref = $this->conn->prepare($sql_ref);
                $stmt_ref->bindParam(':sid', $this->ref_id);
                $stmt_ref->bindParam(':aid', $account_id);
                $stmt_ref->bindParam(':fn', $fName);
                $stmt_ref->bindParam(':mn', $mName);
                $stmt_ref->bindParam(':ln', $lName);
                $stmt_ref->bindParam(':dept', $dept_id);
                $stmt_ref->bindParam(':course', $course);
                $stmt_ref->bindParam(':lvl', $year_level);
                $stmt_ref->bindParam(':sec', $section_id);

                if ($adviser_id !== null) {
                    $stmt_ref->bindParam(':adv', $adviser_id, PDO::PARAM_INT);
                } else {
                    $stmt_ref->bindValue(':adv', null, PDO::PARAM_NULL);
                }
            }

            elseif ($this->role == "faculty") {
                $sql_ref = "INSERT INTO faculty (account_id, fName, mName, lName, position, department_id, course_assigned)
                             VALUES (:aid, :fn, :mn, :ln, :pos, :dept, :course_assigned)";

                $stmt_ref = $this->conn->prepare($sql_ref);
                $stmt_ref->bindParam(':aid', $account_id);
                $stmt_ref->bindParam(':fn', $fName);
                $stmt_ref->bindParam(':mn', $mName);
                $stmt_ref->bindParam(':ln', $lName);
                $stmt_ref->bindParam(':pos', $position);
                $stmt_ref->bindParam(':dept', $dept_id);
                $stmt_ref->bindParam(':course_assigned', $course); 
            }

            elseif ($this->role == "organization") {
                $sql_ref = "INSERT INTO organization (account_id, org_name)
                             VALUES (:aid, :org)";

                $stmt_ref = $this->conn->prepare($sql_ref);
                $stmt_ref->bindParam(':aid', $account_id);
                $stmt_ref->bindParam(':org', $org_name);
            }
            
            if (!isset($stmt_ref)) {
                $this->conn->rollBack();
                return "Internal Error: Invalid role specified."; 
            }
            $stmt_ref->execute();
            $this->conn->commit();
            return true;

        } catch (PDOException $e) {
            $this->conn->rollBack();
            return "Database Error during INSERT: " . $e->getMessage(); 
        }
    }

    public function login() {
        $sql = "SELECT account_id, password, role, created_at FROM account WHERE email = :email";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':email', $this->email);
        $stmt->execute();

        $account = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($account && password_verify($this->password, $account['password'])) {
            
            $creation_timestamp = strtotime($account['created_at']);
            $current_timestamp = time(); 

            if ($current_timestamp < $creation_timestamp) {
                return 'TIME_TRAVEL_ERROR'; 
            }

            $this->role = $account['role'];
            $this->ref_id = $this->getRefId($account['account_id'], $this->role);
            return $account;
        }

        return false;
    }

    private function getRefId($account_id, $role) {
        if ($role == 'student') {
            $sql = "SELECT student_id AS ref_id FROM student WHERE account_id = :aid";
        } elseif ($role == 'faculty') {
            $sql = "SELECT faculty_id AS ref_id FROM faculty WHERE account_id = :aid";
        } elseif ($role == 'organization') {
            $sql = "SELECT org_id AS ref_id FROM organization WHERE account_id = :aid";
        } else {
            return $account_id;
        }

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':aid', $account_id);
        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['ref_id'] ?? null;
    }
}