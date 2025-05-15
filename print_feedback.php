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
                background: white;
            }
            .feedback-container {
                box-shadow: none;
                padding: 20px;
                width: 100%;
                margin: 0;
            }
            .feedback-item {
                page-break-inside: avoid;
                border: 1px solid #ddd;
                margin-bottom: 20px;
                padding: 15px;
                width: 100%;
            }
        }
        
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            margin: 0;
            padding: 0;
            background: #f5f5f5;
            min-height: 100vh;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding: 30px;
            background: linear-gradient(45deg,rgb(150, 145, 79),rgb(47, 0, 177));
            color: white;
            width: 100%;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .header p {
            margin: 10px 0 0;
            font-size: 16px;
            opacity: 0.9;
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
            background: linear-gradient(45deg,rgb(150, 145, 79),rgb(47, 0, 177));
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            transition: all 0.3s ease;
        }
        
        .action-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.25);
        }
        
        .feedback-container {
            background: white;
            padding: 30px;
            width: 100%;
            min-height: calc(100vh - 100px);
            max-width: 100%;
            margin: 0;
        }
        
        .feedback-item {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border: 1px solid #eee;
            transition: all 0.3s ease;
            width: 100%;
        }
        
        .feedback-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .student-info {
            margin-bottom: 15px;
            color: rgb(47, 0, 177);
            font-weight: 600;
            font-size: 1.2em;
            padding-bottom: 10px;
            border-bottom: 2px solid #eee;
        }
        
        .feedback-content {
            margin: 15px 0;
            line-height: 1.8;
            font-size: 1.1em;
            color: #333;
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid rgb(150, 145, 79);
        }
        
        .feedback-date {
            color: #666;
            font-size: 0.9em;
            margin-top: 15px;
            text-align: right;
            font-style: italic;
        }
        
        .no-feedback {
            text-align: center;
            padding: 50px;
            color: #666;
            font-style: italic;
            font-size: 1.2em;
            background: #f8f9fa;
            border-radius: 10px;
            margin: 20px 0;
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
            
            .header {
                padding: 20px;
            }
            
            .header h1 {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Student Feedback Report</h1>
        <p>Generated on: <?php echo date('F d, Y h:i A'); ?></p>
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
                        Submitted on: <?php echo date('F d, Y h:i A', strtotime($row['CREATED_AT'])); ?>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="no-feedback">
                <i class="fas fa-info-circle"></i> No feedback submissions found.
            </div>
        <?php endif; ?>
    </div>
</body>
</html> 