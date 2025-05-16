<?php
session_start();
include("connector.php");

// Check if user is logged in
if (!isset($_SESSION['Username'])) {
    header("Location: login.php");
    exit();
}

// Fetch user info
$username = $_SESSION['Username'];
$query = "SELECT IDNO, LASTNAME, FIRSTNAME, MIDNAME, COURSE, YEARLEVEL, EMAIL, PROFILE_PIC FROM user WHERE USERNAME = '$username'";
$result = mysqli_query($con, $query);

if ($result && mysqli_num_rows($result) > 0) {
    $user_data = mysqli_fetch_assoc($result);
} else {
    echo "User data not found.";
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

// Update User Info
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $lastname = $_POST['LASTNAME'];
    $firstname = $_POST['FIRSTNAME'];
    $midname = $_POST['MIDNAME'];
    $course = $_POST['COURSE'];
    $yearlevel = $_POST['YEARLEVEL'];
    $email = $_POST['EMAIL'];

    // Handle file upload
    if (isset($_FILES['PROFILE_PIC']) && $_FILES['PROFILE_PIC']['error'] == 0) {
        $target_dir = "uploads/";
        $target_file = $target_dir . basename($_FILES["PROFILE_PIC"]["name"]);
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

        // Check if image file is a actual image or fake image
        $check = getimagesize($_FILES["PROFILE_PIC"]["tmp_name"]);
        if ($check !== false) {
            // Check file size (limit to 5MB)
            if ($_FILES["PROFILE_PIC"]["size"] <= 5000000) {
                // Allow certain file formats
                if ($imageFileType == "jpg" || $imageFileType == "png" || $imageFileType == "jpeg" || $imageFileType == "gif") {
                    // Move the uploaded file to the target directory
                    if (move_uploaded_file($_FILES["PROFILE_PIC"]["tmp_name"], $target_file)) {
                        $profile_pic = basename($_FILES["PROFILE_PIC"]["name"]);
                    } else {
                        echo "Sorry, there was an error uploading your file.";
                    }
                } else {
                    echo "Sorry, only JPG, JPEG, PNG & GIF files are allowed.";
                }
            } else {
                echo "Sorry, your file is too large.";
            }
        } else {
            echo "File is not an image.";
        }
    } else {
        $profile_pic = $user_data['PROFILE_PIC'];
    }

    $update_query = "UPDATE user SET
    LASTNAME='$lastname', FIRSTNAME='$firstname',
    MIDNAME='$midname', COURSE='$course', YEARLEVEL='$yearlevel', EMAIL='$email', PROFILE_PIC='$profile_pic'
    WHERE USERNAME='$username'";

    if (mysqli_query($con, $update_query)) {
        echo "<script>alert('Profile Updated Successfully'); window.location.href='profile.php';</script>";
    } else {
        echo "Error updating record: " . mysqli_error($con);
    }
}

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
<title>Edit Student Profile</title>
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

