<?php
require_once __DIR__ . '/../config/database.php';

$pdo = db();

$appName = 'Hệ thống Quản lý Minh chứng Kiểm định CTĐT';

$trainingProgram = [
    'name'    => 'Công nghệ thông tin',
    'code'    => '7480201',
    'degree'  => 'Cử nhân',
    'school'  => 'Trường Đại học Tài chính - Ngân hàng Hà Nội',
    'faculty' => 'Khoa Công nghệ thông tin',
    'cycle'   => '2026-2031',
];

try {
    $settingsStmt = $pdo->query("SELECT khoa, gia_tri FROM cau_hinh");
    if ($settingsStmt) {
        $settingsRows = $settingsStmt->fetchAll(PDO::FETCH_KEY_PAIR);
        if (!empty($settingsRows)) {
            if (!empty($settingsRows['school']))       $trainingProgram['school']  = $settingsRows['school'];
            if (!empty($settingsRows['faculty']))      $trainingProgram['faculty'] = $settingsRows['faculty'];
            if (!empty($settingsRows['program_name'])) $trainingProgram['name']    = $settingsRows['program_name'];
            if (!empty($settingsRows['program_code'])) $trainingProgram['code']    = $settingsRows['program_code'];
            if (!empty($settingsRows['degree']))       $trainingProgram['degree']  = $settingsRows['degree'];
            if (!empty($settingsRows['cycle']))        $trainingProgram['cycle']   = $settingsRows['cycle'];
        }
    }
} catch (Throwable $e) {
    // Graceful fallback to default configuration
}

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
            COUNT(DISTINCT COALESCE(mctc.MaMinhChung, m.MaMinhChung)) AS evidence_count
        FROM BoTieuChuan b
        LEFT JOIN TieuChuan tc ON tc.MaBoTieuChuan = b.MaBoTieuChuan
        LEFT JOIN TieuChi tchi ON tchi.MaTieuChuan = tc.MaTieuChuan
        LEFT JOIN minh_chung_tieu_chi mctc ON mctc.MaTieuChi = tchi.MaTieuChi
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

// ─── Standards (Cập nhật Tiêu chuẩn) ──────────────────────────────────────────
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

// ─── Criteria (Cập nhật Tiêu chí) ───────────────────────────────────────────
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

