<?php
session_start();
include("connector.php");

// Set timezone to Philippines
date_default_timezone_set('Asia/Manila');
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Ensure MySQL is using the correct timezone
$con->query("SET time_zone = '+08:00'");
$con->query("SET @@session.time_zone = '+08:00'");

// Debug logging
error_log("adreservation.php started");
error_log("Session data: " . print_r($_SESSION, true));

// Function to approve reservation
function approveReservation($con, $reservation_id) {
    try {
        // First check if reservation exists and is pending
        $check_query = "SELECT * FROM reservations WHERE id = ? AND status = 'pending'";
        $check_stmt = $con->prepare($check_query);
        $check_stmt->bind_param("i", $reservation_id);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        
        if ($result->num_rows > 0) {
            // Update the status to approved
            $update_query = "UPDATE reservations SET status = 'approved', created_at = NOW() WHERE id = ?";
            $update_stmt = $con->prepare($update_query);
            $update_stmt->bind_param("i", $reservation_id);
            
            if ($update_stmt->execute()) {
                return true;
            }
        }
        return false;
    } catch (Exception $e) {
        error_log("Error in approveReservation: " . $e->getMessage());
        return false;
    }
}

// Function to reject reservation
function rejectReservation($con, $reservation_id) {
    try {
        // First check if reservation exists and is pending
        $check_query = "SELECT * FROM reservations WHERE id = ? AND status = 'pending'";
        $check_stmt = $con->prepare($check_query);
        $check_stmt->bind_param("i", $reservation_id);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        
        if ($result->num_rows > 0) {
            // Update the status to rejected
            $update_query = "UPDATE reservations SET status = 'rejected', created_at = NOW() WHERE id = ?";
            $update_stmt = $con->prepare($update_query);
            $update_stmt->bind_param("i", $reservation_id);
            
            if ($update_stmt->execute()) {
                return true;
            }
        }
        return false;
    } catch (Exception $e) {
        error_log("Error in rejectReservation: " . $e->getMessage());
        return false;
    }
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['reservation_id'])) {
        $reservation_id = $_POST['reservation_id'];
        $action = $_POST['action'];
        
        if ($action === 'approve') {
            if (approveReservation($con, $reservation_id)) {
                echo "<script>
                    alert('Reservation approved successfully!');
                    window.location.reload();
                </script>";
            } else {
                echo "<script>alert('Failed to approve reservation. It may have already been processed.');</script>";
            }
        } elseif ($action === 'reject') {
            if (rejectReservation($con, $reservation_id)) {
                echo "<script>
                    alert('Reservation rejected successfully!');
                    window.location.reload();
                </script>";
            } else {
                echo "<script>alert('Failed to reject reservation. It may have already been processed.');</script>";
            }
        }
    }
}

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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservation Requests</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="https://www.w3schools.com/w3css/4/w3.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        html, body {
            background: linear-gradient(135deg, #14569b, #2a3f5f);
            display: flex;
            flex-direction: column;
            width: 100%;
        }

        /* Top Navigation Bar Styles */
        .top-nav {
            background-color: rgba(42, 63, 95, 0.9);
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
            background: rgba(220, 53, 69, 0.1);
            margin-left: 10px;
        }

        .nav-right .logout-button:hover {
            background: rgba(220, 53, 69, 0.2);
        }

        .content {
            margin-top: 80px;
            padding: 30px;
            min-height: calc(100vh - 80px);
            background: #f0f2f5;
        }

        .container {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            height: 90vh;
            max-width: auto;
            margin: 0 auto;
            overflow-y: auto;
        }

        h1 {
            color: #14569b;
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 25px;
        }

        /* Table styles */
        .table-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            margin-top: 20px;
        }

        #requestsTable {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        #requestsTable thead th {
            background: #14569b;
            color: white;
            padding: 15px;
            font-weight: 500;
            text-align: left;
            font-size: 0.95rem;
        }

        #requestsTable tbody td {
            padding: 12px 15px;
            border-bottom: 1px solid #e2e8f0;
            color: #2a3f5f;
            font-size: 0.95rem;
        }

        #requestsTable tbody tr:hover {
            background: #f8fafc;
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

        /* Animation for table rows */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        #requestsTable tbody tr {
            animation: fadeIn 0.3s ease;
        }

        /* Responsive styles */
        @media (max-width: 768px) {
            .top-nav {
                flex-direction: column;
                padding: 10px;
            }
            
            .nav-left {
                margin-bottom: 10px;
            }
            
            .nav-right {
                width: 100%;
                justify-content: center;
                flex-wrap: wrap;
            }
            
            .nav-right a {
                font-size: 0.8rem;
                padding: 6px 10px;
            }
            
            .content {
                margin-top: 120px;
            }
            
            .container {
                padding: 15px;
            }
            
            .table-container {
                overflow-x: auto;
            }
            
            #requestsTable {
                min-width: 800px;
            }
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
            background: #14569b;
            color: white;
            transform: translateY(-2px);
        }

        .nav-btn.active {
            background: #14569b;
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
    </style>
</head>
<body>
    <div class="top-nav">
        <div class="nav-left">
            <img src="uploads/<?php echo htmlspecialchars($profile_pic); ?>" alt="Profile Picture" onerror="this.src='assets/default.png';">
            <div class="user-name"><?php echo htmlspecialchars($user_name); ?></div>
        </div>
        <div class="nav-right">
            <a href="admindash.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="adannouncement.php"><i class="fas fa-bullhorn"></i> Announcements</a>
            <a href="liststudent.php"><i class="fas fa-user-graduate"></i> Students</a>
            <a href="adsitin.php"><i class="fas fa-chair"></i> Current Sitin</a>
            <a href="adreservation.php"><i class="fas fa-calendar-check"></i> Reservations</a>
            <a href="adlabresources.php"><i class="fas fa-book"></i> Lab Resources</a>
            <a href="adlabsched.php"><i class="fas fa-calendar"></i> Lab Schedule</a>
            <a href="adfeedback.php"><i class="fas fa-book-open"></i> Feedback</a>
            <a href="admindash.php?logout=true" class="logout-button"><i class="fas fa-sign-out-alt"></i> Log Out</a>
        </div>
    </div>

    <div class="content">
        <div class="container">
            <div class="header">
                <h1>Reservation Requests</h1>
                <div class="nav-buttons">
                    <a href="adreservation.php" class="nav-btn <?php echo basename($_SERVER['PHP_SELF']) == 'adreservation.php' ? 'active' : ''; ?>">
                        <i class="fas fa-clock"></i> Reservation Management
                    </a>
                    <a href="reservation_requests.php" class="nav-btn <?php echo basename($_SERVER['PHP_SELF']) == 'reservation_requests.php' ? 'active' : ''; ?>">
                        <i class="fas fa-history"></i> Pending Requests
                    </a>
                    <a href="reservation_logs.php" class="nav-btn" <?php echo basename($_SERVER['PHP_SELF']) == 'reservation_logs.php' ? 'active' : ''; ?>">
                        <i class="fas fa-history"></i> Reservation Logs
                    </a>
                </div>
            </div>
            <div class="table-container">
                <table id="requestsTable">
                    <thead>
                        <tr>
                            <th>ID Number</th>
                            <th>Name</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Lab Room</th>
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
                                         COALESCE(u.MIDNAME, '') as MIDNAME
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

                            echo "<tr>";
                            echo "<td>" . htmlspecialchars($row['student_id']) . "</td>";
                            echo "<td>" . htmlspecialchars($fullName) . "</td>";
                            echo "<td>" . date('M d, Y', strtotime($row['date'])) . "</td>";
                            echo "<td>" . htmlspecialchars($row['time']) . "</td>";
                            echo "<td>Room " . htmlspecialchars($row['room']) . "</td>";
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
    </div>

    <script>
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
    </script>
</body>
</html>