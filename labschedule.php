<?php
session_start();
include("connector.php");

// Check if user is logged in
if (!isset($_SESSION['Username'])) {
    header("Location: login.php");
    exit();
}

// Fetch user details
$username = $_SESSION['Username'];
$query = "SELECT PROFILE_PIC, FIRSTNAME, MIDNAME, LASTNAME FROM user WHERE USERNAME = '$username'";
$result = mysqli_query($con, $query);

if ($result && mysqli_num_rows($result) > 0) {
    $row = mysqli_fetch_assoc($result);
    $profile_pic = !empty($row['PROFILE_PIC']) ? htmlspecialchars($row['PROFILE_PIC']) : 'default.jpg';
    $user_name = htmlspecialchars($row['LASTNAME'] . ' ' . substr($row['FIRSTNAME'], 0, 1) . '.');  
} else {
    $profile_pic = 'default.jpg';
    $user_name = 'User';
}

// Fetch schedules
$schedules = mysqli_query($con, "SELECT * FROM lab_schedules ORDER BY last_updated DESC");

// Fetch PC statuses
$pc_statuses = [];
$pc_status_query = "SELECT room_number, pc_number, status FROM pc_status";
$pc_status_result = mysqli_query($con, $pc_status_query);
while ($row = mysqli_fetch_assoc($pc_status_result)) {
    $pc_statuses[$row['room_number']][$row['pc_number']] = $row['status'];
}

