<?php
session_start();
include("connector.php");

// Check if admin is logged in
if (!isset($_SESSION['Username'])) {
    header("Location: login.php");
    exit();
}

// Check if ID is provided
if (!isset($_GET['id'])) {
    die("No resource ID provided");
}

$id = mysqli_real_escape_string($con, $_GET['id']);

// Get file information from database
$query = "SELECT file_name, file_path, file_type FROM lab_resources WHERE id = ?";
$stmt = $con->prepare($query);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $file_path = 'uploads/resources/' . $row['file_path'];
    $file_name = $row['file_name'];
    $file_type = $row['file_type'];

    // Check if file exists
    if (file_exists($file_path)) {
        // Set headers for file download
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $file_name . '"');
        header('Content-Length: ' . filesize($file_path));
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        // Output file
        readfile($file_path);
        exit();
    } else {
        die("File not found");
    }
} else {
    die("Resource not found");
}
?> 