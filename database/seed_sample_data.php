<?php
require_once __DIR__ . '/../config/database.php';

$pdo = db();

echo "=== KIỂM TRA DỮ LIỆU HIỆN TẠI ===\n";

// 1. Kiểm tra Bộ tiêu chuẩn
$sets = $pdo->query("SELECT * FROM BoTieuChuan")->fetchAll();
echo "Bộ tiêu chuẩn hiện có: " . count($sets) . "\n";
foreach ($sets as $s) {
    echo " - [{$s['MaBoTieuChuan']}] {$s['TenBoTieuChuan']}\n";
}

$setId = 'BTC01';
if (empty($sets)) {
    // Tạo 1 bộ tiêu chuẩn mẫu
    $stmtSet = $pdo->prepare("INSERT INTO BoTieuChuan (MaBoTieuChuan, TenBoTieuChuan, ThongTu, NgayBanHanh, MoTa, TrangThai) VALUES (?, ?, ?, ?, ?, ?)");
    $stmtSet->execute([
        'BTC01',
        'Bộ tiêu chuẩn kiểm định CTĐT theo Thông tư 04/2016/TT-BGDĐT',
        'Thông tư 04/2016/TT-BGDĐT',
        '2016-03-14',
        'Bộ tiêu chuẩn đánh giá chất lượng chương trình đào tạo các trình độ của giáo dục đại học do Bộ Giáo dục và Đào tạo ban hành.',
        1
    ]);
    echo "-> Đã tạo mới Bộ tiêu chuẩn BTC01\n";
    $setId = 'BTC01';
} else {
    $setId = $sets[0]['MaBoTieuChuan'];
    echo "-> Sử dụng Bộ tiêu chuẩn: {$setId}\n";
}

