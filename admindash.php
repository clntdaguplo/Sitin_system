<?php
session_start();
include("connector.php");

// Check if the user is logged in
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

// Handle search
$search_result = null;
$user_not_found = false;
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['search'])) {
    $search_id = $_POST['search_id'];
    $search_query = "SELECT u.*, 
                    (SELECT COUNT(*) FROM login_records lr WHERE lr.IDNO = u.IDNO AND lr.TIME_OUT IS NULL) as active_sessions 
                    FROM user u WHERE u.IDNO = ?";
    $stmt = $con->prepare($search_query);
    $stmt->bind_param("s", $search_id);
    $stmt->execute();
    $search_result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$search_result) {
        $user_not_found = true;
    } else {
        // Check if user has active sessions
        if ($search_result['active_sessions'] > 0) {
            $has_active_session = true;
        }
        
        // Get remaining sessions
        $session_query = "SELECT REMAINING_SESSIONS FROM user WHERE IDNO = ?";
        $stmt = $con->prepare($session_query);
        $stmt->bind_param("s", $search_result['IDNO']);
        $stmt->execute();
        $session_result = $stmt->get_result()->fetch_assoc();
        $session_value = $session_result['REMAINING_SESSIONS'];
        $stmt->close();
    }
}

// Handle sit-in form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['sit_in'])) {
    $student_id = $_POST['student_id'];
    $purpose = $_POST['purpose'];
    $lab_room = $_POST['lab_room'];
    date_default_timezone_set("Asia/Manila");
    $time_in = date("Y-m-d H:i:s");

    // Start transaction
    $con->begin_transaction();

    try {
        // Check if student exists and has remaining sessions
        $user_query = "SELECT FIRSTNAME, MIDNAME, LASTNAME, REMAINING_SESSIONS FROM user WHERE IDNO = ?";
        $stmt = $con->prepare($user_query);
        $stmt->bind_param("s", $student_id);
        $stmt->execute();
        $user_result = $stmt->get_result()->fetch_assoc();

        if (!$user_result) {
            throw new Exception("Student not found.");
        }

        if ($user_result['REMAINING_SESSIONS'] <= 0) {
            throw new Exception("No remaining sessions available.");
        }

        // Check for active sessions
        $check_active = "SELECT COUNT(*) as active_count FROM login_records WHERE IDNO = ? AND TIME_OUT IS NULL";
        $stmt = $con->prepare($check_active);
        $stmt->bind_param("s", $student_id);
        $stmt->execute();
        $active_result = $stmt->get_result()->fetch_assoc();
        
        if ($active_result['active_count'] > 0) {
            throw new Exception("This student already has an active sit-in session.");
        }

        $fullname = $user_result['FIRSTNAME'] . ' ' . $user_result['MIDNAME'] . ' ' . $user_result['LASTNAME'];
        
        // Insert into login records
        $stmt = $con->prepare("INSERT INTO login_records (IDNO, FULLNAME, TIME_IN, PURPOSE, LAB_ROOM) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $student_id, $fullname, $time_in, $purpose, $lab_room);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to record sit-in.");
        }

        $con->commit();
        header("Location: admindash.php?success=1");
        exit;
    } catch (Exception $e) {
        $con->rollback();
        header("Location: admindash.php?error=" . urlencode($e->getMessage()));
        exit;
    }
}

// Fetch session value
$session_value = 30; // Default value
if ($search_result) {
    $session_query = "SELECT REMAINING_SESSIONS FROM user WHERE IDNO = ?";
    $stmt = $con->prepare($session_query);
    $stmt->bind_param("s", $search_result['IDNO']);
    $stmt->execute();
    $session_result = $stmt->get_result()->fetch_assoc();
    $session_value = $session_result['REMAINING_SESSIONS'];
    $stmt->close();
}

// Decrease session value on logout
if (isset($_GET['logout'])) {
    $update_session_query = "UPDATE user SET REMAINING_SESSIONS = REMAINING_SESSIONS - 1 WHERE USERNAME = ?";
    $stmt = $con->prepare($update_session_query);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->close();
    session_destroy();
    header("Location: logout.php");
    exit();
}

// Fetch sit-in records for different rooms
$room_query = "SELECT LAB_ROOM, COUNT(*) as count 
               FROM login_records 
               WHERE TIME_OUT IS NULL 
               GROUP BY LAB_ROOM 
               HAVING count > 0";
$room_result = mysqli_query($con, $room_query);

$rooms = [];
$counts = [];

if ($room_result) {
    while ($row = mysqli_fetch_assoc($room_result)) {
        $rooms[] = $row['LAB_ROOM'];
        $counts[] = $row['count'];
    }
}

// Fetch purpose distribution
$purpose_query = "SELECT PURPOSE, COUNT(*) as count 
                 FROM login_records 
                 WHERE TIME_OUT IS NULL 
                 GROUP BY PURPOSE 
                 HAVING count > 0";
$purpose_result = mysqli_query($con, $purpose_query);

$purposes = [];
$purpose_counts = [];

if ($purpose_result) {
    while ($row = mysqli_fetch_assoc($purpose_result)) {
        $purposes[] = $row['PURPOSE'];
        $purpose_counts[] = $row['count'];
    }
}

// Fetch the most active student
$most_active_query = "SELECT 
    IDNO,
    CONCAT(FIRSTNAME, ' ', MIDNAME, ' ', LASTNAME) as FULLNAME,
    (30 - REMAINING_SESSIONS) as SESSIONS_USED,
    POINTS as TOTAL_POINTS,
    PROFILE_PIC
FROM user 
WHERE REMAINING_SESSIONS < 30
ORDER BY REMAINING_SESSIONS ASC
LIMIT 1";

$most_active_result = mysqli_query($con, $most_active_query);
$most_active_student = $most_active_result ? mysqli_fetch_assoc($most_active_result) : null;

// Update the leaderboard query to fetch all students with points
$leaderboardQuery = "SELECT 
    u.IDNO,
    CONCAT(u.FIRSTNAME, ' ', u.MIDNAME, ' ', u.LASTNAME) as FULLNAME,
    (30 - u.REMAINING_SESSIONS) as SESSIONS_USED,
    u.POINTS as TOTAL_POINTS,
    u.PROFILE_PIC
FROM user u 
WHERE u.POINTS > 0
ORDER BY u.POINTS DESC, (30 - u.REMAINING_SESSIONS) DESC";

$leaderboardResult = mysqli_query($con, $leaderboardQuery);
$leaderboard_data = [];
if ($leaderboardResult) {
    while ($row = mysqli_fetch_assoc($leaderboardResult)) {
        $leaderboard_data[] = $row;
    }
}

// Fetch the count of pending reservations
$pendingCount = 0;
$query = "SELECT COUNT(*) as count FROM reservations WHERE status = 'pending'";
$result = $con->query($query);
if ($result) {
    $row = $result->fetch_assoc();
    $pendingCount = $row['count'];
}

