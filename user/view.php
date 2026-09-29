<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/helpers.php';
require_login();
require_once __DIR__ . '/../config/database.php';

$evidenceId = trim($_GET['id'] ?? '');
$standardSetId = trim($_GET['standard_set'] ?? '');

if ($standardSetId !== '') {
    $stmt = db()->prepare("
        SELECT
            MaBoTieuChuan AS id,
            TenBoTieuChuan AS original_name,
            TepTinPDF AS file_path
        FROM BoTieuChuan
        WHERE MaBoTieuChuan = :id
        LIMIT 1
    ");
    $stmt->execute(['id' => $standardSetId]);
    $file = $stmt->fetch();
} elseif ($evidenceId !== '') {
    $stmt = db()->prepare("
        SELECT
            MaMinhChung AS id,
            TenMinhChung AS original_name,
            TepTin AS file_path,
            TrangThai
        FROM MinhChung
        WHERE MaMinhChung = :id
        LIMIT 1
    ");
    $stmt->execute(['id' => $evidenceId]);
    $file = $stmt->fetch();
} else {
    header('Location: ' . base_url('user/search.php?download_error=invalid'));
    exit;
}

if (!$file || empty($file['file_path'])) {
    header('Location: ' . base_url('user/search.php?download_error=not_found'));
    exit;
}

// Kiểm tra quyền xem: Nếu minh chứng ở trạng thái không hoạt động (TrangThai = 0), chỉ Admin mới được xem
if ($evidenceId !== '' && (int)($file['TrangThai'] ?? 1) === 0 && current_role() !== 'admin') {
    header('Location: ' . base_url('user/search.php?download_error=inactive'));
    exit;
}

$projectRoot = realpath(__DIR__ . '/..');
$relativePath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $file['file_path']);
$absolutePath = realpath($projectRoot . DIRECTORY_SEPARATOR . $relativePath);

if (!$absolutePath || strpos($absolutePath, $projectRoot) !== 0 || !is_file($absolutePath)) {
    header('Location: ' . base_url('user/search.php?download_error=missing_file'));
    exit;
}

if ($standardSetId !== '') {
    log_activity('xem', 'bo_tieu_chuan', 0, $standardSetId . ' - ' . ($file['original_name'] ?? ''));
} else {
    log_activity('xem', 'minh_chung', 0, $evidenceId . ' - ' . ($file['original_name'] ?? ''));
}

$displayName = basename($absolutePath);
$mimeType = mime_content_type($absolutePath) ?: 'application/octet-stream';

header('Content-Type: ' . $mimeType);
header('Content-Disposition: inline; filename="' . str_replace('"', '', $displayName) . '"');
header('Content-Length: ' . filesize($absolutePath));
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');
readfile($absolutePath);
exit;
