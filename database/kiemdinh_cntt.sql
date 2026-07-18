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
(1, 1, 1, '1.1', 'Mục tiêu của CTĐT được xác định rõ ràng', 'Mục tiêu CTĐT phù hợp sứ mạng và nhu cầu xã hội', 'complete', 1, '2026-07-17 03:40:15', '2026-07-17 03:40:15'),
(2, 1, 1, '1.2', 'Chuẩn đầu ra phản ánh yêu cầu của các bên liên quan', 'Chuẩn đầu ra được xây dựng và rà soát định kỳ', 'complete', 2, '2026-07-17 03:40:15', '2026-07-17 10:25:59'),
(3, 2, 2, '2.1', 'Bản mô tả CTĐT đầy đủ thông tin cần thiết', 'Bản mô tả nêu rõ mục tiêu, CĐR, cấu trúc và học phần', 'complete', 1, '2026-07-17 03:40:15', '2026-07-17 03:40:15'),
(4, 3, 5, '3.2', 'Nội dung học phần cập nhật theo định hướng nghề nghiệp', 'Đề cương học phần được cập nhật và phê duyệt', 'complete', 2, '2026-07-17 03:40:15', '2026-07-17 03:40:15'),
(5, 4, 1, '4.1', 'Hoạt động dạy học thúc đẩy năng lực tự học', 'Hoạt động dạy học có định hướng phát triển năng lực', 'missing', 1, '2026-07-17 03:40:15', '2026-07-17 03:40:15'),
(6, 5, 4, '5.3', 'Quy trình đánh giá kết quả học tập được công bố', 'Quy trình và ma trận đánh giá được công khai', 'need_update', 3, '2026-07-17 03:40:15', '2026-07-17 03:40:15');

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
(1, 'KHOA_CNTT', 'Khoa Công nghệ thông tin', NULL, 'cntt@fbu.edu.vn', '2026-07-17 03:40:15'),
(2, 'PDT', 'Phòng Đào tạo', NULL, 'daotao@fbu.edu.vn', '2026-07-17 03:40:15'),
(3, 'PDBCL', 'Phòng Đảm bảo chất lượng', NULL, 'dbcl@fbu.edu.vn', '2026-07-17 03:40:15'),
(4, 'PKT', 'Phòng Khảo thí', NULL, 'khaothi@fbu.edu.vn', '2026-07-17 03:40:15'),
(5, 'BM_PM', 'Bộ môn Phần mềm', NULL, 'bomonpm@fbu.edu.vn', '2026-07-17 03:40:15');

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
(1, 'MC.01.01.01', 'Quyết định ban hành mục tiêu và chuẩn đầu ra ngành CNTT', 'Quyết định ban hành mục tiêu và chuẩn đầu ra của CTĐT ngành CNTT', '2025-2026', '2025-09-01', 1, 1, 'approved', '2026-07-17 03:40:15', '2026-07-17 13:09:07'),
(2, 'MC.02.01.03', 'Bản mô tả chương trình đào tạo ngành CNTT', 'Bản mô tả chương trình đào tạo phục vụ kiểm định', '2025-2026', '2025-08-15', 2, 2, 'approved', '2026-07-17 03:40:15', '2026-07-17 03:40:15'),
(3, 'MC.03.02.04', 'Đề cương chi tiết các học phần chuyên ngành', 'Tập hợp đề cương học phần chuyên ngành CNTT', '2024-2025', '2024-09-05', 5, 1, 'reviewing', '2026-07-17 03:40:15', '2026-07-17 03:40:15'),
(4, 'MC.04.01.02', 'Kế hoạch đổi mới phương pháp dạy học', 'Kế hoạch cải tiến phương pháp giảng dạy trong CTĐT', '2025-2026', '2025-10-20', 1, 1, 'need_update', '2026-07-17 03:40:15', '2026-07-17 03:40:15'),
(5, 'MC.05.03.05', 'Quy chế đánh giá học phần và ma trận điểm', 'Quy chế đánh giá, rubrics và ma trận điểm học phần', '2025-2026', '2025-11-10', 4, 2, 'approved', '2026-07-17 03:40:15', '2026-07-17 03:40:15'),
(10, 'MC.01.01.02', 'Biên bản họp rà soát mục tiêu chương trình đào tạo CNTT', 'Biên bản họp hội đồng khoa về rà soát mục tiêu CTĐT ngành CNTT', '2025-2026', '2025-09-12', 1, 1, 'approved', '2026-07-17 16:20:07', '2026-07-17 16:24:09'),
(11, 'MC.01.02.01', 'Khảo sát nhu cầu các bên liên quan về chuẩn đầu ra CNTT', 'Tổng hợp khảo sát doanh nghiệp, cựu sinh viên và người học về chuẩn đầu ra', '2025-2026', '2025-10-03', 3, 2, 'reviewing', '2026-07-17 16:20:07', '2026-07-17 16:24:09'),
(12, 'MC.01.02.02', 'Ma trận đối sánh chuẩn đầu ra với mục tiêu CTĐT', 'Ma trận liên kết mục tiêu chương trình với chuẩn đầu ra ngành CNTT', '2025-2026', '2025-10-08', 1, 1, 'need_update', '2026-07-17 16:20:07', '2026-07-17 16:24:09'),
(13, 'MC.02.01.04', 'Kế hoạch cập nhật bản mô tả chương trình đào tạo', 'Kế hoạch rà soát và cập nhật bản mô tả CTĐT theo chu kỳ kiểm định', '2025-2026', '2025-08-22', 2, 1, 'approved', '2026-07-17 16:20:07', '2026-07-17 16:24:09'),
(14, 'MC.02.01.05', 'Phụ lục cấu trúc chương trình đào tạo CNTT', 'Phụ lục khối kiến thức, số tín chỉ và phân bổ học phần theo học kỳ', '2025-2026', '2025-08-28', 2, 2, 'approved', '2026-07-17 16:20:07', '2026-07-17 16:24:09'),
(15, 'MC.02.01.06', 'Bảng đối sánh CTĐT với khung trình độ quốc gia', 'Bảng đối sánh chương trình đào tạo ngành CNTT với khung trình độ quốc gia Việt Nam', '2024-2025', '2025-01-14', 3, 1, 'reviewing', '2026-07-17 16:20:07', '2026-07-17 16:24:09'),
(16, 'MC.03.02.05', 'Danh mục đề cương học phần chuyên ngành CNTT', 'Danh mục đề cương học phần chuyên ngành phục vụ tự đánh giá CTĐT', '2025-2026', '2025-09-05', 5, 1, 'approved', '2026-07-17 16:20:07', '2026-07-17 16:24:09'),
(17, 'MC.03.02.06', 'Biên bản nghiệm thu đề cương học phần cập nhật', 'Biên bản nghiệm thu đề cương học phần sau khi cập nhật định hướng nghề nghiệp', '2025-2026', '2025-09-18', 5, 2, 'need_update', '2026-07-17 16:20:07', '2026-07-17 16:24:09'),
(18, 'MC.03.02.07', 'Ma trận học phần và chuẩn đầu ra học phần', 'Ma trận liên kết học phần với chuẩn đầu ra học phần và chuẩn đầu ra chương trình', '2024-2025', '2024-11-20', 1, 1, 'approved', '2026-07-17 16:20:07', '2026-07-17 16:24:09'),
(19, 'MC.04.01.03', 'Kế hoạch đổi mới phương pháp giảng dạy học kỳ I', 'Kế hoạch áp dụng phương pháp dạy học tích cực trong các học phần CNTT', '2025-2026', '2025-09-25', 1, 1, 'reviewing', '2026-07-17 16:20:07', '2026-07-17 16:24:09'),
(20, 'MC.04.01.04', 'Báo cáo triển khai lớp học dự án ngành CNTT', 'Báo cáo minh chứng hoạt động dạy học theo dự án và đánh giá sản phẩm học tập', '2025-2026', '2025-12-02', 1, 2, 'need_update', '2026-07-17 16:20:07', '2026-07-17 16:24:09'),
(21, 'MC.04.01.05', 'Danh sách học phần áp dụng blended learning', 'Danh sách các học phần triển khai blended learning và tài nguyên LMS liên quan', '2024-2025', '2025-03-11', 5, 1, 'approved', '2026-07-17 16:20:07', '2026-07-17 16:24:09'),
(22, 'MC.05.03.06', 'Quy định xây dựng ma trận đề thi học phần', 'Quy định về xây dựng ma trận đề thi, rubrics và ngân hàng câu hỏi', '2025-2026', '2025-11-18', 4, 1, 'approved', '2026-07-17 16:20:07', '2026-07-17 16:24:09'),
(23, 'MC.05.03.07', 'Bảng tổng hợp kết quả đánh giá học phần', 'Bảng tổng hợp điểm quá trình, điểm thi và phân tích kết quả học tập', '2025-2026', '2026-01-10', 4, 2, 'reviewing', '2026-07-17 16:20:07', '2026-07-17 16:24:09'),
(24, 'MC.05.03.08', 'Biên bản rà soát quy trình chấm thi và phúc khảo', 'Biên bản rà soát quy trình đánh giá, chấm thi và phúc khảo học phần', '2024-2025', '2025-04-18', 4, 1, 'need_update', '2026-07-17 16:20:07', '2026-07-17 16:24:09'),
(25, 'MC.06.01.01', 'Kế hoạch thu thập minh chứng kiểm định CTĐT CNTT', 'Kế hoạch phân công thu thập, chuẩn hóa và cập nhật minh chứng theo tiêu chuẩn', '2025-2026', '2025-07-15', 3, 1, 'approved', '2026-07-17 16:20:07', '2026-07-17 16:24:09'),
(26, 'MC.06.01.02', 'Danh mục phân quyền khai thác kho minh chứng', 'Danh mục tài khoản, quyền truy cập và phạm vi khai thác dữ liệu minh chứng', '2025-2026', '2025-07-20', 3, 1, 'approved', '2026-07-17 16:20:07', '2026-07-17 16:24:09'),
(27, 'MC.06.01.03', 'Biên bản kiểm tra định kỳ cơ sở dữ liệu minh chứng', 'Biên bản kiểm tra tính đầy đủ, hợp lệ và khả năng truy xuất của minh chứng', '2025-2026', '2025-12-20', 3, 2, 'reviewing', '2026-07-17 16:20:07', '2026-07-17 16:24:09'),
(28, 'MC.07.01.01', 'Báo cáo tự đánh giá chương trình đào tạo ngành CNTT', 'Bản dự thảo báo cáo tự đánh giá CTĐT ngành CNTT phục vụ kiểm định chất lượng', '2025-2026', '2026-02-05', 3, 1, 'need_update', '2026-07-17 16:20:07', '2026-07-17 16:24:09'),
(29, 'MC.07.01.02', 'Phụ lục minh chứng phục vụ đoàn đánh giá ngoài', 'Phụ lục tổng hợp đường dẫn, mã hóa và trạng thái minh chứng phục vụ đánh giá ngoài', '2025-2026', '2026-02-12', 3, 1, 'approved', '2026-07-17 16:20:07', '2026-07-17 16:24:09');

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
(2, 3, 'Bản mô tả CTĐT', '2026-07-17 03:40:15'),
(3, 4, 'Đề cương chi tiết', '2026-07-17 03:40:15'),
(4, 5, 'Cần bổ sung phụ lục triển khai', '2026-07-17 03:40:15'),
(5, 6, 'Quy chế và ma trận điểm', '2026-07-17 03:40:15'),
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
(1, 'admin', 'Quản trị viên', 'Quản trị toàn bộ hệ thống', '2026-07-17 03:40:15'),
(2, 'staff', 'Cán bộ kiểm định', 'Cập nhật và rà soát hồ sơ minh chứng', '2026-07-17 03:40:15'),
(3, 'viewer', 'Người dùng tra cứu', 'Tra cứu và tải minh chứng được phép', '2026-07-17 03:40:15');

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
(1, 1, 'TC01', 'Mục tiêu và chuẩn đầu ra của chương trình đào tạo', 'Quản lý minh chứng liên quan mục tiêu và chuẩn đầu ra', 1, '2026-07-17 03:40:15', '2026-07-17 03:40:15'),
(2, 1, 'TC02', 'Bản mô tả chương trình đào tạo', 'Quản lý bản mô tả chương trình đào tạo', 2, '2026-07-17 03:40:15', '2026-07-17 03:40:15'),
(3, 1, 'TC03', 'Cấu trúc và nội dung chương trình dạy học', 'Quản lý cấu trúc và nội dung chương trình dạy học', 3, '2026-07-17 03:40:15', '2026-07-17 03:40:15'),
(4, 1, 'TC04', 'Phương pháp tiếp cận trong dạy và học', 'Quản lý minh chứng về phương pháp dạy học', 4, '2026-07-17 03:40:15', '2026-07-17 03:40:15'),
(5, 1, 'TC05', 'Đánh giá kết quả học tập của người học', 'Quản lý minh chứng về đánh giá kết quả học tập', 5, '2026-07-17 03:40:15', '2026-07-17 03:40:15');

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
(1, 1, 'Bộ tiêu chuẩn đánh giá chất lượng chương trình đào tạo', '2025', 'Bộ Giáo dục và Đào tạo', 'Bộ tiêu chuẩn dùng cho cơ sở dữ liệu minh chứng phục vụ kiểm định CTĐT', 'active', '2026-07-17 03:40:15', '2026-07-17 03:40:15');

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
(1, '7480201', 'Công nghệ thông tin', 'Đại học chính quy', 'Khoa Công nghệ thông tin', 'Chu kỳ kiểm định 2026-2031', 'Chương trình đào tạo ngành Công nghệ thông tin của Trường Đại học Tài chính - Ngân hàng Hà Nội', 'active', '2026-07-17 03:40:15', '2026-07-17 03:40:15');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
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

INSERT INTO `users` (`id`, `role_id`, `department_id`, `full_name`, `username`, `email`, `avatar_path`, `password_hash`, `status`, `last_login_at`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 'Dev Nguyen', 'admin', 'admin@fbu.edu.vn', 'uploads/avatars/avatar_user_1_20260717062114.jpg', '$2y$10$JvlJpiWq7KynMo1bP47ptuuZUNRRKwNYJpbip17YDEzsMInyQGk3G', 'active', '2026-07-17 23:44:15', '2026-07-17 03:40:15', '2026-07-17 16:44:15'),
(2, 3, 3, 'Trần Thu Hà', 'kiemdinhtt', 'ha.tt@fbu.edu.vn', NULL, '$2y$10$98IdFASFlTYGEIBVWcui4u52XXRv1x2h4jO/GAJ612wzPiGkMJtsK', 'active', '2026-07-17 23:43:27', '2026-07-17 03:40:15', '2026-07-17 16:43:27');

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