// Get statistics
$registered_students_query = "SELECT COUNT(*) as count FROM user WHERE IDNO IS NOT NULL";
$current_sitins_query = "SELECT COUNT(*) as count FROM login_records WHERE TIME_OUT IS NULL";
$total_sitins_query = "SELECT COUNT(*) as count FROM login_records";

$registered_students = mysqli_fetch_assoc(mysqli_query($con, $registered_students_query))['count'];
$current_sitins = mysqli_fetch_assoc(mysqli_query($con, $current_sitins_query))['count'];
$total_sitins = mysqli_fetch_assoc(mysqli_query($con, $total_sitins_query))['count'];

// Fetch announcements
$announcements_query = "SELECT * FROM announcements ORDER BY CREATED_AT DESC LIMIT 5";
$announcements_result = mysqli_query($con, $announcements_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<title>Admin Dashboard</title>
<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: Arial, sans-serif;
        
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
    background: rgba(247, 162, 5, 0.88);
    margin-left: 10px;
}

.nav-right .logout-button:hover {
    background: rgba(255, 251, 0, 0.93);
}

.content {
    margin-top: 80px;
    padding: 30px;
    min-height: calc(100vh - 80px);
    background: #f0f2f5;
    width: 100%;
}

/* Remove old sidebar styles */
.sidebar {
    display: none;
}

/* Update notification badge position */
.notification-badge {
    position: relative;
    top: -2px;
    right: -5px;
    margin-left: 5px;
}

/* Responsive adjustments */
@media (max-width: 1200px) {
    .nav-right {
        flex-wrap: wrap;
        justify-content: center;
    }
    
    .nav-right a {
        font-size: 0.8rem;
        padding: 6px 12px;
    }
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
}

.parent {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    grid-template-rows: auto auto;
    gap: 20px;
    width: 100%;
    max-width: 100%;
    margin: 0 auto;
    padding: 0 20px;
}
.div1 {
    grid-column: 1 / 3;
    background: rgb(26, 19, 46);
    border-radius: 15px;
    padding: 25px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    width: 100%;
}
.profile-section {
    display: flex;
    align-items: center;
    gap: 20px;
}
.search-section {
    display: flex;
    gap: 30px;
    
       
}
.search-form {
    display: flex;
    gap: 10px;
}
.search-container {
    display: flex;
    gap: 5px;
}
.search-container input {
    padding: 8px 15px;
    border: none;
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.1);
    color: white;
    width: 200px;
}
.search-container input::placeholder {
    color: rgba(255, 255, 255, 0.7);
}
.search-container button,
.list-button {
    padding: 8px 15px;
    border: none;
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.1);
    color: white;
    cursor: pointer;
    transition: background 0.2s;
}
.search-container button:hover,
.list-button:hover {
    background: rgba(255, 255, 255, 0.2);
}
.div1 img {
    width: 70px;
    height: 70px;
    border-radius: 12px;
    border: 3px solid rgba(255, 255, 255, 0.2);
}
.welcome-text {
    color: white;
}
.welcome-text p:first-child {
    font-size: 1.4rem;
    font-weight: 600;
    margin-bottom: 5px;
}
.date {
    font-size: 0.9rem;
    opacity: 0.8;
}
.div2, .div3 {
    background: gray;
    border-radius: 15px;
    padding: 20px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    width: 100%;
}
.div4 {
    grid-column: 3;
    grid-row: 1 / 3;
    background: rgb(26, 19, 46);
    border-radius: 15px;
    padding: 20px;
    color: white;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    display: flex;
    flex-direction: column;
    height: calc(100vh - 100px);
    max-height: none;
    width: 100%;
}

.points-badge {
    background: linear-gradient(135deg, #0369a1, #0284c7);
    color: white;
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 0.9rem;
    font-weight: 600;
    white-space: nowrap;
    box-shadow: 0 2px 8px rgba(3, 105, 161, 0.3);
    transition: all 0.3s ease;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.points-badge:hover {
    transform: scale(1.05);
    box-shadow: 0 4px 12px rgba(197, 162, 6, 0.4);
}
@keyframes sparkle {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.1); }
}

.leaderboard-item:nth-child(-n+3) .rank i {
    animation: sparkle 2s infinite;
}
/* Rank Icons */
.rank {
    min-width:30x;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    font-weight: 600;
    color: #64748b;
}

.rank i {
    font-size: 1.5rem;
    filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.1));
}

.leaderboard {
    flex: 1;
    overflow-y: auto;
    padding-right: 10px;
    margin-top: 15px;
}
.leaderboard-user {
    display: flex;
    align-items: center;
    gap: 12px;
    flex: 1;
}
.points-badge {
    background: #e0f2fe;
    color: #0369a1;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.9rem;
    font-weight: 500;
    white-space: nowrap;
}

