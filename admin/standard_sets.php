<?php
require_once __DIR__ . '/../includes/helpers.php';
require_roles(['admin']);
require_once __DIR__ . '/../config/database.php';

$pdo = db();

if (!function_exists('match_search_kw')) {
    function match_search_kw(?string $haystack, string $needle): bool {
        if ($needle === '' || $haystack === null || $haystack === '') return false;
        if (function_exists('search_contains')) {
            return search_contains($haystack, $needle);
        }
        return mb_stripos($haystack, $needle, 0, 'UTF-8') !== false;
    }
}

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

// Handle Excel Export (Xuất đa tầng phân cấp Bộ TC -> Tiêu chuẩn -> Tiêu chí -> Minh chứng kèm Grouping / Outlining sổ ra - gập vào)
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    $filter = [
        'q'            => trim($_GET['q'] ?? $_GET['keyword'] ?? $_GET['search'] ?? ''),
        'standard_set' => trim($_GET['standard_set'] ?? $_GET['set'] ?? ''),
        'standard'     => trim($_GET['standard'] ?? ''),
        'criterion'    => trim($_GET['criterion'] ?? ''),
        'evidence'     => trim($_GET['evidence'] ?? ''),
        'status'       => isset($_GET['status']) && $_GET['status'] !== '' ? (int)$_GET['status'] : null,
    ];

    $filename = 'danh_sach_phan_cap_bo_tieu_chuan_' . date('Ymd_His') . '.xlsx';
    export_hierarchical_standards_excel($filename, $filter);
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

        // ==========================================
        // 4. CẤP MINH CHỨNG
        // ==========================================
        if ($action === 'save_evidence') {
            $rawId       = trim($_POST['id'] ?? '');
            $maMC        = trim($_POST['ma_minh_chung'] ?? '');
            $tenMC       = trim($_POST['ten_minh_chung'] ?? '');
            $soHieu      = trim($_POST['so_hieu'] ?? '') ?: null;
            $ngayBanHanh = trim($_POST['ngay_ban_hanh'] ?? '') ?: null;
            $namHoc      = trim($_POST['nam_hoc'] ?? '') ?: null;
            $moTa        = trim($_POST['mo_ta'] ?? '');
            $userId      = $_SESSION['user_id'] ?? 'ND001';
            $status      = isset($_POST['trang_thai']) ? (int)$_POST['trang_thai'] : 1;
            $status      = in_array($status, [0, 1], true) ? $status : 1;

            $rawTChi     = $_POST['ma_tieu_chi'] ?? [];
            if (!is_array($rawTChi)) {
                $rawTChi = [$rawTChi];
            }
            $maTieuChiList = array_values(array_unique(array_filter(array_map('trim', $rawTChi))));
            $maTieuChi     = !empty($maTieuChiList) ? $maTieuChiList[0] : null;

            if ($maMC === '') {
                throw new RuntimeException('Vui lòng nhập Mã minh chứng.');
            }
            if ($tenMC === '') {
                throw new RuntimeException('Vui lòng nhập Tên minh chứng.');
            }
            if (empty($maTieuChiList)) {
                throw new RuntimeException('Vui lòng chọn ít nhất một Tiêu chuẩn / Tiêu chí cho minh chứng.');
            }

            // Lấy MaBoTieuChuan từ MaTieuChi đầu tiên
            $maBoTieuChuan = null;
            if ($maTieuChi) {
                $stmtSet = $pdo->prepare('SELECT tc.MaBoTieuChuan FROM TieuChi tchi LEFT JOIN TieuChuan tc ON tc.MaTieuChuan = tchi.MaTieuChuan WHERE tchi.MaTieuChi = :tchi LIMIT 1');
                $stmtSet->execute(['tchi' => $maTieuChi]);
                $maBoTieuChuan = $stmtSet->fetchColumn() ?: null;
            }

            $file = $_FILES['evidence_file'] ?? null;
            $maxUploadMb = 100;
            $maxUploadBytes = $maxUploadMb * 1024 * 1024;
            if ($file && $file['error'] === UPLOAD_ERR_OK && (int)$file['size'] > $maxUploadBytes) {
                throw new RuntimeException('Dung lượng tệp tin tải lên vượt quá giới hạn tối đa cho phép (' . $maxUploadMb . 'MB).');
            }

            $relativePath = null;
            if ($file && $file['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $uploadDir = __DIR__ . '/../uploads/evidences';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                $storedName = 'MC_' . date('YmdHis') . '_' . random_int(100, 999) . '.' . $ext;
                $targetPath = $uploadDir . '/' . $storedName;
                if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                    $relativePath = 'uploads/evidences/' . $storedName;
                }
            }

            if ($rawId !== '') {
                // Update
                if ($maMC !== $rawId) {
                    $chk = $pdo->prepare('SELECT COUNT(*) FROM MinhChung WHERE MaMinhChung = :code');
                    $chk->execute(['code' => $maMC]);
                    if ((int)$chk->fetchColumn() > 0) {
                        throw new RuntimeException('Mã minh chứng "' . $maMC . '" đã tồn tại.');
                    }
                    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
                    $upLogs = $pdo->prepare('UPDATE download_logs SET MaMinhChung = :new_code WHERE MaMinhChung = :old_code');
                    $upLogs->execute(['new_code' => $maMC, 'old_code' => $rawId]);
                }

                if ($relativePath) {
                    $stmt = $pdo->prepare('UPDATE MinhChung SET MaMinhChung = :new_code, TenMinhChung = :title, SoHieu = :so_hieu, NgayBanHanh = :ngay_ban_hanh, MoTa = :mota, TepTin = :file, NamHoc = :namhoc, TrangThai = :status, MaTieuChi = :tchi, MaBoTieuChuan = :set_id, MaNguoiDung = :user_id, NgayCapNhat = NOW() WHERE MaMinhChung = :old_code');
                    $stmt->execute([
                        'new_code'      => $maMC,
                        'title'         => $tenMC,
                        'so_hieu'       => $soHieu,
                        'ngay_ban_hanh' => $ngayBanHanh,
                        'mota'          => $moTa,
                        'file'          => $relativePath,
                        'namhoc'        => $namHoc,
                        'status'        => $status,
                        'tchi'          => $maTieuChi,
                        'set_id'        => $maBoTieuChuan,
                        'user_id'       => $userId,
                        'old_code'      => $rawId,
                    ]);
                } else {
                    $stmt = $pdo->prepare('UPDATE MinhChung SET MaMinhChung = :new_code, TenMinhChung = :title, SoHieu = :so_hieu, NgayBanHanh = :ngay_ban_hanh, MoTa = :mota, NamHoc = :namhoc, TrangThai = :status, MaTieuChi = :tchi, MaBoTieuChuan = :set_id, MaNguoiDung = :user_id, NgayCapNhat = NOW() WHERE MaMinhChung = :old_code');
                    $stmt->execute([
                        'new_code'      => $maMC,
                        'title'         => $tenMC,
                        'so_hieu'       => $soHieu,
                        'ngay_ban_hanh' => $ngayBanHanh,
                        'mota'          => $moTa,
                        'namhoc'        => $namHoc,
                        'status'        => $status,
                        'tchi'          => $maTieuChi,
                        'set_id'        => $maBoTieuChuan,
                        'user_id'       => $userId,
                        'old_code'      => $rawId,
                    ]);
                }
                if ($maMC !== $rawId) {
                    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
                }

                // Đồng bộ quan hệ nhiều tiêu chí vào bảng minh_chung_tieu_chi
                $delMCTC = $pdo->prepare('DELETE FROM minh_chung_tieu_chi WHERE MaMinhChung = :mc');
                $delMCTC->execute(['mc' => $rawId]);
                if ($rawId !== $maMC) {
                    $delMCTC->execute(['mc' => $maMC]);
                }
                $insMCTC = $pdo->prepare('INSERT IGNORE INTO minh_chung_tieu_chi (MaMinhChung, MaTieuChi) VALUES (:mc, :tc)');
                foreach ($maTieuChiList as $tcCode) {
                    $insMCTC->execute(['mc' => $maMC, 'tc' => $tcCode]);
                }

                log_activity('cap_nhat', 'minh_chung', 0, $maMC . ' - ' . $tenMC);
                $success = 'Cập nhật minh chứng thành công.';
            } else {
                // Insert
                $chk = $pdo->prepare('SELECT COUNT(*) FROM MinhChung WHERE MaMinhChung = :code');
                $chk->execute(['code' => $maMC]);
                if ((int)$chk->fetchColumn() > 0) {
                    throw new RuntimeException('Mã minh chứng "' . $maMC . '" đã tồn tại.');
                }

                $stmt = $pdo->prepare('INSERT INTO MinhChung (MaMinhChung, TenMinhChung, SoHieu, NgayBanHanh, MoTa, TepTin, NamHoc, TrangThai, MaTieuChi, MaBoTieuChuan, MaNguoiDung, NgayCapNhat) VALUES (:code, :title, :so_hieu, :ngay_ban_hanh, :mota, :file, :namhoc, :status, :tchi, :set_id, :user_id, NOW())');
                $stmt->execute([
                    'code'          => $maMC,
                    'title'         => $tenMC,
                    'so_hieu'       => $soHieu,
                    'ngay_ban_hanh' => $ngayBanHanh,
                    'mota'          => $moTa,
                    'file'          => $relativePath,
                    'namhoc'        => $namHoc,
                    'status'        => $status,
                    'tchi'          => $maTieuChi,
                    'set_id'        => $maBoTieuChuan,
                    'user_id'       => $userId,
                ]);

                // Thêm quan hệ nhiều tiêu chí vào bảng minh_chung_tieu_chi
                $insMCTC = $pdo->prepare('INSERT IGNORE INTO minh_chung_tieu_chi (MaMinhChung, MaTieuChi) VALUES (:mc, :tc)');
                foreach ($maTieuChiList as $tcCode) {
                    $insMCTC->execute(['mc' => $maMC, 'tc' => $tcCode]);
                }

                log_activity('them_moi', 'minh_chung', 0, $maMC . ' - ' . $tenMC);
                $success = 'Thêm mới minh chứng thành công.';
            }
        }

        if ($action === 'delete_evidence') {
            $id = trim($_POST['id'] ?? '');
            if ($id === '') {
                throw new RuntimeException('Mã minh chứng không hợp lệ.');
            }
            $stmt = $pdo->prepare('SELECT TenMinhChung, TepTin FROM MinhChung WHERE MaMinhChung = :id');
            $stmt->execute(['id' => $id]);
            $evData = $stmt->fetch();
            if (!$evData) {
                throw new RuntimeException('Minh chứng không tồn tại.');
            }

            $filePath = $evData['TepTin'] ?? null;
            $pdo->prepare('DELETE FROM download_logs WHERE MaMinhChung = :id')->execute(['id' => $id]);
            $pdo->prepare('DELETE FROM minh_chung_tieu_chi WHERE MaMinhChung = :id')->execute(['id' => $id]);
            $delStmt = $pdo->prepare('DELETE FROM MinhChung WHERE MaMinhChung = :id');
            $delStmt->execute(['id' => $id]);

            if (!empty($filePath)) {
                $projectRoot = realpath(__DIR__ . '/..');
                $normalizedRel = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($filePath, '/\\'));
                $fullPath = $projectRoot ? ($projectRoot . DIRECTORY_SEPARATOR . $normalizedRel) : null;
                if ($fullPath && file_exists($fullPath) && is_file($fullPath)) {
                    @unlink($fullPath);
                }
            }
            log_activity('xoa', 'minh_chung', 0, $id . ' - ' . ($evData['TenMinhChung'] ?? ''));
            $success = 'Xóa minh chứng thành công.';
        }

    } catch (Throwable $exception) {
        $error = 'Thao tác không thành công: ' . $exception->getMessage();
    }
}

// Fetch Full Hierarchy for Tree View with Pagination (5 records per page)
$searchKeyword        = trim($_GET['q'] ?? $_GET['keyword'] ?? $_GET['search'] ?? '');
$selectedStandardSet  = trim($_GET['standard_set'] ?? $_GET['set'] ?? '');
$selectedStandard     = trim($_GET['standard'] ?? '');
$selectedCriterion    = trim($_GET['criterion'] ?? '');
$selectedEvidence     = trim($_GET['evidence'] ?? '');
$selectedStatus       = isset($_GET['status']) && $_GET['status'] !== '' ? (int)$_GET['status'] : null;
$isFilterActive       = ($searchKeyword !== '' || $selectedStandardSet !== '' || $selectedStandard !== '' || $selectedCriterion !== '' || $selectedEvidence !== '' || $selectedStatus !== null);

$perPage = 5;
$currentPage = max(1, (int)($_GET['page'] ?? 1));

// Build where clauses
$whereClauses = [];
$queryParams = [];

if ($searchKeyword !== '') {
    $whereClauses[] = "(
        (b.MaBoTieuChuan LIKE :kw_b1 OR b.TenBoTieuChuan LIKE :kw_b2 OR b.ThongTu LIKE :kw_b3 OR b.MoTa LIKE :kw_b4)
        OR b.MaBoTieuChuan IN (
            SELECT tc_s.MaBoTieuChuan FROM TieuChuan tc_s 
            WHERE tc_s.MaTieuChuan LIKE :kw_tc1 OR tc_s.TenTieuChuan LIKE :kw_tc2 OR tc_s.MoTa LIKE :kw_tc3
        )
        OR b.MaBoTieuChuan IN (
            SELECT tc_c.MaBoTieuChuan FROM TieuChuan tc_c 
            JOIN TieuChi tchi_c ON tchi_c.MaTieuChuan = tc_c.MaTieuChuan 
            WHERE tchi_c.MaTieuChi LIKE :kw_crit1 OR tchi_c.TenTieuChi LIKE :kw_crit2 OR tchi_c.NoiDung LIKE :kw_crit3
        )
        OR b.MaBoTieuChuan IN (
            SELECT DISTINCT COALESCE(m_s.MaBoTieuChuan, tc_m.MaBoTieuChuan)
            FROM MinhChung m_s
            LEFT JOIN TieuChi tchi_m ON tchi_m.MaTieuChi = m_s.MaTieuChi
            LEFT JOIN TieuChuan tc_m ON tc_m.MaTieuChuan = tchi_m.MaTieuChuan
            WHERE m_s.MaMinhChung LIKE :kw_ev1 OR m_s.TenMinhChung LIKE :kw_ev2 OR m_s.MoTa LIKE :kw_ev3 OR m_s.NamHoc LIKE :kw_ev4 OR m_s.TepTin LIKE :kw_ev5
        )
    )";
    $kwParam = "%$searchKeyword%";
    $queryParams['kw_b1'] = $kwParam;
    $queryParams['kw_b2'] = $kwParam;
    $queryParams['kw_b3'] = $kwParam;
    $queryParams['kw_b4'] = $kwParam;
    $queryParams['kw_tc1'] = $kwParam;
    $queryParams['kw_tc2'] = $kwParam;
    $queryParams['kw_tc3'] = $kwParam;
    $queryParams['kw_crit1'] = $kwParam;
    $queryParams['kw_crit2'] = $kwParam;
    $queryParams['kw_crit3'] = $kwParam;
    $queryParams['kw_ev1'] = $kwParam;
    $queryParams['kw_ev2'] = $kwParam;
    $queryParams['kw_ev3'] = $kwParam;
    $queryParams['kw_ev4'] = $kwParam;
    $queryParams['kw_ev5'] = $kwParam;
}

