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

// Database connection
require_once '../config/database.php';
require_once '../classes/DashboardModel.php';

$db = new Database();
$conn = $db->connect();
$dashboardModel = new DashboardModel($conn);

$total_disasters = $dashboardModel->getTotalActiveDisasters();
$total_residents = $dashboardModel->getTotalResidents();
$total_affected = $dashboardModel->getTotalAffectedResidents();
$total_goods = $dashboardModel->getTotalReliefGoods();
$total_distributions = $dashboardModel->getTotalDistributions();
$total_barangays = $dashboardModel->getTotalBarangays();
$recent_disasters = $dashboardModel->getRecentDisasters();
$package_stocks = $dashboardModel->getPackageStocks();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Disaster Relief Tracker</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="../style/dashboard.css">
    <link rel="stylesheet" href="../style/tables.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

   <?php $activePage = 'dashboard'; $sidebarBase = ''; include '../includes/sidebar.php'; ?>

    <div class="main">
        <div class="header">
            <h1>Welcome, <?php echo $_SESSION['user']; ?>!</h1>
            <p>Disaster Relief Tracker Dashboard</p>
        </div>

        <div class="cards">
            <div class="card">
                <h3>Active Relief Distributions</h3>
                <p><?php echo $total_disasters; ?></p>
            </div>
            <div class="card">
                <h3>Total Estimated Affected Residents</h3>
                <p><?php echo number_format($total_affected); ?></p>
            </div>
            <div class="card">
                <h3>Recorded Affected Residents</h3>
                <p><?php echo number_format($total_residents); ?></p>
            </div>
            <div class="card">
                <h3>Relief Goods</h3>
                <p><?php echo number_format($total_goods); ?></p>
            </div>
            <div class="card">
                <h3>Total Distributions</h3>
                <p><?php echo number_format($total_distributions); ?></p>
            </div>
            
        </div>

        <div style="display: flex; gap: 20px; flex-wrap: wrap; margin-top: 2rem;">
            <!-- Chart Container -->
            <div style="background: white; padding: 20px; border-radius: 8px; flex: 1; min-width: 400px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                <h2 style="margin-top: 0; margin-bottom: 20px; font-size: 1.2rem; display: flex; align-items: center; gap: 10px;">
                    <i class="fa-solid fa-boxes-stacked" style="color: #3498db;"></i>
                    Relief Package Stocks
                </h2>
                <div style="height: 300px;">
                    <canvas id="stockChart"></canvas>
                </div>
            </div>

            <!-- Recent Disasters Container -->
            <div class="table-container" style="flex: 1; min-width: 400px; margin-top: 0;">
                <h2 style="margin-top: 0; margin-bottom: 20px; font-size: 1.2rem; display: flex; align-items: center; gap: 10px;">
                    <i class="fa-solid fa-clock-rotate-left" style="color: #2c3e50;"></i>
                    Recent Disasters
                </h2>
                <table>
                    <thead>
                    <tr>
                        <th>Type</th>
                        <th>Location</th>
                        <th>Date</th>
                        <th>Relief Status</th>
                    </tr>
                    </thead>
                    <tbody id="recentDisastersTbody">
                    <?php foreach ($recent_disasters as $row): ?>
                    <tr>
                        <td><strong><?php echo $row['type']; ?></strong></td>
                        <td>
                            <span class="location-pill">
                                <i class="fa-solid fa-location-dot"></i>
                                <?php echo $row['location']; ?>
                            </span>
                        </td>
                        <td class="date-cell">
                            <?php echo ($row['status'] == 'Pending Assessment' || empty($row['date'])) ? '<span style="color: #94a3b8; font-style: italic;">Pending Assessment</span>' : $row['date']; ?>
                        </td>
                        <td>
                            <?php 
                                $pillClass = 'pill-green';
                                $pillIcon = 'fa-circle-check';
                                if ($row['status'] == 'Ongoing') {
                                    $pillClass = 'pill-orange';
                                    $pillIcon = 'fa-spinner';
                                } elseif ($row['status'] == 'Pending Assessment') {
                                    $pillClass = 'pill-blue';
                                    $pillIcon = 'fa-magnifying-glass';
                                }
                            ?>
                            <span class="pill <?php echo $pillClass; ?>">
                                <i class="fa-solid <?php echo $pillIcon; ?>"></i>
                                <?php echo $row['status']; ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <div id="recentDisastersPagination" class="pagination-controls"></div>
            </div>
        </div>
    </div>

    <script>
        const packageData = <?php echo json_encode($package_stocks); ?>;
        
        const labels = packageData.map(item => item.package_name);
        const data = packageData.map(item => item.stock);
        
        const ctx = document.getElementById('stockChart').getContext('2d');
        
        // Create dynamic gradients
        function getGradient(ctx, stock) {
            const gradient = ctx.createLinearGradient(0, 0, 0, 300);
            if (stock <= 10) {
                gradient.addColorStop(0, '#ff4b2b'); // Red
                gradient.addColorStop(1, '#ff416c');
            } else if (stock <= 50) {
                gradient.addColorStop(0, '#f2994a'); // Orange
                gradient.addColorStop(1, '#f2c94c');
            } else {
                gradient.addColorStop(0, '#11998e'); // Green
                gradient.addColorStop(1, '#38ef7d');
            }
            return gradient;
        }

        const backgroundGradients = data.map(stock => getGradient(ctx, stock));

        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Units in Stock',
                    data: data,
                    backgroundColor: backgroundGradients,
                    borderRadius: 8, // Modern rounded corners
                    borderSkipped: false,
                    maxBarThickness: 40,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: {
                    duration: 1500,
                    easing: 'easeOutElastic'
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)',
                            drawBorder: false
                        },
                        ticks: {
                            font: {
                                size: 11,
                                weight: '500'
                            }
                        }
                    },
                    x: {
                        grid: {
                            display: false // Hide X-axis grid for cleaner look
                        },
                        ticks: {
                            font: {
                                size: 12,
                                weight: '600'
                            }
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: '#2c3e50',
                        padding: 12,
                        cornerRadius: 8,
                        titleFont: {
                            size: 14,
                            weight: 'bold'
                        },
                        bodyFont: {
                            size: 13
                        },
                        displayColors: false,
                        callbacks: {
                            label: function(context) {
                                return ` Stock Count: ${context.parsed.y} units`;
                            }
                        }
                    }
                }
            }
        });
    </script>
    <script src="../style/paginate.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            if (document.getElementById('recentDisastersTbody')) {
                paginateTable('recentDisastersTbody', 'recentDisastersPagination', 10);
            }
        });
    </script>
</body>
</html>