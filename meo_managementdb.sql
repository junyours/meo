-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Sep 02, 2026 at 06:53 AM
-- Server version: 8.0.30
-- PHP Version: 8.1.10

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `meo_managementdb`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` bigint UNSIGNED NOT NULL,
  `user_id` bigint UNSIGNED DEFAULT NULL,
  `user_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_role` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `module` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'system',
  `action` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `severity` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'info',
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `properties` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `activity_logs`
--

INSERT INTO `activity_logs` (`id`, `user_id`, `user_name`, `user_email`, `user_role`, `module`, `action`, `description`, `severity`, `ip_address`, `user_agent`, `properties`, `created_at`, `updated_at`) VALUES
(1, NULL, 'System Engine', NULL, 'system', 'system', 'initialize', 'Municipal Engineering Office Management System successfully initialized with database schemas.', 'success', '127.0.0.1', 'System Bootstrapper', '{\"version\": \"1.0.0\", \"environment\": \"local\"}', '2026-08-31 22:12:21', '2026-08-31 22:12:21'),
(2, 3, 'Super Administrator', 'superadmin@meo.local', 'superadmin', 'users', 'create', 'Official superadmin account for \'Super Administrator\' (superadmin@meo.local) was provisioned.', 'warning', '127.0.0.1', 'Administrative Console', '{\"role\": \"superadmin\", \"email\": \"superadmin@meo.local\", \"user_id\": 3}', '2026-08-31 22:12:22', '2026-08-31 22:12:22'),
(3, 4, 'Administrator', 'admin@meo.local', 'admin', 'users', 'create', 'Official admin account for \'Administrator\' (admin@meo.local) was provisioned.', 'info', '127.0.0.1', 'Administrative Console', '{\"role\": \"admin\", \"email\": \"admin@meo.local\", \"user_id\": 4}', '2026-08-31 22:12:22', '2026-08-31 22:12:22'),
(4, 5, 'Staff Member', 'staff@meo.local', 'staff', 'users', 'create', 'Official staff account for \'Staff Member\' (staff@meo.local) was provisioned.', 'info', '127.0.0.1', 'Administrative Console', '{\"role\": \"staff\", \"email\": \"staff@meo.local\", \"user_id\": 5}', '2026-08-31 22:12:22', '2026-08-31 22:12:22'),
(5, 7, 'Charlie Nipaya', 'charlienp@gmail.com', 'staff', 'users', 'create', 'Official staff account for \'Charlie Nipaya\' (charlienp@gmail.com) was provisioned.', 'info', '127.0.0.1', 'Administrative Console', '{\"role\": \"staff\", \"email\": \"charlienp@gmail.com\", \"user_id\": 7}', '2026-08-31 22:12:22', '2026-08-31 22:12:22'),
(6, 8, 'Joge Cyle Opena', 'jogecyle@meo.local', 'staff', 'users', 'create', 'Official staff account for \'Joge Cyle Opena\' (jogecyle@meo.local) was provisioned.', 'info', '127.0.0.1', 'Administrative Console', '{\"role\": \"staff\", \"email\": \"jogecyle@meo.local\", \"user_id\": 8}', '2026-08-31 22:12:22', '2026-08-31 22:12:22'),
(7, NULL, 'Superadmin Official', NULL, 'superadmin', 'projects', 'create', 'Infrastructure project record \'Joge Project\' was registered under LGSF (Status: Ongoing).', 'info', '127.0.0.1', 'MEO Project Management Portal', '{\"budget\": 1, \"status\": \"Ongoing\", \"location\": \"Opol\", \"project_id\": 5, \"project_name\": \"Joge Project\"}', '2026-08-31 22:12:22', '2026-08-31 22:12:22'),
(8, NULL, 'Superadmin Official', NULL, 'superadmin', 'projects', 'create', 'Infrastructure project record \'OCC Toilet\' was registered under LGU - Fund (Status: Ongoing).', 'info', '127.0.0.1', 'MEO Project Management Portal', '{\"budget\": 124000, \"status\": \"Ongoing\", \"location\": \"Opol Community College\", \"project_id\": 4, \"project_name\": \"OCC Toilet\"}', '2026-08-31 22:12:22', '2026-08-31 22:12:22'),
(9, NULL, 'Superadmin Official', NULL, 'superadmin', 'projects', 'create', 'Infrastructure project record \'Rehabilitation of Municipal Hall\' was registered under LGSF (Status: Ongoing).', 'info', '127.0.0.1', 'MEO Project Management Portal', '{\"budget\": 2000000, \"status\": \"Ongoing\", \"location\": \"Opol,Poblacion,Misamis Oriental\", \"project_id\": 3, \"project_name\": \"Rehabilitation of Municipal Hall\"}', '2026-08-31 22:12:22', '2026-08-31 22:12:22'),
(10, NULL, 'Gedeoni Ammiel N. Pairat', 'staff@meo.local', 'citizen', 'inquiries', 'submit', 'Public concern #MEO-20260901-7FCZ submitted by Gedeoni Ammiel N. Pairat for Opol: \'Spider\'', 'info', '127.0.0.1', 'Ask MEO Citizen Portal', '{\"subject\": \"Spider\", \"fullname\": \"Gedeoni Ammiel N. Pairat\", \"location\": \"Opol\", \"tracking_token\": \"MEO-20260901-7FCZ\"}', '2026-08-31 22:12:22', '2026-08-31 22:12:22'),
(11, NULL, 'Super Administrator', NULL, 'superadmin', 'inquiries', 'cancel', 'Cancellation of concern #MEO-20260901-7FCZ confirmed. Reason: No longer needed or applicable', 'warning', '127.0.0.1', 'Administrative Console', '{\"reason\": \"No longer needed or applicable\", \"tracking_token\": \"MEO-20260901-7FCZ\"}', '2026-08-31 22:12:22', '2026-08-31 22:12:22'),
(12, NULL, 'Gedeoni Ammiel N. Pairat', 'occ.pairat.gedeoniammiel@gmail.com', 'citizen', 'inquiries', 'submit', 'Public concern #MEO-20260901-G1G7 submitted by Gedeoni Ammiel N. Pairat for Igpit: \'Ask about spider man\'', 'info', '127.0.0.1', 'Ask MEO Citizen Portal', '{\"subject\": \"Ask about spider man\", \"fullname\": \"Gedeoni Ammiel N. Pairat\", \"location\": \"Igpit\", \"tracking_token\": \"MEO-20260901-G1G7\"}', '2026-08-31 22:12:22', '2026-08-31 22:12:22'),
(13, NULL, 'Super Administrator', NULL, 'superadmin', 'inquiries', 'cancel', 'Cancellation of concern #MEO-20260901-G1G7 confirmed. Reason: No longer needed or applicable', 'warning', '127.0.0.1', 'Administrative Console', '{\"reason\": \"No longer needed or applicable\", \"tracking_token\": \"MEO-20260901-G1G7\"}', '2026-08-31 22:12:22', '2026-08-31 22:12:22'),
(14, NULL, 'Joge Cyle Opena', NULL, 'citizen', 'inquiries', 'submit', 'Public concern #MEO-20260826-WSXH submitted by Joge Cyle Opena for Igpit: \'Naay Liki and dalan\'', 'info', '127.0.0.1', 'Ask MEO Citizen Portal', '{\"subject\": \"Naay Liki and dalan\", \"fullname\": \"Joge Cyle Opena\", \"location\": \"Igpit\", \"tracking_token\": \"MEO-20260826-WSXH\"}', '2026-08-31 22:12:22', '2026-08-31 22:12:22'),
(15, 4, 'Administrator', 'admin@meo.local', 'admin', 'inquiries', 'resolve', 'Concern #MEO-20260826-WSXH was resolved and closed by Administrator.', 'success', '127.0.0.1', 'Administrative Console', '{\"notes\": null, \"tracking_token\": \"MEO-20260826-WSXH\"}', '2026-08-31 22:12:22', '2026-08-31 22:12:22'),
(16, NULL, 'Chabertzieni', NULL, 'citizen', 'inquiries', 'submit', 'Public concern #MEO-20260826-BB2F submitted by Chabertzieni for Puntakan, Poblacion, Opol: \'Akoy naguguluhan\'', 'info', '127.0.0.1', 'Ask MEO Citizen Portal', '{\"subject\": \"Akoy naguguluhan\", \"fullname\": \"Chabertzieni\", \"location\": \"Puntakan, Poblacion, Opol\", \"tracking_token\": \"MEO-20260826-BB2F\"}', '2026-08-31 22:12:22', '2026-08-31 22:12:22'),
(17, 4, 'Administrator', 'admin@meo.local', 'admin', 'inquiries', 'resolve', 'Concern #MEO-20260826-BB2F was resolved and closed by Administrator.', 'success', '127.0.0.1', 'Administrative Console', '{\"notes\": \"Kuan na lala\", \"tracking_token\": \"MEO-20260826-BB2F\"}', '2026-08-31 22:12:22', '2026-08-31 22:12:22'),
(18, NULL, 'Joge Cyle Opena', 'samplegmail@gmail.com', 'citizen', 'inquiries', 'submit', 'Public concern #MEO-20260824-XC0P submitted by Joge Cyle Opena for Igpit: \'Naay Liki and dalan\'', 'info', '127.0.0.1', 'Ask MEO Citizen Portal', '{\"subject\": \"Naay Liki and dalan\", \"fullname\": \"Joge Cyle Opena\", \"location\": \"Igpit\", \"tracking_token\": \"MEO-20260824-XC0P\"}', '2026-08-31 22:12:22', '2026-08-31 22:12:22'),
(19, 3, 'Super Administrator', 'superadmin@meo.local', 'superadmin', 'inquiries', 'resolve', 'Concern #MEO-20260824-XC0P was resolved and closed by Super Administrator.', 'success', '127.0.0.1', 'Administrative Console', '{\"notes\": null, \"tracking_token\": \"MEO-20260824-XC0P\"}', '2026-08-31 22:12:22', '2026-08-31 22:12:22'),
(20, 3, 'Super Administrator', 'superadmin@meo.local', 'superadmin', 'system', 'export', 'Superadmin exported system audit activity logs to CSV.', 'info', '10.0.0.44', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '{\"file_name\": \"meo_activity_logs_2026-09-01_061245.csv\"}', '2026-08-31 22:12:45', '2026-08-31 22:12:45'),
(21, 3, 'Super Administrator', 'superadmin@meo.local', 'superadmin', 'auth', 'logout', 'User \'Super Administrator\' (superadmin@meo.local) logged out of the session.', 'info', '10.0.0.44', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '{\"role\": \"superadmin\", \"user_id\": 3}', '2026-09-01 00:56:56', '2026-09-01 00:56:56');

-- --------------------------------------------------------

--
-- Table structure for table `bulletins`
--

CREATE TABLE `bulletins` (
  `id` bigint UNSIGNED NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `summary` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_public` tinyint(1) NOT NULL DEFAULT '1',
  `is_archived` tinyint(1) NOT NULL DEFAULT '0',
  `archived_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `bulletins`
--

INSERT INTO `bulletins` (`id`, `title`, `category`, `summary`, `is_public`, `is_archived`, `archived_at`, `created_at`, `updated_at`) VALUES
(1, 'Sample Notice', 'Notice', 'Hellooo', 1, 0, NULL, '2026-08-17 19:07:06', '2026-08-17 19:07:06');

-- --------------------------------------------------------

--
-- Table structure for table `document_ai_analysis_tb`
--

CREATE TABLE `document_ai_analysis_tb` (
  `id` bigint UNSIGNED NOT NULL,
  `document_id` bigint UNSIGNED NOT NULL,
  `ai_classification` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ai_confidence` decimal(5,2) DEFAULT NULL,
  `ai_tags` json DEFAULT NULL,
  `ai_entities` json DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `document_blockchain_verification_tb`
