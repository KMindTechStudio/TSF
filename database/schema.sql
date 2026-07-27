CREATE DATABASE IF NOT EXISTS kiemdinh_cntt
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE kiemdinh_cntt;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS download_logs;
DROP TABLE IF EXISTS audit_logs;
DROP TABLE IF EXISTS remember_tokens;
DROP TABLE IF EXISTS evidence_criteria;
DROP TABLE IF EXISTS evidence_files;
DROP TABLE IF EXISTS evidences;
DROP TABLE IF EXISTS criteria;
DROP TABLE IF EXISTS standards;
DROP TABLE IF EXISTS standard_sets;
DROP TABLE IF EXISTS training_programs;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS roles;
DROP TABLE IF EXISTS departments;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    description TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    phone VARCHAR(30) NULL,
    email VARCHAR(150) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_code VARCHAR(50) NULL UNIQUE,
    role_id INT NOT NULL,
    department_id INT NULL,
    full_name VARCHAR(150) NOT NULL,
    username VARCHAR(80) NOT NULL UNIQUE,
    email VARCHAR(150) NOT NULL UNIQUE,
    avatar_path VARCHAR(500) NULL,
    password_hash VARCHAR(255) NOT NULL,
    status ENUM('active', 'locked') NOT NULL DEFAULT 'active',
    last_login_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(id),
    CONSTRAINT fk_users_department FOREIGN KEY (department_id) REFERENCES departments(id)
) ENGINE=InnoDB;

CREATE TABLE remember_tokens (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token_hash VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_remember_token_hash (token_hash),
    KEY idx_remember_user (user_id),
    KEY idx_remember_expires (expires_at),
    CONSTRAINT fk_remember_tokens_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE training_programs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    degree_level VARCHAR(100) NOT NULL,
    faculty VARCHAR(255) NOT NULL,
    accreditation_cycle VARCHAR(100) NULL,
    description TEXT NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE standard_sets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    training_program_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    version_year VARCHAR(20) NOT NULL,
    issuing_body VARCHAR(255) NULL,
    description TEXT NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_standard_sets_program FOREIGN KEY (training_program_id) REFERENCES training_programs(id)
) ENGINE=InnoDB;

CREATE TABLE standards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    standard_set_id INT NOT NULL,
    code VARCHAR(50) NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    display_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_standard_code_set (standard_set_id, code),
    CONSTRAINT fk_standards_set FOREIGN KEY (standard_set_id) REFERENCES standard_sets(id)
) ENGINE=InnoDB;

CREATE TABLE criteria (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_tieu_chuan INT NOT NULL,
    id_don_vi INT NULL,
    ma_tieu_chi VARCHAR(50) NOT NULL,
    ten_tieu_chi VARCHAR(255) NOT NULL,
    noi_dung_mo_ta TEXT NULL,
    trang_thai VARCHAR(50) NOT NULL DEFAULT 'thieu_minh_chung',
    thu_tu_hien_thi INT NOT NULL DEFAULT 0,
    ngay_tao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ngay_cap_nhat TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_criteria_code_standard (id_tieu_chuan, ma_tieu_chi),
    CONSTRAINT fk_criteria_standard FOREIGN KEY (id_tieu_chuan) REFERENCES standards(id),
    CONSTRAINT fk_criteria_department FOREIGN KEY (id_don_vi) REFERENCES departments(id)
) ENGINE=InnoDB;

