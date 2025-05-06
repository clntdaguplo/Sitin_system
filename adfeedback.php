<?php
include("connector.php");

session_start();
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
    $user_name = htmlspecialchars(trim($row['FIRSTNAME'] . ' ' . $row['MIDNAME'] . ' ' . $row['LASTNAME']));
} else {
    $profile_pic = 'default.jpg';
    $user_name = 'Admin';
}
$stmt->close();

// Fetch feedback from users
$feedback_query = "SELECT f.FEEDBACK, f.LAB_ROOM, f.CREATED_AT, f.USER_ID, u.IDNO, u.USERNAME 
                   FROM feedback f 
                   INNER JOIN user u ON f.USER_ID = u.IDNO 
                   ORDER BY f.CREATED_AT DESC";

$feedback_result = $con->query($feedback_query);

// Debug: Check if there are any errors in the query
if (!$feedback_result) {
    echo "Error: " . $con->error;
}

// Debug: Print the raw data
echo "<pre style='display:none;'>";
while ($row = mysqli_fetch_assoc($feedback_result)) {
    print_r($row);
}
echo "</pre>";

// Reset the result pointer
mysqli_data_seek($feedback_result, 0);



?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
<link rel="stylesheet" href="https://www.w3schools.com/w3css/4/w3.css">
<title>Admin Feedback</title>
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
    min-height: 100vh;
    width: 100%;
}

/* Sidebar Styles */
.sidebar {
    width: 250px;
    background-color: rgba(42, 63, 95, 0.9);
    height: 100vh;
    padding: 20px;
    position: fixed;
    display: flex;
    flex-direction: column;
    align-items: center;
    box-shadow: 5px 0 10px rgba(0, 0, 0, 0.2);
    backdrop-filter: blur(10px);
    transform: translateX(0); /* Keep sidebar visible by default */
}

.sidebar img {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    border: 3px solid rgba(255, 255, 255, 0.2);
    margin-bottom: 15px;
}

.sidebar a {
    width: 100%;
    color: white;
    text-decoration: none;
    padding: 12px 15px;
    border-radius: 8px;
    margin: 5px 0;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    gap: 10px;
}

.sidebar a i {
    width: 20px;
    text-align: center;
}

.sidebar a:hover {
    background: rgba(255, 255, 255, 0.1);
    transform: translateX(5px);
}

.sidebar .logout-button {
    margin-top: auto;
    background: rgba(220, 53, 69, 0.1);
}

.sidebar .logout-button:hover {
    background: rgba(220, 53, 69, 0.2);
}

/* Content Area */
.content {
    flex-grow: 1;
    margin-left: 250px;
    padding: 30px;
    min-height: 100vh;
    background: #f0f2f5;
    transition: margin-left 0.3s ease-in-out;
    width: calc(100% - 250px);
}

.container {
    background: white;
    border-radius: 15px;
    padding: 25px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    width: 100%;
    max-width: 1400px;
    margin: 0 auto;
    height: calc(100vh - 60px);
}

h1 {
    color: #14569b;
    font-size: 1.8rem;
    font-weight: 600;
    margin-bottom: 25px;
    text-align: left;
}

.feedback-container {
    height: calc(100% - 100px);
    overflow-y: auto;
    border-radius: 12px;
    background: white;
}

/* Table Styles */
table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
}

thead {
    position: sticky;
    top: 0;
    z-index: 1;
}

th {
    background: #14569b;
    color: white;
    padding: 15px;
    text-align: left;
    font-weight: 500;
    border: none;
}

td {
    padding: 12px 15px;
    border-bottom: 1px solid #e2e8f0;
    background: transparent;
}

tbody tr:hover {
    background: #f8fafc;
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
    background: #14569b;
    border-radius: 4px;
}

::-webkit-scrollbar-thumb:hover {
    background: #0f4578;
}

/* Responsive Design */
@media (max-width: 768px) {
    .sidebar {
        transform: translateX(-100%);
        z-index: 1000;
    }
    
    .sidebar.active {
        transform: translateX(0);
    }
    
    .content {
        margin-left: 0;
        width: 100%;
        padding: 15px;
    }
    
    .container {
        height: calc(100vh - 30px);
    }
    
    .burger {
        display: block;
    }
}

.burger {
    display: none;
    position: fixed;
    top: 20px;
    left: 20px;
    z-index: 1001;
    cursor: pointer;
}

.burger div {
    width: 25px;
    height: 3px;
    background-color: #14569b;
    margin: 5px;
    transition: 0.3s;
}
</style>
</head>
<body>
<div class="burger" onclick="toggleSidebar()">
    <div></div>
    <div></div>
    <div></div>
</div>
<div class="sidebar">
    <img src="uploads/<?php echo htmlspecialchars($profile_pic); ?>" alt="Profile Picture">
    <center><div class="user-name" style="font-size: x-large; color: white;"><?php echo htmlspecialchars($user_name); ?></div></center>
    <a href="admindash.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
    <a href="adannouncement.php"><i class="fas fa-bullhorn"></i> Announcements</a>
    <a href="adsitin.php"><i class="fas fa-chair"></i> Current Sitin</a>
    <a href="addaily.php"><i class="fas fa-chair"></i> Daily Sitin Records</a>
    <a href="adviewsitin.php"><i class="fas fa-eye"></i> Generate Reports</a>
    <a href="adreservation.php"><i class="fas fa-chair"></i> Reservation</a>
   <!-- <a href="adlabreward.php"><i class="fas fa-chair"></i> Lab Reward</a>-->
   <a href="adlabresources.php"><i class="fas fa-book"></i> Lab Resources</a>
    <a href="adlabsched.php"><i class="fas fa-calendar"></i> Lab Schedule</a>
    <a href="adfeedback.php"><i class="fas fa-book-open"></i> Feedback Reports</a>
    <a href="admindash.php?logout=true" class="logout-button"><i class="fas fa-sign-out-alt"></i> Log Out</a>
</div>
<div class="content">
    <div class="container">
        <h1>Feedback from Users</h1>
        <div class="feedback-container">
        <table>
            <thead>
                <tr>
                    <th>ID No.</th>
                    <th>Lab Room</th>
                    <th style="margin-left: 100px;">Date</th>
                    <th>Feedback</th>
                    
                </tr>
            </thead>
            <tbody>
                <?php while ($feedback_row = mysqli_fetch_assoc($feedback_result)): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($feedback_row['USER_ID']); ?></td>
                        <td><?php echo htmlspecialchars($feedback_row['LAB_ROOM']); ?></td>
                        <td><?php echo htmlspecialchars($feedback_row['CREATED_AT']); ?></td>
                        <td><?php echo htmlspecialchars($feedback_row['FEEDBACK']); ?></td>
                        
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>
<script>
function toggleSidebar() {
    document.querySelector('.sidebar').classList.toggle('active');
    document.querySelector('.content').classList.toggle('sidebar-active');
}
</script>
</body>
</html>
