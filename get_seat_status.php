<?php
include("connector.php");

header('Content-Type: application/json');

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
$room = $data['room'] ?? '';
$date = $data['date'] ?? '';
$time = $data['time'] ?? '';

if (empty($room)) {
    echo json_encode(['success' => false, 'message' => 'Room number is required']);
    exit;
}

try {
    // Get PC statuses from pc_status table
    $pc_query = "SELECT pc_number, status FROM pc_status WHERE room_number = ?";
    $pc_stmt = $con->prepare($pc_query);
    $pc_stmt->bind_param("s", $room);
    $pc_stmt->execute();
    $pc_result = $pc_stmt->get_result();
    
    $pcStatus = [];
    while ($row = $pc_result->fetch_assoc()) {
        $pcStatus[] = [
            'pc_number' => $row['pc_number'],
            'status' => $row['status']
        ];
    }

    // Get reservations for the selected date and time
    $res_query = "SELECT seat_number, status FROM reservations 
                  WHERE room = ? AND date = ? AND time = ? 
                  AND status IN ('approved', 'pending')";
    $res_stmt = $con->prepare($res_query);
    $res_stmt->bind_param("sss", $room, $date, $time);
    $res_stmt->execute();
    $res_result = $res_stmt->get_result();
    
    $reservations = [];
    while ($row = $res_result->fetch_assoc()) {
        $reservations[] = [
            'seat_number' => $row['seat_number'],
            'status' => $row['status']
        ];
    }

    echo json_encode([
        'success' => true,
        'pcStatus' => $pcStatus,
        'reservations' => $reservations
    ]);
} catch (Exception $e) {
    error_log("Error in get_seat_status.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching seat status: ' . $e->getMessage()
    ]);
}
?>