.edit-container {
    background: white;
    border-radius: 15px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    height: calc(100vh - 100px);
    width: 100%;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.edit-header {
    background: rgb(26, 19, 46);
    color: white;
    padding: 25px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-radius: 15px 15px 0 0;
}

.edit-header h1 {
    font-size: 24px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.edit-content {
    padding: 30px;
    overflow-y: auto;
}

.profile-image-container {
    position: relative;
    width: 150px;
    height: 150px;
    margin: 0 auto 30px;
    cursor: pointer;
}

.profile-image-container img {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid rgb(47, 0, 177);
}

.profile-image-container:hover::after {
    content: 'Change Photo';
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    background: rgba(47, 0, 177, 0.8);
    color: white;
    padding: 8px;
    border-radius: 0 0 75px 75px;
    text-align: center;
    font-size: 14px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    color: rgb(47, 0, 177);
    font-weight: 500;
    font-size: 0.95rem;
}

.form-group input,
.form-group select {
    width: 100%;
    padding: 12px 15px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    font-size: 1rem;
    transition: all 0.3s ease;
    background: white;
    color: #2d3748;
}

.form-group input:focus,
.form-group select:focus {
    border-color: rgb(47, 0, 177);
    box-shadow: 0 0 0 3px rgba(47, 0, 177, 0.1);
    outline: none;
}

.readonly-input {
    background: #f8fafc !important;
    cursor: not-allowed;
    color: #718096;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 20px;
}

.form-group.full-width {
    grid-column: 1 / -1;
}

.form-section {
    background: #f8fafc;
    padding: 20px;
    border-radius: 12px;
    margin-bottom: 25px;
}

.form-section-title {
    color: rgb(47, 0, 177);
    font-size: 1.1rem;
    font-weight: 600;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 2px solid rgba(47, 0, 177, 0.1);
}

.edit-form {
    max-width: 800px;
    margin: 0 auto;
    padding: 20px;
}

.submit-btn {
    background: rgb(6, 134, 64);
    color: white;
    padding: 12px 25px;
    border: none;
    border-radius: 8px;
    font-size: 1rem;
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-block;
    width: auto;
    margin-top: 30px;
    margin-right: 10px;
}

.cancel-btn {
    background: #e2e8f0;
    color: #4a5568;
    padding: 12px 25px;
    border: none;
    border-radius: 8px;
    font-size: 1rem;
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-block;
    width: auto;
    margin-top: 30px;
    text-decoration: none;
}

.submit-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
}

.cancel-btn:hover {
    background: #cbd5e0;
    transform: translateY(-2px);
}

.button-group {
    display: flex;
    justify-content: center;
    gap: 10px;
    margin-top: 30px;
}

/* Remove burger menu styles */
.burger {
    display: none;
}

.profile-section {
    text-align: center;
    margin-bottom: 30px;
    padding-bottom: 30px;
    border-bottom: 1px solid #e2e8f0;
}

/* Replace the existing back-button styles in your CSS */
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
    <div class="edit-container">
        <div class="edit-header">
            <h1>
                <i class="fas fa-user-edit"></i>
                Edit Profile
            </h1>
        </div>
        
        <div class="edit-content">
            <form class="edit-form" action="" method="POST" enctype="multipart/form-data">
                <div class="profile-section">
                    <div class="profile-image-container" onclick="document.getElementById('profile_pic_input').click()">
                        <img id="profile_preview" src="uploads/<?php echo htmlspecialchars($user_data['PROFILE_PIC']); ?>" alt="Profile Picture">
                        <input type="file" id="profile_pic_input" name="PROFILE_PIC" accept="image/*" style="display: none;">
                    </div>
                </div>

                <div class="form-section">
                    <div class="form-section-title">Student Information</div>
                    <!-- ID Number - Full Width -->
                    <div class="form-row">
                        <div class="form-group full-width">
                            <label>ID Number</label>
                            <input type="text" name="IDNO" value="<?php echo htmlspecialchars($user_data['IDNO']); ?>" readonly class="readonly-input">
                        </div>
                    </div>

                    <!-- Name Fields -->
                    <div class="form-row">
                        <div class="form-group">
                            <label>Last Name</label>
                            <input type="text" name="LASTNAME" value="<?php echo htmlspecialchars($user_data['LASTNAME']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>First Name</label>
                            <input type="text" name="FIRSTNAME" value="<?php echo htmlspecialchars($user_data['FIRSTNAME']); ?>" required>
                        </div>
                    </div>

                    <!-- Middle Name and Course -->
                    <div class="form-row">
                        <div class="form-group">
                            <label>Middle Name</label>
                            <input type="text" name="MIDNAME" value="<?php echo htmlspecialchars($user_data['MIDNAME']); ?>">
                        </div>
                        <div class="form-group">
                            <label>Course</label>
                            <select name="COURSE" required>
                                <option value="BSCS" <?php if($user_data['COURSE'] == 'BSCS') echo 'selected'; ?>>BSCS</option>
                                <option value="BSIT" <?php if($user_data['COURSE'] == 'BSIT') echo 'selected'; ?>>BSIT</option>
                                <option value="BSHM" <?php if($user_data['COURSE'] == 'BSHM') echo 'selected'; ?>>BSHM</option>
                                <option value="BEED" <?php if($user_data['COURSE'] == 'BEED') echo 'selected'; ?>>BEED</option>
                                <option value="BSCRIM" <?php if($user_data['COURSE'] == 'BSCRIM') echo 'selected'; ?>>BSCRIM</option>
                                <option value="BSED" <?php if($user_data['COURSE'] == 'BSED') echo 'selected'; ?>>BSED</option>
                                <option value="BSBA" <?php if($user_data['COURSE'] == 'BSBA') echo 'selected'; ?>>BSBA</option>
                            </select>
                        </div>
                    </div>

                    <!-- Year Level and Email -->
                    <div class="form-row">
                        <div class="form-group">
                            <label>Year Level</label>
                            <select name="YEARLEVEL" required>
                                <option value="1" <?php if($user_data['YEARLEVEL'] == '1') echo 'selected'; ?>>1st Year</option>
                                <option value="2" <?php if($user_data['YEARLEVEL'] == '2') echo 'selected'; ?>>2nd Year</option>
                                <option value="3" <?php if($user_data['YEARLEVEL'] == '3') echo 'selected'; ?>>3rd Year</option>
                                <option value="4" <?php if($user_data['YEARLEVEL'] == '4') echo 'selected'; ?>>4th Year</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" name="EMAIL" value="<?php echo htmlspecialchars($user_data['EMAIL']); ?>" required>
                        </div>
                    </div>
                </div>

                <div class="button-group">
                    <button type="submit" class="submit-btn">Update Profile</button>
                    <a href="profile.php" class="cancel-btn">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('profile_pic_input').onchange = function(e) {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('profile_preview').src = e.target.result;
        }
        reader.readAsDataURL(file);
    }
}
</script>
</body>
</html>