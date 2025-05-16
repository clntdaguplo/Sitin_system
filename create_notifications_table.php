<?php
include("connector.php");

// Create notifications table
$create_notifications_table = "CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(50) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('reservation', 'reward', 'system') NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES user(IDNO)
)";

if ($con->query($create_notifications_table)) {
    echo "Notifications table created successfully";
} else {
    echo "Error creating notifications table: " . $con->error;
}
?> 