// 2. Định nghĩa 3 Tiêu chuẩn mẫu, mỗi Tiêu chuẩn 2 Tiêu chí, mỗi Tiêu chí 2 Minh chứng mẫu
$standardsData = [
    [
        'id' => 'TC01',
        'name' => 'Tiêu chuẩn 1: Mục tiêu và chuẩn đầu ra của chương trình đào tạo',
        'desc' => 'Mục tiêu của chương trình đào tạo được xác định rõ ràng, phù hợp với sứ mạng và tầm nhìn của cơ sở giáo dục đại học. Chuẩn đầu ra được xây dựng rõ ràng, bao quát đầy đủ kiến thức, kỹ năng và mức độ tự chủ.',
        'order' => 1,
        'criteria' => [
            [
                'id' => 'TC01.01',
                'name' => 'Tiêu chí 1.1: Mục tiêu của chương trình đào tạo được xác định rõ ràng và phù hợp với sứ mạng của Nhà trường',
                'content' => 'Mục tiêu CTĐT ngành Công nghệ thông tin được công khai, định kỳ rà soát, lấy ý kiến các bên liên quan và phù hợp với chiến lược phát triển của Trường ĐH Tài chính - Ngân hàng Hà Nội.',
                'order' => 1,
                'evidences' => [
                    [
                        'code' => 'MC01.01.01',
                        'name' => 'Quyết định ban hành Sứ mạng, Tầm nhìn và Triết lý giáo dục của Trường Đại học Tài chính - Ngân hàng Hà Nội',
                        'so_hieu' => '128/QĐ-ĐHTCNH',
                        'date' => '2022-01-15',
                        'desc' => 'Quyết định công bố sứ mạng, tầm nhìn chiến lược giai đoạn 2021-2030 và triết lý giáo dục của Nhà trường.',
                        'file' => 'uploads/evidences/qd_ban_hanh_su_mang_fbu.pdf'
                    ],
                    [
                        'code' => 'MC01.01.02',
                        'name' => 'Biên bản họp Hội đồng Khoa CNTT về rà soát, cập nhật mục tiêu đào tạo CTĐT ngành Công nghệ thông tin',
                        'so_hieu' => '15/BB-KCNTT',
                        'date' => '2022-03-20',
                        'desc' => 'Biên bản họp chuyên môn lấy ý kiến giảng viên, chuyên gia doanh nghiệp về mục tiêu đào tạo cử nhân CNTT.',
                        'file' => 'uploads/evidences/bien_ban_hoi_thao_cdr.pdf'
                    ]
                ]
            ],
            [
                'id' => 'TC01.02',
                'name' => 'Tiêu chí 1.2: Chuẩn đầu ra của chương trình đào tạo được ban hành rõ ràng, đo lường được',
                'content' => 'Chuẩn đầu ra (PLOs) thể hiện đầy đủ các yêu cầu về kiến thức chuyên ngành, kỹ năng lập trình, tư duy hệ thống và thái độ nghề nghiệp theo Khung trình độ quốc gia Việt Nam (VQF).',
                'order' => 2,
                'evidences' => [
                    [
                        'code' => 'MC01.02.01',
                        'name' => 'Quyết định ban hành Chuẩn đầu ra chương trình đào tạo ngành Công nghệ thông tin trình độ Đại học',
                        'so_hieu' => '245/QĐ-ĐHTCNH',
                        'date' => '2022-06-18',
                        'desc' => 'Bộ chuẩn đầu ra chi tiết gồm 12 PLOs bao quát kiến thức nền tảng, kiến thức chuyên sâu và kỹ năng thực hành nghề nghiệp.',
                        'file' => 'uploads/evidences/chuan_dau_ra_cntt_2022.pdf'
                    ],
                    [
                        'code' => 'MC01.02.02',
                        'name' => 'Bản ma trận tích hợp Chuẩn đầu ra chương trình đào tạo và Chuẩn đầu ra học phần (Matrix PLO - CLO)',
                        'so_hieu' => '08/MT-CNTT',
                        'date' => '2022-08-10',
                        'desc' => 'Bảng ma trận đối sánh mức độ đóng góp của từng học phần trong chương trình vào việc đạt được các chuẩn đầu ra CTĐT.',
                        'file' => 'uploads/evidences/MC_20261005103227_544.pdf'
                    ]
                ]
            ]
        ]
    ],
    [
        'id' => 'TC02',
        'name' => 'Tiêu chuẩn 2: Bản mô tả chương trình đào tạo và cấu trúc khối kiến thức',
        'desc' => 'Chương trình đào tạo được thiết kế có cấu trúc hợp lý, bao gồm khối kiến thức giáo dục đại cương, cơ sở ngành, chuyên ngành và thực tập tốt nghiệp, đáp ứng khối lượng tối thiểu 135 tín chỉ.',
        'order' => 2,
        'criteria' => [
            [
                'id' => 'TC02.01',
                'name' => 'Tiêu chí 2.1: Bản mô tả chương trình đào tạo được thiết kế đầy đủ, cập nhật và công khai',
                'content' => 'Bản mô tả CTĐT cung cấp thông tin toàn diện về cấu trúc khóa học, sơ đồ tiên quyết, điều kiện tốt nghiệp và cơ hội việc làm sau khi ra trường cho người học.',
                'order' => 1,
                'evidences' => [
                    [
                        'code' => 'MC02.01.01',
                        'name' => 'Quyết định ban hành Chương trình đào tạo trình độ Đại học ngành Công nghệ thông tin (135 tín chỉ)',
                        'so_hieu' => '312/QĐ-ĐHTCNH',
                        'date' => '2022-07-25',
                        'desc' => 'Chương trình khung chi tiết 135 tín chỉ định hướng ứng dụng và phát triển phần mềm cho sinh viên CNTT.',
                        'file' => 'uploads/evidences/ctdt_cntt_fbu_135tc.pdf'
                    ],
                    [
                        'code' => 'MC02.01.02',
                        'name' => 'Sổ tay sinh viên và Sơ đồ cây tiến trình học tập CTĐT ngành Công nghệ thông tin các khóa',
                        'so_hieu' => '19/ST-ĐT',
                        'date' => '2022-09-05',
                        'desc' => 'Cẩm nang hướng dẫn sinh viên đăng ký học tập theo tiến độ, quy định học phần tiên quyết và định hướng chuyên ngành.',
                        'file' => 'uploads/evidences/MC_20261005104125_836.pdf'
                    ]
                ]
            ],
            [
                'id' => 'TC02.02',
                'name' => 'Tiêu chí 2.2: Đề cương chi tiết học phần được xây dựng chuẩn hóa theo chuẩn đầu ra',
                'content' => 'Tất cả các học phần trong CTĐT đều có đề cương chi tiết (Syllabus) được phê duyệt, công bố đầu mỗi học kỳ, nêu rõ mục tiêu, CLO và phương pháp đánh giá.',
                'order' => 2,
                'evidences' => [
                    [
                        'code' => 'MC02.02.01',
                        'name' => 'Tập Đề cương chi tiết các học phần chuyên ngành Công nghệ thông tin (Bộ môn Kỹ thuật phần mềm & Mạng máy tính)',
                        'so_hieu' => '56/ĐC-KCNTT',
                        'date' => '2023-01-10',
                        'desc' => 'Tập hợp đề cương chi tiết học phần: Lập trình Web, Cơ sở dữ liệu nâng cao, An toàn thông tin, Trí tuệ nhân tạo.',
                        'file' => 'uploads/evidences/tap_de_cuong_chi_tiet_cntt.pdf'
                    ],
                    [
                        'code' => 'MC02.02.02',
                        'name' => 'Quy định về việc xây dựng, thẩm định và cập nhật Đề cương chi tiết học phần trình độ đại học',
                        'so_hieu' => '88/QĐ-ĐHTCNH',
                        'date' => '2023-02-15',
                        'desc' => 'Quy định hướng dẫn giảng viên biên soạn đề cương theo chuẩn đầu ra OBE (Outcome-Based Education).',
                        'file' => 'uploads/evidences/MC_20261005104740_264.pdf'
                    ]
                ]
            ]
        ]
    ],
    [
        'id' => 'TC03',
        'name' => 'Tiêu chuẩn 3: Đội ngũ Giảng viên và Nghiên cứu khoa học',
        'desc' => 'Đội ngũ giảng viên cơ hữu và thỉnh giảng có trình độ chuyên môn cao, cơ cấu phù hợp, tham gia tích cực vào các hoạt động giảng dạy, hướng dẫn đồ án và nghiên cứu khoa học công nghệ.',
        'order' => 3,
        'criteria' => [
            [
                'id' => 'TC03.01',
                'name' => 'Tiêu chí 3.1: Quy hoạch và phát triển đội ngũ giảng viên đáp ứng yêu cầu đào tạo ngành CNTT',
                'content' => 'Số lượng, trình độ (Tiến sĩ, Thạc sĩ) và tỷ lệ sinh viên/giảng viên đảm bảo theo quy định chuẩn của Bộ GD&ĐT.',
                'order' => 1,
                'evidences' => [
                    [
                        'code' => 'MC03.01.01',
                        'name' => 'Báo cáo thống kê cơ cấu đội ngũ giảng viên cơ hữu và thỉnh giảng Khoa CNTT năm học 2023-2024',
                        'so_hieu' => '42/BC-TCCB',
                        'date' => '2023-10-12',
                        'desc' => 'Danh sách trích ngang, trình độ học hàm học vị và phân công giảng dạy của giảng viên Khoa CNTT.',
                        'file' => 'uploads/evidences/MC_20261005105359_397.pdf'
                    ],
                    [
                        'code' => 'MC03.01.02',
                        'name' => 'Kế hoạch đào tạo, bồi dưỡng nâng cao trình độ chuyên môn và kỹ năng sư phạm cho giảng viên CNTT',
                        'so_hieu' => '67/KH-ĐHTCNH',
                        'date' => '2023-11-20',
                        'desc' => 'Kế hoạch cử giảng viên đi học nghiên cứu sinh, chứng chỉ quốc tế (AWS, Cisco, Scrum) và tập huấn công nghệ mới.',
                        'file' => 'uploads/evidences/MC_20261005105634_158.pdf'
                    ]
                ]
            ],
            [
                'id' => 'TC03.02',
                'name' => 'Tiêu chí 3.2: Hoạt động nghiên cứu khoa học, công bố bài báo và chuyển giao công nghệ của giảng viên',
                'content' => 'Giảng viên tích cực chủ trì đề tài NCKH các cấp, công bố bài báo khoa học trên các tạp chí uy tín trong và ngoài nước, gắn NCKH với đào tạo sinh viên.',
                'order' => 2,
                'evidences' => [
                    [
                        'code' => 'MC03.02.01',
                        'name' => 'Tổng hợp danh mục bài báo khoa học và công trình công bố của Giảng viên Khoa CNTT (Scopus, ISI, VJOL)',
                        'so_hieu' => '23/KHCN-KCNTT',
                        'date' => '2024-01-08',
                        'desc' => 'Danh mục hơn 25 công trình khoa học của giảng viên được công bố trên các kỷ yếu hội thảo quốc tế và tạp chí chuyên ngành.',
                        'file' => 'uploads/evidences/MC_20261005105958_186.pdf'
                    ],
                    [
                        'code' => 'MC03.02.02',
                        'name' => 'Quyết định nghiệm thu các đề tài Nghiên cứu khoa học cấp Trường ứng dụng trong giảng dạy CNTT',
                        'so_hieu' => '95/QĐ-KHCN',
                        'date' => '2024-03-15',
                        'desc' => 'Quyết định công nhận kết quả nghiệm thu 05 đề tài NCKH cấp Trường do giảng viên Khoa CNTT chủ nhiệm đạt loại Giỏi.',
                        'file' => 'uploads/evidences/MC_20261005110224_316.pdf'
                    ]
                ]
            ]
        ]
    ]
];

