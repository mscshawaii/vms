-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: May 30, 2025 at 03:43 AM
-- Server version: 8.4.3
-- PHP Version: 8.3.16

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `vessel_management_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `crew_members`
--

CREATE TABLE `crew_members` (
  `crew_id` int NOT NULL,
  `first_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `last_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `title` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `license_number` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `crew_members`
--

INSERT INTO `crew_members` (`crew_id`, `first_name`, `last_name`, `title`, `license_number`, `notes`) VALUES
(1, 'Sean', '0', 'Surveyor', '122323', ''),
(2, 'Anna', '0', 'Boss', '1', ''),
(3, 'test 1', '0', 'test 1', 'test 1', 'test 1'),
(4, 'test', '0', 'test', 'test', 'test'),
(6, 'Test', 'Crew', 'Tester', 'test 123456789', 'test');

-- --------------------------------------------------------

--
-- Table structure for table `documents`
--

CREATE TABLE `documents` (
  `id` int NOT NULL,
  `docType` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `category` enum('Certificate of Inspection','Certificate of Documentation','State Registration','Stability Letter','Commercial Permit','Insurance','FCC Station License','FCC Safety Radio Certificate','FCC Bridge-to-Bridge','FCC Marine Radio Operator Permit','EPIRB Registration','Fire Equipment Servicing','Lifesavings equipment servicing','Emergency Instructions','Emergency Broadcast Insructions','Oil Discharge Placard','Garbage Placard','Waste Management Plan','Broadcast Notice to Mariners','Charts','Tides & Currents Tables','Light list','Coast Pilot','Navigation Rules','Merchant Mariner Credential','First Aid & CPR Certificate','Lifeguard Certificate','Survey report','Safety Management System','Company Policy','General') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `docName` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `related_to` enum('vessel','crew','equipment','company') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'company',
  `vessel_id` int DEFAULT NULL,
  `crew_id` int DEFAULT NULL,
  `equipment_id` int DEFAULT NULL,
  `issueDate` date DEFAULT NULL,
  `expDate` date DEFAULT NULL,
  `file_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `uploaded_by` int DEFAULT NULL,
  `uploaded_on` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `documents`
--

INSERT INTO `documents` (`id`, `docType`, `category`, `docName`, `related_to`, `vessel_id`, `crew_id`, `equipment_id`, `issueDate`, `expDate`, `file_path`, `notes`, `uploaded_by`, `uploaded_on`) VALUES
(8, 'Other', NULL, '', 'vessel', 1, NULL, NULL, '2022-01-01', '0000-00-00', '', '', NULL, '2025-04-09 20:21:08'),
(9, 'FCC Station License', 'Certificate of Inspection', 'FCC Station License', 'vessel', 7, NULL, NULL, '2025-04-01', '2035-04-09', 'uploads/documents/67f827ee76657_Radio Station Authorization.pdf', '', NULL, '2025-04-10 20:19:58'),
(10, 'FCC Safety Radiotelephony Certificate', NULL, '', 'vessel', 7, NULL, NULL, '2025-04-03', '2030-04-10', 'uploads/documents/67f82810cc310_Radiotelephony Certificate.pdf', '', NULL, '2025-04-10 20:20:32'),
(11, 'Certificate of Inspection', NULL, '', 'vessel', 7, NULL, NULL, '2025-04-01', '2030-04-01', '', '', NULL, '2025-04-15 05:09:44'),
(12, 'Certificate of Documentation / State Registration', NULL, '', 'vessel', 7, NULL, NULL, '2025-04-19', '2026-04-30', 'uploads/doc_6804016335c2c.pdf', '', NULL, '2025-04-19 20:02:43'),
(14, 'Certificate of Inspection', NULL, '', 'vessel', NULL, 1, NULL, '2025-04-05', '2025-06-21', 'uploads/doc_68040248ccdca.pdf', '', NULL, '2025-04-19 20:06:32'),
(16, 'Certificate of Inspection', NULL, '', 'vessel', NULL, NULL, NULL, '2025-01-01', '2030-01-01', 'uploads/doc_680577157dc18.pdf', '', NULL, '2025-04-20 22:37:09'),
(17, 'Certificate of Inspection', NULL, '', 'vessel', 10, NULL, NULL, '2021-04-05', '2026-04-05', 'uploads/doc_6809a69bcb911.pdf', '', NULL, '2025-04-24 02:48:59'),
(18, 'Certificate of Documentation / State Registration', NULL, 'Certificate of Documentation / State Registration', 'vessel', 10, NULL, NULL, '2024-01-31', '2025-01-31', 'uploads/doc_6809ac2e49954.pdf', NULL, NULL, '2025-04-24 03:12:46'),
(19, 'Certificate of Inspection', NULL, 'Certificate of Inspection', 'vessel', 9, NULL, NULL, '2022-04-29', '2027-04-29', 'uploads/doc_680f0480b7f7b.pdf', NULL, NULL, '2025-04-28 04:30:56'),
(20, 'Certificate of Documentation / State Registration', 'Certificate of Inspection', 'Certificate of Documentation / State Registration', 'vessel', 9, NULL, NULL, '2024-04-29', '2025-04-29', 'uploads/doc_680f04abbff32.pdf', '<br />\r\n<b>Deprecated</b>:  htmlspecialchars(): Passing null to parameter #1 ($string) of type string is deprecated in <b>C:\\laragon\\www\\vessel_management_system\\edit_document.php</b> on line <b>114</b><br />', NULL, '2025-04-28 04:31:39'),
(21, 'FCC Station License', NULL, 'FCC Station License', 'vessel', 9, NULL, NULL, '2022-02-18', '2032-02-18', 'uploads/doc_680f166d9cbf5.pdf', NULL, NULL, '2025-04-28 05:47:25'),
(22, 'FCC Safety Radiotelephony Certificate', NULL, 'FCC Safety Radiotelephony Certificate', 'vessel', 9, NULL, NULL, '2022-03-07', '2027-03-07', 'uploads/doc_68106fbd36e0e.pdf', NULL, NULL, '2025-04-29 06:20:45'),
(23, 'Certificate of Inspection', NULL, 'Certificate of Inspection', 'vessel', 12, NULL, NULL, '2021-03-17', '2026-03-17', 'uploads/1746392641_IMG_7046.JPG', '', NULL, '2025-05-04 21:02:04'),
(24, 'Stability Letter', NULL, 'Stability Letter', 'vessel', 12, NULL, NULL, '2021-02-16', NULL, 'uploads/doc_6817d62f9a011.JPG', NULL, NULL, '2025-05-04 21:03:43'),
(25, 'Stability Letter', NULL, 'Stability Letter', 'vessel', 9, NULL, NULL, '2021-03-23', NULL, 'uploads/doc_6817db628d8fb.pdf', NULL, NULL, '2025-05-04 21:25:54'),
(27, 'Certificate of Documentation / State Registration', NULL, 'Certificate of Documentation / State Registration', 'vessel', 13, NULL, NULL, '2025-01-27', '2026-01-31', 'uploads/doc_681c0bdb724f0.pdf', '', NULL, '2025-05-08 01:41:47'),
(28, 'Certificate of Inspection', NULL, 'Certificate of Inspection', 'vessel', 13, NULL, NULL, '2025-03-07', '2030-03-07', 'uploads/doc_681c0c63beb30.pdf', NULL, NULL, '2025-05-08 01:44:03'),
(29, 'Certificate of Inspection', NULL, 'Certificate of Inspection', 'vessel', 13, NULL, NULL, '2025-01-15', NULL, 'uploads/doc_681c0c80cf6d9.pdf', NULL, NULL, '2025-05-08 01:44:32'),
(30, 'EPRIB Registration', NULL, 'EPRIB Registration', 'vessel', 13, NULL, NULL, '2025-02-26', '2027-02-26', 'uploads/doc_681c0ca93a2ed.pdf', NULL, NULL, '2025-05-08 01:45:13'),
(31, 'Certificate of Inspection', NULL, 'Certificate of Inspection', 'vessel', 14, NULL, NULL, '2023-05-24', '2028-05-24', 'uploads/doc_68252f4325377.pdf', NULL, NULL, '2025-05-15 00:03:15'),
(32, 'Certificate of Documentation / State Registration', NULL, 'Certificate of Documentation / State Registration', 'vessel', 14, NULL, NULL, '2025-01-23', '2026-02-28', 'uploads/doc_68252f6796547.pdf', NULL, NULL, '2025-05-15 00:03:51'),
(33, 'Food Establishment Permit', NULL, 'Food Establishment Permit', 'vessel', 14, NULL, NULL, '2024-01-31', '2025-01-31', 'uploads/doc_68252f91c1cbb.pdf', NULL, NULL, '2025-05-15 00:04:33'),
(34, 'FCC Station License', NULL, 'FCC Station License', 'vessel', 14, NULL, NULL, '2018-03-14', '2028-03-14', 'uploads/doc_68252fb0b4c70.pdf', NULL, NULL, '2025-05-15 00:05:04'),
(35, 'FCC Safety Radiotelephony Certificate', NULL, 'FCC Safety Radiotelephony Certificate', 'vessel', 14, NULL, NULL, '2023-02-03', '2028-02-03', 'uploads/doc_68252fe1282a9.pdf', NULL, NULL, '2025-05-15 00:05:53'),
(36, 'Certificate of Inspection', NULL, 'Certificate of Inspection', 'vessel', 14, NULL, NULL, '2018-05-29', NULL, 'uploads/doc_68253004e541f.pdf', NULL, NULL, '2025-05-15 00:06:28'),
(37, 'Liquor License', NULL, 'Liquor License', 'vessel', 14, NULL, NULL, '2024-07-01', '2025-06-30', 'uploads/doc_68253034c1a0e.pdf', NULL, NULL, '2025-05-15 00:07:16'),
(38, 'EPRIB Registration', NULL, 'EPRIB Registration', 'vessel', 14, NULL, NULL, '2023-10-21', '2025-10-21', 'uploads/doc_68253167ed86f.jpeg', NULL, NULL, '2025-05-15 00:12:23'),
(39, 'Certificate of Inspection', NULL, 'Certificate of Inspection', 'vessel', 17, NULL, NULL, '2025-05-22', '2030-05-22', 'uploads/1748314787_COI22may2030.pdf', '', NULL, '2025-05-22 02:03:41'),
(40, 'Certificate of Documentation / State Registration', NULL, 'Certificate of Documentation / State Registration', 'vessel', 17, NULL, NULL, '2025-02-05', '2026-02-05', 'uploads/doc_6834eb9d27664.pdf', NULL, NULL, '2025-05-26 22:30:53'),
(41, 'Certificate of Inspection', NULL, 'Certificate of Inspection', 'vessel', 1, NULL, NULL, '2025-05-22', '2030-05-22', 'uploads/doc_68352b317776f.pdf', NULL, NULL, '2025-05-27 03:02:09'),
(42, 'Certificate of Inspection', NULL, 'Certificate of Inspection', 'vessel', 8, NULL, NULL, '2020-01-01', '2025-06-01', 'uploads/doc_6837dad494ff8.pdf', NULL, NULL, '2025-05-29 03:56:04');

-- --------------------------------------------------------

--
-- Table structure for table `equipment`
--

