<?php
require_once __DIR__ . '/../includes/helpers.php';
require_roles(['admin']);
require_once __DIR__ . '/../config/database.php';

$pdo = db();
$success = '';
$error = '';

// Handle AJAX Toggle Status
if (isset($_POST['action']) && $_POST['action'] === 'toggle_status_ajax') {
    header('Content-Type: application/json; charset=utf-8');
    $id = trim($_POST['id'] ?? '');
    $status = isset($_POST['status']) ? (int)$_POST['status'] : 0;
    try {
        if ($id === '') {
            throw new RuntimeException('Mã bộ tiêu chuẩn không hợp lệ.');
        }
        $stmt = $pdo->prepare('UPDATE BoTieuChuan SET TrangThai = :status WHERE MaBoTieuChuan = :id');
        $stmt->execute(['status' => $status, 'id' => $id]);

        $statusText = $status === 1 ? 'Hoạt động' : 'Ngừng hoạt động';
        log_activity('cap_nhat_trang_thai', 'bo_tieu_chuan', 0, 'Đổi trạng thái bộ ' . $id . ' sang ' . $statusText);
        echo json_encode(['success' => true, 'status' => $status, 'message' => 'Đã cập nhật trạng thái bộ ' . $id . ' sang: ' . $statusText]);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'message' => 'Lỗi cập nhật: ' . $e->getMessage()]);
    }
    exit;
}

// Handle Excel Export
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    require_once __DIR__ . '/../includes/data.php';

    $exportRows = [];
    $stmtSets = $pdo->query("SELECT * FROM BoTieuChuan ORDER BY TrangThai DESC, MaBoTieuChuan ASC");
    $sets = $stmtSets->fetchAll(PDO::FETCH_ASSOC);

    foreach ($sets as $idx => $s) {
        $exportRows[] = [
            'stt'           => $idx + 1,
            'code'          => $s['MaBoTieuChuan'],
            'name'          => $s['TenBoTieuChuan'],
            'thong_tu'      => $s['ThongTu'] ?? '',
            'ngay_ban_hanh' => $s['NgayBanHanh'] ?? '',
            'status'        => (int)$s['TrangThai'] === 1 ? 'Hoạt động' : 'Ngừng hoạt động',
            'mo_ta'         => $s['MoTa'] ?? '',
        ];
    }

    $columns = [
        ['key' => 'stt', 'label' => 'STT', 'align' => 'center', 'width' => '60px'],
        ['key' => 'code', 'label' => 'Mã bộ tiêu chuẩn', 'align' => 'center', 'width' => '140px'],
        ['key' => 'name', 'label' => 'Tên bộ tiêu chuẩn', 'align' => 'left', 'width' => '320px'],
        ['key' => 'thong_tu', 'label' => 'Số hiệu / Thông tư', 'align' => 'left', 'width' => '200px'],
        ['key' => 'ngay_ban_hanh', 'label' => 'Ngày ban hành', 'align' => 'center', 'width' => '120px'],
        ['key' => 'status', 'label' => 'Trạng thái', 'align' => 'center', 'width' => '130px'],
        ['key' => 'mo_ta', 'label' => 'Mô tả', 'align' => 'left', 'width' => '280px'],
    ];

    export_to_excel('danh_sach_bo_tieu_chuan_' . date('Ymd_His') . '.xls', 'DANH SÁCH BỘ TIÊU CHUẨN KIỂM ĐỊNH ĐỘNG', $columns, $exportRows);
    exit;
}

// Handle Form Submissions (CRUD for 3 levels)
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        // ==========================================
        // 1. CẤP THÔNG TƯ / BỘ TIÊU CHUẨN
        // ==========================================
        if ($action === 'save_standard_set') {
            $rawId         = trim($_POST['id'] ?? '');
            $maBo          = trim($_POST['ma_bo_tieu_chuan'] ?? '');
            $tenBo         = trim($_POST['ten_bo_tieu_chuan'] ?? '');
            $thongTu       = trim($_POST['thong_tu'] ?? '');
            $ngayBanHanh   = trim($_POST['ngay_ban_hanh'] ?? '');
            $moTa          = trim($_POST['mo_ta'] ?? '');
            $trangThai     = isset($_POST['trang_thai']) ? (int)$_POST['trang_thai'] : 0;
            $oldPdfPath    = trim($_POST['old_tep_tin_pdf'] ?? '');
            $pdfPath       = $oldPdfPath;

            if ($maBo === '') {
                throw new RuntimeException('Vui lòng nhập Mã bộ tiêu chuẩn.');
            }
            if ($tenBo === '') {
                throw new RuntimeException('Vui lòng nhập Tên bộ tiêu chuẩn.');
            }

            // Handle File Upload (PDF, PNG, JPG, JPEG, WEBP, GIF, DOCX...)
            if (isset($_FILES['tep_tin_pdf']) && $_FILES['tep_tin_pdf']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['tep_tin_pdf'];
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $allowedExts = ['pdf', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'doc', 'docx', 'xls', 'xlsx'];
                if (!in_array($ext, $allowedExts, true)) {
                    throw new RuntimeException('Chỉ chấp nhận các định dạng file tài liệu/ảnh: PDF, PNG, JPG, JPEG, WEBP, DOCX.');
                }
                if ($file['size'] > 50 * 1024 * 1024) {
                    throw new RuntimeException('Dung lượng file tải lên không được vượt quá 50MB.');
                }

                $uploadDir = __DIR__ . '/../uploads/standards';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                $fileName = 'file_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $maBo) . '_' . time() . '.' . $ext;
                $destPath = $uploadDir . '/' . $fileName;

                if (!move_uploaded_file($file['tmp_name'], $destPath)) {
                    throw new RuntimeException('Không thể lưu file tải lên máy chủ.');
                }

                $pdfPath = 'uploads/standards/' . $fileName;

                // Delete old file if updating
                if ($oldPdfPath && file_exists(__DIR__ . '/../' . $oldPdfPath) && $oldPdfPath !== $pdfPath) {
                    @unlink(__DIR__ . '/../' . $oldPdfPath);
                }
            }

            if ($rawId !== '') {
                // UPDATE
                if ($maBo !== $rawId) {
                    $chk = $pdo->prepare('SELECT COUNT(*) FROM BoTieuChuan WHERE MaBoTieuChuan = :code');
                    $chk->execute(['code' => $maBo]);
                    if ((int)$chk->fetchColumn() > 0) {
                        throw new RuntimeException('Mã bộ tiêu chuẩn "' . $maBo . '" đã tồn tại.');
                    }
                    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
                    $upTC = $pdo->prepare('UPDATE TieuChuan SET MaBoTieuChuan = :new WHERE MaBoTieuChuan = :old');
                    $upTC->execute(['new' => $maBo, 'old' => $rawId]);
                    $upMC = $pdo->prepare('UPDATE MinhChung SET MaBoTieuChuan = :new WHERE MaBoTieuChuan = :old');
                    $upMC->execute(['new' => $maBo, 'old' => $rawId]);
                }

                $stmt = $pdo->prepare('UPDATE BoTieuChuan SET MaBoTieuChuan = :new_code, TenBoTieuChuan = :name, ThongTu = :thong_tu, NgayBanHanh = :ngay_ban_hanh, MoTa = :description, TepTinPDF = :pdf, TrangThai = :status WHERE MaBoTieuChuan = :old_code');
                $stmt->execute([
                    'new_code'      => $maBo,
                    'name'          => $tenBo,
                    'thong_tu'      => $thongTu,
                    'ngay_ban_hanh' => $ngayBanHanh ?: null,
                    'description'   => $moTa,
                    'pdf'           => $pdfPath ?: null,
                    'status'        => $trangThai,
                    'old_code'      => $rawId,
                ]);

                if ($maBo !== $rawId) {
                    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
                }
                log_activity('cap_nhat', 'bo_tieu_chuan', 0, $maBo . ' - ' . $tenBo);
                $success = 'Cập nhật bộ tiêu chuẩn / thông tư thành công.';
            } else {
                // INSERT
                $chk = $pdo->prepare('SELECT COUNT(*) FROM BoTieuChuan WHERE MaBoTieuChuan = :code');
                $chk->execute(['code' => $maBo]);
                if ((int)$chk->fetchColumn() > 0) {
                    throw new RuntimeException('Mã bộ tiêu chuẩn "' . $maBo . '" đã tồn tại.');
                }

                $stmt = $pdo->prepare('INSERT INTO BoTieuChuan (MaBoTieuChuan, TenBoTieuChuan, ThongTu, NgayBanHanh, MoTa, TepTinPDF, TrangThai) VALUES (:code, :name, :thong_tu, :ngay_ban_hanh, :description, :pdf, :status)');
                $stmt->execute([
                    'code'          => $maBo,
                    'name'          => $tenBo,
                    'thong_tu'      => $thongTu,
                    'ngay_ban_hanh' => $ngayBanHanh ?: null,
                    'description'   => $moTa,
                    'pdf'           => $pdfPath ?: null,
                    'status'        => $trangThai,
                ]);
                log_activity('them_moi', 'bo_tieu_chuan', 0, $maBo . ' - ' . $tenBo);
                $success = 'Thêm mới bộ tiêu chuẩn / thông tư thành công.';
            }
        }

        if ($action === 'delete_standard_set') {
            $id = trim($_POST['id'] ?? '');

            // Constraint check 1: Check child Standards
            $checkTC = $pdo->prepare('SELECT COUNT(*) FROM TieuChuan WHERE MaBoTieuChuan = :id');
            $checkTC->execute(['id' => $id]);
            $countTC = (int)$checkTC->fetchColumn();
            if ($countTC > 0) {
                throw new RuntimeException('Không thể xóa Thông tư / Bộ tiêu chuẩn này vì đang chứa ' . $countTC . ' Tiêu chuẩn con bên trong. Vui lòng xóa các tiêu chuẩn trước.');
            }

            // Constraint check 2: Check child Evidences
            $checkMC = $pdo->prepare('SELECT COUNT(*) FROM MinhChung WHERE MaBoTieuChuan = :id');
            $checkMC->execute(['id' => $id]);
            $countMC = (int)$checkMC->fetchColumn();
            if ($countMC > 0) {
                throw new RuntimeException('Không thể xóa Thông tư / Bộ tiêu chuẩn này vì đang có ' . $countMC . ' Minh chứng liên kết.');
            }

            // Get PDF file path to delete
            $stmtFile = $pdo->prepare('SELECT TepTinPDF FROM BoTieuChuan WHERE MaBoTieuChuan = :id');
            $stmtFile->execute(['id' => $id]);
            $filePath = $stmtFile->fetchColumn();
            if ($filePath && file_exists(__DIR__ . '/../' . $filePath)) {
                @unlink(__DIR__ . '/../' . $filePath);
            }

            $stmt = $pdo->prepare('DELETE FROM BoTieuChuan WHERE MaBoTieuChuan = :id');
            $stmt->execute(['id' => $id]);
            log_activity('xoa', 'bo_tieu_chuan', 0, $id);
            $success = 'Xóa bộ tiêu chuẩn thành công.';
        }

        // ==========================================
        // 2. CẤP TIÊU CHUẨN
        // ==========================================
        if ($action === 'save_standard') {
            $rawId         = trim($_POST['id'] ?? '');
            $maTC          = trim($_POST['ma_tieu_chuan'] ?? '');
            $tenTC         = trim($_POST['ten_tieu_chuan'] ?? '');
            $moTa          = trim($_POST['mo_ta'] ?? '');
            $thuTu         = (int)($_POST['thu_tu'] ?? 0);
            $maBo          = trim($_POST['ma_bo_tieu_chuan'] ?? '');

            if ($maTC === '') {
                throw new RuntimeException('Vui lòng nhập Mã tiêu chuẩn.');
            }
            if ($tenTC === '') {
                throw new RuntimeException('Vui lòng nhập Tên tiêu chuẩn.');
            }
            if ($maBo === '') {
                throw new RuntimeException('Vui lòng chọn Thông tư / Bộ tiêu chuẩn cha.');
            }

            if ($rawId !== '') {
                // Update
                if ($maTC !== $rawId) {
                    $chk = $pdo->prepare('SELECT COUNT(*) FROM TieuChuan WHERE MaTieuChuan = :code');
                    $chk->execute(['code' => $maTC]);
                    if ((int)$chk->fetchColumn() > 0) {
                        throw new RuntimeException('Mã tiêu chuẩn "' . $maTC . '" đã tồn tại.');
                    }
                    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
                    $upTChi = $pdo->prepare('UPDATE TieuChi SET MaTieuChuan = :new WHERE MaTieuChuan = :old');
                    $upTChi->execute(['new' => $maTC, 'old' => $rawId]);
                }

                $stmt = $pdo->prepare('UPDATE TieuChuan SET MaTieuChuan = :new_code, TenTieuChuan = :name, MoTa = :description, ThuTu = :order_num, MaBoTieuChuan = :set_id WHERE MaTieuChuan = :old_code');
                $stmt->execute([
                    'new_code'    => $maTC,
                    'name'        => $tenTC,
                    'description' => $moTa,
                    'order_num'   => $thuTu,
                    'set_id'      => $maBo,
                    'old_code'    => $rawId,
                ]);

                if ($maTC !== $rawId) {
                    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
                }
                log_activity('cap_nhat', 'tieu_chuan', 0, $maTC . ' - ' . $tenTC);
                $success = 'Cập nhật tiêu chuẩn thành công.';
            } else {
                // Insert
                $chk = $pdo->prepare('SELECT COUNT(*) FROM TieuChuan WHERE MaTieuChuan = :code');
                $chk->execute(['code' => $maTC]);
                if ((int)$chk->fetchColumn() > 0) {
                    throw new RuntimeException('Mã tiêu chuẩn "' . $maTC . '" đã tồn tại.');
                }

                $stmt = $pdo->prepare('INSERT INTO TieuChuan (MaTieuChuan, TenTieuChuan, MoTa, ThuTu, MaBoTieuChuan, TrangThai) VALUES (:code, :name, :description, :order_num, :set_id, 1)');
                $stmt->execute([
                    'code'        => $maTC,
                    'name'        => $tenTC,
                    'description' => $moTa,
                    'order_num'   => $thuTu,
                    'set_id'      => $maBo,
                ]);
                log_activity('them_moi', 'tieu_chuan', 0, $maTC . ' - ' . $tenTC);
                $success = 'Thêm mới tiêu chuẩn thành công.';
            }
        }

        if ($action === 'delete_standard') {
            $id = trim($_POST['id'] ?? '');

            // Constraint check: Check child Criteria
            $checkTChi = $pdo->prepare('SELECT COUNT(*) FROM TieuChi WHERE MaTieuChuan = :id');
            $checkTChi->execute(['id' => $id]);
            $countTChi = (int)$checkTChi->fetchColumn();
            if ($countTChi > 0) {
                throw new RuntimeException('Không thể xóa Tiêu chuẩn này vì đang chứa ' . $countTChi . ' Tiêu chí con bên trong. Vui lòng xóa các tiêu chí trước.');
            }

            $stmt = $pdo->prepare('DELETE FROM TieuChuan WHERE MaTieuChuan = :id');
            $stmt->execute(['id' => $id]);
            log_activity('xoa', 'tieu_chuan', 0, $id);
            $success = 'Xóa tiêu chuẩn thành công.';
        }

        // ==========================================
        // 3. CẤP TIÊU CHÍ
        // ==========================================
        if ($action === 'save_criterion') {
            $rawId         = trim($_POST['id'] ?? '');
            $maTChi        = trim($_POST['ma_tieu_chi'] ?? '');
            $tenTChi       = trim($_POST['ten_tieu_chi'] ?? '');
            $noiDung       = trim($_POST['noi_dung'] ?? '');
            $thuTu         = (int)($_POST['thu_tu'] ?? 0);
            $maTC          = trim($_POST['ma_tieu_chuan'] ?? '');

            if ($maTChi === '') {
                throw new RuntimeException('Vui lòng nhập Mã tiêu chí.');
            }
            if ($tenTChi === '') {
                throw new RuntimeException('Vui lòng nhập Tên tiêu chí.');
            }
            if ($maTC === '') {
                throw new RuntimeException('Vui lòng chọn Tiêu chuẩn cha.');
            }

            if ($rawId !== '') {
                // Update
                if ($maTChi !== $rawId) {
                    $chk = $pdo->prepare('SELECT COUNT(*) FROM TieuChi WHERE MaTieuChi = :code');
                    $chk->execute(['code' => $maTChi]);
                    if ((int)$chk->fetchColumn() > 0) {
                        throw new RuntimeException('Mã tiêu chí "' . $maTChi . '" đã tồn tại.');
                    }
                    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
                    $upMC = $pdo->prepare('UPDATE MinhChung SET MaTieuChi = :new WHERE MaTieuChi = :old');
                    $upMC->execute(['new' => $maTChi, 'old' => $rawId]);
                }

                $stmt = $pdo->prepare('UPDATE TieuChi SET MaTieuChi = :new_code, TenTieuChi = :name, NoiDung = :description, ThuTu = :order_num, MaTieuChuan = :standard_id WHERE MaTieuChi = :old_code');
                $stmt->execute([
                    'new_code'    => $maTChi,
                    'name'        => $tenTChi,
                    'description' => $noiDung,
                    'order_num'   => $thuTu,
                    'standard_id' => $maTC,
                    'old_code'    => $rawId,
                ]);

                if ($maTChi !== $rawId) {
                    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
                }
                log_activity('cap_nhat', 'tieu_chi', 0, $maTChi . ' - ' . $tenTChi);
                $success = 'Cập nhật tiêu chí thành công.';
            } else {
                // Insert
                $chk = $pdo->prepare('SELECT COUNT(*) FROM TieuChi WHERE MaTieuChi = :code');
                $chk->execute(['code' => $maTChi]);
                if ((int)$chk->fetchColumn() > 0) {
                    throw new RuntimeException('Mã tiêu chí "' . $maTChi . '" đã tồn tại.');
                }

                $stmt = $pdo->prepare('INSERT INTO TieuChi (MaTieuChi, TenTieuChi, NoiDung, ThuTu, MaTieuChuan, TrangThai) VALUES (:code, :name, :description, :order_num, :standard_id, 1)');
                $stmt->execute([
                    'code'        => $maTChi,
                    'name'        => $tenTChi,
                    'description' => $noiDung,
                    'order_num'   => $thuTu,
                    'standard_id' => $maTC,
                ]);
                log_activity('them_moi', 'tieu_chi', 0, $maTChi . ' - ' . $tenTChi);
                $success = 'Thêm mới tiêu chí thành công.';
            }
        }

        if ($action === 'delete_criterion') {
            $id = trim($_POST['id'] ?? '');

            // Constraint check: Check linked Evidences
            $checkMC = $pdo->prepare('SELECT COUNT(*) FROM MinhChung WHERE MaTieuChi = :id');
            $checkMC->execute(['id' => $id]);
            $countMC = (int)$checkMC->fetchColumn();
            if ($countMC > 0) {
                throw new RuntimeException('Không thể xóa Tiêu chí này vì đang có ' . $countMC . ' Minh chứng liên kết. Vui lòng gỡ liên kết minh chứng trước.');
            }

            $stmt = $pdo->prepare('DELETE FROM TieuChi WHERE MaTieuChi = :id');
            $stmt->execute(['id' => $id]);
            log_activity('xoa', 'tieu_chi', 0, $id);
            $success = 'Xóa tiêu chí thành công.';
        }

    } catch (Throwable $exception) {
        $error = 'Thao tác không thành công: ' . $exception->getMessage();
    }
}