// ─── Evidences (Cập nhật Minh chứng) ──────────────────────────────────────────
$evidences = [];
try {
    // 1. Fetch many-to-many associations
    $mctcMap = [];
    try {
        $stmtMctc = $pdo->query("
            SELECT 
                mctc.MaMinhChung,
                mctc.MaTieuChi,
                c.TenTieuChi AS criterion_name,
                tc.MaTieuChuan AS standard_id,
                tc.TenTieuChuan AS standard_name,
                tc.MaBoTieuChuan AS set_id,
                COALESCE(b.TenBoTieuChuan, '') AS set_name
            FROM minh_chung_tieu_chi mctc
            JOIN TieuChi c ON c.MaTieuChi = mctc.MaTieuChi
            LEFT JOIN TieuChuan tc ON tc.MaTieuChuan = c.MaTieuChuan
            LEFT JOIN BoTieuChuan b ON b.MaBoTieuChuan = tc.MaBoTieuChuan
            ORDER BY c.ThuTu ASC, c.MaTieuChi ASC
        ");
        foreach ($stmtMctc->fetchAll() as $link) {
            $mctcMap[$link['MaMinhChung']][] = [
                'id'            => $link['MaTieuChi'],
                'code'          => $link['MaTieuChi'],
                'name'          => $link['criterion_name'],
                'standard_id'   => $link['standard_id'] ?? '',
                'standard_name' => $link['standard_name'] ?? '',
                'set_id'        => $link['set_id'] ?? '',
                'set_name'      => $link['set_name'] ?? '',
            ];
        }
    } catch (Throwable $ex) {}

    $stmt = $pdo->query("
        SELECT
            m.MaMinhChung AS id,
            m.TenMinhChung AS title,
            m.SoHieu AS document_number,
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
            m.NgayCapNhat,
            m.NgayTao,
            u.HoTen AS user_name,
            u.TenDangNhap AS username,
            u.VaiTro AS user_role
        FROM MinhChung m
        LEFT JOIN TieuChi tc ON tc.MaTieuChi = m.MaTieuChi
        LEFT JOIN TieuChuan tch ON tch.MaTieuChuan = tc.MaTieuChuan
        LEFT JOIN BoTieuChuan b ON b.MaBoTieuChuan = tch.MaBoTieuChuan
        LEFT JOIN BoTieuChuan bm ON bm.MaBoTieuChuan = m.MaBoTieuChuan
        LEFT JOIN NguoiDung u ON u.MaNguoiDung = m.MaNguoiDung
        ORDER BY m.MaMinhChung ASC
    ");
    foreach ($stmt->fetchAll() as $row) {
        $linkedCriteria = $mctcMap[$row['id']] ?? [];
        if (empty($linkedCriteria) && !empty($row['criterion_id'])) {
            $linkedCriteria[] = [
                'id'            => $row['criterion_id'],
                'code'          => $row['criterion_id'],
                'name'          => $row['criterion_name'],
                'standard_id'   => $row['standard_id'] ?? '',
                'standard_name' => $row['standard_name'] ?? '',
                'set_id'        => $row['set_id'] ?? '',
                'set_name'      => $row['set_name'] ?? '',
            ];
        }

        $criteriaIds = array_values(array_unique(array_column($linkedCriteria, 'id')));
        $standardIds = array_values(array_unique(array_filter(array_column($linkedCriteria, 'standard_id'))));
        $setIds      = array_values(array_unique(array_filter(array_column($linkedCriteria, 'set_id'))));

        $primaryCrit = !empty($linkedCriteria) ? $linkedCriteria[0] : null;
        $primaryCritId = $primaryCrit ? $primaryCrit['id'] : ($row['criterion_id'] ?? '');
        $primaryCritName = $primaryCrit ? $primaryCrit['name'] : ($row['criterion_name'] ?? '');
        $primaryStdId = $primaryCrit ? $primaryCrit['standard_id'] : ($row['standard_id'] ?? '');
        $primaryStdName = $primaryCrit ? $primaryCrit['standard_name'] : ($row['standard_name'] ?? '');
        $primarySetId = $primaryCrit ? $primaryCrit['set_id'] : ($row['set_id'] ?? '');
        $primarySetName = $primaryCrit ? $primaryCrit['set_name'] : ($row['set_name'] ?? '');

        $setCode = $primarySetId ?: ($row['set_id'] ?: 'N/A');
        $userCode = $row['MaNguoiDung'] ?: 'ND001';

        $updatedFormatted = !empty($row['updated_date']) 
            ? $row['updated_date'] 
            : (!empty($row['NgayCapNhat']) 
                ? date('d/m/Y H:i', strtotime($row['NgayCapNhat'])) 
                : (!empty($row['NgayTao']) ? date('d/m/Y H:i', strtotime($row['NgayTao'])) : date('d/m/Y H:i')));

        $evidences[] = [
            'id'                  => $row['id'],
            'code'                => $row['id'],
            'name'                => $row['title'],
            'so_hieu'             => $row['document_number'] ?? '',
            'document_number'     => $row['document_number'] ?? '',
            'issue_date'          => $row['issue_date'] ?? '',
            'issue_date_formatted'=> $row['formatted_issue_date'] ?? '',
            'description'         => $row['description'] ?? '',
            'file_path'           => $row['file_path'] ?? '',
            'year'                => $row['academic_year'] ?? '',
            'updated'             => $updatedFormatted,
            'updated_raw'         => $row['NgayCapNhat'] ?? '',
            'status_raw'          => (int) $row['status'],
            'status'              => (int) $row['status'] === 1 ? 'Đang hoạt động' : 'Không hoạt động',
            
            // Multiple criteria & standards support
            'criteria'            => $linkedCriteria,
            'criteria_ids'        => $criteriaIds,
            'standard_ids'        => $standardIds,
            'set_ids'             => $setIds,

            // Primary / Backward-compatible fields
            'ma_tieu_chi'         => $primaryCritId,
            'criterion_code'      => $primaryCritId,
            'criterion_name'      => $primaryCritName,
            'ma_tieu_chuan'       => $primaryStdId,
            'standard_code'       => $primaryStdId,
            'standard_name'       => $primaryStdName,
            'ma_bo_tieu_chuan'    => $primarySetId,
            'set_code'            => $setCode,
            'set_name'            => $primarySetName,
            'standard_set'        => $setCode . ($primarySetName ? (' - ' . $primarySetName) : ''),
            'ma_nguoi_dung'       => $row['MaNguoiDung'],
            'user_code'           => $userCode,
            'user_name'           => $row['user_name'] ?? 'Quản trị viên',
            'username'            => $row['username'] ?? 'admin',
            'user_role'           => $row['user_role'] ?? 'admin',
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
