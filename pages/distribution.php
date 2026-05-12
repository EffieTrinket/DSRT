<?php
session_start();

// Protect page
if (!isset($_SESSION['user']) || !isset($_SESSION['user_id'])) {
    header('Location: logout.php');
    exit;
}

// Database connection
require_once '../config/database.php';
require_once '../classes/DistributionModel.php';
require_once '../classes/ResidentModel.php';
require_once '../classes/PackageModel.php';

$db = new Database();
$conn = $db->connect();
$distributionModel = new DistributionModel($conn);
$residentModel = new ResidentModel($conn);
$packageModel = new PackageModel($conn);

$search = $_GET['search'] ?? '';

// Handle recording new distribution
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_distribution'])) {
    $resident_id = $_POST['resident_id'];
    $package_ids = $_POST['package_id']; // Array
    $quantities = $_POST['quantity'];   // Array
    $date_distributed = $_POST['date_distributed'];
    $user_id = $_SESSION['user_id'];

    $any_error = false;
    $recorded_count = 0;

    foreach ($package_ids as $index => $package_id) {
        $quantity = $quantities[$index];
        if (empty($package_id) || $quantity <= 0) continue;

        // Check if package has enough stock
        $package = $packageModel->getPackageById($package_id);
        if ($package && $package['stock'] >= $quantity) {
            if ($distributionModel->addDistribution($resident_id, $package_id, $user_id, $quantity, $date_distributed)) {
                $recorded_count++;
            } else {
                $any_error = true;
            }
        } else {
            $any_error = true;
            $error_msg = "Error: Insufficient stock for " . ($package['package_name'] ?? 'one of the packages') . ".";
            break; // Stop if stock error occurs
        }
    }

    if (!$any_error && $recorded_count > 0) {
        $success_msg = "Successfully recorded $recorded_count relief items!";
    } elseif ($recorded_count > 0) {
        $success_msg = "Partially recorded $recorded_count relief items, but some errors occurred.";
    } elseif (!isset($error_msg)) {
        $error_msg = "Failed to record any distribution.";
    }
}

// Handle deleting distribution (Admin only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    if ($_SESSION['role_id'] == 1) {
        $delete_id = $_POST['delete_id'];
        // Note: In a real system, you might want to return the stock back to the package,
        // but for now, we'll just delete the log entry as requested.
        $query = "DELETE FROM distributions WHERE distribution_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->execute([$delete_id]);
        $success_msg = "Distribution record deleted.";
    }
}

$distributions = $distributionModel->getAllDistributions($search);
$residents = $residentModel->getAllResidents();
$packages = $packageModel->getAllPackages();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relief Distribution - Disaster Relief Tracker</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="../style/dashboard.css">
    <link rel="stylesheet" href="../style/tables.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>

<?php $activePage = 'distribution'; $sidebarBase = ''; include '../includes/sidebar.php'; ?>

<div class="main">
    <div class="header">
        <h1>Relief Distribution Logs</h1>
        <p>Track and manage the release of relief packages to residents.</p>
    </div>

    <div class="table-container" style="margin-top: 2rem;">
        
        <form method="GET" action="distribution.php" class="filter-bar">
            <input type="text" name="search" placeholder="Search resident, package, or distributor..." value="<?php echo htmlspecialchars($search); ?>">
            
            <button type="submit" class="btn-filter"><i class="fa-solid fa-magnifying-glass"></i> Search</button>
            
            <?php if (!empty($search)): ?>
                <a href="distribution.php" class="btn-clear"><i class="fa-solid fa-xmark"></i> Clear</a>
            <?php endif; ?>
        </form>

        <div class="table-header">
            <h2>Distribution History</h2>
            <?php if (isset($_SESSION['role_name']) && $_SESSION['role_name'] !== 'volunteer'): ?>
            <button onclick="document.getElementById('distModal').style.display='block'" class="btn-add">
                <i class="fa-solid fa-plus"></i> Record New Distribution
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
                <th>Date</th>
                <th>Resident Name</th>
                <th>Package</th>
                <th>Quantity</th>
                <th>Distributed By</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody id="distributionTableBody">
            <?php if (count($distributions) == 0): ?>
                <tr>
                    <td colspan="7">
                        <div class="table-empty">
                            <i class="fa-solid fa-truck-ramp-box"></i>
                            <p>No distribution logs recorded yet.</p>
                            <small>Use "Record New Distribution" to add your first entry.</small>
                        </div>
                    </td>
                </tr>
            <?php else: ?>
            <?php foreach ($distributions as $row): ?>
            <tr>
                <td class="cell-id">#<?php echo $row['distribution_id']; ?></td>
                <td class="date-cell"><?php echo htmlspecialchars($row['date_distributed']); ?></td>
                <td><strong><?php echo htmlspecialchars($row['resident_name'] ?? 'Unknown Resident'); ?></strong></td>
                <td>
                    <span class="package-pill">
                        <i class="fa-solid fa-box"></i>
                        <?php echo htmlspecialchars($row['package_name'] ?? 'Unknown Package'); ?>
                    </span>
                </td>
                <td><span class="qty-badge qty-blue"><?php echo htmlspecialchars($row['quantity']); ?></span></td>
                <td>
                    <span class="distributor-text">
                        <i class="fa-solid fa-user-check"></i>
                        <?php echo htmlspecialchars($row['distributor_name'] ?? 'System'); ?>
                    </span>
                </td>
                <td>
                    <div class="actions-cell">
                        <a href="../actions/view_distribution.php?id=<?php echo $row['distribution_id']; ?>" class="btn-table-view" title="View Details">
                            <i class="fa-solid fa-eye"></i> View
                        </a>
                        <?php if (isset($_SESSION['role_id']) && $_SESSION['role_id'] == 1): ?>
                        <form method="POST" onsubmit="return confirm('Are you sure you want to delete this distribution record?');" style="display: inline;">
                            <input type="hidden" name="delete_id" value="<?php echo $row['distribution_id']; ?>">
                            <button type="submit" class="btn-table-delete" title="Delete Log">
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
        <div id="distributionTablePagination" class="pagination-controls"></div>

    </div>
