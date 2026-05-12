<?php
class ResidentModel {
    private $conn;

    public function __construct($dbConnection) {
        $this->conn = $dbConnection;
    }

    /**
     * SQL: LEFT JOIN + Search + Filters + ORDER BY
     * FOR: Residents directory with search and barangay filtering
     */
    public function getAllResidents($search = '', $barangay_id = '') {
        $query = "SELECT r.*, b.barangay_name 
                  FROM residents r
                  LEFT JOIN barangays b ON r.barangay_id = b.barangay_id
                  WHERE 1=1";

        $params = [];

        if (!empty($search)) {
            $query .= " AND (r.name LIKE ? OR r.address LIKE ?)";
            $searchTerm = "%{$search}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        if (!empty($barangay_id)) {
            $query .= " AND r.barangay_id = ?";
            $params[] = $barangay_id;
        }

        $query .= " ORDER BY r.resident_id DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * SQL: Simple SELECT with alphabetical ORDER BY
     * FOR: Barangay list for dropdowns
     */
    public function getAllBarangays() {
        $query = "SELECT * FROM barangays ORDER BY barangay_name ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * SQL: 2 JOINs + DISTINCT + WHERE status_id = 1
     * FOR: Listing barangays currently affected by ongoing disasters
     */
    public function getBarangaysWithOngoingDisasters() {
        $query = "
            SELECT DISTINCT b.* 
            FROM barangays b
            JOIN disaster_impact di ON b.barangay_id = di.barangay_id
            JOIN disasters d ON di.disaster_id = d.disaster_id
            JOIN disaster_statuses ds ON d.status_id = ds.status_id
            WHERE ds.status_name IN ('Ongoing', 'Pending Assessment')
            ORDER BY b.barangay_name ASC
        ";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * SQL: LEFT JOIN by resident_id
     * FOR: Single resident profile details
     */
    public function getResidentById($id) {
        $query = "SELECT r.*, b.barangay_name 
                  FROM residents r
                  LEFT JOIN barangays b ON r.barangay_id = b.barangay_id
                  WHERE r.resident_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * SQL: Single UPDATE on residents table
     * FOR: Modifying resident personal information
     */
    public function updateResident($id, $name, $age, $barangay_id, $address, $contact) {
        $query = "UPDATE residents 
                  SET name = ?, age = ?, barangay_id = ?, address = ?, contact = ? 
                  WHERE resident_id = ?";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([$name, $age, $barangay_id, $address, $contact, $id]);
    }

    /**
     * SQL: 2 JOINs + 1 LEFT JOIN + ORDER BY date
     * FOR: Historical list of disasters a resident was involved in
     */
    public function getResidentDisasters($id) {
        $query = "
            SELECT rd.*, d.date, dt.type_name, cs.condition_name
            FROM resident_disasters rd
            JOIN disasters d ON rd.disaster_id = d.disaster_id
            JOIN disaster_types dt ON d.disaster_type_id = dt.disaster_type_id
            LEFT JOIN condition_statuses cs ON rd.condition_id = cs.condition_id
            WHERE rd.resident_id = ?
            ORDER BY d.date DESC
        ";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * SQL: JOIN with relief_packages + ORDER BY date
     * FOR: History of relief goods received by a resident
     */
    public function getResidentDistributions($id) {
        $query = "
            SELECT dist.*, p.package_name
            FROM distributions dist
            JOIN relief_packages p ON dist.package_id = p.package_id
            WHERE dist.resident_id = ?
            ORDER BY dist.date_distributed DESC
        ";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    /**
     * SQL: Single INSERT into residents table
     * FOR: Registering a new resident in the system
     */
    public function addResident($name, $age, $barangay_id, $address, $contact) {
        $query = "INSERT INTO residents (name, age, barangay_id, address, contact) VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([$name, $age, $barangay_id, $address, $contact]);
    }
}
?>
