<?php
class PackageModel {
    private $conn;

    public function __construct($dbConnection) {
        $this->conn = $dbConnection;
    }

    /**
     * SQL: Simple SELECT with dynamic search and ORDER BY
     * FOR: Relief packages inventory list
     */
    public function getAllPackages($search = '') {
        $query = "SELECT * FROM relief_packages WHERE 1=1";
        $params = [];

        if (!empty($search)) {
            $query .= " AND (package_name LIKE ? OR description LIKE ?)";
            $searchTerm = "%{$search}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        $query .= " ORDER BY package_name ASC";

        $stmt = $this->conn->prepare($query);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * SQL: Simple SELECT by package_id
     * FOR: Single package details
     */
    public function getPackageById($id) {
        $query = "SELECT * FROM relief_packages WHERE package_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * SQL: Single INSERT into relief_packages
     * FOR: Adding a new type of relief package
     */
    public function addPackage($name, $description, $initialStock) {
        $query = "INSERT INTO relief_packages (package_name, description, stock) VALUES (?, ?, ?)";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([$name, $description, $initialStock]);
    }

    /**
     * SQL: Single UPDATE to modify stock levels
     * FOR: Manual stock adjustments
     */
    public function updateStock($id, $amount) {
        $query = "UPDATE relief_packages SET stock = stock + ? WHERE package_id = ?";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([$amount, $id]);
    }

    /**
     * SQL: 2 JOINs + ORDER BY
     * FOR: History of distributions for a specific package
     */
    public function getPackageDistributions($id) {
        $query = "
            SELECT d.*, r.name AS resident_name, u.username AS distributor_name
            FROM distributions d
            JOIN residents r ON d.resident_id = r.resident_id
            JOIN users u ON d.user_id = u.user_id
            WHERE d.package_id = ?
            ORDER BY d.date_distributed DESC
        ";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
