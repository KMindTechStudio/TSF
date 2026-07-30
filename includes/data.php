<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../config/database.php';

require_login();

$appName = 'Hệ thống CSDL minh chứng kiểm định chất lượng CTĐT ngành CNTT';

function vn_criteria_status(string $status): string
{
    switch ($status) {
        case 'complete':
        case 'du_minh_chung':
            return 'Đủ minh chứng';
        case 'need_update':
        case 'can_bo_sung':
            return 'Cần bổ sung';
        case 'missing':
        case 'thieu_minh_chung':
            return 'Thiếu minh chứng';
        default:
            return 'Chưa xác định';
    }
}

function vn_user_status($status): string
{
    return (int)$status === 1 ? 'Hoạt động' : 'Khóa';
}

$pdo = db();

// ─── Roles ───────────────────────────────────────────────────────────────────
$roles = [
    'admin' => 'Quản trị viên',
    'user'  => 'Người dùng',
];

// ─── Training program ────────────────────────────────────────────────────────
$trainingProgram = [
    'name'   => 'Công nghệ thông tin',
    'code'   => '7480201',
    'degree' => 'Đại học chính quy',
    'school' => 'Trường Đại học Tài chính - Ngân hàng Hà Nội',
    'cycle'  => 'Chu kỳ kiểm định 2026-2031',
];

