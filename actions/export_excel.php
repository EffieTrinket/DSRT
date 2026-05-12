<?php
session_start();

if (!isset($_SESSION['user']) || !isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

require_once '../config/database.php';
require_once '../classes/ReportModel.php';
require_once '../classes/ResidentModel.php';
require_once '../classes/DisasterModel.php';

$db = new Database();
$conn = $db->connect();
$reportModel = new ReportModel($conn);
$residentModel = new ResidentModel($conn);
$disasterModel = new DisasterModel($conn);

$type = isset($_GET['report_type']) ? $_GET['report_type'] : '';

if (empty($type)) {
    die("Report type not specified.");
}

$filename = "export_" . $type . "_" . date('Ymd_His') . ".csv";

// Set headers for download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

// UTF-8 BOM for Excel to properly read special characters
fputs($output, $bom =(chr(0xEF) . chr(0xBB) . chr(0xBF)));

switch ($type) {
    case 'resident_list':
        fputcsv($output, ['Resident ID', 'Full Name', 'Age', 'Barangay', 'Contact', 'Address']);
        $residents = $residentModel->getAllResidents();
        foreach ($residents as $row) {
            fputcsv($output, [
                $row['resident_id'],
                $row['name'],
                $row['age'],
                $row['barangay_name'],
                $row['contact'],
                $row['address']
            ]);
        }
        break;

    case 'disaster_list':
        fputcsv($output, ['Disaster ID', 'Type', 'Date Recorded', 'Date Ended', 'Relief Status', 'Location(s)', 'Total Affected']);
        $disasters = $disasterModel->getAllDisasters();
        foreach ($disasters as $row) {
            fputcsv($output, [
                $row['disaster_id'],
                $row['type'],
                $row['date'],
                $row['end_date'] ?: 'N/A',
                $row['status'],
                $row['location'],
                $row['total_affected']
            ]);
        }
        break;

    case 'inventory_audit':
        fputcsv($output, ['Package ID', 'Package Name', 'Current Stock', 'Total Distributed', 'Average Quantity/Dist', 'Distribution Count']);
        $packages = $reportModel->getPackageSummary();
        foreach ($packages as $row) {
            fputcsv($output, [
                $row['package_id'],
                $row['package_name'],
                $row['current_stock'],
                $row['total_distributed'],
                round($row['avg_qty_per_distribution'], 1),
                $row['distribution_count']
            ]);
        }
        break;

    case 'staff_performance':
        fputcsv($output, ['Staff Name', 'Username', 'Role', 'Total Items Distributed', 'Number of Distributions', 'Unique Residents Served', 'Max Single Distribution']);
        $staff = $reportModel->getStaffDistributionReport();
        foreach ($staff as $row) {
            fputcsv($output, [
                trim($row['full_name']) ?: $row['username'],
                $row['username'],
                $row['role_name'],
                $row['total_items_given'],
                $row['total_distributions'],
                $row['unique_residents_served'],
                $row['max_single_distribution'] ?: 0
            ]);
        }
        break;

    case 'barangay_impact':
        fputcsv($output, ['Barangay', 'Disaster Events', 'Total Affected Residents', 'Worst Event (Peak Affected)', 'Registered Resident Count']);
        $barangays = $reportModel->getBarangayActivity();
        foreach ($barangays as $row) {
            fputcsv($output, [
                $row['barangay_name'],
                $row['disaster_count'],
                $row['total_affected'],
                $row['worst_event_affected'],
                $row['registered_residents']
            ]);
        }
        break;

    default:
        fputcsv($output, ['Invalid report type specified.']);
        break;
}

fclose($output);
exit;
?>