// Fetch Full Hierarchy for Tree View with Pagination (5 records per page)
$searchKeyword        = trim($_GET['q'] ?? $_GET['keyword'] ?? $_GET['search'] ?? '');
$selectedStandardSet  = trim($_GET['standard_set'] ?? $_GET['set'] ?? '');
$selectedStandard     = trim($_GET['standard'] ?? '');
$selectedStatus       = isset($_GET['status']) && $_GET['status'] !== '' ? (int)$_GET['status'] : null;

$perPage = 5;
$currentPage = max(1, (int)($_GET['page'] ?? 1));

// Build where clauses
$whereClauses = [];
$queryParams = [];

if ($searchKeyword !== '') {
    $whereClauses[] = "(b.MaBoTieuChuan LIKE :k1 OR b.TenBoTieuChuan LIKE :k2 OR b.ThongTu LIKE :k3)";
    $queryParams['k1'] = "%$searchKeyword%";
    $queryParams['k2'] = "%$searchKeyword%";
    $queryParams['k3'] = "%$searchKeyword%";
}

if ($selectedStandardSet !== '') {
    $whereClauses[] = "b.MaBoTieuChuan = :selected_set";
    $queryParams['selected_set'] = $selectedStandardSet;
}

if ($selectedStandard !== '') {
    $whereClauses[] = "b.MaBoTieuChuan IN (SELECT MaBoTieuChuan FROM TieuChuan WHERE MaTieuChuan = :selected_std)";
    $queryParams['selected_std'] = $selectedStandard;
}

if ($selectedStatus !== null) {
    $whereClauses[] = "b.TrangThai = :selected_status";
    $queryParams['selected_status'] = $selectedStatus;
}

$whereSql = !empty($whereClauses) ? ' WHERE ' . implode(' AND ', $whereClauses) : '';

// Count total records for pagination
$countQuery = "SELECT COUNT(*) FROM BoTieuChuan b" . $whereSql;
$stmtCount = $pdo->prepare($countQuery);
$stmtCount->execute($queryParams);
$totalRecords = (int)$stmtCount->fetchColumn();
$totalPages = max(1, ceil($totalRecords / $perPage));
if ($currentPage > $totalPages) {
    $currentPage = $totalPages;
}
$offset = ($currentPage - 1) * $perPage;

// 1. Fetch Standard Sets (Paginated)
$querySets = "
    SELECT 
        b.MaBoTieuChuan,
        b.TenBoTieuChuan,
        COALESCE(b.ThongTu, '') AS ThongTu,
        b.NgayBanHanh,
        b.MoTa,
        b.TepTinPDF,
        b.TrangThai,
        COUNT(DISTINCT tc.MaTieuChuan) AS total_standards,
        COUNT(DISTINCT tchi.MaTieuChi) AS total_criteria,
        COUNT(DISTINCT m.MaMinhChung) AS total_evidences
    FROM BoTieuChuan b
    LEFT JOIN TieuChuan tc ON tc.MaBoTieuChuan = b.MaBoTieuChuan
    LEFT JOIN TieuChi tchi ON tchi.MaTieuChuan = tc.MaTieuChuan
    LEFT JOIN MinhChung m ON (m.MaBoTieuChuan = b.MaBoTieuChuan OR m.MaTieuChi = tchi.MaTieuChi)
" . $whereSql . " GROUP BY b.MaBoTieuChuan, b.TenBoTieuChuan, b.ThongTu, b.NgayBanHanh, b.MoTa, b.TepTinPDF, b.TrangThai ORDER BY b.TrangThai DESC, b.MaBoTieuChuan ASC LIMIT :offset, :perPage";

$stmtSets = $pdo->prepare($querySets);
foreach ($queryParams as $k => $v) {
    $stmtSets->bindValue($k, $v);
}
$stmtSets->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
$stmtSets->bindValue(':perPage', (int)$perPage, PDO::PARAM_INT);
$stmtSets->execute();
$standardSetsList = $stmtSets->fetchAll(PDO::FETCH_ASSOC);

// Full list for Filter Dropdowns
$allSetsForFilter = $pdo->query("SELECT MaBoTieuChuan, TenBoTieuChuan, ThongTu FROM BoTieuChuan ORDER BY TrangThai DESC, MaBoTieuChuan ASC")->fetchAll(PDO::FETCH_ASSOC);
$allStandardsForFilter = $pdo->query("SELECT MaTieuChuan, TenTieuChuan, MaBoTieuChuan FROM TieuChuan ORDER BY MaBoTieuChuan ASC, ThuTu ASC, MaTieuChuan ASC")->fetchAll(PDO::FETCH_ASSOC);

// Filter query parameters for links
$currentFilterParams = [];
if ($searchKeyword !== '') $currentFilterParams['q'] = $searchKeyword;
if ($selectedStandardSet !== '') $currentFilterParams['standard_set'] = $selectedStandardSet;
if ($selectedStandard !== '') $currentFilterParams['standard'] = $selectedStandard;
if ($selectedStatus !== null) $currentFilterParams['status'] = $selectedStatus;