// Tiến hành chèn dữ liệu
$pdo->beginTransaction();
try {
    $stmtStd = $pdo->prepare("INSERT INTO TieuChuan (MaTieuChuan, TenTieuChuan, MoTa, ThuTu, MaBoTieuChuan, TrangThai) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE TenTieuChuan=VALUES(TenTieuChuan), MoTa=VALUES(MoTa), ThuTu=VALUES(ThuTu), MaBoTieuChuan=VALUES(MaBoTieuChuan), TrangThai=VALUES(TrangThai)");
    $stmtCri = $pdo->prepare("INSERT INTO TieuChi (MaTieuChi, TenTieuChi, NoiDung, ThuTu, MaTieuChuan, TrangThai) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE TenTieuChi=VALUES(TenTieuChi), NoiDung=VALUES(NoiDung), ThuTu=VALUES(ThuTu), MaTieuChuan=VALUES(MaTieuChuan), TrangThai=VALUES(TrangThai)");
    $stmtEv  = $pdo->prepare("INSERT INTO MinhChung (MaMinhChung, TenMinhChung, SoHieu, NgayBanHanh, MoTa, TepTin, TrangThai, MaTieuChi, MaBoTieuChuan, MaNguoiDung, NgayCapNhat) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE TenMinhChung=VALUES(TenMinhChung), SoHieu=VALUES(SoHieu), NgayBanHanh=VALUES(NgayBanHanh), MoTa=VALUES(MoTa), TepTin=VALUES(TepTin), TrangThai=VALUES(TrangThai), MaTieuChi=VALUES(MaTieuChi), MaBoTieuChuan=VALUES(MaBoTieuChuan), MaNguoiDung=VALUES(MaNguoiDung), NgayCapNhat=NOW()");

    $countStd = 0;
    $countCri = 0;
    $countEv  = 0;

    foreach ($standardsData as $std) {
        $stmtStd->execute([
            $std['id'],
            $std['name'],
            $std['desc'],
            $std['order'],
            $setId,
            1
        ]);
        $countStd++;
        echo " [+] Đã nạp Tiêu chuẩn: {$std['id']} - {$std['name']}\n";

        foreach ($std['criteria'] as $cri) {
            $stmtCri->execute([
                $cri['id'],
                $cri['name'],
                $cri['content'],
                $cri['order'],
                $std['id'],
                1
            ]);
            $countCri++;
            echo "    [+] Đã nạp Tiêu chí: {$cri['id']} - {$cri['name']}\n";

            foreach ($cri['evidences'] as $ev) {
                $stmtEv->execute([
                    $ev['code'],
                    $ev['name'],
                    $ev['so_hieu'],
                    $ev['date'],
                    $ev['desc'],
                    $ev['file'],
                    1,
                    $cri['id'],
                    $setId,
                    'ND001'
                ]);
                $countEv++;
                echo "       [+] Đã nạp Minh chứng: {$ev['code']} - {$ev['name']}\n";
            }
        }
    }

    $pdo->commit();
    echo "\n=== KẾT QUẢ NẠP DỮ LIỆU THÀNH CÔNG ===\n";
    echo "✔ Số Tiêu chuẩn đã nạp: {$countStd}\n";
    echo "✔ Số Tiêu chí đã nạp: {$countCri}\n";
    echo "✔ Số Minh chứng đã nạp: {$countEv}\n";

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "❌ LỖI KHI NẠP DỮ LIỆU: " . $e->getMessage() . "\n";
}
