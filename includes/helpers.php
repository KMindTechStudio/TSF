<?php
require_once __DIR__ . '/../config/database.php';
date_default_timezone_set('Asia/Ho_Chi_Minh');

if (!function_exists('str_contains')) {
    function str_contains(string $haystack, string $needle): bool
    {
        return $needle === '' || mb_strpos($haystack, $needle) !== false;
    }
}

function base_url(string $path = ''): string
{
    $script = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
    $root = preg_replace('#/(admin|user|auth|includes)$#', '', $script);
    $root = rtrim($root, '/');
    return $root . '/' . ltrim($path, '/');
}

function app_cookie_path(): string
{
    $script = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    $root = preg_replace('#/(admin|user|auth|includes)$#', '', $script);
    $root = '/' . trim((string) $root, '/');

    return $root === '/' ? '/' : $root . '/';
}


function ensure_remember_tokens_table(): void
{
    static $created = false;

    if ($created) {
        return;
    }

    db()->exec("
        CREATE TABLE IF NOT EXISTS remember_tokens (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            MaNguoiDung VARCHAR(50) NOT NULL,
            ma_token VARCHAR(255) NOT NULL UNIQUE,
            het_han DATETIME NOT NULL,
            ngay_tao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_remember_tokens_nguoi_dung FOREIGN KEY (MaNguoiDung) REFERENCES NguoiDung(MaNguoiDung) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $created = true;
}

function create_login_token($userId, bool $remember = false): void
{
    ensure_remember_tokens_table();

    $token = bin2hex(random_bytes(32));
    $days = $remember ? 30 : 7;
    $expiresAt = (new DateTimeImmutable('+' . $days . ' days'))->format('Y-m-d H:i:s');

    $stmt = db()->prepare('INSERT INTO remember_tokens (MaNguoiDung, ma_token, het_han) VALUES (:user_id, :token_hash, :expires_at)');
    $stmt->execute([
        'user_id' => $userId,
        'token_hash' => hash('sha256', $token),
        'expires_at' => $expiresAt,
    ]);

    setcookie('kiemdinh_login', $token, [
        'expires' => time() + ($days * 86400),
        'path' => app_cookie_path(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function clear_login_token(): void
{
    $token = $_COOKIE['kiemdinh_login'] ?? '';

    if ($token !== '') {
        ensure_remember_tokens_table();
        $stmt = db()->prepare('DELETE FROM remember_tokens WHERE ma_token = :token_hash');
        $stmt->execute(['token_hash' => hash('sha256', $token)]);
    }

    setcookie('kiemdinh_login', '', [
        'expires' => time() - 3600,
        'path' => app_cookie_path(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function restore_login_from_cookie(): bool
{
    if (!empty($_SESSION['user_id'])) {
        return true;
    }

    $token = $_COOKIE['kiemdinh_login'] ?? '';

    if ($token === '') {
        return false;
    }

    ensure_remember_tokens_table();

    $stmt = db()->prepare("
        SELECT rt.id AS token_id, rt.MaNguoiDung AS user_id, u.TrangThai AS status, u.VaiTro AS role_code
        FROM remember_tokens rt
        JOIN NguoiDung u ON u.MaNguoiDung = rt.MaNguoiDung
        WHERE rt.ma_token = :token_hash
          AND rt.het_han > NOW()
        LIMIT 1
    ");
    $stmt->execute(['token_hash' => hash('sha256', $token)]);
    $record = $stmt->fetch();

    if (!$record || (int) $record['status'] !== 1) {
        clear_login_token();
        return false;
    }

    $_SESSION['user_id'] = $record['user_id'];
    $_SESSION['role'] = $record['role_code'];

    return true;
}

function current_role(): ?string
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    restore_login_from_cookie();

    return $_SESSION['role'] ?? null;
}

function require_login(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    restore_login_from_cookie();

    if (empty($_SESSION['user_id'])) {
        header('Location: ' . base_url('auth/login.php'));
        exit;
    }
}

function require_roles(array $allowedRoles): void
{
    require_login();

    if (!in_array(current_role(), $allowedRoles, true)) {
        $fallback = current_role() === 'admin'
            ? base_url('admin/dashboard.php')
            : base_url('admin/evidences.php');

        header('Location: ' . $fallback);
        exit;
    }
}

function user_can_manage_accounts(): bool
{
    return current_role() === 'admin';
}

function user_can_manage_accreditation(): bool
{
    return current_role() === 'admin';
}

function log_activity(string $action, string $module, ?int $recordId = null, ?string $recordName = null, ?array $newValue = null): void
{
    try {
        $pdo = db();
        $userId = $_SESSION['user_id'] ?? null;
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $jsonVal = $newValue !== null ? json_encode($newValue, JSON_UNESCAPED_UNICODE) : null;

        $stmt = $pdo->prepare("
            INSERT INTO audit_logs (MaNguoiDung, hanh_dong, phan_he, ten_ban_ghi, id_ban_ghi, gia_tri_moi, dia_chi_ip)
            VALUES (:user_id, :action, :module, :record_name, :record_id, :new_val, :ip)
        ");
        $stmt->execute([
            'user_id'     => $userId,
            'action'      => $action,
            'module'      => $module,
            'record_name' => $recordName,
            'record_id'   => $recordId,
            'new_val'     => $jsonVal,
            'ip'          => $ip,
        ]);
    } catch (Throwable $e) {
        // Silently swallow audit logging errors
    }
}

function is_active(string $page): string
{
    $current = basename($_SERVER['PHP_SELF']);
    return $current === $page ? 'active' : '';
}

function status_class(string $status): string
{
    $cleanMap = [
        'Đủ minh chứng' => 'success',
        'Đã duyệt' => 'success',
        'Hoạt động' => 'success',
        'Đang hoạt động' => 'success',
        'Cần bổ sung' => 'warning',
        'Chờ rà soát' => 'warning',
        'Thiếu minh chứng' => 'danger',
        'Tạm khóa' => 'danger',
    ];

    if (isset($cleanMap[$status])) {
        return $cleanMap[$status];
    }

    switch ($status) {
        case 'Đủ minh chứng':
        case 'Đã duyệt':
        case 'Hoạt động':
        case 'Đang áp dụng':
            return 'success';
        case 'Cần bổ sung':
        case 'Chờ rà soát':
            return 'warning';
        case 'Thiếu minh chứng':
        case 'Tạm khóa':
            return 'danger';
        default:
            return 'secondary';
    }
}

function status_icon(string $status): string
{
    $cleanMap = [
        'Đủ minh chứng' => 'bi-check-circle-fill',
        'Đã duyệt' => 'bi-check-circle-fill',
        'Hoạt động' => 'bi-check-circle-fill',
        'Đang áp dụng' => 'bi-check-circle-fill',
        'Cần bổ sung' => 'bi-exclamation-circle-fill',
        'Chờ rà soát' => 'bi-exclamation-circle-fill',
        'Thiếu minh chứng' => 'bi-exclamation-triangle-fill',
        'Tạm khóa' => 'bi-exclamation-triangle-fill',
    ];

    if (isset($cleanMap[$status])) {
        return $cleanMap[$status];
    }

    switch ($status) {
        case 'Đủ minh chứng':
        case 'Đã duyệt':
        case 'Hoạt động':
        case 'Đang áp dụng':
            return 'bi-check-circle-fill';
        case 'Cần bổ sung':
        case 'Chờ rà soát':
            return 'bi-exclamation-circle-fill';
        case 'Thiếu minh chứng':
        case 'Tạm khóa':
            return 'bi-exclamation-triangle-fill';
        default:
            return 'bi-info-circle-fill';
    }
}

function status_badge(string $status): string
{
    $class = status_class($status);
    $icon = status_icon($status);
    $label = htmlspecialchars($status, ENT_QUOTES, 'UTF-8');

    return '<span class="badge status-badge text-bg-' . $class . '"><i class="bi ' . $icon . '"></i>' . $label . '</span>';
}

function status_value_from_label(string $status): string
{
    $map = [
        'Đã duyệt' => 'approved',
        'Chờ rà soát' => 'reviewing',
        'Cần bổ sung' => 'need_update',
        'Đủ minh chứng' => 'complete',
        'Thiếu minh chứng' => 'missing',
        'Hoạt động' => 'active',
        'Tạm khóa' => 'locked',
        'Đang áp dụng' => 'active_set',
        'Ngưng áp dụng' => 'inactive_set',
        'Đã duyệt' => 'approved',
        'Chờ rà soát' => 'reviewing',
        'Cần bổ sung' => 'need_update',
        'Đủ minh chứng' => 'complete',
        'Thiếu minh chứng' => 'missing',
        'Hoạt động' => 'active',
        'Tạm khóa' => 'locked',
        'Đang áp dụng' => 'active_set',
        'Ngưng áp dụng' => 'inactive_set',
    ];

    return $map[$status] ?? strtolower(preg_replace('/[^a-z0-9_]+/i', '_', $status));
}

function status_select_tone(string $value): string
{
    switch ($value) {
        case 'success':
        case 'warning':
        case 'danger':
        case 'secondary':
            return $value;
        case 'approved':
        case 'complete':
        case 'active':
        case 'active_set':
            return 'success';
        case 'reviewing':
        case 'need_update':
            return 'warning';
        case 'missing':
        case 'locked':
        case 'inactive_set':
            return 'danger';
        default:
            return 'secondary';
    }
}

function status_select(
    string $currentValue,
    array $options,
    string $name = 'status',
    bool $disabled = false,
    string $extraClass = '',
    string $ariaLabel = 'Trạng thái'
): string {
    $tone = status_select_tone($currentValue);
    $safeValueClass = preg_replace('/[^a-z0-9_\-]+/i', '_', $currentValue);
    $classes = trim('form-select form-select-sm status-select status-select-' . $tone . ' status-select-' . $safeValueClass . ' ' . $extraClass);
    $html = '<select class="' . htmlspecialchars($classes, ENT_QUOTES, 'UTF-8') . '" name="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '" aria-label="' . htmlspecialchars($ariaLabel, ENT_QUOTES, 'UTF-8') . '"' . ($disabled ? ' disabled' : '') . '>';

    foreach ($options as $value => $label) {
        $html .= '<option value="' . htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') . '"' . ((string) $value === $currentValue ? ' selected' : '') . '>' . htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8') . '</option>';
    }

    $html .= '</select>';

    return $html;
}

function readonly_status_select(string $status): string
{
    $value = status_value_from_label($status);
    $tone = status_select_tone($value);
    $safeValueClass = preg_replace('/[^a-z0-9_\-]+/i', '_', $value);
    $label = htmlspecialchars($status, ENT_QUOTES, 'UTF-8');

    return '<span class="status-select status-select-readonly status-select-' . htmlspecialchars($tone, ENT_QUOTES, 'UTF-8') . ' status-select-' . htmlspecialchars($safeValueClass, ENT_QUOTES, 'UTF-8') . '" aria-label="Trạng thái"><span>' . $label . '</span></span>';
}

function normalize_search_text(string $text): string
{
    $text = mb_strtolower($text, 'UTF-8');
    $map = [
        'à' => 'a', 'á' => 'a', 'ạ' => 'a', 'ả' => 'a', 'ã' => 'a', 'â' => 'a', 'ầ' => 'a', 'ấ' => 'a', 'ậ' => 'a', 'ẩ' => 'a', 'ẫ' => 'a', 'ă' => 'a', 'ằ' => 'a', 'ắ' => 'a', 'ặ' => 'a', 'ẳ' => 'a', 'ẵ' => 'a',
        'è' => 'e', 'é' => 'e', 'ẹ' => 'e', 'ẻ' => 'e', 'ẽ' => 'e', 'ê' => 'e', 'ề' => 'e', 'ế' => 'e', 'ệ' => 'e', 'ể' => 'e', 'ễ' => 'e',
        'ì' => 'i', 'í' => 'i', 'ị' => 'i', 'ỉ' => 'i', 'ĩ' => 'i',
        'ò' => 'o', 'ó' => 'o', 'ọ' => 'o', 'ỏ' => 'o', 'õ' => 'o', 'ô' => 'o', 'ồ' => 'o', 'ố' => 'o', 'ộ' => 'o', 'ổ' => 'o', 'ỗ' => 'o', 'ơ' => 'o', 'ờ' => 'o', 'ớ' => 'o', 'ợ' => 'o', 'ở' => 'o', 'ỡ' => 'o',
        'ù' => 'u', 'ú' => 'u', 'ụ' => 'u', 'ủ' => 'u', 'ũ' => 'u', 'ư' => 'u', 'ừ' => 'u', 'ứ' => 'u', 'ự' => 'u', 'ử' => 'u', 'ữ' => 'u',
        'ỳ' => 'y', 'ý' => 'y', 'ỵ' => 'y', 'ỷ' => 'y', 'ỹ' => 'y',
        'đ' => 'd',
    ];

    return strtr($text, $map);
}

function search_contains(string $haystack, string $needle): bool
{
    return mb_stripos(normalize_search_text($haystack), normalize_search_text($needle), 0, 'UTF-8') !== false;
}

function highlight_search_text(?string $text, string $keyword): string
{
    if ($text === null || $text === '') {
        return '';
    }
    $escaped = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    $kw = trim($keyword);
    if ($kw === '') {
        return $escaped;
    }
    $escapedKw = htmlspecialchars($kw, ENT_QUOTES, 'UTF-8');
    $kwRegex = preg_quote($escapedKw, '/');
    return preg_replace('/(' . $kwRegex . ')/iu', '<mark class="search-matched-text">$1</mark>', $escaped);
}

function user_initials(string $name): string
{
    $name = trim($name);
    if ($name === '') {
        return 'U';
    }

    $parts = preg_split('/\s+/u', $name);
    $first = mb_substr($parts[0] ?? 'U', 0, 1, 'UTF-8');
    $last = mb_substr($parts[count($parts) - 1] ?? $first, 0, 1, 'UTF-8');

    return mb_strtoupper($first . $last, 'UTF-8');
}

function avatar_html(?string $avatarPath, ?string $name = 'User', string $class = 'avatar'): string
{
    $name = (string)($name ?: 'User');
    if ($avatarPath) {
        return '<span class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . ' avatar-image"><img src="' . base_url($avatarPath) . '" alt="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '"></span>';
    }

    return '<span class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars(user_initials($name), ENT_QUOTES, 'UTF-8') . '</span>';
}

function page_title(string $title): string
{
    global $appName;
    return $title . ' | ' . $appName;
}

function export_to_excel(string $filename, string $title, array $columns, array $data): void
{
    date_default_timezone_set('Asia/Ho_Chi_Minh');

    if (ob_get_length()) {
        ob_end_clean();
    }

    $exportedAt = date('H:i:s - d/m/Y');
    $currentUser = function_exists('current_user') ? current_user() : [];
    $exportedBy = $currentUser['name'] ?? ($_SESSION['user_name'] ?? 'Quản trị viên');

    // Tự động loại bỏ cột 'stt' nếu caller đã định nghĩa để tránh trùng lặp 2 cột STT
    $cleanedColumns = [];
    foreach ($columns as $col) {
        if (($col['key'] ?? '') !== 'stt') {
            $cleanedColumns[] = $col;
        }
    }
    $totalCols = count($cleanedColumns) + 1; // +1 cho cột STT
    $leftColspan = max(2, (int)floor($totalCols / 2));
    $rightColspan = max(1, $totalCols - $leftColspan);

    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0, no-cache, must-revalidate');
    header('Pragma: public');

    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
    echo '<head>';
    echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">';
    echo '<!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>Báo cáo</x:Name><x:WorksheetOptions><x:DisplayGridlines/><x:Print><x:ValidPrinterInfo/></x:Print></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]-->';
    echo '<style>';
    echo 'body { font-family: "Times New Roman", "Segoe UI", Arial, sans-serif; font-size: 11pt; color: #1e293b; background-color: #ffffff; }';
    echo 'table { border-collapse: collapse; width: 100%; }';
    echo '.school-title { font-size: 11pt; font-weight: bold; color: #1e3a8a; text-transform: uppercase; text-align: center; }';
    echo '.dept-title { font-size: 10.5pt; font-weight: bold; color: #334155; text-align: center; }';
    echo '.national-title { font-size: 11pt; font-weight: bold; color: #0f172a; text-transform: uppercase; text-align: center; }';
    echo '.national-sub { font-size: 10.5pt; font-weight: bold; color: #0f172a; text-align: center; text-decoration: underline; }';
    echo '.doc-main-title { font-size: 16pt; font-weight: bold; color: #1e3a8a; text-transform: uppercase; text-align: center; padding: 14px 0 6px 0; }';
    echo '.meta-desc { font-size: 10pt; color: #475569; font-style: italic; text-align: center; padding-bottom: 12px; }';
    echo 'th.tbl-hdr { background-color: #1e3a8a; color: #ffffff; font-weight: bold; border: 1.5pt solid #0f295c; padding: 10px 8px; text-align: center; font-size: 11pt; vertical-align: middle; }';
    echo 'td.tbl-cell { border: 1px solid #cbd5e1; padding: 8px 8px; vertical-align: middle; font-size: 10.5pt; }';
    echo 'tr.row-even td.tbl-cell { background-color: #f8fafc; }';
    echo 'tr.row-odd td.tbl-cell { background-color: #ffffff; }';
    echo '.text-center { text-align: center; }';
    echo '.text-right { text-align: right; }';
    echo '.text-left { text-align: left; }';
    echo '.text-bold { font-weight: bold; }';
    echo '.code-style { font-family: "Consolas", "Courier New", monospace; font-weight: bold; color: #1e40af; }';
    echo '.status-active { color: #166534; font-weight: bold; }';
    echo '.status-inactive { color: #64748b; }';
    echo '.summary-bar { background-color: #f1f5f9; font-weight: bold; border: 1.5pt solid #cbd5e1; padding: 8px 12px; }';
    echo '.date-sign-row { font-size: 10.5pt; font-style: italic; text-align: right; padding-top: 16px; padding-bottom: 8px; }';
    echo '.sign-hdr { font-size: 11pt; font-weight: bold; text-align: center; color: #0f172a; }';
    echo '.sign-sub { font-size: 9.5pt; font-style: italic; text-align: center; color: #64748b; }';
    echo '</style>';
    echo '</head>';
    echo '<body>';

    echo '<table cellpadding="0" cellspacing="0">';

    // 1. HEADER CƠ QUAN / ĐƠN VỊ
    echo '<tr>';
    echo '<td colspan="' . $leftColspan . '" class="school-title">TRƯỜNG ĐẠI HỌC TÀI CHÍNH - NGÂN HÀNG HÀ NỘI</td>';
    echo '<td colspan="' . $rightColspan . '" class="national-title">CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM</td>';
    echo '</tr>';

    echo '<tr>';
    echo '<td colspan="' . $leftColspan . '" class="dept-title">KHOA CÔNG NGHỆ THÔNG TIN</td>';
    echo '<td colspan="' . $rightColspan . '" class="national-sub">Độc lập - Tự do - Hạnh phúc</td>';
    echo '</tr>';

    echo '<tr>';
    echo '<td colspan="' . $leftColspan . '" style="text-align: center; font-size: 9pt; color: #64748b;">HỆ THỐNG QUẢN LÝ MINH CHỨNG KIỂM ĐỊNH</td>';
    echo '<td colspan="' . $rightColspan . '" style="text-align: center; font-size: 9pt; color: #94a3b8;">-------------------o0o-------------------</td>';
    echo '</tr>';

    // Dòng trống
    echo '<tr><td colspan="' . $totalCols . '" style="height: 15px;"></td></tr>';

    // 2. TIÊU ĐỀ BÁO CÁO
    echo '<tr>';
    echo '<td colspan="' . $totalCols . '" class="doc-main-title">' . htmlspecialchars(mb_strtoupper($title, 'UTF-8')) . '</td>';
    echo '</tr>';

    // 3. THÔNG TIN XUẤT BÁO CÁO (Múi giờ Việt Nam UTC+7)
    echo '<tr>';
    echo '<td colspan="' . $totalCols . '" class="meta-desc">';
    echo 'Thời gian xuất: <strong>' . htmlspecialchars($exportedAt) . ' (Giờ Việt Nam - GMT+7)</strong> &nbsp;|&nbsp; ';
    echo 'Người xuất: <strong>' . htmlspecialchars($exportedBy) . '</strong> &nbsp;|&nbsp; ';
    echo 'Tổng số: <strong>' . count($data) . ' bản ghi</strong>';
    echo '</td>';
    echo '</tr>';

    // Dòng trống
    echo '<tr><td colspan="' . $totalCols . '" style="height: 10px;"></td></tr>';

    // 4. TIÊU ĐỀ CỘT DỮ LIỆU (THEAD)
    echo '<tr>';
    echo '<th class="tbl-hdr" style="width: 55px;">STT</th>';
    foreach ($cleanedColumns as $col) {
        $widthStyle = isset($col['width']) ? ' style="width:' . $col['width'] . ';"' : '';
        echo '<th class="tbl-hdr"' . $widthStyle . '>' . htmlspecialchars($col['label']) . '</th>';
    }
    echo '</tr>';

    // 5. THÂN BẢNG DỮ LIỆU (TBODY)
    $stt = 1;
    if (empty($data)) {
        echo '<tr><td colspan="' . $totalCols . '" class="tbl-cell text-center" style="padding: 25px; color: #94a3b8; font-style: italic;">Không tìm thấy dữ liệu nào phù hợp với bộ lọc</td></tr>';
    } else {
        foreach ($data as $idx => $row) {
            $rowClass = ($idx % 2 === 0) ? 'row-even' : 'row-odd';
            echo '<tr class="' . $rowClass . '">';
            echo '<td class="tbl-cell text-center text-bold">' . $stt++ . '</td>';
            foreach ($cleanedColumns as $col) {
                $key = $col['key'];
                $align = $col['align'] ?? 'left';
                $val = (string)($row[$key] ?? '');

                $styleAttr = ' class="tbl-cell text-' . $align . '"';

                // Format chuỗi mã code dạng text để Excel không tự động đổi định dạng
                if (in_array($key, ['code', 'ma_bo', 'ma_tc', 'ma_tchi', 'ma_mc', 'phone'], true)) {
                    echo '<td' . $styleAttr . ' style="mso-number-format:\'\@\';"><span class="code-style">' . htmlspecialchars($val) . '</span></td>';
                } elseif ($key === 'status' || $key === 'trang_thai') {
                    $isAct = (mb_stripos($val, 'hoạt động') !== false || mb_stripos($val, 'active') !== false);
                    $statusClass = $isAct ? 'status-active' : 'status-inactive';
                    echo '<td' . $styleAttr . '><span class="' . $statusClass . '">' . htmlspecialchars($val) . '</span></td>';
                } elseif (in_array($key, ['date', 'ngay_ban_hanh', 'issue_date', 'created_at', 'updated_at'], true)) {
                    echo '<td' . $styleAttr . ' style="mso-number-format:\'dd\/mm\/yyyy\';">' . htmlspecialchars($val) . '</td>';
                } else {
                    echo '<td' . $styleAttr . ' style="mso-number-format:\'\@\';">' . htmlspecialchars($val) . '</td>';
                }
            }
            echo '</tr>';
        }

        // Dòng tổng kết
        echo '<tr>';
        echo '<td colspan="' . $totalCols . '" class="summary-bar text-left">';
        echo 'Tổng cộng: <strong>' . count($data) . '</strong> dòng dữ liệu đã được xuất.';
        echo '</td>';
        echo '</tr>';
    }

    // 6. PHẦN CHỮ KÝ VÀ NGÀY THÁNG XUẤT (CHUYÊN NGHIỆP)
    echo '<tr><td colspan="' . $totalCols . '" style="height: 20px;"></td></tr>';

    echo '<tr>';
    echo '<td colspan="' . $leftColspan . '"></td>';
    echo '<td colspan="' . $rightColspan . '" class="date-sign-row">Hà Nội, ngày ' . date('d') . ' tháng ' . date('m') . ' năm ' . date('Y') . ' (GMT+7)</td>';
    echo '</tr>';

    echo '<tr>';
    echo '<td colspan="' . $leftColspan . '" class="sign-hdr">NGƯỜI LẬP BÁO CÁO</td>';
    echo '<td colspan="' . $rightColspan . '" class="sign-hdr">TRƯỞNG ĐƠN VỊ / BAN KIỂM ĐỊNH</td>';
    echo '</tr>';

    echo '<tr>';
    echo '<td colspan="' . $leftColspan . '" class="sign-sub">(Ký và ghi rõ họ tên)</td>';
    echo '<td colspan="' . $rightColspan . '" class="sign-sub">(Ký, đóng dấu và ghi rõ họ tên)</td>';
    echo '</tr>';

    // Khoảng trống cho chữ ký
    echo '<tr><td colspan="' . $totalCols . '" style="height: 60px;"></td></tr>';

    echo '<tr>';
    echo '<td colspan="' . $leftColspan . '" style="text-align: center; font-weight: bold; color: #1e3a8a;">' . htmlspecialchars($exportedBy) . '</td>';
    echo '<td colspan="' . $rightColspan . '" style="text-align: center; font-weight: bold; color: #1e3a8a;"></td>';
    echo '</tr>';

    echo '</table>';
    echo '</body>';
    echo '</html>';
    exit;
}

/**
 * Xuất dữ liệu phân cấp 4 cấp: Bộ Tiêu chuẩn -> Tiêu chuẩn -> Tiêu chí -> Minh chứng
 * Có tích hợp Menu con sổ ra/gập vào đa tầng (Row Outlining / Grouping Levels 1, 2, 3) trực tiếp trong Excel.
 * Thiết kế giao diện báo cáo chuyên nghiệp, sang trọng, rõ ràng theo chuẩn đại học.
 */
function export_hierarchical_standards_excel(string $filename, array $filter = []): void
{
    date_default_timezone_set('Asia/Ho_Chi_Minh');

    if (ob_get_length()) {
        ob_end_clean();
    }

    $pdo = db();

    // 1. Phân tích tham số bộ lọc
    $searchKeyword   = trim($filter['q'] ?? $filter['keyword'] ?? $filter['search'] ?? '');
    $selectedSet     = trim($filter['standard_set'] ?? $filter['set'] ?? '');
    $selectedStd     = trim($filter['standard'] ?? '');
    $selectedCrit    = trim($filter['criterion'] ?? '');
    $selectedEv      = trim($filter['evidence'] ?? '');
    $selectedStatus  = isset($filter['status']) && $filter['status'] !== '' ? (int)$filter['status'] : null;

    $clauses = [];
    $params = [];

    if ($searchKeyword !== '') {
        $clauses[] = "(
            b.MaBoTieuChuan LIKE :kw_b1 
            OR b.TenBoTieuChuan LIKE :kw_b2 
            OR b.ThongTu LIKE :kw_b3 
            OR b.MoTa LIKE :kw_b4
            OR b.MaBoTieuChuan IN (
                SELECT tc_s.MaBoTieuChuan FROM TieuChuan tc_s 
                WHERE tc_s.MaTieuChuan LIKE :kw_tc1 OR tc_s.TenTieuChuan LIKE :kw_tc2 OR tc_s.MoTa LIKE :kw_tc3
            )
            OR b.MaBoTieuChuan IN (
                SELECT tc_c.MaBoTieuChuan FROM TieuChi tchi_s 
                JOIN TieuChuan tc_c ON tc_c.MaTieuChuan = tchi_s.MaTieuChuan
                WHERE tchi_s.MaTieuChi LIKE :kw_crit1 OR tchi_s.TenTieuChi LIKE :kw_crit2 OR tchi_s.NoiDung LIKE :kw_crit3
            )
            OR b.MaBoTieuChuan IN (
                SELECT DISTINCT COALESCE(m_s.MaBoTieuChuan, tc_m.MaBoTieuChuan)
                FROM MinhChung m_s
                LEFT JOIN TieuChi tchi_m ON tchi_m.MaTieuChi = m_s.MaTieuChi
                LEFT JOIN TieuChuan tc_m ON tc_m.MaTieuChuan = tchi_m.MaTieuChuan
                WHERE m_s.MaMinhChung LIKE :kw_ev1 OR m_s.TenMinhChung LIKE :kw_ev2 OR m_s.SoHieu LIKE :kw_ev_sohieu OR m_s.MoTa LIKE :kw_ev3 OR m_s.NamHoc LIKE :kw_ev4
            )
        )";
        $kwParam = "%$searchKeyword%";
        $params['kw_b1'] = $kwParam;
        $params['kw_b2'] = $kwParam;
        $params['kw_b3'] = $kwParam;
        $params['kw_b4'] = $kwParam;
        $params['kw_tc1'] = $kwParam;
        $params['kw_tc2'] = $kwParam;
        $params['kw_tc3'] = $kwParam;
        $params['kw_crit1'] = $kwParam;
        $params['kw_crit2'] = $kwParam;
        $params['kw_crit3'] = $kwParam;
        $params['kw_ev1'] = $kwParam;
        $params['kw_ev2'] = $kwParam;
        $params['kw_ev3'] = $kwParam;
        $params['kw_ev4'] = $kwParam;
        $params['kw_ev_sohieu'] = $kwParam;
    }
    if ($selectedSet !== '') {
        $clauses[] = "b.MaBoTieuChuan = :selected_set";
        $params['selected_set'] = $selectedSet;
    }
    if ($selectedStd !== '') {
        $clauses[] = "b.MaBoTieuChuan IN (SELECT MaBoTieuChuan FROM TieuChuan WHERE MaTieuChuan = :selected_std)";
        $params['selected_std'] = $selectedStd;
    }
    if ($selectedCrit !== '') {
        $clauses[] = "b.MaBoTieuChuan IN (SELECT tc.MaBoTieuChuan FROM TieuChuan tc JOIN TieuChi tchi ON tchi.MaTieuChuan = tc.MaTieuChuan WHERE tchi.MaTieuChi = :selected_crit)";
        $params['selected_crit'] = $selectedCrit;
    }
    if ($selectedEv !== '') {
        $clauses[] = "b.MaBoTieuChuan IN (SELECT DISTINCT COALESCE(m.MaBoTieuChuan, tc.MaBoTieuChuan) FROM MinhChung m LEFT JOIN TieuChi tchi ON tchi.MaTieuChi = m.MaTieuChi LEFT JOIN TieuChuan tc ON tc.MaTieuChuan = tchi.MaTieuChuan WHERE m.MaMinhChung = :selected_ev)";
        $params['selected_ev'] = $selectedEv;
    }
    if ($selectedStatus !== null) {
        $clauses[] = "b.TrangThai = :selected_status";
        $params['selected_status'] = $selectedStatus;
    }

    $whereSql = !empty($clauses) ? ' WHERE ' . implode(' AND ', $clauses) : '';
    $stmtSets = $pdo->prepare("SELECT * FROM BoTieuChuan b" . $whereSql . " ORDER BY b.TrangThai DESC, b.MaBoTieuChuan ASC");
    $stmtSets->execute($params);
    $sets = $stmtSets->fetchAll(PDO::FETCH_ASSOC);

    // Lấy toàn bộ cây dữ liệu con
    $allStandards = $pdo->query("SELECT * FROM TieuChuan ORDER BY ThuTu ASC, MaTieuChuan ASC")->fetchAll(PDO::FETCH_ASSOC);
    $allCriteria = $pdo->query("SELECT * FROM TieuChi ORDER BY ThuTu ASC, MaTieuChi ASC")->fetchAll(PDO::FETCH_ASSOC);
    $allEvidences = $pdo->query("
        SELECT m.*, u.HoTen AS NguoiTao 
        FROM MinhChung m 
        LEFT JOIN NguoiDung u ON u.MaNguoiDung = m.MaNguoiDung 
        ORDER BY m.MaMinhChung ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Gom nhóm theo mã cha
    $standardsBySet = [];
    foreach ($allStandards as $tc) {
        $standardsBySet[$tc['MaBoTieuChuan']][] = $tc;
    }

    $criteriaByStd = [];
    foreach ($allCriteria as $tchi) {
        $criteriaByStd[$tchi['MaTieuChuan']][] = $tchi;
    }

    $evidencesByCrit = [];
    foreach ($allEvidences as $mc) {
        if (!empty($mc['MaTieuChi'])) {
            $evidencesByCrit[$mc['MaTieuChi']][] = $mc;
        }
    }

    // Tạo file tạm XLSX với ZipArchive
    $tempFilePath = tempnam(sys_get_temp_dir(), 'xlsx_export_');
    $zip = new ZipArchive();
    if ($zip->open($tempFilePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
        throw new RuntimeException('Không thể khởi tạo tệp Excel tạm thời.');
    }

    // [Content_Types].xml
    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
</Types>');

    // _rels/.rels
    $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>');

    // xl/_rels/workbook.xml.rels
    $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>');

    // xl/workbook.xml
    $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>
    <sheet name="Phân cấp kiểm định" sheetId="1" r:id="rId1"/>
  </sheets>
</workbook>');

    // xl/styles.xml - THIẾT KẾ ĐỊNH DẠNG MÀU SẮC SANG TRỌNG & RÕ RÀNG
    $stylesXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <fonts count="17">
    <!-- 0: Default font -->
    <font><sz val="10.5"/><name val="Segoe UI"/><color rgb="FF1E293B"/></font>
    <!-- 1: School Title -->
    <font><b/><sz val="11.5"/><name val="Segoe UI"/><color rgb="FF1E3A8A"/></font>
    <!-- 2: Dept Title -->
    <font><b/><sz val="10.5"/><name val="Segoe UI"/><color rgb="FF334155"/></font>
    <!-- 3: National Title -->
    <font><b/><sz val="11.5"/><name val="Segoe UI"/><color rgb="FF0F172A"/></font>
    <!-- 4: National Sub -->
    <font><b/><u/><sz val="10.5"/><name val="Segoe UI"/><color rgb="FF0F172A"/></font>
    <!-- 5: Document Main Title -->
    <font><b/><sz val="16"/><name val="Segoe UI"/><color rgb="FF1E3A8A"/></font>
    <!-- 6: Metadata description -->
    <font><i/><sz val="10"/><name val="Segoe UI"/><color rgb="FF475569"/></font>
    <!-- 7: Table Header White Bold -->
    <font><b/><sz val="10.5"/><name val="Segoe UI"/><color rgb="FFFFFFFF"/></font>
    <!-- 8: Level 0 (Bộ TC) Bold Navy -->
    <font><b/><sz val="11"/><name val="Segoe UI"/><color rgb="FF0F295C"/></font>
    <!-- 9: Level 1 (Tiêu chuẩn) Bold Blue -->
    <font><b/><sz val="10.5"/><name val="Segoe UI"/><color rgb="FF1D4ED8"/></font>
    <!-- 10: Level 2 (Tiêu chí) Semi-Bold Dark -->
    <font><b/><sz val="10"/><name val="Segoe UI"/><color rgb="FF1E293B"/></font>
    <!-- 11: Level 3 (Minh chứng) Regular Slate -->
    <font><sz val="10"/><name val="Segoe UI"/><color rgb="FF334155"/></font>
    <!-- 12: Code Level 0 (Consolas Bold Navy) -->
    <font><b/><sz val="10.5"/><name val="Consolas"/><color rgb="FF0F295C"/></font>
    <!-- 13: Code Level 1 (Consolas Bold Blue) -->
    <font><b/><sz val="10"/><name val="Consolas"/><color rgb="FF1D4ED8"/></font>
    <!-- 14: Code Level 2 (Consolas Bold Teal) -->
    <font><b/><sz val="9.5"/><name val="Consolas"/><color rgb="FF0F766E"/></font>
    <!-- 15: Code Level 3 (Consolas Bold Emerald) -->
    <font><b/><sz val="9.5"/><name val="Consolas"/><color rgb="FF047857"/></font>
    <!-- 16: KPI Summary Bold -->
    <font><b/><sz val="11"/><name val="Segoe UI"/><color rgb="FF0F172A"/></font>
  </fonts>
  <fills count="10">
    <fill><patternFill fillType="none"/></fill>
    <fill><patternFill fillType="gray125"/></fill>
    <!-- 2: Navy Header Fill -->
    <fill><patternFill fillType="solid"><fgColor rgb="FF1E3A8A"/></patternFill></fill>
    <!-- 3: Ice Blue Accent (Level 0 - Bộ TC) -->
    <fill><patternFill fillType="solid"><fgColor rgb="FFDBEAFE"/></patternFill></fill>
    <!-- 4: Sky Soft Mist (Level 1 - Tiêu chuẩn) -->
    <fill><patternFill fillType="solid"><fgColor rgb="FFEFF6FF"/></patternFill></fill>
    <!-- 5: Slate Pearl (Level 2 - Tiêu chí) -->
    <fill><patternFill fillType="solid"><fgColor rgb="FFF8FAFC"/></patternFill></fill>
    <!-- 6: Pure White (Level 3 - Minh chứng) -->
    <fill><patternFill fillType="solid"><fgColor rgb="FFFFFFFF"/></patternFill></fill>
    <!-- 7: Summary Light Slate Fill -->
    <fill><patternFill fillType="solid"><fgColor rgb="FFF1F5F9"/></patternFill></fill>
    <!-- 8: Active Status Soft Green -->
    <fill><patternFill fillType="solid"><fgColor rgb="FFDCFCE7"/></patternFill></fill>
    <!-- 9: Inactive Status Soft Amber -->
    <fill><patternFill fillType="solid"><fgColor rgb="FFFEF3C7"/></patternFill></fill>
  </fills>
  <borders count="4">
    <border><left/><right/><top/><bottom/></border>
    <!-- 1: Subtle Gridline Border -->
    <border>
      <left style="thin"><color rgb="FFCBD5E1"/></left>
      <right style="thin"><color rgb="FFCBD5E1"/></right>
      <top style="thin"><color rgb="FFCBD5E1"/></top>
      <bottom style="thin"><color rgb="FFCBD5E1"/></bottom>
    </border>
    <!-- 2: Heavy Header Border -->
    <border>
      <left style="medium"><color rgb="FF0F295C"/></left>
      <right style="medium"><color rgb="FF0F295C"/></right>
      <top style="medium"><color rgb="FF0F295C"/></top>
      <bottom style="medium"><color rgb="FF0F295C"/></bottom>
    </border>
    <!-- 3: Level 0 Accent Border (thicker top) -->
    <border>
      <left style="thin"><color rgb="FF93C5FD"/></left>
      <right style="thin"><color rgb="FF93C5FD"/></right>
      <top style="medium"><color rgb="FF60A5FA"/></top>
      <bottom style="thin"><color rgb="FF93C5FD"/></bottom>
    </border>
  </borders>
  <cellStyleXfs count="1">
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>
  </cellStyleXfs>
  <cellXfs count="30">
    <!-- 0: Default -->
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
    <!-- 1: Top School Title -->
    <xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0"><alignment horizontal="center" vertical="center"/></xf>
    <!-- 2: Top Dept Title -->
    <xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0"><alignment horizontal="center" vertical="center"/></xf>
    <!-- 3: Top National Title -->
    <xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0"><alignment horizontal="center" vertical="center"/></xf>
    <!-- 4: Top National Sub -->
    <xf numFmtId="0" fontId="4" fillId="0" borderId="0" xfId="0"><alignment horizontal="center" vertical="center"/></xf>
    <!-- 5: Main Document Title -->
    <xf numFmtId="0" fontId="5" fillId="0" borderId="0" xfId="0"><alignment horizontal="center" vertical="center"/></xf>
    <!-- 6: Metadata Description -->
    <xf numFmtId="0" fontId="6" fillId="0" borderId="0" xfId="0"><alignment horizontal="center" vertical="center"/></xf>
    <!-- 7: Table Header (Navy, White Bold, Centered, Wrapped) -->
    <xf numFmtId="0" fontId="7" fillId="2" borderId="2" xfId="0" applyAlignment="1" applyFill="1" applyBorder="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>
    
    <!-- 8: Level 0 (Bộ TC) Left -->
    <xf numFmtId="0" fontId="8" fillId="3" borderId="3" xfId="0" applyAlignment="1" applyFill="1" applyBorder="1"><alignment horizontal="left" vertical="center" wrapText="1"/></xf>
    <!-- 9: Level 0 (Bộ TC) Center -->
    <xf numFmtId="0" fontId="8" fillId="3" borderId="3" xfId="0" applyAlignment="1" applyFill="1" applyBorder="1"><alignment horizontal="center" vertical="center"/></xf>
    <!-- 10: Level 0 (Bộ TC) Code Monospace Center -->
    <xf numFmtId="0" fontId="12" fillId="3" borderId="3" xfId="0" applyAlignment="1" applyFill="1" applyBorder="1"><alignment horizontal="center" vertical="center"/></xf>
    
    <!-- 11: Level 1 (Tiêu chuẩn) Left -->
    <xf numFmtId="0" fontId="9" fillId="4" borderId="1" xfId="0" applyAlignment="1" applyFill="1" applyBorder="1"><alignment horizontal="left" vertical="center" wrapText="1"/></xf>
    <!-- 12: Level 1 (Tiêu chuẩn) Center -->
    <xf numFmtId="0" fontId="9" fillId="4" borderId="1" xfId="0" applyAlignment="1" applyFill="1" applyBorder="1"><alignment horizontal="center" vertical="center"/></xf>
    <!-- 13: Level 1 (Tiêu chuẩn) Code Center -->
    <xf numFmtId="0" fontId="13" fillId="4" borderId="1" xfId="0" applyAlignment="1" applyFill="1" applyBorder="1"><alignment horizontal="center" vertical="center"/></xf>
    
    <!-- 14: Level 2 (Tiêu chí) Left -->
    <xf numFmtId="0" fontId="10" fillId="5" borderId="1" xfId="0" applyAlignment="1" applyFill="1" applyBorder="1"><alignment horizontal="left" vertical="center" wrapText="1"/></xf>
    <!-- 15: Level 2 (Tiêu chí) Center -->
    <xf numFmtId="0" fontId="10" fillId="5" borderId="1" xfId="0" applyAlignment="1" applyFill="1" applyBorder="1"><alignment horizontal="center" vertical="center"/></xf>
    <!-- 16: Level 2 (Tiêu chí) Code Center -->
    <xf numFmtId="0" fontId="14" fillId="5" borderId="1" xfId="0" applyAlignment="1" applyFill="1" applyBorder="1"><alignment horizontal="center" vertical="center"/></xf>
    
    <!-- 17: Level 3 (Minh chứng) Left -->
    <xf numFmtId="0" fontId="11" fillId="6" borderId="1" xfId="0" applyAlignment="1" applyFill="1" applyBorder="1"><alignment horizontal="left" vertical="center" wrapText="1"/></xf>
    <!-- 18: Level 3 (Minh chứng) Center -->
    <xf numFmtId="0" fontId="11" fillId="6" borderId="1" xfId="0" applyAlignment="1" applyFill="1" applyBorder="1"><alignment horizontal="center" vertical="center"/></xf>
    <!-- 19: Level 3 (Minh chứng) Code Center -->
    <xf numFmtId="0" fontId="15" fillId="6" borderId="1" xfId="0" applyAlignment="1" applyFill="1" applyBorder="1"><alignment horizontal="center" vertical="center"/></xf>
    <!-- 20: Level 3 (Minh chứng) Active Status Pill -->
    <xf numFmtId="0" fontId="11" fillId="8" borderId="1" xfId="0" applyAlignment="1" applyFill="1" applyBorder="1"><alignment horizontal="center" vertical="center"/></xf>
    <!-- 21: Level 3 (Minh chứng) Inactive Status Pill -->
    <xf numFmtId="0" fontId="11" fillId="9" borderId="1" xfId="0" applyAlignment="1" applyFill="1" applyBorder="1"><alignment horizontal="center" vertical="center"/></xf>

    <!-- 22: KPI Summary Card Main Row -->
    <xf numFmtId="0" fontId="16" fillId="7" borderId="1" xfId="0" applyAlignment="1" applyFill="1" applyBorder="1"><alignment horizontal="left" vertical="center"/></xf>
    <!-- 23: Sign Header -->
    <xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0"><alignment horizontal="center" vertical="center"/></xf>
    <!-- 24: Sign Sub -->
    <xf numFmtId="0" fontId="6" fillId="0" borderId="0" xfId="0"><alignment horizontal="center" vertical="center"/></xf>
    <!-- 25: Sign Date -->
    <xf numFmtId="0" fontId="6" fillId="0" borderId="0" xfId="0"><alignment horizontal="right" vertical="center"/></xf>
    <!-- 26: Sign Name -->
    <xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0"><alignment horizontal="center" vertical="center"/></xf>
    <!-- 27: Generic Center Border -->
    <xf numFmtId="0" fontId="0" fillId="6" borderId="1" xfId="0" applyAlignment="1" applyBorder="1"><alignment horizontal="center" vertical="center"/></xf>
    <!-- 28: Generic Left Border -->
    <xf numFmtId="0" fontId="0" fillId="6" borderId="1" xfId="0" applyAlignment="1" applyBorder="1"><alignment horizontal="left" vertical="center" wrapText="1"/></xf>
  </cellXfs>
</styleSheet>';
    $zip->addFromString('xl/styles.xml', $stylesXml);

    $colLetters = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I'];
    $makeCell = function ($colIdx, $rowNum, $val, $styleIdx = 0) use ($colLetters) {
        $ref = $colLetters[$colIdx] . $rowNum;
        $escaped = htmlspecialchars((string)$val, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        return '<c r="' . $ref . '" s="' . $styleIdx . '" t="inlineStr"><is><t xml:space="preserve">' . $escaped . '</t></is></c>';
    };

    $rowsXml = '';
    $mergeCells = [];
    $rowNum = 1;

    // Header 1: Tên đơn vị / Quốc hiệu
    $rowsXml .= '<row r="' . $rowNum . '" ht="24" customHeight="1">';
    $rowsXml .= $makeCell(0, $rowNum, 'TRƯỜNG ĐẠI HỌC TÀI CHÍNH - NGÂN HÀNG HÀ NỘI', 1);
    for ($c = 1; $c <= 4; $c++) $rowsXml .= '<c r="' . $colLetters[$c] . $rowNum . '" s="1"/>';
    $rowsXml .= $makeCell(5, $rowNum, 'CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM', 3);
    for ($c = 6; $c <= 8; $c++) $rowsXml .= '<c r="' . $colLetters[$c] . $rowNum . '" s="3"/>';
    $rowsXml .= '</row>';
    $mergeCells[] = 'A' . $rowNum . ':E' . $rowNum;
    $mergeCells[] = 'F' . $rowNum . ':I' . $rowNum;
    $rowNum++;

    // Header 2: Khoa / Tiêu ngữ
    $rowsXml .= '<row r="' . $rowNum . '" ht="20" customHeight="1">';
    $rowsXml .= $makeCell(0, $rowNum, 'KHOA CÔNG NGHỆ THÔNG TIN', 2);
    for ($c = 1; $c <= 4; $c++) $rowsXml .= '<c r="' . $colLetters[$c] . $rowNum . '" s="2"/>';
    $rowsXml .= $makeCell(5, $rowNum, 'Độc lập - Tự do - Hạnh phúc', 4);
    for ($c = 6; $c <= 8; $c++) $rowsXml .= '<c r="' . $colLetters[$c] . $rowNum . '" s="4"/>';
    $rowsXml .= '</row>';
    $mergeCells[] = 'A' . $rowNum . ':E' . $rowNum;
    $mergeCells[] = 'F' . $rowNum . ':I' . $rowNum;
    $rowNum++;

    // Header 3: Hệ thống
    $rowsXml .= '<row r="' . $rowNum . '" ht="18" customHeight="1">';
    $rowsXml .= $makeCell(0, $rowNum, 'HỆ THỐNG QUẢN LÝ MINH CHỨNG KIỂM ĐỊNH', 6);
    for ($c = 1; $c <= 4; $c++) $rowsXml .= '<c r="' . $colLetters[$c] . $rowNum . '" s="6"/>';
    $rowsXml .= $makeCell(5, $rowNum, '-------------------o0o-------------------', 6);
    for ($c = 6; $c <= 8; $c++) $rowsXml .= '<c r="' . $colLetters[$c] . $rowNum . '" s="6"/>';
    $rowsXml .= '</row>';
    $mergeCells[] = 'A' . $rowNum . ':E' . $rowNum;
    $mergeCells[] = 'F' . $rowNum . ':I' . $rowNum;
    $rowNum++;

    // Dòng trống
    $rowsXml .= '<row r="' . $rowNum . '" ht="10" customHeight="1"/>';
    $rowNum++;

    // Tiêu đề báo cáo
    $rowsXml .= '<row r="' . $rowNum . '" ht="32" customHeight="1">';
    $rowsXml .= $makeCell(0, $rowNum, 'BẢNG TỔNG HỢP PHÂN CẤP BỘ TIÊU CHUẨN - TIÊU CHUẨN - TIÊU CHÍ - MINH CHỨNG', 5);
    for ($c = 1; $c <= 8; $c++) $rowsXml .= '<c r="' . $colLetters[$c] . $rowNum . '" s="5"/>';
    $rowsXml .= '</row>';
    $mergeCells[] = 'A' . $rowNum . ':I' . $rowNum;
    $rowNum++;

    // Thông tin metadata
    $exportedAt = date('H:i:s - d/m/Y');
    $currentUser = function_exists('current_user') ? current_user() : [];
    $exportedBy = $currentUser['name'] ?? ($_SESSION['user_name'] ?? 'Quản trị viên');
    $metaText = 'Thời gian xuất: ' . $exportedAt . ' (GMT+7)   |   Người xuất: ' . $exportedBy . '   |   Cấu trúc phân cấp 4 cấp đa tầng (Hỗ trợ nút bấm 1-2-3-4 và dấu +/- để mở rộng/thu gọn)';
    $rowsXml .= '<row r="' . $rowNum . '" ht="22" customHeight="1">';
    $rowsXml .= $makeCell(0, $rowNum, $metaText, 6);
    for ($c = 1; $c <= 8; $c++) $rowsXml .= '<c r="' . $colLetters[$c] . $rowNum . '" s="6"/>';
    $rowsXml .= '</row>';
    $mergeCells[] = 'A' . $rowNum . ':I' . $rowNum;
    $rowNum++;

    // Dòng trống
    $rowsXml .= '<row r="' . $rowNum . '" ht="10" customHeight="1"/>';
    $rowNum++;

    // TIÊU ĐỀ CỘT DỮ LIỆU
    $headers = [
        ['label' => 'STT', 'width' => 12],
        ['label' => 'Phân cấp danh mục', 'width' => 24],
        ['label' => 'Mã hiệu', 'width' => 18],
        ['label' => 'Tên danh mục / Nội dung chi tiết', 'width' => 50],
        ['label' => 'Số hiệu / Căn cứ / Tệp đính kèm', 'width' => 32],
        ['label' => 'Ngày ban hành / Năm học', 'width' => 22],
        ['label' => 'Trạng thái', 'width' => 18],
        ['label' => 'Thống kê con / Người tạo', 'width' => 28],
        ['label' => 'Mô tả chi tiết / Ghi chú', 'width' => 38],
    ];

    $rowsXml .= '<row r="' . $rowNum . '" ht="30" customHeight="1">';
    foreach ($headers as $idx => $hdr) {
        $rowsXml .= $makeCell($idx, $rowNum, $hdr['label'], 7);
    }
    $rowsXml .= '</row>';
    $rowNum++;

    // XUẤT CÂY DỮ LIỆU ĐA TẦNG VỚI OUTLINE LEVELS & VISUAL TREE ICONS
    $setIndex = 1;
    $totalSetsCount = count($sets);
    $totalStandardsCount = 0;
    $totalCriteriaCount = 0;
    $totalEvidencesCount = 0;

    foreach ($sets as $set) {
        $setId = $set['MaBoTieuChuan'];
        $setName = $set['TenBoTieuChuan'];
        $setThongTu = $set['ThongTu'] ? '📜 ' . $set['ThongTu'] : '-';
        $setDate = $set['NgayBanHanh'] ? date('d/m/Y', strtotime($set['NgayBanHanh'])) : '-';
        $setStatus = (int)$set['TrangThai'] === 1 ? '✓ Hoạt động' : '⊘ Ngừng áp dụng';
        $setDesc = $set['MoTa'] ?: '-';

        $stds = $standardsBySet[$setId] ?? [];
        $stdCount = count($stds);
        $totalStandardsCount += $stdCount;

        $critCountInSet = 0;
        $evCountInSet = 0;
        foreach ($stds as $tc) {
            $crits = $criteriaByStd[$tc['MaTieuChuan']] ?? [];
            $critCountInSet += count($crits);
            foreach ($crits as $tchi) {
                $evs = $evidencesByCrit[$tchi['MaTieuChi']] ?? [];
                $evCountInSet += count($evs);
            }
        }
        $totalCriteriaCount += $critCountInSet;
        $totalEvidencesCount += $evCountInSet;

        $setStat = '📊 ' . $stdCount . ' TC  |  ' . $critCountInSet . ' Tiêu chí  |  ' . $evCountInSet . ' MC';

        // 1. CẤP 1: BỘ TIÊU CHUẨN (Outline Level 0)
        $rowsXml .= '<row r="' . $rowNum . '" ht="28" customHeight="1">';
        $rowsXml .= $makeCell(0, $rowNum, str_pad((string)$setIndex, 2, '0', STR_PAD_LEFT), 9);
        $rowsXml .= $makeCell(1, $rowNum, '📁 [BỘ TIÊU CHUẨN]', 8);
        $rowsXml .= $makeCell(2, $rowNum, $setId, 10);
        $rowsXml .= $makeCell(3, $rowNum, mb_strtoupper($setName, 'UTF-8'), 8);
        $rowsXml .= $makeCell(4, $rowNum, $setThongTu, 8);
        $rowsXml .= $makeCell(5, $rowNum, $setDate, 9);
        $rowsXml .= $makeCell(6, $rowNum, $setStatus, 9);
        $rowsXml .= $makeCell(7, $rowNum, $setStat, 8);
        $rowsXml .= $makeCell(8, $rowNum, $setDesc, 8);
        $rowsXml .= '</row>';
        $rowNum++;

        // 2. CẤP 2: TIÊU CHUẨN (Outline Level 1)
        $tcIndex = 1;
        foreach ($stds as $tc) {
            $tcId = $tc['MaTieuChuan'];
            $tcName = $tc['TenTieuChuan'];
            $tcDesc = $tc['MoTa'] ?: '-';
            $crits = $criteriaByStd[$tcId] ?? [];
            $critCount = count($crits);

            $evCountInTc = 0;
            foreach ($crits as $tchi) {
                $evCountInTc += count($evidencesByCrit[$tchi['MaTieuChi']] ?? []);
            }
            $tcStat = '📌 ' . $critCount . ' Tiêu chí  |  ' . $evCountInTc . ' MC';

            $rowsXml .= '<row r="' . $rowNum . '" outlineLevel="1" ht="25" customHeight="1">';
            $rowsXml .= $makeCell(0, $rowNum, $setIndex . '.' . $tcIndex, 12);
            $rowsXml .= $makeCell(1, $rowNum, '  ├─ 📌 Tiêu chuẩn', 11);
            $rowsXml .= $makeCell(2, $rowNum, $tcId, 13);
            $rowsXml .= $makeCell(3, $rowNum, $tcName, 11);
            $rowsXml .= $makeCell(4, $rowNum, 'Thuộc: ' . $setId, 11);
            $rowsXml .= $makeCell(5, $rowNum, '-', 12);
            $rowsXml .= $makeCell(6, $rowNum, '✓ Áp dụng', 12);
            $rowsXml .= $makeCell(7, $rowNum, $tcStat, 11);
            $rowsXml .= $makeCell(8, $rowNum, $tcDesc, 11);
            $rowsXml .= '</row>';
            $rowNum++;

            // 3. CẤP 3: TIÊU CHÍ (Outline Level 2)
            $tchiIndex = 1;
            foreach ($crits as $tchi) {
                $tchiId = $tchi['MaTieuChi'];
                $tchiName = $tchi['TenTieuChi'];
                $tchiContent = $tchi['NoiDung'] ?: '-';
                $evs = $evidencesByCrit[$tchiId] ?? [];
                $evCount = count($evs);
                $tchiStat = '📋 ' . $evCount . ' Minh chứng';

                $rowsXml .= '<row r="' . $rowNum . '" outlineLevel="2" ht="23" customHeight="1">';
                $rowsXml .= $makeCell(0, $rowNum, $setIndex . '.' . $tcIndex . '.' . $tchiIndex, 15);
                $rowsXml .= $makeCell(1, $rowNum, '  │   └─ 📋 Tiêu chí', 14);
                $rowsXml .= $makeCell(2, $rowNum, $tchiId, 16);
                $rowsXml .= $makeCell(3, $rowNum, $tchiName, 14);
                $rowsXml .= $makeCell(4, $rowNum, 'Thuộc: ' . $tcId, 14);
                $rowsXml .= $makeCell(5, $rowNum, '-', 15);
                $rowsXml .= $makeCell(6, $rowNum, '✓ Áp dụng', 15);
                $rowsXml .= $makeCell(7, $rowNum, $tchiStat, 14);
                $rowsXml .= $makeCell(8, $rowNum, $tchiContent, 14);
                $rowsXml .= '</row>';
                $rowNum++;

                // 4. CẤP 4: MINH CHỨNG (Outline Level 3)
                $evIndex = 1;
                foreach ($evs as $ev) {
                    $evId = $ev['MaMinhChung'];
                    $evName = $ev['TenMinhChung'] . (!empty($ev['SoHieu']) ? ' (Số hiệu: ' . $ev['SoHieu'] . ')' : '');
                    $evDate = $ev['NgayBanHanh'] ? date('d/m/Y', strtotime($ev['NgayBanHanh'])) : '-';
                    $evYear = $ev['NamHoc'] ?: '';
                    $evStatus = (int)$ev['TrangThai'] === 1 ? '✓ Đã duyệt' : '⊘ Tạm khóa';
                    $statusStyle = (int)$ev['TrangThai'] === 1 ? 20 : 21;
                    $evFile = $ev['TepTin'] ? '📎 ' . basename($ev['TepTin']) : 'Chưa có file';
                    $evUser = $ev['NguoiTao'] ? '👤 ' . $ev['NguoiTao'] : '👤 Quản trị viên';
                    $evDesc = $ev['MoTa'] ?: '-';

                    $dateFormatted = $evDate;
                    if ($evYear !== '') {
                        $dateFormatted .= ' (NH: ' . $evYear . ')';
                    }

                    $rowsXml .= '<row r="' . $rowNum . '" outlineLevel="3" ht="22" customHeight="1">';
                    $rowsXml .= $makeCell(0, $rowNum, $setIndex . '.' . $tcIndex . '.' . $tchiIndex . '.' . $evIndex, 18);
                    $rowsXml .= $makeCell(1, $rowNum, '  │       └─ 📄 Minh chứng', 17);
                    $rowsXml .= $makeCell(2, $rowNum, $evId, 19);
                    $rowsXml .= $makeCell(3, $rowNum, $evName, 17);
                    $rowsXml .= $makeCell(4, $rowNum, $evFile, 17);
                    $rowsXml .= $makeCell(5, $rowNum, $dateFormatted, 18);
                    $rowsXml .= $makeCell(6, $rowNum, $evStatus, $statusStyle);
                    $rowsXml .= $makeCell(7, $rowNum, $evUser, 17);
                    $rowsXml .= $makeCell(8, $rowNum, $evDesc, 17);
                    $rowsXml .= '</row>';
                    $rowNum++;
                    $evIndex++;
                }

                $tchiIndex++;
            }

            $tcIndex++;
        }

        $setIndex++;
    }

    // DÒNG TỔNG KẾT THỐNG KÊ (KPI SUMMARY BANNER)
    $summaryText = 'TỔNG HỢP: ' . $totalSetsCount . ' Bộ tiêu chuẩn  |  ' . $totalStandardsCount . ' Tiêu chuẩn  |  ' . $totalCriteriaCount . ' Tiêu chí  |  ' . $totalEvidencesCount . ' Minh chứng kiểm định chất lượng';
    $rowsXml .= '<row r="' . $rowNum . '" ht="26" customHeight="1">';
    $rowsXml .= $makeCell(0, $rowNum, $summaryText, 22);
    for ($c = 1; $c <= 8; $c++) $rowsXml .= '<c r="' . $colLetters[$c] . $rowNum . '" s="22"/>';
    $rowsXml .= '</row>';
    $mergeCells[] = 'A' . $rowNum . ':I' . $rowNum;
    $rowNum++;

    // DÒNG TRỐNG
    $rowsXml .= '<row r="' . $rowNum . '" ht="15" customHeight="1"/>';
    $rowNum++;

    // DÒNG NGÀY KÝ BÁO CÁO
    $rowsXml .= '<row r="' . $rowNum . '" ht="20" customHeight="1">';
    $signDateText = 'Hà Nội, ngày ' . date('d') . ' tháng ' . date('m') . ' năm ' . date('Y') . ' (GMT+7)';
    $rowsXml .= $makeCell(5, $rowNum, $signDateText, 25);
    for ($c = 6; $c <= 8; $c++) $rowsXml .= '<c r="' . $colLetters[$c] . $rowNum . '" s="25"/>';
    $rowsXml .= '</row>';
    $mergeCells[] = 'F' . $rowNum . ':I' . $rowNum;
    $rowNum++;

    // TIÊU ĐỀ CHỮ KÝ
    $rowsXml .= '<row r="' . $rowNum . '" ht="22" customHeight="1">';
    $rowsXml .= $makeCell(0, $rowNum, 'NGƯỜI LẬP BÁO CÁO', 23);
    for ($c = 1; $c <= 4; $c++) $rowsXml .= '<c r="' . $colLetters[$c] . $rowNum . '" s="23"/>';
    $rowsXml .= $makeCell(5, $rowNum, 'TRƯỞNG ĐƠN VỊ / BAN KIỂM ĐỊNH', 23);
    for ($c = 6; $c <= 8; $c++) $rowsXml .= '<c r="' . $colLetters[$c] . $rowNum . '" s="23"/>';
    $rowsXml .= '</row>';
    $mergeCells[] = 'A' . $rowNum . ':E' . $rowNum;
    $mergeCells[] = 'F' . $rowNum . ':I' . $rowNum;
    $rowNum++;

    // GHI CHÚ CHỮ KÝ
    $rowsXml .= '<row r="' . $rowNum . '" ht="18" customHeight="1">';
    $rowsXml .= $makeCell(0, $rowNum, '(Ký và ghi rõ họ tên)', 24);
    for ($c = 1; $c <= 4; $c++) $rowsXml .= '<c r="' . $colLetters[$c] . $rowNum . '" s="24"/>';
    $rowsXml .= $makeCell(5, $rowNum, '(Ký, đóng dấu và ghi rõ họ tên)', 24);
    for ($c = 6; $c <= 8; $c++) $rowsXml .= '<c r="' . $colLetters[$c] . $rowNum . '" s="24"/>';
    $rowsXml .= '</row>';
    $mergeCells[] = 'A' . $rowNum . ':E' . $rowNum;
    $mergeCells[] = 'F' . $rowNum . ':I' . $rowNum;
    $rowNum++;

    // KHOẢNG TRỐNG KÝ
    $rowsXml .= '<row r="' . $rowNum . '" ht="48" customHeight="1"/>';
    $rowNum++;

    // TÊN NGƯỜI KÝ
    $rowsXml .= '<row r="' . $rowNum . '" ht="22" customHeight="1">';
    $rowsXml .= $makeCell(0, $rowNum, $exportedBy, 26);
    for ($c = 1; $c <= 4; $c++) $rowsXml .= '<c r="' . $colLetters[$c] . $rowNum . '" s="26"/>';
    $rowsXml .= '<c r="F' . $rowNum . '" s="26"/>';
    for ($c = 6; $c <= 8; $c++) $rowsXml .= '<c r="' . $colLetters[$c] . $rowNum . '" s="26"/>';
    $rowsXml .= '</row>';
    $mergeCells[] = 'A' . $rowNum . ':E' . $rowNum;
    $mergeCells[] = 'F' . $rowNum . ':I' . $rowNum;

    // Chiều rộng cột
    $colsXml = '<cols>';
    foreach ($headers as $idx => $hdr) {
        $cNum = $idx + 1;
        $w = $hdr['width'];
        $colsXml .= '<col min="' . $cNum . '" max="' . $cNum . '" width="' . $w . '" customWidth="1"/>';
    }
    $colsXml .= '</cols>';

    // Merge cells
    $mergeXml = '<mergeCells count="' . count($mergeCells) . '">';
    foreach ($mergeCells as $mcRef) {
        $mergeXml .= '<mergeCell ref="' . $mcRef . '"/>';
    }
    $mergeXml .= '</mergeCells>';

    // Worksheet với outlinePr summaryBelow="0" để các nút [+] và cấp 1,2,3,4 sổ ra từ dòng cha
    $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheetPr>
    <outlinePr summaryBelow="0" summaryRight="0"/>
  </sheetPr>
  <sheetViews>
    <sheetView showGridLines="1" tabSelected="1" workbookViewId="0">
      <pane ySplit="8" topLeftCell="A9" activePane="bottomLeft" state="frozen"/>
    </sheetView>
  </sheetViews>
  <sheetFormatPr defaultRowHeight="20" defaultColWidth="15"/>
  ' . $colsXml . '
  <sheetData>
    ' . $rowsXml . '
  </sheetData>
  ' . $mergeXml . '
</worksheet>';

    $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
    $zip->close();

    // Stream download về client
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($tempFilePath));
    header('Cache-Control: max-age=0, no-cache, must-revalidate');
    header('Pragma: public');

    readfile($tempFilePath);
    @unlink($tempFilePath);
    exit;
}

?>
