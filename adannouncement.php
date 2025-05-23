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
    $user_name = htmlspecialchars($row['FIRSTNAME'] . ' ' . $row['MIDNAME'] . ' ' . $row['LASTNAME']);
} else {
    $profile_pic = 'default.jpg';
    $user_name = 'Admin';
}

// Handle search
$search_result = null;
$user_not_found = false;
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['search'])) {
    $search_id = $_POST['search_id'];
    $search_query = "SELECT * FROM user WHERE IDNO = ?";
    $stmt = $con->prepare($search_query);
    $stmt->bind_param("s", $search_id);
    $stmt->execute();
    $search_result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$search_result) {
        $user_not_found = true;
    }
}

// Prepare and execute the SQL statement to insert announcement
if ($_SERVER["REQUEST_METHOD"] == "POST" && !isset($_POST['search'])) {
    $title = $_POST["title"];
    $content = $_POST["content"];

    // Prepare the SQL statement
    $stmt = $con->prepare("INSERT INTO announcements (TITLE, CONTENT) VALUES (?, ?)");
    $stmt->bind_param("ss", $title, $content);

    if ($stmt->execute()) {
        echo "<script>alert('Announcement posted successfully!');</script>";
    } else {
        echo "<script>alert('Failed to post announcement!');</script>";
    }

    $stmt->close();
}

// Handle delete announcement
if (isset($_GET['delete'])) {
    $title_to_delete = $_GET['delete'];
    $stmt = $con->prepare("DELETE FROM announcements WHERE TITLE = ?");
    $stmt->bind_param("s", $title_to_delete);

    if ($stmt->execute()) {
        echo "<script>alert('Announcement deleted successfully!');</script>";
    } else {
        echo "<script>alert('Failed to delete announcement!');</script>";
    }

    $stmt->close();
}

// Fetch announcements
$announcements_query = "SELECT * FROM announcements ORDER BY CREATED_AT DESC";
$announcements_result = $con->query($announcements_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
<link rel="stylesheet" href="https://www.w3schools.com/w3css/4/w3.css">
<title>Admin Announcements</title>
<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

html, body {
    background: #f0f2f5;
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

/* Update notification badge position */
.notification-badge {
    position: relative;
    top: -2px;
    right: -5px;
    margin-left: 5px;
}

.content {
    margin-top: 100px;
    padding: 30px;
    min-height: calc(100vh - 100px);
}

.parent {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 30px;
    max-width: 1400px;
    margin: 0 auto;
}

.div1 {
    background: rgb(216, 213, 213);
    padding: 30px;
    border-radius: 15px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
    position: relative;
    min-height: 400px;
    display: flex;
    flex-direction: column;
}

.div1 h1 {
    color: rgb(0, 0, 0);
    margin-bottom: 25px;
    font-size: 1.8rem;
    display: flex;
    align-items: center;
    gap: 10px;
}

form {
    display: flex;
    flex-direction: column;
    gap: 20px;
    flex: 1;
}

input[type="text"], textarea {
    width: 100%;
    padding: 15px;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    font-size: 1em;
    background: #f8fafc;
    transition: all 0.3s ease;
}

input[type="text"]:focus, textarea:focus {
    border-color: rgb(0, 0, 0);
    outline: none;
    box-shadow: 0 0 0 4px rgba(20, 86, 155, 0.1);
}

textarea {
    min-height: 200px;
    resize: vertical;
    flex: 1;
}

button[type="submit"] {
    background: linear-gradient(45deg,rgb(150, 145, 79),rgb(47, 0, 177));
    color: white;
    padding: 15px 25px;
    border: none;
    border-radius: 12px;
    cursor: pointer;
    font-weight: 600;
    font-size: 1rem;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    margin-top: auto;
}

button[type="submit"]:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(20, 86, 155, 0.2);
}

.current-time {
    background: rgba(255, 255, 255, 0.1);
    padding: 15px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    gap: 15px;
    color: #333;
    font-size: 1.1rem;
    font-weight: 500;
    margin-top: 20px;
    border: 1px solid #e2e8f0;
}

.current-time i {
    color: rgb(47, 0, 177);
}

.current-time span {
    margin-right: 10px;
}

.div2 {
    background: rgb(216, 213, 213);
    padding: 30px;
    border-radius: 15px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
    max-height: 800px;
    overflow-y: auto;
}

.div2 h2 {
    color: rgb(0, 0, 0);
    margin-bottom: 30px;
    font-size: 1.8rem;
    display: flex;
    align-items: center;
    gap: 10px;
    padding-bottom: 15px;
    border-bottom: 3px solid rgb(47, 0, 177);
}

.announcement {
    background: #ffffff;
    border-radius: 10px;
    margin-bottom: 30px;
    transition: all 0.3s ease;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    overflow: hidden;
    border: 1px solid #e2e8f0;
    position: relative;
}

.announcement:not(:last-child)::after {
    content: '';
    position: absolute;
    bottom: -15px;
    left: 0;
    right: 0;
    height: 1px;
    background: linear-gradient(to right, transparent, rgb(47, 0, 177), transparent);
}

.announcement-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    background: #f8f9fa;
    border-bottom: 1px solid #e2e8f0;
}