CREATE TABLE evidences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(80) NOT NULL UNIQUE,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    academic_year VARCHAR(20) NULL,
    issued_date DATE NULL,
    evidence_type VARCHAR(100) NULL DEFAULT 'Minh chứng chính',
    issuing_department_id INT NULL,
    responsible_user_id INT NULL,
    approval_status ENUM('approved', 'reviewing', 'need_update') NOT NULL DEFAULT 'reviewing',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_evidences_department FOREIGN KEY (issuing_department_id) REFERENCES departments(id),
    CONSTRAINT fk_evidences_user FOREIGN KEY (responsible_user_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE evidence_files (
    id INT AUTO_INCREMENT PRIMARY KEY,
    evidence_id INT NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_type VARCHAR(50) NOT NULL,
    file_size BIGINT NOT NULL DEFAULT 0,
    version_no INT NOT NULL DEFAULT 1,
    uploaded_by INT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_evidence_files_evidence FOREIGN KEY (evidence_id) REFERENCES evidences(id) ON DELETE CASCADE,
    CONSTRAINT fk_evidence_files_user FOREIGN KEY (uploaded_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE evidence_criteria (
    evidence_id INT NOT NULL,
    criteria_id INT NOT NULL,
    note VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (evidence_id, criteria_id),
    CONSTRAINT fk_evidence_criteria_evidence FOREIGN KEY (evidence_id) REFERENCES evidences(id) ON DELETE CASCADE,
    CONSTRAINT fk_evidence_criteria_criteria FOREIGN KEY (criteria_id) REFERENCES criteria(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE audit_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(100) NOT NULL,
    module VARCHAR(100) NOT NULL,
    record_id INT NULL,
    old_value JSON NULL,
    new_value JSON NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_audit_logs_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE download_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    evidence_file_id INT NOT NULL,
    downloaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45) NULL,
    CONSTRAINT fk_download_logs_user FOREIGN KEY (user_id) REFERENCES users(id),
    CONSTRAINT fk_download_logs_file FOREIGN KEY (evidence_file_id) REFERENCES evidence_files(id)
) ENGINE=InnoDB;

INSERT INTO roles (code, name, description) VALUES
('admin', 'Quản trị viên', 'Quản trị toàn bộ hệ thống'),
('staff', 'Cán bộ kiểm định', 'Cập nhật và rà soát hồ sơ minh chứng'),
('viewer', 'Người dùng tìm kiếm', 'Tìm kiếm và tải minh chứng được phép');

INSERT INTO departments (code, name, phone, email) VALUES
('KHOA_CNTT', 'Khoa Công nghệ thông tin', NULL, 'cntt@fbu.edu.vn'),
('PDT', 'Phòng Đào tạo', NULL, 'daotao@fbu.edu.vn'),
('PDBCL', 'Phòng Đảm bảo chất lượng', NULL, 'dbcl@fbu.edu.vn'),
('PKT', 'Phòng Khảo thí', NULL, 'khaothi@fbu.edu.vn'),
('BM_PM', 'Bộ môn Phần mềm', NULL, 'bomonpm@fbu.edu.vn');

INSERT INTO users (user_code, role_id, department_id, full_name, username, email, password_hash, status) VALUES
('ND001', (SELECT id FROM roles WHERE code = 'admin'), (SELECT id FROM departments WHERE code = 'KHOA_CNTT'), 'Nguyễn Minh Anh', 'admin', 'admin@fbu.edu.vn', '$2y$10$G..wbhOHZFYD4lfhGWj/qe3Ng9a/WklLNUaJBa9tqvu5FYugLs/Ee', 'active'),
('ND002', (SELECT id FROM roles WHERE code = 'staff'), (SELECT id FROM departments WHERE code = 'PDBCL'), 'Trần Thu Hà', 'kiemdinhtt', 'ha.tt@fbu.edu.vn', '$2y$10$G..wbhOHZFYD4lfhGWj/qe3Ng9a/WklLNUaJBa9tqvu5FYugLs/Ee', 'active'),
('ND003', (SELECT id FROM roles WHERE code = 'viewer'), (SELECT id FROM departments WHERE code = 'PDT'), 'Lê Quang Huy', 'viewer01', 'huy.lq@fbu.edu.vn', '$2y$10$G..wbhOHZFYD4lfhGWj/qe3Ng9a/WklLNUaJBa9tqvu5FYugLs/Ee', 'locked');

INSERT INTO training_programs (code, name, degree_level, faculty, accreditation_cycle, description) VALUES
('7480201', 'Công nghệ thông tin', 'Đại học chính quy', 'Khoa Công nghệ thông tin', 'Chu kỳ kiểm định 2026-2031', 'Chương trình đào tạo ngành Công nghệ thông tin của Trường Đại học Tài chính - Ngân hàng Hà Nội');

INSERT INTO standard_sets (training_program_id, name, version_year, issuing_body, description) VALUES
((SELECT id FROM training_programs WHERE code = '7480201'), 'Bộ tiêu chuẩn đánh giá chất lượng chương trình đào tạo', '2025', 'Bộ Giáo dục và Đào tạo', 'Bộ tiêu chuẩn dùng cho cơ sở dữ liệu minh chứng phục vụ kiểm định CTĐT');

INSERT INTO standards (standard_set_id, code, name, description, display_order) VALUES
(1, 'TC01', 'Mục tiêu và chuẩn đầu ra của chương trình đào tạo', 'Quản lý minh chứng liên quan mục tiêu và chuẩn đầu ra', 1),
(1, 'TC02', 'Bản mô tả chương trình đào tạo', 'Quản lý bản mô tả chương trình đào tạo', 2),
(1, 'TC03', 'Cấu trúc và nội dung chương trình dạy học', 'Quản lý cấu trúc và nội dung chương trình dạy học', 3),
(1, 'TC04', 'Phương pháp tiếp cận trong dạy và học', 'Quản lý minh chứng về phương pháp dạy học', 4),
(1, 'TC05', 'Đánh giá kết quả học tập của người học', 'Quản lý minh chứng về đánh giá kết quả học tập', 5);

INSERT INTO criteria (standard_id, department_id, code, name, description, evidence_status, display_order) VALUES
((SELECT id FROM standards WHERE code = 'TC01'), (SELECT id FROM departments WHERE code = 'KHOA_CNTT'), '1.1', 'Mục tiêu của CTĐT được xác định rõ ràng', 'Mục tiêu CTĐT phù hợp sứ mạng và nhu cầu xã hội', 'complete', 1),
((SELECT id FROM standards WHERE code = 'TC01'), (SELECT id FROM departments WHERE code = 'KHOA_CNTT'), '1.2', 'Chuẩn đầu ra phản ánh yêu cầu của các bên liên quan', 'Chuẩn đầu ra được xây dựng và rà soát định kỳ', 'need_update', 2),
((SELECT id FROM standards WHERE code = 'TC02'), (SELECT id FROM departments WHERE code = 'PDT'), '2.1', 'Bản mô tả CTĐT đầy đủ thông tin cần thiết', 'Bản mô tả nêu rõ mục tiêu, CĐR, cấu trúc và học phần', 'complete', 1),
((SELECT id FROM standards WHERE code = 'TC03'), (SELECT id FROM departments WHERE code = 'BM_PM'), '3.2', 'Nội dung học phần cập nhật theo định hướng nghề nghiệp', 'Đề cương học phần được cập nhật và phê duyệt', 'complete', 2),
((SELECT id FROM standards WHERE code = 'TC04'), (SELECT id FROM departments WHERE code = 'KHOA_CNTT'), '4.1', 'Hoạt động dạy học thúc đẩy năng lực tự học', 'Hoạt động dạy học có định hướng phát triển năng lực', 'missing', 1),
((SELECT id FROM standards WHERE code = 'TC05'), (SELECT id FROM departments WHERE code = 'PKT'), '5.3', 'Quy trình đánh giá kết quả học tập được công bố', 'Quy trình và ma trận đánh giá được công khai', 'need_update', 3);

INSERT INTO evidences (code, title, description, academic_year, issued_date, issuing_department_id, responsible_user_id, approval_status) VALUES
('MC.01.01.01', 'Quyết định ban hành mục tiêu và chuẩn đầu ra ngành CNTT', 'Quyết định ban hành mục tiêu và chuẩn đầu ra của CTĐT ngành CNTT', '2025-2026', '2025-09-01', (SELECT id FROM departments WHERE code = 'KHOA_CNTT'), (SELECT id FROM users WHERE username = 'admin'), 'approved'),
('MC.02.01.03', 'Bản mô tả chương trình đào tạo ngành CNTT', 'Bản mô tả chương trình đào tạo phục vụ kiểm định', '2025-2026', '2025-08-15', (SELECT id FROM departments WHERE code = 'PDT'), (SELECT id FROM users WHERE username = 'kiemdinhtt'), 'approved'),
('MC.03.02.04', 'Đề cương chi tiết các học phần chuyên ngành', 'Tập hợp đề cương học phần chuyên ngành CNTT', '2024-2025', '2024-09-05', (SELECT id FROM departments WHERE code = 'BM_PM'), (SELECT id FROM users WHERE username = 'admin'), 'reviewing'),
('MC.04.01.02', 'Kế hoạch đổi mới phương pháp dạy học', 'Kế hoạch cải tiến phương pháp giảng dạy trong CTĐT', '2025-2026', '2025-10-20', (SELECT id FROM departments WHERE code = 'KHOA_CNTT'), (SELECT id FROM users WHERE username = 'admin'), 'need_update'),
('MC.05.03.05', 'Quy chế đánh giá học phần và ma trận điểm', 'Quy chế đánh giá, rubrics và ma trận điểm học phần', '2025-2026', '2025-11-10', (SELECT id FROM departments WHERE code = 'PKT'), (SELECT id FROM users WHERE username = 'kiemdinhtt'), 'approved');

INSERT INTO evidence_files (evidence_id, original_name, stored_name, file_path, file_type, file_size, uploaded_by) VALUES
((SELECT id FROM evidences WHERE code = 'MC.01.01.01'), 'quyet-dinh-cdr-cntt.pdf', 'MC_01_01_01.pdf', 'uploads/evidences/MC_01_01_01.pdf', 'PDF', 1250000, (SELECT id FROM users WHERE username = 'admin')),
((SELECT id FROM evidences WHERE code = 'MC.02.01.03'), 'ban-mo-ta-ctdt-cntt.docx', 'MC_02_01_03.docx', 'uploads/evidences/MC_02_01_03.docx', 'DOCX', 840000, (SELECT id FROM users WHERE username = 'kiemdinhtt')),
((SELECT id FROM evidences WHERE code = 'MC.03.02.04'), 'de-cuong-hoc-phan.zip', 'MC_03_02_04.zip', 'uploads/evidences/MC_03_02_04.zip', 'ZIP', 6400000, (SELECT id FROM users WHERE username = 'admin')),
((SELECT id FROM evidences WHERE code = 'MC.04.01.02'), 'ke-hoach-doi-moi-day-hoc.pdf', 'MC_04_01_02.pdf', 'uploads/evidences/MC_04_01_02.pdf', 'PDF', 930000, (SELECT id FROM users WHERE username = 'admin')),
((SELECT id FROM evidences WHERE code = 'MC.05.03.05'), 'quy-che-danh-gia.xlsx', 'MC_05_03_05.xlsx', 'uploads/evidences/MC_05_03_05.xlsx', 'XLSX', 420000, (SELECT id FROM users WHERE username = 'kiemdinhtt'));

INSERT INTO evidence_criteria (evidence_id, criteria_id, note) VALUES
((SELECT id FROM evidences WHERE code = 'MC.01.01.01'), (SELECT id FROM criteria WHERE code = '1.1'), 'Minh chứng chính'),
((SELECT id FROM evidences WHERE code = 'MC.01.01.01'), (SELECT id FROM criteria WHERE code = '1.2'), 'Minh chứng liên quan chuẩn đầu ra'),
((SELECT id FROM evidences WHERE code = 'MC.02.01.03'), (SELECT id FROM criteria WHERE code = '2.1'), 'Bản mô tả CTĐT'),
((SELECT id FROM evidences WHERE code = 'MC.03.02.04'), (SELECT id FROM criteria WHERE code = '3.2'), 'Đề cương chi tiết'),
((SELECT id FROM evidences WHERE code = 'MC.04.01.02'), (SELECT id FROM criteria WHERE code = '4.1'), 'Cần bổ sung phụ lục triển khai'),
((SELECT id FROM evidences WHERE code = 'MC.05.03.05'), (SELECT id FROM criteria WHERE code = '5.3'), 'Quy chế và ma trận điểm');

INSERT INTO audit_logs (user_id, action, module, record_id, new_value, ip_address) VALUES
((SELECT id FROM users WHERE username = 'admin'), 'create', 'evidences', 1, JSON_OBJECT('code', 'MC.01.01.01'), '127.0.0.1'),
((SELECT id FROM users WHERE username = 'kiemdinhtt'), 'review', 'criteria', 5, JSON_OBJECT('status', 'missing'), '127.0.0.1'),
((SELECT id FROM users WHERE username = 'admin'), 'create', 'users', 3, JSON_OBJECT('username', 'viewer01'), '127.0.0.1');

INSERT INTO download_logs (user_id, evidence_file_id, ip_address) VALUES
((SELECT id FROM users WHERE username = 'admin'), 1, '127.0.0.1'),
((SELECT id FROM users WHERE username = 'kiemdinhtt'), 2, '127.0.0.1'),
((SELECT id FROM users WHERE username = 'admin'), 5, '127.0.0.1');
