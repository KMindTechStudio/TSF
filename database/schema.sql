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
    TepTinPDF VARCHAR(500) NULL,
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
    NgayBanHanh DATE NULL,
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

-- Khởi tạo 8 Bộ tiêu chuẩn
INSERT INTO BoTieuChuan (MaBoTieuChuan, TenBoTieuChuan, ThongTu, NgayBanHanh, MoTa, TepTinPDF, TrangThai) VALUES
('BTC01', 'Bộ tiêu chuẩn đánh giá chất lượng chương trình đào tạo trình độ đại học', 'Thông tư 04/2016/TT-BGDĐT', '2016-03-14', 'Quy định về tiêu chuẩn đánh giá chất lượng chương trình đào tạo các trình độ của giáo dục đại học (11 tiêu chuẩn, 50 tiêu chí).', 'uploads/standards/thong_tu_04_2016_tt_bgddt.pdf', 1),
('BTC02', 'Bộ tiêu chuẩn kiểm định chất lượng cơ sở giáo dục đại học', 'Thông tư 12/2017/TT-BGDĐT', '2017-05-19', 'Quy định về kiểm định chất lượng cơ sở giáo dục đại học gồm 25 tiêu chuẩn và 111 tiêu chí.', 'uploads/standards/thong_tu_12_2017_tt_bgddt.pdf', 1),
('BTC03', 'Bộ tiêu chuẩn đánh giá chất lượng chương trình đào tạo ngành CNTT (AUN-QA 4.0)', 'Chuẩn kiểm định AUN-QA v4.0', '2020-08-15', 'Bộ tiêu chuẩn bảo đảm chất lượng mạng lưới các trường đại học Đông Nam Á phiên bản 4.0.', 'uploads/standards/aun_qa_v4_standard_guide.pdf', 1),
('BTC04', 'Bộ tiêu chuẩn kiểm định chất lượng CTĐT ngành Kỹ thuật và Công nghệ (ABET)', 'Thông tư 38/2013/TT-BGDĐT', '2013-11-29', 'Quy định về quy trình và chu kỳ kiểm định chất lượng chương trình đào tạo các trường đại học theo chuẩn ABET.', 'uploads/standards/thong_tu_04_2016_tt_bgddt.pdf', 1),
('BTC05', 'Bộ tiêu chuẩn kiểm định chuẩn đầu ra ngành Kỹ thuật phần mềm', 'Thông tư 17/2021/TT-BGDĐT', '2021-06-22', 'Quy định chuẩn chương trình đào tạo các ngành kỹ thuật và công nghệ thông tin.', 'uploads/standards/thong_tu_12_2017_tt_bgddt.pdf', 1),
('BTC06', 'Bộ tiêu chuẩn đánh giá chất lượng CTĐT Thạc sĩ ngành CNTT', 'Thông tư 18/2021/TT-BGDĐT', '2021-06-28', 'Quy chế tuyển sinh và đào tạo trình độ thạc sĩ ngành Công nghệ thông tin.', 'uploads/standards/aun_qa_v4_standard_guide.pdf', 1),
('BTC07', 'Bộ tiêu chuẩn kiểm định CTĐT ngành Trí tuệ nhân tạo và Khoa học dữ liệu', 'Chuẩn ABET CAC 2024', '2024-01-10', 'Chuẩn kiểm định quốc tế của Ủy ban Kiểm định Máy tính (CAC) thuộc ABET cho các CTĐT AI và Data Science.', 'uploads/standards/aun_qa_v4_standard_guide.pdf', 1),
('BTC08', 'Bộ tiêu chuẩn đánh giá năng lực nghề nghiệp CNTT theo chuẩn ITSS Nhật Bản', 'Quyết định 78/QĐ-BGDĐT', '2022-03-15', 'Khung trình độ và chuẩn kỹ năng CNTT tương thích Hệ thống Chuẩn kỹ năng Công nghệ thông tin Nhật Bản.', 'uploads/standards/thong_tu_04_2016_tt_bgddt.pdf', 1);

