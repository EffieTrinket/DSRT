<?php
// classes/DisasterModel.php

class DisasterModel {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * SQL: 4 LEFT JOINs + GROUP_CONCAT + SUM + GROUP BY + Dynamic filtering
     * FOR: Main disasters list with search and status filtering
     */
    public function getAllDisasters($search = '', $status_id = '') {
        $params = [];
        $whereFilters = [];
        $query = "
            SELECT 
                d.disaster_id,
                dt.type_name AS type,
                IFNULL(GROUP_CONCAT(DISTINCT b.barangay_name SEPARATOR ', '), 'N/A') as location,
                d.date,
                d.end_date,
                ds.status_name AS status,
                p.package_name,
                IFNULL(SUM(di.affected_residents), 0) as total_affected
            FROM disasters d
            LEFT JOIN disaster_types dt ON d.disaster_type_id = dt.disaster_type_id
            LEFT JOIN disaster_statuses ds ON d.status_id = ds.status_id
            LEFT JOIN disaster_impact di ON d.disaster_id = di.disaster_id
            LEFT JOIN barangays b ON di.barangay_id = b.barangay_id
            LEFT JOIN relief_packages p ON d.package_id = p.package_id
        ";

        if (!empty($search)) {
            $whereFilters[] = "(dt.type_name LIKE ? OR b.barangay_name LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        if (!empty($status_id)) {
            $whereFilters[] = "d.status_id = ?";
            $params[] = $status_id;
        }

        if (!empty($whereFilters)) {
            $query .= " WHERE " . implode(" AND ", $whereFilters);
        }

        $query .= " GROUP BY d.disaster_id ORDER BY CASE 
            WHEN ds.status_name = 'Ongoing' THEN 0 
            WHEN ds.status_name = 'Pending Assessment' THEN 1 
            ELSE 2 
        END, d.disaster_id DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * SQL: 3 LEFT JOINs + SUM + GROUP BY
     * FOR: Single disaster details by ID
     */
    public function getDisasterById($id) {
        $query = "
            SELECT 
                d.disaster_id,
                dt.type_name AS type,
                d.disaster_type_id,
                d.date,
                d.end_date,
                ds.status_name AS status,
                d.status_id,
                d.package_id,
                p.package_name,
                IFNULL(SUM(di.affected_residents), 0) as total_affected
            FROM disasters d
            LEFT JOIN disaster_types dt ON d.disaster_type_id = dt.disaster_type_id
            LEFT JOIN disaster_statuses ds ON d.status_id = ds.status_id
            LEFT JOIN disaster_impact di ON d.disaster_id = di.disaster_id
            LEFT JOIN relief_packages p ON d.package_id = p.package_id
            WHERE d.disaster_id = ?
            GROUP BY d.disaster_id
        ";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * SQL: JOIN with barangays table
     * FOR: Disaster impact breakdown by barangay
     */
    public function getDisasterImpacts($id) {
        $query = "
            SELECT 
                b.barangay_id,
                b.barangay_name,
                di.affected_residents
            FROM disaster_impact di
            JOIN barangays b ON di.barangay_id = b.barangay_id
            WHERE di.disaster_id = ?
            ORDER BY di.affected_residents ASC
        ";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * SQL: 3 JOINs + ORDER BY name
     * FOR: List of residents affected by a specific disaster
     */
    public function getDisasterResidents($id) {
        $query = "
            SELECT 
                r.resident_id,
                r.name,
                r.age,
                r.contact,
                b.barangay_name,
                cs.condition_name
            FROM resident_disasters rd
            JOIN residents r ON rd.resident_id = r.resident_id
            LEFT JOIN barangays b ON r.barangay_id = b.barangay_id
            LEFT JOIN condition_statuses cs ON rd.condition_id = cs.condition_id
            WHERE rd.disaster_id = ?
            ORDER BY r.name ASC
        ";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * SQL: Simple SELECT on disaster_statuses
     * FOR: Status selection dropdowns
     */
    public function getAllStatuses() {
        return $this->conn->query("SELECT * FROM disaster_statuses")->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * SQL: Simple SELECT on disaster_types
     * FOR: Disaster type selection dropdowns
     */
    public function getAllDisasterTypes() {
        return $this->conn->query("SELECT * FROM disaster_types")->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * SQL: Simple SELECT on barangays table
     * FOR: Barangay selection dropdowns
     */
    public function getAllBarangays() {
        return $this->conn->query("SELECT * FROM barangays ORDER BY barangay_name ASC")->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * SQL: Single UPDATE on disasters table
     * FOR: Updating disaster record metadata
     */
    public function updateDisaster($id, $type_id, $date, $end_date, $status_id, $package_id = null) {
        // Fetch current record to check transition
        $current = $this->getDisasterById($id);
        
        // If status is changed to Ongoing (1) and it was previously Pending Assessment (3), 
        // or if it's Ongoing and date is currently empty/null, set date to today.
        if ($status_id == 1 && ($current['status_id'] == 3 || empty($current['date']))) {
            $date = date('Y-m-d');
        } 
        // If status is Pending Assessment (3), date must be NULL
        elseif ($status_id == 3) {
            $date = null;
        }

        // If status is changed to Resolved (2) and it wasn't Resolved before, 
        // or if it's Resolved and end_date is empty, set end_date to today.
        if ($status_id == 2 && ($current['status_id'] != 2 || empty($current['end_date']))) {
            $end_date = date('Y-m-d');
        }
        // If status is moved back to Ongoing (1) from Resolved (2), clear the end_date
        elseif ($status_id == 1 && $current['status_id'] == 2) {
            $end_date = null;
        }

        $query = "UPDATE disasters SET disaster_type_id = ?, date = ?, end_date = ?, status_id = ?, package_id = ? WHERE disaster_id = ?";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([$type_id, $date, $end_date, $status_id, $package_id, $id]);
    }

    /**
     * SQL: Single UPDATE on disasters table
     * FOR: Updating only the assigned package
     */
    public function assignDisasterPackage($id, $package_id) {
        // If package_id is empty string, set it to null
        $pkg = empty($package_id) ? null : $package_id;
        $query = "UPDATE disasters SET package_id = ? WHERE disaster_id = ?";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([$pkg, $id]);
    }
    /**
     * SQL: Single INSERT into disasters table
     * FOR: Creating a new disaster entry
     */
    public function addDisaster($type_id, $date, $status_id, $end_date = null, $package_id = null) {
        // If status is Pending Assessment (3), set date to NULL
        if ($status_id == 3) {
            $date = null;
        }
        
        $query = "INSERT INTO disasters (disaster_type_id, date, status_id, end_date, package_id) VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->conn->prepare($query);
        if ($stmt->execute([$type_id, $date, $status_id, $end_date, $package_id])) {
            return $this->conn->lastInsertId();
        }
        return false;
    }

    /**
     * SQL: Single INSERT into disaster_impact table
     * FOR: Recording impact data for a specific barangay
     */
    public function addDisasterImpact($disaster_id, $barangay_id, $affected_residents = 0) {
        $query = "INSERT INTO disaster_impact (disaster_id, barangay_id, affected_residents) VALUES (?, ?, ?)";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([$disaster_id, $barangay_id, $affected_residents]);
    }

    /**
     * SQL: Simple SELECT on condition_statuses
     * FOR: Resident health/safety condition dropdowns
     */
    public function getAllConditions() {
        return $this->conn->query("SELECT * FROM condition_statuses")->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * SQL: Single INSERT into resident_disasters table
     * FOR: Linking a resident to a specific disaster event
     */
    public function addResidentDisaster($resident_id, $disaster_id, $condition_id) {
        // Deduplication check
        $checkQuery = "SELECT 1 FROM resident_disasters WHERE resident_id = ? AND disaster_id = ?";
        $checkStmt = $this->conn->prepare($checkQuery);
        $checkStmt->execute([$resident_id, $disaster_id]);
        if ($checkStmt->fetch()) {
            return false; // Resident already added
        }

        $query = "INSERT INTO resident_disasters (resident_id, disaster_id, condition_id) VALUES (?, ?, ?)";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([$resident_id, $disaster_id, $condition_id]);
    }

    /**
     * SQL: Single DELETE from resident_disasters table
     * FOR: Removing a resident link from a disaster event
     */
    public function removeResidentDisaster($resident_id, $disaster_id) {
        $query = "DELETE FROM resident_disasters WHERE resident_id = ? AND disaster_id = ?";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([$resident_id, $disaster_id]);
    }

    /**
     * SQL: Transaction with multiple DELETEs
     * FOR: Fully removing a disaster and all its related impact/resident data
     */
    public function deleteDisaster($id) {
        try {
            $this->conn->beginTransaction();

            // Delete child records first to avoid foreign key constraints
            $stmt1 = $this->conn->prepare("DELETE FROM disaster_impact WHERE disaster_id = ?");
            $stmt1->execute([$id]);

            $stmt2 = $this->conn->prepare("DELETE FROM resident_disasters WHERE disaster_id = ?");
            $stmt2->execute([$id]);

            // Delete the parent record
            $stmt3 = $this->conn->prepare("DELETE FROM disasters WHERE disaster_id = ?");
            $stmt3->execute([$id]);

            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollBack();
            return false;
        }
    }
    /**
     * SQL: Multi-JOIN with GROUP BY
     * FOR: Getting residents who have received the assigned package for this disaster
     */
    public function getDistributedResidents($disaster_id) {
        $query = "
            SELECT 
                r.resident_id,
                r.name,
                b.barangay_name,
                SUM(d.quantity) as total_received,
                MAX(d.date_distributed) as latest_distribution
            FROM residents r
            JOIN resident_disasters rd 
                ON r.resident_id = rd.resident_id AND rd.disaster_id = :disaster_id
            JOIN distributions d ON r.resident_id = d.resident_id
            LEFT JOIN barangays b ON r.barangay_id = b.barangay_id
            GROUP BY r.resident_id, r.name, b.barangay_name
        ";
        $stmt = $this->conn->prepare($query);
        $stmt->execute(['disaster_id' => $disaster_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
