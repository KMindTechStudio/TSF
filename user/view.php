<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/helpers.php';
require_login();
require_once __DIR__ . '/../config/database.php';

$fileId = (int) ($_GET['id'] ?? 0);
if ($fileId <= 0) {
    header('Location: ' . base_url('user/search.php?download_error=invalid'));
    exit;
}

$stmt = db()->prepare("
    SELECT
        ef.id,
        ef.ten_goc AS original_name,
        ef.duong_dan AS file_path,
        e.ma_minh_chung AS evidence_code
    FROM file_minh_chung ef
    JOIN minh_chung e ON e.id = ef.id_minh_chung
    WHERE ef.id = :id
    LIMIT 1
");
$stmt->execute(['id' => $fileId]);
$file = $stmt->fetch();

if (!$file) {
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

log_activity('xem', 'minh_chung', (int) $file['id'], $file['evidence_code'] . ' - ' . $file['original_name']);

$displayName = $file['original_name'] ?: basename($absolutePath);
$mimeType = mime_content_type($absolutePath) ?: 'application/octet-stream';

header('Content-Type: ' . $mimeType);
header('Content-Disposition: inline; filename="' . str_replace('"', '', $displayName) . '"');
header('Content-Length: ' . filesize($absolutePath));
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');
readfile($absolutePath);
exit;
