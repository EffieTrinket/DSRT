<?php

class DashboardModel {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * SQL: Single COUNT with WHERE status_id = 1
     * FOR: Dashboard active disasters count
     */
    public function getTotalActiveDisasters() {
        return $this->conn->query("SELECT COUNT(*) FROM disasters d JOIN disaster_statuses ds ON d.status_id = ds.status_id WHERE ds.status_name IN ('Ongoing', 'Pending Assessment')")->fetchColumn();
    }

    /**
     * SQL: Single COUNT on residents table
     * FOR: Dashboard total residents count
     */
    public function getTotalResidents() {
        return $this->conn->query("SELECT COUNT(*) FROM residents")->fetchColumn();
    }

        /**
     * SQL: Single SUM on affected_residents column
     * FOR: Dashboard total affected residents count
     */
    public function getTotalAffectedResidents() {
        return $this->conn->query("SELECT SUM(affected_residents) FROM disaster_impact")->fetchColumn();
    }

    /**
     * SQL: Single SUM on stock column
     * FOR: Dashboard total relief goods count
     */
    public function getTotalReliefGoods() {
        return $this->conn->query("SELECT SUM(stock) FROM relief_packages")->fetchColumn() ?: 0;
    }

    /**
     * SQL: Single COUNT on distributions table
     * FOR: Dashboard total distributions count
     */
    public function getTotalDistributions() {
        return $this->conn->query("SELECT COUNT(*) FROM distributions")->fetchColumn();
    }

    /**
     * SQL: 4 LEFT JOINs + GROUP_CONCAT + GROUP BY + CASE Ordering
     * FOR: Dashboard recent disasters overview list
     */
    public function getRecentDisasters($limit = 5) {
        $query = "
            SELECT 
                dt.type_name AS type,
                IFNULL(GROUP_CONCAT(b.barangay_name SEPARATOR ', '), 'N/A') as location,
                d.date,
                ds.status_name AS status
            FROM disasters d
            LEFT JOIN disaster_types dt ON d.disaster_type_id = dt.disaster_type_id
            LEFT JOIN disaster_statuses ds ON d.status_id = ds.status_id
            LEFT JOIN disaster_impact di ON d.disaster_id = di.disaster_id
            LEFT JOIN barangays b ON di.barangay_id = b.barangay_id
            GROUP BY d.disaster_id
            ORDER BY CASE 
                WHEN ds.status_name = 'Ongoing' THEN 0 
                WHEN ds.status_name = 'Pending Assessment' THEN 1 
                ELSE 2 
            END, d.date DESC
            LIMIT " . (int)$limit;
        
        return $this->conn->query($query)->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * SQL: Single SELECT with alphabetical ORDER BY
     * FOR: Dashboard package stocks overview
     */
    public function getPackageStocks() {
        return $this->conn->query("SELECT package_name, stock FROM relief_packages ORDER BY package_name ASC")->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * SQL: Single COUNT on barangays table
     * FOR: Dashboard total barangays count
     */
    public function getTotalBarangays() {
        return $this->conn->query("SELECT COUNT(*) FROM barangays")->fetchColumn();
    }
}
?>
