<?php
session_start();

if (!isset($_SESSION['user']) || !isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

require_once '../config/database.php';
require_once '../classes/DisasterModel.php';
require_once '../classes/ResidentModel.php';
require_once '../classes/PackageModel.php';
require_once '../classes/DistributionModel.php';

$db = new Database();
$conn = $db->connect();
$disasterModel = new DisasterModel($conn);
$residentModel = new ResidentModel($conn);
$packageModel = new PackageModel($conn);
$distributionModel = new DistributionModel($conn);

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: disasters.php');
    exit;
}

$id = $_GET['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_disaster'])) {
    if (isset($_SESSION['role_id']) && $_SESSION['role_id'] == 1) {
        $edit_id = $_POST['disaster_id'];
        $type_id = $_POST['disaster_type_id'];
        $date = $_POST['date'];
        $status_id = $_POST['status_id'];
        $package_id = !empty($_POST['package_id']) ? $_POST['package_id'] : null;
        $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
        
        $disasterModel->updateDisaster($edit_id, $type_id, $date, $end_date, $status_id, $package_id);
        header("Location: view_disaster.php?id=$edit_id");
        exit;
    }
}

// Handle adding victim (Open to Admin and Staff)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_victim'])) {
    if (isset($_SESSION['user_id'])) {
        $dis_id = $_POST['disaster_id'];
        
        // Security check: Prevent adding victims if status is Pending Assessment
        $disInfo = $disasterModel->getDisasterById($dis_id);
        if ($disInfo['status'] === 'Pending Assessment') {
            header("Location: view_disaster.php?id=$dis_id&error=pending_assessment");
            exit;
        }

        $cond_id = 1; // Default condition, not tracked in relief distribution context
        $res_id = null;

        // Check if we are creating a new resident
        if (isset($_POST['is_new_resident']) && $_POST['is_new_resident'] == '1') {
            $name = trim($_POST['new_name'] ?? '');
            $age  = isset($_POST['new_age']) && $_POST['new_age'] !== '' ? max(0, min(120, (int)$_POST['new_age'])) : null;
            $brgy_id = $_POST['new_barangay_id'];
            $addr = trim($_POST['new_address'] ?? '');
            $cont = trim($_POST['new_contact'] ?? '');

            if (empty($name)) {
                header("Location: view_disaster.php?id=$dis_id&error=missing_name");
                exit;
            }
            
            $res_id = $residentModel->addResident($name, $age, $brgy_id, $addr, $cont);
            // ResidentModel->addResident currently returns boolean, I should update it to return lastInsertId or fetch it
            if ($res_id) {
                // Since I modified addResident to return execute result, I need to get the ID
                $res_id = $conn->lastInsertId();
            }
        } else {
            $res_id = $_POST['resident_id'];
        }
        
        if ($res_id) {
            $success = $disasterModel->addResidentDisaster($res_id, $dis_id, $cond_id);
            if ($success) {
                header("Location: view_disaster.php?id=$dis_id&success=added");
                exit;
            } else {
                header("Location: view_disaster.php?id=$dis_id&error=exists");
                exit;
            }
        }
        
        header("Location: view_disaster.php?id=$dis_id");
        exit;
    }
}

// Handle deleting victim
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_victim'])) {
    if (isset($_SESSION['role_id']) && $_SESSION['role_id'] == 1) {
        $res_id = $_POST['resident_id'];
        $dis_id = $_POST['disaster_id'];
        
        $disasterModel->removeResidentDisaster($res_id, $dis_id);
        header("Location: view_disaster.php?id=$dis_id");
        exit;
    }
}


// Handle batch distribution
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['batch_distribute'])) {
    if (isset($_SESSION['user_id'])) {
        $dis_id = $_POST['disaster_id'];
        $selected_residents = $_POST['selected_residents'] ?? [];
        $user_id = $_SESSION['user_id'];
        $date_distributed = date('Y-m-d');
        
        if (empty($selected_residents)) {
            header("Location: view_disaster.php?id=$dis_id&error=no_selection");
            exit;
        }

        $disasterInfo = $disasterModel->getDisasterById($dis_id);
        $package_id = $disasterInfo['package_id'];
        
        if (empty($package_id)) {
            header("Location: view_disaster.php?id=$dis_id&error=no_package");
            exit;
        }

        $package = $packageModel->getPackageById($package_id);
        $total_needed = count($selected_residents);
        
        if ($package && $package['stock'] >= $total_needed) {
            foreach ($selected_residents as $res_id) {
                $distributionModel->addDistribution($res_id, $package_id, $user_id, 1, $date_distributed);
            }
            header("Location: view_disaster.php?id=$dis_id&success=batch_dist");
            exit;
        } else {
            header("Location: view_disaster.php?id=$dis_id&error=stock");
            exit;
        }
    }
}

