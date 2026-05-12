<?php
session_start();

if (!isset($_SESSION['user']) || !isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

require_once '../config/database.php';
require_once '../classes/PackageModel.php';

$db = new Database();
$conn = $db->connect();
$packageModel = new PackageModel($conn);

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: ../pages/packages.php');
    exit;
}

$id = $_GET['id'];
$package = $packageModel->getPackageById($id);

if (!$package) {
    header('Location: ../pages/packages.php');
    exit;
}

$distributions = $packageModel->getPackageDistributions($id);

// Stock status helpers
$stockColor  = $package['stock'] <= 0  ? '#ef4444' : ($package['stock'] <= 10 ? '#f59e0b' : '#10b981');
$stockBg     = $package['stock'] <= 0  ? '#fef2f2' : ($package['stock'] <= 10 ? '#fffbeb' : '#f0fdf4');
$stockLabel  = $package['stock'] <= 0  ? 'Out of Stock' : ($package['stock'] <= 10 ? 'Low Stock' : 'Available');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Package - Disaster Relief Tracker</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="../style/dashboard.css">
    <link rel="stylesheet" href="../style/tables.css">
    <link rel="stylesheet" href="../style/view_pages.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>

<?php $activePage = 'packages'; $sidebarBase = '../pages/'; include '../includes/sidebar.php'; ?>

<div class="main">

    <!-- Header -->
    <div class="header header-flex">
        <div>
            <h1><?php echo htmlspecialchars($package['package_name']); ?></h1>
            <p>Inventory status and distribution history for this relief package.</p>
        </div>
        <a href="../pages/packages.php" class="btn-back">
            <i class="fa-solid fa-chevron-left"></i> Back to Inventory
        </a>
    </div>

    <!-- Info Cards Row -->
    <div class="cards-container">

        <!-- General Info Card -->
        <div class="detail-card">
            <h3>
                <i class="fa-solid fa-circle-info" style="color: #2563eb;"></i> General Information
            </h3>

            <div class="info-grid">
                <div style="grid-column: span 2;">
                    <p class="info-label">Package Name</p>
                    <p class="info-value" style="font-size: 20px;"><?php echo htmlspecialchars($package['package_name']); ?></p>
                </div>

                <div style="grid-column: span 2;">
                    <p class="info-label">Description</p>
                    <p style="margin: 6px 0 0 0; color: #475569; line-height: 1.6; font-size: 14px; font-weight: 500;"><?php echo htmlspecialchars($package['description'] ?: 'No description provided.'); ?></p>
                </div>

                <div style="background: <?php echo $stockBg; ?>; padding: 16px 20px; border-radius: 12px;">
                    <p class="info-label">Current Stock</p>
                    <p style="font-weight: 800; font-size: 30px; margin: 4px 0 0 0; color: <?php echo $stockColor; ?>; letter-spacing: -1px; line-height: 1;">
                        <?php echo number_format($package['stock']); ?>
                        <span style="font-size: 13px; font-weight: 600; letter-spacing: 0;">units</span>
                    </p>
                </div>
                <div style="padding: 16px 20px; border-radius: 12px; background: #f8fafc; border: 1.5px solid #e2e8f0; display: flex; flex-direction: column; justify-content: center;">
                    <p class="info-label">Status</p>
                    <span style="background: <?php echo $stockColor; ?>; color: white; padding: 5px 14px; border-radius: 20px; font-size: 12px; font-weight: 700; display: inline-block; width: fit-content; margin-top: 5px;"><?php echo $stockLabel; ?></span>
                </div>
            </div>
        </div>

        <!-- Totals Card -->
        <div class="detail-card" style="display: flex; flex-direction: column; justify-content: center; align-items: center; text-align: center;">
            <i class="fa-solid fa-truck-fast" style="font-size: 52px; color: #e0f2fe; margin-bottom: 16px;"></i>
            <p class="info-label">Total Distributions</p>
            <p style="font-size: 44px; font-weight: 800; color: #2563eb; margin: 6px 0; letter-spacing: -2px;"><?php echo count($distributions); ?></p>
            <p style="color: #94a3b8; font-size: 13px; font-weight: 500;">release<?php echo count($distributions) != 1 ? 's' : ''; ?> recorded</p>
        </div>

    </div>

    <!-- Distribution History -->
    <div class="table-container" style="margin-top: 24px;">
        <div class="table-header-flex">
            <h2 style="font-size: 1.2rem; display: flex; align-items: center; gap: 10px;">
                <i class="fa-solid fa-clock-rotate-left" style="color: #2563eb;"></i> Distribution History
            </h2>
        </div>

        <?php if (count($distributions) > 0): ?>
        <table>
            <tr>
                <th>Log #</th>
                <th>Recipient</th>
                <th>Distributor</th>
                <th>Quantity</th>
                <th>Date Distributed</th>
            </tr>
            <?php foreach ($distributions as $d): ?>
            <tr>
                <td style="color: #94a3b8; font-weight: 700;">#<?php echo $d['distribution_id']; ?></td>
                <td><strong style="color: #0f172a;"><?php echo htmlspecialchars($d['resident_name']); ?></strong></td>
                <td><?php echo htmlspecialchars($d['distributor_name']); ?></td>
                <td>
                    <span class="qty-badge qty-blue">
                        <?php echo $d['quantity']; ?> units
                    </span>
                </td>
                <td style="color: #64748b;">
                    <i class="fa-solid fa-calendar-check" style="color: #2563eb; margin-right: 5px;"></i>
                    <?php echo date('M d, Y', strtotime($d['date_distributed'])); ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
        <?php else: ?>
            <div class="empty-state">
                <i class="fa-solid fa-truck-ramp-box"></i>
                <p>No distribution records yet.</p>
                <p style="font-size: 13px; margin-top: 4px; font-weight: normal;">Records will appear here once this package is distributed.</p>
            </div>
        <?php endif; ?>
    </div>

</div>

</body>
</html>
