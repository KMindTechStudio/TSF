-- phpMyAdmin SQL Dump
-- version 5.1.3
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 22, 2026 at 06:09 AM
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
  `id_nguoi_dung` int(11) DEFAULT NULL,
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

INSERT INTO `audit_logs` (`id`, `id_nguoi_dung`, `hanh_dong`, `phan_he`, `ten_ban_ghi`, `id_ban_ghi`, `gia_tri_cu`, `gia_tri_moi`, `dia_chi_ip`, `ngay_tao`) VALUES
(1, 1, 'them_moi', 'minh_chung', NULL, 1, NULL, '{\"code\": \"MC.01.01.01\"}', '127.0.0.1', '2026-07-17 03:40:15'),
(2, 2, 'ra_soat', 'tieu_chi', NULL, 5, NULL, '{\"status\": \"missing\"}', '127.0.0.1', '2026-07-17 03:40:15'),
(3, 1, 'them_moi', 'nguoi_dung', NULL, 3, NULL, '{\"username\": \"viewer01\"}', '127.0.0.1', '2026-07-17 03:40:15'),
(8, 1, 'cap_nhat_trang_thai', 'minh_chung', NULL, 1, NULL, '{\"approval_status\": \"need_update\"}', '::1', '2026-07-17 09:46:38'),
(9, 1, 'cap_nhat_trang_thai', 'minh_chung', NULL, 1, NULL, '{\"approval_status\": \"approved\"}', '::1', '2026-07-17 09:46:38'),
(10, 1, 'cap_nhat_trang_thai', 'minh_chung', NULL, 1, NULL, '{\"approval_status\": \"reviewing\"}', '::1', '2026-07-17 13:09:05'),
(11, 1, 'cap_nhat_trang_thai', 'minh_chung', NULL, 1, NULL, '{\"approval_status\": \"approved\"}', '::1', '2026-07-17 13:09:07');

-- --------------------------------------------------------

--
-- Table structure for table `bo_tieu_chuan`
--

