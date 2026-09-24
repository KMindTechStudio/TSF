<?php
require_once __DIR__ . '/../config/database.php';

function seed_database_if_needed($force = false) {
    $pdo = db();

    $countUsers = (int) $pdo->query("SELECT COUNT(*) FROM NguoiDung")->fetchColumn();
    $countSets  = (int) $pdo->query("SELECT COUNT(*) FROM BoTieuChuan")->fetchColumn();

    if (!$force && ($countUsers > 0 || $countSets > 0)) {
        return; // Database already has data, do NOT re-seed or truncate
    }

    // Disable Foreign Key checks for clean insertion
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");

    // 1. Seed NguoiDung (Exactly 1 Admin and 2 Users)
    $pdo->exec("TRUNCATE TABLE NguoiDung;");
    $passHash = password_hash('123456', PASSWORD_DEFAULT);

    $usersData = [
        ['ND001', 'Quản trị viên', 'Khoa Công nghệ thông tin', 'admin@fbu.edu.vn', '0912345678', 'admin', 'admin', 1],
        ['ND002', 'Nguyễn Văn A', 'Phòng Đảm bảo chất lượng', 'user01@fbu.edu.vn', '0987654321', 'kiemdinhtt', 'user', 1],
        ['ND003', 'Trần Thị B', 'Bộ môn Kỹ thuật phần mềm', 'user02@fbu.edu.vn', '0911223344', 'viewer01', 'user', 1],
    ];

    $stmtUser = $pdo->prepare("INSERT INTO NguoiDung (MaNguoiDung, HoTen, DonViCongTac, Email, SoDienThoai, TenDangNhap, MatKhau, VaiTro, TrangThai) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($usersData as $u) {
        $stmtUser->execute([$u[0], $u[1], $u[2], $u[3], $u[4], $u[5], $passHash, $u[6], $u[7]]);
    }

    // 2. Seed BoTieuChuan (55 standard sets)
    if ($countSets < 50) {
        $pdo->exec("TRUNCATE TABLE BoTieuChuan;");
        $setNames = [
            'Bộ tiêu chuẩn đánh giá chất lượng chương trình đào tạo trình độ đại học ngành Công nghệ thông tin',
            'Bộ tiêu chuẩn kiểm định chất lượng cơ sở giáo dục đại học (Thông tư 12/2017/TT-BGDĐT)',
            'Bộ tiêu chuẩn đánh giá chất lượng chương trình đào tạo ngành Kỹ thuật Phần mềm (AUN-QA 4.0)',
            'Bộ tiêu chuẩn kiểm định chất lượng chương trình đào tạo ngành Hệ thống thông tin (ABET)',
            'Bộ tiêu chuẩn đánh giá chất lượng CTĐT ngành Trí tuệ nhân tạo và Khoa học dữ liệu',
            'Bộ tiêu chuẩn kiểm định chất lượng chương trình đào tạo ngành An toàn thông tin',
            'Bộ tiêu chuẩn đánh giá chất lượng chương trình đào tạo Thạc sĩ Công nghệ thông tin',
            'Bộ tiêu chuẩn kiểm định chất lượng chương trình đào tạo Tiến sĩ Công nghệ thông tin',
            'Bộ tiêu chuẩn đánh giá chất lượng chương trình đào tạo cử nhân Khoa học máy tính',
            'Bộ tiêu chuẩn chuẩn hóa năng lực công nghệ thông tin định hướng chuẩn kỹ năng ITSS',
        ];

        $thongTuList = [
            'Thông tư 04/2016/TT-BGDĐT',
            'Thông tư 12/2017/TT-BGDĐT',
            'Thông tư 17/2021/TT-BGDĐT',
            'Quyết định 78/QĐ-BGDĐT',
            'Chuẩn kiểm định AUN-QA v4.0',
            'Chuẩn kiểm định ABET CAC',
            'Thông tư 38/2013/TT-BGDĐT',
        ];

        $stmtSet = $pdo->prepare("INSERT INTO BoTieuChuan (MaBoTieuChuan, TenBoTieuChuan, ThongTu, NgayBanHanh, MoTa, TrangThai) VALUES (?, ?, ?, ?, ?, ?)");
        for ($i = 1; $i <= 55; $i++) {
            $setCode = "BTC" . str_pad($i, 2, '0', STR_PAD_LEFT);
            $name = isset($setNames[$i - 1]) ? $setNames[$i - 1] : "Bộ tiêu chuẩn kiểm định chất lượng chương trình đào tạo ngành CNTT bổ sung đợt " . $i;
            $tt = $thongTuList[array_rand($thongTuList)];
            $year = rand(2018, 2025);
            $month = str_pad(rand(1, 12), 2, '0', STR_PAD_LEFT);
            $day = str_pad(rand(1, 28), 2, '0', STR_PAD_LEFT);
            $date = "$year-$month-$day";
            $desc = "Bộ tiêu chuẩn quy định các yêu cầu kiểm định và bảo đảm chất lượng giáo dục cho ngành CNTT đợt $i.";
            $status = ($i % 10 === 0) ? 0 : 1;

            $stmtSet->execute([$setCode, $name, $tt, $date, $desc, $status]);
        }

        // 3. Seed TieuChuan (55 standards)
        $pdo->exec("TRUNCATE TABLE TieuChuan;");
        $standardTitles = [
            'Mục tiêu và chuẩn đầu ra của chương trình đào tạo',
            'Bản mô tả chương trình đào tạo và cấu trúc khóa học',
            'Cấu trúc và nội dung chương trình dạy học',
            'Phương pháp tiếp cận trong dạy và học',
            'Đánh giá kết quả học tập của người học',
            'Đội ngũ giảng viên và nghiên cứu viên',
            'Đội ngũ nhân viên hành chính và hỗ trợ kỹ thuật',
            'Người học và các dịch vụ hỗ trợ người học',
            'Cơ sở vật chất, phòng máy tính và trang thiết bị',
            'Nâng cao chất lượng liên tục và rà soát định kỳ',
            'Kết quả đầu ra của người học và tỷ lệ có việc làm',
            'Hoạt động nghiên cứu khoa học và chuyển giao công nghệ',
            'Tương tác giữa nhà trường, doanh nghiệp và xã hội',
            'Công tác quản lý tài chính và nguồn lực phát triển',
            'Hệ thống đảm bảo chất lượng nội bộ',
        ];

        $stmtStd = $pdo->prepare("INSERT INTO TieuChuan (MaTieuChuan, TenTieuChuan, MoTa, ThuTu, MaBoTieuChuan, TrangThai) VALUES (?, ?, ?, ?, ?, ?)");
        for ($i = 1; $i <= 55; $i++) {
            $code = "TC" . str_pad($i, 2, '0', STR_PAD_LEFT);
            $title = $standardTitles[($i - 1) % count($standardTitles)] . " (Tiêu chuẩn $i)";
            $desc = "Tiêu chuẩn đánh giá chi tiết yêu cầu thuộc bộ tiêu chuẩn kiểm định ngành CNTT.";
            $setId = "BTC" . str_pad(rand(1, 55), 2, '0', STR_PAD_LEFT);
            $status = ($i % 15 === 0) ? 0 : 1;

            $stmtStd->execute([$code, $title, $desc, $i, $setId, $status]);
        }

        // 4. Seed TieuChi (60 criteria)
        $pdo->exec("TRUNCATE TABLE TieuChi;");
        $criteriaTitles = [
            'Mục tiêu của CTĐT được xác định rõ ràng, phù hợp với sứ mạng và tầm nhìn',
            'Chuẩn đầu ra phản ánh yêu cầu của các bên liên quan và được rà soát định kỳ',
            'Bản mô tả CTĐT đầy đủ thông tin cần thiết, rõ ràng và công khai minh bạch',
            'Cấu trúc chương trình học có tính logic, tích hợp và cập nhật xu hướng công nghệ',
            'Đề cương chi tiết học phần được cập nhật hằng năm và được phê duyệt chính thức',
            'Phương pháp dạy học thúc đẩy năng lực tự học, tư duy phản biện và sáng tạo',
            'Phương pháp đánh giá đa dạng, công bằng, bám sát chuẩn đầu ra học phần',
            'Quy trình tuyển sinh minh bạch, đúng quy định và đảm bảo chất lượng đầu vào',
            'Hoạt động tư vấn học tập, hướng nghiệp và hỗ trợ tâm lý sinh viên hiệu quả',
            'Trình độ chuyên môn và kỹ năng sư phạm của giảng viên đáp ứng tốt yêu cầu',
            'Giáo trình, tài liệu tham khảo và học liệu số đầy đủ và cập nhật',
            'Hệ thống phòng thí nghiệm, phòng máy tính đáp ứng tốt yêu cầu thực hành',
            'Kết quả đánh giá sự hài lòng của nhà tuyển dụng đối với sinh viên tốt nghiệp',
            'Quy trình bảo đảm chất lượng nội bộ được vận hành thường xuyên và hiệu quả',
        ];

        $stmtCri = $pdo->prepare("INSERT INTO TieuChi (TenTieuChi, NoiDung, ThuTu, MaTieuChuan, TrangThai) VALUES (?, ?, ?, ?, ?)");
        for ($i = 1; $i <= 60; $i++) {
            $title = $criteriaTitles[($i - 1) % count($criteriaTitles)] . " (TC" . str_pad($i, 2, '0', STR_PAD_LEFT) . ")";
            $content = "Nội dung chi tiết tiêu chí đánh giá mức độ đạt được chuẩn đầu ra và minh chứng đi kèm.";
            $stdId = rand(1, 55);
            $status = ($i % 12 === 0) ? 0 : 1;

            $stmtCri->execute([$title, $content, $i, $stdId, $status]);
        }

        // 5. Seed MinhChung (65 evidence records)
        $pdo->exec("TRUNCATE TABLE MinhChung;");
        $evidenceFiles = [
            'MC_01_01_01.pdf', 'MC_02_01_03.docx', 'MC_03_02_04.zip', 'MC_04_01_02.pdf', 'MC_05_03_05.xlsx',
            'QuyDuyet_CTDT_2025.pdf', 'KhaoSat_DoanhNghiep_2025.docx', 'BaoCao_TuDanhGia_CNTT.pdf', 'DanhSach_GiangVien_2025.xlsx',
            'KeHoach_CaiTien_ChatLuong.pdf', 'BienBan_Hop_Rasoat_CDR.docx', 'QuyChe_DanhGia_HocPhan.pdf'
        ];

        $evidenceNames = [
            'Quyết định ban hành mục tiêu và chuẩn đầu ra ngành Công nghệ thông tin',
            'Bản mô tả chương trình đào tạo ngành Công nghệ thông tin năm 2025',
            'Đề cương chi tiết các học phần chuyên ngành Công nghệ phần mềm',
            'Kế hoạch đổi mới phương pháp dạy và học định hướng ứng dụng thực tiễn',
            'Quy chế đánh giá học phần và ma trận kiểm tra đánh giá CĐR',
            'Biên bản rà soát và cập nhật chương trình đào tạo định kỳ năm 2025',
            'Phiếu khảo sát ý kiến doanh nghiệp về chất lượng sinh viên tốt nghiệp',
            'Báo cáo tự đánh giá chất lượng chương trình đào tạo CNTT',
            'Danh sách công trình nghiên cứu khoa học và bài báo của giảng viên',
            'Quyết định thành lập Hội đồng kiểm định chất lượng giáo dục',
            'Báo cáo tổng kết công tác tuyển sinh và phân tích chất lượng đầu vào',
            'Sổ tay hướng dẫn thực tập tốt nghiệp và đồ án khóa luận',
            'Biên bản nghiệm thu nâng cấp hệ thống phòng máy tính thực hành',
            'Quyết định khen thưởng sinh viên có thành tích xuất sắc trong học tập',
            'Báo cáo tình hình việc làm của sinh viên sau 1 năm tốt nghiệp',
        ];

        $years = ['2023-2024', '2024-2025', '2025-2026'];

        $stmtEv = $pdo->prepare("INSERT INTO MinhChung (TenMinhChung, MoTa, TepTin, NamHoc, NgayCapNhat, TrangThai, MaTieuChi, MaNguoiDung) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        for ($i = 1; $i <= 65; $i++) {
            $name = $evidenceNames[($i - 1) % count($evidenceNames)] . " - Tập " . ceil($i / count($evidenceNames));
            $desc = "Hồ sơ minh chứng chính thức phục vụ công tác kiểm định chất lượng giáo dục đợt $i.";
            $file = "uploads/evidences/" . $evidenceFiles[array_rand($evidenceFiles)];
            $year = $years[array_rand($years)];
            
            $upYear = rand(2025, 2026);
            $upMonth = str_pad(rand(1, 12), 2, '0', STR_PAD_LEFT);
            $upDay = str_pad(rand(1, 28), 2, '0', STR_PAD_LEFT);
            $upTime = str_pad(rand(8, 20), 2, '0', STR_PAD_LEFT) . ":" . str_pad(rand(10, 59), 2, '0', STR_PAD_LEFT) . ":00";
            $updated = "$upYear-$upMonth-$upDay $upTime";
            
            $status = ($i % 10 === 0) ? 0 : 1;
            $criId = rand(1, 60);
            $userId = rand(1, 3); // Assigned to one of the 3 users

            $stmtEv->execute([$name, $desc, $file, $year, $updated, $status, $criId, $userId]);
        }
    } else {
        // Just update existing MinhChung to reference MaNguoiDung IN (1,2,3)
        $pdo->exec("UPDATE MinhChung SET MaNguoiDung = ((MaMinhChung % 3) + 1);");
    }

    // Re-enable Foreign Key checks
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
}

seed_database_if_needed();
