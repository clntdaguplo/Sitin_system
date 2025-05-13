<?php
session_start();
date_default_timezone_set('Asia/Manila');
include("connector.php");

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

// Modify the POST handler section for sit-in
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['student_id']) && isset($_POST['purpose']) && isset($_POST['lab_room'])) {
        $student_id = $_POST['student_id'];
        $purpose = $_POST['purpose'];
        $labRoom = $_POST['lab_room'];
        date_default_timezone_set("Asia/Manila");
        $time_in = date("Y-m-d H:i:s");

        // Start transaction
        $con->begin_transaction();

        try {
            // First check if student is already sitting in
            $check_query = "SELECT COUNT(*) as count FROM login_records WHERE IDNO = ? AND TIME_OUT IS NULL";
            $stmt = $con->prepare($check_query);
            $stmt->bind_param("s", $student_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $is_sitting = $result->fetch_assoc()['count'] > 0;

            if ($is_sitting) {
                throw new Exception("Student is already sitting in");
            }

            // Fetch user details and check remaining sessions
            $user_query = "SELECT FIRSTNAME, MIDNAME, LASTNAME, REMAINING_SESSIONS FROM user WHERE IDNO = ?";
            $stmt = $con->prepare($user_query);
            $stmt->bind_param("s", $student_id);
            $stmt->execute();
            $user_result = $stmt->get_result()->fetch_assoc();

            if (!$user_result) {
                throw new Exception("Student not found");
            }

            if ($user_result['REMAINING_SESSIONS'] <= 0) {
                throw new Exception("no_sessions");
            }

            $fullname = $user_result['FIRSTNAME'] . ' ' . $user_result['MIDNAME'] . ' ' . $user_result['LASTNAME'];
            
            // Insert into login records
            $stmt = $con->prepare("INSERT INTO login_records (IDNO, FULLNAME, TIME_IN, PURPOSE, LAB_ROOM) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $student_id, $fullname, $time_in, $purpose, $labRoom);
            $stmt->execute();

            $con->commit();
            header("Location: adsitin.php?success=1");
            exit;
        } catch (Exception $e) {
            $con->rollback();
            if ($e->getMessage() === "no_sessions") {
                header("Location: adsitin.php?error=no_sessions");
            } else {
                header("Location: adsitin.php?error=" . urlencode($e->getMessage()));
            }
            exit;
        }
    }
}

// Modify the logout handler to remove the session deduction
if (isset($_GET['id'])) {
    $id = $_GET['id'];
    date_default_timezone_set("Asia/Manila");
    $time_out = date("Y-m-d H:i:s");

    $con->begin_transaction();

    try {
        // Update TIME_OUT and deduct session
        $stmt = $con->prepare("UPDATE login_records SET TIME_OUT = ? WHERE IDNO = ? AND TIME_OUT IS NULL");
        $stmt->bind_param("ss", $time_out, $id);
        $stmt->execute();

        // Deduct session
        $stmt = $con->prepare("UPDATE user SET REMAINING_SESSIONS = REMAINING_SESSIONS - 1 WHERE IDNO = ?");
        $stmt->bind_param("s", $id);
        $stmt->execute();

        $con->commit();
        header("Location: adsitin.php?logout_success=1");
        exit;
    } catch (Exception $e) {
        $con->rollback();
        header("Location: adsitin.php?error=logout_failed");
        exit;
    }
}

// Make sure this is at the top of your file
require_once('includes/notification_helper.php');

// Update the points addition handler
if (isset($_GET['addpoint'])) {
    $id = $_GET['addpoint'];
    date_default_timezone_set("Asia/Manila");
    $time_out = date("Y-m-d H:i:s");
    
    $con->begin_transaction();

    try {
        // Get user data first
        $stmt = $con->prepare("SELECT POINTS, REMAINING_SESSIONS, FIRSTNAME, MIDNAME, LASTNAME FROM user WHERE IDNO = ?");
        $stmt->bind_param("s", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user_data = $result->fetch_assoc();
        
        if (!$user_data || $user_data['REMAINING_SESSIONS'] <= 0) {
            throw new Exception("No sessions available");
        }

        $current_points = $user_data['POINTS'];
        $fullname = $user_data['FIRSTNAME'] . ' ' . $user_data['MIDNAME'] . ' ' . $user_data['LASTNAME'];

        // Update TIME_OUT first
        $stmt = $con->prepare("UPDATE login_records SET TIME_OUT = ? WHERE IDNO = ? AND TIME_OUT IS NULL");
        $stmt->bind_param("ss", $time_out, $id);
        $stmt->execute();

        // Deduct session
        $stmt = $con->prepare("UPDATE user SET REMAINING_SESSIONS = REMAINING_SESSIONS - 1 WHERE IDNO = ?");
        $stmt->bind_param("s", $id);
        $stmt->execute();

        // Add point
        $stmt = $con->prepare("UPDATE user SET POINTS = POINTS + 1 WHERE IDNO = ?");
        $stmt->bind_param("s", $id);
        $stmt->execute();

        // Add point notification
        addPointsNotification($con, $id, 1, "completing your lab session");

        // Check if points reach a multiple of 3
        $new_points = $current_points + 1;
        if ($new_points % 3 == 0) {
            // Add session
            $stmt = $con->prepare("UPDATE user SET REMAINING_SESSIONS = REMAINING_SESSIONS + 1 WHERE IDNO = ?");
            $stmt->bind_param("s", $id);
            $stmt->execute();

            // Add session notification
            addSessionNotification($con, $id);

            // Record in points history
            $stmt = $con->prepare("INSERT INTO points_history (IDNO, FULLNAME, POINTS_EARNED, CONVERTED_TO_SESSION, CONVERSION_DATE) 
                                 VALUES (?, ?, ?, 1, NOW())");
            $stmt->bind_param("ssi", $id, $fullname, $new_points);
            $stmt->execute();
            
            $reward_message = "&reward=session";
        } else {
            // Record in points history
            $stmt = $con->prepare("INSERT INTO points_history (IDNO, FULLNAME, POINTS_EARNED, CONVERSION_DATE) 
                                 VALUES (?, ?, 1, NOW())");
            $stmt->bind_param("ss", $id, $fullname);
            $stmt->execute();
            
            $reward_message = "&reward=point";
        }

        $con->commit();
        header("Location: adsitin.php?point_success=1" . $reward_message . "&points=" . $new_points);
        exit;

    } catch (Exception $e) {
        $con->rollback();
        header("Location: adsitin.php?error=no_sessions");
        exit;
    }
}

// Add this where you handle adding points
if(isset($_POST['add_points'])) {
    $student_id = $_POST['student_id'];
    $points = $_POST['points'];
    $reason = $_POST['reason'] ?? ''; // Add a reason field in your form
    
    $con->begin_transaction();
    
    try {
        // Add points
        $update_query = "UPDATE user SET POINTS = POINTS + ? WHERE IDNO = ?";
        $stmt = $con->prepare($update_query);
        $stmt->bind_param("is", $points, $student_id);
        $stmt->execute();
        
        // Send notification
        addPointsNotification($con, $student_id, $points, $reason);
        
        // Check if points can be converted to sessions
        checkAndConvertPoints($con, $student_id);

        // Function to check and convert points to sessions
        function checkAndConvertPoints($con, $student_id) {
            $stmt = $con->prepare("SELECT POINTS FROM user WHERE IDNO = ?");
            $stmt->bind_param("s", $student_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $user_data = $result->fetch_assoc();
            $current_points = $user_data['POINTS'];

            if ($current_points % 3 == 0) {
                // Add session
                $stmt = $con->prepare("UPDATE user SET REMAINING_SESSIONS = REMAINING_SESSIONS + 1 WHERE IDNO = ?");
                $stmt->bind_param("s", $student_id);
                $stmt->execute();

                // Add session notification
                addSessionNotification($con, $student_id);
            }
        }
        
        $con->commit();
        echo "<script>alert('Points added successfully!');</script>";
    } catch (Exception $e) {
        $con->rollback();
        echo "<script>alert('Error adding points!');</script>";
    }
}

// Get current date in Manila timezone
$current_date = date('Y-m-d');
$selected_date = isset($_GET['date']) ? $_GET['date'] : $current_date;
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
<link rel="stylesheet" href="https://www.w3schools.com/w3css/4/w3.css">
<title>Admin Sit-in Management</title>
<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

html, body {
    background: linear-gradient(45deg, #ff4757, #ffae42);
    display: flex;
    flex-direction: column;
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

/* Remove old sidebar styles */
.sidebar {
    display: none;
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
    color:rgb(0, 0, 0);
    font-size: 1.8rem;
    font-weight: 600;
    margin-bottom: 25px;
}

.table-container {
    height: calc(100% - 140px);
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
    background:rgb(30, 117, 211);
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

.w3-button {
    padding: 8px 16px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s;
    font-size: 0.9em;
}

.w3-red {
    background: #dc3545;
    color: white;
}

.w3-green {
    background: #28a745;
    color: white;
}

.w3-button:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
}

.success-message {
    background: #d4edda;
    color: #155724;
    padding: 15px;
    margin-bottom: 20px;
    border-radius: 8px;
    text-align: center;
    font-weight: 500;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    animation: fadeIn 0.5s ease;
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
    background:rgb(6, 62, 122);
    border-radius: 4px;
}

::-webkit-scrollbar-thumb:hover {
    background: #0f4578;
}

/* Responsive Design */
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
}

/* Add to your existing styles */
.error-message {
    background: #f8d7da;
    color: #721c24;
    padding: 15px;
    margin-bottom: 20px;
    border-radius: 8px;
    text-align: center;
    font-weight: 500;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    animation: fadeIn 0.5s ease;
}

/* Add these new styles for the daily records section */
.tab-container {
    margin-bottom: 20px;
}

.tab-buttons {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
}

.tab-button {
    padding: 10px 20px;
    background: #f8f9fa;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 500;
    color: #2a3f5f;
    transition: all 0.3s ease;
}

.tab-button.active {
    background: linear-gradient(45deg,rgb(150, 145, 79),rgb(47, 0, 177));
    color: white;
}

.tab-button:hover {
    transform: translateY(-2px);
}

.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
}

.date-picker {
    padding: 10px 15px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    margin-right: 10px;
}

.date-picker:focus {
    border-color: #14569b;
    outline: none;
    box-shadow: 0 0 0 3px rgba(20, 86, 155, 0.1);
}

/* Add these new styles for the search bar */
.search-container {
    margin-bottom: 20px;
    padding: 0 10px;
}

.search-box {
    position: relative;
    max-width: 500px;
    margin: 0 auto;
}

.search-box input {
    width: 100%;
    padding: 12px 20px 12px 45px;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    font-size: 1rem;
    color: #2a3f5f;
    background: white;
    transition: all 0.3s ease;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
}

.search-box input:focus {
    outline: none;
    border-color: #14569b;
    box-shadow: 0 0 0 3px rgba(20, 86, 155, 0.1);
}

.search-box::before {
    content: '\f002';
    font-family: 'Font Awesome 5 Free';
    font-weight: 900;
    position: absolute;
    left: 15px;
    top: 50%;
    transform: translateY(-50%);
    color: #14569b;
    font-size: 1.1rem;
}

.search-box input::placeholder {
    color: #94a3b8;
}

/* Update table container for better spacing */
.table-container {
    margin-top: 20px;
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    overflow: hidden;
}

/* Update table styles for better readability */
table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
}

thead th {
    background: rgb(26, 19, 46);
    color: white;
    padding: 15px;
    font-weight: 500;
    text-align: left;
    font-size: 0.95rem;
}

tbody td {
    padding: 12px 15px;
    border-bottom: 1px solid #e2e8f0;
    color: #2a3f5f;
    font-size: 0.95rem;
}

tbody tr:hover {
    background: #f8fafc;
}

/* Add animation for search results */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

tbody tr {
    animation: fadeIn 0.3s ease;
}

.report-container {
    background: white;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
}

.report-filters {
    display: flex;
    gap: 20px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.filter-group {
    display: flex;
    align-items: center;
    gap: 10px;
}

.filter-group label {
    font-weight: 500;
    color: #2a3f5f;
}

.filter-select {
    padding: 8px 12px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    background: white;
    color: #2a3f5f;
    min-width: 150px;
}

.filter-select:focus {
    border-color: #14569b;
    outline: none;
    box-shadow: 0 0 0 3px rgba(20, 86, 155, 0.1);
}

.export-buttons {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
}

.export-btn {
    padding: 10px 20px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.9em;
    font-weight: 500;
}

.export-btn.csv {
    background: green;
    color: white;
}

.export-btn.excel {
    background:rgb(197, 177, 0);
    color: white;
}

.export-btn.pdf {
    background: rgb(163, 9, 3);
    color: white;
}

.export-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
}

.filter-btn {
    padding: 8px 15px;
    background: #14569b;
    color: white;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    gap: 5px;
}

.filter-btn:hover {
    background: #0f4578;
    transform: translateY(-2px);
}

.filter-btn i {
    font-size: 0.9em;
}

/* Add these styles for better table display */
.purpose-column {
    max-width: 200px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.room-column {
    text-align: center;
}

table td {
    vertical-align: middle;
}

.w3-button {
    padding: 6px 12px;
    font-size: 0.9em;
    white-space: nowrap;
}

/* Add hover effect for purpose column */
.purpose-column:hover {
    white-space: normal;
    overflow: visible;
    position: relative;
    z-index: 1;
    background: white;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    border-radius: 4px;
    padding: 5px;
}

/* Add these new styles */
.status-badge {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.85em;
    font-weight: 500;
    display: inline-block;
}

.status-badge.active {
    background: rgb(2, 126, 29);
    color: white;
}

.active-session {
    background-color: #f8f9fa;
    border-left: 4px solid #28a745;
}

.empty-state {
    text-align: center;
    padding: 40px;
    color: #6c757d;
}

.empty-state i {
    font-size: 48px;
    margin-bottom: 15px;
    color: #dee2e6;
}

.empty-state p {
    font-size: 1.1em;
    margin: 0;
}

.no-records {
    text-align: center;
    padding: 20px;
}

/* Add these new styles */
.status-badge.completed {
    background-color: #6c757d;
    color: white;
}

.duration-column {
    text-align: center;
    font-family: monospace;
    font-weight: 500;
    min-width: 100px;
    color: #2a3f5f;
}

.duration-column[data-active="true"] {
    color: #28a745;
    font-weight: 600;
}

/* Add animation for active duration */
@keyframes pulse {
    0% { opacity: 1; }
    50% { opacity: 0.7; }
    100% { opacity: 1; }
}

.duration-column[data-active="true"] {
    animation: pulse 2s infinite;
}

/* Add styles for time column */
.time-column {
    font-family: monospace;
    white-space: nowrap;
    color: #2a3f5f;
}

.time-column[data-time] {
    color: #28a745;
}

.w3-button.w3-green {
    background: #28a745;
    color: white;
}

.w3-button.w3-red {
    background:rgb(168, 6, 6);
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
        <a href="adsitin.php"></i> CURRENT SIT-IN</a>
        
       
        <a href="adlabresources.php"></i> Lab Resources</a>
        <a href="adlabsched.php"></i> Lab Schedule</a>
        <a href="adreservation.php"></i> Reservations</a>
        <a href="adfeedback.php"></i> Feedback</a>
        <a href="admindash.php?logout=true" class="logout-button"><i class="fas fa-sign-out-alt"></i> Log Out</a>
    </div>
</div>

<div class="content">
    <div class="container">
        <div class="tab-container">
            <div class="tab-buttons">
                <button class="tab-button active" onclick="openTab('current')">Current Sit-ins</button>
                <button class="tab-button" onclick="openTab('daily')">Daily Records</button>
                <button class="tab-button" onclick="openTab('generate')">Generate All Reports</button>
            </div>
        </div>

        <div id="current" class="tab-content active">
            <div class="header">
                <h1>Current Sit-in</h1>
            </div>
            <?php
            if (isset($_GET['success'])) {
                echo "<div class='success-message'>
                         User information successfully added to login records table.
                      </div>";
            }
            if (isset($_GET['logout_success'])) {
                echo "<div class='success-message'>
                         Student successfully logged out
                      </div>";
            }
            if (isset($_GET['point_success'])) {
                $reward_type = isset($_GET['reward']) ? $_GET['reward'] : '';
                $reward_msg = '';
                
                if (isset($_GET['points'])) {
                    $current_points = intval($_GET['points']);
                    if ($reward_type === 'point') {
                        $reward_msg = "Student logged out and earned 1 point! (Total points: {$current_points})";
                    } else if ($reward_type === 'session') {
                        $reward_msg = "Student logged out and earned 1 new session for reaching {$current_points} points!";
                    }
                } else {
                    $reward_msg = "Student logged out and earned 1 point!";
                }
                echo "<div class='success-message'> " . $reward_msg . "</div>";
            }
            if (isset($_GET['error'])) {
                $error_msg = '';
                switch($_GET['error']) {
                    case 'no_sessions':
                        $error_msg = " Student has no remaining sessions";
                        break;
                    case 'db_error':
                        $error_msg = " Database error occurred";
                        break;
                    case 'logout_failed':
                        $error_msg = " Failed to logout student";
                        break;
                    default:
                        $error_msg = " " . htmlspecialchars($_GET['error']);
                }
                if ($error_msg) {
                    echo "<div class='error-message'>$error_msg</div>";
                }
            }
            ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Status</th>
                            <th>ID No</th>
                            <th>Full Name</th>
                            <th>Purpose</th>
                            <th>Room</th>
                            <th>Time In</th>
                            <th>Duration</th>
                            <th>Sessions</th>
                            <th>Points</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sitin_result = mysqli_query($con, "SELECT 
                            lr.IDNO, 
                            lr.FULLNAME, 
                            lr.PURPOSE, 
                            lr.LAB_ROOM, 
                            lr.TIME_IN,
                            u.REMAINING_SESSIONS, 
                            u.POINTS,
                            CONVERT_TZ(lr.TIME_IN, 'UTC', 'Asia/Manila') as local_time 
                            FROM login_records lr 
                            JOIN user u ON lr.IDNO = u.IDNO 
                            WHERE lr.TIME_OUT IS NULL 
                            ORDER BY lr.TIME_IN DESC");

                        if (mysqli_num_rows($sitin_result) > 0) {
                            while ($sitin_row = mysqli_fetch_assoc($sitin_result)) { 
                                $time_in = strtotime($sitin_row['TIME_IN']);
                                $current_time = time();
                                $duration_seconds = max(0, $current_time - $time_in);
                                $total_minutes = floor($duration_seconds / 60);
                                
                                // Convert to hours if more than 60 minutes
                                if ($total_minutes >= 60) {
                                    $hours = floor($total_minutes / 60);
                                    $remaining_minutes = $total_minutes % 60;
                                    $duration = $hours . " hr" . ($remaining_minutes > 0 ? " " . $remaining_minutes . " mins" : "");
                                } else {
                                    $duration = $total_minutes . " mins";
                                }
                            ?>
                                <tr class="active-session">
                                    <td>
                                        <span class="status-badge active">Currently Sitting</span>
                                    </td>
                                    <td><?php echo htmlspecialchars($sitin_row['IDNO']); ?></td>
                                    <td><?php echo htmlspecialchars($sitin_row['FULLNAME']); ?></td>
                                    <td class="purpose-column"><?php echo htmlspecialchars($sitin_row['PURPOSE']); ?></td>
                                    <td class="room-column"><?php echo htmlspecialchars($sitin_row['LAB_ROOM']); ?></td>
                                    <td class="time-column" data-time="<?php echo $time_in; ?>">
                                        <?php echo date('M d, Y h:i A', $time_in); ?>
                                    </td>
                                    <td class="duration-column" data-start="<?php echo $time_in; ?>" data-active="true">
                                        <?php echo $duration; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($sitin_row['REMAINING_SESSIONS']); ?></td>
                                    <td><?php echo htmlspecialchars($sitin_row['POINTS']); ?></td>
                                    <td>
                                        <div style="display: flex; gap: 5px;">
                                            <a href="adsitin.php?addpoint=<?php echo htmlspecialchars($sitin_row['IDNO']); ?>" 
                                               class="w3-button w3-green" 
                                               title="Add 1 point">Reward</a>
                                            <a href="adsitin.php?id=<?php echo htmlspecialchars($sitin_row['IDNO']); ?>" 
                                               class="w3-button w3-red">Logout</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php }
                        } else { ?>
                            <tr>
                                <td colspan="10" class="no-records">
                                    <div class="empty-state">
                                        <i class="fas fa-users"></i>
                                        <p>No students are currently sitting in</p>
                                    </div>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div id="daily" class="tab-content">
            <div class="header">
                <h1>Daily Sit-in Records</h1>
            </div>
            <div class="search-container">
                <div class="search-box">
                    <input type="text" id="searchInput" placeholder="Search by ID Number, Name, Purpose, or Lab Room..." onkeyup="searchTable()">
                </div>
            </div>
            <div class="table-container">
                <table id="dailyTable">
                    <thead>
                        <tr>
                            <th>ID Number</th>
                            <th>Name</th>
                            <th>Date</th>
                            <th>Time In</th>
                            <th>Time Out</th>
                            <th>Duration</th>
                            <th>Purpose</th>
                            <th>Lab Room</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $daily_query = "SELECT * FROM login_records ORDER BY TIME_IN DESC";
                        $daily_result = mysqli_query($con, $daily_query);

                        while ($row = mysqli_fetch_assoc($daily_result)) {
                            $time_in = strtotime($row['TIME_IN']);
                            $time_out = $row['TIME_OUT'] ? strtotime($row['TIME_OUT']) : null;
                            
                            // Calculate duration in minutes
                            $duration = '';
                            if ($time_out) {
                                $diff = max(0, $time_out - $time_in); // Prevent negative values
                                $total_minutes = floor($diff / 60);
                                if ($total_minutes >= 60) {
                                    $hours = floor($total_minutes / 60);
                                    $remaining_minutes = $total_minutes % 60;
                                    $duration = $hours . " hr" . ($remaining_minutes > 0 ? " " . $remaining_minutes . " mins" : "");
                                } else {
                                    $duration = $total_minutes . " mins";
                                }
                            } else {
                                $current_time = time();
                                $diff = max(0, $current_time - $time_in); // Prevent negative values
                                $total_minutes = floor($diff / 60);
                                if ($total_minutes >= 60) {
                                    $hours = floor($total_minutes / 60);
                                    $remaining_minutes = $total_minutes % 60;
                                    $duration = $hours . " hr" . ($remaining_minutes > 0 ? " " . $remaining_minutes . " mins" : "");
                                } else {
                                    $duration = $total_minutes . " mins";
                                }
                            }
                            
                            echo "<tr>";
                            echo "<td>" . htmlspecialchars($row['IDNO']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['FULLNAME']) . "</td>";
                            echo "<td>" . date('M d, Y', $time_in) . "</td>";
                            echo "<td>" . date('h:i A', $time_in) . "</td>";
                            echo "<td>" . ($time_out ? date('h:i A', $time_out) : 'Active') . "</td>";
                            echo "<td class='duration-column'>" . $duration . "</td>";
                            echo "<td>" . htmlspecialchars($row['PURPOSE']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['LAB_ROOM']) . "</td>";
                            echo "<td><span class='status-badge " . ($time_out ? 'completed' : 'active') . "'>" . 
                                 ($time_out ? 'Completed' : 'Active') . "</span></td>";
                            echo "</tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div id="generate" class="tab-content">
            <div class="header">
                <h1>Sitin Reports</h1>
            </div>
            <div class="report-container">
                <div class="export-buttons">
                    <button class="export-btn csv" onclick="exportReportToCSV()">
                        <i class="fas fa-file-csv"></i> Export to CSV
                    </button>
                    <button class="export-btn excel" onclick="exportReportToExcel()">
                        <i class="fas fa-file-excel"></i> Export to Excel
                    </button>
                    <button class="export-btn pdf" onclick="exportReportToPDF()">
                        <i class="fas fa-file-pdf"></i> Export to PDF
                    </button>
                </div>
                <div class="table-container">
                    <table id="reportTable">
                        <thead>
                            <tr>
                                <th>ID Number</th>
                                <th>Name</th>
                                <th>Date</th>
                                <th>Time In</th>
                                <th>Time Out</th>
                                <th>Duration</th>
                                <th>Purpose</th>
                                <th>Lab Room</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $report_query = "SELECT * FROM login_records ORDER BY TIME_IN DESC";
                            $report_result = mysqli_query($con, $report_query);

                            while ($row = mysqli_fetch_assoc($report_result)) {
                                $time_in = strtotime($row['TIME_IN']);
                                $time_out = $row['TIME_OUT'] ? strtotime($row['TIME_OUT']) : null;
                                
                                // Calculate duration in minutes
                                $duration = '';
                                if ($time_out) {
                                    $diff = max(0, $time_out - $time_in); // Prevent negative values
                                    $total_minutes = floor($diff / 60);
                                    if ($total_minutes >= 60) {
                                        $hours = floor($total_minutes / 60);
                                        $remaining_minutes = $total_minutes % 60;
                                        $duration = $hours . " hr" . ($remaining_minutes > 0 ? " " . $remaining_minutes . " mins" : "");
                                    } else {
                                        $duration = $total_minutes . " mins";
                                    }
                                } else {
                                    $current_time = time();
                                    $diff = max(0, $current_time - $time_in); // Prevent negative values
                                    $total_minutes = floor($diff / 60);
                                    if ($total_minutes >= 60) {
                                        $hours = floor($total_minutes / 60);
                                        $remaining_minutes = $total_minutes % 60;
                                        $duration = $hours . " hr" . ($remaining_minutes > 0 ? " " . $remaining_minutes . " mins" : "");
                                    } else {
                                        $duration = $total_minutes . " mins";
                                    }
                                }
                                
                                echo "<tr>";
                                echo "<td>" . htmlspecialchars($row['IDNO']) . "</td>";
                                echo "<td>" . htmlspecialchars($row['FULLNAME']) . "</td>";
                                echo "<td>" . date('M d, Y', $time_in) . "</td>";
                                echo "<td>" . date('h:i A', $time_in) . "</td>";
                                echo "<td>" . ($time_out ? date('h:i A', $time_out) : 'Active') . "</td>";
                                echo "<td class='duration-column'>" . $duration . "</td>";
                                echo "<td>" . htmlspecialchars($row['PURPOSE']) . "</td>";
                                echo "<td>" . htmlspecialchars($row['LAB_ROOM']) . "</td>";
                                echo "<td><span class='status-badge " . ($time_out ? 'completed' : 'active') . "'>" . 
                                     ($time_out ? 'Completed' : 'Active') . "</span></td>";
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

<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.4.0/jspdf.umd.min.js"></script>

<script>
function openTab(tabName) {
    // Hide all tab contents
    var tabContents = document.getElementsByClassName("tab-content");
    for (var i = 0; i < tabContents.length; i++) {
        tabContents[i].classList.remove("active");
    }

    // Remove active class from all buttons
    var tabButtons = document.getElementsByClassName("tab-button");
    for (var i = 0; i < tabButtons.length; i++) {
        tabButtons[i].classList.remove("active");
    }

    // Show the selected tab content and activate the button
    document.getElementById(tabName).classList.add("active");
    event.currentTarget.classList.add("active");
}

function searchTable() {
    var input, filter, table, tr, td, i, j, txtValue;
    input = document.getElementById("searchInput");
    filter = input.value.toUpperCase();
    table = document.getElementById("dailyTable");
    tr = table.getElementsByTagName("tr");

    // Loop through all table rows
    for (i = 1; i < tr.length; i++) { // Start from 1 to skip header row
        var found = false;
        td = tr[i].getElementsByTagName("td");
        
        // Loop through all columns
        for (j = 0; j < td.length; j++) {
            if (td[j]) {
                txtValue = td[j].textContent || td[j].innerText;
                if (txtValue.toUpperCase().indexOf(filter) > -1) {
                    found = true;
                    break;
                }
            }
        }
        
        // Show/hide row based on search result
        if (found) {
            tr[i].style.display = "";
        } else {
            tr[i].style.display = "none";
        }
    }
}

if (window.location.href.indexOf("success=1") > -1) {
    setTimeout(function() {
        window.history.replaceState(null, null, window.location.pathname);
    }, 3000); // Clears success message after 3 seconds
}

function exportReportToCSV() {
    const table = document.querySelector('#reportTable');
    let csv = [];
    
    // Add styled header information with centering
    csv.push('"                                                                  "');
    csv.push('"                              UNIVERSITY OF CEBU - MAIN                              "');
    csv.push('"                            COLLEGE OF COMPUTER STUDIES                            "');
    csv.push('"              COMPUTER LABORATORY SITIN MONITORING SYSTEM REPORT                    "');
    csv.push('"                                                                  "');
    csv.push(''); // Empty line for spacing
    
    const headers = Array.from(table.querySelectorAll('thead th')).map(th => `"${th.innerText}"`);
    csv.push(headers.join(','));
    
    // Only get visible rows (filtered results)
    const visibleRows = Array.from(table.querySelectorAll('tbody tr')).filter(row => row.style.display !== 'none');
    
    visibleRows.forEach(row => {
        const rowData = Array.from(row.querySelectorAll('td')).map(cell => `"${cell.innerText.replace(/"/g, '""')}"`);
        csv.push(rowData.join(','));
    });

    const csvFile = new Blob(['\ufeff' + csv.join('\n')], { type: 'text/csv;charset=utf-8' });
    const downloadLink = document.createElement('a');
    downloadLink.download = 'sitin_report.csv';
    downloadLink.href = window.URL.createObjectURL(csvFile);
    downloadLink.style.display = 'none';
    document.body.appendChild(downloadLink);
    downloadLink.click();
}

function exportReportToExcel() {
    try {
        const table = document.querySelector('#reportTable');
        const headers = Array.from(table.querySelectorAll('thead th')).map(th => th.innerText);
        const visibleRows = Array.from(table.querySelectorAll('tbody tr')).filter(row => row.style.display !== 'none');
        
        // Create new workbook with only filtered data
        const wb = XLSX.utils.book_new();
        const wsData = [
            [''],  // Space for logo
            [''],  // Space for logo
            [''],  // Space for logo
            ['                         UNIVERSITY OF CEBU - MAIN      '],
            ['                        COLLEGE OF COMPUTER STUDIES     '],
            ['             COMPUTER LABORATORY SITIN MONITORING SYSTEM REPORT       '],
            [''],  // Empty line for spacing
            headers
        ];
        
        visibleRows.forEach(row => {
            const rowData = Array.from(row.querySelectorAll('td')).map(cell => cell.innerText);
            wsData.push(rowData);
        });
        
        const ws = XLSX.utils.aoa_to_sheet(wsData);
        
        // Set column widths
        ws['!cols'] = [
            { wch: 15 }, // ID Number
            { wch: 30 }, // Name
            { wch: 15 }, // Date
            { wch: 15 }, // Time In
            { wch: 15 }, // Time Out
            { wch: 15 }, // Duration
            { wch: 40 }, // Purpose
            { wch: 15 }, // Lab Room
            { wch: 15 }  // Status
        ];
        
        // Style the header
        ws['!merges'] = [
            { s: { r: 3, c: 0 }, e: { r: 3, c: 8 } },  // University name
            { s: { r: 4, c: 0 }, e: { r: 4, c: 8 } },  // College name
            { s: { r: 5, c: 0 }, e: { r: 5, c: 8 } }   // Report title
        ];
        
        XLSX.utils.book_append_sheet(wb, ws, 'Sit-in Report');
        XLSX.writeFile(wb, 'sitin_report.xlsx');
    } catch (error) {
        console.error('Error exporting to Excel:', error);
        alert('There was an error exporting to Excel. Please try again.');
    }
}

function exportReportToPDF() {
    try {
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF('l', 'mm', 'a4');
        
        // Set font and size
        doc.setFont('helvetica', 'bold');
        
        // Center everything on the page
        const pageWidth = doc.internal.pageSize.width;
        const pageCenter = pageWidth / 2;
        
        // Add header information with styling and UC branding colors
        const ucBlue = [20, 86, 155];  // UC Blue color
        const ucGold = [218, 170, 0];  // UC Gold color
        
        // Draw decorative top border
        doc.setDrawColor(...ucBlue);
        doc.setLineWidth(1);
        doc.line(20, 15, pageWidth - 20, 15);
        
        // Add header text
        doc.setTextColor(...ucBlue);
        doc.setFontSize(22);
        doc.text('UNIVERSITY OF CEBU - MAIN', pageCenter, 30, { align: 'center' });
        
        doc.setFontSize(18);
        doc.text('COLLEGE OF COMPUTER STUDIES', pageCenter, 40, { align: 'center' });
        
        doc.setFontSize(16);
        doc.text('COMPUTER LABORATORY SITIN MONITORING SYSTEM REPORT', pageCenter, 50, { align: 'center' });
        
        // Draw bottom border for header
        doc.setDrawColor(...ucBlue);
        doc.setLineWidth(1);
        doc.line(20, 55, pageWidth - 20, 55);
        
        // Get current date and time
        const now = new Date();
        const dateStr = now.toLocaleDateString();
        const timeStr = now.toLocaleTimeString();
        
        // Add date and time
        doc.setFontSize(10);
        doc.setTextColor(0, 0, 0);
        doc.text(`Generated on: ${dateStr} ${timeStr}`, pageCenter, 65, { align: 'center' });
        
        // Set font size for table content
        doc.setFontSize(10);
        
        const table = document.querySelector('#reportTable');
        const visibleRows = Array.from(table.querySelectorAll('tbody tr')).filter(row => row.style.display !== 'none');
        
        // Define column widths and positions
        const colWidths = [20, 40, 20, 20, 20, 40, 20];
        let yPos = 75; // Starting position for table
        
        // Calculate total width of the table
        const totalWidth = colWidths.reduce((a, b) => a + b, 0);
        // Calculate starting X position to center the table
        let startX = (pageWidth - totalWidth) / 2;
        
        // Draw headers
        const headers = Array.from(table.querySelectorAll('th'));
        let xPos = startX;
        headers.forEach((header, index) => {
            doc.setFillColor(...ucBlue);
            doc.setTextColor(255, 255, 255);
            doc.rect(xPos, yPos, colWidths[index], 10, 'F');
            doc.text(header.innerText, xPos + 2, yPos + 7);
            xPos += colWidths[index];
        });
        
        yPos += 10;
        
        // Draw rows
        visibleRows.forEach(row => {
            xPos = startX;
            const cells = row.querySelectorAll('td');
            cells.forEach((cell, index) => {
                doc.setFillColor(255, 255, 255);
                doc.setTextColor(0, 0, 0);
                doc.rect(xPos, yPos, colWidths[index], 10, 'F');
                doc.text(cell.innerText, xPos + 2, yPos + 7);
                xPos += colWidths[index];
            });
            yPos += 10;
            
            // Check if we need a new page
            if (yPos > doc.internal.pageSize.height - 20) {
                doc.addPage();
                yPos = 20;
            }
        });
        
        // Save the PDF
        doc.save('sitin_report.pdf');
    } catch (error) {
        console.error('Error generating PDF:', error);
        alert('There was an error generating the PDF. Please try again.');
    }
}

// Update both time and duration in real-time
function updateTimeAndDuration() {
    const now = Math.floor(Date.now() / 1000);
    
    // Update durations for active sessions
    const durationCells = document.querySelectorAll('.duration-column[data-active="true"]');
    durationCells.forEach(cell => {
        const startTime = parseInt(cell.getAttribute('data-start'));
        const durationSeconds = Math.max(0, now - startTime);
        const totalMinutes = Math.floor(durationSeconds / 60);
        
        // Convert to hours if more than 60 minutes
        if (totalMinutes >= 60) {
            const hours = Math.floor(totalMinutes / 60);
            const remainingMinutes = totalMinutes % 60;
            cell.textContent = hours + " hr" + (remainingMinutes > 0 ? " " + remainingMinutes + " mins" : "");
        } else {
            cell.textContent = totalMinutes + " mins";
        }
    });
    
    // Update times
    const timeCells = document.querySelectorAll('.time-column[data-time]');
    timeCells.forEach(cell => {
        const timeIn = parseInt(cell.getAttribute('data-time'));
        const date = new Date(timeIn * 1000);
        cell.textContent = date.toLocaleString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
            hour12: true
        });
    });
}

// Update every second for smoother counting
setInterval(updateTimeAndDuration, 1000);
// Initial update
updateTimeAndDuration();
</script>
</body>
</html>