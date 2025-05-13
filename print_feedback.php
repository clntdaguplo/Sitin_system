<?php
session_start();
include("connector.php");

// Check if the user is logged in
if (!isset($_SESSION['Username'])) {
    header("Location: login.php");
    exit();
}

// Fetch all feedbacks
$query = "SELECT f.*, u.FIRSTNAME, u.MIDNAME, u.LASTNAME, u.IDNO 
          FROM feedback f 
          JOIN user u ON f.USER_ID = u.IDNO 
          WHERE f.FEEDBACK != 'No feedback submitted yet.'
          ORDER BY f.CREATED_AT DESC";
$result = mysqli_query($con, $query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Feedbacks</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        @media print {
            .no-print {
                display: none;
            }
            body {
                margin: 0;
                padding: 0;
            }
            .feedback-container {
                box-shadow: none;
                padding: 0;
            }
        }
        
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background: #f5f5f5;
            min-height: 100vh;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding: 20px;
            background: #14569b;
            color: white;
            width: 100%;
        }
        
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        
        .header p {
            margin: 10px 0 0;
            font-size: 14px;
        }
        
        .button-container {
            display: flex;
            gap: 15px;
            margin: 20px;
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
        }
        
        .action-button {
            background: #14569b;
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
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        
        .action-button:hover {
            background: #0f4578;
            transform: translateY(-2px);
        }
        
        .feedback-container {
            background: white;
            padding: 20px;
            width: 100%;
            min-height: calc(100vh - 100px);
        }
        
        .feedback-item {
            border-bottom: 1px solid #eee;
            padding: 15px 0;
            margin-bottom: 15px;
            max-width: 1200px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .feedback-item:last-child {
            border-bottom: none;
        }
        
        .student-info {
            margin-bottom: 10px;
            color: #14569b;
            font-weight: bold;
            font-size: 1.1em;
        }
        
        .feedback-content {
            margin: 10px 0;
            line-height: 1.6;
            font-size: 1.05em;
            color: #333;
        }
        
        .feedback-date {
            color: #666;
            font-size: 0.9em;
            margin-top: 10px;
        }
        
        .no-feedback {
            text-align: center;
            padding: 40px;
            color: #666;
            font-style: italic;
            font-size: 1.2em;
        }

        @media (max-width: 768px) {
            .button-container {
                position: static;
                justify-content: center;
                margin: 20px 0;
            }
            
            .feedback-item {
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Student Feedback Report</h1>
        <p>Generated on: <?php echo date('M d, Y h:i A'); ?></p>
    </div>
    
    <div class="button-container no-print">
        <button onclick="window.print()" class="action-button">
            <i class="fas fa-print"></i> Print Report
        </button>
    </div>
    
    <div class="feedback-container">
        <?php if (mysqli_num_rows($result) > 0): ?>
            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                <div class="feedback-item">
                    <div class="student-info">
                        <?php 
                        $fullname = $row['FIRSTNAME'] . ' ' . 
                                  (!empty($row['MIDNAME']) ? substr($row['MIDNAME'], 0, 1) . '. ' : '') . 
                                  $row['LASTNAME'];
                        echo htmlspecialchars($fullname) . ' (' . htmlspecialchars($row['IDNO']) . ')';
                        ?>
                    </div>
                    <div class="feedback-content">
                        <?php echo nl2br(htmlspecialchars($row['FEEDBACK'])); ?>
                    </div>
                    <div class="feedback-date">
                        Submitted on: <?php echo date('M d, Y h:i A', strtotime($row['CREATED_AT'])); ?>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="no-feedback">
                No feedback submissions found.
            </div>
        <?php endif; ?>
    </div>
</body>
</html> 