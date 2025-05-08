<?php
session_start();
include("connector.php");

// Set timezone to Philippines
date_default_timezone_set('Asia/Manila');

// Check if admin is logged in
if (!isset($_SESSION['Username'])) {
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
    <title>Reservation Logs</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
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

        .nav-right a:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateY(-2px);
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

        .table-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            margin-top: 20px;
        }

        #logsTable {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        #logsTable thead th {
            background: #14569b;
            color: white;
            padding: 15px;
            font-weight: 500;
            text-align: left;
            font-size: 0.95rem;
        }

        #logsTable tbody td {
            padding: 12px 15px;
            border-bottom: 1px solid #e2e8f0;
            color: #2a3f5f;
            font-size: 0.95rem;
        }

        #logsTable tbody tr:hover {
            background: #f8fafc;
        }

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
            
            #logsTable {
                min-width: 800px;
            }

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
                <h1>Reservation Logs</h1>
                <div class="nav-buttons">
                    <a href="reservation_requests.php" class="nav-btn <?php echo basename($_SERVER['PHP_SELF']) == 'adreservation.php' ? 'active' : ''; ?>">
                        <i class="fas fa-clock"></i> Reservation Management
                    </a>
                    <a href="reservation_requests.php" class="nav-btn <?php echo basename($_SERVER['PHP_SELF']) == 'reservation_request.php' ? 'active' : ''; ?>">
                        <i class="fas fa-history"></i> Pending Requests
                    </a>
                    <a href="reservation_logs.php" class="nav-btn" <?php echo basename($_SERVER['PHP_SELF']) == 'reservation_logs.php' ? 'active' : ''; ?>">
                        <i class="fas fa-history"></i> Reservation Logs
                    </a>
                </div>
            </div>
            <div class="table-container">
                <table id="logsTable">
                    <thead>
                        <tr>
                            <th>ID Number</th>
                            <th>Name</th>
                            <th>Date Requested</th>
                            <th>Date to Reserve</th>
                            <th>Time</th>
                            <th>Lab Room</th>
                            <th>Purpose</th>
                            <th>Status</th>
                            <th>Action Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Query to get all reservations with their status
                        $query = "SELECT r.*, 
                                 COALESCE(u.LASTNAME, '') as LASTNAME,
                                 COALESCE(u.FIRSTNAME, '') as FIRSTNAME,
                                 COALESCE(u.MIDNAME, '') as MIDNAME,
                                 DATE_FORMAT(r.created_at, '%M %d, %Y %h:%i %p') as date_requested
                                 FROM reservations r 
                                 LEFT JOIN user u ON r.student_id = u.IDNO 
                                 WHERE r.status IN ('approved', 'rejected')
                                 ORDER BY r.date DESC, r.created_at DESC";
                        $result = mysqli_query($con, $query);

                        if (!$result) {
                            echo "<tr><td colspan='9'>Error: " . mysqli_error($con) . "</td></tr>";
                        } else {
                            while ($row = mysqli_fetch_assoc($result)) {
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
                                echo "<td>" . htmlspecialchars($row['date_requested']) . "</td>";
                                echo "<td>" . date('M d, Y', strtotime($row['date'])) . "</td>";
                                echo "<td>" . htmlspecialchars($row['time']) . "</td>";
                                echo "<td>Room " . htmlspecialchars($row['room']) . "</td>";
                                echo "<td>" . htmlspecialchars($row['purpose']) . "</td>";
                                echo "<td><span class='status-badge status-" . htmlspecialchars($row['status']) . "'>" . ucfirst(htmlspecialchars($row['status'])) . "</span></td>";
                                echo "<td>" . htmlspecialchars($row['date_requested']) . "</td>";
                                echo "</tr>";
                            }
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>