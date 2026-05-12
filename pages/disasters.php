<?php
session_start();

// Protect page
if (!isset($_SESSION['user']) || !isset($_SESSION['user_id'])) {
    header('Location: logout.php');
    exit;
}

// Database connection
require_once '../config/database.php';
require_once '../classes/DisasterModel.php';
require_once '../classes/PackageModel.php';

$db = new Database();
$conn = $db->connect();
$disasterModel = new DisasterModel($conn);
$packageModel = new PackageModel($conn);

$search = $_GET['search'] ?? '';
$status_id = $_GET['status_id'] ?? '';

$disasters = $disasterModel->getAllDisasters($search, $status_id);
$statuses = $disasterModel->getAllStatuses();
$disasterTypes = $disasterModel->getAllDisasterTypes();
$allBarangays = $disasterModel->getAllBarangays();
$allPackages = $packageModel->getAllPackages();

// Handle adding new disaster
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_disaster'])) {
    if ($_SESSION['role_id'] == 1) {
        $type_id = $_POST['disaster_type_id'];
        $date = $_POST['date'];
        $status_id_post = $_POST['status_id'];
        $package_id = !empty($_POST['package_id']) ? $_POST['package_id'] : null;
        $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
        
        $barangay_id_post = $_POST['barangay_id'] ?? null;
        $affected = !empty($_POST['affected_residents']) ? $_POST['affected_residents'] : 0;

        if ($barangay_id_post) {
            $new_id = $disasterModel->addDisaster($type_id, $date, $status_id_post, $end_date, $package_id);
            if ($new_id) {
                // Record initial impact zone
                $disasterModel->addDisasterImpact($new_id, $barangay_id_post, $affected);
                $success_msg = "Disaster event and location recorded successfully!";
                $disasters = $disasterModel->getAllDisasters($search, $status_id);
            } else {
                $error_msg = "Failed to record disaster event.";
            }
        } else {
            $error_msg = "Error: Please select a location for the disaster.";
        }
    }
}

