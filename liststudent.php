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

if ($result && $result->num_rows > 0) {
$row = $result->fetch_assoc();
$profile_pic = !empty($row['PROFILE_PIC']) ? htmlspecialchars($row['PROFILE_PIC']) : 'default.jpg';
$user_name = htmlspecialchars($row['FIRSTNAME'] . ' ' . $row['MIDNAME'] . ' ' . $row['LASTNAME']);
} else {
$profile_pic = 'default.jpg';
$user_name = 'Admin';
}

$query = "SELECT * FROM user WHERE POINTS > 0 OR REMAINING_SESSIONS > 0 ORDER BY POINTS DESC, LASTNAME ASC";

// Fetch students
$students_query = "SELECT u.*, f.FEEDBACK 
                  FROM user u 
                  LEFT JOIN feedback f ON u.IDNO = f.USER_ID 
                  WHERE u.IDNO IS NOT NULL 
                  AND (f.FEEDBACK IS NULL OR f.FEEDBACK != 'No feedback submitted yet.')
                  ORDER BY u.LASTNAME ASC";
$students_result = $con->query($students_query);

// Handle reset sessions
if (isset($_POST['reset_sessions'])) {
    // Start transaction
    $con->begin_transaction();
    
    try {
        // First clear all active sessions by setting TIME_OUT to current time
        $clear_sessions = "UPDATE login_records SET TIME_OUT = NOW() WHERE TIME_OUT IS NULL";
        if (!$con->query($clear_sessions)) {
            throw new Exception("Error clearing active sessions");
        }
        
        // Then reset all student sessions to 30
        $reset_query = "UPDATE user SET REMAINING_SESSIONS = 30 WHERE IDNO IS NOT NULL";
        if (!$con->query($reset_query)) {
            throw new Exception("Error resetting sessions");
        }
        
        // Commit the transaction
        $con->commit();
        
        // Redirect with success message
        header("Location: liststudent.php?reset_success=1");
        exit();
    } catch (Exception $e) {
        // Rollback on error
        $con->rollback();
        header("Location: liststudent.php?reset_error=" . urlencode($e->getMessage()));
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<title>List of Students</title>
<style>
/* Base font styles */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: Arial, sans-serif;
}

/* Alert Messages */
.alert {
    padding: 12px 20px;
    border-radius: 8px;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 14px;
    animation: fadeIn 0.3s ease-in-out;
}

.alert-success {
    background-color: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.alert-danger {
    background-color: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.alert i {
    font-size: 16px;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

html, body {
    background: linear-gradient(45deg, #ff4757, #ffae42);
    min-height: 100vh;
    width: 100%;
}

/* Top Navigation Bar Styles */
.topnav {
    width: 100%;
    background: linear-gradient(45deg,rgb(150, 145, 79),rgb(47, 0, 177));
    padding: 15px 30px;
    position: fixed;
    top: 0;
    left: 0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
    backdrop-filter: blur(10px);
    z-index: 1000;
}

.user-profile {
    display: flex;
    align-items: center;
    gap: 15px;
}

.user-profile img {
    width: 70px;
    height: 70px;
    border-radius: 50%;
    border: 2px solid rgba(255, 255, 255, 0.2);
}

.user-name {
    color: white;
    font-size: 14px;
    font-weight: normal;
}

.nav-links {
    display: flex;
    gap: 20px;
    align-items: center;
}

.nav-links a {
    color: white;
    text-decoration: none;
    padding: 8px 15px;
    border-radius: 6px;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    gap: 8px;
}

.nav-links a i {
    width: 20px;
    text-align: center;
}

.nav-links a:hover {
    background: rgba(255, 255, 255, 0.1);
    transform: translateY(-2px);
}

.nav-links .logout-button {
    background: rgba(247, 162, 5, 0.88);
}

.nav-links .logout-button:hover {
    background: rgba(255, 251, 0, 0.93);
}

/* Content Area */
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
    height: calc(100vh - 60px);
    max-width: 1400px;
    margin: 0 auto;
    overflow: hidden;
}

.header {
    margin-bottom: 20px;
}

.header h1 {
    color: #14569b;
    font-size: 16px;
    font-weight: normal;
    margin-bottom: 15px;
}

.controls-container {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    margin-bottom: 20px;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 12px;
}

.search-box {
    display: flex;
    align-items: center;
    gap: 10px;
    flex: 1;
    max-width: 400px;
    position: relative;
}

.search-box input {
    width: 100%;
    padding: 8px 12px;
    padding-right: 45px;
    border: 1px solid #e2e8f0;
    border-radius: 4px;
    transition: all 0.2s;
    font-size: 14px;
    font-weight: normal;
}

.search-box button {
    position: absolute;
    right: 5px;
    padding: 8px 16px;
    background: linear-gradient(45deg,rgb(150, 145, 79),rgb(47, 0, 177));
    color: white;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
}

.search-box button:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
}

.search-box input:focus {
    border-color: #14569b;
    outline: none;
    box-shadow: 0 0 0 3px rgba(20, 86, 155, 0.1);
}

.reset-button {
    background:  rgb(219, 99, 0);
    color: white;
    padding: 10px 20px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 8px;
}

.reset-button:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
}

.table-container {
    height: calc(100% - 180px);
    overflow-y: auto;
    border-radius: 12px;
    background: white;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
}

table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    font-size: 14px;
    font-weight: normal;
}

thead {
    position: sticky;
    top: 0;
    z-index: 2;
}

th {
    background: rgb(26, 19, 46);
    color: white;
    padding: 12px;
    text-align: left;
    font-size: 14px;
    font-weight: normal;
}

td {
    padding: 12px;
    border-bottom: 1px solid #e2e8f0;
    font-size: 14px;
    font-weight: normal;
}

tbody tr:hover {
    background: #f8fafc;
}

.action-buttons {
    display: flex;
    gap: 8px;
    justify-content: center;
}

.reset-btn, .delete-btn {
    padding: 6px 12px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s;
}

.reset-btn {
    background: linear-gradient(45deg, #4CAF50, #45a049);
    color: white;
}

.delete-btn {
    background:  rgb(219, 99, 0);
    color: white;
}

.reset-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 5px rgba(76, 175, 80, 0.3);
}


.delete-btn:hover {
    background:rgb(134, 5, 15);
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
    background: linear-gradient(45deg,rgb(150, 145, 79),rgb(47, 0, 177));
    border-radius: 4px;
}

::-webkit-scrollbar-thumb:hover {
    background: linear-gradient(45deg,rgb(47, 0, 177),rgb(150, 145, 79));
}

/* Responsive Design */
@media (max-width: 768px) {
    .topnav {
        padding: 10px 15px;
    }
    
    .nav-links {
        display: none;
        position: absolute;
        top: 70px;
        left: 0;
        width: 100%;
        background: rgba(42, 63, 95, 0.95);
        flex-direction: column;
        padding: 20px;
    }
    
    .nav-links.active {
        display: flex;
    }
    
    .burger {
        display: block;
        cursor: pointer;
    }
    
    .burger div {
        width: 25px;
        height: 3px;
        background-color: white;
        margin: 5px 0;
        transition: all 0.3s;
    }
    
    .content {
        margin-top: 70px;
        padding: 15px;
    }
    
    table {
        font-size: 13px;
    }
    
    th, td {
        font-size: 13px;
        padding: 8px;
    }
    
    .nav-right a {
        font-size: 13px;
    }
    
    .user-name {
        font-size: 13px;
    }
    
    .status-badge {
        font-size: 11px;
    }
    
    .pagination {
        font-size: 13px;
    }
    
    .modal-content {
        font-size: 13px;
    }
}
</style>
</head>
<body>
<div class="topnav">
    <div class="user-profile">
        <img src="uploads/<?php echo htmlspecialchars($profile_pic); ?>" alt="Profile Picture">
        <div class="user-name"><?php echo htmlspecialchars($user_name); ?></div>
    </div>
    <div class="burger" onclick="toggleNav()">
        <div></div>
        <div></div>
        <div></div>
    </div>
    <div class="nav-links">
        <a href="admindash.php"></i> Dashboard</a>
        <a href="adannouncement.php"></i> Announcements</a>
        <a href="liststudent.php"></i> STUDENTS</a>
        <a href="adsitin.php"></i> Current Sitin</a>
        
        <a href="adlabresources.php"></i> Lab Resources</a>
        <a href="adlabsched.php"></i> Lab Schedule</a>
        <a href="adreservation.php"></i> Reservations</a>
        <a href="adfeedback.php"></i> Feedback</a>
        <a href="admindash.php?logout=true" class="logout-button"> Log Out</a>
    </div>
</div>
<div class="content">
<div class="container">
<div class="header">
<h1>List of Students</h1>
</div>
<div class="controls-container">
<?php
// Display success/error messages
if (isset($_GET['reset_success'])) {
    echo '<div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            All student sessions have been reset to 30!
          </div>';
} elseif (isset($_GET['reset_error'])) {
    echo '<div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i>
            Error: ' . htmlspecialchars($_GET['reset_error']) . '
          </div>';
}
?>
<div class="search-box">
<input type="text" id="searchInput" placeholder="Search by ID, Name, or Course...">
<button type="button">
<i class="fas fa-search"></i>
</button>
</div>
<form method="POST" style="margin: 0;">
<button type="submit" name="reset_sessions" class="reset-button">
<i class="fas fa-sync-alt"></i> Reset All Sessions
</button>
</form>
</div>
<div class="table-container">
<table>
<thead>
<tr>
<th>ID Number</th>
<th>Last Name</th>
<th>First Name</th>
<th>Middle Name</th>
<th>Course</th>
<th>Year Level</th>
<th>Username</th>
<th>Email</th>
<th>Points</th>
<th>Remaining Sessions</th>
<th>Actions</th>
</tr>
</thead>
<tbody>
<?php
$query = "SELECT * FROM user ORDER BY LASTNAME ASC";
$result = mysqli_query($con, $query);
while ($row = mysqli_fetch_assoc($result)) {
    // Determine points class based on value
    $points_class = '';
    if ($row['POINTS'] >= 2) {
        $points_class = 'points-high';
    } elseif ($row['POINTS'] == 1) {
        $points_class = 'points-medium';
    } else {
        $points_class = 'points-low';
    }

    echo "<tr>";
    echo "<td>" . htmlspecialchars($row['IDNO']) . "</td>";
    echo "<td>" . htmlspecialchars($row['LASTNAME']) . "</td>";
    echo "<td>" . htmlspecialchars($row['FIRSTNAME']) . "</td>";
    echo "<td>" . htmlspecialchars($row['MIDNAME']) . "</td>";
    echo "<td>" . htmlspecialchars($row['COURSE']) . "</td>";
    echo "<td class='text-center'>" . htmlspecialchars($row['YEARLEVEL']) . "</td>";
    echo "<td>" . htmlspecialchars($row['USERNAME']) . "</td>";
    echo "<td>" . htmlspecialchars($row['EMAIL']) . "</td>";
    echo "<td class='points-column " . $points_class . "'>" . htmlspecialchars($row['POINTS']) . "</td>";
    echo "<td class='text-center'>" . htmlspecialchars($row['REMAINING_SESSIONS']) . "</td>";
    echo "<td class='action-buttons'>";
    echo "<button onclick='resetSessions(" . $row['IDNO'] . ")' class='reset-btn' title='Reset Sessions'>Reset</button>";
    echo "<button onclick='deleteStudent(" . $row['IDNO'] . ")' class='delete-btn' title='Delete Student'>Delete</button>";
    echo "</td>";
    echo "</tr>";
}
?>
</tbody>
</table>
</div>
</div>
</div>
<script>
document.getElementById('searchInput').addEventListener('keyup', function() {
let searchValue = this.value.toLowerCase();
document.querySelectorAll('tbody tr').forEach(row => {
let text = Array.from(row.cells).map(cell => cell.textContent.toLowerCase()).join(' ');
row.style.display = text.includes(searchValue) ? '' : 'none';
});
});

function toggleNav() {
    document.querySelector('.nav-links').classList.toggle('active');
}

function resetSessions(idno) {
if (confirm('Are you sure you want to reset this student\'s remaining sessions to 30?')) {
window.location.href = 'reset_sessions.php?id=' + idno;
}
}

function deleteStudent(idno) {
if (confirm('Are you sure you want to delete this student? This action cannot be undone.')) {
window.location.href = 'delete_student.php?id=' + idno;
}
}
</script>
</body>
</html>