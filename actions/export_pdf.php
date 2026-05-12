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

// Ensure FPDF library exists
if (!file_exists('../libs/fpdf/fpdf.php')) {
    die("PDF Library not found. Please contact administrator.");
}
require_once '../libs/fpdf/fpdf.php';

$type = isset($_GET['report_type']) ? $_GET['report_type'] : '';
if (empty($type)) {
    die("Report type not specified.");
}

class PDF extends FPDF {
    public $reportTitle = "Report";

    // Page header
    function Header() {
        // Arial bold 15
        $this->SetFont('Arial', 'B', 15);
        $this->SetTextColor(44, 62, 80);
        // Title
        $this->Cell(0, 10, 'Disaster Relief Tracker (DSRT)', 0, 1, 'C');
        
        // Subtitle
        $this->SetFont('Arial', 'B', 12);
        $this->SetTextColor(52, 152, 219);
        $this->Cell(0, 8, $this->reportTitle, 0, 1, 'C');
        
        // Date generated
        $this->SetFont('Arial', 'I', 9);
        $this->SetTextColor(127, 140, 141);
        $this->Cell(0, 6, 'Generated on: ' . date('F d, Y - h:i A'), 0, 1, 'C');
        
        // Line break
        $this->Ln(10);
    }

    // Page footer
    function Footer() {
        // Position at 1.5 cm from bottom
        $this->SetY(-15);
        // Arial italic 8
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(127, 140, 141);
        // Page number
        $this->Cell(0, 10, 'Page ' . $this->PageNo() . ' / {nb} | DSRT Official Document', 0, 0, 'C');
    }
}

$db = new Database();
$conn = $db->connect();
$reportModel = new ReportModel($conn);
$residentModel = new ResidentModel($conn);
$disasterModel = new DisasterModel($conn);

$pdf = new PDF();
$pdf->AliasNbPages();
// Some tables require landscape depending on columns, but let's stick to Portrait, 
// using A4 page width = 210mm. Margins are 10mm by default, so active width is 190mm.
$pdf->AddPage();

