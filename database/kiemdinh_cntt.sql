-- phpMyAdmin SQL Dump
-- version 5.1.3
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 03, 2026 at 05:34 AM
-- Server version: 10.4.24-MariaDB
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
  `id_ban_ghi` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gia_tri_cu` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`gia_tri_cu`)),
  `gia_tri_moi` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`gia_tri_moi`)),
  `dia_chi_ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ngay_tao` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `botieuchuan`
--

CREATE TABLE `botieuchuan` (
  `MaBoTieuChuan` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `TenBoTieuChuan` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ThongTu` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `NgayBanHanh` date DEFAULT NULL,
  `MoTa` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `TepTinPDF` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `TrangThai` tinyint(1) NOT NULL DEFAULT 1,
  `NgayTao` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cau_hinh`
--

CREATE TABLE `cau_hinh` (
  `khoa` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `gia_tri` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ngay_cap_nhat` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `cau_hinh`
--

INSERT INTO `cau_hinh` (`khoa`, `gia_tri`, `ngay_cap_nhat`) VALUES
('cycle', '2026-2031', '2026-09-30 09:36:27'),
('degree', 'Cử nhân', '2026-09-30 09:36:27'),
('faculty', 'Khoa Công nghệ thông tin', '2026-09-30 09:36:27'),
('program_code', '7480201', '2026-09-30 09:36:27'),
('program_name', 'Công nghệ thông tin', '2026-09-30 09:36:27'),
('school', 'Trường Đại học Tài chính - Ngân hàng Hà Nội', '2026-09-30 09:36:27');

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

-- --------------------------------------------------------

--
-- Table structure for table `minhchung`
--

