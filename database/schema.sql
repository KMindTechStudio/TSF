CREATE DATABASE IF NOT EXISTS kiemdinh_cntt
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE kiemdinh_cntt;

SET NAMES utf8mb4;
SET time_zone = '+07:00';
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS download_logs;
DROP TABLE IF EXISTS audit_logs;
DROP TABLE IF EXISTS remember_tokens;
DROP TABLE IF EXISTS MinhChung;
DROP TABLE IF EXISTS TieuChi;
DROP TABLE IF EXISTS TieuChuan;
DROP TABLE IF EXISTS BoTieuChuan;
DROP TABLE IF EXISTS NguoiDung;
DROP TABLE IF EXISTS loaiminhchung;
DROP TABLE IF EXISTS don_vi;

-- 1. Bảng NguoiDung (Quản lý Người dùng & Quản trị viên)
CREATE TABLE NguoiDung (
    MaNguoiDung VARCHAR(50) NOT NULL PRIMARY KEY,
    HoTen VARCHAR(150) NOT NULL,
    Email VARCHAR(150) NOT NULL UNIQUE,
    SoDienThoai VARCHAR(30) NULL,
    TenDangNhap VARCHAR(80) NOT NULL UNIQUE,
    MatKhau VARCHAR(255) NOT NULL,
    VaiTro ENUM('admin', 'user') NOT NULL DEFAULT 'user',
    TrangThai TINYINT(1) NOT NULL DEFAULT 1,
    DuongDanAnhDaiDien VARCHAR(500) NULL,
    DangNhapCuoi DATETIME NULL,
    NgayTao TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Bảng remember_tokens (Ghi nhớ phiên đăng nhập)
CREATE TABLE remember_tokens (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    MaNguoiDung VARCHAR(50) NOT NULL,
    ma_token VARCHAR(255) NOT NULL UNIQUE,
    het_han DATETIME NOT NULL,
    ngay_tao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_remember_tokens_nguoi_dung FOREIGN KEY (MaNguoiDung) REFERENCES NguoiDung(MaNguoiDung) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Bảng BoTieuChuan (Quản lý Bộ Tiêu chuẩn kiểm định)
CREATE TABLE BoTieuChuan (
    MaBoTieuChuan VARCHAR(50) NOT NULL PRIMARY KEY,
    TenBoTieuChuan VARCHAR(255) NOT NULL,
    ThongTu VARCHAR(255) NULL,
    NgayBanHanh DATE NULL,
    MoTa TEXT NULL,
    TrangThai TINYINT(1) NOT NULL DEFAULT 1,
    NgayTao TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Bảng TieuChuan (Quản lý Tiêu chuẩn thuộc Bộ tiêu chuẩn)
CREATE TABLE TieuChuan (
    MaTieuChuan VARCHAR(50) NOT NULL PRIMARY KEY,
    TenTieuChuan VARCHAR(255) NOT NULL,
    MoTa TEXT NULL,
    ThuTu INT NOT NULL DEFAULT 0,
    MaBoTieuChuan VARCHAR(50) NULL,
    TrangThai TINYINT(1) NOT NULL DEFAULT 1,
    NgayTao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_tieu_chuan_bo FOREIGN KEY (MaBoTieuChuan) REFERENCES BoTieuChuan(MaBoTieuChuan) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Bảng TieuChi (Quản lý Tiêu chí thuộc Tiêu chuẩn)
CREATE TABLE TieuChi (
    MaTieuChi VARCHAR(50) NOT NULL PRIMARY KEY,
    TenTieuChi VARCHAR(255) NOT NULL,
    NoiDung TEXT NULL,
    ThuTu INT NOT NULL DEFAULT 0,
    MaTieuChuan VARCHAR(50) NULL,
    TrangThai TINYINT(1) NOT NULL DEFAULT 1,
    NgayTao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_tieu_chi_tieu_chuan FOREIGN KEY (MaTieuChuan) REFERENCES TieuChuan(MaTieuChuan) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Bảng MinhChung (Quản lý Hồ sơ Minh chứng kiểm định)
CREATE TABLE MinhChung (
    MaMinhChung VARCHAR(50) NOT NULL PRIMARY KEY,
    TenMinhChung VARCHAR(255) NOT NULL,
    MoTa TEXT NULL,
    TepTin VARCHAR(500) NULL,
    NamHoc VARCHAR(50) NULL,
    TrangThai TINYINT(1) NOT NULL DEFAULT 1,
    MaTieuChi VARCHAR(50) NULL,
    MaBoTieuChuan VARCHAR(50) NULL,
    MaNguoiDung VARCHAR(50) NULL,
    NgayCapNhat DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    NgayTao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_minh_chung_tieu_chi FOREIGN KEY (MaTieuChi) REFERENCES TieuChi(MaTieuChi) ON DELETE SET NULL,
    CONSTRAINT fk_minh_chung_bo FOREIGN KEY (MaBoTieuChuan) REFERENCES BoTieuChuan(MaBoTieuChuan) ON DELETE SET NULL,
    CONSTRAINT fk_minh_chung_nguoi_dung FOREIGN KEY (MaNguoiDung) REFERENCES NguoiDung(MaNguoiDung) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Bảng audit_logs (Nhật ký hoạt động hệ thống)
CREATE TABLE audit_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    MaNguoiDung VARCHAR(50) NULL,
    hanh_dong VARCHAR(100) NOT NULL,
    phan_he VARCHAR(100) NOT NULL,
    ten_ban_ghi VARCHAR(255) NULL,
    id_ban_ghi VARCHAR(50) NULL,
    gia_tri_cu JSON NULL,
    gia_tri_moi JSON NULL,
    dia_chi_ip VARCHAR(45) NULL,
    ngay_tao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_audit_logs_nguoi_dung FOREIGN KEY (MaNguoiDung) REFERENCES NguoiDung(MaNguoiDung) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Bảng download_logs (Nhật ký tải minh chứng)
CREATE TABLE download_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    MaNguoiDung VARCHAR(50) NULL,
    MaMinhChung VARCHAR(50) NOT NULL,
    ngay_tai TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    dia_chi_ip VARCHAR(45) NULL,
    CONSTRAINT fk_download_logs_nguoi_dung FOREIGN KEY (MaNguoiDung) REFERENCES NguoiDung(MaNguoiDung) ON DELETE SET NULL,
    CONSTRAINT fk_download_logs_minh_chung FOREIGN KEY (MaMinhChung) REFERENCES MinhChung(MaMinhChung) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Khởi tạo danh sách tài khoản cơ sở (Mật khẩu mặc định: 123456)
INSERT INTO NguoiDung (MaNguoiDung, HoTen, Email, SoDienThoai, TenDangNhap, MatKhau, VaiTro, TrangThai) VALUES
('ND001', 'Quản trị viên', 'admin@fbu.edu.vn', '0912345678', 'admin', '$2y$10$JvlJpiWq7KynMo1bP47ptuuZUNRRKwNYJpbip17YDEzsMInyQGk3G', 'admin', 1),
('ND002', 'ThS. Nguyễn Văn An', 'annv@fbu.edu.vn', '0987654321', 'nguyenvanan', '$2y$10$JvlJpiWq7KynMo1bP47ptuuZUNRRKwNYJpbip17YDEzsMInyQGk3G', 'user', 1),
('ND003', 'TS. Trần Thị Bích', 'bich.tt@fbu.edu.vn', '0911223344', 'tranthibich', '$2y$10$JvlJpiWq7KynMo1bP47ptuuZUNRRKwNYJpbip17YDEzsMInyQGk3G', 'admin', 1),
('ND004', 'PGS.TS. Lê Hoàng Nam', 'namlh@fbu.edu.vn', '0903112233', 'lehoangnam', '$2y$10$JvlJpiWq7KynMo1bP47ptuuZUNRRKwNYJpbip17YDEzsMInyQGk3G', 'user', 1),
('ND005', 'ThS. Phạm Thu Trang', 'trangpt@fbu.edu.vn', '0978998877', 'phamthutrang', '$2y$10$JvlJpiWq7KynMo1bP47ptuuZUNRRKwNYJpbip17YDEzsMInyQGk3G', 'user', 1),
('ND006', 'KS. Vũ Đình Trọng', 'trongvd@fbu.edu.vn', '0934556677', 'vudinhtrong', '$2y$10$JvlJpiWq7KynMo1bP47ptuuZUNRRKwNYJpbip17YDEzsMInyQGk3G', 'user', 1),
('ND007', 'ThS. Hoàng Minh Đức', 'duchm@fbu.edu.vn', '0945667788', 'hoangminhduc', '$2y$10$JvlJpiWq7KynMo1bP47ptuuZUNRRKwNYJpbip17YDEzsMInyQGk3G', 'user', 0),
('ND008', 'Người dùng thử nghiệm', 'user@fbu.edu.vn', '0966889900', 'user', '$2y$10$JvlJpiWq7KynMo1bP47ptuuZUNRRKwNYJpbip17YDEzsMInyQGk3G', 'user', 1);

SET FOREIGN_KEY_CHECKS = 1;