.leaderboard-item .user-name strong {
    background: linear-gradient(135deg, #0369a1, #0284c7);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    font-weight: 700;
    font-size: 1.1rem;
}
.leaderboard-item .user-name strong {
    background: linear-gradient(135deg, #0369a1, #0284c7);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    font-weight: 700;
    font-size: 1.1rem;
}

.leaderboard::-webkit-scrollbar {
    width: 6px;
}

.leaderboard::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 3px;
}

.leaderboard::-webkit-scrollbar-thumb {
    background: rgb(255, 255, 255);
    border-radius: 3px;
}

/* Updated Rankings Design */
.leaderboard-item {
    background: white;
    padding: 15px 20px;
    border-radius: 12px;
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    gap: 15px;
    transition: all 0.3s ease;
    border: 1px solid #e2e8f0;
}

.leaderboard-item:hover {
    transform: translateX(5px);
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
}

.leaderboard-item:nth-child(1) {
    background: linear-gradient(to right, rgba(108, 162, 220, 0.9), rgba(255, 255, 255, 0.7));
    border-left: 4px solid rgb(255, 255, 255);
    box-shadow: 0 4px 15px rgba(20, 86, 155, 0.2);
}

.leaderboard-item:nth-child(2) {
    background: linear-gradient(to right, rgba(65, 145, 231, 0.7), rgba(255, 255, 255, 0.7));
    border-left: 4px solid rgb(255, 255, 255);
    box-shadow: 0 4px 15px rgba(20, 86, 155, 0.15);
}

.leaderboard-item:nth-child(3) {
    background: linear-gradient(to right, rgba(25, 122, 225, 0.5), rgba(255, 255, 255, 0.7));
    border-left: 4px solid rgb(255, 255, 255);
    box-shadow: 0 4px 15px rgba(20, 86, 155, 0.1);
}

.leaderboard-item:nth-child(4) {
    background: linear-gradient(to right, rgba(25, 122, 225, 0.5), rgba(255, 255, 255, 0.7));
    border-left: 4px solid rgb(255, 255, 255);
    box-shadow: 0 4px 15px rgba(20, 86, 155, 0.1);
}

.leaderboard-item:nth-child(5) {
    background: linear-gradient(to right, rgba(25, 122, 225, 0.5), rgba(255, 255, 255, 0.7));
    border-left: 4px solid rgb(255, 255, 255);
    box-shadow: 0 4px 15px rgba(20, 86, 155, 0.1);
}

.leaderboard-item:nth-child(6) {
    background: linear-gradient(to right, rgba(25, 122, 225, 0.5), rgba(255, 255, 255, 0.7));
    border-left: 4px solid rgb(255, 255, 255);
    box-shadow: 0 4px 15px rgba(20, 86, 155, 0.1);
}

.leaderboard-item:nth-child(7) {
    background: linear-gradient(to right, rgba(25, 122, 225, 0.5), rgba(255, 255, 255, 0.7));
    border-left: 4px solid rgb(255, 255, 255);
    box-shadow: 0 4px 15px rgba(20, 86, 155, 0.1);
}

.leaderboard-item:nth-child(8) {
    background: linear-gradient(to right, rgba(25, 122, 225, 0.5), rgba(255, 255, 255, 0.7));
    border-left: 4px solid rgb(255, 255, 255);
    box-shadow: 0 4px 15px rgba(20, 86, 155, 0.1);
}

.leaderboard-item:nth-child(9) {
    background: linear-gradient(to right, rgba(25, 122, 225, 0.5), rgba(255, 255, 255, 0.7));
    border-left: 4px solid rgb(255, 255, 255);
    box-shadow: 0 4px 15px rgba(20, 86, 155, 0.1);
}

.leaderboard-item:nth-child(10) {
    background: linear-gradient(to right, rgba(25, 122, 225, 0.5), rgba(255, 255, 255, 0.7));
    border-left: 4px solid rgb(255, 255, 255);
    box-shadow: 0 4px 15px rgba(20, 86, 155, 0.1);
}
.leaderboard-item:nth-child(11) {
    background: linear-gradient(to right, rgba(25, 122, 225, 0.5), rgba(255, 255, 255, 0.7));
    border-left: 4px solid rgb(255, 255, 255);
    box-shadow: 0 4px 15px rgba(20, 86, 155, 0.1);
}
.leaderboard-item:nth-child(12) {
    background: linear-gradient(to right, rgba(25, 122, 225, 0.5), rgba(255, 255, 255, 0.7));
    border-left: 4px solid rgb(255, 255, 255);
    box-shadow: 0 4px 15px rgba(20, 86, 155, 0.1);
}
.leaderboard-item:nth-child(13) {
    background: linear-gradient(to right, rgba(25, 122, 225, 0.5), rgba(255, 255, 255, 0.7));
    border-left: 4px solid rgb(255, 255, 255);
    box-shadow: 0 4px 15px rgba(20, 86, 155, 0.1);
}
.leaderboard-item:nth-child(14) {
    background: linear-gradient(to right, rgba(25, 122, 225, 0.5), rgba(255, 255, 255, 0.7));
    border-left: 4px solid rgb(255, 255, 255);
    box-shadow: 0 4px 15px rgba(20, 86, 155, 0.1);
}
.leaderboard-item:nth-child(15) {
    background: linear-gradient(to right, rgba(25, 122, 225, 0.5), rgba(255, 255, 255, 0.7));
    border-left: 4px solid rgb(255, 255, 255);
    box-shadow: 0 4px 15px rgba(20, 86, 155, 0.1);
}
.leaderboard-item:nth-child(16) {
    background: linear-gradient(to right, rgba(25, 122, 225, 0.5), rgba(255, 255, 255, 0.7));
    border-left: 4px solid rgb(255, 255, 255);
    box-shadow: 0 4px 15px rgba(20, 86, 155, 0.1);
}
.leaderboard-item:nth-child(17) {
    background: linear-gradient(to right, rgba(25, 122, 225, 0.5), rgba(255, 255, 255, 0.7));
    border-left: 4px solid rgb(255, 255, 255);
    box-shadow: 0 4px 15px rgba(20, 86, 155, 0.1);
}
.leaderboard-item:nth-child(18) {
    background: linear-gradient(to right, rgba(25, 122, 225, 0.5), rgba(255, 255, 255, 0.7));
    border-left: 4px solid rgb(255, 255, 255);
    box-shadow: 0 4px 15px rgba(20, 86, 155, 0.1);
}


.rank {
    min-width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    font-weight: 600;
    color: rgb(255, 255, 255);
}

.rank i {
    font-size: 1.2rem;
}

.leaderboard-title {
    
    font-size: 1.5rem;
    font-weight: 600;
    color:rgba(3, 3, 3, 0);
    box-shadow: 0 4px 15px rgba(20, 86, 155, 0.1);
    margin-bottom: 15px;
}

/* Ensure text is visible on darker backgrounds */
.leaderboard-item:nth-child(-n+3) .user-name,
.leaderboard-item:nth-child(-n+3) .user-id,
.leaderboard-item:nth-child(-n+3) .rank {
    color: white;
}

.leaderboard-item:nth-child(-n+3) .points-badge {
    background: white;
    color: #14569b;
}

/* Add hover effect */
.leaderboard-item:nth-child(-n+3):hover {
    transform: translateX(5px);
    filter: brightness(1.1);
}
.leaderboard-avatar {
    width: 40px;
    height:40px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid #e2e8f0;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
}
.leaderboard-item:hover .leaderboard-avatar {
    transform: scale(1.1);
    border-color: #0369a1;
}
.leaderboard-list {
    flex: 1;
    overflow-y: auto;
    padding-right: 10px;
}
.student-info {
    display: flex;
    align-items: center;
    gap: 12px;
    flex: 1;
}

.student-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid rgba(255, 255, 255, 0.2);
}

.student-details {
    display: flex;
    flex-direction: column;
}

.name {
    color: black;
    font-weight: 600;
}

.student-id {
    color: rgba(0, 0, 0, 0.7);
    font-size: 0.8rem;
}

.stats {
    display: flex;
    flex-direction: column;
    gap: 6px;
    min-width: 120px;
}

.sessions, .points {
    background: rgba(8, 8, 8, 0.18);
    padding: 4px 10px;
    border-radius: 15px;
    font-size: 0.85rem;
    color: black    ;
    text-align: center;
}

.rank-number {
    color: rgba(0, 0, 0, 0.96);
    font-size: 0.9rem;
    font-weight: bold;
}
.search-bar {
    position: fixed;
    top: 20px;
    right: 30px;
    display: flex;
    gap: 10px;
    z-index: 1000;
}
.search-bar input[type="text"] {
    padding: 8px 15px;
    border: none;
    border-radius: 8px;
    background: white;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    width: 200px;
}
.search-bar button {
    padding: 8px 15px;
    border: none;
    border-radius: 8px;
    background: #14569b;
    color: white;
    cursor: pointer;
    transition: background 0.2s;
}
.search-bar button:hover {
    background: #0f4578;
}
@media (max-width: 1200px) {
    .parent {
        grid-template-columns: 1fr 1fr;
        padding: 15px;
    }
    
    .div4 {
        grid-column: 1 / 3;
        grid-row: 3;
        height: auto;
    }
}
@media (max-width: 768px) {
    .content {
        margin-left: 0;
        padding: 15px;
    }
    
    .parent {
        grid-template-columns: 1fr;
        padding: 10px;
    }
    
    .div1, .div2, .div3, .div4 {
        grid-column: 1;
        width: 100%;
    }
    
    .search-bar {
        position: relative;
        top: 0;
        right: 0;
        margin-bottom: 20px;
    }
}