if ($selectedStandardSet !== '') {
    $whereClauses[] = "b.MaBoTieuChuan = :selected_set";
    $queryParams['selected_set'] = $selectedStandardSet;
}

if ($selectedStandard !== '') {
    $whereClauses[] = "b.MaBoTieuChuan IN (SELECT MaBoTieuChuan FROM TieuChuan WHERE MaTieuChuan = :selected_std)";
    $queryParams['selected_std'] = $selectedStandard;
}

if ($selectedCriterion !== '') {
    $whereClauses[] = "b.MaBoTieuChuan IN (
        SELECT tc.MaBoTieuChuan 
        FROM TieuChuan tc 
        JOIN TieuChi tchi ON tchi.MaTieuChuan = tc.MaTieuChuan 
        WHERE tchi.MaTieuChi = :selected_crit
    )";
    $queryParams['selected_crit'] = $selectedCriterion;
}

if ($selectedEvidence !== '') {
    $whereClauses[] = "b.MaBoTieuChuan IN (
        SELECT DISTINCT COALESCE(m.MaBoTieuChuan, tc.MaBoTieuChuan)
        FROM MinhChung m
        LEFT JOIN TieuChi tchi ON tchi.MaTieuChi = m.MaTieuChi
        LEFT JOIN TieuChuan tc ON tc.MaTieuChuan = tchi.MaTieuChuan
        WHERE m.MaMinhChung = :selected_ev
    )";
    $queryParams['selected_ev'] = $selectedEvidence;
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
        COUNT(DISTINCT COALESCE(mctc.MaMinhChung, m.MaMinhChung)) AS total_evidences
    FROM BoTieuChuan b
    LEFT JOIN TieuChuan tc ON tc.MaBoTieuChuan = b.MaBoTieuChuan
    LEFT JOIN TieuChi tchi ON tchi.MaTieuChuan = tc.MaTieuChuan
    LEFT JOIN minh_chung_tieu_chi mctc ON mctc.MaTieuChi = tchi.MaTieuChi
    LEFT JOIN MinhChung m ON (m.MaBoTieuChuan = b.MaBoTieuChuan OR m.MaTieuChi = tchi.MaTieuChi OR m.MaMinhChung = mctc.MaMinhChung)
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
$allCriteriaForFilter = $pdo->query("SELECT tchi.MaTieuChi, tchi.TenTieuChi, tchi.MaTieuChuan, tc.MaBoTieuChuan FROM TieuChi tchi LEFT JOIN TieuChuan tc ON tc.MaTieuChuan = tchi.MaTieuChuan ORDER BY tchi.ThuTu ASC, tchi.MaTieuChi ASC")->fetchAll(PDO::FETCH_ASSOC);
$allEvidencesForFilter = $pdo->query("
    SELECT DISTINCT m.MaMinhChung, m.TenMinhChung, COALESCE(mctc.MaTieuChi, m.MaTieuChi) AS MaTieuChi, m.MaBoTieuChuan, tc.MaTieuChuan 
    FROM MinhChung m 
    LEFT JOIN minh_chung_tieu_chi mctc ON mctc.MaMinhChung = m.MaMinhChung
    LEFT JOIN TieuChi tchi ON tchi.MaTieuChi = COALESCE(mctc.MaTieuChi, m.MaTieuChi) 
    LEFT JOIN TieuChuan tc ON tc.MaTieuChuan = tchi.MaTieuChuan 
    ORDER BY m.MaMinhChung ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Filter query parameters for links
$currentFilterParams = [];
if ($searchKeyword !== '') $currentFilterParams['q'] = $searchKeyword;
if ($selectedStandardSet !== '') $currentFilterParams['standard_set'] = $selectedStandardSet;
if ($selectedStandard !== '') $currentFilterParams['standard'] = $selectedStandard;
if ($selectedCriterion !== '') $currentFilterParams['criterion'] = $selectedCriterion;
if ($selectedEvidence !== '') $currentFilterParams['evidence'] = $selectedEvidence;
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
        COUNT(DISTINCT COALESCE(mctc.MaMinhChung, m.MaMinhChung)) AS total_evidences
    FROM TieuChi tchi
    LEFT JOIN minh_chung_tieu_chi mctc ON mctc.MaTieuChi = tchi.MaTieuChi
    LEFT JOIN MinhChung m ON m.MaTieuChi = tchi.MaTieuChi
    GROUP BY tchi.MaTieuChi, tchi.TenTieuChi, tchi.NoiDung, tchi.ThuTu, tchi.MaTieuChuan
    ORDER BY tchi.ThuTu ASC, tchi.MaTieuChi ASC
");
$allCriteriaByStandard = [];
foreach ($stmtAllTChi->fetchAll(PDO::FETCH_ASSOC) as $tchi) {
    $allCriteriaByStandard[$tchi['MaTieuChuan']][] = $tchi;
}

// Map of all criteria relations per evidence
$mctcMap = [];
try {
    $mctcRows = $pdo->query("SELECT MaMinhChung, MaTieuChi FROM minh_chung_tieu_chi ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($mctcRows as $r) {
        $mctcMap[$r['MaMinhChung']][] = $r['MaTieuChi'];
    }
} catch (Throwable $e) {}

// 4. Fetch Evidences grouped by Criterion (supporting Many-to-Many via minh_chung_tieu_chi)
$stmtAllMC = $pdo->query("
    SELECT 
        m.MaMinhChung,
        m.TenMinhChung,
        m.SoHieu,
        m.NgayBanHanh,
        m.MoTa,
        m.TepTin,
        m.NamHoc,
        m.TrangThai,
        COALESCE(mctc.MaTieuChi, m.MaTieuChi) AS AssignedTieuChi,
        m.MaTieuChi AS PrimaryTieuChi,
        m.MaBoTieuChuan,
        m.NgayCapNhat,
        u.HoTen AS NguoiTao,
        u.VaiTro AS VaiTroNguoiTao
    FROM MinhChung m
    LEFT JOIN minh_chung_tieu_chi mctc ON mctc.MaMinhChung = m.MaMinhChung
    LEFT JOIN NguoiDung u ON u.MaNguoiDung = m.MaNguoiDung
    ORDER BY m.MaMinhChung ASC
");
$allEvidencesByCriterion = [];
$seenEvidencePerCriterion = [];
foreach ($stmtAllMC->fetchAll(PDO::FETCH_ASSOC) as $mc) {
    $assignedTc = $mc['AssignedTieuChi'] ?? $mc['PrimaryTieuChi'];
    if (!empty($assignedTc)) {
        $key = $assignedTc . '_' . $mc['MaMinhChung'];
        if (!isset($seenEvidencePerCriterion[$key])) {
            $seenEvidencePerCriterion[$key] = true;
            $mc['all_criteria_ids'] = $mctcMap[$mc['MaMinhChung']] ?? (!empty($mc['PrimaryTieuChi']) ? [$mc['PrimaryTieuChi']] : []);
            $allEvidencesByCriterion[$assignedTc][] = $mc;
        }
    }
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

/* Interactive Hierarchy Navigation & Target Highlighting */
.interactive-hierarchy-step {
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    user-select: none;
}
.interactive-hierarchy-step:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.16) !important;
    border-color: #2563eb !important;
    background-color: #eff6ff !important;
}
.interactive-tree-item {
    cursor: pointer;
    border-radius: 4px;
    padding: 2px 6px;
    transition: all 0.15s ease-in-out;
    display: block;
    user-select: none;
}
.interactive-tree-item:hover {
    background-color: #dbeafe;
    color: #1d4ed8 !important;
    text-decoration: underline;
}
.interactive-link {
    cursor: pointer;
    transition: all 0.15s ease-in-out;
}
.interactive-link:hover {
    color: #1d4ed8 !important;
    text-decoration: underline;
}
/* Hiệu ứng Đốm sáng & Viền phát quang cực kỳ nổi bật khi nhảy tới dòng */
@keyframes targetRowPulse {
    0% {
        background: linear-gradient(90deg, rgba(254, 240, 138, 0.75) 0%, rgba(191, 219, 254, 0.85) 35%, rgba(219, 234, 254, 0.4) 100%) !important;
        box-shadow: 0 0 0 4px #2563eb, 0 0 28px rgba(37, 99, 235, 0.55), inset 0 0 12px rgba(37, 99, 235, 0.15) !important;
        outline: 2px solid #2563eb !important;
        outline-offset: -1px;
        transform: scale(1.012);
    }
    30% {
        background: linear-gradient(90deg, rgba(254, 240, 138, 0.55) 0%, rgba(191, 219, 254, 0.65) 45%, rgba(219, 234, 254, 0.25) 100%) !important;
        box-shadow: 0 0 0 6px rgba(37, 99, 235, 0.4), 0 0 35px rgba(37, 99, 235, 0.4) !important;
        outline: 2px solid #2563eb !important;
        transform: scale(1.006);
    }
    60% {
        background: rgba(219, 234, 254, 0.3) !important;
        box-shadow: 0 0 0 8px rgba(37, 99, 235, 0.15), 0 0 20px rgba(37, 99, 235, 0.2) !important;
        outline: 2px solid rgba(37, 99, 235, 0.4) !important;
        transform: scale(1);
    }
    100% {
        background: transparent;
        box-shadow: none;
        outline: none;
        transform: scale(1);
    }
}

.highlight-target-row {
    animation: targetRowPulse 3.5s cubic-bezier(0.2, 0.8, 0.2, 1) forwards;
    position: relative !important;
    z-index: 30 !important;
    border-left: 6px solid #2563eb !important;
    border-radius: 6px;
    transition: border-left 0.3s ease;
}

/* Thẻ định vị nổi (Floating Target Beacon Badge) */
.jump-target-beacon {
    position: absolute;
    top: -14px;
    left: 24px;
    z-index: 999;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 14px;
    background: linear-gradient(135deg, #1e3a8a, #2563eb);
    color: #ffffff;
    font-size: 0.78rem;
    font-weight: 700;
    letter-spacing: 0.3px;
    border-radius: 20px;
    box-shadow: 0 4px 16px rgba(37, 99, 235, 0.5), 0 0 0 2px rgba(255, 255, 255, 0.8);
    pointer-events: none;
    animation: beaconBounce 3.5s ease-out forwards;
}
@keyframes beaconBounce {
    0% {
        opacity: 0;
        transform: translateY(-8px) scale(0.85);
    }
    15% {
        opacity: 1;
        transform: translateY(0) scale(1.05);
    }
    30% {
        transform: translateY(-2px) scale(1);
    }
    75% {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
    100% {
        opacity: 0;
        transform: translateY(-6px) scale(0.9);
    }
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

/* Search Result Highlighting & Location Indicator */
.search-matched-row {
    background-color: #fef08a !important;
    border-left: 5px solid #d97706 !important;
    position: relative;
    transition: all 0.3s ease;
}
.search-matched-row.current-focus-match {
    background-color: #fde047 !important;
    box-shadow: 0 0 0 3px rgba(217, 119, 6, 0.5) !important;
    z-index: 5;
    animation: searchPulseGlow 1.5s infinite ease-in-out;
}
@keyframes searchPulseGlow {
    0% { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.6); }
    70% { box-shadow: 0 0 0 10px rgba(245, 158, 11, 0); }
    100% { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0); }
}
html[data-theme="dark"] .search-matched-row {
    background-color: #451a03 !important;
    border-left: 5px solid #f59e0b !important;
}
html[data-theme="dark"] .search-matched-row.current-focus-match {
    background-color: #78350f !important;
    box-shadow: 0 0 0 3px rgba(250, 204, 21, 0.6) !important;
}
.search-matched-text {
    background: #fef08a;
    color: #78350f;
    font-weight: 700;
    padding: 1px 5px;
    border-radius: 4px;
    border-bottom: 2px solid #f59e0b;
    display: inline;
}
html[data-theme="dark"] .search-matched-text {
    background: #854d0e;
    color: #fef08a;
    border-bottom-color: #fde047;
}
.badge-matched-locator {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.72rem;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 20px;
    background-color: #fef08a;
    color: #854d0e;
    border: 1px solid #f59e0b;
    box-shadow: 0 1px 3px rgba(0,0,0,0.08);
}
html[data-theme="dark"] .badge-matched-locator {
    background-color: #78350f;
    color: #fef08a;
    border-color: #f59e0b;
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
                            <span class="text-muted small">Mô hình cây phân cấp: <strong>Bộ tiêu chuẩn &rarr; Tiêu chuẩn &rarr; Tiêu chí &rarr; Minh chứng</strong></span>
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



                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <button type="submit" form="filterForm" class="btn btn-sm btn-primary rounded-pill px-3 shadow-sm d-inline-flex align-items-center gap-1" id="btnSubmitFilterHeader" title="Áp dụng bộ lọc">
                                <i class="bi bi-funnel-fill"></i> Lọc dữ liệu
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3" id="btnResetAll" title="Đặt lại tất cả bộ lọc">
                                <i class="bi bi-arrow-counterclockwise me-1"></i> Đặt lại
                            </button>
                        </div>
                    </div>

                    <form id="filterForm" method="get" action="" class="row g-3">
                        <!-- Ô NHẬP TỪ KHÓA TÌM NHANH -->
                        <div class="col-12 col-xxl-3 col-xl-3">
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
                                <button class="btn btn-primary d-inline-flex align-items-center gap-1" type="submit" title="Tìm kiếm theo từ khóa">
                                    <i class="bi bi-search"></i> Lọc
                                </button>
                            </div>



                        </div>

                        <!-- CỤM Ô CHỌN NHANH PHÂN CẤP LIÊN KẾT + NÚT LỌC -->
                        <div class="col-12 col-xxl-9 col-xl-9">
                            <div class="row g-2 align-items-center">
                                <!-- Dropdown 1: Tiêu chuẩn -->
                                <div class="col-12 col-sm-6 col-md-4 col-xl">
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

                                <!-- Dropdown 2: Tiêu chí -->
                                <div class="col-12 col-sm-6 col-md-4 col-xl">
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text bg-light text-muted" title="Lọc theo Tiêu chí"><i class="bi bi-list-task"></i></span>
                                        <select class="form-select form-select-sm" id="filterCriterion" name="criterion">
                                            <option value="">-- Tất cả Tiêu chí (<?= count($allCriteriaForFilter) ?>) --</option>
                                            <?php foreach ($allCriteriaForFilter as $cri): ?>
                                                <option value="<?= htmlspecialchars($cri['MaTieuChi']) ?>" 
                                                        data-standard="<?= htmlspecialchars($cri['MaTieuChuan']) ?>"
                                                        data-set="<?= htmlspecialchars($cri['MaBoTieuChuan']) ?>"
                                                        <?= $selectedCriterion === (string)$cri['MaTieuChi'] ? 'selected' : '' ?> 
                                                        title="<?= htmlspecialchars($cri['TenTieuChi']) ?>">
                                                    <?= htmlspecialchars($cri['MaTieuChi'] . ' - ' . $cri['TenTieuChi']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>

                                <!-- Dropdown 3: Minh chứng -->
                                <div class="col-12 col-sm-6 col-md-4 col-xl">
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text bg-light text-muted" title="Lọc theo Minh chứng"><i class="bi bi-file-earmark-text"></i></span>
                                        <select class="form-select form-select-sm" id="filterEvidence" name="evidence">
                                            <option value="">-- Tất cả Minh chứng (<?= count($allEvidencesForFilter) ?>) --</option>
                                            <?php foreach ($allEvidencesForFilter as $ev): ?>
                                                <option value="<?= htmlspecialchars($ev['MaMinhChung']) ?>" 
                                                        data-criterion="<?= htmlspecialchars($ev['MaTieuChi']) ?>"
                                                        data-standard="<?= htmlspecialchars($ev['MaTieuChuan']) ?>"
                                                        data-set="<?= htmlspecialchars($ev['MaBoTieuChuan']) ?>"
                                                        <?= $selectedEvidence === (string)$ev['MaMinhChung'] ? 'selected' : '' ?> 
                                                        title="<?= htmlspecialchars($ev['TenMinhChung']) ?>">
                                                    <?= htmlspecialchars($ev['MaMinhChung'] . ' - ' . $ev['TenMinhChung']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>

                                <!-- Dropdown 4: Trạng thái -->
                                <div class="col-12 col-sm-6 col-md-4 col-xl">
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text bg-light text-muted" title="Lọc theo Trạng thái"><i class="bi bi-toggle-on"></i></span>
                                        <select class="form-select form-select-sm" id="filterStatus" name="status">
                                            <option value="">-- Tất cả Trạng thái --</option>
                                            <option value="1" <?= $selectedStatus === 1 ? 'selected' : '' ?>>Hoạt động</option>
                                            <option value="0" <?= $selectedStatus === 0 ? 'selected' : '' ?>>Ngừng hoạt động</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Nút Lọc kết quả -->
                                <div class="col-12 col-sm-6 col-md-4 col-xl-auto">
                                    <button type="submit" class="btn btn-primary btn-sm px-3 d-inline-flex align-items-center justify-content-center gap-1 shadow-sm w-100" style="height: 31px;" id="btnApplyFilter" title="Áp dụng lọc">
                                        <i class="bi bi-funnel-fill"></i> Lọc
                                    </button>
                                </div>
                            </div>



                        </div>
                    </form>

                    <!-- DẢI CHIPS HIỂN THỊ CÁC TIÊU CHÍ ĐANG LỌC -->
                    <div id="activeFilterTags" class="d-flex flex-wrap align-items-center gap-2 mt-3 pt-3 border-top" style="<?= ($searchKeyword !== '' || $selectedStandardSet !== '' || $selectedStandard !== '' || $selectedCriterion !== '' || $selectedEvidence !== '' || $selectedStatus !== null) ? '' : 'display: none !important;' ?>">
                        <span class="text-secondary small fw-semibold"><i class="bi bi-tags"></i> Đang lọc theo:</span>
                        <div id="tagList" class="d-flex flex-wrap gap-2"></div>
                    </div>
                </div>
            </div>

            <!-- Main Master-Detail Table -->
                        <!-- Search Result Navigator Alert -->
            <?php if ($searchKeyword !== ''): ?>
                <div class="alert alert-warning border border-warning-subtle shadow-sm rounded-3 py-2 px-3 mb-3 d-flex flex-wrap align-items-center justify-content-between gap-2" id="searchResultsAlert">
                    <div class="d-flex align-items-center gap-2">
                        <span class="spinner-grow spinner-grow-sm text-warning" role="status"></span>
                        <span class="fw-bold text-dark">
                            <i class="bi bi-geo-alt-fill text-danger me-1"></i>
                            Kết quả tìm kiếm cho: <span class="badge bg-dark text-warning fs-7 px-2 py-1">"<?= htmlspecialchars($searchKeyword) ?>"</span>
                        </span>
                        <span class="text-secondary small fw-medium" id="searchMatchCountText">(Đang định vị...)</span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="text-muted small d-none d-md-inline">Di chuyển vị trí khớp:</span>
                        <button type="button" class="btn btn-sm btn-outline-dark d-inline-flex align-items-center gap-1" id="btnPrevMatch" title="Đến kết quả trước">
                            <i class="bi bi-chevron-up"></i> Trước
                        </button>
                        <span class="badge bg-warning text-dark fw-bold" id="matchCurrentIndexBadge">1 / 1</span>
                        <button type="button" class="btn btn-sm btn-outline-dark d-inline-flex align-items-center gap-1" id="btnNextMatch" title="Đến kết quả tiếp theo">
                            <i class="bi bi-chevron-down"></i> Sau
                        </button>
                    </div>
                </div>
            <?php endif; ?>

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
                        
                            // Kiểm tra khớp từ khóa tìm kiếm & bộ lọc Cấp 1 (Bộ Tiêu chuẩn)
                            $setMatchesSelf = ($searchKeyword !== '' && (
                                match_search_kw($set['MaBoTieuChuan'], $searchKeyword) ||
                                match_search_kw($set['TenBoTieuChuan'], $searchKeyword) ||
                                match_search_kw($set['ThongTu'], $searchKeyword) ||
                                match_search_kw($set['MoTa'], $searchKeyword)
                            )) || ($selectedStandardSet === $setId);

                            // Lọc danh sách Tiêu chuẩn hiển thị theo bộ lọc & từ khóa
                            $displayStandards = [];
                            $totalDisplayCriteriaCount = 0;
                            $totalDisplayEvidencesCount = 0;

                            foreach ($standards as $tc_item) {
                                $tcId_item = $tc_item['MaTieuChuan'];
                                $crit_list_item = $allCriteriaByStandard[$tcId_item] ?? [];
                                $stdMatchesSelf_item = ($searchKeyword !== '' && (
                                    match_search_kw($tc_item['MaTieuChuan'], $searchKeyword) ||
                                    match_search_kw($tc_item['TenTieuChuan'], $searchKeyword) ||
                                    match_search_kw($tc_item['MoTa'], $searchKeyword)
                                )) || ($selectedStandard === $tcId_item);

                                $displayCriteria_item = [];
                                foreach ($crit_list_item as $cri_item) {
                                    $tchiId_item = $cri_item['MaTieuChi'];
                                    $ev_list_item = $allEvidencesByCriterion[$tchiId_item] ?? [];
                                    $critMatchesSelf_item = ($searchKeyword !== '' && (
                                        match_search_kw($cri_item['MaTieuChi'], $searchKeyword) ||
                                        match_search_kw($cri_item['TenTieuChi'], $searchKeyword) ||
                                        match_search_kw($cri_item['NoiDung'], $searchKeyword)
                                    )) || ($selectedCriterion === $tchiId_item);

                                    $displayEvidences_item = [];
                                    foreach ($ev_list_item as $ev_item) {
                                        $mcId_item = $ev_item['MaMinhChung'];
                                        $evMatchesSelf_item = ($searchKeyword !== '' && (
                                            match_search_kw($ev_item['MaMinhChung'], $searchKeyword) ||
                                            match_search_kw($ev_item['TenMinhChung'], $searchKeyword) ||
                                            match_search_kw($ev_item['MoTa'], $searchKeyword) ||
                                            match_search_kw($ev_item['NamHoc'], $searchKeyword) ||
                                            match_search_kw($ev_item['TepTin'], $searchKeyword)
                                        )) || ($selectedEvidence === $mcId_item);

                                        $keepEv = true;
                                        if ($selectedEvidence !== '') {
                                            $keepEv = ($mcId_item === $selectedEvidence);
                                        } elseif ($searchKeyword !== '') {
                                            $keepEv = ($evMatchesSelf_item || $critMatchesSelf_item || $stdMatchesSelf_item || $setMatchesSelf);
                                        }
                                        if ($keepEv) {
                                            $displayEvidences_item[] = $ev_item;
                                        }
                                    }

                                    $keepCrit = true;
                                    if ($selectedCriterion !== '') {
                                        $keepCrit = ($tchiId_item === $selectedCriterion);
                                    } elseif ($selectedEvidence !== '') {
                                        $keepCrit = !empty($displayEvidences_item);
                                    } elseif ($searchKeyword !== '') {
                                        $keepCrit = ($critMatchesSelf_item || !empty($displayEvidences_item) || $stdMatchesSelf_item || $setMatchesSelf);
                                    }
                                    if ($keepCrit) {
                                        $displayCriteria_item[] = [
                                            'data' => $cri_item,
                                            'evidences' => $displayEvidences_item
                                        ];
                                        $totalDisplayEvidencesCount += count($displayEvidences_item);
                                    }
                                }

                                $keepStd = true;
                                if ($selectedStandard !== '') {
                                    $keepStd = ($tcId_item === $selectedStandard);
                                } elseif ($selectedCriterion !== '' || $selectedEvidence !== '') {
                                    $keepStd = !empty($displayCriteria_item);
                                } elseif ($searchKeyword !== '') {
                                    $keepStd = ($stdMatchesSelf_item || !empty($displayCriteria_item) || $setMatchesSelf);
                                }
                                if ($keepStd) {
                                    $displayStandards[] = [
                                        'data' => $tc_item,
                                        'criteria' => $displayCriteria_item
                                    ];
                                    $totalDisplayCriteriaCount += count($displayCriteria_item);
                                }
                            }

                            $hasChildrenMatch = !empty($displayStandards);
                            $isSetOpen = $isFilterActive || ($selectedStandardSet === $setId) || ($setMatchesSelf && $searchKeyword !== '') || $hasChildrenMatch;
                        ?>
                            <!-- LEVEL 1: THÔNG TƯ / BỘ TIÊU CHUẨN ROW -->
                            <tr class="table-set-row <?= $isActive ? 'is-active-set' : '' ?> <?= $setMatchesSelf ? 'search-matched-row' : '' ?>" id="set-row-<?= htmlspecialchars($setId) ?>">
                                <td class="text-center">
                                    <div class="d-inline-flex align-items-center gap-1">
                                        <button class="btn btn-sm btn-light tree-toggle-btn <?= $isSetOpen ? 'is-open expanded' : '' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-set-<?= htmlspecialchars($setId) ?>" aria-expanded="<?= $isSetOpen ? 'true' : 'false' ?>" title="Mở rộng / Thu gọn Tiêu chuẩn con">
                                            <i class="bi bi-chevron-right fs-6"></i>
                                        </button>
                                        <span class="fw-bold text-secondary"><?= $sttNumber ?></span>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-3">
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle py-2 px-2 fs-7 fw-bold text-nowrap"><?= highlight_search_text($setId, $searchKeyword) ?></span>
                                        <div class="flex-grow-1">
                                            <a class="fw-bold text-decoration-none text-dark d-block mb-1" href="javascript:void(0)" data-bs-toggle="collapse" data-bs-target="#collapse-set-<?= htmlspecialchars($setId) ?>">
                                                <?= highlight_search_text($set['TenBoTieuChuan'], $searchKeyword) ?>
                                            </a>
                                            <?php if ($set['MoTa']): ?>
                                                <small class="text-muted line-clamp-1 mb-1 d-block"><?= highlight_search_text($set['MoTa'], $searchKeyword) ?></small>
                                            <?php endif; ?>
                                            <div class="d-flex flex-wrap gap-2 align-items-center">
                                                <span class="badge bg-secondary-subtle text-secondary small"><i class="bi bi-folder2 me-1"></i><?= $isFilterActive ? count($displayStandards) : count($standards) ?> Tiêu chuẩn</span>
                                                <span class="badge bg-secondary-subtle text-secondary small"><i class="bi bi-list-check me-1"></i><?= $isFilterActive ? $totalDisplayCriteriaCount : (int)$set['total_criteria'] ?> Tiêu chí</span>
                                                <span class="badge bg-secondary-subtle text-secondary small"><i class="bi bi-file-earmark-check me-1"></i><?= $isFilterActive ? $totalDisplayEvidencesCount : (int)$set['total_evidences'] ?> Minh chứng</span>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-info-subtle text-dark border border-info-subtle fw-semibold">
                                        <i class="bi bi-file-text me-1"></i><?= highlight_search_text($set['ThongTu'] ?: 'Chưa nhập số hiệu', $searchKeyword) ?>
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
                                            <i class="bi bi-plus-circle me-1"></i>Tiêu chuẩn
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
                                            title="Sửa Bộ tiêu chuẩn">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <?php if (!empty($standards) || (int)($set['MinhChungCount'] ?? 0) > 0): ?>
                                            <?php 
                                                $reason = !empty($standards) 
                                                    ? 'Bộ tiêu chuẩn này đang chứa ' . count($standards) . ' tiêu chuẩn con' 
                                                    : 'Bộ tiêu chuẩn này đang có ' . (int)$set['MinhChungCount'] . ' minh chứng liên kết';
                                            ?>
                                            <button class="btn btn-sm btn-outline-secondary opacity-50" type="button" disabled title="Không thể xóa: <?= $reason ?>">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        <?php else: ?>
                                            <form method="post" class="d-inline" data-confirm-form="Bạn có chắc chắn muốn xóa bộ tiêu chuẩn này?">
                                                <input type="hidden" name="action" value="delete_standard_set">
                                                <input type="hidden" name="id" value="<?= htmlspecialchars($setId) ?>">
                                                <button class="btn btn-sm btn-outline-danger" type="submit" title="Xóa Bộ tiêu chuẩn"><i class="bi bi-trash"></i></button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>

                            <!-- LEVEL 2 COLLAPSIBLE CONTAINER: CẤP TIÊU CHUẨN -->
                            <tr class="p-0 border-0">
                                <td colspan="7" class="p-0 border-0">
                                    <div class="collapse <?= $isSetOpen ? 'show' : '' ?>" id="collapse-set-<?= htmlspecialchars($setId) ?>">
                                        <div class="nested-container mx-3 my-2 shadow-sm">
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <div class="d-flex align-items-center gap-2">
                                                    <i class="bi bi-diagram-3-fill text-primary"></i>
                                                    <h6 class="mb-0 fw-bold text-dark">Cập nhật Tiêu chuẩn</h6>
                                                    <span class="badge bg-primary-subtle text-primary"><?= $isFilterActive ? count($displayStandards) : count($standards) ?> tiêu chuẩn</span>
                                                </div>
                                                <button class="btn btn-sm btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#modalAddStandard" data-set-id="<?= htmlspecialchars($setId) ?>" data-set-name="<?= htmlspecialchars($set['TenBoTieuChuan']) ?>">
                                                    <i class="bi bi-plus-lg me-1"></i>Thêm Tiêu chuẩn mới
                                                </button>
                                            </div>

                                            <?php if (empty($displayStandards)): ?>
                                                <div class="text-center py-3 text-muted bg-white rounded border border-dashed">
                                                    <small><?= $isFilterActive ? 'Không có tiêu chuẩn nào phù hợp với bộ lọc/từ khóa.' : 'Chưa có tiêu chuẩn nào được tạo trong bộ tiêu chuẩn này. Bấm <strong>"+ Thêm Tiêu chuẩn mới"</strong> để bắt đầu.' ?></small>
                                                </div>
                                            <?php else: ?>
                                                <div class="table-responsive bg-white rounded border">
                                                    <table class="table table-sm align-middle mb-0">
                                                        <thead class="table-light">
                                                            <tr>
                                                                <th style="width: 50px;" class="text-center">Sổ</th>
                                                                <th style="width: 120px;">Mã TC</th>
                                                                <th style="min-width: 250px;">Tên Tiêu chuẩn</th>
                                                                <th style="min-width: 200px;">Nội dung</th>
                                                                <th style="width: 120px;" class="text-center">Số tiêu chí</th>
                                                                <th style="width: 160px;" class="text-end">Hành động</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php foreach ($displayStandards as $stdObj): 
                                                                $tc = $stdObj['data'];
                                                                $criteria = $stdObj['criteria'];
                                                                $tcId = $tc['MaTieuChuan'];
                                                                $tcTotalCriteriaCount = count($allCriteriaByStandard[$tcId] ?? []);
                                                            
                                                                // Kiểm tra khớp từ khóa tìm kiếm & bộ lọc Cấp 2 (Tiêu chuẩn)
                                                                $stdMatchesSelf = ($searchKeyword !== '' && (
                                                                    match_search_kw($tc['MaTieuChuan'], $searchKeyword) ||
                                                                    match_search_kw($tc['TenTieuChuan'], $searchKeyword) ||
                                                                    match_search_kw($tc['MoTa'], $searchKeyword)
                                                                )) || ($selectedStandard === $tcId);

                                                                $isStdOpen = $isFilterActive || ($selectedStandard === $tcId) || ($stdMatchesSelf && $searchKeyword !== '') || !empty($criteria);
                                                            ?>
                                                                <tr class="nested-standard-row <?= $stdMatchesSelf ? 'search-matched-row' : '' ?>" id="standard-row-<?= htmlspecialchars($tcId) ?>">
                                                                    <td class="text-center">
                                                                        <button class="btn btn-xs btn-outline-secondary tree-toggle-btn <?= $isStdOpen ? 'is-open expanded' : '' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-standard-<?= htmlspecialchars($tcId) ?>" aria-expanded="<?= $isStdOpen ? 'true' : 'false' ?>" title="Mở rộng / Thu gọn Tiêu chí con">
                                                                            <i class="bi bi-chevron-right" style="font-size: 0.75rem;"></i>
                                                                        </button>
                                                                    </td>
                                                                    <td class="fw-bold text-primary"><?= highlight_search_text($tcId, $searchKeyword) ?></td>
                                                                    <td class="fw-semibold">
                                                                        <a class="text-decoration-none text-dark" href="javascript:void(0)" data-bs-toggle="collapse" data-bs-target="#collapse-standard-<?= htmlspecialchars($tcId) ?>">
                                                                            <?= highlight_search_text($tc['TenTieuChuan'], $searchKeyword) ?>
                                                                        </a>
                                                                    </td>
                                                                    <td class="small text-secondary"><?= highlight_search_text($tc['MoTa'] ?: '-', $searchKeyword) ?></td>
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
                                                                            <?php if (!empty($criteria)): ?>
                                                                                <button class="btn btn-xs btn-outline-secondary opacity-50" type="button" disabled title="Không thể xóa: Tiêu chuẩn này đang chứa <?= count($criteria) ?> tiêu chí con">
                                                                                    <i class="bi bi-trash"></i>
                                                                                </button>
                                                                            <?php else: ?>
                                                                                <form method="post" class="d-inline" data-confirm-form="Bạn chắc chắn muốn xóa tiêu chuẩn này?">
                                                                                    <input type="hidden" name="action" value="delete_standard">
                                                                                    <input type="hidden" name="id" value="<?= htmlspecialchars($tcId) ?>">
                                                                                    <button class="btn btn-xs btn-outline-danger" type="submit" title="Xóa Tiêu chuẩn"><i class="bi bi-trash"></i></button>
                                                                                </form>
                                                                            <?php endif; ?>
                                                                        </div>
                                                                    </td>
                                                                </tr>

                                                                <!-- LEVEL 3 COLLAPSIBLE CONTAINER: CẤP TIÊU CHÍ -->
                                                                <tr class="p-0 border-0">
                                                                    <td colspan="6" class="p-0 border-0">
                                                                        <div class="collapse <?= $isStdOpen ? 'show' : '' ?>" id="collapse-standard-<?= htmlspecialchars($tcId) ?>">
                                                                            <div class="p-3 my-1 ms-4 bg-light rounded border">
                                                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                                                    <div class="d-flex align-items-center gap-2">
                                                                                        <i class="bi bi-list-task text-success"></i>
                                                                                        <strong class="small text-dark">Cập nhật Tiêu chí</strong>
                                                                                        <span class="badge bg-success-subtle text-success small"><?= count($criteria) ?> tiêu chí</span>
                                                                                    </div>
                                                                                    <button class="btn btn-xs btn-success" type="button" data-bs-toggle="modal" data-bs-target="#modalAddCriterion" data-standard-id="<?= htmlspecialchars($tcId) ?>" data-standard-name="<?= htmlspecialchars($tc['TenTieuChuan']) ?>">
                                                                                        <i class="bi bi-plus-lg me-1"></i>Thêm Tiêu chí
                                                                                    </button>
                                                                                </div>

                                                                                <?php if (empty($criteria)): ?>
                                                                                    <div class="text-center py-2 text-muted small bg-white rounded border border-dashed">
                                                                                        <?= $isFilterActive ? 'Không có tiêu chí nào phù hợp với bộ lọc/từ khóa.' : 'Chưa có tiêu chí nào. Bấm <strong>"+ Thêm Tiêu chí"</strong> để tạo mới.' ?>
                                                                                    </div>
                                                                                <?php else: ?>
                                                                                    <div class="table-responsive bg-white rounded border">
                                                                                        <table class="table table-xs table-hover align-middle mb-0">
                                                                                            <thead class="table-secondary">
                                                                                                <tr>
                                                                                                    <th style="width: 45px;" class="text-center">Sổ</th>
                                                                                                    <th style="width: 110px;">Mã Tiêu chí</th>
                                                                                                    <th style="min-width: 220px;">Tên Tiêu chí</th>
                                                                                                    <th style="min-width: 220px;">Nội dung / Yêu cầu</th>
                                                                                                    <th style="width: 140px;" class="text-center">Minh chứng</th>
                                                                                                    <th style="width: 160px;" class="text-end">Hành động</th>
                                                                                                </tr>
                                                                                            </thead>
                                                                                            <tbody>
                                                                                                <?php foreach ($criteria as $critObj): 
                                                                    $tchi = $critObj['data'];
                                                                    $evidences = $critObj['evidences'];
                                                                    $tchiId = $tchi['MaTieuChi'];
                                                                    $tchiTotalEvidencesCount = count($allEvidencesByCriterion[$tchiId] ?? []);
                                                                                                
                                                                                                    // Kiểm tra khớp từ khóa tìm kiếm & bộ lọc Cấp 3 (Tiêu chí)
                                                                                                    $critMatchesSelf = ($searchKeyword !== '' && (
                                                                                                        match_search_kw($tchi['MaTieuChi'], $searchKeyword) ||
                                                                                                        match_search_kw($tchi['TenTieuChi'], $searchKeyword) ||
                                                                                                        match_search_kw($tchi['NoiDung'], $searchKeyword)
                                                                                                    )) || ($selectedCriterion === $tchiId);

                                                                                                    $critHasMatchingChild = false;
                                                                                                    if (!empty($evidences)) {
                                                                                                        foreach ($evidences as $ev_chk) {
                                                                                                            $evMatches = ($searchKeyword !== '' && (match_search_kw($ev_chk['MaMinhChung'], $searchKeyword) || match_search_kw($ev_chk['TenMinhChung'], $searchKeyword) || match_search_kw($ev_chk['MoTa'], $searchKeyword) || match_search_kw($ev_chk['NamHoc'], $searchKeyword) || match_search_kw($ev_chk['TepTin'], $searchKeyword))) || ($selectedEvidence !== '' && $selectedEvidence === $ev_chk['MaMinhChung']);
                                                                                                            if ($evMatches) {
                                                                                                                $critHasMatchingChild = true;
                                                                                                                break;
                                                                                                            }
                                                                                                        }
                                                                                                    }
                                                                                                    $isCritOpen = ($selectedCriterion === $tchiId) || ($critMatchesSelf && $searchKeyword !== '') || $critHasMatchingChild;
                                                                                                ?>
                                                                                                    <tr class="nested-criterion-row <?= $critMatchesSelf ? 'search-matched-row' : '' ?>" id="criterion-row-<?= htmlspecialchars($tchiId) ?>">
                                                                                                        <td class="text-center">
                                                                                                            <button class="btn btn-xs btn-outline-secondary tree-toggle-btn <?= $isCritOpen ? 'is-open expanded' : '' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-criterion-<?= htmlspecialchars($tchiId) ?>" aria-expanded="<?= $isCritOpen ? 'true' : 'false' ?>" title="Mở rộng / Thu gọn Minh chứng con">
                                                                                                                <i class="bi bi-chevron-right" style="font-size: 0.75rem;"></i>
                                                                                                            </button>
                                                                                                        </td>
                                                                                                        <td class="fw-bold text-success"><?= highlight_search_text($tchiId, $searchKeyword) ?></td>
                                                                                                        <td class="fw-semibold">
                                                                                                            <a class="text-decoration-none text-dark" href="javascript:void(0)" data-bs-toggle="collapse" data-bs-target="#collapse-criterion-<?= htmlspecialchars($tchiId) ?>">
                                                                                                                <?= highlight_search_text($tchi['TenTieuChi'], $searchKeyword) ?>
                                                                                                            </a>
                                                                                                        </td>
                                                                                                        <td class="small text-secondary"><?= highlight_search_text($tchi['NoiDung'] ?: '-', $searchKeyword) ?></td>
                                                                                                        <td class="text-center">
                                                                                                            <span class="badge <?= !empty($evidences) ? 'bg-primary' : 'bg-light text-muted border' ?>">
                                                                                                                <?= count($evidences) ?> minh chứng
                                                                                                            </span>
                                                                                                        </td>
                                                                                                        <td class="text-end">
                                                                                                            <div class="d-inline-flex gap-1">
                                                                                                                <button class="btn btn-xs btn-outline-success btn-add-evidence" type="button" 
                                                                                                                    data-bs-toggle="modal" data-bs-target="#modalAddEvidence" 
                                                                                                                    data-criterion-id="<?= htmlspecialchars($tchiId) ?>" 
                                                                                                                    data-criterion-name="<?= htmlspecialchars($tchi['TenTieuChi']) ?>"
                                                                                                                    title="Thêm Minh chứng con">
                                                                                                                    <i class="bi bi-plus-circle me-1"></i>Minh chứng
                                                                                                                </button>
                                                                                                                <button class="btn btn-xs btn-outline-primary btn-edit-criterion" type="button"
                                                                                                                    data-id="<?= htmlspecialchars($tchiId) ?>"
                                                                                                                    data-name="<?= htmlspecialchars($tchi['TenTieuChi']) ?>"
                                                                                                                    data-desc="<?= htmlspecialchars($tchi['NoiDung']) ?>"
                                                                                                                    data-order="<?= htmlspecialchars($tchi['ThuTu']) ?>"
                                                                                                                    data-standard-id="<?= htmlspecialchars($tcId) ?>"
                                                                                                                    title="Sửa Tiêu chí">
                                                                                                                    <i class="bi bi-pencil"></i>
                                                                                                                </button>
                                                                                                                <?php if ($tchiTotalEvidencesCount > 0): ?>
                                                                                        <button class="btn btn-xs btn-outline-secondary opacity-50" type="button" disabled title="Không thể xóa: Tiêu chí này đang có <?= $tchiTotalEvidencesCount ?> minh chứng liên kết">
                                                                                                                        <i class="bi bi-trash"></i>
                                                                                                                    </button>
                                                                                                                <?php else: ?>
                                                                                                                    <form method="post" class="d-inline" data-confirm-form="Bạn chắc chắn muốn xóa tiêu chí này?">
                                                                                                                        <input type="hidden" name="action" value="delete_criterion">
                                                                                                                        <input type="hidden" name="id" value="<?= htmlspecialchars($tchiId) ?>">
                                                                                                                        <button class="btn btn-xs btn-outline-danger" type="submit" title="Xóa Tiêu chí"><i class="bi bi-trash"></i></button>
                                                                                                                    </form>
                                                                                                                <?php endif; ?>
                                                                                                            </div>
                                                                                                        </td>
                                                                                                    </tr>

                                                                                                    <!-- LEVEL 4 COLLAPSIBLE CONTAINER: CẤP MINH CHỨNG -->
                                                                                                    <tr class="p-0 border-0">
                                                                                                        <td colspan="6" class="p-0 border-0">
                                                                                                            <div class="collapse <?= $isCritOpen ? 'show' : '' ?>" id="collapse-criterion-<?= htmlspecialchars($tchiId) ?>">
                                                                                                                <div class="p-3 my-1 ms-4 bg-white rounded border shadow-sm">
                                                                                                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                                                                                                        <div class="d-flex align-items-center gap-2">
                                                                                                                            <i class="bi bi-files text-primary"></i>
                                                                                                                            <strong class="small text-dark">Cập nhật Minh chứng</strong>
                                                                                                                            <span class="badge bg-primary-subtle text-primary small"><?= count($evidences) ?> minh chứng</span>
                                                                                                                        </div>
                                                                                                                        <button class="btn btn-xs btn-primary btn-add-evidence" type="button" 
                                                                                                                            data-bs-toggle="modal" data-bs-target="#modalAddEvidence" 
                                                                                                                            data-criterion-id="<?= htmlspecialchars($tchiId) ?>" 
                                                                                                                            data-criterion-name="<?= htmlspecialchars($tchi['TenTieuChi']) ?>">
                                                                                                                            <i class="bi bi-plus-lg me-1"></i>Thêm Minh chứng
                                                                                                                        </button>
                                                                                                                    </div>

                                                                                                                    <?php if (empty($evidences)): ?>
                                                                                                                        <div class="text-center py-2 text-muted small bg-light rounded border border-dashed">
                                                                                                                            <?= $isFilterActive ? 'Không có minh chứng nào phù hợp với bộ lọc/từ khóa.' : 'Chưa có minh chứng nào được gắn cho tiêu chí này. Bấm <strong>"+ Thêm Minh chứng"</strong> để tạo mới.' ?>
                                                                                                                        </div>
                                                                                                                    <?php else: ?>
                                                                                                                        <div class="table-responsive bg-white rounded border">
                                                                                                                            <table class="table table-xs align-middle mb-0">
                                                                                                                                <thead class="table-light">
                                                                                                                                    <tr>
                                                                                                                                        <th style="width: 45px;" class="text-center">STT</th>
                                                                                                                                        <th style="width: 90px;">Mã MC</th>
                                                                                                                                        <th style="min-width: 200px;">Tên Minh chứng</th>
                                                                                                         <th style="width: 110px;" class="text-center">Số hiệu</th>
                                                                                                                                        <th style="width: 100px;" class="text-center">Năm học</th>
                                                                                                                                        <th style="width: 105px;" class="text-center">Ngày ban hành</th>
                                                                                                         <th style="width: 130px;" class="text-center">Ngày cập nhật</th>
                                                                                                                                        <th style="width: 130px;" class="text-center">Mã người dùng</th>
                                                                                                         <th style="width: 130px;">Tệp đính kèm</th>
                                                                                                                                        <th style="width: 95px;" class="text-center">Trạng thái</th>
                                                                                                                                        <th style="width: 90px;" class="text-end">Hành động</th>
                                                                                                                                    </tr>
                                                                                                                                </thead>
                                                                                                                                <tbody>
                                                                                                                                    <?php 
                                                                                                                                    $mcIdx = 1;
                                                                                                                                    foreach ($evidences as $mc): 
                                                                                                                                        $mcId = $mc['MaMinhChung'];
                                                                                                                                        $mcFile = $mc['TepTin'];
                                                                                                                                        
                                                                                                                                        // Kiểm tra khớp từ khóa tìm kiếm & bộ lọc Cấp 4 (Minh chứng)
                                                                                                                                        $evMatchesSelf = ($searchKeyword !== '' && (
                                                                                                                                            match_search_kw($mc['MaMinhChung'], $searchKeyword) ||
                                                                                                                                            match_search_kw($mc['TenMinhChung'], $searchKeyword) ||
                                                                                                             match_search_kw($mc['SoHieu'] ?? '', $searchKeyword) ||
                                                                                                                                            match_search_kw($mc['MoTa'], $searchKeyword) ||
                                                                                                                                            match_search_kw($mc['NamHoc'], $searchKeyword) ||
                                                                                                                                            match_search_kw($mc['TepTin'], $searchKeyword)
                                                                                                                                        )) || ($selectedEvidence === $mcId);
                                                                                                                                        $mcIsActive = (int)($mc['TrangThai'] ?? 1) === 1;
                                                                                                                                        $hasFile = !empty($mcFile) && file_exists(__DIR__ . '/../' . $mcFile);
                                                                                                                                        $fileExt = $hasFile ? strtolower(pathinfo($mcFile, PATHINFO_EXTENSION)) : '';
                                                                                                                                        $fileUrl = $hasFile ? base_url($mcFile) : '#';
                                                                                                                                    ?>
                                                                                                                                        <tr class="nested-evidence-row <?= $evMatchesSelf ? 'search-matched-row' : '' ?>" id="evidence-row-<?= htmlspecialchars($mcId) ?>">
                                                                                                                                            <td class="text-center text-muted small"><?= $mcIdx++ ?></td>
                                                                                                                                            <td class="fw-bold text-primary"><?= highlight_search_text($mcId, $searchKeyword) ?></td>
                                                                                                                                            <td>
                                                                                                                                                <div class="fw-medium text-dark"><?= highlight_search_text($mc['TenMinhChung'], $searchKeyword) ?></div>
                                                                                                                                                <?php if (!empty($mc['MoTa'])): ?>
                                                                                                                                                    <small class="text-muted line-clamp-1"><?= highlight_search_text($mc['MoTa'], $searchKeyword) ?></small>
                                                                                                                                                <?php endif; ?>
                                                                                                                                            </td>
                                                                                                                                            <td class="text-center small font-monospace">
                                                                                                                 <?php if (!empty($mc['SoHieu'])): ?>
                                                                                                                     <span class="badge bg-light text-dark border"><?= highlight_search_text($mc['SoHieu'], $searchKeyword) ?></span>
                                                                                                                 <?php else: ?>
                                                                                                                     <span class="text-muted">-</span>
                                                                                                                 <?php endif; ?>
                                                                                                             </td>
                                                                                                             <td class="text-center small"><?= highlight_search_text($mc['NamHoc'] ?: '-', $searchKeyword) ?></td>
                                                                                                                                            <td class="text-center small"><?= !empty($mc['NgayBanHanh']) ? date('d/m/Y', strtotime($mc['NgayBanHanh'])) : '-' ?></td>
                                                                                                                <td class="text-center small text-nowrap">
                                                                                                                    <?php 
                                                                                                                    $rawUp = !empty($mc['NgayCapNhat']) ? $mc['NgayCapNhat'] : null;
                                                                                                                    $formattedUp = $rawUp ? date('d/m/Y H:i', strtotime($rawUp)) : '-';
                                                                                                                    $fullUp = $rawUp ? date('d/m/Y H:i:s', strtotime($rawUp)) : 'Chưa cập nhật';
                                                                                                                    ?>
                                                                                                                    <span class="badge bg-light text-secondary border px-2 py-1" title="Thời gian cập nhật thực tế: <?= htmlspecialchars($fullUp) ?>">
                                                                                                                        <i class="bi bi-clock-history me-1 text-info"></i><?= htmlspecialchars($formattedUp) ?>
                                                                                                                    </span>
                                                                                                                </td>
                                                                                                                <td class="text-center small">
                                                                                                                    <?php
                                                                                                                    $userCode = !empty($mc['MaNguoiDung']) ? $mc['MaNguoiDung'] : 'ND001';
                                                                                                                    $creatorName = !empty($mc['NguoiTao']) ? $mc['NguoiTao'] : 'Quản trị viên';
                                                                                                                    ?>
                                                                                                                    <span class="badge bg-light text-primary border font-monospace px-2 py-1" title="<?= htmlspecialchars($creatorName . ' (' . $userCode . ')') ?>">
                                                                                                                        <i class="bi bi-person me-1"></i><?= htmlspecialchars($userCode) ?>
                                                                                                                    </span>
                                                                                                                </td>
                                                                                                                                            <td>
                                                                                                                                                <?php if ($hasFile): ?>
                                                                                                                                                    <div class="d-inline-flex gap-1 align-items-center">
                                                                                                                                                        <?php if ($fileExt === 'pdf'): ?>
                                                                                                                                                            <button class="btn btn-xs btn-outline-danger btn-view-pdf" type="button" data-pdf-url="<?= htmlspecialchars($fileUrl) ?>" data-pdf-title="<?= htmlspecialchars($mc['TenMinhChung']) ?>" title="Xem tệp PDF">
                                                                                                                                                                <i class="bi bi-file-earmark-pdf"></i> Xem
                                                                                                                                                            </button>
                                                                                                                                                        <?php else: ?>
                                                                                                                                                            <a href="<?= htmlspecialchars($fileUrl) ?>" target="_blank" class="btn btn-xs btn-outline-secondary" title="Mở / Tải tệp">
                                                                                                                                                                <i class="bi bi-file-earmark"></i> Tải về
                                                                                                                                                            </a>
                                                                                                                                                        <?php endif; ?>
                                                                                                                                                        <small class="text-muted font-monospace" style="font-size: 0.7rem;">.<?= htmlspecialchars($fileExt) ?></small>
                                                                                                                                                    </div>
                                                                                                                                                <?php else: ?>
                                                                                                                                                    <span class="text-muted small">Không có file</span>
                                                                                                                                                <?php endif; ?>
                                                                                                                                            </td>
                                                                                                                                            <td class="text-center">
                                                                                                                                                <span class="badge <?= $mcIsActive ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' ?> small">
                                                                                                                                                    <?= $mcIsActive ? 'Hoạt động' : 'Tạm ẩn' ?>
                                                                                                                                                </span>
                                                                                                                                            </td>
                                                                                                                                            <td class="text-end">
                                                                                                                                                <div class="d-inline-flex gap-1">
                                                                                                                                                    <button class="btn btn-xs btn-outline-primary btn-edit-evidence" type="button"
                                                                                                                                                        data-id="<?= htmlspecialchars($mcId) ?>"
                                                                                                                                                        data-name="<?= htmlspecialchars($mc['TenMinhChung']) ?>"
                                                                                                                         data-sohieu="<?= htmlspecialchars($mc['SoHieu'] ?? '') ?>"
                                                                                                                                                        data-date="<?= htmlspecialchars($mc['NgayBanHanh'] ?? '') ?>"
                                                                                                                                                        data-namhoc="<?= htmlspecialchars($mc['NamHoc'] ?? '') ?>"
                                                                                                                                                        data-mota="<?= htmlspecialchars($mc['MoTa'] ?? '') ?>"
                                                                                                                                                        data-status="<?= $mcIsActive ? '1' : '0' ?>"
                                                                                                                                                        data-file="<?= htmlspecialchars($mc['TepTin'] ?? '') ?>"
                                                                                                                                                        data-criterion-id="<?= htmlspecialchars($tchiId) ?>"
                                                                                                                        data-criterion-ids="<?= htmlspecialchars(json_encode($mc['all_criteria_ids'] ?? [$tchiId])) ?>"
                                                                                                                                                        title="Sửa Minh chứng">
                                                                                                                                                        <i class="bi bi-pencil"></i>
                                                                                                                                                    </button>
                                                                                                                                                    <form method="post" class="d-inline" data-confirm-form="Bạn có chắc chắn muốn xóa minh chứng <?= htmlspecialchars($mcId) ?> này?">
                                                                                                                                                        <input type="hidden" name="action" value="delete_evidence">
                                                                                                                                                        <input type="hidden" name="id" value="<?= htmlspecialchars($mcId) ?>">
                                                                                                                                                        <button class="btn btn-xs btn-outline-danger" type="submit" title="Xóa Minh chứng"><i class="bi bi-trash"></i></button>
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
                    <h5 class="modal-title" id="modalStandardSetLabel"><i class="bi bi-collection me-2"></i>Thêm mới Bộ Tiêu chuẩn</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Mã Bộ tiêu chuẩn <span class="text-danger">*</span></label>
                            <input type="text" name="ma_bo_tieu_chuan" id="set_ma_bo" class="form-control" placeholder="VD: BTC01, BTC_2026..." required>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label fw-bold">Số hiệu <span class="text-danger">*</span></label>
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
                            <label class="form-label fw-bold">Tệp đính kèm (PDF, DOCX, XLSX...)</label>
                            <input type="file" name="tep_tin_pdf" id="set_file_pdf" class="form-control" accept=".pdf,.png,.jpg,.jpeg,.gif,.webp,.doc,.docx,.xls,.xlsx,application/pdf,image/*">
                            <div class="form-text" id="pdf_help_text">Hỗ trợ định dạng: <strong>PDF, PNG, JPG, JPEG, WEBP, DOCX</strong> (tối đa 50MB). Xem trực tiếp trên tab trình duyệt hoặc popup.</div>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-bold">Nội dung</label>
                            <textarea name="mo_ta" id="set_mo_ta" class="form-control" rows="3" placeholder="Nhập tóm tắt nội dung bộ tiêu chuẩn..."></textarea>
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
                            <label class="form-label fw-bold">Thuộc Bộ tiêu chuẩn <span class="text-danger">*</span></label>
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
                            <label class="form-label fw-bold">Nội dung</label>
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
                            <label class="form-label fw-bold">Nội dung</label>
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
     MODAL 4: THÊM / SỬA MINH CHỨNG
=============================================== -->
<div class="modal fade" id="modalAddEvidence" tabindex="-1" aria-labelledby="modalEvidenceLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="" id="formEvidence" enctype="multipart/form-data">
                <input type="hidden" name="action" value="save_evidence">
                <input type="hidden" name="id" id="ev_edit_id" value="">

                <div class="modal-header">
                    <h5 class="modal-title" id="modalEvidenceLabel"><i class="bi bi-file-earmark-plus me-2"></i>Thêm mới Minh chứng</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="form-label fw-bold mb-0">
                                    <i class="bi bi-diagram-3-fill text-primary me-1"></i>Thuộc Tiêu chuẩn &amp; Tiêu chí <span class="text-danger">*</span>
                                    <small class="text-muted fw-normal">(Có thể chọn 1 hoặc nhiều Tiêu chuẩn / Tiêu chí)</small>
                                </label>
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle" id="ev_modal_selected_criteria_count">Đã chọn: 0 tiêu chí</span>
                            </div>
                            
                            <div class="border rounded-3 p-2 bg-light criteria-selection-box" style="max-height: 220px; overflow-y: auto; background-color: #f8fafc;">
                                <div class="input-group input-group-sm mb-2">
                                    <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                                    <input type="text" class="form-control bg-white border-start-0" id="evModalCriteriaSearchInput" placeholder="Tìm nhanh theo mã hoặc tên tiêu chí/tiêu chuẩn...">
                                </div>
                                <div id="evModalCriteriaCheckboxContainer" class="d-flex flex-column gap-2">
                                    <?php foreach ($allStandardsBySet as $setK => $standardsList): ?>
                                        <?php foreach ($standardsList as $tcItem): 
                                            $critList = $allCriteriaByStandard[$tcItem['MaTieuChuan']] ?? [];
                                            if (empty($critList)) continue;
                                        ?>
                                            <div class="ev-modal-criteria-group p-2 rounded-2 bg-white border shadow-xs">
                                                <div class="d-flex align-items-center justify-content-between pb-1 mb-1 border-bottom">
                                                    <span class="fw-bold text-primary small d-flex align-items-center gap-1">
                                                        <i class="bi bi-folder2 text-primary"></i> [<?= htmlspecialchars($setK) ?>] <?= htmlspecialchars($tcItem['MaTieuChuan'] . ' - ' . $tcItem['TenTieuChuan']) ?>
                                                    </span>
                                                    <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none ev-btn-toggle-group-criteria" style="font-size: 0.72rem;">
                                                        Chọn tất cả
                                                    </button>
                                                </div>
                                                <div class="d-flex flex-column gap-1 ps-1">
                                                    <?php foreach ($critList as $cItem): ?>
                                                        <div class="form-check ev-criteria-item-row py-0.5">
                                                            <input class="form-check-input ev-modal-criteria-checkbox" type="checkbox" name="ma_tieu_chi[]" value="<?= htmlspecialchars($cItem['MaTieuChi']) ?>" id="ev_chk_crit_<?= htmlspecialchars($cItem['MaTieuChi']) ?>">
                                                            <label class="form-check-label small user-select-none" for="ev_chk_crit_<?= htmlspecialchars($cItem['MaTieuChi']) ?>">
                                                                <strong class="text-dark font-monospace"><?= htmlspecialchars($cItem['MaTieuChi']) ?></strong>: <?= htmlspecialchars($cItem['TenTieuChi']) ?>
                                                            </label>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div class="form-text small text-muted mt-1"><i class="bi bi-info-circle text-info me-1"></i>Minh chứng sẽ xuất hiện ở tất cả các Tiêu chuẩn / Tiêu chí được chọn.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Mã Minh chứng <span class="text-danger">*</span></label>
                            <input type="text" name="ma_minh_chung" id="ev_ma_mc" class="form-control" placeholder="VD: MC01, MC02..." required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Số hiệu</label>
                            <input type="text" name="so_hieu" id="ev_so_hieu" class="form-control font-monospace" placeholder="VD: 123/QĐ-ĐHTCNH, 45/TB-KCNTT...">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Tên Minh chứng <span class="text-danger">*</span></label>
                            <input type="text" name="ten_minh_chung" id="ev_ten_mc" class="form-control" placeholder="VD: Quyết định thành lập hội đồng đánh giá..." required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Năm học</label>
                            <input type="text" name="nam_hoc" id="ev_nam_hoc" class="form-control" placeholder="VD: 2025-2026, 2024-2025...">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Ngày ban hành</label>
                            <input type="date" name="ngay_ban_hanh" id="ev_ngay_ban_hanh" class="form-control">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-bold">Nội dung</label>
                            <textarea name="mo_ta" id="ev_mo_ta" class="form-control" rows="2" placeholder="Nhập trích yếu hoặc tóm tắt minh chứng..."></textarea>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label fw-bold">Tệp đính kèm (PDF, DOCX, XLSX...)</label>
                            <input type="file" name="evidence_file" id="ev_file" class="form-control" accept=".pdf,.doc,.docx,.xls,.xlsx,.zip,.rar,.jpg,.jpeg,.png">
                            <div class="form-text small text-muted" id="ev_current_file_text">Tối đa 100MB. Định dạng hỗ trợ: PDF, Word, Excel, ZIP.</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Trạng thái</label>
                            <select name="trang_thai" id="ev_trang_thai" class="form-select">
                                <option value="1">Đang hoạt động (Hiển thị)</option>
                                <option value="0">Tạm ẩn</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy bỏ</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Lưu Minh chứng</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ==========================================
     MODAL 5: XEM TRỰC TIẾP FILE PDF
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
<!-- ==========================================
     MODAL XEM CHI TIẾT BỘ TIÊU CHUẨN
=============================================== -->
<div class="modal fade" id="modalViewStandardSetDetails" tabindex="-1" aria-labelledby="modalViewStandardSetLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header text-white py-3 px-4" style="background: linear-gradient(135deg, #1e3a8a, #2563eb);">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-white bg-opacity-20 p-2 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                        <i class="bi bi-folder2-open fs-5 text-white"></i>
                    </div>
                    <div>
                        <h5 class="modal-title mb-0 text-white fw-bold" id="modalViewStandardSetLabel">Chi tiết Bộ Tiêu chuẩn</h5>
                        <small class="text-white text-opacity-75">Thông tin danh mục & tài liệu kiểm định chất lượng</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 bg-light">
                <!-- Hero Header Card -->
                <div class="card border-0 shadow-sm rounded-3 mb-3 bg-white">
                    <div class="card-body p-3 p-md-4">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3 pb-3 border-bottom">
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <span class="badge bg-primary px-3 py-2 fs-6 fw-bold shadow-sm" id="view_set_id_badge">BTC01</span>
                                <h5 class="mb-0 fw-bold text-dark fs-5" id="view_set_title">Tên bộ tiêu chuẩn</h5>
                            </div>
                            <span id="view_set_status_badge" class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 fs-7 fw-semibold">
                                <i class="bi bi-check-circle-fill me-1"></i>Hoạt động
                            </span>
                        </div>

                        <!-- 2-Column Info Grid -->
                        <div class="row g-3">
                            <div class="col-lg-6">
                                <div class="p-3 rounded-3 bg-light border h-100">
                                    <div class="small fw-bold text-muted text-uppercase mb-3"><i class="bi bi-card-checklist me-1 text-primary"></i>Căn cứ pháp lý & Thông tin</div>
                                    <div class="d-flex flex-column gap-2">
                                        <div class="d-flex justify-content-between align-items-center py-1 border-bottom border-light-subtle">
                                            <span class="text-muted small">Mã bộ:</span>
                                            <span class="fw-bold font-monospace text-primary" id="view_set_id"></span>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center py-1 border-bottom border-light-subtle">
                                            <span class="text-muted small">Số hiệu:</span>
                                            <span id="view_set_thongtu" class="badge bg-info-subtle text-dark border border-info-subtle fs-7 fw-semibold"></span>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center py-1 border-bottom border-light-subtle">
                                            <span class="text-muted small">Ngày ban hành:</span>
                                            <span class="fw-semibold text-dark" id="view_set_date"></span>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center py-1">
                                            <span class="text-muted small">Trạng thái:</span>
                                            <span id="view_set_status_text"></span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-6">
                                <div class="p-3 rounded-3 bg-light border h-100 d-flex flex-column justify-content-between">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <div class="small fw-bold text-muted text-uppercase"><i class="bi bi-diagram-3-fill me-1 text-primary"></i>Vị trí & Quy mô phân cấp</div>
                                        <span class="badge bg-primary text-white rounded-pill px-2 py-1" style="font-size: 0.72rem;">
                                            <i class="bi bi-layers me-1"></i>Cấp 1 / 4
                                        </span>
                                    </div>

                                    <!-- 4-Level Visual Pipeline / Hierarchy Flow -->
                                    <div class="p-2 bg-white rounded-3 border shadow-xs mb-2">
                                        <div class="d-flex align-items-center justify-content-between text-center gap-1">
                                            <!-- Cấp 1: Bộ Tiêu chuẩn (Active) -->
                                            <div class="flex-fill p-1 rounded-2 bg-primary-subtle border border-primary-subtle interactive-hierarchy-step" data-modal-type="set" data-jump-level="set" role="button" title="Bấm để nhảy tới vị trí Bộ tiêu chuẩn này trên bảng">
                                                <span class="badge bg-primary text-white rounded-pill px-2 py-0 mb-1" style="font-size: 0.65rem;">Cấp 1</span>
                                                <div class="fw-bold text-primary small text-truncate"><i class="bi bi-folder2-open me-1"></i>Bộ tiêu chuẩn</div>
                                                <div class="text-primary fw-semibold" style="font-size: 0.68rem;">(Đang xem)</div>
                                            </div>

                                            <div class="text-muted"><i class="bi bi-chevron-right text-secondary opacity-50"></i></div>

                                            <!-- Cấp 2: Tiêu chuẩn -->
                                            <div class="flex-fill p-1 rounded-2 bg-light border interactive-hierarchy-step" data-modal-type="set" data-jump-level="standard" role="button" title="Bấm để mở và trỏ tới bảng Tiêu chuẩn con trong danh sách">
                                                <span class="badge bg-secondary-subtle text-secondary rounded-pill px-2 py-0 mb-1" style="font-size: 0.65rem;">Cấp 2</span>
                                                <div class="fw-bold text-dark small text-truncate"><i class="bi bi-folder me-1 text-secondary"></i>Tiêu chuẩn</div>
                                                <div class="fw-bold text-primary fs-7"><span id="view_set_std_num">0</span> <small class="text-muted fw-normal" style="font-size: 0.7rem;">mục</small></div>
                                            </div>

                                            <div class="text-muted"><i class="bi bi-chevron-right text-secondary opacity-50"></i></div>

                                            <!-- Cấp 3: Tiêu chí -->
                                            <div class="flex-fill p-1 rounded-2 bg-light border interactive-hierarchy-step" data-modal-type="set" data-jump-level="criterion" role="button" title="Bấm để mở và trỏ tới bảng Tiêu chí con trong danh sách">
                                                <span class="badge bg-info-subtle text-info rounded-pill px-2 py-0 mb-1" style="font-size: 0.65rem;">Cấp 3</span>
                                                <div class="fw-bold text-dark small text-truncate"><i class="bi bi-list-check me-1 text-info"></i>Tiêu chí</div>
                                                <div class="fw-bold text-info fs-7"><span id="view_set_crit_num">0</span> <small class="text-muted fw-normal" style="font-size: 0.7rem;">mục</small></div>
                                            </div>

                                            <div class="text-muted"><i class="bi bi-chevron-right text-secondary opacity-50"></i></div>

                                            <!-- Cấp 4: Minh chứng -->
                                            <div class="flex-fill p-1 rounded-2 bg-light border interactive-hierarchy-step" data-modal-type="set" data-jump-level="evidence" role="button" title="Bấm để mở và trỏ tới bảng Minh chứng con trong danh sách">
                                                <span class="badge bg-success-subtle text-success rounded-pill px-2 py-0 mb-1" style="font-size: 0.65rem;">Cấp 4</span>
                                                <div class="fw-bold text-dark small text-truncate"><i class="bi bi-file-earmark-check me-1 text-success"></i>Minh chứng</div>
                                                <div class="fw-bold text-success fs-7"><span id="view_set_ev_num">0</span> <small class="text-muted fw-normal" style="font-size: 0.7rem;">mục</small></div>
                                            </div>
                                        </div>
                                        <div class="text-center mt-1">
                                            <small class="text-primary fw-medium" style="font-size: 0.72rem;"><i class="bi bi-cursor-fill me-1"></i>Bấm vào ô bất kỳ để nhảy ngay tới bảng dữ liệu đó</small>
                                        </div>
                                    </div>

                                    <!-- Chi tiết cây phân cấp (Tree Hierarchy Path) -->
                                    <div class="p-2 bg-white rounded-2 border">
                                        <div class="small fw-semibold text-muted text-uppercase mb-1" style="font-size: 0.7rem;">
                                            <i class="bi bi-diagram-2 me-1"></i>Sơ đồ phân cấp (Bấm để nhảy tới bảng):
                                        </div>
                                        <div class="font-monospace text-dark ps-1" style="font-size: 0.76rem; line-height: 1.45;">
                                            <div class="interactive-tree-item text-primary fw-bold text-truncate" data-modal-type="set" data-jump-level="set" role="button" title="Bấm để nhảy tới Bộ tiêu chuẩn">
                                                <i class="bi bi-box-seam me-1"></i>[1] Bộ TC: <span class="fw-normal text-secondary" id="view_set_tree_name">-</span>
                                            </div>
                                            <div class="interactive-tree-item text-secondary ps-3 text-truncate" data-modal-type="set" data-jump-level="standard" role="button" title="Bấm để mở và nhảy tới Tiêu chuẩn">
                                                <i class="bi bi-arrow-return-right me-1 text-muted"></i>[2] <span class="fw-bold text-primary" id="view_set_tree_std">0</span> Tiêu chuẩn trực thuộc &rarr;
                                            </div>
                                            <div class="interactive-tree-item text-secondary ps-4 ms-2 text-truncate" data-modal-type="set" data-jump-level="criterion" role="button" title="Bấm để mở và nhảy tới Tiêu chí">
                                                <i class="bi bi-arrow-return-right me-1 text-muted"></i>[3] <span class="fw-bold text-info" id="view_set_tree_crit">0</span> Tiêu chí trực thuộc &rarr;
                                            </div>
                                            <div class="interactive-tree-item text-success ps-5 ms-3 fw-bold text-truncate" data-modal-type="set" data-jump-level="evidence" role="button" title="Bấm để mở và nhảy tới Minh chứng">
                                                <i class="bi bi-arrow-return-right me-1 text-success"></i>[4] <span class="badge bg-success-subtle text-success px-2 py-0" id="view_set_tree_ev">0 minh chứng</span> &rarr;
                                            </div>
                                        </div>
                                    </div>
                                    <div class="d-none" id="view_set_standards_count"></div>
                                    <div class="d-none" id="view_set_criteria_count"></div>
                                    <div class="d-none" id="view_set_evidences_count"></div>
                                </div>
                            </div>

                            <!-- Mô tả trích yếu -->
                            <div class="col-12">
                                <div class="p-3 rounded-3 bg-light border">
                                    <div class="small fw-bold text-muted text-uppercase mb-2"><i class="bi bi-chat-left-quote me-1 text-primary"></i>Nội dung</div>
                                    <div id="view_set_desc" class="text-secondary small" style="white-space: pre-wrap; line-height: 1.6;"></div>
                                </div>
                            </div>

                            <!-- Tệp đính kèm -->
                            <div class="col-12">
                                <div class="small fw-bold text-muted text-uppercase mb-2"><i class="bi bi-paperclip me-1 text-primary"></i>File dữ liệu đính kèm</div>
                                <div id="view_set_file_container">
                                    <!-- Populated via JS -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Preview Area -->
                <div id="view_set_preview_box" class="card border-0 shadow-sm rounded-3 bg-white p-3" style="display: none;">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 pb-2 border-bottom">
                        <div class="fw-bold text-dark"><i class="bi bi-eye-fill me-2 text-primary"></i>Xem trước tài liệu trực tiếp:</div>
                        <a href="#" id="view_set_preview_newtab_link" target="_blank" class="btn btn-sm btn-primary">
                            <i class="bi bi-box-arrow-up-right me-1"></i>Mở toàn màn hình ở tab mới
                        </a>
                    </div>
                    <div id="view_set_preview_content" class="text-center rounded bg-secondary-subtle overflow-hidden" style="min-height: 420px;">
                        <!-- Embed / Image / Message -->
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-white border-top py-2 px-4 d-flex justify-content-between">
                <div>
                    <button type="button" class="btn btn-outline-primary btn-sm" id="view_set_btn_edit_trigger">
                        <i class="bi bi-pencil me-1"></i>Chỉnh sửa bộ này
                    </button>
                </div>
                <div class="d-inline-flex gap-2">
                    <a href="#" id="view_set_btn_open_tab_footer" target="_blank" class="btn btn-primary btn-sm" style="display: none;">
                        <i class="bi bi-box-arrow-up-right me-1"></i>Xem file dữ liệu (Tab mới)
                    </a>
                    <button type="button" class="btn btn-secondary btn-sm px-3" data-bs-dismiss="modal">Đóng</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
window.STANDARD_SETS_FILTER_DATA = <?= json_encode($allSetsForFilter, JSON_UNESCAPED_UNICODE) ?>;
window.STANDARDS_FILTER_DATA = <?= json_encode($allStandardsForFilter, JSON_UNESCAPED_UNICODE) ?>;
window.CRITERIA_FILTER_DATA = <?= json_encode($allCriteriaForFilter, JSON_UNESCAPED_UNICODE) ?>;
window.EVIDENCES_FILTER_DATA = <?= json_encode($allEvidencesForFilter, JSON_UNESCAPED_UNICODE) ?>;

document.addEventListener('DOMContentLoaded', function () {
    // ==============================================
    // 0. BỘ LỌC & TÌM KIẾM TẬP TRUNG THÔNG MINH
    // ==============================================
    const filterForm        = document.getElementById('filterForm');
    const keywordInput      = document.getElementById('filterKeyword');
    const clearKeywordBtn   = document.getElementById('btnClearKeyword');
    const standardSetSelect = document.getElementById('filterStandardSet');
    const standardSelect    = document.getElementById('filterStandard');
    const criterionSelect   = document.getElementById('filterCriterion');
    const evidenceSelect    = document.getElementById('filterEvidence');
    const statusSelect      = document.getElementById('filterStatus');
    const btnResetAll       = document.getElementById('btnResetAll');
    const activeTagsBox     = document.getElementById('activeFilterTags');
    const tagList           = document.getElementById('tagList');

    let debounceTimer = null;

    const ALL_SETS_DATA      = window.STANDARD_SETS_FILTER_DATA || [];
    const ALL_STANDARDS_DATA = window.STANDARDS_FILTER_DATA || [];
    const ALL_CRITERIA_DATA  = window.CRITERIA_FILTER_DATA || [];
    const ALL_EVIDENCES_DATA = window.EVIDENCES_FILTER_DATA || [];

    function escapeHtml(text) {
        if (!text) return '';
        const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return text.toString().replace(/[&<>"']/g, m => map[m]);
    }

    // Cập nhật danh sách Tiêu chí theo Tiêu chuẩn đã chọn
    function updateCriteriaDropdown(selectedStdId, currentCriVal = '') {
        if (!criterionSelect) return;
        let filtered = ALL_CRITERIA_DATA;
        if (selectedStdId) {
            filtered = filtered.filter(cri => cri.MaTieuChuan === selectedStdId);
        }

        let html = `<option value="">-- Tất cả Tiêu chí (${filtered.length}) --</option>`;
        filtered.forEach(cri => {
            const isSel = (currentCriVal && currentCriVal === cri.MaTieuChi) ? 'selected' : '';
            html += `<option value="${escapeHtml(cri.MaTieuChi)}" data-standard="${escapeHtml(cri.MaTieuChuan)}" data-set="${escapeHtml(cri.MaBoTieuChuan)}" ${isSel} title="${escapeHtml(cri.TenTieuChi)}">${escapeHtml(cri.MaTieuChi)} - ${escapeHtml(cri.TenTieuChi)}</option>`;
        });
        criterionSelect.innerHTML = html;
    }

    // Cập nhật danh sách Minh chứng theo Tiêu chuẩn & Tiêu chí đã chọn
    function updateEvidencesDropdown(selectedStdId, selectedCriId, currentEvVal = '') {
        if (!evidenceSelect) return;
        let filtered = ALL_EVIDENCES_DATA;
        if (selectedCriId) {
            filtered = filtered.filter(ev => ev.MaTieuChi === selectedCriId);
        } else if (selectedStdId) {
            filtered = filtered.filter(ev => ev.MaTieuChuan === selectedStdId);
        }

        let html = `<option value="">-- Tất cả Minh chứng (${filtered.length}) --</option>`;
        filtered.forEach(ev => {
            const isSel = (currentEvVal && currentEvVal === ev.MaMinhChung) ? 'selected' : '';
            html += `<option value="${escapeHtml(ev.MaMinhChung)}" data-criterion="${escapeHtml(ev.MaTieuChi)}" data-standard="${escapeHtml(ev.MaTieuChuan)}" data-set="${escapeHtml(ev.MaBoTieuChuan)}" ${isSel} title="${escapeHtml(ev.TenMinhChung)}">${escapeHtml(ev.MaMinhChung)} - ${escapeHtml(ev.TenMinhChung)}</option>`;
        });
        evidenceSelect.innerHTML = html;
    }

    // Hiển thị Chips lọc đang hoạt động
    function renderActiveFilterTags() {
        if (!activeTagsBox || !tagList) return;
        const kw = keywordInput ? keywordInput.value.trim() : '';
        const stdVal = standardSelect ? standardSelect.value : '';
        const criVal = criterionSelect ? criterionSelect.value : '';
        const evVal  = evidenceSelect ? evidenceSelect.value : '';
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

        if (stdVal) {
            const stdObj = ALL_STANDARDS_DATA.find(tc => tc.MaTieuChuan === stdVal);
            const stdLabel = stdObj ? `${stdObj.MaTieuChuan} - ${stdObj.TenTieuChuan}` : stdVal;
            tags.push({
                type: 'standard',
                label: `Tiêu chuẩn: ${stdLabel}`,
                onRemove: () => {
                    if (standardSelect) standardSelect.value = '';
                    updateCriteriaDropdown('');
                    updateEvidencesDropdown('', '');
                    if (filterForm) filterForm.submit();
                }
            });
        }

        if (criVal) {
            const criObj = ALL_CRITERIA_DATA.find(cri => cri.MaTieuChi === criVal);
            const criLabel = criObj ? `${criObj.MaTieuChi} - ${criObj.TenTieuChi}` : criVal;
            tags.push({
                type: 'criterion',
                label: `Tiêu chí: ${criLabel}`,
                onRemove: () => {
                    if (criterionSelect) criterionSelect.value = '';
                    updateEvidencesDropdown(standardSelect ? standardSelect.value : '', '');
                    if (filterForm) filterForm.submit();
                }
            });
        }

        if (evVal) {
            const evObj = ALL_EVIDENCES_DATA.find(ev => ev.MaMinhChung === evVal);
            const evLabel = evObj ? `${evObj.MaMinhChung} - ${evObj.TenMinhChung}` : evVal;
            tags.push({
                type: 'evidence',
                label: `Minh chứng: ${evLabel}`,
                onRemove: () => {
                    if (evidenceSelect) evidenceSelect.value = '';
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

    // Khởi tạo các dropdown liên kết theo tham số URL ban đầu
    const initStdVal = standardSelect ? standardSelect.value : '';
    const initCriVal = criterionSelect ? criterionSelect.value : '';
    const initEvVal  = evidenceSelect ? evidenceSelect.value : '';

    if (initStdVal) {
        updateCriteriaDropdown(initStdVal, initCriVal);
    }
    if (initStdVal || initCriVal) {
        updateEvidencesDropdown(initStdVal, initCriVal, initEvVal);
    }

    renderActiveFilterTags();

    // Tìm kiếm từ khóa: hiển thị nút xóa khi có nội dung
    if (keywordInput) {
        keywordInput.addEventListener('input', function () {
            const hasVal = this.value.trim() !== '';
            if (clearKeywordBtn) clearKeywordBtn.style.display = hasVal ? 'block' : 'none';
        });
    }

    if (clearKeywordBtn) {
        clearKeywordBtn.addEventListener('click', function () {
            if (keywordInput) keywordInput.value = '';
            this.style.display = 'none';
            if (filterForm) filterForm.submit();
        });
    }

    // Khi chọn Tiêu chuẩn -> Cập nhật Tiêu chí & Minh chứng
    if (standardSelect) {
        standardSelect.addEventListener('change', function () {
            const stdVal = this.value;
            updateCriteriaDropdown(stdVal, '');
            updateEvidencesDropdown(stdVal, '', '');
        });
    }

    // Khi chọn Tiêu chí -> Tự động sync Tiêu chuẩn nếu cần, cập nhật Minh chứng
    if (criterionSelect) {
        criterionSelect.addEventListener('change', function () {
            const criVal = this.value;
            if (criVal) {
                const criObj = ALL_CRITERIA_DATA.find(cri => cri.MaTieuChi === criVal);
                if (criObj && criObj.MaTieuChuan && standardSelect && standardSelect.value !== criObj.MaTieuChuan) {
                    standardSelect.value = criObj.MaTieuChuan;
                }
            }
            const currentStdVal = standardSelect ? standardSelect.value : '';
            updateEvidencesDropdown(currentStdVal, criVal, '');
        });
    }

    // Khi chọn Minh chứng -> Tự động sync Tiêu chuẩn & Tiêu chí nếu cần
    if (evidenceSelect) {
        evidenceSelect.addEventListener('change', function () {
            const evVal = this.value;
            if (evVal) {
                const evObj = ALL_EVIDENCES_DATA.find(ev => ev.MaMinhChung === evVal);
                if (evObj) {
                    if (evObj.MaTieuChuan && standardSelect && standardSelect.value !== evObj.MaTieuChuan) {
                        standardSelect.value = evObj.MaTieuChuan;
                    }
                    if (evObj.MaTieuChi && criterionSelect && criterionSelect.value !== evObj.MaTieuChi) {
                        criterionSelect.value = evObj.MaTieuChi;
                    }
                }
            }
        });
    }

    // Đặt lại toàn bộ bộ lọc
    if (btnResetAll) {
        btnResetAll.addEventListener('click', function () {
            window.location.href = window.location.pathname;
        });
    }

    // ==============================================
    // 0.1. TÌM KIẾM TRỰC QUAN & ĐIỀU HƯỚNG VỊ TRÍ KHỚP (SEARCH MATCH NAVIGATOR)
    // ==============================================
    const searchAlert = document.getElementById('searchResultsAlert');
    const matchCountText = document.getElementById('searchMatchCountText');
    const matchBadge = document.getElementById('matchCurrentIndexBadge');
    const btnPrevMatch = document.getElementById('btnPrevMatch');
    const btnNextMatch = document.getElementById('btnNextMatch');

    if (searchAlert) {
        const matchedRows = Array.from(document.querySelectorAll('.search-matched-row'));
        const totalMatches = matchedRows.length;
        let currentMatchIndex = 0;

        function focusMatch(index) {
            if (totalMatches === 0) return;
            matchedRows.forEach(r => r.classList.remove('current-focus-match'));
            const targetRow = matchedRows[index];
            if (!targetRow) return;

            // Tự động mở toàn bộ các cây cha của dòng khớp
            let parent = targetRow.parentElement;
            while (parent && parent !== document.body) {
                if (parent.classList.contains('collapse') && !parent.classList.contains('show')) {
                    bootstrap.Collapse.getOrCreateInstance(parent, { toggle: false }).show();
                }
                parent = parent.parentElement;
            }

            targetRow.classList.add('current-focus-match');
            targetRow.scrollIntoView({ behavior: 'smooth', block: 'center' });

            if (matchBadge) {
                matchBadge.textContent = `${index + 1} / ${totalMatches}`;
            }
        }

        if (totalMatches > 0) {
            if (matchCountText) {
                matchCountText.textContent = `(Tìm thấy ${totalMatches} vị trí khớp)`;
            }
            if (matchBadge) {
                matchBadge.textContent = `1 / ${totalMatches}`;
            }

            // Tự động trỏ đến dòng khớp đầu tiên
            setTimeout(() => {
                focusMatch(0);
            }, 300);

            if (btnNextMatch) {
                btnNextMatch.addEventListener('click', function () {
                    currentMatchIndex = (currentMatchIndex + 1) % totalMatches;
                    focusMatch(currentMatchIndex);
                });
            }

            if (btnPrevMatch) {
                btnPrevMatch.addEventListener('click', function () {
                    currentMatchIndex = (currentMatchIndex - 1 + totalMatches) % totalMatches;
                    focusMatch(currentMatchIndex);
                });
            }
        } else {
            if (matchCountText) {
                matchCountText.textContent = '(Không tìm thấy kết quả phù hợp trên trang này)';
            }
            if (matchBadge) {
                matchBadge.textContent = '0 / 0';
            }
            if (btnNextMatch) btnNextMatch.disabled = true;
            if (btnPrevMatch) btnPrevMatch.disabled = true;
        }
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

    // Safe helper setters
    const setSafeText = (id, text) => {
        const el = document.getElementById(id);
        if (el) el.textContent = text !== undefined && text !== null ? text : '';
    };
    const setSafeHtml = (id, html) => {
        const el = document.getElementById(id);
        if (el) el.innerHTML = html !== undefined && html !== null ? html : '';
    };

    // 3. Setup PDF Viewer Modal
    const pdfModalEl = document.getElementById('modalPdfViewer');
    const getPdfModalInstance = () => pdfModalEl ? bootstrap.Modal.getOrCreateInstance(pdfModalEl) : null;
    const pdfIframe = document.getElementById('pdfViewerIframe');
    const pdfTitle = document.getElementById('modalPdfViewerLabel');
    const pdfNewTab = document.getElementById('btnPdfOpenNewTab');
    const pdfDownload = document.getElementById('btnPdfDownload');

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.btn-view-pdf');
        if (!btn) return;
        e.stopPropagation();
        e.preventDefault();

        const url = btn.dataset.pdfUrl;
        const title = btn.dataset.pdfTitle;
        if (!url) return;

        if (pdfIframe) pdfIframe.src = url;
        if (pdfTitle) pdfTitle.textContent = title || 'Xem chi tiết Thông tư PDF';
        if (pdfNewTab) pdfNewTab.href = url;
        if (pdfDownload) pdfDownload.href = url;

        const modalInst = getPdfModalInstance();
        if (modalInst) modalInst.show();
    });

    if (pdfModalEl) {
        pdfModalEl.addEventListener('hidden.bs.modal', function () {
            if (pdfIframe) pdfIframe.src = 'about:blank';
        });
    }

    // 4. Modal View Standard Set Details
    const modalViewDetailEl = document.getElementById('modalViewStandardSetDetails');
    const getModalViewDetailInstance = () => modalViewDetailEl ? bootstrap.Modal.getOrCreateInstance(modalViewDetailEl) : null;
    let currentViewedSetId = '';

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.btn-view-set-detail');
        if (!btn) return;
        e.stopPropagation();
        e.preventDefault();

        const id = btn.dataset.id || '';
        currentViewedSetId = id;
        const name = btn.dataset.name || '';
        const thongtu = btn.dataset.thongtu || 'Chưa có số hiệu';
        const date = btn.dataset.dateFormatted || (btn.dataset.date || '-');
        const desc = btn.dataset.desc || 'Chưa có nội dung';
        const status = btn.dataset.status === '1';
        const pdf = btn.dataset.pdf || '';
        const pdfUrl = btn.dataset.pdfUrl || '';
        const viewUrl = btn.dataset.viewUrl || '';
        const stdCount = btn.dataset.standardsCount || '0';
        const criCount = btn.dataset.criteriaCount || '0';
        const eviCount = btn.dataset.evidencesCount || '0';

        setSafeText('view_set_id_badge', id);
        setSafeText('view_set_title', name);
        setSafeText('view_set_id', id);
        setSafeText('view_set_thongtu', thongtu);
        setSafeText('view_set_date', date);
        setSafeText('view_set_desc', desc);
        setSafeText('view_set_std_num', stdCount);
        setSafeText('view_set_crit_num', criCount);
        setSafeText('view_set_ev_num', eviCount);
        setSafeText('view_set_tree_name', name ? `${id} - ${name}` : id);
        setSafeText('view_set_tree_std', stdCount);
        setSafeText('view_set_tree_crit', criCount);
        setSafeText('view_set_tree_ev', `${eviCount} minh chứng`);
        setSafeHtml('view_set_standards_count', '<i class="bi bi-folder2 me-1"></i>' + stdCount + ' Tiêu chuẩn');
        setSafeHtml('view_set_criteria_count', '<i class="bi bi-list-check me-1"></i>' + criCount + ' Tiêu chí');
        setSafeHtml('view_set_evidences_count', '<i class="bi bi-file-earmark-check me-1"></i>' + eviCount + ' Minh chứng');

        const statusBadge = document.getElementById('view_set_status_badge');
        const statusTextEl = document.getElementById('view_set_status_text');
        if (status) {
            if (statusBadge) {
                statusBadge.className = 'badge bg-success-subtle text-success border border-success-subtle px-3 py-2 fs-7 fw-semibold';
                statusBadge.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i>Hoạt động';
            }
            if (statusTextEl) {
                statusTextEl.innerHTML = '<span class="badge bg-success-subtle text-success"><i class="bi bi-check-circle-fill me-1"></i>Hoạt động</span>';
            }
        } else {
            if (statusBadge) {
                statusBadge.className = 'badge bg-secondary-subtle text-secondary border border-secondary-subtle px-3 py-2 fs-7 fw-semibold';
                statusBadge.innerHTML = '<i class="bi bi-pause-circle me-1"></i>Ngừng hoạt động';
            }
            if (statusTextEl) {
                statusTextEl.innerHTML = '<span class="badge bg-secondary-subtle text-secondary"><i class="bi bi-pause-circle me-1"></i>Ngừng hoạt động</span>';
            }
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

            if (fileContainer) {
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
            }

            if (previewBox) previewBox.style.display = 'block';
            if (previewNewTabLink) previewNewTabLink.href = viewUrl;
            if (footerOpenTab) {
                footerOpenTab.href = viewUrl;
                footerOpenTab.style.display = 'inline-flex';
            }

            if (isPdf) {
                if (previewContent) previewContent.innerHTML = `<iframe src="${pdfUrl}" style="width: 100%; height: 420px; border: none; border-radius: 6px;"></iframe>`;
            } else if (isImage) {
                if (previewContent) previewContent.innerHTML = `<a href="${viewUrl}" target="_blank" title="Bấm để mở kích thước lớn"><img src="${pdfUrl}" alt="Preview" class="img-fluid rounded shadow-sm" style="max-height: 420px; object-fit: contain;"></a>`;
            } else {
                if (previewContent) previewContent.innerHTML = `
                    <div class="py-4 text-center">
                        <i class="bi bi-file-earmark-word text-primary" style="font-size: 3rem;"></i>
                        <h6 class="mt-2 fw-bold">${pdf.split('/').pop()}</h6>
                        <p class="text-muted small">Tài liệu văn bản (${ext.toUpperCase()}). Bấm vào nút bên dưới để xem hoặc tải về máy.</p>
                        <a href="${viewUrl}" target="_blank" class="btn btn-primary btn-sm"><i class="bi bi-box-arrow-up-right me-1"></i>Mở file trong tab mới</a>
                    </div>
                `;
            }
        } else {
            if (fileContainer) fileContainer.innerHTML = '<span class="badge bg-light text-muted border p-2"><i class="bi bi-dash-circle me-1"></i>Chưa có file dữ liệu đính kèm</span>';
            if (previewBox) previewBox.style.display = 'none';
            if (previewContent) previewContent.innerHTML = '';
            if (footerOpenTab) footerOpenTab.style.display = 'none';
        }

        // Edit trigger button inside view modal
        const editBtn = document.getElementById('view_set_btn_edit_trigger');
        if (editBtn) {
            editBtn.onclick = function () {
                const modalInst = getModalViewDetailInstance();
                if (modalInst) modalInst.hide();
                const targetEditBtn = document.querySelector(`.btn-edit-set[data-id="${id}"]`);
                if (targetEditBtn) {
                    targetEditBtn.click();
                }
            };
        }

        const modalInst = getModalViewDetailInstance();
        if (modalInst) modalInst.show();
    });

    if (modalViewDetailEl) {
        modalViewDetailEl.addEventListener('hidden.bs.modal', function () {
            const previewContent = document.getElementById('view_set_preview_content');
            if (previewContent) previewContent.innerHTML = '';
        });
    }

    /**
     * Kích hoạt hiệu ứng phát quang đặc biệt & gắn thẻ định vị nổi (Beacon) tại dòng mục tiêu Admin
     */
    function triggerHighlightTargetAdmin(targetRow, label) {
        if (!targetRow) return;

        // Cuộn màn hình mượt mà đưa đối tượng vào giữa khung nhìn
        targetRow.scrollIntoView({ behavior: 'smooth', block: 'center' });

        // Kích hoạt hiệu ứng phát sáng & viền xung quanh
        targetRow.classList.remove('highlight-target-row');
        void targetRow.offsetWidth; // Buộc reflow để kích hoạt lại animation
        targetRow.classList.add('highlight-target-row');

        // Gắn thẻ chỉ đường định vị nổi (Floating Locator Beacon)
        const oldBeacon = targetRow.querySelector('.jump-target-beacon');
        if (oldBeacon) oldBeacon.remove();

        const beacon = document.createElement('div');
        beacon.className = 'jump-target-beacon';
        beacon.innerHTML = `<i class="bi bi-geo-alt-fill text-warning"></i><span>Vị trí bạn chọn: <strong>${escapeHtml(label)}</strong></span>`;

        // Đảm bảo phần tử có position tương đối
        if (getComputedStyle(targetRow).position === 'static') {
            targetRow.style.position = 'relative';
        }
        targetRow.appendChild(beacon);

        // Tự động dọn dẹp thẻ beacon sau khi animation kết thúc (3.6 giây)
        setTimeout(() => {
            if (beacon && beacon.parentNode) {
                beacon.remove();
            }
        }, 3600);
    }

    // Jump to Hierarchy Target in Main Table for Admin
    /**
     * Chức năng: Điều hướng & Nhảy trực tiếp đến vị trí dòng tương ứng trên bảng phân cấp Quản trị (Admin)
     * -----------------------------------------------------------------------------------------------------
     * @param {string} level - Cấp độ phân cấp ('set', 'standard', 'criterion', 'evidence')
     * @param {object} context - Chứa mã ID bộ tiêu chuẩn và đối tượng Modal hiện tại
     */
    function jumpToHierarchyAdmin(level, context) {
        // Đóng modal chi tiết
        if (context && context.modalInstance) {
            context.modalInstance.hide();
        }

        const setId = context ? context.setId : '';
        if (!setId) return;

        // CẤP 1: BỘ TIÊU CHUẨN
        if (level === 'set') {
            const row = document.getElementById('set-row-' + setId);
            if (row) {
                triggerHighlightTargetAdmin(row, 'Bộ tiêu chuẩn (Cấp 1)');
            }
            return;
        }

        // CẤP 2: TIÊU CHUẨN (Mở accordion Bộ TC, cuộn tới dòng Tiêu chuẩn đầu tiên của bộ)
        if (level === 'standard') {
            const setCollapse = document.getElementById('collapse-set-' + setId);
            if (setCollapse) {
                bootstrap.Collapse.getOrCreateInstance(setCollapse, { toggle: false }).show();
            }
            setTimeout(() => {
                let targetRow = setCollapse ? (setCollapse.querySelector('.nested-standard-row') || setCollapse) : null;
                if (targetRow) {
                    triggerHighlightTargetAdmin(targetRow, 'Tiêu chuẩn (Cấp 2)');
                }
            }, 250);
            return;
        }

        // CẤP 3: TIÊU CHÍ (Mở accordion Bộ TC -> Tiêu chuẩn, cuộn tới dòng Tiêu chí)
        if (level === 'criterion') {
            const setCollapse = document.getElementById('collapse-set-' + setId);
            if (setCollapse) {
                bootstrap.Collapse.getOrCreateInstance(setCollapse, { toggle: false }).show();
            }
            setTimeout(() => {
                let stdCollapse = setCollapse ? setCollapse.querySelector('.collapse[id^="collapse-standard-"]') : null;
                if (stdCollapse) {
                    bootstrap.Collapse.getOrCreateInstance(stdCollapse, { toggle: false }).show();
                }
                setTimeout(() => {
                    let targetRow = setCollapse ? setCollapse.querySelector('.nested-criterion-row') : null;
                    if (targetRow) {
                        triggerHighlightTargetAdmin(targetRow, 'Tiêu chí (Cấp 3)');
                    }
                }, 220);
            }, 250);
            return;
        }

        // CẤP 4: MINH CHỨNG (Mở accordion Bộ TC -> Tiêu chuẩn -> Tiêu chí, cuộn tới dòng Minh chứng)
        if (level === 'evidence') {
            const setCollapse = document.getElementById('collapse-set-' + setId);
            if (setCollapse) {
                bootstrap.Collapse.getOrCreateInstance(setCollapse, { toggle: false }).show();
            }
            setTimeout(() => {
                let stdCollapse = setCollapse ? setCollapse.querySelector('.collapse[id^="collapse-standard-"]') : null;
                if (stdCollapse) {
                    bootstrap.Collapse.getOrCreateInstance(stdCollapse, { toggle: false }).show();
                }
                setTimeout(() => {
                    let criCollapse = setCollapse ? setCollapse.querySelector('.collapse[id^="collapse-criterion-"]') : null;
                    if (criCollapse) {
                        bootstrap.Collapse.getOrCreateInstance(criCollapse, { toggle: false }).show();
                    }
                    setTimeout(() => {
                        let targetRow = setCollapse ? setCollapse.querySelector('.nested-evidence-row') : null;
                        if (targetRow) {
                            triggerHighlightTargetAdmin(targetRow, 'Minh chứng (Cấp 4)');
                        }
                    }, 220);
                }, 220);
            }, 250);
            return;
        }
    }

    // 4. Đăng ký sự kiện Click cho các thẻ Phân cấp trong Modal Chi tiết Bộ tiêu chuẩn (Admin)
    document.addEventListener('click', function (e) {
        const jumpEl = e.target.closest('[data-jump-level]');
        if (!jumpEl) return;

        const level = jumpEl.dataset.jumpLevel;
        const modalType = jumpEl.dataset.modalType;

        if (modalType === 'set') {
            const modalInstance = getModalViewDetailInstance();
            jumpToHierarchyAdmin(level, {
                setId: currentViewedSetId,
                modalInstance: modalInstance
            });
        }
    });

    // 5. Modal Edit Standard Set
    const modalSetEl = document.getElementById('modalAddStandardSet');
    const modalSet = modalSetEl ? new bootstrap.Modal(modalSetEl) : null;

    document.querySelectorAll('.btn-edit-set').forEach(btn => {
        btn.addEventListener('click', function () {
            document.getElementById('modalStandardSetLabel').innerHTML = '<i class="bi bi-pencil-square me-2"></i>Cập nhật Bộ Tiêu chuẩn';
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
            document.getElementById('modalStandardSetLabel').innerHTML = '<i class="bi bi-collection me-2"></i>Thêm mới Bộ Tiêu chuẩn';
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

    // 7. Modal Add / Edit Evidence (Minh chứng - Nhiều tiêu chí)
    const modalEvEl = document.getElementById('modalAddEvidence');
    const modalEv = modalEvEl ? new bootstrap.Modal(modalEvEl) : null;
    const evCheckboxes = document.querySelectorAll('.ev-modal-criteria-checkbox');
    const evCountBadge = document.getElementById('ev_modal_selected_criteria_count');
    const evSearchInput = document.getElementById('evModalCriteriaSearchInput');

    function updateEvModalCriteriaCount() {
        if (!evCountBadge) return;
        const checkedCount = document.querySelectorAll('.ev-modal-criteria-checkbox:checked').length;
        evCountBadge.textContent = 'Đã chọn: ' + checkedCount + ' tiêu chí';
    }

    if (evCheckboxes.length > 0) {
        evCheckboxes.forEach(chk => {
            chk.addEventListener('change', updateEvModalCriteriaCount);
        });
    }

    // Toggle group criteria
    document.querySelectorAll('.ev-btn-toggle-group-criteria').forEach(btn => {
        btn.addEventListener('click', function () {
            const card = this.closest('.ev-modal-criteria-group');
            if (!card) return;
            const groupCheckboxes = card.querySelectorAll('.ev-modal-criteria-checkbox');
            const allChecked = Array.from(groupCheckboxes).every(c => c.checked);
            groupCheckboxes.forEach(c => {
                c.checked = !allChecked;
            });
            this.textContent = allChecked ? 'Chọn tất cả' : 'Bỏ chọn';
            updateEvModalCriteriaCount();
        });
    });

    // Quick search criteria in modal
    if (evSearchInput) {
        evSearchInput.addEventListener('input', function () {
            const kw = this.value.toLowerCase().trim();
            document.querySelectorAll('.ev-modal-criteria-group').forEach(group => {
                let anyVisibleInGroup = false;
                group.querySelectorAll('.ev-criteria-item-row').forEach(row => {
                    const text = row.textContent.toLowerCase();
                    if (kw === '' || text.includes(kw)) {
                        row.style.display = '';
                        anyVisibleInGroup = true;
                    } else {
                        row.style.display = 'none';
                    }
                });
                group.style.display = anyVisibleInGroup ? '' : 'none';
            });
        });
    }

    if (modalEvEl) {
        modalEvEl.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            if (!button || button.classList.contains('btn-edit-evidence')) return;
            const critId = button.getAttribute('data-criterion-id');
            evCheckboxes.forEach(chk => {
                chk.checked = (critId && chk.value === critId);
            });
            updateEvModalCriteriaCount();
        });
        modalEvEl.addEventListener('hidden.bs.modal', function () {
            document.getElementById('formEvidence').reset();
            document.getElementById('ev_edit_id').value = '';
            if (document.getElementById('ev_so_hieu')) document.getElementById('ev_so_hieu').value = '';
            document.getElementById('ev_current_file_text').innerHTML = 'Tối đa 100MB. Định dạng hỗ trợ: PDF, Word, Excel, ZIP.';
            document.getElementById('modalEvidenceLabel').innerHTML = '<i class="bi bi-file-earmark-plus me-2"></i>Thêm mới Minh chứng';
            evCheckboxes.forEach(chk => { chk.checked = false; });
            if (evSearchInput) {
                evSearchInput.value = '';
                document.querySelectorAll('.ev-modal-criteria-group').forEach(g => { g.style.display = ''; });
                document.querySelectorAll('.ev-criteria-item-row').forEach(r => { r.style.display = ''; });
            }
            updateEvModalCriteriaCount();
        });
    }

    document.querySelectorAll('.btn-edit-evidence').forEach(btn => {
        btn.addEventListener('click', function () {
            document.getElementById('ev_edit_id').value = this.dataset.id || '';
            document.getElementById('ev_ma_mc').value = this.dataset.id || '';
            document.getElementById('ev_ten_mc').value = this.dataset.name || '';
            if (document.getElementById('ev_so_hieu')) document.getElementById('ev_so_hieu').value = this.dataset.sohieu || '';
            document.getElementById('ev_ngay_ban_hanh').value = this.dataset.date || '';
            document.getElementById('ev_nam_hoc').value = this.dataset.namhoc || '';
            document.getElementById('ev_mo_ta').value = this.dataset.mota || '';
            document.getElementById('ev_trang_thai').value = this.dataset.status || '1';

            // Check all associated criteria
            let targetCritIds = [];
            try {
                if (this.dataset.criterionIds) {
                    targetCritIds = JSON.parse(this.dataset.criterionIds);
                }
            } catch (e) {}
            if (!Array.isArray(targetCritIds) || targetCritIds.length === 0) {
                if (this.dataset.criterionId) targetCritIds = [this.dataset.criterionId];
            }

            evCheckboxes.forEach(chk => {
                chk.checked = targetCritIds.includes(chk.value);
            });
            updateEvModalCriteriaCount();

            if (this.dataset.file) {
                document.getElementById('ev_current_file_text').innerHTML = '<span class="text-primary"><i class="bi bi-paperclip"></i> Tệp hiện tại: <strong>' + this.dataset.file.split('/').pop() + '</strong> (Chọn tệp mới nếu muốn thay thế)</span>';
            } else {
                document.getElementById('ev_current_file_text').innerHTML = 'Chưa có tệp đính kèm. Chọn tệp để tải lên (Tối đa 100MB).';
            }
            document.getElementById('modalEvidenceLabel').innerHTML = '<i class="bi bi-pencil-square me-2"></i>Cập nhật Minh chứng';
            if (modalEv) modalEv.show();
        });
    });

    // =========================================================================
    // Auto-scroll and highlight target document from hash or query parameters
    // =========================================================================
    const urlParams = new URLSearchParams(window.location.search);
    const targetEv = urlParams.get('evidence');
    const targetCrit = urlParams.get('criterion');
    const targetStd = urlParams.get('standard');
    const hash = window.location.hash;

    let targetRowEl = null;
    let targetLabel = '';

    if (hash && hash.startsWith('#') && hash.length > 1) {
        try {
            targetRowEl = document.querySelector(hash);
            if (targetRowEl) targetLabel = 'Vị trí bạn chọn';
        } catch (e) {}
    }
    if (!targetRowEl && targetEv) {
        targetRowEl = document.getElementById('evidence-row-' + targetEv);
        targetLabel = 'Minh chứng ' + targetEv;
    }
    if (!targetRowEl && targetCrit) {
        targetRowEl = document.getElementById('criterion-row-' + targetCrit);
        targetLabel = 'Tiêu chí ' + targetCrit;
    }
    if (!targetRowEl && targetStd) {
        targetRowEl = document.getElementById('standard-row-' + targetStd);
        targetLabel = 'Tiêu chuẩn ' + targetStd;
    }

    if (targetRowEl) {
        let parent = targetRowEl.parentElement;
        while (parent && parent !== document.body) {
            if (parent.classList.contains('collapse') && !parent.classList.contains('show')) {
                bootstrap.Collapse.getOrCreateInstance(parent, { toggle: false }).show();
            }
            parent = parent.parentElement;
        }
        setTimeout(() => {
            triggerHighlightTargetAdmin(targetRowEl, targetLabel || 'Vị trí tài liệu');
        }, 350);
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
