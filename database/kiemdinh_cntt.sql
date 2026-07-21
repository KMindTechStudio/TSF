-- phpMyAdmin SQL Dump
-- version 5.1.3
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 17, 2026 at 06:57 PM
-- Server version: 10.4.22-MariaDB
-- PHP Version: 7.4.28

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `kiemdinh_cntt`
--

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` bigint(20) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `module` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `record_id` int(11) DEFAULT NULL,
  `old_value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_value`)),
  `new_value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_value`)),
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `module`, `record_id`, `old_value`, `new_value`, `ip_address`, `created_at`) VALUES
(1, 1, 'create', 'evidences', 1, NULL, '{\"code\": \"MC.01.01.01\"}', '127.0.0.1', '2026-07-17 03:40:15'),
(2, 2, 'review', 'criteria', 5, NULL, '{\"status\": \"missing\"}', '127.0.0.1', '2026-07-17 03:40:15'),
(3, 1, 'create', 'users', 3, NULL, '{\"username\": \"viewer01\"}', '127.0.0.1', '2026-07-17 03:40:15'),
(8, 1, 'status_update', 'evidences', 1, NULL, '{\"approval_status\": \"need_update\"}', '::1', '2026-07-17 09:46:38'),
(9, 1, 'status_update', 'evidences', 1, NULL, '{\"approval_status\": \"approved\"}', '::1', '2026-07-17 09:46:38'),
(10, 1, 'status_update', 'evidences', 1, NULL, '{\"approval_status\": \"reviewing\"}', '::1', '2026-07-17 13:09:05'),
(11, 1, 'status_update', 'evidences', 1, NULL, '{\"approval_status\": \"approved\"}', '::1', '2026-07-17 13:09:07');

-- --------------------------------------------------------

--
-- Table structure for table `criteria`
--

CREATE TABLE `criteria` (
  `id` int(11) NOT NULL,
  `standard_id` int(11) NOT NULL,
  `department_id` int(11) DEFAULT NULL,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `evidence_status` enum('complete','need_update','missing') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'missing',
  `display_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `criteria`
--

INSERT INTO `criteria` (`id`, `standard_id`, `department_id`, `code`, `name`, `description`, `evidence_status`, `display_order`, `created_at`, `updated_at`) VALUES
(1, 1, 1, '1.1', 'Má»¥c tiÃªu cá»§a CTÄT Ä‘Æ°á»£c xÃ¡c Ä‘á»‹nh rÃµ rÃ ng', 'Má»¥c tiÃªu CTÄT phÃ¹ há»£p sá»© máº¡ng vÃ  nhu cáº§u xÃ£ há»™i', 'complete', 1, '2026-07-17 03:40:15', '2026-07-17 03:40:15'),
(2, 1, 1, '1.2', 'Chuáº©n Ä‘áº§u ra pháº£n Ã¡nh yÃªu cáº§u cá»§a cÃ¡c bÃªn liÃªn quan', 'Chuáº©n Ä‘áº§u ra Ä‘Æ°á»£c xÃ¢y dá»±ng vÃ  rÃ  soÃ¡t Ä‘á»‹nh ká»³', 'complete', 2, '2026-07-17 03:40:15', '2026-07-17 10:25:59'),
(3, 2, 2, '2.1', 'Báº£n mÃ´ táº£ CTÄT Ä‘áº§y Ä‘á»§ thÃ´ng tin cáº§n thiáº¿t', 'Báº£n mÃ´ táº£ nÃªu rÃµ má»¥c tiÃªu, CÄR, cáº¥u trÃºc vÃ  há»c pháº§n', 'complete', 1, '2026-07-17 03:40:15', '2026-07-17 03:40:15'),
(4, 3, 5, '3.2', 'Ná»™i dung há»c pháº§n cáº­p nháº­t theo Ä‘á»‹nh hÆ°á»›ng nghá» nghiá»‡p', 'Äá» cÆ°Æ¡ng há»c pháº§n Ä‘Æ°á»£c cáº­p nháº­t vÃ  phÃª duyá»‡t', 'complete', 2, '2026-07-17 03:40:15', '2026-07-17 03:40:15'),
(5, 4, 1, '4.1', 'Hoáº¡t Ä‘á»™ng dáº¡y há»c thÃºc Ä‘áº©y nÄƒng lá»±c tá»± há»c', 'Hoáº¡t Ä‘á»™ng dáº¡y há»c cÃ³ Ä‘á»‹nh hÆ°á»›ng phÃ¡t triá»ƒn nÄƒng lá»±c', 'missing', 1, '2026-07-17 03:40:15', '2026-07-17 03:40:15'),
(6, 5, 4, '5.3', 'Quy trÃ¬nh Ä‘Ã¡nh giÃ¡ káº¿t quáº£ há»c táº­p Ä‘Æ°á»£c cÃ´ng bá»‘', 'Quy trÃ¬nh vÃ  ma tráº­n Ä‘Ã¡nh giÃ¡ Ä‘Æ°á»£c cÃ´ng khai', 'need_update', 3, '2026-07-17 03:40:15', '2026-07-17 03:40:15');

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `id` int(11) NOT NULL,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`id`, `code`, `name`, `phone`, `email`, `created_at`) VALUES
(1, 'KHOA_CNTT', 'Khoa CÃ´ng nghá»‡ thÃ´ng tin', NULL, 'cntt@fbu.edu.vn', '2026-07-17 03:40:15'),
(2, 'PDT', 'PhÃ²ng ÄÃ o táº¡o', NULL, 'daotao@fbu.edu.vn', '2026-07-17 03:40:15'),
(3, 'PDBCL', 'PhÃ²ng Äáº£m báº£o cháº¥t lÆ°á»£ng', NULL, 'dbcl@fbu.edu.vn', '2026-07-17 03:40:15'),
(4, 'PKT', 'PhÃ²ng Kháº£o thÃ­', NULL, 'khaothi@fbu.edu.vn', '2026-07-17 03:40:15'),
(5, 'BM_PM', 'Bá»™ mÃ´n Pháº§n má»m', NULL, 'bomonpm@fbu.edu.vn', '2026-07-17 03:40:15');

