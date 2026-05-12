<?php
session_start();

if (!isset($_SESSION['user']) || !isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

require_once '../config/database.php';
require_once '../classes/DistributionModel.php';

$db = new Database();
$conn = $db->connect();
$distributionModel = new DistributionModel($conn);

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: ../pages/distribution.php');
    exit;
}

$id = $_GET['id'];
$distribution = $distributionModel->getDistributionById($id);

if (!$distribution) {
    header('Location: ../pages/distribution.php');
    exit;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Distribution - Disaster Relief Tracker</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="../style/dashboard.css">
    <link rel="stylesheet" href="../style/tables.css">
    <link rel="stylesheet" href="../style/view_pages.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>

   <?php $activePage = 'distribution'; $sidebarBase = '../pages/'; include '../includes/sidebar.php'; ?>
</div>

    <div class="main">
        <div class="header header-flex">
            <div>
                <h1>Distribution Details: ID#<?php echo $distribution['distribution_id']; ?></h1>
                <p>Recorded on <?php echo date('F d, Y', strtotime($distribution['date_distributed'])); ?></p>
            </div>
            <a href="../pages/distribution.php" class="btn-back">
                <i class="fa-solid fa-chevron-left"></i> Back
            </a>
        </div>

        <div class="cards-container">
            
            <!-- Resident Info Card -->
            <div class="detail-card">
                <h3><i class="fa-solid fa-user"></i> Recipient Information</h3>
                
                <div class="info-grid">
                    <div>
                        <p class="info-label">Name</p>
                        <p class="info-value"><i class="fa-solid fa-user" style="margin-right: 5px;"></i> <?php echo htmlspecialchars($distribution['resident_name']); ?></p>
                    </div>
                    <div>
                        <p class="info-label">Barangay</p>
                        <p class="info-value"><i class="fa-solid fa-location-dot" style="margin-right: 5px;"></i> <?php echo htmlspecialchars($distribution['barangay_name']); ?></p>
                    </div>
                    <div>
                        <p class="info-label">Contact</p>
                        <p class="info-value"><i class="fa-solid fa-phone" style="margin-right: 5px;"></i> <?php echo htmlspecialchars($distribution['resident_contact'] ?? 'N/A'); ?></p>
                    </div>
                    <div>
                        <p class="info-label">Address</p>
                        <p class="info-value"><i class="fa-solid fa-map-pin" style="margin-right: 5px;"></i> <?php echo htmlspecialchars($distribution['resident_address'] ?? 'N/A'); ?></p>
                    </div>
                </div>
            </div>

            <!-- Package Info Card -->
            <div class="detail-card">
                <h3><i class="fa-solid fa-box-open"></i> Package Details</h3>
                
                <div class="info-grid">
                    <div>
                        <p class="info-label">Package Name</p>
                        <p class="info-value"><i class="fa-solid fa-box" style="margin-right: 5px;"></i> <?php echo htmlspecialchars($distribution['package_name']); ?></p>
                    </div>
                    <div>
                        <p class="info-label">Quantity Released</p>
                        <p class="info-value"><i class="fa-solid fa-list-ol" style="margin-right: 5px;"></i> <?php echo htmlspecialchars($distribution['quantity']); ?> Units</p>
                    </div>
                    <div style="grid-column: span 2;">
                        <p class="info-label">Description</p>
                        <p style="font-size: 14px; margin: 5px 0 0 0; color: #475569;"><?php echo htmlspecialchars($distribution['package_description'] ?? 'No description available.'); ?></p>
                    </div>
                    <div style="grid-column: span 2; margin-top: 4px; padding-top: 14px; border-top: 1px solid #f1f5f9;">
                        <p class="info-label">Distributed By</p>
                        <p class="info-value"><i class="fa-solid fa-user-check" style="margin-right: 5px;"></i> <?php echo htmlspecialchars($distribution['distributor_name']); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

</body>
</html>
