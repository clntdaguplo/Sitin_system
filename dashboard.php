<?php
session_start();
include("connector.php");

// Check if the user is logged in
if (!isset($_SESSION['Username'])) {
    // Redirect to login page if not logged in
    header("Location: login.php");
    exit();
}

// Fetch the profile picture from the database
$username = $_SESSION['Username'];
$query = "SELECT FIRSTNAME, MIDNAME, LASTNAME, PROFILE_PIC FROM user WHERE USERNAME = '$username'";
$result = mysqli_query($con, $query);

if ($result && mysqli_num_rows($result) > 0) {
    $row = mysqli_fetch_assoc($result);
    $firstName = htmlspecialchars($row['FIRSTNAME']);
    $profile_pic = !empty($row['PROFILE_PIC']) ? htmlspecialchars($row['PROFILE_PIC']) : 'default.jpg';
    $user_name = htmlspecialchars($row['LASTNAME'] . ' ' . substr($row['FIRSTNAME'], 0, 1) . '.');
} else {
    $firstName = 'User';
    $profile_pic = 'default.jpg';
    $user_name = 'User';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
<link rel="stylesheet" href="https://www.w3schools.com/w3css/4/w3.css">
<title>Dashboard</title>
<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

html, body {
    background: linear-gradient(135deg, #14569b, #2a3f5f);
    min-height: 100vh;
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
    padding: 20px;
    min-height: calc(100vh - 80px);
    background: #f0f2f5;
}

/* Remove old sidebar styles */
.sidebar {
    display: none;
}

/* Content Area */
.dashboard-grid {
    width: 100%;
    max-width: 1400px;
    margin: 0 auto;
    padding: 0;
}

.welcome-container.fullscreen {
    width: 100%;
    max-width: 100%;
    margin: 0;
    padding: 30px;
    min-height: 100vh;
    background: white;
    border-radius: 0;
    box-shadow: none;
}

.dashboard-content {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 30px;
    margin-top: 30px;
    width: 100%;
    max-width: 100%;
}

.dashboard-left {
    display: flex;
    flex-direction: column;
    gap: 25px;
    width: 100%;
}

.dashboard-right {
    height: 100%;
    width: 100%;
}

.greeting-card {
    background: linear-gradient(135deg, #14569b, #2a3f5f);
    padding: 30px;
    border-radius: 15px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    color: white;
}

.greeting-content {
    display: flex;
    align-items: center;
    gap: 20px;
}

.profile-pic-link {
    text-decoration: none;
    transition: transform 0.3s ease;
}

.profile-pic-link:hover {
    transform: scale(1.05);
}

.profile-pic {
    width: 70px;
    height: 70px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid rgba(255, 255, 255, 0.3);
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
}

.greeting-text h1 {
    color: white;
    font-size: 32px;
    margin-bottom: 5px;
    font-weight: 600;
}

.greeting-text .date {
    color: rgba(255, 255, 255, 0.8);
    font-size: 1rem;
}

.welcome-container {
    margin: 0;
    width: 100%;
    text-align: left;
}

.welcome-container h1 {
    font-size: 2rem;
    color: #14569b;
    margin-bottom: 15px;
    font-weight: 600;
}

.welcome-container p {
    color: #4a5568;
    font-size: 1.1rem;
    line-height: 1.6;
    margin-bottom: 25px;
}

.welcome-container i {
    display: none;
}

.leaderboard-section {
    background: linear-gradient(135deg, #14569b, #2a3f5f);
    border-radius: 15px;
    padding: 20px;
    height: auto;
    max-height: 800px;
    display: flex;
    flex-direction: column;
}

.leaderboard-header {
    margin-bottom: 15px;
}

.leaderboard-title {
    font-size: 1.3rem;
    font-weight: 600;
    color: white;
    display: flex;
    align-items: center;
    gap: 10px;
}

.leaderboard-title i {
    color: #FFD700;
}

.leaderboard-list {
    flex: 1;
    overflow-y: auto;
    padding-right: 10px;
    margin-top: 10px;
}

.leaderboard-item {
    background: rgba(255, 255, 255, 0.1);
    padding: 12px 15px;
    border-radius: 10px;
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 12px;
    transition: all 0.3s ease;
}

.leaderboard-item:hover {
    background: rgba(255, 255, 255, 0.15);
    transform: translateX(5px);
}

.leaderboard-user {
    display: flex;
    align-items: center;
    gap: 10px;
    flex: 1;
}

.leaderboard-avatar {
    width: 35px;
    height: 35px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid rgba(255, 255, 255, 0.3);
}

.user-info {
    flex: 1;
}

.user-name {
    color: white;
    font-size: 0.95rem;
    font-weight: 500;
}

.user-points {
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.8rem;
}


.rank {
    min-width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    font-weight: 600;
    color: red;
}

.rank i {
    font-size: 1.2rem;
}

.welcome-container:hover {
    transform: translateY(-5px);
}

.welcome-container i {
    font-size: 70px;
    color: #14569b;
    margin-bottom: 25px;
    background: #e8f1f8;
    width: 120px;
    height: 120px;
    border-radius: 60px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 25px auto;
}

.welcome-container h1 {
    font-size: 32px;
    color: #2d3748;
    margin-bottom: 20px;
    font-weight: 600;
}

.welcome-container p {
    color: #718096;
    font-size: 1.1rem;
    line-height: 1.6;
    margin-bottom: 25px;
}

.quick-stats {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
}

/* Update the stat-card styles */
.stat-card {
    background: linear-gradient(135deg, #14569b, #2a3f5f);
    padding: 20px;
    border-radius: 12px;
    text-align: left;
    transition: all 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
}

.stat-card h3 {
    color: rgba(255, 255, 255, 0.8);
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 8px;
}

.stat-card .value {
    color: white;
    font-size: 1.5rem;
    font-weight: 600;
}

/* Remove burger menu styles */
.burger {
    display: none;
}

/* Update responsive styles */
@media (max-width: 1024px) {
    .dashboard-content {
        grid-template-columns: 1fr;
    }
    
    .dashboard-right {
        height: auto;
    }
    
    .leaderboard-section {
        max-height: 600px;
    }
}

@media (max-width: 768px) {
    .welcome-container.fullscreen {
        padding: 20px;
    }
    
    .quick-stats {
        grid-template-columns: 1fr;
    }
    
    .leaderboard-section {
        max-height: 500px;
    }
}

/* Remove backdrop-filter properties since we don't need them anymore */
.sidebar, .burger {
    backdrop-filter: none;
}

/* Add to your existing <style> section */
.notification-icon {
    position: fixed;
    top: 20px;
    right: 30px;
    z-index: 1000;
    cursor: pointer;
}

.notification-bell {
    font-size: 24px;
    color: #14569b;
    position: relative;
    transition: transform 0.2s;
}

.notification-bell:hover {
    transform: scale(1.1);
}

.notification-count {
    position: absolute;
    top: -8px;
    right: -8px;
    background: #ef4444;
    color: white;
    border-radius: 50%;
    padding: 2px 6px;
    font-size: 12px;
    font-weight: bold;
}

.notification-dropdown {
    display: none;
    position: absolute;
    top: 40px;
    right: 0;
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    width: 300px;
    max-height: 400px;
    overflow-y: auto;
    z-index: 1000;
}

.notification-header {
    padding: 15px;
    border-bottom: 1px solid #e2e8f0;
    font-weight: 600;
    color: #14569b;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.notification-list {
    padding: 0;
    margin: 0;
    list-style: none;
}

.notification-item {
    padding: 12px 15px;
    border-bottom: 1px solid #f1f5f9;
    transition: background 0.2s;
    display: flex;
    align-items: flex-start;
    gap: 10px;
    cursor: pointer;
}

.notification-item:hover {
    background: #f8fafc;
}

.notification-item.unread {
    background: #f0f7ff;
}

.notification-content {
    flex: 1;
}

.notification-message {
    color: #2d3748;
    margin-bottom: 4px;
}

.notification-time {
    font-size: 0.8rem;
    color: #718096;
}

.notification-type {
    font-size: 20px;
    color: #14569b;
}

.mark-read-btn {
    background: none;
    border: none;
    color: #14569b;
    cursor: pointer;
    padding: 5px;
    border-radius: 4px;
}

.mark-read-btn:hover {
    background: rgba(20, 86, 155, 0.1);
}

/* Add these new styles to your existing CSS */
.announcements-section {
    background: #f8fafc;
    border-radius: 12px;
    padding: 20px;
    margin: 0;
    border: 1px solid #e2e8f0;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.section-header h2 {
    color: #14569b;
    font-size: 1.2rem;
    display: flex;
    align-items: center;
    gap: 8px;
}

.section-header h2 i {
    color: #578FCA;
}

.view-all {
    color: #14569b;
    text-decoration: none;
    font-size: 0.9rem;
    padding: 5px 10px;
    border-radius: 5px;
    transition: all 0.3s ease;
}

.view-all:hover {
    background: rgba(20, 86, 155, 0.1);
}

.announcements-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.announcement-card {
    background: white;
    border-radius: 10px;
    padding: 15px;
    border: 1px solid #e2e8f0;
    margin-bottom: 10px;
    transition: all 0.3s ease;
}

.announcement-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
}

.announcement-card h3 {
    color: #14569b;
    font-size: 1.1rem;
    margin-bottom: 8px;
}

.announcement-card p {
    color: #4a5568;
    line-height: 1.5;
    margin-bottom: 10px;
    font-size: 0.95rem;
}

.announcement-card small {
    color: #718096;
    font-size: 0.85rem;
    display: block;
    margin-top: 5px;
}

.no-announcements {
    text-align: center;
    color: #718096;
    padding: 15px;
    background: white;
    border-radius: 8px;
    border: 1px dashed #e2e8f0;
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
        <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="viewAnnouncement.php"><i class="fas fa-bullhorn"></i> Announcement</a>
        <a href="labRules&Regulations.php"><i class="fas fa-flask"></i> Rules & Regulations</a>
        <a href="sitinrules.php"><i class="fas fa-book"></i> Sit-in Rules</a>
        <a href="history.php"><i class="fas fa-history"></i> History</a>
        <a href="reservation.php"><i class="fas fa-calendar-alt"></i> Reservation</a>
        <a href="labschedule.php"><i class="fas fa-calendar-alt"></i> Lab Schedules</a>
        <a href="viewlabresources.php"><i class="fas fa-book"></i> Lab Resources</a>
        <a href="login.php" class="logout-button"><i class="fas fa-sign-out-alt"></i> Log Out</a>
    </div>
</div>

<div class="content">
    <div class="dashboard-grid">    
        <!-- Main Content -->
        <?php
        include "connector.php";

        if (isset($_SESSION['Username'])) {
            $username = $_SESSION['Username'];
            $sql = "SELECT * FROM user WHERE USERNAME='$username'";
            $result = mysqli_query($con, $sql);

            if ($result && mysqli_num_rows($result) > 0) {
                $row = mysqli_fetch_assoc($result);
                $Firstname = htmlspecialchars($row['FIRSTNAME']);
                echo "<h1 class='page-title'><b>Welcome <b>$Firstname</b> to Sit-in Monitoring System</b></h1>";
            } else {
                echo "<h1 class='page-title'>Welcome to Sit-in Monitoring System</h1>";
            }
        } else {
            echo "<h1 class='page-title'>Welcome to Sit-in Monitoring System</h1>";
        }
        ?>
        <div class="dashboard-content">
            <!-- Left Section -->
            <div class="dashboard-left">
                <!-- Announcements Section -->
                <article class="announcements-section">
                    <div class="section-header">
                        <h2><i class="fas fa-bullhorn"></i> Recent Announcements</h2>
                        <a href="viewAnnouncement.php" class="view-all">View All</a>
                    </div>
                    <div class="announcements-list">
                        <?php
                        // Fetch recent announcements
                        $announcements_query = "SELECT * FROM announcements ORDER BY CREATED_AT DESC LIMIT 3";
                        $announcements_result = mysqli_query($con, $announcements_query);
                        
                        if ($announcements_result && mysqli_num_rows($announcements_result) > 0) {
                            while ($announcement = mysqli_fetch_assoc($announcements_result)) {
                                ?>
                                <div class="announcement-card">
                                    <h3><?php echo htmlspecialchars($announcement['TITLE']); ?></h3>
                                    <p><?php echo nl2br(htmlspecialchars($announcement['CONTENT'])); ?></p>
                                    <small><?php echo date('F j, Y g:i A', strtotime($announcement['CREATED_AT'])); ?></small>
                                </div>
                                <?php
                            }
                        } else {
                            echo '<div class="no-announcements">No announcements available.</div>';
                        }
                        ?>
                    </div>
                </article>

                <!-- Quick Stats -->
                <div class="quick-stats">
                    <div class="stat-card">
                        <h3>Remaining Sessions</h3>
                        <div class="value">
                            <?php 
                                $sessionQuery = "SELECT REMAINING_SESSIONS FROM user WHERE USERNAME = '$username'";
                                $sessionResult = mysqli_query($con, $sessionQuery);
                                $sessions = mysqli_fetch_assoc($sessionResult);
                                echo $sessions['REMAINING_SESSIONS'] ?? '0';
                            ?> 
                        </div>
                    </div>
                    <div class="stat-card">
                        <h3>Current Points</h3>
                        <div class="value">
                            <?php 
                            $pointsQuery = "SELECT POINTS FROM user WHERE USERNAME = '$username'";
                            $pointsResult = mysqli_query($con, $pointsQuery);
                            $points = mysqli_fetch_assoc($pointsResult);
                            $currentPoints = $points['POINTS'] ?? '0';
                            echo $currentPoints . ' ' . ($currentPoints <= 1 ? 'Point' : 'Points');
                            ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Section - Top Students -->
            <aside class="dashboard-right">
                <div class="leaderboard-section">
                    <div class="leaderboard-header">
                        <div class="leaderboard-title">

                            <span>Leaderboard</span>
                            Top Students    
                        </div>
                    </div>
                    <div class="leaderboard-list">
                        <?php
                        // Fetch all students by points with consistent ordering
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
                                $isCurrentUser = ($user['USERNAME'] === $username);
                                $middleInitial = !empty($user['MIDNAME']) ? ' ' . substr($user['MIDNAME'], 0, 1) . '.' : '';
                                ?>
                                <div class="leaderboard-item">
                                    <div class="rank">
                                        <?php 
                                        if ($rank <= 15) {
                                            switch($rank) {
                                                case 1:
                                                    echo '<i>1st</i>';
                                                    break;
                                                case 2:
                                                    echo '<i>2nd</i>';
                                                    break;
                                                case 3:
                                                    echo '<i>3rd</i>';
                                                    break;
                                                case 4:
                                                    echo '<i>4th</i>';
                                                    break;
                                                case 5:
                                                    echo '<i>5th</i>';
                                                    break;
                                                case 6:
                                                    echo '<i>6th</i>';
                                                    break;
                                                case 7:
                                                    echo '<i>7th</i>';
                                                    break;
                                                case 8:
                                                    echo '<i>8th</i>';
                                                    break;
                                                case 9:
                                                    echo '<i>9th</i>';
                                                    break;
                                                case 10:
                                                    echo '<i>10th</i>';
                                                    break;
                                                case 11:
                                                    echo '<i>11th</i>';
                                                    break;
                                                case 12:
                                                    echo '<i>12th</i>';
                                                        break;case 13:
                                                     echo '<i>13th</i>';
                                                    break;
                                                case 14:
                                                    echo '<i>14th</i>';
                                                    break;
                                                case 15:
                                                    echo '<i>15th</i>';
                                                    break;
                                            }
                                        } else {
                                            echo $rank;
                                        }
                                        ?>
                                    </div>
                                    <div class="leaderboard-user">
                                        <img src="uploads/<?php echo $profile_pic; ?>" alt="Profile" class="leaderboard-avatar">
                                        <div class="user-info">
                                            <div class="user-name">
                                                <?php 
                                                if ($isCurrentUser) {
                                                    echo '<strong style="color: #0369a1;">YOU</strong>';
                                                } else {
                                                    echo htmlspecialchars($user['LASTNAME'] . ', ' . $user['FIRSTNAME'] . $middleInitial);
                                                }
                                                ?>
                                            </div>
                                            <div class="user-points">
                                                <?php echo htmlspecialchars($user['IDNO']); ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div>
                                        <?php 
                                        if ($isCurrentUser) {
                                            echo $user['POINTS'] . ' ' . ($user['POINTS'] <= 1 ? 'Point' : 'Points');
                                        } else {
                                            if ($user['POINTS'] >= 100) {
                                                echo '<i class=""></i><i class=""></i><i class=""></i>';
                                            } elseif ($user['POINTS'] >= 50) {
                                                echo '<i class=""></i><i class=""></i>';
                                            } else {
                                                echo '<i class=""></i>';
                                            }
                                        }
                                        ?>
                                    </div>
                                </div>
                                <?php
                                $rank++;
                            }
                        }
                        ?>
                    </div>
                </div>
            </aside>
        </div>
    </div>
</div>

<script>
function updateDateTime() {
    const options = { 
        weekday: 'long', 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric',
        timeZone: 'Asia/Manila'
    };
    
    const now = new Date();
    const philippineDate = now.toLocaleDateString('en-US', options);
    document.getElementById('current-date').textContent = philippineDate;
}

// Update immediately and then every second
updateDateTime();
setInterval(updateDateTime, 1000);
</script>

<script>
let notifications = [];
let lastNotificationCount = 0;
const notificationSound = new Audio('notification.mp3');

// Update the playNotificationSound function
function playNotificationSound() {
    notificationSound.currentTime = 0; // Reset sound to start
    notificationSound.play().catch(error => console.log('Error playing sound:', error));
}

async function fetchNotifications() {
    try {
        const response = await fetch('get_notifications.php');
        const data = await response.json();
        
        if (data.error) {
            console.error(data.error);
            return;
        }
        
        // Check if there are new notifications
        if (data.unread_count > lastNotificationCount) {
            playNotificationSound();
        }
        
        lastNotificationCount = data.unread_count;
        notifications = data.notifications;
        updateNotificationUI(data);
    } catch (error) {
        console.error('Error fetching notifications:', error);
    }
}

function getNotificationIcon(type) {
    switch (type) {
        case 'points':
            return 'fa-star';
        case 'session':
            return 'fa-clock';
        case 'reservation':
            return 'fa-calendar-check';
        case 'warning':
            return 'fa-exclamation-triangle';
        default:
            return 'fa-bell';
    }
}

function updateNotificationUI(data) {
    const listElement = document.querySelector('.notification-list');
    listElement.innerHTML = notifications.map(notif => `
        <li class="notification-item ${!notif.is_read ? 'unread' : ''}" 
            onclick="markAsRead(${notif.id})">
            <div class="notification-type">
                <i class="fas ${getNotificationIcon(notif.type)}"></i>
            </div>
            <div class="notification-content">
                <div class="notification-message">
                    ${notif.message}
                </div>
                <div class="notification-time">
                    ${formatTime(notif.created_at)}
                </div>
            </div>
        </li>
    `).join('');
    
    // Update count
    const countElement = document.querySelector('.notification-count');
    countElement.textContent = data.unread_count;
    countElement.style.display = data.unread_count > 0 ? 'block' : 'none';
}

function formatTime(timestamp) {
    const date = new Date(timestamp);
    const now = new Date();
    const diff = Math.floor((now - date) / 1000); // difference in seconds
    
    if (diff < 60) return 'Just now';
    if (diff < 3600) return `${Math.floor(diff / 60)} minutes ago`;
    if (diff < 86400) return `${Math.floor(diff / 3600)} hours ago`;
    return date.toLocaleDateString();
}

function toggleNotifications() {
    const dropdown = document.querySelector('.notification-dropdown');
    const currentDisplay = dropdown.style.display;
    dropdown.style.display = currentDisplay === 'block' ? 'none' : 'block';
}

function markAllRead() {
    fetch('mark_all_notifications_read.php', {
        method: 'POST'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            fetchNotifications();
        }
    })
    .catch(error => console.error('Error:', error));
}

// Add this function to your script section
async function markAsRead(notificationId) {
    try {
        const response = await fetch('mark_notification_read.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ id: notificationId })
        });
        
        if (response.ok) {
            fetchNotifications();
        }
    } catch (error) {
        console.error('Error marking notification as read:', error);
    }
}

fetchNotifications();
setInterval(fetchNotifications, 30000);

// Close dropdown when clicking outside
document.addEventListener('click', function(event) {
    const dropdown = document.querySelector('.notification-dropdown');
    const bell = document.querySelector('.notification-bell');
    if (!bell.contains(event.target) && !dropdown.contains(event.target)) {
        dropdown.style.display = 'none';
    }
});
</script>

</body>
</html>