--

CREATE TABLE `document_blockchain_verification_tb` (
  `id` bigint UNSIGNED NOT NULL,
  `document_id` bigint UNSIGNED NOT NULL,
  `blockchain_tx_hash` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `blockchain_verified_at` timestamp NULL DEFAULT NULL,
  `blockchain_verified` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `document_digital_signatures_tb`
--

CREATE TABLE `document_digital_signatures_tb` (
  `id` bigint UNSIGNED NOT NULL,
  `document_id` bigint UNSIGNED NOT NULL,
  `digital_signature` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `signed_by` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `signed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `document_ocr_results_tb`
--

CREATE TABLE `document_ocr_results_tb` (
  `id` bigint UNSIGNED NOT NULL,
  `document_id` bigint UNSIGNED NOT NULL,
  `extracted_text` longtext COLLATE utf8mb4_unicode_ci,
  `ocr_confidence` decimal(5,2) DEFAULT NULL,
  `ocr_language` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'eng'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `document_scanner_tb`
--

CREATE TABLE `document_scanner_tb` (
  `id` bigint UNSIGNED NOT NULL,
  `project_id` bigint UNSIGNED DEFAULT NULL,
  `techprep_id` bigint UNSIGNED DEFAULT NULL,
  `uploaded_by` bigint UNSIGNED DEFAULT NULL,
  `parent_document_id` bigint UNSIGNED DEFAULT NULL,
  `document_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `document_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pdf',
  `file_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_size` bigint UNSIGNED NOT NULL DEFAULT '0',
  `page_number` int UNSIGNED DEFAULT NULL,
  `page_count` int UNSIGNED DEFAULT NULL,
  `resolution` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `color_mode` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `version` int UNSIGNED NOT NULL DEFAULT '1',
  `processing_status` enum('pending','processing','completed','failed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `processing_error` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `scan_device` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `scan_software` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `scan_ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `scan_location` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `access_permissions` json DEFAULT NULL,
  `is_public` tinyint(1) NOT NULL DEFAULT '0',
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `document_scanner_tb`
--

INSERT INTO `document_scanner_tb` (`id`, `project_id`, `techprep_id`, `uploaded_by`, `parent_document_id`, `document_name`, `document_type`, `file_path`, `file_hash`, `file_size`, `page_number`, `page_count`, `resolution`, `color_mode`, `version`, `processing_status`, `processing_error`, `scan_device`, `scan_software`, `scan_ip`, `scan_location`, `access_permissions`, `is_public`, `expires_at`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 4, NULL, 7, NULL, 'IMG_6850 (new).jpeg', 'jpeg', 'documents/26eecd55-fdb3-4a49-bc58-383aa953b829.jpeg', 'ed0535abaacd0f24346afccf9da7155ed599fc0133b97622db4877be64543c82', 2966082, 1, NULL, NULL, NULL, 1, 'completed', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', NULL, '127.0.0.1', NULL, NULL, 0, NULL, '2026-08-19 22:04:15', '2026-08-19 22:04:15', NULL),
(2, 4, NULL, 7, NULL, 'LGU_OPOL.pdf', 'pdf', 'documents/77a7757b-748e-43cd-b272-e1260e4eab0a.pdf', 'e52ccd34a78ad556bdccebe3411c1b7d42185c9e2928500a7115159920ae481b', 282640, 2, NULL, NULL, NULL, 1, 'completed', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', NULL, '127.0.0.1', NULL, NULL, 0, NULL, '2026-08-19 22:04:40', '2026-08-19 22:04:40', NULL),
(3, 3, NULL, 4, NULL, 'Scanned_Page_1.jpg', 'jpg', 'documents/3f4b290c-f717-4d56-b41f-1a1b78e1cc96.jpg', '2e297c315558e0df9e958a7b96114c94bb0fd1289caebb4b8c3a59084d093c5b', 246428, 1, NULL, NULL, NULL, 1, 'completed', NULL, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Mobile Safari/537.36', NULL, '175.176.84.208', NULL, NULL, 0, NULL, '2026-08-23 17:55:25', '2026-08-23 17:55:25', NULL),
(4, 3, NULL, 4, NULL, 'Scanned_Page_2.jpg', 'jpg', 'documents/dadff519-3110-4a80-b3ae-1030ad29e2c4.jpg', 'fe11fb29c209c98995b21cd9f6cd39276ddff68242b43595c284e8edbbfd7751', 61287, 2, NULL, NULL, NULL, 1, 'completed', NULL, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Mobile Safari/537.36', NULL, '175.176.84.208', NULL, NULL, 0, NULL, '2026-08-23 17:57:57', '2026-08-23 17:57:57', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `failed_jobs`
--

CREATE TABLE `failed_jobs` (
  `id` bigint UNSIGNED NOT NULL,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `infra_audit_tb`
--

CREATE TABLE `infra_audit_tb` (
  `id` bigint UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `project_id` bigint UNSIGNED NOT NULL,
  `form1` int NOT NULL,
  `form2a` int NOT NULL,
  `form2b` int NOT NULL,
  `status` tinyint UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inquiries_tb`
--

CREATE TABLE `inquiries_tb` (
  `id` bigint UNSIGNED NOT NULL,
  `tracking_token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `fullname` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `location` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `photo_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `photos` json DEFAULT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `admin_notes` text COLLATE utf8mb4_unicode_ci,
  `cancellation_reason` text COLLATE utf8mb4_unicode_ci,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `accepted_at` timestamp NULL DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `accepted_by` bigint UNSIGNED DEFAULT NULL,
  `resolved_by` bigint UNSIGNED DEFAULT NULL,
  `updated_by` bigint UNSIGNED DEFAULT NULL,
  `cancelled_by` bigint UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `inquiries_tb`
--

INSERT INTO `inquiries_tb` (`id`, `tracking_token`, `fullname`, `phone`, `email`, `location`, `subject`, `message`, `photo_path`, `photos`, `status`, `admin_notes`, `cancellation_reason`, `cancelled_at`, `accepted_at`, `resolved_at`, `created_at`, `updated_at`, `accepted_by`, `resolved_by`, `updated_by`, `cancelled_by`) VALUES
(3, 'MEO-20260824-XC0P', 'Joge Cyle Opena', '097287324873', 'samplegmail@gmail.com', 'Igpit', 'Naay Liki and dalan', 'Need action lang ana ra.', 'inquiries/cp8BIKR9uV42yUwkhdSspYVCEynCcIgzkbhlT7Us.jpg', '[\"inquiries/cp8BIKR9uV42yUwkhdSspYVCEynCcIgzkbhlT7Us.jpg\", \"inquiries/sKzEP1IorELVfGNMPuvW978NTnDEG971oi8EPzme.jpg\", \"inquiries/AyccCvx7rv0AAYmj9WJ5k1nc5KNEhex2ktUxYa2F.jpg\"]', 'resolved', NULL, NULL, NULL, '2026-08-23 23:37:04', '2026-08-23 23:37:32', '2026-08-23 23:36:30', '2026-08-23 23:37:32', 3, 3, 3, NULL),
(4, 'MEO-20260826-BB2F', 'Chabertzieni', '09084444369', NULL, 'Puntakan, Poblacion, Opol', 'Akoy naguguluhan', 'Ano po ba ang Radyo aktibong kaka? Tenks.', NULL, '[]', 'resolved', 'Kuan na lala', NULL, NULL, '2026-08-25 23:51:49', '2026-08-25 23:53:23', '2026-08-25 23:50:17', '2026-08-25 23:53:23', 4, 4, 4, NULL),
(5, 'MEO-20260826-WSXH', 'Joge Cyle Opena', '097287324873', NULL, 'Igpit', 'Naay Liki and dalan', 'Ano ano ano ano ano', NULL, '[]', 'resolved', NULL, NULL, NULL, '2026-08-26 00:11:02', '2026-08-26 00:11:10', '2026-08-26 00:10:46', '2026-08-26 00:11:10', 4, 4, 4, NULL),
(6, 'MEO-20260901-G1G7', 'Gedeoni Ammiel N. Pairat', '09706209298', 'occ.pairat.gedeoniammiel@gmail.com', 'Igpit', 'Ask about spider man', 'Pew pew spider man', NULL, '[]', 'cancelled', NULL, 'No longer needed or applicable', '2026-08-31 21:37:28', NULL, NULL, '2026-08-31 21:34:46', '2026-08-31 21:37:28', NULL, NULL, 3, 3),
(7, 'MEO-20260901-7FCZ', 'Gedeoni Ammiel N. Pairat', '+1 (555) 123-4567', 'staff@meo.local', 'Opol', 'Spider', 'spaydey', NULL, '[]', 'cancelled', NULL, 'No longer needed or applicable', '2026-08-31 21:48:47', NULL, NULL, '2026-08-31 21:47:51', '2026-08-31 21:48:47', NULL, NULL, 3, 3);

-- --------------------------------------------------------

--
-- Table structure for table `migrations`
--

CREATE TABLE `migrations` (
  `id` int UNSIGNED NOT NULL,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `migrations`
--

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
(1, '2014_10_12_000000_create_users_table', 1),
(2, '2014_10_12_100000_create_password_reset_tokens_table', 1),
(3, '2019_08_19_000000_create_failed_jobs_table', 1),
(4, '2019_12_14_000001_create_personal_access_tokens_table', 1),
(5, '2024_06_03_000000_add_role_to_users_table', 1),
(6, '2026_06_24_083258_create_project_tb_table', 1),
(7, '2026_06_24_085140_create_tech_prep_tb_table', 1),
(8, '2026_06_24_090711_create_project_fund_type_tb_table', 1),
(9, '2026_06_24_091743_create_remarks_tb_table', 1),
(10, '2026_06_24_092125_create_pow_prep_tb_table', 1),
(11, '2026_06_24_092525_create_infra_audit_tb_table', 1),
(12, '2026_07_06_000001_update_project_fund_type_to_provincial', 1),
(13, '2026_07_13_071112_create_document_scanner_tb_table', 1),
(14, '2026_07_13_071113_create_document_ocr_results_tb_table', 1),
(15, '2026_07_13_071114_create_document_ai_analysis_tb_table', 1),
(16, '2026_07_13_071115_create_document_blockchain_verification_tb_table', 1),
(17, '2026_07_13_071116_create_document_digital_signatures_tb_table', 1),
(18, '2026_07_30_080023_add_overview_fields_to_project_tb_table', 1),
(19, '2026_07_31_000000_make_actual_completion_date_nullable', 1),
(20, '2026_07_31_000001_add_page_number_to_document_scanner_tb', 1),
(21, '2026_08_04_000000_create_bulletins_table', 1),
(22, '2026_08_04_000001_create_reminders_table', 1),
(23, '2026_08_04_000002_add_completion_fields_to_reminders_table', 1),
(24, '2026_08_10_021413_create_welcome_contents_table', 1),
(25, '2026_08_10_022537_add_slideshow_images_to_welcome_contents_table', 1),
(26, '2026_08_18_000000_add_remarks_to_tech_prep_tb_table', 1),
(27, '2026_08_18_000001_add_notes_to_tech_prep_tb_table', 1),
(28, '2026_08_18_000002_add_achievement_images_to_welcome_contents_table', 2),
(29, '2026_08_18_000003_create_staff_assignments_table', 3),
(30, '2026_08_19_000000_add_staff_reply_to_staff_assignments_table', 4),
(31, '2026_08_19_000001_add_profile_photo_path_to_users_table', 5),
(32, '2026_08_24_000000_create_inquiries_table', 6),
(33, '2026_08_24_000001_add_photos_to_inquiries_table', 7),
(34, '2026_08_24_000002_add_user_tracking_to_inquiries_table', 8),
(35, '2026_08_25_000000_add_conversation_to_staff_assignments_table', 9),
(36, '2026_09_01_000000_add_cancellation_fields_to_inquiries_table', 10),
(37, '2026_09_01_010000_create_activity_logs_table', 11);

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `personal_access_tokens`
--

CREATE TABLE `personal_access_tokens` (
  `id` bigint UNSIGNED NOT NULL,
  `tokenable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tokenable_id` bigint UNSIGNED NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `abilities` text COLLATE utf8mb4_unicode_ci,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pow_prep_tb`
--

CREATE TABLE `pow_prep_tb` (
  `id` bigint UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `project_id` bigint UNSIGNED NOT NULL,
  `project_cost` int NOT NULL,
  `office_concern` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` tinyint UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `project_fund_type_tb`
--

CREATE TABLE `project_fund_type_tb` (
  `id` bigint UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `fund_type` enum('National','Provincial','LGU') COLLATE utf8mb4_unicode_ci NOT NULL,
  `fund_source` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `project_id` bigint UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `project_fund_type_tb`
--

INSERT INTO `project_fund_type_tb` (`id`, `created_at`, `updated_at`, `fund_type`, `fund_source`, `project_id`) VALUES
(1, '2026-08-17 17:15:17', '2026-08-17 17:15:17', 'Provincial', 'LGSF', 3),
(2, '2026-08-18 22:48:18', '2026-08-18 22:48:18', 'LGU', 'LGU - Fund', 4),
(3, '2026-08-23 19:29:36', '2026-08-23 19:29:36', 'Provincial', 'LGSF', 5);

-- --------------------------------------------------------

--
-- Table structure for table `project_tb`
--

CREATE TABLE `project_tb` (
  `id` bigint UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `project_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `location` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_project_cost` double(10,2) NOT NULL,
  `original_cost` decimal(15,2) DEFAULT NULL,
  `revised_cost` decimal(15,2) DEFAULT NULL,
  `project_description` text COLLATE utf8mb4_unicode_ci,
  `source_of_fund` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `year` int NOT NULL,
  `project_duration` int NOT NULL,
  `start_date` date NOT NULL,
  `target_completion_date` date NOT NULL,
  `actual_completion_date` date DEFAULT NULL,
  `revised_completion_date` date DEFAULT NULL,
  `time_extention` int NOT NULL,
  `days_suspension_order` int NOT NULL DEFAULT '0',
  `percentage_of_accomplishment` decimal(5,2) NOT NULL,
  `contractor` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` tinyint UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `project_tb`
--

INSERT INTO `project_tb` (`id`, `created_at`, `updated_at`, `project_name`, `location`, `total_project_cost`, `original_cost`, `revised_cost`, `project_description`, `source_of_fund`, `year`, `project_duration`, `start_date`, `target_completion_date`, `actual_completion_date`, `revised_completion_date`, `time_extention`, `days_suspension_order`, `percentage_of_accomplishment`, `contractor`, `status`) VALUES
(3, '2026-08-17 17:15:17', '2026-08-18 22:26:35', 'Rehabilitation of Municipal Hall', 'Opol,Poblacion,Misamis Oriental', 2000000.00, '2000000.00', '2000000.00', 'This is just a sample Project Description.', 'LGSF', 2026, 245, '2025-02-03', '2026-02-05', NULL, NULL, 0, 0, '93.70', 'MLS', 0),
(4, '2026-08-18 22:48:18', '2026-08-18 22:49:42', 'OCC Toilet', 'Opol Community College', 124000.00, '124000.00', NULL, 'Sample description of Opol Community College Project.', 'LGU - Fund', 2026, 256, '2026-07-14', '2027-08-31', NULL, NULL, 0, 0, '25.00', 'Primax', 0),
(5, '2026-08-23 19:29:36', '2026-08-23 19:29:36', 'Joge Project', 'Opol', 1.00, NULL, NULL, NULL, 'LGSF', 2026, 126, '2026-08-01', '2026-08-31', NULL, NULL, 0, 0, '0.00', 'GED', 0);

-- --------------------------------------------------------

--
-- Table structure for table `remarks_tb`
--

CREATE TABLE `remarks_tb` (
  `id` bigint UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `project_id` bigint UNSIGNED NOT NULL,
  `remark` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `remarks_tb`
--

INSERT INTO `remarks_tb` (`id`, `created_at`, `updated_at`, `project_id`, `remark`) VALUES
(1, '2026-08-17 17:15:17', '2026-08-17 17:15:17', 3, 'This is just a sample Project Remarks.'),
(2, '2026-08-18 22:48:18', '2026-08-18 22:48:18', 4, 'Sample Remarks.');

-- --------------------------------------------------------

--
-- Table structure for table `reminders`
--

CREATE TABLE `reminders` (
  `id` bigint UNSIGNED NOT NULL,
  `user_id` bigint UNSIGNED NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `starts_at` datetime NOT NULL,
  `ends_at` datetime DEFAULT NULL,
  `location` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `audience` enum('personal','everyone') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'personal',
  `is_done` tinyint(1) NOT NULL DEFAULT '0',
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `reminders`
--

INSERT INTO `reminders` (`id`, `user_id`, `title`, `category`, `description`, `starts_at`, `ends_at`, `location`, `audience`, `is_done`, `completed_at`, `created_at`, `updated_at`) VALUES
(1, 7, 'MEETING', 'Meeting', 'Kuan', '2026-08-20 15:35:00', NULL, 'Apple Tree', 'personal', 1, '2026-08-20 00:00:42', '2026-08-18 23:35:29', '2026-08-20 00:00:42'),
(2, 4, 'Inspection', 'Inspection', 'NEED.', '2026-09-01 15:53:00', NULL, 'Malanang', 'everyone', 0, NULL, '2026-08-18 23:53:54', '2026-08-18 23:53:54');

-- --------------------------------------------------------

--
-- Table structure for table `staff_assignments`
--

CREATE TABLE `staff_assignments` (
  `id` bigint UNSIGNED NOT NULL,
  `user_id` bigint UNSIGNED NOT NULL,
  `assigned_by` bigint UNSIGNED DEFAULT NULL,
  `project_id` bigint UNSIGNED DEFAULT NULL,
  `type` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'assignment',
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `note` text COLLATE utf8mb4_unicode_ci,
  `staff_reply` text COLLATE utf8mb4_unicode_ci,
  `staff_replied_at` timestamp NULL DEFAULT NULL,
  `conversation` json DEFAULT NULL,
  `role_in_project` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `target_deadline` date DEFAULT NULL,
  `priority` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'normal',
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `staff_assignments`
--

INSERT INTO `staff_assignments` (`id`, `user_id`, `assigned_by`, `project_id`, `type`, `title`, `note`, `staff_reply`, `staff_replied_at`, `conversation`, `role_in_project`, `target_deadline`, `priority`, `status`, `completed_at`, `created_at`, `updated_at`) VALUES
(1, 5, 4, 3, 'assignment', 'Assignment: Rehabilitation of Municipal Hall', 'gfhfgh', 'Currently under going.', '2026-08-18 22:36:49', NULL, 'Project Inspector', '2026-08-22', 'high', 'in_progress', NULL, '2026-08-18 00:19:53', '2026-08-18 22:36:49'),
(2, 5, 4, NULL, 'note', 'priority ni', 'cascascasc', NULL, NULL, NULL, NULL, NULL, 'normal', 'pending', NULL, '2026-08-18 00:20:15', '2026-08-18 00:20:15'),
(3, 7, 4, 4, 'assignment', 'Assignment: OCC Toilet', 'CHARLIEEE!!!', 'HELLOO!', '2026-08-18 22:50:09', NULL, 'Site Engineer', '2026-08-29', 'normal', 'in_progress', NULL, '2026-08-18 22:49:27', '2026-08-18 22:50:09'),
(4, 5, 4, NULL, 'note', 'OCC POW', 'Kuan', NULL, NULL, NULL, NULL, NULL, 'normal', 'pending', NULL, '2026-08-18 22:51:17', '2026-08-18 22:51:17'),
(5, 7, 4, NULL, 'note', 'POW', 'sadsadsa', NULL, NULL, NULL, NULL, NULL, 'normal', 'pending', NULL, '2026-08-18 22:52:32', '2026-08-18 22:52:32'),
(6, 8, 3, 5, 'assignment', 'Assignment: Joge Project', 'Hello', '1', '2026-08-23 19:33:06', NULL, 'Site Engineer', NULL, 'normal', 'in_progress', NULL, '2026-08-23 19:30:55', '2026-08-23 19:33:06'),
(7, 8, 3, NULL, 'note', 'POW', 'Kamusta ang POW', 'humana po', '2026-08-23 19:35:32', NULL, NULL, NULL, 'normal', 'pending', NULL, '2026-08-23 19:35:01', '2026-08-23 19:35:32');

-- --------------------------------------------------------

--
-- Table structure for table `tech_prep_tb`
--

CREATE TABLE `tech_prep_tb` (
  `id` bigint UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `project_id` bigint UNSIGNED NOT NULL,
  `hazard_assessment_status` tinyint UNSIGNED DEFAULT NULL,
  `hazard_assessment_notes` text COLLATE utf8mb4_unicode_ci,
  `pow_ded_status` tinyint UNSIGNED DEFAULT NULL,
  `pow_ded_notes` text COLLATE utf8mb4_unicode_ci,
  `supplementary_budget_status` tinyint UNSIGNED DEFAULT NULL,
  `supplementary_budget_notes` text COLLATE utf8mb4_unicode_ci,
  `alobs_status` tinyint UNSIGNED DEFAULT NULL,
  `alobs_notes` text COLLATE utf8mb4_unicode_ci,
  `ecc_cnc_status` tinyint UNSIGNED DEFAULT NULL,
  `ecc_cnc_notes` text COLLATE utf8mb4_unicode_ci,
  `submission_tech_docs_status` tinyint UNSIGNED DEFAULT NULL,
  `submission_tech_docs_notes` text COLLATE utf8mb4_unicode_ci,
  `bidding_status` tinyint UNSIGNED DEFAULT NULL,
  `bidding_notes` text COLLATE utf8mb4_unicode_ci,
  `contract_ntp_status` tinyint UNSIGNED DEFAULT NULL,
  `contract_ntp_notes` text COLLATE utf8mb4_unicode_ci,
  `remarks` text COLLATE utf8mb4_unicode_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tech_prep_tb`
--

INSERT INTO `tech_prep_tb` (`id`, `created_at`, `updated_at`, `project_id`, `hazard_assessment_status`, `hazard_assessment_notes`, `pow_ded_status`, `pow_ded_notes`, `supplementary_budget_status`, `supplementary_budget_notes`, `alobs_status`, `alobs_notes`, `ecc_cnc_status`, `ecc_cnc_notes`, `submission_tech_docs_status`, `submission_tech_docs_notes`, `bidding_status`, `bidding_notes`, `contract_ntp_status`, `contract_ntp_notes`, `remarks`) VALUES
(3, '2026-08-17 17:16:21', '2026-08-17 17:18:53', 3, 2, 'Reminder to submit Hazard Assessment.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint UNSIGNED NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `profile_photo_path` varchar(2048) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `role` enum('superadmin','admin','staff') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'staff',
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `profile_photo_path`, `role`, `email_verified_at`, `password`, `remember_token`, `created_at`, `updated_at`) VALUES
(3, 'Super Administrator', 'superadmin@meo.local', 'profile-photos/bQQ9RtqR7VE5X0FXYD3LJvQNqap2bIEdiNyzxkT6.jpg', 'superadmin', '2026-08-17 17:09:23', '$2y$12$sdQRDTAKkQZ1vyEpvkMUQee/VJ9/uqN/1YejC9ycU4fK.rVHH4/KG', NULL, '2026-08-17 17:09:23', '2026-08-19 16:54:10'),
(4, 'Administrator', 'admin@meo.local', 'profile-photos/KMtO0c957gvjH4K9QVszi0CBDIQVThYfkOrWzB1i.png', 'admin', '2026-08-17 17:09:23', '$2y$12$ZOYdVgu7BnFEPrgzpB2zWuxbPeKZt.GYvIQn4XHh14EcXKOeAV.bC', NULL, '2026-08-17 17:09:23', '2026-08-19 23:23:54'),
(5, 'Staff Member', 'staff@meo.local', NULL, 'staff', '2026-08-17 17:09:24', '$2y$12$kh2hQwzEcH0hbxA/Qps4zuPgRnvVq.p40S.x51dxaiF3XboazWSIa', NULL, '2026-08-17 17:09:24', '2026-08-17 17:09:24'),
(7, 'Charlie Nipaya', 'charlienp@gmail.com', 'profile-photos/Tr7bDM5jfytS9WJitWqIShEjlMX1StNorFj0bWtd.jpg', 'staff', '2026-08-19 00:35:45', '$2y$12$Pzvz/KCVa2R03AiSFlM6tOT36D0hx7ArlrUuha65AnWTOt8a9QiBe', NULL, '2026-08-18 22:44:32', '2026-08-19 00:35:45'),
(8, 'Joge Cyle Opena', 'jogecyle@meo.local', NULL, 'staff', '2026-08-23 19:16:24', '$2y$12$X8AQuv33quQ5CUq9s8M23.y.RBBuJsC3/xLuRO8DifxGwPTYmrala', NULL, '2026-08-23 19:16:24', '2026-08-23 19:16:24');

-- --------------------------------------------------------

--
-- Table structure for table `welcome_contents`
--

CREATE TABLE `welcome_contents` (
  `id` bigint UNSIGNED NOT NULL,
  `hero_title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hero_description` text COLLATE utf8mb4_unicode_ci,
  `hero_image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hero_background_image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `additional_images` json DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `slideshow_images` json DEFAULT NULL,
  `achievement_images` json DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `welcome_contents`
--

INSERT INTO `welcome_contents` (`id`, `hero_title`, `hero_description`, `hero_image`, `hero_background_image`, `additional_images`, `is_active`, `created_at`, `updated_at`, `slideshow_images`, `achievement_images`) VALUES
(1, NULL, NULL, NULL, NULL, NULL, 1, '2026-08-17 17:44:32', '2026-08-20 00:08:45', '[{\"id\": 1787017471946.097, \"url\": \"/storage/welcome-images/bX0W7M7fKAF7XCaeJivTt4izaS9h18o3KAWyzSwU.jpg\", \"name\": \"AdminBldg2.jfif\", \"type\": \"slideshow\"}, {\"id\": 1787017486095.6091, \"url\": \"/storage/welcome-images/LG9UWjXnsy9siyX3vqTa64lGZKTGvpPeepmOSiKD.jpg\", \"name\": \"LEACHETE TREATMENT PLANT.jfif\", \"type\": \"slideshow\"}, {\"id\": 1787017492203.1873, \"url\": \"/storage/welcome-images/FmpSb2J5r8C43Rg2vO3Gsuf6qnwPHWiynbk2dpag.jpg\", \"name\": \"LEACHETE TREATMENT PLANT 2.jfif\", \"type\": \"slideshow\"}]', '[{\"id\": 1787213273468.513, \"url\": \"/storage/welcome-images/snYtZxBcox6Jg36jck0I09ZiColmzKO8dHAEsmzB.jpg\", \"type\": \"achievement\", \"year\": \"2026\", \"title\": \"AdminBldg2\", \"caption\": \"This is just a sample Details or caption.\", \"category\": \"completed_project\", \"location\": \"Municipality of Opol\"}, {\"id\": 1787021747578.751, \"url\": \"/storage/welcome-images/JCk232KFdMuNm05D2zadbVOyqPaEnRF9o4EUaPSe.jpg\", \"type\": \"achievement\", \"year\": \"2026\", \"title\": \"Construction of Luyong bonbon\", \"caption\": \"Sample\", \"category\": \"completed_project\", \"location\": \"Municipality of Opol\"}, {\"id\": 1787018985954.8708, \"url\": \"/storage/welcome-images/AUHJmAeB4YHzsEithVrBOp4WYlMF1Xs40Brzn2hM.jpg\", \"type\": \"achievement\", \"year\": \"2026\", \"title\": \"MRF\", \"caption\": \"The Achievement Project.\", \"category\": \"completed_project\", \"location\": \"Municipality of Opol\"}]');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `activity_logs_user_id_foreign` (`user_id`),
  ADD KEY `activity_logs_module_index` (`module`),
  ADD KEY `activity_logs_action_index` (`action`),
  ADD KEY `activity_logs_severity_index` (`severity`);

--
-- Indexes for table `bulletins`
--
ALTER TABLE `bulletins`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `document_ai_analysis_tb`
--
ALTER TABLE `document_ai_analysis_tb`
  ADD PRIMARY KEY (`id`),
  ADD KEY `document_ai_analysis_tb_document_id_foreign` (`document_id`);

--
-- Indexes for table `document_blockchain_verification_tb`
--
ALTER TABLE `document_blockchain_verification_tb`
  ADD PRIMARY KEY (`id`),
  ADD KEY `document_blockchain_verification_tb_document_id_foreign` (`document_id`);

--
-- Indexes for table `document_digital_signatures_tb`
--
ALTER TABLE `document_digital_signatures_tb`
  ADD PRIMARY KEY (`id`),
  ADD KEY `document_digital_signatures_tb_document_id_foreign` (`document_id`);

--
-- Indexes for table `document_ocr_results_tb`
--
ALTER TABLE `document_ocr_results_tb`
  ADD PRIMARY KEY (`id`),
  ADD KEY `document_ocr_results_tb_document_id_foreign` (`document_id`);

--
-- Indexes for table `document_scanner_tb`
--
ALTER TABLE `document_scanner_tb`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `document_scanner_tb_file_hash_unique` (`file_hash`),
  ADD KEY `document_scanner_tb_project_id_foreign` (`project_id`),
  ADD KEY `document_scanner_tb_techprep_id_foreign` (`techprep_id`),
  ADD KEY `document_scanner_tb_uploaded_by_foreign` (`uploaded_by`),
  ADD KEY `document_scanner_tb_parent_document_id_foreign` (`parent_document_id`),
  ADD KEY `document_scanner_tb_processing_status_index` (`processing_status`),
  ADD KEY `document_scanner_tb_document_type_index` (`document_type`),
  ADD KEY `document_scanner_tb_created_at_index` (`created_at`),
  ADD KEY `document_scanner_tb_page_number_index` (`page_number`);

--
-- Indexes for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`);

--
-- Indexes for table `infra_audit_tb`
--
ALTER TABLE `infra_audit_tb`
  ADD PRIMARY KEY (`id`),
  ADD KEY `infra_audit_tb_project_id_foreign` (`project_id`);

--
-- Indexes for table `inquiries_tb`
--
ALTER TABLE `inquiries_tb`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `inquiries_tb_tracking_token_unique` (`tracking_token`),
  ADD KEY `inquiries_tb_accepted_by_foreign` (`accepted_by`),
  ADD KEY `inquiries_tb_resolved_by_foreign` (`resolved_by`),
  ADD KEY `inquiries_tb_updated_by_foreign` (`updated_by`),
  ADD KEY `inquiries_tb_cancelled_by_foreign` (`cancelled_by`);

--
-- Indexes for table `migrations`
--
ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`email`);

--
-- Indexes for table `personal_access_tokens`
--
ALTER TABLE `personal_access_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  ADD KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`);

--
-- Indexes for table `pow_prep_tb`
--
ALTER TABLE `pow_prep_tb`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pow_prep_tb_project_id_foreign` (`project_id`);

--
-- Indexes for table `project_fund_type_tb`
--
ALTER TABLE `project_fund_type_tb`
  ADD PRIMARY KEY (`id`),
  ADD KEY `project_fund_type_tb_project_id_foreign` (`project_id`),
  ADD KEY `project_fund_type_tb_fund_type_fund_source_index` (`fund_type`,`fund_source`);

--
-- Indexes for table `project_tb`
--
ALTER TABLE `project_tb`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `remarks_tb`
--
ALTER TABLE `remarks_tb`
  ADD PRIMARY KEY (`id`),
  ADD KEY `remarks_tb_project_id_foreign` (`project_id`);

--
-- Indexes for table `reminders`
--
ALTER TABLE `reminders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `reminders_user_id_foreign` (`user_id`),
  ADD KEY `reminders_starts_at_audience_index` (`starts_at`,`audience`),
  ADD KEY `reminders_is_done_starts_at_index` (`is_done`,`starts_at`);

--
-- Indexes for table `staff_assignments`
--
ALTER TABLE `staff_assignments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `staff_assignments_user_id_foreign` (`user_id`),
  ADD KEY `staff_assignments_assigned_by_foreign` (`assigned_by`),
  ADD KEY `staff_assignments_project_id_foreign` (`project_id`);

--
-- Indexes for table `tech_prep_tb`
--
ALTER TABLE `tech_prep_tb`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tech_prep_tb_project_id_foreign` (`project_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `users_email_unique` (`email`);

--
-- Indexes for table `welcome_contents`
--
ALTER TABLE `welcome_contents`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `bulletins`
--
ALTER TABLE `bulletins`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `document_ai_analysis_tb`
--
ALTER TABLE `document_ai_analysis_tb`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `document_blockchain_verification_tb`
--
ALTER TABLE `document_blockchain_verification_tb`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `document_digital_signatures_tb`
--
ALTER TABLE `document_digital_signatures_tb`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `document_ocr_results_tb`
--
ALTER TABLE `document_ocr_results_tb`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `document_scanner_tb`
--
ALTER TABLE `document_scanner_tb`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `infra_audit_tb`
--
ALTER TABLE `infra_audit_tb`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inquiries_tb`
--
ALTER TABLE `inquiries_tb`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT for table `personal_access_tokens`
--
ALTER TABLE `personal_access_tokens`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pow_prep_tb`
--
ALTER TABLE `pow_prep_tb`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `project_fund_type_tb`
--
ALTER TABLE `project_fund_type_tb`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `project_tb`
--
ALTER TABLE `project_tb`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `remarks_tb`
--
ALTER TABLE `remarks_tb`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `reminders`
--
ALTER TABLE `reminders`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `staff_assignments`
--
ALTER TABLE `staff_assignments`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `tech_prep_tb`
--
ALTER TABLE `tech_prep_tb`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `welcome_contents`
--
ALTER TABLE `welcome_contents`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `activity_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `document_ai_analysis_tb`
--
ALTER TABLE `document_ai_analysis_tb`
  ADD CONSTRAINT `document_ai_analysis_tb_document_id_foreign` FOREIGN KEY (`document_id`) REFERENCES `document_scanner_tb` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `document_blockchain_verification_tb`
--
ALTER TABLE `document_blockchain_verification_tb`
  ADD CONSTRAINT `document_blockchain_verification_tb_document_id_foreign` FOREIGN KEY (`document_id`) REFERENCES `document_scanner_tb` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `document_digital_signatures_tb`
--
ALTER TABLE `document_digital_signatures_tb`
  ADD CONSTRAINT `document_digital_signatures_tb_document_id_foreign` FOREIGN KEY (`document_id`) REFERENCES `document_scanner_tb` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `document_ocr_results_tb`
--
ALTER TABLE `document_ocr_results_tb`
  ADD CONSTRAINT `document_ocr_results_tb_document_id_foreign` FOREIGN KEY (`document_id`) REFERENCES `document_scanner_tb` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `document_scanner_tb`
--
ALTER TABLE `document_scanner_tb`
  ADD CONSTRAINT `document_scanner_tb_parent_document_id_foreign` FOREIGN KEY (`parent_document_id`) REFERENCES `document_scanner_tb` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `document_scanner_tb_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `project_tb` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `document_scanner_tb_techprep_id_foreign` FOREIGN KEY (`techprep_id`) REFERENCES `tech_prep_tb` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `document_scanner_tb_uploaded_by_foreign` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `infra_audit_tb`
--
ALTER TABLE `infra_audit_tb`
  ADD CONSTRAINT `infra_audit_tb_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `project_tb` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `inquiries_tb`
--
ALTER TABLE `inquiries_tb`
  ADD CONSTRAINT `inquiries_tb_accepted_by_foreign` FOREIGN KEY (`accepted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `inquiries_tb_cancelled_by_foreign` FOREIGN KEY (`cancelled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `inquiries_tb_resolved_by_foreign` FOREIGN KEY (`resolved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `inquiries_tb_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `pow_prep_tb`
--
ALTER TABLE `pow_prep_tb`
  ADD CONSTRAINT `pow_prep_tb_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `project_tb` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `project_fund_type_tb`
--
ALTER TABLE `project_fund_type_tb`
  ADD CONSTRAINT `project_fund_type_tb_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `project_tb` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `remarks_tb`
--
ALTER TABLE `remarks_tb`
  ADD CONSTRAINT `remarks_tb_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `project_tb` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `reminders`
--
ALTER TABLE `reminders`
  ADD CONSTRAINT `reminders_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `staff_assignments`
--
ALTER TABLE `staff_assignments`
  ADD CONSTRAINT `staff_assignments_assigned_by_foreign` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `staff_assignments_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `project_tb` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `staff_assignments_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tech_prep_tb`
--
ALTER TABLE `tech_prep_tb`
  ADD CONSTRAINT `tech_prep_tb_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `project_tb` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
