<?php
require_once __DIR__ . '/../config/database.php';

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
            id_nguoi_dung INT NOT NULL,
            ma_token VARCHAR(255) NOT NULL,
            het_han DATETIME NOT NULL,
            ngay_tao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_remember_token_hash (ma_token),
            KEY idx_remember_user (id_nguoi_dung),
            KEY idx_remember_expires (het_han),
            CONSTRAINT fk_remember_tokens_user FOREIGN KEY (id_nguoi_dung) REFERENCES nguoi_dung(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $created = true;
}

function create_login_token(int $userId, bool $remember = false): void
{
    ensure_remember_tokens_table();

    $token = bin2hex(random_bytes(32));
    $days = $remember ? 30 : 7;
    $expiresAt = (new DateTimeImmutable('+' . $days . ' days'))->format('Y-m-d H:i:s');

    $stmt = db()->prepare('INSERT INTO remember_tokens (id_nguoi_dung, ma_token, het_han) VALUES (:user_id, :token_hash, :expires_at)');
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
        SELECT rt.id AS token_id, rt.id_nguoi_dung AS user_id, u.trang_thai AS status, v.ma_vai_tro AS role_code
        FROM remember_tokens rt
        JOIN nguoi_dung u ON u.id = rt.id_nguoi_dung
        JOIN vai_tro v ON v.id = u.id_vai_tro
        WHERE rt.ma_token = :token_hash
          AND rt.het_han > NOW()
        LIMIT 1
    ");
    $stmt->execute(['token_hash' => hash('sha256', $token)]);
    $record = $stmt->fetch();

    if (!$record || $record['status'] !== 'active') {
        clear_login_token();
        return false;
    }

    $_SESSION['user_id'] = (int) $record['user_id'];
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
            : base_url('user/search.php');

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
        $hasCol = $pdo->query("SHOW COLUMNS FROM audit_logs LIKE 'ten_ban_ghi'")->fetch();
        if (!$hasCol) {
            $pdo->exec("ALTER TABLE audit_logs ADD COLUMN ten_ban_ghi VARCHAR(255) NULL AFTER phan_he");
        }

        $userId = $_SESSION['user_id'] ?? null;
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $jsonVal = $newValue !== null ? json_encode($newValue, JSON_UNESCAPED_UNICODE) : null;

        $stmt = $pdo->prepare("
            INSERT INTO audit_logs (id_nguoi_dung, hanh_dong, phan_he, ten_ban_ghi, id_ban_ghi, gia_tri_moi, dia_chi_ip)
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
        // Silently swallow audit logging errors to prevent breaking main operations
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
        'Đang áp dụng' => 'success',
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

function avatar_html(?string $avatarPath, string $name, string $class = 'avatar'): string
{
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
?>
