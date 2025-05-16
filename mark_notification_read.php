<?php
session_start();
include("connector.php");

header('Content-Type: application/json');

if (!isset($_SESSION['Username'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

if (!isset($_POST['notification_id'])) {
    echo json_encode(['success' => false, 'message' => 'Notification ID is required']);
    exit;
}

$username = $_SESSION['Username'];
$notification_id = $_POST['notification_id'];

try {
    // Get user ID
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

    // Mark notification as read
    $query = "UPDATE notifications 
              SET is_read = TRUE 
              WHERE id = ? AND user_id = ?";
    $stmt = $con->prepare($query);
    $stmt->bind_param("is", $notification_id, $user['IDNO']);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to mark notification as read']);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error marking notification as read: ' . $e->getMessage()
    ]);
}
?>