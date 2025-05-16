<?php
session_start();
include("connector.php");

// Debug logging
error_log("adreservation.php started");
error_log("Session data: " . print_r($_SESSION, true));

// Check if admin is logged in
if (!isset($_SESSION['Username'])) {
    error_log("Admin not logged in, redirecting to login.php");
    header("Location: login.php");
    exit();
}

$username = $_SESSION['Username'];
$query = "SELECT PROFILE_PIC, FIRSTNAME, MIDNAME, LASTNAME FROM user WHERE USERNAME = ?";
$stmt = $con->prepare($query);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result && mysqli_num_rows($result) > 0) {
    $row = mysqli_fetch_assoc($result);
    $profile_pic = !empty($row['PROFILE_PIC']) ? htmlspecialchars($row['PROFILE_PIC']) : 'default.jpg';
    $user_name = htmlspecialchars($row['FIRSTNAME'] . ' ' . $row['MIDNAME'] . ' ' . $row['LASTNAME']);
} else {
    $profile_pic = 'default.jpg';
    $user_name = 'Admin';
}

// Create reservation_logs table if it doesn't exist
$create_logs_table = "CREATE TABLE IF NOT EXISTS reservation_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reservation_id INT NOT NULL,
    status VARCHAR(20) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reservation_id) REFERENCES reservations(id)
)";

if (!$con->query($create_logs_table)) {
    error_log("Error creating reservation_logs table: " . $con->error);
}

// Handle reservation approval
if(isset($_POST['action']) && $_POST['action'] === 'approve') {
    $reservation_id = $_POST['reservation_id'];
    
    // Get reservation details
    $query = "SELECT r.*, u.EMAIL, u.IDNO FROM reservations r 
              JOIN user u ON r.student_id = u.IDNO 
              WHERE r.id = ?";
    $stmt = $con->prepare($query);
    $stmt->bind_param("i", $reservation_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $reservation = $result->fetch_assoc();
    
    if($reservation) {
        // Start transaction
        $con->begin_transaction();
        
        try {
            // Update reservation status
            $update_query = "UPDATE reservations SET status = 'approved' WHERE id = ?";
            $update_stmt = $con->prepare($update_query);
            $update_stmt->bind_param("i", $reservation_id);
            $update_stmt->execute();
            
            // Update PC status to used
            $pc_status_query = "INSERT INTO pc_status (room_number, pc_number, status) 
                              VALUES (?, ?, 'used') 
                              ON DUPLICATE KEY UPDATE status = 'used'";
            $pc_status_stmt = $con->prepare($pc_status_query);
            $pc_status_stmt->bind_param("si", $reservation['room'], $reservation['seat_number']);
            $pc_status_stmt->execute();
            
            // Log the approval
            $log_query = "INSERT INTO reservation_logs (reservation_id, status, created_at) VALUES (?, 'approved', NOW())";
            $log_stmt = $con->prepare($log_query);
            $log_stmt->bind_param("i", $reservation_id);
            $log_stmt->execute();
            
            // Add notification for the student
            $notification_message = "Your reservation for Room " . $reservation['room'] . 
                                  " (PC " . $reservation['seat_number'] . ") on " . 
                                  date('F j, Y', strtotime($reservation['date'])) . 
                                  " at " . $reservation['time'] . " has been approved.";
            
            $notification_query = "INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'reservation')";
            $notification_stmt = $con->prepare($notification_query);
            $notification_stmt->bind_param("ss", $reservation['IDNO'], $notification_message);
            $notification_stmt->execute();
            
            // Send email notification
            $to = $reservation['EMAIL'];
            $subject = "Reservation Approved - Lab Room " . $reservation['room'];
            $message = "Dear Student,\n\n";
            $message .= "Your reservation for Lab Room " . $reservation['room'] . " has been approved.\n";
            $message .= "Date: " . date('F j, Y', strtotime($reservation['date'])) . "\n";
            $message .= "Time: " . $reservation['time'] . "\n";
            $message .= "Seat Number: " . $reservation['seat_number'] . "\n\n";
            $message .= "Please arrive 30 minutes before your scheduled time.\n";
            $message .= "Thank you for using our lab reservation system.\n\n";
            $message .= "Best regards,\nLab Management Team";
            
            $headers = "From: labmanagement@example.com";
            
            // Try to send email, but don't fail if it doesn't work
            $mail_sent = @mail($to, $subject, $message, $headers);
            if (!$mail_sent) {
                error_log("Failed to send email to: " . $to);
            }
            
            // Commit transaction
            $con->commit();
            
            // Update the PC display immediately
            echo "<script>
                alert('Reservation approved and notification sent to student.');
                // Update PC status in the UI
                const pcItem = document.querySelector('#pcItem" . $reservation['seat_number'] . "');
                if (pcItem) {
                    pcItem.className = 'pc-item used';
                    const statusLabel = pcItem.querySelector('.pc-status');
                    if (statusLabel) {
                        statusLabel.textContent = 'In Use';
                    }
                }
                // Fetch updated PC status
                fetch('get_pc_status.php?room=" . $reservation['room'] . "')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Update all PCs based on their status
                            data.data.forEach(function(pc) {
                                const pcItem = document.querySelector('#pcItem' + pc.pc_number);
                                if (pcItem) {
                                    pcItem.className = 'pc-item ' + pc.status;
                                    const statusLabel = pcItem.querySelector('.pc-status');
                                    if (statusLabel) {
                                        statusLabel.textContent = pc.status.charAt(0).toUpperCase() + pc.status.slice(1);
                                    }
                                }
                            });
                            
                            // Update pending reservations
                            if (data.pending_reservations) {
                                data.pending_reservations.forEach(function(pcNumber) {
                                    const pcItem = document.querySelector('#pcItem' + pcNumber);
                                    if (pcItem) {
                                        pcItem.className = 'pc-item reserved';
                                        const statusLabel = pcItem.querySelector('.pc-status');
                                        if (statusLabel) {
                                            statusLabel.textContent = 'Reserved';
                                        }
                                    }
                                });
                            }
                            
                            // Update approved reservations
                            if (data.approved_reservations) {
                                data.approved_reservations.forEach(function(pcNumber) {
                                    const pcItem = document.querySelector('#pcItem' + pcNumber);
                                    if (pcItem) {
                                        pcItem.className = 'pc-item used';
                                        const statusLabel = pcItem.querySelector('.pc-status');
                                        if (statusLabel) {
                                            statusLabel.textContent = 'In Use';
                                        }
                                    }
                                });
                            }
                        }
                    })
                    .catch(error => console.error('Error fetching PC status:', error));
                window.location.href = 'adreservation.php';
            </script>";
        } catch (Exception $e) {
            // Rollback transaction on error
            $con->rollback();
            echo "<script>
                alert('Error approving reservation: " . addslashes($e->getMessage()) . "');
                window.location.href = 'adreservation.php';
            </script>";
        }
    }
}

