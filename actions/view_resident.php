<?php
session_start();

if (!isset($_SESSION['user']) || !isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}



require_once '../config/database.php';
require_once '../classes/ResidentModel.php';

$db = new Database();
$conn = $db->connect();
$residentModel = new ResidentModel($conn);

if (!isset($_GET['id']) || empty($_GET['id'])) {
    if (isset($_SESSION['role_name']) && $_SESSION['role_name'] === 'volunteer') {
        header('Location: ../pages/disasters.php');
    } else {
        header('Location: ../pages/residents.php');
    }
    exit;
}

$id = $_GET['id'];

// Handle Edit Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_resident']) && isset($_SESSION['role_id']) && $_SESSION['role_id'] == 1) {
    $edit_id = $_POST['resident_id'];
    $name = $_POST['name'];
    $age = !empty($_POST['age']) ? $_POST['age'] : null;
    $barangay_id = !empty($_POST['barangay_id']) ? $_POST['barangay_id'] : null;
    $address = $_POST['address'];
    $contact = $_POST['contact'];
    
    $residentModel->updateResident($edit_id, $name, $age, $barangay_id, $address, $contact);
    header("Location: view_resident.php?id=$edit_id");
    exit;
}

$resident = $residentModel->getResidentById($id);

if (!$resident) {
    if (isset($_SESSION['role_name']) && $_SESSION['role_name'] === 'volunteer') {
        header('Location: ../pages/disasters.php');
    } else {
        header('Location: ../pages/residents.php');
    }
    exit;
}

$disasters = $residentModel->getResidentDisasters($id);
$distributions = $residentModel->getResidentDistributions($id);
$allBarangays = $residentModel->getAllBarangays();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Resident - Disaster Relief Tracker</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="../style/dashboard.css">
    <link rel="stylesheet" href="../style/tables.css">
    <link rel="stylesheet" href="../style/view_pages.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>

    <?php $activePage = 'residents'; $sidebarBase = '../pages/'; include '../includes/sidebar.php'; ?>