// Handle deleting disaster (Admin only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_disaster_id'])) {
    if ($_SESSION['role_id'] == 1) {
        $delete_id = $_POST['delete_disaster_id'];
        if ($disasterModel->deleteDisaster($delete_id)) {
            $success_msg = "Disaster record and its associated data deleted successfully.";
        } else {
            $error_msg = "Failed to delete disaster record.";
        }
        $disasters = $disasterModel->getAllDisasters($search, $status_id);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Disaster Records - Disaster Relief Tracker</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="../style/dashboard.css">
    <link rel="stylesheet" href="../style/tables.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>

   <?php $activePage = 'disasters'; $sidebarBase = ''; include '../includes/sidebar.php'; ?>

    <div class="main">
        <div class="header">
            <h1>Disaster Records</h1>
            <p>Manage and track all recorded disaster events, impact zones, and statuses.</p>
        </div>

        <div class="table-container" style="margin-top: 2rem;">
            
            <form method="GET" action="disasters.php" class="filter-bar">
                <input type="text" name="search" placeholder="Search disaster types or barangays..." value="<?php echo htmlspecialchars($search); ?>">
                
                <select name="status_id">
                    <option value="">All Relief Statuses</option>
                    <?php foreach ($statuses as $stat): ?>
                        <option value="<?php echo $stat['status_id']; ?>" <?php echo ($status_id == $stat['status_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($stat['status_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <button type="submit" class="btn-filter"><i class="fa-solid fa-magnifying-glass"></i> Filter</button>
                
                <?php if (!empty($search) || !empty($status_id)): ?>
                    <a href="disasters.php" class="btn-clear"><i class="fa-solid fa-xmark"></i> Clear</a>
                <?php endif; ?>
            </form>

            <div class="table-header">
                <h2>All Recorded Disasters</h2>
                <?php if (isset($_SESSION['role_id']) && $_SESSION['role_id'] == 1): ?>
                <button onclick="document.getElementById('addDisasterModal').style.display='block'" class="btn-add">
                    <i class="fa-solid fa-plus"></i> Record New Disaster
                </button>
                <?php endif; ?>
            </div>

            <?php if (isset($success_msg)): ?>
                <div class="alert alert-success">
                    <i class="fa-solid fa-circle-check"></i> <?php echo $success_msg; ?>
                </div>
            <?php endif; ?>

            <?php if (isset($error_msg)): ?>
                <div class="alert alert-error">
                    <i class="fa-solid fa-circle-xmark"></i> <?php echo $error_msg; ?>
                </div>
            <?php endif; ?>
            
            <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Disaster Type</th>
                        <th>Impact Zones</th>
                        <th>Total Affected</th>
                        <th>Date</th>
                        <th>Relief Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="disastersTableBody">
                <?php if (count($disasters) == 0): ?>
                    <tr>
                        <td colspan="7">
                            <div class="table-empty">
                                <i class="fa-solid fa-folder-open"></i>
                                <p>No disaster records found</p>
                                <small>Use "Record New Disaster" to add the first entry.</small>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                <?php foreach ($disasters as $row): ?>
                <tr>
                    <td class="cell-id">#<?php echo $row['disaster_id']; ?></td>
                    <td><strong><?php echo htmlspecialchars($row['type']); ?></strong></td>
                    <td>
                        <span class="location-pill">
                            <i class="fa-solid fa-location-dot"></i>
                            <?php echo htmlspecialchars($row['location']); ?>
                        </span>
                    </td>
                    <td>
                        <span class="affected-count">
                            <?php echo number_format($row['total_affected']); ?>
                            <span class="unit">ppl</span>
                        </span>
                    </td>
                    <td class="date-cell">
                        <?php 
                            if ($row['status'] == 'Pending Assessment' || empty($row['date'])) {
                                echo '<span style="color: #64748b; font-style: italic; font-weight: 500;">Pending Assessment</span>';
                            } else {
                                echo date('M d, Y', strtotime($row['date'])); 
                            }
                        ?>
                        <?php if (!empty($row['end_date'])): ?>
                            <div class="end-date">
                                <i class="fa-solid fa-flag-checkered"></i> Ended <?php echo date('M d, Y', strtotime($row['end_date'])); ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php 
                            $pillClass = 'pill-green';
                            $pillIcon = 'fa-circle-check';
                            if ($row['status'] == 'Ongoing') {
                                $pillClass = 'pill-yellow';
                                $pillIcon = 'fa-spinner';
                            } elseif ($row['status'] == 'Pending Assessment') {
                                $pillClass = 'pill-blue';
                                $pillIcon = 'fa-magnifying-glass';
                            }
                        ?>
                        <span class="pill <?php echo $pillClass; ?>">
                            <i class="fa-solid <?php echo $pillIcon; ?>"></i>
                            <?php echo htmlspecialchars($row['status']); ?>
                        </span>
                    </td>
                    <td>
                        <div class="actions-cell">
                            <a href="../actions/view_disaster.php?id=<?php echo $row['disaster_id']; ?>" class="btn-table-view">
                                <i class="fa-solid fa-eye"></i> View
                            </a>
                            <?php if (isset($_SESSION['role_id']) && $_SESSION['role_id'] == 1): ?>
                            <form method="POST" onsubmit="return confirm('Delete this disaster record?');" style="display: inline;">
                                <input type="hidden" name="delete_disaster_id" value="<?php echo $row['disaster_id']; ?>">
                                <button type="submit" class="btn-table-delete" title="Delete">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
            </div>
            <div id="disastersTablePagination" class="pagination-controls"></div>
        </div>
    </div>

    <!-- Add Disaster Modal -->
    <div id="addDisasterModal" class="modal-overlay">
        <div class="modal-content" style="width:450px;">
            <h2 style="margin-top:0; color: #0f172a; border-bottom: 2px solid #f1f5f9; padding-bottom: 15px; font-size: 18px;">Record New Disaster</h2>
            <form method="POST">
                <input type="hidden" name="add_disaster" value="1">
                
                <div style="margin-bottom: 15px;">
                    <label style="font-size: 13px; font-weight: 700; color: #475569; display: block; margin-bottom: 6px;">Disaster Type:</label>
                    <select name="disaster_type_id" required style="width: 100%; padding: 10px 14px; border-radius: 10px; border: 1.5px solid #e2e8f0; font-family: 'Plus Jakarta Sans', sans-serif; font-size: 13.5px;">
                        <option value="">-- Select Type --</option>
                        <?php foreach ($disasterTypes as $type): ?>
                            <option value="<?php echo $type['disaster_type_id']; ?>"><?php echo htmlspecialchars($type['type_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div id="startDateGroup" style="margin-bottom: 15px;">
                    <label style="font-size: 13px; font-weight: 700; color: #475569; display: block; margin-bottom: 6px;">Start Date:</label>
                    <div style="padding: 10px 14px; border-radius: 10px; border: 1.5px solid #e2e8f0; background-color: #f8fafc; color: #64748b; font-size: 13.5px; font-style: italic;">
                        <i class="fa-solid fa-clock-rotate-left" style="margin-right: 8px;"></i> Automatically set when relief starts
                    </div>
                    <input type="hidden" name="date" value="">
                </div>

                <div style="margin-bottom: 15px;">
                    <label style="font-size: 13px; font-weight: 700; color: #475569; display: block; margin-bottom: 6px;">Associated Relief Package (Optional):</label>
                    <select name="package_id" style="width: 100%; padding: 10px 14px; border-radius: 10px; border: 1.5px solid #e2e8f0; font-family: 'Plus Jakarta Sans', sans-serif; font-size: 13.5px;">
                        <option value="">-- No Specific Package --</option>
                        <?php foreach ($allPackages as $pkg): ?>
                            <option value="<?php echo $pkg['package_id']; ?>"><?php echo htmlspecialchars($pkg['package_name']); ?> (Stock: <?php echo $pkg['stock']; ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="margin-bottom: 15px;">
                    <label style="font-size: 13px; font-weight: 700; color: #475569; display: block; margin-bottom: 6px;">Relief Status:</label>
                    <select name="status_id" id="addStatus" required onchange="toggleAddEndDate()" style="width: 100%; padding: 10px 14px; border-radius: 10px; border: 1.5px solid #e2e8f0; font-family: 'Plus Jakarta Sans', sans-serif; font-size: 13.5px; background-color: #f8fafc; pointer-events: none;" tabindex="-1">
                        <?php foreach ($statuses as $stat): ?>
                            <?php if ($stat['status_name'] == 'Pending Assessment'): ?>
                            <option value="<?php echo $stat['status_id']; ?>" selected><?php echo htmlspecialchars($stat['status_name']); ?></option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="margin-bottom: 15px;">
                    <label style="font-size: 13px; font-weight: 700; color: #475569; display: block; margin-bottom: 6px;">Initial Impact Location (Barangay):</label>
                    <select name="barangay_id" required style="width: 100%; padding: 10px 14px; border-radius: 10px; border: 1.5px solid #e2e8f0; font-family: 'Plus Jakarta Sans', sans-serif; font-size: 13.5px;">
                        <option value="">-- Select Barangay --</option>
                        <?php foreach ($allBarangays as $b): ?>
                            <option value="<?php echo $b['barangay_id']; ?>"><?php echo htmlspecialchars($b['barangay_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="margin-bottom: 15px;">
                    <label style="font-size: 13px; font-weight: 700; color: #475569; display: block; margin-bottom: 6px;">Estimated Affected Residents:</label>
                    <input type="number" name="affected_residents" min="0" value="0" style="width: 100%; padding: 10px 14px; border-radius: 10px; border: 1.5px solid #e2e8f0; font-family: 'Plus Jakarta Sans', sans-serif; font-size: 13.5px;">
                </div>

                <div id="addEndDateGroup" style="margin-bottom: 20px; display: none;">
                    <label style="font-size: 13px; font-weight: 700; color: #475569; display: block; margin-bottom: 6px;">End Date:</label>
                    <input type="date" name="end_date" style="width: 100%; padding: 10px 14px; border-radius: 10px; border: 1.5px solid #e2e8f0; font-family: 'Plus Jakarta Sans', sans-serif; font-size: 13.5px;">
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 10px; border-top: 1.5px solid #f1f5f9; padding-top: 18px;">
                    <button type="button" onclick="document.getElementById('addDisasterModal').style.display='none'" style="padding: 10px 18px; background: #f1f5f9; color: #475569; border: 1.5px solid #e2e8f0; border-radius: 10px; cursor: pointer; font-weight: 700; font-family: 'Plus Jakarta Sans', sans-serif;">Cancel</button>
                    <button type="submit" style="padding: 10px 22px; background: linear-gradient(135deg, #2563eb, #3b82f6); color: white; border: none; border-radius: 10px; cursor: pointer; font-weight: 700; font-family: 'Plus Jakarta Sans', sans-serif; box-shadow: 0 2px 8px rgba(37,99,235,0.25);"><i class="fa-solid fa-save"></i> Save Event</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../style/paginate.js"></script>
    <script>
    function toggleAddEndDate() {
        const status = document.getElementById('addStatus').value;
        const endDateGroup = document.getElementById('addEndDateGroup');
        if (status == "2") {
            endDateGroup.style.display = 'block';
        } else {
            endDateGroup.style.display = 'none';
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        paginateTable('disastersTableBody', 'disastersTablePagination', 10);
    });
    </script>

</body>
</html>
