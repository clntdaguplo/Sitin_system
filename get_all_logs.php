<?php
session_start();
include("connector.php");

// Check if admin is logged in
if (!isset($_SESSION['Username'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Not authorized']);
    exit();
}

// Query to get all reservation logs
$query = "SELECT r.*, 
          COALESCE(u.LASTNAME, '') as LASTNAME,
          COALESCE(u.FIRSTNAME, '') as FIRSTNAME,
          COALESCE(u.MIDNAME, '') as MIDNAME,
          DATE_FORMAT(r.created_at, '%M %d, %Y %h:%i %p') as date_requested,
          DATE_FORMAT(rl.created_at, '%M %d, %Y %h:%i %p') as action_date
          FROM reservations r 
          LEFT JOIN user u ON r.student_id = u.IDNO 
          INNER JOIN reservation_logs rl ON r.id = rl.reservation_id
          WHERE r.status IN ('approved', 'rejected')
          ORDER BY rl.created_at DESC, r.date DESC";

$result = mysqli_query($con, $query);
$logs = [];

while ($row = mysqli_fetch_assoc($result)) {
    // Format the full name
    $fullName = '';
    if (!empty($row['LASTNAME']) || !empty($row['FIRSTNAME'])) {
        $fullName = $row['LASTNAME'] . ', ' . $row['FIRSTNAME'];
        if (!empty($row['MIDNAME'])) {
            $fullName .= ' ' . substr($row['MIDNAME'], 0, 1) . '.';
        }
    } else {
        $fullName = 'N/A';
    }

    // Format the reservation date
    $reserveDate = date('M d, Y', strtotime($row['date']));

    // Add the formatted data to the logs array
    $logs[] = [
        'student_id' => $row['student_id'],
        'full_name' => $fullName,
        'date_requested' => $row['date_requested'],
        'reserve_date' => $reserveDate,
        'time' => $row['time'],
        'room' => $row['room'],
        'seat_number' => $row['seat_number'],
        'purpose' => $row['purpose'],
        'status' => $row['status'],
        'action_date' => $row['action_date']
    ];
}

// Return the logs as JSON
header('Content-Type: application/json');
echo json_encode($logs);
?> 