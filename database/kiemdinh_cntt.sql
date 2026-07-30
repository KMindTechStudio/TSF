-- phpMyAdmin SQL Dump
-- version 5.1.3
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 30, 2026 at 05:02 PM
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
  `MaNguoiDung` int(11) DEFAULT NULL,
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
(1, 2, 'xem', 'minh_chung', 'MC.01', 1, NULL, NULL, '::1', '2026-07-30 14:56:27');

-- --------------------------------------------------------

--
-- Table structure for table `botieuchuan`
--

CREATE TABLE `botieuchuan` (
  `MaBoTieuChuan` int(11) NOT NULL,
  `TenBoTieuChuan` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `CoQuanBanHanh` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `NamBanHanh` int(11) DEFAULT NULL,
  `MoTa` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `TrangThai` tinyint(1) NOT NULL DEFAULT 1,
  `NgayTao` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `botieuchuan`
--

INSERT INTO `botieuchuan` (`MaBoTieuChuan`, `TenBoTieuChuan`, `CoQuanBanHanh`, `NamBanHanh`, `MoTa`, `TrangThai`, `NgayTao`) VALUES
(1, 'Bộ tiêu chuẩn đánh giá chất lượng chương trình đào tạo', 'Bộ Giáo dục và Đào tạo', 2025, 'Bộ tiêu chuẩn dùng cho cơ sở dữ liệu minh chứng phục vụ kiểm định CTĐT', 1, '2026-07-30 14:39:02');

-- --------------------------------------------------------

--
-- Table structure for table `download_logs`
--

CREATE TABLE `download_logs` (
  `id` bigint(20) NOT NULL,
  `MaNguoiDung` int(11) DEFAULT NULL,
  `MaMinhChung` int(11) NOT NULL,
  `ngay_tai` timestamp NOT NULL DEFAULT current_timestamp(),
  `dia_chi_ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
-- Table structure for table `minhchung`
--

CREATE TABLE `minhchung` (
  `MaMinhChung` int(11) NOT NULL,
  `TenMinhChung` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `MoTa` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `TepTin` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `NamHoc` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `NgayCapNhat` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `TrangThai` tinyint(1) NOT NULL DEFAULT 1,
  `MaLoai` int(11) DEFAULT NULL,
  `MaTieuChi` int(11) NOT NULL,
  `MaNguoiDung` int(11) DEFAULT NULL,
  `NgayTao` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `minhchung`
--

INSERT INTO `minhchung` (`MaMinhChung`, `TenMinhChung`, `MoTa`, `TepTin`, `NamHoc`, `NgayCapNhat`, `TrangThai`, `MaLoai`, `MaTieuChi`, `MaNguoiDung`, `NgayTao`) VALUES
(1, 'Quyết định ban hành mục tiêu và chuẩn đầu ra ngành CNTT', 'Quyết định ban hành mục tiêu và chuẩn đầu ra của CTĐT ngành CNTT', 'uploads/evidences/MC_01_01_01.pdf', '2025-2026', '2026-07-30 21:39:03', 1, 1, 1, 1, '2026-07-30 14:39:03'),
(2, 'Bản mô tả chương trình đào tạo ngành CNTT', 'Bản mô tả chương trình đào tạo phục vụ kiểm định', 'uploads/evidences/MC_02_01_03.docx', '2025-2026', '2026-07-30 21:39:03', 1, 1, 3, 2, '2026-07-30 14:39:03'),
(3, 'Đề cương chi tiết các học phần chuyên ngành', 'Tập hợp đề cương học phần chuyên ngành CNTT', 'uploads/evidences/MC_03_02_04.zip', '2024-2025', '2026-07-30 21:39:03', 1, 2, 4, 1, '2026-07-30 14:39:03'),
(4, 'Kế hoạch đổi mới phương pháp dạy học', 'Kế hoạch cải tiến phương pháp giảng dạy trong CTĐT', 'uploads/evidences/MC_04_01_02.pdf', '2025-2026', '2026-07-30 21:39:03', 1, 1, 5, 1, '2026-07-30 14:39:03'),
(5, 'Quy chế đánh giá học phần và ma trận điểm', 'Quy chế đánh giá, rubrics và ma trận điểm học phần', 'uploads/evidences/MC_05_03_05.xlsx', '2025-2026', '2026-07-30 21:39:03', 1, 1, 6, 2, '2026-07-30 14:39:03');

-- --------------------------------------------------------

--
-- Table structure for table `nguoidung`
--

CREATE TABLE `nguoidung` (
  `MaNguoiDung` int(11) NOT NULL,
  `HoTen` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `DonViCongTac` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Email` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `SDT` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `TenDangNhap` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `MatKhau` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `VaiTro` enum('admin','user') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'user',
  `TrangThai` tinyint(1) NOT NULL DEFAULT 1,
  `DuongDanAnhDaiDien` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `DangNhapCuoi` datetime DEFAULT NULL,
  `NgayTao` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `nguoidung`
--

INSERT INTO `nguoidung` (`MaNguoiDung`, `HoTen`, `DonViCongTac`, `Email`, `SDT`, `TenDangNhap`, `MatKhau`, `VaiTro`, `TrangThai`, `DuongDanAnhDaiDien`, `DangNhapCuoi`, `NgayTao`) VALUES
(1, 'Quản trị viên', 'Khoa Công nghệ thông tin', 'admin@fbu.edu.vn', '0912345678', 'admin', '$2y$10$JvlJpiWq7KynMo1bP47ptuuZUNRRKwNYJpbip17YDEzsMInyQGk3G', 'admin', 1, NULL, '2026-07-30 22:00:34', '2026-07-30 14:39:02'),
(2, 'Nguyễn Văn A', 'Phòng Đảm bảo chất lượng', 'user01@fbu.edu.vn', '0987654321', 'user01', '$2y$10$JvlJpiWq7KynMo1bP47ptuuZUNRRKwNYJpbip17YDEzsMInyQGk3G', 'user', 1, NULL, '2026-07-30 21:48:25', '2026-07-30 14:39:02');

-- --------------------------------------------------------

--
-- Table structure for table `remember_tokens`
--

CREATE TABLE `remember_tokens` (
  `id` bigint(20) NOT NULL,
  `MaNguoiDung` int(11) NOT NULL,
  `ma_token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `het_han` datetime NOT NULL,
  `ngay_tao` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `remember_tokens`
--

INSERT INTO `remember_tokens` (`id`, `MaNguoiDung`, `ma_token`, `het_han`, `ngay_tao`) VALUES
(2, 1, '038f20bd8b36e1eb5e68ddd0c944d321b0002759252946ef6b0f55477749f794', '2026-08-06 17:00:34', '2026-07-30 15:00:34');

-- --------------------------------------------------------

--
-- Table structure for table `tieuchi`
--

CREATE TABLE `tieuchi` (
  `MaTieuChi` int(11) NOT NULL,
  `TenTieuChi` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `NoiDung` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ThuTu` int(11) NOT NULL DEFAULT 0,
  `MaTieuChuan` int(11) NOT NULL,
  `TrangThai` tinyint(1) NOT NULL DEFAULT 1,
  `NgayTao` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tieuchi`
--

INSERT INTO `tieuchi` (`MaTieuChi`, `TenTieuChi`, `NoiDung`, `ThuTu`, `MaTieuChuan`, `TrangThai`, `NgayTao`) VALUES
(1, 'Mục tiêu của CTĐT được xác định rõ ràng', 'Mục tiêu CTĐT phù hợp sứ mạng và nhu cầu xã hội', 1, 1, 1, '2026-07-30 14:39:03'),
(2, 'Chuẩn đầu ra phản ánh yêu cầu của các bên liên quan', 'Chuẩn đầu ra được xây dựng và rà soát định kỳ', 2, 1, 1, '2026-07-30 14:39:03'),
(3, 'Bản mô tả CTĐT đầy đủ thông tin cần thiết', 'Bản mô tả nêu rõ mục tiêu, CĐR, cấu trúc và học phần', 1, 2, 1, '2026-07-30 14:39:03'),
(4, 'Nội dung học phần cập nhật theo định hướng nghề nghiệp', 'Đề cương học phần được cập nhật và phê duyệt', 2, 3, 1, '2026-07-30 14:39:03'),
(5, 'Hoạt động dạy học thúc đẩy năng lực tự học', 'Hoạt động dạy học có định hướng phát triển năng lực', 1, 4, 1, '2026-07-30 14:39:03'),
(6, 'Quy trình đánh giá kết quả học tập được công bố', 'Quy trình và ma trận đánh giá được công khai', 3, 5, 1, '2026-07-30 14:39:03');

-- --------------------------------------------------------

--
-- Table structure for table `tieuchuan`
--

CREATE TABLE `tieuchuan` (
  `MaTieuChuan` int(11) NOT NULL,
  `TenTieuChuan` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `MoTa` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ThuTu` int(11) NOT NULL DEFAULT 0,
  `MaBoTieuChuan` int(11) NOT NULL,
  `TrangThai` tinyint(1) NOT NULL DEFAULT 1,
  `NgayTao` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tieuchuan`
--

INSERT INTO `tieuchuan` (`MaTieuChuan`, `TenTieuChuan`, `MoTa`, `ThuTu`, `MaBoTieuChuan`, `TrangThai`, `NgayTao`) VALUES
(1, 'Mục tiêu và chuẩn đầu ra của chương trình đào tạo', 'Quản lý minh chứng liên quan mục tiêu và chuẩn đầu ra', 1, 1, 1, '2026-07-30 14:39:02'),
(2, 'Bản mô tả chương trình đào tạo', 'Quản lý bản mô tả chương trình đào tạo', 2, 1, 1, '2026-07-30 14:39:02'),
(3, 'Cấu trúc và nội dung chương trình dạy học', 'Quản lý cấu trúc và nội dung chương trình dạy học', 3, 1, 1, '2026-07-30 14:39:02'),
(4, 'Phương pháp tiếp cận trong dạy và học', 'Quản lý minh chứng về phương pháp dạy học', 4, 1, 1, '2026-07-30 14:39:02'),
(5, 'Đánh giá kết quả học tập của người học', 'Quản lý minh chứng về đánh giá kết quả học tập', 5, 1, 1, '2026-07-30 14:39:02');

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
-- Indexes for table `botieuchuan`
--
ALTER TABLE `botieuchuan`
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
-- Indexes for table `minhchung`
--
ALTER TABLE `minhchung`
  ADD PRIMARY KEY (`MaMinhChung`),
  ADD KEY `fk_minh_chung_loai` (`MaLoai`),
  ADD KEY `fk_minh_chung_tieu_chi` (`MaTieuChi`),
  ADD KEY `fk_minh_chung_nguoi_dung` (`MaNguoiDung`);

--
-- Indexes for table `nguoidung`
--
ALTER TABLE `nguoidung`
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
-- Indexes for table `tieuchi`
--
ALTER TABLE `tieuchi`
  ADD PRIMARY KEY (`MaTieuChi`),
  ADD KEY `fk_tieu_chi_chuan` (`MaTieuChuan`);

--
-- Indexes for table `tieuchuan`
--
ALTER TABLE `tieuchuan`
  ADD PRIMARY KEY (`MaTieuChuan`),
  ADD KEY `fk_tieu_chuan_bo` (`MaBoTieuChuan`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `botieuchuan`
--
ALTER TABLE `botieuchuan`
  MODIFY `MaBoTieuChuan` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `download_logs`
--
ALTER TABLE `download_logs`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `loaiminhchung`
--
ALTER TABLE `loaiminhchung`
  MODIFY `MaLoai` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `minhchung`
--
ALTER TABLE `minhchung`
  MODIFY `MaMinhChung` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `nguoidung`
--
ALTER TABLE `nguoidung`
  MODIFY `MaNguoiDung` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `remember_tokens`
--
ALTER TABLE `remember_tokens`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `tieuchi`
--
ALTER TABLE `tieuchi`
  MODIFY `MaTieuChi` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `tieuchuan`
--
ALTER TABLE `tieuchuan`
  MODIFY `MaTieuChuan` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

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
-- Constraints for table `minhchung`
--
ALTER TABLE `minhchung`
  ADD CONSTRAINT `fk_minh_chung_loai` FOREIGN KEY (`MaLoai`) REFERENCES `loaiminhchung` (`MaLoai`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_minh_chung_nguoi_dung` FOREIGN KEY (`MaNguoiDung`) REFERENCES `nguoidung` (`MaNguoiDung`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_minh_chung_tieu_chi` FOREIGN KEY (`MaTieuChi`) REFERENCES `tieuchi` (`MaTieuChi`) ON DELETE CASCADE;

--
-- Constraints for table `remember_tokens`
--
ALTER TABLE `remember_tokens`
  ADD CONSTRAINT `fk_remember_tokens_nguoi_dung` FOREIGN KEY (`MaNguoiDung`) REFERENCES `nguoidung` (`MaNguoiDung`) ON DELETE CASCADE;

--
-- Constraints for table `tieuchi`
--
ALTER TABLE `tieuchi`
  ADD CONSTRAINT `fk_tieu_chi_chuan` FOREIGN KEY (`MaTieuChuan`) REFERENCES `tieuchuan` (`MaTieuChuan`) ON DELETE CASCADE;

--
-- Constraints for table `tieuchuan`
--
ALTER TABLE `tieuchuan`
  ADD CONSTRAINT `fk_tieu_chuan_bo` FOREIGN KEY (`MaBoTieuChuan`) REFERENCES `botieuchuan` (`MaBoTieuChuan`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
