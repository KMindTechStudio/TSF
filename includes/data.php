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
            return 'Đủ minh chứng';
        case 'need_update':
            return 'Cần bổ sung';
        case 'missing':
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
    return $status === 'active' ? 'Hoạt động' : 'Tạm khóa';
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

$rolesRows = $pdo->query('SELECT code, name FROM roles ORDER BY id')->fetchAll();
$roles = [];
foreach ($rolesRows as $row) {
    $roles[$row['code']] = $row['name'];
}

$programRow = $pdo->query('SELECT * FROM training_programs ORDER BY id LIMIT 1')->fetch();
$trainingProgram = [
    'name' => $programRow['name'] ?? 'Công nghệ thông tin',
    'code' => $programRow['code'] ?? '7480201',
    'degree' => $programRow['degree_level'] ?? 'Đại học chính quy',
    'school' => 'Trường Đại học Tài chính - Ngân hàng Hà Nội',
    'cycle' => $programRow['accreditation_cycle'] ?? 'Chu kỳ kiểm định 2026-2031',
];

$sessionUserId = $_SESSION['user_id'] ?? null;
if ($sessionUserId) {
    $stmt = $pdo->prepare("
        SELECT u.*, r.code AS role_code, r.name AS role_name, d.name AS department_name
        FROM users u
        JOIN roles r ON r.id = u.role_id
        LEFT JOIN departments d ON d.id = u.department_id
        WHERE u.id = :id
        LIMIT 1
    ");
    $stmt->execute(['id' => $sessionUserId]);
    $userRow = $stmt->fetch();
}

$currentUser = [
    'id' => $userRow['id'] ?? 1,
    'name' => $userRow['full_name'] ?? 'Quản trị viên',
    'role' => $userRow['role_code'] ?? 'admin',
    'department' => $userRow['department_name'] ?? 'Khoa Công nghệ thông tin',
    'avatar' => $userRow['avatar_path'] ?? null,
];

$standardSets = [];
$stmt = $pdo->query('SELECT id, name, version_year, status FROM standard_sets ORDER BY id');
foreach ($stmt->fetchAll() as $row) {
    $standardSets[] = [
        'id' => (int) $row['id'],
        'name' => $row['name'],
        'version' => $row['version_year'],
        'status' => $row['status'] === 'active' ? 'Đang áp dụng' : 'Ngưng áp dụng',
    ];
}

$standards = [];
$stmt = $pdo->query("
    SELECT
        s.id,
        s.code,
        s.name,
        COUNT(DISTINCT c.id) AS criteria_count,
        COUNT(DISTINCT ec.evidence_id) AS evidence_count,
        SUM(CASE WHEN c.evidence_status = 'missing' THEN 1 ELSE 0 END) AS missing_count,
        SUM(CASE WHEN c.evidence_status = 'need_update' THEN 1 ELSE 0 END) AS need_update_count
    FROM standards s
    LEFT JOIN criteria c ON c.standard_id = s.id
    LEFT JOIN evidence_criteria ec ON ec.criteria_id = c.id
    GROUP BY s.id, s.code, s.name, s.display_order
    ORDER BY s.display_order, s.id
");
foreach ($stmt->fetchAll() as $row) {
    $standards[] = [
        'id' => (int) $row['id'],
        'code' => $row['code'],
        'name' => $row['name'],
        'criteria' => (int) $row['criteria_count'],
        'evidences' => (int) $row['evidence_count'],
        'status' => standard_status_from_counts((int) $row['missing_count'], (int) $row['need_update_count']),
    ];
}

$criteria = [];
$stmt = $pdo->query("
    SELECT
        c.id,
        c.code,
        c.name,
        c.evidence_status,
        s.code AS standard_code,
        COALESCE(d.name, 'Chưa phân công') AS owner,
        COUNT(ec.evidence_id) AS evidence_count
    FROM criteria c
    JOIN standards s ON s.id = c.standard_id
    LEFT JOIN departments d ON d.id = c.department_id
    LEFT JOIN evidence_criteria ec ON ec.criteria_id = c.id
    GROUP BY c.id, c.code, c.name, c.evidence_status, s.code, d.name, c.display_order, s.display_order
    ORDER BY s.display_order, c.display_order, c.id
");
foreach ($stmt->fetchAll() as $row) {
    $criteria[] = [
        'id' => (int) $row['id'],
        'code' => $row['code'],
        'standard' => $row['standard_code'],
        'name' => $row['name'],
        'owner' => $row['owner'],
        'status_raw' => $row['evidence_status'],
        'status' => vn_criteria_status($row['evidence_status']),
        'evidences' => (int) $row['evidence_count'],
    ];
}

$evidences = [];
$stmt = $pdo->query("
    SELECT
        e.id,
        e.code,
        e.title,
        e.academic_year,
        e.approval_status,
        DATE_FORMAT(e.updated_at, '%d/%m/%Y') AS updated_date,
        COALESCE(d.name, 'Chưa xác định') AS department_name,
        MIN(ef.id) AS file_id,
        COALESCE(MAX(ef.file_type), 'N/A') AS file_type,
        COALESCE(GROUP_CONCAT(DISTINCT c.code ORDER BY c.code SEPARATOR ', '), 'Chưa gắn') AS criteria_codes
    FROM evidences e
    LEFT JOIN departments d ON d.id = e.issuing_department_id
    LEFT JOIN evidence_files ef ON ef.evidence_id = e.id
    LEFT JOIN evidence_criteria ec ON ec.evidence_id = e.id
    LEFT JOIN criteria c ON c.id = ec.criteria_id
    GROUP BY e.id, e.code, e.title, e.academic_year, e.approval_status, e.updated_at, d.name
    ORDER BY e.updated_at DESC, e.id DESC
");
foreach ($stmt->fetchAll() as $row) {
    $evidences[] = [
        'id' => (int) $row['id'],
        'code' => $row['code'],
        'name' => $row['title'],
        'criteria' => $row['criteria_codes'],
        'year' => $row['academic_year'],
        'department' => $row['department_name'],
        'file_id' => (int) ($row['file_id'] ?? 0),
        'type' => $row['file_type'],
        'updated' => $row['updated_date'],
        'status_raw' => $row['approval_status'],
        'status' => vn_approval_status($row['approval_status']),
    ];
}

$users = [];
$stmt = $pdo->query("
    SELECT
        u.id,
        u.full_name,
        u.username,
        u.email,
        u.status,
        r.name AS role_name,
        COALESCE(d.name, 'Chưa phân đơn vị') AS department_name
    FROM users u
    JOIN roles r ON r.id = u.role_id
    LEFT JOIN departments d ON d.id = u.department_id
    ORDER BY u.id
");
foreach ($stmt->fetchAll() as $row) {
    $users[] = [
        'id' => (int) $row['id'],
        'name' => $row['full_name'],
        'username' => $row['username'],
        'email' => $row['email'],
        'role' => $row['role_name'],
        'department' => $row['department_name'],
        'status_raw' => $row['status'],
        'status' => vn_user_status($row['status']),
    ];
}

$activityLogs = [];
$stmt = $pdo->query("
    SELECT
        DATE_FORMAT(al.created_at, '%d/%m/%Y %H:%i') AS action_time,
        COALESCE(u.full_name, 'Hệ thống') AS actor,
        al.action,
        al.module,
        al.record_id
    FROM audit_logs al
    LEFT JOIN users u ON u.id = al.user_id
    ORDER BY al.created_at DESC
    LIMIT 5
");
foreach ($stmt->fetchAll() as $row) {
    $activityLogs[] = [
        'time' => $row['action_time'],
        'actor' => $row['actor'],
        'action' => strtoupper($row['action']) . ' #' . $row['record_id'],
        'module' => $row['module'],
    ];
}
?>
