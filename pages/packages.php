<?php
session_start();

// Protect page
if (!isset($_SESSION['user']) || !isset($_SESSION['user_id'])) {
    header('Location: logout.php');
    exit;
}

// Database connection
require_once '../config/database.php';
require_once '../classes/PackageModel.php';

$db = new Database();
$conn = $db->connect();
$packageModel = new PackageModel($conn);

$search = $_GET['search'] ?? '';

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_SESSION['role_id']) && $_SESSION['role_id'] == 1) {
        if (isset($_POST['add_package'])) {
            $name = $_POST['package_name'];
            $desc = $_POST['description'];
            $stock = $_POST['initial_stock'];
            $packageModel->addPackage($name, $desc, $stock);
            header('Location: packages.php');
            exit;
        } elseif (isset($_POST['update_stock'])) {
            $id = $_POST['package_id'];
            $amount = $_POST['amount'];
            $packageModel->updateStock($id, $amount);
            header('Location: packages.php');
            exit;
        } elseif (isset($_POST['delete_package'])) {
            $id = $_POST['package_id'];
            $packageModel->deletePackage($id);
            header('Location: packages.php');
            exit;
        }
    }
}

$packages = $packageModel->getAllPackages($search);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relief Packages - Disaster Relief Tracker</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="../style/dashboard.css">
    <link rel="stylesheet" href="../style/tables.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../style/package.css">
</head>
<body>

<?php $activePage = 'packages'; $sidebarBase = ''; include '../includes/sidebar.php'; ?>

<div class="main">
    <div class="header">
        <h1>Relief Packages Inventory</h1>
        <p>Manage and track stock levels for emergency relief supplies.</p>
    </div>

    <div style="margin-top: 2rem;">
        
        <form method="GET" action="packages.php" class="filter-bar">
            <input type="text" name="search" placeholder="Search package name or description..." value="<?php echo htmlspecialchars($search); ?>">
            
            <button type="submit" class="btn-filter"><i class="fa-solid fa-magnifying-glass"></i> Search</button>
            
            <?php if (!empty($search)): ?>
                <a href="packages.php" class="btn-clear"><i class="fa-solid fa-xmark"></i> Clear</a>
            <?php endif; ?>
        </form>

        <div class="table-header">
            <h2>Inventory Gallery</h2>
            <?php if (isset($_SESSION['role_id']) && $_SESSION['role_id'] == 1): ?>
            <button onclick="document.getElementById('addPackageModal').style.display='block'" class="btn-add">
                <i class="fa-solid fa-plus"></i> Add New Package
            </button>
            <?php endif; ?>
        </div>
        
        <div class="package-grid">
            <?php foreach ($packages as $row): ?>
            <?php 
                $stock = $row['stock'];
                $statusClass = 'sufficient';
                $statusText = 'In Stock';
                
                if ($stock <= 10) {
                    $statusClass = 'critical';
                    $statusText = 'Critical';
                } elseif ($stock <= 50) {
                    $statusClass = 'low';
                    $statusText = 'Low Stock';
                }
            ?>
            <div class="package-card <?php echo $statusClass; ?>">
                <div class="card-header">
                    <h3><?php echo htmlspecialchars($row['package_name']); ?></h3>
                    <span class="pkg-id">#<?php echo $row['package_id']; ?></span>
                </div>
                
                <div class="stock-info">
                    <div class="stock-number"><?php echo htmlspecialchars($stock); ?></div>
                    <div>
                        <div class="stock-label">Quantity</div>
                        <div style="font-size: 11px; color: <?php echo ($statusClass == 'critical' ? '#e74c3c' : ($statusClass == 'low' ? '#f39c12' : '#27ae60')); ?>; font-weight: bold;"><?php echo $statusText; ?></div>
                    </div>
                </div>

                <div class="description-box">
                    <?php echo htmlspecialchars($row['description'] ?? 'No description available for this relief package.'); ?>
                </div>

                <div class="card-actions">
                    <a href="../actions/view_package.php?id=<?php echo $row['package_id']; ?>" class="btn-action btn-view" title="View Details" style="text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 5px;">
                        <i class="fa-solid fa-eye"></i> View
                    </a>
                    <?php if (isset($_SESSION['role_id']) && $_SESSION['role_id'] == 1): ?>
                    <button class="btn-action btn-stock" title="Update Stock" onclick="openStockModal(<?php echo $row['package_id']; ?>, '<?php echo htmlspecialchars(addslashes($row['package_name'])); ?>')">
                        <i class="fa-solid fa-plus-minus"></i> Stock
                    </button>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this package?');">
                        <input type="hidden" name="delete_package" value="1">
                        <input type="hidden" name="package_id" value="<?php echo $row['package_id']; ?>">
                        <button type="submit" class="btn-action btn-delete" title="Delete Package">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Empty state fallback -->
        <?php if (count($packages) == 0): ?>
            <div style="text-align: center; padding: 40px; color: #7f8c8d; background: white; border-radius: 18px;">
                <i class="fa-solid fa-box-open" style="font-size: 48px; margin-bottom: 10px;"></i>
                <p>No relief packages found in inventory.</p>
            </div>
        <?php endif; ?>

    </div>
