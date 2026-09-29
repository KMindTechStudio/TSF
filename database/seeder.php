<?php
require_once __DIR__ . '/../config/database.php';

function seed_database_if_needed($force = false) {
    $pdo = db();

    $countUsers = (int) $pdo->query("SELECT COUNT(*) FROM NguoiDung")->fetchColumn();

    if (!$force && $countUsers > 0) {
        return;
    }

    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
    $pdo->exec("TRUNCATE TABLE NguoiDung;");
    $passHash = password_hash('123456', PASSWORD_DEFAULT);

    $usersData = [
        ['ND001', 'Quản trị viên', 'admin@fbu.edu.vn', '0912345678', 'admin', 'admin', 1],
        ['ND002', 'ThS. Nguyễn Văn An', 'annv@fbu.edu.vn', '0987654321', 'nguyenvanan', 'user', 1],
        ['ND003', 'TS. Trần Thị Bích', 'bich.tt@fbu.edu.vn', '0911223344', 'tranthibich', 'admin', 1],
        ['ND004', 'PGS.TS. Lê Hoàng Nam', 'namlh@fbu.edu.vn', '0903112233', 'lehoangnam', 'user', 1],
        ['ND005', 'ThS. Phạm Thu Trang', 'trangpt@fbu.edu.vn', '0978998877', 'phamthutrang', 'user', 1],
        ['ND006', 'KS. Vũ Đình Trọng', 'trongvd@fbu.edu.vn', '0934556677', 'vudinhtrong', 'user', 1],
        ['ND007', 'ThS. Hoàng Minh Đức', 'duchm@fbu.edu.vn', '0945667788', 'hoangminhduc', 'user', 0],
        ['ND008', 'Người dùng thử nghiệm', 'user@fbu.edu.vn', '0966889900', 'user', 'user', 1],
    ];

    $stmtUser = $pdo->prepare("INSERT INTO NguoiDung (MaNguoiDung, HoTen, Email, SoDienThoai, TenDangNhap, MatKhau, VaiTro, TrangThai) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($usersData as $u) {
        $stmtUser->execute([$u[0], $u[1], $u[2], $u[3], $u[4], $passHash, $u[5], $u[6]]);
    }

    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
}
