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

function vn_approval_status(string $status): string
{
    switch ($status) {
        case 'approved':
            return 'Đã duyệt';
        case 'reviewing':
            return 'Chờ rà soát';
        case 'need_update':
            return 'Cần bổ sung';
        default:
            return 'Chưa xác định';
    }
}

function vn_user_status(string $status): string
{
    return $status === 'active' ? 'Đang hoạt động' : 'Khóa';
}

function standard_status_from_counts(int $missing, int $needUpdate): string
{
    if ($missing > 0) {
        return 'Thiếu minh chứng';
    }
    if ($needUpdate > 0) {
        return 'Cần bổ sung';
    }
    return 'Đủ minh chứng';
}

$pdo = db();

// ─── Roles ───────────────────────────────────────────────────────────────────
$rolesRows = $pdo->query('SELECT ma_vai_tro, ten_vai_tro FROM vai_tro ORDER BY id')->fetchAll();
$roles = [];
foreach ($rolesRows as $row) {
    $roles[$row['ma_vai_tro']] = $row['ten_vai_tro'];
}

// ─── Training program ────────────────────────────────────────────────────────
$programRow = $pdo->query('SELECT * FROM chuong_trinh_dao_tao ORDER BY id LIMIT 1')->fetch();
$trainingProgram = [
    'name'   => $programRow['ten_chuong_trinh'] ?? 'Công nghệ thông tin',
    'code'   => $programRow['ma_chuong_trinh'] ?? '7480201',
    'degree' => $programRow['trinh_do_dao_tao'] ?? 'Đại học chính quy',
    'school' => 'Trường Đại học Tài chính - Ngân hàng Hà Nội',
    'cycle'  => $programRow['chu_ky_kiem_dinh'] ?? 'Chu kỳ kiểm định 2026-2031',
];

