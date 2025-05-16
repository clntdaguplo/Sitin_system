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
$query = "SELECT PROFILE_PIC FROM user WHERE USERNAME = '$username'";
$result = mysqli_query($con, $query);

if ($result && mysqli_num_rows($result) > 0) {
    $row = mysqli_fetch_assoc($result);
    $profile_pic = !empty($row['PROFILE_PIC']) ? $row['PROFILE_PIC'] : 'default.jpg';
} else {
    // Default profile picture if not found
    $profile_pic = 'default.jpg';
}

// Fetch the user's name from the database
$query = "SELECT FIRSTNAME, MIDNAME, LASTNAME FROM user WHERE USERNAME = '$username'";
$result = mysqli_query($con, $query);

if ($result && mysqli_num_rows($result) > 0) {
    $row = mysqli_fetch_assoc($result);
    $user_name = htmlspecialchars($row['LASTNAME'] . ' ' . substr($row['FIRSTNAME'], 0, 1) . '.');  
} else {
    $user_name = 'User';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
<title>Lab Rules & Regulations</title>
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

.rules-container {
    background: white;
    border-radius: 15px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    height: calc(100vh - 100px);
    width: 100%;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.rules-header {
    background: rgb(26, 19, 46);
    color: white;
    padding: 25px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-radius: 15px 15px 0 0;
}

.rules-header h1 {
    font-size: 24px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.rules-content {
    padding: 30px;
    overflow-y: auto;
    background: white;
}

.rules-content h2 {
    color: rgb(47, 0, 177);
    margin-bottom: 20px;
    font-size: 1.5rem;
}

.rules-content h5 {
    color: rgb(47, 0, 177);
    font-size: 1.2rem;
    margin-bottom: 10px;
}

.rules-content h6 {
    color: rgb(150, 145, 79);
    font-size: 1.1rem;
    margin-bottom: 20px;
}

.rules-content p {
    margin-bottom: 15px;
    color: #4a5568;
    line-height: 1.8;
}

.rules-content p strong {
    color: rgb(47, 0, 177);
    display: inline-block;
    margin-right: 10px;
}

.rules-content ol, .rules-content ul {
    margin-left: 20px;
    margin-bottom: 20px;
}

.rules-content li {
    color: #4a5568;
    margin-bottom: 10px;
    line-height: 1.6;
}

.rules-content ul li strong {
    color: rgb(47, 0, 177);
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

/* Scrollbar Styling */
::-webkit-scrollbar {
    width: 8px;
}

::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}

::-webkit-scrollbar-thumb {
    background: rgb(47, 0, 177);
    border-radius: 4px;
}

::-webkit-scrollbar-thumb:hover {
    background: rgb(150, 145, 79);
}

@media (max-width: 768px) {
    .content {
        margin-top: 80px;
        padding: 20px;
        width: 100%;
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
        <a href="viewAnnouncement.php"> Announcement</a>
        <a href="profile.php"> Edit Profile</a>
        <a href="labRules&Regulations.php"> Lab Rules</a>
        <a href="labschedule.php"> Lab Schedules</a>
        
        <a href="reservation.php"> Reservation</a>
        <a href="history.php"> History</a>

        <a href="login.php" class="logout-button"> Log Out</a>
    </div>
</div>

<div class="content">
    <div class="rules-container">
        <div class="rules-header">
            <h1>
                <i class="fas fa-gavel"></i>
                Rules & Regulations
            </h1>
            
        </div>
        <div class="rules-content">
            <center><h5 class="text-center">University of Cebu</h5></center>
                                <center><h6 class="text-center">COLLEGE OF INFORMATION & COMPUTER STUDIES</h6></center>
                                <center><p><strong>LABORATORY RULES AND REGULATIONS</strong></p></center>
                               
                               
                                <ol>
                                    <li>Maintain silence, proper decorum, and discipline inside the laboratory. Mobile phones, walkmans, and other personal equipment must be switched off.</li>
                                    <li>Games are not allowed inside the lab. This includes computer-related games, card games, and other games that may disturb the operation of the lab.</li>
                                    <li>Surfing the Internet is allowed only with the permission of the instructor. Downloading and installing software are strictly prohibited.</li>
                                    <li>Getting access to other websites not related to the course (especially pornographic and illicit sites) is strictly prohibited.</li>
                                    <li>Deleting computer files and changing the set-up of the computer is a major offense.</li>
                                    <li>Observe computer time usage carefully. A fifteen-minute allowance is given for each use. Otherwise, the unit will be given to those who wish to "sit-in".</li>
                                    <li>Observe proper decorum while inside the laboratory.
                                        <ul>
                                            <li>Do not get inside the lab unless the instructor is present.</li>
                                            <li>All bags, knapsacks, and the like must be deposited at the counter.</li>
                                            <li>Follow the seating arrangement of your instructor.</li>
                                            <li>At the end of class, all software programs must be closed.</li>
                                            <li>Return all chairs to their proper places after use.</li>
                                        </ul>
                                    </li>
                                    <li>Chewing gum, eating, drinking, smoking, and other forms of vandalism are prohibited inside the lab.</li>
                                    <li>Anyone causing a continual disturbance will be asked to leave the lab. Acts or gestures offensive to the community, including public display of physical intimacy, are not tolerated.</li>
                                    <li>Persons exhibiting hostile or threatening behavior such as yelling, swearing, or disregarding requests made by lab personnel will be asked to leave the lab.</li>
                                    <li>For serious offenses, the lab personnel may call the Civil Security Office (CSU) for assistance.</li>
                                    <li>Any technical problem or difficulty must be addressed to the laboratory supervisor, student assistant, or instructor immediately.</li>
                                </ol>
                                

                                
                                <center><p><strong>DISCIPLINARY ACTION</strong></p></center>
                                <ul>
                                    <li><strong>First Offense:</strong> The Head, Dean, or OIC recommends suspension from classes to the Guidance Center.</li>
                                    <li><strong>Second and Subsequent Offenses:</strong> A recommendation for a heavier sanction will be endorsed to the Guidance Center.</li>
                                </ul>
                            </div>
    </div>
</div>
</body>
</html>