<?php
session_start();
include("connector.php");

$upload_dir = 'uploads/resources/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

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
$stmt->close();

// Handle link submission
if (isset($_POST['submit'])) {
    $title = mysqli_real_escape_string($con, $_POST['title']);
    $description = mysqli_real_escape_string($con, $_POST['description']);
    $category = mysqli_real_escape_string($con, $_POST['category']);
    $link = !empty($_POST['link']) ? mysqli_real_escape_string($con, $_POST['link']) : '';
    
    // File upload handling
    $file_path = '';
    $file_name = '';
    $file_type = '';
    
    if (!empty($_FILES['resource_file']['name'])) {
        $file_name = $_FILES['resource_file']['name'];
        $file_type = pathinfo($file_name, PATHINFO_EXTENSION);
        $temp_name = $_FILES['resource_file']['tmp_name'];
        
        // Generate unique filename
        $unique_file_name = uniqid() . '_' . $file_name;
        $file_path = $upload_dir . $unique_file_name;
        
        if (move_uploaded_file($temp_name, $file_path)) {
            $file_path = $unique_file_name;
        } else {
            $error_message = "Error uploading file.";
        }
    }

    // First, check if the table exists
    $table_check = mysqli_query($con, "SHOW TABLES LIKE 'lab_resources'");
    if (mysqli_num_rows($table_check) == 0) {
        // Create table if it doesn't exist
        $create_table = "CREATE TABLE lab_resources (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            category VARCHAR(100) NOT NULL,
            link TEXT NOT NULL,
            file_name VARCHAR(255),
            file_path TEXT,
            file_type VARCHAR(50),
            upload_date DATETIME NOT NULL
        )";
        mysqli_query($con, $create_table);
    }

    // Insert the resource with file information
    $query = "INSERT INTO lab_resources (title, description, category, link, file_name, file_path, file_type, upload_date) 
              VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
    $stmt = mysqli_prepare($con, $query);
    
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "sssssss", $title, $description, $category, $link, $file_name, $file_path, $file_type);
        
        if (mysqli_stmt_execute($stmt)) {
            $success_message = "Resource added successfully!";
        } else {
            $error_message = "Error adding resource: " . mysqli_error($con);
        }
        mysqli_stmt_close($stmt);
    } else {
        $error_message = "Error preparing statement: " . mysqli_error($con);
    }
}

// Handle delete
if (isset($_GET['delete'])) {
    $id = mysqli_real_escape_string($con, $_GET['delete']);
    $query = "DELETE FROM lab_resources WHERE id = ?";
    $stmt = mysqli_prepare($con, $query);
    mysqli_stmt_bind_param($stmt, "i", $id);
    
    if (mysqli_stmt_execute($stmt)) {
        $success_message = "Resource deleted successfully!";
    } else {
        $error_message = "Error deleting resource: " . mysqli_error($con);
    }
}