-- Khởi tạo Tiêu chuẩn
INSERT INTO TieuChuan (MaTieuChuan, TenTieuChuan, MoTa, ThuTu, MaBoTieuChuan, TrangThai) VALUES
('TC_ABET01', 'Criterion 1: Students Performance & Monitoring', 'Student admission, advising, and career guidance processes', 1, 'BTC04', 1),
('TC_ABET02', 'Criterion 2: Program Educational Objectives', 'Documented and published program educational objectives', 2, 'BTC04', 1),
('TC_ABET03', 'Criterion 3: Student Outcomes', 'Measurement and evaluation of student technical and professional competencies', 3, 'BTC04', 1),
('TC_ABET04', 'Criterion 4: Continuous Improvement', 'Documented process of using assessment results to improve curriculum and teaching', 4, 'BTC04', 1),
('TC_AUN01', 'Criterion 1: Expected Learning Outcomes', 'Formulation, alignment, and communication of expected learning outcomes', 1, 'BTC03', 1),
('TC_AUN02', 'Criterion 2: Programme Structure and Content', 'Curriculum design, course alignment, and academic progression', 2, 'BTC03', 1),
('TC_AUN03', 'Criterion 3: Teaching and Learning Approach', 'Constructive alignment, active learning, and student-centred approach', 3, 'BTC03', 1),
('TC_AUN04', 'Criterion 4: Student Assessment', 'Assessment rubrics, constructive alignment, appeals process and academic integrity', 4, 'BTC03', 1),
('TC_AUN05', 'Criterion 5: Academic Staff Quality', 'Staff recruitment, retention, competencies development and performance evaluation', 5, 'BTC03', 1),
('TC01', 'Tiêu chuẩn 1: Mục tiêu và chuẩn đầu ra của chương trình đào tạo', 'Mục tiêu và CĐR được xác định rõ ràng, phù hợp sứ mạng nhà trường', 1, 'BTC01', 1),
('TC02', 'Tiêu chuẩn 2: Bản mô tả chương trình đào tạo', 'Bản mô tả CTĐT và đề cương chi tiết học phần đầy đủ, công khai', 2, 'BTC01', 1),
('TC03', 'Tiêu chuẩn 3: Cấu trúc và nội dung chương trình dạy học', 'Cấu trúc khối kiến thức logic, cân đối lý thuyết và thực hành', 3, 'BTC01', 1),
('TC04', 'Tiêu chuẩn 4: Phương pháp tiếp cận trong dạy và học', 'Áp dụng phương pháp dạy học tích cực, lấy người học làm trung tâm', 4, 'BTC01', 1),
('TC05', 'Tiêu chuẩn 5: Đánh giá kết quả học tập của người học', 'Phương pháp đánh giá đa dạng, đảm bảo tính khách quan và đo lường CĐR', 5, 'BTC01', 1),
('TC05_01', 'Tiêu chuẩn 1: Mục tiêu và Chuẩn đầu ra ngành KTPM', 'Chuẩn năng lực kỹ thuật phần mềm, thiết kế hệ thống và kiểm thử', 1, 'BTC05', 1),
('TC05_02', 'Tiêu chuẩn 2: Đội ngũ giảng viên hướng dẫn đồ án KTPM', 'Năng lực thực tế công nghiệp và hướng dẫn đồ án tốt nghiệp', 2, 'BTC05', 1),
('TC05_03', 'Tiêu chuẩn 3: Môi trường thực hành phần mềm và công cụ DevOps', 'Trang bị nền tảng CI/CD, máy chủ thử nghiệm đám mây và công cụ phát triển hiện đại', 3, 'BTC05', 1),
('TC05_04', 'Tiêu chuẩn 4: Chuẩn đầu ra ngoại ngữ và kỹ năng hội nhập', 'Chuẩn tiếng Anh chuyên ngành công nghệ thông tin và năng lực làm việc đa văn hóa', 4, 'BTC05', 1),
('TC06', 'Tiêu chuẩn 6: Đội ngũ giảng viên, nghiên cứu viên', 'Quy mô, cơ cấu và trình độ của giảng viên đáp ứng yêu cầu CTĐT', 6, 'BTC01', 1),
('TC06_01', 'Tiêu chuẩn 1: Tuyển sinh và định hướng đào tạo Thạc sĩ CNTT', 'Yêu cầu đầu vào và định hướng nghiên cứu chuyên sâu CNTT', 1, 'BTC06', 1),
('TC06_02', 'Tiêu chuẩn 2: Luận văn thạc sĩ và Nghiên cứu khoa học', 'Quy trình giao đề tài, thẩm định và đánh giá luận văn thạc sĩ', 2, 'BTC06', 1),
('TC06_03', 'Tiêu chuẩn 3: Giảng viên cơ hữu và Hội đồng bảo vệ luận văn', 'Hội đồng chấm luận văn thạc sĩ đúng chuyên ngành và quy trình phản biện nghiêm ngặt', 3, 'BTC06', 1),
('TC06_04', 'Tiêu chuẩn 4: Cơ sở dữ liệu khoa học số và Thư viện chuyên ngành', 'Hệ thống truy cập cơ sở dữ liệu số IEEE Xplore, ScienceDirect, Springer cho học viên cao học', 4, 'BTC06', 1),
('TC07', 'Tiêu chuẩn 7: Đội ngũ nhân viên', 'Đội ngũ chuyên viên và nhân viên hỗ trợ đầy đủ về số lượng và năng lực', 7, 'BTC01', 1),
('TC07_01', 'Tiêu chuẩn 1: Kiến thức cốt lõi AI & Data Science', 'Chương trình giảng dạy Machine Learning, Deep Learning, Big Data', 1, 'BTC07', 1),
('TC07_02', 'Tiêu chuẩn 2: Hạ tầng tính toán hiệu năng cao và Lab AI', 'Phòng thí nghiệm GPU Server phục vụ huấn luyện mô hình AI', 2, 'BTC07', 1),
('TC07_03', 'Tiêu chuẩn 3: Đạo đức AI và Bảo mật dữ liệu lớn', 'Nguyên tắc phát triển trí tuệ nhân tạo có trách nhiệm, liêm chính dữ liệu và an toàn thông tin', 3, 'BTC07', 1),
('TC07_04', 'Tiêu chuẩn 4: Dự án tốt nghiệp Capstone AI thực tế doanh nghiệp', 'Sinh viên giải quyết bài toán thị giác máy tính, xử lý ngôn ngữ tự nhiên từ doanh nghiệp', 4, 'BTC07', 1),
('TC08', 'Tiêu chuẩn 8: Người học và hoạt động hỗ trợ người học', 'Chính sách tuyển sinh, tư vấn học tập và hỗ trợ việc làm cho sinh viên', 8, 'BTC01', 1),
('TC08_01', 'Tiêu chuẩn 1: Chuẩn kỹ năng công nghệ thông tin ITSS Level 2-3', 'Khung năng lực lập trình, phân tích yêu cầu theo chuẩn Nhật Bản', 1, 'BTC08', 1),
('TC08_02', 'Tiêu chuẩn 2: Năng lực tiếng Nhật IT và quy trình phần mềm', 'Kỹ năng giao tiếp tiếng Nhật chuyên ngành IT và quy trình Scrum/Agile', 2, 'BTC08', 1),
('TC08_03', 'Tiêu chuẩn 3: Chuẩn kỹ sư ứng dụng AP (Applied IT Engineer)', 'Kiến thức chuyên sâu về thiết kế hệ thống, giải thuật tối ưu và cơ sở dữ liệu lớn', 3, 'BTC08', 1),
('TC08_04', 'Tiêu chuẩn 4: Văn hóa doanh nghiệp và phong cách làm việc BrSE', 'Kỹ năng làm việc vị trí Kỹ sư cầu nối (Bridge System Engineer), quy trình quản trị dự án', 4, 'BTC08', 1),
('TC09', 'Tiêu chuẩn 9: Cơ sở vật chất và trang thiết bị', 'Phòng học, phòng thực hành máy tính và thư viện đáp ứng nhu cầu', 9, 'BTC01', 1),
('TC10', 'Tiêu chuẩn 10: Nâng cao chất lượng', 'Quy trình rà soát, đánh giá định kỳ và cải tiến chất lượng CTĐT', 10, 'BTC01', 1),
('TC11', 'Tiêu chuẩn 11: Kết quả đầu ra', 'Tỷ lệ tốt nghiệp, việc làm đúng ngành và sự hài lòng của doanh nghiệp', 11, 'BTC01', 1),
('TC12', 'Tiêu chuẩn 12: Đóng góp cho cộng đồng và khởi nghiệp sáng tạo', 'Các hoạt động phục vụ cộng đồng, chuyển giao công nghệ và hỗ trợ sinh viên khởi nghiệp', 12, 'BTC01', 1),
('TC12_01', 'Tiêu chuẩn 1: Tầm nhìn, sứ mạng và văn hóa', 'Tầm nhìn, sứ mạng và giá trị cốt lõi của cơ sở giáo dục đại học', 1, 'BTC02', 1),
('TC12_02', 'Tiêu chuẩn 2: Quản trị và quản lý', 'Hệ thống quản trị, bộ máy tổ chức và quy chế hoạt động', 2, 'BTC02', 1),
('TC12_03', 'Tiêu chuẩn 3: Đào tạo và bảo đảm chất lượng', 'Hệ thống bảo đảm chất lượng bên trong và quản lý đào tạo', 3, 'BTC02', 1),
('TC12_04', 'Tiêu chuẩn 4: Cơ sở vật chất và tài chính', 'Nguồn lực tài chính bền vững và cơ sở vật chất đồng bộ', 4, 'BTC02', 1),
('TC12_05', 'Tiêu chuẩn 5: Đội ngũ giảng viên và cán bộ quản lý', 'Quy hoạch phát triển đội ngũ giảng viên, chính sách thu hút nhân tài và bồi dưỡng chuyên môn', 5, 'BTC02', 1),
('TC12_06', 'Tiêu chuẩn 6: Nghiên cứu khoa học và chuyển giao công nghệ', 'Chiến lược NCKH, các đề tài nghiên cứu cấp bộ, nhà nước và hợp tác quốc tế', 6, 'BTC02', 1);

