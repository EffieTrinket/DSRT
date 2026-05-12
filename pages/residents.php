<?php
session_start();

// Protect page
if (!isset($_SESSION['user']) || !isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

if (isset($_SESSION['role_name']) && $_SESSION['role_name'] === 'volunteer') {
    header('Location: disasters.php');
    exit;
}

// Database connection
require_once '../config/database.php';
require_once '../classes/ResidentModel.php';

$db = new Database();
$conn = $db->connect();
$residentModel = new ResidentModel($conn);

$search = $_GET['search'] ?? '';
$barangay_id = $_GET['barangay_id'] ?? '';

$residents = $residentModel->getAllResidents($search, $barangay_id);
$barangays = $residentModel->getAllBarangays();
$activeBarangays = $residentModel->getBarangaysWithOngoingDisasters();

// Handle recording new resident
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_resident'])) {
    if ($_SESSION['role_id'] == 1) {
        $name            = trim($_POST['name'] ?? '');
        $age             = isset($_POST['age']) && $_POST['age'] !== '' ? max(0, min(120, (int)$_POST['age'])) : null;
        $barangay_id_post = $_POST['barangay_id'];
        $address         = trim($_POST['address'] ?? '');
        $contact         = trim($_POST['contact'] ?? '');

        if (empty($name)) {
            $error_msg = "Full name is required.";
        } elseif ($residentModel->addResident($name, $age, $barangay_id_post, $address, $contact)) {
            $success_msg = "Resident added successfully!";
            $residents = $residentModel->getAllResidents($search, $barangay_id);
        } else {
            $error_msg = "Failed to add resident.";
        }
    }
}