// Handle assigning package directly from view
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_package'])) {
    if (isset($_SESSION['role_id']) && $_SESSION['role_id'] == 1) {
        $dis_id = $_POST['disaster_id'];
        $package_id = $_POST['package_id'];
        $disasterModel->assignDisasterPackage($dis_id, $package_id);
        header("Location: view_disaster.php?id=$dis_id&success=package_assigned");
        exit;
    }
}

$disaster = $disasterModel->getDisasterById($id);

if (!$disaster) {
    header('Location: disasters.php');
    exit;
}

$impacts = $disasterModel->getDisasterImpacts($id);
$allDisasterResidents = $disasterModel->getDisasterResidents($id);
$distributedResidents = $disasterModel->getDistributedResidents($id);

$distributedIds = array_column($distributedResidents, 'resident_id');
$residents = array_filter($allDisasterResidents, function($r) use ($distributedIds) {
    return !in_array($r['resident_id'], $distributedIds);
});

$allTypes = $disasterModel->getAllDisasterTypes();
$allStatuses = $disasterModel->getAllStatuses();
$allConditions = $disasterModel->getAllConditions();
$allResidents = $residentModel->getAllResidents();
$allBarangays = $disasterModel->getAllBarangays();
$allPackages = $packageModel->getAllPackages();

