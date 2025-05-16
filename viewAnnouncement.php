<?php
session_start();
include("connector.php");

// Check if user is logged in
if (!isset($_SESSION['Username'])) {
    header("Location: login.php");
    exit();
}

// Fetch user details
$username = $_SESSION['Username'];
$query = "SELECT PROFILE_PIC, FIRSTNAME, MIDNAME, LASTNAME FROM user WHERE USERNAME = '$username'";
$result = mysqli_query($con, $query);

if ($result && mysqli_num_rows($result) > 0) {
    $row = mysqli_fetch_assoc($result);
    $profile_pic = !empty($row['PROFILE_PIC']) ? htmlspecialchars($row['PROFILE_PIC']) : 'default.jpg';
    $user_name = htmlspecialchars($row['LASTNAME'] . ' ' . substr($row['FIRSTNAME'], 0, 1) . '.');  
} else {
    $profile_pic = 'default.jpg';
    $user_name = 'User';
}

// Fetch announcements
$announcements = $con->query("SELECT * FROM announcements ORDER BY created_at DESC");

// Fetch lab resources
$resources_query = "SELECT * FROM lab_resources ORDER BY upload_date DESC";
$resources_result = mysqli_query($con, $resources_query);

// Fetch categories
$categories_query = "SELECT DISTINCT category FROM lab_resources";
$categories_result = mysqli_query($con, $categories_query);

// Initialize filters
$search = isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '';
$category_filter = isset($_GET['category']) ? htmlspecialchars($_GET['category']) : '';
$type_filter = isset($_GET['type']) ? htmlspecialchars($_GET['type']) : '';

