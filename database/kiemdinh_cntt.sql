-- phpMyAdmin SQL Dump
-- version 5.1.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Aug 31, 2026 at 04:29 PM
-- Server version: 10.4.21-MariaDB
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
  `MaNguoiDung` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hanh_dong` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phan_he` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ten_ban_ghi` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id_ban_ghi` int(11) DEFAULT NULL,
  `gia_tri_cu` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`gia_tri_cu`)),
  `gia_tri_moi` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`gia_tri_moi`)),
  `dia_chi_ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ngay_tao` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `MaNguoiDung`, `hanh_dong`, `phan_he`, `ten_ban_ghi`, `id_ban_ghi`, `gia_tri_cu`, `gia_tri_moi`, `dia_chi_ip`, `ngay_tao`) VALUES
(1, 'ND002', 'xem', 'minh_chung', 'MC.01', 1, NULL, NULL, '::1', '2026-07-30 14:56:27');

-- --------------------------------------------------------

--
-- Table structure for table `BoTieuChuan`
--

CREATE TABLE `BoTieuChuan` (
  `MaBoTieuChuan` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `TenBoTieuChuan` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ThongTu` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `NgayBanHanh` date DEFAULT NULL,
  `CoQuanBanHanh` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `NamBanHanh` int(11) DEFAULT NULL,
  `MoTa` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `TrangThai` tinyint(1) NOT NULL DEFAULT 1,
  `NgayTao` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `BoTieuChuan`
--

INSERT INTO `BoTieuChuan` (`MaBoTieuChuan`, `TenBoTieuChuan`, `ThongTu`, `NgayBanHanh`, `CoQuanBanHanh`, `NamBanHanh`, `MoTa`, `TrangThai`, `NgayTao`) VALUES
('BTC01', 'Bộ tiêu chuẩn đánh giá chất lượng chương trình đào tạo trình độ đại học ngành Công nghệ thông tin', 'Chuẩn kiểm định AUN-QA v4.0', '2019-06-21', NULL, NULL, 'Bộ tiêu chuẩn quy định các yêu cầu kiểm định và bảo đảm chất lượng giáo dục cho ngành CNTT đợt 1.', 1, '2026-08-31 12:25:35'),
('BTC02', 'Bộ tiêu chuẩn kiểm định chất lượng cơ sở giáo dục đại học (Thông tư 12/2017/TT-BGDĐT)', 'Thông tư 17/2021/TT-BGDĐT', '2018-10-07', NULL, NULL, 'Bộ tiêu chuẩn quy định các yêu cầu kiểm định và bảo đảm chất lượng giáo dục cho ngành CNTT đợt 2.', 1, '2026-08-31 12:25:35'),
('BTC03', 'Bộ tiêu chuẩn đánh giá chất lượng chương trình đào tạo ngành Kỹ thuật Phần mềm (AUN-QA 4.0)', 'Thông tư 17/2021/TT-BGDĐT', '2022-04-23', NULL, NULL, 'Bộ tiêu chuẩn quy định các yêu cầu kiểm định và bảo đảm chất lượng giáo dục cho ngành CNTT đợt 3.', 1, '2026-08-31 12:25:35'),
('BTC04', 'Bộ tiêu chuẩn kiểm định chất lượng chương trình đào tạo ngành Hệ thống thông tin (ABET)', 'Thông tư 38/2013/TT-BGDĐT', '2021-09-20', NULL, NULL, 'Bộ tiêu chuẩn quy định các yêu cầu kiểm định và bảo đảm chất lượng giáo dục cho ngành CNTT đợt 4.', 1, '2026-08-31 12:25:35'),
('BTC05', 'Bộ tiêu chuẩn đánh giá chất lượng CTĐT ngành Trí tuệ nhân tạo và Khoa học dữ liệu', 'Thông tư 38/2013/TT-BGDĐT', '2021-04-22', NULL, NULL, 'Bộ tiêu chuẩn quy định các yêu cầu kiểm định và bảo đảm chất lượng giáo dục cho ngành CNTT đợt 5.', 1, '2026-08-31 12:25:35'),
('BTC06', 'Bộ tiêu chuẩn kiểm định chất lượng chương trình đào tạo ngành An toàn thông tin', 'Chuẩn kiểm định ABET CAC', '2022-04-17', NULL, NULL, 'Bộ tiêu chuẩn quy định các yêu cầu kiểm định và bảo đảm chất lượng giáo dục cho ngành CNTT đợt 6.', 1, '2026-08-31 12:25:35'),
('BTC07', 'Bộ tiêu chuẩn đánh giá chất lượng chương trình đào tạo Thạc sĩ Công nghệ thông tin', 'Thông tư 17/2021/TT-BGDĐT', '2019-10-21', NULL, NULL, 'Bộ tiêu chuẩn quy định các yêu cầu kiểm định và bảo đảm chất lượng giáo dục cho ngành CNTT đợt 7.', 1, '2026-08-31 12:25:35'),
('BTC08', 'Bộ tiêu chuẩn kiểm định chất lượng chương trình đào tạo Tiến sĩ Công nghệ thông tin', 'Chuẩn kiểm định AUN-QA v4.0', '2019-07-21', NULL, NULL, 'Bộ tiêu chuẩn quy định các yêu cầu kiểm định và bảo đảm chất lượng giáo dục cho ngành CNTT đợt 8.', 1, '2026-08-31 12:25:35'),
('BTC09', 'Bộ tiêu chuẩn đánh giá chất lượng chương trình đào tạo cử nhân Khoa học máy tính', 'Thông tư 04/2016/TT-BGDĐT', '2019-08-24', NULL, NULL, 'Bộ tiêu chuẩn quy định các yêu cầu kiểm định và bảo đảm chất lượng giáo dục cho ngành CNTT đợt 9.', 1, '2026-08-31 12:25:35'),
('BTC10', 'Bộ tiêu chuẩn chuẩn hóa năng lực công nghệ thông tin định hướng chuẩn kỹ năng ITSS', 'Thông tư 17/2021/TT-BGDĐT', '2018-02-05', NULL, NULL, 'Bộ tiêu chuẩn quy định các yêu cầu kiểm định và bảo đảm chất lượng giáo dục cho ngành CNTT đợt 10.', 0, '2026-08-31 12:25:35'),
('BTC11', 'Bộ tiêu chuẩn kiểm định chất lượng chương trình đào tạo ngành CNTT bổ sung đợt 11', 'Chuẩn kiểm định ABET CAC', '2025-02-01', NULL, NULL, 'Bộ tiêu chuẩn quy định các yêu cầu kiểm định và bảo đảm chất lượng giáo dục cho ngành CNTT đợt 11.', 1, '2026-08-31 12:25:35'),
('BTC12', 'Bộ tiêu chuẩn kiểm định chất lượng chương trình đào tạo ngành CNTT bổ sung đợt 12', 'Chuẩn kiểm định ABET CAC', '2020-09-14', NULL, NULL, 'Bộ tiêu chuẩn quy định các yêu cầu kiểm định và bảo đảm chất lượng giáo dục cho ngành CNTT đợt 12.', 1, '2026-08-31 12:25:35'),
('BTC13', 'Bộ tiêu chuẩn kiểm định chất lượng chương trình đào tạo ngành CNTT bổ sung đợt 13', 'Thông tư 17/2021/TT-BGDĐT', '2023-11-17', NULL, NULL, 'Bộ tiêu chuẩn quy định các yêu cầu kiểm định và bảo đảm chất lượng giáo dục cho ngành CNTT đợt 13.', 1, '2026-08-31 12:25:35'),
('BTC14', 'Bộ tiêu chuẩn kiểm định chất lượng chương trình đào tạo ngành CNTT bổ sung đợt 14', 'Thông tư 12/2017/TT-BGDĐT', '2018-10-28', NULL, NULL, 'Bộ tiêu chuẩn quy định các yêu cầu kiểm định và bảo đảm chất lượng giáo dục cho ngành CNTT đợt 14.', 1, '2026-08-31 12:25:35'),
('BTC15', 'Bộ tiêu chuẩn kiểm định chất lượng chương trình đào tạo ngành CNTT bổ sung đợt 15', 'Thông tư 04/2016/TT-BGDĐT', '2021-06-12', NULL, NULL, 'Bộ tiêu chuẩn quy định các yêu cầu kiểm định và bảo đảm chất lượng giáo dục cho ngành CNTT đợt 15.', 1, '2026-08-31 12:25:35'),
('BTC16', 'Bộ tiêu chuẩn kiểm định chất lượng chương trình đào tạo ngành CNTT bổ sung đợt 16', 'Thông tư 12/2017/TT-BGDĐT', '2022-11-11', NULL, NULL, 'Bộ tiêu chuẩn quy định các yêu cầu kiểm định và bảo đảm chất lượng giáo dục cho ngành CNTT đợt 16.', 1, '2026-08-31 12:25:35'),
('BTC17', 'Bộ tiêu chuẩn kiểm định chất lượng chương trình đào tạo ngành CNTT bổ sung đợt 17', 'Chuẩn kiểm định AUN-QA v4.0', '2020-09-15', NULL, NULL, 'Bộ tiêu chuẩn quy định các yêu cầu kiểm định và bảo đảm chất lượng giáo dục cho ngành CNTT đợt 17.', 1, '2026-08-31 12:25:35'),
('BTC18', 'Bộ tiêu chuẩn kiểm định chất lượng chương trình đào tạo ngành CNTT bổ sung đợt 18', 'Thông tư 38/2013/TT-BGDĐT', '2018-01-15', NULL, NULL, 'Bộ tiêu chuẩn quy định các yêu cầu kiểm định và bảo đảm chất lượng giáo dục cho ngành CNTT đợt 18.', 1, '2026-08-31 12:25:35'),
('BTC19', 'Bộ tiêu chuẩn kiểm định chất lượng chương trình đào tạo ngành CNTT bổ sung đợt 19', 'Thông tư 12/2017/TT-BGDĐT', '2019-12-23', NULL, NULL, 'Bộ tiêu chuẩn quy định các yêu cầu kiểm định và bảo đảm chất lượng giáo dục cho ngành CNTT đợt 19.', 1, '2026-08-31 12:25:35'),
('BTC20', 'Bộ tiêu chuẩn kiểm định chất lượng chương trình đào tạo ngành CNTT bổ sung đợt 20', 'Chuẩn kiểm định AUN-QA v4.0', '2025-08-04', NULL, NULL, 'Bộ tiêu chuẩn quy định các yêu cầu kiểm định và bảo đảm chất lượng giáo dục cho ngành CNTT đợt 20.', 0, '2026-08-31 12:25:35'),
('BTC21', 'Bộ tiêu chuẩn kiểm định chất lượng chương trình đào tạo ngành CNTT bổ sung đợt 21', 'Quyết định 78/QĐ-BGDĐT', '2018-02-10', NULL, NULL, 'Bộ tiêu chuẩn quy định các yêu cầu kiểm định và bảo đảm chất lượng giáo dục cho ngành CNTT đợt 21.', 1, '2026-08-31 12:25:35'),
('BTC22', 'Bộ tiêu chuẩn kiểm định chất lượng chương trình đào tạo ngành CNTT bổ sung đợt 22', 'Thông tư 17/2021/TT-BGDĐT', '2021-03-01', NULL, NULL, 'Bộ tiêu chuẩn quy định các yêu cầu kiểm định và bảo đảm chất lượng giáo dục cho ngành CNTT đợt 22.', 1, '2026-08-31 12:25:35'),
('BTC23', 'Bộ tiêu chuẩn kiểm định chất lượng chương trình đào tạo ngành CNTT bổ sung đợt 23', 'Thông tư 38/2013/TT-BGDĐT', '2024-02-26', NULL, NULL, 'Bộ tiêu chuẩn quy định các yêu cầu kiểm định và bảo đảm chất lượng giáo dục cho ngành CNTT đợt 23.', 1, '2026-08-31 12:25:35'),
('BTC24', 'Bộ tiêu chuẩn kiểm định chất lượng chương trình đào tạo ngành CNTT bổ sung đợt 24', 'Thông tư 12/2017/TT-BGDĐT', '2019-07-22', NULL, NULL, 'Bộ tiêu chuẩn quy định các yêu cầu kiểm định và bảo đảm chất lượng giáo dục cho ngành CNTT đợt 24.', 1, '2026-08-31 12:25:35'),
('BTC25', 'Bộ tiêu chuẩn kiểm định chất lượng chương trình đào tạo ngành CNTT bổ sung đợt 25', 'Chuẩn kiểm định AUN-QA v4.0', '2023-02-03', NULL, NULL, 'Bộ tiêu chuẩn quy định các yêu cầu kiểm định và bảo đảm chất lượng giáo dục cho ngành CNTT đợt 25.', 1, '2026-08-31 12:25:35'),
('BTC26', 'Bộ tiêu chuẩn kiểm định chất lượng chương trình đào tạo ngành CNTT bổ sung đợt 26', 'Chuẩn kiểm định AUN-QA v4.0', '2023-04-25', NULL, NULL, 'Bộ tiêu chuẩn quy định các yêu cầu kiểm định và bảo đảm chất lượng giáo dục cho ngành CNTT đợt 26.', 1, '2026-08-31 12:25:35'),
('BTC27', 'Bộ tiêu chuẩn kiểm định chất lượng chương trình đào tạo ngành CNTT bổ sung đợt 27', 'Thông tư 17/2021/TT-BGDĐT', '2025-12-20', NULL, NULL, 'Bộ tiêu chuẩn quy định các yêu cầu kiểm định và bảo đảm chất lượng giáo dục cho ngành CNTT đợt 27.', 1, '2026-08-31 12:25:35'),
('BTC28', 'Bộ tiêu chuẩn kiểm định chất lượng chương trình đào tạo ngành CNTT bổ sung đợt 28', 'Thông tư 17/2021/TT-BGDĐT', '2019-06-03', NULL, NULL, 'Bộ tiêu chuẩn quy định các yêu cầu kiểm định và bảo đảm chất lượng giáo dục cho ngành CNTT đợt 28.', 1, '2026-08-31 12:25:35'),
('BTC29', 'Bộ tiêu chuẩn kiểm định chất lượng chương trình đào tạo ngành CNTT bổ sung đợt 29', 'Chuẩn kiểm định AUN-QA v4.0', '2025-03-07', NULL, NULL, 'Bộ tiêu chuẩn quy định các yêu cầu kiểm định và bảo đảm chất lượng giáo dục cho ngành CNTT đợt 29.', 1, '2026-08-31 12:25:35'),
('BTC30', 'Bộ tiêu chuẩn kiểm định chất lượng chương trình đào tạo ngành CNTT bổ sung đợt 30', 'Thông tư 12/2017/TT-BGDĐT', '2022-01-20', NULL, NULL, 'Bộ tiêu chuẩn quy định các yêu cầu kiểm định và bảo đảm chất lượng giáo dục cho ngành CNTT đợt 30.', 0, '2026-08-31 12:25:35'),
('BTC31', 'Bộ tiêu chuẩn kiểm định chất lượng chương trình đào tạo ngành CNTT bổ sung đợt 31', 'Chuẩn kiểm định AUN-QA v4.0', '2023-03-18', NULL, NULL, 'Bộ tiêu chuẩn quy định các yêu cầu kiểm định và bảo đảm chất lượng giáo dục cho ngành CNTT đợt 31.', 1, '2026-08-31 12:25:35'),
('BTC32', 'Bộ tiêu chuẩn kiểm định chất lượng chương trình đào tạo ngành CNTT bổ sung đợt 32', 'Thông tư 17/2021/TT-BGDĐT', '2025-11-05', NULL, NULL, 'Bộ tiêu chuẩn quy định các yêu cầu kiểm định và bảo đảm chất lượng giáo dục cho ngành CNTT đợt 32.', 1, '2026-08-31 12:25:35'),
('BTC33', 'Bộ tiêu chuẩn kiểm định chất lượng chương trình đào tạo ngành CNTT bổ sung đợt 33', 'Quyết định 78/QĐ-BGDĐT', '2021-09-23', NULL, NULL, 'Bộ tiêu chuẩn quy định các yêu cầu kiểm định và bảo đảm chất lượng giáo dục cho ngành CNTT đợt 33.', 1, '2026-08-31 12:25:35'),
('BTC34', 'Bộ tiêu chuẩn kiểm định chất lượng chương trình đào tạo ngành CNTT bổ sung đợt 34', 'Thông tư 04/2016/TT-BGDĐT', '2025-07-14', NULL, NULL, 'Bộ tiêu chuẩn quy định các yêu cầu kiểm định và bảo đảm chất lượng giáo dục cho ngành CNTT đợt 34.', 1, '2026-08-31 12:25:35'),
('BTC35', 'Bộ tiêu chuẩn kiểm định chất lượng chương trình đào tạo ngành CNTT bổ sung đợt 35', 'Chuẩn kiểm định ABET CAC', '2022-08-26', NULL, NULL, 'Bộ tiêu chuẩn quy định các yêu cầu kiểm định và bảo đảm chất lượng giáo dục cho ngành CNTT đợt 35.', 1, '2026-08-31 12:25:35'),
('BTC36', 'Bộ tiêu chuẩn kiểm định chất lượng chương trình đào tạo ngành CNTT bổ sung đợt 36', 'Thông tư 04/2016/TT-BGDĐT', '2022-11-12', NULL, NULL, 'Bộ tiêu chuẩn quy định các yêu cầu kiểm định và bảo đảm chất lượng giáo dục cho ngành CNTT đợt 36.', 1, '2026-08-31 12:25:35'),
('BTC37', 'Bộ tiêu chuẩn kiểm định chất lượng chương trình đào tạo ngành CNTT bổ sung đợt 37', 'Quyết định 78/QĐ-BGDĐT', '2022-04-04', NULL, NULL, 'Bộ tiêu chuẩn quy định các yêu cầu kiểm định và bảo đảm chất lượng giáo dục cho ngành CNTT đợt 37.', 1, '2026-08-31 12:25:35'),
('BTC38', 'Bộ tiêu chuẩn kiểm định chất lượng chương trình đào tạo ngành CNTT bổ sung đợt 38', 'Thông tư 12/2017/TT-BGDĐT', '2022-10-07', NULL, NULL, 'Bộ tiêu chuẩn quy định các yêu cầu kiểm định và bảo đảm chất lượng giáo dục cho ngành CNTT đợt 38.', 1, '2026-08-31 12:25:35'),
('BTC39', 'Bộ tiêu chuẩn kiểm định chất lượng chương trình đào tạo ngành CNTT bổ sung đợt 39', 'Thông tư 38/2013/TT-BGDĐT', '2019-01-19', NULL, NULL, 'Bộ tiêu chuẩn quy định các yêu cầu kiểm định và bảo đảm chất lượng giáo dục cho ngành CNTT đợt 39.', 1, '2026-08-31 12:25:35'),
('BTC40', 'Bộ tiêu chuẩn kiểm định chất lượng chương trình đào tạo ngành CNTT bổ sung đợt 40', 'Quyết định 78/QĐ-BGDĐT', '2019-04-19', NULL, NULL, 'Bộ tiêu chuẩn quy định các yêu cầu kiểm định và bảo đảm chất lượng giáo dục cho ngành CNTT đợt 40.', 0, '2026-08-31 12:25:35'),
('BTC41', 'Bộ tiêu chuẩn kiểm định chất lượng chương trình đào tạo ngành CNTT bổ sung đợt 41', 'Chuẩn kiểm định AUN-QA v4.0', '2023-08-05', NULL, NULL, 'Bộ tiêu chuẩn quy định các yêu cầu kiểm định và bảo đảm chất lượng giáo dục cho ngành CNTT đợt 41.', 1, '2026-08-31 12:25:35'),
('BTC42', 'Bộ tiêu chuẩn kiểm định chất lượng chương trình đào tạo ngành CNTT bổ sung đợt 42', 'Thông tư 04/2016/TT-BGDĐT', '2021-04-25', NULL, NULL, 'Bộ tiêu chuẩn quy định các yêu cầu kiểm định và bảo đảm chất lượng giáo dục cho ngành CNTT đợt 42.', 1, '2026-08-31 12:25:35'),
('BTC43', 'Bộ tiêu chuẩn kiểm định chất lượng chương trình đào tạo ngành CNTT bổ sung đợt 43', 'Quyết định 78/QĐ-BGDĐT', '2022-03-27', NULL, NULL, 'Bộ tiêu chuẩn quy định các yêu cầu kiểm định và bảo đảm chất lượng giáo dục cho ngành CNTT đợt 43.', 1, '2026-08-31 12:25:35'),
('BTC44', 'Bộ tiêu chuẩn kiểm định chất lượng chương trình đào tạo ngành CNTT bổ sung đợt 44', 'Quyết định 78/QĐ-BGDĐT', '2021-10-17', NULL, NULL, 'Bộ tiêu chuẩn quy định các yêu cầu kiểm định và bảo đảm chất lượng giáo dục cho ngành CNTT đợt 44.', 1, '2026-08-31 12:25:35'),
('BTC45', 'Bộ tiêu chuẩn kiểm định chất lượng chương trình đào tạo ngành CNTT bổ sung đợt 45', 'Thông tư 04/2016/TT-BGDĐT', '2023-01-14', NULL, NULL, 'Bộ tiêu chuẩn quy định các yêu cầu kiểm định và bảo đảm chất lượng giáo dục cho ngành CNTT đợt 45.', 1, '2026-08-31 12:25:35'),
('BTC46', 'Bộ tiêu chuẩn kiểm định chất lượng chương trình đào tạo ngành CNTT bổ sung đợt 46', 'Thông tư 04/2016/TT-BGDĐT', '2025-03-24', NULL, NULL, 'Bộ tiêu chuẩn quy định các yêu cầu kiểm định và bảo đảm chất lượng giáo dục cho ngành CNTT đợt 46.', 1, '2026-08-31 12:25:35'),
('BTC47', 'Bộ tiêu chuẩn kiểm định chất lượng chương trình đào tạo ngành CNTT bổ sung đợt 47', 'Chuẩn kiểm định AUN-QA v4.0', '2023-07-13', NULL, NULL, 'Bộ tiêu chuẩn quy định các yêu cầu kiểm định và bảo đảm chất lượng giáo dục cho ngành CNTT đợt 47.', 1, '2026-08-31 12:25:35'),
('BTC48', 'Bộ tiêu chuẩn kiểm định chất lượng chương trình đào tạo ngành CNTT bổ sung đợt 48', 'Thông tư 04/2016/TT-BGDĐT', '2024-01-16', NULL, NULL, 'Bộ tiêu chuẩn quy định các yêu cầu kiểm định và bảo đảm chất lượng giáo dục cho ngành CNTT đợt 48.', 1, '2026-08-31 12:25:35'),
('BTC49', 'Bộ tiêu chuẩn kiểm định chất lượng chương trình đào tạo ngành CNTT bổ sung đợt 49', 'Thông tư 17/2021/TT-BGDĐT', '2025-08-04', NULL, NULL, 'Bộ tiêu chuẩn quy định các yêu cầu kiểm định và bảo đảm chất lượng giáo dục cho ngành CNTT đợt 49.', 1, '2026-08-31 12:25:35'),
('BTC50', 'Bộ tiêu chuẩn kiểm định chất lượng chương trình đào tạo ngành CNTT bổ sung đợt 50', 'Thông tư 38/2013/TT-BGDĐT', '2022-12-13', NULL, NULL, 'Bộ tiêu chuẩn quy định các yêu cầu kiểm định và bảo đảm chất lượng giáo dục cho ngành CNTT đợt 50.', 0, '2026-08-31 12:25:35'),
('BTC51', 'Bộ tiêu chuẩn kiểm định chất lượng chương trình đào tạo ngành CNTT bổ sung đợt 51', 'Quyết định 78/QĐ-BGDĐT', '2022-02-24', NULL, NULL, 'Bộ tiêu chuẩn quy định các yêu cầu kiểm định và bảo đảm chất lượng giáo dục cho ngành CNTT đợt 51.', 1, '2026-08-31 12:25:35'),
('BTC52', 'Bộ tiêu chuẩn kiểm định chất lượng chương trình đào tạo ngành CNTT bổ sung đợt 52', 'Quyết định 78/QĐ-BGDĐT', '2019-10-14', NULL, NULL, 'Bộ tiêu chuẩn quy định các yêu cầu kiểm định và bảo đảm chất lượng giáo dục cho ngành CNTT đợt 52.', 1, '2026-08-31 12:25:35'),
('BTC53', 'Bộ tiêu chuẩn kiểm định chất lượng chương trình đào tạo ngành CNTT bổ sung đợt 53', 'Chuẩn kiểm định ABET CAC', '2019-12-26', NULL, NULL, 'Bộ tiêu chuẩn quy định các yêu cầu kiểm định và bảo đảm chất lượng giáo dục cho ngành CNTT đợt 53.', 1, '2026-08-31 12:25:35'),
('BTC54', 'Bộ tiêu chuẩn kiểm định chất lượng chương trình đào tạo ngành CNTT bổ sung đợt 54', 'Quyết định 78/QĐ-BGDĐT', '2024-03-11', NULL, NULL, 'Bộ tiêu chuẩn quy định các yêu cầu kiểm định và bảo đảm chất lượng giáo dục cho ngành CNTT đợt 54.', 1, '2026-08-31 12:25:35'),
('BTC55', 'Bộ tiêu chuẩn kiểm định chất lượng chương trình đào tạo ngành CNTT bổ sung đợt 55', 'Chuẩn kiểm định AUN-QA v4.0', '2018-12-07', NULL, NULL, 'Bộ tiêu chuẩn quy định các yêu cầu kiểm định và bảo đảm chất lượng giáo dục cho ngành CNTT đợt 55.', 1, '2026-08-31 12:25:35');

-- --------------------------------------------------------

--
-- Table structure for table `download_logs`
--

CREATE TABLE `download_logs` (
  `id` bigint(20) NOT NULL,
  `MaNguoiDung` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `MaMinhChung` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ngay_tai` timestamp NOT NULL DEFAULT current_timestamp(),
  `dia_chi_ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `download_logs`
--

INSERT INTO `download_logs` (`id`, `MaNguoiDung`, `MaMinhChung`, `ngay_tai`, `dia_chi_ip`) VALUES
(2, NULL, 'MC01', '2026-08-31 14:15:31', '::1'),
(3, NULL, 'MC01', '2026-08-31 14:15:41', '::1'),
(4, NULL, 'MC04', '2026-08-31 14:18:16', '::1');

-- --------------------------------------------------------

--
-- Table structure for table `loaiminhchung`
--

CREATE TABLE `loaiminhchung` (
  `MaLoai` int(11) NOT NULL,
  `TenLoai` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `MoTa` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `NgayTao` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `loaiminhchung`
--

INSERT INTO `loaiminhchung` (`MaLoai`, `TenLoai`, `MoTa`, `NgayTao`) VALUES
(1, 'Minh chứng chính', 'Các văn bản, quyết định chính thức', '2026-07-30 14:39:03'),
(2, 'Minh chứng bổ sung', 'Các hồ sơ, phụ lục đính kèm bổ sung', '2026-07-30 14:39:03'),
(3, 'Minh chứng khảo sát', 'Phiếu thu thập ý kiến các bên liên quan', '2026-07-30 14:39:03');

-- --------------------------------------------------------

--
-- Table structure for table `MinhChung`
--

CREATE TABLE `MinhChung` (
  `MaMinhChung` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `TenMinhChung` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `MoTa` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `TepTin` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `NamHoc` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `NgayCapNhat` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `TrangThai` tinyint(1) NOT NULL DEFAULT 1,
  `MaLoai` int(11) DEFAULT NULL,
  `MaTieuChi` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `MaNguoiDung` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `NgayTao` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `MinhChung`
--

INSERT INTO `MinhChung` (`MaMinhChung`, `TenMinhChung`, `MoTa`, `TepTin`, `NamHoc`, `NgayCapNhat`, `TrangThai`, `MaLoai`, `MaTieuChi`, `MaNguoiDung`, `NgayTao`) VALUES
('MC01', 'Quyết định ban hành mục tiêu và chuẩn đầu ra ngành Công nghệ thông tin - Tập 1', 'Hồ sơ minh chứng chính thức phục vụ công tác kiểm định chất lượng giáo dục đợt 1.', 'uploads/evidences/MC_04_01_02.pdf', '2024-2025', '2026-08-31 21:14:26', 1, NULL, 'TC30', 'ND002', '2026-08-31 12:25:35'),
('MC02', 'Bản mô tả chương trình đào tạo ngành Công nghệ thông tin năm 2025 - Tập 1', 'Hồ sơ minh chứng chính thức phục vụ công tác kiểm định chất lượng giáo dục đợt 2.', 'uploads/evidences/KeHoach_CaiTien_ChatLuong.pdf', '2023-2024', '2026-08-31 21:14:26', 1, NULL, 'TC02', 'ND003', '2026-08-31 12:25:35'),
('MC03', 'Đề cương chi tiết các học phần chuyên ngành Công nghệ phần mềm - Tập 1', 'Hồ sơ minh chứng chính thức phục vụ công tác kiểm định chất lượng giáo dục đợt 3.', 'uploads/evidences/MC_01_01_01.pdf', '2024-2025', '2026-08-31 21:14:26', 1, NULL, 'TC15', 'ND001', '2026-08-31 12:25:35'),
('MC04', 'Kế hoạch đổi mới phương pháp dạy và học định hướng ứng dụng thực tiễn - Tập 1', 'Hồ sơ minh chứng chính thức phục vụ công tác kiểm định chất lượng giáo dục đợt 4.', 'uploads/evidences/MC_01_01_01.pdf', '2025-2026', '2026-08-31 21:14:26', 1, NULL, 'TC19', 'ND002', '2026-08-31 12:25:35'),
('MC05', 'Quy chế đánh giá học phần và ma trận kiểm tra đánh giá CĐR - Tập 1', 'Hồ sơ minh chứng chính thức phục vụ công tác kiểm định chất lượng giáo dục đợt 5.', 'uploads/evidences/KhaoSat_DoanhNghiep_2025.docx', '2025-2026', '2026-08-31 21:14:26', 1, NULL, 'TC35', 'ND003', '2026-08-31 12:25:35'),
('MC06', 'Biên bản rà soát và cập nhật chương trình đào tạo định kỳ năm 2025 - Tập 1', 'Hồ sơ minh chứng chính thức phục vụ công tác kiểm định chất lượng giáo dục đợt 6.', 'uploads/evidences/MC_01_01_01.pdf', '2024-2025', '2026-08-31 21:14:26', 1, NULL, 'TC14', 'ND001', '2026-08-31 12:25:35'),
('MC07', 'Phiếu khảo sát ý kiến doanh nghiệp về chất lượng sinh viên tốt nghiệp - Tập 1', 'Hồ sơ minh chứng chính thức phục vụ công tác kiểm định chất lượng giáo dục đợt 7.', 'uploads/evidences/KeHoach_CaiTien_ChatLuong.pdf', '2024-2025', '2026-08-31 21:14:26', 1, NULL, 'TC56', 'ND002', '2026-08-31 12:25:35'),
('MC08', 'Báo cáo tự đánh giá chất lượng chương trình đào tạo CNTT - Tập 1', 'Hồ sơ minh chứng chính thức phục vụ công tác kiểm định chất lượng giáo dục đợt 8.', 'uploads/evidences/MC_01_01_01.pdf', '2024-2025', '2026-08-31 21:14:26', 1, NULL, 'TC41', 'ND003', '2026-08-31 12:25:35'),
('MC09', 'Danh sách công trình nghiên cứu khoa học và bài báo của giảng viên - Tập 1', 'Hồ sơ minh chứng chính thức phục vụ công tác kiểm định chất lượng giáo dục đợt 9.', 'uploads/evidences/MC_02_01_03.docx', '2025-2026', '2026-08-31 21:14:26', 1, NULL, 'TC07', 'ND001', '2026-08-31 12:25:35'),
('MC11', 'Báo cáo tổng kết công tác tuyển sinh và phân tích chất lượng đầu vào - Tập 1', 'Hồ sơ minh chứng chính thức phục vụ công tác kiểm định chất lượng giáo dục đợt 11.', 'uploads/evidences/MC_01_01_01.pdf', '2023-2024', '2026-08-31 21:14:26', 1, NULL, 'TC41', 'ND003', '2026-08-31 12:25:35'),
('MC12', 'Sổ tay hướng dẫn thực tập tốt nghiệp và đồ án khóa luận - Tập 1', 'Hồ sơ minh chứng chính thức phục vụ công tác kiểm định chất lượng giáo dục đợt 12.', 'uploads/evidences/MC_02_01_03.docx', '2023-2024', '2026-08-31 21:14:26', 1, NULL, 'TC08', 'ND001', '2026-08-31 12:25:35'),
('MC13', 'Biên bản nghiệm thu nâng cấp hệ thống phòng máy tính thực hành - Tập 1', 'Hồ sơ minh chứng chính thức phục vụ công tác kiểm định chất lượng giáo dục đợt 13.', 'uploads/evidences/MC_02_01_03.docx', '2025-2026', '2026-08-31 21:14:26', 1, NULL, 'TC52', 'ND002', '2026-08-31 12:25:35'),
('MC14', 'Quyết định khen thưởng sinh viên có thành tích xuất sắc trong học tập - Tập 1', 'Hồ sơ minh chứng chính thức phục vụ công tác kiểm định chất lượng giáo dục đợt 14.', 'uploads/evidences/KeHoach_CaiTien_ChatLuong.pdf', '2024-2025', '2026-08-31 21:14:26', 1, NULL, 'TC37', 'ND003', '2026-08-31 12:25:35'),
('MC15', 'Báo cáo tình hình việc làm của sinh viên sau 1 năm tốt nghiệp - Tập 1', 'Hồ sơ minh chứng chính thức phục vụ công tác kiểm định chất lượng giáo dục đợt 15.', 'uploads/evidences/QuyDuyet_CTDT_2025.pdf', '2023-2024', '2026-08-31 21:14:26', 1, NULL, 'TC49', 'ND001', '2026-08-31 12:25:35'),
('MC16', 'Quyết định ban hành mục tiêu và chuẩn đầu ra ngành Công nghệ thông tin - Tập 2', 'Hồ sơ minh chứng chính thức phục vụ công tác kiểm định chất lượng giáo dục đợt 16.', 'uploads/evidences/KeHoach_CaiTien_ChatLuong.pdf', '2023-2024', '2026-08-31 21:14:26', 1, NULL, 'TC42', 'ND002', '2026-08-31 12:25:35'),
('MC17', 'Bản mô tả chương trình đào tạo ngành Công nghệ thông tin năm 2025 - Tập 2', 'Hồ sơ minh chứng chính thức phục vụ công tác kiểm định chất lượng giáo dục đợt 17.', 'uploads/evidences/BaoCao_TuDanhGia_CNTT.pdf', '2024-2025', '2026-08-31 21:14:26', 1, NULL, 'TC60', 'ND003', '2026-08-31 12:25:35'),
('MC18', 'Đề cương chi tiết các học phần chuyên ngành Công nghệ phần mềm - Tập 2', 'Hồ sơ minh chứng chính thức phục vụ công tác kiểm định chất lượng giáo dục đợt 18.', 'uploads/evidences/MC_01_01_01.pdf', '2025-2026', '2026-08-31 21:14:26', 1, NULL, 'TC57', 'ND001', '2026-08-31 12:25:35'),
('MC19', 'Kế hoạch đổi mới phương pháp dạy và học định hướng ứng dụng thực tiễn - Tập 2', 'Hồ sơ minh chứng chính thức phục vụ công tác kiểm định chất lượng giáo dục đợt 19.', 'uploads/evidences/QuyChe_DanhGia_HocPhan.pdf', '2024-2025', '2026-08-31 21:14:26', 1, NULL, 'TC09', 'ND002', '2026-08-31 12:25:35'),
('MC20', 'Quy chế đánh giá học phần và ma trận kiểm tra đánh giá CĐR - Tập 2', 'Hồ sơ minh chứng chính thức phục vụ công tác kiểm định chất lượng giáo dục đợt 20.', 'uploads/evidences/KeHoach_CaiTien_ChatLuong.pdf', '2023-2024', '2026-08-31 21:14:26', 0, NULL, 'TC23', 'ND003', '2026-08-31 12:25:35'),
('MC21', 'Biên bản rà soát và cập nhật chương trình đào tạo định kỳ năm 2025 - Tập 2', 'Hồ sơ minh chứng chính thức phục vụ công tác kiểm định chất lượng giáo dục đợt 21.', 'uploads/evidences/QuyChe_DanhGia_HocPhan.pdf', '2025-2026', '2026-08-31 21:14:26', 1, NULL, 'TC38', 'ND001', '2026-08-31 12:25:35'),
('MC22', 'Phiếu khảo sát ý kiến doanh nghiệp về chất lượng sinh viên tốt nghiệp - Tập 2', 'Hồ sơ minh chứng chính thức phục vụ công tác kiểm định chất lượng giáo dục đợt 22.', 'uploads/evidences/BaoCao_TuDanhGia_CNTT.pdf', '2024-2025', '2026-08-31 21:14:26', 1, NULL, 'TC33', 'ND002', '2026-08-31 12:25:35'),
('MC23', 'Báo cáo tự đánh giá chất lượng chương trình đào tạo CNTT - Tập 2', 'Hồ sơ minh chứng chính thức phục vụ công tác kiểm định chất lượng giáo dục đợt 23.', 'uploads/evidences/MC_05_03_05.xlsx', '2025-2026', '2026-08-31 21:14:26', 1, NULL, 'TC59', 'ND003', '2026-08-31 12:25:35'),
('MC24', 'Danh sách công trình nghiên cứu khoa học và bài báo của giảng viên - Tập 2', 'Hồ sơ minh chứng chính thức phục vụ công tác kiểm định chất lượng giáo dục đợt 24.', 'uploads/evidences/MC_03_02_04.zip', '2024-2025', '2026-08-31 21:14:26', 1, NULL, 'TC19', 'ND001', '2026-08-31 12:25:35'),
('MC25', 'Quyết định thành lập Hội đồng kiểm định chất lượng giáo dục - Tập 2', 'Hồ sơ minh chứng chính thức phục vụ công tác kiểm định chất lượng giáo dục đợt 25.', 'uploads/evidences/BienBan_Hop_Rasoat_CDR.docx', '2025-2026', '2026-08-31 21:14:26', 1, NULL, 'TC50', 'ND002', '2026-08-31 12:25:35'),
('MC26', 'Báo cáo tổng kết công tác tuyển sinh và phân tích chất lượng đầu vào - Tập 2', 'Hồ sơ minh chứng chính thức phục vụ công tác kiểm định chất lượng giáo dục đợt 26.', 'uploads/evidences/MC_05_03_05.xlsx', '2024-2025', '2026-08-31 21:14:26', 1, NULL, 'TC58', 'ND003', '2026-08-31 12:25:35'),
('MC27', 'Sổ tay hướng dẫn thực tập tốt nghiệp và đồ án khóa luận - Tập 2', 'Hồ sơ minh chứng chính thức phục vụ công tác kiểm định chất lượng giáo dục đợt 27.', 'uploads/evidences/QuyChe_DanhGia_HocPhan.pdf', '2024-2025', '2026-08-31 21:14:26', 1, NULL, 'TC43', 'ND001', '2026-08-31 12:25:35'),
('MC28', 'Biên bản nghiệm thu nâng cấp hệ thống phòng máy tính thực hành - Tập 2', 'Hồ sơ minh chứng chính thức phục vụ công tác kiểm định chất lượng giáo dục đợt 28.', 'uploads/evidences/QuyChe_DanhGia_HocPhan.pdf', '2025-2026', '2026-08-31 21:14:26', 1, NULL, 'TC10', 'ND002', '2026-08-31 12:25:35'),
('MC29', 'Quyết định khen thưởng sinh viên có thành tích xuất sắc trong học tập - Tập 2', 'Hồ sơ minh chứng chính thức phục vụ công tác kiểm định chất lượng giáo dục đợt 29.', 'uploads/evidences/BaoCao_TuDanhGia_CNTT.pdf', '2023-2024', '2026-08-31 21:14:26', 1, NULL, 'TC09', 'ND003', '2026-08-31 12:25:35'),
('MC30', 'Báo cáo tình hình việc làm của sinh viên sau 1 năm tốt nghiệp - Tập 2', 'Hồ sơ minh chứng chính thức phục vụ công tác kiểm định chất lượng giáo dục đợt 30.', 'uploads/evidences/MC_05_03_05.xlsx', '2025-2026', '2026-08-31 21:14:26', 0, NULL, 'TC12', 'ND001', '2026-08-31 12:25:35'),
('MC31', 'Quyết định ban hành mục tiêu và chuẩn đầu ra ngành Công nghệ thông tin - Tập 3', 'Hồ sơ minh chứng chính thức phục vụ công tác kiểm định chất lượng giáo dục đợt 31.', 'uploads/evidences/DanhSach_GiangVien_2025.xlsx', '2023-2024', '2026-08-31 21:14:26', 1, NULL, 'TC03', 'ND002', '2026-08-31 12:25:35'),
('MC32', 'Bản mô tả chương trình đào tạo ngành Công nghệ thông tin năm 2025 - Tập 3', 'Hồ sơ minh chứng chính thức phục vụ công tác kiểm định chất lượng giáo dục đợt 32.', 'uploads/evidences/MC_02_01_03.docx', '2024-2025', '2026-08-31 21:14:26', 1, NULL, 'TC50', 'ND003', '2026-08-31 12:25:35'),
('MC33', 'Đề cương chi tiết các học phần chuyên ngành Công nghệ phần mềm - Tập 3', 'Hồ sơ minh chứng chính thức phục vụ công tác kiểm định chất lượng giáo dục đợt 33.', 'uploads/evidences/DanhSach_GiangVien_2025.xlsx', '2025-2026', '2026-08-31 21:14:26', 1, NULL, 'TC25', 'ND001', '2026-08-31 12:25:35'),
('MC34', 'Kế hoạch đổi mới phương pháp dạy và học định hướng ứng dụng thực tiễn - Tập 3', 'Hồ sơ minh chứng chính thức phục vụ công tác kiểm định chất lượng giáo dục đợt 34.', 'uploads/evidences/QuyChe_DanhGia_HocPhan.pdf', '2025-2026', '2026-08-31 21:14:26', 1, NULL, 'TC14', 'ND002', '2026-08-31 12:25:35'),
('MC35', 'Quy chế đánh giá học phần và ma trận kiểm tra đánh giá CĐR - Tập 3', 'Hồ sơ minh chứng chính thức phục vụ công tác kiểm định chất lượng giáo dục đợt 35.', 'uploads/evidences/MC_02_01_03.docx', '2025-2026', '2026-08-31 21:14:26', 1, NULL, 'TC25', 'ND003', '2026-08-31 12:25:35'),
('MC36', 'Biên bản rà soát và cập nhật chương trình đào tạo định kỳ năm 2025 - Tập 3', 'Hồ sơ minh chứng chính thức phục vụ công tác kiểm định chất lượng giáo dục đợt 36.', 'uploads/evidences/MC_04_01_02.pdf', '2024-2025', '2026-08-31 21:14:26', 1, NULL, 'TC51', 'ND001', '2026-08-31 12:25:35'),
('MC37', 'Phiếu khảo sát ý kiến doanh nghiệp về chất lượng sinh viên tốt nghiệp - Tập 3', 'Hồ sơ minh chứng chính thức phục vụ công tác kiểm định chất lượng giáo dục đợt 37.', 'uploads/evidences/QuyDuyet_CTDT_2025.pdf', '2023-2024', '2026-08-31 21:14:26', 1, NULL, 'TC37', 'ND002', '2026-08-31 12:25:35'),
('MC38', 'Báo cáo tự đánh giá chất lượng chương trình đào tạo CNTT - Tập 3', 'Hồ sơ minh chứng chính thức phục vụ công tác kiểm định chất lượng giáo dục đợt 38.', 'uploads/evidences/QuyDuyet_CTDT_2025.pdf', '2024-2025', '2026-08-31 21:14:26', 1, NULL, 'TC04', 'ND003', '2026-08-31 12:25:35'),
('MC39', 'Danh sách công trình nghiên cứu khoa học và bài báo của giảng viên - Tập 3', 'Hồ sơ minh chứng chính thức phục vụ công tác kiểm định chất lượng giáo dục đợt 39.', 'uploads/evidences/KhaoSat_DoanhNghiep_2025.docx', '2025-2026', '2026-08-31 21:14:26', 1, NULL, 'TC57', 'ND001', '2026-08-31 12:25:35'),
('MC40', 'Quyết định thành lập Hội đồng kiểm định chất lượng giáo dục - Tập 3', 'Hồ sơ minh chứng chính thức phục vụ công tác kiểm định chất lượng giáo dục đợt 40.', 'uploads/evidences/KhaoSat_DoanhNghiep_2025.docx', '2024-2025', '2026-08-31 21:14:26', 0, NULL, 'TC57', 'ND002', '2026-08-31 12:25:35'),
('MC41', 'Báo cáo tổng kết công tác tuyển sinh và phân tích chất lượng đầu vào - Tập 3', 'Hồ sơ minh chứng chính thức phục vụ công tác kiểm định chất lượng giáo dục đợt 41.', 'uploads/evidences/MC_03_02_04.zip', '2023-2024', '2026-08-31 21:14:26', 1, NULL, 'TC55', 'ND003', '2026-08-31 12:25:35'),
('MC42', 'Sổ tay hướng dẫn thực tập tốt nghiệp và đồ án khóa luận - Tập 3', 'Hồ sơ minh chứng chính thức phục vụ công tác kiểm định chất lượng giáo dục đợt 42.', 'uploads/evidences/MC_01_01_01.pdf', '2023-2024', '2026-08-31 21:14:26', 1, NULL, 'TC24', 'ND001', '2026-08-31 12:25:35'),
('MC43', 'Biên bản nghiệm thu nâng cấp hệ thống phòng máy tính thực hành - Tập 3', 'Hồ sơ minh chứng chính thức phục vụ công tác kiểm định chất lượng giáo dục đợt 43.', 'uploads/evidences/MC_04_01_02.pdf', '2025-2026', '2026-08-31 21:14:26', 1, NULL, 'TC06', 'ND002', '2026-08-31 12:25:35'),
('MC44', 'Quyết định khen thưởng sinh viên có thành tích xuất sắc trong học tập - Tập 3', 'Hồ sơ minh chứng chính thức phục vụ công tác kiểm định chất lượng giáo dục đợt 44.', 'uploads/evidences/MC_05_03_05.xlsx', '2025-2026', '2026-08-31 21:14:26', 1, NULL, 'TC49', 'ND003', '2026-08-31 12:25:35'),
('MC45', 'Báo cáo tình hình việc làm của sinh viên sau 1 năm tốt nghiệp - Tập 3', 'Hồ sơ minh chứng chính thức phục vụ công tác kiểm định chất lượng giáo dục đợt 45.', 'uploads/evidences/QuyDuyet_CTDT_2025.pdf', '2023-2024', '2026-08-31 21:14:26', 1, NULL, 'TC17', 'ND001', '2026-08-31 12:25:35'),
('MC46', 'Quyết định ban hành mục tiêu và chuẩn đầu ra ngành Công nghệ thông tin - Tập 4', 'Hồ sơ minh chứng chính thức phục vụ công tác kiểm định chất lượng giáo dục đợt 46.', 'uploads/evidences/MC_02_01_03.docx', '2025-2026', '2026-08-31 21:14:26', 1, NULL, 'TC13', 'ND002', '2026-08-31 12:25:35'),
('MC47', 'Bản mô tả chương trình đào tạo ngành Công nghệ thông tin năm 2025 - Tập 4', 'Hồ sơ minh chứng chính thức phục vụ công tác kiểm định chất lượng giáo dục đợt 47.', 'uploads/evidences/MC_05_03_05.xlsx', '2023-2024', '2026-08-31 21:14:26', 1, NULL, 'TC14', 'ND003', '2026-08-31 12:25:35'),
('MC48', 'Đề cương chi tiết các học phần chuyên ngành Công nghệ phần mềm - Tập 4', 'Hồ sơ minh chứng chính thức phục vụ công tác kiểm định chất lượng giáo dục đợt 48.', 'uploads/evidences/QuyChe_DanhGia_HocPhan.pdf', '2024-2025', '2026-08-31 21:14:26', 1, NULL, 'TC43', 'ND001', '2026-08-31 12:25:35'),
('MC49', 'Kế hoạch đổi mới phương pháp dạy và học định hướng ứng dụng thực tiễn - Tập 4', 'Hồ sơ minh chứng chính thức phục vụ công tác kiểm định chất lượng giáo dục đợt 49.', 'uploads/evidences/MC_03_02_04.zip', '2023-2024', '2026-08-31 21:14:26', 1, NULL, 'TC53', 'ND002', '2026-08-31 12:25:35'),
('MC50', 'Quy chế đánh giá học phần và ma trận kiểm tra đánh giá CĐR - Tập 4', 'Hồ sơ minh chứng chính thức phục vụ công tác kiểm định chất lượng giáo dục đợt 50.', 'uploads/evidences/MC_03_02_04.zip', '2023-2024', '2026-08-31 21:14:26', 0, NULL, 'TC30', 'ND003', '2026-08-31 12:25:35'),
('MC51', 'Biên bản rà soát và cập nhật chương trình đào tạo định kỳ năm 2025 - Tập 4', 'Hồ sơ minh chứng chính thức phục vụ công tác kiểm định chất lượng giáo dục đợt 51.', 'uploads/evidences/MC_05_03_05.xlsx', '2024-2025', '2026-08-31 21:14:26', 1, NULL, 'TC45', 'ND001', '2026-08-31 12:25:35'),
('MC52', 'Phiếu khảo sát ý kiến doanh nghiệp về chất lượng sinh viên tốt nghiệp - Tập 4', 'Hồ sơ minh chứng chính thức phục vụ công tác kiểm định chất lượng giáo dục đợt 52.', 'uploads/evidences/BienBan_Hop_Rasoat_CDR.docx', '2024-2025', '2026-08-31 21:14:26', 1, NULL, 'TC22', 'ND002', '2026-08-31 12:25:35'),
('MC53', 'Báo cáo tự đánh giá chất lượng chương trình đào tạo CNTT - Tập 4', 'Hồ sơ minh chứng chính thức phục vụ công tác kiểm định chất lượng giáo dục đợt 53.', 'uploads/evidences/KhaoSat_DoanhNghiep_2025.docx', '2025-2026', '2026-08-31 21:14:26', 1, NULL, 'TC31', 'ND003', '2026-08-31 12:25:35'),
('MC54', 'Danh sách công trình nghiên cứu khoa học và bài báo của giảng viên - Tập 4', 'Hồ sơ minh chứng chính thức phục vụ công tác kiểm định chất lượng giáo dục đợt 54.', 'uploads/evidences/DanhSach_GiangVien_2025.xlsx', '2024-2025', '2026-08-31 21:14:26', 1, NULL, 'TC05', 'ND001', '2026-08-31 12:25:35'),
('MC55', 'Quyết định thành lập Hội đồng kiểm định chất lượng giáo dục - Tập 4', 'Hồ sơ minh chứng chính thức phục vụ công tác kiểm định chất lượng giáo dục đợt 55.', 'uploads/evidences/QuyChe_DanhGia_HocPhan.pdf', '2024-2025', '2026-08-31 21:14:26', 1, NULL, 'TC09', 'ND002', '2026-08-31 12:25:35'),
('MC56', 'Báo cáo tổng kết công tác tuyển sinh và phân tích chất lượng đầu vào - Tập 4', 'Hồ sơ minh chứng chính thức phục vụ công tác kiểm định chất lượng giáo dục đợt 56.', 'uploads/evidences/BaoCao_TuDanhGia_CNTT.pdf', '2025-2026', '2026-08-31 21:14:26', 1, NULL, 'TC44', 'ND003', '2026-08-31 12:25:35'),
('MC57', 'Sổ tay hướng dẫn thực tập tốt nghiệp và đồ án khóa luận - Tập 4', 'Hồ sơ minh chứng chính thức phục vụ công tác kiểm định chất lượng giáo dục đợt 57.', 'uploads/evidences/QuyChe_DanhGia_HocPhan.pdf', '2025-2026', '2026-08-31 21:14:26', 1, NULL, 'TC55', 'ND001', '2026-08-31 12:25:35'),
('MC58', 'Biên bản nghiệm thu nâng cấp hệ thống phòng máy tính thực hành - Tập 4', 'Hồ sơ minh chứng chính thức phục vụ công tác kiểm định chất lượng giáo dục đợt 58.', 'uploads/evidences/MC_05_03_05.xlsx', '2023-2024', '2026-08-31 21:14:26', 1, NULL, 'TC07', 'ND002', '2026-08-31 12:25:35'),
('MC59', 'Quyết định khen thưởng sinh viên có thành tích xuất sắc trong học tập - Tập 4', 'Hồ sơ minh chứng chính thức phục vụ công tác kiểm định chất lượng giáo dục đợt 59.', 'uploads/evidences/QuyChe_DanhGia_HocPhan.pdf', '2025-2026', '2026-08-31 21:14:26', 1, NULL, 'TC24', 'ND003', '2026-08-31 12:25:35'),
('MC60', 'Báo cáo tình hình việc làm của sinh viên sau 1 năm tốt nghiệp - Tập 4', 'Hồ sơ minh chứng chính thức phục vụ công tác kiểm định chất lượng giáo dục đợt 60.', 'uploads/evidences/KeHoach_CaiTien_ChatLuong.pdf', '2024-2025', '2026-08-31 21:14:26', 0, NULL, 'TC30', 'ND001', '2026-08-31 12:25:35'),
('MC61', 'Quyết định ban hành mục tiêu và chuẩn đầu ra ngành Công nghệ thông tin - Tập 5', 'Hồ sơ minh chứng chính thức phục vụ công tác kiểm định chất lượng giáo dục đợt 61.', 'uploads/evidences/QuyChe_DanhGia_HocPhan.pdf', '2025-2026', '2026-08-31 21:14:26', 1, NULL, 'TC23', 'ND002', '2026-08-31 12:25:35'),
('MC62', 'Bản mô tả chương trình đào tạo ngành Công nghệ thông tin năm 2025 - Tập 5', 'Hồ sơ minh chứng chính thức phục vụ công tác kiểm định chất lượng giáo dục đợt 62.', 'uploads/evidences/BienBan_Hop_Rasoat_CDR.docx', '2025-2026', '2026-08-31 21:14:26', 1, NULL, 'TC13', 'ND003', '2026-08-31 12:25:35'),
('MC63', 'Đề cương chi tiết các học phần chuyên ngành Công nghệ phần mềm - Tập 5', 'Hồ sơ minh chứng chính thức phục vụ công tác kiểm định chất lượng giáo dục đợt 63.', 'uploads/evidences/BienBan_Hop_Rasoat_CDR.docx', '2025-2026', '2026-08-31 21:14:26', 1, NULL, 'TC21', 'ND001', '2026-08-31 12:25:35'),
('MC64', 'Kế hoạch đổi mới phương pháp dạy và học định hướng ứng dụng thực tiễn - Tập 5', 'Hồ sơ minh chứng chính thức phục vụ công tác kiểm định chất lượng giáo dục đợt 64.', 'uploads/evidences/BienBan_Hop_Rasoat_CDR.docx', '2023-2024', '2026-08-31 21:14:26', 1, NULL, 'TC30', 'ND002', '2026-08-31 12:25:35'),
('MC65', 'Quy chế đánh giá học phần và ma trận kiểm tra đánh giá CĐR - Tập 5', 'Hồ sơ minh chứng chính thức phục vụ công tác kiểm định chất lượng giáo dục đợt 65.', 'uploads/evidences/DanhSach_GiangVien_2025.xlsx', '2023-2024', '2026-08-31 21:14:26', 1, NULL, 'TC59', 'ND003', '2026-08-31 12:25:35');

-- --------------------------------------------------------

--
-- Table structure for table `NguoiDung`
--

CREATE TABLE `NguoiDung` (
  `MaNguoiDung` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `HoTen` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `DonViCongTac` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Email` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `SoDienThoai` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `TenDangNhap` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `MatKhau` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `VaiTro` enum('admin','user') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'user',
  `TrangThai` tinyint(1) NOT NULL DEFAULT 1,
  `DuongDanAnhDaiDien` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `DangNhapCuoi` datetime DEFAULT NULL,
  `NgayTao` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `NguoiDung`
--

INSERT INTO `NguoiDung` (`MaNguoiDung`, `HoTen`, `DonViCongTac`, `Email`, `SoDienThoai`, `TenDangNhap`, `MatKhau`, `VaiTro`, `TrangThai`, `DuongDanAnhDaiDien`, `DangNhapCuoi`, `NgayTao`) VALUES
('ND001', 'Quản trị viên', 'Khoa Công nghệ thông tin', 'admin@fbu.edu.vn', '0912345678', 'admin', '$2y$10$epbVeUHJztDc/l95cLcWlu/.bkc9SsQ/IQqRVKbEXqLVUtjVn3u36', 'admin', 1, NULL, NULL, '2026-08-31 13:46:14'),
('ND002', 'Nguyễn Văn A', 'Phòng Đảm bảo chất lượng', 'user01@fbu.edu.vn', '0987654321', 'kiemdinhtt', '$2y$10$epbVeUHJztDc/l95cLcWlu/.bkc9SsQ/IQqRVKbEXqLVUtjVn3u36', 'user', 1, NULL, NULL, '2026-08-31 13:46:14'),
('ND003', 'Trần Thị B', 'Bộ môn Kỹ thuật phần mềm', 'user02@fbu.edu.vn', '0911223344', 'viewer01', '$2y$10$epbVeUHJztDc/l95cLcWlu/.bkc9SsQ/IQqRVKbEXqLVUtjVn3u36', 'user', 1, NULL, NULL, '2026-08-31 13:46:14');

-- --------------------------------------------------------

--
-- Table structure for table `remember_tokens`
--

CREATE TABLE `remember_tokens` (
  `id` bigint(20) NOT NULL,
  `MaNguoiDung` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ma_token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `het_han` datetime NOT NULL,
  `ngay_tao` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `remember_tokens`
--

INSERT INTO `remember_tokens` (`id`, `MaNguoiDung`, `ma_token`, `het_han`, `ngay_tao`) VALUES
(2, 'ND001', '038f20bd8b36e1eb5e68ddd0c944d321b0002759252946ef6b0f55477749f794', '2026-08-06 17:00:34', '2026-07-30 15:00:34'),
(3, 'ND001', '59864149bff596f0d9255b820b77f8a98919c02b3ae6b3c89dcf9daa7a859b3b', '2026-09-07 13:58:34', '2026-08-31 11:58:34'),
(4, 'ND002', '06526a07eed7ede0593cee2d52cf52068a05ea9ac5fdbfa2d650a5fa74c7176a', '2026-09-07 14:04:37', '2026-08-31 12:04:37');

-- --------------------------------------------------------

--
-- Table structure for table `TieuChi`
--

CREATE TABLE `TieuChi` (
  `MaTieuChi` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `TenTieuChi` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `NoiDung` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ThuTu` int(11) NOT NULL DEFAULT 0,
  `MaTieuChuan` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `TrangThai` tinyint(1) NOT NULL DEFAULT 1,
  `NgayTao` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `TieuChi`
--

INSERT INTO `TieuChi` (`MaTieuChi`, `TenTieuChi`, `NoiDung`, `ThuTu`, `MaTieuChuan`, `TrangThai`, `NgayTao`) VALUES
('TC01', 'Mục tiêu của CTĐT được xác định rõ ràng, phù hợp với sứ mạng và tầm nhìn (TC01)', 'Nội dung chi tiết tiêu chí đánh giá mức độ đạt được chuẩn đầu ra và minh chứng đi kèm.', 1, 'TC45', 1, '2026-08-31 12:25:35'),
('TC02', 'Chuẩn đầu ra phản ánh yêu cầu của các bên liên quan và được rà soát định kỳ (TC02)', 'Nội dung chi tiết tiêu chí đánh giá mức độ đạt được chuẩn đầu ra và minh chứng đi kèm.', 2, 'TC36', 1, '2026-08-31 12:25:35'),
('TC03', 'Bản mô tả CTĐT đầy đủ thông tin cần thiết, rõ ràng và công khai minh bạch (TC03)', 'Nội dung chi tiết tiêu chí đánh giá mức độ đạt được chuẩn đầu ra và minh chứng đi kèm.', 3, 'TC31', 1, '2026-08-31 12:25:35'),
('TC04', 'Cấu trúc chương trình học có tính logic, tích hợp và cập nhật xu hướng công nghệ (TC04)', 'Nội dung chi tiết tiêu chí đánh giá mức độ đạt được chuẩn đầu ra và minh chứng đi kèm.', 4, 'TC06', 1, '2026-08-31 12:25:35'),
('TC05', 'Đề cương chi tiết học phần được cập nhật hằng năm và được phê duyệt chính thức (TC05)', 'Nội dung chi tiết tiêu chí đánh giá mức độ đạt được chuẩn đầu ra và minh chứng đi kèm.', 5, 'TC22', 1, '2026-08-31 12:25:35'),
('TC06', 'Phương pháp dạy học thúc đẩy năng lực tự học, tư duy phản biện và sáng tạo (TC06)', 'Nội dung chi tiết tiêu chí đánh giá mức độ đạt được chuẩn đầu ra và minh chứng đi kèm.', 6, 'TC29', 1, '2026-08-31 12:25:35'),
('TC07', 'Phương pháp đánh giá đa dạng, công bằng, bám sát chuẩn đầu ra học phần (TC07)', 'Nội dung chi tiết tiêu chí đánh giá mức độ đạt được chuẩn đầu ra và minh chứng đi kèm.', 7, 'TC21', 1, '2026-08-31 12:25:35'),
('TC08', 'Quy trình tuyển sinh minh bạch, đúng quy định và đảm bảo chất lượng đầu vào (TC08)', 'Nội dung chi tiết tiêu chí đánh giá mức độ đạt được chuẩn đầu ra và minh chứng đi kèm.', 8, 'TC39', 1, '2026-08-31 12:25:35'),
('TC09', 'Hoạt động tư vấn học tập, hướng nghiệp và hỗ trợ tâm lý sinh viên hiệu quả (TC09)', 'Nội dung chi tiết tiêu chí đánh giá mức độ đạt được chuẩn đầu ra và minh chứng đi kèm.', 9, 'TC12', 1, '2026-08-31 12:25:35'),
('TC10', 'Trình độ chuyên môn và kỹ năng sư phạm của giảng viên đáp ứng tốt yêu cầu (TC10)', 'Nội dung chi tiết tiêu chí đánh giá mức độ đạt được chuẩn đầu ra và minh chứng đi kèm.', 10, 'TC24', 1, '2026-08-31 12:25:35'),
('TC11', 'Giáo trình, tài liệu tham khảo và học liệu số đầy đủ và cập nhật (TC11)', 'Nội dung chi tiết tiêu chí đánh giá mức độ đạt được chuẩn đầu ra và minh chứng đi kèm.', 11, 'TC27', 1, '2026-08-31 12:25:35'),
('TC12', 'Hệ thống phòng thí nghiệm, phòng máy tính đáp ứng tốt yêu cầu thực hành (TC12)', 'Nội dung chi tiết tiêu chí đánh giá mức độ đạt được chuẩn đầu ra và minh chứng đi kèm.', 12, 'TC04', 0, '2026-08-31 12:25:35'),
('TC13', 'Kết quả đánh giá sự hài lòng của nhà tuyển dụng đối với sinh viên tốt nghiệp (TC13)', 'Nội dung chi tiết tiêu chí đánh giá mức độ đạt được chuẩn đầu ra và minh chứng đi kèm.', 13, 'TC29', 1, '2026-08-31 12:25:35'),
('TC14', 'Quy trình bảo đảm chất lượng nội bộ được vận hành thường xuyên và hiệu quả (TC14)', 'Nội dung chi tiết tiêu chí đánh giá mức độ đạt được chuẩn đầu ra và minh chứng đi kèm.', 14, 'TC11', 1, '2026-08-31 12:25:35'),
('TC15', 'Mục tiêu của CTĐT được xác định rõ ràng, phù hợp với sứ mạng và tầm nhìn (TC15)', 'Nội dung chi tiết tiêu chí đánh giá mức độ đạt được chuẩn đầu ra và minh chứng đi kèm.', 15, 'TC17', 1, '2026-08-31 12:25:35'),
('TC16', 'Chuẩn đầu ra phản ánh yêu cầu của các bên liên quan và được rà soát định kỳ (TC16)', 'Nội dung chi tiết tiêu chí đánh giá mức độ đạt được chuẩn đầu ra và minh chứng đi kèm.', 16, 'TC35', 1, '2026-08-31 12:25:35'),
('TC17', 'Bản mô tả CTĐT đầy đủ thông tin cần thiết, rõ ràng và công khai minh bạch (TC17)', 'Nội dung chi tiết tiêu chí đánh giá mức độ đạt được chuẩn đầu ra và minh chứng đi kèm.', 17, 'TC06', 1, '2026-08-31 12:25:35'),
('TC18', 'Cấu trúc chương trình học có tính logic, tích hợp và cập nhật xu hướng công nghệ (TC18)', 'Nội dung chi tiết tiêu chí đánh giá mức độ đạt được chuẩn đầu ra và minh chứng đi kèm.', 18, 'TC43', 1, '2026-08-31 12:25:35'),
('TC19', 'Đề cương chi tiết học phần được cập nhật hằng năm và được phê duyệt chính thức (TC19)', 'Nội dung chi tiết tiêu chí đánh giá mức độ đạt được chuẩn đầu ra và minh chứng đi kèm.', 19, 'TC43', 1, '2026-08-31 12:25:35'),
('TC20', 'Phương pháp dạy học thúc đẩy năng lực tự học, tư duy phản biện và sáng tạo (TC20)', 'Nội dung chi tiết tiêu chí đánh giá mức độ đạt được chuẩn đầu ra và minh chứng đi kèm.', 20, 'TC10', 1, '2026-08-31 12:25:35'),
('TC21', 'Phương pháp đánh giá đa dạng, công bằng, bám sát chuẩn đầu ra học phần (TC21)', 'Nội dung chi tiết tiêu chí đánh giá mức độ đạt được chuẩn đầu ra và minh chứng đi kèm.', 21, 'TC33', 1, '2026-08-31 12:25:35'),
('TC22', 'Quy trình tuyển sinh minh bạch, đúng quy định và đảm bảo chất lượng đầu vào (TC22)', 'Nội dung chi tiết tiêu chí đánh giá mức độ đạt được chuẩn đầu ra và minh chứng đi kèm.', 22, 'TC47', 1, '2026-08-31 12:25:35'),
('TC23', 'Hoạt động tư vấn học tập, hướng nghiệp và hỗ trợ tâm lý sinh viên hiệu quả (TC23)', 'Nội dung chi tiết tiêu chí đánh giá mức độ đạt được chuẩn đầu ra và minh chứng đi kèm.', 23, 'TC31', 1, '2026-08-31 12:25:35'),
('TC24', 'Trình độ chuyên môn và kỹ năng sư phạm của giảng viên đáp ứng tốt yêu cầu (TC24)', 'Nội dung chi tiết tiêu chí đánh giá mức độ đạt được chuẩn đầu ra và minh chứng đi kèm.', 24, 'TC06', 0, '2026-08-31 12:25:35'),
('TC25', 'Giáo trình, tài liệu tham khảo và học liệu số đầy đủ và cập nhật (TC25)', 'Nội dung chi tiết tiêu chí đánh giá mức độ đạt được chuẩn đầu ra và minh chứng đi kèm.', 25, 'TC33', 1, '2026-08-31 12:25:35'),
('TC26', 'Hệ thống phòng thí nghiệm, phòng máy tính đáp ứng tốt yêu cầu thực hành (TC26)', 'Nội dung chi tiết tiêu chí đánh giá mức độ đạt được chuẩn đầu ra và minh chứng đi kèm.', 26, 'TC06', 1, '2026-08-31 12:25:35'),
('TC27', 'Kết quả đánh giá sự hài lòng của nhà tuyển dụng đối với sinh viên tốt nghiệp (TC27)', 'Nội dung chi tiết tiêu chí đánh giá mức độ đạt được chuẩn đầu ra và minh chứng đi kèm.', 27, 'TC22', 1, '2026-08-31 12:25:35'),
('TC28', 'Quy trình bảo đảm chất lượng nội bộ được vận hành thường xuyên và hiệu quả (TC28)', 'Nội dung chi tiết tiêu chí đánh giá mức độ đạt được chuẩn đầu ra và minh chứng đi kèm.', 28, 'TC42', 1, '2026-08-31 12:25:35'),
('TC29', 'Mục tiêu của CTĐT được xác định rõ ràng, phù hợp với sứ mạng và tầm nhìn (TC29)', 'Nội dung chi tiết tiêu chí đánh giá mức độ đạt được chuẩn đầu ra và minh chứng đi kèm.', 29, 'TC13', 1, '2026-08-31 12:25:35'),
('TC30', 'Chuẩn đầu ra phản ánh yêu cầu của các bên liên quan và được rà soát định kỳ (TC30)', 'Nội dung chi tiết tiêu chí đánh giá mức độ đạt được chuẩn đầu ra và minh chứng đi kèm.', 30, 'TC13', 1, '2026-08-31 12:25:35'),
('TC31', 'Bản mô tả CTĐT đầy đủ thông tin cần thiết, rõ ràng và công khai minh bạch (TC31)', 'Nội dung chi tiết tiêu chí đánh giá mức độ đạt được chuẩn đầu ra và minh chứng đi kèm.', 31, 'TC42', 1, '2026-08-31 12:25:35'),
('TC32', 'Cấu trúc chương trình học có tính logic, tích hợp và cập nhật xu hướng công nghệ (TC32)', 'Nội dung chi tiết tiêu chí đánh giá mức độ đạt được chuẩn đầu ra và minh chứng đi kèm.', 32, 'TC28', 1, '2026-08-31 12:25:35'),
('TC33', 'Đề cương chi tiết học phần được cập nhật hằng năm và được phê duyệt chính thức (TC33)', 'Nội dung chi tiết tiêu chí đánh giá mức độ đạt được chuẩn đầu ra và minh chứng đi kèm.', 33, 'TC02', 1, '2026-08-31 12:25:35'),
('TC34', 'Phương pháp dạy học thúc đẩy năng lực tự học, tư duy phản biện và sáng tạo (TC34)', 'Nội dung chi tiết tiêu chí đánh giá mức độ đạt được chuẩn đầu ra và minh chứng đi kèm.', 34, 'TC45', 1, '2026-08-31 12:25:35'),
('TC35', 'Phương pháp đánh giá đa dạng, công bằng, bám sát chuẩn đầu ra học phần (TC35)', 'Nội dung chi tiết tiêu chí đánh giá mức độ đạt được chuẩn đầu ra và minh chứng đi kèm.', 35, 'TC23', 1, '2026-08-31 12:25:35'),
('TC36', 'Quy trình tuyển sinh minh bạch, đúng quy định và đảm bảo chất lượng đầu vào (TC36)', 'Nội dung chi tiết tiêu chí đánh giá mức độ đạt được chuẩn đầu ra và minh chứng đi kèm.', 36, 'TC52', 0, '2026-08-31 12:25:35'),
('TC37', 'Hoạt động tư vấn học tập, hướng nghiệp và hỗ trợ tâm lý sinh viên hiệu quả (TC37)', 'Nội dung chi tiết tiêu chí đánh giá mức độ đạt được chuẩn đầu ra và minh chứng đi kèm.', 37, 'TC53', 1, '2026-08-31 12:25:35'),
('TC38', 'Trình độ chuyên môn và kỹ năng sư phạm của giảng viên đáp ứng tốt yêu cầu (TC38)', 'Nội dung chi tiết tiêu chí đánh giá mức độ đạt được chuẩn đầu ra và minh chứng đi kèm.', 38, 'TC38', 1, '2026-08-31 12:25:35'),
('TC39', 'Giáo trình, tài liệu tham khảo và học liệu số đầy đủ và cập nhật (TC39)', 'Nội dung chi tiết tiêu chí đánh giá mức độ đạt được chuẩn đầu ra và minh chứng đi kèm.', 39, 'TC34', 1, '2026-08-31 12:25:35'),
('TC40', 'Hệ thống phòng thí nghiệm, phòng máy tính đáp ứng tốt yêu cầu thực hành (TC40)', 'Nội dung chi tiết tiêu chí đánh giá mức độ đạt được chuẩn đầu ra và minh chứng đi kèm.', 40, 'TC19', 1, '2026-08-31 12:25:35'),
('TC41', 'Kết quả đánh giá sự hài lòng của nhà tuyển dụng đối với sinh viên tốt nghiệp (TC41)', 'Nội dung chi tiết tiêu chí đánh giá mức độ đạt được chuẩn đầu ra và minh chứng đi kèm.', 41, 'TC01', 1, '2026-08-31 12:25:35'),
('TC42', 'Quy trình bảo đảm chất lượng nội bộ được vận hành thường xuyên và hiệu quả (TC42)', 'Nội dung chi tiết tiêu chí đánh giá mức độ đạt được chuẩn đầu ra và minh chứng đi kèm.', 42, 'TC13', 1, '2026-08-31 12:25:35'),
('TC43', 'Mục tiêu của CTĐT được xác định rõ ràng, phù hợp với sứ mạng và tầm nhìn (TC43)', 'Nội dung chi tiết tiêu chí đánh giá mức độ đạt được chuẩn đầu ra và minh chứng đi kèm.', 43, 'TC55', 1, '2026-08-31 12:25:35'),
('TC44', 'Chuẩn đầu ra phản ánh yêu cầu của các bên liên quan và được rà soát định kỳ (TC44)', 'Nội dung chi tiết tiêu chí đánh giá mức độ đạt được chuẩn đầu ra và minh chứng đi kèm.', 44, 'TC53', 1, '2026-08-31 12:25:35'),
('TC45', 'Bản mô tả CTĐT đầy đủ thông tin cần thiết, rõ ràng và công khai minh bạch (TC45)', 'Nội dung chi tiết tiêu chí đánh giá mức độ đạt được chuẩn đầu ra và minh chứng đi kèm.', 45, 'TC24', 1, '2026-08-31 12:25:35'),
('TC46', 'Cấu trúc chương trình học có tính logic, tích hợp và cập nhật xu hướng công nghệ (TC46)', 'Nội dung chi tiết tiêu chí đánh giá mức độ đạt được chuẩn đầu ra và minh chứng đi kèm.', 46, 'TC38', 1, '2026-08-31 12:25:35'),
('TC47', 'Đề cương chi tiết học phần được cập nhật hằng năm và được phê duyệt chính thức (TC47)', 'Nội dung chi tiết tiêu chí đánh giá mức độ đạt được chuẩn đầu ra và minh chứng đi kèm.', 47, 'TC43', 1, '2026-08-31 12:25:35'),
('TC48', 'Phương pháp dạy học thúc đẩy năng lực tự học, tư duy phản biện và sáng tạo (TC48)', 'Nội dung chi tiết tiêu chí đánh giá mức độ đạt được chuẩn đầu ra và minh chứng đi kèm.', 48, 'TC08', 0, '2026-08-31 12:25:35'),
('TC49', 'Phương pháp đánh giá đa dạng, công bằng, bám sát chuẩn đầu ra học phần (TC49)', 'Nội dung chi tiết tiêu chí đánh giá mức độ đạt được chuẩn đầu ra và minh chứng đi kèm.', 49, 'TC21', 1, '2026-08-31 12:25:35'),
('TC50', 'Quy trình tuyển sinh minh bạch, đúng quy định và đảm bảo chất lượng đầu vào (TC50)', 'Nội dung chi tiết tiêu chí đánh giá mức độ đạt được chuẩn đầu ra và minh chứng đi kèm.', 50, 'TC36', 1, '2026-08-31 12:25:35'),
('TC51', 'Hoạt động tư vấn học tập, hướng nghiệp và hỗ trợ tâm lý sinh viên hiệu quả (TC51)', 'Nội dung chi tiết tiêu chí đánh giá mức độ đạt được chuẩn đầu ra và minh chứng đi kèm.', 51, 'TC25', 1, '2026-08-31 12:25:35'),
('TC52', 'Trình độ chuyên môn và kỹ năng sư phạm của giảng viên đáp ứng tốt yêu cầu (TC52)', 'Nội dung chi tiết tiêu chí đánh giá mức độ đạt được chuẩn đầu ra và minh chứng đi kèm.', 52, 'TC53', 1, '2026-08-31 12:25:35'),
('TC53', 'Giáo trình, tài liệu tham khảo và học liệu số đầy đủ và cập nhật (TC53)', 'Nội dung chi tiết tiêu chí đánh giá mức độ đạt được chuẩn đầu ra và minh chứng đi kèm.', 53, 'TC21', 1, '2026-08-31 12:25:35'),
('TC54', 'Hệ thống phòng thí nghiệm, phòng máy tính đáp ứng tốt yêu cầu thực hành (TC54)', 'Nội dung chi tiết tiêu chí đánh giá mức độ đạt được chuẩn đầu ra và minh chứng đi kèm.', 54, 'TC28', 1, '2026-08-31 12:25:35'),
('TC55', 'Kết quả đánh giá sự hài lòng của nhà tuyển dụng đối với sinh viên tốt nghiệp (TC55)', 'Nội dung chi tiết tiêu chí đánh giá mức độ đạt được chuẩn đầu ra và minh chứng đi kèm.', 55, 'TC29', 1, '2026-08-31 12:25:35'),
('TC56', 'Quy trình bảo đảm chất lượng nội bộ được vận hành thường xuyên và hiệu quả (TC56)', 'Nội dung chi tiết tiêu chí đánh giá mức độ đạt được chuẩn đầu ra và minh chứng đi kèm.', 56, 'TC22', 1, '2026-08-31 12:25:35'),
('TC57', 'Mục tiêu của CTĐT được xác định rõ ràng, phù hợp với sứ mạng và tầm nhìn (TC57)', 'Nội dung chi tiết tiêu chí đánh giá mức độ đạt được chuẩn đầu ra và minh chứng đi kèm.', 57, 'TC03', 1, '2026-08-31 12:25:35'),
('TC58', 'Chuẩn đầu ra phản ánh yêu cầu của các bên liên quan và được rà soát định kỳ (TC58)', 'Nội dung chi tiết tiêu chí đánh giá mức độ đạt được chuẩn đầu ra và minh chứng đi kèm.', 58, 'TC25', 1, '2026-08-31 12:25:35'),
('TC59', 'Bản mô tả CTĐT đầy đủ thông tin cần thiết, rõ ràng và công khai minh bạch (TC59)', 'Nội dung chi tiết tiêu chí đánh giá mức độ đạt được chuẩn đầu ra và minh chứng đi kèm.', 59, 'TC17', 1, '2026-08-31 12:25:35'),
('TC60', 'Cấu trúc chương trình học có tính logic, tích hợp và cập nhật xu hướng công nghệ (TC60)', 'Nội dung chi tiết tiêu chí đánh giá mức độ đạt được chuẩn đầu ra và minh chứng đi kèm.', 60, 'TC21', 0, '2026-08-31 12:25:35');

-- --------------------------------------------------------

--
-- Table structure for table `TieuChuan`
--

CREATE TABLE `TieuChuan` (
  `MaTieuChuan` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `TenTieuChuan` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `MoTa` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ThuTu` int(11) NOT NULL DEFAULT 0,
  `MaBoTieuChuan` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `TrangThai` tinyint(1) NOT NULL DEFAULT 1,
  `NgayTao` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `TieuChuan`
--

INSERT INTO `TieuChuan` (`MaTieuChuan`, `TenTieuChuan`, `MoTa`, `ThuTu`, `MaBoTieuChuan`, `TrangThai`, `NgayTao`) VALUES
('TC01', 'Mục tiêu và chuẩn đầu ra của chương trình đào tạo (Tiêu chuẩn 1)', 'Tiêu chuẩn đánh giá chi tiết yêu cầu thuộc bộ tiêu chuẩn kiểm định ngành CNTT.', 1, 'BTC52', 1, '2026-08-31 12:25:35'),
('TC02', 'Bản mô tả chương trình đào tạo và cấu trúc khóa học (Tiêu chuẩn 2)', 'Tiêu chuẩn đánh giá chi tiết yêu cầu thuộc bộ tiêu chuẩn kiểm định ngành CNTT.', 2, 'BTC24', 1, '2026-08-31 12:25:35'),
('TC03', 'Cấu trúc và nội dung chương trình dạy học (Tiêu chuẩn 3)', 'Tiêu chuẩn đánh giá chi tiết yêu cầu thuộc bộ tiêu chuẩn kiểm định ngành CNTT.', 3, 'BTC50', 1, '2026-08-31 12:25:35'),
('TC04', 'Phương pháp tiếp cận trong dạy và học (Tiêu chuẩn 4)', 'Tiêu chuẩn đánh giá chi tiết yêu cầu thuộc bộ tiêu chuẩn kiểm định ngành CNTT.', 4, 'BTC35', 1, '2026-08-31 12:25:35'),
('TC05', 'Đánh giá kết quả học tập của người học (Tiêu chuẩn 5)', 'Tiêu chuẩn đánh giá chi tiết yêu cầu thuộc bộ tiêu chuẩn kiểm định ngành CNTT.', 5, 'BTC21', 1, '2026-08-31 12:25:35'),
('TC06', 'Đội ngũ giảng viên và nghiên cứu viên (Tiêu chuẩn 6)', 'Tiêu chuẩn đánh giá chi tiết yêu cầu thuộc bộ tiêu chuẩn kiểm định ngành CNTT.', 6, 'BTC42', 1, '2026-08-31 12:25:35'),
('TC07', 'Đội ngũ nhân viên hành chính và hỗ trợ kỹ thuật (Tiêu chuẩn 7)', 'Tiêu chuẩn đánh giá chi tiết yêu cầu thuộc bộ tiêu chuẩn kiểm định ngành CNTT.', 7, 'BTC47', 1, '2026-08-31 12:25:35'),
('TC08', 'Người học và các dịch vụ hỗ trợ người học (Tiêu chuẩn 8)', 'Tiêu chuẩn đánh giá chi tiết yêu cầu thuộc bộ tiêu chuẩn kiểm định ngành CNTT.', 8, 'BTC51', 1, '2026-08-31 12:25:35'),
('TC09', 'Cơ sở vật chất, phòng máy tính và trang thiết bị (Tiêu chuẩn 9)', 'Tiêu chuẩn đánh giá chi tiết yêu cầu thuộc bộ tiêu chuẩn kiểm định ngành CNTT.', 9, 'BTC08', 1, '2026-08-31 12:25:35'),
('TC10', 'Nâng cao chất lượng liên tục và rà soát định kỳ (Tiêu chuẩn 10)', 'Tiêu chuẩn đánh giá chi tiết yêu cầu thuộc bộ tiêu chuẩn kiểm định ngành CNTT.', 10, 'BTC22', 1, '2026-08-31 12:25:35'),
('TC11', 'Kết quả đầu ra của người học và tỷ lệ có việc làm (Tiêu chuẩn 11)', 'Tiêu chuẩn đánh giá chi tiết yêu cầu thuộc bộ tiêu chuẩn kiểm định ngành CNTT.', 11, 'BTC22', 1, '2026-08-31 12:25:35'),
('TC12', 'Hoạt động nghiên cứu khoa học và chuyển giao công nghệ (Tiêu chuẩn 12)', 'Tiêu chuẩn đánh giá chi tiết yêu cầu thuộc bộ tiêu chuẩn kiểm định ngành CNTT.', 12, 'BTC33', 1, '2026-08-31 12:25:35'),
('TC13', 'Tương tác giữa nhà trường, doanh nghiệp và xã hội (Tiêu chuẩn 13)', 'Tiêu chuẩn đánh giá chi tiết yêu cầu thuộc bộ tiêu chuẩn kiểm định ngành CNTT.', 13, 'BTC11', 1, '2026-08-31 12:25:35'),
('TC14', 'Công tác quản lý tài chính và nguồn lực phát triển (Tiêu chuẩn 14)', 'Tiêu chuẩn đánh giá chi tiết yêu cầu thuộc bộ tiêu chuẩn kiểm định ngành CNTT.', 14, 'BTC12', 1, '2026-08-31 12:25:35'),
('TC15', 'Hệ thống đảm bảo chất lượng nội bộ (Tiêu chuẩn 15)', 'Tiêu chuẩn đánh giá chi tiết yêu cầu thuộc bộ tiêu chuẩn kiểm định ngành CNTT.', 15, 'BTC37', 0, '2026-08-31 12:25:35'),
('TC16', 'Mục tiêu và chuẩn đầu ra của chương trình đào tạo (Tiêu chuẩn 16)', 'Tiêu chuẩn đánh giá chi tiết yêu cầu thuộc bộ tiêu chuẩn kiểm định ngành CNTT.', 16, 'BTC11', 1, '2026-08-31 12:25:35'),
('TC17', 'Bản mô tả chương trình đào tạo và cấu trúc khóa học (Tiêu chuẩn 17)', 'Tiêu chuẩn đánh giá chi tiết yêu cầu thuộc bộ tiêu chuẩn kiểm định ngành CNTT.', 17, 'BTC15', 1, '2026-08-31 12:25:35'),
('TC18', 'Cấu trúc và nội dung chương trình dạy học (Tiêu chuẩn 18)', 'Tiêu chuẩn đánh giá chi tiết yêu cầu thuộc bộ tiêu chuẩn kiểm định ngành CNTT.', 18, 'BTC07', 1, '2026-08-31 12:25:35'),
('TC19', 'Phương pháp tiếp cận trong dạy và học (Tiêu chuẩn 19)', 'Tiêu chuẩn đánh giá chi tiết yêu cầu thuộc bộ tiêu chuẩn kiểm định ngành CNTT.', 19, 'BTC14', 1, '2026-08-31 12:25:35'),
('TC20', 'Đánh giá kết quả học tập của người học (Tiêu chuẩn 20)', 'Tiêu chuẩn đánh giá chi tiết yêu cầu thuộc bộ tiêu chuẩn kiểm định ngành CNTT.', 20, 'BTC14', 1, '2026-08-31 12:25:35'),
('TC21', 'Đội ngũ giảng viên và nghiên cứu viên (Tiêu chuẩn 21)', 'Tiêu chuẩn đánh giá chi tiết yêu cầu thuộc bộ tiêu chuẩn kiểm định ngành CNTT.', 21, 'BTC22', 1, '2026-08-31 12:25:35'),
('TC22', 'Đội ngũ nhân viên hành chính và hỗ trợ kỹ thuật (Tiêu chuẩn 22)', 'Tiêu chuẩn đánh giá chi tiết yêu cầu thuộc bộ tiêu chuẩn kiểm định ngành CNTT.', 22, 'BTC11', 1, '2026-08-31 12:25:35'),
('TC23', 'Người học và các dịch vụ hỗ trợ người học (Tiêu chuẩn 23)', 'Tiêu chuẩn đánh giá chi tiết yêu cầu thuộc bộ tiêu chuẩn kiểm định ngành CNTT.', 23, 'BTC40', 1, '2026-08-31 12:25:35'),
('TC24', 'Cơ sở vật chất, phòng máy tính và trang thiết bị (Tiêu chuẩn 24)', 'Tiêu chuẩn đánh giá chi tiết yêu cầu thuộc bộ tiêu chuẩn kiểm định ngành CNTT.', 24, 'BTC53', 1, '2026-08-31 12:25:35'),
('TC25', 'Nâng cao chất lượng liên tục và rà soát định kỳ (Tiêu chuẩn 25)', 'Tiêu chuẩn đánh giá chi tiết yêu cầu thuộc bộ tiêu chuẩn kiểm định ngành CNTT.', 25, 'BTC39', 1, '2026-08-31 12:25:35'),
('TC26', 'Kết quả đầu ra của người học và tỷ lệ có việc làm (Tiêu chuẩn 26)', 'Tiêu chuẩn đánh giá chi tiết yêu cầu thuộc bộ tiêu chuẩn kiểm định ngành CNTT.', 26, 'BTC35', 1, '2026-08-31 12:25:35'),
('TC27', 'Hoạt động nghiên cứu khoa học và chuyển giao công nghệ (Tiêu chuẩn 27)', 'Tiêu chuẩn đánh giá chi tiết yêu cầu thuộc bộ tiêu chuẩn kiểm định ngành CNTT.', 27, 'BTC34', 1, '2026-08-31 12:25:35'),
('TC28', 'Tương tác giữa nhà trường, doanh nghiệp và xã hội (Tiêu chuẩn 28)', 'Tiêu chuẩn đánh giá chi tiết yêu cầu thuộc bộ tiêu chuẩn kiểm định ngành CNTT.', 28, 'BTC47', 1, '2026-08-31 12:25:35'),
('TC29', 'Công tác quản lý tài chính và nguồn lực phát triển (Tiêu chuẩn 29)', 'Tiêu chuẩn đánh giá chi tiết yêu cầu thuộc bộ tiêu chuẩn kiểm định ngành CNTT.', 29, 'BTC31', 1, '2026-08-31 12:25:35'),
('TC30', 'Hệ thống đảm bảo chất lượng nội bộ (Tiêu chuẩn 30)', 'Tiêu chuẩn đánh giá chi tiết yêu cầu thuộc bộ tiêu chuẩn kiểm định ngành CNTT.', 30, 'BTC02', 0, '2026-08-31 12:25:35'),
('TC31', 'Mục tiêu và chuẩn đầu ra của chương trình đào tạo (Tiêu chuẩn 31)', 'Tiêu chuẩn đánh giá chi tiết yêu cầu thuộc bộ tiêu chuẩn kiểm định ngành CNTT.', 31, 'BTC07', 1, '2026-08-31 12:25:35'),
('TC32', 'Bản mô tả chương trình đào tạo và cấu trúc khóa học (Tiêu chuẩn 32)', 'Tiêu chuẩn đánh giá chi tiết yêu cầu thuộc bộ tiêu chuẩn kiểm định ngành CNTT.', 32, 'BTC47', 1, '2026-08-31 12:25:35'),
('TC33', 'Cấu trúc và nội dung chương trình dạy học (Tiêu chuẩn 33)', 'Tiêu chuẩn đánh giá chi tiết yêu cầu thuộc bộ tiêu chuẩn kiểm định ngành CNTT.', 33, 'BTC24', 1, '2026-08-31 12:25:35'),
('TC34', 'Phương pháp tiếp cận trong dạy và học (Tiêu chuẩn 34)', 'Tiêu chuẩn đánh giá chi tiết yêu cầu thuộc bộ tiêu chuẩn kiểm định ngành CNTT.', 34, 'BTC54', 1, '2026-08-31 12:25:35'),
('TC35', 'Đánh giá kết quả học tập của người học (Tiêu chuẩn 35)', 'Tiêu chuẩn đánh giá chi tiết yêu cầu thuộc bộ tiêu chuẩn kiểm định ngành CNTT.', 35, 'BTC23', 1, '2026-08-31 12:25:35'),
('TC36', 'Đội ngũ giảng viên và nghiên cứu viên (Tiêu chuẩn 36)', 'Tiêu chuẩn đánh giá chi tiết yêu cầu thuộc bộ tiêu chuẩn kiểm định ngành CNTT.', 36, 'BTC38', 1, '2026-08-31 12:25:35'),
('TC37', 'Đội ngũ nhân viên hành chính và hỗ trợ kỹ thuật (Tiêu chuẩn 37)', 'Tiêu chuẩn đánh giá chi tiết yêu cầu thuộc bộ tiêu chuẩn kiểm định ngành CNTT.', 37, 'BTC42', 1, '2026-08-31 12:25:35'),
('TC38', 'Người học và các dịch vụ hỗ trợ người học (Tiêu chuẩn 38)', 'Tiêu chuẩn đánh giá chi tiết yêu cầu thuộc bộ tiêu chuẩn kiểm định ngành CNTT.', 38, 'BTC06', 1, '2026-08-31 12:25:35'),
('TC39', 'Cơ sở vật chất, phòng máy tính và trang thiết bị (Tiêu chuẩn 39)', 'Tiêu chuẩn đánh giá chi tiết yêu cầu thuộc bộ tiêu chuẩn kiểm định ngành CNTT.', 39, 'BTC23', 1, '2026-08-31 12:25:35'),
('TC40', 'Nâng cao chất lượng liên tục và rà soát định kỳ (Tiêu chuẩn 40)', 'Tiêu chuẩn đánh giá chi tiết yêu cầu thuộc bộ tiêu chuẩn kiểm định ngành CNTT.', 40, 'BTC14', 1, '2026-08-31 12:25:35'),
('TC41', 'Kết quả đầu ra của người học và tỷ lệ có việc làm (Tiêu chuẩn 41)', 'Tiêu chuẩn đánh giá chi tiết yêu cầu thuộc bộ tiêu chuẩn kiểm định ngành CNTT.', 41, 'BTC54', 1, '2026-08-31 12:25:35'),
('TC42', 'Hoạt động nghiên cứu khoa học và chuyển giao công nghệ (Tiêu chuẩn 42)', 'Tiêu chuẩn đánh giá chi tiết yêu cầu thuộc bộ tiêu chuẩn kiểm định ngành CNTT.', 42, 'BTC52', 1, '2026-08-31 12:25:35'),
('TC43', 'Tương tác giữa nhà trường, doanh nghiệp và xã hội (Tiêu chuẩn 43)', 'Tiêu chuẩn đánh giá chi tiết yêu cầu thuộc bộ tiêu chuẩn kiểm định ngành CNTT.', 43, 'BTC43', 1, '2026-08-31 12:25:35'),
('TC44', 'Công tác quản lý tài chính và nguồn lực phát triển (Tiêu chuẩn 44)', 'Tiêu chuẩn đánh giá chi tiết yêu cầu thuộc bộ tiêu chuẩn kiểm định ngành CNTT.', 44, 'BTC02', 1, '2026-08-31 12:25:35'),
('TC45', 'Hệ thống đảm bảo chất lượng nội bộ (Tiêu chuẩn 45)', 'Tiêu chuẩn đánh giá chi tiết yêu cầu thuộc bộ tiêu chuẩn kiểm định ngành CNTT.', 45, 'BTC17', 0, '2026-08-31 12:25:35'),
('TC46', 'Mục tiêu và chuẩn đầu ra của chương trình đào tạo (Tiêu chuẩn 46)', 'Tiêu chuẩn đánh giá chi tiết yêu cầu thuộc bộ tiêu chuẩn kiểm định ngành CNTT.', 46, 'BTC21', 1, '2026-08-31 12:25:35'),
('TC47', 'Bản mô tả chương trình đào tạo và cấu trúc khóa học (Tiêu chuẩn 47)', 'Tiêu chuẩn đánh giá chi tiết yêu cầu thuộc bộ tiêu chuẩn kiểm định ngành CNTT.', 47, 'BTC17', 1, '2026-08-31 12:25:35'),
('TC48', 'Cấu trúc và nội dung chương trình dạy học (Tiêu chuẩn 48)', 'Tiêu chuẩn đánh giá chi tiết yêu cầu thuộc bộ tiêu chuẩn kiểm định ngành CNTT.', 48, 'BTC38', 1, '2026-08-31 12:25:35'),
('TC49', 'Phương pháp tiếp cận trong dạy và học (Tiêu chuẩn 49)', 'Tiêu chuẩn đánh giá chi tiết yêu cầu thuộc bộ tiêu chuẩn kiểm định ngành CNTT.', 49, 'BTC05', 1, '2026-08-31 12:25:35'),
('TC50', 'Đánh giá kết quả học tập của người học (Tiêu chuẩn 50)', 'Tiêu chuẩn đánh giá chi tiết yêu cầu thuộc bộ tiêu chuẩn kiểm định ngành CNTT.', 50, 'BTC11', 1, '2026-08-31 12:25:35'),
('TC51', 'Đội ngũ giảng viên và nghiên cứu viên (Tiêu chuẩn 51)', 'Tiêu chuẩn đánh giá chi tiết yêu cầu thuộc bộ tiêu chuẩn kiểm định ngành CNTT.', 51, 'BTC05', 1, '2026-08-31 12:25:35'),
('TC52', 'Đội ngũ nhân viên hành chính và hỗ trợ kỹ thuật (Tiêu chuẩn 52)', 'Tiêu chuẩn đánh giá chi tiết yêu cầu thuộc bộ tiêu chuẩn kiểm định ngành CNTT.', 52, 'BTC09', 1, '2026-08-31 12:25:35'),
('TC53', 'Người học và các dịch vụ hỗ trợ người học (Tiêu chuẩn 53)', 'Tiêu chuẩn đánh giá chi tiết yêu cầu thuộc bộ tiêu chuẩn kiểm định ngành CNTT.', 53, 'BTC18', 1, '2026-08-31 12:25:35'),
('TC54', 'Cơ sở vật chất, phòng máy tính và trang thiết bị (Tiêu chuẩn 54)', 'Tiêu chuẩn đánh giá chi tiết yêu cầu thuộc bộ tiêu chuẩn kiểm định ngành CNTT.', 54, 'BTC13', 1, '2026-08-31 12:25:35'),
('TC55', 'Nâng cao chất lượng liên tục và rà soát định kỳ (Tiêu chuẩn 55)', 'Tiêu chuẩn đánh giá chi tiết yêu cầu thuộc bộ tiêu chuẩn kiểm định ngành CNTT.', 55, 'BTC55', 1, '2026-08-31 12:25:35');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_audit_logs_nguoi_dung` (`MaNguoiDung`);

--
-- Indexes for table `BoTieuChuan`
--
ALTER TABLE `BoTieuChuan`
  ADD PRIMARY KEY (`MaBoTieuChuan`);

--
-- Indexes for table `download_logs`
--
ALTER TABLE `download_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_download_logs_nguoi_dung` (`MaNguoiDung`),
  ADD KEY `fk_download_logs_minh_chung` (`MaMinhChung`);

--
-- Indexes for table `loaiminhchung`
--
ALTER TABLE `loaiminhchung`
  ADD PRIMARY KEY (`MaLoai`);

--
-- Indexes for table `MinhChung`
--
ALTER TABLE `MinhChung`
  ADD PRIMARY KEY (`MaMinhChung`),
  ADD KEY `fk_minh_chung_loai` (`MaLoai`),
  ADD KEY `fk_minh_chung_tieu_chi` (`MaTieuChi`),
  ADD KEY `fk_minh_chung_nguoi_dung` (`MaNguoiDung`);

--
-- Indexes for table `NguoiDung`
--
ALTER TABLE `NguoiDung`
  ADD PRIMARY KEY (`MaNguoiDung`),
  ADD UNIQUE KEY `Email` (`Email`),
  ADD UNIQUE KEY `TenDangNhap` (`TenDangNhap`);

--
-- Indexes for table `remember_tokens`
--
ALTER TABLE `remember_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ma_token` (`ma_token`),
  ADD KEY `fk_remember_tokens_nguoi_dung` (`MaNguoiDung`);

--
-- Indexes for table `TieuChi`
--
ALTER TABLE `TieuChi`
  ADD PRIMARY KEY (`MaTieuChi`),
  ADD KEY `fk_tieu_chi_chuan` (`MaTieuChuan`);

--
-- Indexes for table `TieuChuan`
--
ALTER TABLE `TieuChuan`
  ADD PRIMARY KEY (`MaTieuChuan`),
  ADD KEY `fk_tieu_chuan_bo` (`MaBoTieuChuan`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `download_logs`
--
ALTER TABLE `download_logs`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `loaiminhchung`
--
ALTER TABLE `loaiminhchung`
  MODIFY `MaLoai` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `remember_tokens`
--
ALTER TABLE `remember_tokens`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `fk_audit_logs_nguoi_dung` FOREIGN KEY (`MaNguoiDung`) REFERENCES `nguoidung` (`MaNguoiDung`) ON DELETE SET NULL;

--
-- Constraints for table `download_logs`
--
ALTER TABLE `download_logs`
  ADD CONSTRAINT `fk_download_logs_minh_chung` FOREIGN KEY (`MaMinhChung`) REFERENCES `minhchung` (`MaMinhChung`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_download_logs_nguoi_dung` FOREIGN KEY (`MaNguoiDung`) REFERENCES `nguoidung` (`MaNguoiDung`) ON DELETE SET NULL;

--
-- Constraints for table `MinhChung`
--
ALTER TABLE `MinhChung`
  ADD CONSTRAINT `fk_minh_chung_loai` FOREIGN KEY (`MaLoai`) REFERENCES `loaiminhchung` (`MaLoai`) ON DELETE SET NULL;

--
-- Constraints for table `TieuChi`
--
ALTER TABLE `TieuChi`
  ADD CONSTRAINT `fk_tieu_chi_chuan` FOREIGN KEY (`MaTieuChuan`) REFERENCES `tieuchuan` (`MaTieuChuan`) ON DELETE CASCADE;

--
-- Constraints for table `TieuChuan`
--
ALTER TABLE `TieuChuan`
  ADD CONSTRAINT `fk_tieu_chuan_bo` FOREIGN KEY (`MaBoTieuChuan`) REFERENCES `botieuchuan` (`MaBoTieuChuan`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
