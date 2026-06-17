-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 15, 2025 at 08:45 PM
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
-- Database: `sakuragi_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `attendance_id` bigint(20) NOT NULL,
  `employee_id` bigint(20) NOT NULL,
  `date` date NOT NULL,
  `time_in` time DEFAULT NULL,
  `time_out` time DEFAULT NULL,
  `total_hours` decimal(5,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `log_id` bigint(20) NOT NULL,
  `user_id` bigint(20) NOT NULL,
  `action` varchar(255) NOT NULL,
  `details` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `branches`
--

CREATE TABLE `branches` (
  `branch_id` bigint(20) NOT NULL,
  `branch_name` varchar(255) NOT NULL,
  `location` text NOT NULL,
  `phone_number` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `branches`
--

INSERT INTO `branches` (`branch_id`, `branch_name`, `location`, `phone_number`) VALUES
(2, 'Davao', '456 Davao St', '0987654321'),
(3, 'Kidapawan', '789 Kidapawan St', '0921654652'),
(4, 'Tagum', '123 Tagum St', '09123456789');

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `department_id` int(11) NOT NULL,
  `department_name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`department_id`, `department_name`) VALUES
(1, 'Tailoring'),
(2, 'Printing'),
(3, 'Embroidery'),
(4, 'Quality Control'),
(5, 'Packaging'),
(6, 'Production'),
(7, 'Sales & Customer Service'),
(8, 'Inventory'),
(9, 'Administration'),
(10, 'Operations');

-- --------------------------------------------------------

--
-- Table structure for table `employees`
--

