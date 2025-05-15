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
    padding: 20px;
    min-height: calc(100vh - 80px);
    background: #f0f2f5;
    width: 100%;
}

.container {
    background: white;
    border-radius: 15px;
    padding: 25px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    height: calc(100vh - 100px);
    width: 100%;
    max-width: 100%;
    margin: 0;
    overflow: hidden;
}

.header {
    margin-bottom: 20px;
}

.header h1 {
    color: #000000;
    font-size: 32px;
    font-weight: bold;
    margin-bottom: 15px;
    text-transform: uppercase;
    letter-spacing: 1px;
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
    background:  rgb(134, 5, 15);
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
    background:  rgb(161, 12, 1);
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
<div style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
    <div class="search-box">
        <input type="text" id="searchInput" placeholder="Search by ID, Name, or Course...">
        <button type="button">
            <i class="fas fa-search"></i>
        </button>
    </div>
    <div style="display: flex; gap: 10px; align-items: center;">
        <button onclick="printAllStudents()" style="background: rgb(2, 141, 56); color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; display: flex; align-items: center; gap: 8px;">
            <i class="fas fa-print"></i> Print All Students
        </button>
        <form method="POST" style="margin: 0;">
            <button type="submit" name="reset_sessions" class="reset-button">
                <i class="fas fa-sync-alt"></i> Reset All Sessions
            </button>
        </form>
    </div>
</div>
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
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.4.0/jspdf.umd.min.js"></script>
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

function printAllStudents() {
    try {
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF('l', 'mm', 'a4');
        
        // Set font and size
        doc.setFont('helvetica', 'bold');
        
        // Center everything on the page
        const pageWidth = doc.internal.pageSize.width;
        const pageCenter = pageWidth / 2;
        
        // Define colors
        const ucBlue = [20, 86, 155];  // UC Blue color
        
        // Add header text
        doc.setTextColor(0, 0, 0);  // Black text
        doc.setFontSize(28);
        doc.text('UNIVERSITY OF CEBU - MAIN', pageCenter, 25, { align: 'center' });
        
        doc.setFontSize(22);
        doc.text('COLLEGE OF COMPUTER STUDIES', pageCenter, 35, { align: 'center' });
        
        // Add report title
        doc.setFontSize(18);
        doc.text('STUDENT RECORDS REPORT', pageCenter, 50, { align: 'center' });
        
        // Add date and time
        const now = new Date();
        const dateStr = now.toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
        const timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit',
            minute: '2-digit',
            hour12: true
        });
        doc.setFontSize(12);
        doc.text(`Generated on: ${dateStr} ${timeStr}`, pageCenter, 60, { align: 'center' });
        
        // Add a decorative line
        doc.setDrawColor(...ucBlue);
        doc.setLineWidth(0.5);
        doc.line(20, 65, pageWidth - 20, 65);
        
        // Set font size for table content
        doc.setFontSize(10);
        
        const table = document.querySelector('table');
        const visibleRows = Array.from(table.querySelectorAll('tbody tr')).filter(row => row.style.display !== 'none');
        
        // Define column widths and positions
        const colWidths = [25, 30, 30, 30, 30, 20, 25, 40, 15, 25];  // Adjusted widths for all columns
        let yPos = 80; // Starting position for table
        
        // Calculate total width of the table
        const totalWidth = colWidths.reduce((a, b) => a + b, 0);
        // Calculate starting X position to center the table
        let startX = (pageWidth - totalWidth) / 2;
        
        // Draw headers
        const headers = Array.from(table.querySelectorAll('th')).slice(0, -1); // Exclude Actions column
        let xPos = startX;
        headers.forEach((header, index) => {
            if (index < colWidths.length) {
                doc.setTextColor(0, 0, 0);
                doc.setFont('helvetica', 'bold');
                doc.text(header.innerText, xPos + 2, yPos + 8);
                xPos += colWidths[index];
            }
        });
        
        // Add header underline
        doc.setDrawColor(...ucBlue);
        doc.setLineWidth(0.5);
        doc.line(startX, yPos + 10, startX + totalWidth, yPos + 10);
        
        yPos += 15;
        
        // Draw rows
        let rowCount = 0;
        visibleRows.forEach(row => {
            // Check if we need a new page
            if (yPos > doc.internal.pageSize.height - 20) {
                doc.addPage();
                yPos = 20;
                rowCount = 0;
            }
            
            xPos = startX;
            const cells = Array.from(row.querySelectorAll('td')).slice(0, -1); // Exclude Actions column
            cells.forEach((cell, index) => {
                if (index < colWidths.length) {
                    doc.setFont('helvetica', 'normal');
                    doc.setTextColor(0, 0, 0);
                    
                    // Handle text overflow
                    let text = cell.innerText;
                    if (text.length > 20) {
                        text = text.substring(0, 17) + '...';
                    }
                    
                    // Special handling for points column
                    if (index === 8) { // Points column
                        const points = parseInt(text);
                        if (points >= 2) {
                            doc.setTextColor(40, 167, 69); // Green for high points
                        } else if (points === 1) {
                            doc.setTextColor(255, 193, 7); // Yellow for medium points
                        } else {
                            doc.setTextColor(108, 117, 125); // Gray for low points
                        }
                    }
                    
                    doc.text(text, xPos + 2, yPos + 8);
                    xPos += colWidths[index];
                }
            });
            
            // Add row separator
            doc.setDrawColor(200, 200, 200);
            doc.setLineWidth(0.2);
            doc.line(startX, yPos + 12, startX + totalWidth, yPos + 12);
            
            yPos += 15;
            rowCount++;
        });
        
        // Add footer
        const footerY = doc.internal.pageSize.height - 20;
        doc.setDrawColor(...ucBlue);
        doc.setLineWidth(0.5);
        doc.line(20, footerY, pageWidth - 20, footerY);
        
        doc.setTextColor(0, 0, 0);
        doc.setFontSize(10);
        doc.text('© University of Cebu - Computer Laboratory Sitin Monitoring System', pageCenter, footerY + 10, { align: 'center' });
        
        // Add page numbers
        const totalPages = doc.internal.getNumberOfPages();
        for (let i = 1; i <= totalPages; i++) {
            doc.setPage(i);
            doc.setFontSize(10);
            doc.setTextColor(128, 128, 128);
            doc.text(`Page ${i} of ${totalPages}`, pageWidth - 20, doc.internal.pageSize.height - 10);
        }
        
        // Save the PDF
        doc.save('student_records_report.pdf');
    } catch (error) {
        console.error('Error generating PDF:', error);
        alert('There was an error generating the PDF. Please try again.');
    }
}
</script>
</body>
</html>