CREATE TABLE `equipment` (
  `eid` int NOT NULL,
  `equipmentName` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `equipmentLocation` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `manufacturer` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `modelNumber` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `serialNumber` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `installDate` date DEFAULT NULL,
  `expDate` date DEFAULT NULL,
  `quantity` int DEFAULT NULL,
  `unit` enum('amp','amp hours','cubic Feet','cubic Meters','gallons','GPM','GPH','liters','HP','KW','volts','watts','PSI','inches','feet-inches','meters','persons','each') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `onBoardNotRequired` tinyint(1) DEFAULT NULL,
  `vessel_id` int DEFAULT NULL,
  `task_taskid` int DEFAULT NULL,
  `category_id` int DEFAULT NULL,
  `type_id` int DEFAULT NULL,
  `subtype_id` int DEFAULT NULL,
  `photo_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `equipment_type_id` int DEFAULT NULL,
  `equipment_subtype_id` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `equipment`
--

INSERT INTO `equipment` (`eid`, `equipmentName`, `equipmentLocation`, `manufacturer`, `modelNumber`, `serialNumber`, `installDate`, `expDate`, `quantity`, `unit`, `notes`, `onBoardNotRequired`, `vessel_id`, `task_taskid`, `category_id`, `type_id`, `subtype_id`, `photo_path`, `equipment_type_id`, `equipment_subtype_id`) VALUES
(106, 'Bridge radio', 'operating station', '', '', '', NULL, NULL, 0, '', '', 0, 1, NULL, 2, 10, 24, NULL, 10, 24),
(110, 'Fireboy', 'Engine Room', 'Fireboy', '', '', '2025-04-07', NULL, 500, 'cubic Feet', '', 0, 7, NULL, 3, 15, 34, NULL, 15, 34),
(111, 'Fire Pump', 'Port engine room', '', '', '', NULL, NULL, 60, 'PSI', '', 0, 7, NULL, 3, 16, 38, NULL, 16, 38),
(118, '', '', 'ACR', '', 'asdlkj', '2025-04-01', NULL, 1, 'each', '', 0, 1, NULL, 2, 9, 23, NULL, 9, 23),
(121, 'Flares', '', '', '', '', NULL, '2025-04-15', 6, 'each', '', 0, 7, NULL, 1, 3, 11, NULL, 3, 11),
(123, 'Pirmary radio', 'Operating station', 'Standard Horizon', 'Eclipse', 'abd123', '2025-04-01', NULL, 1, 'each', '', 1, 1, NULL, 2, 10, 24, 'uploads/eq_67fc86a00f6de.jpg', 10, 24),
(124, 'Aspirin', 'First Aid Kit - Galley', '', '', '', '2025-04-01', '2025-11-13', 48, 'each', '', 1, 1, NULL, 1, 4, 13, NULL, 4, 13),
(125, 'Test this function', 'Port engine', 'Cummins', 'QSE 5.9', '123dklshf', '2025-03-31', NULL, 1, 'each', '', 1, 1, NULL, 4, 20, 44, NULL, 20, 44),
(127, '', '', '', '', '', NULL, NULL, 18, 'each', '', 1, 10, NULL, 1, 1, 1, NULL, 1, 1),
(128, '', '', '', '', '', NULL, NULL, 2, 'each', '', 0, 10, NULL, 1, 1, 2, NULL, 1, 2),
(129, '', '', '', '', '', NULL, '2026-12-31', 3, 'each', '', 1, 10, NULL, 1, 3, 12, NULL, 3, 12),
(130, '', '', '', '', '', NULL, '2025-10-31', 3, 'each', '', 1, 10, NULL, 1, 3, 11, NULL, 3, 11),
(131, '', '', '', '', '', NULL, '2024-11-30', 4, 'each', '', 1, 10, NULL, 1, 3, 12, NULL, 3, 12),
(132, 'Port Main Engine', 'Port', 'Suzuki', '', '', NULL, NULL, 300, 'HP', '', 1, 9, NULL, 4, 20, 43, NULL, 20, 43),
(133, 'Starboard Main Engine', 'starboard', 'Suzuki', '', '', NULL, NULL, 300, 'HP', '', 1, 9, NULL, 4, 20, 43, NULL, 20, 43),
(134, '', '', 'ACR', '', '', NULL, '2031-08-30', 1, 'each', '', 1, 9, NULL, 2, 9, 22, NULL, 9, 22),
(135, '', '', 'ACR', '', '', '2024-08-31', '2026-08-31', 1, 'each', '', 1, 9, NULL, 2, 9, 23, NULL, 9, 23),
(136, 'Adult Life Jackets', 'Port forward compartment', 'Jim Buoy', '', '', NULL, NULL, 18, 'each', '', 1, 9, NULL, 1, 1, 1, NULL, 1, 1),
(137, 'Child Life Jackets', 'under helm console', 'Jim Buoy', '', '', NULL, NULL, 2, 'each', '', 1, 9, NULL, 1, 1, 2, NULL, 1, 2),
(138, '', '', 'Orion', 'Item # 265', '', NULL, '2027-11-30', 12, 'each', '', 1, 12, NULL, 1, 3, 12, 'uploads/eq_6817d6adc7557.JPG', 3, 12),
(139, 'Bilge pump', 'Port aft compartment', 'Rule', '2000', '', NULL, NULL, 2000, 'GPH', '', 1, 9, NULL, 4, 22, 53, NULL, 22, 53),
(140, '', 'Starboard aft compartment', 'Rule', '2000', '', NULL, NULL, 2000, 'GPH', '', 1, 9, NULL, 4, 22, 53, NULL, 22, 53),
(141, '', 'Operating Station', 'Standard Horizon', '', '', NULL, NULL, 1, 'each', '', 1, 9, NULL, 2, 10, 24, NULL, 10, 24),
(142, '', 'Port aft compartment', '', '', '', NULL, NULL, 1, 'each', '', 1, 9, NULL, 4, 22, 50, NULL, 22, 50),
(143, '', 'Starboard forward', '', '', '', NULL, NULL, 1, 'each', '', 1, 9, NULL, 1, 2, 9, 'uploads/eq_681be0fc74bd3.jpg', 2, 9),
(144, '', 'Zippered bags in overhead', 'Sterns', '', '', NULL, NULL, 42, 'each', '', 1, 13, NULL, 1, 1, 1, NULL, 1, 1),
(145, '', '', '', '', '', NULL, NULL, 1, 'each', '', 1, 13, NULL, 1, 2, 7, NULL, 2, 7),
(149, '', '', '', '', '', NULL, '2034-01-31', 1, 'each', '', 1, 14, NULL, 2, 9, 22, 'uploads/eq_682531c5678dd.jpeg', 9, 22),
(150, '', '', 'ACR', '', '', NULL, '2025-10-31', 1, 'each', '', 1, 14, NULL, 2, 9, 23, 'uploads/eq_6825320718784.jpeg', 9, 23),
(151, '', 'Starboard aft main deck', '', '', '', NULL, '2026-03-31', 1, 'each', '', 0, 14, NULL, 3, 14, 32, 'uploads/eq_68253263c92cc.jpeg', 14, 32),
(152, '', 'Port aft main deck', '', '', '', NULL, '2026-03-26', 1, 'each', '', 0, 14, NULL, 3, 14, 32, 'uploads/eq_682532bf81c17.jpeg', 14, 32),
(153, 'Galley', '', '', '', '', NULL, '2025-05-14', 1, 'each', 'Requires replacment', 0, 14, NULL, 3, 14, 32, 'uploads/eq_682532f55e61d.jpeg', 14, 32),
(154, '', 'Port midship space', '', '', '', NULL, '2026-03-26', 1, 'each', '', 1, 14, NULL, 3, 14, 32, 'uploads/eq_682533258b355.jpeg', 14, 32),
(155, 'Fireboy', 'Starboard engine room', 'Fireboy', '', '', NULL, '2026-03-26', 350, 'cubic Feet', '', 1, 14, NULL, 3, 15, 33, 'uploads/eq_682533b38fde0.jpeg', 15, 33),
(156, 'Port aft', 'Port aft', 'Kidde', '', '', NULL, NULL, 1, 'each', '', 1, 17, NULL, 3, 14, 30, 'uploads/eq_6832b34279922.jpeg', 14, 30),
(157, 'VHF Radio -   - Operating Station', 'Operating Station', 'Standard Horizon', '', '', NULL, NULL, 1, 'each', '', 1, 17, NULL, 2, 10, 24, 'uploads/eq_6832b65801f1a.jpeg', 10, 24),
(158, 'Life Jacket - Adult - Port side', 'Port side', '', '', '', NULL, NULL, 20, 'each', '', 1, 17, NULL, 1, 1, 1, 'uploads/eq_6834ebfe2fa75.jpeg', 1, 1),
(161, 'Life Jacket - Child - port side', 'port side', '', '', '', NULL, NULL, 4, 'each', '', 0, 17, NULL, 1, NULL, NULL, 'uploads/eq_6834f26aef4c2.jpeg', 1, 2),
(164, 'Life Jacket - Adult - starboard side', 'starboard side', '', '', '', NULL, NULL, 20, 'each', '', 0, 17, NULL, 1, NULL, NULL, 'uploads/eq_6834f639bd6bd.jpeg', 1, 1);

-- --------------------------------------------------------

--
-- Table structure for table `equipment_category`
--

CREATE TABLE `equipment_category` (
  `id` int NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `equipment_category`
--

INSERT INTO `equipment_category` (`id`, `name`) VALUES
(2, 'Communication'),
(3, 'Fire Fighting'),
(6, 'Hull'),
(1, 'Lifesaving'),
(4, 'Machinery'),
(8, 'Miscellaneous'),
(5, 'Operations & Vessel Control'),
(7, 'Sailing & Rigging');

-- --------------------------------------------------------

--
-- Table structure for table `equipment_photos`
--

CREATE TABLE `equipment_photos` (
  `photo_id` int NOT NULL,
  `eid` int DEFAULT NULL,
  `file_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `file_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `equipment_subtype`
--

CREATE TABLE `equipment_subtype` (
  `id` int NOT NULL,
  `type_id` int DEFAULT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `equipment_subtype`
--

INSERT INTO `equipment_subtype` (`id`, `type_id`, `name`) VALUES
(1, 1, 'Adult'),
(2, 1, 'Child'),
(3, 1, 'Extended'),
(4, 2, '20\" with light and line'),
(5, 2, '20\" with light'),
(6, 2, '20\" with line'),
(7, 2, '24\" with light and line'),
(8, 2, '24\" with light'),
(9, 2, '24\" with line'),
(10, 2, '24\"'),
(11, 3, 'Day'),
(12, 3, 'Night'),
(13, 4, 'Analgesic Pills'),
(14, 4, 'Antiseptic Wipes'),
(16, 5, 'Life Float'),
(17, 5, 'Life Raft'),
(18, 6, 'Battery'),
(19, 6, 'PAD'),
(20, 7, ' '),
(21, 8, ' '),
(22, 9, 'EPRIB Battery'),
(23, 9, 'EPRIB Hydrostatic Release'),
(24, 10, ' '),
(25, 11, ' '),
(26, 12, ' '),
(27, 13, ' '),
(28, 14, '2-A'),
(29, 14, '10-B:C'),
(30, 14, '40-B:C'),
(31, 14, '60-B:C'),
(32, 14, '80-B:C'),
(33, 15, 'HFC-227'),
(34, 15, 'FK-5-1-12'),
(35, 15, 'CO2'),
(36, 15, 'other'),
(37, 16, 'electric'),
(38, 16, 'engine driven'),
(39, 17, ' '),
(40, 18, 'Modular'),
(41, 18, 'Interconnected'),
(42, 19, ' '),
(43, 20, 'Outboard - Gasoline'),
(44, 20, 'Inboard Diesel - Reduction'),
(45, 20, 'Inboard - Gasoline'),
(46, 20, 'Outboard - Diesel'),
(47, 21, 'Battery'),
(48, 21, 'Generator'),
(49, 21, 'Alternator'),
(50, 22, 'High Level Alarm'),
(51, 22, 'Pump - Fixed manual'),
(52, 22, 'Pump - Portable manual'),
(53, 22, 'Pump - Fixed Power - submersible'),
(54, 22, 'Pump - Manifold connected - Engine driven'),
(55, 22, 'Pump - Manifold connected - Electric motor'),
(56, 23, 'Tiller'),
(57, 23, 'Hydraulic Hand'),
(58, 23, 'Hydraulic - Electric'),
(59, 23, 'Electric'),
(60, 24, 'Diesel - Independant'),
(61, 24, 'Gasoline - Independant'),
(62, 24, 'Gasoline - Portable'),
(63, 24, 'Black Water'),
(64, 24, 'Potable Water'),
(65, 25, 'LPG'),
(66, 25, 'CPG'),
(67, 25, 'Electric'),
(68, 26, 'Anchor'),
(69, 26, 'Anchor Chain and Line'),
(70, 26, 'Mooring Line'),
(71, 27, ' '),
(72, 28, ' '),
(73, 29, ' '),
(74, 30, ' '),
(75, 31, ' '),
(76, 32, ' '),
(77, 33, 'Fixed'),
(78, 33, 'Bullhorn'),
(79, 34, 'Manual'),
(80, 34, 'Electronic'),
(81, 35, 'Keel - Stepped'),
(82, 35, 'Deck - Stepped'),
(83, 36, 'Forestay'),
(84, 36, 'Babystay'),
(85, 36, 'Backstay'),
(86, 36, 'Shroud'),
(87, 36, 'Tang'),
(88, 36, 'Toggle'),
(89, 37, 'Halyard'),
(90, 37, 'Sheet'),
(91, 37, 'Topping Life'),
(92, 37, ' '),
(93, 38, 'Mainsail'),
(94, 38, 'Jib'),
(95, 39, 'Jib Furler'),
(96, 39, 'Main Furler'),
(97, 40, 'Winch');

-- --------------------------------------------------------

--
-- Table structure for table `equipment_type`
--

CREATE TABLE `equipment_type` (
  `id` int NOT NULL,
  `category_id` int DEFAULT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `equipment_type`
--

INSERT INTO `equipment_type` (`id`, `category_id`, `name`) VALUES
(1, 1, 'Life Jacket'),
(2, 1, 'Ring Buoy'),
(3, 1, 'Distress Signals'),
(4, 1, 'First Aid Kit'),
(5, 1, 'Primary Lifesaving'),
(6, 1, 'AED'),
(7, 1, 'Medical Oxygen'),
(8, 1, 'Other'),
(9, 2, 'EPRIB'),
(10, 2, 'VHF Radio'),
(11, 2, 'MF Radio'),
(12, 2, 'INMARSAT'),
(13, 2, 'GMDSS'),
(14, 3, 'Portable Extinguisher'),
(15, 3, 'Fixed Extinguisher'),
(16, 3, 'Fire Pump'),
(17, 3, 'Fire Staion / Hose'),
(18, 3, 'Fire Detection'),
(19, 3, 'Fire Buckets'),
(20, 4, 'Propulsion Engine'),
(21, 4, 'Electrical Power'),
(22, 4, 'Bilge System'),
(23, 4, 'Steering'),
(24, 4, 'Tanks'),
(25, 5, 'Cooking & Heating'),
(26, 5, 'Mooring & Towing'),
(27, 5, 'Navigation Lights'),
(28, 5, 'Compass'),
(29, 5, 'Radar'),
(30, 5, 'GPS'),
(31, 5, 'Electronic Chart Plotter'),
(32, 5, 'AIS'),
(33, 5, 'Public Address System'),
(34, 5, 'Engine Controls'),
(35, 7, 'Mast'),
(36, 7, 'Standing Rigging'),
(37, 7, 'Running Rigging'),
(38, 7, 'Sail'),
(39, 7, 'Furler'),
(40, 7, 'Winch');

-- --------------------------------------------------------

--
-- Table structure for table `icrs`
--

CREATE TABLE `icrs` (
  `icr_id` int NOT NULL,
  `icr_number` varchar(10) NOT NULL,
  `title` varchar(255) NOT NULL,
  `category_id` int DEFAULT NULL,
  `type_id` int DEFAULT NULL,
  `related_doc_id` int DEFAULT NULL,
  `inspector_name` varchar(100) DEFAULT NULL,
  `reference_text` text,
  `frequency` enum('Annually','Monthly','Quarterly','Weekly') NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `icrs`
--

INSERT INTO `icrs` (`icr_id`, `icr_number`, `title`, `category_id`, `type_id`, `related_doc_id`, `inspector_name`, `reference_text`, `frequency`, `created_at`, `updated_at`) VALUES
(5, 'A 01', 'Paperwork, required documents', NULL, NULL, NULL, NULL, 'AUTHORIZED INSPECTOR: LICENSED OFFICER or his designated representative \r\nREFERENCES: 46 CFR 176.302, 176.306, 184.502, 184.702, 185.402\r\nVerify that the following documents are on board and current:', 'Annually', '2025-04-21 06:43:31', '2025-04-21 06:43:31'),
(6, 'A 02', 'Paperwork, Required publications', NULL, NULL, NULL, NULL, 'AUTHORIZED INSPECTOR: LICENSED OFFICER or his designated representative \r\nREFERENCES: 46 CFR 184.420 \r\n\r\nVerify that the following publications or appropriate extracts are onboard and currently \r\ncorrected for the intended operating area as determined by the OCMI:', 'Annually', '2025-04-21 06:45:04', '2025-04-21 06:45:04'),
(7, 'A 03', 'Paperwork, Service Reports', NULL, NULL, NULL, NULL, 'AUTHORIZED INSPECTOR: LICENSED OFFICER or his designated representative \r\nREFERENCES: 46 CFR 176.810, 185.730 \r\nVerify the following annual servicing reports', 'Annually', '2025-04-21 06:45:58', '2025-04-21 06:45:58'),
(8, 'B 01', 'Lifesaving Equipment, Life preservers and storage', NULL, NULL, NULL, NULL, 'AUTHORIZED INSPECTOR: LICENSED OFFICER or his designated representative \r\nREFERENCES: 46 CFR 176.808, 180.71, 180.75, 180.78, 185.516, 185.604', 'Quarterly', '2025-04-21 06:47:35', '2025-04-21 06:47:35'),
(9, 'B 02', 'Lifesaving equipment, Ring buoys', NULL, NULL, NULL, NULL, 'AUTHORIZED INSPECTOR: LICENSED OFFICER or his designated representative \r\nREFERENCES: 46 CFR 180.70, 185.604', 'Monthly', '2025-04-21 06:53:33', '2025-04-21 06:53:33'),
(10, 'B 04', 'Lifesaving equipment, life floats', NULL, NULL, NULL, NULL, 'AUTHORIZED INSPECTOR: LICENSED OFFICER or his designated representative \r\nREFERENCES: 46 CFR 180.137, 180.175, 180.200, 185.604', 'Annually', '2025-04-24 04:18:07', '2025-04-24 04:18:07'),
(11, 'C 01', 'Fire Protection Equipment, Fixed CO2 System', NULL, NULL, NULL, NULL, 'AUTHORIZED INSPECTOR: LICENSED OFFICER or his designated representative \r\nREFERENCES: 46 CFR 176.810, 181.410, 185.612', 'Annually', '2025-04-24 04:22:26', '2025-04-24 04:22:26'),
(12, 'C 02', 'Fire Protection Equipment, Clean Agent System (Fireboy)', NULL, NULL, NULL, NULL, 'AUTHORIZED INSPECTOR: LICENSED OFFICER or his designated representative \r\nREFERENCES: 46 CFR 176.810, 181.410, 181.420, 185.612', 'Annually', '2025-04-24 04:25:47', '2025-04-24 04:25:47'),
(13, 'C 04', 'Fire Protection Equipment, Portable Fire Extinguishers', NULL, NULL, NULL, NULL, 'AUTHORIZED INSPECTOR: LICENSED OFFICER or his designated representative \r\nREFERENCES: 46 CFR 176.810, 181.500, 181.520, NFPA 10', 'Monthly', '2025-04-24 04:27:50', '2025-05-22 02:09:46'),
(14, 'C 05', 'Fire Protection Equipment, Fire Main System', NULL, NULL, NULL, NULL, 'AUTHORIZED INSPECTOR: LICENSED OFFICER or his designated representative \r\nREFERENCES: 46 CFR 181.300, 181.310, 181.320, NVIC 6-72', 'Annually', '2025-04-24 04:30:33', '2025-04-24 04:30:33'),
(15, 'C 06', 'Fire protection equipment, fire detection system', NULL, NULL, NULL, NULL, 'AUTHORIZED INSPECTOR: LICENSED OFFICER or his designated representative \r\nREFERENCES: 46 CFR 181.400', 'Annually', '2025-04-25 04:56:55', '2025-04-25 04:56:55'),
(16, 'C 07', 'Fire protection equipment, fire dampers and remote shutdowns', NULL, NULL, NULL, NULL, 'AUTHORIZED INSPECTOR: LICENSED OFFICER or his designated representative \r\nREFERENCES: 46 CFR 181.410, 181.465, 182.465', 'Annually', '2025-04-25 05:11:49', '2025-04-25 05:11:49'),
(17, 'C 10', 'Fire Protection Equipment, Fire Axes', NULL, NULL, NULL, NULL, 'AUTHORIZED INSPECTOR: LICENSED OFFICER or his designated representative \r\nREFERENCES: 46 CFR 181.600', 'Annually', '2025-04-25 05:19:52', '2025-04-25 05:19:52'),
(18, 'C 11', 'Fire Protection Equipment, Fire Bucket', NULL, NULL, NULL, NULL, 'AUTHORIZED INSPECTOR: LICENSED OFFICER or his designated representative \r\nREFERENCES: 46 CFR 181.610', 'Quarterly', '2025-04-26 20:27:53', '2025-04-26 20:27:53'),
(19, 'E 01', 'Emergency Equipment, EPRIB', NULL, NULL, NULL, NULL, 'AUTHORIZED INSPECTOR: LICENSED OFFICER or his designated representative \r\nREFERENCES: 46 CFR 180.64, 185.604', 'Monthly', '2025-04-26 20:51:01', '2025-04-26 20:51:01'),
(20, 'E 02', 'Emergency Equipment; General Alarm', NULL, NULL, NULL, NULL, 'AUTHORIZED INSPECTOR: LICENSED OFFICER or his designated representative \r\nREFERENCES: 46 CFR 183.550', 'Annually', '2025-04-26 20:54:35', '2025-05-27 04:19:42'),
(21, 'E 03', 'Emergency Equipment, Distress Signals (Flares)', NULL, NULL, NULL, NULL, 'AUTHORIZED INSPECTOR: LICENSED OFFICER or his designated representative \r\nREFERENCES: 46 CFR 180.68', 'Quarterly', '2025-04-26 20:58:00', '2025-04-26 20:58:00'),
(22, 'E 04', 'Emergency Equipment, Public Address System', NULL, NULL, NULL, NULL, 'AUTHORIZED INSPECTOR: LICENSED OFFICER or his designated representative \r\nREFERENCES: 46 CFR 184.610', 'Quarterly', '2025-04-26 21:07:35', '2025-04-26 21:07:35'),
(23, 'E 05', 'First Aid, Medical kit', NULL, NULL, NULL, NULL, 'AUTHORIZED INSPECTOR: LICENSED OFFICER or his designated representative \r\nREFERENCES: 46 CFR 184.710', 'Annually', '2025-04-26 21:13:18', '2025-04-26 21:13:18'),
(24, 'F 01', 'Ventillation, Ventillation shutdown', NULL, NULL, NULL, NULL, 'AUTHORIZED INSPECTOR: LICENSED OFFICER or his designated representative \r\nREFERENCES: 46 CFR 177.600, 177.620', 'Annually', '2025-04-26 21:14:20', '2025-04-26 21:14:20'),
(25, 'F 02', 'Ventillation, Fuel tank vents', NULL, NULL, NULL, NULL, 'AUTHORIZED INSPECTOR: LICENSED OFFICER or his designated representative \r\nREFERENCES: 46 CFR 182.450', 'Annually', '2025-04-26 21:34:18', '2025-04-26 21:34:18'),
(26, 'F 03', 'Ventillation, Void and water tanks', NULL, NULL, NULL, NULL, 'AUTHORIZED INSPECTOR: LICENSED OFFICER or his designated representative \r\nREFERENCES: 46 CFR 177.600', 'Annually', '2025-04-29 05:28:00', '2025-04-29 05:28:00'),
(27, 'F 03', 'Ventillation, Galley Vents', NULL, NULL, NULL, NULL, 'AUTHORIZED INSPECTOR: LICENSED OFFICER or his designated representative \r\nREFERENCES: 46 CFR 177.600, 181.425', 'Annually', '2025-04-29 05:30:46', '2025-04-29 05:30:46'),
(28, 'G 01', 'Navigation equipment, Radar', NULL, NULL, NULL, NULL, 'AUTHORIZED INSPECTOR: LICENSED OFFICER or his designated representative \r\nREFERENCES: 46 CFR 184.404', 'Quarterly', '2025-04-29 05:31:52', '2025-04-29 05:31:52'),
(29, 'G 02', 'Navigation Equipment, Magnetic Compass', NULL, NULL, NULL, NULL, 'AUTHORIZED INSPECTOR: LICENSED OFFICER or his designated representative \r\nREFERENCES: 46 CFR 184.402', 'Annually', '2025-04-29 05:32:50', '2025-04-29 05:32:50'),
(30, 'G 04', 'Navigation Equipment, Radio', NULL, NULL, NULL, NULL, 'AUTHORIZED INSPECTOR: LICENSED OFFICER or his designated representative \r\nREFERENCES: 46 CFR 183.310, 183.392, 184.502', 'Annually', '2025-04-29 05:34:40', '2025-04-29 05:34:40'),
(31, 'G 05', 'Navigation equipment, navigation lights', NULL, NULL, NULL, NULL, 'AUTHORIZED INSPECTOR: LICENSED OFFICER or his designated representative \r\nREFERENCES: 46 CFR 183.310, 183.420', 'Monthly', '2025-04-29 05:35:50', '2025-04-29 05:35:50'),
(32, 'G 06', 'Navigation equipment, Internal communications and control systems', NULL, NULL, NULL, NULL, 'AUTHORIZED INSPECTOR: LICENSED OFFICER or his designated representative \r\nREFERENCES: 46 CFR 184.602', 'Annually', '2025-04-29 05:37:22', '2025-04-29 05:37:22'),
(33, 'G 07', 'Navigation equipment, charts and publications', NULL, NULL, NULL, NULL, 'AUTHORIZED INSPECTOR: LICENSED OFFICER or his designated representative \r\nREFERENCES: 46 CFR 184.420', 'Annually', '2025-04-29 05:42:33', '2025-04-29 05:42:33'),
(34, 'G 08', 'Navigation equipment, day shapes and whistle', NULL, NULL, NULL, NULL, 'AUTHORIZED INSPECTOR: LICENSED OFFICER or his designated representative \r\nREFERENCES: 33 CFR 81, 84, 86', 'Annually', '2025-04-29 05:45:26', '2025-04-29 05:45:26'),
(35, 'G 09', 'Navigation equipment, Electronic positioning equipment, (GPS)', NULL, NULL, NULL, NULL, 'AUTHORIZED INSPECTOR: LICENSED OFFICER or his designated representative \r\nREFERENCES: 46 CFR 184.410', 'Annually', '2025-04-29 05:46:21', '2025-04-29 05:46:21'),
(36, 'H 01', 'Ground tackle, anchor system', NULL, NULL, NULL, NULL, 'AUTHORIZED INSPECTOR: LICENSED OFFICER or his designated representative \r\nREFERENCES: 46 CFR 184.300', 'Annually', '2025-04-29 05:56:18', '2025-04-29 05:56:18'),
(37, 'H 02', 'Ground tackle, bitts, cleats, and fairleads', NULL, NULL, NULL, NULL, 'AUTHORIZED INSPECTOR: LICENSED OFFICER or his designated representative \r\nREFERENCES: 46 CFR 184.300', 'Annually', '2025-04-29 05:57:25', '2025-04-29 05:57:25'),
(38, 'I 02', 'Hull, decks, fittings, & watertight integrity, Watertight bulkheads', NULL, NULL, NULL, NULL, 'AUTHORIZED INSPECTOR: LICENSED OFFICER or his designated representative \r\nREFERENCES: 46 CFR 179.320', 'Annually', '2025-04-29 05:59:13', '2025-04-29 05:59:13'),
(39, 'I 03', 'Hull, Decks, Fittings, & Watertight Integrity, Stuffing tubes and sealants', NULL, NULL, NULL, NULL, 'AUTHORIZED INSPECTOR: LICENSED OFFICER or his designated representative \r\nREFERENCES: 46 CFR 179.320', 'Annually', '2025-04-29 06:01:20', '2025-04-29 06:01:20'),
(40, 'I 04', 'Hull, Decks, Fittings, & Watertight Integrity, Remote operated valves and controls', NULL, NULL, NULL, NULL, 'AUTHORIZED INSPECTOR: LICENSED OFFICER or his designated representative \r\nREFERENCES: 46 CFR 182.500, 182.510', 'Quarterly', '2025-04-29 06:02:58', '2025-04-29 06:02:58'),
(41, 'I 05', 'HULL, DECKS, FITTINGS &  WATERTIGHT INTEGRITY; HULL AND DECK OPENINGS', NULL, NULL, NULL, NULL, 'AUTHORIZED INSPECTOR: LICENSED OFFICER or his designated representative \r\nREFERENCES: 46 CFR 179.360', 'Annually', '2025-05-27 04:17:32', '2025-05-27 04:17:32');

-- --------------------------------------------------------

--
-- Table structure for table `icr_steps`
--

CREATE TABLE `icr_steps` (
  `step_id` int NOT NULL,
  `icr_id` int NOT NULL,
  `step_number` int NOT NULL,
  `step_description` text NOT NULL,
  `deficiency_action` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `icr_steps`
--

INSERT INTO `icr_steps` (`step_id`, `icr_id`, `step_number`, `step_description`, `deficiency_action`, `created_at`, `updated_at`) VALUES
(3, 5, 1, 'Certificate of Inspection', '', '2025-04-21 06:43:31', '2025-04-21 06:43:31'),
(4, 5, 2, 'FCC Station License', '', '2025-04-21 06:43:31', '2025-04-21 06:43:31'),
(5, 5, 3, 'FCC Safety Radio Certificate', '', '2025-04-21 06:43:31', '2025-04-21 06:43:31'),
(6, 5, 4, 'Stability Letter', '', '2025-04-21 06:43:31', '2025-04-21 06:43:31'),
(7, 5, 5, 'Certificate of Documentation or Certificate of Numbers issued by the State ', '', '2025-04-21 06:43:31', '2025-04-21 06:43:31'),
(8, 5, 6, 'Master\'s License', '', '2025-04-21 06:43:31', '2025-04-21 06:43:31'),
(9, 6, 1, 'Navigation Rules', '', '2025-04-21 06:45:04', '2025-04-21 06:45:04'),
(10, 6, 2, 'Coast Pilot', '', '2025-04-21 06:45:04', '2025-04-21 06:45:04'),
(11, 6, 3, 'Charts', '', '2025-04-21 06:45:04', '2025-04-21 06:45:04'),
(12, 6, 4, 'Notice to Mariners', '', '2025-04-21 06:45:04', '2025-04-21 06:45:04'),
(13, 6, 5, 'Tide tables', '', '2025-04-21 06:45:04', '2025-04-21 06:45:04'),
(14, 6, 6, 'Current Tables', '', '2025-04-21 06:45:04', '2025-04-21 06:45:04'),
(15, 6, 7, 'Light Lists', '', '2025-04-21 06:45:04', '2025-04-21 06:45:04'),
(16, 7, 1, 'Fire extinguishing equipment servicing', '', '2025-04-21 06:45:58', '2025-04-21 06:45:58'),
(17, 8, 1, 'Retroreflective material on both sides, at least 31 sq. inches on each side.', '', '2025-04-21 06:47:35', '2025-04-21 06:47:35'),
(18, 9, 1, 'Verify proper size onboard: 20\" for vessel less than 26’ or 24” for all others', '', '2025-04-21 06:53:33', '2025-04-21 06:57:40'),
(19, 9, 2, 'Verify free of cracks and weathering.', '', '2025-04-21 06:53:33', '2025-04-21 06:57:40'),
(20, 9, 3, 'Vessel name stenciled on each.', '', '2025-04-21 06:53:33', '2025-04-21 06:53:33'),
(21, 8, 2, 'Type I, CG Approved', '', '2025-04-21 06:56:55', '2025-04-21 06:56:55'),
(22, 8, 3, 'Verify PFD lights work. If chemical type, check expiration date. If battery type, \r\ncheck battery expiration date, lens and seal.', '', '2025-04-21 06:56:55', '2025-04-21 06:56:55'),
(23, 8, 4, 'Vessel name clearly labeled on each PFD.', '', '2025-04-21 06:56:55', '2025-04-21 06:56:55'),
(24, 8, 5, 'Check straps, snaps, jacket fabric for signs of wear, deterioration.', '', '2025-04-21 06:56:55', '2025-04-21 06:56:55'),
(25, 8, 6, 'Stowed in proper location & labeled.', '', '2025-04-21 06:56:55', '2025-04-21 06:56:55'),
(26, 8, 7, 'Wearing instructions posted.', '', '2025-04-21 06:56:55', '2025-04-21 06:56:55'),
(27, 8, 8, 'Adequate number on board. 1 for every person allowed by the COI. \r\n• 10% of total is required to be children\'s PFD\'s or \r\n•  5%, were all extended size PFDs are used on board; unless adult \r\npassengers only', '', '2025-04-21 06:56:55', '2025-04-21 06:56:55'),
(28, 8, 9, 'Stowage location properly labeled.', '', '2025-04-21 06:56:55', '2025-04-21 06:56:55'),
(29, 8, 10, 'Child and adult jackets are stowed separately.', '', '2025-04-21 06:56:55', '2025-04-21 06:56:55'),
(30, 9, 4, 'Proper number onboard. Total count of all including those with lights and lines.', '', '2025-04-21 06:57:40', '2025-04-21 06:57:40'),
(31, 9, 5, 'Ensure properly mounted in racks for easy deployment.', '', '2025-04-21 06:57:40', '2025-04-21 06:57:40'),
(32, 9, 6, 'Check operation of attached waterlights. Check battery expiration date and replace as \r\nnecessary.', '', '2025-04-21 06:57:40', '2025-04-21 06:57:40'),
(33, 10, 1, 'Correct number & capacity in accordance with COI', '', '2025-04-24 04:18:07', '2025-04-24 04:18:07'),
(34, 10, 2, 'Stowed in tiers no more than 4 high. When stowed in tiers, spacers installed between \r\neach life float or buoyant apparatus.', '', '2025-04-24 04:18:07', '2025-04-24 04:18:07'),
(35, 10, 3, 'Stowage is such that units will float free. Acceptable weak link is attached. ', '', '2025-04-24 04:18:07', '2025-04-24 04:18:07'),
(36, 10, 4, 'Painter is in good condition, secured to float and weak link. Weak link is attached to \r\ndeck.', '', '2025-04-24 04:18:07', '2025-04-24 04:18:07'),
(37, 10, 5, 'Stenciled with vessel name in 3” letters and total capacity in 1.5\" letters. ', '', '2025-04-24 04:18:07', '2025-04-24 04:18:07'),
(38, 10, 6, 'Body of unit is in good condition, life lines and netting are in serviceable condition. ', '', '2025-04-24 04:18:07', '2025-04-24 04:18:07'),
(39, 10, 7, 'Each lifefloat shall be equipped with 2 paddles, water light, lifeline, pendents and a \r\npainter. Each buoyant apparatus shall be fitted with a water light, lifeline, pendents and \r\na painter.', '', '2025-04-24 04:18:07', '2025-04-24 04:18:07'),
(40, 11, 1, 'Servicing report current; within last year. All cylinders & flexible loops (12 yrs) within \r\nhydro requirement.', '', '2025-04-24 04:22:26', '2025-04-24 04:23:43'),
(41, 11, 2, 'Diffusers are clear of obstructions.', '', '2025-04-24 04:22:26', '2025-04-24 04:23:43'),
(42, 11, 3, 'Alarms in protected spaces are labeled, warning labels posted.', '', '2025-04-24 04:23:43', '2025-04-24 04:23:43'),
(43, 11, 4, 'Cable pulls are marked.', '', '2025-04-24 04:23:43', '2025-04-24 04:23:43'),
(44, 11, 5, 'Instructions are posted.', '', '2025-04-24 04:23:43', '2025-04-24 04:23:43'),
(45, 11, 6, 'Cylinder brackets fixed and in good condition.', '', '2025-04-24 04:23:43', '2025-04-24 04:23:43'),
(46, 11, 7, 'Cylinders free of corrosion.', '', '2025-04-24 04:23:43', '2025-04-24 04:23:43'),
(47, 11, 8, 'Closure for protected spaces; provided; conduct operational test.', '', '2025-04-24 04:23:43', '2025-04-24 04:23:43'),
(48, 11, 9, 'ventilation and engine shutdowns operational.', '', '2025-04-24 04:23:43', '2025-04-24 04:23:43'),
(49, 11, 10, 'Witness operational test of system by servicing company.', '', '2025-04-24 04:23:43', '2025-04-24 04:23:43'),
(50, 12, 1, 'Servicing report current; within last year. All cylinders & flexible loops (12 yrs) within \r\nhydro requirement.', '', '2025-04-24 04:25:47', '2025-04-24 04:25:47'),
(51, 12, 2, 'Diffusers are clear of obstructions.', '', '2025-04-24 04:25:47', '2025-04-24 04:25:47'),
(52, 12, 3, 'Alarms in protected spaces are labeled, warning labels posted.', '', '2025-04-24 04:25:47', '2025-04-24 04:25:47'),
(53, 12, 4, 'Cable pulls are marked.', '', '2025-04-24 04:25:47', '2025-04-24 04:25:47'),
(54, 12, 5, 'Instructions are posted.', '', '2025-04-24 04:25:47', '2025-04-24 04:25:47'),
(55, 12, 6, 'Cylinder brackets fixed and in good condition. ', '', '2025-04-24 04:25:47', '2025-04-24 04:25:47'),
(56, 12, 7, 'Cylinders free of corrosion. ', '', '2025-04-24 04:25:47', '2025-04-24 04:25:47'),
(57, 12, 8, 'Closure for protected spaces; provided; conduct operational test.', '', '2025-04-24 04:25:47', '2025-04-24 04:25:47'),
(58, 12, 9, 'Ventilation and engine shutdowns operational. ', '', '2025-04-24 04:25:47', '2025-04-24 04:25:47'),
(59, 12, 10, 'Witness operational test of system by servicing company.', '', '2025-04-24 04:25:47', '2025-04-24 04:25:47'),
(60, 13, 1, 'Approved type, mounted in approved bracket.', '', '2025-04-24 04:27:50', '2025-05-22 02:09:46'),
(61, 13, 2, 'Cylinder corrosion free.', '', '2025-04-24 04:27:50', '2025-05-22 02:09:46'),
(62, 13, 3, 'Discharge hose is flexible; no signs of wear, deterioration; discharge nozzle intact.', '', '2025-04-24 04:27:50', '2025-05-22 02:09:46'),
(63, 13, 4, 'Hydro test dates current: 5 yrs for CO2, 12 yrs for dry chemical.', '', '2025-04-24 04:27:50', '2025-05-22 02:09:46'),
(64, 13, 5, 'Location in accordance with table 181.500(a).', '', '2025-04-24 04:27:50', '2025-04-24 04:27:50'),
(65, 13, 6, 'Location in accordance with table 181.500(a).', '', '2025-04-24 04:27:50', '2025-04-24 04:27:50'),
(66, 14, 1, 'operate fire pumps; operating properly? \r\n1.  No excessive leaks. \r\n2.  Foundation/ pump and motor secure. \r\n3.  Shaft bearing- no play. \r\n4.  Coupling guard in place. \r\n5.  Remote operation.\r\n\r\n\r\n\r\n', '', '2025-04-24 04:30:33', '2025-04-24 04:30:33'),
(67, 14, 2, 'All required hoses onboard, compatible threads, satisfactory condition.', '', '2025-04-24 04:30:33', '2025-04-24 04:30:33'),
(68, 14, 3, 'Fire hydrant- Hose at hydrant and attached, spanner wrench, nozzle, low velocity fog \r\napplicator where applicable. All equipment compatible. ', '', '2025-04-24 04:30:33', '2025-04-24 04:30:33'),
(69, 14, 4, 'Hoses correct length (50’) and size, based on COI.', '', '2025-04-24 04:30:33', '2025-04-24 04:30:33'),
(70, 14, 5, 'Satisfactory hydrostatic test of hoses to fire pump shutoff head pressure.', '', '2025-04-24 04:30:33', '2025-04-24 04:30:33'),
(71, 14, 6, 'Check pressure gauge on discharge side of pump to make sure it is functioning \r\nproperly. ', '', '2025-04-24 04:30:33', '2025-04-24 04:30:33'),
(72, 14, 7, 'Verify all valves at fire hydrants are operable. ', '', '2025-04-24 04:30:33', '2025-04-24 04:30:33'),
(73, 14, 8, 'Verify compatibility of equipment at each hydrant.', '', '2025-04-24 04:30:33', '2025-04-24 04:30:33'),
(74, 14, 9, 'Determine that relief valves are set properly and discharge to acceptable location if \r\ninstalled.', '', '2025-04-24 04:30:33', '2025-04-24 04:30:33'),
(75, 15, 1, 'Witness operational test of fire detection system.', '', '2025-04-25 04:56:55', '2025-04-25 04:56:55'),
(76, 15, 2, 'Assure all sensors are free of obstruction and functioning. ', '', '2025-04-25 04:56:55', '2025-04-25 04:56:55'),
(77, 15, 3, 'Verify alarms and indicators are functioning correctly; visible and audible from the pilot \r\nhouse or fire control station. ', '', '2025-04-25 04:56:55', '2025-04-25 04:56:55'),
(78, 15, 4, 'Verify audible alarms in engine room are functioning properly, if provided.', '', '2025-04-25 04:56:55', '2025-04-25 04:56:55'),
(79, 15, 5, 'Ensure engine room, pilothouse , and fire control station alarms are conspicuously \r\nmarked in clearly legible letters. ', '', '2025-04-25 04:56:55', '2025-04-25 04:56:55'),
(80, 15, 6, 'Manual alarm systems functioning properly. ', '', '2025-04-25 04:56:55', '2025-04-25 04:56:55'),
(81, 16, 1, 'Verify manual operation of all fire dampers.', '', '2025-04-25 05:11:49', '2025-04-25 05:11:49'),
(82, 16, 2, 'Test remote operation of all remote ventilation shutdowns.', '', '2025-04-25 05:11:49', '2025-04-25 05:11:49'),
(83, 17, 1, 'Proper number of fire axe(s) in accordance with Certificate of Inspection.', '', '2025-04-25 05:19:52', '2025-04-25 05:19:52'),
(84, 17, 2, 'Stowed at the primary operating station. ', '', '2025-04-25 05:19:52', '2025-04-25 05:19:52'),
(85, 18, 1, 'Proper number of fire buckets; three (3) 2½ gallon buckets with lanyards.', '', '2025-04-26 20:27:53', '2025-04-26 20:27:53'),
(86, 18, 2, 'Stenciled in contrasting color with the words “FIRE BUCKET”.', '', '2025-04-26 20:27:53', '2025-04-26 20:27:53'),
(87, 19, 1, 'Tested monthly using visual or audio output indicator.', '', '2025-04-26 20:51:01', '2025-04-26 20:51:44'),
(88, 19, 2, 'Stowed in a manner so that it will float free should the vessel sink & auto-activate.', '', '2025-04-26 20:51:44', '2025-04-26 20:51:44'),
(89, 19, 3, 'Replace battery  if EPIRB is used for purposes other than testing. Replace battery on \r\nor before the expiration date marked on the battery.', '', '2025-04-26 20:51:44', '2025-04-26 20:51:44'),
(90, 19, 4, 'Vessel name shall be marked on EPIRB.', '', '2025-04-26 20:51:44', '2025-04-26 20:51:44'),
(91, 20, 1, 'General Alarm contact makers and alarm bells are located and marked in accordance \r\nwith the regulations.', '', '2025-04-26 20:54:35', '2025-04-26 20:54:35'),
(92, 20, 2, 'Energize system from each contact maker. Ensure contact  makers are all operable, \r\nensure alarm bells are all operable and that none have been deliberately disabled.', '', '2025-04-26 20:54:35', '2025-05-27 04:19:42'),
(93, 20, 3, 'Ensure alarm bells are sufficiently loud to be easily heard above the ambient noise of \r\nthe space in which they are placed.', '', '2025-04-26 20:54:35', '2025-04-26 20:54:35'),
(94, 20, 4, 'Ensure operation of any flashing red lights installed in addition to alarm bells.', '', '2025-04-26 20:54:35', '2025-04-26 20:54:35'),
(95, 21, 1, 'Verify the correct amount: \r\nOceans, Coastwise, or Limited Coastwise: • 6 hand red & 6 hand orange smoke; or \r\n• 12 rocket parachute; or \r\n• 12 hand red;or \r\n• 6 hand red & 6 orange float; or, \r\n• combination allowed by regulation. \r\n\r\nLakes, Bays, and Sounds, Rivers:\r\n\r\n• 3 hand red & 3 hand orange smoke; or \r\n• 6 rocket parachute; or \r\n• 6 hand red; or \r\n• 3 hand red & 3 orange float; or, \r\n• combination allowed by regulation. ', '', '2025-04-26 20:58:00', '2025-04-26 20:58:00'),
(96, 21, 2, 'The service life of the distress signals shall be stamped by the manufacture on the \r\ndistress signal. ', '', '2025-04-26 20:58:00', '2025-04-26 20:58:00'),
(97, 21, 3, 'The distress signals shall be stowed in a portable watertight container at the operating \r\nstation or a pyrotechnic locker secured above the freeboard deck in the vicinity of the \r\noperating station.', '', '2025-04-26 20:58:00', '2025-04-26 20:58:00'),
(98, 22, 1, 'Must be audible during normal operating conditions throughout accommodations and \r\nother normally manned spaces. ', '', '2025-04-26 21:07:35', '2025-04-26 21:07:35'),
(99, 22, 2, 'Must be operable from operation station when required. ', '', '2025-04-26 21:07:35', '2025-04-26 21:07:35'),
(100, 22, 3, 'When allowed, bullhorn batteries continually maintained. ', '', '2025-04-26 21:07:35', '2025-04-26 21:07:35'),
(101, 23, 1, 'Verify first aid kit is approved under the series 160.041 or other standard specified by \r\nthe Commandant.', '', '2025-04-26 21:13:18', '2025-04-26 21:13:18'),
(102, 23, 2, 'Ensure “First Aid Kit is stenciled on container.', '', '2025-04-26 21:13:18', '2025-04-26 21:13:18'),
(103, 23, 3, 'Ensure first aid kit is visible and readily available to the crew.', '', '2025-04-26 21:13:18', '2025-04-26 21:13:18'),
(104, 23, 4, 'Ensure contents of kit are adequate.', '', '2025-04-26 21:13:18', '2025-04-26 21:13:18'),
(105, 24, 1, 'If power ventilation is installed, it must be capable of being shutdown from the pilot \r\nhouse.', '', '2025-04-26 21:14:20', '2025-04-26 21:14:20'),
(106, 24, 2, 'delete me', '', '2025-04-26 21:14:20', '2025-04-26 21:14:20'),
(107, 25, 1, 'Vent line not holed or excessively corroded. ', '', '2025-04-26 21:34:18', '2025-04-26 21:34:18'),
(108, 25, 2, 'Flame screen or flame arrester is clean, in good condition and firmly attached to the \r\nvent. ', '', '2025-04-26 21:34:18', '2025-04-26 21:34:18'),
(109, 25, 3, 'Flame screen is a single screen of 30x30.', '', '2025-04-26 21:34:18', '2025-04-26 21:34:18'),
(110, 25, 4, 'Containment is available, clean, dry and in good condition. ', '', '2025-04-26 21:34:18', '2025-04-26 21:34:18'),
(111, 26, 1, 'Vent line not holed or excessively corroded.', '', '2025-04-29 05:28:00', '2025-04-29 05:28:00'),
(112, 27, 1, 'Grease extraction hood UL listed. ', '', '2025-04-29 05:30:46', '2025-04-29 05:30:46'),
(113, 27, 2, 'Vent trunk not holed or excessively corroded.', '', '2025-04-29 05:30:46', '2025-04-29 05:30:46'),
(114, 27, 3, 'Interior of vent free of grease.', '', '2025-04-29 05:30:46', '2025-04-29 05:30:46'),
(115, 28, 1, 'Examine radar for acceptable picture quality.', '', '2025-04-29 05:31:52', '2025-04-29 05:31:52'),
(116, 28, 2, 'Verify operator controls and adjustments function properly.', '', '2025-04-29 05:31:52', '2025-04-29 05:31:52'),
(117, 28, 3, 'Examine for excessive noise, vibration, or wear.  Ensure secure mounting. ', '', '2025-04-29 05:31:52', '2025-04-29 05:31:52'),
(118, 28, 4, 'Verify controls illuminate. ', '', '2025-04-29 05:31:52', '2025-04-29 05:31:52'),
(119, 28, 5, 'Verify display at several ranges. ', '', '2025-04-29 05:31:52', '2025-04-29 05:31:52'),
(120, 29, 1, 'Check for illumination.', '', '2025-04-29 05:32:50', '2025-04-29 05:32:50'),
(121, 29, 2, 'Ensure compass can be read from main steering position.', '', '2025-04-29 05:32:50', '2025-04-29 05:32:50'),
(122, 29, 3, 'Ensure deviation table is current, and no major structural changes have been made.', '', '2025-04-29 05:32:50', '2025-04-29 05:32:50'),
(123, 30, 1, 'Must be capable of operating in 156-162 MHz range. Capable of transmitting and \r\nreceiving VHF FM Channels 13, 16, 22A. TEST : Obtain radio checks. ', '', '2025-04-29 05:34:40', '2025-04-29 05:34:40'),
(124, 30, 2, 'Separate circuit with overcurrent protection at the main distribution panel. ', '', '2025-04-29 05:34:40', '2025-04-29 05:34:40'),
(125, 30, 3, 'Supplied by two sources of electricity or batteries with a capacity for three hours. ', '', '2025-04-29 05:34:40', '2025-04-29 05:34:40'),
(126, 30, 4, 'Verify that FCC certificates are valid. ', '', '2025-04-29 05:34:40', '2025-04-29 05:34:40'),
(127, 30, 5, 'Must be DSC equipped and MMSI must be inputted into the radio', '', '2025-04-29 05:34:40', '2025-04-29 05:34:40'),
(128, 30, 6, 'Verify GPS function on radio is operational.', '', '2025-04-29 05:34:40', '2025-04-29 05:34:40'),
(129, 31, 1, 'Verify that navigation lights are operable. Test on emergency power if installed. ', '', '2025-04-29 05:35:50', '2025-04-29 05:35:50'),
(130, 31, 2, 'Proper bulbs installed. ', '', '2025-04-29 05:35:50', '2025-04-29 05:35:50'),
(131, 31, 3, 'If navigation light indicator panel installed; operating properly- check all fuses and \r\nalarms. ', '', '2025-04-29 05:35:50', '2025-04-29 05:35:50'),
(132, 31, 4, 'Verify lights are installed in accordance with Navigation rules.', '', '2025-04-29 05:35:50', '2025-04-29 05:35:50'),
(133, 31, 5, 'Reflective screens in place and painted matte black?', '', '2025-04-29 05:35:50', '2025-04-29 05:35:50'),
(134, 31, 6, 'Lenses clean, wiring free of splices; no deterioration, installation appears sound.', '', '2025-04-29 05:35:50', '2025-04-29 05:35:50'),
(135, 31, 7, 'Supplied by two sources of electricity or batteries with a capacity for three hours.', '', '2025-04-29 05:35:50', '2025-04-29 05:35:50'),
(136, 32, 1, 'If sound powered telephones or voice tubes are installed verify operation. ', '', '2025-04-29 05:37:22', '2025-04-29 05:37:22'),
(137, 32, 2, 'Test ringers, and operation of each voice tube or sound powered phone in a location \r\nrequired by the regulations. Ensure each can be heard above the ambient noise of that \r\nlocation. ', '', '2025-04-29 05:37:22', '2025-04-29 05:37:22'),
(138, 32, 3, 'If hand held portable radios are used verify operation.', '', '2025-04-29 05:37:22', '2025-04-29 05:37:22'),
(139, 32, 4, 'Verify operation from operating station to location for controlling propulsion machinery.', '', '2025-04-29 05:37:22', '2025-04-29 05:37:22'),
(140, 33, 1, 'Verify the following are up-to-date and adequate for the route intended; \r\nLarge scale charts, ', '', '2025-04-29 05:42:33', '2025-04-29 05:42:33'),
(141, 33, 2, 'Coast Pilot,', '', '2025-04-29 05:42:33', '2025-04-29 05:42:33'),
(142, 33, 3, 'Light List, ', '', '2025-04-29 05:42:33', '2025-04-29 05:42:33'),
(143, 33, 4, 'Tide Tables, ', '', '2025-04-29 05:42:33', '2025-04-29 05:42:33'),
(144, 33, 5, 'Current tables or River Current Publications. ', '', '2025-04-29 05:42:33', '2025-04-29 05:42:33'),
(145, 34, 1, 'All dayshapes shall be black. ', '', '2025-04-29 05:45:26', '2025-04-29 05:45:26'),
(146, 34, 2, 'If shape is a ball, it shall not have a diameter of over .6 meter. ', '', '2025-04-29 05:45:26', '2025-04-29 05:45:26'),
(147, 34, 3, 'If shape is a cone, it shall have a base diameter of over .6 meters, and a height equal \r\nto its diameter.', '', '2025-04-29 05:45:26', '2025-04-29 05:45:26'),
(148, 34, 4, 'If the shape is a cylinder,  it shall have a diameter of at least .6 meters, and a height of \r\ntwice the diameter.', '', '2025-04-29 05:45:26', '2025-04-29 05:45:26'),
(149, 34, 5, 'A diamond shape shall consist of cones as defined above, having a common base. ', '', '2025-04-29 05:45:26', '2025-04-29 05:45:26'),
(150, 34, 6, 'The vertical distance between shapes shall be at least 1.5 meters.', '', '2025-04-29 05:45:26', '2025-04-29 05:45:26'),
(151, 34, 7, 'The frequency of a whistle shall be as required by Table 86.05. ', '', '2025-04-29 05:45:26', '2025-04-29 05:45:26'),
(152, 34, 8, 'The whistle shall be installed with it\'s forward axis directed forward and placed as high \r\nas practicable.', '', '2025-04-29 05:45:26', '2025-04-29 05:45:26'),
(153, 35, 1, 'Test the electronic position-fixing device for accuracy by comparing a fix on the \r\ndevice to a charted location. ', '', '2025-04-29 05:46:21', '2025-04-29 05:46:21'),
(154, 36, 1, 'Anchor sized in accordance with industry standards or as required by OCMI.', '', '2025-04-29 05:56:18', '2025-04-29 05:56:18'),
(155, 36, 2, 'Ensure all anchor releasing and retrieval equipment is operable and in good working \r\ncondition (line/chain, winch/davit or windlass foundation, stopper, brake). ', '', '2025-04-29 05:56:18', '2025-04-29 05:56:18'),
(156, 36, 3, 'Anchor winch or windlass should be tested to let out and retrieve chain.', '', '2025-04-29 05:56:18', '2025-04-29 05:56:18'),
(157, 37, 1, 'Bits, cleats and fairleads not excessively corroded or grooved; no scale build-up.', '', '2025-04-29 05:57:25', '2025-04-29 05:57:25'),
(158, 37, 2, 'Cleat/bit horns not missing, broken or excessively.', '', '2025-04-29 05:57:25', '2025-04-29 05:57:25'),
(159, 37, 3, 'Foundations not fractured.', '', '2025-04-29 05:57:25', '2025-04-29 05:57:25'),
(160, 37, 4, 'All guy wires taut, no slack; turnbuckles, wire rope not wasted.', '', '2025-04-29 05:57:25', '2025-04-29 05:57:25'),
(161, 38, 1, 'Examine all watertight bulkheads to ensure they are intact and watertight. Foam \r\nflotation (if required & installed) not waterlogged. ', '', '2025-04-29 05:59:13', '2025-04-29 05:59:13'),
(162, 38, 2, 'Examine collision bulkhead ensuring it is intact and watertight.', '', '2025-04-29 05:59:13', '2025-04-29 05:59:13'),
(163, 38, 3, 'Ensure electrical cable and piping penetrations maintain watertight integrity and are \r\nkept to a minimum. ', '', '2025-04-29 05:59:13', '2025-04-29 05:59:13'),
(164, 38, 4, 'Examine for signs of corrosion or deterioration.', '', '2025-04-29 05:59:13', '2025-04-29 05:59:13'),
(165, 38, 5, 'Ensure sluice valves have not been installed.', '', '2025-04-29 05:59:13', '2025-04-29 05:59:13'),
(166, 39, 1, 'Ensure electrical cable and piping penetrations maintain watertight integrity i. e. stuffing \r\ntubes still serviceable.', '', '2025-04-29 06:01:20', '2025-04-29 06:01:20'),
(167, 39, 2, 'If sealant is used in penetrations, it must be a non-flammable product designed for \r\nsuch use. ', '', '2025-04-29 06:01:20', '2025-04-29 06:01:20'),
(168, 40, 1, 'Verify operation of all remote fuel shutoff valves. Ensure markings on the weather deck \r\nare legible and unobstructed.', '', '2025-04-29 06:02:58', '2025-04-29 06:02:58'),
(169, 40, 2, 'Ensure all valves adequately lubricated and operate freely. ', '', '2025-04-29 06:02:58', '2025-04-29 06:02:58'),
(170, 40, 3, 'Operate each reach rod and other manual remote control mechanisms function \r\nproperly.', '', '2025-04-29 06:02:58', '2025-04-29 06:02:58'),
(171, 40, 4, 'Verify each power operated valve operates properly from control station.', '', '2025-04-29 06:02:58', '2025-04-29 06:02:58'),
(172, 41, 1, 'Ensure all dogs are properly lubricated and operate freely.', '', '2025-05-27 04:17:32', '2025-05-27 04:17:32'),
(173, 41, 2, 'Ensure all gaskets are in place and clean. (i.e., free of paint, not deteriorated.)', '', '2025-05-27 04:17:32', '2025-05-27 04:17:32'),
(174, 41, 3, 'Ensure all knife edges are clean and free of nicks and paint.', '', '2025-05-27 04:17:32', '2025-05-27 04:17:32'),
(175, 41, 4, 'Ensure hinges and bolts are in good condition; no sagging of door due to worn hinge \r\nbolts.', '', '2025-05-27 04:17:32', '2025-05-27 04:17:32'),
(176, 41, 5, 'Ensure dogging wedges are not excessively worn.', '', '2025-05-27 04:17:32', '2025-05-27 04:17:32'),
(177, 41, 6, 'Ensure all hatches have retaining devices.', '', '2025-05-27 04:17:32', '2025-05-27 04:17:32');

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` int NOT NULL,
  `user_id` int DEFAULT NULL,
  `attempt_time` datetime DEFAULT CURRENT_TIMESTAMP,
  `success` tinyint(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `login_attempts`
--

INSERT INTO `login_attempts` (`id`, `user_id`, `attempt_time`, `success`) VALUES
(13, 2, '2025-04-20 11:28:46', 0),
(14, 2, '2025-04-20 11:28:53', 0),
(15, 2, '2025-04-20 11:31:56', 1),
(16, 2, '2025-04-20 11:40:00', 1),
(17, 2, '2025-04-20 11:41:33', 1),
(18, 2, '2025-04-20 12:31:28', 1),
(19, 2, '2025-04-20 12:35:36', 1),
(20, 2, '2025-04-20 19:24:39', 1),
(21, 2, '2025-04-20 21:07:04', 1),
(22, 2, '2025-04-20 21:27:01', 1),
(23, 2, '2025-04-23 08:13:52', 1),
(24, 2, '2025-04-23 09:55:22', 1),
(25, 2, '2025-04-23 15:22:39', 1),
(26, 10, '2025-04-23 15:23:10', 1),
(27, 2, '2025-04-23 15:44:49', 1),
(28, 10, '2025-04-23 15:45:09', 1),
(29, 10, '2025-04-23 16:02:54', 1),
(30, 2, '2025-04-23 16:07:26', 1),
(31, 10, '2025-04-23 16:08:43', 1),
(32, 2, '2025-04-23 17:13:09', 1),
(33, 10, '2025-04-23 17:30:28', 1),
(34, 2, '2025-04-23 17:33:42', 1),
(35, 10, '2025-04-23 17:53:19', 1),
(36, 2, '2025-04-23 18:13:02', 1),
(37, 10, '2025-04-23 18:13:19', 1),
(38, 2, '2025-04-23 18:31:17', 1),
(39, 10, '2025-04-23 19:41:04', 1),
(40, 2, '2025-04-23 19:41:25', 1),
(41, 2, '2025-04-24 18:26:53', 0),
(42, 10, '2025-04-24 18:28:05', 1),
(43, 2, '2025-04-24 18:29:11', 1),
(44, 10, '2025-04-24 18:35:41', 1),
(45, 2, '2025-04-24 18:43:47', 1),
(46, 2, '2025-04-24 18:57:15', 1),
(47, 10, '2025-04-24 19:27:40', 1),
(48, 2, '2025-04-24 19:53:55', 1),
(49, 10, '2025-04-24 20:28:26', 1),
(50, 2, '2025-04-24 20:30:24', 0),
(51, 2, '2025-04-24 20:30:32', 1),
(52, 2, '2025-04-26 10:20:56', 1),
(53, 2, '2025-04-26 11:28:02', 1),
(54, 2, '2025-04-26 12:18:49', 1),
(55, 2, '2025-04-26 12:26:40', 1),
(56, 2, '2025-04-26 15:18:45', 1),
(57, 2, '2025-04-27 18:19:57', 1),
(58, 10, '2025-04-27 18:49:58', 1),
(59, 2, '2025-04-27 18:51:33', 1),
(60, 2, '2025-04-28 18:48:43', 1),
(61, 2, '2025-05-02 16:22:42', 1),
(62, 2, '2025-05-03 10:17:51', 1),
(63, 2, '2025-05-04 10:48:29', 1),
(64, 10, '2025-05-04 12:54:29', 1),
(65, 2, '2025-05-04 12:55:05', 1),
(66, 10, '2025-05-04 13:02:20', 1),
(67, 2, '2025-05-04 13:07:12', 1),
(68, 2, '2025-05-04 14:10:12', 1),
(69, 10, '2025-05-04 14:55:34', 1),
(70, 2, '2025-05-04 14:55:52', 1),
(71, 10, '2025-05-04 15:17:01', 1),
(72, 2, '2025-05-04 15:22:08', 1),
(73, 10, '2025-05-04 15:24:28', 1),
(74, 2, '2025-05-04 15:24:53', 1),
(75, 2, '2025-05-04 16:07:36', 1),
(76, 2, '2025-05-04 17:03:57', 1),
(77, 2, '2025-05-05 18:44:24', 1),
(78, 2, '2025-05-05 22:32:03', 1),
(79, 2, '2025-05-07 09:21:22', 1),
(80, 2, '2025-05-07 21:02:47', 1),
(81, 2, '2025-05-07 21:05:13', 1),
(82, 20, '2025-05-07 21:08:09', 1),
(83, 2, '2025-05-07 21:24:49', 1),
(84, 2, '2025-05-07 21:27:51', 1),
(85, 2, '2025-05-07 21:37:27', 1),
(86, 2, '2025-05-07 22:41:38', 1),
(87, 2, '2025-05-08 00:39:20', 1),
(88, 10, '2025-05-08 00:46:14', 1),
(89, 2, '2025-05-08 01:16:04', 1),
(90, 21, '2025-05-08 01:21:06', 1),
(91, 2, '2025-05-08 01:22:40', 1),
(92, 20, '2025-05-08 01:38:36', 1),
(93, 21, '2025-05-08 01:49:21', 1),
(94, 2, '2025-05-08 01:50:57', 1),
(95, 21, '2025-05-08 01:52:14', 1),
(96, 2, '2025-05-08 01:55:32', 1),
(97, 2, '2025-05-08 05:51:47', 1),
(98, 10, '2025-05-08 05:52:41', 1),
(99, 2, '2025-05-08 05:57:43', 0),
(100, 2, '2025-05-08 05:57:49', 1),
(101, 10, '2025-05-08 06:20:18', 1),
(102, 2, '2025-05-08 06:25:05', 1),
(103, 21, '2025-05-08 16:18:09', 1),
(104, 2, '2025-05-09 03:39:10', 1),
(105, 2, '2025-05-12 04:37:55', 1),
(106, 10, '2025-05-12 04:39:02', 1),
(107, 2, '2025-05-13 07:08:22', 1),
(108, 22, '2025-05-13 08:05:32', 0),
(109, 22, '2025-05-13 08:05:42', 0),
(110, 2, '2025-05-13 08:05:47', 1),
(111, 22, '2025-05-13 08:15:43', 1),
(112, 2, '2025-05-14 16:36:58', 1),
(113, 2, '2025-05-14 23:08:38', 1),
(114, 2, '2025-05-14 23:51:19', 0),
(115, 2, '2025-05-14 23:51:25', 1),
(116, 2, '2025-05-15 01:48:29', 1),
(117, 2, '2025-05-15 16:59:17', 1),
(118, 2, '2025-05-16 16:58:29', 1),
(119, 2, '2025-05-16 18:13:28', 1),
(120, 2, '2025-05-16 19:12:36', 1),
(121, 2, '2025-05-17 21:53:32', 1),
(122, 10, '2025-05-17 21:59:11', 1),
(123, 2, '2025-05-18 07:22:49', 1),
(124, 2, '2025-05-18 08:05:44', 1),
(125, 2, '2025-05-20 22:24:17', 1),
(126, 2, '2025-05-20 22:26:49', 1),
(127, 10, '2025-05-20 22:28:07', 1),
(128, 2, '2025-05-21 06:16:30', 1);

-- --------------------------------------------------------

--
-- Table structure for table `owners`
--

CREATE TABLE `owners` (
  `owner_id` int NOT NULL,
  `company_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `contact_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `logo_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `owners`
--

INSERT INTO `owners` (`owner_id`, `company_name`, `contact_name`, `email`, `phone`, `address`, `logo_path`) VALUES
(1, 'MSCS Hawaii', 'Sean Keeman', 'info@mschawaii.org', '9573161', '5352 Olopua Street, Kapaa, HI  96746', 'uploads/logos/68093a02c24b4_MSCS_Logo_Color.png'),
(2, 'Makana Charters', 'Cain Robinson', 'cr@makanacharters.com', '808-652-9595', '4516 Alawai Road, Waimea, HI  96746', 'uploads/logos/68093cc69f993_OIP.jpg'),
(3, 'Island Magic Catamarans', 'Mike Perez', 'regeniellc@gmail.com', '808-699-8877', '1726 Fern Street\r\nHonolulu, HI  96826', NULL),
(4, 'Kepoikai Catamarans', 'Shiela Lipton', 'sheila@kepoikai.com', '808-224-7688', '45-672 Maiaponi Place, \r\nKaneohe, HI  96744', 'uploads/logos/logo_68316a6df3ff4.jpg'),
(5, 'Maalaea Sport Fishing', 'Scott Turner', 'scott.turner@mmsc-maui.com', '808-357-4836', '325 Hukilike Street #6\r\nKahului, HI  96732', NULL),
(6, 'Malolo 1', 'Cody Kimura', 'cody@goblueadventures.com', '808-634-8588', '1586 Haleukana Street Unit A\r\nLihue HI  96746', NULL),
(7, 'Maui - Molokai Sea Cruises', 'Scott Turner', 'scott.turner@mmsc-maui.com', '808-357-4836', '325 Hukilike Street #6\r\nKahului, HI  96732', NULL),
(8, 'Na Pali Experience', 'Nathaniel Fisher', 'kauaifisher@gmail.com', '808-635-6283', '159A Wailua Road Unit A\r\nKapaa, Hi  96746', NULL),
(9, 'Kauai Sea Tours', 'Darren Paskal', 'darren@kauaiseatours.com', '323-547-7678', '4353 Waialo Road\r\nEleele, HI  96705', NULL),
(10, 'Na Pali Riders', 'Brandon Elsasser', 'napaliriderskauai@gmail.com', '808-634-8353', '4579 Ehako Street\r\nKalaheo, HI  96741', NULL),
(11, 'Pink Sails Waikiki', 'DJ Smith', 'dj@pinksailswaikiki.com', '808-600-7117', '847 McCully Street\r\nHonolulu, HI  96826', NULL),
(12, 'Hawaii Nautical', 'Mark Towill', 'mark@hawaiinautical.com', '808-223-5947', '4348 Waialae Ave #332\r\nHonolulu, HI 96816', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `role_id` int NOT NULL,
  `role_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`role_id`, `role_name`, `description`) VALUES
(1, 'Admin', NULL),
(2, 'Manager', NULL),
(3, 'View Only', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `tasks`
--

CREATE TABLE `tasks` (
  `task_id` int NOT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `corrective_action` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `corrected_date` date DEFAULT NULL,
  `vessel_id` int DEFAULT NULL,
  `vessel_icr_id` int DEFAULT NULL,
  `vessel_icr_run_id` int DEFAULT NULL,
  `equipment_id` int DEFAULT NULL,
  `assigned_to` int DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `completed_date` date DEFAULT NULL,
  `is_recurring` tinyint(1) DEFAULT '0',
  `recurrence_interval` enum('weekly','monthly','quarterly','annually','none') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `status` enum('open','in_progress','complete','overdue','deferred') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'open',
  `priority` enum('urgent','moderate','low','recommendation') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'moderate',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tasks`
--

INSERT INTO `tasks` (`task_id`, `title`, `description`, `corrective_action`, `corrected_date`, `vessel_id`, `vessel_icr_id`, `vessel_icr_run_id`, `equipment_id`, `assigned_to`, `due_date`, `completed_date`, `is_recurring`, `recurrence_interval`, `status`, `priority`, `created_at`, `updated_at`) VALUES
(9, 'test this task', 'testing the task', NULL, NULL, 7, NULL, NULL, 110, 1, '2025-04-24', NULL, 1, 'none', 'deferred', 'moderate', '2025-04-09 21:24:47', '2025-04-19 20:13:54'),
(11, 'test this task 3', 'test this task 3 times', NULL, NULL, 7, NULL, NULL, 110, 1, '0000-00-00', NULL, 0, '', 'complete', 'urgent', '2025-04-09 21:34:38', '2025-04-09 21:36:25'),
(12, 'Check hand rails', 'Verify handrails are properly secured an no welds fractured', NULL, NULL, 7, NULL, NULL, NULL, 1, '0000-00-00', NULL, 0, '', 'open', 'moderate', '2025-04-10 20:21:57', '2025-04-10 20:21:57'),
(13, 'Check bilge pump hoses', 'Bilge pump hose looks old, check and replace if necessary', NULL, NULL, 7, NULL, NULL, NULL, 2, '0000-00-00', NULL, 0, '', 'complete', 'moderate', '2025-04-10 05:53:43', '2025-04-10 05:54:05'),
(20, 'create task test vessel', 'create task test vessel', NULL, NULL, 7, NULL, NULL, 110, 1, '2025-04-23', NULL, 1, 'weekly', 'open', 'moderate', '2025-04-19 19:23:52', '2025-04-19 19:23:52'),
(24, 'test 2', 'test 2', NULL, NULL, 7, NULL, NULL, 110, 2, '2025-04-23', NULL, 1, 'none', 'open', 'moderate', '2025-04-21 07:28:46', '2025-04-21 07:28:46'),
(25, 'ICR Step Failed – 3', 'FCC Safety Radio Certificate\n\nInspector Comment: —', NULL, NULL, 7, NULL, NULL, NULL, NULL, '2025-05-01', NULL, 0, NULL, 'open', 'moderate', '2025-04-24 04:35:38', '2025-04-24 04:35:38'),
(48, 'ICR A 02 – Step 2: Coast Pilot', 'Coast Pilot not on board', '', NULL, 13, 167, 33, NULL, NULL, '2025-05-15', NULL, 0, NULL, 'open', 'moderate', '2025-05-08 01:58:18', '2025-05-08 01:58:18'),
(51, 'ICR A 03 – Step 1: Fire extinguishing equipment servicing', 'two extinguishers deemed not serviceable', 'Replaced extinguishers', '2025-05-26', 17, 197, 39, NULL, 1, '2025-06-03', NULL, 0, NULL, 'complete', 'moderate', '2025-05-27 14:22:29', '2025-05-27 04:23:28'),
(53, 'testing this task again', '', '', NULL, 8, NULL, NULL, NULL, NULL, '2025-06-18', NULL, 0, 'none', 'open', 'moderate', '2025-05-27 04:30:20', '2025-05-27 04:30:20');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `fName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `lName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `phoneNumber` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `email` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `website` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `company_id` int DEFAULT NULL,
  `username` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `pword` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `mmc` date DEFAULT NULL,
  `fa` date DEFAULT NULL,
  `mrop` date DEFAULT NULL,
  `company_cid` int DEFAULT NULL,
  `vessel_id` int DEFAULT NULL,
  `casualties_casid` int DEFAULT NULL,
  `role_id` int DEFAULT '3',
  `mmc_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `fa_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `mrop_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `mmc_file` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `fa_file` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `mrop_file` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `mmc_medical` date DEFAULT NULL,
  `mmc_medical_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `fName`, `lName`, `phoneNumber`, `email`, `website`, `company_id`, `username`, `pword`, `mmc`, `fa`, `mrop`, `company_cid`, `vessel_id`, `casualties_casid`, `role_id`, `mmc_path`, `fa_path`, `mrop_path`, `mmc_file`, `fa_file`, `mrop_file`, `mmc_medical`, `mmc_medical_path`) VALUES
(2, 'Sean', 'Keeman', '8085551234', 'sean@example.com', NULL, 1, 'admin', '$2y$10$5OPmrZ0Pp/.nIDJO0bwQdOVigmcj0HCDHEKKYVJl1/GdhE3oXuq/6', NULL, NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(5, 'Dakota', 'Elsasser', '808-634-7030', 'napaliriders@gmail.com', NULL, 10, 'delsasser', '$2y$10$s/FXWjAugYjj4NtrMZEEtuZn19TXy4.s0MlApD8/tlRDJFVVE3w2G', NULL, NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(6, 'Joda', 'Santos', '808-652-2023', 'joda@makanacharters.com', NULL, 2, 'jsantos', '$2y$10$T9diWt4.RAxVnvIKxxc.Y.2rhsKRlxAJ0doicx4hRrHrkLRPFCP8S', NULL, NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(7, 'Mike', 'Perez', '808-224-7688', 'regniellc@gmail.com', NULL, 3, 'mperez', '$2y$10$6NSgbls8WEaA2gYqfUyTSuKQ8E.Bj.Z./zEJIbY7g3gfoLgZRvbJW', NULL, NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(9, 'Scott', 'Turner', '808-357-4836', 'scott.turner@mmsc-maui.com', NULL, 5, 'sturner', '$2y$10$fdnwF0LqoMTbeaZCkfJafuPioqB6grfIBSjyFjFbXOkUVe44aYulO', NULL, NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(10, 'Cain', 'Robinson', '808-652-9595', 'cr@makanacharters.com', NULL, 2, 'crobinson', '$2y$10$V98WzQFuZbad3lkvagy8GOtqLk3X2AtTc6.t7GDCdDXMq4jjLQ1Dq', NULL, NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(11, 'Mike', 'DeSilva', '808-635-0655', 'mike@makanacharters.com', NULL, 2, 'mdesilva', '$2y$10$ceFUN4UsF4VgJKE3yNTs5OOLfc1z55XTutFhRdN9TR1uAj0XU0qoS', NULL, NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(12, 'Cody', 'Kimura', '808-634-8588', 'cody@goblueadventures.com', NULL, 6, 'ckimura', '$2y$10$WNi.m2F6tSfL.IABs8VK3OB7lblULsWodebD7qoH/U.TMb3W8Pj.6', NULL, NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(13, 'Kalen', 'Kimura', '808-634-9922', 'kalen.kimura@hotmail.com', NULL, 6, 'kkimura', '$2y$10$mKEdcDy.3uhk58SaqhaS2epSBfL5bMxUuL0fAdL9GZ6R7oK2o4FNG', NULL, NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(14, 'Courtney', 'Medieros', '808-635-2634', 'courtney@goblueadventures.com', NULL, 6, 'cmedieros', '$2y$10$9fEN/y8zJOY9V4gVQ6gORuePWD3vVgKM30nWkrSTdoZC.J61SGET6', NULL, NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(15, 'Nathaniel', 'Fisher', '808-635-6283', 'kauaifisher@gmail.com', NULL, 8, 'nfisher', '$2y$10$64H5BavSlLQdwBMsOmKHAeWsk806pR/SYplonct95weY04NlCniIK', NULL, NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(16, 'Darren', 'Paskal', '323-547-7678', 'darren@kauaiseatours.com', NULL, 9, 'dpaskal', '$2y$10$yfApYcYPadzwt3twbUVnyehI9b0HP9kK5idE2rZA6MnuA8T/J4zEG', NULL, NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(17, 'Crois', 'Elsasser', '808-634-9741', 'napaliriderskauai@gmail.com', NULL, 10, 'celsasser', '$2y$10$dI8RLnhy6Oq8KF5puH8pAeloYTa55M1w/tcUkavx64i5DqVEF5s9y', NULL, NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(18, 'Brandon', 'Elsasser', '808-634-8353', 'napaliriderskauai@gmail.com', NULL, 10, 'belsasser', '$2y$10$0WLQH7XzLaQ1ofN2/91d8.DA3Cl7Vx3lhAPIiTiYHrgWMJ18diO/S', NULL, NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(19, 'DJ', 'Smith', '808-600-7117', 'dj@pinksailswaikiki.com', NULL, 11, 'dsmith', '$2y$10$/gu4YUYlHKNQ1qSlOh8YveIsExnQ7VJ6iI5Sw1YCZqrWBorq76GEe', NULL, NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(20, 'Anna', 'Keeman', '810-824-8398', 'anna@mschawaii.org', NULL, 1, 'akeeman', '$2y$10$VsImdAz4m.TQz6pR6IfNGe8R3YS2Ps9sn.42R7TV4GG.NH4DCDK/W', NULL, NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(21, 'Mark', 'Towill', '808-223-5947', 'mark@hawaiinautical.com', NULL, 12, 'mtowill', '$2y$10$OT4OdYYA25S1ePBr025dIOfM/aiZqzg.Ijf/qe.UXtGPUBfbWdP46', NULL, NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(22, 'Allen', 'Dwyer', '', 'allen.r.dwyer@uscg.mil', NULL, 1, 'ADwyer', '$2y$10$dI358PLSozwffdy7Zt9O6uvx6gSw6Z/J5tDPYIlykKnXoU5WOXH5m', NULL, NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(23, 'Sheila', 'Lipton', '808-224-7688', 'sheila@kepoikai.com', NULL, 4, 'sheilal', '$2y$10$7FHdjvAVdXyvh.NMcBNwfuFkM9/B5nYWNg4sSfFw.pBjOSkmoXrXi', NULL, NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `vessels`
--

CREATE TABLE `vessels` (
  `vessel_id` int NOT NULL,
  `vesselName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `vesselON` varchar(9) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `hailingPort` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `callSign` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `mmsi` int DEFAULT NULL,
  `epirbHexId` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `hin` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `photo_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `vesselClass` enum('Passenger Vessel','Towing Vessel','Cargo Vessel','') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `classType` enum('Excursion','Recreational Dive','Parasail','Fishing Charter') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `vesselService` enum('Inspected Passenger','Uninspected Passenger','') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `grossTons` decimal(11,1) NOT NULL,
  `netTons` decimal(11,1) DEFAULT NULL,
  `lightshipTons` decimal(11,1) DEFAULT NULL,
  `length` decimal(11,1) NOT NULL,
  `lbp` decimal(11,1) UNSIGNED DEFAULT NULL,
  `propulsionType` enum('Diesel - Inboard','Diesel - Outboard','Gasoline - Inboard','Gasoline - Outboard','Electric') COLLATE utf8mb4_general_ci DEFAULT NULL,
  `auxSail` tinyint(1) DEFAULT NULL,
  `horsepower` int NOT NULL,
  `inspSubChapter` enum('T','K','L','I','M','R','U') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `sip` tinyint(1) DEFAULT NULL,
  `keelLaidDate` date NOT NULL,
  `deliveryDate` date NOT NULL,
  `master` int NOT NULL,
  `deckhands` int DEFAULT NULL,
  `othersInCrew` int DEFAULT NULL,
  `personInAddition` int DEFAULT NULL,
  `passengers` int DEFAULT NULL,
  `pob` int DEFAULT NULL,
  `route` enum('Rivers','Lakes, Bays, and Sounds','Limited Coastwise','Coastwise','Oceans') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `waters` enum('Protected','Partially Protected','Exposed','') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `hullMaterial` enum('FRP - Fire Retardant','FRP - Non Fire-Retardant','Aluminum','Steel','Wood - Sheathed','Wood - Plank on Frame') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `lastInspection` date DEFAULT NULL,
  `lastDrydock` date DEFAULT NULL,
  `nextDrydock` date DEFAULT NULL,
  `nextUnstep` date DEFAULT NULL,
  `nextScheduledInspection` date DEFAULT NULL,
  `company_id` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `vessels`
--

INSERT INTO `vessels` (`vessel_id`, `vesselName`, `vesselON`, `hailingPort`, `callSign`, `mmsi`, `epirbHexId`, `hin`, `photo_path`, `vesselClass`, `classType`, `vesselService`, `grossTons`, `netTons`, `lightshipTons`, `length`, `lbp`, `propulsionType`, `auxSail`, `horsepower`, `inspSubChapter`, `sip`, `keelLaidDate`, `deliveryDate`, `master`, `deckhands`, `othersInCrew`, `personInAddition`, `passengers`, `pob`, `route`, `waters`, `hullMaterial`, `lastInspection`, `lastDrydock`, `nextDrydock`, `nextUnstep`, `nextScheduledInspection`, `company_id`) VALUES
(1, 'Anna is the bestest', '123456789', 'Kapaa', 'abc123456789', 987654321, 'abcd1234ffbff', 'test05262025', 'uploads/vessels/vessel_6801dd6f91974.jpeg', 'Passenger Vessel', 'Excursion', 'Inspected Passenger', 6.0, 6.0, 99.0, 30.0, 26.0, 'Diesel - Inboard', 0, 777, 'T', 0, '2025-04-01', '2025-04-01', 1, 1, 0, 0, 16, NULL, 'Oceans', 'Exposed', 'FRP - Fire Retardant', '2025-05-26', '2025-04-05', '2027-04-30', '2025-04-28', NULL, 1),
(7, 'Test Vessel', '111222333', 'Waimea', 'ABC123', 999888777, 'ABCDE 12345 FFBFF', 'kasldfj;las', 'uploads/vessels/vessel_6804043548458.jpeg', 'Passenger Vessel', 'Excursion', 'Inspected Passenger', 55.0, 99.0, 55.0, 100.0, 100.0, 'Diesel - Inboard', NULL, 1000, 'T', NULL, '2017-01-18', '2017-12-20', 1, 3, 5, 0, 149, 0, 'Oceans', 'Exposed', 'Aluminum', '2025-04-07', '2025-04-07', '2027-03-31', '2031-04-30', '2025-09-23', 1),
(8, 'Anna', '12353', 'dkashfgjf', 'lasdfj', 293842309, '2809', '3409580', NULL, 'Passenger Vessel', 'Excursion', 'Inspected Passenger', 30.0, 30.0, 30.0, 30.0, 30.0, 'Diesel - Inboard', 1, 30, 'T', 1, '2025-04-01', '2025-05-01', 1, 2, 0, 0, 49, 52, 'Limited Coastwise', 'Exposed', 'FRP - Fire Retardant', '2024-04-01', '2025-04-01', '2025-08-01', '2025-05-01', NULL, 1),
(9, 'Billy', '1323415', 'Kikiaola', 'WDM818', 368240370, '2DCCAA97ECFFBFF', 'Test HIN', 'uploads/1745907036_Billy2.jpeg', 'Passenger Vessel', 'Excursion', 'Inspected Passenger', 11.0, 11.0, 4.6, 34.0, 34.0, 'Gasoline - Inboard', NULL, 600, 'T', NULL, '2020-09-10', '2022-03-04', 1, 1, 0, 0, 18, 20, 'Limited Coastwise', 'Exposed', 'Aluminum', '2025-03-14', '2024-04-27', '2026-04-30', NULL, NULL, 2),
(10, 'SEIKO', '1302452', 'Kikiaola', 'WDL8264', 368167890, '2DCC9FB30CFFBFF', 'ACO6269CL919', 'uploads/1746303664_PortForward.jpeg', 'Passenger Vessel', 'Excursion', 'Inspected Passenger', 11.0, 11.0, 0.0, 34.0, 0.0, 'Gasoline - Outboard', 0, 600, 'T', 0, '2019-07-31', '2021-02-10', 1, NULL, NULL, NULL, NULL, NULL, 'Limited Coastwise', 'Exposed', 'Aluminum', '2025-03-14', '2024-04-09', '2026-04-30', NULL, NULL, 2),
(11, 'AMELIA K', '1277287', 'Port Allen', 'WDM8413', 368240590, '2DCC901822FFBFF', NULL, NULL, 'Passenger Vessel', 'Excursion', 'Inspected Passenger', 26.0, 26.0, NULL, 39.0, 39.0, 'Gasoline - Outboard', 0, 1200, 'T', 0, '2016-11-15', '2017-05-12', 1, 2, NULL, NULL, 49, 52, 'Limited Coastwise', 'Exposed', 'Aluminum', '2025-03-14', '2024-06-09', '2026-06-30', NULL, NULL, 2),
(12, 'HAWAII ECO', '978116', 'Ala Wai', 'WDJ8173', 368013830, '2DCC9FE0E6FFBFF', '—', 'uploads/1746392484_IMG_7054.jpg', 'Passenger Vessel', 'Excursion', 'Inspected Passenger', 11.0, 8.0, 0.0, 46.0, 46.0, 'Diesel - Inboard', 0, 400, 'T', 0, '1990-06-22', '1991-08-02', 1, 2, NULL, NULL, 49, 52, 'Limited Coastwise', 'Partially Protected', 'FRP - Non Fire-Retardant', '2024-12-05', '2024-04-11', '2026-04-30', NULL, NULL, 11),
(13, 'Olohana', '1348576', 'Kawaihae', 'WDQ2303', 368406570, '2DDAA F91BE BFDFF', '—', NULL, 'Passenger Vessel', 'Excursion', 'Inspected Passenger', 12.0, 12.0, NULL, 41.0, NULL, 'Gasoline - Outboard', 0, 900, 'T', 0, '2024-06-20', '2025-02-28', 1, 1, NULL, NULL, 40, 42, 'Limited Coastwise', 'Exposed', 'Aluminum', '2025-03-07', '2025-03-07', '2027-03-31', NULL, '2025-05-26', 12),
(14, 'Spirit of Aloha', '1284381', 'Honolulu', 'WDJ9547', 368027150, '2DDAA F8252 3FDFF', 'GCY124', NULL, 'Passenger Vessel', 'Excursion', 'Inspected Passenger', 20.0, 18.0, NULL, 64.7, NULL, 'Diesel - Inboard', 1, 500, 'T', 0, '2017-05-01', '2018-04-24', 1, 2, 5, NULL, 80, 88, 'Limited Coastwise', 'Exposed', 'FRP - Non Fire-Retardant', '2025-05-28', '2023-10-20', '2025-10-31', '2029-10-31', NULL, 12),
(15, 'Kealoha', '1047753', 'Ala Wai', 'WDL3813', 368125250, '2DCC8118EEFFBFF', NULL, NULL, 'Passenger Vessel', 'Excursion', 'Inspected Passenger', 45.0, 45.0, NULL, 54.0, NULL, 'Diesel - Inboard', 1, 300, 'T', 0, '1996-03-03', '1996-09-18', 1, 2, NULL, NULL, 64, 67, 'Limited Coastwise', 'Exposed', 'FRP - Fire Retardant', '2025-01-05', '2024-11-18', '2026-11-30', '2026-09-30', NULL, 11),
(16, 'KULEA', '1346617', 'Waimea, HI', 'WDP8314', 368389370, '2ddaadb074bfdff', NULL, NULL, 'Passenger Vessel', 'Excursion', 'Inspected Passenger', 6.0, 6.0, NULL, 34.8, NULL, 'Gasoline - Outboard', 0, 600, 'T', 0, '2025-01-31', '2025-01-31', 1, 1, NULL, NULL, 18, 20, 'Limited Coastwise', 'Exposed', 'Aluminum', NULL, NULL, NULL, NULL, NULL, 8),
(17, 'Kepoikai II', '579840', 'Kewalo', 'WBQ7543', 367337210, '2DCCA D7D04 FFBFF', '—', 'uploads/1748300470_Kepoikai16.jpeg', 'Passenger Vessel', 'Excursion', 'Inspected Passenger', 5.0, 5.0, NULL, 40.3, NULL, 'Gasoline - Outboard', 1, 100, 'T', 0, '1976-10-22', '1977-04-29', 1, 2, NULL, NULL, 37, 40, 'Limited Coastwise', 'Partially Protected', 'FRP - Non Fire-Retardant', '2025-05-22', '2025-05-14', '2027-05-31', '2029-05-31', NULL, 4);

-- --------------------------------------------------------

--
-- Table structure for table `vessel_crew`
--

CREATE TABLE `vessel_crew` (
  `id` int NOT NULL,
  `vessel_id` int DEFAULT NULL,
  `crew_id` int DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `position_onboard` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `role` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `assigned_on` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `vessel_crew`
--

INSERT INTO `vessel_crew` (`id`, `vessel_id`, `crew_id`, `start_date`, `end_date`, `position_onboard`, `role`, `assigned_on`) VALUES
(4, 7, 6, NULL, NULL, NULL, 'tester', '2025-04-09'),
(6, 7, 6, NULL, NULL, NULL, 'test', '2025-04-09'),
(9, 7, 2, NULL, NULL, NULL, '', '2025-04-09'),
(11, 7, 1, NULL, NULL, NULL, '', '2025-04-19'),
(12, 1, 1, NULL, NULL, NULL, '', '2025-05-26');

-- --------------------------------------------------------

--
-- Table structure for table `vessel_documents`
--

CREATE TABLE `vessel_documents` (
  `id` int NOT NULL,
  `vessel_id` int NOT NULL,
  `docType` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `docName` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `issueDate` date DEFAULT NULL,
  `expDate` date DEFAULT NULL,
  `file_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vessel_icrs`
--

CREATE TABLE `vessel_icrs` (
  `vessel_icr_id` int NOT NULL,
  `vessel_id` int NOT NULL,
  `icr_id` int NOT NULL,
  `icr_number` varchar(10) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `category_id` int DEFAULT NULL,
  `type_id` int DEFAULT NULL,
  `frequency` enum('Annually','Monthly','Quarterly','Weekly') DEFAULT NULL,
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `vessel_icrs`
--

INSERT INTO `vessel_icrs` (`vessel_icr_id`, `vessel_id`, `icr_id`, `icr_number`, `title`, `category_id`, `type_id`, `frequency`, `notes`, `created_at`, `updated_at`) VALUES
(134, 10, 5, 'A 01', 'Paperwork, required documents', NULL, NULL, 'Annually', NULL, '2025-05-03 20:21:42', '2025-05-03 20:21:42'),
(135, 10, 6, 'A 02', 'Paperwork, Required publications', NULL, NULL, 'Annually', NULL, '2025-05-03 20:21:42', '2025-05-03 20:21:42'),
(136, 10, 7, 'A 03', 'Paperwork, Service Reports', NULL, NULL, 'Annually', NULL, '2025-05-03 20:21:42', '2025-05-03 20:21:42'),
(137, 10, 8, 'B 01', 'Lifesaving Equipment, Life preservers and storage', NULL, NULL, 'Quarterly', NULL, '2025-05-03 20:21:42', '2025-05-03 20:21:42'),
(138, 10, 9, 'B 02', 'Lifesaving equipment, Ring buoys', NULL, NULL, 'Monthly', NULL, '2025-05-03 20:21:42', '2025-05-03 20:21:42'),
(139, 10, 10, 'B 04', 'Lifesaving equipment, life floats', NULL, NULL, 'Annually', NULL, '2025-05-03 20:21:43', '2025-05-03 20:21:43'),
(140, 10, 11, 'C 01', 'Fire Protection Equipment, Fixed CO2 System', NULL, NULL, 'Annually', NULL, '2025-05-03 20:21:43', '2025-05-03 20:21:43'),
(141, 10, 12, 'C 02', 'Fire Protection Equipment, Clean Agent System (Fireboy)', NULL, NULL, 'Annually', NULL, '2025-05-03 20:21:43', '2025-05-03 20:21:43'),
(142, 10, 13, 'C 04', 'Fire Protection Equipment, Portable Fire Extinguishers', NULL, NULL, 'Annually', NULL, '2025-05-03 20:21:43', '2025-05-03 20:21:43'),
(153, 9, 5, 'A 01', 'Paperwork, required documents', NULL, NULL, 'Annually', NULL, '2025-05-05 01:31:58', '2025-05-05 01:31:58'),
(154, 9, 6, 'A 02', 'Paperwork, Required publications', NULL, NULL, 'Annually', NULL, '2025-05-05 01:31:58', '2025-05-05 01:31:58'),
(155, 9, 7, 'A 03', 'Paperwork, Service Reports', NULL, NULL, 'Annually', NULL, '2025-05-05 01:31:58', '2025-05-05 01:31:58'),
(156, 9, 8, 'B 01', 'Lifesaving Equipment, Life preservers and storage', NULL, NULL, 'Quarterly', NULL, '2025-05-05 01:31:58', '2025-05-05 01:31:58'),
(157, 9, 9, 'B 02', 'Lifesaving equipment, Ring buoys', NULL, NULL, 'Monthly', NULL, '2025-05-05 01:31:58', '2025-05-05 01:31:58'),
(158, 9, 10, 'B 04', 'Lifesaving equipment, life floats', NULL, NULL, 'Annually', NULL, '2025-05-05 01:31:58', '2025-05-05 01:31:58'),
(159, 9, 11, 'C 01', 'Fire Protection Equipment, Fixed CO2 System', NULL, NULL, 'Annually', NULL, '2025-05-05 01:31:58', '2025-05-05 01:31:58'),
(160, 9, 12, 'C 02', 'Fire Protection Equipment, Clean Agent System (Fireboy)', NULL, NULL, 'Annually', NULL, '2025-05-05 01:31:59', '2025-05-05 01:31:59'),
(161, 9, 13, 'C 04', 'Fire Protection Equipment, Portable Fire Extinguishers', NULL, NULL, 'Annually', NULL, '2025-05-05 01:31:59', '2025-05-05 01:31:59'),
(162, 9, 14, 'C 05', 'Fire Protection Equipment, Fire Main System', NULL, NULL, 'Annually', NULL, '2025-05-05 01:31:59', '2025-05-05 01:31:59'),
(163, 9, 15, 'C 06', 'Fire protection equipment, fire detection system', NULL, NULL, 'Annually', NULL, '2025-05-05 01:31:59', '2025-05-05 01:31:59'),
(164, 9, 16, 'C 07', 'Fire protection equipment, fire dampers and remote shutdowns', NULL, NULL, 'Annually', NULL, '2025-05-05 01:31:59', '2025-05-05 01:31:59'),
(165, 9, 18, 'C 11', 'Fire Protection Equipment, Fire Bucket', NULL, NULL, 'Quarterly', NULL, '2025-05-05 01:31:59', '2025-05-05 01:31:59'),
(166, 13, 5, 'A 01', 'Paperwork, required documents', NULL, NULL, 'Annually', NULL, '2025-05-08 01:52:02', '2025-05-08 01:52:02'),
(167, 13, 6, 'A 02', 'Paperwork, Required publications', NULL, NULL, 'Annually', NULL, '2025-05-08 01:52:02', '2025-05-08 01:52:02'),
(168, 13, 7, 'A 03', 'Paperwork, Service Reports', NULL, NULL, 'Annually', NULL, '2025-05-08 01:52:02', '2025-05-08 01:52:02'),
(169, 13, 8, 'B 01', 'Lifesaving Equipment, Life preservers and storage', NULL, NULL, 'Quarterly', NULL, '2025-05-08 01:52:02', '2025-05-08 01:52:02'),
(170, 13, 9, 'B 02', 'Lifesaving equipment, Ring buoys', NULL, NULL, 'Monthly', NULL, '2025-05-08 01:52:02', '2025-05-08 01:52:02'),
(171, 13, 10, 'B 04', 'Lifesaving equipment, life floats', NULL, NULL, 'Annually', NULL, '2025-05-08 01:52:02', '2025-05-08 01:52:02'),
(172, 13, 12, 'C 02', 'Fire Protection Equipment, Clean Agent System (Fireboy)', NULL, NULL, 'Annually', NULL, '2025-05-08 01:52:02', '2025-05-08 01:52:02'),
(173, 13, 13, 'C 04', 'Fire Protection Equipment, Portable Fire Extinguishers', NULL, NULL, 'Annually', NULL, '2025-05-08 01:52:02', '2025-05-08 01:52:02'),
(174, 13, 16, 'C 07', 'Fire protection equipment, fire dampers and remote shutdowns', NULL, NULL, 'Annually', NULL, '2025-05-08 01:52:02', '2025-05-08 01:52:02'),
(175, 13, 18, 'C 11', 'Fire Protection Equipment, Fire Bucket', NULL, NULL, 'Quarterly', NULL, '2025-05-08 01:52:02', '2025-05-08 01:52:02'),
(176, 13, 19, 'E 01', 'Emergency Equipment, EPRIB', NULL, NULL, 'Monthly', NULL, '2025-05-08 01:52:02', '2025-05-08 01:52:02'),
(177, 13, 20, 'E 02', 'Emergency Equipment', NULL, NULL, 'Annually', NULL, '2025-05-08 01:52:02', '2025-05-08 01:52:02'),
(178, 13, 21, 'E 03', 'Emergency Equipment, Distress Signals (Flares)', NULL, NULL, 'Quarterly', NULL, '2025-05-08 01:52:02', '2025-05-08 01:52:02'),
(179, 13, 23, 'E 05', 'First Aid, Medical kit', NULL, NULL, 'Annually', NULL, '2025-05-08 01:52:02', '2025-05-08 01:52:02'),
(180, 13, 24, 'F 01', 'Ventillation, Ventillation shutdown', NULL, NULL, 'Annually', NULL, '2025-05-08 01:52:03', '2025-05-08 01:52:03'),
(181, 13, 25, 'F 02', 'Ventillation, Fuel tank vents', NULL, NULL, 'Annually', NULL, '2025-05-08 01:52:03', '2025-05-08 01:52:03'),
(182, 13, 26, 'F 03', 'Ventillation, Void and water tanks', NULL, NULL, 'Annually', NULL, '2025-05-08 01:52:03', '2025-05-08 01:52:03'),
(183, 13, 29, 'G 02', 'Navigation Equipment, Magnetic Compass', NULL, NULL, 'Annually', NULL, '2025-05-08 01:52:03', '2025-05-08 01:52:03'),
(184, 13, 30, 'G 04', 'Navigation Equipment, Radio', NULL, NULL, 'Annually', NULL, '2025-05-08 01:52:03', '2025-05-08 01:52:03'),
(185, 13, 31, 'G 05', 'Navigation equipment, navigation lights', NULL, NULL, 'Monthly', NULL, '2025-05-08 01:52:03', '2025-05-08 01:52:03'),
(186, 13, 33, 'G 07', 'Navigation equipment, charts and publications', NULL, NULL, 'Annually', NULL, '2025-05-08 01:52:03', '2025-05-08 01:52:03'),
(187, 13, 34, 'G 08', 'Navigation equipment, day shapes and whistle', NULL, NULL, 'Annually', NULL, '2025-05-08 01:52:03', '2025-05-08 01:52:03'),
(188, 13, 35, 'G 09', 'Navigation equipment, Electronic positioning equipment, (GPS)', NULL, NULL, 'Annually', NULL, '2025-05-08 01:52:03', '2025-05-08 01:52:03'),
(189, 13, 36, 'H 01', 'Ground tackle, anchor system', NULL, NULL, 'Annually', NULL, '2025-05-08 01:52:03', '2025-05-08 01:52:03'),
(190, 13, 37, 'H 02', 'Ground tackle, bitts, cleats, and fairleads', NULL, NULL, 'Annually', NULL, '2025-05-08 01:52:03', '2025-05-08 01:52:03'),
(191, 13, 38, 'I 02', 'Hull, decks, fittings, & watertight integrity, Watertight bulkheads', NULL, NULL, 'Annually', NULL, '2025-05-08 01:52:03', '2025-05-08 01:52:03'),
(192, 13, 39, 'I 03', 'Hull, Decks, Fittings, & Watertight Integrity, Stuffing tubes and sealants', NULL, NULL, 'Annually', NULL, '2025-05-08 01:52:03', '2025-05-08 01:52:03'),
(193, 13, 40, 'I 04', 'Hull, Decks, Fittings, & Watertight Integrity, Remote operated valves and controls', NULL, NULL, 'Quarterly', NULL, '2025-05-08 01:52:03', '2025-05-08 01:52:03'),
(194, 14, 13, 'C 04', 'Fire Protection Equipment, Portable Fire Extinguishers', NULL, NULL, 'Annually', NULL, '2025-05-16 17:01:08', '2025-05-16 17:01:08'),
(195, 17, 5, 'A 01', 'Paperwork, required documents', NULL, NULL, 'Annually', NULL, '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(196, 17, 6, 'A 02', 'Paperwork, Required publications', NULL, NULL, 'Annually', NULL, '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(197, 17, 7, 'A 03', 'Paperwork, Service Reports', NULL, NULL, 'Annually', NULL, '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(198, 17, 8, 'B 01', 'Lifesaving Equipment, Life preservers and storage', NULL, NULL, 'Quarterly', NULL, '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(199, 17, 9, 'B 02', 'Lifesaving equipment, Ring buoys', NULL, NULL, 'Monthly', NULL, '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(200, 17, 13, 'C 04', 'Fire Protection Equipment, Portable Fire Extinguishers', NULL, NULL, 'Monthly', NULL, '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(201, 17, 18, 'C 11', 'Fire Protection Equipment, Fire Bucket', NULL, NULL, 'Quarterly', NULL, '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(202, 17, 19, 'E 01', 'Emergency Equipment, EPRIB', NULL, NULL, 'Monthly', NULL, '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(203, 17, 21, 'E 03', 'Emergency Equipment, Distress Signals (Flares)', NULL, NULL, 'Quarterly', NULL, '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(204, 17, 23, 'E 05', 'First Aid, Medical kit', NULL, NULL, 'Annually', NULL, '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(205, 17, 29, 'G 02', 'Navigation Equipment, Magnetic Compass', NULL, NULL, 'Annually', NULL, '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(206, 17, 30, 'G 04', 'Navigation Equipment, Radio', NULL, NULL, 'Annually', NULL, '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(207, 17, 31, 'G 05', 'Navigation equipment, navigation lights', NULL, NULL, 'Monthly', NULL, '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(208, 17, 32, 'G 06', 'Navigation equipment, Internal communications and control systems', NULL, NULL, 'Annually', NULL, '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(209, 17, 33, 'G 07', 'Navigation equipment, charts and publications', NULL, NULL, 'Annually', NULL, '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(210, 17, 34, 'G 08', 'Navigation equipment, day shapes and whistle', NULL, NULL, 'Annually', NULL, '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(211, 17, 36, 'H 01', 'Ground tackle, anchor system', NULL, NULL, 'Annually', NULL, '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(212, 17, 37, 'H 02', 'Ground tackle, bitts, cleats, and fairleads', NULL, NULL, 'Annually', NULL, '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(213, 17, 39, 'I 03', 'Hull, Decks, Fittings, & Watertight Integrity, Stuffing tubes and sealants', NULL, NULL, 'Annually', NULL, '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(214, 17, 40, 'I 04', 'Hull, Decks, Fittings, & Watertight Integrity, Remote operated valves and controls', NULL, NULL, 'Quarterly', NULL, '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(215, 17, 41, 'I 05', 'HULL, DECKS, FITTINGS &  WATERTIGHT INTEGRITY; HULL AND DECK OPENINGS', NULL, NULL, 'Annually', NULL, '2025-05-27 04:19:20', '2025-05-27 04:19:20');

-- --------------------------------------------------------

--
-- Table structure for table `vessel_icr_runs`
--

CREATE TABLE `vessel_icr_runs` (
  `run_id` int NOT NULL,
  `vessel_id` int DEFAULT NULL,
  `icr_id` int NOT NULL,
  `vessel_icr_id` int DEFAULT NULL,
  `run_date` date NOT NULL,
  `inspector` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `vessel_icr_runs`
--

INSERT INTO `vessel_icr_runs` (`run_id`, `vessel_id`, `icr_id`, `vessel_icr_id`, `run_date`, `inspector`, `notes`) VALUES
(15, 9, 5, 143, '2025-05-04', 'admin', NULL),
(16, 9, 6, 144, '2025-05-04', 'admin', NULL),
(17, 9, 7, 145, '2025-05-04', 'admin', NULL),
(18, 9, 8, 149, '2025-05-04', 'admin', NULL),
(19, 9, 9, 150, '2025-05-04', 'admin', NULL),
(20, 9, 10, 151, '2025-05-04', 'admin', NULL),
(21, 9, 11, 152, '2025-05-04', 'admin', NULL),
(22, 9, 9, 150, '2025-05-04', 'admin', NULL),
(23, 9, 9, 150, '2025-05-04', 'admin', NULL),
(24, 9, 8, 149, '2025-05-04', 'admin', NULL),
(25, 9, 12, 160, '2025-05-05', 'admin', NULL),
(26, 9, 13, 161, '2025-05-06', 'admin', NULL),
(27, 9, 14, 162, '2025-05-06', 'admin', NULL),
(28, 9, 15, 163, '2025-05-06', 'admin', NULL),
(29, 9, 16, 164, '2025-05-06', 'admin', NULL),
(30, 9, 18, 165, '2025-05-08', 'admin', NULL),
(31, 9, 9, 157, '2025-05-08', 'admin', NULL),
(32, 13, 5, 166, '2025-05-08', 'admin', NULL),
(33, 13, 6, 167, '2025-05-08', 'admin', NULL),
(34, 9, 18, 165, '2025-05-13', 'admin', NULL),
(35, 9, 9, 157, '2025-05-22', 'admin', NULL),
(36, 17, 5, 195, '2025-05-27', 'admin', NULL),
(37, 17, 5, 195, '2025-05-27', 'admin', NULL),
(38, 17, 6, 196, '2025-05-27', 'admin', NULL),
(39, 17, 7, 197, '2025-05-27', 'admin', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `vessel_icr_steps`
--

CREATE TABLE `vessel_icr_steps` (
  `step_id` int NOT NULL,
  `vessel_icr_id` int NOT NULL,
  `step_number` int DEFAULT NULL,
  `step_description` text,
  `deficiency_action` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `vessel_icr_steps`
--

INSERT INTO `vessel_icr_steps` (`step_id`, `vessel_icr_id`, `step_number`, `step_description`, `deficiency_action`, `created_at`, `updated_at`) VALUES
(1, 122, 1, 'Fire extinguishing equipment servicing', '', '2025-04-26 22:38:07', '2025-04-26 22:38:07'),
(2, 123, 1, 'Retroreflective material on both sides, at least 31 sq. inches on each side.', '', '2025-04-26 22:38:07', '2025-04-26 22:38:07'),
(3, 123, 2, 'Type I, CG Approved', '', '2025-04-26 22:38:07', '2025-04-26 22:38:07'),
(4, 123, 3, 'Verify PFD lights work. If chemical type, check expiration date. If battery type, \r\ncheck battery expiration date, lens and seal.', '', '2025-04-26 22:38:07', '2025-04-26 22:38:07'),
(5, 123, 4, 'Vessel name clearly labeled on each PFD.', '', '2025-04-26 22:38:07', '2025-04-26 22:38:07'),
(6, 123, 5, 'Check straps, snaps, jacket fabric for signs of wear, deterioration.', '', '2025-04-26 22:38:07', '2025-04-26 22:38:07'),
(7, 123, 6, 'Stowed in proper location & labeled.', '', '2025-04-26 22:38:07', '2025-04-26 22:38:07'),
(8, 123, 7, 'Wearing instructions posted.', '', '2025-04-26 22:38:07', '2025-04-26 22:38:07'),
(9, 123, 8, 'Adequate number on board. 1 for every person allowed by the COI. \r\n• 10% of total is required to be children\'s PFD\'s or \r\n•  5%, were all extended size PFDs are used on board; unless adult \r\npassengers only', '', '2025-04-26 22:38:07', '2025-04-26 22:38:07'),
(10, 123, 9, 'Stowage location properly labeled.', '', '2025-04-26 22:38:07', '2025-04-26 22:38:07'),
(11, 123, 10, 'Child and adult jackets are stowed separately.', '', '2025-04-26 22:38:07', '2025-04-26 22:38:07'),
(12, 124, 1, 'Verify proper size onboard: 20\" for vessel less than 26’ or 24” for all others', '', '2025-04-26 22:38:07', '2025-04-26 22:38:07'),
(13, 124, 2, 'Verify free of cracks and weathering.', '', '2025-04-26 22:38:07', '2025-04-26 22:38:07'),
(14, 124, 3, 'Vessel name stenciled on each.', '', '2025-04-26 22:38:07', '2025-04-26 22:38:07'),
(15, 124, 4, 'Proper number onboard. Total count of all including those with lights and lines.', '', '2025-04-26 22:38:07', '2025-04-26 22:38:07'),
(16, 124, 5, 'Ensure properly mounted in racks for easy deployment.', '', '2025-04-26 22:38:07', '2025-04-26 22:38:07'),
(17, 124, 6, 'Check operation of attached waterlights. Check battery expiration date and replace as \r\nnecessary.', '', '2025-04-26 22:38:07', '2025-04-26 22:38:07'),
(18, 125, 1, 'Certificate of Inspection', '', '2025-04-27 01:19:54', '2025-04-27 01:19:54'),
(19, 125, 2, 'FCC Station License', '', '2025-04-27 01:19:54', '2025-04-27 01:19:54'),
(20, 125, 3, 'FCC Safety Radio Certificate', '', '2025-04-27 01:19:54', '2025-04-27 01:19:54'),
(21, 125, 4, 'Stability Letter', '', '2025-04-27 01:19:54', '2025-04-27 01:19:54'),
(22, 125, 5, 'Certificate of Documentation or Certificate of Numbers issued by the State ', '', '2025-04-27 01:19:54', '2025-04-27 01:19:54'),
(23, 125, 6, 'Master\'s License', '', '2025-04-27 01:19:54', '2025-04-27 01:19:54'),
(24, 126, 1, 'Navigation Rules', '', '2025-04-27 01:19:54', '2025-04-27 01:19:54'),
(25, 126, 2, 'Coast Pilot', '', '2025-04-27 01:19:54', '2025-04-27 01:19:54'),
(26, 126, 3, 'Charts', '', '2025-04-27 01:19:54', '2025-04-27 01:19:54'),
(27, 126, 4, 'Notice to Mariners', '', '2025-04-27 01:19:54', '2025-04-27 01:19:54'),
(28, 126, 5, 'Tide tables', '', '2025-04-27 01:19:54', '2025-04-27 01:19:54'),
(29, 126, 6, 'Current Tables', '', '2025-04-27 01:19:54', '2025-04-27 01:19:54'),
(30, 126, 7, 'Light Lists', '', '2025-04-27 01:19:54', '2025-04-27 01:19:54'),
(31, 127, 1, 'Fire extinguishing equipment servicing', '', '2025-04-27 01:19:54', '2025-04-27 01:19:54'),
(32, 128, 1, 'Retroreflective material on both sides, at least 31 sq. inches on each side.', '', '2025-04-27 01:19:54', '2025-04-27 01:19:54'),
(33, 128, 2, 'Type I, CG Approved', '', '2025-04-27 01:19:54', '2025-04-27 01:19:54'),
(34, 128, 3, 'Verify PFD lights work. If chemical type, check expiration date. If battery type, \r\ncheck battery expiration date, lens and seal.', '', '2025-04-27 01:19:54', '2025-04-27 01:19:54'),
(35, 128, 4, 'Vessel name clearly labeled on each PFD.', '', '2025-04-27 01:19:54', '2025-04-27 01:19:54'),
(36, 128, 5, 'Check straps, snaps, jacket fabric for signs of wear, deterioration.', '', '2025-04-27 01:19:54', '2025-04-27 01:19:54'),
(37, 128, 6, 'Stowed in proper location & labeled.', '', '2025-04-27 01:19:54', '2025-04-27 01:19:54'),
(38, 128, 7, 'Wearing instructions posted.', '', '2025-04-27 01:19:54', '2025-04-27 01:19:54'),
(39, 128, 8, 'Adequate number on board. 1 for every person allowed by the COI. \r\n• 10% of total is required to be children\'s PFD\'s or \r\n•  5%, were all extended size PFDs are used on board; unless adult \r\npassengers only', '', '2025-04-27 01:19:54', '2025-04-27 01:19:54'),
(40, 128, 9, 'Stowage location properly labeled.', '', '2025-04-27 01:19:54', '2025-04-27 01:19:54'),
(41, 128, 10, 'Child and adult jackets are stowed separately.', '', '2025-04-27 01:19:54', '2025-04-27 01:19:54'),
(42, 129, 1, 'Verify proper size onboard: 20\" for vessel less than 26’ or 24” for all others', '', '2025-04-27 01:19:54', '2025-04-27 01:19:54'),
(43, 129, 2, 'Verify free of cracks and weathering.', '', '2025-04-27 01:19:54', '2025-04-27 01:19:54'),
(44, 129, 3, 'Vessel name stenciled on each.', '', '2025-04-27 01:19:54', '2025-04-27 01:19:54'),
(45, 129, 4, 'Proper number onboard. Total count of all including those with lights and lines.', '', '2025-04-27 01:19:54', '2025-04-27 01:19:54'),
(46, 129, 5, 'Ensure properly mounted in racks for easy deployment.', '', '2025-04-27 01:19:54', '2025-04-27 01:19:54'),
(47, 129, 6, 'Check operation of attached waterlights. Check battery expiration date and replace as \r\nnecessary.', '', '2025-04-27 01:19:54', '2025-04-27 01:19:54'),
(48, 130, 1, 'Correct number & capacity in accordance with COI', '', '2025-04-27 01:19:54', '2025-04-27 01:19:54'),
(49, 130, 2, 'Stowed in tiers no more than 4 high. When stowed in tiers, spacers installed between \r\neach life float or buoyant apparatus.', '', '2025-04-27 01:19:54', '2025-04-27 01:19:54'),
(50, 130, 3, 'Stowage is such that units will float free. Acceptable weak link is attached. ', '', '2025-04-27 01:19:54', '2025-04-27 01:19:54'),
(51, 130, 4, 'Painter is in good condition, secured to float and weak link. Weak link is attached to \r\ndeck.', '', '2025-04-27 01:19:54', '2025-04-27 01:19:54'),
(52, 130, 5, 'Stenciled with vessel name in 3” letters and total capacity in 1.5\" letters. ', '', '2025-04-27 01:19:54', '2025-04-27 01:19:54'),
(53, 130, 6, 'Body of unit is in good condition, life lines and netting are in serviceable condition. ', '', '2025-04-27 01:19:54', '2025-04-27 01:19:54'),
(54, 130, 7, 'Each lifefloat shall be equipped with 2 paddles, water light, lifeline, pendents and a \r\npainter. Each buoyant apparatus shall be fitted with a water light, lifeline, pendents and \r\na painter.', '', '2025-04-27 01:19:54', '2025-04-27 01:19:54'),
(55, 131, 1, 'Servicing report current; within last year. All cylinders & flexible loops (12 yrs) within \r\nhydro requirement.', '', '2025-04-27 01:19:54', '2025-04-27 01:19:54'),
(56, 131, 2, 'Diffusers are clear of obstructions.', '', '2025-04-27 01:19:54', '2025-04-27 01:19:54'),
(57, 131, 3, 'Alarms in protected spaces are labeled, warning labels posted.', '', '2025-04-27 01:19:54', '2025-04-27 01:19:54'),
(58, 131, 4, 'Cable pulls are marked.', '', '2025-04-27 01:19:54', '2025-04-27 01:19:54'),
(59, 131, 5, 'Instructions are posted.', '', '2025-04-27 01:19:54', '2025-04-27 01:19:54'),
(60, 131, 6, 'Cylinder brackets fixed and in good condition. ', '', '2025-04-27 01:19:54', '2025-04-27 01:19:54'),
(61, 131, 7, 'Cylinders free of corrosion. ', '', '2025-04-27 01:19:54', '2025-04-27 01:19:54'),
(62, 131, 8, 'Closure for protected spaces; provided; conduct operational test.', '', '2025-04-27 01:19:55', '2025-04-27 01:19:55'),
(63, 131, 9, 'Ventilation and engine shutdowns operational. ', '', '2025-04-27 01:19:55', '2025-04-27 01:19:55'),
(64, 131, 10, 'Witness operational test of system by servicing company.', '', '2025-04-27 01:19:55', '2025-04-27 01:19:55'),
(65, 132, 1, 'Approved type, mounted in approved bracket. ', '', '2025-04-27 01:19:55', '2025-04-27 01:19:55'),
(66, 132, 2, 'Cylinder corrosion free. ', '', '2025-04-27 01:19:55', '2025-04-27 01:19:55'),
(67, 132, 3, 'Discharge hose is flexible; no signs of wear, deterioration; discharge nozzle intact. ', '', '2025-04-27 01:19:55', '2025-04-27 01:19:55'),
(68, 132, 4, 'Hydro test dates current: 5 yrs for CO2, 12 yrs for dry chemical. ', '', '2025-04-27 01:19:55', '2025-04-27 01:19:55'),
(69, 132, 5, 'Location in accordance with table 181.500(a).', '', '2025-04-27 01:19:55', '2025-04-27 01:19:55'),
(70, 132, 6, 'Location in accordance with table 181.500(a).', '', '2025-04-27 01:19:55', '2025-04-27 01:19:55'),
(71, 133, 1, 'operate fire pumps; operating properly? \r\n1.  No excessive leaks. \r\n2.  Foundation/ pump and motor secure. \r\n3.  Shaft bearing- no play. \r\n4.  Coupling guard in place. \r\n5.  Remote operation.\r\n\r\n\r\n\r\n', '', '2025-04-27 01:19:55', '2025-04-27 01:19:55'),
(72, 133, 2, 'All required hoses onboard, compatible threads, satisfactory condition.', '', '2025-04-27 01:19:55', '2025-04-27 01:19:55'),
(73, 133, 3, 'Fire hydrant- Hose at hydrant and attached, spanner wrench, nozzle, low velocity fog \r\napplicator where applicable. All equipment compatible. ', '', '2025-04-27 01:19:55', '2025-04-27 01:19:55'),
(74, 133, 4, 'Hoses correct length (50’) and size, based on COI.', '', '2025-04-27 01:19:55', '2025-04-27 01:19:55'),
(75, 133, 5, 'Satisfactory hydrostatic test of hoses to fire pump shutoff head pressure.', '', '2025-04-27 01:19:55', '2025-04-27 01:19:55'),
(76, 133, 6, 'Check pressure gauge on discharge side of pump to make sure it is functioning \r\nproperly. ', '', '2025-04-27 01:19:55', '2025-04-27 01:19:55'),
(77, 133, 7, 'Verify all valves at fire hydrants are operable. ', '', '2025-04-27 01:19:55', '2025-04-27 01:19:55'),
(78, 133, 8, 'Verify compatibility of equipment at each hydrant.', '', '2025-04-27 01:19:55', '2025-04-27 01:19:55'),
(79, 133, 9, 'Determine that relief valves are set properly and discharge to acceptable location if \r\ninstalled.', '', '2025-04-27 01:19:55', '2025-04-27 01:19:55'),
(80, 134, 1, 'Certificate of Inspection', '', '2025-05-03 20:21:42', '2025-05-03 20:21:42'),
(81, 134, 2, 'FCC Station License', '', '2025-05-03 20:21:42', '2025-05-03 20:21:42'),
(82, 134, 3, 'FCC Safety Radio Certificate', '', '2025-05-03 20:21:42', '2025-05-03 20:21:42'),
(83, 134, 4, 'Stability Letter', '', '2025-05-03 20:21:42', '2025-05-03 20:21:42'),
(84, 134, 5, 'Certificate of Documentation or Certificate of Numbers issued by the State ', '', '2025-05-03 20:21:42', '2025-05-03 20:21:42'),
(85, 134, 6, 'Master\'s License', '', '2025-05-03 20:21:42', '2025-05-03 20:21:42'),
(86, 135, 1, 'Navigation Rules', '', '2025-05-03 20:21:42', '2025-05-03 20:21:42'),
(87, 135, 2, 'Coast Pilot', '', '2025-05-03 20:21:42', '2025-05-03 20:21:42'),
(88, 135, 3, 'Charts', '', '2025-05-03 20:21:42', '2025-05-03 20:21:42'),
(89, 135, 4, 'Notice to Mariners', '', '2025-05-03 20:21:42', '2025-05-03 20:21:42'),
(90, 135, 5, 'Tide tables', '', '2025-05-03 20:21:42', '2025-05-03 20:21:42'),
(91, 135, 6, 'Current Tables', '', '2025-05-03 20:21:42', '2025-05-03 20:21:42'),
(92, 135, 7, 'Light Lists', '', '2025-05-03 20:21:42', '2025-05-03 20:21:42'),
(93, 136, 1, 'Fire extinguishing equipment servicing', '', '2025-05-03 20:21:42', '2025-05-03 20:21:42'),
(94, 137, 1, 'Retroreflective material on both sides, at least 31 sq. inches on each side.', '', '2025-05-03 20:21:42', '2025-05-03 20:21:42'),
(95, 137, 2, 'Type I, CG Approved', '', '2025-05-03 20:21:42', '2025-05-03 20:21:42'),
(96, 137, 3, 'Verify PFD lights work. If chemical type, check expiration date. If battery type, \r\ncheck battery expiration date, lens and seal.', '', '2025-05-03 20:21:42', '2025-05-03 20:21:42'),
(97, 137, 4, 'Vessel name clearly labeled on each PFD.', '', '2025-05-03 20:21:42', '2025-05-03 20:21:42'),
(98, 137, 5, 'Check straps, snaps, jacket fabric for signs of wear, deterioration.', '', '2025-05-03 20:21:42', '2025-05-03 20:21:42'),
(99, 137, 6, 'Stowed in proper location & labeled.', '', '2025-05-03 20:21:42', '2025-05-03 20:21:42'),
(100, 137, 7, 'Wearing instructions posted.', '', '2025-05-03 20:21:42', '2025-05-03 20:21:42'),
(101, 137, 8, 'Adequate number on board. 1 for every person allowed by the COI. \r\n• 10% of total is required to be children\'s PFD\'s or \r\n•  5%, were all extended size PFDs are used on board; unless adult \r\npassengers only', '', '2025-05-03 20:21:42', '2025-05-03 20:21:42'),
(102, 137, 9, 'Stowage location properly labeled.', '', '2025-05-03 20:21:42', '2025-05-03 20:21:42'),
(103, 137, 10, 'Child and adult jackets are stowed separately.', '', '2025-05-03 20:21:42', '2025-05-03 20:21:42'),
(104, 138, 1, 'Verify proper size onboard: 20\" for vessel less than 26’ or 24” for all others', '', '2025-05-03 20:21:42', '2025-05-03 20:21:42'),
(105, 138, 2, 'Verify free of cracks and weathering.', '', '2025-05-03 20:21:42', '2025-05-03 20:21:42'),
(106, 138, 3, 'Vessel name stenciled on each.', '', '2025-05-03 20:21:42', '2025-05-03 20:21:42'),
(107, 138, 4, 'Proper number onboard. Total count of all including those with lights and lines.', '', '2025-05-03 20:21:42', '2025-05-03 20:21:42'),
(108, 138, 5, 'Ensure properly mounted in racks for easy deployment.', '', '2025-05-03 20:21:42', '2025-05-03 20:21:42'),
(109, 138, 6, 'Check operation of attached waterlights. Check battery expiration date and replace as \r\nnecessary.', '', '2025-05-03 20:21:43', '2025-05-03 20:21:43'),
(110, 139, 1, 'Correct number & capacity in accordance with COI', '', '2025-05-03 20:21:43', '2025-05-03 20:21:43'),
(111, 139, 2, 'Stowed in tiers no more than 4 high. When stowed in tiers, spacers installed between \r\neach life float or buoyant apparatus.', '', '2025-05-03 20:21:43', '2025-05-03 20:21:43'),
(112, 139, 3, 'Stowage is such that units will float free. Acceptable weak link is attached. ', '', '2025-05-03 20:21:43', '2025-05-03 20:21:43'),
(113, 139, 4, 'Painter is in good condition, secured to float and weak link. Weak link is attached to \r\ndeck.', '', '2025-05-03 20:21:43', '2025-05-03 20:21:43'),
(114, 139, 5, 'Stenciled with vessel name in 3” letters and total capacity in 1.5\" letters. ', '', '2025-05-03 20:21:43', '2025-05-03 20:21:43'),
(115, 139, 6, 'Body of unit is in good condition, life lines and netting are in serviceable condition. ', '', '2025-05-03 20:21:43', '2025-05-03 20:21:43'),
(116, 139, 7, 'Each lifefloat shall be equipped with 2 paddles, water light, lifeline, pendents and a \r\npainter. Each buoyant apparatus shall be fitted with a water light, lifeline, pendents and \r\na painter.', '', '2025-05-03 20:21:43', '2025-05-03 20:21:43'),
(117, 140, 1, 'Servicing report current; within last year. All cylinders & flexible loops (12 yrs) within \r\nhydro requirement.', '', '2025-05-03 20:21:43', '2025-05-03 20:21:43'),
(118, 140, 2, 'Diffusers are clear of obstructions.', '', '2025-05-03 20:21:43', '2025-05-03 20:21:43'),
(119, 140, 3, 'Alarms in protected spaces are labeled, warning labels posted.', '', '2025-05-03 20:21:43', '2025-05-03 20:21:43'),
(120, 140, 4, 'Cable pulls are marked.', '', '2025-05-03 20:21:43', '2025-05-03 20:21:43'),
(121, 140, 5, 'Instructions are posted.', '', '2025-05-03 20:21:43', '2025-05-03 20:21:43'),
(122, 140, 6, 'Cylinder brackets fixed and in good condition.', '', '2025-05-03 20:21:43', '2025-05-03 20:21:43'),
(123, 140, 7, 'Cylinders free of corrosion.', '', '2025-05-03 20:21:43', '2025-05-03 20:21:43'),
(124, 140, 8, 'Closure for protected spaces; provided; conduct operational test.', '', '2025-05-03 20:21:43', '2025-05-03 20:21:43'),
(125, 140, 9, 'ventilation and engine shutdowns operational.', '', '2025-05-03 20:21:43', '2025-05-03 20:21:43'),
(126, 140, 10, 'Witness operational test of system by servicing company.', '', '2025-05-03 20:21:43', '2025-05-03 20:21:43'),
(127, 141, 1, 'Servicing report current; within last year. All cylinders & flexible loops (12 yrs) within \r\nhydro requirement.', '', '2025-05-03 20:21:43', '2025-05-03 20:21:43'),
(128, 141, 2, 'Diffusers are clear of obstructions.', '', '2025-05-03 20:21:43', '2025-05-03 20:21:43'),
(129, 141, 3, 'Alarms in protected spaces are labeled, warning labels posted.', '', '2025-05-03 20:21:43', '2025-05-03 20:21:43'),
(130, 141, 4, 'Cable pulls are marked.', '', '2025-05-03 20:21:43', '2025-05-03 20:21:43'),
(131, 141, 5, 'Instructions are posted.', '', '2025-05-03 20:21:43', '2025-05-03 20:21:43'),
(132, 141, 6, 'Cylinder brackets fixed and in good condition. ', '', '2025-05-03 20:21:43', '2025-05-03 20:21:43'),
(133, 141, 7, 'Cylinders free of corrosion. ', '', '2025-05-03 20:21:43', '2025-05-03 20:21:43'),
(134, 141, 8, 'Closure for protected spaces; provided; conduct operational test.', '', '2025-05-03 20:21:43', '2025-05-03 20:21:43'),
(135, 141, 9, 'Ventilation and engine shutdowns operational. ', '', '2025-05-03 20:21:43', '2025-05-03 20:21:43'),
(136, 141, 10, 'Witness operational test of system by servicing company.', '', '2025-05-03 20:21:43', '2025-05-03 20:21:43'),
(137, 142, 1, 'Approved type, mounted in approved bracket. ', '', '2025-05-03 20:21:43', '2025-05-03 20:21:43'),
(138, 142, 2, 'Cylinder corrosion free. ', '', '2025-05-03 20:21:43', '2025-05-03 20:21:43'),
(139, 142, 3, 'Discharge hose is flexible; no signs of wear, deterioration; discharge nozzle intact. ', '', '2025-05-03 20:21:43', '2025-05-03 20:21:43'),
(140, 142, 4, 'Hydro test dates current: 5 yrs for CO2, 12 yrs for dry chemical. ', '', '2025-05-03 20:21:43', '2025-05-03 20:21:43'),
(141, 142, 5, 'Location in accordance with table 181.500(a).', '', '2025-05-03 20:21:43', '2025-05-03 20:21:43'),
(142, 142, 6, 'Location in accordance with table 181.500(a).', '', '2025-05-03 20:21:43', '2025-05-03 20:21:43'),
(143, 143, 1, 'Certificate of Inspection', '', '2025-05-04 21:26:17', '2025-05-04 21:26:17'),
(144, 143, 2, 'FCC Station License', '', '2025-05-04 21:26:17', '2025-05-04 21:26:17'),
(145, 143, 3, 'FCC Safety Radio Certificate', '', '2025-05-04 21:26:17', '2025-05-04 21:26:17'),
(146, 143, 4, 'Stability Letter', '', '2025-05-04 21:26:17', '2025-05-04 22:00:39'),
(147, 143, 5, 'Certificate of Documentation or Certificate of Numbers issued by the State', '', '2025-05-04 21:26:17', '2025-05-04 22:00:16'),
(148, 143, 6, 'Master\'s License', '', '2025-05-04 21:26:17', '2025-05-04 21:26:17'),
(149, 144, 1, 'Navigation Rules', '', '2025-05-04 21:26:17', '2025-05-04 21:26:17'),
(150, 144, 2, 'Coast Pilot', '', '2025-05-04 21:26:17', '2025-05-04 21:26:17'),
(151, 144, 3, 'Charts', '', '2025-05-04 21:26:17', '2025-05-04 21:26:17'),
(152, 144, 4, 'Notice to Mariners', '', '2025-05-04 21:26:17', '2025-05-04 21:26:17'),
(153, 144, 5, 'Tide tables', '', '2025-05-04 21:26:17', '2025-05-04 21:26:17'),
(154, 144, 6, 'Current Tables', '', '2025-05-04 21:26:17', '2025-05-04 21:26:17'),
(155, 144, 7, 'Light Lists', '', '2025-05-04 21:26:17', '2025-05-04 21:26:17'),
(156, 145, 1, 'Fire extinguishing equipment servicing', '', '2025-05-04 21:26:17', '2025-05-04 21:26:17'),
(159, 146, 1, 'Certificate of Inspection', '', '2025-05-04 22:29:35', '2025-05-04 22:29:35'),
(160, 146, 2, 'FCC Station License', '', '2025-05-04 22:29:35', '2025-05-04 22:29:35'),
(161, 146, 3, 'FCC Safety Radio Certificate', '', '2025-05-04 22:29:35', '2025-05-04 22:29:35'),
(162, 146, 4, 'Stability Letter', '', '2025-05-04 22:29:35', '2025-05-04 22:29:35'),
(163, 146, 5, 'Certificate of Documentation or Certificate of Numbers issued by the State ', '', '2025-05-04 22:29:35', '2025-05-04 22:29:35'),
(164, 146, 6, 'Master\'s License', '', '2025-05-04 22:29:35', '2025-05-04 22:29:35'),
(165, 147, 1, 'Navigation Rules', '', '2025-05-04 22:29:35', '2025-05-04 22:29:35'),
(166, 147, 2, 'Coast Pilot', '', '2025-05-04 22:29:35', '2025-05-04 22:29:35'),
(167, 147, 3, 'Charts', '', '2025-05-04 22:29:35', '2025-05-04 22:29:35'),
(168, 147, 4, 'Notice to Mariners', '', '2025-05-04 22:29:35', '2025-05-04 22:29:35'),
(169, 147, 5, 'Tide tables', '', '2025-05-04 22:29:35', '2025-05-04 22:29:35'),
(170, 147, 6, 'Current Tables', '', '2025-05-04 22:29:35', '2025-05-04 22:29:35'),
(171, 147, 7, 'Light Lists', '', '2025-05-04 22:29:35', '2025-05-04 22:29:35'),
(172, 148, 1, 'Fire extinguishing equipment servicing', '', '2025-05-04 22:29:35', '2025-05-04 22:29:35'),
(173, 149, 1, 'Retroreflective material on both sides, at least 31 sq. inches on each side.', '', '2025-05-04 22:29:35', '2025-05-04 22:29:35'),
(174, 149, 2, 'Type I, CG Approved', '', '2025-05-04 22:29:35', '2025-05-04 22:29:35'),
(175, 149, 3, 'Verify PFD lights work. If chemical type, check expiration date. If battery type, \r\ncheck battery expiration date, lens and seal.', '', '2025-05-04 22:29:35', '2025-05-04 22:29:35'),
(176, 149, 4, 'Vessel name clearly labeled on each PFD.', '', '2025-05-04 22:29:35', '2025-05-04 22:29:35'),
(177, 149, 5, 'Check straps, snaps, jacket fabric for signs of wear, deterioration.', '', '2025-05-04 22:29:35', '2025-05-04 22:29:35'),
(178, 149, 6, 'Stowed in proper location & labeled.', '', '2025-05-04 22:29:35', '2025-05-04 22:29:35'),
(179, 149, 7, 'Wearing instructions posted.', '', '2025-05-04 22:29:35', '2025-05-04 22:29:35'),
(180, 149, 8, 'Adequate number on board. 1 for every person allowed by the COI. \r\n• 10% of total is required to be children\'s PFD\'s or \r\n•  5%, were all extended size PFDs are used on board; unless adult \r\npassengers only', '', '2025-05-04 22:29:35', '2025-05-04 22:29:35'),
(181, 149, 9, 'Stowage location properly labeled.', '', '2025-05-04 22:29:35', '2025-05-04 22:29:35'),
(182, 149, 10, 'Child and adult jackets are stowed separately.', '', '2025-05-04 22:29:35', '2025-05-04 22:29:35'),
(183, 150, 1, 'Verify proper size onboard: 20\" for vessel less than 26’ or 24” for all others', '', '2025-05-04 22:29:35', '2025-05-04 22:29:35'),
(184, 150, 2, 'Verify free of cracks and weathering.', '', '2025-05-04 22:29:35', '2025-05-04 22:29:35'),
(185, 150, 3, 'Vessel name stenciled on each.', '', '2025-05-04 22:29:35', '2025-05-04 22:29:35'),
(186, 150, 4, 'Proper number onboard. Total count of all including those with lights and lines.', '', '2025-05-04 22:29:35', '2025-05-04 22:29:35'),
(187, 150, 5, 'Ensure properly mounted in racks for easy deployment.', '', '2025-05-04 22:29:35', '2025-05-04 22:29:35'),
(188, 150, 6, 'Check operation of attached waterlights. Check battery expiration date and replace as \r\nnecessary.', '', '2025-05-04 22:29:35', '2025-05-04 22:29:35'),
(189, 151, 1, 'Correct number & capacity in accordance with COI', '', '2025-05-04 22:29:35', '2025-05-04 22:29:35'),
(190, 151, 2, 'Stowed in tiers no more than 4 high. When stowed in tiers, spacers installed between \r\neach life float or buoyant apparatus.', '', '2025-05-04 22:29:35', '2025-05-04 22:29:35'),
(191, 151, 3, 'Stowage is such that units will float free. Acceptable weak link is attached. ', '', '2025-05-04 22:29:35', '2025-05-04 22:29:35'),
(192, 151, 4, 'Painter is in good condition, secured to float and weak link. Weak link is attached to \r\ndeck.', '', '2025-05-04 22:29:35', '2025-05-04 22:29:35'),
(193, 151, 5, 'Stenciled with vessel name in 3” letters and total capacity in 1.5\" letters. ', '', '2025-05-04 22:29:35', '2025-05-04 22:29:35'),
(194, 151, 6, 'Body of unit is in good condition, life lines and netting are in serviceable condition. ', '', '2025-05-04 22:29:35', '2025-05-04 22:29:35'),
(195, 151, 7, 'Each lifefloat shall be equipped with 2 paddles, water light, lifeline, pendents and a \r\npainter. Each buoyant apparatus shall be fitted with a water light, lifeline, pendents and \r\na painter.', '', '2025-05-04 22:29:35', '2025-05-04 22:29:35'),
(196, 152, 1, 'Servicing report current; within last year. All cylinders & flexible loops (12 yrs) within \r\nhydro requirement.', '', '2025-05-04 22:29:35', '2025-05-04 22:29:35'),
(197, 152, 2, 'Diffusers are clear of obstructions.', '', '2025-05-04 22:29:35', '2025-05-04 22:29:35'),
(198, 152, 3, 'Alarms in protected spaces are labeled, warning labels posted.', '', '2025-05-04 22:29:35', '2025-05-04 22:29:35'),
(199, 152, 4, 'Cable pulls are marked.', '', '2025-05-04 22:29:35', '2025-05-04 22:29:35'),
(200, 152, 5, 'Instructions are posted.', '', '2025-05-04 22:29:35', '2025-05-04 22:29:35'),
(201, 152, 6, 'Cylinder brackets fixed and in good condition.', '', '2025-05-04 22:29:35', '2025-05-04 22:29:35'),
(202, 152, 7, 'Cylinders free of corrosion.', '', '2025-05-04 22:29:35', '2025-05-04 22:29:35'),
(203, 152, 8, 'Closure for protected spaces; provided; conduct operational test.', '', '2025-05-04 22:29:35', '2025-05-04 22:29:35'),
(204, 152, 9, 'ventilation and engine shutdowns operational.', '', '2025-05-04 22:29:35', '2025-05-04 22:29:35'),
(205, 152, 10, 'Witness operational test of system by servicing company.', '', '2025-05-04 22:29:35', '2025-05-04 22:29:35'),
(206, 153, 1, 'Certificate of Inspection', '', '2025-05-05 01:31:58', '2025-05-05 01:31:58'),
(207, 153, 2, 'FCC Station License', '', '2025-05-05 01:31:58', '2025-05-05 01:31:58'),
(208, 153, 3, 'FCC Safety Radio Certificate', '', '2025-05-05 01:31:58', '2025-05-05 01:31:58'),
(209, 153, 4, 'Stability Letter', '', '2025-05-05 01:31:58', '2025-05-05 01:31:58'),
(210, 153, 5, 'Certificate of Documentation or Certificate of Numbers issued by the State ', '', '2025-05-05 01:31:58', '2025-05-05 01:31:58'),
(211, 153, 6, 'Master\'s License', '', '2025-05-05 01:31:58', '2025-05-05 01:31:58'),
(212, 154, 1, 'Navigation Rules', '', '2025-05-05 01:31:58', '2025-05-05 01:31:58'),
(213, 154, 2, 'Coast Pilot', '', '2025-05-05 01:31:58', '2025-05-05 01:31:58'),
(214, 154, 3, 'Charts', '', '2025-05-05 01:31:58', '2025-05-05 01:31:58'),
(215, 154, 4, 'Notice to Mariners', '', '2025-05-05 01:31:58', '2025-05-05 01:31:58'),
(216, 154, 5, 'Tide tables', '', '2025-05-05 01:31:58', '2025-05-05 01:31:58'),
(217, 154, 6, 'Current Tables', '', '2025-05-05 01:31:58', '2025-05-05 01:31:58'),
(218, 154, 7, 'Light Lists', '', '2025-05-05 01:31:58', '2025-05-05 01:31:58'),
(219, 155, 1, 'Fire extinguishing equipment servicing', '', '2025-05-05 01:31:58', '2025-05-05 01:31:58'),
(220, 156, 1, 'Retroreflective material on both sides, at least 31 sq. inches on each side.', '', '2025-05-05 01:31:58', '2025-05-05 01:31:58'),
(221, 156, 2, 'Type I, CG Approved', '', '2025-05-05 01:31:58', '2025-05-05 01:31:58'),
(222, 156, 3, 'Verify PFD lights work. If chemical type, check expiration date. If battery type, \r\ncheck battery expiration date, lens and seal.', '', '2025-05-05 01:31:58', '2025-05-05 01:31:58'),
(223, 156, 4, 'Vessel name clearly labeled on each PFD.', '', '2025-05-05 01:31:58', '2025-05-05 01:31:58'),
(224, 156, 5, 'Check straps, snaps, jacket fabric for signs of wear, deterioration.', '', '2025-05-05 01:31:58', '2025-05-05 01:31:58'),
(225, 156, 6, 'Stowed in proper location & labeled.', '', '2025-05-05 01:31:58', '2025-05-05 01:31:58'),
(226, 156, 7, 'Wearing instructions posted.', '', '2025-05-05 01:31:58', '2025-05-05 01:31:58'),
(227, 156, 8, 'Adequate number on board. 1 for every person allowed by the COI. \r\n• 10% of total is required to be children\'s PFD\'s or \r\n•  5%, were all extended size PFDs are used on board; unless adult \r\npassengers only', '', '2025-05-05 01:31:58', '2025-05-05 01:31:58'),
(228, 156, 9, 'Stowage location properly labeled.', '', '2025-05-05 01:31:58', '2025-05-05 01:31:58'),
(229, 156, 10, 'Child and adult jackets are stowed separately.', '', '2025-05-05 01:31:58', '2025-05-05 01:31:58'),
(230, 157, 1, 'Verify proper size onboard: 20\" for vessel less than 26’ or 24” for all others', '', '2025-05-05 01:31:58', '2025-05-05 01:31:58'),
(231, 157, 2, 'Verify free of cracks and weathering.', '', '2025-05-05 01:31:58', '2025-05-05 01:31:58'),
(232, 157, 3, 'Vessel name stenciled on each.', '', '2025-05-05 01:31:58', '2025-05-05 01:31:58'),
(233, 157, 4, 'Proper number onboard. Total count of all including those with lights and lines.', '', '2025-05-05 01:31:58', '2025-05-05 01:31:58'),
(234, 157, 5, 'Ensure properly mounted in racks for easy deployment.', '', '2025-05-05 01:31:58', '2025-05-05 01:31:58'),
(235, 157, 6, 'Check operation of attached waterlights. Check battery expiration date and replace as necessary.', '', '2025-05-05 01:31:58', '2025-05-06 04:46:28'),
(236, 158, 1, 'Correct number & capacity in accordance with COI', '', '2025-05-05 01:31:58', '2025-05-05 01:31:58'),
(237, 158, 2, 'Stowed in tiers no more than 4 high. When stowed in tiers, spacers installed between \r\neach life float or buoyant apparatus.', '', '2025-05-05 01:31:58', '2025-05-05 01:31:58'),
(238, 158, 3, 'Stowage is such that units will float free. Acceptable weak link is attached. ', '', '2025-05-05 01:31:58', '2025-05-05 01:31:58'),
(239, 158, 4, 'Painter is in good condition, secured to float and weak link. Weak link is attached to \r\ndeck.', '', '2025-05-05 01:31:58', '2025-05-05 01:31:58'),
(240, 158, 5, 'Stenciled with vessel name in 3” letters and total capacity in 1.5\" letters. ', '', '2025-05-05 01:31:58', '2025-05-05 01:31:58'),
(241, 158, 6, 'Body of unit is in good condition, life lines and netting are in serviceable condition. ', '', '2025-05-05 01:31:58', '2025-05-05 01:31:58'),
(242, 158, 7, 'Each lifefloat shall be equipped with 2 paddles, water light, lifeline, pendents and a \r\npainter. Each buoyant apparatus shall be fitted with a water light, lifeline, pendents and \r\na painter.', '', '2025-05-05 01:31:58', '2025-05-05 01:31:58'),
(243, 159, 1, 'Servicing report current; within last year. All cylinders & flexible loops (12 yrs) within \r\nhydro requirement.', '', '2025-05-05 01:31:58', '2025-05-05 01:31:58'),
(244, 159, 2, 'Diffusers are clear of obstructions.', '', '2025-05-05 01:31:58', '2025-05-05 01:31:58'),
(245, 159, 3, 'Alarms in protected spaces are labeled, warning labels posted.', '', '2025-05-05 01:31:58', '2025-05-05 01:31:58'),
(246, 159, 4, 'Cable pulls are marked.', '', '2025-05-05 01:31:59', '2025-05-05 01:31:59'),
(247, 159, 5, 'Instructions are posted.', '', '2025-05-05 01:31:59', '2025-05-05 01:31:59'),
(248, 159, 6, 'Cylinder brackets fixed and in good condition.', '', '2025-05-05 01:31:59', '2025-05-05 01:31:59'),
(249, 159, 7, 'Cylinders free of corrosion.', '', '2025-05-05 01:31:59', '2025-05-05 01:31:59'),
(250, 159, 8, 'Closure for protected spaces; provided; conduct operational test.', '', '2025-05-05 01:31:59', '2025-05-05 01:31:59'),
(251, 159, 9, 'ventilation and engine shutdowns operational.', '', '2025-05-05 01:31:59', '2025-05-05 01:31:59'),
(252, 159, 10, 'Witness operational test of system by servicing company.', '', '2025-05-05 01:31:59', '2025-05-05 01:31:59'),
(253, 160, 1, 'Servicing report current; within last year. All cylinders & flexible loops (12 yrs) within \r\nhydro requirement.', '', '2025-05-05 01:31:59', '2025-05-05 01:31:59'),
(254, 160, 2, 'Diffusers are clear of obstructions.', '', '2025-05-05 01:31:59', '2025-05-05 01:31:59'),
(255, 160, 3, 'Alarms in protected spaces are labeled, warning labels posted.', '', '2025-05-05 01:31:59', '2025-05-05 01:31:59'),
(256, 160, 4, 'Cable pulls are marked.', '', '2025-05-05 01:31:59', '2025-05-05 01:31:59'),
(257, 160, 5, 'Instructions are posted.', '', '2025-05-05 01:31:59', '2025-05-05 01:31:59'),
(258, 160, 6, 'Cylinder brackets fixed and in good condition. ', '', '2025-05-05 01:31:59', '2025-05-05 01:31:59'),
(259, 160, 7, 'Cylinders free of corrosion. ', '', '2025-05-05 01:31:59', '2025-05-05 01:31:59'),
(260, 160, 8, 'Closure for protected spaces; provided; conduct operational test.', '', '2025-05-05 01:31:59', '2025-05-05 01:31:59'),
(261, 160, 9, 'Ventilation and engine shutdowns operational. ', '', '2025-05-05 01:31:59', '2025-05-05 01:31:59'),
(262, 160, 10, 'Witness operational test of system by servicing company.', '', '2025-05-05 01:31:59', '2025-05-05 01:31:59'),
(263, 161, 1, 'Approved type, mounted in approved bracket. ', '', '2025-05-05 01:31:59', '2025-05-05 01:31:59'),
(264, 161, 2, 'Cylinder corrosion free. ', '', '2025-05-05 01:31:59', '2025-05-05 01:31:59'),
(265, 161, 3, 'Discharge hose is flexible; no signs of wear, deterioration; discharge nozzle intact. ', '', '2025-05-05 01:31:59', '2025-05-05 01:31:59'),
(266, 161, 4, 'Hydro test dates current: 5 yrs for CO2, 12 yrs for dry chemical. ', '', '2025-05-05 01:31:59', '2025-05-05 01:31:59'),
(267, 161, 5, 'Location in accordance with table 181.500(a).', '', '2025-05-05 01:31:59', '2025-05-05 01:31:59'),
(268, 161, 6, 'Location in accordance with table 181.500(a).', '', '2025-05-05 01:31:59', '2025-05-05 01:31:59'),
(269, 162, 1, 'operate fire pumps; operating properly? \r\n1.  No excessive leaks. \r\n2.  Foundation/ pump and motor secure. \r\n3.  Shaft bearing- no play. \r\n4.  Coupling guard in place. \r\n5.  Remote operation.\r\n\r\n\r\n\r\n', '', '2025-05-05 01:31:59', '2025-05-05 01:31:59'),
(270, 162, 2, 'All required hoses onboard, compatible threads, satisfactory condition.', '', '2025-05-05 01:31:59', '2025-05-05 01:31:59'),
(271, 162, 3, 'Fire hydrant- Hose at hydrant and attached, spanner wrench, nozzle, low velocity fog \r\napplicator where applicable. All equipment compatible. ', '', '2025-05-05 01:31:59', '2025-05-05 01:31:59'),
(272, 162, 4, 'Hoses correct length (50’) and size, based on COI.', '', '2025-05-05 01:31:59', '2025-05-05 01:31:59'),
(273, 162, 5, 'Satisfactory hydrostatic test of hoses to fire pump shutoff head pressure.', '', '2025-05-05 01:31:59', '2025-05-05 01:31:59'),
(274, 162, 6, 'Check pressure gauge on discharge side of pump to make sure it is functioning \r\nproperly. ', '', '2025-05-05 01:31:59', '2025-05-05 01:31:59'),
(275, 162, 7, 'Verify all valves at fire hydrants are operable. ', '', '2025-05-05 01:31:59', '2025-05-05 01:31:59'),
(276, 162, 8, 'Verify compatibility of equipment at each hydrant.', '', '2025-05-05 01:31:59', '2025-05-05 01:31:59'),
(277, 162, 9, 'Determine that relief valves are set properly and discharge to acceptable location if \r\ninstalled.', '', '2025-05-05 01:31:59', '2025-05-05 01:31:59'),
(278, 163, 1, 'Witness operational test of fire detection system.', '', '2025-05-05 01:31:59', '2025-05-05 01:31:59'),
(279, 163, 2, 'Assure all sensors are free of obstruction and functioning. ', '', '2025-05-05 01:31:59', '2025-05-05 01:31:59'),
(280, 163, 3, 'Verify alarms and indicators are functioning correctly; visible and audible from the pilot \r\nhouse or fire control station. ', '', '2025-05-05 01:31:59', '2025-05-05 01:31:59'),
(281, 163, 4, 'Verify audible alarms in engine room are functioning properly, if provided.', '', '2025-05-05 01:31:59', '2025-05-05 01:31:59'),
(282, 163, 5, 'Ensure engine room, pilothouse , and fire control station alarms are conspicuously \r\nmarked in clearly legible letters. ', '', '2025-05-05 01:31:59', '2025-05-05 01:31:59'),
(283, 163, 6, 'Manual alarm systems functioning properly. ', '', '2025-05-05 01:31:59', '2025-05-05 01:31:59'),
(284, 164, 1, 'Verify manual operation of all fire dampers.', '', '2025-05-05 01:31:59', '2025-05-05 01:31:59'),
(285, 164, 2, 'Test remote operation of all remote ventilation shutdowns.', '', '2025-05-05 01:31:59', '2025-05-05 01:31:59'),
(286, 165, 1, 'Proper number of fire buckets; three (3) 2½ gallon buckets with lanyards.', '', '2025-05-05 01:31:59', '2025-05-05 01:31:59'),
(287, 165, 2, 'Stenciled in contrasting color with the words “FIRE BUCKET”.', '', '2025-05-05 01:31:59', '2025-05-05 01:31:59'),
(289, 166, 1, 'Certificate of Inspection', '', '2025-05-08 01:52:02', '2025-05-08 01:52:02'),
(290, 166, 2, 'FCC Station License', '', '2025-05-08 01:52:02', '2025-05-08 01:52:02'),
(291, 166, 3, 'FCC Safety Radio Certificate', '', '2025-05-08 01:52:02', '2025-05-08 01:52:02'),
(292, 166, 4, 'Stability Letter', '', '2025-05-08 01:52:02', '2025-05-08 01:52:02'),
(293, 166, 5, 'Certificate of Documentation or Certificate of Numbers issued by the State ', '', '2025-05-08 01:52:02', '2025-05-08 01:52:02'),
(294, 166, 6, 'Master\'s License', '', '2025-05-08 01:52:02', '2025-05-08 01:52:02'),
(295, 167, 1, 'Navigation Rules', '', '2025-05-08 01:52:02', '2025-05-08 01:52:02'),
(296, 167, 2, 'Coast Pilot', '', '2025-05-08 01:52:02', '2025-05-08 01:52:02'),
(297, 167, 3, 'Charts', '', '2025-05-08 01:52:02', '2025-05-08 01:52:02'),
(298, 167, 4, 'Notice to Mariners', '', '2025-05-08 01:52:02', '2025-05-08 01:52:02'),
(299, 167, 5, 'Tide tables', '', '2025-05-08 01:52:02', '2025-05-08 01:52:02'),
(300, 167, 6, 'Current Tables', '', '2025-05-08 01:52:02', '2025-05-08 01:52:02'),
(301, 167, 7, 'Light Lists', '', '2025-05-08 01:52:02', '2025-05-08 01:52:02'),
(302, 168, 1, 'Fire extinguishing equipment servicing', '', '2025-05-08 01:52:02', '2025-05-08 01:52:02'),
(303, 169, 1, 'Retroreflective material on both sides, at least 31 sq. inches on each side.', '', '2025-05-08 01:52:02', '2025-05-08 01:52:02'),
(304, 169, 2, 'Type I, CG Approved', '', '2025-05-08 01:52:02', '2025-05-08 01:52:02'),
(305, 169, 3, 'Verify PFD lights work. If chemical type, check expiration date. If battery type, \r\ncheck battery expiration date, lens and seal.', '', '2025-05-08 01:52:02', '2025-05-08 01:52:02'),
(306, 169, 4, 'Vessel name clearly labeled on each PFD.', '', '2025-05-08 01:52:02', '2025-05-08 01:52:02'),
(307, 169, 5, 'Check straps, snaps, jacket fabric for signs of wear, deterioration.', '', '2025-05-08 01:52:02', '2025-05-08 01:52:02'),
(308, 169, 6, 'Stowed in proper location & labeled.', '', '2025-05-08 01:52:02', '2025-05-08 01:52:02'),
(309, 169, 7, 'Wearing instructions posted.', '', '2025-05-08 01:52:02', '2025-05-08 01:52:02'),
(310, 169, 8, 'Adequate number on board. 1 for every person allowed by the COI. \r\n• 10% of total is required to be children\'s PFD\'s or \r\n•  5%, were all extended size PFDs are used on board; unless adult \r\npassengers only', '', '2025-05-08 01:52:02', '2025-05-08 01:52:02'),
(311, 169, 9, 'Stowage location properly labeled.', '', '2025-05-08 01:52:02', '2025-05-08 01:52:02'),
(312, 169, 10, 'Child and adult jackets are stowed separately.', '', '2025-05-08 01:52:02', '2025-05-08 01:52:02'),
(313, 170, 1, 'Verify proper size onboard: 20\" for vessel less than 26’ or 24” for all others', '', '2025-05-08 01:52:02', '2025-05-08 01:52:02'),
(314, 170, 2, 'Verify free of cracks and weathering.', '', '2025-05-08 01:52:02', '2025-05-08 01:52:02'),
(315, 170, 3, 'Vessel name stenciled on each.', '', '2025-05-08 01:52:02', '2025-05-08 01:52:02'),
(316, 170, 4, 'Proper number onboard. Total count of all including those with lights and lines.', '', '2025-05-08 01:52:02', '2025-05-08 01:52:02'),
(317, 170, 5, 'Ensure properly mounted in racks for easy deployment.', '', '2025-05-08 01:52:02', '2025-05-08 01:52:02'),
(318, 170, 6, 'Check operation of attached waterlights. Check battery expiration date and replace as \r\nnecessary.', '', '2025-05-08 01:52:02', '2025-05-08 01:52:02'),
(319, 171, 1, 'Correct number & capacity in accordance with COI', '', '2025-05-08 01:52:02', '2025-05-08 01:52:02'),
(320, 171, 2, 'Stowed in tiers no more than 4 high. When stowed in tiers, spacers installed between \r\neach life float or buoyant apparatus.', '', '2025-05-08 01:52:02', '2025-05-08 01:52:02'),
(321, 171, 3, 'Stowage is such that units will float free. Acceptable weak link is attached. ', '', '2025-05-08 01:52:02', '2025-05-08 01:52:02'),
(322, 171, 4, 'Painter is in good condition, secured to float and weak link. Weak link is attached to \r\ndeck.', '', '2025-05-08 01:52:02', '2025-05-08 01:52:02'),
(323, 171, 5, 'Stenciled with vessel name in 3” letters and total capacity in 1.5\" letters. ', '', '2025-05-08 01:52:02', '2025-05-08 01:52:02'),
(324, 171, 6, 'Body of unit is in good condition, life lines and netting are in serviceable condition. ', '', '2025-05-08 01:52:02', '2025-05-08 01:52:02'),
(325, 171, 7, 'Each lifefloat shall be equipped with 2 paddles, water light, lifeline, pendents and a \r\npainter. Each buoyant apparatus shall be fitted with a water light, lifeline, pendents and \r\na painter.', '', '2025-05-08 01:52:02', '2025-05-08 01:52:02'),
(326, 172, 1, 'Servicing report current; within last year. All cylinders & flexible loops (12 yrs) within \r\nhydro requirement.', '', '2025-05-08 01:52:02', '2025-05-08 01:52:02'),
(327, 172, 2, 'Diffusers are clear of obstructions.', '', '2025-05-08 01:52:02', '2025-05-08 01:52:02'),
(328, 172, 3, 'Alarms in protected spaces are labeled, warning labels posted.', '', '2025-05-08 01:52:02', '2025-05-08 01:52:02'),
(329, 172, 4, 'Cable pulls are marked.', '', '2025-05-08 01:52:02', '2025-05-08 01:52:02'),
(330, 172, 5, 'Instructions are posted.', '', '2025-05-08 01:52:02', '2025-05-08 01:52:02'),
(331, 172, 6, 'Cylinder brackets fixed and in good condition. ', '', '2025-05-08 01:52:02', '2025-05-08 01:52:02'),
(332, 172, 7, 'Cylinders free of corrosion. ', '', '2025-05-08 01:52:02', '2025-05-08 01:52:02'),
(333, 172, 8, 'Closure for protected spaces; provided; conduct operational test.', '', '2025-05-08 01:52:02', '2025-05-08 01:52:02'),
(334, 172, 9, 'Ventilation and engine shutdowns operational. ', '', '2025-05-08 01:52:02', '2025-05-08 01:52:02'),
(335, 172, 10, 'Witness operational test of system by servicing company.', '', '2025-05-08 01:52:02', '2025-05-08 01:52:02'),
(336, 173, 1, 'Approved type, mounted in approved bracket. ', '', '2025-05-08 01:52:02', '2025-05-08 01:52:02'),
(337, 173, 2, 'Cylinder corrosion free. ', '', '2025-05-08 01:52:02', '2025-05-08 01:52:02'),
(338, 173, 3, 'Discharge hose is flexible; no signs of wear, deterioration; discharge nozzle intact. ', '', '2025-05-08 01:52:02', '2025-05-08 01:52:02'),
(339, 173, 4, 'Hydro test dates current: 5 yrs for CO2, 12 yrs for dry chemical. ', '', '2025-05-08 01:52:02', '2025-05-08 01:52:02'),
(340, 173, 5, 'Location in accordance with table 181.500(a).', '', '2025-05-08 01:52:02', '2025-05-08 01:52:02'),
(341, 173, 6, 'Location in accordance with table 181.500(a).', '', '2025-05-08 01:52:02', '2025-05-08 01:52:02'),
(342, 174, 1, 'Verify manual operation of all fire dampers.', '', '2025-05-08 01:52:02', '2025-05-08 01:52:02'),
(343, 174, 2, 'Test remote operation of all remote ventilation shutdowns.', '', '2025-05-08 01:52:02', '2025-05-08 01:52:02'),
(344, 175, 1, 'Proper number of fire buckets; three (3) 2½ gallon buckets with lanyards.', '', '2025-05-08 01:52:02', '2025-05-08 01:52:02'),
(345, 175, 2, 'Stenciled in contrasting color with the words “FIRE BUCKET”.', '', '2025-05-08 01:52:02', '2025-05-08 01:52:02'),
(346, 176, 1, 'Tested monthly using visual or audio output indicator.', '', '2025-05-08 01:52:02', '2025-05-08 01:52:02'),
(347, 176, 2, 'Stowed in a manner so that it will float free should the vessel sink & auto-activate.', '', '2025-05-08 01:52:02', '2025-05-08 01:52:02'),
(348, 176, 3, 'Replace battery  if EPIRB is used for purposes other than testing. Replace battery on \r\nor before the expiration date marked on the battery.', '', '2025-05-08 01:52:02', '2025-05-08 01:52:02'),
(349, 176, 4, 'Vessel name shall be marked on EPIRB.', '', '2025-05-08 01:52:02', '2025-05-08 01:52:02'),
(350, 177, 1, 'General Alarm contact makers and alarm bells are located and marked in accordance \r\nwith the regulations.', '', '2025-05-08 01:52:02', '2025-05-08 01:52:02'),
(351, 177, 2, 'Energize system from each contact maker. Ensure contact  makers are all operable, \r\nensure alarm bells are all operable and that none have been deliberately disabled. ', '', '2025-05-08 01:52:02', '2025-05-08 01:52:02'),
(352, 177, 3, 'Ensure alarm bells are sufficiently loud to be easily heard above the ambient noise of \r\nthe space in which they are placed.', '', '2025-05-08 01:52:02', '2025-05-08 01:52:02'),
(353, 177, 4, 'Ensure operation of any flashing red lights installed in addition to alarm bells.', '', '2025-05-08 01:52:02', '2025-05-08 01:52:02'),
(354, 178, 1, 'Verify the correct amount: \r\nOceans, Coastwise, or Limited Coastwise: • 6 hand red & 6 hand orange smoke; or \r\n• 12 rocket parachute; or \r\n• 12 hand red;or \r\n• 6 hand red & 6 orange float; or, \r\n• combination allowed by regulation. \r\n\r\nLakes, Bays, and Sounds, Rivers:\r\n\r\n• 3 hand red & 3 hand orange smoke; or \r\n• 6 rocket parachute; or \r\n• 6 hand red; or \r\n• 3 hand red & 3 orange float; or, \r\n• combination allowed by regulation. ', '', '2025-05-08 01:52:02', '2025-05-08 01:52:02'),
(355, 178, 2, 'The service life of the distress signals shall be stamped by the manufacture on the \r\ndistress signal. ', '', '2025-05-08 01:52:02', '2025-05-08 01:52:02'),
(356, 178, 3, 'The distress signals shall be stowed in a portable watertight container at the operating \r\nstation or a pyrotechnic locker secured above the freeboard deck in the vicinity of the \r\noperating station.', '', '2025-05-08 01:52:02', '2025-05-08 01:52:02'),
(357, 179, 1, 'Verify first aid kit is approved under the series 160.041 or other standard specified by \r\nthe Commandant.', '', '2025-05-08 01:52:03', '2025-05-08 01:52:03'),
(358, 179, 2, 'Ensure “First Aid Kit is stenciled on container.', '', '2025-05-08 01:52:03', '2025-05-08 01:52:03'),
(359, 179, 3, 'Ensure first aid kit is visible and readily available to the crew.', '', '2025-05-08 01:52:03', '2025-05-08 01:52:03'),
(360, 179, 4, 'Ensure contents of kit are adequate.', '', '2025-05-08 01:52:03', '2025-05-08 01:52:03'),
(361, 180, 1, 'If power ventilation is installed, it must be capable of being shutdown from the pilot \r\nhouse.', '', '2025-05-08 01:52:03', '2025-05-08 01:52:03'),
(362, 180, 2, 'delete me', '', '2025-05-08 01:52:03', '2025-05-08 01:52:03'),
(363, 181, 1, 'Vent line not holed or excessively corroded. ', '', '2025-05-08 01:52:03', '2025-05-08 01:52:03'),
(364, 181, 2, 'Flame screen or flame arrester is clean, in good condition and firmly attached to the \r\nvent. ', '', '2025-05-08 01:52:03', '2025-05-08 01:52:03'),
(365, 181, 3, 'Flame screen is a single screen of 30x30.', '', '2025-05-08 01:52:03', '2025-05-08 01:52:03'),
(366, 181, 4, 'Containment is available, clean, dry and in good condition. ', '', '2025-05-08 01:52:03', '2025-05-08 01:52:03'),
(367, 182, 1, 'Vent line not holed or excessively corroded.', '', '2025-05-08 01:52:03', '2025-05-08 01:52:03'),
(368, 183, 1, 'Check for illumination.', '', '2025-05-08 01:52:03', '2025-05-08 01:52:03'),
(369, 183, 2, 'Ensure compass can be read from main steering position.', '', '2025-05-08 01:52:03', '2025-05-08 01:52:03'),
(370, 183, 3, 'Ensure deviation table is current, and no major structural changes have been made.', '', '2025-05-08 01:52:03', '2025-05-08 01:52:03'),
(371, 184, 1, 'Must be capable of operating in 156-162 MHz range. Capable of transmitting and \r\nreceiving VHF FM Channels 13, 16, 22A. TEST : Obtain radio checks. ', '', '2025-05-08 01:52:03', '2025-05-08 01:52:03'),
(372, 184, 2, 'Separate circuit with overcurrent protection at the main distribution panel. ', '', '2025-05-08 01:52:03', '2025-05-08 01:52:03'),
(373, 184, 3, 'Supplied by two sources of electricity or batteries with a capacity for three hours. ', '', '2025-05-08 01:52:03', '2025-05-08 01:52:03'),
(374, 184, 4, 'Verify that FCC certificates are valid. ', '', '2025-05-08 01:52:03', '2025-05-08 01:52:03'),
(375, 184, 5, 'Must be DSC equipped and MMSI must be inputted into the radio', '', '2025-05-08 01:52:03', '2025-05-08 01:52:03'),
(376, 184, 6, 'Verify GPS function on radio is operational.', '', '2025-05-08 01:52:03', '2025-05-08 01:52:03'),
(377, 185, 1, 'Verify that navigation lights are operable. Test on emergency power if installed. ', '', '2025-05-08 01:52:03', '2025-05-08 01:52:03'),
(378, 185, 2, 'Proper bulbs installed. ', '', '2025-05-08 01:52:03', '2025-05-08 01:52:03'),
(379, 185, 3, 'If navigation light indicator panel installed; operating properly- check all fuses and \r\nalarms. ', '', '2025-05-08 01:52:03', '2025-05-08 01:52:03'),
(380, 185, 4, 'Verify lights are installed in accordance with Navigation rules.', '', '2025-05-08 01:52:03', '2025-05-08 01:52:03'),
(381, 185, 5, 'Reflective screens in place and painted matte black?', '', '2025-05-08 01:52:03', '2025-05-08 01:52:03'),
(382, 185, 6, 'Lenses clean, wiring free of splices; no deterioration, installation appears sound.', '', '2025-05-08 01:52:03', '2025-05-08 01:52:03'),
(383, 185, 7, 'Supplied by two sources of electricity or batteries with a capacity for three hours.', '', '2025-05-08 01:52:03', '2025-05-08 01:52:03'),
(384, 186, 1, 'Verify the following are up-to-date and adequate for the route intended; \r\nLarge scale charts, ', '', '2025-05-08 01:52:03', '2025-05-08 01:52:03'),
(385, 186, 2, 'Coast Pilot,', '', '2025-05-08 01:52:03', '2025-05-08 01:52:03'),
(386, 186, 3, 'Light List, ', '', '2025-05-08 01:52:03', '2025-05-08 01:52:03'),
(387, 186, 4, 'Tide Tables, ', '', '2025-05-08 01:52:03', '2025-05-08 01:52:03'),
(388, 186, 5, 'Current tables or River Current Publications. ', '', '2025-05-08 01:52:03', '2025-05-08 01:52:03'),
(389, 187, 1, 'All dayshapes shall be black. ', '', '2025-05-08 01:52:03', '2025-05-08 01:52:03'),
(390, 187, 2, 'If shape is a ball, it shall not have a diameter of over .6 meter. ', '', '2025-05-08 01:52:03', '2025-05-08 01:52:03'),
(391, 187, 3, 'If shape is a cone, it shall have a base diameter of over .6 meters, and a height equal \r\nto its diameter.', '', '2025-05-08 01:52:03', '2025-05-08 01:52:03'),
(392, 187, 4, 'If the shape is a cylinder,  it shall have a diameter of at least .6 meters, and a height of \r\ntwice the diameter.', '', '2025-05-08 01:52:03', '2025-05-08 01:52:03'),
(393, 187, 5, 'A diamond shape shall consist of cones as defined above, having a common base. ', '', '2025-05-08 01:52:03', '2025-05-08 01:52:03'),
(394, 187, 6, 'The vertical distance between shapes shall be at least 1.5 meters.', '', '2025-05-08 01:52:03', '2025-05-08 01:52:03'),
(395, 187, 7, 'The frequency of a whistle shall be as required by Table 86.05. ', '', '2025-05-08 01:52:03', '2025-05-08 01:52:03'),
(396, 187, 8, 'The whistle shall be installed with it\'s forward axis directed forward and placed as high \r\nas practicable.', '', '2025-05-08 01:52:03', '2025-05-08 01:52:03'),
(397, 188, 1, 'Test the electronic position-fixing device for accuracy by comparing a fix on the \r\ndevice to a charted location. ', '', '2025-05-08 01:52:03', '2025-05-08 01:52:03');
INSERT INTO `vessel_icr_steps` (`step_id`, `vessel_icr_id`, `step_number`, `step_description`, `deficiency_action`, `created_at`, `updated_at`) VALUES
(398, 189, 1, 'Anchor sized in accordance with industry standards or as required by OCMI.', '', '2025-05-08 01:52:03', '2025-05-08 01:52:03'),
(399, 189, 2, 'Ensure all anchor releasing and retrieval equipment is operable and in good working \r\ncondition (line/chain, winch/davit or windlass foundation, stopper, brake). ', '', '2025-05-08 01:52:03', '2025-05-08 01:52:03'),
(400, 189, 3, 'Anchor winch or windlass should be tested to let out and retrieve chain.', '', '2025-05-08 01:52:03', '2025-05-08 01:52:03'),
(401, 190, 1, 'Bits, cleats and fairleads not excessively corroded or grooved; no scale build-up.', '', '2025-05-08 01:52:03', '2025-05-08 01:52:03'),
(402, 190, 2, 'Cleat/bit horns not missing, broken or excessively.', '', '2025-05-08 01:52:03', '2025-05-08 01:52:03'),
(403, 190, 3, 'Foundations not fractured.', '', '2025-05-08 01:52:03', '2025-05-08 01:52:03'),
(404, 190, 4, 'All guy wires taut, no slack; turnbuckles, wire rope not wasted.', '', '2025-05-08 01:52:03', '2025-05-08 01:52:03'),
(405, 191, 1, 'Examine all watertight bulkheads to ensure they are intact and watertight. Foam \r\nflotation (if required & installed) not waterlogged. ', '', '2025-05-08 01:52:03', '2025-05-08 01:52:03'),
(406, 191, 2, 'Examine collision bulkhead ensuring it is intact and watertight.', '', '2025-05-08 01:52:03', '2025-05-08 01:52:03'),
(407, 191, 3, 'Ensure electrical cable and piping penetrations maintain watertight integrity and are \r\nkept to a minimum. ', '', '2025-05-08 01:52:03', '2025-05-08 01:52:03'),
(408, 191, 4, 'Examine for signs of corrosion or deterioration.', '', '2025-05-08 01:52:03', '2025-05-08 01:52:03'),
(409, 191, 5, 'Ensure sluice valves have not been installed.', '', '2025-05-08 01:52:03', '2025-05-08 01:52:03'),
(410, 192, 1, 'Ensure electrical cable and piping penetrations maintain watertight integrity i. e. stuffing \r\ntubes still serviceable.', '', '2025-05-08 01:52:03', '2025-05-08 01:52:03'),
(411, 192, 2, 'If sealant is used in penetrations, it must be a non-flammable product designed for \r\nsuch use. ', '', '2025-05-08 01:52:03', '2025-05-08 01:52:03'),
(412, 193, 1, 'Verify operation of all remote fuel shutoff valves. Ensure markings on the weather deck \r\nare legible and unobstructed.', '', '2025-05-08 01:52:03', '2025-05-08 01:52:03'),
(413, 193, 2, 'Ensure all valves adequately lubricated and operate freely. ', '', '2025-05-08 01:52:03', '2025-05-08 01:52:03'),
(414, 193, 3, 'Operate each reach rod and other manual remote control mechanisms function \r\nproperly.', '', '2025-05-08 01:52:03', '2025-05-08 01:52:03'),
(415, 193, 4, 'Verify each power operated valve operates properly from control station.', '', '2025-05-08 01:52:03', '2025-05-08 01:52:03'),
(416, 194, 1, 'Approved type, mounted in approved bracket.', '', '2025-05-16 17:01:08', '2025-05-16 17:02:40'),
(417, 194, 2, 'Cylinder corrosion free.', '', '2025-05-16 17:01:08', '2025-05-16 17:02:40'),
(418, 194, 3, 'Discharge hose is flexible; no signs of wear, deterioration; discharge nozzle intact.', '', '2025-05-16 17:01:08', '2025-05-16 17:02:40'),
(419, 194, 4, 'Hydro test dates current: 5 yrs for CO2, 12 yrs for dry chemical.', '', '2025-05-16 17:01:08', '2025-05-16 17:02:40'),
(420, 194, 5, 'Location in accordance with table 181.500(a).', '', '2025-05-16 17:01:08', '2025-05-16 17:01:08'),
(422, 195, 1, 'Certificate of Inspection', '', '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(423, 195, 2, 'FCC Station License', '', '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(424, 195, 3, 'FCC Safety Radio Certificate', '', '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(425, 195, 4, 'Stability Letter', '', '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(426, 195, 5, 'Certificate of Documentation or Certificate of Numbers issued by the State ', '', '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(427, 195, 6, 'Master\'s License', '', '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(428, 196, 1, 'Navigation Rules', '', '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(429, 196, 2, 'Coast Pilot', '', '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(430, 196, 3, 'Charts', '', '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(431, 196, 4, 'Notice to Mariners', '', '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(432, 196, 5, 'Tide tables', '', '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(433, 196, 6, 'Current Tables', '', '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(434, 196, 7, 'Light Lists', '', '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(435, 197, 1, 'Fire extinguishing equipment servicing', '', '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(436, 198, 1, 'Retroreflective material on both sides, at least 31 sq. inches on each side.', '', '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(437, 198, 2, 'Type I, CG Approved', '', '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(438, 198, 3, 'Verify PFD lights work. If chemical type, check expiration date. If battery type, \r\ncheck battery expiration date, lens and seal.', '', '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(439, 198, 4, 'Vessel name clearly labeled on each PFD.', '', '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(440, 198, 5, 'Check straps, snaps, jacket fabric for signs of wear, deterioration.', '', '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(441, 198, 6, 'Stowed in proper location & labeled.', '', '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(442, 198, 7, 'Wearing instructions posted.', '', '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(443, 198, 8, 'Adequate number on board. 1 for every person allowed by the COI. \r\n• 10% of total is required to be children\'s PFD\'s or \r\n•  5%, were all extended size PFDs are used on board; unless adult \r\npassengers only', '', '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(444, 198, 9, 'Stowage location properly labeled.', '', '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(445, 198, 10, 'Child and adult jackets are stowed separately.', '', '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(446, 199, 1, 'Verify proper size onboard: 20\" for vessel less than 26’ or 24” for all others', '', '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(447, 199, 2, 'Verify free of cracks and weathering.', '', '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(448, 199, 3, 'Vessel name stenciled on each.', '', '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(449, 199, 4, 'Proper number onboard. Total count of all including those with lights and lines.', '', '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(450, 199, 5, 'Ensure properly mounted in racks for easy deployment.', '', '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(451, 199, 6, 'Check operation of attached waterlights. Check battery expiration date and replace as \r\nnecessary.', '', '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(452, 200, 1, 'Approved type, mounted in approved bracket.', '', '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(453, 200, 2, 'Cylinder corrosion free.', '', '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(454, 200, 3, 'Discharge hose is flexible; no signs of wear, deterioration; discharge nozzle intact.', '', '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(455, 200, 4, 'Hydro test dates current: 5 yrs for CO2, 12 yrs for dry chemical.', '', '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(456, 200, 5, 'Location in accordance with table 181.500(a).', '', '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(457, 200, 6, 'Location in accordance with table 181.500(a).', '', '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(458, 201, 1, 'Proper number of fire buckets; three (3) 2½ gallon buckets with lanyards.', '', '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(459, 201, 2, 'Stenciled in contrasting color with the words “FIRE BUCKET”.', '', '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(460, 202, 1, 'Tested monthly using visual or audio output indicator.', '', '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(461, 202, 2, 'Stowed in a manner so that it will float free should the vessel sink & auto-activate.', '', '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(462, 202, 3, 'Replace battery  if EPIRB is used for purposes other than testing. Replace battery on \r\nor before the expiration date marked on the battery.', '', '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(463, 202, 4, 'Vessel name shall be marked on EPIRB.', '', '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(464, 203, 1, 'Verify the correct amount: \r\nOceans, Coastwise, or Limited Coastwise: • 6 hand red & 6 hand orange smoke; or \r\n• 12 rocket parachute; or \r\n• 12 hand red;or \r\n• 6 hand red & 6 orange float; or, \r\n• combination allowed by regulation. \r\n\r\nLakes, Bays, and Sounds, Rivers:\r\n\r\n• 3 hand red & 3 hand orange smoke; or \r\n• 6 rocket parachute; or \r\n• 6 hand red; or \r\n• 3 hand red & 3 orange float; or, \r\n• combination allowed by regulation. ', '', '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(465, 203, 2, 'The service life of the distress signals shall be stamped by the manufacture on the \r\ndistress signal. ', '', '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(466, 203, 3, 'The distress signals shall be stowed in a portable watertight container at the operating \r\nstation or a pyrotechnic locker secured above the freeboard deck in the vicinity of the \r\noperating station.', '', '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(467, 204, 1, 'Verify first aid kit is approved under the series 160.041 or other standard specified by \r\nthe Commandant.', '', '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(468, 204, 2, 'Ensure “First Aid Kit is stenciled on container.', '', '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(469, 204, 3, 'Ensure first aid kit is visible and readily available to the crew.', '', '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(470, 204, 4, 'Ensure contents of kit are adequate.', '', '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(471, 205, 1, 'Check for illumination.', '', '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(472, 205, 2, 'Ensure compass can be read from main steering position.', '', '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(473, 205, 3, 'Ensure deviation table is current, and no major structural changes have been made.', '', '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(474, 206, 1, 'Must be capable of operating in 156-162 MHz range. Capable of transmitting and \r\nreceiving VHF FM Channels 13, 16, 22A. TEST : Obtain radio checks. ', '', '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(475, 206, 2, 'Separate circuit with overcurrent protection at the main distribution panel. ', '', '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(476, 206, 3, 'Supplied by two sources of electricity or batteries with a capacity for three hours. ', '', '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(477, 206, 4, 'Verify that FCC certificates are valid. ', '', '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(478, 206, 5, 'Must be DSC equipped and MMSI must be inputted into the radio', '', '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(479, 206, 6, 'Verify GPS function on radio is operational.', '', '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(480, 207, 1, 'Verify that navigation lights are operable. Test on emergency power if installed. ', '', '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(481, 207, 2, 'Proper bulbs installed. ', '', '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(482, 207, 3, 'If navigation light indicator panel installed; operating properly- check all fuses and \r\nalarms. ', '', '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(483, 207, 4, 'Verify lights are installed in accordance with Navigation rules.', '', '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(484, 207, 5, 'Reflective screens in place and painted matte black?', '', '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(485, 207, 6, 'Lenses clean, wiring free of splices; no deterioration, installation appears sound.', '', '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(486, 207, 7, 'Supplied by two sources of electricity or batteries with a capacity for three hours.', '', '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(487, 208, 1, 'If sound powered telephones or voice tubes are installed verify operation. ', '', '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(488, 208, 2, 'Test ringers, and operation of each voice tube or sound powered phone in a location \r\nrequired by the regulations. Ensure each can be heard above the ambient noise of that \r\nlocation. ', '', '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(489, 208, 3, 'If hand held portable radios are used verify operation.', '', '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(490, 208, 4, 'Verify operation from operating station to location for controlling propulsion machinery.', '', '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(491, 209, 1, 'Verify the following are up-to-date and adequate for the route intended; \r\nLarge scale charts, ', '', '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(492, 209, 2, 'Coast Pilot,', '', '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(493, 209, 3, 'Light List, ', '', '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(494, 209, 4, 'Tide Tables, ', '', '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(495, 209, 5, 'Current tables or River Current Publications. ', '', '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(496, 210, 1, 'All dayshapes shall be black. ', '', '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(497, 210, 2, 'If shape is a ball, it shall not have a diameter of over .6 meter. ', '', '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(498, 210, 3, 'If shape is a cone, it shall have a base diameter of over .6 meters, and a height equal \r\nto its diameter.', '', '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(499, 210, 4, 'If the shape is a cylinder,  it shall have a diameter of at least .6 meters, and a height of \r\ntwice the diameter.', '', '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(500, 210, 5, 'A diamond shape shall consist of cones as defined above, having a common base. ', '', '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(501, 210, 6, 'The vertical distance between shapes shall be at least 1.5 meters.', '', '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(502, 210, 7, 'The frequency of a whistle shall be as required by Table 86.05. ', '', '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(503, 210, 8, 'The whistle shall be installed with it\'s forward axis directed forward and placed as high \r\nas practicable.', '', '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(504, 211, 1, 'Anchor sized in accordance with industry standards or as required by OCMI.', '', '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(505, 211, 2, 'Ensure all anchor releasing and retrieval equipment is operable and in good working \r\ncondition (line/chain, winch/davit or windlass foundation, stopper, brake). ', '', '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(506, 211, 3, 'Anchor winch or windlass should be tested to let out and retrieve chain.', '', '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(507, 212, 1, 'Bits, cleats and fairleads not excessively corroded or grooved; no scale build-up.', '', '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(508, 212, 2, 'Cleat/bit horns not missing, broken or excessively.', '', '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(509, 212, 3, 'Foundations not fractured.', '', '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(510, 212, 4, 'All guy wires taut, no slack; turnbuckles, wire rope not wasted.', '', '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(511, 213, 1, 'Ensure electrical cable and piping penetrations maintain watertight integrity i. e. stuffing \r\ntubes still serviceable.', '', '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(512, 213, 2, 'If sealant is used in penetrations, it must be a non-flammable product designed for \r\nsuch use. ', '', '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(513, 214, 1, 'Verify operation of all remote fuel shutoff valves. Ensure markings on the weather deck \r\nare legible and unobstructed.', '', '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(514, 214, 2, 'Ensure all valves adequately lubricated and operate freely. ', '', '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(515, 214, 3, 'Operate each reach rod and other manual remote control mechanisms function \r\nproperly.', '', '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(516, 214, 4, 'Verify each power operated valve operates properly from control station.', '', '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(517, 215, 1, 'Ensure all dogs are properly lubricated and operate freely.', '', '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(518, 215, 2, 'Ensure all gaskets are in place and clean. (i.e., free of paint, not deteriorated.)', '', '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(519, 215, 3, 'Ensure all knife edges are clean and free of nicks and paint.', '', '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(520, 215, 4, 'Ensure hinges and bolts are in good condition; no sagging of door due to worn hinge \r\nbolts.', '', '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(521, 215, 5, 'Ensure dogging wedges are not excessively worn.', '', '2025-05-27 04:19:20', '2025-05-27 04:19:20'),
(522, 215, 6, 'Ensure all hatches have retaining devices.', '', '2025-05-27 04:19:20', '2025-05-27 04:19:20');

-- --------------------------------------------------------

--
-- Table structure for table `vessel_icr_step_status`
--

CREATE TABLE `vessel_icr_step_status` (
  `id` int NOT NULL,
  `run_id` int NOT NULL,
  `vessel_icr_step_id` int NOT NULL,
  `status` enum('pass','fail','n/a') DEFAULT 'n/a',
  `comment` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `vessel_icr_step_status`
--

INSERT INTO `vessel_icr_step_status` (`id`, `run_id`, `vessel_icr_step_id`, `status`, `comment`, `created_at`) VALUES
(1, 15, 143, 'pass', '', '2025-05-04 22:10:28'),
(2, 15, 144, 'pass', '', '2025-05-04 22:10:28'),
(3, 15, 145, 'fail', 'cert expired', '2025-05-04 22:10:28'),
(4, 15, 146, 'n/a', '', '2025-05-04 22:10:28'),
(5, 15, 147, 'pass', '', '2025-05-04 22:10:28'),
(6, 15, 148, 'pass', '', '2025-05-04 22:10:28'),
(7, 16, 149, 'pass', '', '2025-05-04 22:13:34'),
(8, 16, 150, 'fail', 'Old publication', '2025-05-04 22:13:34'),
(9, 16, 151, 'pass', '', '2025-05-04 22:13:34'),
(10, 16, 152, 'pass', '', '2025-05-04 22:13:34'),
(11, 16, 153, 'pass', '', '2025-05-04 22:13:34'),
(12, 16, 154, 'pass', '', '2025-05-04 22:13:34'),
(13, 16, 155, 'pass', '', '2025-05-04 22:13:34'),
(14, 17, 156, 'fail', 'equipment servicing certificate passed due', '2025-05-04 22:28:57'),
(15, 18, 173, 'pass', '', '2025-05-04 22:30:02'),
(16, 18, 174, 'pass', '', '2025-05-04 22:30:02'),
(17, 18, 175, 'fail', '', '2025-05-04 22:30:02'),
(18, 18, 176, 'pass', '', '2025-05-04 22:30:02'),
(19, 18, 177, 'pass', '', '2025-05-04 22:30:02'),
(20, 18, 178, 'pass', '', '2025-05-04 22:30:02'),
(21, 18, 179, 'fail', '', '2025-05-04 22:30:02'),
(22, 18, 180, 'pass', '', '2025-05-04 22:30:02'),
(23, 18, 181, 'pass', '', '2025-05-04 22:30:02'),
(24, 18, 182, 'pass', '', '2025-05-04 22:30:02'),
(25, 19, 183, 'pass', '', '2025-05-04 22:34:10'),
(26, 19, 184, 'pass', '', '2025-05-04 22:34:10'),
(27, 19, 185, 'fail', 'Stenciling not legible', '2025-05-04 22:34:10'),
(28, 19, 186, 'pass', '', '2025-05-04 22:34:10'),
(29, 19, 187, 'pass', '', '2025-05-04 22:34:10'),
(30, 19, 188, 'n/a', '', '2025-05-04 22:34:10'),
(31, 20, 189, 'pass', '', '2025-05-04 22:34:42'),
(32, 20, 190, 'pass', '', '2025-05-04 22:34:42'),
(33, 20, 191, 'pass', '', '2025-05-04 22:34:42'),
(34, 20, 192, 'pass', '', '2025-05-04 22:34:42'),
(35, 20, 193, 'fail', 'stenciling not legible', '2025-05-04 22:34:42'),
(36, 20, 194, 'pass', '', '2025-05-04 22:34:42'),
(37, 20, 195, 'pass', '', '2025-05-04 22:34:42'),
(38, 21, 196, 'pass', '', '2025-05-04 22:45:06'),
(39, 21, 197, 'pass', '', '2025-05-04 22:45:06'),
(40, 21, 198, 'pass', '', '2025-05-04 22:45:06'),
(41, 21, 199, 'fail', 'markings not legible', '2025-05-04 22:45:06'),
(42, 21, 200, 'fail', 'instructions not posted', '2025-05-04 22:45:06'),
(43, 21, 201, 'pass', '', '2025-05-04 22:45:06'),
(44, 21, 202, 'pass', '', '2025-05-04 22:45:06'),
(45, 21, 203, 'pass', '', '2025-05-04 22:45:06'),
(46, 21, 204, 'fail', 'engine shutdowns did not function', '2025-05-04 22:45:06'),
(47, 21, 205, 'pass', '', '2025-05-04 22:45:06'),
(48, 22, 183, 'pass', '', '2025-05-04 22:45:34'),
(49, 22, 184, 'pass', '', '2025-05-04 22:45:34'),
(50, 22, 185, 'fail', 'test', '2025-05-04 22:45:34'),
(51, 22, 186, 'pass', '', '2025-05-04 22:45:34'),
(52, 22, 187, 'pass', '', '2025-05-04 22:45:34'),
(53, 22, 188, 'pass', '', '2025-05-04 22:45:34'),
(54, 23, 183, 'pass', '', '2025-05-04 23:10:11'),
(55, 23, 184, 'pass', '', '2025-05-04 23:10:11'),
(56, 23, 185, 'fail', 'stenciling not legible', '2025-05-04 23:10:11'),
(57, 23, 186, 'pass', '', '2025-05-04 23:10:11'),
(58, 23, 187, 'fail', 'ring buoy tied to vessel', '2025-05-04 23:10:11'),
(59, 23, 188, 'pass', '', '2025-05-04 23:10:11'),
(60, 24, 173, 'pass', '', '2025-05-04 23:13:42'),
(61, 24, 174, 'pass', '', '2025-05-04 23:13:42'),
(62, 24, 175, 'pass', '', '2025-05-04 23:13:42'),
(63, 24, 176, 'fail', 'test', '2025-05-04 23:13:42'),
(64, 24, 177, 'pass', '', '2025-05-04 23:13:42'),
(65, 24, 178, 'pass', '', '2025-05-04 23:13:42'),
(66, 24, 179, 'fail', 'test 2', '2025-05-04 23:13:42'),
(67, 24, 180, 'pass', '', '2025-05-04 23:13:42'),
(68, 24, 181, 'pass', '', '2025-05-04 23:13:42'),
(69, 24, 182, 'pass', '', '2025-05-04 23:13:42'),
(70, 25, 253, 'pass', '', '2025-05-05 03:07:16'),
(71, 25, 254, 'pass', '', '2025-05-05 03:07:16'),
(72, 25, 255, 'pass', '', '2025-05-05 03:07:16'),
(73, 25, 256, 'pass', '', '2025-05-05 03:07:16'),
(74, 25, 257, 'pass', '', '2025-05-05 03:07:17'),
(75, 25, 258, 'pass', '', '2025-05-05 03:07:17'),
(76, 25, 259, 'pass', '', '2025-05-05 03:07:17'),
(77, 25, 260, 'pass', '', '2025-05-05 03:07:17'),
(78, 25, 261, 'pass', '', '2025-05-05 03:07:17'),
(79, 25, 262, 'pass', '', '2025-05-05 03:07:17'),
(80, 26, 263, 'pass', '', '2025-05-06 05:01:17'),
(81, 26, 264, 'pass', '', '2025-05-06 05:01:17'),
(82, 26, 265, 'fail', 'flex hose is cracked', '2025-05-06 05:01:17'),
(83, 26, 266, 'pass', '', '2025-05-06 05:01:17'),
(84, 26, 267, 'pass', '', '2025-05-06 05:01:17'),
(85, 26, 268, 'pass', '', '2025-05-06 05:01:17'),
(86, 27, 269, 'pass', '', '2025-05-06 05:13:16'),
(87, 27, 270, 'pass', '', '2025-05-06 05:13:16'),
(88, 27, 271, 'pass', '', '2025-05-06 05:13:16'),
(89, 27, 272, 'pass', '', '2025-05-06 05:13:16'),
(90, 27, 273, 'fail', 'Hose failed hydrostatic test', '2025-05-06 05:13:16'),
(91, 27, 274, 'fail', 'Gauge does not read required pressure', '2025-05-06 05:13:16'),
(92, 27, 275, 'pass', '', '2025-05-06 05:13:16'),
(93, 27, 276, 'pass', '', '2025-05-06 05:13:16'),
(94, 27, 277, 'pass', '', '2025-05-06 05:13:16'),
(95, 28, 278, 'pass', '', '2025-05-06 05:41:19'),
(96, 28, 279, 'pass', '', '2025-05-06 05:41:19'),
(97, 28, 280, 'pass', '', '2025-05-06 05:41:19'),
(98, 28, 281, 'fail', 'Audible alarm not functional', '2025-05-06 05:41:19'),
(99, 28, 282, 'pass', '', '2025-05-06 05:41:19'),
(100, 28, 283, 'pass', '', '2025-05-06 05:41:19'),
(101, 29, 284, 'fail', 'Port fire damper inoperable', '2025-05-06 05:47:55'),
(102, 29, 285, 'pass', '', '2025-05-06 05:47:55'),
(103, 30, 286, 'pass', '', '2025-05-08 00:40:33'),
(104, 30, 287, 'pass', '', '2025-05-08 00:40:33'),
(105, 31, 230, 'pass', '', '2025-05-08 00:41:03'),
(106, 31, 231, 'pass', '', '2025-05-08 00:41:03'),
(107, 31, 232, 'fail', 'Name not stenciled.', '2025-05-08 00:41:03'),
(108, 31, 233, 'pass', '', '2025-05-08 00:41:03'),
(109, 31, 234, 'pass', '', '2025-05-08 00:41:03'),
(110, 31, 235, 'pass', '', '2025-05-08 00:41:03'),
(111, 32, 289, 'pass', '', '2025-05-08 01:57:51'),
(112, 32, 290, 'pass', '', '2025-05-08 01:57:51'),
(113, 32, 291, 'pass', '', '2025-05-08 01:57:51'),
(114, 32, 292, 'pass', '', '2025-05-08 01:57:51'),
(115, 32, 293, 'pass', '', '2025-05-08 01:57:51'),
(116, 32, 294, 'pass', '', '2025-05-08 01:57:51'),
(117, 33, 295, 'pass', '', '2025-05-08 01:58:18'),
(118, 33, 296, 'fail', 'Coast Pilot not on board', '2025-05-08 01:58:18'),
(119, 33, 297, 'pass', '', '2025-05-08 01:58:18'),
(120, 33, 298, 'pass', '', '2025-05-08 01:58:18'),
(121, 33, 299, 'pass', '', '2025-05-08 01:58:18'),
(122, 33, 300, 'pass', '', '2025-05-08 01:58:18'),
(123, 33, 301, 'pass', '', '2025-05-08 01:58:18'),
(124, 34, 286, 'fail', 'not enough buckets', '2025-05-13 07:42:03'),
(125, 34, 287, 'pass', '', '2025-05-13 07:42:03'),
(126, 35, 230, 'pass', '', '2025-05-22 19:04:34'),
(127, 35, 231, 'pass', '', '2025-05-22 19:04:34'),
(128, 35, 232, 'pass', '', '2025-05-22 19:04:34'),
(129, 35, 233, 'pass', '', '2025-05-22 19:04:34'),
(130, 35, 234, 'pass', '', '2025-05-22 19:04:34'),
(131, 35, 235, 'pass', '', '2025-05-22 19:04:34'),
(132, 36, 422, 'pass', '', '2025-05-27 04:20:20'),
(133, 36, 423, 'pass', '', '2025-05-27 04:20:20'),
(134, 36, 424, 'pass', '', '2025-05-27 04:20:20'),
(135, 36, 425, 'pass', '', '2025-05-27 04:20:20'),
(136, 36, 426, 'pass', '', '2025-05-27 04:20:20'),
(137, 36, 427, 'pass', '', '2025-05-27 04:20:20'),
(138, 37, 422, 'pass', '', '2025-05-27 04:20:20'),
(139, 37, 423, 'pass', '', '2025-05-27 04:20:20'),
(140, 37, 424, 'pass', '', '2025-05-27 04:20:20'),
(141, 37, 425, 'pass', '', '2025-05-27 04:20:20'),
(142, 37, 426, 'pass', '', '2025-05-27 04:20:20'),
(143, 37, 427, 'pass', '', '2025-05-27 04:20:20'),
(144, 38, 428, 'pass', '', '2025-05-27 04:21:23'),
(145, 38, 429, 'pass', '', '2025-05-27 04:21:23'),
(146, 38, 430, 'pass', '', '2025-05-27 04:21:23'),
(147, 38, 431, 'pass', '', '2025-05-27 04:21:23'),
(148, 38, 432, 'pass', '', '2025-05-27 04:21:23'),
(149, 38, 433, 'pass', '', '2025-05-27 04:21:23'),
(150, 38, 434, 'pass', '', '2025-05-27 04:21:23'),
(151, 39, 435, 'fail', 'two extinguishers deemed not serviceable', '2025-05-27 04:22:29');

-- --------------------------------------------------------

--
-- Table structure for table `vessel_owners`
--

CREATE TABLE `vessel_owners` (
  `id` int NOT NULL,
  `vessel_id` int DEFAULT NULL,
  `owner_id` int DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vessel_users`
--

CREATE TABLE `vessel_users` (
  `vessel_id` int NOT NULL,
  `user_id` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `crew_members`
--
ALTER TABLE `crew_members`
  ADD PRIMARY KEY (`crew_id`);

--
-- Indexes for table `documents`
--
ALTER TABLE `documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `vessel_id` (`vessel_id`),
  ADD KEY `crew_id` (`crew_id`),
  ADD KEY `equipment_id` (`equipment_id`),
  ADD KEY `uploaded_by` (`uploaded_by`);

--
-- Indexes for table `equipment`
--
ALTER TABLE `equipment`
  ADD PRIMARY KEY (`eid`),
  ADD KEY `fk_equipment_vessel` (`vessel_id`),
  ADD KEY `fk_equipment_category` (`category_id`),
  ADD KEY `fk_equipment_type` (`type_id`),
  ADD KEY `fk_equipment_subtype` (`subtype_id`);

--
-- Indexes for table `equipment_category`
--
ALTER TABLE `equipment_category`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `equipment_photos`
--
ALTER TABLE `equipment_photos`
  ADD PRIMARY KEY (`photo_id`);

--
-- Indexes for table `equipment_subtype`
--
ALTER TABLE `equipment_subtype`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_subtype_type` (`type_id`);

--
-- Indexes for table `equipment_type`
--
ALTER TABLE `equipment_type`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_type_category` (`category_id`);

--
-- Indexes for table `icrs`
--
ALTER TABLE `icrs`
  ADD PRIMARY KEY (`icr_id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `type_id` (`type_id`),
  ADD KEY `related_doc_id` (`related_doc_id`);

--
-- Indexes for table `icr_steps`
--
ALTER TABLE `icr_steps`
  ADD PRIMARY KEY (`step_id`),
  ADD KEY `icr_id` (`icr_id`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `owners`
--
ALTER TABLE `owners`
  ADD PRIMARY KEY (`owner_id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`role_id`),
  ADD UNIQUE KEY `role_name` (`role_name`);

--
-- Indexes for table `tasks`
--
ALTER TABLE `tasks`
  ADD PRIMARY KEY (`task_id`),
  ADD KEY `vessel_id` (`vessel_id`),
  ADD KEY `equipment_id` (`equipment_id`),
  ADD KEY `assigned_to` (`assigned_to`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_users_company` (`company_id`);

--
-- Indexes for table `vessels`
--
ALTER TABLE `vessels`
  ADD PRIMARY KEY (`vessel_id`),
  ADD KEY `fk_vessels_company` (`company_id`);

--
-- Indexes for table `vessel_crew`
--
ALTER TABLE `vessel_crew`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_crew_id` (`crew_id`),
  ADD KEY `fk_vessel_crew_vessels` (`vessel_id`);

--
-- Indexes for table `vessel_documents`
--
ALTER TABLE `vessel_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_vessel_documents_vessels` (`vessel_id`);

--
-- Indexes for table `vessel_icrs`
--
ALTER TABLE `vessel_icrs`
  ADD PRIMARY KEY (`vessel_icr_id`);

--
-- Indexes for table `vessel_icr_runs`
--
ALTER TABLE `vessel_icr_runs`
  ADD PRIMARY KEY (`run_id`),
  ADD KEY `vessel_id` (`vessel_id`),
  ADD KEY `icr_id` (`icr_id`);

--
-- Indexes for table `vessel_icr_steps`
--
ALTER TABLE `vessel_icr_steps`
  ADD PRIMARY KEY (`step_id`);

--
-- Indexes for table `vessel_icr_step_status`
--
ALTER TABLE `vessel_icr_step_status`
  ADD PRIMARY KEY (`id`),
  ADD KEY `run_id` (`run_id`),
  ADD KEY `vessel_icr_step_id` (`vessel_icr_step_id`);

--
-- Indexes for table `vessel_owners`
--
ALTER TABLE `vessel_owners`
  ADD PRIMARY KEY (`id`),
  ADD KEY `owner_id` (`owner_id`),
  ADD KEY `fk_vessel_owners_vessels` (`vessel_id`);

--
-- Indexes for table `vessel_users`
--
ALTER TABLE `vessel_users`
  ADD PRIMARY KEY (`vessel_id`,`user_id`),
  ADD KEY `fk_vessel_users_users` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `crew_members`
--
ALTER TABLE `crew_members`
  MODIFY `crew_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `documents`
--
ALTER TABLE `documents`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT for table `equipment`
--
ALTER TABLE `equipment`
  MODIFY `eid` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=165;

--
-- AUTO_INCREMENT for table `equipment_category`
--
ALTER TABLE `equipment_category`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `equipment_photos`
--
ALTER TABLE `equipment_photos`
  MODIFY `photo_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `equipment_subtype`
--
ALTER TABLE `equipment_subtype`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=98;

--
-- AUTO_INCREMENT for table `equipment_type`
--
ALTER TABLE `equipment_type`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `icrs`
--
ALTER TABLE `icrs`
  MODIFY `icr_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT for table `icr_steps`
--
ALTER TABLE `icr_steps`
  MODIFY `step_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=178;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=129;

--
-- AUTO_INCREMENT for table `owners`
--
ALTER TABLE `owners`
  MODIFY `owner_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `role_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `tasks`
--
ALTER TABLE `tasks`
  MODIFY `task_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=54;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `vessels`
--
ALTER TABLE `vessels`
  MODIFY `vessel_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `vessel_crew`
--
ALTER TABLE `vessel_crew`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `vessel_documents`
--
ALTER TABLE `vessel_documents`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `vessel_icrs`
--
ALTER TABLE `vessel_icrs`
  MODIFY `vessel_icr_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=216;

--
-- AUTO_INCREMENT for table `vessel_icr_runs`
--
ALTER TABLE `vessel_icr_runs`
  MODIFY `run_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- AUTO_INCREMENT for table `vessel_icr_steps`
--
ALTER TABLE `vessel_icr_steps`
  MODIFY `step_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=524;

--
-- AUTO_INCREMENT for table `vessel_icr_step_status`
--
ALTER TABLE `vessel_icr_step_status`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=152;

--
-- AUTO_INCREMENT for table `vessel_owners`
--
ALTER TABLE `vessel_owners`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `documents`
--
ALTER TABLE `documents`
  ADD CONSTRAINT `documents_ibfk_1` FOREIGN KEY (`vessel_id`) REFERENCES `vessels` (`vessel_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `documents_ibfk_2` FOREIGN KEY (`crew_id`) REFERENCES `crew_members` (`crew_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `documents_ibfk_3` FOREIGN KEY (`equipment_id`) REFERENCES `equipment` (`eid`) ON DELETE SET NULL,
  ADD CONSTRAINT `documents_ibfk_4` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `equipment`
--
ALTER TABLE `equipment`
  ADD CONSTRAINT `fk_category` FOREIGN KEY (`category_id`) REFERENCES `equipment_category` (`id`),
  ADD CONSTRAINT `fk_equipment_category` FOREIGN KEY (`category_id`) REFERENCES `equipment_category` (`id`),
  ADD CONSTRAINT `fk_equipment_subtype` FOREIGN KEY (`subtype_id`) REFERENCES `equipment_subtype` (`id`),
  ADD CONSTRAINT `fk_equipment_type` FOREIGN KEY (`type_id`) REFERENCES `equipment_type` (`id`),
  ADD CONSTRAINT `fk_equipment_vessel` FOREIGN KEY (`vessel_id`) REFERENCES `vessels` (`vessel_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_subtype` FOREIGN KEY (`subtype_id`) REFERENCES `equipment_subtype` (`id`),
  ADD CONSTRAINT `fk_type` FOREIGN KEY (`type_id`) REFERENCES `equipment_type` (`id`);

--
-- Constraints for table `equipment_subtype`
--
ALTER TABLE `equipment_subtype`
  ADD CONSTRAINT `equipment_subtype_ibfk_1` FOREIGN KEY (`type_id`) REFERENCES `equipment_type` (`id`),
  ADD CONSTRAINT `fk_subtype_type` FOREIGN KEY (`type_id`) REFERENCES `equipment_type` (`id`);

--
-- Constraints for table `equipment_type`
--
ALTER TABLE `equipment_type`
  ADD CONSTRAINT `equipment_type_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `equipment_category` (`id`),
  ADD CONSTRAINT `fk_type_category` FOREIGN KEY (`category_id`) REFERENCES `equipment_category` (`id`);

--
-- Constraints for table `icrs`
--
ALTER TABLE `icrs`
  ADD CONSTRAINT `icrs_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `equipment_category` (`id`),
  ADD CONSTRAINT `icrs_ibfk_2` FOREIGN KEY (`type_id`) REFERENCES `equipment_type` (`id`),
  ADD CONSTRAINT `icrs_ibfk_3` FOREIGN KEY (`related_doc_id`) REFERENCES `documents` (`id`);

--
-- Constraints for table `icr_steps`
--
ALTER TABLE `icr_steps`
  ADD CONSTRAINT `icr_steps_ibfk_1` FOREIGN KEY (`icr_id`) REFERENCES `icrs` (`icr_id`) ON DELETE CASCADE;

--
-- Constraints for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD CONSTRAINT `login_attempts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `tasks`
--
ALTER TABLE `tasks`
  ADD CONSTRAINT `fk_task_assigned_crew` FOREIGN KEY (`assigned_to`) REFERENCES `crew_members` (`crew_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `tasks_ibfk_1` FOREIGN KEY (`vessel_id`) REFERENCES `vessels` (`vessel_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tasks_ibfk_2` FOREIGN KEY (`equipment_id`) REFERENCES `equipment` (`eid`) ON DELETE SET NULL;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_company` FOREIGN KEY (`company_id`) REFERENCES `owners` (`owner_id`);

--
-- Constraints for table `vessels`
--
ALTER TABLE `vessels`
  ADD CONSTRAINT `fk_vessels_company` FOREIGN KEY (`company_id`) REFERENCES `owners` (`owner_id`);

--
-- Constraints for table `vessel_crew`
--
ALTER TABLE `vessel_crew`
  ADD CONSTRAINT `fk_crew_id` FOREIGN KEY (`crew_id`) REFERENCES `crew_members` (`crew_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_vessel_crew_vessels` FOREIGN KEY (`vessel_id`) REFERENCES `vessels` (`vessel_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `vessel_crew_ibfk_1` FOREIGN KEY (`vessel_id`) REFERENCES `vessels` (`vessel_id`);

--
-- Constraints for table `vessel_documents`
--
ALTER TABLE `vessel_documents`
  ADD CONSTRAINT `fk_vessel_documents_vessels` FOREIGN KEY (`vessel_id`) REFERENCES `vessels` (`vessel_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `vessel_documents_ibfk_1` FOREIGN KEY (`vessel_id`) REFERENCES `vessels` (`vessel_id`);

--
-- Constraints for table `vessel_icr_runs`
--
ALTER TABLE `vessel_icr_runs`
  ADD CONSTRAINT `vessel_icr_runs_ibfk_1` FOREIGN KEY (`vessel_id`) REFERENCES `vessels` (`vessel_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `vessel_icr_runs_ibfk_2` FOREIGN KEY (`icr_id`) REFERENCES `icrs` (`icr_id`) ON DELETE CASCADE;

--
-- Constraints for table `vessel_icr_step_status`
--
ALTER TABLE `vessel_icr_step_status`
  ADD CONSTRAINT `vessel_icr_step_status_ibfk_1` FOREIGN KEY (`run_id`) REFERENCES `vessel_icr_runs` (`run_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `vessel_icr_step_status_ibfk_2` FOREIGN KEY (`vessel_icr_step_id`) REFERENCES `vessel_icr_steps` (`step_id`) ON DELETE CASCADE;

--
-- Constraints for table `vessel_owners`
--
ALTER TABLE `vessel_owners`
  ADD CONSTRAINT `fk_vessel_owners_vessels` FOREIGN KEY (`vessel_id`) REFERENCES `vessels` (`vessel_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `vessel_owners_ibfk_1` FOREIGN KEY (`vessel_id`) REFERENCES `vessels` (`vessel_id`),
  ADD CONSTRAINT `vessel_owners_ibfk_2` FOREIGN KEY (`owner_id`) REFERENCES `owners` (`owner_id`);

--
-- Constraints for table `vessel_users`
--
ALTER TABLE `vessel_users`
  ADD CONSTRAINT `fk_vessel_users_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_vessel_users_vessels` FOREIGN KEY (`vessel_id`) REFERENCES `vessels` (`vessel_id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
