-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 10, 2025 at 09:32 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `sitin_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `TITLE` varchar(255) NOT NULL,
  `CONTENT` varchar(255) NOT NULL,
  `CREATED_AT` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `announcements`
--

INSERT INTO `announcements` (`TITLE`, `CONTENT`, `CREATED_AT`) VALUES
('Hey Mayng Buntag!!!', 'Mayng buntag mga tapulan ready namo pa check?', '2025-05-05 23:25:22'),
('Good morning all', 'For your final requirement especially those who were done in presenting their project, upload your zipped/compressed system(Filename: Lastname-FinalProject) including your database(ready to be imported) and in notepad file(admin user and pass) to a gdrive', '2025-05-10 06:45:13');

-- --------------------------------------------------------

--
-- Table structure for table `feedback`
--

CREATE TABLE `feedback` (
  `USER_ID` int(11) NOT NULL,
  `FEEDBACK` text NOT NULL,
  `CREATED_AT` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `LAB_ROOM` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `feedback`
--

INSERT INTO `feedback` (`USER_ID`, `FEEDBACK`, `CREATED_AT`, `LAB_ROOM`) VALUES
(11331, 'Bugnaw kaayu ang Labroom maka hilanat ig gawas sa room', '2025-05-08 14:09:45', '542'),
(11331, 'iLoveyou Jhynnn!', '2025-05-06 14:25:45', '524'),
(369369, 'loveee biiii', '2025-05-08 14:09:04', '528'),
(22653604, 'ilove jhyn\r\n\r\nsigeg balik', '2025-05-08 14:09:21', '530'),
(22653604, 'bugnaw kaayu hahahaha ang room', '2025-05-06 14:26:31', '530'),
(1000, '54004215404602454203564156451546187148017', '2025-05-10 21:45:31', '530');

-- --------------------------------------------------------

--
-- Table structure for table `lab_resources`
--

CREATE TABLE `lab_resources` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `category` varchar(100) NOT NULL,
  `resource_type` varchar(50) NOT NULL,
  `link` text NOT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `upload_date` datetime NOT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `file_type` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lab_schedules`
--