-- --------------------------------------------------------

--
-- Table structure for table `download_logs`
--

CREATE TABLE `download_logs` (
  `id` bigint(20) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `evidence_file_id` int(11) NOT NULL,
  `downloaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `download_logs`
--

INSERT INTO `download_logs` (`id`, `user_id`, `evidence_file_id`, `downloaded_at`, `ip_address`) VALUES
(1, 1, 1, '2026-07-17 03:40:15', '127.0.0.1'),
(2, 2, 2, '2026-07-17 03:40:15', '127.0.0.1'),
(3, 1, 5, '2026-07-17 03:40:15', '127.0.0.1'),
(4, NULL, 1, '2026-07-17 05:08:23', '::1'),
(5, 2, 5, '2026-07-17 09:24:01', '::1'),
(6, 2, 5, '2026-07-17 09:33:16', '::1');

-- --------------------------------------------------------

--
-- Table structure for table `evidences`
--

CREATE TABLE `evidences` (
  `id` int(11) NOT NULL,
  `code` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `academic_year` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `issued_date` date DEFAULT NULL,
  `issuing_department_id` int(11) DEFAULT NULL,
  `responsible_user_id` int(11) DEFAULT NULL,
  `approval_status` enum('approved','reviewing','need_update') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'reviewing',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `evidences`
--

INSERT INTO `evidences` (`id`, `code`, `title`, `description`, `academic_year`, `issued_date`, `issuing_department_id`, `responsible_user_id`, `approval_status`, `created_at`, `updated_at`) VALUES
(1, 'MC.01.01.01', 'Quyáº¿t Ä‘á»‹nh ban hÃ nh má»¥c tiÃªu vÃ  chuáº©n Ä‘áº§u ra ngÃ nh CNTT', 'Quyáº¿t Ä‘á»‹nh ban hÃ nh má»¥c tiÃªu vÃ  chuáº©n Ä‘áº§u ra cá»§a CTÄT ngÃ nh CNTT', '2025-2026', '2025-09-01', 1, 1, 'approved', '2026-07-17 03:40:15', '2026-07-17 13:09:07'),
(2, 'MC.02.01.03', 'Báº£n mÃ´ táº£ chÆ°Æ¡ng trÃ¬nh Ä‘Ã o táº¡o ngÃ nh CNTT', 'Báº£n mÃ´ táº£ chÆ°Æ¡ng trÃ¬nh Ä‘Ã o táº¡o phá»¥c vá»¥ kiá»ƒm Ä‘á»‹nh', '2025-2026', '2025-08-15', 2, 2, 'approved', '2026-07-17 03:40:15', '2026-07-17 03:40:15'),
(3, 'MC.03.02.04', 'Äá» cÆ°Æ¡ng chi tiáº¿t cÃ¡c há»c pháº§n chuyÃªn ngÃ nh', 'Táº­p há»£p Ä‘á» cÆ°Æ¡ng há»c pháº§n chuyÃªn ngÃ nh CNTT', '2024-2025', '2024-09-05', 5, 1, 'reviewing', '2026-07-17 03:40:15', '2026-07-17 03:40:15'),
(4, 'MC.04.01.02', 'Káº¿ hoáº¡ch Ä‘á»•i má»›i phÆ°Æ¡ng phÃ¡p dáº¡y há»c', 'Káº¿ hoáº¡ch cáº£i tiáº¿n phÆ°Æ¡ng phÃ¡p giáº£ng dáº¡y trong CTÄT', '2025-2026', '2025-10-20', 1, 1, 'need_update', '2026-07-17 03:40:15', '2026-07-17 03:40:15'),
(5, 'MC.05.03.05', 'Quy cháº¿ Ä‘Ã¡nh giÃ¡ há»c pháº§n vÃ  ma tráº­n Ä‘iá»ƒm', 'Quy cháº¿ Ä‘Ã¡nh giÃ¡, rubrics vÃ  ma tráº­n Ä‘iá»ƒm há»c pháº§n', '2025-2026', '2025-11-10', 4, 2, 'approved', '2026-07-17 03:40:15', '2026-07-17 03:40:15'),
(10, 'MC.01.01.02', 'BiÃªn báº£n há»p rÃ  soÃ¡t má»¥c tiÃªu chÆ°Æ¡ng trÃ¬nh Ä‘Ã o táº¡o CNTT', 'BiÃªn báº£n há»p há»™i Ä‘á»“ng khoa vá» rÃ  soÃ¡t má»¥c tiÃªu CTÄT ngÃ nh CNTT', '2025-2026', '2025-09-12', 1, 1, 'approved', '2026-07-17 16:20:07', '2026-07-17 16:24:09'),
(11, 'MC.01.02.01', 'Kháº£o sÃ¡t nhu cáº§u cÃ¡c bÃªn liÃªn quan vá» chuáº©n Ä‘áº§u ra CNTT', 'Tá»•ng há»£p kháº£o sÃ¡t doanh nghiá»‡p, cá»±u sinh viÃªn vÃ  ngÆ°á»i há»c vá» chuáº©n Ä‘áº§u ra', '2025-2026', '2025-10-03', 3, 2, 'reviewing', '2026-07-17 16:20:07', '2026-07-17 16:24:09'),
(12, 'MC.01.02.02', 'Ma tráº­n Ä‘á»‘i sÃ¡nh chuáº©n Ä‘áº§u ra vá»›i má»¥c tiÃªu CTÄT', 'Ma tráº­n liÃªn káº¿t má»¥c tiÃªu chÆ°Æ¡ng trÃ¬nh vá»›i chuáº©n Ä‘áº§u ra ngÃ nh CNTT', '2025-2026', '2025-10-08', 1, 1, 'need_update', '2026-07-17 16:20:07', '2026-07-17 16:24:09'),
(13, 'MC.02.01.04', 'Káº¿ hoáº¡ch cáº­p nháº­t báº£n mÃ´ táº£ chÆ°Æ¡ng trÃ¬nh Ä‘Ã o táº¡o', 'Káº¿ hoáº¡ch rÃ  soÃ¡t vÃ  cáº­p nháº­t báº£n mÃ´ táº£ CTÄT theo chu ká»³ kiá»ƒm Ä‘á»‹nh', '2025-2026', '2025-08-22', 2, 1, 'approved', '2026-07-17 16:20:07', '2026-07-17 16:24:09'),
(14, 'MC.02.01.05', 'Phá»¥ lá»¥c cáº¥u trÃºc chÆ°Æ¡ng trÃ¬nh Ä‘Ã o táº¡o CNTT', 'Phá»¥ lá»¥c khá»‘i kiáº¿n thá»©c, sá»‘ tÃ­n chá»‰ vÃ  phÃ¢n bá»• há»c pháº§n theo há»c ká»³', '2025-2026', '2025-08-28', 2, 2, 'approved', '2026-07-17 16:20:07', '2026-07-17 16:24:09'),
(15, 'MC.02.01.06', 'Báº£ng Ä‘á»‘i sÃ¡nh CTÄT vá»›i khung trÃ¬nh Ä‘á»™ quá»‘c gia', 'Báº£ng Ä‘á»‘i sÃ¡nh chÆ°Æ¡ng trÃ¬nh Ä‘Ã o táº¡o ngÃ nh CNTT vá»›i khung trÃ¬nh Ä‘á»™ quá»‘c gia Viá»‡t Nam', '2024-2025', '2025-01-14', 3, 1, 'reviewing', '2026-07-17 16:20:07', '2026-07-17 16:24:09'),
(16, 'MC.03.02.05', 'Danh má»¥c Ä‘á» cÆ°Æ¡ng há»c pháº§n chuyÃªn ngÃ nh CNTT', 'Danh má»¥c Ä‘á» cÆ°Æ¡ng há»c pháº§n chuyÃªn ngÃ nh phá»¥c vá»¥ tá»± Ä‘Ã¡nh giÃ¡ CTÄT', '2025-2026', '2025-09-05', 5, 1, 'approved', '2026-07-17 16:20:07', '2026-07-17 16:24:09'),
(17, 'MC.03.02.06', 'BiÃªn báº£n nghiá»‡m thu Ä‘á» cÆ°Æ¡ng há»c pháº§n cáº­p nháº­t', 'BiÃªn báº£n nghiá»‡m thu Ä‘á» cÆ°Æ¡ng há»c pháº§n sau khi cáº­p nháº­t Ä‘á»‹nh hÆ°á»›ng nghá» nghiá»‡p', '2025-2026', '2025-09-18', 5, 2, 'need_update', '2026-07-17 16:20:07', '2026-07-17 16:24:09'),
(18, 'MC.03.02.07', 'Ma tráº­n há»c pháº§n vÃ  chuáº©n Ä‘áº§u ra há»c pháº§n', 'Ma tráº­n liÃªn káº¿t há»c pháº§n vá»›i chuáº©n Ä‘áº§u ra há»c pháº§n vÃ  chuáº©n Ä‘áº§u ra chÆ°Æ¡ng trÃ¬nh', '2024-2025', '2024-11-20', 1, 1, 'approved', '2026-07-17 16:20:07', '2026-07-17 16:24:09'),
(19, 'MC.04.01.03', 'Káº¿ hoáº¡ch Ä‘á»•i má»›i phÆ°Æ¡ng phÃ¡p giáº£ng dáº¡y há»c ká»³ I', 'Káº¿ hoáº¡ch Ã¡p dá»¥ng phÆ°Æ¡ng phÃ¡p dáº¡y há»c tÃ­ch cá»±c trong cÃ¡c há»c pháº§n CNTT', '2025-2026', '2025-09-25', 1, 1, 'reviewing', '2026-07-17 16:20:07', '2026-07-17 16:24:09'),
(20, 'MC.04.01.04', 'BÃ¡o cÃ¡o triá»ƒn khai lá»›p há»c dá»± Ã¡n ngÃ nh CNTT', 'BÃ¡o cÃ¡o minh chá»©ng hoáº¡t Ä‘á»™ng dáº¡y há»c theo dá»± Ã¡n vÃ  Ä‘Ã¡nh giÃ¡ sáº£n pháº©m há»c táº­p', '2025-2026', '2025-12-02', 1, 2, 'need_update', '2026-07-17 16:20:07', '2026-07-17 16:24:09'),
(21, 'MC.04.01.05', 'Danh sÃ¡ch há»c pháº§n Ã¡p dá»¥ng blended learning', 'Danh sÃ¡ch cÃ¡c há»c pháº§n triá»ƒn khai blended learning vÃ  tÃ i nguyÃªn LMS liÃªn quan', '2024-2025', '2025-03-11', 5, 1, 'approved', '2026-07-17 16:20:07', '2026-07-17 16:24:09'),
(22, 'MC.05.03.06', 'Quy Ä‘á»‹nh xÃ¢y dá»±ng ma tráº­n Ä‘á» thi há»c pháº§n', 'Quy Ä‘á»‹nh vá» xÃ¢y dá»±ng ma tráº­n Ä‘á» thi, rubrics vÃ  ngÃ¢n hÃ ng cÃ¢u há»i', '2025-2026', '2025-11-18', 4, 1, 'approved', '2026-07-17 16:20:07', '2026-07-17 16:24:09'),
(23, 'MC.05.03.07', 'Báº£ng tá»•ng há»£p káº¿t quáº£ Ä‘Ã¡nh giÃ¡ há»c pháº§n', 'Báº£ng tá»•ng há»£p Ä‘iá»ƒm quÃ¡ trÃ¬nh, Ä‘iá»ƒm thi vÃ  phÃ¢n tÃ­ch káº¿t quáº£ há»c táº­p', '2025-2026', '2026-01-10', 4, 2, 'reviewing', '2026-07-17 16:20:07', '2026-07-17 16:24:09'),
(24, 'MC.05.03.08', 'BiÃªn báº£n rÃ  soÃ¡t quy trÃ¬nh cháº¥m thi vÃ  phÃºc kháº£o', 'BiÃªn báº£n rÃ  soÃ¡t quy trÃ¬nh Ä‘Ã¡nh giÃ¡, cháº¥m thi vÃ  phÃºc kháº£o há»c pháº§n', '2024-2025', '2025-04-18', 4, 1, 'need_update', '2026-07-17 16:20:07', '2026-07-17 16:24:09'),
(25, 'MC.06.01.01', 'Káº¿ hoáº¡ch thu tháº­p minh chá»©ng kiá»ƒm Ä‘á»‹nh CTÄT CNTT', 'Káº¿ hoáº¡ch phÃ¢n cÃ´ng thu tháº­p, chuáº©n hÃ³a vÃ  cáº­p nháº­t minh chá»©ng theo tiÃªu chuáº©n', '2025-2026', '2025-07-15', 3, 1, 'approved', '2026-07-17 16:20:07', '2026-07-17 16:24:09'),
(26, 'MC.06.01.02', 'Danh má»¥c phÃ¢n quyá»n khai thÃ¡c kho minh chá»©ng', 'Danh má»¥c tÃ i khoáº£n, quyá»n truy cáº­p vÃ  pháº¡m vi khai thÃ¡c dá»¯ liá»‡u minh chá»©ng', '2025-2026', '2025-07-20', 3, 1, 'approved', '2026-07-17 16:20:07', '2026-07-17 16:24:09'),
(27, 'MC.06.01.03', 'BiÃªn báº£n kiá»ƒm tra Ä‘á»‹nh ká»³ cÆ¡ sá»Ÿ dá»¯ liá»‡u minh chá»©ng', 'BiÃªn báº£n kiá»ƒm tra tÃ­nh Ä‘áº§y Ä‘á»§, há»£p lá»‡ vÃ  kháº£ nÄƒng truy xuáº¥t cá»§a minh chá»©ng', '2025-2026', '2025-12-20', 3, 2, 'reviewing', '2026-07-17 16:20:07', '2026-07-17 16:24:09'),
(28, 'MC.07.01.01', 'BÃ¡o cÃ¡o tá»± Ä‘Ã¡nh giÃ¡ chÆ°Æ¡ng trÃ¬nh Ä‘Ã o táº¡o ngÃ nh CNTT', 'Báº£n dá»± tháº£o bÃ¡o cÃ¡o tá»± Ä‘Ã¡nh giÃ¡ CTÄT ngÃ nh CNTT phá»¥c vá»¥ kiá»ƒm Ä‘á»‹nh cháº¥t lÆ°á»£ng', '2025-2026', '2026-02-05', 3, 1, 'need_update', '2026-07-17 16:20:07', '2026-07-17 16:24:09'),
(29, 'MC.07.01.02', 'Phá»¥ lá»¥c minh chá»©ng phá»¥c vá»¥ Ä‘oÃ n Ä‘Ã¡nh giÃ¡ ngoÃ i', 'Phá»¥ lá»¥c tá»•ng há»£p Ä‘Æ°á»ng dáº«n, mÃ£ hÃ³a vÃ  tráº¡ng thÃ¡i minh chá»©ng phá»¥c vá»¥ Ä‘Ã¡nh giÃ¡ ngoÃ i', '2025-2026', '2026-02-12', 3, 1, 'approved', '2026-07-17 16:20:07', '2026-07-17 16:24:09');

-- --------------------------------------------------------

--
-- Table structure for table `evidence_criteria`
--

CREATE TABLE `evidence_criteria` (
  `evidence_id` int(11) NOT NULL,
  `criteria_id` int(11) NOT NULL,
  `note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `evidence_criteria`
--

INSERT INTO `evidence_criteria` (`evidence_id`, `criteria_id`, `note`, `created_at`) VALUES
(1, 1, NULL, '2026-07-17 09:43:27'),
(1, 2, NULL, '2026-07-17 09:43:27'),
(2, 3, 'Báº£n mÃ´ táº£ CTÄT', '2026-07-17 03:40:15'),
(3, 4, 'Äá» cÆ°Æ¡ng chi tiáº¿t', '2026-07-17 03:40:15'),
(4, 5, 'Cáº§n bá»• sung phá»¥ lá»¥c triá»ƒn khai', '2026-07-17 03:40:15'),
(5, 6, 'Quy cháº¿ vÃ  ma tráº­n Ä‘iá»ƒm', '2026-07-17 03:40:15'),
(10, 1, 'Minh ch?ng b? sung', '2026-07-17 16:20:07'),
(11, 2, 'Minh ch?ng b? sung', '2026-07-17 16:20:07'),
(12, 2, 'Minh ch?ng b? sung', '2026-07-17 16:20:07'),
(13, 3, 'Minh ch?ng b? sung', '2026-07-17 16:20:07'),
(14, 3, 'Minh ch?ng b? sung', '2026-07-17 16:20:07'),
(15, 3, 'Minh ch?ng b? sung', '2026-07-17 16:20:07'),
(16, 4, 'Minh ch?ng b? sung', '2026-07-17 16:20:07'),
(17, 4, 'Minh ch?ng b? sung', '2026-07-17 16:20:07'),
(18, 4, 'Minh ch?ng b? sung', '2026-07-17 16:20:07'),
(19, 5, 'Minh ch?ng b? sung', '2026-07-17 16:20:07'),
(20, 5, 'Minh ch?ng b? sung', '2026-07-17 16:20:07'),
(21, 5, 'Minh ch?ng b? sung', '2026-07-17 16:20:07'),
(22, 6, 'Minh ch?ng b? sung', '2026-07-17 16:20:07'),
(23, 6, 'Minh ch?ng b? sung', '2026-07-17 16:20:07'),
(24, 6, 'Minh ch?ng b? sung', '2026-07-17 16:20:07'),
(25, 1, 'Minh ch?ng b? sung', '2026-07-17 16:20:07'),
(26, 2, 'Minh ch?ng b? sung', '2026-07-17 16:20:07'),
(27, 3, 'Minh ch?ng b? sung', '2026-07-17 16:20:07'),
(28, 1, 'Minh ch?ng b? sung', '2026-07-17 16:20:07'),
(29, 2, 'Minh ch?ng b? sung', '2026-07-17 16:20:07');

-- --------------------------------------------------------

--
-- Table structure for table `evidence_files`
--

CREATE TABLE `evidence_files` (
  `id` int(11) NOT NULL,
  `evidence_id` int(11) NOT NULL,
  `original_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `stored_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_path` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_size` bigint(20) NOT NULL DEFAULT 0,
  `version_no` int(11) NOT NULL DEFAULT 1,
  `uploaded_by` int(11) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `evidence_files`
--

INSERT INTO `evidence_files` (`id`, `evidence_id`, `original_name`, `stored_name`, `file_path`, `file_type`, `file_size`, `uploaded_by`, `uploaded_at`) VALUES
(1, 1, 'quyet-dinh-cdr-cntt.pdf', 'MC_01_01_01.pdf', 'uploads/evidences/MC_01_01_01.pdf', 'PDF', 1250000, 1, '2026-07-17 03:40:15'),
(2, 2, 'ban-mo-ta-ctdt-cntt.docx', 'MC_02_01_03.docx', 'uploads/evidences/MC_02_01_03.docx', 'DOCX', 840000, 2, '2026-07-17 03:40:15'),
(3, 3, 'de-cuong-hoc-phan.zip', 'MC_03_02_04.zip', 'uploads/evidences/MC_03_02_04.zip', 'ZIP', 6400000, 1, '2026-07-17 03:40:15'),
(4, 4, 'ke-hoach-doi-moi-day-hoc.pdf', 'MC_04_01_02.pdf', 'uploads/evidences/MC_04_01_02.pdf', 'PDF', 930000, 1, '2026-07-17 03:40:15'),
(5, 5, 'quy-che-danh-gia.xlsx', 'MC_05_03_05.xlsx', 'uploads/evidences/MC_05_03_05.xlsx', 'XLSX', 420000, 2, '2026-07-17 03:40:15'),
(10, 10, 'mc_01_01_02.pdf', 'MC_01_01_02.pdf', 'uploads/evidences/MC_01_01_02.pdf', 'PDF', 720000, 1, '2026-07-17 16:20:07'),
(11, 11, 'mc_01_02_01.xlsx', 'MC_01_02_01.xlsx', 'uploads/evidences/MC_01_02_01.xlsx', 'XLSX', 680000, 1, '2026-07-17 16:20:07'),
(12, 12, 'mc_01_02_02.docx', 'MC_01_02_02.docx', 'uploads/evidences/MC_01_02_02.docx', 'DOCX', 540000, 1, '2026-07-17 16:20:07'),
(13, 13, 'mc_02_01_04.pdf', 'MC_02_01_04.pdf', 'uploads/evidences/MC_02_01_04.pdf', 'PDF', 850000, 1, '2026-07-17 16:20:07'),
(14, 14, 'mc_02_01_05.docx', 'MC_02_01_05.docx', 'uploads/evidences/MC_02_01_05.docx', 'DOCX', 760000, 1, '2026-07-17 16:20:07'),
(15, 15, 'mc_02_01_06.xlsx', 'MC_02_01_06.xlsx', 'uploads/evidences/MC_02_01_06.xlsx', 'XLSX', 620000, 1, '2026-07-17 16:20:07'),
(16, 16, 'mc_03_02_05.zip', 'MC_03_02_05.zip', 'uploads/evidences/MC_03_02_05.zip', 'ZIP', 2400000, 1, '2026-07-17 16:20:07'),
(17, 17, 'mc_03_02_06.pdf', 'MC_03_02_06.pdf', 'uploads/evidences/MC_03_02_06.pdf', 'PDF', 930000, 1, '2026-07-17 16:20:07'),
(18, 18, 'mc_03_02_07.xlsx', 'MC_03_02_07.xlsx', 'uploads/evidences/MC_03_02_07.xlsx', 'XLSX', 570000, 1, '2026-07-17 16:20:07'),
(19, 19, 'mc_04_01_03.pdf', 'MC_04_01_03.pdf', 'uploads/evidences/MC_04_01_03.pdf', 'PDF', 810000, 1, '2026-07-17 16:20:07'),
(20, 20, 'mc_04_01_04.docx', 'MC_04_01_04.docx', 'uploads/evidences/MC_04_01_04.docx', 'DOCX', 690000, 1, '2026-07-17 16:20:07'),
(21, 21, 'mc_04_01_05.xlsx', 'MC_04_01_05.xlsx', 'uploads/evidences/MC_04_01_05.xlsx', 'XLSX', 480000, 1, '2026-07-17 16:20:07'),
(22, 22, 'mc_05_03_06.pdf', 'MC_05_03_06.pdf', 'uploads/evidences/MC_05_03_06.pdf', 'PDF', 740000, 1, '2026-07-17 16:20:07'),
(23, 23, 'mc_05_03_07.xlsx', 'MC_05_03_07.xlsx', 'uploads/evidences/MC_05_03_07.xlsx', 'XLSX', 910000, 1, '2026-07-17 16:20:07'),
(24, 24, 'mc_05_03_08.docx', 'MC_05_03_08.docx', 'uploads/evidences/MC_05_03_08.docx', 'DOCX', 610000, 1, '2026-07-17 16:20:07'),
(25, 25, 'mc_06_01_01.pdf', 'MC_06_01_01.pdf', 'uploads/evidences/MC_06_01_01.pdf', 'PDF', 520000, 1, '2026-07-17 16:20:07'),
(26, 26, 'mc_06_01_02.xlsx', 'MC_06_01_02.xlsx', 'uploads/evidences/MC_06_01_02.xlsx', 'XLSX', 450000, 1, '2026-07-17 16:20:07'),
(27, 27, 'mc_06_01_03.pdf', 'MC_06_01_03.pdf', 'uploads/evidences/MC_06_01_03.pdf', 'PDF', 650000, 1, '2026-07-17 16:20:07'),
(28, 28, 'mc_07_01_01.docx', 'MC_07_01_01.docx', 'uploads/evidences/MC_07_01_01.docx', 'DOCX', 1200000, 1, '2026-07-17 16:20:07'),
(29, 29, 'mc_07_01_02.zip', 'MC_07_01_02.zip', 'uploads/evidences/MC_07_01_02.zip', 'ZIP', 3100000, 1, '2026-07-17 16:20:07');

-- --------------------------------------------------------

--
-- Table structure for table `remember_tokens`
--

CREATE TABLE `remember_tokens` (
  `id` bigint(20) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `remember_tokens`
--

INSERT INTO `remember_tokens` (`id`, `user_id`, `token_hash`, `expires_at`, `created_at`) VALUES
(2, 1, 'b1e3f2f82b1cf4b21f9499b9278326086553705d7c43630896ff16ea4fc16c16', '2026-07-24 18:43:58', '2026-07-17 16:43:58'),
(3, 1, 'f4420bdab150989ee29834cd7b0839e1476b6a7f13191a133816632e114029c4', '2026-07-24 18:44:15', '2026-07-17 16:44:15');

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `code`, `name`, `description`, `created_at`) VALUES
(1, 'admin', 'Quáº£n trá»‹ viÃªn', 'Quáº£n trá»‹ toÃ n bá»™ há»‡ thá»‘ng', '2026-07-17 03:40:15'),
(2, 'staff', 'CÃ¡n bá»™ kiá»ƒm Ä‘á»‹nh', 'Cáº­p nháº­t vÃ  rÃ  soÃ¡t há»“ sÆ¡ minh chá»©ng', '2026-07-17 03:40:15'),
(3, 'viewer', 'NgÆ°á»i dÃ¹ng tra cá»©u', 'Tra cá»©u vÃ  táº£i minh chá»©ng Ä‘Æ°á»£c phÃ©p', '2026-07-17 03:40:15');

-- --------------------------------------------------------

--
-- Table structure for table `standards`
--

CREATE TABLE `standards` (
  `id` int(11) NOT NULL,
  `standard_set_id` int(11) NOT NULL,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `display_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `standards`
--

INSERT INTO `standards` (`id`, `standard_set_id`, `code`, `name`, `description`, `display_order`, `created_at`, `updated_at`) VALUES
(1, 1, 'TC01', 'Má»¥c tiÃªu vÃ  chuáº©n Ä‘áº§u ra cá»§a chÆ°Æ¡ng trÃ¬nh Ä‘Ã o táº¡o', 'Quáº£n lÃ½ minh chá»©ng liÃªn quan má»¥c tiÃªu vÃ  chuáº©n Ä‘áº§u ra', 1, '2026-07-17 03:40:15', '2026-07-17 03:40:15'),
(2, 1, 'TC02', 'Báº£n mÃ´ táº£ chÆ°Æ¡ng trÃ¬nh Ä‘Ã o táº¡o', 'Quáº£n lÃ½ báº£n mÃ´ táº£ chÆ°Æ¡ng trÃ¬nh Ä‘Ã o táº¡o', 2, '2026-07-17 03:40:15', '2026-07-17 03:40:15'),
(3, 1, 'TC03', 'Cáº¥u trÃºc vÃ  ná»™i dung chÆ°Æ¡ng trÃ¬nh dáº¡y há»c', 'Quáº£n lÃ½ cáº¥u trÃºc vÃ  ná»™i dung chÆ°Æ¡ng trÃ¬nh dáº¡y há»c', 3, '2026-07-17 03:40:15', '2026-07-17 03:40:15'),
(4, 1, 'TC04', 'PhÆ°Æ¡ng phÃ¡p tiáº¿p cáº­n trong dáº¡y vÃ  há»c', 'Quáº£n lÃ½ minh chá»©ng vá» phÆ°Æ¡ng phÃ¡p dáº¡y há»c', 4, '2026-07-17 03:40:15', '2026-07-17 03:40:15'),
(5, 1, 'TC05', 'ÄÃ¡nh giÃ¡ káº¿t quáº£ há»c táº­p cá»§a ngÆ°á»i há»c', 'Quáº£n lÃ½ minh chá»©ng vá» Ä‘Ã¡nh giÃ¡ káº¿t quáº£ há»c táº­p', 5, '2026-07-17 03:40:15', '2026-07-17 03:40:15');

-- --------------------------------------------------------

--
-- Table structure for table `standard_sets`
--

CREATE TABLE `standard_sets` (
  `id` int(11) NOT NULL,
  `training_program_id` int(11) NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `version_year` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `issuing_body` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `standard_sets`
--

INSERT INTO `standard_sets` (`id`, `training_program_id`, `name`, `version_year`, `issuing_body`, `description`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, 'Bá»™ tiÃªu chuáº©n Ä‘Ã¡nh giÃ¡ cháº¥t lÆ°á»£ng chÆ°Æ¡ng trÃ¬nh Ä‘Ã o táº¡o', '2025', 'Bá»™ GiÃ¡o dá»¥c vÃ  ÄÃ o táº¡o', 'Bá»™ tiÃªu chuáº©n dÃ¹ng cho cÆ¡ sá»Ÿ dá»¯ liá»‡u minh chá»©ng phá»¥c vá»¥ kiá»ƒm Ä‘á»‹nh CTÄT', 'active', '2026-07-17 03:40:15', '2026-07-17 03:40:15');

-- --------------------------------------------------------

--
-- Table structure for table `training_programs`
--

CREATE TABLE `training_programs` (
  `id` int(11) NOT NULL,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `degree_level` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `faculty` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `accreditation_cycle` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `training_programs`
--

INSERT INTO `training_programs` (`id`, `code`, `name`, `degree_level`, `faculty`, `accreditation_cycle`, `description`, `status`, `created_at`, `updated_at`) VALUES
(1, '7480201', 'CÃ´ng nghá»‡ thÃ´ng tin', 'Äáº¡i há»c chÃ­nh quy', 'Khoa CÃ´ng nghá»‡ thÃ´ng tin', 'Chu ká»³ kiá»ƒm Ä‘á»‹nh 2026-2031', 'ChÆ°Æ¡ng trÃ¬nh Ä‘Ã o táº¡o ngÃ nh CÃ´ng nghá»‡ thÃ´ng tin cá»§a TrÆ°á»ng Äáº¡i há»c TÃ i chÃ­nh - NgÃ¢n hÃ ng HÃ  Ná»™i', 'active', '2026-07-17 03:40:15', '2026-07-17 03:40:15');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `user_code` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `role_id` int(11) NOT NULL,
  `department_id` int(11) DEFAULT NULL,
  `full_name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `username` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `avatar_path` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('active','locked') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `last_login_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `user_code`, `role_id`, `department_id`, `full_name`, `username`, `email`, `avatar_path`, `password_hash`, `status`, `last_login_at`, `created_at`, `updated_at`) VALUES
(1, 'ND001', 1, 1, 'Dev Nguyen', 'admin', 'admin@fbu.edu.vn', 'uploads/avatars/avatar_user_1_20260717062114.jpg', '$2y$10$JvlJpiWq7KynMo1bP47ptuuZUNRRKwNYJpbip17YDEzsMInyQGk3G', 'active', '2026-07-17 23:44:15', '2026-07-17 03:40:15', '2026-07-17 16:44:15'),
(2, 'ND002', 3, 3, 'Tráº§n Thu HÃ ', 'kiemdinhtt', 'ha.tt@fbu.edu.vn', NULL, '$2y$10$98IdFASFlTYGEIBVWcui4u52XXRv1x2h4jO/GAJ612wzPiGkMJtsK', 'active', '2026-07-17 23:43:27', '2026-07-17 03:40:15', '2026-07-17 16:43:27');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_audit_logs_user` (`user_id`);

--
-- Indexes for table `criteria`
--
ALTER TABLE `criteria`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_criteria_code_standard` (`standard_id`,`code`),
  ADD KEY `fk_criteria_department` (`department_id`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `download_logs`
--
ALTER TABLE `download_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_download_logs_user` (`user_id`),
  ADD KEY `fk_download_logs_file` (`evidence_file_id`);

--
-- Indexes for table `evidences`
--
ALTER TABLE `evidences`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `fk_evidences_department` (`issuing_department_id`),
  ADD KEY `fk_evidences_user` (`responsible_user_id`);

--
-- Indexes for table `evidence_criteria`
--
ALTER TABLE `evidence_criteria`
  ADD PRIMARY KEY (`evidence_id`,`criteria_id`),
  ADD KEY `fk_evidence_criteria_criteria` (`criteria_id`);

--
-- Indexes for table `evidence_files`
--
ALTER TABLE `evidence_files`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_evidence_files_evidence` (`evidence_id`),
  ADD KEY `fk_evidence_files_user` (`uploaded_by`);

--
-- Indexes for table `remember_tokens`
--
ALTER TABLE `remember_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_remember_token_hash` (`token_hash`),
  ADD KEY `idx_remember_user` (`user_id`),
  ADD KEY `idx_remember_expires` (`expires_at`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `standards`
--
ALTER TABLE `standards`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_standard_code_set` (`standard_set_id`,`code`);

--
-- Indexes for table `standard_sets`
--
ALTER TABLE `standard_sets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_standard_sets_program` (`training_program_id`);

--
-- Indexes for table `training_programs`
--
ALTER TABLE `training_programs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `fk_users_role` (`role_id`),
  ADD KEY `fk_users_department` (`department_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `criteria`
--
ALTER TABLE `criteria`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `download_logs`
--
ALTER TABLE `download_logs`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `evidences`
--
ALTER TABLE `evidences`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `evidence_files`
--
ALTER TABLE `evidence_files`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `remember_tokens`
--
ALTER TABLE `remember_tokens`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `standards`
--
ALTER TABLE `standards`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `standard_sets`
--
ALTER TABLE `standard_sets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `training_programs`
--
ALTER TABLE `training_programs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `fk_audit_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `criteria`
--
ALTER TABLE `criteria`
  ADD CONSTRAINT `fk_criteria_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`),
  ADD CONSTRAINT `fk_criteria_standard` FOREIGN KEY (`standard_id`) REFERENCES `standards` (`id`);

--
-- Constraints for table `download_logs`
--
ALTER TABLE `download_logs`
  ADD CONSTRAINT `fk_download_logs_file` FOREIGN KEY (`evidence_file_id`) REFERENCES `evidence_files` (`id`),
  ADD CONSTRAINT `fk_download_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `evidences`
--
ALTER TABLE `evidences`
  ADD CONSTRAINT `fk_evidences_department` FOREIGN KEY (`issuing_department_id`) REFERENCES `departments` (`id`),
  ADD CONSTRAINT `fk_evidences_user` FOREIGN KEY (`responsible_user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `evidence_criteria`
--
ALTER TABLE `evidence_criteria`
  ADD CONSTRAINT `fk_evidence_criteria_criteria` FOREIGN KEY (`criteria_id`) REFERENCES `criteria` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_evidence_criteria_evidence` FOREIGN KEY (`evidence_id`) REFERENCES `evidences` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `evidence_files`
--
ALTER TABLE `evidence_files`
  ADD CONSTRAINT `fk_evidence_files_evidence` FOREIGN KEY (`evidence_id`) REFERENCES `evidences` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_evidence_files_user` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `remember_tokens`
--
ALTER TABLE `remember_tokens`
  ADD CONSTRAINT `fk_remember_tokens_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `standards`
--
ALTER TABLE `standards`
  ADD CONSTRAINT `fk_standards_set` FOREIGN KEY (`standard_set_id`) REFERENCES `standard_sets` (`id`);

--
-- Constraints for table `standard_sets`
--
ALTER TABLE `standard_sets`
  ADD CONSTRAINT `fk_standard_sets_program` FOREIGN KEY (`training_program_id`) REFERENCES `training_programs` (`id`);

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`),
  ADD CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
