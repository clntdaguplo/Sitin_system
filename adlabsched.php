<?php
session_start();
include("connector.php");

if (!isset($_SESSION['Username'])) {
    header("Location: login.php");
    exit();
}

// Fetch user details
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
$stmt->close();

// Count pending reservations
$pending_query = "SELECT COUNT(*) as count FROM reservations WHERE status = 'pending'";
$pending_result = mysqli_query($con, $pending_query);
$pendingCount = 0;
if ($pending_result && mysqli_num_rows($pending_result) > 0) {
    $row = mysqli_fetch_assoc($pending_result);
    $pendingCount = $row['count'];
}

// Handle file upload
if (isset($_POST['submit'])) {
    $room_number = mysqli_real_escape_string($con, $_POST['room_number']);
    $day_group = mysqli_real_escape_string($con, $_POST['day_group']);
    $time_slot = mysqli_real_escape_string($con, $_POST['time_slot']);
    $status = mysqli_real_escape_string($con, $_POST['status']);
    $notes = mysqli_real_escape_string($con, $_POST['notes']);
    
    $query = "INSERT INTO lab_schedules (room_number, day_group, time_slot, status, notes) 
              VALUES (?, ?, ?, ?, ?)";
    $stmt = $con->prepare($query);
    $stmt->bind_param("sssss", $room_number, $day_group, $time_slot, $status, $notes);

    if ($stmt->execute()) {
        $success_message = "Schedule updated successfully!";
    } else {
        $error_message = "Error updating schedule: " . $stmt->error;
    }
    $stmt->close();
}

// Fetch existing schedules
$schedules = mysqli_query($con, "SELECT * FROM lab_schedules ORDER BY last_updated DESC");