// Define available rooms
$rooms = ['524', '526', '528', '530', '542', '544'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lab Schedule</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            display: flex;
            background: #f0f2f5;
            min-height: 100vh;
            position: relative;
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
            background: rgba(220, 53, 69, 0.1);
            margin-left: 10px;
        }

        .nav-right .logout-button:hover {
            background: rgba(220, 53, 69, 0.2);
        }

        /* Content Area */
        .content {
            margin-top: 80px;
            padding: 20px;
            min-height: calc(100vh - 80px);
            background: #f5f5f5;
            width: 100%;
        }

        /* Remove old sidebar styles */
        .sidebar {
            display: none;
        }

        .schedule-container {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            height: calc(100vh - 100px);
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .resources-header {
            background: rgb(26, 19, 46);
            color: white;
            padding: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-radius: 15px 15px 0 0;
        }

        .resources-header h1 {
            font-size: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .back-button {
            background: rgba(255, 255, 255, 0.15);
            color: white;
            padding: 8px 20px;
            border-radius: 8px;
            text-decoration: none;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.95rem;
        }

        .back-button:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateY(-2px);
        }

        .schedule-table {
            flex: 1;
            overflow: auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .schedule-table table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .schedule-table th {
            background: rgb(26, 19, 46);
            color: white;
            padding: 15px;
            text-align: center;
            font-weight: 500;
            position: sticky;
            top: 0;
            z-index: 10;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .schedule-table td {
            padding: 12px;
            border: 1px solid #e2e8f0;
            text-align: center;
            transition: all 0.3s ease;
        }

        .schedule-table td:hover {
            background: #f8fafc;
        }

        .time-slot {
            background: #f8fafc;
            font-weight: 500;
            color: #2d3748;
            position: sticky;
            left: 0;
            z-index: 5;
            border-right: 2px solid #e2e8f0;
        }

        .status-btn {
            padding: 10px 15px;
            border-radius: 8px;
            display: inline-block;
            font-weight: 600;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            border: 2px solid transparent;
            cursor: default;
            pointer-events: none;
            user-select: none;
        }

        .status-btn.available {
            background: #dcfce7;
            color: #166534;
            border-color: #86efac;
        }

        .status-btn.unavailable {
            background: #fee2e2;
            color: #991b1b;
            border-color: #fca5a5;
            font-weight: 600;
        }

        .status-btn.occupied {
            background: #fee2e2;
            color: #991b1b;
            border-color: #fca5a5;
        }

        /* Remove all hover effects */
        .status-btn:hover {
            transform: none;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .status-btn.available:hover {
            background: #dcfce7;
            border-color: #86efac;
        }

        .status-btn.unavailable:hover,
        .status-btn.occupied:hover {
            background: #fee2e2;
            border-color: #fca5a5;
        }

        /* Add alternating row colors */
        .schedule-table tbody tr:nth-child(even) {
            background: #f8fafc;
        }

        .schedule-table tbody tr:hover {
            background: #f1f5f9;
        }

        /* Style the filter section */
        .filter-section {
            background: white;
            padding: 20px;
            border-bottom: 1px solid #e2e8f0;
            margin-bottom: 20px;
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
            color: linear-gradient(45deg,rgb(150, 145, 79),rgb(47, 0, 177));;
        }

        .room-tab:hover {
            background: linear-gradient(45deg,rgb(150, 145, 79),rgb(47, 0, 177));
            border-color: linear-gradient(45deg,rgb(150, 145, 79),rgb(47, 0, 177));
            transform: translateY(-2px);
        }

        .room-tab.active {
            background: linear-gradient(45deg,rgb(150, 145, 79),rgb(47, 0, 177));
            border-color: linear-gradient(45deg,rgb(150, 145, 79),rgb(47, 0, 177));
            color: white;
        }

        .room-tab.active i {
            color: white;
        }

        @media (max-width: 768px) {
            .content {
                padding: 15px;
            }
            
            .room-tabs {
                gap: 8px;
            }
            
            .room-tab {
                padding: 10px 16px;
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
<div class="top-nav">
    <div class="nav-left">
        <img src="uploads/<?php echo $profile_pic; ?>" alt="Profile Picture" onerror="this.src='assets/default.jpg';">
        <div class="user-name"><?php echo htmlspecialchars($user_name); ?></div>
    </div>
    <div class="nav-right">
        <a href="dashboard.php"> Dashboard</a>
        <a href="viewAnnouncement.php"> Announcements and Resources</a>
        <a href="profile.php"> Edit Profile</a>
        <a href="labRules&Regulations.php"> Lab Rules</a>
        <a href="labschedule.php"> Lab Schedules</a>
        <a href="reservation.php"> Reservation</a>
        <a href="history.php"> History</a>
        <a href="login.php" class="logout-button"> Log Out</a>
    </div>
</div>

<div class="content">
    <div class="schedule-container">
        <div class="resources-header">
            <h1>
                <i class="fas fa-calendar-alt"></i>
                Laboratory Schedule
            </h1>
            <a >
            </a>
        </div>

        <div class="filter-section">
            <div class="room-tabs">
                <?php foreach ($rooms as $room): ?>
                    <div class="room-tab <?php echo (!isset($_GET['room']) && $room == '524') || (isset($_GET['room']) && $_GET['room'] == $room) ? 'active' : ''; ?>" 
                         onclick="filterRoom('<?php echo $room; ?>')">
                        <i class="fas fa-door-open"></i>
                        Room <?php echo $room; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="schedule-table">
            <table>
                <thead>
                    <tr>
                        <th>Time Slot</th>
                        <th>Monday</th>
                        <th>Tuesday</th>
                        <th>Wednesday</th>
                        <th>Thursday</th>
                        <th>Friday</th>
                        <th>Saturday</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $time_slots = [
                        '7:30AM-9:00AM',
                        '9:00AM-10:30AM',
                        '10:30AM-12:00PM',
                        '12:00PM-1:00PM',
                        '1:00PM-3:00PM',
                        '3:00PM-4:30PM',
                        '4:30PM-6:00PM',
                        '6:00PM-7:30PM',
                        '7:30PM-9:00PM'
                    ];
                    
                    foreach ($time_slots as $time_slot): ?>
                        <tr>
                            <td class="time-slot"><?php echo $time_slot; ?></td>
                            <?php foreach (['M', 'T', 'W', 'TH', 'F', 'S'] as $day): ?>
                                <td class="status-cell">
                                    <?php
                                    $query = "SELECT status FROM lab_schedules 
                                              WHERE room_number = ? 
                                              AND day_group = ? 
                                              AND time_slot = ?";
                                    $stmt = $con->prepare($query);
                                    $current_room = isset($_GET['room']) ? $_GET['room'] : '524';
                                    $stmt->bind_param("sss", $current_room, $day, $time_slot);
                                    $stmt->execute();
                                    $result = $stmt->get_result();
                                    $schedule = $result->fetch_assoc();
                                    
                                    // Check if any PC in this room is marked as used
                                    $has_used_pc = false;
                                    if (isset($pc_statuses[$current_room])) {
                                        foreach ($pc_statuses[$current_room] as $status) {
                                            if ($status === 'used') {
                                                $has_used_pc = true;
                                                break;
                                            }
                                        }
                                    }
                                    
                                    $status = $schedule ? $schedule['status'] : 'Available';
                                    // If any PC is used, override the status to show as occupied
                                    if ($has_used_pc) {
                                        $status = 'Occupied';
                                    }
                                    $statusClass = strtolower($status);
                                    ?>
                                    <div class="status-btn <?php echo $statusClass; ?>">
                                        <?php echo $status; ?>
                                    </div>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
// Only allow room filtering, no schedule setting
function filterRoom(room) {
    window.location.href = 'labschedule.php?room=' + room;
}

document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const room = urlParams.get('room') || '524';
    
    // Update active tab
    const tabs = document.querySelectorAll('.room-tab');
    tabs.forEach(tab => {
        if (tab.textContent.includes('Room ' + room)) {
            tab.classList.add('active');
        } else {
            tab.classList.remove('active');
        }
    });
});
</script>
</body>
</html>