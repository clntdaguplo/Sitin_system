<?php
include("connector.php");

header('Content-Type: application/json');

$room = $_GET['room'] ?? '';

if (!$room) {
    echo json_encode(['success' => false, 'message' => 'Room number is required']);
    exit;
}

try {
    // Get schedules for the room
    $query = "SELECT * FROM lab_schedules WHERE room_number = ? ORDER BY day_group, time_slot";
    $stmt = $con->prepare($query);
    $stmt->bind_param("s", $room);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $schedules = [];
    while ($row = $result->fetch_assoc()) {
        $schedules[] = [
            'room_number' => $row['room_number'],
            'day_group' => $row['day_group'],
            'time_slot' => $row['time_slot'],
            'status' => $row['status']
        ];
    }

    echo json_encode([
        'success' => true,
        'schedules' => $schedules
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching schedules: ' . $e->getMessage()
    ]);
}
?>