// 2. Fetch Standards grouped by Set
$stmtAllTC = $pdo->query("
    SELECT 
        tc.MaTieuChuan,
        tc.TenTieuChuan,
        tc.MoTa,
        tc.ThuTu,
        tc.MaBoTieuChuan,
        COUNT(DISTINCT tchi.MaTieuChi) AS total_criteria
    FROM TieuChuan tc
    LEFT JOIN TieuChi tchi ON tchi.MaTieuChuan = tc.MaTieuChuan
    GROUP BY tc.MaTieuChuan, tc.TenTieuChuan, tc.MoTa, tc.ThuTu, tc.MaBoTieuChuan
    ORDER BY tc.ThuTu ASC, tc.MaTieuChuan ASC
");
$allStandardsBySet = [];
foreach ($stmtAllTC->fetchAll(PDO::FETCH_ASSOC) as $tc) {
    $allStandardsBySet[$tc['MaBoTieuChuan']][] = $tc;
}

// 3. Fetch Criteria grouped by Standard
$stmtAllTChi = $pdo->query("
    SELECT 
        tchi.MaTieuChi,
        tchi.TenTieuChi,
        tchi.NoiDung,
        tchi.ThuTu,
        tchi.MaTieuChuan,
        COUNT(DISTINCT m.MaMinhChung) AS total_evidences
    FROM TieuChi tchi
    LEFT JOIN MinhChung m ON m.MaTieuChi = tchi.MaTieuChi
    GROUP BY tchi.MaTieuChi, tchi.TenTieuChi, tchi.NoiDung, tchi.ThuTu, tchi.MaTieuChuan
    ORDER BY tchi.ThuTu ASC, tchi.MaTieuChi ASC
");
$allCriteriaByStandard = [];
foreach ($stmtAllTChi->fetchAll(PDO::FETCH_ASSOC) as $tchi) {
    $allCriteriaByStandard[$tchi['MaTieuChuan']][] = $tchi;
}

$pageTitle = page_title('Quản lý Bộ Tiêu chuẩn động');
$heading   = 'Quản lý Bộ Tiêu chuẩn động';
include __DIR__ . '/../includes/header.php';
?>

<style>
/* ==============================================
   PREMIUM MODERN DESIGN SYSTEM FOR STANDARDS
================================================= */
.standards-card {
    background: #ffffff;
    border: 1px solid rgba(226, 232, 240, 0.8);
    border-radius: 16px;
    box-shadow: 0 4px 20px rgba(18, 48, 95, 0.05);
    padding: 24px;
    transition: all 0.25s ease;
}
html[data-theme="dark"] .standards-card {
    background: #132744;
    border-color: rgba(255, 255, 255, 0.08);
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.25);
}

/* Tree Toggle Button */
.tree-toggle-btn {
    width: 32px;
    height: 32px;
    padding: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    background-color: #f8fafc;
    color: var(--brand, #2f64ad);
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}
.tree-toggle-btn:hover {
    background-color: #eef4fc;
    border-color: var(--brand, #2f64ad);
    transform: scale(1.05);
}
.tree-toggle-btn i {
    display: inline-block;
    transition: transform 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    font-size: 0.9rem;
    line-height: 1;
}
.tree-toggle-btn[aria-expanded="true"] i,
.tree-toggle-btn.is-open i,
.tree-toggle-btn.expanded i {
    transform: rotate(90deg);
}
.tree-toggle-btn[aria-expanded="true"],
.tree-toggle-btn.is-open,
.tree-toggle-btn.expanded {
    background: linear-gradient(135deg, #2f64ad, #174f9a) !important;
    color: #ffffff !important;
    border-color: transparent !important;
    box-shadow: 0 2px 8px rgba(47, 100, 173, 0.35);
}

/* Table Enhancements */
.standards-table {
    border-collapse: separate;
    border-spacing: 0;
}
.standards-table thead th,
.table thead th {
    background: #f8fafc;
    color: #475569;
    font-size: 0.8125rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 14px 16px;
    border-bottom: 2px solid #e2e8f0;
    white-space: nowrap !important;
}
html[data-theme="dark"] .standards-table thead th {
    background: #0f1f38;
    color: #94a3b8;
    border-bottom-color: rgba(255, 255, 255, 0.1);
}
.standards-table tbody tr.table-set-row td {
    padding: 16px 14px;
    border-bottom: 1px solid #f1f5f9;
}
.standards-table tbody tr.table-set-row:hover td {
    background-color: rgba(47, 100, 173, 0.025);
}

/* Badges & Tags */
.badge-code-pill {
    font-size: 0.775rem;
    font-weight: 700;
    padding: 6px 10px;
    border-radius: 8px;
    background: rgba(47, 100, 173, 0.1);
    color: var(--brand, #2f64ad);
    border: 1px solid rgba(47, 100, 173, 0.2);
    letter-spacing: 0.3px;
}
.badge-doc-pill {
    font-size: 0.8125rem;
    padding: 6px 12px;
    border-radius: 20px;
    background: #f0fdf4;
    color: #166534;
    border: 1px solid #bbf7d0;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-weight: 500;
}
html[data-theme="dark"] .badge-doc-pill {
    background: rgba(22, 101, 52, 0.2);
    color: #86efac;
    border-color: rgba(134, 239, 172, 0.3);
}

/* Action Icon Buttons */
.btn-action-round {
    width: 34px;
    height: 34px;
    padding: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    transition: all 0.2s ease;
}
.btn-action-round:hover {
    transform: translateY(-2px);
}

/* Nested Containers (Hierarchy Level 2 & 3) */
.nested-container {
    background: #f8fafc;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    border-left: 4px solid var(--brand, #2f64ad);
    padding: 18px 20px;
    margin: 8px 12px 14px 12px;
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.03);
}
.nested-criteria-container {
    background: #ffffff;
    border-radius: 10px;
    border: 1px solid #e2e8f0;
    border-left: 3px solid #10b981;
    padding: 14px 16px;
    margin: 6px 0 10px 20px;
}
html[data-theme="dark"] .nested-container {
    background: #0f1f38;
    border-color: rgba(255, 255, 255, 0.1);
}
html[data-theme="dark"] .nested-criteria-container {
    background: #132744;
    border-color: rgba(255, 255, 255, 0.1);
}

/* Custom Modern Pagination */
.modern-pagination-wrapper {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    margin-top: 20px;
    padding-top: 18px;
    border-top: 1px solid #e2e8f0;
}
.modern-pagination {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    list-style: none;
    margin: 0;
    padding: 4px;
    background: #f8fafc;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
}
html[data-theme="dark"] .modern-pagination {
    background: #0f1f38;
    border-color: rgba(255, 255, 255, 0.1);
}
.modern-pagination .page-item .page-link {
    min-width: 36px;
    height: 36px;
    padding: 0 12px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px !important;
    border: 1px solid transparent;
    color: #475569;
    font-weight: 600;
    font-size: 0.875rem;
    background: transparent;
    transition: all 0.2s ease;
    text-decoration: none;
}
html[data-theme="dark"] .modern-pagination .page-item .page-link {
    color: #94a3b8;
}
.modern-pagination .page-item .page-link:hover {
    background: #ffffff;
    color: var(--brand, #2f64ad);
    border-color: #e2e8f0;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
    transform: translateY(-1px);
}
.modern-pagination .page-item.active .page-link {
    background: linear-gradient(135deg, #2f64ad, #174f9a) !important;
    color: #ffffff !important;
    border-color: transparent !important;
    box-shadow: 0 3px 10px rgba(47, 100, 173, 0.35);
}
.modern-pagination .page-item.disabled .page-link {
    color: #cbd5e1;
    cursor: not-allowed;
    background: transparent;
    transform: none;
}

/* PDF Modal */
.pdf-viewer-modal-dialog {
    max-width: 90vw;
    height: 90vh;
}
.pdf-iframe-container {
    width: 100%;
    height: calc(85vh - 120px);
    border: none;
    border-radius: 8px;
}
</style>

<!-- Feedback Alerts -->
<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show shadow-sm rounded-3 border-0" role="alert" style="background-color: #ecfdf5; color: #065f46; border-left: 4px solid #10b981 !important;">
        <i class="bi bi-check-circle-fill me-2 fs-5 text-success"></i><?= htmlspecialchars($success) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show shadow-sm rounded-3 border-0" role="alert" style="background-color: #fef2f2; color: #991b1b; border-left: 4px solid #ef4444 !important;">
        <i class="bi bi-exclamation-triangle-fill me-2 fs-5 text-danger"></i><?= htmlspecialchars($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- Toast Notification for AJAX Activation -->
<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1090;">
    <div id="liveToast" class="toast align-items-center text-white bg-dark border-0 shadow-lg rounded-3" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body d-flex align-items-center gap-2 py-3 px-3" id="toastMessage">
                <i class="bi bi-check-circle-fill text-success fs-5"></i>
                <span class="fw-medium">Cập nhật trạng thái thành công!</span>
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-12">
        <div class="standards-card">
            <!-- Top Header & Actions -->
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
                <div>
                    <div class="d-flex align-items-center gap-2">
                        <div class="p-2 rounded-3 bg-primary-subtle text-primary">
                            <i class="bi bi-collection-fill fs-4"></i>
                        </div>
                        <div>
                            <h2 class="h5 mb-0 fw-bold text-dark">Quản lý Bộ tiêu chuẩn động</h2>
                            <span class="text-muted small">Mô hình cây phân cấp: <strong>Bộ tiêu chuẩn &rarr; Tiêu chuẩn &rarr; Tiêu chí</strong></span>
                        </div>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <button class="btn btn-primary d-inline-flex align-items-center gap-2 px-3 py-2 rounded-3 shadow-sm" type="button" data-bs-toggle="modal" data-bs-target="#modalAddStandardSet">
                        <i class="bi bi-plus-circle-fill"></i> Thêm mới Bộ Tiêu chuẩn
                    </button>
                    <a class="btn btn-outline-secondary d-inline-flex align-items-center gap-2 px-3 py-2 rounded-3" href="<?= base_url('admin/standard_sets.php?export=excel' . (!empty($currentFilterParams) ? '&' . http_build_query($currentFilterParams) : '')) ?>">
                        <i class="bi bi-file-earmark-excel-fill text-success"></i> Xuất Excel
                    </a>
                </div>
            </div>

            <!-- ======================= BỘ LỌC & TÌM KIẾM TẬP TRUNG THÔNG MINH ======================= -->
            <div class="card border-0 shadow-sm rounded-4 mb-4 filter-panel-card" style="background: var(--surface, #ffffff); border: 1px solid rgba(226, 232, 240, 0.8) !important;">
                <div class="card-body p-4">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 pb-2 border-bottom">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge rounded-pill bg-primary-subtle text-primary px-3 py-2 fs-6">
                                <i class="bi bi-funnel-fill me-1"></i> Bộ lọc & Tìm kiếm tập trung
                            </span>
                            <span class="text-secondary small d-none d-md-inline">
                                Lọc tự động ngay khi nhập hoặc chọn điều kiện
                            </span>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3" id="btnResetAll" title="Đặt lại tất cả bộ lọc">
                                <i class="bi bi-arrow-counterclockwise me-1"></i> Đặt lại
                            </button>
                        </div>
                    </div>

                    <form id="filterForm" method="get" action="" class="row g-3">
                        <!-- CÁCH 1: Ô NHẬP TỪ KHÓA TÌM NHANH (MÃ HOẶC TÊN BỘ TIÊU CHUẨN) -->
                        <div class="col-12 col-xl-4">
                            <label for="filterKeyword" class="form-label fw-bold small text-primary mb-1 d-flex align-items-center gap-1">
                                <i class="bi bi-search"></i> Cách 1: Ô nhập từ khóa (Mã / Tên bộ tiêu chuẩn)
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0 text-muted">
                                    <i class="bi bi-search"></i>
                                </span>
                                <input type="text" 
                                       class="form-control bg-light border-start-0 border-end-0 ps-0" 
                                       id="filterKeyword" 
                                       name="q"
                                       placeholder="Nhập mã (BTC01...) hoặc tên, số hiệu..." 
                                       value="<?= htmlspecialchars($searchKeyword) ?>"
                                       autocomplete="off">
                                <button class="btn btn-light border border-start-0 text-muted" type="button" id="btnClearKeyword" style="display: <?= $searchKeyword !== '' ? 'block' : 'none' ?>;" title="Xóa từ khóa">
                                    <i class="bi bi-x-circle-fill"></i>
                                </button>
                            </div>
                            <div class="form-text small text-secondary mt-1">
                                <i class="bi bi-lightning-charge text-warning"></i> Tự động tìm kiếm ngay khi gõ
                            </div>
                        </div>

                        <!-- CÁCH 2: CỤM 3 Ô CHỌN NHANH (DROPDOWN SONG SONG & LIÊN KẾT) -->
                        <div class="col-12 col-xl-8">
                            <label class="form-label fw-bold small text-primary mb-1 d-flex align-items-center gap-1">
                                <i class="bi bi-diagram-3"></i> Cách 2: Ô chọn nhanh phân cấp (Bộ Tiêu chuẩn &rarr; Tiêu chuẩn &rarr; Trạng thái)
                            </label>
                            <div class="row g-2">
                                <!-- Dropdown 1: Bộ Tiêu chuẩn -->
                                <div class="col-12 col-md-4">
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text bg-light text-muted" title="Lọc theo Bộ Tiêu chuẩn"><i class="bi bi-collection"></i></span>
                                        <select class="form-select form-select-sm" id="filterStandardSet" name="standard_set">
                                            <option value="">-- Tất cả Bộ Tiêu chuẩn (<?= count($allSetsForFilter) ?>) --</option>
                                            <?php foreach ($allSetsForFilter as $stSet): ?>
                                                <option value="<?= htmlspecialchars($stSet['MaBoTieuChuan']) ?>" <?= $selectedStandardSet === (string)$stSet['MaBoTieuChuan'] ? 'selected' : '' ?> title="<?= htmlspecialchars($stSet['TenBoTieuChuan']) ?>">
                                                    <?= htmlspecialchars($stSet['MaBoTieuChuan'] . ' - ' . $stSet['TenBoTieuChuan']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>

                                <!-- Dropdown 2: Tiêu chuẩn (Tự động lọc theo Bộ tiêu chuẩn đã chọn) -->
                                <div class="col-12 col-md-4">
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text bg-light text-muted" title="Lọc theo Tiêu chuẩn"><i class="bi bi-folder2"></i></span>
                                        <select class="form-select form-select-sm" id="filterStandard" name="standard">
                                            <option value="">-- Tất cả Tiêu chuẩn (<?= count($allStandardsForFilter) ?>) --</option>
                                            <?php foreach ($allStandardsForFilter as $tc): ?>
                                                <option value="<?= htmlspecialchars($tc['MaTieuChuan']) ?>" 
                                                        data-set="<?= htmlspecialchars($tc['MaBoTieuChuan']) ?>"
                                                        <?= $selectedStandard === (string)$tc['MaTieuChuan'] ? 'selected' : '' ?> 
                                                        title="<?= htmlspecialchars($tc['TenTieuChuan']) ?>">
                                                    <?= htmlspecialchars($tc['MaTieuChuan'] . ' - ' . $tc['TenTieuChuan']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>

                                <!-- Dropdown 3: Trạng thái -->
                                <div class="col-12 col-md-4">
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text bg-light text-muted" title="Lọc theo Trạng thái"><i class="bi bi-toggle-on"></i></span>
                                        <select class="form-select form-select-sm" id="filterStatus" name="status">
                                            <option value="">-- Tất cả Trạng thái --</option>
                                            <option value="1" <?= $selectedStatus === 1 ? 'selected' : '' ?>>Hoạt động</option>
                                            <option value="0" <?= $selectedStatus === 0 ? 'selected' : '' ?>>Ngừng hoạt động</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="form-text small text-secondary mt-1">
                                <i class="bi bi-arrow-repeat text-info"></i> Các ô chọn tự động liên kết và lọc danh sách tương ứng
                            </div>
                        </div>
                    </form>

                    <!-- DẢI CHIPS HIỂN THỊ CÁC TIÊU CHÍ ĐANG LỌC -->
                    <div id="activeFilterTags" class="d-flex flex-wrap align-items-center gap-2 mt-3 pt-3 border-top" style="<?= ($searchKeyword !== '' || $selectedStandardSet !== '' || $selectedStandard !== '' || $selectedStatus !== null) ? '' : 'display: none !important;' ?>">
                        <span class="text-secondary small fw-semibold"><i class="bi bi-tags"></i> Đang lọc theo:</span>
                        <div id="tagList" class="d-flex flex-wrap gap-2"></div>
                    </div>
                </div>
            </div>

            <!-- Main Master-Detail Table -->
            <div class="table-responsive border rounded-3 overflow-hidden">
                <table class="table standards-table align-middle mb-0" data-paginated="true" data-no-auto-paginate="true">
                    <thead>
                        <tr>
                            <th class="text-center text-nowrap" style="width: 70px;">STT</th>
                            <th class="text-nowrap" style="min-width: 280px;">Tên bộ tiêu chuẩn</th>
                            <th class="text-nowrap" style="min-width: 170px;">Số hiệu</th>
                            <th class="text-center text-nowrap" style="min-width: 140px;">Ngày ban hành</th>
                            <th class="text-center text-nowrap" style="min-width: 150px;">File dữ liệu</th>
                            <th class="text-center text-nowrap" style="min-width: 140px;">Trạng thái</th>
                            <th class="text-end text-nowrap" style="min-width: 180px;">Hành động</th>
                        </tr>
                    </thead>
                    <tbody id="standardSetsTableBody">
                    <?php if (empty($standardSetsList)): ?>
                        <tr>
                            <td colspan="7" class="text-center text-secondary py-5">
                                <i class="bi bi-inbox fs-2 d-block mb-2 text-muted"></i>
                                Không tìm thấy dữ liệu bộ tiêu chuẩn nào
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($standardSetsList as $index => $set): 
                            $setId = $set['MaBoTieuChuan'];
                            $standards = $allStandardsBySet[$setId] ?? [];
                            $isActive = (int)$set['TrangThai'] === 1;
                            $formattedDate = $set['NgayBanHanh'] ? date('d/m/Y', strtotime($set['NgayBanHanh'])) : '-';
                            $sttNumber = $index + 1 + $offset;
                        ?>
                            <!-- LEVEL 1: THÔNG TƯ / BỘ TIÊU CHUẨN ROW -->
                            <tr class="table-set-row <?= $isActive ? 'is-active-set' : '' ?>" id="set-row-<?= htmlspecialchars($setId) ?>">
                                <td class="text-center">
                                    <div class="d-inline-flex align-items-center gap-1">
                                        <button class="btn btn-sm btn-light tree-toggle-btn" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-set-<?= htmlspecialchars($setId) ?>" aria-expanded="false" title="Mở rộng / Thu gọn Tiêu chuẩn con">
                                            <i class="bi bi-chevron-right fs-6"></i>
                                        </button>
                                        <span class="fw-bold text-secondary"><?= $sttNumber ?></span>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-3">
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle py-2 px-2 fs-7 fw-bold text-nowrap"><?= htmlspecialchars($setId) ?></span>
                                        <div class="flex-grow-1">
                                            <a class="fw-bold text-decoration-none text-dark d-block mb-1" href="javascript:void(0)" data-bs-toggle="collapse" data-bs-target="#collapse-set-<?= htmlspecialchars($setId) ?>">
                                                <?= htmlspecialchars($set['TenBoTieuChuan']) ?>
                                            </a>
                                            <?php if ($set['MoTa']): ?>
                                                <small class="text-muted line-clamp-1 mb-1 d-block"><?= htmlspecialchars($set['MoTa']) ?></small>
                                            <?php endif; ?>
                                            <div class="d-flex flex-wrap gap-2 align-items-center">
                                                <span class="badge bg-secondary-subtle text-secondary small"><i class="bi bi-folder2 me-1"></i><?= count($standards) ?> Tiêu chuẩn</span>
                                                <span class="badge bg-secondary-subtle text-secondary small"><i class="bi bi-list-check me-1"></i><?= (int)$set['total_criteria'] ?> Tiêu chí</span>
                                                <span class="badge bg-secondary-subtle text-secondary small"><i class="bi bi-file-earmark-check me-1"></i><?= (int)$set['total_evidences'] ?> Minh chứng</span>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-info-subtle text-dark border border-info-subtle fw-semibold">
                                        <i class="bi bi-file-text me-1"></i><?= htmlspecialchars($set['ThongTu'] ?: 'Chưa nhập số hiệu') ?>
                                    </span>
                                </td>
                                <td class="text-center text-nowrap">
                                    <span class="small text-secondary"><?= htmlspecialchars($formattedDate) ?></span>
                                </td>
                                <td class="text-center">
                                    <?php if (!empty($set['TepTinPDF'])): ?>
                                        <div class="d-inline-flex gap-1">
                                            <button type="button" class="btn btn-sm btn-outline-danger btn-view-pdf" data-pdf-url="<?= base_url(htmlspecialchars($set['TepTinPDF'])) ?>" data-pdf-title="<?= htmlspecialchars($set['ThongTu'] . ' - ' . $set['TenBoTieuChuan']) ?>" title="Xem chi tiết file PDF trực tiếp">
                                                <i class="bi bi-file-earmark-pdf me-1"></i>Xem chi tiết
                                            </button>
                                            <a class="btn btn-sm btn-light text-secondary" href="<?= base_url(htmlspecialchars($set['TepTinPDF'])) ?>" target="_blank" download title="Tải file PDF">
                                                <i class="bi bi-download"></i>
                                            </a>
                                        </div>
                                    <?php else: ?>
                                        <span class="badge bg-light text-muted border">Chưa có file</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <div class="form-check form-switch d-inline-block m-0" title="Chuyển đổi trạng thái Hoạt động / Ngừng hoạt động">
                                        <input class="form-check-input status-switch-toggle" type="checkbox" role="switch" id="switch-<?= htmlspecialchars($setId) ?>" data-set-id="<?= htmlspecialchars($setId) ?>" <?= $isActive ? 'checked' : '' ?> style="cursor: pointer; width: 2.3rem; height: 1.25rem;">
                                    </div>
                                    <div class="small mt-1 <?= $isActive ? 'text-success fw-bold' : 'text-muted' ?>" id="status-label-<?= htmlspecialchars($setId) ?>">
                                        <?= $isActive ? 'Hoạt động' : 'Ngừng hoạt động' ?>
                                    </div>
                                </td>
                                <td class="text-end">
                                    <div class="d-inline-flex gap-1">
                                        <button class="btn btn-sm btn-outline-success" type="button" data-bs-toggle="modal" data-bs-target="#modalAddStandard" data-set-id="<?= htmlspecialchars($setId) ?>" data-set-name="<?= htmlspecialchars($set['TenBoTieuChuan']) ?>" title="Thêm Tiêu chuẩn con vào bộ này">
                                            <i class="bi bi-plus-circle me-1"></i>Thêm TC
                                        </button>
                                        <button class="btn btn-sm btn-outline-info btn-view-set-detail" type="button"
                                            data-id="<?= htmlspecialchars($setId) ?>"
                                            data-name="<?= htmlspecialchars($set['TenBoTieuChuan']) ?>"
                                            data-thongtu="<?= htmlspecialchars($set['ThongTu']) ?>"
                                            data-date="<?= htmlspecialchars($set['NgayBanHanh']) ?>"
                                            data-date-formatted="<?= htmlspecialchars($formattedDate) ?>"
                                            data-desc="<?= htmlspecialchars($set['MoTa']) ?>"
                                            data-status="<?= $isActive ? '1' : '0' ?>"
                                            data-status-text="<?= $isActive ? 'Hoạt động' : 'Ngừng hoạt động' ?>"
                                            data-pdf="<?= htmlspecialchars($set['TepTinPDF'] ?? '') ?>"
                                            data-pdf-url="<?= !empty($set['TepTinPDF']) ? base_url(htmlspecialchars($set['TepTinPDF'])) : '' ?>"
                                            data-view-url="<?= base_url('user/view.php?standard_set=' . urlencode($setId)) ?>"
                                            data-standards-count="<?= count($standards) ?>"
                                            data-criteria-count="<?= (int)$set['total_criteria'] ?>"
                                            data-evidences-count="<?= (int)$set['total_evidences'] ?>"
                                            title="Xem chi tiết các trường dữ liệu và file">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-primary btn-edit-set" type="button" 
                                            data-id="<?= htmlspecialchars($setId) ?>"
                                            data-name="<?= htmlspecialchars($set['TenBoTieuChuan']) ?>"
                                            data-thongtu="<?= htmlspecialchars($set['ThongTu']) ?>"
                                            data-date="<?= htmlspecialchars($set['NgayBanHanh']) ?>"
                                            data-desc="<?= htmlspecialchars($set['MoTa']) ?>"
                                            data-status="<?= $isActive ? '1' : '0' ?>"
                                            data-pdf="<?= htmlspecialchars($set['TepTinPDF']) ?>"
                                            title="Sửa Thông tư / Bộ tiêu chuẩn">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <form method="post" class="d-inline" data-confirm-form="Bạn có chắc chắn muốn xóa bộ tiêu chuẩn này? Hệ thống sẽ chặn xóa nếu đã có Tiêu chuẩn con bên trong.">
                                            <input type="hidden" name="action" value="delete_standard_set">
                                            <input type="hidden" name="id" value="<?= htmlspecialchars($setId) ?>">
                                            <button class="btn btn-sm btn-outline-danger" type="submit" title="Xóa Bộ tiêu chuẩn"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>

                            <!-- LEVEL 2 COLLAPSIBLE CONTAINER: CẤP TIÊU CHUẨN -->
                            <tr class="p-0 border-0">
                                <td colspan="7" class="p-0 border-0">
                                    <div class="collapse" id="collapse-set-<?= htmlspecialchars($setId) ?>">
                                        <div class="nested-container mx-3 my-2 shadow-sm">
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <div class="d-flex align-items-center gap-2">
                                                    <i class="bi bi-diagram-3-fill text-primary"></i>
                                                    <h6 class="mb-0 fw-bold text-dark">Quản lý Cấp Tiêu chuẩn (thuộc: <?= htmlspecialchars($set['TenBoTieuChuan']) ?>)</h6>
                                                    <span class="badge bg-primary-subtle text-primary"><?= count($standards) ?> tiêu chuẩn</span>
                                                </div>
                                                <button class="btn btn-sm btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#modalAddStandard" data-set-id="<?= htmlspecialchars($setId) ?>" data-set-name="<?= htmlspecialchars($set['TenBoTieuChuan']) ?>">
                                                    <i class="bi bi-plus-lg me-1"></i>Thêm Tiêu chuẩn mới
                                                </button>
                                            </div>

                                            <?php if (empty($standards)): ?>
                                                <div class="text-center py-3 text-muted bg-white rounded border border-dashed">
                                                    <small>Chưa có tiêu chuẩn nào được tạo trong bộ tiêu chuẩn này. Bấm <strong>"+ Thêm Tiêu chuẩn mới"</strong> để bắt đầu.</small>
                                                </div>
                                            <?php else: ?>
                                                <div class="table-responsive bg-white rounded border">
                                                    <table class="table table-sm align-middle mb-0">
                                                        <thead class="table-light">
                                                            <tr>
                                                                <th style="width: 50px;" class="text-center">Sổ</th>
                                                                <th style="width: 120px;">Mã TC</th>
                                                                <th style="min-width: 250px;">Tên Tiêu chuẩn</th>
                                                                <th style="min-width: 200px;">Mô tả</th>
                                                                <th style="width: 120px;" class="text-center">Số tiêu chí</th>
                                                                <th style="width: 160px;" class="text-end">Hành động</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php foreach ($standards as $tc): 
                                                                $tcId = $tc['MaTieuChuan'];
                                                                $criteria = $allCriteriaByStandard[$tcId] ?? [];
                                                            ?>
                                                                <tr class="nested-standard-row" id="standard-row-<?= htmlspecialchars($tcId) ?>">
                                                                    <td class="text-center">
                                                                        <button class="btn btn-xs btn-outline-secondary tree-toggle-btn" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-standard-<?= htmlspecialchars($tcId) ?>" aria-expanded="false" title="Mở rộng / Thu gọn Tiêu chí con">
                                                                            <i class="bi bi-chevron-right" style="font-size: 0.75rem;"></i>
                                                                        </button>
                                                                    </td>
                                                                    <td class="fw-bold text-primary"><?= htmlspecialchars($tcId) ?></td>
                                                                    <td class="fw-semibold">
                                                                        <a class="text-decoration-none text-dark" href="javascript:void(0)" data-bs-toggle="collapse" data-bs-target="#collapse-standard-<?= htmlspecialchars($tcId) ?>">
                                                                            <?= htmlspecialchars($tc['TenTieuChuan']) ?>
                                                                        </a>
                                                                    </td>
                                                                    <td class="small text-secondary"><?= htmlspecialchars($tc['MoTa'] ?: '-') ?></td>
                                                                    <td class="text-center">
                                                                        <span class="badge bg-secondary-subtle text-secondary"><?= count($criteria) ?> tiêu chí</span>
                                                                    </td>
                                                                    <td class="text-end">
                                                                        <div class="d-inline-flex gap-1">
                                                                            <button class="btn btn-xs btn-outline-success" type="button" data-bs-toggle="modal" data-bs-target="#modalAddCriterion" data-standard-id="<?= htmlspecialchars($tcId) ?>" data-standard-name="<?= htmlspecialchars($tc['TenTieuChuan']) ?>" title="Thêm Tiêu chí con">
                                                                                <i class="bi bi-plus-circle me-1"></i>Tiêu chí
                                                                            </button>
                                                                            <button class="btn btn-xs btn-outline-primary btn-edit-standard" type="button"
                                                                                data-id="<?= htmlspecialchars($tcId) ?>"
                                                                                data-name="<?= htmlspecialchars($tc['TenTieuChuan']) ?>"
                                                                                data-desc="<?= htmlspecialchars($tc['MoTa']) ?>"
                                                                                data-order="<?= htmlspecialchars($tc['ThuTu']) ?>"
                                                                                data-set-id="<?= htmlspecialchars($setId) ?>"
                                                                                title="Sửa Tiêu chuẩn">
                                                                                <i class="bi bi-pencil"></i>
                                                                            </button>
                                                                            <form method="post" class="d-inline" data-confirm-form="Bạn chắc chắn muốn xóa tiêu chuẩn này? Hệ thống sẽ chặn nếu có tiêu chí con.">
                                                                                <input type="hidden" name="action" value="delete_standard">
                                                                                <input type="hidden" name="id" value="<?= htmlspecialchars($tcId) ?>">
                                                                                <button class="btn btn-xs btn-outline-danger" type="submit" title="Xóa Tiêu chuẩn"><i class="bi bi-trash"></i></button>
                                                                            </form>
                                                                        </div>
                                                                    </td>
                                                                </tr>

                                                                <!-- LEVEL 3 COLLAPSIBLE CONTAINER: CẤP TIÊU CHÍ -->
                                                                <tr class="p-0 border-0">
                                                                    <td colspan="6" class="p-0 border-0">
                                                                        <div class="collapse" id="collapse-standard-<?= htmlspecialchars($tcId) ?>">
                                                                            <div class="p-3 my-1 ms-4 bg-light rounded border">
                                                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                                                    <div class="d-flex align-items-center gap-2">
                                                                                        <i class="bi bi-list-task text-success"></i>
                                                                                        <strong class="small text-dark">Quản lý Cấp Tiêu chí (thuộc: <?= htmlspecialchars($tc['TenTieuChuan']) ?>)</strong>
                                                                                        <span class="badge bg-success-subtle text-success small"><?= count($criteria) ?> tiêu chí</span>
                                                                                    </div>
                                                                                    <button class="btn btn-xs btn-success" type="button" data-bs-toggle="modal" data-bs-target="#modalAddCriterion" data-standard-id="<?= htmlspecialchars($tcId) ?>" data-standard-name="<?= htmlspecialchars($tc['TenTieuChuan']) ?>">
                                                                                        <i class="bi bi-plus-lg me-1"></i>Thêm Tiêu chí
                                                                                    </button>
                                                                                </div>

                                                                                <?php if (empty($criteria)): ?>
                                                                                    <div class="text-center py-2 text-muted small bg-white rounded border border-dashed">
                                                                                        Chưa có tiêu chí nào. Bấm <strong>"+ Thêm Tiêu chí"</strong> để tạo mới.
                                                                                    </div>
                                                                                <?php else: ?>
                                                                                    <div class="table-responsive bg-white rounded border">
                                                                                        <table class="table table-xs table-hover align-middle mb-0">
                                                                                            <thead class="table-secondary">
                                                                                                <tr>
                                                                                                    <th style="width: 100px;">Mã Tiêu chí</th>
                                                                                                    <th style="min-width: 250px;">Tên Tiêu chí</th>
                                                                                                    <th style="min-width: 250px;">Nội dung / Yêu cầu</th>
                                                                                                    <th style="width: 140px;" class="text-center">Minh chứng gắn</th>
                                                                                                    <th style="width: 110px;" class="text-end">Hành động</th>
                                                                                                </tr>
                                                                                            </thead>
                                                                                            <tbody>
                                                                                                <?php foreach ($criteria as $tchi): ?>
                                                                                                    <tr>
                                                                                                        <td class="fw-bold text-success"><?= htmlspecialchars($tchi['MaTieuChi']) ?></td>
                                                                                                        <td class="fw-medium"><?= htmlspecialchars($tchi['TenTieuChi']) ?></td>
                                                                                                        <td class="small text-secondary"><?= htmlspecialchars($tchi['NoiDung'] ?: '-') ?></td>
                                                                                                        <td class="text-center">
                                                                                                            <span class="badge <?= (int)$tchi['total_evidences'] > 0 ? 'bg-primary' : 'bg-light text-muted border' ?>">
                                                                                                                <?= (int)$tchi['total_evidences'] ?> minh chứng
                                                                                                            </span>
                                                                                                        </td>
                                                                                                        <td class="text-end">
                                                                                                            <div class="d-inline-flex gap-1">
                                                                                                                <button class="btn btn-xs btn-outline-primary btn-edit-criterion" type="button"
                                                                                                                    data-id="<?= htmlspecialchars($tchi['MaTieuChi']) ?>"
                                                                                                                    data-name="<?= htmlspecialchars($tchi['TenTieuChi']) ?>"
                                                                                                                    data-desc="<?= htmlspecialchars($tchi['NoiDung']) ?>"
                                                                                                                    data-order="<?= htmlspecialchars($tchi['ThuTu']) ?>"
                                                                                                                    data-standard-id="<?= htmlspecialchars($tcId) ?>"
                                                                                                                    title="Sửa Tiêu chí">
                                                                                                                    <i class="bi bi-pencil"></i>
                                                                                                                </button>
                                                                                                                <form method="post" class="d-inline" data-confirm-form="Bạn chắc chắn muốn xóa tiêu chí này? Chỉ xóa được khi chưa gắn minh chứng nào.">
                                                                                                                    <input type="hidden" name="action" value="delete_criterion">
                                                                                                                    <input type="hidden" name="id" value="<?= htmlspecialchars($tchi['MaTieuChi']) ?>">
                                                                                                                    <button class="btn btn-xs btn-outline-danger" type="submit" title="Xóa Tiêu chí"><i class="bi bi-trash"></i></button>
                                                                                                                </form>
                                                                                                            </div>
                                                                                                        </td>
                                                                                                    </tr>
                                                                                                <?php endforeach; ?>
                                                                                            </tbody>
                                                                                        </table>
                                                                                    </div>
                                                                                <?php endif; ?>
                                                                            </div>
                                                                        </div>
                                                                    </td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Modern Pagination Bar (5 records per page) -->
            <?php if ($totalPages > 1): ?>
                <div class="modern-pagination-wrapper">
                    <div class="small text-muted d-flex align-items-center gap-2">
                        <i class="bi bi-info-circle text-primary"></i>
                        <span>Hiển thị <strong><?= min($totalRecords, $offset + 1) ?></strong> &ndash; <strong><?= min($totalRecords, $offset + count($standardSetsList)) ?></strong> trong tổng số <strong><?= $totalRecords ?></strong> bộ tiêu chuẩn (5 mục / trang)</span>
                    </div>
                    <nav aria-label="Phân trang bộ tiêu chuẩn">
                        <ul class="modern-pagination">
                            <!-- Previous Page -->
                            <li class="page-item <?= $currentPage <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= base_url('admin/standard_sets.php?' . http_build_query(array_merge($currentFilterParams, ['page' => $currentPage - 1]))) ?>" aria-label="Trang trước" title="Trang trước">
                                    <i class="bi bi-chevron-left"></i>
                                </a>
                            </li>

                            <!-- Page Numbers -->
                            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                                <li class="page-item <?= $p === $currentPage ? 'active' : '' ?>">
                                    <a class="page-link" href="<?= base_url('admin/standard_sets.php?' . http_build_query(array_merge($currentFilterParams, ['page' => $p]))) ?>">
                                        <?= $p ?>
                                    </a>
                                </li>
                            <?php endfor; ?>

                            <!-- Next Page -->
                            <li class="page-item <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= base_url('admin/standard_sets.php?' . http_build_query(array_merge($currentFilterParams, ['page' => $currentPage + 1]))) ?>" aria-label="Trang sau" title="Trang sau">
                                    <i class="bi bi-chevron-right"></i>
                                </a>
                            </li>
                        </ul>
                    </nav>
                </div>
            <?php elseif ($totalRecords > 0): ?>
                <div class="modern-pagination-wrapper">
                    <span class="small text-muted"><i class="bi bi-check2-circle text-success me-1"></i>Tổng số: <strong><?= $totalRecords ?></strong> bộ tiêu chuẩn</span>
                    <span class="small text-muted">Trang 1 / 1</span>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ==========================================
     MODAL 1: THÊM / SỬA BỘ TIÊU CHUẨN
=============================================== -->
<div class="modal fade" id="modalAddStandardSet" tabindex="-1" aria-labelledby="modalStandardSetLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="" enctype="multipart/form-data" id="formStandardSet">
                <input type="hidden" name="action" value="save_standard_set">
                <input type="hidden" name="id" id="set_edit_id" value="">
                <input type="hidden" name="old_tep_tin_pdf" id="set_old_pdf" value="">

                <div class="modal-header">
                    <h5 class="modal-title" id="modalStandardSetLabel"><i class="bi bi-collection me-2"></i>Thêm mới Thông tư / Bộ Tiêu chuẩn</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Mã Bộ tiêu chuẩn <span class="text-danger">*</span></label>
                            <input type="text" name="ma_bo_tieu_chuan" id="set_ma_bo" class="form-control" placeholder="VD: BTC01, BTC_2026..." required>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label fw-bold">Số hiệu / Thông tư ban hành <span class="text-danger">*</span></label>
                            <input type="text" name="thong_tu" id="set_thong_tu" class="form-control" placeholder="VD: Thông tư 04/2016/TT-BGDĐT" required>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-bold">Tên Bộ tiêu chuẩn <span class="text-danger">*</span></label>
                            <input type="text" name="ten_bo_tieu_chuan" id="set_ten_bo" class="form-control" placeholder="VD: Bộ tiêu chuẩn đánh giá chất lượng chương trình đào tạo..." required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Ngày ban hành</label>
                            <input type="date" name="ngay_ban_hanh" id="set_ngay_ban_hanh" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Trạng thái</label>
                            <select name="trang_thai" id="set_trang_thai" class="form-select">
                                <option value="1">Hoạt động</option>
                                <option value="0">Ngừng hoạt động</option>
                            </select>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-bold">Tải lên File dữ liệu Thông tư / Tiêu chuẩn</label>
                            <input type="file" name="tep_tin_pdf" id="set_file_pdf" class="form-control" accept=".pdf,.png,.jpg,.jpeg,.gif,.webp,.doc,.docx,.xls,.xlsx,application/pdf,image/*">
                            <div class="form-text" id="pdf_help_text">Hỗ trợ định dạng: <strong>PDF, PNG, JPG, JPEG, WEBP, DOCX</strong> (tối đa 50MB). Xem trực tiếp trên tab trình duyệt hoặc popup.</div>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-bold">Mô tả / Ghi chú</label>
                            <textarea name="mo_ta" id="set_mo_ta" class="form-control" rows="3" placeholder="Nhập tóm tắt nội dung thông tư / bộ tiêu chuẩn..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy bỏ</button>
                    <button type="submit" class="btn btn-primary" id="btnSaveSet"><i class="bi bi-save me-1"></i>Lưu thông tin</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ==========================================
     MODAL 2: THÊM / SỬA TIÊU CHUẨN
=============================================== -->
<div class="modal fade" id="modalAddStandard" tabindex="-1" aria-labelledby="modalStandardLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="" id="formStandard">
                <input type="hidden" name="action" value="save_standard">
                <input type="hidden" name="id" id="std_edit_id" value="">

                <div class="modal-header">
                    <h5 class="modal-title" id="modalStandardLabel"><i class="bi bi-folder-plus me-2"></i>Thêm mới Tiêu chuẩn</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label fw-bold">Thuộc Thông tư / Bộ tiêu chuẩn <span class="text-danger">*</span></label>
                            <select name="ma_bo_tieu_chuan" id="std_ma_bo" class="form-select" required>
                                <?php foreach ($standardSetsList as $s): ?>
                                    <option value="<?= htmlspecialchars($s['MaBoTieuChuan']) ?>"><?= htmlspecialchars($s['MaBoTieuChuan'] . ' - ' . $s['TenBoTieuChuan']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Mã Tiêu chuẩn <span class="text-danger">*</span></label>
                            <input type="text" name="ma_tieu_chuan" id="std_ma_tc" class="form-control" placeholder="VD: TC01, TC02..." required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Thứ tự hiển thị</label>
                            <input type="number" name="thu_tu" id="std_thu_tu" class="form-control" value="1" min="1">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-bold">Tên Tiêu chuẩn <span class="text-danger">*</span></label>
                            <input type="text" name="ten_tieu_chuan" id="std_ten_tc" class="form-control" placeholder="VD: Tiêu chuẩn 1: Mục tiêu và chuẩn đầu ra của CTĐT" required>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-bold">Mô tả / Yêu cầu tiêu chuẩn</label>
                            <textarea name="mo_ta" id="std_mo_ta" class="form-control" rows="3" placeholder="Nhập mô tả nội dung tiêu chuẩn..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy bỏ</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Lưu Tiêu chuẩn</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ==========================================
     MODAL 3: THÊM / SỬA TIÊU CHÍ
=============================================== -->
<div class="modal fade" id="modalAddCriterion" tabindex="-1" aria-labelledby="modalCriterionLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="" id="formCriterion">
                <input type="hidden" name="action" value="save_criterion">
                <input type="hidden" name="id" id="cri_edit_id" value="">

                <div class="modal-header">
                    <h5 class="modal-title" id="modalCriterionLabel"><i class="bi bi-list-check me-2"></i>Thêm mới Tiêu chí</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label fw-bold">Thuộc Tiêu chuẩn <span class="text-danger">*</span></label>
                            <select name="ma_tieu_chuan" id="cri_ma_tc" class="form-select" required>
                                <?php foreach ($allStandardsBySet as $setK => $standardsList): ?>
                                    <optgroup label="Bộ tiêu chuẩn: <?= htmlspecialchars($setK) ?>">
                                        <?php foreach ($standardsList as $tcItem): ?>
                                            <option value="<?= htmlspecialchars($tcItem['MaTieuChuan']) ?>"><?= htmlspecialchars($tcItem['MaTieuChuan'] . ' - ' . $tcItem['TenTieuChuan']) ?></option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Mã Tiêu chí <span class="text-danger">*</span></label>
                            <input type="text" name="ma_tieu_chi" id="cri_ma_tchi" class="form-control" placeholder="VD: TChi01.1, TChi01.2..." required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Thứ tự hiển thị</label>
                            <input type="number" name="thu_tu" id="cri_thu_tu" class="form-control" value="1" min="1">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-bold">Tên Tiêu chí <span class="text-danger">*</span></label>
                            <input type="text" name="ten_tieu_chi" id="cri_ten_tchi" class="form-control" placeholder="VD: Tiêu chí 1.1: Mục tiêu của CTĐT được xác định rõ ràng..." required>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-bold">Nội dung / Hướng dẫn tiêu chí</label>
                            <textarea name="noi_dung" id="cri_noi_dung" class="form-control" rows="3" placeholder="Nhập nội dung chi tiết hoặc mốc đánh giá của tiêu chí..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy bỏ</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Lưu Tiêu chí</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ==========================================
     MODAL 4: XEM TRỰC TIẾP FILE PDF THÔNG TƯ
=============================================== -->
<div class="modal fade" id="modalPdfViewer" tabindex="-1" aria-labelledby="modalPdfViewerLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered pdf-viewer-modal-dialog">
        <div class="modal-content h-100">
            <div class="modal-header py-2 bg-dark text-white">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-file-earmark-pdf fs-5 text-danger"></i>
                    <h6 class="modal-title mb-0 text-white line-clamp-1" id="modalPdfViewerLabel">Xem chi tiết Thông tư PDF</h6>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <a href="#" id="btnPdfOpenNewTab" target="_blank" class="btn btn-sm btn-outline-light" title="Mở trong tab mới">
                        <i class="bi bi-box-arrow-up-right me-1"></i>Mở tab mới
                    </a>
                    <a href="#" id="btnPdfDownload" download class="btn btn-sm btn-outline-light" title="Tải xuống file PDF">
                        <i class="bi bi-download me-1"></i>Tải về
                    </a>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
            </div>
            <div class="modal-body p-0 bg-secondary-subtle">
                <iframe id="pdfViewerIframe" class="pdf-iframe-container" src="about:blank"></iframe>
            </div>
        </div>
    </div>
</div>

<!-- ==========================================
     MODAL 5: XEM CHI TIẾT BỘ TIÊU CHUẨN
=============================================== -->
<div class="modal fade" id="modalViewStandardSetDetails" tabindex="-1" aria-labelledby="modalViewStandardSetLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white py-3">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-info-circle-fill fs-5 text-white"></i>
                    <h5 class="modal-title mb-0 text-white fw-bold" id="modalViewStandardSetLabel">Chi tiết Bộ Tiêu chuẩn</h5>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <!-- Header Banner -->
                <div class="p-3 mb-3 rounded-3 bg-light border d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-primary px-3 py-2 fs-6 fw-bold" id="view_set_id_badge">BTC01</span>
                        <h6 class="mb-0 fw-bold text-dark fs-6" id="view_set_title">Tên bộ tiêu chuẩn</h6>
                    </div>
                    <span id="view_set_status_badge" class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 fs-7 fw-semibold">
                        <i class="bi bi-check-circle-fill me-1"></i>Hoạt động
                    </span>
                </div>

                <!-- Detail Fields Table -->
                <div class="table-responsive mb-3">
                    <table class="table table-bordered align-middle mb-0">
                        <tbody>
                            <tr>
                                <th style="width: 28%;" class="bg-light text-secondary"><i class="bi bi-upc-scan me-2 text-primary"></i>Mã bộ tiêu chuẩn</th>
                                <td id="view_set_id" class="fw-bold text-primary"></td>
                            </tr>
                            <tr>
                                <th class="bg-light text-secondary"><i class="bi bi-bookmark-star me-2 text-primary"></i>Tên bộ tiêu chuẩn</th>
                                <td id="view_set_name" class="fw-semibold text-dark"></td>
                            </tr>
                            <tr>
                                <th class="bg-light text-secondary"><i class="bi bi-file-earmark-text me-2 text-primary"></i>Số hiệu / Thông tư</th>
                                <td><span id="view_set_thongtu" class="badge bg-info-subtle text-dark border border-info-subtle fs-7 fw-semibold"></span></td>
                            </tr>
                            <tr>
                                <th class="bg-light text-secondary"><i class="bi bi-calendar-event me-2 text-primary"></i>Ngày ban hành</th>
                                <td id="view_set_date" class="text-secondary"></td>
                            </tr>
                            <tr>
                                <th class="bg-light text-secondary"><i class="bi bi-toggle-on me-2 text-primary"></i>Trạng thái</th>
                                <td id="view_set_status_text"></td>
                            </tr>
                            <tr>
                                <th class="bg-light text-secondary"><i class="bi bi-diagram-3 me-2 text-primary"></i>Cấu trúc phân cấp</th>
                                <td>
                                    <div class="d-flex flex-wrap gap-2">
                                        <span class="badge bg-secondary-subtle text-secondary py-2 px-3" id="view_set_standards_count"><i class="bi bi-folder2 me-1"></i>0 Tiêu chuẩn</span>
                                        <span class="badge bg-secondary-subtle text-secondary py-2 px-3" id="view_set_criteria_count"><i class="bi bi-list-check me-1"></i>0 Tiêu chí</span>
                                        <span class="badge bg-secondary-subtle text-secondary py-2 px-3" id="view_set_evidences_count"><i class="bi bi-file-earmark-check me-1"></i>0 Minh chứng</span>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <th class="bg-light text-secondary"><i class="bi bi-card-text me-2 text-primary"></i>Mô tả / Ghi chú</th>
                                <td><div id="view_set_desc" class="text-secondary text-break" style="white-space: pre-wrap;"></div></td>
                            </tr>
                            <tr>
                                <th class="bg-light text-secondary"><i class="bi bi-paperclip me-2 text-primary"></i>File dữ liệu đính kèm</th>
                                <td>
                                    <div id="view_set_file_container">
                                        <!-- Populated via JS -->
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Preview Area -->
                <div id="view_set_preview_box" class="card border shadow-none bg-light p-3" style="display: none;">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                        <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-eye-fill me-2 text-primary"></i>Xem trước File dữ liệu:</h6>
                        <a href="#" id="view_set_preview_newtab_link" target="_blank" class="btn btn-sm btn-primary">
                            <i class="bi bi-box-arrow-up-right me-1"></i>Mở toàn màn hình ở tab mới
                        </a>
                    </div>
                    <div id="view_set_preview_content" class="text-center bg-white rounded border p-2" style="min-height: 200px;">
                        <!-- Embed / Image / Message -->
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light py-2 d-flex justify-content-between">
                <div>
                    <button type="button" class="btn btn-outline-primary btn-sm" id="view_set_btn_edit_trigger">
                        <i class="bi bi-pencil me-1"></i>Chỉnh sửa bộ này
                    </button>
                </div>
                <div class="d-inline-flex gap-2">
                    <a href="#" id="view_set_btn_open_tab_footer" target="_blank" class="btn btn-primary btn-sm" style="display: none;">
                        <i class="bi bi-box-arrow-up-right me-1"></i>Xem file dữ liệu (Tab mới)
                    </a>
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Đóng</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
window.STANDARD_SETS_FILTER_DATA = <?= json_encode($allSetsForFilter, JSON_UNESCAPED_UNICODE) ?>;
window.STANDARDS_FILTER_DATA = <?= json_encode($allStandardsForFilter, JSON_UNESCAPED_UNICODE) ?>;

document.addEventListener('DOMContentLoaded', function () {
    // ==============================================
    // 0. BỘ LỌC & TÌM KIẾM TẬP TRUNG THÔNG MINH
    // ==============================================
    const filterForm        = document.getElementById('filterForm');
    const keywordInput      = document.getElementById('filterKeyword');
    const clearKeywordBtn   = document.getElementById('btnClearKeyword');
    const standardSetSelect = document.getElementById('filterStandardSet');
    const standardSelect    = document.getElementById('filterStandard');
    const statusSelect      = document.getElementById('filterStatus');
    const btnResetAll       = document.getElementById('btnResetAll');
    const activeTagsBox     = document.getElementById('activeFilterTags');
    const tagList           = document.getElementById('tagList');

    let debounceTimer = null;

    const ALL_SETS_DATA = window.STANDARD_SETS_FILTER_DATA || [];
    const ALL_STANDARDS_DATA = window.STANDARDS_FILTER_DATA || [];

    function escapeHtml(text) {
        if (!text) return '';
        const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return text.toString().replace(/[&<>"']/g, m => map[m]);
    }

    // Cập nhật danh sách Tiêu chuẩn theo Bộ tiêu chuẩn đã chọn
    function updateStandardsDropdown(selectedSetId, currentStdVal = '') {
        if (!standardSelect) return;
        let filtered = ALL_STANDARDS_DATA;
        if (selectedSetId) {
            filtered = ALL_STANDARDS_DATA.filter(tc => tc.MaBoTieuChuan === selectedSetId);
        }

        let html = `<option value="">-- Tất cả Tiêu chuẩn (${filtered.length}) --</option>`;
        filtered.forEach(tc => {
            const isSel = (currentStdVal && currentStdVal === tc.MaTieuChuan) ? 'selected' : '';
            html += `<option value="${escapeHtml(tc.MaTieuChuan)}" data-set="${escapeHtml(tc.MaBoTieuChuan)}" ${isSel} title="${escapeHtml(tc.TenTieuChuan)}">${escapeHtml(tc.MaTieuChuan)} - ${escapeHtml(tc.TenTieuChuan)}</option>`;
        });
        standardSelect.innerHTML = html;
    }

    // Hiển thị Chips lọc đang hoạt động
    function renderActiveFilterTags() {
        if (!activeTagsBox || !tagList) return;
        const kw = keywordInput ? keywordInput.value.trim() : '';
        const setVal = standardSetSelect ? standardSetSelect.value : '';
        const stdVal = standardSelect ? standardSelect.value : '';
        const statusVal = statusSelect ? statusSelect.value : '';

        let tags = [];

        if (kw) {
            tags.push({
                type: 'keyword',
                label: `Từ khóa: "${kw}"`,
                onRemove: () => {
                    if (keywordInput) keywordInput.value = '';
                    if (clearKeywordBtn) clearKeywordBtn.style.display = 'none';
                    if (filterForm) filterForm.submit();
                }
            });
        }

        if (setVal) {
            const setObj = ALL_SETS_DATA.find(s => s.MaBoTieuChuan === setVal);
            const setLabel = setObj ? `${setObj.MaBoTieuChuan} - ${setObj.TenBoTieuChuan}` : setVal;
            tags.push({
                type: 'set',
                label: `Bộ: ${setLabel}`,
                onRemove: () => {
                    if (standardSetSelect) standardSetSelect.value = '';
                    updateStandardsDropdown('');
                    if (filterForm) filterForm.submit();
                }
            });
        }

        if (stdVal) {
            const stdObj = ALL_STANDARDS_DATA.find(tc => tc.MaTieuChuan === stdVal);
            const stdLabel = stdObj ? `${stdObj.MaTieuChuan} - ${stdObj.TenTieuChuan}` : stdVal;
            tags.push({
                type: 'standard',
                label: `Tiêu chuẩn: ${stdLabel}`,
                onRemove: () => {
                    if (standardSelect) standardSelect.value = '';
                    if (filterForm) filterForm.submit();
                }
            });
        }

        if (statusVal !== '') {
            const statusLabel = statusVal === '1' ? 'Đang hoạt động' : 'Ngừng hoạt động';
            tags.push({
                type: 'status',
                label: `Trạng thái: ${statusLabel}`,
                onRemove: () => {
                    if (statusSelect) statusSelect.value = '';
                    if (filterForm) filterForm.submit();
                }
            });
        }

        if (tags.length === 0) {
            activeTagsBox.style.setProperty('display', 'none', 'important');
            tagList.innerHTML = '';
        } else {
            activeTagsBox.style.setProperty('display', 'flex', 'important');
            tagList.innerHTML = tags.map((t, idx) => `
                <span class="badge bg-primary text-white rounded-pill px-3 py-2 d-inline-flex align-items-center gap-1 shadow-xs">
                    <span class="text-truncate" style="max-width: 250px;">${escapeHtml(t.label)}</span>
                    <i class="bi bi-x-circle-fill ms-1" style="cursor: pointer;" role="button" data-tag-idx="${idx}" title="Xóa bộ lọc này"></i>
                </span>
            `).join('');

            tagList.querySelectorAll('[data-tag-idx]').forEach(btn => {
                btn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    const idx = parseInt(this.getAttribute('data-tag-idx'));
                    if (tags[idx] && tags[idx].onRemove) {
                        tags[idx].onRemove();
                    }
                });
            });
        }
    }

    renderActiveFilterTags();

    // Debounce tìm kiếm khi gõ
    if (keywordInput) {
        keywordInput.addEventListener('input', function () {
            const hasVal = this.value.trim() !== '';
            if (clearKeywordBtn) clearKeywordBtn.style.display = hasVal ? 'block' : 'none';
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                if (filterForm) filterForm.submit();
            }, 450);
        });
    }

    if (clearKeywordBtn) {
        clearKeywordBtn.addEventListener('click', function () {
            if (keywordInput) keywordInput.value = '';
            this.style.display = 'none';
            if (filterForm) filterForm.submit();
        });
    }

    // Khi chọn Bộ tiêu chuẩn -> Cập nhật dropdown Tiêu chuẩn và submit
    if (standardSetSelect) {
        standardSetSelect.addEventListener('change', function () {
            const setVal = this.value;
            updateStandardsDropdown(setVal);
            if (filterForm) filterForm.submit();
        });
    }

    // Khi chọn Tiêu chuẩn -> Tự động sync Bộ tiêu chuẩn nếu cần và submit
    if (standardSelect) {
        standardSelect.addEventListener('change', function () {
            const stdVal = this.value;
            if (stdVal) {
                const stdObj = ALL_STANDARDS_DATA.find(tc => tc.MaTieuChuan === stdVal);
                if (stdObj && stdObj.MaBoTieuChuan && standardSetSelect && standardSetSelect.value !== stdObj.MaBoTieuChuan) {
                    standardSetSelect.value = stdObj.MaBoTieuChuan;
                }
            }
            if (filterForm) filterForm.submit();
        });
    }

    // Khi đổi Trạng thái -> Submit
    if (statusSelect) {
        statusSelect.addEventListener('change', function () {
            if (filterForm) filterForm.submit();
        });
    }

    // Đặt lại toàn bộ bộ lọc
    if (btnResetAll) {
        btnResetAll.addEventListener('click', function () {
            window.location.href = '<?= base_url('admin/standard_sets.php') ?>';
        });
    }

    // 1. Setup Tree Toggle Icons & Collapsible Rows
    document.querySelectorAll('.collapse').forEach(collapseEl => {
        const id = collapseEl.id;
        if (!id) return;
        const triggerButtons = document.querySelectorAll('[data-bs-target="#' + id + '"]');

        collapseEl.addEventListener('show.bs.collapse', function (e) {
            if (e.target !== collapseEl) return;
            triggerButtons.forEach(btn => {
                btn.classList.add('is-open', 'expanded');
                btn.setAttribute('aria-expanded', 'true');
            });
        });

        collapseEl.addEventListener('hide.bs.collapse', function (e) {
            if (e.target !== collapseEl) return;
            triggerButtons.forEach(btn => {
                btn.classList.remove('is-open', 'expanded');
                btn.setAttribute('aria-expanded', 'false');
            });
        });
    });

    // 2. Setup Toggle Switch Status AJAX
    const toastEl = document.getElementById('liveToast');
    const toastMsg = document.getElementById('toastMessage');
    const toast = toastEl ? new bootstrap.Toast(toastEl, { delay: 3500 }) : null;

    document.querySelectorAll('.status-switch-toggle').forEach(toggleSwitch => {
        toggleSwitch.addEventListener('change', function () {
            const setId = this.dataset.setId;
            const isChecked = this.checked;
            const newStatus = isChecked ? 1 : 0;
            if (!setId) return;

            const formData = new FormData();
            formData.append('action', 'toggle_status_ajax');
            formData.append('id', setId);
            formData.append('status', newStatus);

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const activeLbl = document.getElementById('status-label-' + setId);
                    if (activeLbl) {
                        activeLbl.textContent = isChecked ? 'Hoạt động' : 'Ngừng hoạt động';
                        activeLbl.className = isChecked ? 'small text-success fw-bold' : 'small text-muted';
                    }

                    if (toastMsg) toastMsg.innerHTML = '<i class="bi bi-check-circle-fill fs-5"></i><span>' + data.message + '</span>';
                    if (toast) toast.show();
                } else {
                    this.checked = !isChecked;
                    alert(data.message || 'Không thể cập nhật trạng thái.');
                }
            })
            .catch(err => {
                console.error(err);
                this.checked = !isChecked;
                alert('Có lỗi xảy ra khi kết nối máy chủ.');
            });
        });
    });

    // 3. Setup PDF Viewer Modal
    const pdfModalEl = document.getElementById('modalPdfViewer');
    const pdfIframe = document.getElementById('pdfViewerIframe');
    const pdfTitle = document.getElementById('modalPdfViewerLabel');
    const pdfNewTab = document.getElementById('btnPdfOpenNewTab');
    const pdfDownload = document.getElementById('btnPdfDownload');
    const pdfModal = pdfModalEl ? new bootstrap.Modal(pdfModalEl) : null;

    document.querySelectorAll('.btn-view-pdf').forEach(btn => {
        btn.addEventListener('click', function () {
            const url = this.dataset.pdfUrl;
            const title = this.dataset.pdfTitle;
            if (!url) return;

            if (pdfIframe) pdfIframe.src = url;
            if (pdfTitle) pdfTitle.textContent = title || 'Xem chi tiết Thông tư PDF';
            if (pdfNewTab) pdfNewTab.href = url;
            if (pdfDownload) pdfDownload.href = url;

            if (pdfModal) pdfModal.show();
        });
    });

    if (pdfModalEl) {
        pdfModalEl.addEventListener('hidden.bs.modal', function () {
            if (pdfIframe) pdfIframe.src = 'about:blank';
        });
    }

    // 4. Modal View Standard Set Details
    const modalViewDetailEl = document.getElementById('modalViewStandardSetDetails');
    const modalViewDetail = modalViewDetailEl ? new bootstrap.Modal(modalViewDetailEl) : null;

    document.querySelectorAll('.btn-view-set-detail').forEach(btn => {
        btn.addEventListener('click', function () {
            const id = this.dataset.id || '';
            const name = this.dataset.name || '';
            const thongtu = this.dataset.thongtu || 'Chưa có số hiệu';
            const date = this.dataset.dateFormatted || (this.dataset.date || '-');
            const desc = this.dataset.desc || 'Chưa có mô tả';
            const status = this.dataset.status === '1';
            const pdf = this.dataset.pdf || '';
            const pdfUrl = this.dataset.pdfUrl || '';
            const viewUrl = this.dataset.viewUrl || '';
            const stdCount = this.dataset.standardsCount || '0';
            const criCount = this.dataset.criteriaCount || '0';
            const eviCount = this.dataset.evidencesCount || '0';

            document.getElementById('view_set_id_badge').textContent = id;
            document.getElementById('view_set_title').textContent = name;
            document.getElementById('view_set_id').textContent = id;
            document.getElementById('view_set_name').textContent = name;
            document.getElementById('view_set_thongtu').textContent = thongtu;
            document.getElementById('view_set_date').textContent = date;
            document.getElementById('view_set_desc').textContent = desc;
            document.getElementById('view_set_standards_count').innerHTML = '<i class="bi bi-folder2 me-1"></i>' + stdCount + ' Tiêu chuẩn';
            document.getElementById('view_set_criteria_count').innerHTML = '<i class="bi bi-list-check me-1"></i>' + criCount + ' Tiêu chí';
            document.getElementById('view_set_evidences_count').innerHTML = '<i class="bi bi-file-earmark-check me-1"></i>' + eviCount + ' Minh chứng';

            const statusBadge = document.getElementById('view_set_status_badge');
            const statusTextEl = document.getElementById('view_set_status_text');
            if (status) {
                statusBadge.className = 'badge bg-success-subtle text-success border border-success-subtle px-3 py-2 fs-7 fw-semibold';
                statusBadge.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i>Hoạt động';
                statusTextEl.innerHTML = '<span class="badge bg-success-subtle text-success"><i class="bi bi-check-circle-fill me-1"></i>Hoạt động</span>';
            } else {
                statusBadge.className = 'badge bg-secondary-subtle text-secondary border border-secondary-subtle px-3 py-2 fs-7 fw-semibold';
                statusBadge.innerHTML = '<i class="bi bi-pause-circle me-1"></i>Ngừng hoạt động';
                statusTextEl.innerHTML = '<span class="badge bg-secondary-subtle text-secondary"><i class="bi bi-pause-circle me-1"></i>Ngừng hoạt động</span>';
            }

            const fileContainer = document.getElementById('view_set_file_container');
            const previewBox = document.getElementById('view_set_preview_box');
            const previewContent = document.getElementById('view_set_preview_content');
            const previewNewTabLink = document.getElementById('view_set_preview_newtab_link');
            const footerOpenTab = document.getElementById('view_set_btn_open_tab_footer');

            if (pdf) {
                const ext = pdf.split('.').pop().toLowerCase();
                const isPdf = (ext === 'pdf');
                const isImage = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'].includes(ext);

                fileContainer.innerHTML = `
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi ${isPdf ? 'bi-file-earmark-pdf text-danger' : (isImage ? 'bi-file-earmark-image text-success' : 'bi-file-earmark-text text-primary')} fs-4"></i>
                            <div>
                                <strong class="d-block text-dark">${pdf.split('/').pop()}</strong>
                                <small class="text-muted">Định dạng: ${ext.toUpperCase()}</small>
                            </div>
                        </div>
                        <div class="d-inline-flex gap-2">
                            <a href="${viewUrl}" target="_blank" class="btn btn-sm btn-primary">
                                <i class="bi bi-box-arrow-up-right me-1"></i>Mở file ở tab mới
                            </a>
                            <a href="${pdfUrl}" download class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-download me-1"></i>Tải về
                            </a>
                        </div>
                    </div>
                `;

                previewBox.style.display = 'block';
                previewNewTabLink.href = viewUrl;
                footerOpenTab.href = viewUrl;
                footerOpenTab.style.display = 'inline-flex';

                if (isPdf) {
                    previewContent.innerHTML = `<iframe src="${pdfUrl}" style="width: 100%; height: 420px; border: none; border-radius: 6px;"></iframe>`;
                } else if (isImage) {
                    previewContent.innerHTML = `<a href="${viewUrl}" target="_blank" title="Bấm để mở kích thước lớn"><img src="${pdfUrl}" alt="Preview" class="img-fluid rounded shadow-sm" style="max-height: 420px; object-fit: contain;"></a>`;
                } else {
                    previewContent.innerHTML = `
                        <div class="py-4 text-center">
                            <i class="bi bi-file-earmark-word text-primary" style="font-size: 3rem;"></i>
                            <h6 class="mt-2 fw-bold">${pdf.split('/').pop()}</h6>
                            <p class="text-muted small">Tài liệu văn bản (${ext.toUpperCase()}). Bấm vào nút bên dưới để xem hoặc tải về máy.</p>
                            <a href="${viewUrl}" target="_blank" class="btn btn-primary btn-sm"><i class="bi bi-box-arrow-up-right me-1"></i>Mở file trong tab mới</a>
                        </div>
                    `;
                }
            } else {
                fileContainer.innerHTML = '<span class="badge bg-light text-muted border p-2"><i class="bi bi-dash-circle me-1"></i>Chưa có file dữ liệu đính kèm</span>';
                previewBox.style.display = 'none';
                previewContent.innerHTML = '';
                footerOpenTab.style.display = 'none';
            }

            // Edit trigger button inside view modal
            const editBtn = document.getElementById('view_set_btn_edit_trigger');
            editBtn.onclick = function () {
                if (modalViewDetail) modalViewDetail.hide();
                const targetEditBtn = document.querySelector(`.btn-edit-set[data-id="${id}"]`);
                if (targetEditBtn) {
                    targetEditBtn.click();
                }
            };

            if (modalViewDetail) modalViewDetail.show();
        });
    });

    if (modalViewDetailEl) {
        modalViewDetailEl.addEventListener('hidden.bs.modal', function () {
            const previewContent = document.getElementById('view_set_preview_content');
            if (previewContent) previewContent.innerHTML = '';
        });
    }

    // 5. Modal Edit Standard Set
    const modalSetEl = document.getElementById('modalAddStandardSet');
    const modalSet = modalSetEl ? new bootstrap.Modal(modalSetEl) : null;

    document.querySelectorAll('.btn-edit-set').forEach(btn => {
        btn.addEventListener('click', function () {
            document.getElementById('modalStandardSetLabel').innerHTML = '<i class="bi bi-pencil-square me-2"></i>Cập nhật Thông tư / Bộ Tiêu chuẩn';
            document.getElementById('set_edit_id').value = this.dataset.id || '';
            document.getElementById('set_ma_bo').value = this.dataset.id || '';
            document.getElementById('set_ten_bo').value = this.dataset.name || '';
            document.getElementById('set_thong_tu').value = this.dataset.thongtu || '';
            document.getElementById('set_ngay_ban_hanh').value = this.dataset.date || '';
            document.getElementById('set_trang_thai').value = this.dataset.status || '0';
            document.getElementById('set_mo_ta').value = this.dataset.desc || '';
            document.getElementById('set_old_pdf').value = this.dataset.pdf || '';

            const helpText = document.getElementById('pdf_help_text');
            if (this.dataset.pdf) {
                helpText.innerHTML = '<span class="text-success"><i class="bi bi-file-earmark-check me-1"></i>Đã có file đính kèm: ' + this.dataset.pdf + '</span>. Chọn file mới để ghi đè.';
            } else {
                helpText.textContent = 'Định dạng file PDF (tối đa 30MB).';
            }

            if (modalSet) modalSet.show();
        });
    });

    if (modalSetEl) {
        modalSetEl.addEventListener('hidden.bs.modal', function () {
            document.getElementById('formStandardSet').reset();
            document.getElementById('set_edit_id').value = '';
            document.getElementById('set_old_pdf').value = '';
            document.getElementById('modalStandardSetLabel').innerHTML = '<i class="bi bi-collection me-2"></i>Thêm mới Thông tư / Bộ Tiêu chuẩn';
            document.getElementById('pdf_help_text').textContent = 'Định dạng file PDF (tối đa 30MB). File này sẽ dùng để xem trực tiếp khi ấn nút "Xem chi tiết".';
        });
    }

    // 5. Modal Add / Edit Standard (Tiêu chuẩn)
    const modalStdEl = document.getElementById('modalAddStandard');
    const modalStd = modalStdEl ? new bootstrap.Modal(modalStdEl) : null;

    if (modalStdEl) {
        modalStdEl.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            if (button && button.dataset.setId) {
                const sel = document.getElementById('std_ma_bo');
                if (sel) sel.value = button.dataset.setId;
            }
        });
        modalStdEl.addEventListener('hidden.bs.modal', function () {
            document.getElementById('formStandard').reset();
            document.getElementById('std_edit_id').value = '';
            document.getElementById('modalStandardLabel').innerHTML = '<i class="bi bi-folder-plus me-2"></i>Thêm mới Tiêu chuẩn';
        });
    }

    document.querySelectorAll('.btn-edit-standard').forEach(btn => {
        btn.addEventListener('click', function () {
            document.getElementById('modalStandardLabel').innerHTML = '<i class="bi bi-pencil-square me-2"></i>Cập nhật Tiêu chuẩn';
            document.getElementById('std_edit_id').value = this.dataset.id || '';
            document.getElementById('std_ma_tc').value = this.dataset.id || '';
            document.getElementById('std_ten_tc').value = this.dataset.name || '';
            document.getElementById('std_mo_ta').value = this.dataset.desc || '';
            document.getElementById('std_thu_tu').value = this.dataset.order || '1';
            document.getElementById('std_ma_bo').value = this.dataset.setId || '';

            if (modalStd) modalStd.show();
        });
    });

    // 6. Modal Add / Edit Criterion (Tiêu chí)
    const modalCriEl = document.getElementById('modalAddCriterion');
    const modalCri = modalCriEl ? new bootstrap.Modal(modalCriEl) : null;

    if (modalCriEl) {
        modalCriEl.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            if (button && button.dataset.standardId) {
                const sel = document.getElementById('cri_ma_tc');
                if (sel) sel.value = button.dataset.standardId;
            }
        });
        modalCriEl.addEventListener('hidden.bs.modal', function () {
            document.getElementById('formCriterion').reset();
            document.getElementById('cri_edit_id').value = '';
            document.getElementById('modalCriterionLabel').innerHTML = '<i class="bi bi-list-check me-2"></i>Thêm mới Tiêu chí';
        });
    }

    document.querySelectorAll('.btn-edit-criterion').forEach(btn => {
        btn.addEventListener('click', function () {
            document.getElementById('modalCriterionLabel').innerHTML = '<i class="bi bi-pencil-square me-2"></i>Cập nhật Tiêu chí';
            document.getElementById('cri_edit_id').value = this.dataset.id || '';
            document.getElementById('cri_ma_tchi').value = this.dataset.id || '';
            document.getElementById('cri_ten_tchi').value = this.dataset.name || '';
            document.getElementById('cri_noi_dung').value = this.dataset.desc || '';
            document.getElementById('cri_thu_tu').value = this.dataset.order || '1';
            document.getElementById('cri_ma_tc').value = this.dataset.standardId || '';

            if (modalCri) modalCri.show();
        });
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