// ─── Current user ────────────────────────────────────────────────────────────
$sessionUserId = $_SESSION['user_id'] ?? null;
$userRow = null;
if ($sessionUserId) {
    $stmt = $pdo->prepare("
        SELECT u.*
        FROM NguoiDung u
        WHERE u.MaNguoiDung = :id
        LIMIT 1
    ");
    $stmt->execute(['id' => $sessionUserId]);
    $userRow = $stmt->fetch();
}

$currentUser = [
    'id'         => $userRow['MaNguoiDung'] ?? 1,
    'name'       => $userRow['HoTen'] ?? 'Quản trị viên',
    'role'       => $userRow['VaiTro'] ?? 'admin',
    'department' => $userRow['DonViCongTac'] ?? '',
    'avatar'     => $userRow['DuongDanAnhDaiDien'] ?? null,
];

// ─── Standard sets ───────────────────────────────────────────────────────────
$standardSets = [];
$stmt = $pdo->query('SELECT MaBoTieuChuan, TenBoTieuChuan, CoQuanBanHanh, NamBanHanh, MoTa, TrangThai FROM BoTieuChuan ORDER BY MaBoTieuChuan');
foreach ($stmt->fetchAll() as $row) {
    $code = 'BTC' . str_pad($row['MaBoTieuChuan'], 2, '0', STR_PAD_LEFT);
    $standardSets[] = [
        'id'           => (int) $row['MaBoTieuChuan'],
        'code'         => $code,
        'name'         => $row['TenBoTieuChuan'],
        'issuing_body' => $row['CoQuanBanHanh'] ?? '',
        'version'      => (string) $row['NamBanHanh'],
        'year'         => (string) $row['NamBanHanh'],
        'description'  => $row['MoTa'] ?? '',
        'status_raw'   => (int) $row['TrangThai'] === 1 ? 'active' : 'inactive',
        'status'       => (int) $row['TrangThai'] === 1 ? 'Đang hoạt động' : 'Ngưng áp dụng',
    ];
}

// ─── Standards ───────────────────────────────────────────────────────────────
$standards = [];
$stmt = $pdo->query("
    SELECT
        s.MaTieuChuan AS id,
        s.TenTieuChuan AS name,
        s.ThuTu,
        s.MoTa,
        s.TrangThai,
        s.MaBoTieuChuan AS set_id,
        b.TenBoTieuChuan AS set_name,
        COUNT(DISTINCT c.MaTieuChi) AS criteria_count,
        COUNT(DISTINCT m.MaMinhChung) AS evidence_count
    FROM TieuChuan s
    LEFT JOIN BoTieuChuan b ON b.MaBoTieuChuan = s.MaBoTieuChuan
    LEFT JOIN TieuChi c ON c.MaTieuChuan = s.MaTieuChuan
    LEFT JOIN MinhChung m ON m.MaTieuChi = c.MaTieuChi
    GROUP BY s.MaTieuChuan, s.TenTieuChuan, s.ThuTu, s.MoTa, s.TrangThai, s.MaBoTieuChuan, b.TenBoTieuChuan
    ORDER BY s.MaTieuChuan ASC
");
foreach ($stmt->fetchAll() as $row) {
    $setCode = $row['set_id'] ? ('BTC' . str_pad($row['set_id'], 2, '0', STR_PAD_LEFT)) : 'N/A';
    $standards[] = [
        'id'          => (int) $row['id'],
        'code'        => 'TC' . str_pad($row['id'], 2, '0', STR_PAD_LEFT),
        'name'        => $row['name'],
        'order'       => (int) $row['ThuTu'],
        'description' => $row['MoTa'] ?? '',
        'set_id'      => (int) $row['set_id'],
        'set_code'    => $setCode,
        'set_name'    => $row['set_name'] ?? '',
        'status_raw'  => (int) $row['TrangThai'] === 1 ? 'active' : 'inactive',
        'status'      => (int) $row['TrangThai'] === 1 ? 'Đang hoạt động' : 'Ngưng áp dụng',
        'criteria'    => (int) $row['criteria_count'],
        'evidences'   => (int) $row['evidence_count'],
    ];
}

// ─── Criteria ────────────────────────────────────────────────────────────────
$criteria = [];
$stmt = $pdo->query("
    SELECT
        c.MaTieuChi AS id,
        c.TenTieuChi AS name,
        c.NoiDung AS description,
        c.ThuTu,
        c.TrangThai,
        s.MaTieuChuan AS standard_id,
        s.TenTieuChuan AS standard_name,
        COUNT(m.MaMinhChung) AS evidence_count
    FROM TieuChi c
    JOIN TieuChuan s ON s.MaTieuChuan = c.MaTieuChuan
    LEFT JOIN MinhChung m ON m.MaTieuChi = c.MaTieuChi
    GROUP BY c.MaTieuChi, c.TenTieuChi, c.NoiDung, c.ThuTu, c.TrangThai, s.MaTieuChuan, s.TenTieuChuan
    ORDER BY c.MaTieuChi ASC
");
foreach ($stmt->fetchAll() as $row) {
    $stdCode = 'TC' . str_pad($row['standard_id'], 2, '0', STR_PAD_LEFT);
    $criteria[] = [
        'id'            => (int) $row['id'],
        'code'          => 'TC' . str_pad($row['standard_id'], 2, '0', STR_PAD_LEFT) . '.' . $row['ThuTu'],
        'standard_id'   => (int) $row['standard_id'],
        'standard_code' => $stdCode,
        'standard'      => $stdCode,
        'name'          => $row['name'],
        'description'   => $row['description'] ?? '',
        'order'         => (int) $row['ThuTu'],
        'status_raw'    => (int) $row['TrangThai'] === 1 ? 'active' : 'inactive',
        'status'        => (int) $row['TrangThai'] === 1 ? 'Đang hoạt động' : 'Ngưng áp dụng',
        'evidences'     => (int) $row['evidence_count'],
    ];
}

// ─── Evidence Types ──────────────────────────────────────────────────────────
$evidenceTypes = [];
$stmt = $pdo->query("SELECT MaLoai, TenLoai, MoTa FROM LoaiMinhChung ORDER BY MaLoai");
foreach ($stmt->fetchAll() as $row) {
    $evidenceTypes[] = [
        'id'          => (int) $row['MaLoai'],
        'name'        => $row['TenLoai'],
        'description' => $row['MoTa'] ?? '',
    ];
}

// ─── Evidences ───────────────────────────────────────────────────────────────
$evidences = [];
$stmt = $pdo->query("
    SELECT
        m.MaMinhChung AS id,
        m.TenMinhChung AS title,
        m.MoTa AS description,
        m.TepTin AS file_path,
        m.NamHoc AS academic_year,
        m.TrangThai AS status,
        m.MaLoai,
        m.MaTieuChi,
        m.MaNguoiDung,
        DATE_FORMAT(m.NgayCapNhat, '%d/%m/%Y %H:%i') AS updated_date,
        l.TenLoai AS type_name,
        c.TenTieuChi AS criteria_name,
        c.ThuTu AS criteria_order,
        c.MaTieuChuan AS standard_id,
        u.HoTen AS user_name
    FROM MinhChung m
    LEFT JOIN LoaiMinhChung l ON l.MaLoai = m.MaLoai
    LEFT JOIN TieuChi c ON c.MaTieuChi = m.MaTieuChi
    LEFT JOIN NguoiDung u ON u.MaNguoiDung = m.MaNguoiDung
    ORDER BY m.MaMinhChung ASC
");
foreach ($stmt->fetchAll() as $row) {
    $stdId = $row['standard_id'] ? (int) $row['standard_id'] : 0;
    $criteriaCode = $stdId ? ('TC' . str_pad($stdId, 2, '0', STR_PAD_LEFT) . '.' . $row['criteria_order']) : 'N/A';
    $typeCode = $row['MaLoai'] ? ('LMC' . str_pad($row['MaLoai'], 2, '0', STR_PAD_LEFT)) : 'N/A';
    $userCode = $row['MaNguoiDung'] ? ('ND' . str_pad($row['MaNguoiDung'], 3, '0', STR_PAD_LEFT)) : 'N/A';

    $evidences[] = [
        'id'            => (int) $row['id'],
        'code'          => 'MC' . str_pad($row['id'], 2, '0', STR_PAD_LEFT),
        'name'          => $row['title'],
        'description'   => $row['description'] ?? '',
        'file_path'     => $row['file_path'] ?? '',
        'year'          => $row['academic_year'] ?? '',
        'updated'       => $row['updated_date'] ?: 'Chưa cập nhật',
        'status_raw'    => (int) $row['status'],
        'status'        => (int) $row['status'] === 1 ? 'Đang hoạt động' : 'Ngưng áp dụng',
        'ma_loai'       => $row['MaLoai'] ? (int) $row['MaLoai'] : null,
        'type_code'     => $typeCode,
        'type_name'     => $row['type_name'] ?? '',
        'evidence_type' => $row['type_name'] ?? 'Chưa phân loại',
        'ma_tieu_chi'   => $row['MaTieuChi'] ? (int) $row['MaTieuChi'] : null,
        'criteria_code' => $criteriaCode,
        'criteria'      => $criteriaCode . ($row['criteria_name'] ? (' - ' . $row['criteria_name']) : ''),
        'ma_nguoi_dung' => $row['MaNguoiDung'] ? (int) $row['MaNguoiDung'] : null,
        'user_code'     => $userCode,
        'user_name'     => $row['user_name'] ?? 'Hệ thống',
        'standards'     => $stdId ? ('TC' . str_pad($stdId, 2, '0', STR_PAD_LEFT)) : '',
    ];
}

// ─── Users ───────────────────────────────────────────────────────────────────
$users = [];
$stmt = $pdo->query("
    SELECT
        u.MaNguoiDung AS id,
        CONCAT('ND', LPAD(u.MaNguoiDung, 3, '0')) AS user_code,
        u.HoTen,
        u.DonViCongTac,
        u.Email,
        u.SDT,
        u.TenDangNhap,
        u.VaiTro,
        u.TrangThai,
        u.DuongDanAnhDaiDien AS avatar
    FROM NguoiDung u
    ORDER BY u.MaNguoiDung
");
foreach ($stmt->fetchAll() as $row) {
    $roleName = $row['VaiTro'] === 'admin' ? 'Quản trị viên' : 'Người dùng';
    $users[] = [
        'id'         => (int) $row['id'],
        'code'       => $row['user_code'],
        'name'       => $row['HoTen'],
        'username'   => $row['TenDangNhap'],
        'email'      => $row['Email'],
        'phone'      => $row['SDT'] ?? '',
        'department' => $row['DonViCongTac'] ?? '',
        'role'       => $roleName,
        'role_code'  => $row['VaiTro'],
        'avatar'     => $row['avatar'],
        'status_raw' => (int) $row['TrangThai'],
        'status'     => vn_user_status($row['TrangThai']),
    ];
}

// ─── Activity Logs ───────────────────────────────────────────────────────────
$activityLogs = [];
try {
    $stmt = $pdo->query("
        SELECT
            a.id,
            a.hanh_dong,
            a.phan_he,
            a.ten_ban_ghi,
            DATE_FORMAT(a.ngay_tao, '%d/%m/%Y %H:%i') AS log_time,
            u.HoTen AS user_name
        FROM audit_logs a
        LEFT JOIN NguoiDung u ON u.MaNguoiDung = a.MaNguoiDung
        ORDER BY a.id DESC
        LIMIT 10
    ");
    foreach ($stmt->fetchAll() as $row) {
        switch ($row['hanh_dong']) {
            case 'them_moi':
                $actionText = 'Thêm mới ' . $row['phan_he'];
                break;
            case 'cap_nhat':
                $actionText = 'Cập nhật ' . $row['phan_he'];
                break;
            case 'xoa':
                $actionText = 'Xóa ' . $row['phan_he'];
                break;
            case 'cap_nhat_trang_thai':
                $actionText = 'Cập nhật trạng thái ' . $row['phan_he'];
                break;
            default:
                $actionText = $row['hanh_dong'];
                break;
        }
        if (!empty($row['ten_ban_ghi'])) {
            $actionText .= ': ' . $row['ten_ban_ghi'];
        }
        $activityLogs[] = [
            'id'     => (int) $row['id'],
            'action' => $actionText,
            'actor'  => $row['user_name'] ?? 'Hệ thống',
            'time'   => $row['log_time'],
        ];
    }
} catch (Throwable $e) {
    $activityLogs = [];
}

