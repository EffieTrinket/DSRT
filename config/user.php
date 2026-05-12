<?php
require_once 'database.php';

class User extends Database {
    protected $conn;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->connect();
    }

    public function login($email, $password) {
        $sql = "SELECT u.*, r.role_name FROM users u JOIN user_roles r ON u.role_id = r.role_id WHERE u.email = :email LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':email', $email);
        $stmt->execute();

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Support both hashed and legacy plain text passwords
            if (password_verify($password, $user['password']) || $password === $user['password']) {
                if (isset($user['is_active']) && $user['is_active'] == 0) {
                    return 'deactivated'; // Signal that account is deactivated
                }
                if (isset($user['is_approved']) && $user['is_approved'] == 0) {
                    return 'pending'; // Signal that account is pending
                }
                return $user;
            }
        }

        return false;
    }
}
?>