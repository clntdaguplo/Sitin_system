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
            file_name VARCHAR(255),
            file_path TEXT,
            file_type VARCHAR(50),
            upload_date DATETIME NOT NULL
        )";
        mysqli_query($con, $create_table);
    } else {
        // Check if PRIMARY KEY exists before dropping
        $check_primary = "SHOW KEYS FROM lab_resources WHERE Key_name = 'PRIMARY'";
        $primary_exists = mysqli_query($con, $check_primary);

        if (mysqli_num_rows($primary_exists) > 0) {
            // Only drop PRIMARY KEY if it exists
            $drop_primary = "ALTER TABLE lab_resources DROP PRIMARY KEY";
            mysqli_query($con, $drop_primary);
        }

        // Now add the new PRIMARY KEY
        $add_primary = "ALTER TABLE lab_resources ADD PRIMARY KEY (id)";
        mysqli_query($con, $add_primary);
    }

    // Insert the resource with file information
    $query = "INSERT INTO lab_resources (title, description, file_name, file_path, file_type, upload_date) 
              VALUES (?, ?, ?, ?, ?, NOW())";
    $stmt = mysqli_prepare($con, $query);
    
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "sssss", $title, $description, $file_name, $file_path, $file_type);
        
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
            background: #f5f5f5;
            width: 100%;
        }

        .container {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            height: calc(100vh - 100px);
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .resources-header {
            background: rgb(26, 19, 46);
            color: white;
            padding: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-radius: 15px 15px 0 0;
        }

        .resources-header h1 {
            font-size: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .content-wrapper {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 25px;
            margin-top: 20px;
            flex: 1;
            overflow: hidden;
        }

        .div1, .div2 {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            height: 100%;
            overflow-y: auto;
        }

        .resource-form {
            background: #f8fafc;
            padding: 25px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 10px;
            color: #2d3748;
            font-weight: 500;
            font-size: 1rem;
        }

        input[type="text"],
        textarea,
        select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            background: white;
            transition: all 0.3s ease;
            font-size: 0.95rem;
            color: #4a5568;
        }

        input[type="text"]:focus,
        textarea:focus,
        select:focus {
            border-color: rgb(47, 0, 177);
            outline: none;
            box-shadow: 0 0 0 3px rgba(47, 0, 177, 0.1);
        }

        .submit-btn {
            background: rgb(3, 138, 86);
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
            width: 100%;
            margin-top: 10px;
            font-size: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .submit-btn:hover {
            background: rgb(3, 138, 86);
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .resource-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            border: 1px solid #e2e8f0;
        }

        .resource-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .resource-info {
            margin-bottom: 15px;
        }

        .resource-title {
            color: rgb(47, 0, 177);
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .resource-description {
            color: #4a5568;
            margin-bottom: 15px;
            font-size: 0.95rem;
            line-height: 1.6;
        }

        .resource-meta {
            font-size: 0.9rem;
            color: #718096;
            margin-bottom: 15px;
        }

        .file-info {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px;
            background: #f8fafc;
            border-radius: 8px;
            margin-bottom: 10px;
        }

        .file-info i {
            color: rgb(47, 0, 177);
            font-size: 1.1rem;
        }

        .file-type {
            color: #64748b;
            font-size: 0.9em;
        }

        .resource-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .action-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border-radius: 8px;
            color: white;
            text-decoration: none;
            transition: all 0.3s ease;
            font-size: 0.95rem;
            font-weight: 500;
        }

        .download-btn {
            background: rgb(47, 0, 177);
        }

        .download-btn:hover {
            background: rgb(37, 0, 137);
            transform: translateY(-2px);
        }

        .edit-btn {
            background: rgb(3, 138, 86);
        }

        .edit-btn:hover {
            background: rgb(3, 138, 86);
            transform: translateY(-2px);
        }

        .delete-btn {
            background: #dc3545;
        }

        .delete-btn:hover {
            background: #c82333;
            transform: translateY(-2px);
        }

        .message {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }

        .success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #86efac;
        }

        .error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
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
            background: rgb(47, 0, 177);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: rgb(37, 0, 137);
        }

        @media (max-width: 1200px) {
            .content-wrapper {
                grid-template-columns: 1fr;
            }
            
            .div1, .div2 {
                height: auto;
            }
        }

        @media (max-width: 768px) {
            .content {
                padding: 15px;
            }
            
            .container {
                padding: 15px;
            }
            
            .resources-header {
                padding: 20px;
            }
            
            .resource-actions {
                flex-direction: column;
            }
            
            .action-btn {
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
            <a href="adannouncement.php"> Announcements</a>
            <a href="liststudent.php"> Students</a>
            <a href="adsitin.php"> Current Sitin</a>
            

            <a href="adlabresources.php"> LAB RESOURCES</a>
            <a href="adlabsched.php"> Lab Schedule</a>
            <a href="adreservation.php"> Reservations</a>
            <a href="adfeedback.php"> Feedback</a>
            <a href="admindash.php?logout=true" class="logout-button"> Log Out</a>
        </div>
    </div>

    <div class="content">
        <div class="container">
            <div class="resources-header">
                <h1>
                    <i class="fas fa-book"></i>
                    Laboratory Resources Management
                </h1>
            </div>
            
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
                                        <?php if (!empty($row['file_name'])): ?>
                                            <div class="file-info">
                                                <i class="fas fa-file"></i> File: <?php echo htmlspecialchars($row['file_name']); ?>
                                                <span class="file-type">(<?php echo strtoupper(htmlspecialchars($row['file_type'])); ?>)</span>
                                            </div>
                                        <?php endif; ?>
                                        <br>
                                        Uploaded: <?php echo date('M d, Y g:i A', strtotime($row['upload_date'])); ?>
                                    </div>
                                </div>
                                <div class="resource-actions">
                                    <?php if (!empty($row['file_path'])): ?>
                                        <a href="download_resource.php?id=<?php echo $row['id']; ?>" class="action-btn download-btn" title="Download File">
                                            <i class="fas fa-download"></i> Download
                                        </a>
                                    <?php endif; ?>
                                    <a href="edit_resource.php?id=<?php echo $row['id']; ?>" class="action-btn edit-btn" title="Edit Resource">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    <a href="?delete=<?php echo $row['id']; ?>" class="action-btn delete-btn" onclick="return confirm('Are you sure you want to delete this resource?')" title="Delete Resource">
                                        <i class="fas fa-trash"></i> Delete
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