/* Add this CSS in your <style> tag */
.modal {
    display: none;
    position: fixed;
    z-index: 1050;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    animation: fadeIn 0.3s;
}

.modal-content {
    background: white;
    margin: 5% auto;
    padding: 25px;
    border-radius: 15px;
    width: 90%;
    max-width: 500px;
    position: relative;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
    animation: slideIn 0.3s;
}

.close {
    position: absolute;
    right: 20px;
    top: 15px;
    font-size: 28px;
    font-weight: bold;
    color: #718096;
    cursor: pointer;
    transition: color 0.2s;
}

.close:hover {
    color: #2d3748;
}

.modal-content h3 {
    color: #14569b;
    margin-bottom: 20px;
    font-size: 1.5rem;
    font-weight: 600;
}

.modal-content p {
    margin: 10px 0;
    color: #2d3748;
}

.modal-content label {
    display: block;
    margin: 15px 0 5px;
    color: #14569b;
    font-weight: 500;
}

.modal-content select,
.modal-content input {
    width: 100%;
    padding: 10px;
    margin-bottom: 15px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    background: #f8fafc;
}

.modal-content button {
    background: #14569b;
    color: white;
    padding: 12px 25px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 500;
    width: 100%;
    margin-top: 20px;
    transition: background 0.2s;
}

.modal-content button:hover {
    background: #0f4578;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes slideIn {
    from {
        transform: translateY(-10%);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

/* Statistics Styles */
.statistics-container {
    background: white;
    border-radius: 12px;
    padding: 20px;
    margin: 20px 30px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
}

.statistics-container h2 {
    color: #14569b;
    font-size: 1.5rem;
    margin-bottom: 20px;
}

.statistics-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
}

.stat-card {
    background: #f8f9fa;
    border-radius: 10px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
    transition: transform 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-5px);
}

.stat-card i {
    font-size: 2rem;
    color: #14569b;
    background: rgba(20, 86, 155, 0.1);
    padding: 15px;
    border-radius: 10px;
}

.stat-info h3 {
    color: #2a3f5f;
    font-size: 1rem;
    margin-bottom: 5px;
}

.stat-info p {
    color: #14569b;
    font-size: 1.8rem;
    font-weight: bold;
    margin: 0;
}

@media (max-width: 768px) {
    .statistics-grid {
        grid-template-columns: 1fr;
    }
    
    .statistics-container {
        margin: 20px 15px;
    }
}

.div2 {
    background: white;
    border-radius: 15px;
    padding: 20px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    width: 100%;
    position: relative;
    perspective: 1000px;
}

.chart-container {
    position: relative;
    width: 100%;
    height: 300px;
    transition: transform 0.6s;
    transform-style: preserve-3d;
}

.chart-container.flipped {
    transform: rotateY(180deg);
}

.chart-front, .chart-back {
    position: absolute;
    width: 100%;
    height: 100%;
    backface-visibility: hidden;
    display: flex;
    flex-direction: column;
    align-items: center;
}

.chart-back {
    transform: rotateY(180deg);
}

.toggle-chart-btn {
    position: absolute;
    top: 10px;
    right: 10px;
    background: #4CAF50;
    color: white;
    border: none;
    padding: 8px 15px;
    border-radius: 20px;
    cursor: pointer;
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    gap: 5px;
    transition: all 0.3s ease;
    z-index: 10;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
}

.toggle-chart-btn:hover {
    background: #45a049;
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
}

.chart-title {
    color: #000000;
    font-size: 1.5rem;
    margin-bottom: 20px;
    text-align: center;
}

.div3 {
    background: white;
    border-radius: 15px;
    padding: 20px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    width: 100%;
    height: 100%;
    display: flex;
    flex-direction: column;
    position: relative;
    min-height: 400px;
}

.current-time {
    background: linear-gradient(45deg, rgb(150, 145, 79), rgb(47, 0, 177));
    padding: 15px 20px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    gap: 15px;
    color: white;
    font-size: 1.2rem;
    font-weight: 500;
    margin-top: 20px;
    position: sticky;
    bottom: 0;
    left: 0;
    right: 0;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
}

.current-time i {
    color: white;
    font-size: 1.3rem;
}

.current-time span {
    margin-right: 15px;
    text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.2);
}

.announcement-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.new-announcement-btn {
    background: #4CAF50;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 8px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.9rem;
    transition: all 0.3s ease;
    text-decoration: none;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
}

.new-announcement-btn:hover {
    background: #45a049;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
}

.new-announcement-btn i {
    font-size: 1rem;
}

.announcements-container {
    flex: 1;
    overflow-y: auto;
    padding-right: 10px;
    max-height: 500px;
}

.announcement-item {
    background: #ffffff;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 20px;
    border-left: 6px solid #14569b;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
}