// Handle reservation rejection
if(isset($_POST['action']) && $_POST['action'] === 'reject') {
    $reservation_id = $_POST['reservation_id'];
    
    // Get reservation details
    $query = "SELECT r.*, u.EMAIL FROM reservations r 
              JOIN user u ON r.student_id = u.IDNO 
              WHERE r.id = ?";
    $stmt = $con->prepare($query);
    $stmt->bind_param("i", $reservation_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $reservation = $result->fetch_assoc();
    
    if($reservation) {
        // Start transaction
        $con->begin_transaction();
        
        try {
            // Update reservation status
            $update_query = "UPDATE reservations SET status = 'rejected' WHERE id = ?";
            $update_stmt = $con->prepare($update_query);
            $update_stmt->bind_param("i", $reservation_id);
            $update_stmt->execute();
            
            // Log the rejection with current timestamp
            $log_query = "INSERT INTO reservation_logs (reservation_id, status, created_at) VALUES (?, 'rejected', NOW())";
            $log_stmt = $con->prepare($log_query);
            $log_stmt->bind_param("i", $reservation_id);
            $log_stmt->execute();
            
            // Send email notification
            $to = $reservation['EMAIL'];
            $subject = "Reservation Rejected - Lab Room " . $reservation['room'];
            $message = "Dear Student,\n\n";
            $message .= "We regret to inform you that your reservation for Lab Room " . $reservation['room'] . " has been rejected.\n";
            $message .= "Date: " . date('F j, Y', strtotime($reservation['date'])) . "\n";
            $message .= "Time: " . $reservation['time'] . "\n\n";
            $message .= "Please make a new reservation or contact the lab administrator for more information.\n";
            $message .= "Thank you for your understanding.\n\n";
            $message .= "Best regards,\nLab Management Team";
            
            $headers = "From: labmanagement@example.com";
            
            // Try to send email, but don't fail if it doesn't work
            $mail_sent = @mail($to, $subject, $message, $headers);
            if (!$mail_sent) {
                error_log("Failed to send email to: " . $to);
            }
            
            // Commit transaction
            $con->commit();
            
            echo "<script>
                alert('Reservation rejected and notification sent to student.');
                window.location.href = 'adreservation.php';
            </script>";
        } catch (Exception $e) {
            // Rollback transaction on error
            $con->rollback();
            echo "<script>
                alert('Error rejecting reservation: " . addslashes($e->getMessage()) . "');
                window.location.href = 'adreservation.php';
            </script>";
        }
    }
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$room_filter = isset($_GET['room']) ? $_GET['room'] : '';

// Build the query based on filters
$query = "SELECT r.*, u.FIRSTNAME, u.LASTNAME, u.EMAIL 
          FROM reservations r 
          JOIN user u ON r.student_id = u.IDNO 
          WHERE 1=1";

if (!empty($status_filter)) {
    $query .= " AND r.status = '" . mysqli_real_escape_string($con, $status_filter) . "'";
}

if (!empty($room_filter)) {
    $query .= " AND r.room = '" . mysqli_real_escape_string($con, $room_filter) . "'";
}

$query .= " ORDER BY r.date DESC, r.time DESC";
$result = mysqli_query($con, $query);

// Get pending reservations for PC status
$pending_reservations = [];
$pending_query = "SELECT room, seat_number FROM reservations WHERE status = 'pending'";
$pending_result = mysqli_query($con, $pending_query);
while ($row = mysqli_fetch_assoc($pending_result)) {
    $pending_reservations[$row['room']][] = $row['seat_number'];
}

// Debug logging
error_log("Reservations query: " . $query);
error_log("Number of reservations found: " . mysqli_num_rows($result));
if (mysqli_error($con)) {
    error_log("SQL Error: " . mysqli_error($con));
}

// Fetch the count of pending reservations
$pendingCount = 0;
$query = "SELECT COUNT(*) as count FROM reservations WHERE status = 'pending'";
$result = $con->query($query);
if ($result) {
    $row = $result->fetch_assoc();
    $pendingCount = $row['count'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="https://www.w3schools.com/w3css/4/w3.css">
    <title>Manage Reservations</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        html, body {
            background: white;
            min-height: 100vh;
            width: 100%;
        }

        /* Top Navigation Bar Styles */
        .top-nav {
            background: linear-gradient(45deg,rgb(150, 145, 79),rgb(47, 0, 177));
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
            backdrop-filter: blur(10px);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
        }

        .nav-left {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .nav-left img {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            border: 2px solid rgba(255, 255, 255, 0.2);
        }

        .nav-left .user-name {
            color: white;
            font-weight: 600;
            font-size: 1.1rem;
        }

        .nav-right {
            display: flex;
            gap: 15px;
        }

        .nav-right a {
            color: white;
            text-decoration: none;
            padding: 8px 15px;
            border-radius: 8px;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
        }

        .nav-right a i {
            font-size: 1rem;
        }

        .nav-right a:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateY(-2px);
        }

        .nav-right .logout-button {
            background: rgba(247, 162, 5, 0.88);
            margin-left: 10px;
        }

        .nav-right .logout-button:hover {
            background: rgba(255, 251, 0, 0.93);
        }

        .content {
            margin-top: 80px;
            padding: 20px;
            min-height: calc(100vh - 80px);
            background: #f0f2f5;
            width: 100%;
        }

        /* Remove old sidebar styles */
        .sidebar {
            display: none;
        }

        .container {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            height: calc(100vh - 100px);
            width: 100%;
            margin: 0;
        }

        .header {
            margin-bottom: 20px;
        }

        .header h1 {
            color: #14569b;
            font-size: 1.8rem;
            font-weight: 600;
        }

        .filter-section {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            gap: 15px;
            justify-content: flex-start;
            flex-wrap: wrap;
        }

        select {
            padding: 8px 15px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background: white;
            min-width: 150px;
        }

        select:focus {
            border-color: #14569b;
            outline: none;
            box-shadow: 0 0 0 3px rgba(20, 86, 155, 0.1);
        }

        .filter-section button {
            background: linear-gradient(45deg,rgb(150, 145, 79),rgb(47, 0, 177));
            color: white;
            padding: 8px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .filter-section button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        .reservation-table {
            height: calc(100vh - 250px);
            overflow-y: auto;
            border-radius: 12px;
            background: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        thead {
            position: sticky;
            top: 0;
            z-index: 2;
        }

        th {
            background: linear-gradient(45deg,rgb(150, 145, 79),rgb(47, 0, 177));
            color: white;
            padding: 15px;
            font-weight: 500;
            text-align: left;
        }

        td {
            padding: 12px 15px;
            border-bottom: 1px solid #e2e8f0;
        }

        tbody tr:hover {
            background: #f8fafc;
        }

        .btn {
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.2s;
        }

        .btn-approve {
            background: #27ae60;
            color: white;
        }

        .btn-reject {
            background: #e74c3c;
            color: white;
        }

        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb {
            background: linear-gradient(45deg,rgb(150, 145, 79),rgb(47, 0, 177));
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(45deg,rgb(47, 0, 177),rgb(150, 145, 79));
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .content {
                margin-top: 120px;
                padding: 10px;
            }
            
            .container {
                padding: 15px;
                height: calc(100vh - 140px);
            }
            
            .filter-section {
                flex-direction: column;
            }
            
            select {
                width: 100%;
            }
        }

        /* Update these styles */
.content-header {
    background: rgb(26, 19, 46);
    color: white;
    padding: 20px;
    border-radius: 15px 15px 0 0;
    margin-bottom: 20px;
}

.content-header h1 {
    font-size: 24px;
    margin: 0;
    margin-bottom: 15px;
}

.action-buttons {
    display: flex;
    gap: 15px;
}

.nav-button {
    background: rgba(255, 255, 255, 0.1);
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 6px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s ease;
}

.nav-button:hover {
    background: rgba(255, 255, 255, 0.2);
    transform: translateY(-2px);
}

.nav-button i {
    font-size: 16px;
}

.pc-management {
    background: white;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
}

.control-panel {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding: 15px;
    background: #f8fafc;
    border-radius: 8px;
    flex-wrap: wrap;
    gap: 15px;
}

.left-controls, .right-controls {
    display: flex;
    gap: 10px;
    align-items: center;
}

.control-btn {
    padding: 8px 16px;
    border: none;
    border-radius: 6px;
    font-weight: 500;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s ease;
    color: white;
}

.control-btn.select-all {
    background: #64748b;
}

.control-btn.available {
    background: #22c55e;
}

.control-btn.used {
    background: #ef4444;
}

.control-btn.maintenance {
    background: #f59e0b;
}

.control-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
}

.pc-item {
    position: relative;
    background: #f8fafc;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    padding: 15px;
    text-align: center;
    transition: all 0.3s ease;
    cursor: pointer;
}

.pc-item.selected {
    background: #3b82f6 !important;
    border-color: #2563eb !important;
    color: white;
}

.pc-item.available {
    background: #dcfce7;
    border-color: #22c55e;
}

.pc-item.used {
    background: #fee2e2;
    border-color: #ef4444;
}

.pc-item.maintenance {
    background: #fef3c7;
    border-color: #f59e0b;
}

.pc-label {
    pointer-events: none;
    font-weight: 500;
    display: block;
}

.pc-checkbox {
    position: absolute;
    opacity: 0;
    width: 100%;
    height: 100%;
    top: 0;
    left: 0;
    margin: 0;
    cursor: pointer;
    z-index: 2;
}

.pc-label {
    pointer-events: none; /* Prevent label from interfering with clicks */
    font-weight: 500;
    color: #4a5568;
    display: block;
}

.pc-item.available {
    background: #dcfce7;
    border-color: #22c55e;
}

.pc-item.used {
    background: #fee2e2;
    border-color: #ef4444;
}

.pc-item.maintenance {
    background: #fef3c7;
    border-color: #f59e0b;
}

.pc-checkbox {
    position: absolute;
    opacity: 0;
}

.pc-label {
    cursor: pointer;
    font-weight: 500;
    color: #4a5568;
}

.pc-checkbox:checked + .pc-label {
    color: #14569b;
}

.pc-item.used {
    background: #fee2e2;
    border-color: #ef4444;
}

.pc-item.available {
    background: #dcfce7;
    border-color: #22c55e;
}

.status-buttons {
    display: flex;
    justify-content: center;
    gap: 15px;
    margin-top: 20px;
}

.status-btn {
    padding: 10px 24px;
    border: none;
    border-radius: 8px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 8px;
}

.status-btn.available {
    background: #22c55e;
    color: white;
}

.status-btn.used {
    background: #ef4444;
    color: white;
}

.status-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
}

/* Add these styles to your existing <style> section */
.pc-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 20px;
    padding: 25px;
    margin-top: 20px;
    background: #f8fafc;
    border-radius: 15px;
}

.pc-item {
    background: white;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    padding: 20px;
    text-align: center;
    transition: all 0.3s ease;
    position: relative;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
}

.pc-item i {
    font-size: 24px;
    color: #4a5568;
    transition: all 0.3s ease;
}

.pc-item:hover {
    transform: translateY(-5px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.pc-item.selected {
    background: #3b82f6 !important;
    border-color: #2563eb !important;
    color: white;
    transform: scale(1.05);
}

.pc-item.selected i {
    color: white;
}

.pc-item.available {
    background: #dcfce7;
    border-color: #22c55e;
}

.pc-item.available i {
    color: #166534;
}

.pc-item.used {
    background: #fee2e2;
    border-color: #ef4444;
}

.pc-item.used i {
    color: #991b1b;
}

.pc-item.maintenance {
    background: #fef3c7;
    border-color: #f59e0b;
}

.pc-item.maintenance i {
    color: #92400e;
}

.pc-label {
    font-weight: 600;
    font-size: 1.1rem;
    color: #4a5568;
    transition: all 0.3s ease;
}

.pc-item.selected .pc-label {
    color: white;
}

.pc-status {
    font-size: 0.8rem;
    padding: 4px 8px;
    border-radius: 12px;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.pc-item.available .pc-status {
    background: #22c55e;
    color: white;
}

.pc-item.used .pc-status {
    background: #ef4444;
    color: white;
}

.pc-item.maintenance .pc-status {
    background: #f59e0b;
    color: white;
}

/* Add these styles to your existing CSS */
.notification {
    position: fixed;
    top: 20px;
    right: 20px;
    padding: 15px 25px;
    border-radius: 8px;
    color: white;
    z-index: 1000;
    animation: slideIn 0.3s ease-out;
}

.notification.success {
    background: #22c55e;
}

.notification.error {
    background: #ef4444;
}

@keyframes slideIn {
    from { transform: translateX(100%); }
    to { transform: translateX(0); }
}

.pc-item {
    cursor: pointer;
    transition: all 0.3s ease;
}

.pc-item.selected {
    background: #3b82f6 !important;
    border-color: #2563eb !important;
    color: white;
    transform: scale(1.05);
}

.pc-item.available {
    background: #dcfce7;
    border-color: #22c55e;
}

.pc-item.used {
    background: #fee2e2;
    border-color: #ef4444;
}

.pc-item.maintenance {
    background: #fef3c7;
    border-color: #f59e0b;
}

.notification-badge {
    background: #dc3545;
    color: white;
    border-radius: 50%;
    padding: 1px 5px;
    font-size: 12px;
    position: absolute;
    top: 15px;
    right: 10px;
    min-width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 2px 4px rgba(239, 68, 68, 0.2);
}

/* Add these styles to your existing CSS */
.nav-buttons {
    display: flex;
    gap: 15px;
    margin-bottom: 25px;
}

.nav-btn {
    padding: 10px 20px;
    background: #f8fafc;
    border: 2px solid #14569b;
    border-radius: 8px;
    color: #14569b;
    text-decoration: none;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s ease;
}

.nav-btn:hover {
    background: linear-gradient(45deg,rgb(150, 145, 79),rgb(47, 0, 177));
    color: white;
    transform: translateY(-2px);
}

.nav-btn.active {
    background: linear-gradient(45deg,rgb(150, 145, 79),rgb(47, 0, 177));
    color: white;
}

.nav-btn i {
    font-size: 1.1rem;
}

@media (max-width: 768px) {
    .nav-buttons {
        flex-direction: column;
        gap: 10px;
    }

    .nav-btn {
        width: 100%;
        justify-content: center;
    }
}

/* Tab styles */
.tab-container {
    margin-bottom: 20px;
}

.tab-buttons {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
}

.tab-btn {
    padding: 10px 20px;
    background: #f8fafc;
    border: 2px solid #14569b;
    border-radius: 8px;
    color: #14569b;
    cursor: pointer;
    font-weight: 500;
    transition: all 0.3s ease;
}

.tab-btn:hover {
    background: linear-gradient(45deg,rgb(150, 145, 79),rgb(47, 0, 177));
    color: white;
    transform: translateY(-2px);
}

.tab-btn.active {
    background: linear-gradient(45deg,rgb(150, 145, 79),rgb(47, 0, 177));
    color: white;
}

.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
}

/* Table styles */
.table-container {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    overflow: hidden;
    margin-top: 20px;
    height: calc(100vh - 250px);
    overflow-y: auto;
}

table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
}

thead {
    position: sticky;
    top: 0;
    z-index: 2;
}

th {
    background: rgb(26, 19, 46);
    color: white;
    padding: 15px;
    font-weight: 500;
    text-align: left;
}

td {
    padding: 12px 15px;
    border-bottom: 1px solid #e2e8f0;
}

tbody tr:hover {
    background: #f8fafc;
}

/* Status badges */
.status-badge {
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: 500;
}

.status-pending {
    background: #fef3c7;
    color: #92400e;
}

.status-approved {
    background: #dcfce7;
    color: #166534;
}

.status-rejected {
    background: #fee2e2;
    color: #991b1b;
}

/* Action buttons */
.action-buttons {
    display: flex;
    gap: 8px;
}

.approve-btn, .reject-btn {
    padding: 6px 12px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.3s;
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    gap: 5px;
}

.approve-btn {
    background: #28a745;
    color: white;
}

.reject-btn {
    background: #dc3545;
    color: white;
}

.approve-btn:hover, .reject-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
}

@media (max-width: 768px) {
    .content {
        margin-top: 120px;
        padding: 10px;
    }
    
    .container {
        padding: 15px;
        height: calc(100vh - 140px);
    }
    
    .tab-buttons {
        flex-direction: column;
    }
    
    .tab-btn {
        width: 100%;
        text-align: center;
    }
    
    .table-container {
        overflow-x: auto;
    }
    
    table {
        min-width: 800px;
    }
}

.left-controls {
    display: flex;
    gap: 10px;
    align-items: center;
}

.room-tabs {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.room-tab {
    padding: 12px 24px;
    background: #f8fafc;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
    font-weight: 500;
    color: #4a5568;
    display: flex;
    align-items: center;
    gap: 8px;
}

.room-tab i {
    font-size: 1.1rem;
    color: rgb(47, 0, 177);
}

.room-tab:hover {
    background: linear-gradient(45deg,rgb(150, 145, 79),rgb(47, 0, 177));
    border-color: transparent;
    color: white;
}

.room-tab.active {
    background: linear-gradient(45deg,rgb(150, 145, 79),rgb(47, 0, 177));
    border-color: transparent;
    color: white;
}

.room-tab.active i {
    color: white;
}

.pc-item.reserved {
    background: #dbeafe;
    border-color: #3b82f6;
}

.pc-item.reserved i {
    color: #1e40af;
}

.pc-item.reserved .pc-status {
    background: #3b82f6;
    color: white;
}
    </style>
</head>
<body>
    <div class="top-nav">
        <div class="nav-left">
            <img src="uploads/<?php echo htmlspecialchars($profile_pic); ?>" alt="Profile Picture" onerror="this.src='assets/default.png';">
            <div class="user-name"><?php echo htmlspecialchars($user_name); ?></div>
        </div>
        <div class="nav-right">
            <a href="admindash.php"></i> Dashboard</a>
            <a href="adannouncement.php"></i> Announcements</a>
            <a href="liststudent.php"></i> Students</a>
            <a href="adsitin.php"></i> Current Sitin</a>
            
            
            <a href="adlabresources.php"></i> Lab Resources</a>
            <a href="adlabsched.php"></i> Lab Schedule</a>
            <a href="adreservation.php" style="position: relative;">
                </i> RESERVATION    
                <?php if ($pendingCount > 0): ?>
                    <span class="notification-badge"><?php echo $pendingCount; ?></span>
                <?php endif; ?>
            </a>
            <a href="adfeedback.php"></i> Feedback</a>
            <a href="admindash.php?logout=true" class="logout-button">
                 Log Out</a>
        </div>
    </div>

    <div class="content">
        <div class="container">
            <div class="header">
                <h1>Reservation Management</h1>
            </div>
            
            <div class="tab-container">
                <div class="tab-buttons">
                    <button class="tab-btn active" onclick="showTab('management')">Reservation Management</button>
                    <button class="tab-btn" onclick="showTab('requests')">Pending Requests</button>
                    <button class="tab-btn" onclick="showTab('logs')">Reservation Logs</button>
                </div>

                <!-- Reservation Management Tab -->
                <div id="management" class="tab-content active">
                    <div class="content-header">
                        
                    </div>
                    <div class="pc-management">
                        <div class="control-panel">
                            <div class="left-controls">
                                <div class="room-tabs">
                                    <div class="room-tab active" onclick="changeRoom('524')">
                                        <i class="fas fa-door-open"></i>
                                        Room 524
                                    </div>
                                    <div class="room-tab" onclick="changeRoom('526')">
                                        <i class="fas fa-door-open"></i>
                                        Room 526
                                    </div>
                                    <div class="room-tab" onclick="changeRoom('528')">
                                        <i class="fas fa-door-open"></i>
                                        Room 528
                                    </div>
                                    <div class="room-tab" onclick="changeRoom('530')">
                                        <i class="fas fa-door-open"></i>
                                        Room 530
                                    </div>
                                    <div class="room-tab" onclick="changeRoom('542')">
                                        <i class="fas fa-door-open"></i>
                                        Room 542
                                    </div>
                                    <div class="room-tab" onclick="changeRoom('544')">
                                        <i class="fas fa-door-open"></i>
                                        Room 544
                                    </div>
                                </div>
                            </div>
                            <div class="right-controls">
                                <button onclick="selectAllPCs()" class="control-btn select-all">
                                    <i class="fas fa-check-double"></i> Select All
                                </button>
                                <button onclick="updatePCStatus('available')" class="control-btn available">
                                    <i class="fas fa-check-circle"></i> Set Available
                                </button>
                                <button onclick="updatePCStatus('used')" class="control-btn used">
                                    <i class="fas fa-times-circle"></i> Set Used
                                </button>
                                <button onclick="updatePCStatus('maintenance')" class="control-btn maintenance">
                                    <i class="fas fa-tools"></i> Set Maintenance
                                </button>
                            </div>
                        </div>
                        <div class="pc-grid">
                            <?php
                            $pcsPerRow = 5;
                            $totalPCs = 40;
                            for ($i = 1; $i <= $totalPCs; $i++) {
                                echo '<div class="pc-item available" id="pcItem' . $i . '" onclick="togglePC(' . $i . ')">';
                                echo '<i class="fas fa-desktop"></i>';
                                echo '<span class="pc-label">PC' . $i . '</span>';
                                echo '<span class="pc-status">Available</span>';
                                echo '</div>';
                            }
                            ?>
                        </div>
                    </div>
                </div>

                <!-- Pending Requests Tab -->
                <div id="requests" class="tab-content">
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID Number</th>
                                    <th>Name</th>
                                    <th>Date Time</th>
                                    <th>Date Time to Reserve</th>
                                    <th>Lab Room</th>
                                    <th>PC Number</th>
                                    <th>Purpose</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $requests_query = "SELECT r.*, 
                                                 COALESCE(u.LASTNAME, '') as LASTNAME,
                                                 COALESCE(u.FIRSTNAME, '') as FIRSTNAME,
                                                 COALESCE(u.MIDNAME, '') as MIDNAME,
                                                 DATE_FORMAT(r.created_at, '%M %d, %Y %h:%i %p') as request_datetime
                                                 FROM reservations r 
                                                 LEFT JOIN user u ON r.student_id = u.IDNO 
                                                 WHERE r.status = 'pending' 
                                                 ORDER BY r.date ASC";
                                $requests_result = mysqli_query($con, $requests_query);

                                while ($row = mysqli_fetch_assoc($requests_result)) {
                                    $fullName = '';
                                    if (!empty($row['LASTNAME']) || !empty($row['FIRSTNAME'])) {
                                        $fullName = $row['LASTNAME'] . ', ' . $row['FIRSTNAME'];
                                        if (!empty($row['MIDNAME'])) {
                                            $fullName .= ' ' . substr($row['MIDNAME'], 0, 1) . '.';
                                        }
                                    } else {
                                        $fullName = 'N/A';
                                    }

                                    // Format the reservation date and time
                                    $reserveDateTime = date('M d, Y', strtotime($row['date'])) . ' ' . htmlspecialchars($row['time']);

                                    echo "<tr>";
                                    echo "<td>" . htmlspecialchars($row['student_id']) . "</td>";
                                    echo "<td>" . htmlspecialchars($fullName) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['request_datetime']) . "</td>";
                                    echo "<td>" . $reserveDateTime . "</td>";
                                    echo "<td>Room " . htmlspecialchars($row['room']) . "</td>";
                                    echo "<td>PC " . htmlspecialchars($row['seat_number']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['purpose']) . "</td>";
                                    echo "<td><span class='status-badge status-" . htmlspecialchars($row['status']) . "'>" . ucfirst(htmlspecialchars($row['status'])) . "</span></td>";
                                    echo "<td class='action-buttons'>";
                                    echo "<button class='approve-btn' onclick='approveReservation(" . $row['id'] . ")'><i class='fas fa-check'></i> Approve</button>";
                                    echo "<button class='reject-btn' onclick='rejectReservation(" . $row['id'] . ")'><i class='fas fa-times'></i> Reject</button>";
                                    echo "</td>";
                                    echo "</tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Reservation Logs Tab -->
                <div id="logs" class="tab-content">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h3 style="margin: 0; color: rgb(26, 19, 46);">Reservation Logs</h3>
                        <button onclick="printAllLogs()" style="background: rgb(6, 128, 77); color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; display: flex; align-items: center; gap: 8px;">
                            <i class="fas fa-print"></i> Print All Logs
                        </button>
                    </div>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID Number</th>
                                    <th>Name</th>
                                    <th>Date Requested</th>
                                    <th>Date to Reserve</th>
                                    <th>Time</th>
                                    <th>Lab Room</th>
                                    <th>PC Number</th>
                                    <th>Purpose</th>
                                    <th>Status</th>
                                    <th>Action Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $logs_query = "SELECT r.*, 
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
                                $logs_result = mysqli_query($con, $logs_query);

                                while ($row = mysqli_fetch_assoc($logs_result)) {
                                    $fullName = '';
                                    if (!empty($row['LASTNAME']) || !empty($row['FIRSTNAME'])) {
                                        $fullName = $row['LASTNAME'] . ', ' . $row['FIRSTNAME'];
                                        if (!empty($row['MIDNAME'])) {
                                            $fullName .= ' ' . substr($row['MIDNAME'], 0, 1) . '.';
                                        }
                                    } else {
                                        $fullName = 'N/A';
                                    }

                                    // Format the action date using the timestamp from reservation_logs
                                    $actionDate = !empty($row['action_date']) ? 
                                                 date('M d, Y h:i A', strtotime($row['action_date'])) : 
                                                 'N/A';

                                    echo "<tr>";
                                    echo "<td>" . htmlspecialchars($row['student_id']) . "</td>";
                                    echo "<td>" . htmlspecialchars($fullName) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['date_requested']) . "</td>";
                                    echo "<td>" . date('M d, Y', strtotime($row['date'])) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['time']) . "</td>";
                                    echo "<td>Room " . htmlspecialchars($row['room']) . "</td>";
                                    echo "<td>PC " . htmlspecialchars($row['seat_number']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['purpose']) . "</td>";
                                    echo "<td><span class='status-badge status-" . htmlspecialchars($row['status']) . "'>" . ucfirst(htmlspecialchars($row['status'])) . "</span></td>";
                                    echo "<td>" . $actionDate . "</td>";
                                    echo "</tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Tab switching functionality
    function showTab(tabId) {
        // Hide all tab contents
        document.querySelectorAll('.tab-content').forEach(tab => {
            tab.classList.remove('active');
        });
        
        // Remove active class from all buttons
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.classList.remove('active');
        });
        
        // Show selected tab content
        document.getElementById(tabId).classList.add('active');
        
        // Add active class to clicked button
        event.target.classList.add('active');
    }

    // Existing PC management functions
    let selectedRoom = '';

    function togglePC(pcNumber) {
        if (!selectedRoom) {
            alert('Please select a room first');
            return;
        }
        
        const pcItem = document.querySelector(`#pcItem${pcNumber}`);
        if (pcItem) {
            pcItem.classList.toggle('selected');
        }
    }

    async function updatePCStatus(status) {
        if (!selectedRoom) {
            alert('Please select a room first');
            return;
        }

        const selectedPCs = document.querySelectorAll('.pc-item.selected');
        if (selectedPCs.length === 0) {
            alert('Please select at least one PC');
            return;
        }

        try {
            const pcNumbers = Array.from(selectedPCs).map(item => 
                parseInt(item.id.replace('pcItem', ''))
            );

            const response = await fetch('update_pc_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    room: selectedRoom,
                    pcNumbers: pcNumbers,
                    status: status
                })
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            if (data.success) {
                selectedPCs.forEach(item => {
                    item.classList.remove('selected', 'available', 'used', 'maintenance');
                    item.classList.add(status);
                    const statusLabel = item.querySelector('.pc-status');
                    if (statusLabel) {
                        statusLabel.textContent = status.charAt(0).toUpperCase() + status.slice(1);
                    }
                });
                clearSelections();
                showNotification(`PCs updated to ${status} successfully`, 'success');
            } else {
                throw new Error(data.message || 'Failed to update PC status');
            }
        } catch (error) {
            console.error('Error:', error);
            showNotification(error.message, 'error');
        }
    }

    async function changeRoom(roomId) {
        if (!roomId) {
            clearSelections();
            return;
        }

        selectedRoom = roomId;
        
        // Update active tab
        document.querySelectorAll('.room-tab').forEach(tab => {
            if (tab.textContent.includes('Room ' + roomId)) {
                tab.classList.add('active');
            } else {
                tab.classList.remove('active');
            }
        });

        try {
            const response = await fetch(`get_pc_status.php?room=${roomId}`);
            const data = await response.json();
            
            if (!data.success) {
                throw new Error(data.message);
            }

            document.querySelectorAll('.pc-item').forEach(item => {
                item.className = 'pc-item available';
            });
            
            data.data.forEach(pc => {
                const pcItem = document.querySelector(`#pcItem${pc.pc_number}`);
                if (pcItem) {
                    // Check if PC is in pending reservations
                    const isPending = data.pending_reservations && 
                                    data.pending_reservations.includes(pc.pc_number);
                    
                    if (isPending) {
                        pcItem.className = 'pc-item reserved';
                        const statusLabel = pcItem.querySelector('.pc-status');
                        if (statusLabel) {
                            statusLabel.textContent = 'Reserved';
                        }
                    } else {
                        pcItem.className = `pc-item ${pc.status}`;
                        const statusLabel = pcItem.querySelector('.pc-status');
                        if (statusLabel) {
                            statusLabel.textContent = pc.status.charAt(0).toUpperCase() + pc.status.slice(1);
                        }
                    }
                }
            });

            clearSelections();
        } catch (error) {
            console.error('Error:', error);
            showNotification('Error loading room status: ' + error.message, 'error');
        }
    }

    function selectAllPCs() {
        if (!selectedRoom) {
            alert('Please select a room first');
            return;
        }
        
        const pcItems = document.querySelectorAll('.pc-item');
        const anyUnselected = Array.from(pcItems).some(item => 
            !item.classList.contains('selected')
        );
        
        pcItems.forEach(item => {
            item.classList.toggle('selected', anyUnselected);
        });
    }

    function clearSelections() {
        document.querySelectorAll('.pc-item').forEach(item => {
            item.classList.remove('selected');
        });
    }

    function showNotification(message, type = 'success') {
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        notification.textContent = message;
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.remove();
        }, 3000);
    }

    // Reservation management functions
    function approveReservation(id) {
        if (confirm('Are you sure you want to approve this reservation?')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="approve">
                <input type="hidden" name="reservation_id" value="${id}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    }

    function rejectReservation(id) {
        if (confirm('Are you sure you want to reject this reservation?')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="reject">
                <input type="hidden" name="reservation_id" value="${id}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    }

    // Initialize on page load
    document.addEventListener('DOMContentLoaded', () => {
        const roomSelect = document.querySelector('.room-select');
        if (roomSelect.value) {
            changeRoom(roomSelect.value);
        }
    });

    function printAllLogs() {
        // Create a new window for printing
        let printWindow = window.open("", "_blank");
        
        // Create the print content with enhanced styling
        let printContent = `
            <html>
            <head>
                <title>All Reservation Logs Report</title>
                <style>
                    @page {
                        size: landscape;
                        margin: 1cm;
                    }
                    body { 
                        font-family: Arial, sans-serif;
                        margin: 0;
                        padding: 20px;
                    }
                    .header {
                        text-align: center;
                        margin-bottom: 20px;
                        padding-bottom: 10px;
                        border-bottom: 2px solid rgb(26, 19, 46);
                    }
                    .header h2 {
                        color: rgb(26, 19, 46);
                        margin: 0;
                        font-size: 24px;
                    }
                    .header p {
                        color: #666;
                        margin: 5px 0 0 0;
                        font-size: 14px;
                    }
                    table { 
                        width: 100%;
                        border-collapse: collapse;
                        margin-bottom: 20px;
                        font-size: 12px;
                    }
                    th, td { 
                        border: 1px solid #ddd;
                        padding: 8px;
                        text-align: left;
                    }
                    th { 
                        background: rgb(26, 19, 46);
                        color: white;
                        font-weight: 500;
                    }
                    tr:nth-child(even) { 
                        background-color: #f8f9fa;
                    }
                    .status-badge { 
                        padding: 4px 8px;
                        border-radius: 12px;
                        font-size: 0.8rem;
                        display: inline-block;
                    }
                    .status-approved { 
                        background: #dcfce7;
                        color: #166534;
                    }
                    .status-rejected { 
                        background: #fee2e2;
                        color: #991b1b;
                    }
                    .footer {
                        text-align: center;
                        margin-top: 20px;
                        padding-top: 10px;
                        border-top: 1px solid #ddd;
                        font-size: 12px;
                        color: #666;
                    }
                    @media print {
                        body { margin: 0; }
                        .no-print { display: none; }
                        table { page-break-inside: auto; }
                        tr { page-break-inside: avoid; page-break-after: auto; }
                    }
                </style>
            </head>
            <body>
                <div class="header">
                    <h2>All Reservation Logs Report</h2>
                    <p>Generated on: ${new Date().toLocaleString()}</p>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>ID Number</th>
                            <th>Name</th>
                            <th>Date Requested</th>
                            <th>Date to Reserve</th>
                            <th>Time</th>
                            <th>Lab Room</th>
                            <th>PC Number</th>
                            <th>Purpose</th>
                            <th>Status</th>
                            <th>Action Date</th>
                        </tr>
                    </thead>
                    <tbody>`;

        // Fetch all logs from the database using AJAX
        fetch('get_all_logs.php')
            .then(response => response.json())
            .then(logs => {
                logs.forEach(log => {
                    printContent += '<tr>';
                    printContent += `<td>${log.student_id}</td>`;
                    printContent += `<td>${log.full_name}</td>`;
                    printContent += `<td>${log.date_requested}</td>`;
                    printContent += `<td>${log.reserve_date}</td>`;
                    printContent += `<td>${log.time}</td>`;
                    printContent += `<td>Room ${log.room}</td>`;
                    printContent += `<td>PC ${log.seat_number}</td>`;
                    printContent += `<td>${log.purpose}</td>`;
                    printContent += `<td><span class="status-badge status-${log.status}">${log.status.charAt(0).toUpperCase() + log.status.slice(1)}</span></td>`;
                    printContent += `<td>${log.action_date}</td>`;
                    printContent += '</tr>';
                });

                printContent += `
                    </tbody>
                </table>
                <div class="footer">
                    <p>This is a computer-generated report. No signature is required.</p>
                </div>
            </body>
            </html>`;

                // Write the content to the new window
                printWindow.document.write(printContent);
                printWindow.document.close();

                // Wait for content to load then print
                printWindow.onload = function() {
                    printWindow.print();
                    // Close the window after printing
                    printWindow.onafterprint = function() {
                        printWindow.close();
                    };
                };
            })
            .catch(error => {
                console.error('Error fetching logs:', error);
                alert('Error generating print report. Please try again.');
                printWindow.close();
            });
    }
    </script>
</body>
</html>