</div>

    <div class="main">
        <div class="header header-flex">
            <div>
                <h1>Resident Profile: ID# <?php echo $resident['resident_id']; ?></h1>
                <p>Comprehensive overview of the resident's relief history and details.</p>
            </div>
            <div style="display: flex; gap: 10px;">
                <?php if (isset($_SESSION['role_id']) && $_SESSION['role_id'] == 1): ?>
                <button onclick="document.getElementById('editModal').style.display='block'" class="btn-back" style="background: #3498db; color: white; border: none;">
                    <i class="fa-solid fa-pen"></i> Edit Resident
                </button>
                <?php endif; ?>
                <?php 
                $backLink = '../pages/residents.php';
                if (isset($_SESSION['role_name']) && $_SESSION['role_name'] === 'volunteer') {
                    $backLink = '../pages/disasters.php';
                }
                ?>
                <a href="<?php echo $backLink; ?>" class="btn-back">
                    <i class="fa-solid fa-chevron-left"></i> Back
                </a>
            </div>
        </div>

        <div class="cards-container">
            
            <!-- General Info Card -->
            <div class="detail-card">
                <h3><i class="fa-solid fa-address-card" style="color: #3b82f6;"></i> General Information</h3>
                
                <div class="info-grid">
                    <div>
                        <p class="info-label">Full Name</p>
                        <p class="info-value"><i class="fa-solid fa-user" style="color: #3b82f6; width: 20px;"></i> <?php echo htmlspecialchars($resident['name']); ?></p>
                    </div>
                    <div>
                        <p class="info-label">Age</p>
                        <p class="info-value"><i class="fa-solid fa-calendar-day" style="color: #f59e0b; width: 20px;"></i> <?php echo htmlspecialchars($resident['age'] ?? 'N/A'); ?></p>
                    </div>
                    <div>
                        <p class="info-label">Contact Number</p>
                        <p class="info-value"><i class="fa-solid fa-phone" style="color: #10b981; width: 20px;"></i> <?php echo htmlspecialchars($resident['contact'] ?? 'N/A'); ?></p>
                    </div>
                    <div>
                        <p class="info-label">Barangay</p>
                        <p class="info-value"><i class="fa-solid fa-location-dot" style="color: #ef4444; width: 20px;"></i> <?php echo htmlspecialchars($resident['barangay_name'] ?? 'Unknown'); ?></p>
                    </div>
                    <div style="grid-column: span 2;">
                        <p class="info-label">Exact Address</p>
                        <p class="info-value"><i class="fa-solid fa-map-pin" style="color: #8b5cf6; width: 20px;"></i> <?php echo htmlspecialchars($resident['address'] ?? 'N/A'); ?></p>
                    </div>
                </div>
            </div>

        </div>

        <div style="display: flex; gap: 20px; flex-wrap: wrap; margin-top: 2rem;">
            
            <!-- Disasters History Table -->
            <div class="table-container" style="flex: 1; min-width: 400px; margin-top: 0;">
                <div class="table-header-flex">
                    <h2 style="font-size: 1.2rem; display: flex; align-items: center; gap: 10px;">
                        <i class="fa-solid fa-triangle-exclamation" style="color: #e74c3c;"></i>
                        Disaster Impact History
                    </h2>
                </div>
                <table>
                    <thead>
                    <tr>
                        <th>Date</th>
                        <th>Disaster Type</th>
                        <th>Condition</th>
                    </tr>
                    </thead>
                    <tbody id="disasterHistoryTbody">
                    <?php foreach ($disasters as $d): ?>
                    <tr>
                        <td style="white-space: nowrap;"><?php echo htmlspecialchars($d['date']); ?></td>
                        <td><strong style="color: #2c3e50;"><?php echo htmlspecialchars($d['type_name']); ?></strong></td>
                        <td>
                            <?php 
                                $cond = $d['condition_name'];
                                $condClass = 'condition-default';
                                if ($cond == 'Safe') $condClass = 'condition-safe';
                                if ($cond == 'Injured') $condClass = 'condition-injured';
                                if ($cond == 'Missing') $condClass = 'condition-missing';
                                if ($cond == 'Casualty') $condClass = 'condition-casualty';
                                if ($cond == 'Evacuated') $condClass = 'condition-evacuated';
                            ?>
                            <span class="condition-badge <?php echo $condClass; ?>">
                                <?php echo htmlspecialchars($cond ?? 'Unknown'); ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <div id="disasterHistoryPagination" class="pagination-controls"></div>
                <?php if (count($disasters) == 0): ?>
                    <p style="color: #7f8c8d; text-align: center; margin-top: 20px;">No disaster records linked to this resident.</p>
                <?php endif; ?>
            </div>

            <!-- Relief Distributions Table -->
            <div class="table-container" style="flex: 1; min-width: 400px; margin-top: 0;">
                <div class="table-header-flex">
                    <h2 style="font-size: 1.2rem; display: flex; align-items: center; gap: 10px;">
                        <i class="fa-solid fa-boxes-stacked" style="color: #3498db;"></i>
                        Relief Package History
                    </h2>
                </div>
                <table>
                    <thead>
                    <tr>
                        <th>Date</th>
                        <th>Package</th>
                        <th>Qty</th>
                    </tr>
                    </thead>
                    <tbody id="reliefHistoryTbody">
                    <?php foreach ($distributions as $dist): ?>
                    <tr>
                        <td style="white-space: nowrap;"><?php echo htmlspecialchars($dist['date_distributed']); ?></td>
                        <td><strong style="color: #2c3e50;"><?php echo htmlspecialchars($dist['package_name']); ?></strong></td>
                        <td><span class="qty-badge qty-blue"><?php echo htmlspecialchars($dist['quantity']); ?> units</span></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <div id="reliefHistoryPagination" class="pagination-controls"></div>
                <?php if (count($distributions) == 0): ?>
                    <p style="color: #7f8c8d; text-align: center; margin-top: 20px;">No relief packages distributed to this resident yet.</p>
                <?php endif; ?>
            </div>

        </div>

    </div>

    <!-- Edit Resident Modal -->
    <div id="editModal" class="modal-overlay">
        <div class="modal-content" style="width:450px;">
            <h2 style="margin-top:0; color: #0f172a; border-bottom: 2px solid #f1f5f9; padding-bottom: 15px; font-size: 18px;">Edit Resident Profile</h2>
            <form method="POST">
                <input type="hidden" name="edit_resident" value="1">
                <input type="hidden" name="resident_id" value="<?php echo $resident['resident_id']; ?>">
                
                <div style="margin-bottom: 15px;">
                    <label style="font-size: 13px; font-weight: 700; color: #475569; display: block; margin-bottom: 6px;">Full Name:</label>
                    <input type="text" name="name" required value="<?php echo htmlspecialchars($resident['name']); ?>" style="width: 100%; padding: 10px 14px; border-radius: 10px; border: 1.5px solid #e2e8f0; font-family: 'Plus Jakarta Sans', sans-serif; font-size: 13.5px;">
                </div>

                <div style="margin-bottom: 15px;">
                    <label style="font-size: 13px; font-weight: 700; color: #475569; display: block; margin-bottom: 6px;">Age:</label>
                    <input type="number" name="age" value="<?php echo htmlspecialchars($resident['age']); ?>" style="width: 100%; padding: 10px 14px; border-radius: 10px; border: 1.5px solid #e2e8f0; font-family: 'Plus Jakarta Sans', sans-serif; font-size: 13.5px;">
                </div>

                <div style="margin-bottom: 15px;">
                    <label style="font-size: 13px; font-weight: 700; color: #475569; display: block; margin-bottom: 6px;">Contact Number:</label>
                    <input type="text" name="contact" value="<?php echo htmlspecialchars($resident['contact']); ?>" style="width: 100%; padding: 10px 14px; border-radius: 10px; border: 1.5px solid #e2e8f0; font-family: 'Plus Jakarta Sans', sans-serif; font-size: 13.5px;">
                </div>

                <div style="margin-bottom: 15px;">
                    <label style="font-size: 13px; font-weight: 700; color: #475569; display: block; margin-bottom: 6px;">Barangay:</label>
                    <select name="barangay_id" style="width: 100%; padding: 10px 14px; border-radius: 10px; border: 1.5px solid #e2e8f0; font-family: 'Plus Jakarta Sans', sans-serif; font-size: 13.5px;">
                        <option value="">None Selected</option>
                        <?php foreach($allBarangays as $b): ?>
                            <option value="<?php echo $b['barangay_id']; ?>" <?php echo ($b['barangay_id'] == $resident['barangay_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($b['barangay_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="margin-bottom: 20px;">
                    <label style="font-size: 13px; font-weight: 700; color: #475569; display: block; margin-bottom: 6px;">Exact Address:</label>
                    <input type="text" name="address" value="<?php echo htmlspecialchars($resident['address']); ?>" style="width: 100%; padding: 10px 14px; border-radius: 10px; border: 1.5px solid #e2e8f0; font-family: 'Plus Jakarta Sans', sans-serif; font-size: 13.5px;">
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 10px; border-top: 1.5px solid #f1f5f9; padding-top: 18px;">
                    <button type="button" onclick="document.getElementById('editModal').style.display='none'" style="padding: 10px 18px; background: #f1f5f9; color: #475569; border: 1.5px solid #e2e8f0; border-radius: 10px; cursor: pointer; font-weight: 700; font-family: 'Plus Jakarta Sans', sans-serif;">Cancel</button>
                    <button type="submit" style="padding: 10px 22px; background: linear-gradient(135deg, #10b981, #059669); color: white; border: none; border-radius: 10px; cursor: pointer; font-weight: 700; font-family: 'Plus Jakarta Sans', sans-serif; box-shadow: 0 2px 8px rgba(16, 185, 129, 0.25);"><i class="fa-solid fa-save"></i> Save Changes</button>
                </div>
            </form>
        </div>
    </div>

<script src="../style/paginate.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    paginateTable('disasterHistoryTbody', 'disasterHistoryPagination', 10);
    paginateTable('reliefHistoryTbody', 'reliefHistoryPagination', 10);
});
</script>
</body>
</html>
