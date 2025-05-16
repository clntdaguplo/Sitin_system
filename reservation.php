<?php
session_start();
include("connector.php");

// Check if the user is logged in
if (!isset($_SESSION['Username'])) {
    header("Location: login.php");
    exit();
}

// Fetch user details
$username = $_SESSION['Username'];
$user_query = "SELECT IDNO, FIRSTNAME, MIDNAME, LASTNAME, REMAINING_SESSIONS, PROFILE_PIC 
               FROM user 
               WHERE USERNAME = ?";
$stmt = $con->prepare($user_query);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    $user_data = $result->fetch_assoc();
    $profile_pic = !empty($user_data['PROFILE_PIC']) ? htmlspecialchars($user_data['PROFILE_PIC']) : 'default.jpg';
    $user_name = htmlspecialchars($user_data['LASTNAME'] . ' ' . substr($user_data['FIRSTNAME'], 0, 1) . '.');
} else {
    $profile_pic = 'default.jpg';
    $user_name = 'User';
    // Redirect if user data not found
    header("Location: login.php");
    exit();
}

// Fetch purposes from database
$purpose_query = "SELECT DISTINCT PURPOSE FROM login_records WHERE PURPOSE IS NOT NULL";
$purpose_result = $con->query($purpose_query);
$purposes = [];
while($row = $purpose_result->fetch_assoc()) {
    if (!empty($row['PURPOSE'])) {
        $purposes[] = $row['PURPOSE'];
    }
}

// Include the database connection
include 'connector.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

$taken_seats = [];
$reservation_date = '';
$time_slot = '';
$lab_classroom = '';

// Check Availability functionality
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['check_availability'])) {
    $reservation_date = $_POST['reservation_date'];
    $time_slot = $_POST['time_slot'];
    $lab_classroom = $_POST['lab_classroom'];

    // Query to get reserved seats for the selected date, time slot, and classroom
    $sql = "SELECT seat_number FROM lab_reservations
            WHERE reservation_date = '$reservation_date'
            AND time_slot = '$time_slot'
            AND lab_classroom = '$lab_classroom'";

    $result = mysqli_query($conn, $sql);

    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $taken_seats[] = $row['seat_number'];
        }
    }
}

