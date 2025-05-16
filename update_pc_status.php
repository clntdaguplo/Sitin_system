<?php
include("connector.php");

header('Content-Type: application/json');

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
$room = $data['room'] ?? '';
$pcNumbers = $data['pcNumbers'] ?? [];
$status = $data['status'] ?? '';

if (!$room || empty($pcNumbers) || !$status) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

try {
    // Start transaction
    $con->begin_transaction();

    // Delete existing statuses for these PCs
    $delete_query = "DELETE FROM pc_status WHERE room_number = ? AND pc_number IN (" . 
                   implode(',', array_fill(0, count($pcNumbers), '?')) . ")";
    $stmt = $con->prepare($delete_query);
    $params = array_merge([$room], $pcNumbers);
    $stmt->bind_param(str_repeat('s', count($params)), ...$params);
    $stmt->execute();

    // Insert new statuses
    $insert_query = "INSERT INTO pc_status (room_number, pc_number, status) VALUES (?, ?, ?)";
    $stmt = $con->prepare($insert_query);
    
    foreach ($pcNumbers as $pcNumber) {
        $stmt->bind_param("sis", $room, $pcNumber, $status);
        $stmt->execute();
    }

    // Commit transaction
    $con->commit();

    echo json_encode([
        'success' => true,
        'message' => 'PC status updated successfully'
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    $con->rollback();
    
    echo json_encode([
        'success' => false,
        'message' => 'Error updating PC status: ' . $e->getMessage()
    ]);
}
?>