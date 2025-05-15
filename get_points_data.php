<?php
session_start();
include("connector.php");

if (!isset($_SESSION['Username'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit();
}

$username = $_SESSION['Username'];

// Get current points
$current_points_query = "SELECT POINTS FROM user WHERE USERNAME = '$username'";
$current_points_result = mysqli_query($con, $current_points_query);
$current_points = mysqli_fetch_assoc($current_points_result)['POINTS'] ?? 0;

// Get today's points
$today = date('Y-m-d');
$today_points_query = "SELECT SUM(POINTS_EARNED) as today_points FROM points_history 
                      WHERE USERNAME = '$username' AND DATE(EARNED_DATE) = '$today'";
$today_points_result = mysqli_query($con, $today_points_query);
$today_points = mysqli_fetch_assoc($today_points_result)['today_points'] ?? 0;

// Get this week's points
$week_start = date('Y-m-d', strtotime('monday this week'));
$week_points_query = "SELECT SUM(POINTS_EARNED) as week_points FROM points_history 
                     WHERE USERNAME = '$username' AND DATE(EARNED_DATE) >= '$week_start'";
$week_points_result = mysqli_query($con, $week_points_query);
$week_points = mysqli_fetch_assoc($week_points_result)['week_points'] ?? 0;

// Get this month's points
$month_start = date('Y-m-01');
$month_points_query = "SELECT SUM(POINTS_EARNED) as month_points FROM points_history 
                      WHERE USERNAME = '$username' AND DATE(EARNED_DATE) >= '$month_start'";
$month_points_result = mysqli_query($con, $month_points_query);
$month_points = mysqli_fetch_assoc($month_points_result)['month_points'] ?? 0;

// Get points history
$history_query = "SELECT DESCRIPTION, EARNED_DATE, POINTS_EARNED 
                 FROM points_history 
                 WHERE USERNAME = '$username' 
                 ORDER BY EARNED_DATE DESC 
                 LIMIT 10";
$history_result = mysqli_query($con, $history_query);
$history = [];

while ($row = mysqli_fetch_assoc($history_result)) {
    $history[] = [
        'description' => $row['DESCRIPTION'],
        'date' => date('M j, Y g:i A', strtotime($row['EARNED_DATE'])),
        'points' => $row['POINTS_EARNED']
    ];
}

// Prepare response
$response = [
    'current_points' => $current_points,
    'today_points' => $today_points,
    'week_points' => $week_points,
    'month_points' => $month_points,
    'total_points' => $current_points,
    'history' => $history
];

header('Content-Type: application/json');
echo json_encode($response);
?> 