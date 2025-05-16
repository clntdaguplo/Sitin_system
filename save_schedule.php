<?php
include("connector.php");

header('Content-Type: application/json');

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

if (empty($data)) {
    echo json_encode(['success' => false, 'message' => 'No data received']);
    exit;
}

try {
    // Start transaction
    $con->begin_transaction();

    // Delete existing schedules for the room
    $room = $data[0]['room'];
    $delete_query = "DELETE FROM lab_schedules WHERE room_number = ?";
    $stmt = $con->prepare($delete_query);
    $stmt->bind_param("s", $room);
    $stmt->execute();

    // Insert new schedules
    $insert_query = "INSERT INTO lab_schedules (room_number, day_group, time_slot, status, last_updated) 
                    VALUES (?, ?, ?, ?, NOW())";
    $stmt = $con->prepare($insert_query);

    foreach ($data as $schedule) {
        $stmt->bind_param("ssss", 
            $schedule['room'],
            $schedule['day'],
            $schedule['time'],
            $schedule['status']
        );
        $stmt->execute();
    }

    // Commit transaction
    $con->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Schedule saved successfully'
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    $con->rollback();
    
    echo json_encode([
        'success' => false,
        'message' => 'Error saving schedule: ' . $e->getMessage()
    ]);
}
?>