-- Khởi tạo Tiêu chí
INSERT INTO TieuChi (MaTieuChi, TenTieuChi, NoiDung, ThuTu, MaTieuChuan, TrangThai) VALUES
('TChi_ABET01.1', 'Criterion 1.1: Quá trình theo dõi và hỗ trợ sinh viên', 'Quy trình tư vấn học vụ và theo dõi tiến độ học tập của sinh viên', 1, 'TC_ABET01', 1),
('TChi_ABET02.1', 'Criterion 2.1: Mục tiêu giáo dục chương trình đào tạo (PEOs)', 'Xác định các mục tiêu PEOs dựa trên nhu cầu của doanh nghiệp', 1, 'TC_ABET02', 1),
('TChi_ABET03.1', 'Criterion 3.1: Student Outcomes Assessment & Attainment', 'Systematic evaluation demonstrating graduates achieve all required student outcomes', 1, 'TC_ABET03', 1),
('TChi_ABET04.1', 'Criterion 4.1: Continuous Improvement Action Plans', 'Documented improvements in curriculum, laboratories, and instruction based on assessment feedback', 1, 'TC_ABET04', 1),
('TChi_AUN01.1', 'Criterion 1.1: ELOs formulation and alignment with university vision', 'Expected learning outcomes are clearly defined and aligned with institutional vision', 1, 'TC_AUN01', 1),
('TChi_AUN01.2', 'Criterion 1.2: Generic and programme-specific learning outcomes', 'ELOs encompass both generic soft skills and specific technical skills', 2, 'TC_AUN01', 1),
('TChi_AUN02.1', 'Criterion 2.1: Curriculum design and course mapping matrix', 'Curriculum matrix maps each course to corresponding programme learning outcomes', 1, 'TC_AUN02', 1),
('TChi_AUN03.1', 'Criterion 3.1: Constructive alignment in teaching and active learning', 'Teaching approaches promote active, collaborative and project-based learning', 1, 'TC_AUN03', 1),
('TChi_AUN04.1', 'Criterion 4.1: Constructive Alignment in Student Assessment', 'Formative and summative assessment methods measure the achievement of expected learning outcomes', 1, 'TC_AUN04', 1),
('TChi_AUN05.1', 'Criterion 5.1: Academic Staff Competencies and Development', 'Adequate academic staff with appropriate qualifications, continuous training and development', 1, 'TC_AUN05', 1),
('TChi01.1', 'Tiêu chí 1.1: Mục tiêu của CTĐT được xác định rõ ràng', 'Mục tiêu CTĐT phản ánh đúng định hướng của Nhà trường và nhu cầu xã hội', 1, 'TC01', 1),
('TChi01.2', 'Tiêu chí 1.2: Chuẩn đầu ra được xác định rõ ràng', 'Chuẩn đầu ra bao quát kiến thức, kỹ năng, mức độ tự chủ và trách nhiệm', 2, 'TC01', 1),
('TChi01.3', 'Tiêu chí 1.3: Chuẩn đầu ra được rà soát và công bố công khai', 'Định kỳ rà soát, lấy ý kiến các bên liên quan và công bố trên website', 3, 'TC01', 1),
('TChi02.1', 'Tiêu chí 2.1: Bản mô tả CTĐT đầy đủ thông tin', 'Bản mô tả cung cấp đầy đủ thông tin về mục tiêu, CĐR, cấu trúc chương trình', 1, 'TC02', 1),
('TChi02.2', 'Tiêu chí 2.2: Đề cương chi tiết học phần được công bố', 'Đề cương nêu rõ mục tiêu, chuẩn đầu ra học phần và phương pháp đánh giá', 2, 'TC02', 1),
('TChi03.1', 'Tiêu chí 3.1: CTĐT được thiết kế theo định hướng ứng dụng', 'Cấu trúc các học phần logic, tăng cường kỹ năng thực hành và đồ án', 1, 'TC03', 1),
('TChi03.2', 'Tiêu chí 3.2: Đóng góp của các khối kiến thức vào CĐR', 'Ma trận tương thích giữa học phần và chuẩn đầu ra CTĐT', 2, 'TC03', 1),
('TChi04.1', 'Tiêu chí 4.1: Phương pháp dạy học đa dạng', 'Kết hợp thuyết trình, thảo luận nhóm, giải quyết vấn đề và học qua dự án', 1, 'TC04', 1),
('TChi05_01.1', 'Tiêu chí 1.1: Chuẩn năng lực phát triển phần mềm', 'Sinh viên đạt chuẩn kiến trúc, kiểm thử và quản lý dự án phần mềm', 1, 'TC05_01', 1),
('TChi05_02.1', 'Tiêu chí 2.1: Giảng viên hướng dẫn đồ án thực tế doanh nghiệp', 'Giảng viên có kinh nghiệm thực chiến và chủ trì các dự án CNTT', 1, 'TC05_02', 1),
('TChi05_03.1', 'Tiêu chí 3.1: Hệ thống thực hành CI/CD và Cloud Computing', 'Sinh viên được tiếp cận môi trường phát triển Docker, Kubernetes, AWS/Azure thực hành', 1, 'TC05_03', 1),
('TChi05_04.1', 'Tiêu chí 4.1: Chuẩn tiếng Anh giao tiếp kỹ thuật phần mềm', 'Yêu cầu đạt chuẩn TOEIC/IELTS và đọc hiểu tài liệu đặc tả kỹ thuật quốc tế', 1, 'TC05_04', 1),
('TChi05.1', 'Tiêu chí 5.1: Đánh giá quá trình và tổng kết', 'Đánh giá học phần theo trọng số điểm quá trình và điểm thi kết thúc học phần', 1, 'TC05', 1),
('TChi06_01.1', 'Tiêu chí 1.1: Tiêu chuẩn tuyển sinh thạc sĩ CNTT', 'Quy chế xét tuyển và yêu cầu văn bằng đại học đúng ngành', 1, 'TC06_01', 1),
('TChi06_02.1', 'Tiêu chí 2.1: Luận văn thạc sĩ và công bố khoa học', 'Yêu cầu công bố bài báo khoa học trên các tạp chí chuyên ngành uy tín', 1, 'TC06_02', 1),
('TChi06_03.1', 'Tiêu chí 3.1: Tiêu chuẩn người hướng dẫn khoa học luận văn thạc sĩ', 'Giảng viên hướng dẫn có các công trình nghiên cứu uy tín thuộc đúng hướng đề tài học viên', 1, 'TC06_03', 1),
('TChi06_04.1', 'Tiêu chí 4.1: Nguồn học liệu số và cơ sở dữ liệu nghiên cứu', 'Cung cấp tài khoản truy cập cơ sở dữ liệu trực tuyến phục vụ nghiên cứu luận văn cao học', 1, 'TC06_04', 1),
('TChi06.1', 'Tiêu chí 6.1: Đội ngũ giảng viên cơ hữu', 'Giảng viên có trình độ thạc sĩ, tiến sĩ đúng chuyên ngành CNTT', 1, 'TC06', 1),
('TChi07_01.1', 'Tiêu chí 1.1: Khối kiến thức Học máy và Khoa học dữ liệu', 'Chương trình bao quát toán giải tích, đại số tuyến tính và thuật toán AI', 1, 'TC07_01', 1),
('TChi07_02.1', 'Tiêu chí 2.1: Phòng thí nghiệm AI GPU Server', 'Trang bị máy chủ chuyên dụng tính toán song song CUDA phục vụ đào tạo', 1, 'TC07_02', 1),
('TChi07_03.1', 'Tiêu chí 3.1: Nguyên tắc đạo đức AI và bảo vệ quyền riêng tư dữ liệu', 'Quy chuẩn kiểm định thuật toán, giảm thiểu thiên vị (bias) và bảo đảm tính minh bạch mô hình', 1, 'TC07_03', 1),
('TChi07_04.1', 'Tiêu chí 4.1: Đồ án Capstone AI thực chiến cùng doanh nghiệp', 'Đồ án giải quyết bài toán thực tế như nhận diện khuôn mặt, chatbot thông minh, chẩn đoán y tế', 1, 'TC07_04', 1),
('TChi08_01.1', 'Tiêu chí 1.1: Chuẩn kỹ năng FE (Fundamental IT Engineer)', 'Chuẩn kiến thức phần cứng, mạng, thuật toán và bảo mật theo ITSS', 1, 'TC08_01', 1),
('TChi08_02.1', 'Tiêu chí 2.1: Năng lực tiếng Nhật IT và làm việc nhóm', 'Chuẩn tiếng Nhật chuyên ngành công nghệ thông tin và văn hóa doanh nghiệp', 1, 'TC08_02', 1),
('TChi08_03.1', 'Tiêu chí 3.1: Chuẩn kỹ năng AP (Applied Information Technology Engineer)', 'Năng lực thiết kế kiến trúc hệ thống, an ninh thông tin và tối ưu hiệu năng phần mềm', 1, 'TC08_03', 1),
('TChi08_04.1', 'Tiêu chí 4.1: Tiếng Nhật IT và quy trình dự án chuẩn Nhật Bản', 'Đạt chứng chỉ năng lực tiếng Nhật JLPT N3 trở lên và thành thạo quy trình giao việc B-spec', 1, 'TC08_04', 1),
('TChi09.1', 'Tiêu chí 9.1: Phòng thực hành máy tính và phòng lab', 'Hệ thống phòng máy tính có cấu hình cao, kết nối Internet tốc độ cao', 1, 'TC09', 1),
('TChi11.1', 'Tiêu chí 11.1: Tỷ lệ sinh viên có việc làm sau tốt nghiệp', 'Khảo sát tình hình việc làm của sinh viên tốt nghiệp trong vòng 12 tháng', 1, 'TC11', 1),
('TChi12_01.1', 'Tiêu chí 1.1: Sứ mạng và tầm nhìn nhà trường', 'Sứ mạng, tầm nhìn được định kỳ rà soát và công bố rộng rãi', 1, 'TC12_01', 1),
('TChi12_01.2', 'Tiêu chí 1.2: Văn hóa tổ chức và liêm chính học thuật', 'Xây dựng môi trường học thuật chuyên nghiệp và tôn trọng bản quyền', 2, 'TC12_01', 1),
('TChi12_02.1', 'Tiêu chí 2.1: Cơ cấu tổ chức và phân cấp quản lý', 'Quy chế tổ chức và hoạt động phân định rõ chức năng, nhiệm vụ', 1, 'TC12_02', 1),
('TChi12_03.1', 'Tiêu chí 3.1: Hệ thống bảo đảm chất lượng bên trong', 'Đơn vị chuyên trách ĐBCL hoạt động hiệu quả và có quy trình giám sát', 1, 'TC12_03', 1),
('TChi12_04.1', 'Tiêu chí 4.1: Quy hoạch và phát triển cơ sở vật chất', 'Cơ sở vật chất, giảng đường đáp ứng quy mô đào tạo của Nhà trường', 1, 'TC12_04', 1),
('TChi12_05.1', 'Tiêu chí 5.1: Quy hoạch và phát triển đội ngũ cán bộ, giảng viên', 'Tỷ lệ giảng viên có học vị tiến sĩ, chức danh giáo sư, phó giáo sư đạt chuẩn quốc gia', 1, 'TC12_05', 1),
('TChi12_06.1', 'Tiêu chí 6.1: Kết quả nghiên cứu khoa học và công bố quốc tế', 'Số lượng bài báo đăng tải trên tạp chí ISI/Scopus và đề tài khoa học được nghiệm thu', 1, 'TC12_06', 1),
('TChi12.1', 'Tiêu chí 12.1: Hoạt động phục vụ cộng đồng và phong trào đổi mới sáng tạo', 'Nhà trường và Khoa tổ chức các chương trình hỗ trợ cộng đồng, TechFest và cuộc thi sáng tạo', 1, 'TC12', 1);

