<?php
session_start();
include("connector.php");

// Check if the user is logged in
if (!isset($_SESSION['Username'])) {
    header("Location: login.php");
    exit();
}

// Fetch user information
$username = $_SESSION['Username'];
$query = "SELECT IDNO, LASTNAME, FIRSTNAME, MIDNAME, COURSE, YEARLEVEL, EMAIL, PROFILE_PIC FROM user WHERE USERNAME = '$username'";
$result = mysqli_query($con, $query);

if ($result && mysqli_num_rows($result) > 0) {
    $user_data = mysqli_fetch_assoc($result);
    $profile_pic = !empty($user_data['PROFILE_PIC']) ? $user_data['PROFILE_PIC'] : 'default.jpg';
    $user_name = htmlspecialchars($user_data['LASTNAME'] . ' ' . substr($user_data['FIRSTNAME'], 0, 1) . '.');
} else {
    echo "User data not found.";
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <title>Profile</title>
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

        .profile-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            height: calc(100vh - 100px);
            width: 100%;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .profile-header {
            background: rgb(26, 19, 46);
            color: white;
            padding: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-radius: 15px 15px 0 0;
        }

        .profile-header h1 {
            font-size: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .profile-content {
            padding: 30px;
            background: white;
            overflow-y: auto;
        }

        .profile-content img {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            border: 3px solid rgb(47, 0, 177);
            margin-bottom: 20px;
            object-fit: cover;
        }

        table {
            width: 100%;
            max-width: 800px;
            margin: 20px auto;
            border-collapse: collapse;
        }

        table td {
            padding: 15px;
            border-bottom: 1px solid #e2e8f0;
        }

        table td:first-child {
            font-weight: 600;
            color: rgb(47, 0, 177);
            width: 30%;
        }

        button {
            background: rgb(0, 117, 63);
            color: white;
            padding: 12px 25px;
            border-radius: 8px;
            border: none;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        @media (max-width: 768px) {
            .content {
                padding: 20px;
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
    <div class="profile-container">
        <div class="profile-header">
            <h1>
                <i class="fas fa-user"></i>
                Profile Information
            </h1>
        </div>
        
        <div class="profile-content">
            <center>
                <img src="uploads/<?php echo htmlspecialchars($user_data['PROFILE_PIC']); ?>" alt="Profile Picture">
                <h2>Student Information</h2>
            </center>
            
            <table>
                <tr>
                    <td>ID Number:</td>
                    <td><?php echo htmlspecialchars($user_data['IDNO']); ?></td>
                </tr>
                <tr>
                    <td>Last Name:</td>
                    <td><?php echo htmlspecialchars($user_data['LASTNAME']); ?></td>
                </tr>
                <tr>
                    <td>First Name:</td>
                    <td><?php echo htmlspecialchars($user_data['FIRSTNAME']); ?></td>
                </tr>
                <tr>
                    <td>Middle Name:</td>
                    <td><?php echo htmlspecialchars($user_data['MIDNAME']); ?></td>
                </tr>
                <tr>
                    <td>Course:</td>
                    <td><?php echo htmlspecialchars($user_data['COURSE']); ?></td>
                </tr>
                <tr>
                    <td>Year Level:</td>
                    <td><?php echo htmlspecialchars($user_data['YEARLEVEL']); ?></td>
                </tr>
                <tr>
                    <td>Email:</td>
                    <td><?php echo htmlspecialchars($user_data['EMAIL']); ?></td>
                </tr>
            </table>
            
            <center>
                <button type="button" onclick="window.location.href='edit.php'">
                    <i class="fas fa-edit"></i> Edit Profile
                </button>
            </center>
        </div>
    </div>
</div>
</body>
</html>