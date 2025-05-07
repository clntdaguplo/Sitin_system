<?php
session_start();
date_default_timezone_set('Asia/Manila');
include("connector.php");
require('fpdf/fpdf.php');

if (!isset($_SESSION['Username'])) {
    header("Location: login.php");
    exit();
}

class PDF extends FPDF {
    function Header() {
        $this->SetFont('Arial', 'B', 15);
        $this->Cell(0, 10, 'Sit-in Management System Report', 0, 1, 'C');
        $this->Ln(10);
    }

    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Page ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }
}

// Get report parameters
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'daily';

// Create PDF
$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage();

// Set font
$pdf->SetFont('Arial', '', 12);

// Add report type and date range
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 10, ucfirst($report_type) . ' Report', 0, 1, 'L');
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(0, 10, 'Period: ' . date('M d, Y', strtotime($start_date)) . ' to ' . date('M d, Y', strtotime($end_date)), 0, 1, 'L');
$pdf->Ln(10);

// Get summary data
$summary_query = "SELECT 
    DATE(TIME_IN) as date,
    COUNT(DISTINCT IDNO) as total_students,
    SUM(TIMESTAMPDIFF(HOUR, TIME_IN, IFNULL(TIME_OUT, NOW()))) as total_hours,
    LAB_ROOM,
    COUNT(*) as room_count
FROM login_records 
WHERE DATE(TIME_IN) BETWEEN ? AND ?
GROUP BY DATE(TIME_IN), LAB_ROOM
ORDER BY date DESC, room_count DESC";

$stmt = $con->prepare($summary_query);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$summary_result = $stmt->get_result();

// Add summary table
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 10, 'Summary', 0, 1, 'L');
$pdf->Ln(5);

// Table header
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(40, 7, 'Date', 1);
$pdf->Cell(40, 7, 'Total Students', 1);
$pdf->Cell(40, 7, 'Total Hours', 1);
$pdf->Cell(40, 7, 'Most Used Room', 1);
$pdf->Ln();

// Table data
$pdf->SetFont('Arial', '', 10);
$current_date = null;
$row_data = [];

while ($row = mysqli_fetch_assoc($summary_result)) {
    if ($current_date !== $row['date']) {
        if ($current_date !== null) {
            // Output the previous date's data
            $pdf->Cell(40, 7, date('M d, Y', strtotime($current_date)), 1);
            $pdf->Cell(40, 7, $row_data['total_students'], 1);
            $pdf->Cell(40, 7, $row_data['total_hours'], 1);
            $pdf->Cell(40, 7, $row_data['most_used_room'], 1);
            $pdf->Ln();
        }
        // Initialize new date data
        $current_date = $row['date'];
        $row_data = [
            'total_students' => $row['total_students'],
            'total_hours' => $row['total_hours'],
            'most_used_room' => $row['LAB_ROOM']
        ];
    } else {
        // Update existing date data
        $row_data['total_students'] += $row['total_students'];
        $row_data['total_hours'] += $row['total_hours'];
        if ($row['room_count'] > $row_data['room_count']) {
            $row_data['most_used_room'] = $row['LAB_ROOM'];
        }
    }
}

// Output the last date's data
if ($current_date !== null) {
    $pdf->Cell(40, 7, date('M d, Y', strtotime($current_date)), 1);
    $pdf->Cell(40, 7, $row_data['total_students'], 1);
    $pdf->Cell(40, 7, $row_data['total_hours'], 1);
    $pdf->Cell(40, 7, $row_data['most_used_room'], 1);
    $pdf->Ln();
}

// Add detailed records
$pdf->Ln(10);
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 10, 'Detailed Records', 0, 1, 'L');
$pdf->Ln(5);

// Get detailed records
$details_query = "SELECT 
    IDNO,
    FULLNAME,
    TIME_IN,
    TIME_OUT,
    PURPOSE,
    LAB_ROOM
FROM login_records 
WHERE DATE(TIME_IN) BETWEEN ? AND ?
ORDER BY TIME_IN DESC";

$stmt = $con->prepare($details_query);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$details_result = $stmt->get_result();

// Table header
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(30, 7, 'ID Number', 1);
$pdf->Cell(50, 7, 'Name', 1);
$pdf->Cell(30, 7, 'Time In', 1);
$pdf->Cell(30, 7, 'Time Out', 1);
$pdf->Cell(50, 7, 'Purpose', 1);
$pdf->Ln();

// Table data
$pdf->SetFont('Arial', '', 10);
while ($row = mysqli_fetch_assoc($details_result)) {
    $pdf->Cell(30, 7, $row['IDNO'], 1);
    $pdf->Cell(50, 7, $row['FULLNAME'], 1);
    $pdf->Cell(30, 7, date('h:i A', strtotime($row['TIME_IN'])), 1);
    $pdf->Cell(30, 7, $row['TIME_OUT'] ? date('h:i A', strtotime($row['TIME_OUT'])) : 'Active', 1);
    $pdf->Cell(50, 7, $row['PURPOSE'], 1);
    $pdf->Ln();
}

// Output PDF
$pdf->Output('I', 'sit-in_report.pdf');
?> 