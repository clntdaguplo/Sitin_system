<?php
session_start();
include("connector.php");

header('Content-Type: application/json');

$room = $_GET['room'] ?? '';

if (empty($room)) {
    echo json_encode(['success' => false, 'message' => 'Room number is required']);
    exit;
}

try {
    // Get PC statuses from admin settings
    $query = "SELECT pc_number, status FROM pc_status WHERE room_number = ?";
    $stmt = $con->prepare($query);
    $stmt->bind_param("s", $room);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $pcStatus = [];
    while ($row = $result->fetch_assoc()) {
        $pcStatus[] = [
            'pc_number' => $row['pc_number'],
            'status' => $row['status']
        ];
    }
    
    // Get pending reservations
    $pending_query = "SELECT seat_number FROM reservations 
                     WHERE room = ? AND status = 'pending'";
    $pending_stmt = $con->prepare($pending_query);
    $pending_stmt->bind_param("s", $room);
    $pending_stmt->execute();
    $pending_result = $pending_stmt->get_result();
    
    $pending_reservations = [];
    while ($row = $pending_result->fetch_assoc()) {
        $pending_reservations[] = $row['seat_number'];
    }

    // Get approved reservations (used PCs)
    $approved_query = "SELECT seat_number FROM reservations 
                      WHERE room = ? AND status = 'approved' 
                      AND date = CURDATE()";
    $approved_stmt = $con->prepare($approved_query);
    $approved_stmt->bind_param("s", $room);
    $approved_stmt->execute();
    $approved_result = $approved_stmt->get_result();
    
    $approved_reservations = [];
    while ($row = $approved_result->fetch_assoc()) {
        $approved_reservations[] = $row['seat_number'];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $pcStatus,
        'pending_reservations' => $pending_reservations,
        'approved_reservations' => $approved_reservations
    ]);
    
} catch (Exception $e) {
    error_log("Error in get_pc_status.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching PC status: ' . $e->getMessage()
    ]);
}
?>