</div>

<!-- Add Package Modal -->
<div id="addPackageModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000;">
    <div style="background:white; width:400px; margin: 100px auto; padding: 25px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.2);">
        <h2 style="margin-top:0; color: #2c3e50;">Add New Relief Package</h2>
        <form method="POST">
            <input type="hidden" name="add_package" value="1">
            
            <div style="margin-bottom: 15px;">
                <label style="font-size: 14px; font-weight: bold; color: #34495e;">Package Name:</label>
                <input type="text" name="package_name" required style="width: 100%; padding: 10px; margin-top: 5px; border-radius: 5px; border: 1px solid #bdc3c7;">
            </div>

            <div style="margin-bottom: 15px;">
                <label style="font-size: 14px; font-weight: bold; color: #34495e;">Description:</label>
                <textarea name="description" rows="3" style="width: 100%; padding: 10px; margin-top: 5px; border-radius: 5px; border: 1px solid #bdc3c7; resize: vertical;"></textarea>
            </div>

            <div style="margin-bottom: 20px;">
                <label style="font-size: 14px; font-weight: bold; color: #34495e;">Initial Stock Quantity:</label>
                <input type="number" name="initial_stock" required value="0" min="0" style="width: 100%; padding: 10px; margin-top: 5px; border-radius: 5px; border: 1px solid #bdc3c7;">
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" onclick="document.getElementById('addPackageModal').style.display='none'" style="padding: 10px 15px; background: #ecf0f1; color: #2c3e50; border: none; border-radius: 5px; cursor: pointer; font-weight: bold;">Cancel</button>
                <button type="submit" style="padding: 10px 15px; background: #27ae60; color: white; border: none; border-radius: 5px; cursor: pointer; font-weight: bold;"><i class="fa-solid fa-save"></i> Save Package</button>
            </div>
        </form>
    </div>
</div>

<!-- Update Stock Modal -->
<div id="updateStockModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000;">
    <div style="background:white; width:400px; margin: 100px auto; padding: 25px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.2);">
        <h2 style="margin-top:0; color: #2c3e50;">Update Stock Level</h2>
        <p id="stockModalPackageName" style="color: #7f8c8d; margin-bottom: 20px; font-size: 14px;"></p>
        <form method="POST">
            <input type="hidden" name="update_stock" value="1">
            <input type="hidden" name="package_id" id="stockModalPackageId" value="">
            
            <div style="margin-bottom: 20px;">
                <label style="font-size: 14px; font-weight: bold; color: #34495e;">Amount to Add:</label>
                <p style="font-size: 12px; color: #95a5a6; margin: 2px 0 8px 0;">(Use a negative number to reduce stock)</p>
                <input type="number" name="amount" required value="0" style="width: 100%; padding: 10px; border-radius: 5px; border: 1px solid #bdc3c7;">
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" onclick="document.getElementById('updateStockModal').style.display='none'" style="padding: 10px 15px; background: #ecf0f1; color: #2c3e50; border: none; border-radius: 5px; cursor: pointer; font-weight: bold;">Cancel</button>
                <button type="submit" style="padding: 10px 15px; background: #3498db; color: white; border: none; border-radius: 5px; cursor: pointer; font-weight: bold;"><i class="fa-solid fa-plus-minus"></i> Update</button>
            </div>
        </form>
    </div>
</div>

<script>
function openStockModal(packageId, packageName) {
    document.getElementById('stockModalPackageId').value = packageId;
    document.getElementById('stockModalPackageName').innerText = "Adjusting stock for: " + packageName;
    document.getElementById('updateStockModal').style.display = 'block';
}
</script>

</body>
</html>
