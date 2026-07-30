<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/helpers.php';
require_login();
require_once __DIR__ . '/../config/database.php';

$evidenceId = (int) ($_GET['id'] ?? 0);
if ($evidenceId <= 0) {
    header('Location: ' . base_url('user/search.php?download_error=invalid'));
    exit;
}

$stmt = db()->prepare("
    SELECT
        MaMinhChung AS id,
        TenMinhChung AS original_name,
        TepTin AS file_path
    FROM MinhChung
    WHERE MaMinhChung = :id
    LIMIT 1
");
$stmt->execute(['id' => $evidenceId]);
$file = $stmt->fetch();

if (!$file || empty($file['file_path'])) {
    header('Location: ' . base_url('user/search.php?download_error=not_found'));
    exit;
}

$projectRoot = realpath(__DIR__ . '/..');
$relativePath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $file['file_path']);
$absolutePath = realpath($projectRoot . DIRECTORY_SEPARATOR . $relativePath);

if (!$absolutePath || strpos($absolutePath, $projectRoot) !== 0 || !is_file($absolutePath)) {
    header('Location: ' . base_url('user/search.php?download_error=missing_file'));
    exit;
}

log_activity('xem', 'minh_chung', $evidenceId, 'MC.' . str_pad($evidenceId, 2, '0', STR_PAD_LEFT));

$displayName = basename($absolutePath);
$mimeType = mime_content_type($absolutePath) ?: 'application/octet-stream';

header('Content-Type: ' . $mimeType);
header('Content-Disposition: inline; filename="' . str_replace('"', '', $displayName) . '"');
header('Content-Length: ' . filesize($absolutePath));
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');
readfile($absolutePath);
exit;