// Fetch existing resources
$query = "SELECT * FROM lab_resources ORDER BY upload_date DESC";
$result = mysqli_query($con, $query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lab Resources Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        html, body {
            background: linear-gradient(45deg, #ff4757, #ffae42);
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

        .container {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            width: 100%;
            margin: 0 auto;
            height: calc(100vh - 100px);
        }

        h1 {
            color: black;
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 25px;
            text-align: left;
        }

        .content-wrapper {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 25px;
            margin-top: 20px;
            height: calc(100% - 80px);
        }

        .div1, .div2 {
            background: rgb(216, 213, 213);
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            height: 100%;
        }

        .div2 {
            overflow-y: auto;
        }

        .resource-form {
            background: rgba(255, 255, 255, 0.1);
            padding: 20px;
            border-radius: 8px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: black;
            font-weight: 500;
        }

        input[type="text"],
        textarea,
        select {
            width: 100%;
            padding: 10px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.9);
            transition: all 0.2s;
        }

        input[type="text"]:focus,
        textarea:focus,
        select:focus {
            border-color: white;
            outline: none;
            box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.1);
        }

        .submit-btn {
            background: #45a049;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.2s;
            width: 100%;
            margin-top: 10px;
        }

        .submit-btn:hover {
            background: #45a049;
            transform: translateY(-1px);
        }

        .resource-card {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }

        .resource-info {
            flex: 1;
        }

        .resource-title {
            color: black;
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .resource-description {
            color: rgba(0, 0, 0, 0.8);
            margin-bottom: 10px;
            font-size: 0.95rem;
        }

        .resource-meta {
            font-size: 0.85rem;
            color: rgba(0, 0, 0, 0.6);
        }

        .resource-actions {
            display: flex;
            gap: 10px;
        }

        .action-btn {
            padding: 8px 15px;
            border-radius: 6px;
            color: white;
            text-decoration: none;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }

        .edit-btn {
            background: #45a049;
        }

        .edit-btn:hover {
            background: #0f4578;
            transform: translateY(-2px);
        }

        .delete-btn {
            background: rgba(220, 53, 69, 0.8);
        }

        .action-btn:hover {
            transform: translateY(-2px);
            opacity: 0.9;
        }

        .message {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .success {
            background: rgba(40, 167, 69, 0.1);
            color: black;
            border: 1px solid rgba(40, 167, 69, 0.3);
        }

        .error {
            background: rgba(220, 53, 69, 0.1);
            color: black;
            border: 1px solid rgba(220, 53, 69, 0.3);
        }

        .resources-list {
            height: 100%;
            overflow-y: auto;
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
        @media (max-width: 1200px) {
            .content {
                padding: 15px;
            }
            
            .container {
                padding: 20px;
            }
            
            .content-wrapper {
                grid-template-columns: 1fr;
                height: auto;
            }
            
            .div1 {
                height: auto;
            }
            
            .div2 {
                height: 500px;
            }
        }

        @media (max-width: 768px) {
            .content {
                margin-top: 120px;
                padding: 10px;
            }
            
            .container {
                padding: 15px;
                height: calc(100vh - 140px);
            }
            
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
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .file-upload {
            position: relative;
            width: 100%;
        }

        .file-input {
            position: absolute;
            width: 0.1px;
            height: 0.1px;
            opacity: 0;
            overflow: hidden;
            z-index: -1;
        }

        .file-label {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 20px;
            background: rgba(255, 255, 255, 0.9);
            border: 2px dashed rgba(0, 0, 0, 0.2);
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            color: black;
        }

        .file-label:hover {
            background: rgba(255, 255, 255, 1);
            border-color: rgba(0, 0, 0, 0.3);
        }

        .file-label i {
            font-size: 1.2rem;
            color: black;
        }

        .file-info {
            margin-top: 8px;
            font-size: 0.9rem;
            color: rgba(0, 0, 0, 0.6);
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
            <a href="adannouncement.php"> Announcements</a>
            <a href="liststudent.php"> Students</a>
            <a href="adsitin.php"> Current Sitin</a>
            

            <a href="adlabresources.php"> LAB RESOURCES</a>
            <a href="adlabsched.php"> Lab Schedule</a>
            <a href="adreservation.php"> Reservations</a>
            <a href="adfeedback.php"> Feedback</a>
            <a href="admindash.php?logout=true" class="logout-button"><i class="fas fa-sign-out-alt"></i> Log Out</a>
        </div>
    </div>

    <div class="content">
        <div class="container">
            <h1>Laboratory Resources </h1>
            
            <?php if (isset($success_message)): ?>
                <div class="message success"><?php echo $success_message; ?></div>
            <?php endif; ?>
            
            <?php if (isset($error_message)): ?>
                <div class="message error"><?php echo $error_message; ?></div>
            <?php endif; ?>

            <div class="content-wrapper">
                <div class="div1">
                    <div class="resource-form">
                        <form method="POST" enctype="multipart/form-data">
                            <div class="form-group">
                                <label for="title">Title</label>
                                <input type="text" id="title" name="title" required>
                            </div>

                            <div class="form-group">
                                <label for="description">Description</label>
                                <textarea id="description" name="description" required></textarea>
                            </div>

                            

                            <div class="form-row">
                                

                                <div class="form-group">
                                    <label for="resource_file">Upload File (Optional)</label>
                                    <div class="file-upload">
                                        <input type="file" id="resource_file" name="resource_file" class="file-input">
                                        <label for="resource_file" class="file-label">
                                            <i class="fas fa-cloud-upload-alt"></i>
                                            <span>Choose a file</span>
                                        </label>
                                        <div class="file-info"></div>
                                    </div>
                                </div>
                            </div>

                            <button type="submit" name="submit" class="submit-btn">
                                 Add Resource
                            </button>
                        </form>
                    </div>
                </div>

                <div class="div2">
                    <div class="resources-list">
                        <?php while ($row = mysqli_fetch_assoc($result)): ?>
                            <div class="resource-card">
                                <div class="resource-info">
                                    <div class="resource-title"><?php echo htmlspecialchars($row['title']); ?></div>
                                    <div class="resource-description"><?php echo htmlspecialchars($row['description']); ?></div>
                                    <div class="resource-meta">
                                        
                                        <br>
                                        Uploaded: <?php echo date('M d, Y', strtotime($row['upload_date'])); ?>
                                    </div>
                                </div>
                                <div class="resource-actions">
                                    <a href="edit_resource.php?id=<?php echo $row['id']; ?>" class="action-btn edit-btn" title="Edit Resource">
                                        Edit
                                    </a>
                                    <a href="?delete=<?php echo $row['id']; ?>" class="action-btn delete-btn" onclick="return confirm('Are you sure you want to delete this resource?')" title="Delete Resource">
                                        Delete
                                    </a>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>