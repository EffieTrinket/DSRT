<?php
class UserModel {
    private $conn;

    public function __construct($dbConnection) {
        $this->conn = $dbConnection;
    }

    /**
     * SQL: JOIN with user_roles
     * FOR: Fetching a single user with their role name
     */
    public function getUserById($id) {
        $query = "SELECT u.*, r.role_name FROM users u JOIN user_roles r ON u.role_id = r.role_id WHERE u.user_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * SQL: SELECT
     * FOR: Getting pending volunteer applications
     */
    public function getPendingVolunteers() {
        $query = "SELECT u.*, r.role_name FROM users u JOIN user_roles r ON u.role_id = r.role_id WHERE u.role_id = 3 AND u.is_approved = 0 ORDER BY u.user_id DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * SQL: SELECT
     * FOR: Getting approved volunteers
     */
    public function getActiveVolunteers() {
        $query = "SELECT u.*, r.role_name FROM users u JOIN user_roles r ON u.role_id = r.role_id WHERE u.role_id = 3 AND u.is_approved = 1 ORDER BY u.first_name ASC, u.username ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * SQL: SELECT
     * FOR: Getting staff and administrators
     */
    public function getStaffAndAdmins() {
        $query = "SELECT u.*, r.role_name FROM users u JOIN user_roles r ON u.role_id = r.role_id WHERE u.role_id != 3 ORDER BY u.username ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * SQL: SELECT to check if username or email exists
     * FOR: Registration validation
     */
    public function checkUserExists($username, $email) {
        $query = "SELECT username, email FROM users WHERE username = ? OR email = ? LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$username, $email]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * SQL: JOIN with user_roles + alphabetical ORDER BY
     * FOR: Listing all registered users for administration
     */
    public function getAllUsers() {
        $query = "SELECT u.*, r.role_name FROM users u JOIN user_roles r ON u.role_id = r.role_id ORDER BY u.username ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * SQL: Simple SELECT on user_roles
     * FOR: Role selection dropdowns
     */
    public function getAllRoles() {
        return $this->conn->query("SELECT * FROM user_roles")->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * SQL: UPDATE with password hashing
     * FOR: User security and password management
     */
    public function changePassword($id, $newPassword) {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $query = "UPDATE users SET password = ? WHERE user_id = ?";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([$hashedPassword, $id]);
    }

    /**
     * SQL: INSERT with password hashing
     * FOR: Creating new administrative or staff accounts
     */
    public function addUser($username, $email, $password, $role_id, $fname, $mname, $lname, $suffix) {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $query = "INSERT INTO users (username, email, password, role_id, first_name, middle_name, last_name, suffix) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([$username, $email, $hashedPassword, $role_id, $fname, $mname, $lname, $suffix]);
    }

    /**
     * SQL: INSERT with auto-generated temp password
     * FOR: Admin creating new staff accounts (sends email)
     * RETURNS: plain-text temp password on success, false on failure
     */
    public function addStaff($username, $email, $role_id, $fname, $mname, $lname, $suffix) {
        $tempPassword = substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ!@#$"), 0, 10);
        $hashedPassword = password_hash($tempPassword, PASSWORD_DEFAULT);
        $query = "INSERT INTO users (username, email, password, role_id, first_name, middle_name, last_name, suffix, is_approved) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)";
        $stmt = $this->conn->prepare($query);
        if ($stmt->execute([$username, $email, $hashedPassword, $role_id, $fname, $mname, $lname, $suffix])) {
            return $tempPassword;
        }
        return false;
    }

    /**
     * SQL: Single DELETE with self-deletion prevention check
     * FOR: Removing user accounts
     */
    public function deleteUser($id) {
        // Prevent deleting yourself
        if ($id == $_SESSION['user_id']) return false;
        
        $query = "DELETE FROM users WHERE user_id = ?";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([$id]);
    }

    /**
     * SQL: INSERT with is_approved=0 and dummy password
     * FOR: Public volunteer registration
     */
    public function registerVolunteer($username, $email, $fname, $mname, $lname, $notes, $id_front_path = null, $id_back_path = null) {
        // Use a secure random dummy password that can't be guessed until approval
        $dummyPassword = bin2hex(random_bytes(16));
        $hashedPassword = password_hash($dummyPassword, PASSWORD_DEFAULT);
        $role_id = 3; // Volunteer
        $is_approved = 0;
        
        $query = "INSERT INTO users (username, email, password, role_id, first_name, middle_name, last_name, volunteer_notes, is_approved, id_front_path, id_back_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([$username, $email, $hashedPassword, $role_id, $fname, $mname, $lname, $notes, $is_approved, $id_front_path, $id_back_path]);
    }

    /**
     * SQL: UPDATE is_approved=1 and generate temp password
     * FOR: Admin approving pending volunteers
     * RETURNS: The plain text temporary password on success, false on failure
     */
    public function approveUser($id) {
        // Generate an 8-character temporary password
        $tempPassword = substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 8);
        $hashedPassword = password_hash($tempPassword, PASSWORD_DEFAULT);

        $query = "UPDATE users SET is_approved = 1, password = ? WHERE user_id = ?";
        $stmt = $this->conn->prepare($query);
        if ($stmt->execute([$hashedPassword, $id])) {
            return $tempPassword;
        }
        return false;
    }
    /**
     * SQL: UPDATE is_active
     * FOR: Toggling user access
     */
    public function toggleUserStatus($id) {
        // Prevent toggling yourself
        if ($id == $_SESSION['user_id']) return false;

        $query = "UPDATE users SET is_active = IF(is_active = 1, 0, 1) WHERE user_id = ?";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([$id]);
    }
}
?>