</div>

<!-- Record Distribution Modal -->
<div id="distModal" class="modal-overlay">
    <div class="modal-content" style="width:600px;">
        <h2 style="margin-top:0; color: #0f172a; border-bottom: 2px solid #f1f5f9; padding-bottom: 15px; font-size: 18px;">Record Relief Distribution</h2>
        <form method="POST">
            <input type="hidden" name="record_distribution" value="1">
            
            <div style="margin-bottom: 20px;">
                <label style="font-size: 13px; font-weight: 700; color: #475569; display: block; margin-bottom: 6px;">Recipient Resident:</label>
                <select name="resident_id" required style="width: 100%; padding: 12px 14px; border-radius: 10px; border: 1.5px solid #e2e8f0; font-family: 'Plus Jakarta Sans', sans-serif; font-size: 13.5px; background: #f9fafb;">
                    <option value="">-- Search and Select Resident --</option>
                    <?php foreach ($residents as $res): ?>
                        <option value="<?php echo $res['resident_id']; ?>"><?php echo htmlspecialchars($res['name']); ?> (<?php echo htmlspecialchars($res['barangay_name']); ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="margin-bottom: 20px;">
                <label style="font-size: 13px; font-weight: 700; color: #475569; display: block; margin-bottom: 6px;">Relief Packages to Give:</label>
                <div id="packageList">
                    <!-- Dynamic package rows will be added here -->
                    <div class="package-row" style="display: flex; gap: 10px; margin-bottom: 10px; align-items: flex-start; background: #f8fafc; padding: 12px; border-radius: 10px; border: 1.5px dashed #e2e8f0;">
                        <div style="flex: 2;">
                            <select name="package_id[]" required style="width: 100%; padding: 10px 14px; border-radius: 8px; border: 1.5px solid #e2e8f0; font-family: 'Plus Jakarta Sans', sans-serif; font-size: 13px;">
                                <option value="">-- Select Package --</option>
                                <?php foreach ($packages as $pkg): ?>
                                    <option value="<?php echo $pkg['package_id']; ?>"><?php echo htmlspecialchars($pkg['package_name']); ?> (Stock: <?php echo $pkg['stock']; ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div style="flex: 1;">
                            <input type="number" name="quantity[]" required min="1" value="1" placeholder="Qty" style="width: 100%; padding: 10px 14px; border-radius: 8px; border: 1.5px solid #e2e8f0; font-family: 'Plus Jakarta Sans', sans-serif; font-size: 13px;">
                        </div>
                        <button type="button" onclick="removePackageRow(this)" style="padding: 10px 12px; background: #fff1f2; color: #dc2626; border: 1.5px solid #fecaca; border-radius: 8px; cursor: pointer; font-size: 13px;"><i class="fa-solid fa-trash"></i></button>
                    </div>
                </div>
                <button type="button" onclick="addPackageRow()" style="margin-top: 5px; padding: 8px 16px; background: #f1f5f9; color: #475569; border: 1.5px solid #e2e8f0; border-radius: 8px; cursor: pointer; font-size: 12px; font-weight: 700; font-family: 'Plus Jakarta Sans', sans-serif;">
                    <i class="fa-solid fa-plus"></i> Add Another Package
                </button>
            </div>

            <div style="margin-bottom: 25px;">
                <label style="font-size: 13px; font-weight: 700; color: #475569; display: block; margin-bottom: 6px;">Date Distributed:</label>
                <input type="date" name="date_distributed" required value="<?php echo date('Y-m-d'); ?>" style="width: 100%; padding: 12px 14px; border-radius: 10px; border: 1.5px solid #e2e8f0; font-family: 'Plus Jakarta Sans', sans-serif; font-size: 13.5px; background: #f9fafb;">
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 12px; border-top: 1.5px solid #f1f5f9; padding-top: 20px;">
                <button type="button" onclick="document.getElementById('distModal').style.display='none'" style="padding: 12px 20px; background: #f1f5f9; color: #475569; border: 1.5px solid #e2e8f0; border-radius: 10px; cursor: pointer; font-weight: 700; font-family: 'Plus Jakarta Sans', sans-serif;">Cancel</button>
                <button type="submit" style="padding: 12px 25px; background: linear-gradient(135deg, #16a34a, #22c55e); color: white; border: none; border-radius: 10px; cursor: pointer; font-weight: 700; font-family: 'Plus Jakarta Sans', sans-serif; box-shadow: 0 4px 10px rgba(34, 197, 94, 0.25);">
                    <i class="fa-solid fa-truck-fast"></i> Finalize Distribution
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function addPackageRow() {
    const container = document.getElementById('packageList');
    const firstRow = container.querySelector('.package-row');
    const newRow = firstRow.cloneNode(true);
    
    // Clear the selections in the new row
    newRow.querySelector('select').value = "";
    newRow.querySelector('input').value = "1";
    
    container.appendChild(newRow);
}

function removePackageRow(btn) {
    const container = document.getElementById('packageList');
    if (container.querySelectorAll('.package-row').length > 1) {
        btn.closest('.package-row').remove();
    } else {
        alert("At least one package is required.");
    }
}
</script>

<script src="../style/paginate.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    paginateTable('distributionTableBody', 'distributionTablePagination', 10);
});
</script>
</body>
</html>