CREATE TABLE `bo_tieu_chuan` (
  `id` int(11) NOT NULL,
  `id_chuong_trinh_dao_tao` int(11) NOT NULL,
  `ten_bo_tieu_chuan` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nam_ban_hanh` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `don_vi_ban_hanh` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mo_ta` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `trang_thai` enum('active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `ngay_tao` timestamp NOT NULL DEFAULT current_timestamp(),
  `ngay_cap_nhat` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `bo_tieu_chuan`
--

INSERT INTO `bo_tieu_chuan` (`id`, `id_chuong_trinh_dao_tao`, `ten_bo_tieu_chuan`, `nam_ban_hanh`, `don_vi_ban_hanh`, `mo_ta`, `trang_thai`, `ngay_tao`, `ngay_cap_nhat`) VALUES
(1, 1, 'Bộ tiêu chuẩn đánh giá chất lượng chương trình đào tạo', '2025', 'Bộ Giáo dục và Đào tạo', 'Bộ tiêu chuẩn dùng cho cơ sở dữ liệu minh chứng phục vụ kiểm định CTĐT', 'active', '2026-07-17 03:40:15', '2026-07-22 00:48:23');

-- --------------------------------------------------------

--
-- Table structure for table `chuong_trinh_dao_tao`
--

CREATE TABLE `chuong_trinh_dao_tao` (
  `id` int(11) NOT NULL,
  `ma_chuong_trinh` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ten_chuong_trinh` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `trinh_do_dao_tao` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `khoa_don_vi` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `chu_ky_kiem_dinh` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mo_ta` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `trang_thai` enum('active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `ngay_tao` timestamp NOT NULL DEFAULT current_timestamp(),
  `ngay_cap_nhat` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `chuong_trinh_dao_tao`
--

INSERT INTO `chuong_trinh_dao_tao` (`id`, `ma_chuong_trinh`, `ten_chuong_trinh`, `trinh_do_dao_tao`, `khoa_don_vi`, `chu_ky_kiem_dinh`, `mo_ta`, `trang_thai`, `ngay_tao`, `ngay_cap_nhat`) VALUES
(1, '7480201', 'Công nghệ thông tin', 'Đại học chính quy', 'Khoa Cong nghe thong tin', 'Chu kỳ kiểm định 2026-2031', 'Chuong trinh dao tao nganh Cong nghe thong tin cua Truong Dai hoc Tai chinh - Ngan hang Ha Noi', 'active', '2026-07-17 03:40:15', '2026-07-22 00:48:23');

-- --------------------------------------------------------

--
-- Table structure for table `don_vi`
--

CREATE TABLE `don_vi` (
  `id` int(11) NOT NULL,
  `ma_don_vi` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ten_don_vi` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `trang_thai` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `so_dien_thoai` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ngay_tao` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `don_vi`
--

INSERT INTO `don_vi` (`id`, `ma_don_vi`, `ten_don_vi`, `trang_thai`, `so_dien_thoai`, `email`, `ngay_tao`) VALUES
(1, 'KHOA_CNTT', 'Khoa Công nghệ thông tin', 'active', NULL, 'cntt@fbu.edu.vn', '2026-07-17 03:40:15'),
(2, 'PDT', 'Phòng Đào tạo', 'inactive', NULL, 'daotao@fbu.edu.vn', '2026-07-17 03:40:15'),
(3, 'PDBCL', 'Phòng Đảm bảo chất lượng', 'active', NULL, 'dbcl@fbu.edu.vn', '2026-07-17 03:40:15'),
(4, 'PKT', 'Phòng Khảo thí', 'inactive', NULL, 'khaothi@fbu.edu.vn', '2026-07-17 03:40:15'),
(5, 'BM_PM', 'Bộ môn Phần mềm', 'active', NULL, 'bomonpm@fbu.edu.vn', '2026-07-17 03:40:15');

-- --------------------------------------------------------

--
-- Table structure for table `download_logs`
--

CREATE TABLE `download_logs` (
  `id` bigint(20) NOT NULL,
  `id_nguoi_dung` int(11) DEFAULT NULL,
  `id_file_minh_chung` int(11) NOT NULL,
  `ngay_tai` timestamp NOT NULL DEFAULT current_timestamp(),
  `dia_chi_ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `download_logs`
--

INSERT INTO `download_logs` (`id`, `id_nguoi_dung`, `id_file_minh_chung`, `ngay_tai`, `dia_chi_ip`) VALUES
(1, 1, 1, '2026-07-17 03:40:15', '127.0.0.1'),
(2, 2, 2, '2026-07-17 03:40:15', '127.0.0.1'),
(3, 1, 5, '2026-07-17 03:40:15', '127.0.0.1'),
(4, NULL, 1, '2026-07-17 05:08:23', '::1'),
(5, 2, 5, '2026-07-17 09:24:01', '::1'),
(6, 2, 5, '2026-07-17 09:33:16', '::1');

-- --------------------------------------------------------

--
-- Table structure for table `file_minh_chung`
--

CREATE TABLE `file_minh_chung` (
  `id` int(11) NOT NULL,
  `id_minh_chung` int(11) NOT NULL,
  `ten_goc` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ten_luu_tru` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `duong_dan` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `loai_file` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `kich_thuoc` bigint(20) NOT NULL DEFAULT 0,
  `so_phien_ban` int(11) NOT NULL DEFAULT 1,
  `nguoi_tai_len` int(11) DEFAULT NULL,
  `ngay_tai_len` timestamp NOT NULL DEFAULT current_timestamp(),
  `version_no` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `file_minh_chung`
--

INSERT INTO `file_minh_chung` (`id`, `id_minh_chung`, `ten_goc`, `ten_luu_tru`, `duong_dan`, `loai_file`, `kich_thuoc`, `so_phien_ban`, `nguoi_tai_len`, `ngay_tai_len`, `version_no`) VALUES
(1, 1, 'quyet-dinh-cdr-cntt.pdf', 'MC_01_01_01.pdf', 'uploads/evidences/MC_01_01_01.pdf', 'PDF', 1250000, 1, 1, '2026-07-17 03:40:15', 1),
(2, 2, 'ban-mo-ta-ctdt-cntt.docx', 'MC_02_01_03.docx', 'uploads/evidences/MC_02_01_03.docx', 'DOCX', 840000, 1, 2, '2026-07-17 03:40:15', 1),
(3, 3, 'de-cuong-hoc-phan.zip', 'MC_03_02_04.zip', 'uploads/evidences/MC_03_02_04.zip', 'ZIP', 6400000, 1, 1, '2026-07-17 03:40:15', 1),
(4, 4, 'ke-hoach-doi-moi-day-hoc.pdf', 'MC_04_01_02.pdf', 'uploads/evidences/MC_04_01_02.pdf', 'PDF', 930000, 1, 1, '2026-07-17 03:40:15', 1),
(5, 5, 'quy-che-danh-gia.xlsx', 'MC_05_03_05.xlsx', 'uploads/evidences/MC_05_03_05.xlsx', 'XLSX', 420000, 1, 2, '2026-07-17 03:40:15', 1),
(10, 10, 'mc_01_01_02.pdf', 'MC_01_01_02.pdf', 'uploads/evidences/MC_01_01_02.pdf', 'PDF', 720000, 1, 1, '2026-07-17 16:20:07', 1),
(11, 11, 'mc_01_02_01.xlsx', 'MC_01_02_01.xlsx', 'uploads/evidences/MC_01_02_01.xlsx', 'XLSX', 680000, 1, 1, '2026-07-17 16:20:07', 1),
(12, 12, 'mc_01_02_02.docx', 'MC_01_02_02.docx', 'uploads/evidences/MC_01_02_02.docx', 'DOCX', 540000, 1, 1, '2026-07-17 16:20:07', 1),
(13, 13, 'mc_02_01_04.pdf', 'MC_02_01_04.pdf', 'uploads/evidences/MC_02_01_04.pdf', 'PDF', 850000, 1, 1, '2026-07-17 16:20:07', 1),
(14, 14, 'mc_02_01_05.docx', 'MC_02_01_05.docx', 'uploads/evidences/MC_02_01_05.docx', 'DOCX', 760000, 1, 1, '2026-07-17 16:20:07', 1),
(15, 15, 'mc_02_01_06.xlsx', 'MC_02_01_06.xlsx', 'uploads/evidences/MC_02_01_06.xlsx', 'XLSX', 620000, 1, 1, '2026-07-17 16:20:07', 1),
(16, 16, 'mc_03_02_05.zip', 'MC_03_02_05.zip', 'uploads/evidences/MC_03_02_05.zip', 'ZIP', 2400000, 1, 1, '2026-07-17 16:20:07', 1),
(17, 17, 'mc_03_02_06.pdf', 'MC_03_02_06.pdf', 'uploads/evidences/MC_03_02_06.pdf', 'PDF', 930000, 1, 1, '2026-07-17 16:20:07', 1),
(18, 18, 'mc_03_02_07.xlsx', 'MC_03_02_07.xlsx', 'uploads/evidences/MC_03_02_07.xlsx', 'XLSX', 570000, 1, 1, '2026-07-17 16:20:07', 1),
(19, 19, 'mc_04_01_03.pdf', 'MC_04_01_03.pdf', 'uploads/evidences/MC_04_01_03.pdf', 'PDF', 810000, 1, 1, '2026-07-17 16:20:07', 1),
(20, 20, 'mc_04_01_04.docx', 'MC_04_01_04.docx', 'uploads/evidences/MC_04_01_04.docx', 'DOCX', 690000, 1, 1, '2026-07-17 16:20:07', 1),
(21, 21, 'mc_04_01_05.xlsx', 'MC_04_01_05.xlsx', 'uploads/evidences/MC_04_01_05.xlsx', 'XLSX', 480000, 1, 1, '2026-07-17 16:20:07', 1),
(22, 22, 'mc_05_03_06.pdf', 'MC_05_03_06.pdf', 'uploads/evidences/MC_05_03_06.pdf', 'PDF', 740000, 1, 1, '2026-07-17 16:20:07', 1),
(23, 23, 'mc_05_03_07.xlsx', 'MC_05_03_07.xlsx', 'uploads/evidences/MC_05_03_07.xlsx', 'XLSX', 910000, 1, 1, '2026-07-17 16:20:07', 1),
(24, 24, 'mc_05_03_08.docx', 'MC_05_03_08.docx', 'uploads/evidences/MC_05_03_08.docx', 'DOCX', 610000, 1, 1, '2026-07-17 16:20:07', 1),
(25, 25, 'mc_06_01_01.pdf', 'MC_06_01_01.pdf', 'uploads/evidences/MC_06_01_01.pdf', 'PDF', 520000, 1, 1, '2026-07-17 16:20:07', 1),
(26, 26, 'mc_06_01_02.xlsx', 'MC_06_01_02.xlsx', 'uploads/evidences/MC_06_01_02.xlsx', 'XLSX', 450000, 1, 1, '2026-07-17 16:20:07', 1),
(28, 28, 'mc_07_01_01.docx', 'MC_07_01_01.docx', 'uploads/evidences/MC_07_01_01.docx', 'DOCX', 1200000, 1, 1, '2026-07-17 16:20:07', 1),
(29, 29, 'mc_07_01_02.zip', 'MC_07_01_02.zip', 'uploads/evidences/MC_07_01_02.zip', 'ZIP', 3100000, 1, 1, '2026-07-17 16:20:07', 1);

-- --------------------------------------------------------

--
-- Table structure for table `minh_chung`
--

CREATE TABLE `minh_chung` (
  `id` int(11) NOT NULL,
  `ma_minh_chung` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tieu_de` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mo_ta` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nam_hoc` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ngay_ban_hanh` date DEFAULT NULL,
  `id_don_vi_phu_trach` int(11) DEFAULT NULL,
  `id_nguoi_phu_trach` int(11) DEFAULT NULL,
  `trang_thai_duyet` enum('approved','reviewing','need_update') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'reviewing',
  `ngay_tao` timestamp NOT NULL DEFAULT current_timestamp(),
  `ngay_cap_nhat` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `loai_minh_chung` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT 'Minh chung chinh'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `minh_chung`
--

INSERT INTO `minh_chung` (`id`, `ma_minh_chung`, `tieu_de`, `mo_ta`, `nam_hoc`, `ngay_ban_hanh`, `id_don_vi_phu_trach`, `id_nguoi_phu_trach`, `trang_thai_duyet`, `ngay_tao`, `ngay_cap_nhat`, `loai_minh_chung`) VALUES
(1, 'MC.01.01.01', 'Quyết định ban hành mục tiêu và chuẩn đầu ra ngành CNTT', 'Quyết định ban hành mục tiêu và chuẩn đầu ra của CTĐT ngành CNTT', '2025-2026', '2025-09-01', 1, 1, 'approved', '2026-07-17 03:40:15', '2026-07-22 00:48:23', 'Minh chứng chính'),
(2, 'MC.02.01.03', 'Bản mô tả chương trình đào tạo ngành CNTT', 'Bản mô tả chương trình đào tạo phục vụ kiểm định', '2025-2026', '2025-08-15', 2, 2, 'approved', '2026-07-17 03:40:15', '2026-07-22 00:48:23', 'Minh chứng chính'),
(3, 'MC.03.02.04', 'Đề cương chi tiết các học phần chuyên ngành', 'Tập hợp đề cương học phần chuyên ngành CNTT', '2024-2025', '2024-09-05', 5, 1, 'reviewing', '2026-07-17 03:40:15', '2026-07-22 00:48:23', 'Minh chứng chính'),
(4, 'MC.04.01.02', 'Kế hoạch đổi mới phương pháp dạy học', 'Kế hoạch cải tiến phương pháp giảng dạy trong CTĐT', '2025-2026', '2025-10-20', 1, 1, 'need_update', '2026-07-17 03:40:15', '2026-07-22 00:48:23', 'Minh chứng chính'),
(5, 'MC.05.03.05', 'Quy chế đánh giá học phần và ma trận điểm', 'Quy chế đánh giá, rubrics và ma trận điểm học phần', '2025-2026', '2025-11-10', 4, 2, 'approved', '2026-07-17 03:40:15', '2026-07-22 00:48:23', 'Minh chứng chính'),
(10, 'MC.01.01.02', 'Biên bản họp rà soát mục tiêu chương trình đào tạo CNTT', 'Biên bản họp hội đồng khoa về rà soát mục tiêu CTĐT ngành CNTT', '2025-2026', '2025-09-12', 1, 1, 'approved', '2026-07-17 16:20:07', '2026-07-22 00:48:23', 'Minh chung chinh'),
(11, 'MC.01.02.01', 'Khảo sát nhu cầu các bên liên quan về chuẩn đầu ra CNTT', 'Tổng hợp khảo sát doanh nghiệp, cựu sinh viên và người học về chuẩn đầu ra', '2025-2026', '2025-10-03', 3, 2, 'reviewing', '2026-07-17 16:20:07', '2026-07-22 00:48:23', 'Minh chung chinh'),
(12, 'MC.01.02.02', 'Ma trận đối sánh chuẩn đầu ra với mục tiêu CTĐT', 'Ma trận liên kết mục tiêu chương trình với chuẩn đầu ra ngành CNTT', '2025-2026', '2025-10-08', 1, 1, 'need_update', '2026-07-17 16:20:07', '2026-07-22 00:48:23', 'Minh chung chinh'),
(13, 'MC.02.01.04', 'Kế hoạch cập nhật bản mô tả chương trình đào tạo', 'Kế hoạch rà soát và cập nhật bản mô tả CTĐT theo chu kỳ kiểm định', '2025-2026', '2025-08-22', 2, 1, 'approved', '2026-07-17 16:20:07', '2026-07-22 00:48:23', 'Minh chung chinh'),
(14, 'MC.02.01.05', 'Phụ lục cấu trúc chương trình đào tạo CNTT', 'Phụ lục khối kiến thức, số tín chỉ và phân bổ học phần theo học kỳ', '2025-2026', '2025-08-28', 2, 2, 'approved', '2026-07-17 16:20:07', '2026-07-22 00:48:23', 'Minh chung chinh'),
(15, 'MC.02.01.06', 'Bảng đối sánh CTĐT với khung trình độ quốc gia', 'Bảng đối sánh chương trình đào tạo ngành CNTT với khung trình độ quốc gia Việt Nam', '2024-2025', '2025-01-14', 3, 1, 'reviewing', '2026-07-17 16:20:07', '2026-07-22 00:48:23', 'Minh chung chinh'),
(16, 'MC.03.02.05', 'Danh mục đề cương học phần chuyên ngành CNTT', 'Danh mục đề cương học phần chuyên ngành phục vụ tự đánh giá CTĐT', '2025-2026', '2025-09-05', 5, 1, 'approved', '2026-07-17 16:20:07', '2026-07-22 00:48:23', 'Minh chung chinh'),
(17, 'MC.03.02.06', 'Biên bản nghiệm thu đề cương học phần cập nhật', 'Biên bản nghiệm thu đề cương học phần sau khi cập nhật định hướng nghề nghiệp', '2025-2026', '2025-09-18', 5, 2, 'need_update', '2026-07-17 16:20:07', '2026-07-22 00:48:23', 'Minh chung chinh'),
(18, 'MC.03.02.07', 'Ma trận học phần và chuẩn đầu ra học phần', 'Ma trận liên kết học phần với chuẩn đầu ra học phần và chuẩn đầu ra chương trình', '2024-2025', '2024-11-20', 1, 1, 'approved', '2026-07-17 16:20:07', '2026-07-22 00:48:23', 'Minh chung chinh'),
(19, 'MC.04.01.03', 'Kế hoạch đổi mới phương pháp giảng dạy học kỳ I', 'Kế hoạch áp dụng phương pháp dạy học tích cực trong các học phần CNTT', '2025-2026', '2025-09-25', 1, 1, 'reviewing', '2026-07-17 16:20:07', '2026-07-22 00:48:23', 'Minh chung chinh'),
(20, 'MC.04.01.04', 'Báo cáo triển khai lớp học dự án ngành CNTT', 'Báo cáo minh chứng hoạt động dạy học theo dự án và đánh giá sản phẩm học tập', '2025-2026', '2025-12-02', 1, 2, 'need_update', '2026-07-17 16:20:07', '2026-07-22 00:48:23', 'Minh chung chinh'),
(21, 'MC.04.01.05', 'Danh sách học phần áp dụng blended learning', 'Danh sách các học phần triển khai blended learning và tài nguyên LMS liên quan', '2024-2025', '2025-03-11', 5, 1, 'approved', '2026-07-17 16:20:07', '2026-07-22 00:48:23', 'Minh chung chinh'),
(22, 'MC.05.03.06', 'Quy định xây dựng ma trận đề thi học phần', 'Quy định về xây dựng ma trận đề thi, rubrics và ngân hàng câu hỏi', '2025-2026', '2025-11-18', 4, 1, 'approved', '2026-07-17 16:20:07', '2026-07-22 00:48:23', 'Minh chung chinh'),
(23, 'MC.05.03.07', 'Bảng tổng hợp kết quả đánh giá học phần', 'Bảng tổng hợp điểm quá trình, điểm thi và phân tích kết quả học tập', '2025-2026', '2026-01-10', 4, 2, 'reviewing', '2026-07-17 16:20:07', '2026-07-22 00:48:23', 'Minh chung chinh'),
(24, 'MC.05.03.08', 'Biên bản rà soát quy trình chấm thi và phúc khảo', 'Biên bản rà soát quy trình đánh giá, chấm thi và phúc khảo học phần', '2024-2025', '2025-04-18', 4, 1, 'need_update', '2026-07-17 16:20:07', '2026-07-22 00:48:23', 'Minh chung chinh'),
(25, 'MC.06.01.01', 'Kế hoạch thu thập minh chứng kiểm định CTĐT CNTT', 'Kế hoạch phân công thu thập, chuẩn hóa và cập nhật minh chứng theo tiêu chuẩn', '2025-2026', '2025-07-15', 3, 1, 'approved', '2026-07-17 16:20:07', '2026-07-22 00:48:23', 'Minh chung chinh'),
(26, 'MC.06.01.02', 'Danh mục phân quyền khai thác kho minh chứng', 'Danh mục tài khoản, quyền truy cập và phạm vi khai thác dữ liệu minh chứng', '2025-2026', '2025-07-20', 3, 1, 'approved', '2026-07-17 16:20:07', '2026-07-22 00:48:23', 'Minh chung chinh'),
(28, 'MC.07.01.01', 'Báo cáo tự đánh giá chương trình đào tạo ngành CNTT', 'Bản dự thảo báo cáo tự đánh giá CTĐT ngành CNTT phục vụ kiểm định chất lượng', '2025-2026', '2026-02-05', 3, 1, 'need_update', '2026-07-17 16:20:07', '2026-07-22 01:35:16', 'Minh chứng chính'),
(29, 'MC.07.01.02', 'Phụ lục minh chứng phục vụ đoàn đánh giá ngoài', 'Phụ lục tổng hợp đường dẫn, mã hóa và trạng thái minh chứng phục vụ đánh giá ngoài', '2025-2026', '2026-02-12', 3, 1, 'approved', '2026-07-17 16:20:07', '2026-07-22 01:35:09', 'Minh chứng chính');

-- --------------------------------------------------------

--
-- Table structure for table `minh_chung_tieu_chi`
--

CREATE TABLE `minh_chung_tieu_chi` (
  `id_minh_chung` int(11) NOT NULL,
  `id_tieu_chi` int(11) NOT NULL,
  `ghi_chu` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ngay_tao` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `minh_chung_tieu_chi`
--

INSERT INTO `minh_chung_tieu_chi` (`id_minh_chung`, `id_tieu_chi`, `ghi_chu`, `ngay_tao`) VALUES
(1, 1, NULL, '2026-07-17 09:43:27'),
(1, 2, NULL, '2026-07-17 09:43:27'),
(2, 3, 'Ban mo ta CTDT', '2026-07-17 03:40:15'),
(3, 4, 'De cuong chi tiet', '2026-07-17 03:40:15'),
(4, 5, 'Can bo sung phu luc trien khai', '2026-07-17 03:40:15'),
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
(25, 1, 'Minh ch?ng b? sung', '2026-07-17 16:20:07'),
(26, 2, 'Minh ch?ng b? sung', '2026-07-17 16:20:07'),
(28, 1, NULL, '2026-07-22 01:35:16'),
(29, 2, NULL, '2026-07-22 01:35:09');

-- --------------------------------------------------------

--
-- Table structure for table `nguoi_dung`
--

CREATE TABLE `nguoi_dung` (
  `id` int(11) NOT NULL,
  `ma_nguoi_dung` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id_vai_tro` int(11) NOT NULL,
  `id_don_vi` int(11) DEFAULT NULL,
  `ho_ten` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ten_dang_nhap` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `duong_dan_anh_dai_dien` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mat_khau_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `trang_thai` enum('active','locked') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `dang_nhap_cuoi` datetime DEFAULT NULL,
  `ngay_tao` timestamp NOT NULL DEFAULT current_timestamp(),
  `ngay_cap_nhat` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `nguoi_dung`
--

INSERT INTO `nguoi_dung` (`id`, `ma_nguoi_dung`, `id_vai_tro`, `id_don_vi`, `ho_ten`, `ten_dang_nhap`, `email`, `duong_dan_anh_dai_dien`, `mat_khau_hash`, `trang_thai`, `dang_nhap_cuoi`, `ngay_tao`, `ngay_cap_nhat`) VALUES
(1, 'ND001', 1, 5, 'Dev Nguyen', 'admin', 'admin@fbu.edu.vn', 'uploads/avatars/avatar_user_1_20260717062114.jpg', '$2y$10$JvlJpiWq7KynMo1bP47ptuuZUNRRKwNYJpbip17YDEzsMInyQGk3G', 'active', '2026-07-22 11:07:05', '2026-07-17 03:40:15', '2026-07-22 04:07:05'),
(2, 'ND002', 2, 3, 'Tran Thu Ha', 'kiemdinhtt', 'ha.tt@fbu.edu.vn', 'uploads/avatars/avatar_user_2_20260722060656.jpg', '$2y$10$98IdFASFlTYGEIBVWcui4u52XXRv1x2h4jO/GAJ612wzPiGkMJtsK', 'active', '2026-07-22 11:06:22', '2026-07-17 03:40:15', '2026-07-22 04:06:56');

-- --------------------------------------------------------

--
-- Table structure for table `remember_tokens`
--

CREATE TABLE `remember_tokens` (
  `id` bigint(20) NOT NULL,
  `id_nguoi_dung` int(11) NOT NULL,
  `ma_token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `het_han` datetime NOT NULL,
  `ngay_tao` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `remember_tokens`
--

INSERT INTO `remember_tokens` (`id`, `id_nguoi_dung`, `ma_token`, `het_han`, `ngay_tao`) VALUES
(2, 1, 'b1e3f2f82b1cf4b21f9499b9278326086553705d7c43630896ff16ea4fc16c16', '2026-07-24 18:43:58', '2026-07-17 16:43:58'),
(3, 1, 'f4420bdab150989ee29834cd7b0839e1476b6a7f13191a133816632e114029c4', '2026-07-24 18:44:15', '2026-07-17 16:44:15'),
(4, 1, 'b124adb663deb1eb2daba8cf7188dbc7c3bfed49ce2b8c57a392e55c71e09457', '2026-07-25 08:48:03', '2026-07-18 06:48:03'),
(5, 1, 'f27a57993177966a72d22ad28147856ef02ac9152574b64f64186a7a8d22cc0e', '2026-07-28 18:08:29', '2026-07-21 16:08:29'),
(12, 1, 'dd5c44eda87d17950711d2383c8634128c9d2e153ccb9ec8080f2b2676c26b3a', '2026-07-29 01:56:48', '2026-07-21 23:56:48'),
(13, 1, '6fa4132769a86159d165e4311c262bcfa47d1f3fe8a9eed2dbacf2d3aec4b371', '2026-07-29 04:58:59', '2026-07-22 02:58:59'),
(17, 1, 'eeaf7353b1c2dd8ea7229485b5938e3896236f599be391c30acfca9126074bf0', '2026-07-29 06:07:05', '2026-07-22 04:07:05');

-- --------------------------------------------------------

--
-- Table structure for table `tieu_chi`
--

CREATE TABLE `tieu_chi` (
  `id` int(11) NOT NULL,
  `id_tieu_chuan` int(11) NOT NULL,
  `id_don_vi` int(11) DEFAULT NULL,
  `ma_tieu_chi` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ten_tieu_chi` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `noi_dung_mo_ta` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `trang_thai` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'thieu_minh_chung',
  `thu_tu_hien_thi` int(11) NOT NULL DEFAULT 0,
  `ngay_tao` timestamp NOT NULL DEFAULT current_timestamp(),
  `ngay_cap_nhat` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tieu_chi`
--

INSERT INTO `tieu_chi` (`id`, `id_tieu_chuan`, `id_don_vi`, `ma_tieu_chi`, `ten_tieu_chi`, `noi_dung_mo_ta`, `trang_thai`, `thu_tu_hien_thi`, `ngay_tao`, `ngay_cap_nhat`) VALUES
(1, 1, 1, '1.1', 'Mục tiêu của CTĐT được xác định rõ ràng', 'Mục tiêu CTĐT phù hợp sứ mạng và nhu cầu xã hội', 'du_minh_chung', 1, '2026-07-17 03:40:15', '2026-07-22 00:48:23'),
(2, 1, 1, '1.2', 'Chuẩn đầu ra phản ánh yêu cầu của các bên liên quan', 'Chuẩn đầu ra được xây dựng và rà soát định kỳ', 'du_minh_chung', 2, '2026-07-17 03:40:15', '2026-07-22 00:48:23'),
(3, 2, NULL, '2.1', 'Bản mô tả CTĐT đầy đủ thông tin cần thiết nads', 'Bản mô tả nêu rõ mục tiêu, CĐR, cấu trúc và học phần', 'need_update', 1, '2026-07-17 03:40:15', '2026-07-22 01:18:35'),
(4, 3, 5, '3.2', 'Nội dung học phần cập nhật theo định hướng nghề nghiệp', 'Đề cương học phần được cập nhật và phê duyệt', 'du_minh_chung', 2, '2026-07-17 03:40:15', '2026-07-22 00:48:23'),
(5, 4, 1, '4.1', 'Hoạt động dạy học thúc đẩy năng lực tự học', 'Hoạt động dạy học có định hướng phát triển năng lực', 'thieu_minh_chung', 1, '2026-07-17 03:40:15', '2026-07-22 00:48:23'),
(11, 1, NULL, '6.4', 'Tiêu chí', 'Tiêu chí', 'missing', 4, '2026-07-22 01:18:11', '2026-07-22 01:18:11');

-- --------------------------------------------------------

--
-- Table structure for table `tieu_chuan`
--

CREATE TABLE `tieu_chuan` (
  `id` int(11) NOT NULL,
  `id_bo_tieu_chuan` int(11) NOT NULL,
  `ma_tieu_chuan` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ten_tieu_chuan` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mo_ta` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `thu_tu_hien_thi` int(11) NOT NULL DEFAULT 0,
  `ngay_tao` timestamp NOT NULL DEFAULT current_timestamp(),
  `ngay_cap_nhat` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tieu_chuan`
--

INSERT INTO `tieu_chuan` (`id`, `id_bo_tieu_chuan`, `ma_tieu_chuan`, `ten_tieu_chuan`, `mo_ta`, `thu_tu_hien_thi`, `ngay_tao`, `ngay_cap_nhat`) VALUES
(1, 1, 'TC01', 'Mục tiêu và chuẩn đầu ra của chương trình đào tạo', 'Quản lý minh chứng liên quan mục tiêu và chuẩn đầu ra', 1, '2026-07-17 03:40:15', '2026-07-22 00:48:23'),
(2, 1, 'TC02', 'Bản mô tả chương trình đào tạo', 'Quản lý bản mô tả chương trình đào tạo', 2, '2026-07-17 03:40:15', '2026-07-22 00:48:23'),
(3, 1, 'TC03', 'Cấu trúc và nội dung chương trình dạy học', 'Quản lý cấu trúc và nội dung chương trình dạy học', 3, '2026-07-17 03:40:15', '2026-07-22 00:48:23'),
(4, 1, 'TC04', 'Phương pháp tiếp cận trong dạy và học', 'Quản lý minh chứng về phương pháp dạy học', 4, '2026-07-17 03:40:15', '2026-07-22 00:48:23'),
(5, 1, 'TC05', 'Đánh giá kết quả học tập của người học', 'Quản lý minh chứng về đánh giá kết quả học tập', 5, '2026-07-17 03:40:15', '2026-07-22 00:48:23'),
(11, 1, 'TC06', 'Thử nghiệm', 'Mô tả thử nghiệm', 6, '2026-07-22 00:54:04', '2026-07-22 00:54:04'),
(15, 1, 'TC07', 'Thử nghiệm 2', 'Thử nghiệm 2', 7, '2026-07-22 01:00:02', '2026-07-22 01:00:02'),
(16, 1, 'TC08', 'Thử nghiệm', 'Thử nghiệm', 8, '2026-07-22 01:12:23', '2026-07-22 01:12:23');

-- --------------------------------------------------------

--
-- Table structure for table `vai_tro`
--

CREATE TABLE `vai_tro` (
  `id` int(11) NOT NULL,
  `ma_vai_tro` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ten_vai_tro` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mo_ta` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ngay_tao` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `vai_tro`
--

INSERT INTO `vai_tro` (`id`, `ma_vai_tro`, `ten_vai_tro`, `mo_ta`, `ngay_tao`) VALUES
(1, 'admin', 'Quản trị viên', 'Quản trị toàn bộ hệ thống', '2026-07-17 03:40:15'),
(2, 'user', 'Người dùng', 'Tài khoản người dùng khai thác dữ liệu', '2026-07-17 03:40:15');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_log_nguoi_dung` (`id_nguoi_dung`);

--
-- Indexes for table `bo_tieu_chuan`
--
ALTER TABLE `bo_tieu_chuan`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_standard_sets_program` (`id_chuong_trinh_dao_tao`);

--
-- Indexes for table `chuong_trinh_dao_tao`
--
ALTER TABLE `chuong_trinh_dao_tao`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`ma_chuong_trinh`);

--
-- Indexes for table `don_vi`
--
ALTER TABLE `don_vi`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`ma_don_vi`);

--
-- Indexes for table `download_logs`
--
ALTER TABLE `download_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_tai_nguoi_dung` (`id_nguoi_dung`),
  ADD KEY `fk_tai_file` (`id_file_minh_chung`);

--
-- Indexes for table `file_minh_chung`
--
ALTER TABLE `file_minh_chung`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_file_minh_chung` (`id_minh_chung`),
  ADD KEY `fk_file_nguoi_tai` (`nguoi_tai_len`);

--
-- Indexes for table `minh_chung`
--
ALTER TABLE `minh_chung`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`ma_minh_chung`),
  ADD KEY `fk_mc_don_vi` (`id_don_vi_phu_trach`),
  ADD KEY `fk_mc_nguoi_dung` (`id_nguoi_phu_trach`);

--
-- Indexes for table `minh_chung_tieu_chi`
--
ALTER TABLE `minh_chung_tieu_chi`
  ADD PRIMARY KEY (`id_minh_chung`,`id_tieu_chi`),
  ADD KEY `fk_mc_tc_tieu_chi` (`id_tieu_chi`);

--
-- Indexes for table `nguoi_dung`
--
ALTER TABLE `nguoi_dung`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`ten_dang_nhap`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `fk_users_role` (`id_vai_tro`),
  ADD KEY `fk_users_department` (`id_don_vi`);

--
-- Indexes for table `remember_tokens`
--
ALTER TABLE `remember_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_ma_token` (`ma_token`),
  ADD KEY `fk_token_nguoi_dung` (`id_nguoi_dung`);

--
-- Indexes for table `tieu_chi`
--
ALTER TABLE `tieu_chi`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_criteria_code_standard` (`id_tieu_chuan`,`ma_tieu_chi`),
  ADD KEY `fk_criteria_department` (`id_don_vi`);

--
-- Indexes for table `tieu_chuan`
--
ALTER TABLE `tieu_chuan`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_standard_code_set` (`id_bo_tieu_chuan`,`ma_tieu_chuan`);

--
-- Indexes for table `vai_tro`
--
ALTER TABLE `vai_tro`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`ma_vai_tro`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `bo_tieu_chuan`
--
ALTER TABLE `bo_tieu_chuan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `chuong_trinh_dao_tao`
--
ALTER TABLE `chuong_trinh_dao_tao`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `don_vi`
--
ALTER TABLE `don_vi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `download_logs`
--
ALTER TABLE `download_logs`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `file_minh_chung`
--
ALTER TABLE `file_minh_chung`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `minh_chung`
--
ALTER TABLE `minh_chung`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `nguoi_dung`
--
ALTER TABLE `nguoi_dung`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `remember_tokens`
--
ALTER TABLE `remember_tokens`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `tieu_chi`
--
ALTER TABLE `tieu_chi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `tieu_chuan`
--
ALTER TABLE `tieu_chuan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `vai_tro`
--
ALTER TABLE `vai_tro`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `fk_log_nguoi_dung` FOREIGN KEY (`id_nguoi_dung`) REFERENCES `nguoi_dung` (`id`);

--
-- Constraints for table `bo_tieu_chuan`
--
ALTER TABLE `bo_tieu_chuan`
  ADD CONSTRAINT `fk_standard_sets_program` FOREIGN KEY (`id_chuong_trinh_dao_tao`) REFERENCES `chuong_trinh_dao_tao` (`id`);

--
-- Constraints for table `download_logs`
--
ALTER TABLE `download_logs`
  ADD CONSTRAINT `fk_tai_file` FOREIGN KEY (`id_file_minh_chung`) REFERENCES `file_minh_chung` (`id`),
  ADD CONSTRAINT `fk_tai_nguoi_dung` FOREIGN KEY (`id_nguoi_dung`) REFERENCES `nguoi_dung` (`id`);

--
-- Constraints for table `file_minh_chung`
--
ALTER TABLE `file_minh_chung`
  ADD CONSTRAINT `fk_file_minh_chung` FOREIGN KEY (`id_minh_chung`) REFERENCES `minh_chung` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_file_nguoi_tai` FOREIGN KEY (`nguoi_tai_len`) REFERENCES `nguoi_dung` (`id`);

--
-- Constraints for table `minh_chung`
--
ALTER TABLE `minh_chung`
  ADD CONSTRAINT `fk_mc_don_vi` FOREIGN KEY (`id_don_vi_phu_trach`) REFERENCES `don_vi` (`id`),
  ADD CONSTRAINT `fk_mc_nguoi_dung` FOREIGN KEY (`id_nguoi_phu_trach`) REFERENCES `nguoi_dung` (`id`);

--
-- Constraints for table `minh_chung_tieu_chi`
--
ALTER TABLE `minh_chung_tieu_chi`
  ADD CONSTRAINT `fk_mc_tc_minh_chung` FOREIGN KEY (`id_minh_chung`) REFERENCES `minh_chung` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_mc_tc_tieu_chi` FOREIGN KEY (`id_tieu_chi`) REFERENCES `tieu_chi` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `nguoi_dung`
--
ALTER TABLE `nguoi_dung`
  ADD CONSTRAINT `fk_users_department` FOREIGN KEY (`id_don_vi`) REFERENCES `don_vi` (`id`),
  ADD CONSTRAINT `fk_users_role` FOREIGN KEY (`id_vai_tro`) REFERENCES `vai_tro` (`id`);

--
-- Constraints for table `remember_tokens`
--
ALTER TABLE `remember_tokens`
  ADD CONSTRAINT `fk_token_nguoi_dung` FOREIGN KEY (`id_nguoi_dung`) REFERENCES `nguoi_dung` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tieu_chi`
--
ALTER TABLE `tieu_chi`
  ADD CONSTRAINT `fk_criteria_department` FOREIGN KEY (`id_don_vi`) REFERENCES `don_vi` (`id`),
  ADD CONSTRAINT `fk_criteria_standard` FOREIGN KEY (`id_tieu_chuan`) REFERENCES `tieu_chuan` (`id`);

--
-- Constraints for table `tieu_chuan`
--
ALTER TABLE `tieu_chuan`
  ADD CONSTRAINT `fk_standards_set` FOREIGN KEY (`id_bo_tieu_chuan`) REFERENCES `bo_tieu_chuan` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