CREATE TABLE `lab_schedules` (
  `id` int(11) NOT NULL,
  `room_number` varchar(10) DEFAULT NULL,
  `day_group` varchar(10) DEFAULT NULL,
  `time_slot` varchar(20) DEFAULT NULL,
  `status` varchar(20) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `lab_schedules`
--

INSERT INTO `lab_schedules` (`id`, `room_number`, `day_group`, `time_slot`, `status`, `notes`, `last_updated`) VALUES
(0, '524', 'MW', '7:30AM-9:00AM', 'Available', NULL, '2025-05-07 22:56:54'),
(0, '524', 'TTH', '7:30AM-9:00AM', 'Available', NULL, '2025-05-07 22:56:54'),
(0, '524', 'F', '7:30AM-9:00AM', 'Available', NULL, '2025-05-07 22:56:54'),
(0, '524', 'S', '7:30AM-9:00AM', 'Available', NULL, '2025-05-07 22:56:54'),
(0, '524', 'MW', '9:00AM-10:30AM', 'Available', NULL, '2025-05-07 22:56:54'),
(0, '524', 'TTH', '9:00AM-10:30AM', 'Available', NULL, '2025-05-07 22:56:54'),
(0, '524', 'F', '9:00AM-10:30AM', 'Available', NULL, '2025-05-07 22:56:54'),
(0, '524', 'S', '9:00AM-10:30AM', 'Available', NULL, '2025-05-07 22:56:54'),
(0, '524', 'MW', '10:30AM-12:00PM', 'Available', NULL, '2025-05-07 22:56:54'),
(0, '524', 'TTH', '10:30AM-12:00PM', 'Available', NULL, '2025-05-07 22:56:54'),
(0, '524', 'F', '10:30AM-12:00PM', 'Available', NULL, '2025-05-07 22:56:54'),
(0, '524', 'S', '10:30AM-12:00PM', 'Available', NULL, '2025-05-07 22:56:54'),
(0, '524', 'MW', '12:00PM-1:00PM', 'Available', NULL, '2025-05-07 22:56:54'),
(0, '524', 'TTH', '12:00PM-1:00PM', 'Available', NULL, '2025-05-07 22:56:54'),
(0, '524', 'F', '12:00PM-1:00PM', 'Available', NULL, '2025-05-07 22:56:54'),
(0, '524', 'S', '12:00PM-1:00PM', 'Available', NULL, '2025-05-07 22:56:54'),
(0, '524', 'MW', '1:00PM-3:00PM', 'Available', NULL, '2025-05-07 22:56:54'),
(0, '524', 'TTH', '1:00PM-3:00PM', 'Available', NULL, '2025-05-07 22:56:54'),
(0, '524', 'F', '1:00PM-3:00PM', 'Available', NULL, '2025-05-07 22:56:54'),
(0, '524', 'S', '1:00PM-3:00PM', 'Available', NULL, '2025-05-07 22:56:54'),
(0, '524', 'MW', '3:00PM-4:30PM', 'Available', NULL, '2025-05-07 22:56:54'),
(0, '524', 'TTH', '3:00PM-4:30PM', 'Available', NULL, '2025-05-07 22:56:54'),
(0, '524', 'F', '3:00PM-4:30PM', 'Available', NULL, '2025-05-07 22:56:54'),
(0, '524', 'S', '3:00PM-4:30PM', 'Available', NULL, '2025-05-07 22:56:54'),
(0, '524', 'MW', '4:30PM-6:00PM', 'Available', NULL, '2025-05-07 22:56:54'),
(0, '524', 'TTH', '4:30PM-6:00PM', 'Available', NULL, '2025-05-07 22:56:54'),
(0, '524', 'F', '4:30PM-6:00PM', 'Available', NULL, '2025-05-07 22:56:54'),
(0, '524', 'S', '4:30PM-6:00PM', 'Available', NULL, '2025-05-07 22:56:54'),
(0, '524', 'MW', '6:00PM-7:30PM', 'Available', NULL, '2025-05-07 22:56:54'),
(0, '524', 'TTH', '6:00PM-7:30PM', 'Available', NULL, '2025-05-07 22:56:54'),
(0, '524', 'F', '6:00PM-7:30PM', 'Available', NULL, '2025-05-07 22:56:54'),
(0, '524', 'S', '6:00PM-7:30PM', 'Available', NULL, '2025-05-07 22:56:54'),
(0, '524', 'MW', '7:30PM-9:00PM', 'Available', NULL, '2025-05-07 22:56:54'),
(0, '524', 'TTH', '7:30PM-9:00PM', 'Available', NULL, '2025-05-07 22:56:54'),
(0, '524', 'F', '7:30PM-9:00PM', 'Available', NULL, '2025-05-07 22:56:54'),
(0, '524', 'S', '7:30PM-9:00PM', 'Available', NULL, '2025-05-07 22:56:54');

-- --------------------------------------------------------

--
-- Table structure for table `login_records`
--

CREATE TABLE `login_records` (
  `IDNO` int(11) NOT NULL,
  `FULLNAME` varchar(255) NOT NULL,
  `TIME_IN` datetime NOT NULL,
  `TIME_OUT` datetime NOT NULL,
  `LAB_ROOM` enum('524','526','528','530','542','544') NOT NULL,
  `PURPOSE` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `login_records`
--

INSERT INTO `login_records` (`IDNO`, `FULLNAME`, `TIME_IN`, `TIME_OUT`, `LAB_ROOM`, `PURPOSE`) VALUES
(11331, 'Clint G Daguplo', '2025-05-06 00:25:45', '2025-05-06 00:25:47', '524', 'Embedded System & IoT'),
(22653604, 'Clnt G. Thegups', '2025-05-06 00:26:31', '2025-05-07 23:55:10', '530', 'Database'),
(987987, 'Mic M Check', '2025-05-08 00:08:46', '2025-05-08 00:37:20', '526', 'C Programming'),
(369369, 'Sy s Arch', '2025-05-08 00:09:04', '2025-05-08 00:37:18', '528', 'Java Programming'),
(22653604, 'Clnt G. Thegups', '2025-05-08 00:09:21', '2025-05-08 00:37:19', '530', 'C# Programming'),
(11331, 'Clint G Daguplo', '2025-05-08 00:09:45', '2025-05-08 00:22:59', '542', 'System Integration & Architecture'),
(11331, 'Daguplo, Clint G.', '2025-05-08 00:22:28', '2025-05-08 00:22:59', '524', 'Database'),
(987987, 'Mic M Check', '2025-05-08 00:45:29', '2025-05-08 02:13:19', '544', 'Database'),
(11331, 'Clint G Daguplo', '2025-05-08 01:43:39', '2025-05-08 02:13:16', '526', 'C Programming'),
(11331, 'Daguplo, Clint G.', '2025-05-08 02:11:06', '2025-05-08 02:13:16', '524', 'Database'),
(11331, 'Daguplo, Clint G.', '2025-05-08 02:15:12', '2025-05-08 02:26:43', '524', 'Database'),
(1000, 'Face S Book', '2025-05-10 14:45:31', '2025-05-10 15:31:25', '530', 'Java Programming');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `type` varchar(50) DEFAULT 'general',
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details`)),
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `message`, `type`, `details`, `is_read`, `created_at`) VALUES
(0, 11331, 'You received 1 point for completing your lab session', 'points', NULL, 1, '2025-05-05 23:25:47'),
(0, 11331, 'Your reservation has been approved', 'approval', '{\"room\":\"524\",\"pc_number\":6,\"date\":\"May 10, 2025\",\"time\":\"09:00 AM\"}', 1, '2025-05-07 23:22:28'),
(0, 11331, 'You received 1 point for completing your lab session', 'points', NULL, 1, '2025-05-07 23:22:59'),
(0, 369369, 'You received 1 point for completing your lab session', 'points', NULL, 1, '2025-05-07 23:37:18'),
(0, 22653604, 'You received 1 point for completing your lab session', 'points', NULL, 1, '2025-05-07 23:37:19'),
(0, 987987, 'You received 1 point for completing your lab session', 'points', NULL, 1, '2025-05-07 23:37:20'),
(0, 11331, 'Your reservation has been approved', 'approval', '{\"room\":\"524\",\"pc_number\":6,\"date\":\"May 10, 2025\",\"time\":\"09:00 AM\"}', 0, '2025-05-08 01:11:06'),
(0, 11331, 'You received 1 point for completing your lab session', 'points', NULL, 0, '2025-05-08 01:13:16'),
(0, 11331, 'Congratulations! You\'ve earned a new session for collecting 3 points!', 'session', NULL, 0, '2025-05-08 01:13:16'),
(0, 987987, 'You received 1 point for completing your lab session', 'points', NULL, 0, '2025-05-08 01:13:19'),
(0, 11331, 'Your reservation has been approved', 'approval', '{\"room\":\"524\",\"pc_number\":6,\"date\":\"May 10, 2025\",\"time\":\"09:00 AM\"}', 0, '2025-05-08 01:15:12'),
(0, 1000, 'You received 1 point for completing your lab session', 'points', NULL, 0, '2025-05-10 07:31:25');

-- --------------------------------------------------------

--
-- Table structure for table `pc_status`
--

CREATE TABLE `pc_status` (
  `id` int(11) NOT NULL,
  `room_number` varchar(10) NOT NULL,
  `pc_number` int(11) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'available',
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pc_status`
--

INSERT INTO `pc_status` (`id`, `room_number`, `pc_number`, `status`, `last_updated`) VALUES
(0, '524', 1, 'available', '2025-05-05 23:29:26'),
(0, '524', 1, 'used', '2025-05-05 23:29:31'),
(0, '524', 1, 'used', '2025-05-05 23:29:34'),
(0, '524', 1, 'available', '2025-05-05 23:29:45'),
(0, '524', 1, 'maintenance', '2025-05-07 23:11:19'),
(0, '524', 2, 'maintenance', '2025-05-07 23:11:19'),
(0, '524', 3, 'maintenance', '2025-05-07 23:11:19'),
(0, '524', 4, 'maintenance', '2025-05-07 23:11:19'),
(0, '524', 5, 'maintenance', '2025-05-07 23:11:19'),
(0, '524', 6, 'used', '2025-05-08 01:15:12'),
(0, '524', 7, 'used', '2025-05-10 07:05:01'),
(0, '524', 8, 'used', '2025-05-10 07:05:01'),
(0, '524', 9, 'used', '2025-05-10 07:05:01'),
(0, '524', 10, 'used', '2025-05-10 07:05:01'),
(0, '524', 11, 'maintenance', '2025-05-10 07:05:07'),
(0, '524', 12, 'maintenance', '2025-05-10 07:05:07'),
(0, '524', 13, 'maintenance', '2025-05-10 07:05:07'),
(0, '524', 14, 'maintenance', '2025-05-10 07:05:07'),
(0, '524', 15, 'maintenance', '2025-05-10 07:05:07'),
(0, '524', 16, 'used', '2025-05-10 07:05:11'),
(0, '524', 17, 'used', '2025-05-10 07:05:11'),
(0, '524', 18, 'used', '2025-05-10 07:05:11'),
(0, '524', 19, 'used', '2025-05-10 07:05:11'),
(0, '524', 20, 'used', '2025-05-10 07:05:11');

-- --------------------------------------------------------

--
-- Table structure for table `points_history`
--

CREATE TABLE `points_history` (
  `ID` int(11) NOT NULL,
  `IDNO` varchar(20) NOT NULL,
  `FULLNAME` varchar(100) NOT NULL,
  `POINTS_EARNED` int(11) DEFAULT 1,
  `CONVERTED_TO_SESSION` tinyint(1) DEFAULT 0,
  `CONVERSION_DATE` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `points_history`
--

INSERT INTO `points_history` (`ID`, `IDNO`, `FULLNAME`, `POINTS_EARNED`, `CONVERTED_TO_SESSION`, `CONVERSION_DATE`) VALUES
(0, '11331', 'Clint G Daguplo', 1, 0, '2025-05-05 09:25:47'),
(0, '11331', 'Clint G Daguplo', 1, 0, '2025-05-07 09:22:59'),
(0, '369369', 'Sy s Arch', 1, 0, '2025-05-07 09:37:18'),
(0, '22653604', 'Clnt G. Thegups', 1, 0, '2025-05-07 09:37:19'),
(0, '987987', 'Mic M Check', 1, 0, '2025-05-07 09:37:20'),
(0, '11331', 'Clint G Daguplo', 3, 1, '2025-05-07 11:13:16'),
(0, '987987', 'Mic M Check', 1, 0, '2025-05-07 11:13:19'),
(0, '1000', 'Face S Book', 1, 0, '2025-05-10 00:31:25');

-- --------------------------------------------------------

--
-- Table structure for table `reservations`
--

CREATE TABLE `reservations` (
  `id` int(11) NOT NULL,
  `student_id` varchar(50) DEFAULT NULL,
  `fullname` varchar(100) DEFAULT NULL,
  `room` varchar(50) DEFAULT NULL,
  `date` date DEFAULT NULL,
  `time` time DEFAULT NULL,
  `purpose` varchar(100) DEFAULT NULL,
  `seat_number` int(11) DEFAULT NULL,
  `remaining_sessions` int(11) DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reservations`
--

INSERT INTO `reservations` (`id`, `student_id`, `fullname`, `room`, `date`, `time`, `purpose`, `seat_number`, `remaining_sessions`, `status`, `created_at`) VALUES
(0, '11331', 'Clint G Daguplo', '524', '2025-05-10', '09:00:00', 'Database', 6, 29, 'approved', '2025-05-10 06:53:29'),
(0, '11331', 'Clint G Daguplo', '530', '2025-05-20', '13:00:00', 'C# Programming', 1, 29, 'approved', '2025-05-10 06:53:29'),
(0, '369369', 'Sy s Arch', '526', '2025-05-09', '17:30:00', 'Embedded System & IoT', 1, 29, 'approved', '2025-05-10 06:53:29'),
(0, '369369', 'Sy s Arch', '530', '2025-05-12', '10:30:00', 'Java Programming', 40, 29, 'approved', '2025-05-10 06:53:29'),
(0, '1000', 'Face S Book', '524', '2025-05-16', '09:00:00', '09:00', 1, 30, 'approved', '2025-05-10 06:53:29'),
(0, '3000', 'Gram T Insta', '526', '2025-05-14', '13:00:00', 'Mobile Application', 40, 30, 'approved', '2025-05-10 06:53:29'),
(0, '4000', 'iPhone V ProMax', '524', '2025-05-13', '10:30:00', 'Computer Application', 40, 30, 'pending', '2025-05-10 07:02:19'),
(0, '1000', 'Face S Book', '544', '2025-05-16', '13:00:00', 'Others...', 20, 30, 'pending', '2025-05-10 07:03:18');

--
-- Triggers `reservations`
--
DELIMITER $$
CREATE TRIGGER `before_reservation_insert` BEFORE INSERT ON `reservations` FOR EACH ROW BEGIN
    DECLARE student_exists INT;
    SELECT COUNT(*) INTO student_exists 
    FROM user 
    WHERE IDNO = NEW.student_id;
    
    IF student_exists = 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Invalid student ID';
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `reward_points`
--

CREATE TABLE `reward_points` (
  `id` int(11) NOT NULL,
  `student_id` varchar(50) DEFAULT NULL,
  `points` int(11) DEFAULT 0,
  `last_reward_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user`
--

CREATE TABLE `user` (
  `IDNO` int(11) NOT NULL,
  `LASTNAME` varchar(255) NOT NULL,
  `FIRSTNAME` varchar(255) NOT NULL,
  `MIDNAME` varchar(255) NOT NULL,
  `COURSE` varchar(255) NOT NULL,
  `YEARLEVEL` int(11) NOT NULL,
  `USERNAME` varchar(255) NOT NULL,
  `PASSWORD` varchar(255) NOT NULL,
  `EMAIL` varchar(255) NOT NULL,
  `PROFILE_PIC` varchar(255) NOT NULL DEFAULT 'default.jpg',
  `REMAINING_SESSIONS` int(30) NOT NULL DEFAULT 30,
  `POINTS` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user`
--

INSERT INTO `user` (`IDNO`, `LASTNAME`, `FIRSTNAME`, `MIDNAME`, `COURSE`, `YEARLEVEL`, `USERNAME`, `PASSWORD`, `EMAIL`, `PROFILE_PIC`, `REMAINING_SESSIONS`, `POINTS`) VALUES
(11331, 'Daguplo', 'Clint', 'G', 'BSIT', 3, 'clint', '123123', 'clint@gmail.com', 'default.jpg', 27, 3),
(1000, 'Book', 'Face', 'S', 'BSCRIM', 3, 'face', '123123', 'face@gmail.com', 'superfly.jpg', 29, 1),
(2000, 'Tube', 'You', 'C', 'BSHM', 3, 'you', '123123', 'you@gmail.com', 'default.jpg', 30, 0),
(3000, 'Insta', 'Gram', 'T', 'BSCS', 3, 'gram', '123123', 'gram@gmail.com', 'default.jpg', 30, 0),
(4000, 'ProMax', 'iPhone', 'V', 'BEED', 3, 'iphone', '123123', 'iphone@gmail.com', 'default.jpg', 30, 0),
(5000, 'Unli', 'Scatter', 'V', 'BEED', 3, 'scatter', '123123', 'scatter@gmail.com', 'default.jpg', 30, 0),
(6000, 'Helix', 'Cabal', 'V', 'BEED', 3, 'cabal', '123123', 'cabal@gmail.com', 'default.jpg', 30, 0),
(22653604, 'Thegups', 'Clnt', 'G.', 'BSCS', 2, 'clintclint', '123123', 'clntthegups@gmail.com', 'default.jpg', 28, 1),
(987987, 'Check', 'Mic', 'M', 'BSBA', 1, 'mic', 'check', 'miccheck@gmail.com', 'patata.jpg', 28, 2),
(369369, 'Arch', 'Sy', 's', 'BSED', 3, 'sysarch', '123123', 'sysarch@gmail.com', 'default.jpg', 29, 1);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