CREATE TABLE `minhchung` (
  `MaMinhChung` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `TenMinhChung` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `NgayBanHanh` date DEFAULT NULL,
  `MoTa` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `TepTin` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `NamHoc` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `TrangThai` tinyint(1) NOT NULL DEFAULT 1,
  `MaTieuChi` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `MaBoTieuChuan` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `MaNguoiDung` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `NgayCapNhat` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `NgayTao` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `nguoidung`
--

CREATE TABLE `nguoidung` (
  `MaNguoiDung` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `HoTen` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
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
-- Dumping data for table `nguoidung`
--

INSERT INTO `nguoidung` (`MaNguoiDung`, `HoTen`, `Email`, `SoDienThoai`, `TenDangNhap`, `MatKhau`, `VaiTro`, `TrangThai`, `DuongDanAnhDaiDien`, `DangNhapCuoi`, `NgayTao`) VALUES
('ND001', 'Quản trị viên', 'admin@fbu.edu.vn', '0912345678', 'admin', '$2y$10$JvlJpiWq7KynMo1bP47ptuuZUNRRKwNYJpbip17YDEzsMInyQGk3G', 'admin', 1, NULL, '2026-10-03 10:00:43', '2026-09-29 17:02:28'),
('ND002', 'ThS. Nguyễn Văn An', 'annv@fbu.edu.vn', '0987654321', 'nguyenvanan', '$2y$10$JvlJpiWq7KynMo1bP47ptuuZUNRRKwNYJpbip17YDEzsMInyQGk3G', 'user', 1, NULL, '2026-09-30 20:18:35', '2026-09-29 17:02:28'),
('ND003', 'TS. Trần Thị Bích', 'bich.tt@fbu.edu.vn', '0911223344', 'tranthibich', '$2y$10$JvlJpiWq7KynMo1bP47ptuuZUNRRKwNYJpbip17YDEzsMInyQGk3G', 'admin', 1, NULL, NULL, '2026-09-29 17:02:28'),
('ND004', 'PGS.TS. Lê Hoàng Nam', 'namlh@fbu.edu.vn', '0903112233', 'lehoangnam', '$2y$10$JvlJpiWq7KynMo1bP47ptuuZUNRRKwNYJpbip17YDEzsMInyQGk3G', 'user', 1, NULL, '2026-09-30 15:16:26', '2026-09-29 17:02:28'),
('ND005', 'ThS. Phạm Thu Trang', 'trangpt@fbu.edu.vn', '0978998877', 'phamthutrang', '$2y$10$JvlJpiWq7KynMo1bP47ptuuZUNRRKwNYJpbip17YDEzsMInyQGk3G', 'user', 1, NULL, NULL, '2026-09-29 17:02:28'),
('ND006', 'KS. Vũ Đình Trọng', 'trongvd@fbu.edu.vn', '0934556677', 'vudinhtrong', '$2y$10$JvlJpiWq7KynMo1bP47ptuuZUNRRKwNYJpbip17YDEzsMInyQGk3G', 'user', 1, NULL, NULL, '2026-09-29 17:02:28'),
('ND007', 'ThS. Hoàng Minh Đức', 'duchm@fbu.edu.vn', '0945667788', 'hoangminhduc', '$2y$10$JvlJpiWq7KynMo1bP47ptuuZUNRRKwNYJpbip17YDEzsMInyQGk3G', 'user', 0, NULL, NULL, '2026-09-29 17:02:28'),
('ND008', 'Người dùng thử nghiệm', 'user@fbu.edu.vn', '0966889900', 'user', '$2y$10$JvlJpiWq7KynMo1bP47ptuuZUNRRKwNYJpbip17YDEzsMInyQGk3G', 'user', 1, NULL, '2026-10-03 09:43:31', '2026-09-29 17:02:28');

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

-- --------------------------------------------------------

--
-- Table structure for table `tieuchi`
--

CREATE TABLE `tieuchi` (
  `MaTieuChi` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `TenTieuChi` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `NoiDung` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ThuTu` int(11) NOT NULL DEFAULT 0,
  `MaTieuChuan` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `TrangThai` tinyint(1) NOT NULL DEFAULT 1,
  `NgayTao` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tieuchuan`
--

CREATE TABLE `tieuchuan` (
  `MaTieuChuan` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `TenTieuChuan` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `MoTa` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ThuTu` int(11) NOT NULL DEFAULT 0,
  `MaBoTieuChuan` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `TrangThai` tinyint(1) NOT NULL DEFAULT 1,
  `NgayTao` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
-- Indexes for table `cau_hinh`
--
ALTER TABLE `cau_hinh`
  ADD PRIMARY KEY (`khoa`);

--
-- Indexes for table `download_logs`
--
ALTER TABLE `download_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_download_logs_nguoi_dung` (`MaNguoiDung`),
  ADD KEY `fk_download_logs_minh_chung` (`MaMinhChung`);

--
-- Indexes for table `minhchung`
--
ALTER TABLE `minhchung`
  ADD PRIMARY KEY (`MaMinhChung`),
  ADD KEY `fk_minh_chung_tieu_chi` (`MaTieuChi`),
  ADD KEY `fk_minh_chung_bo` (`MaBoTieuChuan`),
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
  ADD KEY `fk_tieu_chi_tieu_chuan` (`MaTieuChuan`);

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
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `download_logs`
--
ALTER TABLE `download_logs`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `remember_tokens`
--
ALTER TABLE `remember_tokens`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

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
  ADD CONSTRAINT `fk_minh_chung_bo` FOREIGN KEY (`MaBoTieuChuan`) REFERENCES `botieuchuan` (`MaBoTieuChuan`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_minh_chung_nguoi_dung` FOREIGN KEY (`MaNguoiDung`) REFERENCES `nguoidung` (`MaNguoiDung`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_minh_chung_tieu_chi` FOREIGN KEY (`MaTieuChi`) REFERENCES `tieuchi` (`MaTieuChi`) ON DELETE SET NULL;

--
-- Constraints for table `remember_tokens`
--
ALTER TABLE `remember_tokens`
  ADD CONSTRAINT `fk_remember_tokens_nguoi_dung` FOREIGN KEY (`MaNguoiDung`) REFERENCES `nguoidung` (`MaNguoiDung`) ON DELETE CASCADE;

--
-- Constraints for table `tieuchi`
--
ALTER TABLE `tieuchi`
  ADD CONSTRAINT `fk_tieu_chi_tieu_chuan` FOREIGN KEY (`MaTieuChuan`) REFERENCES `tieuchuan` (`MaTieuChuan`) ON DELETE SET NULL;

--
-- Constraints for table `tieuchuan`
--
ALTER TABLE `tieuchuan`
  ADD CONSTRAINT `fk_tieu_chuan_bo` FOREIGN KEY (`MaBoTieuChuan`) REFERENCES `botieuchuan` (`MaBoTieuChuan`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