switch ($type) {
    case 'resident_list':
        $pdf->reportTitle = "Resident Master List";
        $pdf->SetTitle($pdf->reportTitle);
        // Table Header
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->SetFillColor(44, 62, 80);
        $pdf->SetTextColor(255, 255, 255);
        // Total width: 15+50+15+40+30+40 = 190
        $pdf->Cell(15, 8, 'ID', 1, 0, 'C', true);
        $pdf->Cell(50, 8, 'Full Name', 1, 0, 'C', true);
        $pdf->Cell(15, 8, 'Age', 1, 0, 'C', true);
        $pdf->Cell(40, 8, 'Barangay', 1, 0, 'C', true);
        $pdf->Cell(30, 8, 'Contact', 1, 0, 'C', true);
        $pdf->Cell(40, 8, 'Address', 1, 1, 'C', true);
        
        $pdf->SetFont('Arial', '', 9);
        $pdf->SetTextColor(0, 0, 0);
        $residents = $residentModel->getAllResidents();
        foreach ($residents as $row) {
            $pdf->Cell(15, 7, $row['resident_id'], 1, 0, 'C');
            $pdf->Cell(50, 7, substr($row['name'], 0, 25), 1, 0, 'L');
            $pdf->Cell(15, 7, $row['age'], 1, 0, 'C');
            $pdf->Cell(40, 7, substr($row['barangay_name'], 0, 20), 1, 0, 'L');
            $pdf->Cell(30, 7, $row['contact'], 1, 0, 'C');
            $pdf->Cell(40, 7, substr($row['address'], 0, 20), 1, 1, 'L');
        }
        break;

    case 'disaster_list':
        $pdf->reportTitle = "Disaster Events Record";
        $pdf->SetTitle($pdf->reportTitle);
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->SetFillColor(44, 62, 80);
        $pdf->SetTextColor(255, 255, 255);
        // Total width: 15+35+30+35+55+20 = 190
        $pdf->Cell(15, 8, 'ID', 1, 0, 'C', true);
        $pdf->Cell(35, 8, 'Type', 1, 0, 'C', true);
        $pdf->Cell(30, 8, 'Date', 1, 0, 'C', true);
        $pdf->Cell(35, 8, 'Status', 1, 0, 'C', true);
        $pdf->Cell(55, 8, 'Location', 1, 0, 'C', true);
        $pdf->Cell(20, 8, 'Affected', 1, 1, 'C', true);

        $pdf->SetFont('Arial', '', 9);
        $pdf->SetTextColor(0, 0, 0);
        $disasters = $disasterModel->getAllDisasters();
        foreach ($disasters as $row) {
            $pdf->Cell(15, 7, $row['disaster_id'], 1, 0, 'C');
            $pdf->Cell(35, 7, substr($row['type'], 0, 20), 1, 0, 'L');
            $dateStr = ($row['status'] == 'Pending Assessment' || empty($row['date'])) ? 'Pending' : date('Y-m-d', strtotime($row['date']));
            $pdf->Cell(30, 7, $dateStr, 1, 0, 'C');
            $pdf->Cell(35, 7, $row['status'], 1, 0, 'C');
            $pdf->Cell(55, 7, substr($row['location'], 0, 30), 1, 0, 'L');
            $pdf->Cell(20, 7, $row['total_affected'], 1, 1, 'C');
        }
        break;

    case 'inventory_audit':
        $pdf->reportTitle = "Relief Inventory Audit";
        $pdf->SetTitle($pdf->reportTitle);
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->SetFillColor(44, 62, 80);
        $pdf->SetTextColor(255, 255, 255);
        // Total width: 15+60+30+30+25+30 = 190
        $pdf->Cell(15, 8, 'ID', 1, 0, 'C', true);
        $pdf->Cell(60, 8, 'Package Name', 1, 0, 'C', true);
        $pdf->Cell(30, 8, 'Stock Left', 1, 0, 'C', true);
        $pdf->Cell(30, 8, 'Distributed', 1, 0, 'C', true);
        $pdf->Cell(25, 8, 'Avg/Dist', 1, 0, 'C', true);
        $pdf->Cell(30, 8, 'Releases', 1, 1, 'C', true);

        $pdf->SetFont('Arial', '', 9);
        $pdf->SetTextColor(0, 0, 0);
        $packages = $reportModel->getPackageSummary();
        foreach ($packages as $row) {
            $pdf->Cell(15, 7, $row['package_id'], 1, 0, 'C');
            $pdf->Cell(60, 7, substr($row['package_name'], 0, 35), 1, 0, 'L');
            $pdf->Cell(30, 7, number_format($row['current_stock']), 1, 0, 'C');
            $pdf->Cell(30, 7, number_format($row['total_distributed']), 1, 0, 'C');
            $pdf->Cell(25, 7, number_format($row['avg_qty_per_distribution'], 1), 1, 0, 'C');
            $pdf->Cell(30, 7, $row['distribution_count'], 1, 1, 'C');
        }
        break;

    case 'staff_performance':
        $pdf->reportTitle = "Staff Distribution Performance";
        $pdf->SetTitle($pdf->reportTitle);
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->SetFillColor(44, 62, 80);
        $pdf->SetTextColor(255, 255, 255);
        // Total width: 45+30+25+30+30+30 = 190
        $pdf->Cell(45, 8, 'Staff Name', 1, 0, 'C', true);
        $pdf->Cell(30, 8, 'Username', 1, 0, 'C', true);
        $pdf->Cell(25, 8, 'Role', 1, 0, 'C', true);
        $pdf->Cell(30, 8, 'Items Given', 1, 0, 'C', true);
        $pdf->Cell(30, 8, 'Releases', 1, 0, 'C', true);
        $pdf->Cell(30, 8, 'Unique Served', 1, 1, 'C', true);

        $pdf->SetFont('Arial', '', 9);
        $pdf->SetTextColor(0, 0, 0);
        $staff = $reportModel->getStaffDistributionReport();
        foreach ($staff as $row) {
            $name = trim($row['full_name']) ?: $row['username'];
            $pdf->Cell(45, 7, substr($name, 0, 25), 1, 0, 'L');
            $pdf->Cell(30, 7, substr($row['username'], 0, 15), 1, 0, 'L');
            $pdf->Cell(25, 7, ucfirst($row['role_name']), 1, 0, 'C');
            $pdf->Cell(30, 7, number_format($row['total_items_given']), 1, 0, 'C');
            $pdf->Cell(30, 7, $row['total_distributions'], 1, 0, 'C');
            $pdf->Cell(30, 7, $row['unique_residents_served'], 1, 1, 'C');
        }
        break;

    case 'barangay_impact':
        $pdf->reportTitle = "Barangay Impact Ranking";
        $pdf->SetTitle($pdf->reportTitle);
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->SetFillColor(44, 62, 80);
        $pdf->SetTextColor(255, 255, 255);
        // Total width: 50+30+35+35+40 = 190
        $pdf->Cell(50, 8, 'Barangay Name', 1, 0, 'C', true);
        $pdf->Cell(30, 8, 'Events', 1, 0, 'C', true);
        $pdf->Cell(35, 8, 'Total Affected', 1, 0, 'C', true);
        $pdf->Cell(35, 8, 'Worst Event', 1, 0, 'C', true);
        $pdf->Cell(40, 8, 'Reg. Victims', 1, 1, 'C', true);

        $pdf->SetFont('Arial', '', 9);
        $pdf->SetTextColor(0, 0, 0);
        $barangays = $reportModel->getBarangayActivity();
        foreach ($barangays as $row) {
            $pdf->Cell(50, 7, substr($row['barangay_name'], 0, 30), 1, 0, 'L');
            $pdf->Cell(30, 7, $row['disaster_count'], 1, 0, 'C');
            $pdf->Cell(35, 7, number_format($row['total_affected']), 1, 0, 'C');
            $pdf->Cell(35, 7, number_format($row['worst_event_affected']), 1, 0, 'C');
            $pdf->Cell(40, 7, number_format($row['registered_residents']), 1, 1, 'C');
        }
        break;

    default:
        $pdf->Cell(0, 10, 'Invalid report type specified.', 0, 1);
        break;
}

// Ensure no output was sent before this point
if (ob_get_length()) ob_clean();

// D = force download
$pdf->Output('D', 'export_' . $type . '_' . date('Ymd_His') . '.pdf');
?>