.announcement-item:hover {
    transform: translateX(5px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    background: #f8fafc;
}

.announcement-title {
    color: #14569b;
    font-size: 1.3rem;
    font-weight: 700;
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.announcement-title i {
    color: #14569b;
    font-size: 1.2rem;
}

.announcement-content {
    color: #2d3748;
    font-size: 1.1rem;
    margin-bottom: 15px;
    line-height: 1.6;
    padding: 10px;
    background: #f8fafc;
    border-radius: 8px;
}

.announcement-date {
    color: #718096;
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    gap: 8px;
    padding-top: 10px;
    border-top: 1px solid #e2e8f0;
}

.announcement-date i {
    color: #14569b;
    font-size: 1rem;
}

.no-announcements {
    text-align: center;
    color: #718096;
    padding: 30px;
    font-style: italic;
    background: #f8fafc;
    border-radius: 12px;
    margin: 20px 0;
}

.no-announcements i {
    font-size: 2rem;
    color: #14569b;
    margin-bottom: 10px;
    display: block;
}

.points-card {
    background: linear-gradient(135deg, #4CAF50, #45a049);
    border: none;
}

.points-card i {
    color: #333;
    background: rgba(255, 255, 255, 0.9);
}

.points-card .session-label {
    color: #333;
}

.points-card .session-value {
    color: #333;
    font-size: 1.6rem;
}

.points-card:hover {
    transform: translateX(5px);
    background: linear-gradient(135deg, #45a049, #4CAF50);
    box-shadow: 0 4px 12px rgba(76, 175, 80, 0.3);
}

.logout-btn {
    width: 100%;
    padding: 12px;
    background: #dc3545;
    color: white;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 500;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    transition: all 0.3s ease;
    margin-top: 20px;
}

.logout-btn:hover {
    background: #c82333;
    transform: translateY(-2px);
}

.logout-btn i {
    font-size: 1.1rem;
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
        <a href="admindash.php"></i> DASHBOARD</a>
        <a href="adannouncement.php"></i> Announcements</a>
        <a href="liststudent.php"></i> Students</a>
        <a href="adsitin.php" onclick="showCurrentSitins()"></i> Current Sitin</a>
        
     
        <a href="adlabresources.php"></i> Lab Resources</a>
        <a href="adlabsched.php"></i> Lab Schedule</a>
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
    <div class="parent">
        <div class="div1">
            <div>
                
                <div class="welcome-text">
                    <p><?php 
                        $current_time = date('H:i');
                        $hour = date('H');
                        if ($hour >= 1 && $hour < 12) {
                            echo "Good Afternoon";
                        } elseif ($hour >= 12 && $hour < 17) {
                            echo "Good Morning";
                        } else {
                            echo "Good evening";
                        }
                        echo ", " . htmlspecialchars($user_name) . "!";
                    ?></p>
                    <p class="date">Today is <?php date_default_timezone_set("Asia/Manila"); echo date("l, F j, Y h:i A"); ?></p>
                </div>
            </div>
            <div class="search-section">
                <form method="POST" action="" class="search-form">
                    <div class="search-container">
                        <input type="text" name="search_id" placeholder="ID Number" required>
                        <button type="submit" name="search"><i class="fas fa-search"></i></button>
                    </div>
                   
                </form>
            </div>
        </div>
        <div class="div2">
            <button class="toggle-chart-btn" onclick="toggleChart()">
                <i class="fas fa-sync-alt"></i> Toggle View
            </button>
            <div class="chart-container">
                <div class="chart-front">
                    <h2 class="chart-title">Room Distribution</h2>
            <canvas id="roomPieChart"></canvas>
        </div>
                <div class="chart-back">
                    <h2 class="chart-title">Purpose Distribution</h2>
            <canvas id="purposePieChart"></canvas>
                </div>
            </div>
        </div>
        <div class="div3">
            <div class="announcement-header">
                <h2 style="color: #000000; font-size: 1.8rem; display: flex; align-items: center; gap: 10px;">
                     Latest Announcements
                </h2>
                <a href="adannouncement.php" class="new-announcement-btn">
                    <i class="fas fa-plus"></i> Create New
                </a>
            </div>
            <div class="announcements-container" id="announcementsContainer">
                <?php if ($announcements_result && mysqli_num_rows($announcements_result) > 0): ?>
                    <?php while ($announcement = mysqli_fetch_assoc($announcements_result)): ?>
                        <div class="announcement-item">
                            <div class="announcement-title">
                                <i class="fas fa-bell"></i>
                                <?php echo htmlspecialchars($announcement['TITLE']); ?>
                            </div>
                            <div class="announcement-content">
                                <?php echo htmlspecialchars($announcement['CONTENT']); ?>
                            </div>
                            <div class="announcement-date">
                                <i class="far fa-clock"></i>
                                Posted on: <?php echo date('F j, Y g:i A', strtotime($announcement['CREATED_AT'])); ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="no-announcements">
                        <i class="fas fa-info-circle"></i>
                        <p>No announcements available at the moment</p>
                    </div>
                <?php endif; ?>
            </div>
            <div class="current-time">
                <i class="fas fa-calendar-alt"></i>
                <span id="current-date-div3"></span>
                <i class="fas fa-clock"></i>
                <span id="current-time-div3"></span>
            </div>
        </div>
        <div class="div4">
            <div class="statistics-container" style="margin: 0 0 20px 0; background: transparent; box-shadow: none;">
                <h2 style="color: white;">Statistics</h2>
                <div class="statistics-grid">
                    <div class="stat-card" style="background: rgba(255, 255, 255, 0.1);">
                        <i class="fas fa-users" style="color: white;"></i>
                        <div class="stat-info">
                            <h3 style="color: white;">Registered Students</h3>
                            <p style="color: white;"><?php echo $registered_students; ?></p>
                        </div>
                    </div>
                    <div class="stat-card" style="background: rgba(255, 255, 255, 0.1); cursor: pointer;" onclick="showCurrentSitins()">
                        <i class="fas fa-chair" style="color: white;"></i>
                        <div class="stat-info">
                            <h3 style="color: white;">Current Sit-ins</h3>
                            <p style="color: white;"><?php echo $current_sitins; ?></p>
                        </div>
                    </div>
                    <div class="stat-card" style="background: rgba(255, 255, 255, 0.1);">
                        <i class="fas fa-history" style="color: white;"></i>
                        <div class="stat-info">
                            <h3 style="color: white;">Total Sit-ins</h3>
                            <p style="color: white;"><?php echo $total_sitins; ?></p>
                        </div>
                    </div>
                </div>
            </div>
            <h2 style="color: white;"> Student Rankings</h2>
            <div class="leaderboard">
                <?php
                // Fetch all students by points with consistent ordering (same as dashboard.php)
                $leaderboardQuery = "SELECT 
                    u.IDNO, 
                    u.FIRSTNAME, 
                    u.MIDNAME,
                    u.LASTNAME, 
                    u.PROFILE_PIC, 
                    u.POINTS, 
                    u.USERNAME,
                    u.REMAINING_SESSIONS 
                FROM user u 
                WHERE u.USERNAME != 'admin' 
                ORDER BY u.POINTS DESC, u.REMAINING_SESSIONS ASC, u.LASTNAME ASC";

                $leaderboardResult = mysqli_query($con, $leaderboardQuery);
                $rank = 1;

                if ($leaderboardResult) {
                    while ($user = mysqli_fetch_assoc($leaderboardResult)) {
                        $profile_pic = !empty($user['PROFILE_PIC']) ? htmlspecialchars($user['PROFILE_PIC']) : 'default.jpg';
                        $isCurrentUser = ($user['USERNAME'] === $username); // $username should be set
                        $middleInitial = !empty($user['MIDNAME']) ? ' ' . substr($user['MIDNAME'], 0, 1) . '.' : '';
                        ?>
                        <div class="leaderboard-item">
                            <div class="rank">
                                <?php 
                                if ($rank <= 16) {
                                    switch($rank) {
                                        case 1:
                                            echo '1st';
                                            break;
                                        case 2:
                                            echo '2nd';
                                            break;
                                        case 3:
                                            echo '3rd';
                                            break;
                                        case 4:
                                            echo '4th';
                                            break;
                                        case 5:
                                            echo '5th';
                                            break;
                                        case 6:
                                            echo '6th';
                                            break;
                                        case 7:
                                            echo '7th';
                                            break;  
                                        case 8:
                                            echo '8th';
                                            break;
                                        case 9:
                                            echo '9th';
                                            break;
                                        case 10:
                                            echo '10th';        
                                            break;
                                        case 11:
                                            echo '11th';
                                            break;
                                        case 12:
                                            echo '12th';
                                            break;  
                                        case 13:
                                            echo '13th';
                                            break;
                                        case 14:
                                            echo '14th';
                                            break;
                                        case 15:
                                            echo '15th';
                                            break;
                                        case 16:
                                            echo '16th';
                                            break;
                                        default:
                                    }
                                } else {
                                    echo $rank;
                                }
                                ?>
                            </div>
                            <div class="leaderboard-user">
                                <img src="uploads/<?php echo $profile_pic; ?>" alt="Profile" class="leaderboard-avatar">
                                <div class="user-info">
                                    <div class="user-name" style="color: black; font-size: medium;">
                                        
                                        <?php 
                                        if ($isCurrentUser) {
                                            echo '<strong style="color: #0369a1;">YOU</strong>';
                                        } else {
                                            echo htmlspecialchars($user['LASTNAME'] . ', ' . $user['FIRSTNAME'] . $middleInitial);
                                        }
                                        ?>
                                    </div>
                                    <div class="user-points" style="color: rgba(36, 36, 36, 0.72);">
                                        <?php echo htmlspecialchars($user['IDNO']); ?>
                                    </div>
                                </div>
                            </div>
                            <div class="points-badge" style="display: flex; flex-direction: column; align-items: flex-end; gap: 4px;">
                        <?php 
                        $sessionsUsed = 30 - (int)$user['REMAINING_SESSIONS'];
                        $points = (int)$user['POINTS'];
                                
                                // Check if student has active session
                                $active_session_query = "SELECT COUNT(*) as active FROM login_records WHERE IDNO = ? AND TIME_OUT IS NULL";
                                $stmt = $con->prepare($active_session_query);
                                $stmt->bind_param("s", $user['IDNO']);
                                $stmt->execute();
                                $active_result = $stmt->get_result()->fetch_assoc();
                                $has_active_session = $active_result['active'] > 0;
                                $stmt->close();
                                ?>
                                
                        <div style="
                                    background-color:rgb(1, 129, 65); 
                            color: #fff; 
                            padding: 4px 10px; 
                            border-radius: 20px; 
                            font-size: 13px;
                            font-weight: 500;
                            display: inline-block;
                                    cursor: pointer;
                                    transition: all 0.3s ease;
                                " onclick="showSessionsInfo(<?php echo $user['REMAINING_SESSIONS']; ?>, '<?php echo htmlspecialchars($user['FIRSTNAME'] . ' ' . $user['LASTNAME']); ?>', <?php echo $has_active_session ? 'true' : 'false'; ?>)">
                            <?php echo $points . ' ' . ($points <= 1 ? 'Point' : 'Points'); ?>
                        </div>
                    </div>
                        </div>
                        <?php
                        $rank++;
                    }
                }
                ?>
            </div>
        </div>
    </div>
</div>

<?php if ($user_not_found): ?>
<script>
alert("User does not exist");
</script>
<?php endif; ?>

<?php if ($search_result): ?>
    <div id="searchModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal('searchModal')">&times;</span>
            <h3>Student Information</h3>
            <div class="student-info-display">
        <p><strong>ID:</strong> <?php echo htmlspecialchars($search_result['IDNO']); ?></p>
        <p><strong>Name:</strong> <?php echo htmlspecialchars($search_result['FIRSTNAME'] . ' ' . $search_result['MIDNAME'] . ' ' . $search_result['LASTNAME']); ?></p>
        <p><strong>Course:</strong> <?php echo htmlspecialchars($search_result['COURSE']); ?></p>
        <p><strong>Year Level:</strong> <?php echo htmlspecialchars($search_result['YEARLEVEL']); ?></p>
                <p><strong>Remaining Sessions:</strong> <?php echo htmlspecialchars($session_value); ?></p>
            </div>

            <?php if (isset($has_active_session) && $has_active_session): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    This student already has an active sit-in session.
                </div>
                <form method="POST" action="adsitin.php" style="margin-top: 20px;">
                    <input type="hidden" name="logout_student" value="<?php echo htmlspecialchars($search_result['IDNO']); ?>">
                    <button type="submit" class="logout-btn" onclick="return confirm('Are you sure you want to log out this student? This will award points based on session duration.');">
                        <i class="fas fa-sign-out-alt"></i> Logout Student
                    </button>
                </form>
            <?php elseif ($session_value <= 0): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-times-circle"></i>
                    No remaining sessions available.
                </div>
            <?php else: ?>
                <form method="POST" action="adsitin.php">
                    <input type="hidden" name="student_id" value="<?php echo htmlspecialchars($search_result['IDNO']); ?>">
                    
                    <div class="form-group">
        <label for="purpose">Purpose:</label>
                        <select name="purpose" id="purpose" required>
                            <option value="">Select Purpose</option>
            <option value="C Programming">C Programming</option>
            <option value="Java Programming">Java Programming</option>
            <option value="C# Programming">C# Programming</option>
            <option value="System Integration & Architecture">System Integration & Architecture</option>
            <option value="Embedded System & IoT">Embedded System & IoT</option>
            <option value="Digital logic & Design">Digital logic & Design</option>
            <option value="Computer Application">Computer Application</option>
            <option value="Database">Database</option>
            <option value="Project Management">Project Management</option>
            <option value="Python Programming">Python Programming</option>
            <option value="Mobile Application">Mobile Application</option>
            <option value="Others...">Others...</option>
        </select>
                    </div>

                    <div class="form-group">
        <label for="lab_room">Lab Room:</label>
                        <select name="lab_room" id="lab_room" required>
                            <option value="">Select Room</option>
            <option value="524">524</option>
            <option value="526">526</option>
            <option value="528">528</option>
            <option value="530">530</option>
            <option value="542">542</option>
            <option value="544">544</option>
        </select>
    </div>

                    <button type="submit" class="submit-btn">
                        <i class="fas fa-check"></i> Confirm Sit-in
                    </button>
                </form>
            <?php endif; ?>
</div>
    </div>

    <style>
    .modal-content {
        max-width: 500px;
        width: 90%;
    }

    .student-info-display {
        background: #f8fafc;
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 20px;
    }

    .student-info-display p {
        margin: 8px 0;
        color: #2d3748;
    }

    .form-group {
        margin-bottom: 15px;
    }

    .form-group label {
        display: block;
        margin-bottom: 5px;
        color: #14569b;
        font-weight: 500;
    }

    .form-group select {
        width: 100%;
        padding: 10px;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        background: #f8fafc;
        color: #2d3748;
    }

    .submit-btn {
        width: 100%;
        padding: 12px;
        background: #14569b;
        color: white;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        font-weight: 500;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        transition: all 0.3s ease;
    }

    .submit-btn:hover {
        background: #0f4578;
        transform: translateY(-2px);
    }

    .alert {
        padding: 12px;
        border-radius: 8px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .alert-warning {
        background: #fff3cd;
        color: #856404;
        border: 1px solid #ffeeba;
    }

    .alert-danger {
        background: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }

    .alert i {
        font-size: 1.2rem;
    }
    </style>

<script>
document.getElementById('searchModal').style.display = 'block';

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    modal.style.animation = 'fadeOut 0.3s';
    setTimeout(() => {
        modal.style.display = 'none';
        modal.style.animation = '';
    }, 300);
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('searchModal');
    if (event.target == modal) {
        closeModal('searchModal');
    }
}

    // Form submission handling
    document.getElementById('sitInForm')?.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const purpose = document.getElementById('purpose').value;
        const labRoom = document.getElementById('lab_room').value;
        
        if (!purpose || !labRoom) {
            alert('Please fill in all required fields');
            return;
        }
        
        this.submit();
    });
</script>
<?php endif; ?>

<div id="sessionsModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal('sessionsModal')">&times;</span>
        <h3 id="studentName"></h3>
        <div class="session-info">
            <div class="session-card points-card">
                <i class="fas fa-star"></i>
                <div class="session-details">
                    <span class="session-label">Total Points</span>
                    <span id="totalPoints" class="session-value"></span>
                </div>
            </div>
            <div class="session-card">
                <i class="fas fa-clock"></i>
                <div class="session-details">
                    <span class="session-label">Sessions Used</span>
                    <span id="sessionsUsed" class="session-value"></span>
                </div>
            </div>
            <div class="session-card">
                <i class="fas fa-hourglass-half"></i>
                <div class="session-details">
                    <span class="session-label">Remaining Sessions</span>
                    <span id="remainingSessions" class="session-value"></span>
                </div>
            </div>
            <div id="activeSessionStatus" class="session-card" style="display: none;">
                <i class="fas fa-user-check"></i>
                <div class="session-details">
                    <span class="session-label">Current Status</span>
                    <span class="session-value" style="color: #4CAF50;">Currently Sitting In</span>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.modal {
    display: none;
    position: fixed;
    z-index: 1050;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    animation: fadeIn 0.3s;
}

.modal-content {
    background: white;
    margin: 5% auto;
    padding: 25px;
    border-radius: 15px;
    width: 90%;
    max-width: 400px;
    position: relative;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
    animation: slideIn 0.3s;
}

.session-info {
    display: flex;
    flex-direction: column;
    gap: 15px;
    margin-top: 20px;
}

.session-card {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    gap: 15px;
    transition: all 0.3s ease;
    border: 1px solid #e0e0e0;
}

.session-card:hover {
    transform: translateX(5px);
    background: #f0f0f0;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}

.session-card i {
    font-size: 1.8rem;
    color: #4CAF50;
    background: rgba(76, 175, 80, 0.1);
    padding: 15px;
    border-radius: 12px;
}

.session-details {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.session-label {
    color: #666;
    font-size: 0.9rem;
}

.session-value {
    color: #333;
    font-size: 1.4rem;
    font-weight: 600;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes slideIn {
    from {
        transform: translateY(-10%);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}
</style>

<script>
function showSessionsInfo(remainingSessions, studentName, hasActiveSession) {
    const sessionsUsed = 30 - remainingSessions;
    const modal = document.getElementById('sessionsModal');
    const studentNameElement = document.getElementById('studentName');
    const totalPointsElement = document.getElementById('totalPoints');
    const sessionsUsedElement = document.getElementById('sessionsUsed');
    const remainingSessionsElement = document.getElementById('remainingSessions');
    const activeSessionStatus = document.getElementById('activeSessionStatus');

    // Get points from the clicked element
    const pointsText = event.target.textContent;
    const points = parseInt(pointsText);

    // Set the content
    studentNameElement.textContent = studentName;
    totalPointsElement.textContent = points + ' ' + (points === 1 ? 'Point' : 'Points');
    sessionsUsedElement.textContent = sessionsUsed;
    remainingSessionsElement.textContent = remainingSessions;
    
    // Show/hide active session status
    activeSessionStatus.style.display = hasActiveSession ? 'flex' : 'none';

    // Show the modal
    modal.style.display = 'block';
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    modal.style.display = 'none';
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('sessionsModal');
    if (event.target == modal) {
        closeModal('sessionsModal');
    }
}
</script>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
function toggleSidebar() {
    document.querySelector('.sidebar').classList.toggle('active');
    document.querySelector('.content').classList.toggle('sidebar-active');
}

function toggleChart() {
    const container = document.querySelector('.chart-container');
    container.classList.toggle('flipped');
    
    // Update button text based on current view
    const button = document.querySelector('.toggle-chart-btn');
    if (container.classList.contains('flipped')) {
        button.innerHTML = '<i class="fas fa-sync-alt"></i> Show Purposes';
    } else {
        button.innerHTML = '<i class="fas fa-sync-alt"></i> Show Rooms';
    }
}

// Initialize charts with proper sizing
window.addEventListener('load', function() {
const roomCtx = document.getElementById('roomPieChart').getContext('2d');
    const purposeCtx = document.getElementById('purposePieChart').getContext('2d');
    
    // Room Distribution Chart
const roomPieChart = new Chart(roomCtx, {
    type: 'pie',
    data: {
        labels: <?php echo json_encode($rooms); ?>,
        datasets: [{
            label: 'Current Sit-ins',
            data: <?php echo json_encode($counts); ?>,
            backgroundColor: [
                    '#1EFF00',  // Solid Green
                    '#0000FF',  // Solid Blue
                    '#800080',  // Solid Purple
                    '#FFFF00',  // Solid Yellow
                    '#FFA500',  // Solid Orange
                    '#FFC0CB'   // Solid Pink
            ],
            borderColor: [
                    '#1EFF00',  // Solid Green
                    '#0000FF',  // Solid Blue
                    '#800080',  // Solid Purple
                    '#FFFF00',  // Solid Yellow
                    '#FFA500',  // Solid Orange
                    '#FFC0CB'   // Solid Pink
            ],
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
            maintainAspectRatio: false,
        plugins: {
            legend: {
                    position: 'top'
            },
            tooltip: {
                callbacks: {
                    label: function(tooltipItem) {
                        return tooltipItem.label + ': ' + tooltipItem.raw + ' student(s)';
                    }
                }
            }
        }
    }
});

// Purpose Distribution Chart
const purposePieChart = new Chart(purposeCtx, {
    type: 'pie',
    data: {
        labels: <?php echo json_encode($purposes); ?>,
        datasets: [{
            label: 'Current Sit-ins',
            data: <?php echo json_encode($purpose_counts); ?>,
            backgroundColor: [
                    '#FF6384',  // C Programming - Pink
                    '#36A2EB',  // Java Programming - Blue
                    '#FFCE56',  // C# Programming - Yellow
                    '#4BC0C0',  // System Integration & Architecture - Teal
                    '#9966FF',  // Embedded System & IoT - Purple
                    '#1EFF00',  // Digital logic & Design - Green
                    '#FFA500',  // Computer Application - Orange
                    '#FF1493',  // Database - Deep Pink
                    '#00CED1',  // Project Management - Dark Turquoise
                    '#FF4500',  // Python Programming - Orange Red
                    '#9370DB',  // Mobile Application - Medium Purple
                    '#20B2AA'   // Others - Light Sea Green
            ],
            borderColor: [
                    '#FF6384',  // C Programming - Pink
                    '#36A2EB',  // Java Programming - Blue
                    '#FFCE56',  // C# Programming - Yellow
                    '#4BC0C0',  // System Integration & Architecture - Teal
                    '#9966FF',  // Embedded System & IoT - Purple
                    '#1EFF00',  // Digital logic & Design - Green
                    '#FFA500',  // Computer Application - Orange
                    '#FF1493',  // Database - Deep Pink
                    '#00CED1',  // Project Management - Dark Turquoise
                    '#FF4500',  // Python Programming - Orange Red
                    '#9370DB',  // Mobile Application - Medium Purple
                    '#20B2AA'   // Others - Light Sea Green
            ],
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
            maintainAspectRatio: false,
        plugins: {
            legend: {
                    position: 'top'
            },
            tooltip: {
                callbacks: {
                    label: function(tooltipItem) {
                        return tooltipItem.label + ': ' + tooltipItem.raw + ' student(s)';
                    }
                }
            }
        }
    }
    });
});

function updatePendingCount() {
    fetch('get_pending_count.php')
        .then(response => response.json())
        .then(data => {
            const badge = document.querySelector('.notification-badge');
            if (data.count > 0) {
                if (!badge) {
                    const newBadge = document.createElement('span');
                    newBadge.className = 'notification-badge';
                    newBadge.textContent = data.count;
                    document.querySelector('a[href="adreservation.php"]').appendChild(newBadge);
                } else {
                    badge.textContent = data.count;
                    badge.style.display = 'flex';
                }
            } else if (badge) {
                badge.style.display = 'none';
            }
        })
        .catch(error => console.error('Error updating pending count:', error));
}

// Update count every 30 seconds
setInterval(updatePendingCount, 30000);

// Initial update on page load
document.addEventListener('DOMContentLoaded', updatePendingCount);

function updateDateTime() {
    const now = new Date();
    
    // Update time with seconds
    const timeString = now.toLocaleTimeString('en-US', { 
        hour: '2-digit', 
        minute: '2-digit', 
        second: '2-digit',
        hour12: true 
    });
    
    // Update date with full format
    const dateString = now.toLocaleDateString('en-US', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
    
    // Update div3 date and time
    const dateDiv3 = document.getElementById('current-date-div3');
    const timeDiv3 = document.getElementById('current-time-div3');
    
    if (dateDiv3 && timeDiv3) {
        dateDiv3.textContent = dateString;
        timeDiv3.textContent = timeString;
    }
}

// Update immediately when page loads
document.addEventListener('DOMContentLoaded', function() {
    updateDateTime();
    // Update every second
    setInterval(updateDateTime, 1000);
});
</script>

<!-- Add this before the closing body tag -->
<div id="currentSitinsModal" class="modal">
    <div class="modal-content" style="max-width: 800px;">
        <span class="close" onclick="closeModal('currentSitinsModal')">&times;</span>
        <h3 style="color: #14569b; margin-bottom: 20px;">Currently Sitting Students</h3>
        <div class="table-container" style="max-height: 500px; overflow-y: auto;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr>
                        <th style="background: #14569b; color: white; padding: 12px; text-align: left;">ID No</th>
                        <th style="background: #14569b; color: white; padding: 12px; text-align: left;">Full Name</th>
                        <th style="background: #14569b; color: white; padding: 12px; text-align: left;">Purpose</th>
                        <th style="background: #14569b; color: white; padding: 12px; text-align: left;">Room</th>
                        <th style="background: #14569b; color: white; padding: 12px; text-align: left;">Time In</th>
                        <th style="background: #14569b; color: white; padding: 12px; text-align: left;">Duration</th>
                    </tr>
                </thead>
                <tbody id="currentSitinsTableBody">
                    <!-- Data will be populated here -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function showCurrentSitins() {
    // Fetch current sit-ins data
    fetch('get_current_sitins.php')
        .then(response => response.json())
        .then(data => {
            const tableBody = document.getElementById('currentSitinsTableBody');
            tableBody.innerHTML = '';
            
            if (data.length === 0) {
                tableBody.innerHTML = `
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 20px;">
                            <div style="color: #666; font-style: italic;">
                                <i class="fas fa-users" style="font-size: 24px; margin-bottom: 10px;"></i>
                                <p>No students are currently sitting in</p>
                            </div>
                        </td>
                    </tr>
                `;
            } else {
                data.forEach(student => {
                    const timeIn = new Date(student.TIME_IN);
                    const duration = calculateDuration(timeIn);
                    
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td style="padding: 12px; border-bottom: 1px solid #eee;">${student.IDNO}</td>
                        <td style="padding: 12px; border-bottom: 1px solid #eee;">${student.FULLNAME}</td>
                        <td style="padding: 12px; border-bottom: 1px solid #eee;">${student.PURPOSE}</td>
                        <td style="padding: 12px; border-bottom: 1px solid #eee;">${student.LAB_ROOM}</td>
                        <td style="padding: 12px; border-bottom: 1px solid #eee;">${formatDateTime(timeIn)}</td>
                        <td style="padding: 12px; border-bottom: 1px solid #eee;" class="duration-cell" data-start="${timeIn.getTime()}">${duration}</td>
                    `;
                    tableBody.appendChild(row);
                });
            }
            
            // Show the modal
            document.getElementById('currentSitinsModal').style.display = 'block';
            
            // Start updating durations
            startDurationUpdates();
        })
        .catch(error => {
            console.error('Error fetching current sit-ins:', error);
            alert('Error loading current sit-ins data');
        });
}

function calculateDuration(startTime) {
    const now = new Date();
    const diff = Math.max(0, now - startTime);
    const minutes = Math.floor(diff / 60000);
    return `${minutes} mins`;
}

function formatDateTime(date) {
    return date.toLocaleString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        hour12: true
    });
}

function startDurationUpdates() {
    setInterval(() => {
        const durationCells = document.querySelectorAll('.duration-cell');
        durationCells.forEach(cell => {
            const startTime = parseInt(cell.getAttribute('data-start'));
            cell.textContent = calculateDuration(new Date(startTime));
        });
    }, 60000); // Update every minute
}

// Add this to your existing closeModal function
function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    modal.style.display = 'none';
}

// Add this to your existing window.onclick function
window.onclick = function(event) {
    const modals = document.querySelectorAll('.modal');
    modals.forEach(modal => {
        if (event.target == modal) {
            closeModal(modal.id);
        }
    });
}
</script>
</body>
</html>