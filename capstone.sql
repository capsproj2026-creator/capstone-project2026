-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 21, 2026 at 06:20 AM
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
-- Database: `capstone`
--

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `id` int(11) NOT NULL,
  `departmentcode` varchar(10) NOT NULL,
  `departmentname` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`id`, `departmentcode`, `departmentname`) VALUES
(1, 'CCS', 'College of Computer Studies'),
(2, 'CHS', 'College of Health Sciences'),
(3, 'CEA', 'College of Engineering and Architecture'),
(4, 'CTDE', 'College of Teacher Education'),
(5, 'CAS', 'College of Arts & Sciences'),
(6, 'CTHBM', 'College of Tourism, Hospitality and Business Management');

-- --------------------------------------------------------

--
-- Table structure for table `gate_logs`
--

CREATE TABLE `gate_logs` (
  `id` int(11) NOT NULL,
  `daily_log_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` enum('In','Out') NOT NULL,
  `log_date` date NOT NULL DEFAULT curdate(),
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Triggers `gate_logs`
--
DELIMITER $$
CREATE TRIGGER `before_insert_gate_logs_reset` BEFORE INSERT ON `gate_logs` FOR EACH ROW BEGIN
    DECLARE last_daily_id INT;

    -- Look for the highest daily_log_id recorded for TODAY only
    SELECT MAX(daily_log_id) INTO last_daily_id 
    FROM gate_logs 
    WHERE log_date = CURDATE();

    -- Reset logic: If no records today, start at 1. Else, increment.
    IF last_daily_id IS NULL THEN
        SET NEW.daily_log_id = 1;
    ELSE
        SET NEW.daily_log_id = last_daily_id + 1;
    END IF;

    -- Ensure the date is explicitly set to today
    SET NEW.log_date = CURDATE();
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `general_informations`
--

CREATE TABLE `general_informations` (
  `id` int(11) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `general_informations`
--

INSERT INTO `general_informations` (`id`, `description`) VALUES
(1, 'The CSPC-designated parking areas are on a \"first come, first served\" basis. Having a parking stickers does not guaranted a parking space but provides the privilege to park in any vacant and designated parking space.'),
(2, 'Parking is authorized only in the designated parking areas.'),
(3, 'Drivers of vehicle parked on CSPC-assigned parking spaces shall beer their own risk. The College shall not be liable for any loss or damage to any vehicle or other property or any damage or injury to any person arising from or for the prevention of ingress to egress from the parking spaces caused by the use or attempted use by any person of the parking spaces or any parking spaces thereof, except in the case of negligence of the part of the CSPC, its employees and students.'),
(4, 'Vehicle must be properly parked at the designated parking spaces.'),
(5, 'Overnight parking (10pm - 5am) is prohibited. In the event an employee needs to leave his/her vehicle in a parking area ovrnight or for an extended period due to work-related travel or other extenuating circumstances, the employee shall notify and seek approval from the GSU.'),
(6, 'All parking users are enjoiend to maintain a clean and safe parking area.'),
(7, 'Strictly no idling while parked on the premises of the College.');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `title` varchar(150) NOT NULL,
  `message` text NOT NULL,
  `type` enum('System','Violation','Access','Announcement') DEFAULT 'System',
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `sender_id`, `title`, `message`, `type`, `is_read`, `created_at`) VALUES
(1, 1, 1, 'Registration Received', 'Hello Christine Joy Campo, your account is now pending review. You will be notified once access is granted.', 'System', 0, '2026-05-16 14:06:21'),
(2, 2, 2, 'Registration Received', 'Hello John Michael M. Toldanes, your account is now pending review. You will be notified once access is granted.', 'System', 1, '2026-05-16 14:40:11'),
(3, 2, 1, 'Account Approved', 'Your account registration has been approved as Student.', 'System', 1, '2026-05-16 14:40:34'),
(4, 2, 1, 'Account Approved', 'Your account registration has been approved as Student.', 'System', 1, '2026-05-16 14:40:34'),
(5, 3, 3, 'Registration Received', 'Hello Jonald Hecita, your account is now pending review. You will be notified once access is granted.', 'System', 0, '2026-05-16 14:56:23'),
(7, 4, 4, 'Registration Received', 'Hello Short Film, your account is now pending review. You will be notified once access is granted.', 'System', 1, '2026-05-18 05:08:46'),
(8, 4, 1, 'Account Approved', 'Your account registration as Staff has been approved. You now have campus access.', 'System', 1, '2026-05-18 05:09:35'),
(9, 2, 3, 'Violation Recorded: Wrong Parking', 'Your vehicle (JMT23) has been cited. Total strikes: 1/3.', 'Violation', 1, '2026-05-18 12:31:06');

-- --------------------------------------------------------

--
-- Table structure for table `offense_sanctions`
--

CREATE TABLE `offense_sanctions` (
  `id` int(11) NOT NULL,
  `offense_level` enum('1st','2nd','3rd') NOT NULL,
  `sanction_description` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `offense_sanctions`
--

INSERT INTO `offense_sanctions` (`id`, `offense_level`, `sanction_description`) VALUES
(1, '1st', 'Issuance of a warning ticket by Security Guards'),
(2, '2nd', 'Suspension of Parking Permit for six (6) months'),
(3, '3rd', 'Revocation of Parking Privileges');

-- --------------------------------------------------------

--
-- Table structure for table `parking_areas`
--

CREATE TABLE `parking_areas` (
  `id` int(11) NOT NULL,
  `area_name` varchar(100) NOT NULL,
  `capacity` int(11) NOT NULL,
  `designation_notes` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `parking_areas`
--

INSERT INTO `parking_areas` (`id`, `area_name`, `capacity`, `designation_notes`) VALUES
(1, 'Administration Building', 9, 'College Officials'),
(2, 'Food Laboratory (Front)', 20, 'Employees Motorcycle'),
(3, 'Duran hall (Front)', 10, 'College Officials'),
(4, 'ACAD 1 Building(Front)', 10, 'College Officials'),
(5, 'Cultural Office (Front)', 15, 'Employees Motorcycle'),
(6, 'College Gymnasium (Right Wing)', 9, 'Car'),
(7, 'College Gymnasium (Right Left)', 30, 'Employees Motorcycle'),
(8, 'College Auditorium (left/Right Wing)', 12, 'Car'),
(9, 'Villafuerte Hall Circle', 70, 'Motorcycle/Car Employee/Students'),
(10, 'Talipapa', 250, 'Motorcycle/Car Employee/Students'),
(11, 'Green Building', 7, 'Car'),
(12, 'ACAD 5 Building Circle', 14, 'Car'),
(13, 'ACAD building 5 (Front)', 6, 'Car'),
(14, 'ACAD Building 5 (Right Wing)', 20, 'Employees Motorcycle'),
(15, 'ACAD Building 5 (Open space)', 500, 'Motorcycle/Car Employee/Students'),
(16, 'ACAD Building 3 (CTDE)', 12, 'Car'),
(17, 'ACAD Building 4 (CCS)', 48, '18 Car / 30 Employees Motorcycle'),
(18, 'Supply Building (Right Wing)', 25, 'Employees Motorcycle');

-- --------------------------------------------------------

--
-- Table structure for table `parking_rules`
--

CREATE TABLE `parking_rules` (
  `id` int(11) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `parking_rules`
--

INSERT INTO `parking_rules` (`id`, `description`) VALUES
(1, 'Drivers are required to observed speed restrictions of 1kph within the compound and give right-of-way to pedestrians.'),
(2, 'No littering.'),
(3, 'Drivers must respect others property.'),
(4, 'Drivers must not turn carelessly or drive irresponsibly'),
(5, 'Employees and students must not conduct maintenance or repair jobs to thier cars while they are parked in our lot, except in emergency cases, e.g., jump start of vehicle or related cases.'),
(6, 'Lack of available space in a desired area is not a valid excuse for violating parking regulations.');

-- --------------------------------------------------------

--
-- Table structure for table `parking_slots`
--

CREATE TABLE `parking_slots` (
  `id` int(11) NOT NULL,
  `area_id` int(11) NOT NULL,
  `slot_number` varchar(20) NOT NULL,
  `status` enum('Available','Occupied','Maintenance','Reserved') DEFAULT 'Available',
  `parked_user_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `parking_slots`
--

INSERT INTO `parking_slots` (`id`, `area_id`, `slot_number`, `status`, `parked_user_id`) VALUES
(1, 1, 'AD-1', 'Available', NULL),
(2, 1, 'AD-2', 'Available', NULL),
(3, 1, 'AD-3', 'Available', NULL),
(4, 1, 'AD-4', 'Available', NULL),
(5, 1, 'AD-5', 'Available', NULL),
(6, 1, 'AD-6', 'Available', NULL),
(7, 1, 'AD-7', 'Available', NULL),
(8, 1, 'AD-8', 'Available', NULL),
(9, 1, 'AD-9', 'Available', NULL),
(10, 2, 'FO-1', 'Available', NULL),
(11, 2, 'FO-2', 'Available', NULL),
(12, 2, 'FO-3', 'Available', NULL),
(13, 2, 'FO-4', 'Available', NULL),
(14, 2, 'FO-5', 'Available', NULL),
(15, 2, 'FO-6', 'Available', NULL),
(16, 2, 'FO-7', 'Available', NULL),
(17, 2, 'FO-8', 'Available', NULL),
(18, 2, 'FO-9', 'Available', NULL),
(19, 2, 'FO-10', 'Available', NULL),
(20, 2, 'FO-11', 'Available', NULL),
(21, 2, 'FO-12', 'Available', NULL),
(22, 2, 'FO-13', 'Available', NULL),
(23, 2, 'FO-14', 'Available', NULL),
(24, 2, 'FO-15', 'Available', NULL),
(25, 2, 'FO-16', 'Available', NULL),
(26, 2, 'FO-17', 'Available', NULL),
(27, 2, 'FO-18', 'Available', NULL),
(28, 2, 'FO-19', 'Available', NULL),
(29, 2, 'FO-20', 'Available', NULL),
(30, 3, 'DU-1', 'Available', NULL),
(31, 3, 'DU-2', 'Available', NULL),
(32, 3, 'DU-3', 'Available', NULL),
(33, 3, 'DU-4', 'Available', NULL),
(34, 3, 'DU-5', 'Available', NULL),
(35, 3, 'DU-6', 'Available', NULL),
(36, 3, 'DU-7', 'Available', NULL),
(37, 3, 'DU-8', 'Available', NULL),
(38, 3, 'DU-9', 'Available', NULL),
(39, 3, 'DU-10', 'Available', NULL),
(40, 4, 'AC-1', 'Available', NULL),
(41, 4, 'AC-2', 'Available', NULL),
(42, 4, 'AC-3', 'Available', NULL),
(43, 4, 'AC-4', 'Available', NULL),
(44, 4, 'AC-5', 'Available', NULL),
(45, 4, 'AC-6', 'Available', NULL),
(46, 4, 'AC-7', 'Available', NULL),
(47, 4, 'AC-8', 'Available', NULL),
(48, 4, 'AC-9', 'Available', NULL),
(49, 4, 'AC-10', 'Available', NULL),
(50, 5, 'CU-1', 'Available', NULL),
(51, 5, 'CU-2', 'Available', NULL),
(52, 5, 'CU-3', 'Available', NULL),
(53, 5, 'CU-4', 'Available', NULL),
(54, 5, 'CU-5', 'Available', NULL),
(55, 5, 'CU-6', 'Available', NULL),
(56, 5, 'CU-7', 'Available', NULL),
(57, 5, 'CU-8', 'Available', NULL),
(58, 5, 'CU-9', 'Available', NULL),
(59, 5, 'CU-10', 'Available', NULL),
(60, 5, 'CU-11', 'Available', NULL),
(61, 5, 'CU-12', 'Available', NULL),
(62, 5, 'CU-13', 'Available', NULL),
(63, 5, 'CU-14', 'Available', NULL),
(64, 5, 'CU-15', 'Available', NULL),
(65, 6, 'CO-1', 'Available', NULL),
(66, 6, 'CO-2', 'Available', NULL),
(67, 6, 'CO-3', 'Available', NULL),
(68, 6, 'CO-4', 'Available', NULL),
(69, 6, 'CO-5', 'Available', NULL),
(70, 6, 'CO-6', 'Available', NULL),
(71, 6, 'CO-7', 'Available', NULL),
(72, 6, 'CO-8', 'Available', NULL),
(73, 6, 'CO-9', 'Available', NULL),
(74, 7, 'CO-1', 'Available', NULL),
(75, 7, 'CO-2', 'Available', NULL),
(76, 7, 'CO-3', 'Available', NULL),
(77, 7, 'CO-4', 'Available', NULL),
(78, 7, 'CO-5', 'Available', NULL),
(79, 7, 'CO-6', 'Available', NULL),
(80, 7, 'CO-7', 'Available', NULL),
(81, 7, 'CO-8', 'Available', NULL),
(82, 7, 'CO-9', 'Available', NULL),
(83, 7, 'CO-10', 'Available', NULL),
(84, 7, 'CO-11', 'Available', NULL),
(85, 7, 'CO-12', 'Available', NULL),
(86, 7, 'CO-13', 'Available', NULL),
(87, 7, 'CO-14', 'Available', NULL),
(88, 7, 'CO-15', 'Available', NULL),
(89, 7, 'CO-16', 'Available', NULL),
(90, 7, 'CO-17', 'Available', NULL),
(91, 7, 'CO-18', 'Available', NULL),
(92, 7, 'CO-19', 'Available', NULL),
(93, 7, 'CO-20', 'Available', NULL),
(94, 7, 'CO-21', 'Available', NULL),
(95, 7, 'CO-22', 'Available', NULL),
(96, 7, 'CO-23', 'Available', NULL),
(97, 7, 'CO-24', 'Available', NULL),
(98, 7, 'CO-25', 'Available', NULL),
(99, 7, 'CO-26', 'Available', NULL),
(100, 7, 'CO-27', 'Available', NULL),
(101, 7, 'CO-28', 'Available', NULL),
(102, 7, 'CO-29', 'Available', NULL),
(103, 7, 'CO-30', 'Available', NULL),
(104, 8, 'CO-1', 'Available', NULL),
(105, 8, 'CO-2', 'Available', NULL),
(106, 8, 'CO-3', 'Available', NULL),
(107, 8, 'CO-4', 'Available', NULL),
(108, 8, 'CO-5', 'Available', NULL),
(109, 8, 'CO-6', 'Available', NULL),
(110, 8, 'CO-7', 'Available', NULL),
(111, 8, 'CO-8', 'Available', NULL),
(112, 8, 'CO-9', 'Available', NULL),
(113, 8, 'CO-10', 'Available', NULL),
(114, 8, 'CO-11', 'Available', NULL),
(115, 8, 'CO-12', 'Available', NULL),
(116, 9, 'VI-1', 'Available', NULL),
(117, 9, 'VI-2', 'Available', NULL),
(118, 9, 'VI-3', 'Available', NULL),
(119, 9, 'VI-4', 'Available', NULL),
(120, 9, 'VI-5', 'Available', NULL),
(121, 9, 'VI-6', 'Available', NULL),
(122, 9, 'VI-7', 'Available', NULL),
(123, 9, 'VI-8', 'Available', NULL),
(124, 9, 'VI-9', 'Available', NULL),
(125, 9, 'VI-10', 'Available', NULL),
(126, 9, 'VI-11', 'Available', NULL),
(127, 9, 'VI-12', 'Available', NULL),
(128, 9, 'VI-13', 'Available', NULL),
(129, 9, 'VI-14', 'Available', NULL),
(130, 9, 'VI-15', 'Available', NULL),
(131, 9, 'VI-16', 'Available', NULL),
(132, 9, 'VI-17', 'Available', NULL),
(133, 9, 'VI-18', 'Available', NULL),
(134, 9, 'VI-19', 'Available', NULL),
(135, 9, 'VI-20', 'Available', NULL),
(136, 9, 'VI-21', 'Available', NULL),
(137, 9, 'VI-22', 'Available', NULL),
(138, 9, 'VI-23', 'Available', NULL),
(139, 9, 'VI-24', 'Available', NULL),
(140, 9, 'VI-25', 'Available', NULL),
(141, 9, 'VI-26', 'Available', NULL),
(142, 9, 'VI-27', 'Available', NULL),
(143, 9, 'VI-28', 'Available', NULL),
(144, 9, 'VI-29', 'Available', NULL),
(145, 9, 'VI-30', 'Available', NULL),
(146, 9, 'VI-31', 'Available', NULL),
(147, 9, 'VI-32', 'Available', NULL),
(148, 9, 'VI-33', 'Available', NULL),
(149, 9, 'VI-34', 'Available', NULL),
(150, 9, 'VI-35', 'Available', NULL),
(151, 9, 'VI-36', 'Available', NULL),
(152, 9, 'VI-37', 'Available', NULL),
(153, 9, 'VI-38', 'Available', NULL),
(154, 9, 'VI-39', 'Available', NULL),
(155, 9, 'VI-40', 'Available', NULL),
(156, 9, 'VI-41', 'Available', NULL),
(157, 9, 'VI-42', 'Available', NULL),
(158, 9, 'VI-43', 'Available', NULL),
(159, 9, 'VI-44', 'Available', NULL),
(160, 9, 'VI-45', 'Available', NULL),
(161, 9, 'VI-46', 'Available', NULL),
(162, 9, 'VI-47', 'Available', NULL),
(163, 9, 'VI-48', 'Available', NULL),
(164, 9, 'VI-49', 'Available', NULL),
(165, 9, 'VI-50', 'Available', NULL),
(166, 9, 'VI-51', 'Available', NULL),
(167, 9, 'VI-52', 'Available', NULL),
(168, 9, 'VI-53', 'Available', NULL),
(169, 9, 'VI-54', 'Available', NULL),
(170, 9, 'VI-55', 'Available', NULL),
(171, 9, 'VI-56', 'Available', NULL),
(172, 9, 'VI-57', 'Available', NULL),
(173, 9, 'VI-58', 'Available', NULL),
(174, 9, 'VI-59', 'Available', NULL),
(175, 9, 'VI-60', 'Available', NULL),
(176, 9, 'VI-61', 'Available', NULL),
(177, 9, 'VI-62', 'Available', NULL),
(178, 9, 'VI-63', 'Available', NULL),
(179, 9, 'VI-64', 'Available', NULL),
(180, 9, 'VI-65', 'Available', NULL),
(181, 9, 'VI-66', 'Available', NULL),
(182, 9, 'VI-67', 'Available', NULL),
(183, 9, 'VI-68', 'Available', NULL),
(184, 9, 'VI-69', 'Available', NULL),
(185, 9, 'VI-70', 'Available', NULL),
(186, 10, 'TA-1', 'Available', NULL),
(187, 10, 'TA-2', 'Available', NULL),
(188, 10, 'TA-3', 'Available', NULL),
(189, 10, 'TA-4', 'Available', NULL),
(190, 10, 'TA-5', 'Available', NULL),
(191, 10, 'TA-6', 'Available', NULL),
(192, 10, 'TA-7', 'Available', NULL),
(193, 10, 'TA-8', 'Available', NULL),
(194, 10, 'TA-9', 'Available', NULL),
(195, 10, 'TA-10', 'Available', NULL),
(196, 10, 'TA-11', 'Available', NULL),
(197, 10, 'TA-12', 'Available', NULL),
(198, 10, 'TA-13', 'Available', NULL),
(199, 10, 'TA-14', 'Available', NULL),
(200, 10, 'TA-15', 'Available', NULL),
(201, 10, 'TA-16', 'Available', NULL),
(202, 10, 'TA-17', 'Available', NULL),
(203, 10, 'TA-18', 'Available', NULL),
(204, 10, 'TA-19', 'Available', NULL),
(205, 10, 'TA-20', 'Available', NULL),
(206, 10, 'TA-21', 'Available', NULL),
(207, 10, 'TA-22', 'Available', NULL),
(208, 10, 'TA-23', 'Available', NULL),
(209, 10, 'TA-24', 'Available', NULL),
(210, 10, 'TA-25', 'Available', NULL),
(211, 10, 'TA-26', 'Available', NULL),
(212, 10, 'TA-27', 'Available', NULL),
(213, 10, 'TA-28', 'Available', NULL),
(214, 10, 'TA-29', 'Available', NULL),
(215, 10, 'TA-30', 'Available', NULL),
(216, 10, 'TA-31', 'Available', NULL),
(217, 10, 'TA-32', 'Available', NULL),
(218, 10, 'TA-33', 'Available', NULL),
(219, 10, 'TA-34', 'Available', NULL),
(220, 10, 'TA-35', 'Available', NULL),
(221, 10, 'TA-36', 'Available', NULL),
(222, 10, 'TA-37', 'Available', NULL),
(223, 10, 'TA-38', 'Available', NULL),
(224, 10, 'TA-39', 'Available', NULL),
(225, 10, 'TA-40', 'Available', NULL),
(226, 10, 'TA-41', 'Available', NULL),
(227, 10, 'TA-42', 'Available', NULL),
(228, 10, 'TA-43', 'Available', NULL),
(229, 10, 'TA-44', 'Available', NULL),
(230, 10, 'TA-45', 'Available', NULL),
(231, 10, 'TA-46', 'Available', NULL),
(232, 10, 'TA-47', 'Available', NULL),
(233, 10, 'TA-48', 'Available', NULL),
(234, 10, 'TA-49', 'Available', NULL),
(235, 10, 'TA-50', 'Available', NULL),
(236, 10, 'TA-51', 'Available', NULL),
(237, 10, 'TA-52', 'Available', NULL),
(238, 10, 'TA-53', 'Available', NULL),
(239, 10, 'TA-54', 'Available', NULL),
(240, 10, 'TA-55', 'Available', NULL),
(241, 10, 'TA-56', 'Available', NULL),
(242, 10, 'TA-57', 'Available', NULL),
(243, 10, 'TA-58', 'Available', NULL),
(244, 10, 'TA-59', 'Available', NULL),
(245, 10, 'TA-60', 'Available', NULL),
(246, 10, 'TA-61', 'Available', NULL),
(247, 10, 'TA-62', 'Available', NULL),
(248, 10, 'TA-63', 'Available', NULL),
(249, 10, 'TA-64', 'Available', NULL),
(250, 10, 'TA-65', 'Available', NULL),
(251, 10, 'TA-66', 'Available', NULL),
(252, 10, 'TA-67', 'Available', NULL),
(253, 10, 'TA-68', 'Available', NULL),
(254, 10, 'TA-69', 'Available', NULL),
(255, 10, 'TA-70', 'Available', NULL),
(256, 10, 'TA-71', 'Available', NULL),
(257, 10, 'TA-72', 'Available', NULL),
(258, 10, 'TA-73', 'Available', NULL),
(259, 10, 'TA-74', 'Available', NULL),
(260, 10, 'TA-75', 'Available', NULL),
(261, 10, 'TA-76', 'Available', NULL),
(262, 10, 'TA-77', 'Available', NULL),
(263, 10, 'TA-78', 'Available', NULL),
(264, 10, 'TA-79', 'Available', NULL),
(265, 10, 'TA-80', 'Available', NULL),
(266, 10, 'TA-81', 'Available', NULL),
(267, 10, 'TA-82', 'Available', NULL),
(268, 10, 'TA-83', 'Available', NULL),
(269, 10, 'TA-84', 'Available', NULL),
(270, 10, 'TA-85', 'Available', NULL),
(271, 10, 'TA-86', 'Available', NULL),
(272, 10, 'TA-87', 'Available', NULL),
(273, 10, 'TA-88', 'Available', NULL),
(274, 10, 'TA-89', 'Available', NULL),
(275, 10, 'TA-90', 'Available', NULL),
(276, 10, 'TA-91', 'Available', NULL),
(277, 10, 'TA-92', 'Available', NULL),
(278, 10, 'TA-93', 'Available', NULL),
(279, 10, 'TA-94', 'Available', NULL),
(280, 10, 'TA-95', 'Available', NULL),
(281, 10, 'TA-96', 'Available', NULL),
(282, 10, 'TA-97', 'Available', NULL),
(283, 10, 'TA-98', 'Available', NULL),
(284, 10, 'TA-99', 'Available', NULL),
(285, 10, 'TA-100', 'Available', NULL),
(286, 10, 'TA-101', 'Available', NULL),
(287, 10, 'TA-102', 'Available', NULL),
(288, 10, 'TA-103', 'Available', NULL),
(289, 10, 'TA-104', 'Available', NULL),
(290, 10, 'TA-105', 'Available', NULL),
(291, 10, 'TA-106', 'Available', NULL),
(292, 10, 'TA-107', 'Available', NULL),
(293, 10, 'TA-108', 'Available', NULL),
(294, 10, 'TA-109', 'Available', NULL),
(295, 10, 'TA-110', 'Available', NULL),
(296, 10, 'TA-111', 'Available', NULL),
(297, 10, 'TA-112', 'Available', NULL),
(298, 10, 'TA-113', 'Available', NULL),
(299, 10, 'TA-114', 'Available', NULL),
(300, 10, 'TA-115', 'Available', NULL),
(301, 10, 'TA-116', 'Available', NULL),
(302, 10, 'TA-117', 'Available', NULL),
(303, 10, 'TA-118', 'Available', NULL),
(304, 10, 'TA-119', 'Available', NULL),
(305, 10, 'TA-120', 'Available', NULL),
(306, 10, 'TA-121', 'Available', NULL),
(307, 10, 'TA-122', 'Available', NULL),
(308, 10, 'TA-123', 'Available', NULL),
(309, 10, 'TA-124', 'Available', NULL),
(310, 10, 'TA-125', 'Available', NULL),
(311, 10, 'TA-126', 'Available', NULL),
(312, 10, 'TA-127', 'Available', NULL),
(313, 10, 'TA-128', 'Available', NULL),
(314, 10, 'TA-129', 'Available', NULL),
(315, 10, 'TA-130', 'Available', NULL),
(316, 10, 'TA-131', 'Available', NULL),
(317, 10, 'TA-132', 'Available', NULL),
(318, 10, 'TA-133', 'Available', NULL),
(319, 10, 'TA-134', 'Available', NULL),
(320, 10, 'TA-135', 'Available', NULL),
(321, 10, 'TA-136', 'Available', NULL),
(322, 10, 'TA-137', 'Available', NULL),
(323, 10, 'TA-138', 'Available', NULL),
(324, 10, 'TA-139', 'Available', NULL),
(325, 10, 'TA-140', 'Available', NULL),
(326, 10, 'TA-141', 'Available', NULL),
(327, 10, 'TA-142', 'Available', NULL),
(328, 10, 'TA-143', 'Available', NULL),
(329, 10, 'TA-144', 'Available', NULL),
(330, 10, 'TA-145', 'Available', NULL),
(331, 10, 'TA-146', 'Available', NULL),
(332, 10, 'TA-147', 'Available', NULL),
(333, 10, 'TA-148', 'Available', NULL),
(334, 10, 'TA-149', 'Available', NULL),
(335, 10, 'TA-150', 'Available', NULL),
(336, 10, 'TA-151', 'Available', NULL),
(337, 10, 'TA-152', 'Available', NULL),
(338, 10, 'TA-153', 'Available', NULL),
(339, 10, 'TA-154', 'Available', NULL),
(340, 10, 'TA-155', 'Available', NULL),
(341, 10, 'TA-156', 'Available', NULL),
(342, 10, 'TA-157', 'Available', NULL),
(343, 10, 'TA-158', 'Available', NULL),
(344, 10, 'TA-159', 'Available', NULL),
(345, 10, 'TA-160', 'Available', NULL),
(346, 10, 'TA-161', 'Available', NULL),
(347, 10, 'TA-162', 'Available', NULL),
(348, 10, 'TA-163', 'Available', NULL),
(349, 10, 'TA-164', 'Available', NULL),
(350, 10, 'TA-165', 'Available', NULL),
(351, 10, 'TA-166', 'Available', NULL),
(352, 10, 'TA-167', 'Available', NULL),
(353, 10, 'TA-168', 'Available', NULL),
(354, 10, 'TA-169', 'Available', NULL),
(355, 10, 'TA-170', 'Available', NULL),
(356, 10, 'TA-171', 'Available', NULL),
(357, 10, 'TA-172', 'Available', NULL),
(358, 10, 'TA-173', 'Available', NULL),
(359, 10, 'TA-174', 'Available', NULL),
(360, 10, 'TA-175', 'Available', NULL),
(361, 10, 'TA-176', 'Available', NULL),
(362, 10, 'TA-177', 'Available', NULL),
(363, 10, 'TA-178', 'Available', NULL),
(364, 10, 'TA-179', 'Available', NULL),
(365, 10, 'TA-180', 'Available', NULL),
(366, 10, 'TA-181', 'Available', NULL),
(367, 10, 'TA-182', 'Available', NULL),
(368, 10, 'TA-183', 'Available', NULL),
(369, 10, 'TA-184', 'Available', NULL),
(370, 10, 'TA-185', 'Available', NULL),
(371, 10, 'TA-186', 'Available', NULL),
(372, 10, 'TA-187', 'Available', NULL),
(373, 10, 'TA-188', 'Available', NULL),
(374, 10, 'TA-189', 'Available', NULL),
(375, 10, 'TA-190', 'Available', NULL),
(376, 10, 'TA-191', 'Available', NULL),
(377, 10, 'TA-192', 'Available', NULL),
(378, 10, 'TA-193', 'Available', NULL),
(379, 10, 'TA-194', 'Available', NULL),
(380, 10, 'TA-195', 'Available', NULL),
(381, 10, 'TA-196', 'Available', NULL),
(382, 10, 'TA-197', 'Available', NULL),
(383, 10, 'TA-198', 'Available', NULL),
(384, 10, 'TA-199', 'Available', NULL),
(385, 10, 'TA-200', 'Available', NULL),
(386, 10, 'TA-201', 'Available', NULL),
(387, 10, 'TA-202', 'Available', NULL),
(388, 10, 'TA-203', 'Available', NULL),
(389, 10, 'TA-204', 'Available', NULL),
(390, 10, 'TA-205', 'Available', NULL),
(391, 10, 'TA-206', 'Available', NULL),
(392, 10, 'TA-207', 'Available', NULL),
(393, 10, 'TA-208', 'Available', NULL),
(394, 10, 'TA-209', 'Available', NULL),
(395, 10, 'TA-210', 'Available', NULL),
(396, 10, 'TA-211', 'Available', NULL),
(397, 10, 'TA-212', 'Available', NULL),
(398, 10, 'TA-213', 'Available', NULL),
(399, 10, 'TA-214', 'Available', NULL),
(400, 10, 'TA-215', 'Available', NULL),
(401, 10, 'TA-216', 'Available', NULL),
(402, 10, 'TA-217', 'Available', NULL),
(403, 10, 'TA-218', 'Available', NULL),
(404, 10, 'TA-219', 'Available', NULL),
(405, 10, 'TA-220', 'Available', NULL),
(406, 10, 'TA-221', 'Available', NULL),
(407, 10, 'TA-222', 'Available', NULL),
(408, 10, 'TA-223', 'Available', NULL),
(409, 10, 'TA-224', 'Available', NULL),
(410, 10, 'TA-225', 'Available', NULL),
(411, 10, 'TA-226', 'Available', NULL),
(412, 10, 'TA-227', 'Available', NULL),
(413, 10, 'TA-228', 'Available', NULL),
(414, 10, 'TA-229', 'Available', NULL),
(415, 10, 'TA-230', 'Available', NULL),
(416, 10, 'TA-231', 'Available', NULL),
(417, 10, 'TA-232', 'Available', NULL),
(418, 10, 'TA-233', 'Available', NULL),
(419, 10, 'TA-234', 'Available', NULL),
(420, 10, 'TA-235', 'Available', NULL),
(421, 10, 'TA-236', 'Available', NULL),
(422, 10, 'TA-237', 'Available', NULL),
(423, 10, 'TA-238', 'Available', NULL),
(424, 10, 'TA-239', 'Available', NULL),
(425, 10, 'TA-240', 'Available', NULL),
(426, 10, 'TA-241', 'Available', NULL),
(427, 10, 'TA-242', 'Available', NULL),
(428, 10, 'TA-243', 'Available', NULL),
(429, 10, 'TA-244', 'Available', NULL),
(430, 10, 'TA-245', 'Available', NULL),
(431, 10, 'TA-246', 'Available', NULL),
(432, 10, 'TA-247', 'Available', NULL),
(433, 10, 'TA-248', 'Available', NULL),
(434, 10, 'TA-249', 'Available', NULL),
(435, 10, 'TA-250', 'Available', NULL),
(436, 11, 'GR-1', 'Available', NULL),
(437, 11, 'GR-2', 'Available', NULL),
(438, 11, 'GR-3', 'Available', NULL),
(439, 11, 'GR-4', 'Available', NULL),
(440, 11, 'GR-5', 'Available', NULL),
(441, 11, 'GR-6', 'Available', NULL),
(442, 11, 'GR-7', 'Available', NULL),
(443, 12, 'AC-1', 'Available', NULL),
(444, 12, 'AC-2', 'Available', NULL),
(445, 12, 'AC-3', 'Available', NULL),
(446, 12, 'AC-4', 'Available', NULL),
(447, 12, 'AC-5', 'Available', NULL),
(448, 12, 'AC-6', 'Available', NULL),
(449, 12, 'AC-7', 'Available', NULL),
(450, 12, 'AC-8', 'Available', NULL),
(451, 12, 'AC-9', 'Available', NULL),
(452, 12, 'AC-10', 'Available', NULL),
(453, 12, 'AC-11', 'Available', NULL),
(454, 12, 'AC-12', 'Available', NULL),
(455, 12, 'AC-13', 'Available', NULL),
(456, 12, 'AC-14', 'Available', NULL),
(457, 13, 'AC-1', 'Available', NULL),
(458, 13, 'AC-2', 'Available', NULL),
(459, 13, 'AC-3', 'Available', NULL),
(460, 13, 'AC-4', 'Available', NULL),
(461, 13, 'AC-5', 'Available', NULL),
(462, 13, 'AC-6', 'Available', NULL),
(463, 14, 'AC-1', 'Available', NULL),
(464, 14, 'AC-2', 'Available', NULL),
(465, 14, 'AC-3', 'Available', NULL),
(466, 14, 'AC-4', 'Available', NULL),
(467, 14, 'AC-5', 'Available', NULL),
(468, 14, 'AC-6', 'Available', NULL),
(469, 14, 'AC-7', 'Available', NULL),
(470, 14, 'AC-8', 'Available', NULL),
(471, 14, 'AC-9', 'Available', NULL),
(472, 14, 'AC-10', 'Available', NULL),
(473, 14, 'AC-11', 'Available', NULL),
(474, 14, 'AC-12', 'Available', NULL),
(475, 14, 'AC-13', 'Available', NULL),
(476, 14, 'AC-14', 'Available', NULL),
(477, 14, 'AC-15', 'Available', NULL),
(478, 14, 'AC-16', 'Available', NULL),
(479, 14, 'AC-17', 'Available', NULL),
(480, 14, 'AC-18', 'Available', NULL),
(481, 14, 'AC-19', 'Available', NULL),
(482, 14, 'AC-20', 'Available', NULL),
(483, 15, 'AC-1', 'Available', NULL),
(484, 15, 'AC-2', 'Available', NULL),
(485, 15, 'AC-3', 'Available', NULL),
(486, 15, 'AC-4', 'Available', NULL),
(487, 15, 'AC-5', 'Available', NULL),
(488, 15, 'AC-6', 'Available', NULL),
(489, 15, 'AC-7', 'Available', NULL),
(490, 15, 'AC-8', 'Available', NULL),
(491, 15, 'AC-9', 'Available', NULL),
(492, 15, 'AC-10', 'Available', NULL),
(493, 15, 'AC-11', 'Available', NULL),
(494, 15, 'AC-12', 'Available', NULL),
(495, 15, 'AC-13', 'Available', NULL),
(496, 15, 'AC-14', 'Available', NULL),
(497, 15, 'AC-15', 'Available', NULL),
(498, 15, 'AC-16', 'Available', NULL),
(499, 15, 'AC-17', 'Available', NULL),
(500, 15, 'AC-18', 'Available', NULL),
(501, 15, 'AC-19', 'Available', NULL),
(502, 15, 'AC-20', 'Available', NULL),
(503, 15, 'AC-21', 'Available', NULL),
(504, 15, 'AC-22', 'Available', NULL),
(505, 15, 'AC-23', 'Available', NULL),
(506, 15, 'AC-24', 'Available', NULL),
(507, 15, 'AC-25', 'Available', NULL),
(508, 15, 'AC-26', 'Available', NULL),
(509, 15, 'AC-27', 'Available', NULL),
(510, 15, 'AC-28', 'Available', NULL),
(511, 15, 'AC-29', 'Available', NULL),
(512, 15, 'AC-30', 'Available', NULL),
(513, 15, 'AC-31', 'Available', NULL),
(514, 15, 'AC-32', 'Available', NULL),
(515, 15, 'AC-33', 'Available', NULL),
(516, 15, 'AC-34', 'Available', NULL),
(517, 15, 'AC-35', 'Available', NULL),
(518, 15, 'AC-36', 'Available', NULL),
(519, 15, 'AC-37', 'Available', NULL),
(520, 15, 'AC-38', 'Available', NULL),
(521, 15, 'AC-39', 'Available', NULL),
(522, 15, 'AC-40', 'Available', NULL),
(523, 15, 'AC-41', 'Available', NULL),
(524, 15, 'AC-42', 'Available', NULL),
(525, 15, 'AC-43', 'Available', NULL),
(526, 15, 'AC-44', 'Available', NULL),
(527, 15, 'AC-45', 'Available', NULL),
(528, 15, 'AC-46', 'Available', NULL),
(529, 15, 'AC-47', 'Available', NULL),
(530, 15, 'AC-48', 'Available', NULL),
(531, 15, 'AC-49', 'Available', NULL),
(532, 15, 'AC-50', 'Available', NULL),
(533, 15, 'AC-51', 'Available', NULL),
(534, 15, 'AC-52', 'Available', NULL),
(535, 15, 'AC-53', 'Available', NULL),
(536, 15, 'AC-54', 'Available', NULL),
(537, 15, 'AC-55', 'Available', NULL),
(538, 15, 'AC-56', 'Available', NULL),
(539, 15, 'AC-57', 'Available', NULL),
(540, 15, 'AC-58', 'Available', NULL),
(541, 15, 'AC-59', 'Available', NULL),
(542, 15, 'AC-60', 'Available', NULL),
(543, 15, 'AC-61', 'Available', NULL),
(544, 15, 'AC-62', 'Available', NULL),
(545, 15, 'AC-63', 'Available', NULL),
(546, 15, 'AC-64', 'Available', NULL),
(547, 15, 'AC-65', 'Available', NULL),
(548, 15, 'AC-66', 'Available', NULL),
(549, 15, 'AC-67', 'Available', NULL),
(550, 15, 'AC-68', 'Available', NULL),
(551, 15, 'AC-69', 'Available', NULL),
(552, 15, 'AC-70', 'Available', NULL),
(553, 15, 'AC-71', 'Available', NULL),
(554, 15, 'AC-72', 'Available', NULL),
(555, 15, 'AC-73', 'Available', NULL),
(556, 15, 'AC-74', 'Available', NULL),
(557, 15, 'AC-75', 'Available', NULL),
(558, 15, 'AC-76', 'Available', NULL),
(559, 15, 'AC-77', 'Available', NULL),
(560, 15, 'AC-78', 'Available', NULL),
(561, 15, 'AC-79', 'Available', NULL),
(562, 15, 'AC-80', 'Available', NULL),
(563, 15, 'AC-81', 'Available', NULL),
(564, 15, 'AC-82', 'Available', NULL),
(565, 15, 'AC-83', 'Available', NULL),
(566, 15, 'AC-84', 'Available', NULL),
(567, 15, 'AC-85', 'Available', NULL),
(568, 15, 'AC-86', 'Available', NULL),
(569, 15, 'AC-87', 'Available', NULL),
(570, 15, 'AC-88', 'Available', NULL),
(571, 15, 'AC-89', 'Available', NULL),
(572, 15, 'AC-90', 'Available', NULL),
(573, 15, 'AC-91', 'Available', NULL),
(574, 15, 'AC-92', 'Available', NULL),
(575, 15, 'AC-93', 'Available', NULL),
(576, 15, 'AC-94', 'Available', NULL),
(577, 15, 'AC-95', 'Available', NULL),
(578, 15, 'AC-96', 'Available', NULL),
(579, 15, 'AC-97', 'Available', NULL),
(580, 15, 'AC-98', 'Available', NULL),
(581, 15, 'AC-99', 'Available', NULL),
(582, 15, 'AC-100', 'Available', NULL),
(583, 15, 'AC-101', 'Available', NULL),
(584, 15, 'AC-102', 'Available', NULL),
(585, 15, 'AC-103', 'Available', NULL),
(586, 15, 'AC-104', 'Available', NULL),
(587, 15, 'AC-105', 'Available', NULL),
(588, 15, 'AC-106', 'Available', NULL),
(589, 15, 'AC-107', 'Available', NULL),
(590, 15, 'AC-108', 'Available', NULL),
(591, 15, 'AC-109', 'Available', NULL),
(592, 15, 'AC-110', 'Available', NULL),
(593, 15, 'AC-111', 'Available', NULL),
(594, 15, 'AC-112', 'Available', NULL),
(595, 15, 'AC-113', 'Available', NULL),
(596, 15, 'AC-114', 'Available', NULL),
(597, 15, 'AC-115', 'Available', NULL),
(598, 15, 'AC-116', 'Available', NULL),
(599, 15, 'AC-117', 'Available', NULL),
(600, 15, 'AC-118', 'Available', NULL),
(601, 15, 'AC-119', 'Available', NULL),
(602, 15, 'AC-120', 'Available', NULL),
(603, 15, 'AC-121', 'Available', NULL),
(604, 15, 'AC-122', 'Available', NULL),
(605, 15, 'AC-123', 'Available', NULL),
(606, 15, 'AC-124', 'Available', NULL),
(607, 15, 'AC-125', 'Available', NULL),
(608, 15, 'AC-126', 'Available', NULL),
(609, 15, 'AC-127', 'Available', NULL),
(610, 15, 'AC-128', 'Available', NULL),
(611, 15, 'AC-129', 'Available', NULL),
(612, 15, 'AC-130', 'Available', NULL),
(613, 15, 'AC-131', 'Available', NULL),
(614, 15, 'AC-132', 'Available', NULL),
(615, 15, 'AC-133', 'Available', NULL),
(616, 15, 'AC-134', 'Available', NULL),
(617, 15, 'AC-135', 'Available', NULL),
(618, 15, 'AC-136', 'Available', NULL),
(619, 15, 'AC-137', 'Available', NULL),
(620, 15, 'AC-138', 'Available', NULL),
(621, 15, 'AC-139', 'Available', NULL),
(622, 15, 'AC-140', 'Available', NULL),
(623, 15, 'AC-141', 'Available', NULL),
(624, 15, 'AC-142', 'Available', NULL),
(625, 15, 'AC-143', 'Available', NULL),
(626, 15, 'AC-144', 'Available', NULL),
(627, 15, 'AC-145', 'Available', NULL),
(628, 15, 'AC-146', 'Available', NULL),
(629, 15, 'AC-147', 'Available', NULL),
(630, 15, 'AC-148', 'Available', NULL),
(631, 15, 'AC-149', 'Available', NULL),
(632, 15, 'AC-150', 'Available', NULL),
(633, 15, 'AC-151', 'Available', NULL),
(634, 15, 'AC-152', 'Available', NULL),
(635, 15, 'AC-153', 'Available', NULL),
(636, 15, 'AC-154', 'Available', NULL),
(637, 15, 'AC-155', 'Available', NULL),
(638, 15, 'AC-156', 'Available', NULL),
(639, 15, 'AC-157', 'Available', NULL),
(640, 15, 'AC-158', 'Available', NULL),
(641, 15, 'AC-159', 'Available', NULL),
(642, 15, 'AC-160', 'Available', NULL),
(643, 15, 'AC-161', 'Available', NULL),
(644, 15, 'AC-162', 'Available', NULL),
(645, 15, 'AC-163', 'Available', NULL),
(646, 15, 'AC-164', 'Available', NULL),
(647, 15, 'AC-165', 'Available', NULL),
(648, 15, 'AC-166', 'Available', NULL),
(649, 15, 'AC-167', 'Available', NULL),
(650, 15, 'AC-168', 'Available', NULL),
(651, 15, 'AC-169', 'Available', NULL),
(652, 15, 'AC-170', 'Available', NULL),
(653, 15, 'AC-171', 'Available', NULL),
(654, 15, 'AC-172', 'Available', NULL),
(655, 15, 'AC-173', 'Available', NULL),
(656, 15, 'AC-174', 'Available', NULL),
(657, 15, 'AC-175', 'Available', NULL),
(658, 15, 'AC-176', 'Available', NULL),
(659, 15, 'AC-177', 'Available', NULL),
(660, 15, 'AC-178', 'Available', NULL),
(661, 15, 'AC-179', 'Available', NULL),
(662, 15, 'AC-180', 'Available', NULL),
(663, 15, 'AC-181', 'Available', NULL),
(664, 15, 'AC-182', 'Available', NULL),
(665, 15, 'AC-183', 'Available', NULL),
(666, 15, 'AC-184', 'Available', NULL),
(667, 15, 'AC-185', 'Available', NULL),
(668, 15, 'AC-186', 'Available', NULL),
(669, 15, 'AC-187', 'Available', NULL),
(670, 15, 'AC-188', 'Available', NULL),
(671, 15, 'AC-189', 'Available', NULL),
(672, 15, 'AC-190', 'Available', NULL),
(673, 15, 'AC-191', 'Available', NULL),
(674, 15, 'AC-192', 'Available', NULL),
(675, 15, 'AC-193', 'Available', NULL),
(676, 15, 'AC-194', 'Available', NULL),
(677, 15, 'AC-195', 'Available', NULL),
(678, 15, 'AC-196', 'Available', NULL),
(679, 15, 'AC-197', 'Available', NULL),
(680, 15, 'AC-198', 'Available', NULL),
(681, 15, 'AC-199', 'Available', NULL),
(682, 15, 'AC-200', 'Available', NULL),
(683, 15, 'AC-201', 'Available', NULL),
(684, 15, 'AC-202', 'Available', NULL),
(685, 15, 'AC-203', 'Available', NULL),
(686, 15, 'AC-204', 'Available', NULL),
(687, 15, 'AC-205', 'Available', NULL),
(688, 15, 'AC-206', 'Available', NULL),
(689, 15, 'AC-207', 'Available', NULL),
(690, 15, 'AC-208', 'Available', NULL),
(691, 15, 'AC-209', 'Available', NULL),
(692, 15, 'AC-210', 'Available', NULL),
(693, 15, 'AC-211', 'Available', NULL),
(694, 15, 'AC-212', 'Available', NULL),
(695, 15, 'AC-213', 'Available', NULL),
(696, 15, 'AC-214', 'Available', NULL),
(697, 15, 'AC-215', 'Available', NULL),
(698, 15, 'AC-216', 'Available', NULL),
(699, 15, 'AC-217', 'Available', NULL),
(700, 15, 'AC-218', 'Available', NULL),
(701, 15, 'AC-219', 'Available', NULL),
(702, 15, 'AC-220', 'Available', NULL),
(703, 15, 'AC-221', 'Available', NULL),
(704, 15, 'AC-222', 'Available', NULL),
(705, 15, 'AC-223', 'Available', NULL),
(706, 15, 'AC-224', 'Available', NULL),
(707, 15, 'AC-225', 'Available', NULL),
(708, 15, 'AC-226', 'Available', NULL),
(709, 15, 'AC-227', 'Available', NULL),
(710, 15, 'AC-228', 'Available', NULL),
(711, 15, 'AC-229', 'Available', NULL),
(712, 15, 'AC-230', 'Available', NULL),
(713, 15, 'AC-231', 'Available', NULL),
(714, 15, 'AC-232', 'Available', NULL),
(715, 15, 'AC-233', 'Available', NULL),
(716, 15, 'AC-234', 'Available', NULL),
(717, 15, 'AC-235', 'Available', NULL),
(718, 15, 'AC-236', 'Available', NULL),
(719, 15, 'AC-237', 'Available', NULL),
(720, 15, 'AC-238', 'Available', NULL),
(721, 15, 'AC-239', 'Available', NULL),
(722, 15, 'AC-240', 'Available', NULL),
(723, 15, 'AC-241', 'Available', NULL),
(724, 15, 'AC-242', 'Available', NULL),
(725, 15, 'AC-243', 'Available', NULL),
(726, 15, 'AC-244', 'Available', NULL),
(727, 15, 'AC-245', 'Available', NULL),
(728, 15, 'AC-246', 'Available', NULL),
(729, 15, 'AC-247', 'Available', NULL),
(730, 15, 'AC-248', 'Available', NULL),
(731, 15, 'AC-249', 'Available', NULL),
(732, 15, 'AC-250', 'Available', NULL),
(733, 15, 'AC-251', 'Available', NULL),
(734, 15, 'AC-252', 'Available', NULL),
(735, 15, 'AC-253', 'Available', NULL),
(736, 15, 'AC-254', 'Available', NULL),
(737, 15, 'AC-255', 'Available', NULL),
(738, 15, 'AC-256', 'Available', NULL),
(739, 15, 'AC-257', 'Available', NULL),
(740, 15, 'AC-258', 'Available', NULL),
(741, 15, 'AC-259', 'Available', NULL),
(742, 15, 'AC-260', 'Available', NULL),
(743, 15, 'AC-261', 'Available', NULL),
(744, 15, 'AC-262', 'Available', NULL),
(745, 15, 'AC-263', 'Available', NULL),
(746, 15, 'AC-264', 'Available', NULL),
(747, 15, 'AC-265', 'Available', NULL),
(748, 15, 'AC-266', 'Available', NULL),
(749, 15, 'AC-267', 'Available', NULL),
(750, 15, 'AC-268', 'Available', NULL),
(751, 15, 'AC-269', 'Available', NULL),
(752, 15, 'AC-270', 'Available', NULL),
(753, 15, 'AC-271', 'Available', NULL),
(754, 15, 'AC-272', 'Available', NULL),
(755, 15, 'AC-273', 'Available', NULL),
(756, 15, 'AC-274', 'Available', NULL),
(757, 15, 'AC-275', 'Available', NULL),
(758, 15, 'AC-276', 'Available', NULL),
(759, 15, 'AC-277', 'Available', NULL),
(760, 15, 'AC-278', 'Available', NULL),
(761, 15, 'AC-279', 'Available', NULL),
(762, 15, 'AC-280', 'Available', NULL),
(763, 15, 'AC-281', 'Available', NULL),
(764, 15, 'AC-282', 'Available', NULL),
(765, 15, 'AC-283', 'Available', NULL),
(766, 15, 'AC-284', 'Available', NULL),
(767, 15, 'AC-285', 'Available', NULL),
(768, 15, 'AC-286', 'Available', NULL),
(769, 15, 'AC-287', 'Available', NULL),
(770, 15, 'AC-288', 'Available', NULL),
(771, 15, 'AC-289', 'Available', NULL),
(772, 15, 'AC-290', 'Available', NULL),
(773, 15, 'AC-291', 'Available', NULL),
(774, 15, 'AC-292', 'Available', NULL),
(775, 15, 'AC-293', 'Available', NULL),
(776, 15, 'AC-294', 'Available', NULL),
(777, 15, 'AC-295', 'Available', NULL),
(778, 15, 'AC-296', 'Available', NULL),
(779, 15, 'AC-297', 'Available', NULL),
(780, 15, 'AC-298', 'Available', NULL),
(781, 15, 'AC-299', 'Available', NULL),
(782, 15, 'AC-300', 'Available', NULL),
(783, 15, 'AC-301', 'Available', NULL),
(784, 15, 'AC-302', 'Available', NULL),
(785, 15, 'AC-303', 'Available', NULL),
(786, 15, 'AC-304', 'Available', NULL),
(787, 15, 'AC-305', 'Available', NULL),
(788, 15, 'AC-306', 'Available', NULL),
(789, 15, 'AC-307', 'Available', NULL),
(790, 15, 'AC-308', 'Available', NULL),
(791, 15, 'AC-309', 'Available', NULL),
(792, 15, 'AC-310', 'Available', NULL),
(793, 15, 'AC-311', 'Available', NULL),
(794, 15, 'AC-312', 'Available', NULL),
(795, 15, 'AC-313', 'Available', NULL),
(796, 15, 'AC-314', 'Available', NULL),
(797, 15, 'AC-315', 'Available', NULL),
(798, 15, 'AC-316', 'Available', NULL),
(799, 15, 'AC-317', 'Available', NULL),
(800, 15, 'AC-318', 'Available', NULL),
(801, 15, 'AC-319', 'Available', NULL),
(802, 15, 'AC-320', 'Available', NULL),
(803, 15, 'AC-321', 'Available', NULL),
(804, 15, 'AC-322', 'Available', NULL),
(805, 15, 'AC-323', 'Available', NULL),
(806, 15, 'AC-324', 'Available', NULL),
(807, 15, 'AC-325', 'Available', NULL),
(808, 15, 'AC-326', 'Available', NULL),
(809, 15, 'AC-327', 'Available', NULL),
(810, 15, 'AC-328', 'Available', NULL),
(811, 15, 'AC-329', 'Available', NULL),
(812, 15, 'AC-330', 'Available', NULL),
(813, 15, 'AC-331', 'Available', NULL),
(814, 15, 'AC-332', 'Available', NULL),
(815, 15, 'AC-333', 'Available', NULL),
(816, 15, 'AC-334', 'Available', NULL),
(817, 15, 'AC-335', 'Available', NULL),
(818, 15, 'AC-336', 'Available', NULL),
(819, 15, 'AC-337', 'Available', NULL),
(820, 15, 'AC-338', 'Available', NULL),
(821, 15, 'AC-339', 'Available', NULL),
(822, 15, 'AC-340', 'Available', NULL),
(823, 15, 'AC-341', 'Available', NULL),
(824, 15, 'AC-342', 'Available', NULL),
(825, 15, 'AC-343', 'Available', NULL),
(826, 15, 'AC-344', 'Available', NULL),
(827, 15, 'AC-345', 'Available', NULL),
(828, 15, 'AC-346', 'Available', NULL),
(829, 15, 'AC-347', 'Available', NULL),
(830, 15, 'AC-348', 'Available', NULL),
(831, 15, 'AC-349', 'Available', NULL),
(832, 15, 'AC-350', 'Available', NULL),
(833, 15, 'AC-351', 'Available', NULL),
(834, 15, 'AC-352', 'Available', NULL),
(835, 15, 'AC-353', 'Available', NULL),
(836, 15, 'AC-354', 'Available', NULL),
(837, 15, 'AC-355', 'Available', NULL),
(838, 15, 'AC-356', 'Available', NULL),
(839, 15, 'AC-357', 'Available', NULL),
(840, 15, 'AC-358', 'Available', NULL),
(841, 15, 'AC-359', 'Available', NULL),
(842, 15, 'AC-360', 'Available', NULL),
(843, 15, 'AC-361', 'Available', NULL),
(844, 15, 'AC-362', 'Available', NULL),
(845, 15, 'AC-363', 'Available', NULL),
(846, 15, 'AC-364', 'Available', NULL),
(847, 15, 'AC-365', 'Available', NULL),
(848, 15, 'AC-366', 'Available', NULL),
(849, 15, 'AC-367', 'Available', NULL),
(850, 15, 'AC-368', 'Available', NULL),
(851, 15, 'AC-369', 'Available', NULL),
(852, 15, 'AC-370', 'Available', NULL),
(853, 15, 'AC-371', 'Available', NULL),
(854, 15, 'AC-372', 'Available', NULL),
(855, 15, 'AC-373', 'Available', NULL),
(856, 15, 'AC-374', 'Available', NULL),
(857, 15, 'AC-375', 'Available', NULL),
(858, 15, 'AC-376', 'Available', NULL),
(859, 15, 'AC-377', 'Available', NULL),
(860, 15, 'AC-378', 'Available', NULL),
(861, 15, 'AC-379', 'Available', NULL),
(862, 15, 'AC-380', 'Available', NULL),
(863, 15, 'AC-381', 'Available', NULL),
(864, 15, 'AC-382', 'Available', NULL),
(865, 15, 'AC-383', 'Available', NULL),
(866, 15, 'AC-384', 'Available', NULL),
(867, 15, 'AC-385', 'Available', NULL),
(868, 15, 'AC-386', 'Available', NULL),
(869, 15, 'AC-387', 'Available', NULL),
(870, 15, 'AC-388', 'Available', NULL),
(871, 15, 'AC-389', 'Available', NULL),
(872, 15, 'AC-390', 'Available', NULL),
(873, 15, 'AC-391', 'Available', NULL),
(874, 15, 'AC-392', 'Available', NULL),
(875, 15, 'AC-393', 'Available', NULL),
(876, 15, 'AC-394', 'Available', NULL),
(877, 15, 'AC-395', 'Available', NULL),
(878, 15, 'AC-396', 'Available', NULL),
(879, 15, 'AC-397', 'Available', NULL),
(880, 15, 'AC-398', 'Available', NULL),
(881, 15, 'AC-399', 'Available', NULL),
(882, 15, 'AC-400', 'Available', NULL),
(883, 15, 'AC-401', 'Available', NULL),
(884, 15, 'AC-402', 'Available', NULL),
(885, 15, 'AC-403', 'Available', NULL),
(886, 15, 'AC-404', 'Available', NULL),
(887, 15, 'AC-405', 'Available', NULL),
(888, 15, 'AC-406', 'Available', NULL),
(889, 15, 'AC-407', 'Available', NULL),
(890, 15, 'AC-408', 'Available', NULL),
(891, 15, 'AC-409', 'Available', NULL),
(892, 15, 'AC-410', 'Available', NULL),
(893, 15, 'AC-411', 'Available', NULL),
(894, 15, 'AC-412', 'Available', NULL),
(895, 15, 'AC-413', 'Available', NULL),
(896, 15, 'AC-414', 'Available', NULL),
(897, 15, 'AC-415', 'Available', NULL),
(898, 15, 'AC-416', 'Available', NULL),
(899, 15, 'AC-417', 'Available', NULL),
(900, 15, 'AC-418', 'Available', NULL),
(901, 15, 'AC-419', 'Available', NULL),
(902, 15, 'AC-420', 'Available', NULL),
(903, 15, 'AC-421', 'Available', NULL),
(904, 15, 'AC-422', 'Available', NULL),
(905, 15, 'AC-423', 'Available', NULL),
(906, 15, 'AC-424', 'Available', NULL),
(907, 15, 'AC-425', 'Available', NULL),
(908, 15, 'AC-426', 'Available', NULL),
(909, 15, 'AC-427', 'Available', NULL),
(910, 15, 'AC-428', 'Available', NULL),
(911, 15, 'AC-429', 'Available', NULL),
(912, 15, 'AC-430', 'Available', NULL),
(913, 15, 'AC-431', 'Available', NULL),
(914, 15, 'AC-432', 'Available', NULL),
(915, 15, 'AC-433', 'Available', NULL),
(916, 15, 'AC-434', 'Available', NULL),
(917, 15, 'AC-435', 'Available', NULL),
(918, 15, 'AC-436', 'Available', NULL),
(919, 15, 'AC-437', 'Available', NULL),
(920, 15, 'AC-438', 'Available', NULL),
(921, 15, 'AC-439', 'Available', NULL),
(922, 15, 'AC-440', 'Available', NULL),
(923, 15, 'AC-441', 'Available', NULL),
(924, 15, 'AC-442', 'Available', NULL),
(925, 15, 'AC-443', 'Available', NULL),
(926, 15, 'AC-444', 'Available', NULL),
(927, 15, 'AC-445', 'Available', NULL),
(928, 15, 'AC-446', 'Available', NULL),
(929, 15, 'AC-447', 'Available', NULL),
(930, 15, 'AC-448', 'Available', NULL),
(931, 15, 'AC-449', 'Available', NULL),
(932, 15, 'AC-450', 'Available', NULL),
(933, 15, 'AC-451', 'Available', NULL),
(934, 15, 'AC-452', 'Available', NULL),
(935, 15, 'AC-453', 'Available', NULL),
(936, 15, 'AC-454', 'Available', NULL),
(937, 15, 'AC-455', 'Available', NULL),
(938, 15, 'AC-456', 'Available', NULL),
(939, 15, 'AC-457', 'Available', NULL),
(940, 15, 'AC-458', 'Available', NULL),
(941, 15, 'AC-459', 'Available', NULL),
(942, 15, 'AC-460', 'Available', NULL),
(943, 15, 'AC-461', 'Available', NULL),
(944, 15, 'AC-462', 'Available', NULL),
(945, 15, 'AC-463', 'Available', NULL),
(946, 15, 'AC-464', 'Available', NULL),
(947, 15, 'AC-465', 'Available', NULL),
(948, 15, 'AC-466', 'Available', NULL),
(949, 15, 'AC-467', 'Available', NULL),
(950, 15, 'AC-468', 'Available', NULL),
(951, 15, 'AC-469', 'Available', NULL),
(952, 15, 'AC-470', 'Available', NULL),
(953, 15, 'AC-471', 'Available', NULL),
(954, 15, 'AC-472', 'Available', NULL),
(955, 15, 'AC-473', 'Available', NULL),
(956, 15, 'AC-474', 'Available', NULL),
(957, 15, 'AC-475', 'Available', NULL),
(958, 15, 'AC-476', 'Available', NULL),
(959, 15, 'AC-477', 'Available', NULL),
(960, 15, 'AC-478', 'Available', NULL),
(961, 15, 'AC-479', 'Available', NULL),
(962, 15, 'AC-480', 'Available', NULL),
(963, 15, 'AC-481', 'Available', NULL),
(964, 15, 'AC-482', 'Available', NULL),
(965, 15, 'AC-483', 'Available', NULL),
(966, 15, 'AC-484', 'Available', NULL),
(967, 15, 'AC-485', 'Available', NULL),
(968, 15, 'AC-486', 'Available', NULL),
(969, 15, 'AC-487', 'Available', NULL),
(970, 15, 'AC-488', 'Available', NULL),
(971, 15, 'AC-489', 'Available', NULL),
(972, 15, 'AC-490', 'Available', NULL),
(973, 15, 'AC-491', 'Available', NULL),
(974, 15, 'AC-492', 'Available', NULL),
(975, 15, 'AC-493', 'Available', NULL),
(976, 15, 'AC-494', 'Available', NULL),
(977, 15, 'AC-495', 'Available', NULL),
(978, 15, 'AC-496', 'Available', NULL),
(979, 15, 'AC-497', 'Available', NULL),
(980, 15, 'AC-498', 'Available', NULL),
(981, 15, 'AC-499', 'Available', NULL),
(982, 15, 'AC-500', 'Available', NULL),
(983, 16, 'AC-1', 'Available', NULL),
(984, 16, 'AC-2', 'Available', NULL),
(985, 16, 'AC-3', 'Available', NULL),
(986, 16, 'AC-4', 'Available', NULL),
(987, 16, 'AC-5', 'Available', NULL),
(988, 16, 'AC-6', 'Available', NULL),
(989, 16, 'AC-7', 'Available', NULL),
(990, 16, 'AC-8', 'Available', NULL),
(991, 16, 'AC-9', 'Available', NULL),
(992, 16, 'AC-10', 'Available', NULL),
(993, 16, 'AC-11', 'Available', NULL),
(994, 16, 'AC-12', 'Available', NULL),
(995, 17, 'AC-1', 'Available', NULL),
(996, 17, 'AC-2', 'Available', NULL),
(997, 17, 'AC-3', 'Available', NULL),
(998, 17, 'AC-4', 'Available', NULL),
(999, 17, 'AC-5', 'Available', NULL),
(1000, 17, 'AC-6', 'Available', NULL),
(1001, 17, 'AC-7', 'Available', NULL),
(1002, 17, 'AC-8', 'Available', NULL),
(1003, 17, 'AC-9', 'Available', NULL),
(1004, 17, 'AC-10', 'Available', NULL),
(1005, 17, 'AC-11', 'Available', NULL),
(1006, 17, 'AC-12', 'Available', NULL),
(1007, 17, 'AC-13', 'Available', NULL),
(1008, 17, 'AC-14', 'Available', NULL),
(1009, 17, 'AC-15', 'Available', NULL),
(1010, 17, 'AC-16', 'Available', NULL),
(1011, 17, 'AC-17', 'Available', NULL),
(1012, 17, 'AC-18', 'Available', NULL),
(1013, 17, 'AC-19', 'Available', NULL),
(1014, 17, 'AC-20', 'Available', NULL),
(1015, 17, 'AC-21', 'Available', NULL),
(1016, 17, 'AC-22', 'Available', NULL),
(1017, 17, 'AC-23', 'Available', NULL),
(1018, 17, 'AC-24', 'Available', NULL),
(1019, 17, 'AC-25', 'Available', NULL),
(1020, 17, 'AC-26', 'Available', NULL),
(1021, 17, 'AC-27', 'Available', NULL),
(1022, 17, 'AC-28', 'Available', NULL),
(1023, 17, 'AC-29', 'Available', NULL),
(1024, 17, 'AC-30', 'Available', NULL),
(1025, 17, 'AC-31', 'Available', NULL),
(1026, 17, 'AC-32', 'Available', NULL),
(1027, 17, 'AC-33', 'Available', NULL),
(1028, 17, 'AC-34', 'Available', NULL),
(1029, 17, 'AC-35', 'Available', NULL),
(1030, 17, 'AC-36', 'Available', NULL),
(1031, 17, 'AC-37', 'Available', NULL),
(1032, 17, 'AC-38', 'Available', NULL),
(1033, 17, 'AC-39', 'Available', NULL),
(1034, 17, 'AC-40', 'Available', NULL),
(1035, 17, 'AC-41', 'Available', NULL),
(1036, 17, 'AC-42', 'Available', NULL),
(1037, 17, 'AC-43', 'Available', NULL),
(1038, 17, 'AC-44', 'Available', NULL),
(1039, 17, 'AC-45', 'Available', NULL),
(1040, 17, 'AC-46', 'Available', NULL),
(1041, 17, 'AC-47', 'Available', NULL),
(1042, 17, 'AC-48', 'Available', NULL),
(1043, 18, 'SU-1', 'Available', NULL),
(1044, 18, 'SU-2', 'Available', NULL),
(1045, 18, 'SU-3', 'Available', NULL),
(1046, 18, 'SU-4', 'Available', NULL),
(1047, 18, 'SU-5', 'Available', NULL),
(1048, 18, 'SU-6', 'Available', NULL),
(1049, 18, 'SU-7', 'Available', NULL),
(1050, 18, 'SU-8', 'Available', NULL),
(1051, 18, 'SU-9', 'Available', NULL),
(1052, 18, 'SU-10', 'Available', NULL),
(1053, 18, 'SU-11', 'Available', NULL),
(1054, 18, 'SU-12', 'Available', NULL),
(1055, 18, 'SU-13', 'Available', NULL),
(1056, 18, 'SU-14', 'Available', NULL),
(1057, 18, 'SU-15', 'Available', NULL),
(1058, 18, 'SU-16', 'Available', NULL),
(1059, 18, 'SU-17', 'Available', NULL),
(1060, 18, 'SU-18', 'Available', NULL),
(1061, 18, 'SU-19', 'Available', NULL),
(1062, 18, 'SU-20', 'Available', NULL),
(1063, 18, 'SU-21', 'Available', NULL),
(1064, 18, 'SU-22', 'Available', NULL),
(1065, 18, 'SU-23', 'Available', NULL),
(1066, 18, 'SU-24', 'Available', NULL),
(1067, 18, 'SU-25', 'Available', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `stalled_vehicles`
--

CREATE TABLE `stalled_vehicles` (
  `id` int(11) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stalled_vehicles`
--

INSERT INTO `stalled_vehicles` (`id`, `description`) VALUES
(1, 'Stalled vehicle owners must notify GSU, through the security officers immediately, with their name, the vehicles license plate number, and parking location.'),
(2, 'A grace period of up to 12 hours may be allowed. No extensions will be granted. A lost/broken vehicle key is considered a stalled vehicle and falls under this policy. If 12 hours is not sufficient time to remove the vehicle, the owner is requiered to contact a towing company through any means to have the vehicle removed at their expense within 3 hours.');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `fullname` varchar(100) NOT NULL,
  `profile_pic` varchar(255) DEFAULT 'default_avatar.png',
  `phone_number` varchar(20) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `user_role_id` int(11) NOT NULL,
  `department_code` varchar(10) DEFAULT NULL,
  `vehicle_id` int(11) DEFAULT NULL,
  `id_number` varchar(50) NOT NULL,
  `plate_number` varchar(20) DEFAULT NULL,
  `driver_license` varchar(255) DEFAULT NULL,
  `or_cr_photo` varchar(255) DEFAULT NULL,
  `status` enum('Pending','Granted','Denied') DEFAULT 'Pending',
  `strike_count` int(11) DEFAULT 0,
  `Gate_access` enum('Pending','Access','Denied') DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- Table structure for table `user_roles`
--

CREATE TABLE `user_roles` (
  `id` int(11) NOT NULL,
  `role_name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_roles`
--

INSERT INTO `user_roles` (`id`, `role_name`) VALUES
(1, 'Admin'),
(2, 'Guard'),
(3, 'Student'),
(4, 'Staff'),
(5, 'Visitors');

-- --------------------------------------------------------

--
-- Table structure for table `user_suspensions`
--

CREATE TABLE `user_suspensions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `strike_count` int(11) DEFAULT 0,
  `is_suspended` tinyint(1) DEFAULT 0,
  `suspended_until` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vehicles`
--

CREATE TABLE `vehicles` (
  `id` int(11) NOT NULL,
  `vehicle_name` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `vehicles`
--

INSERT INTO `vehicles` (`id`, `vehicle_name`) VALUES
(1, 'Motorcycles'),
(2, 'Automobiles');

-- --------------------------------------------------------

--
-- Table structure for table `violations_log`
--

CREATE TABLE `violations_log` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `violator_name` varchar(100) NOT NULL,
  `id_number` varchar(20) NOT NULL,
  `user_type` enum('Student','Staff') NOT NULL,
  `plate_number` varchar(15) DEFAULT NULL,
  `violation_type` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `guard_id` varchar(20) DEFAULT NULL,
  `status` enum('Active','Cleared') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `violations_log`
--

INSERT INTO `violations_log` (`id`, `user_id`, `violator_name`, `id_number`, `user_type`, `plate_number`, `violation_type`, `description`, `guard_id`, `status`, `created_at`) VALUES
(4, 2, 'John Michael M. Toldanes', '', 'Student', 'JMT23', 'Wrong Parking', '1', '3', 'Active', '2026-05-18 12:31:06');

-- --------------------------------------------------------

--
-- Table structure for table `violation_sanctions`
--

CREATE TABLE `violation_sanctions` (
  `id` int(11) NOT NULL,
  `sanctions_name` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `violation_sanctions`
--

INSERT INTO `violation_sanctions` (`id`, `sanctions_name`, `description`) VALUES
(1, '1st Offense', 'Issuance of warning ticket by Security Guards'),
(2, '2nd Offense', 'Suspension of Parking Permit for six (6) months by endorsement of Security Guards to GSU'),
(3, '3rd Offense', 'Revocation of Parking Privileges by endorsement of GSU to VPAF');

-- --------------------------------------------------------

--
-- Table structure for table `violation_types`
--

CREATE TABLE `violation_types` (
  `id` int(11) NOT NULL,
  `violation_name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('Active','Inactive') DEFAULT 'Active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `violation_types`
--

INSERT INTO `violation_types` (`id`, `violation_name`, `description`, `status`) VALUES
(1, 'Wrong Parking', 'Vehicles are not parked at the designated parking area.', 'Active'),
(2, 'Over Speeding', 'The driver has violated the approved speed limit within the College premises, which is 15 kph.', 'Active'),
(3, 'Use of Motorcycle Mufflers', 'Mufflers are strictly prohibited inside the College premises.', 'Active'),
(4, 'Explicit disrespect', 'Explicit disrespect to Security Personnel implementing the Policy.', 'Active');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `departmentcode` (`departmentcode`);

--
-- Indexes for table `gate_logs`
--
ALTER TABLE `gate_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_gate_user_log` (`user_id`);

--
-- Indexes for table `general_informations`
--
ALTER TABLE `general_informations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_notif_user` (`user_id`),
  ADD KEY `fk_notif_sender` (`sender_id`);

--
-- Indexes for table `offense_sanctions`
--
ALTER TABLE `offense_sanctions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `parking_areas`
--
ALTER TABLE `parking_areas`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `parking_rules`
--
ALTER TABLE `parking_rules`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `parking_slots`
--
ALTER TABLE `parking_slots`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_slot_area` (`area_id`),
  ADD KEY `fk_slot_user` (`parked_user_id`);

--
-- Indexes for table `stalled_vehicles`
--
ALTER TABLE `stalled_vehicles`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `id_number` (`id_number`),
  ADD KEY `fk_user_role` (`user_role_id`),
  ADD KEY `fk_user_dept` (`department_code`),
  ADD KEY `fk_user_vehicle` (`vehicle_id`);

--
-- Indexes for table `user_roles`
--
ALTER TABLE `user_roles`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `user_suspensions`
--
ALTER TABLE `user_suspensions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `vehicles`
--
ALTER TABLE `vehicles`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `violations_log`
--
ALTER TABLE `violations_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `violation_sanctions`
--
ALTER TABLE `violation_sanctions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `violation_types`
--
ALTER TABLE `violation_types`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `gate_logs`
--
ALTER TABLE `gate_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `general_informations`
--
ALTER TABLE `general_informations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `offense_sanctions`
--
ALTER TABLE `offense_sanctions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `parking_areas`
--
ALTER TABLE `parking_areas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `parking_rules`
--
ALTER TABLE `parking_rules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `parking_slots`
--
ALTER TABLE `parking_slots`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1068;

--
-- AUTO_INCREMENT for table `stalled_vehicles`
--
ALTER TABLE `stalled_vehicles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `user_roles`
--
ALTER TABLE `user_roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `user_suspensions`
--
ALTER TABLE `user_suspensions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `vehicles`
--
ALTER TABLE `vehicles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `violations_log`
--
ALTER TABLE `violations_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `violation_sanctions`
--
ALTER TABLE `violation_sanctions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `violation_types`
--
ALTER TABLE `violation_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `gate_logs`
--
ALTER TABLE `gate_logs`
  ADD CONSTRAINT `fk_gate_user_log` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notif_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `parking_slots`
--
ALTER TABLE `parking_slots`
  ADD CONSTRAINT `fk_slot_area` FOREIGN KEY (`area_id`) REFERENCES `parking_areas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_slot_user` FOREIGN KEY (`parked_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_user_dept` FOREIGN KEY (`department_code`) REFERENCES `departments` (`departmentcode`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_user_role` FOREIGN KEY (`user_role_id`) REFERENCES `user_roles` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_user_vehicle` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `user_suspensions`
--
ALTER TABLE `user_suspensions`
  ADD CONSTRAINT `user_suspensions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `violations_log`
--
ALTER TABLE `violations_log`
  ADD CONSTRAINT `violations_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
