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

// Fetch sit-in history with feedback status (one feedback per sit-in session)
$history_query = "SELECT DISTINCT 
    lr.IDNO,
    lr.FULLNAME,
    lr.TIME_IN,
    lr.TIME_OUT,
    lr.LAB_ROOM,
    lr.PURPOSE,
    CASE WHEN f.FEEDBACK IS NOT NULL THEN 'Submitted' ELSE 'Not Submitted' END as FEEDBACK_STATUS
FROM login_records lr
LEFT JOIN feedback f ON lr.IDNO = f.USER_ID AND DATE(lr.TIME_IN) = DATE(f.CREATED_AT)
WHERE lr.IDNO = (SELECT IDNO FROM user WHERE USERNAME = '$username' LIMIT 1)
GROUP BY lr.IDNO, lr.FULLNAME, lr.TIME_IN, lr.TIME_OUT, lr.LAB_ROOM, lr.PURPOSE
ORDER BY lr.TIME_IN DESC";
$history_result = mysqli_query($con, $history_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>History</title>
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

.history-container {
    background: white;
    border-radius: 15px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    height: calc(100vh - 100px);
    width: 100%;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.history-title {
    background: rgb(26, 19, 46);
    color: white;
    padding: 25px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-radius: 15px 15px 0 0;
}

.history-title h1 {
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

table {
    width: 100%;
    border-collapse: collapse;
    margin: 0;
}

th {
    background: #f8fafc;
    color: #2d3748;
    font-weight: 600;
    padding: 15px;
    text-align: left;
    border-bottom: 2px solid #e2e8f0;
}

td {
    padding: 15px;
    border-bottom: 1px solid #e2e8f0;
    color: #4a5568;
}

.feedback-button {
    background: linear-gradient(45deg,rgb(150, 145, 79),rgb(47, 0, 177));
    color: white;
    padding: 6px 12px;
    border-radius: 6px;
    text-decoration: none;
    transition: all 0.3s ease;
    display: inline-block;
    font-size: 0.9rem;
}

.feedback-button:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(47, 0, 177, 0.2);
}

.feedback-disabled {
    color: #a0aec0;
    font-size: 0.9rem;
}

tbody tr:hover {
    background: #f8fafc;
}

@media (max-width: 768px) {
    .content {
        padding: 15px;
    }
    
    .nav-right {
        display: none;
    }
    
    .nav-right.active {
        display: flex;
        flex-direction: column;
        position: absolute;
        top: 100%;
        right: 0;
        background: linear-gradient(45deg,rgb(150, 145, 79),rgb(47, 0, 177));
        padding: 15px;
        border-radius: 0 0 15px 15px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    }
}
</style>
</head>
<body>
<div class="top-nav">
    <div class="nav-left">
        <img src="uploads/<?php echo $profile_pic; ?>" alt="Profile Picture" onerror="this.src='assets/default.jpg';">
        <div class="user-name"><?php echo $user_name; ?></div>
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
<div class="history-container">
<div class="history-title">
    <h1>
        <i class="fas fa-history"></i>
        Sit-in History
    </h1>
    <a>
    </a>
</div>
<table>
<thead>
<tr>
<th>ID Number</th>
<th>Name</th>
<th>Purpose</th>
<th>Room</th>
<th>Date Sit-in</th>
<th>Date Logout</th>
<th>Action</th>
</tr>
</thead>
<tbody>
<?php
if (mysqli_num_rows($history_result) > 0) {
while ($history_row = mysqli_fetch_assoc($history_result)) { ?>
<tr>
<td><?php echo htmlspecialchars($history_row['IDNO']); ?></td>
<td><?php echo htmlspecialchars($history_row['FULLNAME']); ?></td>
<td><?php echo htmlspecialchars($history_row['PURPOSE']); ?></td>
<td><?php echo htmlspecialchars($history_row['LAB_ROOM']); ?></td>
<td><?php echo htmlspecialchars($history_row['TIME_IN']); ?></td>
<td><?php echo htmlspecialchars($history_row['TIME_OUT']); ?></td>
<td>
<?php if ($history_row['FEEDBACK_STATUS'] === 'Not Submitted') { ?>
<a href="feedback.php?id=<?php echo htmlspecialchars($history_row['IDNO']); ?>&time_in=<?php echo urlencode($history_row['TIME_IN']); ?>&lab_room=<?php echo urlencode($history_row['LAB_ROOM']); ?>" class="feedback-button">Feedback</a>
<?php } else { ?>
<span class="feedback-disabled">Already Submitted</span>
<?php } ?>
</td>
</tr>
<?php }
} else { ?>
<tr>
<td colspan="7" style="text-align: center;">No History Yet!</td>
</tr>
<?php } ?>
</tbody>
</table>
</div>
</div>
</body>
</html>