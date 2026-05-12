<?php
class ReportModel {
    private $conn;

    public function __construct($dbConnection) {
        $this->conn = $dbConnection;
    }

    /**
     * SQL: CTE + Multiple JOINs (4 tables) + Multiple Aggregations (COUNT, SUM, AVG, MAX)
     * FOR Comprehensive Disaster Summary Report
     */
    public function getDisasterOverview() {
        $query = "
            WITH disaster_summary AS (
                SELECT 
                    d.disaster_id,
                    dt.type_name,
                    d.date,
                    d.end_date,
                    ds.status_name,
                    COUNT(DISTINCT di.barangay_id)   AS zones_count,
                    IFNULL(SUM(di.affected_residents), 0)   AS total_affected,
                    AVG(di.affected_residents)        AS avg_per_barangay,
                    MAX(di.affected_residents)        AS peak_barangay_affected,
                    COUNT(DISTINCT rd.resident_id)   AS registered_victims
                FROM disasters d
                LEFT JOIN disaster_types dt      ON d.disaster_type_id = dt.disaster_type_id
                LEFT JOIN disaster_statuses ds   ON d.status_id = ds.status_id
                LEFT JOIN disaster_impact di     ON d.disaster_id = di.disaster_id
                LEFT JOIN resident_disasters rd  ON d.disaster_id = rd.disaster_id
                GROUP BY d.disaster_id, dt.type_name, d.date, d.end_date, ds.status_name
            )
            SELECT * FROM disaster_summary
            ORDER BY 
                CASE 
                    WHEN status_name = 'Ongoing' THEN 0 
                    WHEN status_name = 'Pending Assessment' THEN 1 
                    ELSE 2 
                END,
                date DESC
        ";
        return $this->conn->query($query)->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * SQL: Subquery #1 + JOINs (3 tables) + Aggregations (SUM, COUNT)
     * FOR: Package Inventory Audit with total_distributed from subquery
     */
    public function getPackageSummary() {
        $query = "
            SELECT 
                rp.package_id,
                rp.package_name,
                rp.stock AS current_stock,
                IFNULL(dist_agg.total_distributed, 0) AS total_distributed,
                IFNULL(dist_agg.distribution_count, 0) AS distribution_count,
                IFNULL(dist_agg.avg_qty_per_distribution, 0) AS avg_qty_per_distribution
            FROM relief_packages rp
            LEFT JOIN (
                -- Subquery #1: Aggregate distribution data per package
                SELECT 
                    package_id,
                    SUM(quantity)   AS total_distributed,
                    COUNT(*)        AS distribution_count,
                    AVG(quantity)   AS avg_qty_per_distribution
                FROM distributions
                GROUP BY package_id
            ) AS dist_agg ON rp.package_id = dist_agg.package_id
            ORDER BY total_distributed DESC
        ";
        return $this->conn->query($query)->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * SQL: 3 JOINs + Aggregations (COUNT, SUM)
     * FOR: Barangay Impact Activity
     */
    public function getBarangayActivity() {
        $query = "
            SELECT 
                b.barangay_name,
                COUNT(DISTINCT di.disaster_id)          AS disaster_count,
                IFNULL(SUM(di.affected_residents), 0)   AS total_affected,
                IFNULL(MAX(di.affected_residents), 0)   AS worst_event_affected,
                COUNT(DISTINCT r.resident_id)            AS registered_residents
            FROM barangays b
            LEFT JOIN disaster_impact di ON b.barangay_id = di.barangay_id
            LEFT JOIN residents r        ON b.barangay_id = r.barangay_id
            GROUP BY b.barangay_id, b.barangay_name
            HAVING disaster_count > 0
            ORDER BY total_affected DESC, disaster_count DESC
            LIMIT 10
        ";
        return $this->conn->query($query)->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * SQL: Subquery #2 + Subquery #3 + 3 JOINs + Aggregations
     * FOR: Distribution Activity per Staff Member
     */
    public function getStaffDistributionReport() {
        $query = "
            SELECT 
                u.username,
                CONCAT(
                    IFNULL(u.first_name, ''),
                    IF(u.middle_name IS NOT NULL AND u.middle_name != '', CONCAT(' ', SUBSTRING(u.middle_name,1,1), '.'), ''),
                    IFNULL(CONCAT(' ', u.last_name), '')
                ) AS full_name,
                r.role_name,
                IFNULL(staff_dist.total_items_given, 0) AS total_items_given,
                IFNULL(staff_dist.total_distributions, 0) AS total_distributions,
                IFNULL(staff_dist.unique_residents_served, 0) AS unique_residents_served,
                -- Subquery #2: get max single distribution for this staff member
                (
                    SELECT MAX(d2.quantity) 
                    FROM distributions d2 
                    WHERE d2.user_id = u.user_id
                ) AS max_single_distribution
            FROM users u
            JOIN user_roles r ON u.role_id = r.role_id
            LEFT JOIN (
                -- Subquery #3: aggregate all distributions per staff
                SELECT 
                    user_id,
                    SUM(quantity)             AS total_items_given,
                    COUNT(*)                  AS total_distributions,
                    COUNT(DISTINCT resident_id) AS unique_residents_served
                FROM distributions
                GROUP BY user_id
            ) AS staff_dist ON u.user_id = staff_dist.user_id
            ORDER BY total_items_given DESC
        ";
        return $this->conn->query($query)->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * SQL: Aggregations (COUNT, SUM, AVG, MAX, MIN)
     * FOR: Summary stat cards
     */
    public function getStats() {
        $stats = [];

        // Most Affected Barangay
        $queryBrgy = "
            SELECT b.barangay_name 
            FROM barangays b 
            LEFT JOIN disaster_impact di ON b.barangay_id = di.barangay_id 
            GROUP BY b.barangay_id 
            ORDER BY SUM(di.affected_residents) DESC 
            LIMIT 1
        ";
        $stats['top_barangay'] = $this->conn->query($queryBrgy)->fetchColumn() ?: 'N/A';

        // Total Distributed Packages
        $stats['total_distributed'] = $this->conn->query("SELECT SUM(quantity) FROM distributions")->fetchColumn() ?: 0;

        // Active Disasters
        $stats['active_disasters'] = $this->conn->query("SELECT COUNT(*) FROM disasters d JOIN disaster_statuses ds ON d.status_id = ds.status_id WHERE ds.status_name IN ('Ongoing', 'Pending Assessment')")->fetchColumn() ?: 0;

        // Average affected residents per disaster event
        $stats['avg_affected'] = round(
            $this->conn->query("SELECT AVG(total) FROM (SELECT SUM(affected_residents) AS total FROM disaster_impact GROUP BY disaster_id) AS sub")->fetchColumn() ?: 0
        );

        // Total registered victims across all disasters
        $stats['total_victims'] = $this->conn->query("SELECT COUNT(DISTINCT resident_id) FROM resident_disasters")->fetchColumn() ?: 0;

        return $stats;
    }
}
?>