CREATE TABLE `employees` (
  `employee_id` bigint(20) NOT NULL,
  `user_id` bigint(20) NOT NULL,
  `branch_id` bigint(20) NOT NULL,
  `hire_date` date NOT NULL,
  `salary` decimal(10,2) NOT NULL,
  `position_id` int(11) DEFAULT NULL,
  `shift_id` int(11) DEFAULT NULL,
  `status_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employees`
--

INSERT INTO `employees` (`employee_id`, `user_id`, `branch_id`, `hire_date`, `salary`, `position_id`, `shift_id`, `status_id`) VALUES
(8, 11, 3, '2025-05-06', 2828867.00, 1, 2, 1),
(9, 12, 2, '2025-05-24', 747474.00, 2, 1, 1),
(11, 16, 2, '2025-05-15', 0.00, 1, 3, 2),
(12, 18, 2, '2025-05-15', 0.00, 2, 1, 1),
(13, 20, 2, '2025-05-15', 0.00, 4, 1, 1),
(14, 22, 4, '2025-05-15', 0.00, 4, 3, 1);

-- --------------------------------------------------------

--
-- Table structure for table `feedback`
--

CREATE TABLE `feedback` (
  `review_id` bigint(20) NOT NULL,
  `user_id` bigint(20) NOT NULL,
  `order_id` bigint(20) NOT NULL,
  `rating` int(11) DEFAULT NULL CHECK (`rating` between 1 and 5),
  `comments` text DEFAULT NULL,
  `review_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inventory`
--

CREATE TABLE `inventory` (
  `inventory_id` bigint(20) NOT NULL,
  `branch_id` bigint(20) NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `supplier_id` bigint(20) DEFAULT NULL,
  `supply_type_id` bigint(20) DEFAULT NULL,
  `quantity` int(11) DEFAULT 0,
  `reorder_level` int(11) DEFAULT 10,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory`
--

INSERT INTO `inventory` (`inventory_id`, `branch_id`, `item_name`, `supplier_id`, `supply_type_id`, `quantity`, `reorder_level`, `last_updated`) VALUES
(31, 2, 'Cotton Twill Fabric - White', 1, 1, 120, 30, '2025-05-15 04:15:36'),
(32, 2, 'Canvas Fabric Roll - Natural', 3, 1, 100, 20, '2025-05-15 04:15:36'),
(33, 2, 'Jersey Knit - Black', 3, 1, 150, 25, '2025-05-15 04:15:36'),
(34, 2, 'Linen Blend Fabric - Beige', 1, 1, 80, 15, '2025-05-15 04:15:36'),
(35, 2, 'Polyester Satin - Red', 1, 1, 90, 20, '2025-05-15 04:15:36'),
(36, 2, 'Mesh Fabric - White', 1, 1, 60, 10, '2025-05-15 04:15:36'),
(37, 2, 'Fleece Fabric - Blue', 10, 1, 100, 20, '2025-05-15 04:15:36'),
(38, 2, 'Denim Fabric - Light Blue', 3, 1, 70, 15, '2025-05-15 04:15:36'),
(39, 2, 'Wool Blend Fabric - Gray', 1, 1, 50, 10, '2025-05-15 04:15:36'),
(40, 2, 'Tulle Fabric - Pink', 1, 1, 45, 10, '2025-05-15 04:15:36'),
(41, 2, 'Canvas Fabric Roll - Army Green', 3, 1, 65, 15, '2025-05-15 04:15:36'),
(42, 2, 'Cotton Voile - Light Yellow', 10, 1, 55, 10, '2025-05-15 04:15:36'),
(43, 2, 'Poplin Fabric - Black', 3, 1, 80, 20, '2025-05-15 04:15:36'),
(44, 2, 'Cotton Interlock - White', 1, 1, 90, 20, '2025-05-15 04:15:36'),
(45, 2, 'Hemp Fabric Roll - Natural', 3, 1, 40, 10, '2025-05-15 04:15:36'),
(46, 2, 'Rayon Fabric - Sky Blue', 3, 1, 50, 12, '2025-05-15 04:15:36'),
(47, 2, 'Silk Charmeuse - Ivory', 3, 1, 30, 8, '2025-05-15 04:15:36'),
(48, 2, 'Cotton Chambray - Light Blue', 1, 1, 60, 15, '2025-05-15 04:15:36'),
(49, 2, 'Flannel Fabric - Plaid Red', 3, 1, 100, 20, '2025-05-15 04:15:36'),
(50, 2, 'Corduroy Fabric - Brown', 3, 1, 50, 10, '2025-05-15 04:15:36'),
(51, 2, 'Polyester Mesh - Neon Orange', 1, 1, 70, 15, '2025-05-15 04:15:36'),
(52, 2, 'Terry Cloth - White', 1, 1, 55, 10, '2025-05-15 04:15:36'),
(53, 2, 'Cotton Broadcloth - Navy', 1, 1, 85, 20, '2025-05-15 04:15:36'),
(54, 2, 'Double Gauze Cotton - Cream', 3, 1, 60, 12, '2025-05-15 04:15:36'),
(55, 2, 'Sequin Fabric - Silver', 3, 1, 35, 8, '2025-05-15 04:15:36'),
(56, 2, 'Spandex Fabric - Black', 3, 1, 90, 25, '2025-05-15 04:15:36'),
(57, 2, 'Velvet Fabric - Emerald', 3, 1, 40, 10, '2025-05-15 04:15:36'),
(58, 2, 'Printed Floral Cotton', 3, 1, 100, 20, '2025-05-15 04:15:36'),
(59, 2, 'Dobby Cotton - Beige', 3, 1, 60, 12, '2025-05-15 04:15:36'),
(60, 2, 'Printed Canvas Roll - Tropical', 3, 1, 45, 10, '2025-05-15 04:15:36'),
(61, 2, 'Polyester Thread - Red', 1, 2, 200, 40, '2025-05-15 04:16:01'),
(62, 2, 'Cotton Thread - Black', 8, 2, 250, 50, '2025-05-15 04:16:01'),
(63, 2, 'Rayon Embroidery Thread - Gold', 8, 2, 180, 30, '2025-05-15 04:16:01'),
(64, 2, 'Metallic Thread - Silver', 10, 2, 100, 25, '2025-05-15 04:16:01'),
(65, 2, 'Nylon Thread - White', 1, 2, 120, 30, '2025-05-15 04:16:01'),
(66, 2, 'Wooly Nylon - Navy Blue', 8, 2, 80, 20, '2025-05-15 04:16:01'),
(67, 2, 'Silk Thread - Light Gray', 10, 2, 60, 15, '2025-05-15 04:16:01'),
(68, 2, 'Variegated Thread - Rainbow', 8, 2, 40, 10, '2025-05-15 04:16:01'),
(69, 2, 'UV Reactive Thread - Glow Green', 1, 2, 30, 5, '2025-05-15 04:16:01'),
(70, 2, 'Fluorescent Thread - Neon Pink', 8, 2, 90, 25, '2025-05-15 04:16:01'),
(71, 2, 'Heavy-Duty Thread - Brown', 4, 2, 110, 20, '2025-05-15 04:16:01'),
(72, 2, 'Thread Cone - Multi-Pack', 10, 2, 300, 50, '2025-05-15 04:16:01'),
(73, 2, 'Bobbin Thread - Black', 8, 2, 130, 30, '2025-05-15 04:16:01'),
(74, 2, 'Elastic Thread - White', 8, 2, 60, 10, '2025-05-15 04:16:01'),
(75, 2, 'Serger Thread - Red', 1, 2, 150, 35, '2025-05-15 04:16:01'),
(76, 2, 'Tear-Away Stabilizer - Medium', 8, 9, 50, 10, '2025-05-15 04:16:01'),
(77, 2, 'Cut-Away Stabilizer - Heavy', 8, 9, 40, 10, '2025-05-15 04:16:01'),
(78, 2, 'Water-Soluble Stabilizer Sheet', 8, 9, 30, 5, '2025-05-15 04:16:01'),
(79, 2, 'Iron-On Embroidery Backing', 8, 9, 45, 10, '2025-05-15 04:16:01'),
(80, 2, 'Spray Adhesive for Backing', 5, 9, 60, 15, '2025-05-15 04:16:01'),
(81, 2, 'Fusible Poly Mesh Roll', 8, 9, 55, 10, '2025-05-15 04:16:01'),
(82, 2, 'No-Show Nylon Mesh', 8, 9, 35, 8, '2025-05-15 04:16:01'),
(83, 2, 'Heat Seal Film Roll', 8, 9, 20, 5, '2025-05-15 04:16:01'),
(84, 2, 'Sticky Back Tear-Away Sheet', 8, 9, 30, 6, '2025-05-15 04:16:01'),
(85, 2, 'Transparent Film Backing', 8, 9, 40, 8, '2025-05-15 04:16:01'),
(86, 2, 'Stabilizer Pack - Mixed Sizes', 8, 9, 25, 5, '2025-05-15 04:16:01'),
(87, 2, 'Soft Poly Mesh - Skin Tone', 8, 9, 30, 6, '2025-05-15 04:16:01'),
(88, 2, 'Adhesive Backing Tape', 5, 9, 35, 7, '2025-05-15 04:16:01'),
(89, 2, 'Lightweight Tear-Away White', 8, 9, 40, 8, '2025-05-15 04:16:01'),
(90, 2, 'Backing Roll - Jumbo (100m)', 8, 9, 20, 5, '2025-05-15 04:16:01'),
(91, 2, 'Sublimation Ink - Cyan', 2, 3, 80, 20, '2025-05-15 04:16:29'),
(92, 2, 'Sublimation Ink - Magenta', 6, 3, 75, 20, '2025-05-15 04:16:29'),
(93, 2, 'Sublimation Ink - Yellow', 6, 3, 70, 20, '2025-05-15 04:16:29'),
(94, 2, 'Sublimation Ink - Black', 2, 3, 100, 25, '2025-05-15 04:16:29'),
(95, 2, 'Screen Printing Ink - White (1L)', 6, 3, 90, 20, '2025-05-15 04:16:29'),
(96, 2, 'Screen Printing Ink - Red (500ml)', 2, 3, 50, 15, '2025-05-15 04:16:29'),
(97, 2, 'Discharge Ink - Base (1kg)', 2, 3, 40, 10, '2025-05-15 04:16:29'),
(98, 2, 'Pigment Ink - Navy Blue', 2, 3, 60, 15, '2025-05-15 04:16:29'),
(99, 2, 'Transfer Paper A4 - 100 sheets', 2, 4, 70, 15, '2025-05-15 04:16:29'),
(100, 2, 'Transfer Paper A3 - 50 sheets', 6, 4, 55, 10, '2025-05-15 04:16:29'),
(101, 2, 'T-Shirt Transfer Paper - Dark Fabric', 6, 4, 60, 12, '2025-05-15 04:16:29'),
(102, 2, 'Foil Transfer Sheet - Gold', 5, 4, 45, 10, '2025-05-15 04:16:29'),
(103, 2, 'Foil Transfer Sheet - Silver', 5, 4, 40, 10, '2025-05-15 04:16:29'),
(104, 2, 'Printable HTV Sheets - Pack of 20', 5, 4, 35, 10, '2025-05-15 04:16:29'),
(105, 2, 'Sublimation Paper - Roll (50m)', 6, 4, 25, 5, '2025-05-15 04:16:29'),
(106, 2, 'Heat Transfer Vinyl - Red', 5, 6, 100, 20, '2025-05-15 04:16:29'),
(107, 2, 'Heat Transfer Vinyl - Blue', 5, 6, 90, 20, '2025-05-15 04:16:29'),
(108, 2, 'HTV Glitter Vinyl - Silver', 5, 6, 60, 15, '2025-05-15 04:16:29'),
(109, 2, 'HTV Holographic - Rainbow', 5, 6, 40, 10, '2025-05-15 04:16:29'),
(110, 2, 'Glow-in-the-Dark Vinyl - Green', 5, 6, 25, 5, '2025-05-15 04:16:29'),
(111, 2, 'Flock HTV - Black', 5, 6, 30, 6, '2025-05-15 04:16:29'),
(112, 2, 'PU Vinyl Roll - White', 5, 6, 50, 10, '2025-05-15 04:16:29'),
(113, 2, 'Teflon Heat Press Sheet (Reusable)', 5, 12, 35, 10, '2025-05-15 04:16:29'),
(114, 2, 'Silicone Heat Press Mat', 5, 12, 20, 5, '2025-05-15 04:16:29'),
(115, 2, 'Heat Resistant Tape - Roll', 5, 12, 100, 30, '2025-05-15 04:16:29'),
(116, 2, 'Protective Sheet for Heat Press', 5, 12, 30, 8, '2025-05-15 04:16:29'),
(117, 2, 'Heat Press Pillow (12x14)', 5, 12, 25, 5, '2025-05-15 04:16:29'),
(118, 2, 'Anti-Scorch Sheet - 3 Pack', 5, 12, 40, 10, '2025-05-15 04:16:29'),
(119, 2, 'Multipurpose PTFE Sheets', 5, 12, 50, 15, '2025-05-15 04:16:29'),
(120, 2, 'Tailor Scissors - 10in', 4, 7, 25, 5, '2025-05-15 04:18:02'),
(121, 2, 'Embroidery Scissors - Curved Tip', 4, 7, 15, 5, '2025-05-15 04:18:02'),
(122, 2, 'Pinking Shears - Serrated', 4, 7, 10, 3, '2025-05-15 04:18:02'),
(123, 2, 'Rotary Cutter - 45mm', 4, 7, 20, 5, '2025-05-15 04:18:02'),
(124, 2, 'Fabric Snips - Stainless', 4, 7, 18, 5, '2025-05-15 04:18:02'),
(125, 2, 'Seam Ripper Set', 4, 7, 30, 10, '2025-05-15 04:18:02'),
(126, 2, 'Machine Needles - Assorted Sizes', 4, 7, 100, 30, '2025-05-15 04:18:02'),
(127, 2, 'Hand Sewing Needles - Size 9/12', 4, 7, 200, 50, '2025-05-15 04:18:02'),
(128, 2, 'Embroidery Needles - Gold Eye', 4, 7, 60, 20, '2025-05-15 04:18:02'),
(129, 2, 'Twin Needles for Machines', 4, 7, 50, 15, '2025-05-15 04:18:02'),
(130, 2, 'Serger Needles - ELx705', 4, 7, 40, 10, '2025-05-15 04:18:02'),
(131, 2, 'Leather Needles', 4, 7, 35, 8, '2025-05-15 04:18:02'),
(132, 2, 'Ball Point Needles', 4, 7, 50, 12, '2025-05-15 04:18:02'),
(133, 2, 'Universal Sewing Needles - Size 14', 4, 7, 90, 25, '2025-05-15 04:18:02'),
(134, 2, 'Heavy Duty Shears - Carbon Steel', 4, 7, 10, 3, '2025-05-15 04:18:02'),
(135, 2, 'Zipper Coil 10in - Black', 7, 8, 100, 30, '2025-05-15 04:18:02'),
(136, 2, 'Zipper Coil 14in - White', 7, 8, 90, 25, '2025-05-15 04:18:02'),
(137, 2, 'Invisible Zipper 8in - Beige', 7, 8, 80, 20, '2025-05-15 04:18:02'),
(138, 2, 'Metal Zipper 12in - Silver Teeth', 7, 8, 60, 15, '2025-05-15 04:18:02'),
(139, 2, 'Plastic Zipper - Neon Colors', 7, 8, 120, 30, '2025-05-15 04:18:02'),
(140, 2, 'Button Set - 1cm Black Plastic (100pcs)', 7, 8, 500, 100, '2025-05-15 04:18:02'),
(141, 2, 'Wooden Buttons - Assorted Sizes', 7, 8, 300, 80, '2025-05-15 04:18:02'),
(142, 2, 'Snap Buttons - Metal', 7, 8, 200, 50, '2025-05-15 04:18:02'),
(143, 2, 'Press Stud Kit', 7, 8, 150, 40, '2025-05-15 04:18:02'),
(144, 2, 'Hook & Eye Closures (100 pairs)', 7, 8, 100, 30, '2025-05-15 04:18:02'),
(145, 2, 'Jeans Buttons with Tool', 7, 8, 70, 20, '2025-05-15 04:18:02'),
(146, 2, 'Velcro Tape - 1 inch x 5m Roll', 7, 8, 60, 15, '2025-05-15 04:18:02'),
(147, 2, 'Adjustable Sliders - Plastic', 7, 8, 90, 20, '2025-05-15 04:18:02'),
(148, 2, 'Cord Locks - Spring Loaded', 7, 8, 80, 20, '2025-05-15 04:18:02'),
(149, 2, 'Button Hole Cutter Tool', 7, 8, 25, 5, '2025-05-15 04:18:02'),
(150, 2, 'Wooden Embroidery Hoop 4in', 8, 10, 80, 20, '2025-05-15 04:18:34'),
(151, 2, 'Wooden Embroidery Hoop 6in', 8, 10, 60, 15, '2025-05-15 04:18:34'),
(152, 2, 'Plastic Embroidery Frame 5x7', 8, 10, 50, 10, '2025-05-15 04:18:34'),
(153, 2, 'Snap Frame Hoop - Medium', 8, 10, 40, 10, '2025-05-15 04:18:34'),
(154, 2, 'Adjustable Embroidery Hoop Stand', 8, 10, 15, 5, '2025-05-15 04:18:34'),
(155, 2, 'Self-Healing Cutting Mat A3', 9, 11, 25, 8, '2025-05-15 04:18:34'),
(156, 2, 'Self-Healing Cutting Mat A2', 9, 11, 20, 6, '2025-05-15 04:18:34'),
(157, 2, 'Fabric Cutting Mat 12x18in', 9, 11, 18, 5, '2025-05-15 04:18:34'),
(158, 2, 'Rotary Cutting Board - 18x24in', 9, 11, 10, 3, '2025-05-15 04:18:34'),
(159, 2, 'Portable Cutting Mat with Grid', 9, 11, 12, 3, '2025-05-15 04:18:34'),
(160, 2, 'Tote Bag Blank - Natural Canvas', 3, 13, 100, 20, '2025-05-15 04:18:34'),
(161, 2, 'Cotton Shirt Blank - White (S)', 6, 13, 200, 40, '2025-05-15 04:18:34'),
(162, 2, 'Cotton Shirt Blank - Black (M)', 6, 13, 150, 35, '2025-05-15 04:18:34'),
(163, 2, 'Pouch Blank - 8x10in Canvas', 3, 13, 80, 20, '2025-05-15 04:18:34'),
(164, 2, 'Apron Blank - Adjustable Cotton', 3, 13, 60, 15, '2025-05-15 04:18:34'),
(165, 2, 'Drawstring Bag Blank - White', 3, 13, 90, 25, '2025-05-15 04:18:34'),
(166, 2, 'Pillow Cover Blank - 16x16in', 6, 13, 70, 20, '2025-05-15 04:18:34'),
(167, 2, 'Baseball Cap Blank - Navy', 6, 13, 50, 15, '2025-05-15 04:18:34'),
(168, 2, 'Towel Blank - Microfiber Hand Towel', 6, 13, 60, 15, '2025-05-15 04:18:34'),
(169, 2, 'Sublimation Patch Blank - Circle', 6, 13, 80, 20, '2025-05-15 04:18:34'),
(170, 2, 'Polybag Packaging - 10x13in', 7, 14, 400, 100, '2025-05-15 04:18:34'),
(171, 2, 'Kraft Box - Shirt Size', 7, 14, 120, 30, '2025-05-15 04:18:34'),
(172, 2, 'Bubble Mailer - Medium', 7, 14, 150, 50, '2025-05-15 04:18:34'),
(173, 2, 'Garment Tag - Custom Printable', 7, 14, 300, 75, '2025-05-15 04:18:34'),
(174, 2, 'Transparent OPP Bag - Small', 7, 14, 500, 120, '2025-05-15 04:18:34'),
(175, 2, 'Label Sticker Roll - Thank You', 7, 14, 200, 50, '2025-05-15 04:18:34'),
(176, 2, 'Twine for Wrapping - 100m Roll', 7, 14, 60, 15, '2025-05-15 04:18:34'),
(177, 2, 'Hang Tag String with Pin', 7, 14, 250, 60, '2025-05-15 04:18:34'),
(178, 2, 'Packaging Tape - Custom Printed', 7, 14, 80, 20, '2025-05-15 04:18:34'),
(179, 2, 'Gift Box - Foldable (5x5x5)', 7, 14, 90, 25, '2025-05-15 04:18:34'),
(183, 2, 'Socks Blank - Mid Calf', 6, 13, 120, 30, '2025-05-15 09:46:09'),
(184, 2, 'Long Sleeve Tee Blank - XL', 6, 13, 80, 20, '2025-05-15 04:19:06'),
(185, 2, 'Baby Bib Blank - Cotton', 6, 13, 100, 25, '2025-05-15 04:19:06'),
(187, 2, 'Pouch Blank with Zipper - 6x9in', 3, 13, 60, 15, '2025-05-15 04:19:06'),
(188, 2, 'Canvas Sling Bag Blank', 3, 13, 40, 10, '2025-05-15 04:19:06'),
(190, 2, 'Mailer Box - Custom Kraft', 7, 14, 90, 20, '2025-05-15 04:19:06'),
(191, 2, 'Clear Zip Bag - Resealable 6x9in', 7, 14, 300, 60, '2025-05-15 04:19:06'),
(192, 2, 'Hang Tag - Minimal Design', 7, 14, 200, 50, '2025-05-15 04:19:06'),
(193, 2, 'String for Hang Tags - Black', 7, 14, 180, 40, '2025-05-15 04:19:06'),
(194, 2, 'Flat Paper Bag - Medium', 7, 14, 250, 70, '2025-05-15 04:19:06'),
(195, 2, 'Sticker Roll - Logo Labels', 7, 14, 150, 40, '2025-05-15 04:19:06'),
(196, 2, 'Custom Ribbon - 1in x 25yds', 7, 14, 30, 10, '2025-05-15 04:19:06'),
(197, 2, 'Plastic Bag - White Handle (L)', 7, 14, 120, 30, '2025-05-15 04:19:06'),
(198, 2, 'Eco Tape - Kraft Paper', 7, 14, 40, 10, '2025-05-15 04:19:06'),
(199, 2, 'Poly Mailer - Large', 7, 14, 130, 30, '2025-05-15 04:19:06'),
(200, 2, 'Measuring Tape - 60in', 10, 7, 100, 25, '2025-05-15 04:19:06'),
(201, 2, 'Marking Chalk - 10pcs', 10, 7, 60, 15, '2025-05-15 04:19:06'),
(202, 2, 'Fabric Pen - Disappearing Ink', 10, 7, 80, 20, '2025-05-15 04:19:06'),
(203, 2, 'Pin Cushion with Pins', 10, 7, 40, 10, '2025-05-15 04:19:06'),
(204, 2, 'Cutting Ruler - 12in', 10, 11, 20, 5, '2025-05-15 04:19:06'),
(205, 2, 'Blade Refill for Rotary Cutter', 10, 7, 50, 10, '2025-05-15 04:19:06'),
(206, 2, 'Thread Conditioner', 10, 2, 60, 10, '2025-05-15 04:19:06'),
(207, 2, 'Lint Roller', 10, 14, 100, 25, '2025-05-15 04:19:06'),
(208, 2, 'Steam Iron - Mini Craft', 10, 12, 12, 3, '2025-05-15 04:19:06'),
(209, 2, 'Iron Cleaner Stick', 10, 12, 25, 6, '2025-05-15 04:19:06'),
(210, 2, 'Spray Bottle for Water', 10, 14, 60, 15, '2025-05-15 04:19:06'),
(211, 2, 'Clip Set for Fabric Holding', 10, 7, 90, 20, '2025-05-15 04:19:06'),
(212, 2, 'Fabric Glue Stick', 5, 5, 70, 20, '2025-05-15 04:19:06'),
(213, 2, 'Adhesive Velcro Circle Dots', 5, 5, 150, 40, '2025-05-15 04:19:06'),
(214, 2, 'Small Storage Organizer Box', 10, 14, 40, 10, '2025-05-15 04:19:06'),
(215, 2, 'Patch Display Binder', 10, 14, 20, 5, '2025-05-15 04:19:06'),
(216, 2, 'Digital Weighing Scale', 10, 14, 15, 5, '2025-05-15 04:19:06'),
(217, 2, 'Paper Tags with Twine', 7, 14, 180, 40, '2025-05-15 04:19:06'),
(218, 2, 'Shipping Label Stickers (4x6)', 7, 14, 300, 80, '2025-05-15 04:19:06'),
(226, 2, 'Bucket Hat Blank - Blue', 7, 12, 5, 10, '2025-05-15 10:27:42'),
(229, 2, 'Bucket Hat Blank - Blue', 5, 5, 32, 10, '2025-05-15 14:12:09'),
(230, 2, 'Bucket Hat Blank - Green', 7, 5, 13, 10, '2025-05-15 14:14:08');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_stock_log`
--

CREATE TABLE `inventory_stock_log` (
  `log_id` bigint(20) NOT NULL,
  `inventory_id` bigint(20) NOT NULL,
  `change_type` enum('in','out') NOT NULL,
  `quantity` int(11) NOT NULL,
  `supplier_id` bigint(20) DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `invoices`
--

CREATE TABLE `invoices` (
  `invoice_id` bigint(20) NOT NULL,
  `order_id` bigint(20) NOT NULL,
  `customer_id` bigint(20) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `payment_status` enum('Paid','Pending','Overdue') DEFAULT 'Pending',
  `due_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `loyalty`
--

CREATE TABLE `loyalty` (
  `loyalty_id` bigint(20) NOT NULL,
  `user_id` bigint(20) NOT NULL,
  `total_orders` int(11) NOT NULL DEFAULT 0,
  `free_shirts_earned` int(11) NOT NULL DEFAULT 0,
  `free_shirts_claimed` int(11) NOT NULL DEFAULT 0,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` bigint(20) NOT NULL,
  `user_id` bigint(20) NOT NULL,
  `message` text NOT NULL,
  `status` enum('Sent','Pending','Failed') DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `order_id` bigint(20) NOT NULL,
  `branch_id` bigint(20) NOT NULL,
  `user_id` bigint(20) NOT NULL,
  `employee_id` bigint(20) DEFAULT NULL,
  `order_date` datetime DEFAULT current_timestamp(),
  `status` enum('Pending','In Progress','Completed','Cancelled','Refunded') DEFAULT 'Pending',
  `total_price` decimal(10,2) NOT NULL,
  `payment_status` enum('Pending','Paid','Refunded') DEFAULT 'Pending',
  `expected_completion` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `order_custom_data`
--

CREATE TABLE `order_custom_data` (
  `custom_id` bigint(20) NOT NULL,
  `order_id` bigint(20) DEFAULT NULL,
  `name` varchar(100) DEFAULT NULL,
  `number` varchar(50) DEFAULT NULL,
  `size` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `order_details`
--

CREATE TABLE `order_details` (
  `order_detail_id` bigint(20) NOT NULL,
  `order_id` bigint(20) DEFAULT NULL,
  `product_id` bigint(20) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `order_files`
--

CREATE TABLE `order_files` (
  `file_id` bigint(20) NOT NULL,
  `order_id` bigint(20) DEFAULT NULL,
  `file_path` text NOT NULL,
  `file_type` enum('psd','excel') NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `order_workflow`
--

CREATE TABLE `order_workflow` (
  `workflow_id` bigint(20) NOT NULL,
  `order_id` bigint(20) DEFAULT NULL,
  `stage` enum('Designing','Printing','Embroidery','Quality Check','Packaging','Shipped') DEFAULT 'Designing',
  `assigned_employee` bigint(20) DEFAULT NULL,
  `expected_completion` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `payment_id` bigint(20) NOT NULL,
  `order_id` bigint(20) DEFAULT NULL,
  `payment_date` datetime DEFAULT current_timestamp(),
  `amount` decimal(10,2) NOT NULL,
  `currency` varchar(3) DEFAULT 'PHP',
  `payment_method` enum('Cash','Card','Online Transfer','GCash','Crypto') NOT NULL,
  `status` enum('Pending','Completed','Failed','Refunded') DEFAULT 'Pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `positions`
--

CREATE TABLE `positions` (
  `position_id` int(11) NOT NULL,
  `position_name` varchar(100) NOT NULL,
  `department_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `positions`
--

INSERT INTO `positions` (`position_id`, `position_name`, `department_id`) VALUES
(1, 'Tailor', 1),
(2, 'Senior Tailor', 1),
(3, 'Alteration Specialist', 1),
(4, 'Pattern Maker', 1),
(5, 'Sublimation Technician', 2),
(6, 'Screen Printing Operator', 2),
(7, 'Print Finisher', 2),
(8, 'Embroidery Machine Operator', 3),
(9, 'Embroidery Technician', 3),
(10, 'Quality Control Inspector', 4),
(11, 'Packing Staff', 5),
(12, 'Production Staff', 6),
(13, 'Floor Supervisor', 6),
(14, 'Shop Assistant', 7),
(15, 'Inventory Clerk', 8),
(16, 'Admin Assistant', 9),
(17, 'HR Staff', 9),
(18, 'Accountant', 9),
(19, 'Operations Manager', 10);

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `product_id` bigint(20) NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `category` enum('Embroidery','Sublimation','Screen Printing','Alterations','Patches') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `services`
--

CREATE TABLE `services` (
  `service_id` bigint(20) NOT NULL,
  `service_name` varchar(255) NOT NULL,
  `service_description` text DEFAULT NULL,
  `service_price` decimal(10,2) NOT NULL,
  `service_category` enum('Embroidery','Sublimation','Screen Printing','Alterations','Patches') NOT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `services`
--

INSERT INTO `services` (`service_id`, `service_name`, `service_description`, `service_price`, `service_category`, `is_active`) VALUES
(1, 'Embroidery', 'High-quality embroidery services for custom designs.', 800.00, 'Embroidery', 1),
(2, 'Sublimation', 'Custom sublimation printing for vibrant designs.', 450.00, 'Sublimation', 1),
(3, 'Screen Printing', 'Durable screen printing for various materials.', 600.00, 'Screen Printing', 1),
(4, 'Alterations', 'Professional alterations for a perfect fit.', 300.00, 'Alterations', 1),
(5, 'Patches', 'Custom patches for clothing and accessories.', 400.00, 'Patches', 1);

-- --------------------------------------------------------

--
-- Table structure for table `shifts`
--

CREATE TABLE `shifts` (
  `shift_id` int(11) NOT NULL,
  `shift_name` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `shifts`
--

INSERT INTO `shifts` (`shift_id`, `shift_name`) VALUES
(1, 'Morning'),
(2, 'Afternoon'),
(3, 'Night');

-- --------------------------------------------------------

--
-- Table structure for table `shipping`
--

CREATE TABLE `shipping` (
  `delivery_id` bigint(20) NOT NULL,
  `order_id` bigint(20) NOT NULL,
  `courier_name` varchar(255) NOT NULL,
  `tracking_number` varchar(50) NOT NULL,
  `delivery_status` enum('Pending','Shipped','Delivered','Returned') DEFAULT 'Pending',
  `estimated_arrival` date DEFAULT NULL,
  `actual_delivery_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `statuses`
--

CREATE TABLE `statuses` (
  `status_id` int(11) NOT NULL,
  `status_name` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `statuses`
--

INSERT INTO `statuses` (`status_id`, `status_name`) VALUES
(1, 'Active'),
(2, 'Resigned'),
(3, 'Terminated');

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `supplier_id` bigint(20) NOT NULL,
  `supplier_name` varchar(255) NOT NULL,
  `contact_person` varchar(255) DEFAULT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `suppliers`
--

INSERT INTO `suppliers` (`supplier_id`, `supplier_name`, `contact_person`, `phone_number`, `email`, `address`) VALUES
(1, 'StitchMaster Co.', 'Anna Reyes', '09171234567', 'anna@stitchmaster.com', 'Makati City'),
(2, 'Inkology Supplies', 'John Tiu', '09285551234', 'john@inkology.com', 'Davao City'),
(3, 'The Fabric Yard', 'Maria Dela Cruz', '09179876543', 'maria@fabrics.com', 'Quezon City'),
(4, 'SewTech Distributors', 'Leo Santos', '09081231234', 'leo@sewtech.com', 'Taguig'),
(5, 'Craft & Print PH', 'Ella Navarro', '09195551222', 'ella@craftprint.ph', 'Cebu City'),
(6, 'PrintSmart Solutions', 'Carlos Tan', '09981234567', 'carlos@printsmart.com', 'Pasig'),
(7, 'Accessory World', 'Kim Uy', '09178889999', 'kim@accessoryworld.com', 'Manila'),
(8, 'Embroidery Pro', 'April Gomez', '09178882222', 'april@embpro.com', 'Bacolod'),
(9, 'Stitch & Patch', 'Diane Robles', '09281114444', 'diane@stitchpatch.com', 'Cagayan de Oro'),
(10, 'Sewing Central', 'Ben Garcia', '09097775555', 'ben@sewcentral.com', 'Iloilo');

-- --------------------------------------------------------

--
-- Table structure for table `supplier_supplies`
--

CREATE TABLE `supplier_supplies` (
  `id` bigint(20) NOT NULL,
  `supplier_id` bigint(20) NOT NULL,
  `supply_type_id` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `supplier_supplies`
--

INSERT INTO `supplier_supplies` (`id`, `supplier_id`, `supply_type_id`) VALUES
(1, 1, 1),
(2, 1, 2),
(3, 1, 7),
(4, 2, 3),
(5, 2, 4),
(6, 2, 6),
(7, 3, 1),
(8, 3, 13),
(9, 4, 7),
(10, 4, 8),
(11, 5, 5),
(12, 5, 12),
(13, 5, 6),
(14, 6, 3),
(15, 6, 4),
(16, 6, 13),
(17, 7, 8),
(18, 7, 14),
(19, 8, 2),
(20, 8, 9),
(21, 8, 10),
(22, 9, 11),
(23, 9, 13),
(24, 10, 1),
(25, 10, 2),
(26, 10, 14);

-- --------------------------------------------------------

--
-- Table structure for table `supply_types`
--

CREATE TABLE `supply_types` (
  `supply_type_id` bigint(20) NOT NULL,
  `name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `supply_types`
--

INSERT INTO `supply_types` (`supply_type_id`, `name`) VALUES
(5, 'Adhesive Backing'),
(11, 'Cutting Mats'),
(9, 'Embroidery Backing'),
(1, 'Fabric'),
(13, 'Garment Blanks'),
(12, 'Heat Press Sheets'),
(10, 'Hoops & Frames'),
(3, 'Ink'),
(7, 'Needles & Scissors'),
(14, 'Packaging Supplies'),
(2, 'Thread'),
(4, 'Transfer Paper'),
(6, 'Vinyl'),
(8, 'Zippers & Buttons');

-- --------------------------------------------------------

--
-- Table structure for table `uploads`
--

CREATE TABLE `uploads` (
  `uploads_id` int(11) NOT NULL,
  `user_id` bigint(20) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_type` varchar(50) NOT NULL,
  `file_size` bigint(20) NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `order_id` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` bigint(20) NOT NULL,
  `branch_id` bigint(20) DEFAULT NULL,
  `full_name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `phone_number` varchar(20) NOT NULL,
  `role` enum('admin','manager','employee','customer') DEFAULT 'customer',
  `status` enum('Active','Inactive','Suspended') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `branch_id`, `full_name`, `email`, `password`, `phone_number`, `role`, `status`, `created_at`) VALUES
(1, 3, 'Fe Anne L. Malasarte', 'fe@example.com', '$2y$10$va1D.8msOF/WcP/ReyQ/CODkQGlnd7G1ZDOFTuTHCelmlknp0JOWS', '09758373702', 'customer', 'Active', '2025-04-29 07:06:43'),
(2, NULL, 'admin', 'admin@example.com', '$2y$10$tuHBZXcGSM8bMupz1fN1V.kLI55u0GdphzhuSlIQJQYeq3OSmK1Cm', '12344321', 'admin', 'Active', '2025-04-29 08:02:21'),
(3, NULL, 'employee', 'employee@example.com', '$2y$10$5OO4Qf4q8Wq5XKasRP4X4uQk19rnUFPk5eSKSyBWV7/Q8Ve0TOc12', '09758373702', 'employee', 'Active', '2025-04-30 15:40:19'),
(7, NULL, 'Joevan Capote', 'capote@example.com', '$2y$10$BlO0ichJzX28BWb5bAqgVuItTsnHeB3dLn34zKjLNwIbDFoCMVcAm', '09758373702', 'employee', 'Active', '2025-05-05 19:33:57'),
(8, 3, 'david Tan', 'tan@example.com', '$2y$10$8RE7HyLtG3xUUtuTtTS35eS8x9clK7a498slVVTkhCl6OiMo8CMA2', '09758373702', 'customer', 'Active', '2025-05-05 19:56:23'),
(9, NULL, 'macjanek connor', 'macjanek@example.com', '$2y$10$HXzlKDrWFRWMEsKG.xRJjOLfXtaVkyTAzKtxZ5WKDzQce5Z6fHpKC', '09758373702', 'employee', 'Active', '2025-05-05 21:01:58'),
(10, NULL, 'anna londar', 'anna@example.com', '$2y$10$2.aNdWIgC1l/Vueocqx6iO.83sQOqvgyubBJQ.oYl3VvD6qRO65n2', '09758373702', 'customer', 'Active', '2025-05-05 21:05:06'),
(11, NULL, 'winter ranola', 'winter@example.com', '$2y$10$TNi9KQqq49J5eTJRWVD.MOp4vll.GTHS1rIaIjDiGOK33YUHodOg6', '09758373702', 'employee', 'Active', '2025-05-05 21:05:48'),
(12, 2, 'janice pempito', 'janice@example.com', '$2y$10$7cT.zdPcDZ0fNgn/Ua2dfODeFLijKmLiUv6bKWfLIBaho7XyyWeqa', '09758373702', 'employee', 'Active', '2025-05-05 21:06:15'),
(16, 2, 'joevn', 'joevn838@sakuragi.com', '$2y$10$Tn6nby9MzbRiNqz6mcCXd.fQBT.R/gBuD8RzT9BqAwQPWrk.6/tCK', '09346001341', 'employee', 'Active', '2025-05-15 03:47:32'),
(18, 2, 'Janna Malasarte', 'jannamalasarte587@sakuragi.com', '$2y$10$/Awi2LLtzLvr9HRroYDPOe1UR18R2DeYXHruP7fhnjXD3B2xoVMoC', '09111701730', 'employee', 'Active', '2025-05-15 03:48:40'),
(20, 2, 'jeff lance malasarte', 'jefflancemalasarte279@sakuragi.com', '$2y$10$UqoETDs1f9.xlDxurwIhGenekm3jtOuME1aY2w7ZB29sevArjaxOi', '09159059179', 'employee', 'Active', '2025-05-15 08:54:02'),
(22, 4, 'Nathan Bamino', 'nathanbamino540@sakuragi.com', '$2y$10$A0LYZXU9eIK9QnfoBHYUWOQLFl1LEzjBG7EMFGNJPvu3GsYV9n/1O', '09632400347', 'employee', 'Active', '2025-05-15 15:08:20');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`attendance_id`),
  ADD KEY `attendance_ibfk_1` (`employee_id`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `branches`
--
ALTER TABLE `branches`
  ADD PRIMARY KEY (`branch_id`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`department_id`);

--
-- Indexes for table `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`employee_id`),
  ADD UNIQUE KEY `employee_id` (`employee_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `branch_id` (`branch_id`),
  ADD KEY `position_id` (`position_id`),
  ADD KEY `shift_id` (`shift_id`),
  ADD KEY `status_id` (`status_id`);

--
-- Indexes for table `feedback`
--
ALTER TABLE `feedback`
  ADD PRIMARY KEY (`review_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `order_id` (`order_id`);

--
-- Indexes for table `inventory`
--
ALTER TABLE `inventory`
  ADD PRIMARY KEY (`inventory_id`),
  ADD KEY `branch_id` (`branch_id`),
  ADD KEY `supplier_id` (`supplier_id`),
  ADD KEY `fk_supply_type` (`supply_type_id`);

--
-- Indexes for table `inventory_stock_log`
--
ALTER TABLE `inventory_stock_log`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `inventory_id` (`inventory_id`),
  ADD KEY `supplier_id` (`supplier_id`);

--
-- Indexes for table `invoices`
--
ALTER TABLE `invoices`
  ADD PRIMARY KEY (`invoice_id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `customer_id` (`customer_id`);

--
-- Indexes for table `loyalty`
--
ALTER TABLE `loyalty`
  ADD PRIMARY KEY (`loyalty_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`order_id`),
  ADD KEY `branch_id` (`branch_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `employee_id` (`employee_id`);

--
-- Indexes for table `order_custom_data`
--
ALTER TABLE `order_custom_data`
  ADD PRIMARY KEY (`custom_id`),
  ADD KEY `order_id` (`order_id`);

--
-- Indexes for table `order_details`
--
ALTER TABLE `order_details`
  ADD PRIMARY KEY (`order_detail_id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `order_files`
--
ALTER TABLE `order_files`
  ADD PRIMARY KEY (`file_id`),
  ADD KEY `order_id` (`order_id`);

--
-- Indexes for table `order_workflow`
--
ALTER TABLE `order_workflow`
  ADD PRIMARY KEY (`workflow_id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `assigned_employee` (`assigned_employee`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`payment_id`),
  ADD KEY `order_id` (`order_id`);

--
-- Indexes for table `positions`
--
ALTER TABLE `positions`
  ADD PRIMARY KEY (`position_id`),
  ADD KEY `department_id` (`department_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`product_id`);

--
-- Indexes for table `services`
--
ALTER TABLE `services`
  ADD PRIMARY KEY (`service_id`);

--
-- Indexes for table `shifts`
--
ALTER TABLE `shifts`
  ADD PRIMARY KEY (`shift_id`);

--
-- Indexes for table `shipping`
--
ALTER TABLE `shipping`
  ADD PRIMARY KEY (`delivery_id`),
  ADD UNIQUE KEY `tracking_number` (`tracking_number`),
  ADD KEY `order_id` (`order_id`);

--
-- Indexes for table `statuses`
--
ALTER TABLE `statuses`
  ADD PRIMARY KEY (`status_id`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`supplier_id`);

--
-- Indexes for table `supplier_supplies`
--
ALTER TABLE `supplier_supplies`
  ADD PRIMARY KEY (`id`),
  ADD KEY `supplier_id` (`supplier_id`),
  ADD KEY `supply_type_id` (`supply_type_id`);

--
-- Indexes for table `supply_types`
--
ALTER TABLE `supply_types`
  ADD PRIMARY KEY (`supply_type_id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `branch_id` (`branch_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `attendance_id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `log_id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `branches`
--
ALTER TABLE `branches`
  MODIFY `branch_id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `department_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `employee_id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `feedback`
--
ALTER TABLE `feedback`
  MODIFY `review_id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inventory`
--
ALTER TABLE `inventory`
  MODIFY `inventory_id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=231;

--
-- AUTO_INCREMENT for table `inventory_stock_log`
--
ALTER TABLE `inventory_stock_log`
  MODIFY `log_id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `invoices`
--
ALTER TABLE `invoices`
  MODIFY `invoice_id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `loyalty`
--
ALTER TABLE `loyalty`
  MODIFY `loyalty_id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `order_id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `order_custom_data`
--
ALTER TABLE `order_custom_data`
  MODIFY `custom_id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `order_details`
--
ALTER TABLE `order_details`
  MODIFY `order_detail_id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `order_files`
--
ALTER TABLE `order_files`
  MODIFY `file_id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `order_workflow`
--
ALTER TABLE `order_workflow`
  MODIFY `workflow_id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `payment_id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `positions`
--
ALTER TABLE `positions`
  MODIFY `position_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `product_id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `shifts`
--
ALTER TABLE `shifts`
  MODIFY `shift_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `shipping`
--
ALTER TABLE `shipping`
  MODIFY `delivery_id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `statuses`
--
ALTER TABLE `statuses`
  MODIFY `status_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `supplier_id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `supplier_supplies`
--
ALTER TABLE `supplier_supplies`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `supply_types`
--
ALTER TABLE `supply_types`
  MODIFY `supply_type_id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`);

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `employees`
--
ALTER TABLE `employees`
  ADD CONSTRAINT `employees_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `employees_ibfk_2` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`branch_id`),
  ADD CONSTRAINT `employees_ibfk_3` FOREIGN KEY (`position_id`) REFERENCES `positions` (`position_id`),
  ADD CONSTRAINT `employees_ibfk_4` FOREIGN KEY (`shift_id`) REFERENCES `shifts` (`shift_id`),
  ADD CONSTRAINT `employees_ibfk_5` FOREIGN KEY (`status_id`) REFERENCES `statuses` (`status_id`);

--
-- Constraints for table `feedback`
--
ALTER TABLE `feedback`
  ADD CONSTRAINT `feedback_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `feedback_ibfk_2` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`);

--
-- Constraints for table `inventory`
--
ALTER TABLE `inventory`
  ADD CONSTRAINT `fk_supply_type` FOREIGN KEY (`supply_type_id`) REFERENCES `supply_types` (`supply_type_id`),
  ADD CONSTRAINT `inventory_ibfk_1` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`branch_id`),
  ADD CONSTRAINT `inventory_ibfk_2` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`supplier_id`);

--
-- Constraints for table `inventory_stock_log`
--
ALTER TABLE `inventory_stock_log`
  ADD CONSTRAINT `inventory_stock_log_ibfk_1` FOREIGN KEY (`inventory_id`) REFERENCES `inventory` (`inventory_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `inventory_stock_log_ibfk_2` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`supplier_id`) ON DELETE SET NULL;

--
-- Constraints for table `invoices`
--
ALTER TABLE `invoices`
  ADD CONSTRAINT `invoices_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`),
  ADD CONSTRAINT `invoices_ibfk_2` FOREIGN KEY (`customer_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `loyalty`
--
ALTER TABLE `loyalty`
  ADD CONSTRAINT `loyalty_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`branch_id`),
  ADD CONSTRAINT `orders_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `orders_ibfk_3` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`);

--
-- Constraints for table `order_custom_data`
--
ALTER TABLE `order_custom_data`
  ADD CONSTRAINT `order_custom_data_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`);

--
-- Constraints for table `order_details`
--
ALTER TABLE `order_details`
  ADD CONSTRAINT `order_details_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`),
  ADD CONSTRAINT `order_details_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`);

--
-- Constraints for table `order_files`
--
ALTER TABLE `order_files`
  ADD CONSTRAINT `order_files_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`);

--
-- Constraints for table `order_workflow`
--
ALTER TABLE `order_workflow`
  ADD CONSTRAINT `order_workflow_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`),
  ADD CONSTRAINT `order_workflow_ibfk_2` FOREIGN KEY (`assigned_employee`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`);

--
-- Constraints for table `positions`
--
ALTER TABLE `positions`
  ADD CONSTRAINT `positions_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`department_id`);

--
-- Constraints for table `shipping`
--
ALTER TABLE `shipping`
  ADD CONSTRAINT `shipping_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`);

--
-- Constraints for table `supplier_supplies`
--
ALTER TABLE `supplier_supplies`
  ADD CONSTRAINT `supplier_supplies_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`supplier_id`),
  ADD CONSTRAINT `supplier_supplies_ibfk_2` FOREIGN KEY (`supply_type_id`) REFERENCES `supply_types` (`supply_type_id`);

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`branch_id`);

-- Schema fixes: add columns expected by the application code
ALTER TABLE `orders`
  ADD COLUMN `service_id` bigint(20) DEFAULT NULL AFTER `user_id`,
  ADD COLUMN `design_file_id` bigint(20) DEFAULT NULL AFTER `service_id`,
  ADD COLUMN `completion_date` datetime DEFAULT NULL AFTER `expected_completion`;

ALTER TABLE `order_details`
  ADD COLUMN `size` varchar(20) DEFAULT NULL AFTER `order_detail_id`,
  ADD COLUMN `service_id` bigint(20) DEFAULT NULL AFTER `size`,
  MODIFY COLUMN `product_id` bigint(20) DEFAULT NULL;

ALTER TABLE `order_workflow`
  ADD COLUMN `product_type` varchar(100) DEFAULT NULL AFTER `stage`;

ALTER TABLE `payments`
  ADD COLUMN `reference_number` varchar(100) DEFAULT NULL AFTER `status`,
  ADD COLUMN `proof_file_path` text DEFAULT NULL AFTER `reference_number`;

-- Shop settings table
CREATE TABLE IF NOT EXISTS `shop_settings` (
  `setting_id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL UNIQUE,
  `setting_value` text DEFAULT NULL,
  PRIMARY KEY (`setting_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT IGNORE INTO `shop_settings` (setting_key, setting_value) VALUES
('shop_name', 'Sakuragi Tailoring Shop'),
('shop_email', 'contact@sakuragi.ph'),
('contact_number', '0917 123 4567'),
('address', '123 JP Laurel Ave, Davao City'),
('open_time', '09:00'),
('close_time', '18:00'),
('notifications_enabled', '1'),
('facebook_link', 'https://facebook.com/sakuragi.shop'),
('instagram_link', 'https://instagram.com/sakuragi.shop');

-- Work submission tables (employee QC workflow)
CREATE TABLE IF NOT EXISTS `work_submissions` (
  `submission_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `order_id` bigint(20) NOT NULL,
  `employee_id` bigint(20) NOT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('Pending','Passed','Failed','Reopened') DEFAULT 'Pending',
  `feedback` text DEFAULT NULL,
  `submission_date` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`submission_id`),
  KEY `order_id` (`order_id`),
  KEY `employee_id` (`employee_id`),
  CONSTRAINT `work_submissions_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`),
  CONSTRAINT `work_submissions_ibfk_2` FOREIGN KEY (`employee_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `submission_files` (
  `file_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `submission_id` bigint(20) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `upload_date` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`file_id`),
  KEY `submission_id` (`submission_id`),
  CONSTRAINT `submission_files_ibfk_1` FOREIGN KEY (`submission_id`) REFERENCES `work_submissions` (`submission_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
