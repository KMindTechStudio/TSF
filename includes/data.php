<?php
require_once __DIR__ . '/../config/database.php';

$pdo = db();

$appName = 'Hệ thống Quản lý Minh chứng Kiểm định CTĐT';

$trainingProgram = [
    'name'   => 'Công nghệ thông tin',
    'code'   => '7480201',
    'degree' => 'Cử nhân',
    'school' => 'Trường Đại học Tài chính - Ngân hàng Hà Nội',
    'cycle'  => '2026-2031',
];

$roles = [
    'admin' => 'Quản trị viên',
    'user'  => 'Người dùng',
];

if (!function_exists('search_contains')) {
    function search_contains(string $text, string $keyword): bool
    {
        if ($keyword === '') {
            return true;
        }
        return mb_stripos($text, $keyword) !== false;
    }
}

if (!function_exists('vn_user_status')) {
    function vn_user_status(int $status): string
    {
        return $status === 1 ? 'Đang hoạt động' : 'Ngưng áp dụng';
    }
}

// ─── Standard Sets ───────────────────────────────────────────────────────────
$standardSets = [];
$stmt = $pdo->query("
    SELECT
        b.MaBoTieuChuan AS id,
        b.TenBoTieuChuan AS name,
        COALESCE(b.ThongTu, '') AS thong_tu,
        COALESCE(DATE_FORMAT(b.NgayBanHanh, '%Y-%m-%d'), '') AS ngay_ban_hanh,
        b.MoTa,
        b.TrangThai,
        COUNT(DISTINCT s.MaTieuChuan) AS standards_count,
        COUNT(DISTINCT c.MaTieuChi) AS criteria_count,
        COUNT(DISTINCT m.MaMinhChung) AS evidence_count
    FROM BoTieuChuan b
    LEFT JOIN TieuChuan s ON s.MaBoTieuChuan = b.MaBoTieuChuan
    LEFT JOIN TieuChi c ON c.MaTieuChuan = s.MaTieuChuan
    LEFT JOIN MinhChung m ON m.MaTieuChi = c.MaTieuChi
    GROUP BY b.MaBoTieuChuan, b.TenBoTieuChuan, b.ThongTu, b.NgayBanHanh, b.MoTa, b.TrangThai
    ORDER BY b.MaBoTieuChuan ASC
");
foreach ($stmt->fetchAll() as $row) {
    $standardSets[] = [
        'id'            => $row['id'],
        'code'          => $row['id'],
        'name'          => $row['name'],
        'thong_tu'      => $row['thong_tu'] ?: 'Thông tư 04/2016/TT-BGDĐT',
        'ngay_ban_hanh' => $row['ngay_ban_hanh'] ?: '2025-01-15',
        'description'   => $row['MoTa'] ?? '',
        'status_raw'    => (int) $row['TrangThai'] === 1 ? 'active' : 'inactive',
        'status'        => (int) $row['TrangThai'] === 1 ? 'Đang hoạt động' : 'Ngưng áp dụng',
        'standards'     => (int) $row['standards_count'],
        'criteria'      => (int) $row['criteria_count'],
        'evidences'     => (int) $row['evidence_count'],
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
    $setCode = $row['set_id'] ? $row['set_id'] : 'N/A';
    $standards[] = [
        'id'          => $row['id'],
        'code'        => $row['id'],
        'name'        => $row['name'],
        'order'       => (int) $row['ThuTu'],
        'description' => $row['MoTa'] ?? '',
        'set_id'      => $row['set_id'],
        'set_code'    => $setCode,
        'set_name'    => $row['set_name'] ?? '',
        'status_raw'  => (int) $row['TrangThai'] === 1 ? 'active' : 'inactive',
        'status'      => (int) $row['TrangThai'] === 1 ? 'Đang hoạt động' : 'Ngưng áp dụng',
        'criteria'    => (int) $row['criteria_count'],
        'evidences'   => (int) $row['evidence_count'],
    ];
}

// ─── Criteria ─────────────────────────────────────────────────────────────────
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
    $stdCode = $row['standard_id'];
    $criteria[] = [
        'id'            => $row['id'],
        'code'          => $row['id'],
        'standard_id'   => $row['standard_id'],
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
    $stdId = $row['standard_id'] ?? '';
    $criteriaCode = $row['MaTieuChi'] ?? 'N/A';
    $typeCode = $row['MaLoai'] ? ('LMC' . str_pad($row['MaLoai'], 2, '0', STR_PAD_LEFT)) : 'N/A';
    $userCode = $row['MaNguoiDung'] ?? 'N/A';

    $evidences[] = [
        'id'            => $row['id'],
        'code'          => $row['id'],
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
        'ma_tieu_chi'   => $row['MaTieuChi'],
        'criteria_code' => $criteriaCode,
        'criteria'      => $criteriaCode . ($row['criteria_name'] ? (' - ' . $row['criteria_name']) : ''),
        'ma_nguoi_dung' => $row['MaNguoiDung'],
        'user_code'     => $userCode,
        'user_name'     => $row['user_name'] ?? 'Hệ thống',
        'standards'     => $stdId ? $stdId : '',
    ];
}

// ─── Users ───────────────────────────────────────────────────────────────────
$users = [];
$stmt = $pdo->query("
    SELECT
        u.MaNguoiDung AS id,
        u.MaNguoiDung AS user_code,
        u.HoTen,
        u.DonViCongTac,
        u.Email,
        COALESCE(u.SoDienThoai, '') AS phone,
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
        'id'         => $row['id'],
        'code'       => $row['user_code'],
        'name'       => $row['HoTen'],
        'username'   => $row['TenDangNhap'],
        'email'      => $row['Email'],
        'phone'      => $row['phone'] ?? '',
        'department' => $row['DonViCongTac'] ?? '',
        'role'       => $row['VaiTro'],
        'role_code'  => $row['VaiTro'],
        'role_name'  => $roleName,
        'avatar'     => $row['avatar'],
        'status_raw' => (int) $row['TrangThai'],
        'status'     => vn_user_status($row['TrangThai']),
    ];
}

if (!function_exists('current_user')) {
    function current_user(): array
    {
        global $users;
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $userId = $_SESSION['user_id'] ?? 'ND001';
        foreach ($users as $u) {
            if ((string) $u['id'] === (string) $userId) {
                return $u;
            }
        }
        return $users[0] ?? [
            'id'     => 'ND001',
            'code'   => 'ND001',
            'name'   => 'Quản trị viên',
            'email'  => 'admin@fbu.edu.vn',
            'role'   => 'admin',
            'avatar' => null,
        ];
    }
}

$currentUser = current_user();

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
            u.HoTen AS user_name,
            u.VaiTro AS user_role
        FROM audit_logs a
        LEFT JOIN NguoiDung u ON u.MaNguoiDung = a.MaNguoiDung
        ORDER BY a.id DESC
        LIMIT 15
    ");
    
    $modulesMap = [
        'bo_tieu_chuan' => 'Bộ tiêu chuẩn',
        'tieu_chuan'    => 'Tiêu chuẩn',
        'tieu_chi'      => 'Tiêu chí',
        'minh_chung'    => 'Minh chứng',
        'nguoi_dung'    => 'Người dùng',
        'he_thong'      => 'Hệ thống',
    ];

    foreach ($stmt->fetchAll() as $row) {
        $modName = $modulesMap[$row['phan_he']] ?? $row['phan_he'];
        $icon = 'bi-activity';
        $badgeClass = 'bg-secondary';
        
        switch ($row['hanh_dong']) {
            case 'them_moi':
                $actionName = 'Thêm mới ' . mb_strtolower($modName);
                $icon = 'bi-plus-circle-fill';
                $badgeClass = 'bg-success';
                break;
            case 'cap_nhat':
                $actionName = 'Cập nhật ' . mb_strtolower($modName);
                $icon = 'bi-pencil-square';
                $badgeClass = 'bg-warning text-dark';
                break;
            case 'xoa':
                $actionName = 'Xóa ' . mb_strtolower($modName);
                $icon = 'bi-trash-fill';
                $badgeClass = 'bg-danger';
                break;
            case 'cap_nhat_trang_thai':
                $actionName = 'Đổi trạng thái ' . mb_strtolower($modName);
                $icon = 'bi-toggle-on';
                $badgeClass = 'bg-info text-dark';
                break;
            case 'dang_nhap':
                $actionName = 'Đăng nhập hệ thống';
                $icon = 'bi-box-arrow-in-right';
                $badgeClass = 'bg-primary';
                break;
            case 'dang_xuat':
                $actionName = 'Đăng xuất hệ thống';
                $icon = 'bi-box-arrow-right';
                $badgeClass = 'bg-secondary';
                break;
            case 'tai_ve':
                $actionName = 'Tải xuống minh chứng';
                $icon = 'bi-download';
                $badgeClass = 'bg-success';
                break;
            default:
                $actionName = $row['hanh_dong'] . ' ' . $modName;
                break;
        }

        $detail = !empty($row['ten_ban_ghi']) ? $row['ten_ban_ghi'] : '';
        $roleName = ($row['user_role'] ?? 'admin') === 'admin' ? 'Quản trị viên' : 'Người dùng';

        $activityLogs[] = [
            'id'          => (int) $row['id'],
            'action_name' => $actionName,
            'action_raw'  => $row['hanh_dong'],
            'detail'      => $detail,
            'icon'        => $icon,
            'badge_class' => $badgeClass,
            'actor'       => $row['user_name'] ?? 'Hệ thống',
            'role_name'   => $roleName,
            'role_code'   => $row['user_role'] ?? 'admin',
            'time'        => $row['log_time'],
        ];
    }
} catch (Throwable $e) {
    $activityLogs = [];
}