// Add this at the top of the file after session_start() and include
if (isset($_GET['delete']) && !empty($_GET['delete'])) {
    $id = mysqli_real_escape_string($con, $_GET['delete']);
    
    // First get the file name to delete the actual file
    $file_query = "SELECT file_name FROM lab_schedules WHERE id = '$id'";
    $file_result = mysqli_query($con, $file_query);
    if ($file_result && mysqli_num_rows($file_result) > 0) {
        $file_data = mysqli_fetch_assoc($file_result);
        $file_path = 'uploads/schedules/' . $file_data['file_name'];
        if (file_exists($file_path)) {
            unlink($file_path); // Delete the actual file
        }
    }
    
    // Then delete the database record
    $delete_query = "DELETE FROM lab_schedules WHERE id = '$id'";
    if (mysqli_query($con, $delete_query)) {
        echo "<script>alert('Schedule deleted successfully!'); window.location.href = 'adlabsched.php';</script>";
    } else {
        echo "<script>alert('Error deleting schedule.');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lab Schedule Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, sans-serif;
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

        .schedule-container {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            width: 100%;
            margin: 0 auto;
            height: calc(100vh - 100px);
        }

        .schedule-content {
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .schedule-header {
            margin-bottom: 20px;
        }

        .room-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        .room-btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            background: #e9ecef;
            color: #495057;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 500;
        }

        .room-btn:hover {
            background: #dee2e6;
        }

        .room-btn.active {
            background: linear-gradient(45deg,rgb(150, 145, 79),rgb(47, 0, 177));
            color: white;
        }

        .schedule-table {
            flex: 1;
            overflow-y: auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 100%;
        }

        th, td {
            padding: 15px;
            text-align: center;
            border: 1px solid #dee2e6;
        }

        th {
            background: rgb(26, 19, 46);
            color: white;
            font-weight: 600;
        }

        .time-slot {
            font-weight: 500;
            color: #495057;
        }

        .status-btn {
            width: 100%;
            padding: 8px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 500;
        }

        .status-btn.available {
            background: #d4edda;
            color: #155724;
        }

        .status-btn.unavailable {
            background: #f8d7da;
            color: #721c24;
        }

        .schedule-actions {
            margin-top: 20px;
            display: flex;
            justify-content: flex-end;
            padding: 10px 0;
        }

        .save-btn {
            background: #155724;
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
        }

        .save-btn:hover {
            background: #155724;
            transform: translateY(-2px);
        }

        .popup {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 25px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideIn 0.3s ease-out;
            z-index: 1000;
        }

        .popup.success {
            background: #d4edda;
            color: #155724;
        }

        .popup.error {
            background: #f8d7da;
            color: #721c24;
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @media (max-width: 1200px) {
            .content {
                padding: 15px;
            }
            
            .schedule-container {
                padding: 20px;
            }
        }

        @media (max-width: 768px) {
            .content {
                margin-top: 120px;
                padding: 10px;
            }
            
            .schedule-container {
                padding: 15px;
                height: calc(100vh - 140px);
            }
        }

        /* Update scrollbar colors */
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

        /* Notification badge */
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #dc3545;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
        }
    </style>
</head>
<body>
<div class="top-nav">
    <div class="nav-left">
        <img src="uploads/<?php echo htmlspecialchars($profile_pic); ?>" alt="Profile Picture" onerror="this.src='assets/default.jpg';">
        <div class="user-name"><?php echo htmlspecialchars($user_name); ?></div>
    </div>
    <div class="nav-right">
        <a href="admindash.php"></i> Dashboard</a>
        <a href="adannouncement.php"></i> Announcements</a>
        <a href="liststudent.php"></i> Students</a>
        <a href="adsitin.php"></i> Current Sitin</a>
        
       
        <a href="adlabresources.php"></i> Lab Resources</a>
        <a href="adlabsched.php"></i> LAB SCHEDULES</a>
        <a href="adreservation.php" style="position: relative;">
             Reservations
            <?php if ($pendingCount > 0): ?>
                <span class="notification-badge"><?php echo $pendingCount; ?></span>
            <?php endif; ?>

        <a href="adfeedback.php"></i> Feedback</a>
        <a href="admindash.php?logout=true" class="logout-button"> Log Out</a>
    </div>
    </div>

    <div class="content">
        <div class="schedule-container">
            <h1> Laboratory Schedule </h1>
            
            <?php if (isset($success_message)): ?>
                <div class="popup success">
                    <i class="fas fa-check-circle"></i>
                    <span><?php echo $success_message; ?></span>
                </div>
            <?php endif; ?>

            <div class="schedule-content">
                <div class="schedule-header">
                    <div class="room-buttons">
                        <button class="room-btn" data-room="524">Room 524</button>
                        <button class="room-btn" data-room="526">Room 526</button>
                        <button class="room-btn" data-room="528">Room 528</button>
                        <button class="room-btn" data-room="530">Room 530</button>
                        <button class="room-btn" data-room="542">Room 542</button>
                        <button class="room-btn" data-room="544">Room 544</button>
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
                                        <td>
                                            <button class="status-btn available" 
                                                    data-time="<?php echo $time_slot; ?>" 
                                                    data-day="<?php echo $day; ?>"
                                                    onclick="toggleStatus(this)">
                                                Available
                                            </button>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="schedule-actions">
                    <button class="save-btn" onclick="saveSchedule()">
                        <i class="fas fa-save"></i> Save Schedule
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('active');
            document.querySelector('.content').classList.toggle('sidebar-active');
        }

        function updateSchedule(select) {
            const status = select.value;
            select.className = status.toLowerCase();
        }

        async function loadSchedules(room) {
            try {
                const response = await fetch(`get_schedules.php?room=${room}`);
                const data = await response.json();
                if (data.success) {
                    updateScheduleUI(data.schedules);
                }
            } catch (error) {
                console.error('Error loading schedules:', error);
            }
        }

        function updateScheduleUI(schedules) {
            // Reset all buttons to available
            document.querySelectorAll('.status-btn').forEach(btn => {
                btn.classList.remove('unavailable');
                btn.classList.add('available');
                btn.textContent = 'Available';
            });

            // Update buttons based on saved schedules
            schedules.forEach(schedule => {
                const button = document.querySelector(
                    `.status-btn[data-time="${schedule.time_slot}"][data-day="${schedule.day_group}"]`
                );
                if (button) {
                    button.classList.remove('available');
                    button.classList.add(schedule.status.toLowerCase());
                    button.textContent = schedule.status;
                }
            });
        }

        function toggleStatus(button) {
            const currentStatus = button.classList.contains('available') ? 'available' : 'unavailable';
            const newStatus = currentStatus === 'available' ? 'unavailable' : 'available';
            
            button.classList.remove(currentStatus);
            button.classList.add(newStatus);
            button.textContent = newStatus.charAt(0).toUpperCase() + newStatus.slice(1);
        }

        // Update room filter functionality
        document.querySelectorAll('.room-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.room-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                loadSchedules(btn.dataset.room);
            });
        });

        async function saveSchedule() {
            const activeRoom = document.querySelector('.room-btn.active').dataset.room;
            const scheduleData = [];
            const buttons = document.querySelectorAll('.status-btn');
            
            buttons.forEach(btn => {
                scheduleData.push({
                    room: activeRoom,
                    time: btn.dataset.time,
                    day: btn.dataset.day,
                    status: btn.classList.contains('available') ? 'Available' : 'Unavailable'
                });
            });

            try {
                const response = await fetch('save_schedule.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(scheduleData)
                });
                
                const result = await response.json();
                showPopup(result.message, result.success ? 'success' : 'error');
                
                if (result.success) {
                    // Reload schedules after successful save
                    loadSchedules(activeRoom);
                }
            } catch (error) {
                showPopup('Error saving schedule', 'error');
            }
        }

        function showPopup(message, type = 'success') {
            const popup = document.createElement('div');
            popup.className = `popup ${type}`;
            popup.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check' : 'exclamation'}-circle"></i>
                <span>${message}</span>
            `;
            document.body.appendChild(popup);
            setTimeout(() => popup.remove(), 3000);
        }

        // Load schedules when page loads
        document.addEventListener('DOMContentLoaded', () => {
            // Set Room 524 as active by default
            const room524Btn = document.querySelector('.room-btn[data-room="524"]');
            room524Btn.classList.add('active');
            loadSchedules('524'); // Load Room 524 schedules by default
        });
    </script>
</body>
</html>