// Modify the query if filters are applied
if (!empty($search) || !empty($category_filter) || !empty($type_filter)) {
    $resources_query = "SELECT * FROM lab_resources WHERE 1=1";
    
    if (!empty($search)) {
        $resources_query .= " AND (title LIKE '%" . mysqli_real_escape_string($con, $search) . "%' 
                            OR description LIKE '%" . mysqli_real_escape_string($con, $search) . "%')";
    }
    
    if (!empty($category_filter)) {
        $resources_query .= " AND category = '" . mysqli_real_escape_string($con, $category_filter) . "'";
    }
    
    if (!empty($type_filter)) {
        switch($type_filter) {
            case 'link':
                $resources_query .= " AND link IS NOT NULL AND link != ''";
                break;
            case 'file':
                $resources_query .= " AND file_path IS NOT NULL AND file_path != ''";
                break;
        }
    }
    
    $resources_query .= " ORDER BY upload_date DESC";
    $resources_result = mysqli_query($con, $resources_query);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <title>Announcements</title>
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
            display: flex;
            flex-direction: row;
            gap: 20px;
        }

        /* Remove old sidebar styles */
        .sidebar {
            display: none;
        }

        /* Content Area */
        .section-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            height: calc(100vh - 100px);
            width: 50%;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .section-header {
            background: rgb(26, 19, 46);
            color: white;
            padding: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-radius: 15px 15px 0 0;
        }

        .section-header h1 {
            font-size: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-content {
            padding: 25px;
            overflow-y: auto;
            flex-grow: 1;
            background: rgba(248, 250, 252, 0.8);
        }

        /* Resources Grid Styles */
        .resources-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 15px;
            padding: 10px;
        }

        .resource-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            transition: all 0.3s ease;
            border: 1px solid #e2e8f0;
            display: flex;
            flex-direction: column;
            height: 220px;
        }

        .resource-header {
            padding: 15px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .resource-body {
            padding: 15px;
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .resource-title {
            font-size: 1.1rem;
            color: rgb(47, 0, 177);
            font-weight: 600;
            margin-bottom: 10px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .resource-description {
            color: #718096;
            font-size: 0.95rem;
            line-height: 1.6;
            margin-bottom: auto;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .resource-actions {
            padding: 15px;
            display: flex;
            gap: 12px;
            border-top: 1px solid #e2e8f0;
            background: white;
        }

        .resource-btn {
            flex: 1;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 15px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .link-btn {
            background: rgb(47, 0, 177);
            color: white !important;
        }

        .download-btn {
            background: #f8fafc;
            color: rgb(47, 0, 177) !important;
            border: 1px solid rgb(47, 0, 177);
        }

        .category-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            background: rgb(47, 0, 177);
            color: white;
            border-radius: 20px;
            font-size: 0.85rem;
        }

        .resource-date {
            color: #64748b;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        /* Filters Styles */
        .filters {
            padding: 12px;
            background: white;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }

        .search-box {
            flex: 1;
            min-width: 200px;
        }

        .search-box input {
            width: 100%;
            padding: 10px 15px;
            padding-left: 40px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 0.9rem;
            color: #4a5568;
            transition: all 0.3s ease;
            background: #f8fafc;
        }

        .category-filter select,
        .type-filter select {
            min-width: 120px;
            padding: 10px 30px 10px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 0.9rem;
            color: #4a5568;
            background: #f8fafc;
            cursor: pointer;
            transition: all 0.3s ease;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23a0aec0'%3E%3Cpath d='M7 10l5 5 5-5z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 10px center;
            background-size: 20px;
        }

        /* Remove these styles */
        .burger,
        .sidebar.active,
        .content.sidebar-active {
            display: none;
        }

        /* Update responsive design */
        @media (max-width: 1200px) {
            .content {
                flex-direction: column;
            }
            
            .section-container {
                width: 100%;
                height: calc(50vh - 50px);
            }
        }

        @media (max-width: 768px) {
            .content {
                padding: 15px;
            }
            
            .filters {
                flex-direction: column;
                align-items: stretch;
            }
            
            .search-box,
            .category-filter select,
            .type-filter select {
                width: 100%;
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
        <a href="labRules&Regulations.php">  Lab Rules</a>
        <a href="labschedule.php"> Lab Schedules</a>
        
        <a href="reservation.php"> Reservation</a>
        <a href="history.php"> History</a>

        <a href="login.php" class="logout-button"> Log Out</a>
    </div>
</div>

<div class="content">
    <!-- Announcements Section -->
    <div class="section-container">
        <div class="section-header">
            <h1>
                <i class="fas fa-bullhorn"></i>
                Announcements
            </h1>
        </div>
        <div class="section-content">
            <?php while ($announcement = $announcements->fetch_assoc()) { ?>
                <div class="announcement">
                    <h2><?php echo htmlspecialchars($announcement['TITLE']); ?><i class="fas fa-bullhorn"></i></h2>
                    <p><?php echo nl2br(htmlspecialchars($announcement['CONTENT'])); ?></p>
                    <small><i class="fas fa-clock"></i><?php echo htmlspecialchars($announcement['CREATED_AT']); ?></small>
                </div>
            <?php } ?>
        </div>
    </div>

    <!-- Lab Resources Section -->
    <div class="section-container">
        <div class="section-header">
            <h1>
                <i class="fas fa-book"></i>
                Lab Resources
            </h1>
        </div>
        
        <div class="filters">
            <div class="search-box">
                <input type="text" id="search" placeholder="Search resources..." 
                       value="<?php echo htmlspecialchars($search); ?>">
            </div>
            
        
        <div class="section-content">
            <div class="resources-grid">
                <?php if (mysqli_num_rows($resources_result) > 0): ?>
                    <?php while ($row = mysqli_fetch_assoc($resources_result)): ?>
                        <div class="resource-card">
                            <div class="resource-header">
                                <div class="category-badge">
                                    <i class="fas fa-tag"></i> <?php echo htmlspecialchars($row['category']); ?>
                                </div>
                                <div class="resource-date">
                                    <i class="fas fa-calendar-alt"></i>
                                    <?php echo date('F j, Y', strtotime($row['upload_date'])); ?>
                                </div>
                            </div>
                            
                            <div class="resource-body">
                                <h3 class="resource-title">
                                    <?php echo htmlspecialchars($row['title']); ?>
                                </h3>
                                <p class="resource-description">
                                    <?php echo htmlspecialchars($row['description']); ?>
                                </p>
                            </div>

                            <div class="resource-actions">
                                <?php 
                                $hasLink = isset($row['link']) && !empty(trim($row['link']));
                                $hasFile = isset($row['file_path']) && !empty(trim($row['file_path']));
                                ?>

                                <?php if ($hasLink): ?>
                                    <a href="<?php echo htmlspecialchars($row['link']); ?>" 
                                       target="_blank" 
                                       class="resource-btn link-btn">
                                        <i class="fas fa-external-link-alt"></i> Visit Link
                                    </a>
                                <?php endif; ?>
                                
                                <?php if ($hasFile): ?>
                                    <a href="uploads/resources/<?php echo htmlspecialchars($row['file_path']); ?>" 
                                       download="<?php echo htmlspecialchars($row['file_name']); ?>"
                                       class="resource-btn download-btn">
                                        <i class="fas fa-download"></i> Download File
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="no-resources">
                        <i class="fas fa-search"></i>
                        <p>No resources found. Please try a different search or category.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
    const searchInput = document.getElementById('search');
    const categorySelect = document.getElementById('category');
    const typeSelect = document.getElementById('type');

    function updateResults() {
        const search = searchInput.value;
        const category = categorySelect.value;
        const type = typeSelect.value;
        window.location.href = `viewAnnouncement.php?search=${encodeURIComponent(search)}&category=${encodeURIComponent(category)}&type=${encodeURIComponent(type)}`;
    }

    searchInput.addEventListener('keyup', function(e) {
        if (e.key === 'Enter') {
            updateResults();
        }
    });

    categorySelect.addEventListener('change', updateResults);
    typeSelect.addEventListener('change', updateResults);
</script>
</body>
</html>