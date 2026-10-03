-- MariaDB dump 10.19  Distrib 10.4.24-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: kiemdinh_cntt
-- ------------------------------------------------------
-- Server version	10.4.24-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `audit_logs`
--

DROP TABLE IF EXISTS `audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `audit_logs` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `MaNguoiDung` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hanh_dong` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phan_he` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ten_ban_ghi` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id_ban_ghi` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gia_tri_cu` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`gia_tri_cu`)),
  `gia_tri_moi` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`gia_tri_moi`)),
  `dia_chi_ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ngay_tao` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_audit_logs_nguoi_dung` (`MaNguoiDung`),
  CONSTRAINT `fk_audit_logs_nguoi_dung` FOREIGN KEY (`MaNguoiDung`) REFERENCES `nguoidung` (`MaNguoiDung`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_logs`
--

LOCK TABLES `audit_logs` WRITE;
/*!40000 ALTER TABLE `audit_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `audit_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `botieuchuan`
--

DROP TABLE IF EXISTS `botieuchuan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `botieuchuan` (
  `MaBoTieuChuan` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `TenBoTieuChuan` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ThongTu` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `NgayBanHanh` date DEFAULT NULL,
  `MoTa` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `TepTinPDF` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `TrangThai` tinyint(1) NOT NULL DEFAULT 1,
  `NgayTao` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`MaBoTieuChuan`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `botieuchuan`
--

LOCK TABLES `botieuchuan` WRITE;
/*!40000 ALTER TABLE `botieuchuan` DISABLE KEYS */;
/*!40000 ALTER TABLE `botieuchuan` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cau_hinh`
--

DROP TABLE IF EXISTS `cau_hinh`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cau_hinh` (
  `khoa` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `gia_tri` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ngay_cap_nhat` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`khoa`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cau_hinh`
--

LOCK TABLES `cau_hinh` WRITE;
/*!40000 ALTER TABLE `cau_hinh` DISABLE KEYS */;
INSERT INTO `cau_hinh` VALUES ('cycle','2026-2031','2026-09-30 09:36:27'),('degree','Cử nhân','2026-09-30 09:36:27'),('faculty','Khoa Công nghệ thông tin','2026-09-30 09:36:27'),('program_code','7480201','2026-09-30 09:36:27'),('program_name','Công nghệ thông tin','2026-09-30 09:36:27'),('school','Trường Đại học Tài chính - Ngân hàng Hà Nội','2026-09-30 09:36:27');
/*!40000 ALTER TABLE `cau_hinh` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `download_logs`
--

DROP TABLE IF EXISTS `download_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `download_logs` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `MaNguoiDung` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `MaMinhChung` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ngay_tai` timestamp NOT NULL DEFAULT current_timestamp(),
  `dia_chi_ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_download_logs_nguoi_dung` (`MaNguoiDung`),
  KEY `fk_download_logs_minh_chung` (`MaMinhChung`),
  CONSTRAINT `fk_download_logs_minh_chung` FOREIGN KEY (`MaMinhChung`) REFERENCES `minhchung` (`MaMinhChung`) ON DELETE CASCADE,
  CONSTRAINT `fk_download_logs_nguoi_dung` FOREIGN KEY (`MaNguoiDung`) REFERENCES `nguoidung` (`MaNguoiDung`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `download_logs`
--

LOCK TABLES `download_logs` WRITE;
/*!40000 ALTER TABLE `download_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `download_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `minhchung`
--

DROP TABLE IF EXISTS `minhchung`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
  `NgayTao` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`MaMinhChung`),
  KEY `fk_minh_chung_tieu_chi` (`MaTieuChi`),
  KEY `fk_minh_chung_bo` (`MaBoTieuChuan`),
  KEY `fk_minh_chung_nguoi_dung` (`MaNguoiDung`),
  CONSTRAINT `fk_minh_chung_bo` FOREIGN KEY (`MaBoTieuChuan`) REFERENCES `botieuchuan` (`MaBoTieuChuan`) ON DELETE SET NULL,
  CONSTRAINT `fk_minh_chung_nguoi_dung` FOREIGN KEY (`MaNguoiDung`) REFERENCES `nguoidung` (`MaNguoiDung`) ON DELETE SET NULL,
  CONSTRAINT `fk_minh_chung_tieu_chi` FOREIGN KEY (`MaTieuChi`) REFERENCES `tieuchi` (`MaTieuChi`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `minhchung`
--

LOCK TABLES `minhchung` WRITE;
/*!40000 ALTER TABLE `minhchung` DISABLE KEYS */;
/*!40000 ALTER TABLE `minhchung` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `nguoidung`
--

DROP TABLE IF EXISTS `nguoidung`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
  `NgayTao` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`MaNguoiDung`),
  UNIQUE KEY `Email` (`Email`),
  UNIQUE KEY `TenDangNhap` (`TenDangNhap`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `nguoidung`
--

LOCK TABLES `nguoidung` WRITE;
/*!40000 ALTER TABLE `nguoidung` DISABLE KEYS */;
INSERT INTO `nguoidung` VALUES ('ND001','Quản trị viên','admin@fbu.edu.vn','0912345678','admin','$2y$10$JvlJpiWq7KynMo1bP47ptuuZUNRRKwNYJpbip17YDEzsMInyQGk3G','admin',1,NULL,'2026-10-03 10:00:43','2026-09-29 17:02:28'),('ND002','ThS. Nguyễn Văn An','annv@fbu.edu.vn','0987654321','nguyenvanan','$2y$10$JvlJpiWq7KynMo1bP47ptuuZUNRRKwNYJpbip17YDEzsMInyQGk3G','user',1,NULL,'2026-09-30 20:18:35','2026-09-29 17:02:28'),('ND003','TS. Trần Thị Bích','bich.tt@fbu.edu.vn','0911223344','tranthibich','$2y$10$JvlJpiWq7KynMo1bP47ptuuZUNRRKwNYJpbip17YDEzsMInyQGk3G','admin',1,NULL,NULL,'2026-09-29 17:02:28'),('ND004','PGS.TS. Lê Hoàng Nam','namlh@fbu.edu.vn','0903112233','lehoangnam','$2y$10$JvlJpiWq7KynMo1bP47ptuuZUNRRKwNYJpbip17YDEzsMInyQGk3G','user',1,NULL,'2026-09-30 15:16:26','2026-09-29 17:02:28'),('ND005','ThS. Phạm Thu Trang','trangpt@fbu.edu.vn','0978998877','phamthutrang','$2y$10$JvlJpiWq7KynMo1bP47ptuuZUNRRKwNYJpbip17YDEzsMInyQGk3G','user',1,NULL,NULL,'2026-09-29 17:02:28'),('ND006','KS. Vũ Đình Trọng','trongvd@fbu.edu.vn','0934556677','vudinhtrong','$2y$10$JvlJpiWq7KynMo1bP47ptuuZUNRRKwNYJpbip17YDEzsMInyQGk3G','user',1,NULL,NULL,'2026-09-29 17:02:28'),('ND007','ThS. Hoàng Minh Đức','duchm@fbu.edu.vn','0945667788','hoangminhduc','$2y$10$JvlJpiWq7KynMo1bP47ptuuZUNRRKwNYJpbip17YDEzsMInyQGk3G','user',0,NULL,NULL,'2026-09-29 17:02:28'),('ND008','Người dùng thử nghiệm','user@fbu.edu.vn','0966889900','user','$2y$10$JvlJpiWq7KynMo1bP47ptuuZUNRRKwNYJpbip17YDEzsMInyQGk3G','user',1,NULL,'2026-10-03 09:43:31','2026-09-29 17:02:28');
/*!40000 ALTER TABLE `nguoidung` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `remember_tokens`
--

DROP TABLE IF EXISTS `remember_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `remember_tokens` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `MaNguoiDung` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ma_token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `het_han` datetime NOT NULL,
  `ngay_tao` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `ma_token` (`ma_token`),
  KEY `fk_remember_tokens_nguoi_dung` (`MaNguoiDung`),
  CONSTRAINT `fk_remember_tokens_nguoi_dung` FOREIGN KEY (`MaNguoiDung`) REFERENCES `nguoidung` (`MaNguoiDung`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `remember_tokens`
--

LOCK TABLES `remember_tokens` WRITE;
/*!40000 ALTER TABLE `remember_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `remember_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tieuchi`
--

DROP TABLE IF EXISTS `tieuchi`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tieuchi` (
  `MaTieuChi` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `TenTieuChi` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `NoiDung` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ThuTu` int(11) NOT NULL DEFAULT 0,
  `MaTieuChuan` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `TrangThai` tinyint(1) NOT NULL DEFAULT 1,
  `NgayTao` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`MaTieuChi`),
  KEY `fk_tieu_chi_tieu_chuan` (`MaTieuChuan`),
  CONSTRAINT `fk_tieu_chi_tieu_chuan` FOREIGN KEY (`MaTieuChuan`) REFERENCES `tieuchuan` (`MaTieuChuan`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tieuchi`
--

LOCK TABLES `tieuchi` WRITE;
/*!40000 ALTER TABLE `tieuchi` DISABLE KEYS */;
/*!40000 ALTER TABLE `tieuchi` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tieuchuan`
--

DROP TABLE IF EXISTS `tieuchuan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tieuchuan` (
  `MaTieuChuan` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `TenTieuChuan` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `MoTa` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ThuTu` int(11) NOT NULL DEFAULT 0,
  `MaBoTieuChuan` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `TrangThai` tinyint(1) NOT NULL DEFAULT 1,
  `NgayTao` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`MaTieuChuan`),
  KEY `fk_tieu_chuan_bo` (`MaBoTieuChuan`),
  CONSTRAINT `fk_tieu_chuan_bo` FOREIGN KEY (`MaBoTieuChuan`) REFERENCES `botieuchuan` (`MaBoTieuChuan`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tieuchuan`
--

LOCK TABLES `tieuchuan` WRITE;
/*!40000 ALTER TABLE `tieuchuan` DISABLE KEYS */;
/*!40000 ALTER TABLE `tieuchuan` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-03 10:15:49
