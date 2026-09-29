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

// ─── Standard Sets (Quản lý Bộ Tiêu chuẩn động) ──────────────────────────────
$standardSets = [];
try {
    $stmt = $pdo->query("
        SELECT
            b.MaBoTieuChuan AS id,
            b.TenBoTieuChuan AS name,
            COALESCE(b.ThongTu, '') AS thong_tu,
            COALESCE(DATE_FORMAT(b.NgayBanHanh, '%Y-%m-%d'), '') AS ngay_ban_hanh,
            b.MoTa,
            b.TepTinPDF,
            b.TrangThai,
            COUNT(DISTINCT tc.MaTieuChuan) AS standard_count,
            COUNT(DISTINCT m.MaMinhChung) AS evidence_count
        FROM BoTieuChuan b
        LEFT JOIN TieuChuan tc ON tc.MaBoTieuChuan = b.MaBoTieuChuan
        LEFT JOIN TieuChi tchi ON tchi.MaTieuChuan = tc.MaTieuChuan
        LEFT JOIN MinhChung m ON (m.MaBoTieuChuan = b.MaBoTieuChuan OR m.MaTieuChi = tchi.MaTieuChi)
        GROUP BY b.MaBoTieuChuan, b.TenBoTieuChuan, b.ThongTu, b.NgayBanHanh, b.MoTa, b.TepTinPDF, b.TrangThai
        ORDER BY b.TrangThai DESC, b.MaBoTieuChuan ASC
    ");
    foreach ($stmt->fetchAll() as $row) {
        $standardSets[] = [
            'id'             => $row['id'],
            'code'           => $row['id'],
            'name'           => $row['name'],
            'thong_tu'       => $row['thong_tu'] ?: 'Thông tư 04/2016/TT-BGDĐT',
            'ngay_ban_hanh'  => $row['ngay_ban_hanh'] ?: '',
            'description'    => $row['MoTa'] ?? '',
            'tep_tin_pdf'    => $row['TepTinPDF'] ?? '',
            'status_raw'     => (int) $row['TrangThai'] === 1 ? 'active' : 'inactive',
            'status'         => (int) $row['TrangThai'] === 1 ? 'Đang hoạt động' : 'Ngưng áp dụng',
            'standard_count' => (int) $row['standard_count'],
            'evidences'      => (int) $row['evidence_count'],
        ];
    }
} catch (Throwable $e) {
    $standardSets = [];
}

// ─── Standards (Quản lý Tiêu chuẩn) ──────────────────────────────────────────
$standards = [];
try {
    $stmt = $pdo->query("
        SELECT 
            tc.MaTieuChuan AS id,
            tc.TenTieuChuan AS name,
            tc.MoTa AS description,
            tc.ThuTu AS order_num,
            tc.MaBoTieuChuan AS set_id,
            tc.TrangThai AS status,
            b.TenBoTieuChuan AS set_name
        FROM TieuChuan tc
        LEFT JOIN BoTieuChuan b ON b.MaBoTieuChuan = tc.MaBoTieuChuan
        ORDER BY tc.ThuTu ASC, tc.MaTieuChuan ASC
    ");
    foreach ($stmt->fetchAll() as $row) {
        $standards[] = [
            'id'          => $row['id'],
            'code'        => $row['id'],
            'name'        => $row['name'],
            'description' => $row['description'] ?? '',
            'order'       => (int) $row['order_num'],
            'set_id'      => $row['set_id'] ?? '',
            'set_name'    => $row['set_name'] ?? '',
            'status'      => (int) $row['status'] === 1 ? 'Đang hoạt động' : 'Ngưng áp dụng',
        ];
    }
} catch (Throwable $e) {
    $standards = [];
}

// ─── Criteria (Quản lý Tiêu chí) ───────────────────────────────────────────
$criteria = [];
try {
    $stmt = $pdo->query("
        SELECT 
            c.MaTieuChi AS id,
            c.TenTieuChi AS name,
            c.NoiDung AS description,
            c.ThuTu AS order_num,
            c.MaTieuChuan AS standard_id,
            c.TrangThai AS status,
            tc.TenTieuChuan AS standard_name,
            tc.MaBoTieuChuan AS set_id,
            COALESCE(b.TenBoTieuChuan, '') AS set_name
        FROM TieuChi c
        LEFT JOIN TieuChuan tc ON tc.MaTieuChuan = c.MaTieuChuan
        LEFT JOIN BoTieuChuan b ON b.MaBoTieuChuan = tc.MaBoTieuChuan
        ORDER BY c.ThuTu ASC, c.MaTieuChi ASC
    ");
    foreach ($stmt->fetchAll() as $row) {
        $criteria[] = [
            'id'            => $row['id'],
            'code'          => $row['id'],
            'name'          => $row['name'],
            'description'   => $row['description'] ?? '',
            'order'         => (int) $row['order_num'],
            'standard_id'   => $row['standard_id'] ?? '',
            'standard_name' => $row['standard_name'] ?? '',
            'set_id'        => $row['set_id'] ?? '',
            'set_name'      => $row['set_name'] ?? '',
            'status'        => (int) $row['status'] === 1 ? 'Đang hoạt động' : 'Ngưng áp dụng',
        ];
    }
} catch (Throwable $e) {
    $criteria = [];
}

// ─── Evidences (Quản lý Minh chứng) ──────────────────────────────────────────
$evidences = [];
try {
    $stmt = $pdo->query("
        SELECT
            m.MaMinhChung AS id,
            m.TenMinhChung AS title,
            m.NgayBanHanh AS issue_date,
            DATE_FORMAT(m.NgayBanHanh, '%d/%m/%Y') AS formatted_issue_date,
            m.MoTa AS description,
            m.TepTin AS file_path,
            m.NamHoc AS academic_year,
            m.TrangThai AS status,
            m.MaTieuChi AS criterion_id,
            COALESCE(tc.TenTieuChi, '') AS criterion_name,
            COALESCE(tc.MaTieuChuan, '') AS standard_id,
            COALESCE(tch.TenTieuChuan, '') AS standard_name,
            COALESCE(tch.MaBoTieuChuan, m.MaBoTieuChuan, '') AS set_id,
            COALESCE(b.TenBoTieuChuan, bm.TenBoTieuChuan, '') AS set_name,
            m.MaNguoiDung,
            DATE_FORMAT(m.NgayCapNhat, '%d/%m/%Y %H:%i') AS updated_date,
            u.HoTen AS user_name
        FROM MinhChung m
        LEFT JOIN TieuChi tc ON tc.MaTieuChi = m.MaTieuChi
        LEFT JOIN TieuChuan tch ON tch.MaTieuChuan = tc.MaTieuChuan
        LEFT JOIN BoTieuChuan b ON b.MaBoTieuChuan = tch.MaBoTieuChuan
        LEFT JOIN BoTieuChuan bm ON bm.MaBoTieuChuan = m.MaBoTieuChuan
        LEFT JOIN NguoiDung u ON u.MaNguoiDung = m.MaNguoiDung
        ORDER BY m.MaMinhChung ASC
    ");
    foreach ($stmt->fetchAll() as $row) {
        $setCode = $row['set_id'] ?: 'N/A';
        $userCode = $row['MaNguoiDung'] ?: 'N/A';

        $evidences[] = [
            'id'                  => $row['id'],
            'code'                => $row['id'],
            'name'                => $row['title'],
            'issue_date'          => $row['issue_date'] ?? '',
            'issue_date_formatted'=> $row['formatted_issue_date'] ?? '',
            'description'         => $row['description'] ?? '',
            'file_path'           => $row['file_path'] ?? '',
            'year'                => $row['academic_year'] ?? '',
            'updated'             => $row['updated_date'] ?: 'Chưa cập nhật',
            'status_raw'          => (int) $row['status'],
            'status'              => (int) $row['status'] === 1 ? 'Đang hoạt động' : 'Không hoạt động',
            'ma_tieu_chi'     => $row['criterion_id'] ?? '',
            'criterion_code'  => $row['criterion_id'] ?? '',
            'criterion_name'  => $row['criterion_name'] ?? '',
            'ma_tieu_chuan'   => $row['standard_id'] ?? '',
            'standard_code'   => $row['standard_id'] ?? '',
            'standard_name'   => $row['standard_name'] ?? '',
            'ma_bo_tieu_chuan'=> $row['set_id'] ?? '',
            'set_code'        => $setCode,
            'set_name'        => $row['set_name'] ?? '',
            'standard_set'    => $setCode . ($row['set_name'] ? (' - ' . $row['set_name']) : ''),
            'ma_nguoi_dung'   => $row['MaNguoiDung'],
            'user_code'       => $userCode,
            'user_name'       => $row['user_name'] ?? 'Hệ thống',
        ];
    }
} catch (Throwable $e) {
    $evidences = [];
}

// ─── Users (Quản lý Người dùng) ──────────────────────────────────────────────
$users = [];
try {
    $stmt = $pdo->query("
        SELECT
            u.MaNguoiDung AS id,
            u.MaNguoiDung AS user_code,
            u.HoTen,
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
            'role'       => $row['VaiTro'],
            'role_code'  => $row['VaiTro'],
            'role_name'  => $roleName,
            'avatar'     => $row['avatar'],
            'status_raw' => (int) $row['TrangThai'],
            'status'     => vn_user_status($row['TrangThai']),
        ];
    }
} catch (Throwable $e) {
    $users = [];
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