// Reserve Now functionality
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reserve'])) {
    $student_id = "12345"; // Placeholder: Replace this with the actual logged-in user ID
    $reservation_date = $_POST['reservation_date'];
    $time_slot = $_POST['time_slot'];
    $lab_classroom = $_POST['lab_classroom'];
    $seat_number = $_POST['seat_number'];
    $status = "pending";

    echo "<script>alert('Reserve button clicked! Processing reservation...');</script>";

    // Insert the reservation
    $sql = "INSERT INTO lab_reservations (student_id, lab_classroom, seat_number, reservation_date, time_slot, status)
            VALUES ('$student_id', '$lab_classroom', '$seat_number', '$reservation_date', '$time_slot', '$status')";

    if (mysqli_query($conn, $sql)) {
        echo "<p style='color: green;'>Reservation submitted successfully! Status: Pending</p>";
    } else {
        echo "<p style='color: red;'>Error: " . mysqli_error($conn) . "</p>";
    }

    mysqli_close($conn);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservation</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
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

        /* Content Area */
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

        .reservation-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            height: calc(100vh - 100px);
            display: flex;
            flex-direction: column;
        }

        .reservation-title {
            background: rgb(26, 19, 46);
            color: white;
            padding: 20px 25px;
            border-radius: 15px 15px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .reservation-title h1 {
            font-size: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 0;
        }

        .back-button {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .back-button:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
        }

        .reservation-form {
            padding: 0;
            max-height: none;
            overflow: visible;
        }

        .student-info {
            background: #f8fafc;
            padding: 20px;
            margin-bottom: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .student-info-title {
            color: #14569b;
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-row {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
        }

        .form-group {
            flex: 1;
        }

        .form-group label {
            display: block;
            color: #4a5568;
            font-size: 0.9rem;
            margin-bottom: 6px;
            font-weight: 500;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus {
            border-color: rgb(47, 0, 177);
            outline: none;
            box-shadow: 0 0 0 3px rgba(47, 0, 177, 0.1);
        }

        .student-info input {
            background: #fff;
            color: #2d3748;
            font-weight: 500;
        }

        .remaining-sessions input {
            color: #14569b;
            font-weight: 600;
            text-align: center;
        }

        .submit-btn {
            background: rgb(3, 133, 57);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            display: block;
            margin: 25px auto 0;
            width: fit-content;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(47, 0, 177, 0.2);
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.4);
        }

        .modal-content {
            background-color: white;
            margin: 2% auto;
            padding: 30px;
            border-radius: 15px;
            width: 90%;
            max-width: 1000px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e2e8f0;
        }

        .modal-header h2 {
            color: rgb(47, 0, 177);
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 0;
        }

        .close {
            color: #718096;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .close:hover {
            color: #2d3748;
        }

        .seat-grid {
            display: grid;
            grid-template-columns: repeat(8, 1fr);
            gap: 12px;
            padding: 20px;
            background: #f8fafc;
            border-radius: 10px;
            margin: 0 auto;
            max-width: 1000px;
        }

        .seat {
            aspect-ratio: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 10px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.9rem;
            font-weight: 500;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .seat small {
            font-size: 0.75rem;
            margin-top: 4px;
            opacity: 0.8;
        }

        /* Status-specific styles */
        .seat.available {
            background: #dcfce7;
            border: 2px solid #22c55e;
            color: #166534;
        }

        .seat.used {
            background: #fee2e2;
            border: 2px solid #ef4444;
            color: #991b1b;
            cursor: not-allowed;
        }

        .seat.maintenance {
            background: #fef3c7;
            border: 2px solid #f59e0b;
            color: #92400e;
            cursor: not-allowed;
        }

        .seat.reserved {
            background: #e0e7ff;
            border: 2px solid rgb(3, 7, 202);
            color:rgb(90, 178, 255);
            cursor: not-allowed;
        }

        .seat:hover:not(.used):not(.maintenance):not(.reserved) {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        /* Responsive adjustments */
        @media (max-width: 1024px) {
            .seat-grid {
                grid-template-columns: repeat(6, 1fr);
            }
        }

        @media (max-width: 768px) {
            .seat-grid {
                grid-template-columns: repeat(4, 1fr);
            }
        }

        @media (max-width: 480px) {
            .seat-grid {
                grid-template-columns: repeat(3, 1fr);
                gap: 8px;
            }
            
            .seat {
                font-size: 0.8rem;
            }
        }

        /* Add to your existing styles */
        .status-filters {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }

        .filter-btn {
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            background: #e2e8f0;
            color: #4a5568;
        }

        .filter-btn.active {
            background: rgb(26, 19, 46);
            color: white;
        }

        .cancel-btn {
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
            background: #ef4444;
            color: white;
            transition: all 0.3s ease;
        }

        .cancel-btn:hover {
            background: #dc2626;
        }

        /* Add these CSS rules to your existing style section */
        .reservation-form-container {
            display: flex;
            gap: 25px;
            padding: 25px;
            height: calc(100vh - 90px);
            overflow: hidden;
        }

        .form-section {
            flex: 1;
            background: white;
            border-radius: 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            overflow-y: auto;
            padding: 20px;
        }

        .requests-section {
            flex: 1;
            background: white;
            border-radius: 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            overflow-y: auto;
            padding: 20px;
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
    <div class="reservation-container">
        <div class="reservation-title">
            <h1>
                <i class="fas fa-calendar-alt"></i>
                Reservation
            </h1>
            <a>
            </a>
        </div>
        <div class="reservation-form-container">
            <div class="form-section">
                <form method="POST" action="" class="reservation-form" onsubmit="return validateForm()">
                    <!-- Student Info Section -->
                    <div class="student-info">
                        <div class="student-info-title">
                            <i class="fas fa-user-graduate"></i>
                            Student Information
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Student ID</label>
                                <input type="text" 
                                    value="<?php echo isset($user_data['IDNO']) ? htmlspecialchars($user_data['IDNO']) : ''; ?>" 
                                    readonly>
                            </div>
                            <div class="form-group">
                                <label>Student Name</label>
                                <input type="text" 
                                    value="<?php 
                                        echo isset($user_data['FIRSTNAME']) ? 
                                            htmlspecialchars($user_data['FIRSTNAME'] . ' ' . 
                                            $user_data['MIDNAME'] . ' ' . 
                                            $user_data['LASTNAME']) : ''; 
                                    ?>" 
                                    readonly>
                            </div>
                            <div class="form-group remaining-sessions">
                                <label>Available Sessions</label>
                                <input type="text" 
                                    value="<?php echo isset($user_data['REMAINING_SESSIONS']) ? htmlspecialchars($user_data['REMAINING_SESSIONS']) : '0'; ?>" 
                                    readonly>
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="room">Room:</label>
                            <select name="room" id="room" required>
                                <option value="">Select Room</option>
                                <option value="524">Room 524</option>
                                <option value="526">Room 526</option>
                                <option value="528">Room 528</option>
                                <option value="530">Room 530</option>
                                <option value="542">Room 542</option>
                                <option value="544">Room 544</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="date">Date:</label>
                            <input type="date" name="date" id="date" required min="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="time">Time:</label>
                            <select name="time" id="time" required>
                                <option value="">Select Time</option>
                                <option value="07:30">7:30 AM</option>
                                <option value="09:00">9:00 AM</option>
                                <option value="10:30">10:30 AM</option>
                                <option value="13:00">1:00 PM</option>
                                <option value="14:30">2:30 PM</option>
                                <option value="16:00">4:00 PM</option>
                                <option value="17:30">5:30 PM</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="seat_number">Seat Number:</label>
                            <div class="seat-input-group">
                                <input type="number" name="seat_number" id="seat_number" min="1" max="40" required readonly>
                                <button type="button" onclick="openSeatModal()" class="select-seat-btn">
                                    <i class="fas fa-chair"></i> Select Seat
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="purpose">Purpose:</label>
                        <select name="purpose" id="purpose" required>
                            <option value="">Select Time</option>
                            <option value="Select">Select</option>
                            <option value="C Programming">C Programming</option>
                            <option value="Java Programming">Java Programming</option>
                            <option value="C# Programming">C# Programming</option>
                            <option value="System Integration & Architecture">System Integration & Architecture</option>
                            <option value="Embedded System & IoT">Embedded System & IoT</option>
                            <option value="Digital logic & Design">Digital logic & Design</option>
                            <option value="Computer Application">Computer Application</option>
                            <option value="Database">Database</option>
                            <option value="Project Management">Project Management</option>
                            <option value="Python Programming">Python Programming</option>
                            <option value="Mobile Application">Mobile Application</option>
                            <option value="Others...">Others...</option>
                        </select>
                    </div>

                    <!-- Add this modal HTML at the bottom of your form but before the submit button -->
                    <div id="seatModal" class="modal">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h2>Select a Seat</h2>
                                <span class="close">&times;</span>
                            </div>
                            <div class="seat-legend">
                                <div class="legend-item">
                                    <div class="legend-color" style="background: #dcfce7; border: 2px solid #22c55e;"></div>
                                    <span>Available</span>
                                </div>
                                <div class="legend-item">
                                    <div class="legend-color" style="background: #fee2e2; border: 2px solid #ef4444;"></div>
                                    <span>In Use</span>
                                </div>
                                <div class="legend-item">
                                    <div class="legend-color" style="background: #fef3c7; border: 2px solid #f59e0b;"></div>
                                    <span>Maintenance</span>
                                </div>
                                <div class="legend-item">
                                    <div class="legend-color" style="background: #e0e7ff; border: 2px solid #6366f1;"></div>
                                    <span>Reserved</span>
                                </div>
                            </div>
                            <div class="seat-grid">
                                <?php for($i = 1; $i <= 40; $i++): ?>
                                    <div class="seat available" data-seat="<?php echo $i; ?>" onclick="selectSeat(<?php echo $i; ?>)">
                                        PC<?php echo $i; ?>
                                    </div>
                                <?php endfor; ?>
                            </div>
                        </div>
                    </div>

                    <button type="submit" name="submit" class="submit-btn">Submit Reservation</button>
                </form>
            </div>
            <div class="requests-section">
                <div class="reservation-history">
                    <div class="history-header">
                        <h2><i class="fas fa-clock"></i> Reservation Requests</h2>
                        <div class="status-filters">
                            <button class="filter-btn active" data-status="pending">Pending</button>
                            <button class="filter-btn" data-status="approved">Approved</button>
                            <button class="filter-btn" data-status="rejected">Rejected</button>
                        </div>
                    </div>
                    <div class="history-table-container">
                        <table class="history-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Room</th>
                                    <th>Seat</th>
                                    <th>Purpose</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $history_query = "SELECT * FROM reservations 
                                                WHERE student_id = ? 
                                                ORDER BY date DESC, time DESC";
                                $stmt = $con->prepare($history_query);
                                $stmt->bind_param("s", $user_data['IDNO']);
                                $stmt->execute();
                                $history_result = $stmt->get_result();

                                while ($reservation = $history_result->fetch_assoc()) {
                                    $status_class = '';
                                    switch(strtolower($reservation['status'])) {
                                        case 'approved': $status_class = 'status-approved'; break;
                                        case 'pending': $status_class = 'status-pending'; break;
                                        case 'rejected': $status_class = 'status-rejected'; break;
                                    }
                                    ?>
                                    <tr class="reservation-row" data-status="<?php echo strtolower($reservation['status']); ?>" 
                                        style="display: <?php echo $reservation['status'] === 'pending' ? '' : 'none'; ?>">
                                        <td><?php echo date('M d, Y', strtotime($reservation['date'])); ?></td>
                                        <td><?php echo date('h:i A', strtotime($reservation['time'])); ?></td>
                                        <td>Room <?php echo htmlspecialchars($reservation['room']); ?></td>
                                        <td>PC <?php echo htmlspecialchars($reservation['seat_number']); ?></td>
                                        <td><?php echo htmlspecialchars($reservation['purpose']); ?></td>
                                        <td><span class="status-badge <?php echo $status_class; ?>">
                                            <?php echo ucfirst(htmlspecialchars($reservation['status'])); ?>
                                        </span></td>
                                        <td>
                                            <?php if($reservation['status'] === 'pending'): ?>
                                                <button class="cancel-btn" onclick="cancelReservation(<?php echo $reservation['id']; ?>)">
                                                    <i class="fas fa-times"></i> Cancel
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php
                                }
                                if ($history_result->num_rows === 0) {
                                    echo '<tr><td colspan="7" class="no-records">No reservations found</td></tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Global variables
let selectedRoom = '';
let currentStatuses = {};

// Initialize room selection
document.getElementById('room').addEventListener('change', function() {
    selectedRoom = this.value;
    if (selectedRoom) {
        updateSeatAvailability();
    }
});

// Update when date or time changes
document.getElementById('date').addEventListener('change', function() {
    if (!selectedRoom) {
        alert('Please select a room first');
        this.value = '';
        return;
    }
    updateSeatAvailability();
});

document.getElementById('time').addEventListener('change', function() {
    if (!selectedRoom) {
        alert('Please select a room first');
        this.value = '';
        return;
    }
    updateSeatAvailability();
});

// Replace the existing updateSeatAvailability function

async function updateSeatAvailability() {
    const room = document.getElementById('room').value;
    const date = document.getElementById('date').value;
    const time = document.getElementById('time').value;

    if (!room) {
        alert('Please select a room first');
        return;
    }

    try {
        // Get PC statuses AND reservations in one request
        const response = await fetch('get_seat_status.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                room: room,
                date: date,
                time: time 
            })
        });
        
        const data = await response.json();
        if (!data.success) {
            throw new Error(data.message);
        }

        // Update seats in the modal
        document.querySelectorAll('.seat').forEach(seat => {
            const seatNumber = parseInt(seat.getAttribute('data-seat'));
            
            // Reset seat to default state
            seat.className = 'seat available';
            seat.onclick = () => selectSeat(seatNumber);

            // Check if seat has an approved reservation
            const approvedReservation = data.reservations.find(r => 
                r.seat_number === seatNumber && r.status === 'approved'
            );

            // Check if seat has a pending reservation
            const pendingReservation = data.reservations.find(r => 
                r.seat_number === seatNumber && r.status === 'pending'
            );

            // Check PC status from admin settings
            const pcStatus = data.pcStatus.find(pc => 
                parseInt(pc.pc_number) === seatNumber
            );

            if (approvedReservation) {
                // Show as used (red) if reservation is approved
                seat.className = 'seat used';
                seat.onclick = null;
                statusText = 'In Use';
            } else if (pendingReservation) {
                // Show as reserved (blue) if reservation is pending
                seat.className = 'seat reserved';
                seat.onclick = null;
                statusText = 'Reserved';
            } else if (pcStatus) {
                // Use admin-set status if no reservation
                seat.className = `seat ${pcStatus.status}`;
                statusText = pcStatus.status.charAt(0).toUpperCase() + pcStatus.status.slice(1);
                if (pcStatus.status === 'used' || pcStatus.status === 'maintenance') {
                    seat.onclick = null;
                }
            } else {
                statusText = 'Available';
            }

            // Update seat display
            seat.innerHTML = `PC${seatNumber}<br><small>(${statusText})</small>`;
        });

    } catch (error) {
        console.error('Error:', error);
        alert('Error loading seat availability');
    }
}

function openSeatModal() {
    if (!selectedRoom) {
        alert('Please select a room first');
        return;
    }
    modal.style.display = "block";
    updateSeatAvailability();
}

function selectSeat(seatNumber) {
    const status = currentStatuses[seatNumber];
    if (status === 'used' || status === 'maintenance') {
        alert(`This PC is currently ${status}`);
        return;
    }
    document.getElementById('seat_number').value = seatNumber;
    modal.style.display = "none";
}

// Modal controls
const modal = document.getElementById("seatModal");
const closeBtn = document.getElementsByClassName("close")[0];

closeBtn.onclick = function() {
    modal.style.display = "none";
}

window.onclick = function(event) {
    if (event.target == modal) {
        modal.style.display = "none";
    }
}

// Form validation
function validateForm() {
    const room = document.getElementById('room').value;
    const date = document.getElementById('date').value;
    const time = document.getElementById('time').value;
    const seat = document.getElementById('seat_number').value;
    const purpose = document.getElementById('purpose').value;

    if (!room || !date || !time || !seat || !purpose) {
        alert('Please fill in all required fields.');
        return false;
    }

    const selectedDate = new Date(date);
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    if (selectedDate < today) {
        alert('Please select a future date.');
        return false;
    }

    if (seat < 1 || seat > 40) {
        alert('Please select a valid seat.');
        return false;
    }

    const status = currentStatuses[seat];
    if (status === 'used' || status === 'maintenance') {
        alert(`Cannot reserve this PC. Status: ${status}`);
        return false;
    }

    return true;
}

// Auto-update every 30 seconds if modal is open
setInterval(() => {
    if (modal.style.display === 'block' && selectedRoom) {
        updateSeatAvailability();
    }
}, 30000);

// Add to your existing JavaScript
document.querySelectorAll('.filter-btn').forEach(button => {
    button.addEventListener('click', () => {
        // Remove active class from all buttons
        document.querySelectorAll('.filter-btn').forEach(btn => btn.classList.remove('active'));
        button.classList.add('active');
        
        const status = button.dataset.status;
        document.querySelectorAll('.reservation-row').forEach(row => {
            if (row.dataset.status === status) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    });
});

function cancelReservation(reservationId) {
    if (confirm('Are you sure you want to cancel this reservation?')) {
        fetch('cancel_reservation.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                reservation_id: reservationId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Reservation cancelled successfully');
                location.reload();
            } else {
                alert('Error cancelling reservation: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error cancelling reservation');
        });
    }
}
</script>

<?php
if(isset($_POST['submit'])) {
    // Verify user data exists
    if (!isset($user_data) || !isset($user_data['IDNO'])) {
        echo "<script>
            alert('Error: User data not found. Please login again.');
            window.location.href='login.php';
        </script>";
        exit;
    }

    $student_id = $user_data['IDNO'];
    $fullname = $user_data['FIRSTNAME'] . ' ' . $user_data['MIDNAME'] . ' ' . $user_data['LASTNAME'];
    $room = $_POST['room'];
    $date = $_POST['date'];
    $time = $_POST['time'];
    $purpose = $_POST['purpose'];
    $seat_number = $_POST['seat_number'];
    $remaining_sessions = $user_data['REMAINING_SESSIONS'];
    
    // Check lab schedule availability
    $lab_availability = checkLabAvailability($con, $room, $date, $time);
    if (!$lab_availability['available']) {
        echo "<script>alert('" . $lab_availability['message'] . "');</script>";
        exit;
    }

    // Check seat availability
    if (!checkSeatAvailability($con, $room, $date, $time, $seat_number)) {
        echo "<script>alert('This seat is already reserved for the selected date and time.');</script>";
        exit;
    }

    // Check PC status
    $status_query = "SELECT status FROM pc_status WHERE room_number = ? AND pc_number = ?";
    $stmt = $con->prepare($status_query);
    $stmt->bind_param("si", $room, $seat_number);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if($result->num_rows > 0) {
        $pc_status = $result->fetch_assoc()['status'];
        if($pc_status !== 'available') {
            echo "<script>alert('Selected PC is not available. Current status: " . 
                  ucfirst($pc_status) . "');</script>";
            exit;
        }
    }

    // Check if student has remaining sessions
    if($remaining_sessions <= 0) {
        echo "<script>alert('You have no remaining sessions.');</script>";
        exit;
    }

    $insert_query = "INSERT INTO reservations 
                    (student_id, fullname, room, date, time, purpose, 
                     seat_number, remaining_sessions, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')";
    $insert_stmt = $con->prepare($insert_query);
    $insert_stmt->bind_param("ssssssii", 
        $student_id, $fullname, $room, $date, $time, 
        $purpose, $seat_number, $remaining_sessions
    );
    
    if($insert_stmt->execute()) {
        echo "<script>
            alert('Reservation submitted successfully!'); 
            window.location.href='reservation.php';
        </script>";
    } else {
        echo "<script>
            alert('Error submitting reservation. Please try again.');
        </script>";
    }
}
?>

<?php
// Add this after your database connection
function checkLabAvailability($con, $room, $date, $time) {
    // Convert date to day of week
    $day_map = [
        '1' => 'MW', // Monday
        '2' => 'TTH', // Tuesday
        '3' => 'MW', // Wednesday
        '4' => 'TTH', // Thursday
        '5' => 'F', // Friday
        '6' => 'S', // Saturday
    ];
    
    $day_of_week = date('N', strtotime($date));
    $day_group = $day_map[$day_of_week] ?? null;
    
    if (!$day_group) {
        return ['available' => false, 'message' => 'Laboratory is closed on this day'];
    }

    // Convert time to time slot
    $time_slots = [
        '07:30' => '7:30AM-9:00AM',
        '09:00' => '9:00AM-10:30AM',
        '10:30' => '10:30AM-12:00PM',
        '13:00' => '1:00PM-3:00PM',
        '14:30' => '3:00PM-4:30PM',
        '16:00' => '4:30PM-6:00PM',
        '17:30' => '6:00PM-7:30PM'
    ];
    
    $time_slot = $time_slots[$time] ?? null;
    
    if (!$time_slot) {
        return ['available' => false, 'message' => 'Invalid time slot'];
    }

    // Check lab_schedules table
    $query = "SELECT status FROM lab_schedules 
              WHERE room_number = ? 
              AND day_group = ? 
              AND time_slot = ?";
    
    $stmt = $con->prepare($query);
    $stmt->bind_param("sss", $room, $day_group, $time_slot);
    $stmt->execute();
    $result = $stmt->get_result();
    $schedule = $result->fetch_assoc();

    if ($schedule && $schedule['status'] === 'Occupied') {
        return ['available' => false, 'message' => 'This time slot is occupied according to lab schedule'];
    }

    return ['available' => true, 'message' => 'Available'];
}

// Function to check if seat is available
function checkSeatAvailability($con, $room, $date, $time, $seat) {
    $query = "SELECT COUNT(*) as count FROM reservations 
              WHERE room = ? AND date = ? AND time = ? AND seat_number = ? 
              AND status != 'rejected'";
    
    $stmt = $con->prepare($query);
    $stmt->bind_param("sssi", $room, $date, $time, $seat);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    return $row['count'] == 0;
}
?>
</body>
</html>