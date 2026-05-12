<?php
session_start();

if (!isset($_SESSION['user']) || !isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

if (isset($_SESSION['role_name']) && $_SESSION['role_name'] === 'volunteer') {
    header('Location: disasters.php');
    exit;
}

require_once '../config/database.php';
require_once '../classes/ReportModel.php';

$db = new Database();
$conn = $db->connect();
$reportModel = new ReportModel($conn);

$stats      = $reportModel->getStats();
$disasters  = $reportModel->getDisasterOverview();
$packages   = $reportModel->getPackageSummary();
$barangays  = $reportModel->getBarangayActivity();
$staffReport = $reportModel->getStaffDistributionReport();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports &amp; Analytics - Disaster Relief Tracker</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="../style/dashboard.css">
    <link rel="stylesheet" href="../style/tables.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../style/reports.css">
</head>
<body>

<?php $activePage = 'reports'; $sidebarBase = ''; include '../includes/sidebar.php'; ?>

<div class="main">
    <div class="header" style="display: flex; justify-content: space-between; align-items: start;">
        <div>
            <h1>Reports &amp; Analytics</h1>
            <p>Summarized data insights and operational reports.</p>
        </div>
        <button onclick="document.getElementById('exportModal').style.display='block'" class="btn-print" style="padding: 10px 20px; background: #2c3e50; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: bold; display: flex; align-items: center; gap: 8px; transition: all 0.2s ease;" onmouseover="this.style.transform='translateY(-2px)';" onmouseout="this.style.transform='none';">
            <i class="fa-solid fa-file-export"></i> Export Report
        </button>
    </div>

    <div class="print-only">
        <h1 style="color: #0b5ed7;">DSRT - Official Operational Report</h1>
        <p>Generated on: <?php echo date('F d, Y - h:i A'); ?></p>
        <hr>
    </div>

    <!-- Summary Cards: COUNT, SUM, AVG aggregations -->
    <div class="cards">
        <div class="card" style="border-left-color: #e74c3c;">
            <h3>Top Impacted Barangay</h3>
            <p style="font-size: 18px;"><?php echo htmlspecialchars($stats['top_barangay']); ?></p>
        </div>
        <div class="card" style="border-left-color: #27ae60;">
            <h3>Total Goods Distributed</h3>
            <p><?php echo number_format($stats['total_distributed']); ?></p>
        </div>
        <div class="card" style="border-left-color: #f39c12;">
            <h3>Active Disasters</h3>
            <p><?php echo $stats['active_disasters']; ?></p>
        </div>
        <div class="card" style="border-left-color: #3498db;">
            <h3>Avg. Affected / Disaster</h3>
            <p><?php echo number_format($stats['avg_affected']); ?></p>
        </div>
        <div class="card" style="border-left-color: #8e44ad;">
            <h3>Total Registered Victims</h3>
            <p><?php echo number_format($stats['total_victims']); ?></p>
        </div>
    </div>

    <div style="display: flex; gap: 20px; flex-wrap: wrap;">

        <!-- Disaster Impact Summary: CTE + 4 JOINs + COUNT, SUM, AVG, MAX -->
        <div class="report-section" style="flex: 1; min-width: 500px;">
            <h3>
                <i class="fa-solid fa-triangle-exclamation"></i> Disaster Impact Summary
            </h3>
            <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Date</th>
                        <th>Zones</th>
                        <th>Total Affected</th>
                        <th>Avg/Zone</th>
                        <th>Peak Zone</th>
                        <th>Registered Victims</th>
                        <th>Relief Status</th>
                    </tr>
                </thead>
                <tbody id="disasterTableBody">
                <?php foreach ($disasters as $d): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($d['type_name']); ?></strong></td>
                    <td style="white-space: nowrap;">
                        <?php 
                            if ($d['status_name'] == 'Pending Assessment' || empty($d['date'])) {
                                echo '<span style="color: #94a3b8; font-style: italic;">Pending</span>';
                            } else {
                                echo htmlspecialchars($d['date']); 
                            }
                        ?>
                    </td>
                    <td><?php echo $d['zones_count']; ?> Brgy</td>
                    <td><strong><?php echo number_format($d['total_affected']); ?></strong></td>
                    <td><?php echo number_format($d['avg_per_barangay'], 1); ?></td>
                    <td><?php echo number_format($d['peak_barangay_affected']); ?></td>
                    <td><?php echo $d['registered_victims']; ?></td>
                    <td>
                        <?php 
                            $pillClass = 'pill-green';
                            $pillIcon = 'fa-circle-check';
                            if ($d['status_name'] == 'Ongoing') {
                                $pillClass = 'pill-orange';
                                $pillIcon = 'fa-spinner';
                            } elseif ($d['status_name'] == 'Pending Assessment') {
                                $pillClass = 'pill-blue';
                                $pillIcon = 'fa-magnifying-glass';
                            }
                        ?>
                        <span class="pill <?php echo $pillClass; ?>">
                            <i class="fa-solid <?php echo $pillIcon; ?>"></i>
                            <?php echo htmlspecialchars($d['status_name']); ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <div id="disasterTablePagination" class="pagination-controls"></div>
        </div>
    </div>

    <div style="display: flex; gap: 20px; flex-wrap: wrap; margin-top: 20px;">

        <!-- Package Inventory Audit: Subquery #1 + SUM, COUNT, AVG -->
        <div class="report-section" style="flex: 1; min-width: 420px;">
            <h3>
                <i class="fa-solid fa-boxes-stacked"></i> Relief Inventory Audit
            </h3>
            <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Package</th>
                        <th>Stock Left</th>
                        <th>Distributed</th>
                        <th>Avg Qty/Dist.</th>
                        <th>No. of Releases</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody id="inventoryTableBody">
                <?php foreach ($packages as $p): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($p['package_name']); ?></strong></td>
                    <td><?php echo number_format($p['current_stock']); ?></td>
                    <td><?php echo number_format($p['total_distributed']); ?></td>
                    <td><?php echo number_format($p['avg_qty_per_distribution'], 1); ?></td>
                    <td><?php echo $p['distribution_count']; ?></td>
                    <td>
                        <?php
                            if ($p['current_stock'] <= 10)      echo '<span class="stock-critical">Critical</span>';
                            elseif ($p['current_stock'] <= 50)  echo '<span class="stock-low">Low</span>';
                            else                                echo '<span class="stock-ok">Sufficient</span>';
                        ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <div id="inventoryTablePagination" class="pagination-controls"></div>
        </div>

        <!-- Barangay Activity: 3 JOINs + COUNT, SUM, MAX -->
        <div class="report-section" style="flex: 1; min-width: 400px;">
            <h3>
                <i class="fa-solid fa-map-location-dot"></i> Barangay Impact Ranking
            </h3>
            <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Barangay</th>
                        <th>Disaster Events</th>
                        <th>Total Affected</th>
                        <th>Worst Event</th>
                        <th>Registered Residents</th>
                    </tr>
                </thead>
                <tbody id="barangayTableBody">
                <?php foreach ($barangays as $b): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($b['barangay_name']); ?></strong></td>
                    <td><?php echo $b['disaster_count']; ?> events</td>
                    <td><strong><?php echo number_format($b['total_affected']); ?></strong></td>
                    <td><?php echo number_format($b['worst_event_affected']); ?></td>
                    <td><?php echo $b['registered_residents']; ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <div id="barangayTablePagination" class="pagination-controls"></div>
        </div>
    </div>

    <!-- Staff Distribution Report: Subquery #2 + #3 + 2 JOINs + SUM, COUNT, AVG, MAX -->
    <div class="report-section" style="margin-top: 20px;">
        <h3>
            <i class="fa-solid fa-user-tie"></i> Staff Distribution Performance
        </h3>
        <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Staff Member</th>
                    <th>Username</th>
                    <th>Role</th>
                    <th>Total Items Given</th>
                    <th>No. of Releases</th>
                    <th>Residents Served</th>
                    <th>Largest Single Distribution</th>
                </tr>
            </thead>
            <tbody id="staffTableBody">
            <?php foreach ($staffReport as $s): ?>
            <tr>
                <td><strong><?php echo htmlspecialchars(trim($s['full_name']) ?: $s['username']); ?></strong></td>
                <td><span style="color: #94a3b8; font-size: 12px;">@<?php echo htmlspecialchars($s['username']); ?></span></td>
                <td>
                    <span class="role-badge <?php echo ($s['role_name'] == 'admin') ? 'role-admin' : 'role-staff'; ?>">
                        <?php echo htmlspecialchars($s['role_name']); ?>
                    </span>
                </td>
                <td><?php echo number_format($s['total_items_given']); ?></td>
                <td><?php echo $s['total_distributions']; ?></td>
                <td><?php echo $s['unique_residents_served']; ?></td>
                <td><?php echo $s['max_single_distribution'] ? number_format($s['max_single_distribution']) : '—'; ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <div id="staffTablePagination" class="pagination-controls"></div>
    </div>

</div>

    <!-- Export Modal -->
    <div id="exportModal" class="modal-overlay">
        <div class="modal-content" style="width:400px;">
            <h2 style="margin-top:0; color: #0f172a; border-bottom: 2px solid #f1f5f9; padding-bottom: 15px; font-size: 18px;">Export Report Data</h2>
            <form id="exportForm" method="GET" action="" target="_blank">
                <div style="margin-bottom: 15px;">
                    <label style="font-size: 13px; font-weight: 700; color: #475569; display: block; margin-bottom: 6px;">Select Data to Export:</label>
                    <select name="report_type" required style="width: 100%; padding: 10px 14px; border-radius: 10px; border: 1.5px solid #e2e8f0; font-family: 'Plus Jakarta Sans', sans-serif; font-size: 13.5px;">
                        <option value="resident_list">Resident List</option>
                        <option value="disaster_list">Disaster List</option>
                        <option value="inventory_audit">Inventory Audit</option>
                        <option value="staff_performance">Staff Performance</option>
                        <option value="barangay_impact">Barangay Impact</option>
                    </select>
                </div>
                
                <div style="margin-bottom: 25px;">
                    <label style="font-size: 13px; font-weight: 700; color: #475569; display: block; margin-bottom: 6px;">Output Format:</label>
                    <select id="exportFormat" required style="width: 100%; padding: 10px 14px; border-radius: 10px; border: 1.5px solid #e2e8f0; font-family: 'Plus Jakarta Sans', sans-serif; font-size: 13.5px;" onchange="updateExportAction()">
                        <option value="pdf">PDF Document (.pdf)</option>
                        <option value="excel">Excel Format (.csv)</option>
                    </select>
                </div>
                
                <div style="display: flex; justify-content: flex-end; gap: 10px; border-top: 1.5px solid #f1f5f9; padding-top: 18px;">
                    <button type="button" onclick="document.getElementById('exportModal').style.display='none'" style="padding: 10px 18px; background: #f1f5f9; color: #475569; border: 1.5px solid #e2e8f0; border-radius: 10px; cursor: pointer; font-weight: 700; font-family: 'Plus Jakarta Sans', sans-serif;">Cancel</button>
                    <button type="submit" onclick="setTimeout(() => { document.getElementById('exportModal').style.display='none'; }, 500);" style="padding: 10px 22px; background: linear-gradient(135deg, #10b981, #059669); color: white; border: none; border-radius: 10px; cursor: pointer; font-weight: 700; font-family: 'Plus Jakarta Sans', sans-serif; box-shadow: 0 2px 8px rgba(16, 185, 129, 0.25);"><i class="fa-solid fa-download"></i> Download File</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../style/paginate.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        paginateTable('disasterTableBody', 'disasterTablePagination', 10);
        paginateTable('inventoryTableBody', 'inventoryTablePagination', 10);
        paginateTable('barangayTableBody', 'barangayTablePagination', 10);
        paginateTable('staffTableBody', 'staffTablePagination', 10);
    });
    </script>

    <script>
    function updateExportAction() {
        const format = document.getElementById('exportFormat').value;
        const form = document.getElementById('exportForm');
        if (format === 'pdf') {
            form.action = '../actions/export_pdf.php';
        } else {
            form.action = '../actions/export_excel.php';
        }
    }
    // Set initial action
    updateExportAction();
    </script>

</body>
</html>
