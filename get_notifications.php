<?php
session_start();
include("connector.php");

header('Content-Type: application/json');

if (!isset($_SESSION['Username'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$username = $_SESSION['Username'];
$user_query = "SELECT IDNO FROM user WHERE USERNAME = ?";
$stmt = $con->prepare($user_query);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    echo json_encode(['success' => false, 'message' => 'User not found']);
    exit;
}

try {
    // Get unread notifications
    $query = "SELECT * FROM notifications 
              WHERE user_id = ? AND is_read = FALSE 
              ORDER BY created_at DESC";
    $stmt = $con->prepare($query);
    $stmt->bind_param("s", $user['IDNO']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        $notifications[] = [
            'id' => $row['id'],
            'message' => $row['message'],
            'type' => $row['type'],
            'created_at' => $row['created_at']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'notifications' => $notifications
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching notifications: ' . $e->getMessage()
    ]);
}
?>