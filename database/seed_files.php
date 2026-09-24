<?php
require_once __DIR__ . '/../config/database.php';

$pdo = db();
$projectRoot = realpath(__DIR__ . '/..');

$stmt = $pdo->query("SELECT MaMinhChung, TenMinhChung, MoTa, TepTin FROM MinhChung");
$rows = $stmt->fetchAll();

$createdCount = 0;

foreach ($rows as $row) {
    $filePath = trim($row['TepTin'] ?? '');
    
    // If no file path set, generate a default one
    if ($filePath === '') {
        $filePath = 'uploads/evidences/MC_' . $row['MaMinhChung'] . '.pdf';
        $up = $pdo->prepare("UPDATE MinhChung SET TepTin = :path WHERE MaMinhChung = :id");
        $up->execute(['path' => $filePath, 'id' => $row['MaMinhChung']]);
    }
    
    $relativePath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $filePath);
    $fullPath = $projectRoot . DIRECTORY_SEPARATOR . $relativePath;
    
    $dir = dirname($fullPath);
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }
    
    if (!file_exists($fullPath) || filesize($fullPath) === 0) {
        $title = $row['TenMinhChung'] ?? 'Minh chứng kiểm định chất lượng';
        $code = $row['MaMinhChung'] ?? 'MC';
        $desc = $row['MoTa'] ?? 'Hồ sơ minh chứng phục vụ kiểm định chất lượng CTĐT';
        
        $content = "========================================================================\n";
        $content .= "           TRƯỜNG ĐẠI HỌC TÀI CHÍNH - NGÂN HÀNG HÀ NỘI\n";
        $content .= "              KHOA CÔNG NGHỆ THÔNG TIN - HỆ THỐNG CNTT\n";
        $content .= "========================================================================\n\n";
        $content .= "MÃ MINH CHỨNG: " . $code . "\n";
        $content .= "TÊN MINH CHỨNG: " . $title . "\n";
        $content .= "MÔ TẢ: " . $desc . "\n";
        $content .= "NGÀY KHỞI TẠO: " . date('d/m/Y H:i:s') . "\n\n";
        $content .= "------------------------------------------------------------------------\n";
        $content .= "Dữ liệu tệp minh chứng chính thức được lưu trữ trên hệ thống TSF.\n";
        $content .= "Tệp tin phục vụ công tác kiểm định và tự đánh giá chất lượng CTĐT.\n";
        $content .= "------------------------------------------------------------------------\n";
        
        @file_put_contents($fullPath, $content);
        $createdCount++;
    }
}