-- Khởi tạo Minh chứng kiểm định
INSERT INTO MinhChung (MaMinhChung, TenMinhChung, NgayBanHanh, MoTa, TepTin, NamHoc, TrangThai, MaTieuChi, MaBoTieuChuan, MaNguoiDung) VALUES
('MC001', 'Quyết định ban hành Sứ mạng, Tầm nhìn Trường ĐH Tài chính - Ngân hàng Hà Nội', '2023-01-15', 'Quyết định công bố sứ mạng, tầm nhìn chiến lược phát triển Trường giai đoạn 2023-2030.', 'uploads/evidences/qd_ban_hanh_su_mang_fbu.pdf', '2023-2024', 1, 'TChi01.1', 'BTC01', 'ND001'),
('MC002', 'Bản Chuẩn đầu ra Chương trình đào tạo ngành Công nghệ thông tin', '2023-06-20', 'Bản mô tả chuẩn đầu ra trình độ cử nhân ngành Công nghệ thông tin (135 tín chỉ).', 'uploads/evidences/chuan_dau_ra_cntt_2022.pdf', '2023-2024', 1, 'TChi01.2', 'BTC01', 'ND002'),
('MC003', 'Biên bản hội thảo lấy ý kiến doanh nghiệp và chuyên gia về chuẩn đầu ra CTĐT CNTT', '2023-09-10', 'Biên bản tổng hợp ý kiến đóng góp của đại diện các doanh nghiệp công nghệ phần mềm.', 'uploads/evidences/bien_ban_hoi_thao_cdr.pdf', '2023-2024', 1, 'TChi01.3', 'BTC01', 'ND003'),
('MC004', 'Quyết định phê duyệt CTĐT ngành Công nghệ thông tin trình độ Đại học', '2024-03-05', 'Quyết định phê duyệt điều chỉnh chương trình đào tạo áp dụng từ khóa tuyển sinh 2024.', 'uploads/evidences/ctdt_cntt_fbu_135tc.pdf', '2024-2025', 1, 'TChi02.1', 'BTC01', 'ND001'),
('MC005', 'Tập Đề cương chi tiết học phần khối ngành Công nghệ thông tin', '2024-08-15', 'Đề cương chi tiết các học phần cơ sở ngành và chuyên ngành Kỹ thuật phần mềm, Mạng máy tính.', 'uploads/evidences/tap_de_cuong_chi_tiet_cntt.pdf', '2024-2025', 1, 'TChi02.2', 'BTC01', 'ND004'),
('MC006', 'Chiến lược phát triển Trường ĐH Tài chính - Ngân hàng Hà Nội giai đoạn 2021-2030', '2021-12-10', 'Nghị quyết và kế hoạch chiến lược phát triển toàn diện của Nhà trường.', 'uploads/evidences/qd_ban_hanh_su_mang_fbu.pdf', '2021-2022', 1, 'TChi12_01.1', 'BTC02', 'ND001'),
('MC007', 'Báo cáo tự đánh giá chất lượng cơ sở giáo dục đại học năm 2023', '2023-11-20', 'Báo cáo tự đánh giá nội bộ toàn diện theo 25 tiêu chuẩn kiểm định TT 12/2017.', 'uploads/evidences/ctdt_cntt_fbu_135tc.pdf', '2023-2024', 1, 'TChi12_03.1', 'BTC02', 'ND003'),
('MC008', 'Báo cáo kiểm kê tài sản và cơ sở vật chất phục vụ đào tạo năm học 2023-2024', '2024-05-18', 'Danh mục thống kê phòng học lý thuyết, giảng đường đa năng và trang thiết bị thực hành.', 'uploads/evidences/tap_de_cuong_chi_tiet_cntt.pdf', '2023-2024', 1, 'TChi12_04.1', 'BTC02', 'ND005'),
('MC009', 'AUN-QA Self-Assessment Report (SAR) - Computer Science Programme', '2022-10-15', 'Báo cáo tự đánh giá chất lượng chuẩn quốc tế AUN-QA v4.0 ngành CNTT.', 'uploads/evidences/chuan_dau_ra_cntt_2022.pdf', '2022-2023', 1, 'TChi_AUN01.1', 'BTC03', 'ND002'),
('MC010', 'Course Mapping Matrix & Learning Outcomes Alignment Table', '2023-04-12', 'Bảng ma trận tương thích giữa các học phần và chuẩn đầu ra chương trình AUN-QA.', 'uploads/evidences/bien_ban_hoi_thao_cdr.pdf', '2022-2023', 1, 'TChi_AUN02.1', 'BTC03', 'ND004'),
('MC011', 'Rubric đánh giá đồ án tốt nghiệp và kết quả học tập chuẩn AUN-QA', '2024-01-20', 'Bộ tiêu chí và thang đo Rubrics đánh giá năng lực thực hiện khóa luận tốt nghiệp.', 'uploads/evidences/tap_de_cuong_chi_tiet_cntt.pdf', '2023-2024', 1, 'TChi_AUN03.1', 'BTC03', 'ND001'),
('MC012', 'Báo cáo đánh giá mức độ đạt Chuẩn đầu ra ABET ngành Kỹ thuật phần mềm', '2023-12-05', 'Kết quả đo lường và đánh giá mức độ đạt chuẩn Student Outcomes (SOs 1-6) theo chuẩn ABET.', 'uploads/evidences/chuan_dau_ra_cntt_2022.pdf', '2023-2024', 1, 'TChi_ABET01.1', 'BTC04', 'ND002'),
('MC013', 'Kế hoạch đo lường và cải tiến liên tục (Continuous Improvement Plan)', '2024-04-10', 'Kế hoạch hành động và cải tiến định kỳ chất lượng giảng dạy và học tập.', 'uploads/evidences/bien_ban_hoi_thao_cdr.pdf', '2023-2024', 1, 'TChi_ABET02.1', 'BTC04', 'ND003'),
('MC014', 'Khung chương trình đào tạo chuẩn đầu ra Kỹ sư Phần mềm theo TT 17/2021', '2022-08-30', 'Khung chương trình chi tiết định hướng chuyên ngành Kỹ thuật phần mềm và hệ thống thông tin.', 'uploads/evidences/ctdt_cntt_fbu_135tc.pdf', '2022-2023', 1, 'TChi05_01.1', 'BTC05', 'ND001'),
('MC015', 'Thỏa thuận hợp tác đào tạo và thực tập doanh nghiệp CNTT (MOU)', '2024-02-15', 'Biên bản ghi nhớ hợp tác tiếp nhận sinh viên thực tập và tuyển dụng cùng các tập đoàn CNTT.', 'uploads/evidences/bien_ban_hoi_thao_cdr.pdf', '2023-2024', 1, 'TChi05_02.1', 'BTC05', 'ND005'),
('MC016', 'Quy định đào tạo trình độ Thạc sĩ ngành Khoa học máy tính & CNTT', '2022-09-12', 'Quy định về thời gian đào tạo, bảo vệ luận văn và tiêu chuẩn người hướng dẫn cao học.', 'uploads/evidences/qd_ban_hanh_su_mang_fbu.pdf', '2022-2023', 1, 'TChi06_01.1', 'BTC06', 'ND002'),
('MC017', 'Danh mục đề tài luận văn thạc sĩ và bài báo Scopus/ISI ngành CNTT', '2024-06-25', 'Tổng hợp danh sách các công trình công bố khoa học quốc tế của học viên cao học và giảng viên.', 'uploads/evidences/ctdt_cntt_fbu_135tc.pdf', '2023-2024', 1, 'TChi06_02.1', 'BTC06', 'ND003'),
('MC018', 'Đề án mở ngành đào tạo Trí tuệ nhân tạo và Khoa học dữ liệu', '2024-04-02', 'Hồ sơ đề án khả thi mở ngành đào tạo đại học Trí tuệ nhân tạo và Khoa học dữ liệu.', 'uploads/evidences/chuan_dau_ra_cntt_2022.pdf', '2023-2024', 1, 'TChi07_01.1', 'BTC07', 'ND001'),
('MC019', 'Biên bản nghiệm thu phòng lab AI GPU Server phục vụ nghiên cứu', '2024-07-18', 'Biên bản bàn giao và kiểm định cấu hình máy chủ GPU NVIDIA RTX phục vụ huấn luyện mô hình học sâu.', 'uploads/evidences/tap_de_cuong_chi_tiet_cntt.pdf', '2023-2024', 1, 'TChi07_02.1', 'BTC07', 'ND004'),
('MC020', 'Bảng đối sánh chuẩn đầu ra CTĐT CNTT với khung kỹ năng ITSS Nhật Bản', '2023-05-14', 'Phân tích ma trận tương thích giữa khối kiến thức chuyên ngành với bài thi FE/AP ITSS.', 'uploads/evidences/bien_ban_hoi_thao_cdr.pdf', '2022-2023', 1, 'TChi08_01.1', 'BTC08', 'ND002'),
('MC021', 'Kết quả thi chứng chỉ Kỹ sư CNTT cơ bản (FE) của sinh viên khóa 2020-2024', '2024-05-30', 'Danh sách sinh viên ngành CNTT đạt chứng chỉ FE do Cơ quan Xúc tiến CNTT Nhật Bản (IPA) cấp.', 'uploads/evidences/ctdt_cntt_fbu_135tc.pdf', '2023-2024', 1, 'TChi08_02.1', 'BTC08', 'ND005'),
('MC022', 'Báo cáo tổng kết hoạt động phục vụ cộng đồng và tập huấn tin học cho trường phổ thông', '2024-03-20', 'Chương trình tập huấn chuyển đổi số và lập trình Python miễn phí cho học sinh THPT trên địa bàn Hà Nội.', 'uploads/evidences/ctdt_cntt_fbu_135tc.pdf', '2023-2024', 1, 'TChi12.1', 'BTC01', 'ND002'),
('MC023', 'Quyết định ban hành Quy chế hỗ trợ sinh viên NCKH và Khởi nghiệp đổi mới sáng tạo', '2024-09-05', 'Quy định chính sách tài trợ kinh phí cho các đề tài nghiên cứu khoa học và dự án startup tiềm năng của sinh viên.', 'uploads/evidences/qd_ban_hanh_su_mang_fbu.pdf', '2024-2025', 1, 'TChi12.1', 'BTC01', 'ND001'),
('MC024', 'Quy hoạch phát triển đội ngũ giảng viên có trình độ Tiến sĩ giai đoạn 2021-2026', '2022-04-15', 'Kế hoạch đào tạo và chính sách đãi ngộ, thu hút chuyên gia đầu ngành trong lĩnh vực CNTT và Kinh tế số.', 'uploads/evidences/tap_de_cuong_chi_tiet_cntt.pdf', '2021-2022', 1, 'TChi12_05.1', 'BTC02', 'ND003'),
('MC025', 'Danh mục công trình Nghiên cứu khoa học và bài báo quốc tế của Trường năm 2023', '2023-12-28', 'Thống kê các bài báo thuộc danh mục ISI, Scopus và đề tài NCKH cấp Bộ của giảng viên Nhà trường.', 'uploads/evidences/bien_ban_hoi_thao_cdr.pdf', '2023-2024', 1, 'TChi12_06.1', 'BTC02', 'ND004'),
('MC026', 'AUN-QA Student Assessment Alignment Report & Rubrics Matrix', '2023-08-10', 'Báo cáo ma trận đánh giá kết quả học tập và thang đo chuẩn đầu ra tích hợp cho tất cả các môn học CNTT.', 'uploads/evidences/chuan_dau_ra_cntt_2022.pdf', '2023-2024', 1, 'TChi_AUN04.1', 'BTC03', 'ND002'),
('MC027', 'Faculty Competency Profile & Continuous Professional Development Plan (CPD)', '2024-02-22', 'Hồ sơ năng lực giảng viên và kế hoạch đào tạo bồi dưỡng phương pháp sư phạm hiện đại chuẩn AUN-QA.', 'uploads/evidences/ctdt_cntt_fbu_135tc.pdf', '2023-2024', 1, 'TChi_AUN05.1', 'BTC03', 'ND005'),
('MC028', 'Direct Assessment Results and Attainment Analysis of ABET Student Outcomes (1-6)', '2024-05-15', 'Tổng hợp điểm số và phân tích mức độ đạt chuẩn kỹ năng kỹ thuật, giải quyết vấn đề của sinh viên tốt nghiệp.', 'uploads/evidences/chuan_dau_ra_cntt_2022.pdf', '2023-2024', 1, 'TChi_ABET03.1', 'BTC04', 'ND003'),
('MC029', 'Annual ABET Program Continuous Improvement Actions & Curriculum Refinement', '2024-08-30', 'Biên bản họp Hội đồng khoa học và kế hoạch cập nhật học phần kiến trúc hệ thống và an ninh mạng.', 'uploads/evidences/bien_ban_hoi_thao_cdr.pdf', '2024-2025', 1, 'TChi_ABET04.1', 'BTC04', 'ND001'),
('MC030', 'Biên bản nghiệm thu phòng Lab thực hành Cloud Computing & Hệ thống CI/CD', '2023-10-18', 'Hệ thống hạ tầng máy chủ ảo hóa đám mây phục vụ môn học Kiến trúc phần mềm và Kiểm thử tự động.', 'uploads/evidences/tap_de_cuong_chi_tiet_cntt.pdf', '2023-2024', 1, 'TChi05_03.1', 'BTC05', 'ND004'),
('MC031', 'Bảng tổng hợp chứng chỉ Tiếng Anh quốc tế (TOEIC/IELTS) của sinh viên ngành KTPM', '2024-06-10', 'Danh sách kiểm tra điều kiện tốt nghiệp về năng lực ngoại ngữ chuyên ngành công nghệ phần mềm.', 'uploads/evidences/ctdt_cntt_fbu_135tc.pdf', '2023-2024', 1, 'TChi05_04.1', 'BTC05', 'ND005'),
('MC032', 'Quyết định thành lập Hội đồng đánh giá luận văn Thạc sĩ ngành CNTT đợt 1 năm 2024', '2024-04-12', 'Danh sách các giáo sư, tiến sĩ tham gia phản biện và chấm luận văn tốt nghiệp cao học.', 'uploads/evidences/qd_ban_hanh_su_mang_fbu.pdf', '2023-2024', 1, 'TChi06_03.1', 'BTC06', 'ND001'),
('MC033', 'Hợp đồng bản quyền cơ sở dữ liệu số IEEE Xplore và ScienceDirect cho sau đại học', '2024-01-10', 'Hồ sơ bản quyền thư viện điện tử cung cấp tài khoản truy cập hơn 5 triệu bài báo nghiên cứu quốc tế.', 'uploads/evidences/tap_de_cuong_chi_tiet_cntt.pdf', '2023-2024', 1, 'TChi06_04.1', 'BTC06', 'ND002'),
('MC034', 'Khung nguyên tắc đạo đức AI và Quy định bảo vệ an toàn dữ liệu trong nghiên cứu', '2024-03-15', 'Quy định về bảo mật dữ liệu người dùng, tính minh bạch mô hình và tránh thiên lệch thuật toán AI.', 'uploads/evidences/chuan_dau_ra_cntt_2022.pdf', '2023-2024', 1, 'TChi07_03.1', 'BTC07', 'ND003'),
('MC035', 'Tổng kết đánh giá đồ án Capstone AI ngành Trí tuệ nhân tạo và Khoa học dữ liệu', '2024-07-25', 'Báo cáo nghiệm thu sản phẩm ứng dụng AI: Nhận diện bất thường trong giao dịch tài chính và Y tế số.', 'uploads/evidences/ctdt_cntt_fbu_135tc.pdf', '2023-2024', 1, 'TChi07_04.1', 'BTC07', 'ND004'),
('MC036', 'Danh sách sinh viên đạt chứng chỉ Kỹ sư CNTT ứng dụng (AP) chuẩn ITSS Nhật Bản', '2024-06-30', 'Kết quả kỳ thi sát hạch Kỹ sư CNTT trình độ cao (Level 3 ITSS) do IPA Nhật Bản công nhận.', 'uploads/evidences/bien_ban_hoi_thao_cdr.pdf', '2023-2024', 1, 'TChi08_03.1', 'BTC08', 'ND002'),
('MC037', 'Chương trình đào tạo Kỹ sư Cầu nối (BrSE) và chứng chỉ tiếng Nhật JLPT N2/N3', '2024-08-12', 'Báo cáo kết quả khóa đào tạo chuyên sâu văn hóa doanh nghiệp Nhật và phân tích yêu cầu B-spec.', 'uploads/evidences/tap_de_cuong_chi_tiet_cntt.pdf', '2024-2025', 1, 'TChi08_04.1', 'BTC08', 'ND005');


SET FOREIGN_KEY_CHECKS = 1;
