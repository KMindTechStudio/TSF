<?php
require_once __DIR__ . '/../includes/data.php';

echo "=== KIỂM TRA DỮ LIỆU ĐÃ LOAD VÀO HỆ THỐNG ===\n";
echo "1. Bộ tiêu chuẩn: " . count($standardSets) . "\n";
foreach ($standardSets as $s) {
    echo "   - {$s['id']}: {$s['name']} (Tiêu chuẩn: {$s['standard_count']}, Minh chứng: {$s['evidences']})\n";
}

echo "\n2. Tiêu chuẩn: " . count($standards) . "\n";
foreach ($standards as $std) {
    echo "   - [{$std['id']}] {$std['name']} (Bộ: {$std['set_id']})\n";
}

echo "\n3. Tiêu chí: " . count($criteria) . "\n";
foreach ($criteria as $cr) {
    echo "   - [{$cr['id']}] {$cr['name']} (Tiêu chuẩn: {$cr['standard_id']})\n";
}

echo "\n4. Minh chứng: " . count($evidences) . "\n";
foreach ($evidences as $ev) {
    echo "   - [{$ev['code']}] {$ev['name']}\n";
    echo "     Số hiệu: {$ev['so_hieu']} | Tiêu chí: {$ev['ma_tieu_chi']} | File: {$ev['file_path']}\n";
}
