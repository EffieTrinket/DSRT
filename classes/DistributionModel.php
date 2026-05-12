<?php
class DistributionModel {
    private $conn;

    public function __construct($dbConnection) {
        $this->conn = $dbConnection;
    }

    /**
     * SQL: 3 LEFT JOINs + Dynamic search + ORDER BY
     * FOR: Distribution history list with search functionality
     */
    public function getAllDistributions($search = '') {
        $query = "
            SELECT 
                d.distribution_id,
                r.name AS resident_name,
                rp.package_name,
                u.username AS distributor_name,
                d.quantity,
                d.date_distributed,
                d.created_at
            FROM distributions d
            LEFT JOIN residents r ON d.resident_id = r.resident_id
            LEFT JOIN relief_packages rp ON d.package_id = rp.package_id
            LEFT JOIN users u ON d.user_id = u.user_id
            WHERE 1=1
        ";
        
        $params = [];

        if (!empty($search)) {
            $query .= " AND (r.name LIKE ? OR rp.package_name LIKE ? OR u.username LIKE ?)";
            $searchTerm = "%{$search}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        $query .= " ORDER BY d.date_distributed DESC, d.distribution_id DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    /**
     * SQL: 4 LEFT JOINs
     * FOR: Detailed distribution record by ID
     */
    public function getDistributionById($id) {
        $query = "
            SELECT 
                d.*,
                r.name AS resident_name,
                r.contact AS resident_contact,
                r.address AS resident_address,
                b.barangay_name,
                rp.package_name,
                rp.description AS package_description,
                u.username AS distributor_name
            FROM distributions d
            LEFT JOIN residents r ON d.resident_id = r.resident_id
            LEFT JOIN barangays b ON r.barangay_id = b.barangay_id
            LEFT JOIN relief_packages rp ON d.package_id = rp.package_id
            LEFT JOIN users u ON d.user_id = u.user_id
            WHERE d.distribution_id = ?
        ";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * SQL: Transaction with INSERT (distributions) and UPDATE (relief_packages stock)
     * FOR: Logging a new distribution and automatically updating inventory
     */
    public function addDistribution($resident_id, $package_id, $user_id, $quantity, $date_distributed) {
        try {
            $this->conn->beginTransaction();

            // 1. Insert distribution log
            $query = "INSERT INTO distributions (resident_id, package_id, user_id, quantity, date_distributed) VALUES (?, ?, ?, ?, ?)";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$resident_id, $package_id, $user_id, $quantity, $date_distributed]);

            // 2. Update stock in relief_packages
            $updateStock = "UPDATE relief_packages SET stock = stock - ? WHERE package_id = ?";
            $stmtStock = $this->conn->prepare($updateStock);
            $stmtStock->execute([$quantity, $package_id]);

            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollBack();
            return false;
        }
    }
}
?>