// Handle deleting resident (Admin only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_resident_id'])) {
    if ($_SESSION['role_id'] == 1) {
        $delete_id = $_POST['delete_resident_id'];
        $query = "DELETE FROM residents WHERE resident_id = ?";
        $stmt = $conn->prepare($query);
        if ($stmt->execute([$delete_id])) {
            $success_msg = "Resident record deleted.";
            // Refresh list
            $residents = $residentModel->getAllResidents($search, $barangay_id);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Residents - Disaster Relief Tracker</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="../style/dashboard.css">
    <link rel="stylesheet" href="../style/tables.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>

   <?php $activePage = 'residents'; $sidebarBase = ''; include '../includes/sidebar.php'; ?>

    <div class="main">
        <div class="header">
            <h1>Residents Directory</h1>
            <p>Manage and track resident information across all barangays.</p>
        </div>

        <div class="table-container" style="margin-top: 2rem;">
            
            <form method="GET" action="residents.php" class="filter-bar">
                <input type="text" name="search" placeholder="Search resident name or address..." value="<?php echo htmlspecialchars($search); ?>">
                
                <select name="barangay_id">
                    <option value="">All Barangays</option>
                    <?php foreach ($barangays as $brgy): ?>
                        <option value="<?php echo $brgy['barangay_id']; ?>" <?php echo ($barangay_id == $brgy['barangay_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($brgy['barangay_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <button type="submit" class="btn-filter"><i class="fa-solid fa-magnifying-glass"></i> Filter</button>
                
                <?php if (!empty($search) || !empty($barangay_id)): ?>
                    <a href="residents.php" class="btn-clear"><i class="fa-solid fa-xmark"></i> Clear</a>
                <?php endif; ?>
            </form>

            <div class="table-header">
                <h2>All Residents List</h2>
                <?php if (isset($_SESSION['role_id']) && $_SESSION['role_id'] == 1): ?>
                <button onclick="document.getElementById('addResidentModal').style.display='block'" class="btn-add">
                    <i class="fa-solid fa-plus"></i> Add New Resident
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
                    <th>Name</th>
                    <th>Age</th>
                    <th>Barangay</th>
                    <th>Address</th>
                    <th>Contact</th>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody id="residentsTableBody">
                <?php if (count($residents) == 0): ?>
                    <tr>
                        <td colspan="7">
                            <div class="table-empty">
                                <i class="fa-solid fa-users-slash"></i>
                                <p>No resident records found.</p>
                                <small>Add a new resident to get started.</small>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                <?php foreach ($residents as $row): ?>
                <tr>
                    <td class="cell-id">#<?php echo $row['resident_id']; ?></td>
                    <td><strong><?php echo htmlspecialchars($row['name']); ?></strong></td>
                    <td><?php echo htmlspecialchars($row['age'] ?? 'N/A'); ?></td>
                    <td>
                        <span class="location-pill">
                            <i class="fa-solid fa-location-dot"></i>
                            <?php echo htmlspecialchars($row['barangay_name'] ?? 'Unknown'); ?>
                        </span>
                    </td>
                    <td><?php echo htmlspecialchars($row['address'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($row['contact'] ?? 'N/A'); ?></td>
                    <td>
                        <div class="actions-cell">
                            <a href="../actions/view_resident.php?id=<?php echo $row['resident_id']; ?>" class="btn-table-view" title="View Details">
                                <i class="fa-solid fa-eye"></i> View
                            </a>
                            <?php if (isset($_SESSION['role_id']) && $_SESSION['role_id'] == 1): ?>
                            <form method="POST" onsubmit="return confirm('Are you sure you want to delete this resident record?');" style="display: inline;">
                                <input type="hidden" name="delete_resident_id" value="<?php echo $row['resident_id']; ?>">
                                <button type="submit" class="btn-table-delete" title="Delete Record">
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
            <div id="residentsTablePagination" class="pagination-controls"></div>

        </div>
    </div>

    <!-- Add Resident Modal -->
    <div id="addResidentModal" class="modal-overlay">
        <div class="modal-content" style="width:500px;">
            <h2 style="margin-top:0; color: #0f172a; border-bottom: 2px solid #f1f5f9; padding-bottom: 15px; font-size: 18px;">Add New Resident</h2>
            <form method="POST">
                <input type="hidden" name="add_resident" value="1">
                
                <div style="margin-bottom: 15px;">
                    <label style="font-size: 13px; font-weight: 700; color: #475569; display: block; margin-bottom: 6px;">Full Name:</label>
                    <input type="text" name="name" required placeholder="e.g. Juan Dela Cruz" style="width: 100%; padding: 10px 14px; border-radius: 10px; border: 1.5px solid #e2e8f0; font-family: 'Plus Jakarta Sans', sans-serif; font-size: 13.5px;">
                </div>

                <div style="display: flex; gap: 15px; margin-bottom: 15px;">
                    <div style="flex: 1;">
                        <label style="font-size: 13px; font-weight: 700; color: #475569; display: block; margin-bottom: 6px;">Age:</label>
                        <input type="number" name="age" min="0" max="120" placeholder="e.g. 35" style="width: 100%; padding: 10px 14px; border-radius: 10px; border: 1.5px solid #e2e8f0; font-family: 'Plus Jakarta Sans', sans-serif; font-size: 13.5px;">
                    </div>
                    <div style="flex: 2;">
                        <label style="font-size: 13px; font-weight: 700; color: #475569; display: block; margin-bottom: 6px;">Contact Number:</label>
                        <input type="text" name="contact" placeholder="e.g. 09123456789" pattern="[0-9\-\+\s]{7,15}" title="Enter a valid phone number (7-15 digits)" style="width: 100%; padding: 10px 14px; border-radius: 10px; border: 1.5px solid #e2e8f0; font-family: 'Plus Jakarta Sans', sans-serif; font-size: 13.5px;">
                    </div>
                </div>

                <div style="margin-bottom: 15px;">
                    <label style="font-size: 13px; font-weight: 700; color: #475569; display: block; margin-bottom: 6px;">Barangay (Ongoing Disasters Only):</label>
                    <select name="barangay_id" required style="width: 100%; padding: 10px 14px; border-radius: 10px; border: 1.5px solid #e2e8f0; font-family: 'Plus Jakarta Sans', sans-serif; font-size: 13.5px;">
                        <option value="">-- Select Affected Barangay --</option>
                        <?php foreach ($activeBarangays as $brgy): ?>
                            <option value="<?php echo $brgy['barangay_id']; ?>"><?php echo htmlspecialchars($brgy['barangay_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (empty($activeBarangays)): ?>
                        <p style="color: #ef4444; font-size: 12px; margin-top: 5px;">* No ongoing disasters recorded. Please record a disaster event first.</p>
                    <?php endif; ?>
                </div>

                <div style="margin-bottom: 20px;">
                    <label style="font-size: 13px; font-weight: 700; color: #475569; display: block; margin-bottom: 6px;">Exact Address:</label>
                    <input type="text" name="address" placeholder="e.g. Street, House No." style="width: 100%; padding: 10px 14px; border-radius: 10px; border: 1.5px solid #e2e8f0; font-family: 'Plus Jakarta Sans', sans-serif; font-size: 13.5px;">
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 10px; border-top: 1.5px solid #f1f5f9; padding-top: 18px;">
                    <button type="button" onclick="document.getElementById('addResidentModal').style.display='none'" style="padding: 10px 18px; background: #f1f5f9; color: #475569; border: 1.5px solid #e2e8f0; border-radius: 10px; cursor: pointer; font-weight: 700; font-family: 'Plus Jakarta Sans', sans-serif;">Cancel</button>
                    <button type="submit" style="padding: 10px 22px; background: linear-gradient(135deg, #2563eb, #3b82f6); color: white; border: none; border-radius: 10px; cursor: pointer; font-weight: 700; font-family: 'Plus Jakarta Sans', sans-serif; box-shadow: 0 2px 8px rgba(37,99,235,0.25);"><i class="fa-solid fa-save"></i> Save Resident</button>
                </div>
            </form>
        </div>
    </div>

<script src="../style/paginate.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    paginateTable('residentsTableBody', 'residentsTablePagination', 10);
});
</script>
</body>
</html>
