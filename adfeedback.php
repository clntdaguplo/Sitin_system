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
$feedback_query = "SELECT f.FEEDBACK, f.LAB_ROOM, f.CREATED_AT, f.USER_ID, u.IDNO, u.USERNAME, 
                   CONCAT(u.FIRSTNAME, ' ', u.MIDNAME, ' ', u.LASTNAME) as FULLNAME 
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

/* Remove old sidebar styles */
.sidebar {
    display: none;
}

.container {
    background: white;
    border-radius: 15px;
    padding: 25px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    width: 100%;
    max-width: 100%;
    margin: 0;
    height: calc(100vh - 60px);
}

h1 {
    color: rgb(26, 19, 46);
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
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    width: 100%;
}

/* Table Styles */
table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    table-layout: fixed;
}

thead {
    position: sticky;
    top: 0;
    z-index: 1;
}

th {
    background: rgb(26, 19, 46);
    color: white;
    padding: 15px;
    text-align: left;
    font-weight: 500;
    border: none;
}

th:nth-child(1) { width: 10%; }  /* ID No. */
th:nth-child(2) { width: 20%; }  /* Name */
th:nth-child(3) { width: 40%; }  /* Feedback */
th:nth-child(4) { width: 15%; }  /* Lab Room */
th:nth-child(5) { width: 15%; }  /* Date & Time */

td {
    padding: 15px;
    text-align: left;
    word-wrap: break-word;
}

tbody tr:hover {
    background: #f8fafc;
    transform: translateX(5px);
    transition: all 0.3s ease;
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
        height: calc(100vh - 30px);
    }
}

.button-container {
    display: flex;
    gap: 15px;
    margin-bottom: 20px;
}

.print-button {
    background: #45a049;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 5px;
    cursor: pointer;
    font-size: 16px;
    display: flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
    transition: all 0.3s ease;
}

.print-button:hover {
    background: #45a049;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(20, 86, 155, 0.2);
}

.header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
}

.header h1 {
    margin: 0;
    color: rgb(26, 19, 46);
}

/* Add hover effect to table rows */
tbody tr {
    transition: all 0.3s ease;
}

tbody tr:hover {
    background: rgba(150, 145, 79, 0.1);
    transform: translateX(5px);
}

/* Update notification badge */
.notification-badge {
    background: #dc3545;
    color: white;
    border-radius: 50%;
    padding: 1px 5px;
    font-size: 12px;
    position: absolute;
    top: 15px;
    right: 10px;
    min-width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 2px 4px rgba(239, 68, 68, 0.2);
}

/* Add animation for table rows */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

tbody tr {
    animation: fadeIn 0.3s ease-out;
    animation-fill-mode: both;
}

tbody tr:nth-child(1) { animation-delay: 0.1s; }
tbody tr:nth-child(2) { animation-delay: 0.2s; }
tbody tr:nth-child(3) { animation-delay: 0.3s; }
tbody tr:nth-child(4) { animation-delay: 0.4s; }
tbody tr:nth-child(5) { animation-delay: 0.5s; }

/* Add print styles */
@media print {
    .no-print {
        display: none !important;
    }
    body {
        background: white !important;
    }
    .content {
        margin: 0 !important;
        padding: 0 !important;
    }
    .container {
        box-shadow: none !important;
        height: auto !important;
    }
    .feedback-container {
        height: auto !important;
        overflow: visible !important;
    }
    table {
        width: 100% !important;
    }
    th {
        background: rgb(26, 19, 46) !important;
        color: white !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
    tbody tr:hover {
        background: none !important;
        transform: none !important;
    }
}

/* Update button container styles */
.button-container {
    display: flex;
    gap: 15px;
    margin-bottom: 20px;
}

.print-button {
    background: #45a049;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 5px;
    cursor: pointer;
    font-size: 16px;
    display: flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
    transition: all 0.3s ease;
}

.print-button:hover {
    background: #45a049;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(20, 86, 155, 0.2);
}
</style>
</head>
<body>
    <div class="top-nav no-print">
        <div class="nav-left">
            <img src="uploads/<?php echo htmlspecialchars($profile_pic); ?>" alt="Profile Picture" onerror="this.src='assets/default.png';">
            <div class="user-name"><?php echo htmlspecialchars($user_name); ?></div>
        </div>
        <div class="nav-right">
            <a href="admindash.php"> Dashboard</a>
            <a href="adannouncement.php"> Announcements</a>
            <a href="liststudent.php"> Students</a>
            <a href="adsitin.php"> Current Sitin</a>
            
            
            <a href="adlabresources.php"> Lab Resources</a>
            <a href="adlabsched.php"> Lab Schedule</a>
            <a href="adreservation.php"> Reservations</a>
            <a href="adfeedback.php"> FEEDBACK</a>
            <a href="admindash.php?logout=true" class="logout-button"> Log Out</a>
        </div>
    </div>

    <div class="content">
        <div class="container">
            <div class="header">
                <h1>Student Feedbacks</h1>
                <div class="button-container">
                    <button onclick="printFeedback()" class="print-button">
                        <i class="fas fa-print"></i> Print Feedbacks
                    </button>
                </div>
            </div>
            <div class="feedback-container">
            <table>
                <thead>
                    <tr>
                        <th>ID No.</th>
                        <th>Name</th>
                        <th>Feedback</th>
                        <th>Lab Room</th>
                        <th>Date & Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($feedback_result && mysqli_num_rows($feedback_result) > 0) {
                        while ($row = mysqli_fetch_assoc($feedback_result)) {
                            echo "<tr>";
                            echo "<td>" . htmlspecialchars($row['IDNO']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['FULLNAME']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['FEEDBACK']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['LAB_ROOM']) . "</td>";
                            echo "<td>" . date('M d, Y h:i A', strtotime($row['CREATED_AT'])) . "</td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='5' style='text-align: center; padding: 20px;'>No feedback available</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
            </div>
        </div>
    </div>

    <script>
    function printFeedback() {
        // Add print header
        const printHeader = document.createElement('div');
        printHeader.style.textAlign = 'center';
        printHeader.style.marginBottom = '20px';
        printHeader.innerHTML = `
            <h2 style="color: rgb(26, 19, 46); margin-bottom: 10px;">Student Feedback Report</h2>
            <p style="color: #666;">Generated on: ${new Date().toLocaleString()}</p>
        `;
        
        // Insert header before the table
        const table = document.querySelector('table');
        table.parentNode.insertBefore(printHeader, table);
        
        // Print the page
        window.print();
        
        // Remove the header after printing
        printHeader.remove();
    }
    </script>
</body>
</html>