// Filter residents who are NOT already in the victim list for this disaster (including distributed ones)
$existingResidentIds = array_column($allDisasterResidents, 'resident_id');
$availableResidents = array_filter($allResidents, function($r) use ($existingResidentIds) {
    return !in_array($r['resident_id'], $existingResidentIds);
});
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Disaster - Disaster Relief Tracker</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="../style/dashboard.css">
    <link rel="stylesheet" href="../style/tables.css">
    <link rel="stylesheet" href="../style/view_pages.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>

   <?php $activePage = 'disasters'; $sidebarBase = '../pages/'; include '../includes/sidebar.php'; ?>

    <div class="main">
        <div class="header header-flex">
            <div>
                <h1>Disaster Details: ID# <?php echo $disaster['disaster_id']; ?></h1>
                <p>Comprehensive overview of this specific disaster event.</p>
            </div>
            <div style="display: flex; gap: 10px;">
                <?php if (isset($_SESSION['role_id']) && $_SESSION['role_id'] == 1): ?>
                <button onclick="document.getElementById('editModal').style.display='block'" class="btn-back" style="background: #3498db; color: white; border: none;">
                    <i class="fa-solid fa-pen"></i> Edit Disaster
                </button>
                <?php endif; ?>
                <a href="../pages/disasters.php" class="btn-back">
                    <i class="fa-solid fa-chevron-left"></i> Back
                </a>
            </div>
        </div>

        <?php if (isset($_GET['success']) && $_GET['success'] == 'added'): ?>
            <div class="alert alert-success" style="margin-top: 20px;">
                <i class="fa-solid fa-circle-check"></i> Resident added successfully as a victim.
            </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['success']) && $_GET['success'] == 'package_assigned'): ?>
            <div class="alert alert-success" style="margin-top: 20px;">
                <i class="fa-solid fa-circle-check"></i> Associated relief package updated successfully.
            </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['success']) && $_GET['success'] == 'batch_dist'): ?>
            <div class="alert alert-success" style="margin-top: 20px;">
                <i class="fa-solid fa-circle-check"></i> Relief packages successfully distributed to the selected residents.
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['error']) && $_GET['error'] == 'exists'): ?>
            <div class="alert alert-error" style="margin-top: 20px;">
                <i class="fa-solid fa-circle-xmark"></i> Error: This resident is already added as a victim for this disaster.
            </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['error']) && $_GET['error'] == 'no_selection'): ?>
            <div class="alert alert-error" style="margin-top: 20px;">
                <i class="fa-solid fa-circle-xmark"></i> Error: No residents were selected for distribution.
            </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['error']) && $_GET['error'] == 'no_package'): ?>
            <div class="alert alert-error" style="margin-top: 20px;">
                <i class="fa-solid fa-circle-xmark"></i> Error: No relief package is associated with this disaster. Please assign one first.
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['error']) && $_GET['error'] == 'pending_assessment'): ?>
            <div class="alert alert-error" style="margin-top: 20px;">
                <i class="fa-solid fa-lock"></i> Error: You cannot add residents while the status is still "Pending Assessment". Please update the status to "Ongoing" first.
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['error']) && $_GET['error'] == 'missing_name'): ?>
            <div class="alert alert-error" style="margin-top: 20px;">
                <i class="fa-solid fa-circle-xmark"></i> Error: Resident full name is required.
            </div>
        <?php endif; ?>

        <div class="cards-container">
            
            <!-- Summary Card -->
            <div class="detail-card">
                <h3><i class="fa-solid fa-circle-info"></i> General Information</h3>
                
                <div class="info-grid">
                    <div>
                        <p class="info-label">Type of Disaster</p>
                        <p class="info-value"><i class="fa-solid fa-triangle-exclamation"></i> <?php echo htmlspecialchars($disaster['type']); ?></p>
                    </div>
                    <div>
                        <p class="info-label">Date Started</p>
                        <p class="info-value">
                            <i class="fa-solid fa-calendar-day"></i> 
                            <?php 
                                if ($disaster['status'] == 'Pending Assessment' || empty($disaster['date'])) {
                                    echo '<span style="color: #94a3b8; font-style: italic;">Pending Assessment</span>';
                                } else {
                                    echo date('M d, Y', strtotime($disaster['date'])); 
                                }
                            ?>
                        </p>
                        <?php if (!empty($disaster['end_date'])): ?>
                            <p class="info-value" style="color: #e74c3c; font-size: 13px;"><i class="fa-solid fa-flag-checkered"></i> Ended: <?php echo date('M d, Y', strtotime($disaster['end_date'])); ?></p>
                        <?php endif; ?>
                    </div>
                    <div>
                        <p class="info-label">Relief Status</p>
                        <p style="margin-top: 5px;">
                            <?php 
                                $badgeClass = 'status-resolved';
                                if ($disaster['status'] == 'Ongoing') {
                                    $badgeClass = 'status-ongoing';
                                } elseif ($disaster['status'] == 'Pending Assessment') {
                                    $badgeClass = 'status-pending';
                                }
                            ?>
                            <span class="status-badge <?php echo $badgeClass; ?>">
                                <?php if($disaster['status'] == 'Ongoing'): ?>
                                    <i class="fa-solid fa-spinner" style="margin-right: 5px;"></i>
                                <?php endif; ?>
                                <?php echo htmlspecialchars($disaster['status']); ?>
                            </span>
                        </p>
                    </div>
                    <div>
                        <p class="info-label">Associated Package</p>
                        <p class="info-value">
                            <i class="fa-solid fa-box"></i> 
                            <?php echo !empty($disaster['package_name']) ? htmlspecialchars($disaster['package_name']) : '<span style="color:#94a3b8; font-style: italic;">None</span>'; ?>
                        </p>
                    </div>
                    <div style="grid-column: span 2;">
                        <p class="info-label">Total Estimated Affected</p>
                        <p class="info-value"><i class="fa-solid fa-users"></i> <?php echo htmlspecialchars($disaster['total_affected']); ?> Individuals</p>
                    </div>
                </div>
            </div>

            <!-- Impacted Barangays Card -->
            <div class="detail-card">
                <h3><i class="fa-solid fa-map-location-dot"></i> Impact Zones (Barangays)</h3>
                
                <?php if (count($impacts) > 0): ?>
                <ul class="impact-list">
                    <?php foreach ($impacts as $imp): ?>
                    <li>
                        <span class="impact-label"><i class="fa-solid fa-location-dot" style="margin-right: 8px;"></i> <?php echo htmlspecialchars($imp['barangay_name']); ?></span>
                        <span class="impact-value">
                            <?php echo htmlspecialchars($imp['affected_residents']); ?> affected
                        </span>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fa-solid fa-map"></i>
                        <p>No specific barangays linked to this event yet.</p>
                    </div>
                <?php endif; ?>
            </div>

        </div>

        <div class="table-container" style="margin-top: 2rem;">
            <div class="table-header-flex">
                <h2 style="font-size: 1.2rem; display: flex; align-items: center; gap: 10px;"><i class="fa-solid fa-users"></i> Specifically Targeted Residents (Victims)</h2>
                <div style="display: flex; gap: 10px;">
                    <?php if (isset($_SESSION['role_id']) && $_SESSION['role_id'] == 1): ?>
                    <button onclick="document.getElementById('assignPackageModal').style.display='block'" class="btn-back" style="background: #8b5cf6; color: white; border: none;">
                        <i class="fa-solid fa-box-open"></i> Assign Relief Package
                    </button>
                    <?php endif; ?>
                    <?php if ($disaster['status'] !== 'Pending Assessment'): ?>
                        <button onclick="document.getElementById('addVictimModal').style.display='block'" class="btn-add-victim">
                            <i class="fa-solid fa-plus"></i> Add Affected Resident
                        </button>
                    <?php else: ?>
                        <button class="btn-add-victim" style="background: #94a3b8; cursor: not-allowed; opacity: 0.7;" title="Complete assessment first to add residents" disabled>
                            <i class="fa-solid fa-lock"></i> Add Affected Resident
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <form id="batchDistributeForm" method="POST" action="view_disaster.php?id=<?php echo $id; ?>">
                <input type="hidden" name="batch_distribute" value="1">
                <input type="hidden" name="disaster_id" value="<?php echo $id; ?>">
            </form>

            <div style="margin-bottom: 10px;">
                <button type="submit" form="batchDistributeForm" class="btn-back" style="background: #10b981; color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer;" onclick="return confirm('Distribute 1 package unit to all selected residents?');">
                    <i class="fa-solid fa-truck-fast"></i> Distribute to Selected
                </button>
            </div>

            <table>
                <thead>
                <tr>
                    <th style="width: 40px; text-align: center;"><input type="checkbox" id="selectAllResidents" onclick="toggleSelectAll(this)"></th>
                    <th>Res ID</th>
                    <th>Name</th>
                    <th>Age</th>
                    <th>Barangay</th>
                    <th>Contact</th>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody id="victimsTableBody">
                <?php foreach ($residents as $res): ?>
                <tr>
                    <td style="text-align: center;"><input type="checkbox" name="selected_residents[]" value="<?php echo htmlspecialchars($res['resident_id']); ?>" class="resident-checkbox" form="batchDistributeForm"></td>
                    <td>#<?php echo htmlspecialchars($res['resident_id']); ?></td>
                    <td><strong style="color: #2c3e50;"><?php echo htmlspecialchars($res['name']); ?></strong></td>
                    <td><?php echo htmlspecialchars($res['age'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($res['barangay_name'] ?? 'Unknown'); ?></td>
                    <td><?php echo htmlspecialchars($res['contact'] ?? 'N/A'); ?></td>
                    <td>
                        <div class="actions-cell">
                            <a href="../actions/view_resident.php?id=<?php echo $res['resident_id']; ?>" class="btn-table-view" title="View Resident Profile">
                                <i class="fa-solid fa-eye"></i> View
                            </a>
                            <?php if (isset($_SESSION['role_id']) && $_SESSION['role_id'] == 1): ?>
                            <form method="POST" onsubmit="return confirm('Remove this resident from the victim list?');" style="display:inline;">
                                <input type="hidden" name="delete_victim" value="1">
                                <input type="hidden" name="resident_id" value="<?php echo $res['resident_id']; ?>">
                                <input type="hidden" name="disaster_id" value="<?php echo $id; ?>">
                                <button type="submit" class="btn-table-delete" title="Remove Resident">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <div id="victimsTablePagination" class="pagination-controls"></div>

            
            <?php if (count($residents) == 0): ?>
                <div class="empty-state">
                    <i class="fa-solid fa-user-injured"></i>
                    <p>No specific individuals have been registered for this disaster event.</p>
                </div>
            <?php endif; ?>
        </div>

        <div class="table-container" style="margin-top: 2rem;">
            <div class="table-header-flex">
                <h2 style="font-size: 1.2rem; display: flex; align-items: center; gap: 10px;"><i class="fa-solid fa-check-circle" style="color: #10b981;"></i> Distributed Residents</h2>
            </div>

            <table>
                <thead>
                <tr>
                    <th>Res ID</th>
                    <th>Name</th>
                    <th>Barangay</th>
                    <th>Total Received</th>
                    <th>Latest Distribution</th>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody id="distributedTableBody">
                <?php foreach ($distributedResidents as $dres): ?>
                <tr>
                    <td>#<?php echo htmlspecialchars($dres['resident_id']); ?></td>
                    <td><strong style="color: #2c3e50;"><?php echo htmlspecialchars($dres['name']); ?></strong></td>
                    <td><?php echo htmlspecialchars($dres['barangay_name'] ?? 'Unknown'); ?></td>
                    <td><span class="condition-badge condition-safe"><?php echo htmlspecialchars($dres['total_received']); ?> units</span></td>
                    <td><?php echo htmlspecialchars(date('M d, Y', strtotime($dres['latest_distribution']))); ?></td>
                    <td>
                        <a href="../actions/view_resident.php?id=<?php echo $dres['resident_id']; ?>" class="btn-table-view" title="View Resident Profile">
                            <i class="fa-solid fa-eye"></i> View Profile
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <div id="distributedTablePagination" class="pagination-controls"></div>
            
            <?php if (count($distributedResidents) == 0): ?>
                <div class="empty-state">
                    <i class="fa-solid fa-box-open"></i>
                    <p>No relief packages have been distributed for this disaster yet.</p>
                </div>
            <?php endif; ?>
        </div>

    </div>

    <!-- Edit Disaster Modal -->
    <div id="editModal" class="modal-overlay">
        <div class="modal-content" style="width:450px;">
            <h2 style="margin-top:0; color: #0f172a; border-bottom: 2px solid #f1f5f9; padding-bottom: 15px; font-size: 18px;">Edit Disaster Details</h2>
            <form method="POST">
                <input type="hidden" name="edit_disaster" value="1">
                <input type="hidden" name="disaster_id" value="<?php echo $disaster['disaster_id']; ?>">
                
                <div style="margin-bottom: 15px;">
                    <label style="font-size: 13px; font-weight: 700; color: #475569; display: block; margin-bottom: 6px;">Type of Disaster:</label>
                    <select name="disaster_type_id" required style="width: 100%; padding: 10px 14px; border-radius: 10px; border: 1.5px solid #e2e8f0; font-family: 'Plus Jakarta Sans', sans-serif; font-size: 13.5px;">
                        <?php foreach($allTypes as $type): ?>
                            <option value="<?php echo $type['disaster_type_id']; ?>" <?php echo ($type['disaster_type_id'] == $disaster['disaster_type_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($type['type_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div id="startDateDiv" style="margin-bottom: 15px; display: <?php echo ($disaster['status'] == 'Pending Assessment') ? 'none' : 'block'; ?>;">
                    <label style="font-size: 13px; font-weight: 700; color: #475569; display: block; margin-bottom: 6px;">Date Started:</label>
                    <input type="date" name="date" id="startDateInput" value="<?php echo htmlspecialchars($disaster['date'] ?? ''); ?>" style="width: 100%; padding: 10px 14px; border-radius: 10px; border: 1.5px solid #e2e8f0; font-family: 'Plus Jakarta Sans', sans-serif; font-size: 13.5px;">
                </div>
                
                <div id="pendingDateInfo" style="margin-bottom: 15px; display: <?php echo ($disaster['status'] == 'Pending Assessment') ? 'block' : 'none'; ?>;">
                    <label style="font-size: 13px; font-weight: 700; color: #475569; display: block; margin-bottom: 6px;">Date Started:</label>
                    <div style="padding: 10px 14px; border-radius: 10px; border: 1.5px solid #e2e8f0; background-color: #f8fafc; color: #64748b; font-size: 13.5px; font-style: italic;">
                        <i class="fa-solid fa-clock-rotate-left" style="margin-right: 8px;"></i> Automatically set when relief starts
                    </div>
                </div>

                <div style="margin-bottom: 15px;">
                    <label style="font-size: 13px; font-weight: 700; color: #475569; display: block; margin-bottom: 6px;">Associated Relief Package (Optional):</label>
                    <select name="package_id" style="width: 100%; padding: 10px 14px; border-radius: 10px; border: 1.5px solid #e2e8f0; font-family: 'Plus Jakarta Sans', sans-serif; font-size: 13.5px;">
                        <option value="">-- No Specific Package --</option>
                        <?php foreach ($allPackages as $pkg): ?>
                            <option value="<?php echo $pkg['package_id']; ?>" <?php echo ($pkg['package_id'] == $disaster['package_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($pkg['package_name']); ?> (Stock: <?php echo $pkg['stock']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="margin-bottom: 15px;">
                    <label style="font-size: 13px; font-weight: 700; color: #475569; display: block; margin-bottom: 6px;">Relief Status:</label>
                    <select name="status_id" id="statusSelect" required style="width: 100%; padding: 10px 14px; border-radius: 10px; border: 1.5px solid #e2e8f0; font-family: 'Plus Jakarta Sans', sans-serif; font-size: 13.5px;" onchange="updateFormStates()">
                        <?php foreach($allStatuses as $st): ?>
                            <option value="<?php echo $st['status_id']; ?>" <?php echo ($st['status_id'] == $disaster['status_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($st['status_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div id="endDateDiv" style="margin-bottom: 20px; display: <?php echo ($disaster['status'] == 'Resolved') ? 'block' : 'none'; ?>;">
                    <label style="font-size: 13px; font-weight: 700; color: #475569; display: block; margin-bottom: 6px;">Date Ended:</label>
                    <div id="resolvedDateDisplay" style="padding: 10px 14px; border-radius: 10px; border: 1.5px solid #e2e8f0; background-color: #f8fafc; color: #64748b; font-size: 13.5px; font-style: italic;">
                        <?php if (!empty($disaster['end_date'])): ?>
                            <i class="fa-solid fa-flag-checkered" style="margin-right: 8px;"></i> <?php echo date('M d, Y', strtotime($disaster['end_date'])); ?>
                        <?php else: ?>
                            <i class="fa-solid fa-flag-checkered" style="margin-right: 8px;"></i> Automatically set when resolved
                        <?php endif; ?>
                    </div>
                    <input type="hidden" name="end_date" id="endDateInput" value="<?php echo htmlspecialchars($disaster['end_date'] ?? ''); ?>">
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 10px; border-top: 1.5px solid #f1f5f9; padding-top: 18px;">
                    <button type="button" onclick="document.getElementById('editModal').style.display='none'" style="padding: 10px 18px; background: #f1f5f9; color: #475569; border: 1.5px solid #e2e8f0; border-radius: 10px; cursor: pointer; font-weight: 700; font-family: 'Plus Jakarta Sans', sans-serif;">Cancel</button>
                    <button type="submit" style="padding: 10px 22px; background: linear-gradient(135deg, #10b981, #059669); color: white; border: none; border-radius: 10px; cursor: pointer; font-weight: 700; font-family: 'Plus Jakarta Sans', sans-serif; box-shadow: 0 2px 8px rgba(16, 185, 129, 0.25);"><i class="fa-solid fa-save"></i> Save Changes</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Add Victim Modal -->
    <div id="addVictimModal" class="modal-overlay">
        <div class="modal-content" style="width:500px;">
            <h2 style="margin-top:0; color: #0f172a; border-bottom: 2px solid #f1f5f9; padding-bottom: 15px; font-size: 18px;">Add Affected Resident</h2>
            <form method="POST">
                <input type="hidden" name="add_victim" value="1">
                <input type="hidden" name="disaster_id" value="<?php echo $id; ?>">
                
                <!-- Toggle Section -->
                <div style="margin-bottom: 20px; background: #f8fafc; padding: 12px; border-radius: 10px; display: flex; gap: 20px; border: 1.5px solid #e2e8f0;">
                    <label style="cursor: pointer; display: flex; align-items: center; gap: 8px; font-weight: bold; font-size: 14px;">
                        <input type="radio" name="is_new_resident" value="0" checked onclick="toggleResidentSource(false)"> Existing Resident
                    </label>
                    <label style="cursor: pointer; display: flex; align-items: center; gap: 8px; font-weight: bold; font-size: 14px; color: #e67e22;">
                        <input type="radio" name="is_new_resident" value="1" onclick="toggleResidentSource(true)"> New Resident
                    </label>
                </div>

                <!-- Existing Resident Dropdown -->
                <div id="existingResidentSection" style="margin-bottom: 15px;">
                    <label style="font-size: 13px; font-weight: 700; color: #475569; display: block; margin-bottom: 6px;">Select Resident:</label>
                    <select name="resident_id" id="residentDropdown" required style="width: 100%; padding: 10px 14px; border-radius: 10px; border: 1.5px solid #e2e8f0; font-family: 'Plus Jakarta Sans', sans-serif; font-size: 13.5px;">
                        <option value="">-- Choose Resident --</option>
                        <?php foreach($availableResidents as $r): ?>
                            <option value="<?php echo $r['resident_id']; ?>">
                                <?php echo htmlspecialchars($r['name']); ?> (<?php echo htmlspecialchars($r['barangay_name']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- New Resident Fields -->
                <div id="newResidentSection" style="display: none; border: 1.5px solid #fed7aa; padding: 18px; border-radius: 12px; margin-bottom: 15px; background: #fff7ed;">
                    <h4 style="margin: 0 0 12px 0; color: #ea580c; font-size: 14px;"><i class="fa-solid fa-user-plus"></i> Register New Resident</h4>
                    
                    <div style="margin-bottom: 12px;">
                        <label style="font-size: 12px; font-weight: 700; color: #475569; display: block; margin-bottom: 4px;">Full Name:</label>
                        <input type="text" name="new_name" id="newName" required placeholder="e.g. Juan Dela Cruz" style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1.5px solid #fdba74; font-family: 'Plus Jakarta Sans', sans-serif; font-size: 13px;">
                    </div>

                    <div style="display: flex; gap: 15px; margin-bottom: 12px;">
                        <div style="flex: 1;">
                            <label style="font-size: 12px; font-weight: 700; color: #475569; display: block; margin-bottom: 4px;">Age:</label>
                            <input type="number" name="new_age" id="newAge" min="0" max="120" placeholder="Age" style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1.5px solid #fdba74; font-family: 'Plus Jakarta Sans', sans-serif; font-size: 13px;">
                        </div>
                        <div style="flex: 2;">
                            <label style="font-size: 12px; font-weight: 700; color: #475569; display: block; margin-bottom: 4px;">Contact:</label>
                            <input type="text" name="new_contact" id="newContact" placeholder="e.g. 09123456789" pattern="[0-9\-\+\s]{7,15}" title="Enter a valid phone number (7-15 digits)" style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1.5px solid #fdba74; font-family: 'Plus Jakarta Sans', sans-serif; font-size: 13px;">
                        </div>
                    </div>

                    <div style="margin-bottom: 12px;">
                        <label style="font-size: 12px; font-weight: 700; color: #475569; display: block; margin-bottom: 4px;">Barangay (Auto-detected):</label>
                        <?php if (count($impacts) == 1): ?>
                            <!-- Single barangay -->
                            <input type="text" value="<?php echo htmlspecialchars($impacts[0]['barangay_name']); ?>" readonly style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1.5px solid #fed7aa; background: #ffedd5; font-family: 'Plus Jakarta Sans', sans-serif; font-size: 13px;">
                            <input type="hidden" name="new_barangay_id" id="newBarangay" value="<?php echo $impacts[0]['barangay_id']; ?>">
                        <?php elseif (count($impacts) > 1): ?>
                            <!-- Multiple barangays -->
                            <select name="new_barangay_id" id="newBarangay" style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1.5px solid #fdba74; font-family: 'Plus Jakarta Sans', sans-serif; font-size: 13px;">
                                <?php foreach ($impacts as $imp): ?>
                                    <option value="<?php echo $imp['barangay_id']; ?>"><?php echo htmlspecialchars($imp['barangay_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php else: ?>
                            <!-- Fallback -->
                            <select name="new_barangay_id" id="newBarangay" style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1.5px solid #fdba74; font-family: 'Plus Jakarta Sans', sans-serif; font-size: 13px;">
                                <option value="">-- Select Barangay --</option>
                                <?php foreach ($allBarangays as $b): ?>
                                    <option value="<?php echo $b['barangay_id']; ?>"><?php echo htmlspecialchars($b['barangay_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                    </div>

                    <div style="margin-bottom: 0;">
                        <label style="font-size: 12px; font-weight: 700; color: #475569; display: block; margin-bottom: 4px;">Address:</label>
                        <input type="text" name="new_address" id="newAddress" style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1.5px solid #fdba74; font-family: 'Plus Jakarta Sans', sans-serif; font-size: 13px;">
                    </div>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 10px; border-top: 1.5px solid #f1f5f9; padding-top: 18px;">
                    <button type="button" onclick="document.getElementById('addVictimModal').style.display='none'" style="padding: 10px 18px; background: #f1f5f9; color: #475569; border: 1.5px solid #e2e8f0; border-radius: 10px; cursor: pointer; font-weight: 700; font-family: 'Plus Jakarta Sans', sans-serif;">Cancel</button>
                    <button type="submit" style="padding: 10px 22px; background: linear-gradient(135deg, #dc2626, #b91c1c); color: white; border: none; border-radius: 10px; cursor: pointer; font-weight: 700; font-family: 'Plus Jakarta Sans', sans-serif; box-shadow: 0 2px 8px rgba(220, 38, 38, 0.25);"><i class="fa-solid fa-plus"></i> Add to List</button>
                </div>
            </form>
        </div>
    </div>


    <!-- Assign Package Modal -->
    <div id="assignPackageModal" class="modal-overlay">
        <div class="modal-content" style="width:400px;">
            <h2 style="margin-top:0; color: #0f172a; border-bottom: 2px solid #f1f5f9; padding-bottom: 15px; font-size: 18px;">Assign Relief Package</h2>
            <form method="POST">
                <input type="hidden" name="assign_package" value="1">
                <input type="hidden" name="disaster_id" value="<?php echo $id; ?>">
                
                <div style="margin-bottom: 20px;">
                    <label style="font-size: 13px; font-weight: 700; color: #475569; display: block; margin-bottom: 6px;">Select Package to Distribute Here:</label>
                    <select name="package_id" style="width: 100%; padding: 10px 14px; border-radius: 10px; border: 1.5px solid #e2e8f0; font-family: 'Plus Jakarta Sans', sans-serif; font-size: 13.5px;">
                        <option value="">-- No Specific Package --</option>
                        <?php foreach ($allPackages as $pkg): ?>
                            <option value="<?php echo $pkg['package_id']; ?>" <?php echo ($pkg['package_id'] == $disaster['package_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($pkg['package_name']); ?> (Stock: <?php echo $pkg['stock']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div style="display: flex; justify-content: flex-end; gap: 10px; border-top: 1.5px solid #f1f5f9; padding-top: 18px;">
                    <button type="button" onclick="document.getElementById('assignPackageModal').style.display='none'" style="padding: 10px 18px; background: #f1f5f9; color: #475569; border: 1.5px solid #e2e8f0; border-radius: 10px; cursor: pointer; font-weight: 700; font-family: 'Plus Jakarta Sans', sans-serif;">Cancel</button>
                    <button type="submit" style="padding: 10px 22px; background: linear-gradient(135deg, #8b5cf6, #7c3aed); color: white; border: none; border-radius: 10px; cursor: pointer; font-weight: 700; font-family: 'Plus Jakarta Sans', sans-serif; box-shadow: 0 2px 8px rgba(139, 92, 246, 0.25);"><i class="fa-solid fa-save"></i> Save Assignment</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function toggleSelectAll(source) {
        checkboxes = document.getElementsByClassName('resident-checkbox');
        for(var i=0, n=checkboxes.length; i<n; i++) {
            checkboxes[i].checked = source.checked;
        }
    }
    function toggleResidentSource(isNew) {
        const existingSec = document.getElementById('existingResidentSection');
        const newSec = document.getElementById('newResidentSection');
        const resDropdown = document.getElementById('residentDropdown');
        const newName = document.getElementById('newName');
        const newBarangay = document.getElementById('newBarangay');

        if (isNew) {
            existingSec.style.display = 'none';
            newSec.style.display = 'block';
            resDropdown.required = false;
            newName.required = true;
            if (newBarangay.tagName === 'SELECT') newBarangay.required = true;
        } else {
            existingSec.style.display = 'block';
            newSec.style.display = 'none';
            resDropdown.required = true;
            newName.required = false;
            if (newBarangay.tagName === 'SELECT') newBarangay.required = false;
        }
    }
    </script>

    <script src="../style/paginate.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        paginateTable('victimsTableBody', 'victimsTablePagination', 10);
        paginateTable('distributedTableBody', 'distributedTablePagination', 10);
    });

    function updateFormStates() {
        var statusSelect = document.getElementById("statusSelect");
        var selectedStatus = statusSelect.options[statusSelect.selectedIndex].text.trim();
        
        var endDateDiv = document.getElementById("endDateDiv");
        var endDateInput = document.getElementById("endDateInput");
        var startDateDiv = document.getElementById("startDateDiv");
        var pendingDateInfo = document.getElementById("pendingDateInfo");
        var startDateInput = document.getElementById("startDateInput");
        
        // Handle Resolved state
        if (selectedStatus === "Resolved") {
            endDateDiv.style.display = "block";
        } else {
            endDateDiv.style.display = "none";
        }

        // Handle Pending Assessment state
        if (selectedStatus === "Pending Assessment") {
            startDateDiv.style.display = "none";
            pendingDateInfo.style.display = "block";
            startDateInput.required = false;
        } else {
            startDateDiv.style.display = "block";
            pendingDateInfo.style.display = "none";
            if (selectedStatus !== "Pending Assessment") {
                // If it's already set or being changed to Ongoing, we might want it required
                // But the model handles the auto-set for Ongoing, so we can leave it optional if it's currently Pending
            }
        }
    }
    
    window.onload = function() {
        updateFormStates();
    };
    </script>

</body>
</html>