.announcement-header h3 {
    color: rgb(47, 0, 177);
    font-size: 1.2em;
    font-weight: 600;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.announcement-header h3::before {
    content: '\f0a1';
    font-family: 'Font Awesome 5 Free';
    font-weight: 900;
    font-size: 0.9em;
    color: rgb(150, 145, 79);
}

.announcement-content {
    display: none;
    padding: 0;
    border-bottom: 1px solid #e2e8f0;
}

.announcement.active .announcement-content {
    display: block;
    animation: fadeIn 0.3s ease-out;
}

@keyframes fadeIn {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}

.announcement-content p {
    color: #4a5568;
    line-height: 1.6;
    font-size: 1.05em;
    padding: 25px;
    margin: 0;
    background: #ffffff;
}

.timestamp {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #718096;
    font-size: 0.9em;
    padding: 20px;
    background: #f8f9fa;
    border-top: 1px solid #e2e8f0;
}

.timestamp::before {
    content: '\f017';
    font-family: 'Font Awesome 5 Free';
    font-weight: 900;
    color: rgb(150, 145, 79);
}

.actions {
    display: flex;
    gap: 15px;
    padding: 20px;
    background: #f8f9fa;
    border-top: 1px solid #e2e8f0;
}

.update, .delete {
    padding: 10px 20px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 500;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.9rem;
    min-width: 100px;
    justify-content: center;
}

.update {
    background: rgb(47, 0, 177);
    color: white;
}

.delete {
    background: rgb(150, 145, 79);
    color: white;
}

.update:hover {
    background: rgb(37, 0, 137);
    transform: translateY(-2px);
}

.delete:hover {
    background: rgb(130, 125, 69);
    transform: translateY(-2px);
}

.announcement-toggle {
    color: rgb(47, 0, 177);
    font-size: 1.1em;
    transition: transform 0.3s ease;
    padding: 8px;
    border-radius: 50%;
    background: rgba(47, 0, 177, 0.1);
}

.announcement.active .announcement-toggle {
    transform: rotate(180deg);
    background: rgba(47, 0, 177, 0.2);
}

/* Custom scrollbar for announcements container */
.div2::-webkit-scrollbar {
    width: 8px;
}

.div2::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}

.div2::-webkit-scrollbar-thumb {
    background: rgb(47, 0, 177);
    border-radius: 4px;
}

.div2::-webkit-scrollbar-thumb:hover {
    background: rgb(37, 0, 137);
}

