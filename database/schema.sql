CREATE DATABASE IF NOT EXISTS kiemdinh_cntt
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE kiemdinh_cntt;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS download_logs;
DROP TABLE IF EXISTS audit_logs;
DROP TABLE IF EXISTS remember_tokens;
DROP TABLE IF EXISTS MinhChung;
DROP TABLE IF EXISTS LoaiMinhChung;
DROP TABLE IF EXISTS TieuChi;
DROP TABLE IF EXISTS TieuChuan;
DROP TABLE IF EXISTS BoTieuChuan;
DROP TABLE IF EXISTS NguoiDung;

-- 1. Table NguoiDung
CREATE TABLE NguoiDung (
    MaNguoiDung INT AUTO_INCREMENT PRIMARY KEY,
    HoTen VARCHAR(150) NOT NULL,
    DonViCongTac VARCHAR(255) NULL,
    Email VARCHAR(150) NOT NULL UNIQUE,
    SoDienThoai VARCHAR(30) NULL,
    TenDangNhap VARCHAR(80) NOT NULL UNIQUE,
    MatKhau VARCHAR(255) NOT NULL,
    VaiTro ENUM('admin', 'user') NOT NULL DEFAULT 'user',
    TrangThai TINYINT(1) NOT NULL DEFAULT 1,
    DuongDanAnhDaiDien VARCHAR(500) NULL,
    DangNhapCuoi DATETIME NULL,
    NgayTao TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Table remember_tokens
CREATE TABLE remember_tokens (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    MaNguoiDung INT NOT NULL,
    ma_token VARCHAR(255) NOT NULL UNIQUE,
    het_han DATETIME NOT NULL,
    ngay_tao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_remember_tokens_nguoi_dung FOREIGN KEY (MaNguoiDung) REFERENCES NguoiDung(MaNguoiDung) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 2. Table BoTieuChuan
CREATE TABLE BoTieuChuan (
    MaBoTieuChuan VARCHAR(50) NOT NULL PRIMARY KEY,
    TenBoTieuChuan VARCHAR(255) NOT NULL,
    ThongTu VARCHAR(255) NULL,
    NgayBanHanh DATE NULL,
    MoTa TEXT NULL,
    TrangThai TINYINT(1) NOT NULL DEFAULT 1,
    NgayTao TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 3. Table TieuChuan
CREATE TABLE TieuChuan (
    MaTieuChuan VARCHAR(50) NOT NULL PRIMARY KEY,
    TenTieuChuan VARCHAR(255) NOT NULL,
    MoTa TEXT NULL,
    ThuTu INT NOT NULL DEFAULT 0,
    MaBoTieuChuan VARCHAR(50) NOT NULL,
    TrangThai TINYINT(1) NOT NULL DEFAULT 1,
    NgayTao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_tieu_chuan_bo FOREIGN KEY (MaBoTieuChuan) REFERENCES BoTieuChuan(MaBoTieuChuan) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 4. Table TieuChi
CREATE TABLE TieuChi (
    MaTieuChi INT AUTO_INCREMENT PRIMARY KEY,
    TenTieuChi VARCHAR(255) NOT NULL,
    NoiDung TEXT NULL,
    ThuTu INT NOT NULL DEFAULT 0,
    MaTieuChuan VARCHAR(50) NOT NULL,
    TrangThai TINYINT(1) NOT NULL DEFAULT 1,
    NgayTao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_tieu_chi_chuan FOREIGN KEY (MaTieuChuan) REFERENCES TieuChuan(MaTieuChuan) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 5. Table LoaiMinhChung
CREATE TABLE LoaiMinhChung (
    MaLoai INT AUTO_INCREMENT PRIMARY KEY,
    TenLoai VARCHAR(255) NOT NULL,
    MoTa TEXT NULL,
    NgayTao TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 6. Table MinhChung
CREATE TABLE MinhChung (
    MaMinhChung INT AUTO_INCREMENT PRIMARY KEY,
    TenMinhChung VARCHAR(255) NOT NULL,
    MoTa TEXT NULL,
    TepTin VARCHAR(500) NULL,
    NamHoc VARCHAR(50) NULL,
    NgayCapNhat DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    TrangThai TINYINT(1) NOT NULL DEFAULT 1,
    MaLoai INT NULL,
    MaTieuChi INT NOT NULL,
    MaNguoiDung INT NULL,
    NgayTao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_minh_chung_loai FOREIGN KEY (MaLoai) REFERENCES LoaiMinhChung(MaLoai) ON DELETE SET NULL,
    CONSTRAINT fk_minh_chung_tieu_chi FOREIGN KEY (MaTieuChi) REFERENCES TieuChi(MaTieuChi) ON DELETE CASCADE,
    CONSTRAINT fk_minh_chung_nguoi_dung FOREIGN KEY (MaNguoiDung) REFERENCES NguoiDung(MaNguoiDung) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE audit_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    MaNguoiDung INT NULL,
    hanh_dong VARCHAR(100) NOT NULL,
    phan_he VARCHAR(100) NOT NULL,
    ten_ban_ghi VARCHAR(255) NULL,
    id_ban_ghi INT NULL,
    gia_tri_cu JSON NULL,
    gia_tri_moi JSON NULL,
    dia_chi_ip VARCHAR(45) NULL,
    ngay_tao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_audit_logs_nguoi_dung FOREIGN KEY (MaNguoiDung) REFERENCES NguoiDung(MaNguoiDung) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE download_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    MaNguoiDung VARCHAR(50) NULL,
    MaMinhChung VARCHAR(50) NOT NULL,
    ngay_tai TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    dia_chi_ip VARCHAR(45) NULL,
    CONSTRAINT fk_download_logs_nguoi_dung FOREIGN KEY (MaNguoiDung) REFERENCES NguoiDung(MaNguoiDung) ON DELETE SET NULL,
    CONSTRAINT fk_download_logs_minh_chung FOREIGN KEY (MaMinhChung) REFERENCES MinhChung(MaMinhChung) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT INTO NguoiDung (HoTen, DonViCongTac, Email, SoDienThoai, TenDangNhap, MatKhau, VaiTro, TrangThai) VALUES
('Quản trị viên', 'Khoa Công nghệ thông tin', 'admin@fbu.edu.vn', '0912345678', 'admin', '$2y$10$JvlJpiWq7KynMo1bP47ptuuZUNRRKwNYJpbip17YDEzsMInyQGk3G', 'admin', 1),
('Nguyễn Văn A', 'Phòng Đảm bảo chất lượng', 'user01@fbu.edu.vn', '0987654321', 'kiemdinhtt', '$2y$10$JvlJpiWq7KynMo1bP47ptuuZUNRRKwNYJpbip17YDEzsMInyQGk3G', 'user', 1),
('Trần Thị B', 'Bộ môn Kỹ thuật phần mềm', 'user02@fbu.edu.vn', '0911223344', 'viewer01', '$2y$10$JvlJpiWq7KynMo1bP47ptuuZUNRRKwNYJpbip17YDEzsMInyQGk3G', 'user', 1);

INSERT INTO BoTieuChuan (TenBoTieuChuan, ThongTu, NgayBanHanh, MoTa, TrangThai) VALUES
('Bộ tiêu chuẩn đánh giá chất lượng chương trình đào tạo', 'Thông tư 04/2016/TT-BGDĐT', '2025-01-15', 'Bộ tiêu chuẩn dùng cho cơ sở dữ liệu minh chứng phục vụ kiểm định CTĐT', 1);

INSERT INTO TieuChuan (TenTieuChuan, MoTa, ThuTu, MaBoTieuChuan, TrangThai) VALUES
('Mục tiêu và chuẩn đầu ra của chương trình đào tạo', 'Quản lý minh chứng liên quan mục tiêu và chuẩn đầu ra', 1, 1, 1),
('Bản mô tả chương trình đào tạo', 'Quản lý bản mô tả chương trình đào tạo', 2, 1, 1),
('Cấu trúc và nội dung chương trình dạy học', 'Quản lý cấu trúc và nội dung chương trình dạy học', 3, 1, 1),
('Phương pháp tiếp cận trong dạy và học', 'Quản lý minh chứng về phương pháp dạy học', 4, 1, 1),
('Đánh giá kết quả học tập của người học', 'Quản lý minh chứng về đánh giá kết quả học tập', 5, 1, 1);

INSERT INTO TieuChi (TenTieuChi, NoiDung, ThuTu, MaTieuChuan, TrangThai) VALUES
('Mục tiêu của CTĐT được xác định rõ ràng', 'Mục tiêu CTĐT phù hợp sứ mạng và nhu cầu xã hội', 1, 1, 1),
('Chuẩn đầu ra phản ánh yêu cầu của các bên liên quan', 'Chuẩn đầu ra được xây dựng và rà soát định kỳ', 2, 1, 1),
('Bản mô tả CTĐT đầy đủ thông tin cần thiết', 'Bản mô tả nêu rõ mục tiêu, CĐR, cấu trúc và học phần', 1, 2, 1),
('Nội dung học phần cập nhật theo định hướng nghề nghiệp', 'Đề cương học phần được cập nhật và phê duyệt', 2, 3, 1),
('Hoạt động dạy học thúc đẩy năng lực tự học', 'Hoạt động dạy học có định hướng phát triển năng lực', 1, 4, 1),
('Quy trình đánh giá kết quả học tập được công bố', 'Quy trình và ma trận đánh giá được công khai', 3, 5, 1);

INSERT INTO LoaiMinhChung (TenLoai, MoTa) VALUES
('Minh chứng chính', 'Các văn bản, quyết định chính thức'),
('Minh chứng bổ sung', 'Các hồ sơ, phụ lục đính kèm bổ sung'),
('Minh chứng khảo sát', 'Phiếu thu thập ý kiến các bên liên quan');

INSERT INTO MinhChung (TenMinhChung, MoTa, TepTin, NamHoc, TrangThai, MaLoai, MaTieuChi, MaNguoiDung) VALUES
('Quyết định ban hành mục tiêu và chuẩn đầu ra ngành CNTT', 'Quyết định ban hành mục tiêu và chuẩn đầu ra của CTĐT ngành CNTT', 'uploads/evidences/MC_01_01_01.pdf', '2025-2026', 1, 1, 1, 1),
('Bản mô tả chương trình đào tạo ngành CNTT', 'Bản mô tả chương trình đào tạo phục vụ kiểm định', 'uploads/evidences/MC_02_01_03.docx', '2025-2026', 1, 1, 3, 2),
('Đề cương chi tiết các học phần chuyên ngành', 'Tập hợp đề cương học phần chuyên ngành CNTT', 'uploads/evidences/MC_03_02_04.zip', '2024-2025', 1, 2, 4, 1),
('Kế hoạch đổi mới phương pháp dạy học', 'Kế hoạch cải tiến phương pháp giảng dạy trong CTĐT', 'uploads/evidences/MC_04_01_02.pdf', '2025-2026', 1, 1, 5, 1),
('Quy chế đánh giá học phần và ma trận điểm', 'Quy chế đánh giá, rubrics và ma trận điểm học phần', 'uploads/evidences/MC_05_03_05.xlsx', '2025-2026', 1, 1, 6, 2);

SET FOREIGN_KEY_CHECKS = 1;
