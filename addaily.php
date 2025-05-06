<?php
session_start();
include("connector.php");

// Set timezone to Asia/Manila
date_default_timezone_set('Asia/Manila');

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
<title>Daily Sit-in Reports</title>
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
}

.container {
    background: white;
    border-radius: 15px;
    padding: 25px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    height: 90vh;
    max-width: auto;
    margin: 0 auto;
}

.header h1 {
    color: #14569b;
    font-size: 1.8rem;
    font-weight: 600;
    margin-bottom: 25px;
}

/* Search Container */
.search-container {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    gap: 15px;
}

.search-box {
    display: flex;
    gap: 10px;
    align-items: center;
}

.search-box input {
    padding: 10px 15px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    width: 300px;
    transition: all 0.2s;
}

.search-box input:focus {
    border-color: #14569b;
    outline: none;
    box-shadow: 0 0 0 3px rgba(20, 86, 155, 0.1);
}

.search-box button {
    background: #14569b;
    color: white;
    padding: 10px 20px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s;
}

.search-box button:hover {
    background: #0f4578;
    transform: translateY(-1px);
}

/* Table Styles */
.table-container {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    margin-top: 20px;
    height: calc(100vh - 250px);
    overflow-y: auto;
}

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

/* Date Filter */
.date-filter {
    position: relative;
    display: inline-block;
}

.date-filter input {
    padding: 10px 15px;
    padding-left: 40px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    width: 180px;
    cursor: pointer;
}

.date-filter i {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: #718096;
}

/* Responsive Design */
@media (max-width: 1200px) {
    .container {
        margin: 0 15px;
    }
}

@media (max-width: 768px) {
    .sidebar {
        transform: translateX(-100%);
    }
    
    .sidebar.active {
        transform: translateX(0);
    }
    
    .content {
        margin-left: 0;
    }
    
    .search-container {
        flex-direction: column;
    }
    
    .search-box {
        width: 100%;
    }
    
    .search-box input {
        width: 100%;
    }
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
    <a href="addaily.php"><i class="fas fa-calendar-day"></i> Daily Reports</a>
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
        <div class="header">
            <h1>Daily Sit-in Reports</h1>
        </div>
        <div class="search-container">
        <div class="search-box">
    </select>

    <!-- Search Input -->
    <input type="text" id="searchInput" placeholder="Search by ID, Name, or Purpose...">
    <button><i class="fas fa-search"></i></button>
</div>
        </div>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>ID Number</th>
                        <th>Full Name</th>
                        <th>Purpose</th>
                        <th>Room</th>
                        <th>Time In</th>
                        <th>Time Out</th>
                    </tr>
                </thead>
                <tbody id="sitinTable">
                    <?php
                    $daily_query = "SELECT 
                        lr.IDNO, 
                        lr.FULLNAME, 
                        lr.PURPOSE, 
                        lr.LAB_ROOM, 
                        TIME_FORMAT(lr.TIME_IN, '%h:%i %p') as TIME_IN_ONLY,
                        TIME_FORMAT(lr.TIME_OUT, '%h:%i %p') as TIME_OUT_ONLY
                    FROM login_records lr
                    WHERE DATE(lr.TIME_IN) = ?
                    ORDER BY lr.TIME_IN DESC";
                    
                    $stmt = $con->prepare($daily_query);
                    $stmt->bind_param("s", $selected_date);
                    $stmt->execute();
                    $daily_result = $stmt->get_result();
                    
                    if (mysqli_num_rows($daily_result) > 0) {
                        while ($daily_row = mysqli_fetch_assoc($daily_result)) { ?>
                            <tr>
                                <td><?php echo htmlspecialchars($daily_row['IDNO']); ?></td>
                                <td><?php echo htmlspecialchars($daily_row['FULLNAME']); ?></td>
                                <td class="purpose-column"><?php echo htmlspecialchars($daily_row['PURPOSE']); ?></td>
                                <td class="room-column"><?php echo htmlspecialchars($daily_row['LAB_ROOM']); ?></td>
                                <td><?php echo htmlspecialchars($daily_row['TIME_IN_ONLY']); ?></td>
                                <td><?php echo htmlspecialchars($daily_row['TIME_OUT_ONLY']); ?></td>
                            </tr>
                        <?php }
                    } else { ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 20px; color: #666;">
                                <i class="fas fa-info-circle"></i> 
                                No sit-in records available for this date.
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.4.0/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script>
function filterResults() {
    let date = document.getElementById("dateFilter").value;
    let room = document.getElementById("roomFilter").value;
    let purpose = document.getElementById("purposeFilter").value;
    
    let queryParams = new URLSearchParams(window.location.search);
    if (date) queryParams.set("date", date);
    else queryParams.delete("date");

    if (room) queryParams.set("room", room);
    else queryParams.delete("room");

    if (purpose) queryParams.set("purpose", purpose);
    else queryParams.delete("purpose");

    window.location.href = "addaily.php?" + queryParams.toString();
}
function toggleSidebar() {
    document.querySelector('.sidebar').classList.toggle('active');
    document.querySelector('.content').classList.toggle('sidebar-active');
}

function filterByDate(date) {
    window.location.href = 'addaily.php?date=' + date;
}

document.getElementById('searchInput').addEventListener('keyup', function() {
    let searchValue = this.value.toLowerCase();
    document.querySelectorAll('#sitinTable tr').forEach(row => {
        row.style.display = row.innerText.toLowerCase().includes(searchValue) ? '' : 'none';
    });
});
</script>
</body>
</html>