/* Responsive Design */
@media (max-width: 1200px) {
    .parent {
        grid-template-columns: 1fr;
        padding: 15px;
    }
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
    
    .div1, .div2 {
        padding: 20px;
    }
    
    .actions {
        flex-direction: column;
    }
    
    .update, .delete {
        width: 100%;
        justify-content: center;
    }
}
</style>
</head>
<body>
<div class="top-nav">
    <div class="nav-left">
        <img src="uploads/<?php echo htmlspecialchars($profile_pic); ?>" alt="Profile Picture" onerror="this.src='assets/default.png';">
        <div class="user-name"><?php echo htmlspecialchars($user_name); ?></div>
    </div>
    <div class="nav-right">
        <a href="admindash.php"> Dashboard</a>
        <a href="adannouncement.php"> ANNOUNCEMENT</a>
        <a href="liststudent.php">Students</a>
        <a href="adsitin.php"> Current Sitin</a>
        
        
        <a href="adlabresources.php"> Lab Resources</a>
        <a href="adlabsched.php"> Lab Schedule</a>
        <a href="adreservation.php"> Reservations</a>
        <a href="adfeedback.php"> Feedback</a>
        <a href="admindash.php?logout=true" class="logout-button"> Log Out</a>
    </div>
</div>

<div class="content">
    <div class="parent">
        <div class="div1">
            <h1>Post Announcement</h1>
            <form action="adannouncement.php" method="POST">
                <input type="text" name="title" placeholder="Announcement Title" required>
                <textarea name="content" placeholder="Announcement Content" required></textarea>
                <button type="submit">Post Announcement</button>
            </form>
            <div class="current-time">
                <i class="fas fa-calendar-alt"></i>
                <span id="current-date"></span>
                <i class="fas fa-clock"></i>
                <span id="current-time"></span>
            </div>
        </div>
        <div class="div2">
            <h2>Announcements</h2>
            <?php while ($announcement = $announcements_result->fetch_assoc()): ?>
                <div class="announcement">
                    <div class="announcement-header">
                        <h3><?php echo htmlspecialchars($announcement['TITLE']); ?></h3>
                        <i class="fas fa-chevron-down announcement-toggle"></i>
                    </div>
                    <div class="announcement-content">
                        <p><?php echo htmlspecialchars($announcement['CONTENT']); ?></p>
                        <span class="timestamp"><?php echo htmlspecialchars($announcement['CREATED_AT']); ?></span>
                        <div class="actions">
                            <button class="update" onclick="updateAnnouncement('<?php echo htmlspecialchars($announcement['TITLE']); ?>')">
                                Update
                            </button>
                            <button class="delete" onclick="deleteAnnouncement('<?php echo htmlspecialchars($announcement['TITLE']); ?>')">
                                Delete
                            </button>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    </div>
</div>

<script>
function updateAnnouncement(title) {
    window.location.href = `update_announcement.php?title=${title}`;
}

function deleteAnnouncement(title) {
    if (confirm('Are you sure you want to delete this announcement?')) {
        window.location.href = `adannouncement.php?delete=${title}`;
    }
}

function updateDateTime() {
    const now = new Date();
    
    // Update time
    const timeString = now.toLocaleTimeString('en-US', { 
        hour: '2-digit', 
        minute: '2-digit', 
        second: '2-digit',
        hour12: true 
    });
    
    // Update date
    const dateString = now.toLocaleDateString('en-US', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
    
    document.getElementById('current-time').textContent = timeString;
    document.getElementById('current-date').textContent = dateString;
}

// Update date and time immediately and then every second
updateDateTime();
setInterval(updateDateTime, 1000);

// Add this new code for dropdown functionality
document.addEventListener('DOMContentLoaded', function() {
    const announcements = document.querySelectorAll('.announcement');
    
    announcements.forEach(announcement => {
        announcement.addEventListener('click', function(e) {
            // Don't toggle if clicking on action buttons
            if (e.target.closest('.actions')) {
                return;
            }
            
            // Toggle active class
            this.classList.toggle('active');
        });
    });
});
</script>
</body>
</html>
