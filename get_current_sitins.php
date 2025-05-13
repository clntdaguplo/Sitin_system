<?php
include("connector.php");

// Set timezone
date_default_timezone_set("Asia/Manila");

// Query to get current sit-ins
$query = "SELECT 
    lr.IDNO,
    lr.FULLNAME,
    lr.PURPOSE,
    lr.LAB_ROOM,
    lr.TIME_IN
FROM login_records lr 
WHERE lr.TIME_OUT IS NULL 
ORDER BY lr.TIME_IN DESC";

$result = mysqli_query($con, $query);
$sitins = array();

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $sitins[] = array(
            'IDNO' => $row['IDNO'],
            'FULLNAME' => $row['FULLNAME'],
            'PURPOSE' => $row['PURPOSE'],
            'LAB_ROOM' => $row['LAB_ROOM'],
            'TIME_IN' => $row['TIME_IN']
        );
    }
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($sitins);
?> 