// ─── Current user ────────────────────────────────────────────────────────────
$sessionUserId = $_SESSION['user_id'] ?? null;
$userRow = null;
if ($sessionUserId) {
    $stmt = $pdo->prepare("
        SELECT u.*, v.ma_vai_tro AS role_code, v.ten_vai_tro AS role_name, d.ten_don_vi AS department_name
        FROM nguoi_dung u
        JOIN vai_tro v ON v.id = u.id_vai_tro
        LEFT JOIN don_vi d ON d.id = u.id_don_vi
        WHERE u.id = :id
        LIMIT 1
    ");
    $stmt->execute(['id' => $sessionUserId]);
    $userRow = $stmt->fetch();
}

$currentUser = [
    'id'         => $userRow['id'] ?? 1,
    'name'       => $userRow['ho_ten'] ?? 'Quản trị viên',
    'role'       => $userRow['role_code'] ?? 'admin',
    'department' => $userRow['department_name'] ?? 'Khoa Công nghệ thông tin',
    'avatar'     => $userRow['duong_dan_anh_dai_dien'] ?? null,
];

// ─── Standard sets ───────────────────────────────────────────────────────────
$standardSets = [];
$stmt = $pdo->query('SELECT id, ten_bo_tieu_chuan, nam_ban_hanh, trang_thai FROM bo_tieu_chuan ORDER BY id');
foreach ($stmt->fetchAll() as $row) {
    $standardSets[] = [
        'id'      => (int) $row['id'],
        'name'    => $row['ten_bo_tieu_chuan'],
        'version' => $row['nam_ban_hanh'],
        'status'  => $row['trang_thai'] === 'active' ? 'Đang áp dụng' : 'Ngưng áp dụng',
    ];
}

// ─── Standards ───────────────────────────────────────────────────────────────
$standards = [];
$stmt = $pdo->query("
    SELECT
        s.id,
        s.ma_tieu_chuan AS code,
        s.ten_tieu_chuan AS name,
        COUNT(DISTINCT c.id) AS criteria_count,
        COUNT(DISTINCT ec.id_minh_chung) AS evidence_count,
        SUM(CASE WHEN c.trang_thai IN ('missing', 'thieu_minh_chung') THEN 1 ELSE 0 END) AS missing_count,
        SUM(CASE WHEN c.trang_thai IN ('need_update', 'can_bo_sung') THEN 1 ELSE 0 END) AS need_update_count
    FROM tieu_chuan s
    LEFT JOIN tieu_chi c ON c.id_tieu_chuan = s.id
    LEFT JOIN minh_chung_tieu_chi ec ON ec.id_tieu_chi = c.id
    GROUP BY s.id, s.ma_tieu_chuan, s.ten_tieu_chuan, s.thu_tu_hien_thi
    ORDER BY s.thu_tu_hien_thi, s.id
");
foreach ($stmt->fetchAll() as $row) {
    $standards[] = [
        'id'        => (int) $row['id'],
        'code'      => $row['code'],
        'name'      => $row['name'],
        'criteria'  => (int) $row['criteria_count'],
        'evidences' => (int) $row['evidence_count'],
        'status'    => standard_status_from_counts((int) $row['missing_count'], (int) $row['need_update_count']),
    ];
}

// ─── Criteria ────────────────────────────────────────────────────────────────
$criteria = [];
$stmt = $pdo->query("
    SELECT
        c.id,
        c.ma_tieu_chi AS code,
        c.ten_tieu_chi AS name,
        c.noi_dung_mo_ta AS description,
        c.trang_thai AS evidence_status,
        s.ma_tieu_chuan AS standard_code,
        COALESCE(d.ten_don_vi, 'Chưa phân công') AS owner,
        COUNT(ec.id_minh_chung) AS evidence_count
    FROM tieu_chi c
    JOIN tieu_chuan s ON s.id = c.id_tieu_chuan
    LEFT JOIN don_vi d ON d.id = c.id_don_vi
    LEFT JOIN minh_chung_tieu_chi ec ON ec.id_tieu_chi = c.id
    GROUP BY c.id, c.ma_tieu_chi, c.ten_tieu_chi, c.noi_dung_mo_ta, c.trang_thai, s.ma_tieu_chuan, d.ten_don_vi, c.thu_tu_hien_thi, s.thu_tu_hien_thi
    ORDER BY s.thu_tu_hien_thi, c.thu_tu_hien_thi, c.id
");
foreach ($stmt->fetchAll() as $row) {
    $criteria[] = [
        'id'          => (int) $row['id'],
        'code'        => $row['code'],
        'standard'    => $row['standard_code'],
        'name'        => $row['name'],
        'description' => $row['description'] ?? '',
        'owner'       => $row['owner'],
        'status_raw'  => $row['evidence_status'],
        'status'      => vn_criteria_status($row['evidence_status']),
        'evidences'   => (int) $row['evidence_count'],
    ];
}

// ─── Evidences ───────────────────────────────────────────────────────────────
$evidences = [];
$stmt = $pdo->query("
    SELECT
        e.id,
        e.ma_minh_chung AS code,
        e.tieu_de AS title,
        e.mo_ta AS description,
        e.nam_hoc AS academic_year,
        e.ngay_ban_hanh AS issued_date,
        COALESCE(e.loai_minh_chung, 'Minh chứng chính') AS evidence_type,
        e.trang_thai_duyet AS approval_status,
        DATE_FORMAT(e.ngay_cap_nhat, '%d/%m/%Y') AS updated_date,
        COALESCE(d.ten_don_vi, 'Chưa xác định') AS department_name,
        latest_file.id AS file_id,
        COALESCE(latest_file.loai_file, 'N/A') AS file_type,
        COALESCE(latest_file.so_phien_ban, 1) AS version_no,
        COALESCE(GROUP_CONCAT(DISTINCT s.ma_tieu_chuan ORDER BY s.ma_tieu_chuan SEPARATOR ', '), 'Chưa gắn') AS standard_codes,
        COALESCE(GROUP_CONCAT(DISTINCT c.ma_tieu_chi ORDER BY c.ma_tieu_chi SEPARATOR ', '), 'Chưa gắn') AS criteria_codes
    FROM minh_chung e
    LEFT JOIN don_vi d ON d.id = e.id_don_vi_phu_trach
    LEFT JOIN file_minh_chung latest_file ON latest_file.id = (
        SELECT ef2.id
        FROM file_minh_chung ef2
        WHERE ef2.id_minh_chung = e.id
        ORDER BY ef2.so_phien_ban DESC, ef2.ngay_tai_len DESC, ef2.id DESC
        LIMIT 1
    )
    LEFT JOIN minh_chung_tieu_chi ec ON ec.id_minh_chung = e.id
    LEFT JOIN tieu_chi c ON c.id = ec.id_tieu_chi
    LEFT JOIN tieu_chuan s ON s.id = c.id_tieu_chuan
    GROUP BY e.id, e.ma_minh_chung, e.tieu_de, e.mo_ta, e.nam_hoc, e.ngay_ban_hanh, e.loai_minh_chung, e.trang_thai_duyet, e.ngay_cap_nhat, d.ten_don_vi, latest_file.id, latest_file.loai_file, latest_file.so_phien_ban
    ORDER BY e.ngay_cap_nhat DESC, e.id DESC
");
foreach ($stmt->fetchAll() as $row) {
    $evidences[] = [
        'id'            => (int) $row['id'],
        'code'          => $row['code'],
        'name'          => $row['title'],
        'description'   => $row['description'] ?? '',
        'issued_date'   => $row['issued_date'] ?? '',
        'evidence_type' => $row['evidence_type'] ?? 'Minh chứng chính',
        'criteria'      => $row['criteria_codes'],
        'year'          => $row['academic_year'],
        'department'    => $row['department_name'],
        'file_id'       => (int) ($row['file_id'] ?? 0),
        'type'          => $row['file_type'],
        'version'       => (int) ($row['version_no'] ?? 1),
        'standards'     => $row['standard_codes'],
        'updated'       => $row['updated_date'],
        'status_raw'    => $row['approval_status'],
        'status'        => vn_approval_status($row['approval_status']),
    ];
}

// ─── Users ───────────────────────────────────────────────────────────────────
$users = [];
$stmt = $pdo->query("
    SELECT
        u.id,
        COALESCE(u.ma_nguoi_dung, CONCAT('ND', LPAD(u.id, 3, '0'))) AS user_code,
        u.ho_ten,
        u.ten_dang_nhap,
        u.email,
        u.trang_thai,
        u.duong_dan_anh_dai_dien AS avatar,
        v.ten_vai_tro AS role_name,
        COALESCE(d.ten_don_vi, 'Chưa phân đơn vị') AS department_name
    FROM nguoi_dung u
    JOIN vai_tro v ON v.id = u.id_vai_tro
    LEFT JOIN don_vi d ON d.id = u.id_don_vi
    ORDER BY u.id
");
foreach ($stmt->fetchAll() as $row) {
    $users[] = [
        'id'         => (int) $row['id'],
        'code'       => $row['user_code'],
        'name'       => $row['ho_ten'],
        'username'   => $row['ten_dang_nhap'],
        'email'      => $row['email'],
        'role'       => $row['role_name'],
        'department' => $row['department_name'],
        'avatar'     => $row['avatar'],
        'status_raw' => $row['trang_thai'],
        'status'     => vn_user_status($row['trang_thai']),
    ];
}

// ─── Activity logs ───────────────────────────────────────────────────────────
$activityLogs = [];
$stmt = $pdo->query("
    SELECT
        DATE_FORMAT(al.ngay_tao, '%d/%m/%Y %H:%i') AS action_time,
        COALESCE(u.ho_ten, 'Hệ thống') AS actor,
        al.hanh_dong,
        al.phan_he,
        al.ten_ban_ghi,
        al.id_ban_ghi
    FROM audit_logs al
    LEFT JOIN nguoi_dung u ON u.id = al.id_nguoi_dung
    ORDER BY al.ngay_tao DESC
    LIMIT 6
");
foreach ($stmt->fetchAll() as $row) {
    $moduleNames = [
        'minh_chung'  => 'Minh chứng',
        'evidences'   => 'Minh chứng',
        'tieu_chuan'  => 'Tiêu chuẩn',
        'standards'   => 'Tiêu chuẩn',
        'tieu_chi'    => 'Tiêu chí',
        'criteria'    => 'Tiêu chí',
        'don_vi'      => 'Đơn vị',
        'departments' => 'Đơn vị',
        'nguoi_dung'  => 'Tài khoản',
        'users'       => 'Tài khoản',
    ];
    $actionNames = [
        'them_moi'            => 'Thêm mới',
        'cap_nhat'            => 'Cập nhật',
        'xoa'                 => 'Xóa',
        'xem'                 => 'Xem chi tiết',
        'tai_ve'              => 'Tải xuống file',
        'cap_nhat_trang_thai' => 'Cập nhật trạng thái',
        'ra_soat'             => 'Rà soát',
        'create'              => 'Thêm mới',
        'update'              => 'Cập nhật',
        'delete'              => 'Xóa',
        'status_update'       => 'Cập nhật trạng thái',
        'review'              => 'Rà soát',
    ];

    $actLabel = $actionNames[$row['hanh_dong']] ?? $row['hanh_dong'];
    $modLabel = $moduleNames[$row['phan_he']] ?? $row['phan_he'];
    $target   = $row['ten_ban_ghi'] ? $row['ten_ban_ghi'] : ('#' . $row['id_ban_ghi']);

    $activityLogs[] = [
        'time'   => $row['action_time'],
        'actor'  => $row['actor'],
        'action' => $actLabel . ' ' . mb_strtolower($modLabel) . ': ' . $target,
        'module' => $modLabel,
    ];
}
?>
