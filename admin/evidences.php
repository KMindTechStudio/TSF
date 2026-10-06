<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/helpers.php';
require_roles(['admin', 'user']);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/data.php';

$pdo = db();
$success = '';
$error = '';
$maxUploadMb = 100;
$maxUploadBytes = $maxUploadMb * 1024 * 1024;

// 1. Export Excel
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    $searchKeyword = trim($_GET['search'] ?? $_GET['q'] ?? '');
    $filterStandardSet = trim($_GET['standard_set'] ?? '');
    $filterStandard = trim($_GET['standard'] ?? '');
    $filterCriterion = trim($_GET['criterion'] ?? '');
    $filterStatus = trim($_GET['status'] ?? '');
    $filterHasFile = trim($_GET['has_file'] ?? '');

    $exportList = array_filter($evidences, function ($ev) use ($searchKeyword, $filterStandardSet, $filterStandard, $filterCriterion, $filterStatus, $filterHasFile) {
        if ($searchKeyword !== '') {
            $allCritNames = '';
            if (!empty($ev['criteria'])) {
                foreach ($ev['criteria'] as $c) {
                    $allCritNames .= ' ' . ($c['id'] ?? '') . ' ' . ($c['name'] ?? '') . ' ' . ($c['standard_id'] ?? '') . ' ' . ($c['standard_name'] ?? '') . ' ' . ($c['set_name'] ?? '');
                }
            }
            $haystack = implode(' ', [
                $ev['code'] ?? '',
                $ev['name'] ?? '',
                $ev['so_hieu'] ?? '',
                $ev['criterion_name'] ?? '',
                $ev['standard_name'] ?? '',
                $ev['set_name'] ?? '',
                $allCritNames,
                $ev['issue_date_formatted'] ?? '',
                $ev['issue_date'] ?? '',
                $ev['updated'] ?? '',
                $ev['user_name'] ?? '',
                $ev['file_path'] ?? '',
            ]);
            if (!search_contains($haystack, $searchKeyword)) return false;
        }
        if ($filterStandardSet !== '') {
            $hasSet = in_array($filterStandardSet, $ev['set_ids'] ?? [], true) || ($ev['ma_bo_tieu_chuan'] ?? '') === $filterStandardSet;
            if (!$hasSet) return false;
        }
        if ($filterStandard !== '') {
            $hasStd = in_array($filterStandard, $ev['standard_ids'] ?? [], true) || ($ev['ma_tieu_chuan'] ?? '') === $filterStandard;
            if (!$hasStd) return false;
        }
        if ($filterCriterion !== '') {
            $hasCrit = in_array($filterCriterion, $ev['criteria_ids'] ?? [], true) || ($ev['ma_tieu_chi'] ?? '') === $filterCriterion;
            if (!$hasCrit) return false;
        }
        if ($filterStatus !== '' && (string)($ev['status_raw'] ?? 1) !== $filterStatus) return false;
        if ($filterHasFile === 'yes' && empty($ev['file_path'])) return false;
        if ($filterHasFile === 'no' && !empty($ev['file_path'])) return false;
        return true;
    });

    $exportData = [];
    $stt = 1;
    foreach ($exportList as $ev) {
        $stdLabels = [];
        $critLabels = [];
        if (!empty($ev['criteria'])) {
            foreach ($ev['criteria'] as $c) {
                if (!empty($c['standard_name'])) {
                    $stdLabels[] = $c['standard_id'] . ' - ' . $c['standard_name'];
                }
                if (!empty($c['name'])) {
                    $critLabels[] = $c['id'] . ' - ' . $c['name'];
                }
            }
        }
        $stdText = !empty($stdLabels) ? implode(' | ', array_unique($stdLabels)) : ($ev['standard_name'] ? ($ev['ma_tieu_chuan'] . ' - ' . $ev['standard_name']) : '-');
        $critText = !empty($critLabels) ? implode(' | ', array_unique($critLabels)) : ($ev['criterion_name'] ? ($ev['ma_tieu_chi'] . ' - ' . $ev['criterion_name']) : '-');

        $exportData[] = [
            'stt'          => $stt++,
            'code'         => $ev['code'],
            'name'         => $ev['name'],
            'so_hieu'      => !empty($ev['so_hieu']) ? $ev['so_hieu'] : '-',
            'standard'     => $stdText,
            'criterion'    => $critText,
            'issue_date'   => !empty($ev['issue_date_formatted']) ? $ev['issue_date_formatted'] : (!empty($ev['issue_date']) ? date('d/m/Y', strtotime($ev['issue_date'])) : '-'),
            'updated_date' => !empty($ev['updated']) ? $ev['updated'] : '-',
            'user_name'    => !empty($ev['user_name']) ? $ev['user_name'] : 'Quản trị viên',
            'file_name'    => !empty($ev['file_path']) ? basename($ev['file_path']) : 'Không có',
            'status'       => $ev['status'] ?? 'Đang hoạt động',
        ];
    }

    $columns = [
        ['key' => 'stt', 'label' => 'STT', 'align' => 'center', 'width' => '60px'],
        ['key' => 'code', 'label' => 'Mã minh chứng', 'align' => 'center', 'width' => '130px'],
        ['key' => 'name', 'label' => 'Tên minh chứng', 'align' => 'left', 'width' => '300px'],
        ['key' => 'so_hieu', 'label' => 'Số hiệu', 'align' => 'center', 'width' => '140px'],
        ['key' => 'standard', 'label' => 'Tiêu chuẩn', 'align' => 'left', 'width' => '220px'],
        ['key' => 'criterion', 'label' => 'Tiêu chí', 'align' => 'left', 'width' => '220px'],
        ['key' => 'issue_date', 'label' => 'Ngày ban hành văn bản', 'align' => 'center', 'width' => '160px'],
        ['key' => 'updated_date', 'label' => 'Ngày cập nhật', 'align' => 'center', 'width' => '150px'],
        ['key' => 'user_name', 'label' => 'Người cập nhật', 'align' => 'left', 'width' => '180px'],
        ['key' => 'file_name', 'label' => 'File đính kèm', 'align' => 'left', 'width' => '180px'],
        ['key' => 'status', 'label' => 'Trạng thái', 'align' => 'center', 'width' => '130px'],
    ];

    export_to_excel('danh_sach_minh_chung_' . date('Ymd_His') . '.xls', 'DANH SÁCH MINH CHỨNG KIỂM ĐỊNH', $columns, $exportData);
    exit;
}

// 2. Handle Actions (Save / Delete)
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (current_role() !== 'admin') {
        $error = 'Tài khoản người dùng chỉ có quyền Xem và Tải minh chứng xuống.';
    } elseif (($_POST['action'] ?? '') === 'delete_evidence') {
        $evidenceId = trim($_POST['id'] ?? '');

        if ($evidenceId === '') {
            $error = 'Mã minh chứng cần xóa không hợp lệ.';
        } else {
            try {
                $pdo->beginTransaction();

                // Lấy thông tin tệp tin & tiêu đề để xóa hoàn toàn
                $stmt = $pdo->prepare('SELECT TenMinhChung, TepTin FROM MinhChung WHERE MaMinhChung = :id');
                $stmt->execute(['id' => $evidenceId]);
                $evidenceData = $stmt->fetch();

                if (!$evidenceData) {
                    throw new RuntimeException('Minh chứng "' . $evidenceId . '" không tồn tại hoặc đã bị xóa trước đó.');
                }

                $filePath = $evidenceData['TepTin'] ?? null;
                $evidenceTitle = $evidenceData['TenMinhChung'] ?? '';

                // 1. Xóa nhật ký tải tệp tin liên quan & liên kết nhiều tiêu chí
                $pdo->prepare('DELETE FROM download_logs WHERE MaMinhChung = :id')->execute(['id' => $evidenceId]);
                $pdo->prepare('DELETE FROM minh_chung_tieu_chi WHERE MaMinhChung = :id')->execute(['id' => $evidenceId]);

                // 2. Xóa hoàn toàn bản ghi minh chứng trong CSDL
                $delStmt = $pdo->prepare('DELETE FROM MinhChung WHERE MaMinhChung = :id');
                $delStmt->execute(['id' => $evidenceId]);

                // 3. Ghi nhật ký hoạt động
                log_activity('xoa', 'minh_chung', 0, $evidenceId . ($evidenceTitle ? ' - ' . $evidenceTitle : ''));

                $pdo->commit();

                // 4. Xóa vĩnh viễn tệp đính kèm vật lý trên ổ đĩa
                if (!empty($filePath)) {
                    $projectRoot = realpath(__DIR__ . '/..');
                    $normalizedRel = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($filePath, '/\\'));
                    $fullPath = $projectRoot ? ($projectRoot . DIRECTORY_SEPARATOR . $normalizedRel) : null;
                    if ($fullPath && file_exists($fullPath) && is_file($fullPath)) {
                        @unlink($fullPath);
                    }
                }

                $success = 'Đã xóa hoàn toàn minh chứng "' . htmlspecialchars($evidenceId) . '" và tệp đính kèm khỏi hệ thống.';
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = 'Không thể xóa minh chứng: ' . $exception->getMessage();
            }
        }
    } elseif (($_POST['action'] ?? '') === 'toggle_status') {
        $evidenceId = trim($_POST['id'] ?? '');
        if ($evidenceId === '') {
            $error = 'Mã minh chứng không hợp lệ.';
        } else {
            try {
                $stmt = $pdo->prepare('SELECT MaMinhChung, TenMinhChung, TrangThai FROM MinhChung WHERE MaMinhChung = :id LIMIT 1');
                $stmt->execute(['id' => $evidenceId]);
                $ev = $stmt->fetch();
                if ($ev) {
                    $currentStatus = (int)($ev['TrangThai'] ?? 1);
                    $newStatus = ($currentStatus === 1) ? 0 : 1;
                    $updateStmt = $pdo->prepare('UPDATE MinhChung SET TrangThai = :status, NgayCapNhat = NOW() WHERE MaMinhChung = :id');
                    $updateStmt->execute(['status' => $newStatus, 'id' => $evidenceId]);

                    $statusText = $newStatus === 1 ? 'Đang hoạt động (Hiển thị cho Người dùng)' : 'Không hoạt động (Ẩn khỏi Người dùng)';
                    log_activity('sua', 'minh_chung', 0, 'Chuyển trạng thái minh chứng ' . $evidenceId . ' sang: ' . $statusText);

                    // Trả về JSON nếu là request AJAX
                    $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') 
                              || (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false);
                    if ($isAjax) {
                        header('Content-Type: application/json; charset=utf-8');
                        echo json_encode([
                            'success' => true,
                            'id' => $evidenceId,
                            'new_status' => $newStatus,
                            'status_text' => $newStatus === 1 ? 'Đang hoạt động' : 'Không hoạt động',
                            'message' => 'Đã cập nhật trạng thái minh chứng ' . $evidenceId . ' thành: ' . ($newStatus === 1 ? 'Đang hoạt động' : 'Không hoạt động')
                        ]);
                        exit;
                    }

                    $success = 'Đã cập nhật trạng thái minh chứng "' . htmlspecialchars($evidenceId) . '" thành: ' . $statusText;
                } else {
                    $error = 'Không tìm thấy thông tin minh chứng.';
                }
            } catch (Throwable $e) {
                $error = 'Lỗi cập nhật trạng thái: ' . $e->getMessage();
            }
        }
    } elseif (($_POST['action'] ?? '') === 'save_evidence') {
        $rawId          = trim($_POST['id'] ?? '');
        $maMinhChung    = trim($_POST['ma_minh_chung'] ?? '');
        $title          = trim($_POST['ten_minh_chung'] ?? '');
        $soHieu         = trim($_POST['so_hieu'] ?? '') ?: null;
        $ngayBanHanh    = trim($_POST['ngay_ban_hanh'] ?? '') ?: null;
        
        $rawTChi        = $_POST['ma_tieu_chi'] ?? [];
        if (!is_array($rawTChi)) {
            $rawTChi = [$rawTChi];
        }
        $maTieuChiList  = array_values(array_unique(array_filter(array_map('trim', $rawTChi))));
        $maTieuChi      = !empty($maTieuChiList) ? $maTieuChiList[0] : null;

        $userId         = $_SESSION['user_id'] ?? (function_exists('current_user') ? current_user()['id'] : 'ND001');
        $status         = isset($_POST['trang_thai']) ? (int) $_POST['trang_thai'] : 1;
        $status         = in_array($status, [0, 1], true) ? $status : 1;

        // Tự động tìm Bộ tiêu chuẩn từ Tiêu chí đầu tiên được chọn
        $maBoTieuChuan = null;
        if ($maTieuChi) {
            $stmtSet = $pdo->prepare('SELECT tc.MaBoTieuChuan FROM TieuChi tchi LEFT JOIN TieuChuan tc ON tc.MaTieuChuan = tchi.MaTieuChuan WHERE tchi.MaTieuChi = :tchi LIMIT 1');
            $stmtSet->execute(['tchi' => $maTieuChi]);
            $maBoTieuChuan = $stmtSet->fetchColumn() ?: null;
        }

        $file = $_FILES['evidence_file'] ?? null;

        if ($maMinhChung === '') {
            $error = 'Vui lòng nhập Mã minh chứng.';
        } elseif ($title === '') {
            $error = 'Vui lòng nhập Tên minh chứng.';
        } elseif (empty($maTieuChiList)) {
            $error = 'Vui lòng chọn ít nhất một Tiêu chuẩn / Tiêu chí cho minh chứng.';
        } elseif ($file && $file['error'] === UPLOAD_ERR_OK && (int) $file['size'] > $maxUploadBytes) {
            $error = 'Dung lượng tệp tin tải lên vượt quá giới hạn tối đa cho phép (' . $maxUploadMb . 'MB).';
        } else {
            try {
                $pdo->beginTransaction();

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
                    if ($maMinhChung !== $rawId) {
                        $chk = $pdo->prepare('SELECT COUNT(*) FROM MinhChung WHERE MaMinhChung = :code');
                        $chk->execute(['code' => $maMinhChung]);
                        if ((int) $chk->fetchColumn() > 0) {
                            throw new RuntimeException('Mã minh chứng "' . $maMinhChung . '" đã tồn tại. Vui lòng nhập mã khác.');
                        }
                        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
                        $upLogs = $pdo->prepare('UPDATE download_logs SET MaMinhChung = :new_code WHERE MaMinhChung = :old_code');
                        $upLogs->execute(['new_code' => $maMinhChung, 'old_code' => $rawId]);
                    }

                    if ($relativePath) {
                        $stmt = $pdo->prepare('UPDATE MinhChung SET MaMinhChung = :new_code, TenMinhChung = :title, SoHieu = :so_hieu, NgayBanHanh = :ngay_ban_hanh, TepTin = :file, TrangThai = :status, MaTieuChi = :tchi, MaBoTieuChuan = :set_id, MaNguoiDung = :user_id, NgayCapNhat = NOW() WHERE MaMinhChung = :old_code');
                        $stmt->execute([
                            'new_code'      => $maMinhChung,
                            'title'         => $title,
                            'so_hieu'       => $soHieu,
                            'ngay_ban_hanh' => $ngayBanHanh,
                            'file'          => $relativePath,
                            'status'        => $status,
                            'tchi'          => $maTieuChi,
                            'set_id'        => $maBoTieuChuan,
                            'user_id'       => $userId,
                            'old_code'      => $rawId,
                        ]);
                    } else {
                        $stmt = $pdo->prepare('UPDATE MinhChung SET MaMinhChung = :new_code, TenMinhChung = :title, SoHieu = :so_hieu, NgayBanHanh = :ngay_ban_hanh, TrangThai = :status, MaTieuChi = :tchi, MaBoTieuChuan = :set_id, MaNguoiDung = :user_id, NgayCapNhat = NOW() WHERE MaMinhChung = :old_code');
                        $stmt->execute([
                            'new_code'      => $maMinhChung,
                            'title'         => $title,
                            'so_hieu'       => $soHieu,
                            'ngay_ban_hanh' => $ngayBanHanh,
                            'status'        => $status,
                            'tchi'          => $maTieuChi,
                            'set_id'        => $maBoTieuChuan,
                            'user_id'       => $userId,
                            'old_code'      => $rawId,
                        ]);
                    }
                    if ($maMinhChung !== $rawId) {
                        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
                    }

                    // Đồng bộ liên kết nhiều Tiêu chí trong minh_chung_tieu_chi
                    $delMCTC = $pdo->prepare('DELETE FROM minh_chung_tieu_chi WHERE MaMinhChung = :mc');
                    $delMCTC->execute(['mc' => $rawId]);
                    if ($rawId !== $maMinhChung) {
                        $delMCTC->execute(['mc' => $maMinhChung]);
                    }
                    $insMCTC = $pdo->prepare('INSERT IGNORE INTO minh_chung_tieu_chi (MaMinhChung, MaTieuChi) VALUES (:mc, :tc)');
                    foreach ($maTieuChiList as $tcCode) {
                        $insMCTC->execute(['mc' => $maMinhChung, 'tc' => $tcCode]);
                    }

                    log_activity('cap_nhat', 'minh_chung', 0, $maMinhChung . ' - ' . $title);
                    $success = 'Cập nhật minh chứng thành công.';
                } else {
                    // Insert
                    $chk = $pdo->prepare('SELECT COUNT(*) FROM MinhChung WHERE MaMinhChung = :code');
                    $chk->execute(['code' => $maMinhChung]);
                    if ((int) $chk->fetchColumn() > 0) {
                        throw new RuntimeException('Mã minh chứng "' . $maMinhChung . '" đã tồn tại. Vui lòng nhập mã khác.');
                    }

                    $stmt = $pdo->prepare('INSERT INTO MinhChung (MaMinhChung, TenMinhChung, SoHieu, NgayBanHanh, TepTin, TrangThai, MaTieuChi, MaBoTieuChuan, MaNguoiDung, NgayCapNhat) VALUES (:code, :title, :so_hieu, :ngay_ban_hanh, :file, :status, :tchi, :set_id, :user_id, NOW())');
                    $stmt->execute([
                        'code'          => $maMinhChung,
                        'title'         => $title,
                        'so_hieu'       => $soHieu,
                        'ngay_ban_hanh' => $ngayBanHanh,
                        'file'          => $relativePath,
                        'status'        => $status,
                        'tchi'          => $maTieuChi,
                        'set_id'        => $maBoTieuChuan,
                        'user_id'       => $userId,
                    ]);

                    // Thêm liên kết nhiều Tiêu chí vào minh_chung_tieu_chi
                    $insMCTC = $pdo->prepare('INSERT IGNORE INTO minh_chung_tieu_chi (MaMinhChung, MaTieuChi) VALUES (:mc, :tc)');
                    foreach ($maTieuChiList as $tcCode) {
                        $insMCTC->execute(['mc' => $maMinhChung, 'tc' => $tcCode]);
                    }

                    log_activity('them_moi', 'minh_chung', 0, $maMinhChung . ' - ' . $title);
                    $success = 'Thêm mới minh chứng thành công.';
                }

                $pdo->commit();
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = $exception->getMessage();
            }
        }
    }
}

if ($success) {
    unset($_GET['create'], $_GET['edit']);
}

// Reload data
require __DIR__ . '/../includes/data.php';

$isCreatingEvidence = isset($_GET['create']);
$editId = $isCreatingEvidence ? '' : trim($_GET['edit'] ?? '');
$viewId = trim($_GET['view'] ?? '');

$editingEvidence = null;
$editingCriteriaIds = [];
if ($editId !== '') {
    $stmt = $pdo->prepare('
        SELECT m.*, u.HoTen AS user_name, u.TenDangNhap AS username, u.VaiTro AS user_role
        FROM MinhChung m
        LEFT JOIN NguoiDung u ON u.MaNguoiDung = m.MaNguoiDung
        WHERE m.MaMinhChung = :id LIMIT 1
    ');
    $stmt->execute(['id' => $editId]);
    $editingEvidence = $stmt->fetch();

    if ($editingEvidence) {
        $stmtMCTC = $pdo->prepare('SELECT MaTieuChi FROM minh_chung_tieu_chi WHERE MaMinhChung = :id');
        $stmtMCTC->execute(['id' => $editId]);
        $editingCriteriaIds = $stmtMCTC->fetchAll(PDO::FETCH_COLUMN);
        if (empty($editingCriteriaIds) && !empty($editingEvidence['MaTieuChi'])) {
            $editingCriteriaIds = [$editingEvidence['MaTieuChi']];
        }
    }
}

$viewingEvidence = null;
$viewingCriteriaList = [];
if ($viewId !== '') {
    $stmt = $pdo->prepare('
        SELECT m.*, u.HoTen AS user_name, u.TenDangNhap AS username, u.VaiTro AS user_role, tc.TenTieuChuan AS standard_name, tchi.TenTieuChi AS criterion_name, b.TenBoTieuChuan AS set_name
        FROM MinhChung m
        LEFT JOIN TieuChi tchi ON tchi.MaTieuChi = m.MaTieuChi
        LEFT JOIN TieuChuan tc ON tc.MaTieuChuan = tchi.MaTieuChuan
        LEFT JOIN BoTieuChuan b ON b.MaBoTieuChuan = tc.MaBoTieuChuan
        LEFT JOIN NguoiDung u ON u.MaNguoiDung = m.MaNguoiDung
        WHERE m.MaMinhChung = :id LIMIT 1
    ');
    $stmt->execute(['id' => $viewId]);
    $viewingEvidence = $stmt->fetch();

    if ($viewingEvidence) {
        $stmtV = $pdo->prepare('
            SELECT mctc.MaTieuChi AS id, c.TenTieuChi AS name, tc.MaTieuChuan AS standard_id, tc.TenTieuChuan AS standard_name, tc.MaBoTieuChuan AS set_id, COALESCE(b.TenBoTieuChuan, "") AS set_name
            FROM minh_chung_tieu_chi mctc
            JOIN TieuChi c ON c.MaTieuChi = mctc.MaTieuChi
            LEFT JOIN TieuChuan tc ON tc.MaTieuChuan = c.MaTieuChuan
            LEFT JOIN BoTieuChuan b ON b.MaBoTieuChuan = tc.MaBoTieuChuan
            WHERE mctc.MaMinhChung = :id
            ORDER BY c.ThuTu ASC, c.MaTieuChi ASC
        ');
        $stmtV->execute(['id' => $viewId]);
        $viewingCriteriaList = $stmtV->fetchAll();
        if (empty($viewingCriteriaList) && !empty($viewingEvidence['MaTieuChi'])) {
            $viewingCriteriaList[] = [
                'id' => $viewingEvidence['MaTieuChi'],
                'name' => $viewingEvidence['criterion_name'],
                'standard_id' => $viewingEvidence['MaTieuChuan'] ?? '',
                'standard_name' => $viewingEvidence['standard_name'] ?? '',
                'set_id' => $viewingEvidence['MaBoTieuChuan'] ?? '',
                'set_name' => $viewingEvidence['set_name'] ?? '',
            ];
        }
    }
}

$filterKeyword    = trim($_GET['q'] ?? $_GET['search'] ?? '');
$filterStandardSet= trim($_GET['standard_set'] ?? '');
$filterStandard   = trim($_GET['standard'] ?? '');
$filterCriterion  = trim($_GET['criterion'] ?? '');
$filterStatus     = trim($_GET['status'] ?? '');
$filterHasFile    = trim($_GET['has_file'] ?? '');

$rawTotal = count($evidences);
$countActive = count(array_filter($evidences, fn($ev) => (int)($ev['status_raw'] ?? 1) === 1));
$countInactive = count(array_filter($evidences, fn($ev) => (int)($ev['status_raw'] ?? 1) === 0));
$countHasFile = count(array_filter($evidences, fn($ev) => !empty($ev['file_path'])));

// Lọc danh sách minh chứng
$filteredEvidences = array_filter($evidences, function ($item) use ($filterKeyword, $filterStandardSet, $filterStandard, $filterCriterion, $filterStatus, $filterHasFile) {
    if ($filterKeyword !== '') {
        $allCritNames = '';
        if (!empty($item['criteria'])) {
            foreach ($item['criteria'] as $c) {
                $allCritNames .= ' ' . ($c['id'] ?? '') . ' ' . ($c['name'] ?? '') . ' ' . ($c['standard_id'] ?? '') . ' ' . ($c['standard_name'] ?? '') . ' ' . ($c['set_name'] ?? '');
            }
        }
        $haystack = implode(' ', [
            $item['code'] ?? '',
            $item['name'] ?? '',
            $item['so_hieu'] ?? '',
            $item['criterion_name'] ?? '',
            $item['standard_name'] ?? '',
            $item['set_name'] ?? '',
            $allCritNames,
            $item['issue_date_formatted'] ?? '',
            $item['issue_date'] ?? '',
            $item['file_path'] ?? '',
        ]);
        if (!search_contains($haystack, $filterKeyword)) {
            return false;
        }
    }

    if ($filterStandardSet !== '') {
        $hasSet = in_array($filterStandardSet, $item['set_ids'] ?? [], true) || ($item['ma_bo_tieu_chuan'] ?? '') === $filterStandardSet;
        if (!$hasSet) return false;
    }

    if ($filterStandard !== '') {
        $hasStd = in_array($filterStandard, $item['standard_ids'] ?? [], true) || ($item['ma_tieu_chuan'] ?? '') === $filterStandard;
        if (!$hasStd) return false;
    }

    if ($filterCriterion !== '') {
        $hasCrit = in_array($filterCriterion, $item['criteria_ids'] ?? [], true) || ($item['ma_tieu_chi'] ?? '') === $filterCriterion;
        if (!$hasCrit) return false;
    }

    if ($filterStatus !== '' && (string)($item['status_raw'] ?? 1) !== $filterStatus) {
        return false;
    }

    if ($filterHasFile === 'yes' && empty($item['file_path'])) {
        return false;
    }

    if ($filterHasFile === 'no' && !empty($item['file_path'])) {
        return false;
    }

    return true;
});
$filteredEvidences = array_values($filteredEvidences);

$pageTitle = page_title('Cập nhật Minh chứng');
$heading   = 'Cập nhật Minh chứng';
include __DIR__ . '/../includes/header.php';
?>

<style>
.evidence-card {
    background: #ffffff;
    border: 1px solid rgba(226, 232, 240, 0.8);
    border-radius: 16px;
    box-shadow: 0 4px 20px rgba(18, 48, 95, 0.05);
    padding: 24px;
}
html[data-theme="dark"] .evidence-card {
    background: #132744;
    border-color: rgba(255, 255, 255, 0.08);
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.25);
}
.stat-card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    border: 1px solid rgba(226, 232, 240, 0.8) !important;
}
.stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 25px rgba(18, 48, 95, 0.08) !important;
}
.fs-8 {
    font-size: 0.75rem !important;
}
.table thead th {
    white-space: nowrap !important;
    background-color: #f8fafc !important;
    color: #475569;
    font-weight: 700;
    font-size: 0.78rem;
    text-transform: uppercase;
    letter-spacing: 0.02em;
    padding-top: 10px;
    padding-bottom: 10px;
    padding-left: 8px;
    padding-right: 8px;
}
.table tbody td {
    padding: 8px;
}
.table tbody tr {
    transition: background-color 0.15s ease;
}
.table tbody tr:hover {
    background-color: #f1f5f9 !important;
}
.evidence-title-cell {
    word-break: break-word;
    line-height: 1.4;
    font-size: 0.82rem;
}
.btn-status-toggle {
    cursor: pointer;
    transition: all 0.2s ease-in-out;
    border: none;
    background: transparent;
    padding: 0;
    display: inline-flex;
    align-items: center;
    text-decoration: none;
}
.btn-status-toggle .status-badge {
    transition: transform 0.2s ease, box-shadow 0.2s ease, opacity 0.2s ease;
    user-select: none;
}
.btn-status-toggle:hover .status-badge {
    transform: scale(1.05);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.12);
}
.btn-status-toggle:active .status-badge {
    transform: scale(0.96);
}
.evidence-criteria-cell {
    max-width: 220px;
}
.evidence-code-link {
    display: inline-flex;
    align-items: center;
    text-decoration: none;
    transition: all 0.2s ease;
    cursor: pointer;
}
.evidence-code-link:hover {
    background-color: #2563eb !important;
    color: #ffffff !important;
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(37, 99, 235, 0.3);
}
.criteria-badge-std {
    background-color: #eff6ff;
    color: #1d4ed8;
    border: 1px solid #bfdbfe;
    font-size: 0.68rem;
    padding: 2px 5px;
    border-radius: 4px;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    text-decoration: none;
    transition: all 0.18s ease;
    cursor: pointer;
}
.criteria-badge-std:hover {
    background-color: #2563eb;
    color: #ffffff !important;
    border-color: #1d4ed8;
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(37, 99, 235, 0.25);
}
.criteria-badge-item {
    background-color: #f0fdf4;
    color: #15803d;
    border: 1px solid #bbf7d0;
    font-size: 0.68rem;
    padding: 2px 5px;
    border-radius: 4px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    white-space: nowrap;
    text-decoration: none;
    transition: all 0.18s ease;
    cursor: pointer;
}
.criteria-badge-item:hover {
    background-color: #16a34a;
    color: #ffffff !important;
    border-color: #15803d;
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(22, 163, 74, 0.25);
}
.tooltip .tooltip-inner {
    max-width: 320px;
    padding: 8px 12px;
    font-size: 0.78rem;
    line-height: 1.45;
    border-radius: 8px;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.2);
}
/* Search Highlight & Location Indicator */
.search-matched-row {
    background-color: #fefce8 !important;
    border-left: 4px solid #f59e0b !important;
    position: relative;
    transition: all 0.3s ease;
    animation: rowHighlightPulse 1s ease-out;
}
@keyframes rowHighlightPulse {
    0% { background-color: #fef08a !important; }
    60% { background-color: #fef9c3 !important; }
    100% { background-color: #fefce8 !important; }
}
.search-matched-row:hover {
    background-color: #fef08a !important;
}
.search-matched-row.current-focus-match {
    background-color: #fde047 !important;
    box-shadow: 0 0 0 3px rgba(217, 119, 6, 0.45) !important;
    z-index: 5;
    animation: searchPulseGlow 1.5s infinite ease-in-out;
}
@keyframes searchPulseGlow {
    0% { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.6); }
    70% { box-shadow: 0 0 0 8px rgba(245, 158, 11, 0); }
    100% { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0); }
}
.search-matched-text {
    background: #fef08a;
    color: #854d0e;
    font-weight: 700;
    padding: 1px 4px;
    border-radius: 4px;
    border-bottom: 2px solid #f59e0b;
    display: inline;
}
html[data-theme="dark"] .table thead th {
    background-color: #0d1e36 !important;
    color: #94a3b8;
}
html[data-theme="dark"] .table tbody tr:hover {
    background-color: #1e3a5f !important;
}
html[data-theme="dark"] .search-matched-row {
    background-color: #2b1d0c !important;
    border-left: 4px solid #f59e0b !important;
}
html[data-theme="dark"] .search-matched-row.current-focus-match {
    background-color: #451a03 !important;
    box-shadow: 0 0 0 3px rgba(250, 204, 21, 0.5) !important;
}
html[data-theme="dark"] .search-matched-text {
    background: #78350f;
    color: #fef08a;
    border-bottom-color: #fde047;
}
html[data-theme="dark"] .criteria-badge-std {
    background-color: #1e3a8a !important;
    color: #93c5fd !important;
    border-color: #2563eb !important;
}
html[data-theme="dark"] .criteria-badge-item {
    background-color: #064e3b !important;
    color: #86efac !important;
    border-color: #059669 !important;
}
</style>

<?php if (($editingEvidence || $isCreatingEvidence) && !$success && !$error): ?><script>document.body.dataset.autoOpenModal = 'evidenceFormModal';</script><?php endif; ?>
<?php if ($viewingEvidence): ?><script>document.body.dataset.autoOpenModal = 'evidenceViewModal';</script><?php endif; ?>

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

<!-- ======================= 4 THẺ THỐNG KÊ TỔNG QUAN (STAT CARDS) ======================= -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm rounded-4 p-3 h-100 bg-white stat-card">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-secondary small fw-semibold d-block mb-1">Tổng số minh chứng</span>
                    <h3 class="fw-bold mb-0 text-dark"><?= $rawTotal ?></h3>
                </div>
                <div class="p-3 rounded-4 bg-primary-subtle text-primary">
                    <i class="bi bi-folder2-open fs-3"></i>
                </div>
            </div>
            <div class="mt-2 small text-muted">
                <i class="bi bi-database me-1 text-primary"></i>Toàn bộ hồ sơ trong CSDL
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm rounded-4 p-3 h-100 bg-white stat-card">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-secondary small fw-semibold d-block mb-1">Đang hoạt động</span>
                    <h3 class="fw-bold mb-0 text-success" id="statCardActive"><?= $countActive ?></h3>
                </div>
                <div class="p-3 rounded-4 bg-success-subtle text-success">
                    <i class="bi bi-check2-circle fs-3"></i>
                </div>
            </div>
            <div class="mt-2 small text-muted">
                <i class="bi bi-eye text-success me-1"></i>Cho phép người dùng xem
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm rounded-4 p-3 h-100 bg-white stat-card">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-secondary small fw-semibold d-block mb-1">Không hoạt động (Ẩn)</span>
                    <h3 class="fw-bold mb-0 text-danger" id="statCardInactive"><?= $countInactive ?></h3>
                </div>
                <div class="p-3 rounded-4 bg-danger-subtle text-danger">
                    <i class="bi bi-eye-slash fs-3"></i>
                </div>
            </div>
            <div class="mt-2 small text-muted">
                <i class="bi bi-lock text-danger me-1"></i>Chỉ Quản trị viên theo dõi
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm rounded-4 p-3 h-100 bg-white stat-card">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-secondary small fw-semibold d-block mb-1">Đã có tệp đính kèm</span>
                    <h3 class="fw-bold mb-0" style="color: #7c3aed;"><?= $countHasFile ?></h3>
                </div>
                <div class="p-3 rounded-4" style="background: #ede9fe; color: #7c3aed;">
                    <i class="bi bi-file-earmark-check fs-3"></i>
                </div>
            </div>
            <div class="mt-2 small text-muted">
                <i class="bi bi-paperclip me-1" style="color: #7c3aed;"></i>Tỷ lệ: <?= $rawTotal > 0 ? round(($countHasFile / $rawTotal) * 100) : 0 ?>% văn bản
            </div>
        </div>
    </div>
</div>

<div class="row g-4 evidences-layout">
    <div class="col-12">
        <div class="evidence-card">
            <!-- Top Header & Actions -->
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
                <div>
                    <div class="d-flex align-items-center gap-2">
                        <div class="p-2 rounded-3 bg-primary-subtle text-primary">
                            <i class="bi bi-folder-check fs-4"></i>
                        </div>
                        <div>
                            <h2 class="h5 mb-0 fw-bold text-dark">Cập nhật Minh chứng</h2>
                            <span class="text-muted small">Cập nhật toàn bộ hồ sơ, văn bản minh chứng phục vụ kiểm định</span>
                        </div>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <?php if (current_role() === 'admin'): ?>
                        <button class="btn btn-primary d-inline-flex align-items-center gap-2 px-3 py-2 rounded-3 shadow-sm" type="button" data-bs-toggle="modal" data-bs-target="#evidenceFormModal" id="btnOpenAddModal">
                            <i class="bi bi-plus-circle-fill"></i> Thêm mới Minh chứng
                        </button>
                    <?php endif; ?>
                    <a class="btn btn-outline-secondary d-inline-flex align-items-center gap-2 px-3 py-2 rounded-3" href="<?= base_url('admin/evidences.php?export=excel' . ($filterKeyword ? '&search=' . urlencode($filterKeyword) : '')) ?>">
                        <i class="bi bi-file-earmark-excel-fill text-success"></i> Xuất Excel
                    </a>
                </div>
            </div>

            <!-- ======================= BỘ LỌC TẬP TRUNG THÔNG MINH ======================= -->
            <div class="card border-0 shadow-sm rounded-4 mb-4 filter-panel-card" style="background: #f8fafc; border: 1px solid rgba(226, 232, 240, 0.9) !important;">
                <div class="card-body p-3 p-md-4">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 pb-2 border-bottom">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge rounded-pill bg-primary-subtle text-primary px-3 py-2 fs-7 fw-bold">
                                <i class="bi bi-funnel-fill me-1"></i> Bộ lọc &amp; Tìm kiếm tập trung
                            </span>
                            <span class="text-secondary small d-none d-md-inline">
                                Tìm thấy <strong><?= count($filteredEvidences) ?></strong> / <?= $rawTotal ?> minh chứng phù hợp
                            </span>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <a href="<?= base_url('admin/evidences.php') ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-3" title="Đặt lại tất cả bộ lọc">
                                <i class="bi bi-arrow-counterclockwise me-1"></i> Đặt lại
                            </a>
                        </div>
                    </div>

                    <form method="get" action="" class="row g-3" id="evidenceFilterForm">
                        <!-- Cách 1: Ô tìm kiếm theo Mã minh chứng & Tên minh chứng -->
                        <div class="col-12 col-xl-4">
                            <label for="filterKeyword" class="form-label fw-bold small text-primary mb-1 d-flex align-items-center gap-1">
                                <i class="bi bi-search"></i> Cách 1: Tìm kiếm theo Mã minh chứng &amp; Tên minh chứng
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0 text-muted ps-3"><i class="bi bi-search"></i></span>
                                <input type="text" class="form-control bg-white border-start-0 border-end-0 ps-0" id="filterKeyword" name="q" placeholder="Nhập mã minh chứng (MC001...) hoặc tên minh chứng..." value="<?= htmlspecialchars($filterKeyword) ?>" autocomplete="off">
                                <?php if ($filterKeyword !== ''): ?>
                                    <a href="<?= base_url('admin/evidences.php') ?>" class="btn btn-white border border-start-0 text-muted" title="Xóa từ khóa"><i class="bi bi-x-circle-fill"></i></a>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Cách 2: Lọc theo cột Tiêu chuẩn & Tiêu chí -->
                        <div class="col-12 col-xl-8">
                            <label class="form-label fw-bold small text-primary mb-1 d-flex align-items-center gap-1">
                                <i class="bi bi-diagram-3"></i> Cách 2: Lọc theo cột Tiêu chuẩn &amp; Tiêu chí
                            </label>
                            <div class="row g-2">
                                <div class="col-12 col-sm-6 col-md-3">
                                    <select class="form-select form-select-sm bg-white" name="standard_set" id="filterStandardSet" onchange="this.form.submit()">
                                        <option value="">-- Tất cả Bộ TC --</option>
                                        <?php foreach ($standardSets as $bs): ?>
                                            <option value="<?= htmlspecialchars($bs['id']) ?>" <?= $filterStandardSet === $bs['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($bs['id'] . ' - ' . $bs['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-12 col-sm-6 col-md-3">
                                    <select class="form-select form-select-sm bg-white" name="standard" id="filterStandard" onchange="this.form.submit()">
                                        <option value="">-- Tất cả Tiêu chuẩn --</option>
                                        <?php foreach ($standards as $std): ?>
                                            <?php if ($filterStandardSet !== '' && ($std['set_id'] ?? '') !== $filterStandardSet) continue; ?>
                                            <option value="<?= htmlspecialchars($std['id']) ?>" <?= $filterStandard === $std['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($std['code'] . ' - ' . $std['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-12 col-sm-6 col-md-3">
                                    <select class="form-select form-select-sm bg-white" name="criterion" id="filterCriterion" onchange="this.form.submit()">
                                        <option value="">-- Tất cả Tiêu chí --</option>
                                        <?php foreach ($criteria as $cr): ?>
                                            <?php 
                                            if ($filterStandard !== '' && ($cr['standard_id'] ?? '') !== $filterStandard) continue;
                                            if ($filterStandardSet !== '' && ($cr['set_id'] ?? '') !== $filterStandardSet) continue;
                                            ?>
                                            <option value="<?= htmlspecialchars($cr['id']) ?>" <?= $filterCriterion === $cr['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($cr['code'] . ' - ' . $cr['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-12 col-sm-6 col-md-3">
                                    <select class="form-select form-select-sm bg-white" name="status" onchange="this.form.submit()">
                                        <option value="">-- Tất cả Trạng thái --</option>
                                        <option value="1" <?= $filterStatus === '1' ? 'selected' : '' ?>>Đang hoạt động</option>
                                        <option value="0" <?= $filterStatus === '0' ? 'selected' : '' ?>>Không hoạt động</option>
                                    </select>
                                </div>
                            </div>
                            <div class="d-flex justify-content-end align-items-center mt-2">
                                <button class="btn btn-sm btn-primary px-4 rounded-pill shadow-xs" type="submit"><i class="bi bi-search me-1"></i> Áp dụng</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- ======================= THANH ĐIỀU HƯỚNG VỊ TRÍ KẾT QUẢ TÌM KIẾM ======================= -->
            <?php if ($filterKeyword !== ''): ?>
                <div class="alert alert-warning border-0 shadow-sm rounded-4 py-2.5 px-3 mb-3 d-flex flex-wrap align-items-center justify-content-between gap-2" id="searchResultsAlert" style="background: #fffbeb; border-left: 4px solid #f59e0b !important;">
                    <div class="d-flex align-items-center gap-2">
                        <span class="p-1.5 rounded-3 bg-warning text-dark"><i class="bi bi-search fs-6"></i></span>
                        <div>
                            <span class="fw-bold text-dark fs-7">Kết quả tìm kiếm cho từ khóa:</span>
                            <span class="badge bg-warning text-dark font-monospace px-2 py-1 fs-7">"<?= htmlspecialchars($filterKeyword) ?>"</span>
                            <span class="text-secondary small ms-1" id="searchMatchCountText">(Tìm thấy <strong><?= count($filteredEvidences) ?></strong> minh chứng phù hợp)</span>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <?php if (count($filteredEvidences) > 1): ?>
                            <div class="d-flex align-items-center gap-1 bg-white px-2 py-1 rounded-pill border shadow-xs">
                                <span class="text-muted small fs-8 me-1"><i class="bi bi-geo-alt-fill text-warning me-0.5"></i>Vị trí:</span>
                                <span class="badge bg-warning-subtle text-warning border border-warning-subtle font-monospace fs-8" id="matchCurrentIndexBadge">1 / <?= count($filteredEvidences) ?></span>
                                <button type="button" class="btn btn-sm btn-light border-0 p-0 px-1 text-secondary" id="btnPrevMatch" title="Đến kết quả trước (Lên trên)"><i class="bi bi-chevron-up"></i></button>
                                <button type="button" class="btn btn-sm btn-light border-0 p-0 px-1 text-secondary" id="btnNextMatch" title="Đến kết quả tiếp theo (Xuống dưới)"><i class="bi bi-chevron-down"></i></button>
                            </div>
                        <?php endif; ?>
                        <a href="<?= base_url('admin/evidences.php') ?>" class="btn btn-sm btn-outline-danger rounded-pill px-3" title="Xóa từ khóa tìm kiếm">
                            <i class="bi bi-x-circle me-1"></i>Xóa tìm kiếm
                        </a>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Evidence Table -->
            <div class="table-responsive border rounded-3 overflow-hidden shadow-xs">
                <table class="table align-middle mb-0 table-hover" data-page-size="10" style="width: 100%;">
                    <thead class="table-light">
                        <tr>
                            <th class="text-center text-nowrap" style="width: 45px;">STT</th>
                            <th class="text-center text-nowrap" style="width: 100px;">Mã MC</th>
                            <th style="min-width: 180px;">Tên minh chứng</th>
                            <th class="text-center text-nowrap" style="width: 110px;">Số hiệu</th>
                            <th style="width: 175px; min-width: 150px;">Tiêu chuẩn &amp; Tiêu chí</th>
                            <th class="text-center text-nowrap" style="width: 100px;">Ngày BH</th>
                            <th class="text-nowrap" style="width: 130px;">Cập nhật</th>
                            <th class="text-center text-nowrap" style="width: 85px;">File</th>
                            <th class="text-center text-nowrap" style="width: 110px;">Trạng thái</th>
                            <th class="text-end text-nowrap" style="width: 95px;">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody id="evidencesTableBody">
                    <?php 
                    $stt = 1;
                    foreach ($filteredEvidences as $item): 
                        $formattedDate = !empty($item['issue_date_formatted']) ? $item['issue_date_formatted'] : (!empty($item['issue_date']) ? date('d/m/Y', strtotime($item['issue_date'])) : '-');
                        $formattedUpdated = !empty($item['updated']) ? $item['updated'] : '-';
                        $updaterName = !empty($item['user_name']) ? $item['user_name'] : 'Quản trị viên';
                        $updaterRole = ($item['user_role'] ?? 'admin') === 'admin' ? 'Quản trị viên' : 'Người dùng';
                    ?>
                        <tr class="<?= $filterKeyword !== '' ? 'search-matched-row' : '' ?>" id="evidence-row-<?= htmlspecialchars($item['code']) ?>" data-evidence-code="<?= htmlspecialchars($item['code']) ?>">
                            <td class="text-center text-secondary fw-semibold fs-8"><?= $stt++ ?></td>
                            <td class="text-center text-nowrap">
                                <a href="<?= base_url('admin/standard_sets.php?evidence=' . urlencode($item['code']) . '#evidence-row-' . urlencode($item['code'])) ?>" 
                                   class="badge bg-primary-subtle text-primary border border-primary-subtle px-1.5 py-1 fs-8 fw-bold font-monospace evidence-code-link" 
                                   data-bs-toggle="tooltip" 
                                   data-bs-html="true"
                                   title="<div class='text-start p-1'><strong>Mã: <?= htmlspecialchars($item['code']) ?></strong><br><small class='text-light-50'>Bấm để xem vị trí minh chứng này trong Cây Bộ tiêu chuẩn</small></div>">
                                    <?= highlight_search_text($item['code'], $filterKeyword) ?>
                                    <i class="bi bi-box-arrow-up-right ms-1" style="font-size: 0.62rem;"></i>
                                </a>
                            </td>
                            <td class="evidence-title-cell">
                                <div class="fw-semibold text-dark" title="<?= htmlspecialchars($item['name']) ?>">
                                    <?= highlight_search_text($item['name'], $filterKeyword) ?>
                                </div>
                            </td>
                            <td class="text-center text-nowrap">
                                <?php if (!empty($item['so_hieu'])): ?>
                                    <span class="badge bg-light text-dark border font-monospace px-1.5 py-1 fs-8" title="Số hiệu: <?= htmlspecialchars($item['so_hieu']) ?>">
                                        <?= highlight_search_text($item['so_hieu'], $filterKeyword) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted fs-8">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($item['criteria'])): 
                                    $groupedByStandard = [];
                                    foreach ($item['criteria'] as $c) {
                                        $stdKey = $c['standard_id'] ?: 'Khác';
                                        $groupedByStandard[$stdKey]['standard_name'] = $c['standard_name'] ?? '';
                                        $groupedByStandard[$stdKey]['standard_id'] = $c['standard_id'] ?? '';
                                        $groupedByStandard[$stdKey]['set_name'] = $c['set_name'] ?? '';
                                        $groupedByStandard[$stdKey]['set_id'] = $c['set_id'] ?? '';
                                        $groupedByStandard[$stdKey]['criteria'][] = $c;
                                    }
                                ?>
                                    <div class="evidence-criteria-cell d-flex flex-column gap-1">
                                        <?php foreach ($groupedByStandard as $sKey => $sGroup): 
                                            $stdTooltip = "<div class='text-start p-1'>"
                                                . "<div class='text-warning small fw-bold'><i class='bi bi-collection-fill me-1'></i>" . htmlspecialchars($sGroup['set_name'] ?: ($sGroup['set_id'] ?: 'Bộ tiêu chuẩn')) . "</div>"
                                                . "<div class='mt-1 text-white fw-semibold'><i class='bi bi-folder2-open text-primary me-1'></i>Tiêu chuẩn " . htmlspecialchars($sGroup['standard_id']) . ": " . htmlspecialchars($sGroup['standard_name']) . "</div>"
                                                . "<div class='mt-1 small border-top border-secondary pt-1 text-light-50'><i class='bi bi-cursor-fill me-1 text-warning'></i>Nhấp để chuyển đến Tiêu chuẩn này</div>"
                                                . "</div>";
                                        ?>
                                            <div class="d-flex flex-wrap align-items-center gap-1">
                                                <a href="<?= base_url('admin/standard_sets.php?standard=' . urlencode($sGroup['standard_id']) . '#standard-row-' . urlencode($sGroup['standard_id'])) ?>" 
                                                   class="criteria-badge-std" 
                                                   data-bs-toggle="tooltip" 
                                                   data-bs-html="true" 
                                                   title="<?= htmlspecialchars($stdTooltip, ENT_QUOTES) ?>">
                                                    <i class="bi bi-folder2 me-1"></i><?= highlight_search_text($sGroup['standard_id'], $filterKeyword) ?>
                                                </a>
                                                <?php foreach ($sGroup['criteria'] as $cr): 
                                                    $critTooltip = "<div class='text-start p-1'>"
                                                        . "<div class='small text-light-50'><i class='bi bi-diagram-3-fill text-info me-1'></i>Vị trí cây phân cấp:</div>"
                                                        . "<div class='small text-white'><i class='bi bi-collection me-1 text-info'></i>" . htmlspecialchars($sGroup['set_id'] ?: 'Bộ TC') . " &gt; <i class='bi bi-folder me-1 text-primary'></i>" . htmlspecialchars($sGroup['standard_id']) . "</div>"
                                                        . "<div class='mt-1 fw-bold text-success'><i class='bi bi-check2-circle me-1'></i>Tiêu chí " . htmlspecialchars($cr['id']) . ": " . htmlspecialchars($cr['name']) . "</div>"
                                                        . "<div class='mt-1 small border-top border-secondary pt-1 text-warning'><i class='bi bi-box-arrow-up-right me-1'></i>Nhấp để chuyển đến vị trí minh chứng này</div>"
                                                        . "</div>";
                                                ?>
                                                    <a href="<?= base_url('admin/standard_sets.php?criterion=' . urlencode($cr['id']) . '&evidence=' . urlencode($item['code']) . '#evidence-row-' . urlencode($item['code'])) ?>" 
                                                       class="criteria-badge-item" 
                                                       data-bs-toggle="tooltip" 
                                                       data-bs-html="true" 
                                                       title="<?= htmlspecialchars($critTooltip, ENT_QUOTES) ?>">
                                                        <i class="bi bi-check2 me-0.5"></i><?= highlight_search_text($cr['id'], $filterKeyword) ?>
                                                    </a>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php elseif (!empty($item['ma_tieu_chi'])): 
                                    $singleCritTooltip = "<div class='text-start p-1'>"
                                        . "<div class='small text-light-50'><i class='bi bi-diagram-3-fill text-info me-1'></i>Vị trí cây phân cấp:</div>"
                                        . "<div class='small text-white'><i class='bi bi-folder me-1 text-primary'></i>" . htmlspecialchars($item['ma_tieu_chuan']) . ": " . htmlspecialchars($item['standard_name']) . "</div>"
                                        . "<div class='mt-1 fw-bold text-success'><i class='bi bi-check2-circle me-1'></i>Tiêu chí " . htmlspecialchars($item['ma_tieu_chi']) . ": " . htmlspecialchars($item['criterion_name']) . "</div>"
                                        . "<div class='mt-1 small border-top border-secondary pt-1 text-warning'><i class='bi bi-box-arrow-up-right me-1'></i>Nhấp để chuyển đến vị trí minh chứng này</div>"
                                        . "</div>";
                                ?>
                                    <div class="evidence-criteria-cell d-flex flex-wrap align-items-center gap-1">
                                        <a href="<?= base_url('admin/standard_sets.php?standard=' . urlencode($item['ma_tieu_chuan']) . '#standard-row-' . urlencode($item['ma_tieu_chuan'])) ?>" 
                                           class="criteria-badge-std" 
                                           data-bs-toggle="tooltip" 
                                           data-bs-html="true" 
                                           title="<div class='text-start p-1'><strong>Tiêu chuẩn <?= htmlspecialchars($item['ma_tieu_chuan']) ?>:</strong> <?= htmlspecialchars($item['standard_name']) ?><br><small class='text-warning'>Bấm để mở Tiêu chuẩn</small></div>">
                                            <i class="bi bi-folder2 me-1"></i><?= highlight_search_text($item['ma_tieu_chuan'], $filterKeyword) ?>
                                        </a>
                                        <a href="<?= base_url('admin/standard_sets.php?criterion=' . urlencode($item['ma_tieu_chi']) . '&evidence=' . urlencode($item['code']) . '#evidence-row-' . urlencode($item['code'])) ?>" 
                                           class="criteria-badge-item" 
                                           data-bs-toggle="tooltip" 
                                           data-bs-html="true" 
                                           title="<?= htmlspecialchars($singleCritTooltip, ENT_QUOTES) ?>">
                                            <i class="bi bi-check2 me-0.5"></i><?= highlight_search_text($item['ma_tieu_chi'], $filterKeyword) ?>
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <span class="badge bg-light text-muted border fs-8">Chưa phân loại</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center text-nowrap">
                                <span class="badge bg-light text-secondary border px-1.5 py-1 fs-8">
                                    <i class="bi bi-calendar3 me-1 text-primary"></i><?= htmlspecialchars($formattedDate) ?>
                                </span>
                            </td>
                            <td class="text-nowrap">
                                <div class="d-flex flex-column" style="line-height: 1.25;">
                                    <span class="fw-semibold text-dark fs-8 d-flex align-items-center gap-1" title="Người cập nhật: <?= htmlspecialchars($updaterName) ?> (<?= htmlspecialchars($updaterRole) ?>)">
                                        <i class="bi bi-person-fill text-primary"></i>
                                        <span class="text-truncate" style="max-width: 105px;"><?= htmlspecialchars($updaterName) ?></span>
                                    </span>
                                    <span class="text-muted fs-8 mt-0.5" title="Thời gian cập nhật: <?= htmlspecialchars($formattedUpdated) ?>">
                                        <i class="bi bi-clock-history me-1 text-secondary"></i><?= htmlspecialchars($formattedUpdated) ?>
                                    </span>
                                </div>
                            </td>
                            <td class="text-center text-nowrap">
                                <?php if (!empty($item['file_path'])): ?>
                                    <div class="d-inline-flex gap-1 align-items-center">
                                        <a class="btn btn-sm btn-outline-danger px-1.5 py-0.5 fs-8" href="<?= base_url('user/view.php?id=' . urlencode($item['id'])) ?>" target="_blank" title="Xem trực tiếp file ở tab mới">
                                            <i class="bi bi-file-earmark-pdf-fill me-0.5"></i>Xem
                                        </a>
                                        <a class="btn btn-sm btn-light border text-secondary px-1.5 py-0.5 fs-8" href="<?= base_url('user/download.php?id=' . urlencode($item['id'])) ?>" title="Tải file về máy">
                                            <i class="bi bi-download"></i>
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted fs-8">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center text-nowrap">
                                <?php if (current_role() === 'admin'): ?>
                                    <form method="post" class="d-inline form-toggle-status" data-id="<?= htmlspecialchars($item['code']) ?>">
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="id" value="<?= htmlspecialchars($item['id']) ?>">
                                        <button type="submit" class="btn-status-toggle"
                                            data-id="<?= htmlspecialchars($item['code']) ?>"
                                            data-current-status="<?= (int)($item['status_raw'] ?? 1) ?>"
                                            title="<?= ((int)($item['status_raw'] ?? 1) === 1) ? 'Đang hoạt động (Hiển thị) - Bấm để chuyển sang Ẩn' : 'Không hoạt động (Ẩn) - Bấm để chuyển sang Hiển thị' ?>">
                                            <?php if ((int)($item['status_raw'] ?? 1) === 1): ?>
                                                 <span class="badge bg-success-subtle text-success border border-success-subtle px-1.5 py-1 fs-8 status-badge" style="cursor: pointer;">
                                                    <i class="bi bi-toggle-on fs-7 me-1"></i>Hoạt động
                                                </span>
                                            <?php else: ?>
                                                 <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-1.5 py-1 fs-8 status-badge" style="cursor: pointer;">
                                                    <i class="bi bi-toggle-off fs-7 me-1"></i>Đang ẩn
                                                </span>
                                            <?php endif; ?>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <?php if ((int)($item['status_raw'] ?? 1) === 1): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle px-1.5 py-1 fs-8" title="Hiển thị cho Người dùng xem">
                                            <i class="bi bi-eye-fill me-1"></i>Hoạt động
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-1.5 py-1 fs-8" title="Chỉ Quản trị viên thấy, ẩn với Người dùng">
                                            <i class="bi bi-eye-slash-fill me-1"></i>Đang ẩn
                                        </span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td class="text-end text-nowrap">
                                <div class="d-inline-flex gap-1">
                                    <button type="button" class="btn btn-sm btn-outline-info px-1.5 py-0.5 fs-8 btn-view-evidence-detail"
                                        data-id="<?= htmlspecialchars($item['code']) ?>"
                                        data-name="<?= htmlspecialchars($item['name']) ?>"
                                        data-so-hieu="<?= htmlspecialchars($item['so_hieu'] ?? '') ?>"
                                        data-date="<?= htmlspecialchars($item['issue_date'] ?? '') ?>"
                                        data-date-formatted="<?= htmlspecialchars($formattedDate) ?>"
                                        data-updated="<?= htmlspecialchars($formattedUpdated) ?>"
                                        data-user="<?= htmlspecialchars($updaterName) ?>"
                                        data-user-role="<?= htmlspecialchars($updaterRole) ?>"
                                        data-username="<?= htmlspecialchars($item['username'] ?? '') ?>"
                                        data-criteria-ids='<?= htmlspecialchars(json_encode($item['criteria_ids'] ?? []), ENT_QUOTES) ?>'
                                        data-criteria-json='<?= htmlspecialchars(json_encode($item['criteria'] ?? []), ENT_QUOTES) ?>'
                                        data-file="<?= htmlspecialchars($item['file_path'] ?? '') ?>"
                                        data-file-name="<?= htmlspecialchars(basename($item['file_path'] ?? '')) ?>"
                                        data-status="<?= (int)($item['status_raw'] ?? 1) ?>"
                                        data-view-url="<?= base_url('user/view.php?id=' . urlencode($item['id'])) ?>"
                                        data-download-url="<?= base_url('user/download.php?id=' . urlencode($item['id'])) ?>"
                                        title="Xem chi tiết minh chứng">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                    <?php if (current_role() === 'admin'): ?>
                                        <button type="button" class="btn btn-sm btn-outline-primary px-1.5 py-0.5 fs-8 btn-edit-evidence-item"
                                            data-id="<?= htmlspecialchars($item['code']) ?>"
                                            data-name="<?= htmlspecialchars($item['name']) ?>"
                                            data-so-hieu="<?= htmlspecialchars($item['so_hieu'] ?? '') ?>"
                                            data-date="<?= htmlspecialchars($item['issue_date'] ?? '') ?>"
                                            data-updated="<?= htmlspecialchars($formattedUpdated) ?>"
                                            data-user="<?= htmlspecialchars($updaterName) ?>"
                                            data-user-role="<?= htmlspecialchars($updaterRole) ?>"
                                            data-username="<?= htmlspecialchars($item['username'] ?? '') ?>"
                                            data-criteria-ids='<?= htmlspecialchars(json_encode($item['criteria_ids'] ?? []), ENT_QUOTES) ?>'
                                            data-criteria-json='<?= htmlspecialchars(json_encode($item['criteria'] ?? []), ENT_QUOTES) ?>'
                                            data-file="<?= htmlspecialchars($item['file_path'] ?? '') ?>"
                                            data-file-name="<?= htmlspecialchars(basename($item['file_path'] ?? '')) ?>"
                                            data-status="<?= (int)($item['status_raw'] ?? 1) ?>"
                                            title="Sửa minh chứng">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <form method="post" class="d-inline" data-confirm-form="Bạn có chắc chắn muốn xóa hoàn toàn minh chứng <?= htmlspecialchars($item['code']) ?> và tệp đính kèm khỏi hệ thống? Thao tác này sẽ xóa vĩnh viễn và không thể khôi phục.">
                                            <input type="hidden" name="action" value="delete_evidence">
                                            <input type="hidden" name="id" value="<?= htmlspecialchars($item['id']) ?>">
                                            <button class="btn btn-sm btn-outline-danger px-1.5 py-0.5 fs-8" type="submit" title="Xóa hoàn toàn minh chứng"><i class="bi bi-trash"></i></button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($filteredEvidences)): ?>
                        <tr><td colspan="10" class="text-center text-secondary py-5"><i class="bi bi-inbox fs-2 d-block mb-2 text-muted"></i>Không tìm thấy dữ liệu minh chứng nào phù hợp với điều kiện lọc.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ==========================================
     MODAL 1: THÊM / SỬA MINH CHỨNG
=============================================== -->
<div class="modal fade" id="evidenceFormModal" tabindex="-1" aria-labelledby="evidenceFormModalLabel" aria-hidden="true" <?= ($editingEvidence || $isCreatingEvidence) ? 'data-auto-open-modal' : '' ?>>
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white py-3">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-folder-plus fs-5 text-white"></i>
                    <h5 class="modal-title mb-0 text-white fw-bold" id="evidenceFormModalLabel"><?= $editingEvidence ? 'Cập nhật thông tin Minh chứng' : 'Thêm mới Minh chứng' ?></h5>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <div class="modal-body p-4">
                <form method="post" enctype="multipart/form-data" id="formEvidenceModal">
                    <input type="hidden" name="action" value="save_evidence">
                    <input type="hidden" name="id" id="form_evidence_id" value="<?= htmlspecialchars($editingEvidence['MaMinhChung'] ?? '') ?>">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Mã minh chứng <span class="text-danger">*</span></label>
                            <input class="form-control" name="ma_minh_chung" id="form_ma_minh_chung" value="<?= htmlspecialchars($editingEvidence['MaMinhChung'] ?? '') ?>" placeholder="VD: MC01, MC02..." required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Số hiệu</label>
                            <input class="form-control font-monospace" name="so_hieu" id="form_so_hieu" value="<?= htmlspecialchars($editingEvidence['SoHieu'] ?? '') ?>" placeholder="VD: 123/QĐ-ĐHTCNH, 45/TB-KCNTT...">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Ngày ban hành văn bản <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="ngay_ban_hanh" id="form_ngay_ban_hanh" value="<?= htmlspecialchars($editingEvidence['NgayBanHanh'] ?? '') ?>" required>
                        </div>

                        <!-- Cụm Người cập nhật & Ngày cập nhật (Tự động) -->
                        <div class="col-md-6">
                            <label class="form-label fw-bold"><i class="bi bi-person-badge text-primary me-1"></i>Người cập nhật (Tự động)</label>
                            <div class="p-2.5 rounded bg-light border d-flex align-items-center justify-content-between" style="min-height: 42px;">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge bg-primary text-white font-monospace" id="form_display_user_role"><?= htmlspecialchars($currentUser['role_name'] ?? 'Quản trị viên') ?></span>
                                    <span class="fw-semibold text-dark small" id="form_display_user_name"><?= htmlspecialchars($currentUser['name'] ?? 'Quản trị viên') ?></span>
                                    <span class="text-muted small font-monospace" id="form_display_username">(<?= htmlspecialchars($currentUser['username'] ?? 'admin') ?>)</span>
                                </div>
                                <span class="badge bg-success-subtle text-success border border-success-subtle" style="font-size: 0.72rem;"><i class="bi bi-check-circle-fill me-1"></i>Tự động</span>
                            </div>
                            <div class="form-text small text-muted">Tự động ghi nhận theo tài khoản Quản trị viên đang thao tác.</div>
                        </div>

                        <!-- Cụm chọn nhiều Tiêu chuẩn & Tiêu chí -->
                        <div class="col-md-12">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="form-label fw-bold mb-0">
                                    <i class="bi bi-diagram-3-fill text-primary me-1"></i>Thuộc Tiêu chuẩn &amp; Tiêu chí <span class="text-danger">*</span>
                                    <small class="text-muted fw-normal">(Có thể chọn 1 hoặc nhiều Tiêu chuẩn / Tiêu chí)</small>
                                </label>
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle" id="form_selected_criteria_count">Đã chọn: <?= count($editingCriteriaIds) ?> tiêu chí</span>
                            </div>
                            
                            <div class="border rounded-3 p-2.5 bg-light criteria-selection-box" style="max-height: 250px; overflow-y: auto; background-color: #f8fafc;">
                                <div class="input-group input-group-sm mb-2">
                                    <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                                    <input type="text" class="form-control bg-white border-start-0" id="criteriaSearchInput" placeholder="Tìm nhanh theo mã hoặc tên tiêu chí/tiêu chuẩn...">
                                </div>
                                <div id="criteriaCheckboxContainer" class="d-flex flex-column gap-2">
                                    <?php 
                                    $groupedCriteria = [];
                                    foreach ($criteria as $cr) {
                                        $groupKey = ($cr['standard_id'] ? ($cr['standard_id'] . ' - ' . $cr['standard_name']) : 'Khác');
                                        $groupedCriteria[$groupKey][] = $cr;
                                    }
                                    foreach ($groupedCriteria as $grp => $crList):
                                    ?>
                                        <div class="criteria-group-card p-2 rounded-2 bg-white border shadow-xs">
                                            <div class="d-flex align-items-center justify-content-between pb-1 mb-1 border-bottom">
                                                <span class="fw-bold text-primary small d-flex align-items-center gap-1">
                                                    <i class="bi bi-folder2 text-primary"></i> <?= htmlspecialchars($grp) ?>
                                                </span>
                                                <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none btn-toggle-group-criteria" style="font-size: 0.72rem;">
                                                    Chọn tất cả
                                                </button>
                                            </div>
                                            <div class="d-flex flex-column gap-1 ps-1">
                                                <?php foreach ($crList as $cr): 
                                                    $isCheck = in_array($cr['id'], $editingCriteriaIds, true);
                                                ?>
                                                    <div class="form-check criteria-item-row py-0.5">
                                                        <input class="form-check-input criteria-form-checkbox" type="checkbox" name="ma_tieu_chi[]" value="<?= htmlspecialchars($cr['id']) ?>" id="chk_crit_<?= htmlspecialchars($cr['id']) ?>" <?= $isCheck ? 'checked' : '' ?>
                                                            data-std-id="<?= htmlspecialchars($cr['standard_id']) ?>"
                                                            data-std-name="<?= htmlspecialchars($cr['standard_name']) ?>"
                                                            data-set-id="<?= htmlspecialchars($cr['set_id']) ?>"
                                                            data-set-name="<?= htmlspecialchars($cr['set_name']) ?>">
                                                        <label class="form-check-label small user-select-none" for="chk_crit_<?= htmlspecialchars($cr['id']) ?>">
                                                            <strong class="text-dark font-monospace"><?= htmlspecialchars($cr['id']) ?></strong>: <?= htmlspecialchars($cr['name']) ?>
                                                        </label>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div class="form-text small text-muted mt-1"><i class="bi bi-info-circle text-info me-1"></i>Minh chứng này sẽ tự động xuất hiện ở tất cả các Tiêu chuẩn / Tiêu chí được tích chọn khi xem trên Quản lý Bộ tiêu chuẩn.</div>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label fw-bold">Tên minh chứng <span class="text-danger">*</span></label>
                            <textarea class="form-control" name="ten_minh_chung" id="form_ten_minh_chung" rows="3" placeholder="Nhập tên minh chứng / trích yếu nội dung văn bản..." required><?= htmlspecialchars($editingEvidence['TenMinhChung'] ?? '') ?></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Chọn file đính kèm</label>
                            <input class="form-control" type="file" name="evidence_file" id="form_evidence_file" accept=".pdf,.png,.jpg,.jpeg,.gif,.webp,.doc,.docx,.xls,.xlsx,application/pdf,image/*">
                            <div class="form-text" id="form_file_help_text">
                                <?php if (!empty($editingEvidence['TepTin'])): ?>
                                    <span class="text-success"><i class="bi bi-file-earmark-check me-1"></i>Tệp tin hiện tại: <strong><?= htmlspecialchars(basename($editingEvidence['TepTin'])) ?></strong></span>. Chọn tệp mới để ghi đè.
                                <?php else: ?>
                                    Hỗ trợ định dạng: <strong>PDF, PNG, JPG, JPEG, WEBP, DOCX</strong> (Tối đa 100MB).
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold"><i class="bi bi-toggle-on text-primary me-1"></i>Trạng thái hiển thị <span class="text-danger">*</span></label>
                            <select class="form-select" name="trang_thai" id="form_trang_thai">
                                <option value="1" <?= (int)($editingEvidence['TrangThai'] ?? 1) === 1 ? 'selected' : '' ?>>Đang hoạt động (Hiển thị cho Người dùng xem)</option>
                                <option value="0" <?= (isset($editingEvidence['TrangThai']) && (int)$editingEvidence['TrangThai'] === 0) ? 'selected' : '' ?>>Không hoạt động (Chỉ Admin thấy, ẩn với Người dùng)</option>
                            </select>
                            <div class="form-text small text-muted">Mặc định là Có (Cho phép người dùng tra cứu & xem minh chứng).</div>
                        </div>
                    </div>

                    <div class="d-flex gap-2 justify-content-end pt-3 mt-4 border-top">
                        <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Hủy bỏ</button>
                        <button class="btn btn-primary px-4" type="submit">
                            <i class="bi bi-save me-1"></i> Lưu thông tin
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- ==========================================
     MODAL 2: XEM CHI TIẾT MINH CHỨNG
=============================================== -->
<div class="modal fade" id="evidenceViewModal" tabindex="-1" aria-labelledby="evidenceViewModalLabel" aria-hidden="true" <?= $viewingEvidence ? 'data-auto-open-modal' : '' ?>>
    <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-2xl rounded-4 overflow-hidden">
            <!-- Header -->
            <div class="modal-header border-0 bg-primary text-white py-3 px-4">
                <div class="d-flex align-items-center gap-3">
                    <div class="p-2 rounded-3 bg-white bg-opacity-20 text-white">
                        <i class="bi bi-file-earmark-text-fill fs-5"></i>
                    </div>
                    <div>
                        <div class="small text-white-50 text-uppercase fw-semibold" style="font-size: 0.72rem; letter-spacing: 0.05em;">Hồ sơ minh chứng kiểm định</div>
                        <h5 class="modal-title mb-0 text-white fw-bold" id="evidenceViewModalLabel">
                            Chi tiết Minh chứng: <span id="view_evidence_code_title" class="font-monospace text-warning"><?= htmlspecialchars($viewingEvidence['MaMinhChung'] ?? '') ?></span>
                        </h5>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>

            <!-- Body -->
            <div class="modal-body p-4" style="background-color: #f8fafc;">
                <!-- 1. Tiêu đề & Thông tin cơ bản -->
                <div class="card border-0 shadow-sm rounded-4 mb-3 bg-white">
                    <div class="card-body p-4">
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                            <span class="badge bg-primary px-2 py-1 font-monospace fs-7" id="view_detail_code"><?= htmlspecialchars($viewingEvidence['MaMinhChung'] ?? '') ?></span>
                            <span class="badge bg-info-subtle text-info border border-info-subtle px-2 py-1 font-monospace fs-7" id="view_detail_so_hieu">
                                <i class="bi bi-tag-fill me-1"></i>Số hiệu: <?= htmlspecialchars($viewingEvidence['SoHieu'] ?? '') ?: 'Không có' ?>
                            </span>
                            <span class="badge bg-light text-secondary border px-2 py-1 fs-7" id="view_detail_date">
                                <i class="bi bi-calendar3 me-1 text-primary"></i>Ngày ban hành: <?= !empty($viewingEvidence['NgayBanHanh']) ? date('d/m/Y', strtotime($viewingEvidence['NgayBanHanh'])) : '-' ?>
                            </span>
                            <span class="badge bg-light text-secondary border px-2 py-1 fs-7" id="view_detail_updated_date">
                                <i class="bi bi-clock-history me-1 text-info"></i>Ngày cập nhật: <?= !empty($viewingEvidence['NgayCapNhat']) ? date('d/m/Y H:i', strtotime($viewingEvidence['NgayCapNhat'])) : '-' ?>
                            </span>
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1 fs-7" id="view_detail_user">
                                <i class="bi bi-person-fill me-1"></i>Người cập nhật: <?= htmlspecialchars($viewingEvidence['user_name'] ?? 'Quản trị viên') ?>
                            </span>
                            <span id="view_detail_status">
                                <?php if ((int)($viewingEvidence['TrangThai'] ?? 1) === 1): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 fs-7"><i class="bi bi-eye-fill me-1"></i>Đang hoạt động (Hiển thị)</span>
                                <?php else: ?>
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1 fs-7"><i class="bi bi-eye-slash-fill me-1"></i>Không hoạt động (Ẩn)</span>
                                <?php endif; ?>
                            </span>
                        </div>
                        <h5 class="fw-bold text-dark mb-0 lh-base" id="view_detail_name" style="font-size: 1.15rem;">
                            <?= htmlspecialchars($viewingEvidence['TenMinhChung'] ?? '') ?>
                        </h5>
                    </div>
                </div>

                <!-- 2. Sơ đồ phân cấp đánh giá (Cây tiêu chuẩn) -->
                <div class="card border-0 shadow-sm rounded-4 mb-3 bg-white">
                    <div class="card-body p-3 p-md-4">
                        <div class="d-flex align-items-center justify-content-between gap-2 mb-3 pb-2 border-bottom">
                            <div class="d-flex align-items-center gap-2">
                                <i class="bi bi-diagram-3-fill text-primary fs-5"></i>
                                <h6 class="fw-bold mb-0 text-dark">Sơ đồ phân cấp đánh giá (Cây tiêu chuẩn / Tiêu chí trực thuộc)</h6>
                            </div>
                            <span class="badge bg-primary-subtle text-primary small" id="view_detail_criteria_count_badge">Đang liên kết</span>
                        </div>
                        <div id="view_detail_hierarchy" class="d-flex flex-column gap-2">
                            <?php if (!empty($viewingCriteriaList)): 
                                $phpGrouped = [];
                                foreach ($viewingCriteriaList as $vc) {
                                    $stdKey = $vc['standard_id'] ?: 'Khác';
                                    $phpGrouped[$stdKey]['standard_id'] = $vc['standard_id'] ?? '';
                                    $phpGrouped[$stdKey]['standard_name'] = $vc['standard_name'] ?? '';
                                    $phpGrouped[$stdKey]['set_id'] = $vc['set_id'] ?? '';
                                    $phpGrouped[$stdKey]['set_name'] = $vc['set_name'] ?? '';
                                    $phpGrouped[$stdKey]['criteria'][] = $vc;
                                }
                            ?>
                                <?php foreach ($phpGrouped as $sKey => $grp): ?>
                                    <div class="p-3 rounded-3 bg-light border mb-2">
                                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 pb-2 mb-2 border-bottom">
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="badge bg-primary px-2 py-1 font-monospace fs-7">
                                                    <i class="bi bi-folder2 me-1"></i><?= htmlspecialchars($grp['standard_id']) ?>
                                                </span>
                                                <strong class="text-primary fs-7"><?= htmlspecialchars($grp['standard_name'] ?: 'Tiêu chuẩn ' . $grp['standard_id']) ?></strong>
                                            </div>
                                            <span class="small text-secondary fw-semibold">
                                                <i class="bi bi-collection-fill text-info me-1"></i><?= htmlspecialchars($grp['set_name'] ?: ($grp['set_id'] ?: 'Bộ tiêu chuẩn')) ?>
                                            </span>
                                        </div>
                                        <div class="d-flex flex-column gap-1.5 ps-1">
                                            <div class="small text-muted fw-semibold mb-1"><i class="bi bi-list-check text-success me-1"></i>Các Tiêu chí trực thuộc:</div>
                                            <div class="d-flex flex-column gap-1.5">
                                                <?php foreach ($grp['criteria'] as $c): ?>
                                                    <div class="p-2 rounded-2 bg-white border border-success-subtle d-flex align-items-center gap-2">
                                                        <span class="badge bg-success font-monospace px-2 py-1"><?= htmlspecialchars($c['id']) ?></span>
                                                        <span class="fw-semibold text-dark fs-7"><?= htmlspecialchars($c['name'] ?? '') ?></span>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <span class="text-muted p-2 bg-white rounded border text-center">Chưa phân loại tiêu chuẩn / tiêu chí</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- 3. Tệp tin đính kèm & Tải về -->
                <div class="card border-0 shadow-sm rounded-4 bg-white">
                    <div class="card-body p-3 p-md-4">
                        <div class="d-flex align-items-center gap-2 mb-3 pb-2 border-bottom">
                            <i class="bi bi-paperclip text-primary fs-5"></i>
                            <h6 class="fw-bold mb-0 text-dark">Tệp tin văn bản / Minh chứng đính kèm</h6>
                        </div>
                        <div id="view_detail_file_container">
                            <?php if (!empty($viewingEvidence['TepTin'])): ?>
                                <div class="p-3 rounded-3 bg-light border d-flex flex-wrap align-items-center justify-content-between gap-3">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="p-3 rounded-3 bg-danger-subtle text-danger fs-3">
                                            <i class="bi bi-file-earmark-pdf-fill"></i>
                                        </div>
                                        <div>
                                            <h6 class="fw-bold text-dark mb-1"><?= htmlspecialchars(basename($viewingEvidence['TepTin'])) ?></h6>
                                            <span class="badge bg-light text-secondary border">Tệp đính kèm văn bản</span>
                                        </div>
                                    </div>
                                    <div class="d-inline-flex gap-2">
                                        <a class="btn btn-primary px-3 rounded-3" href="<?= base_url('user/view.php?id=' . urlencode($viewingEvidence['MaMinhChung'])) ?>" target="_blank">
                                            <i class="bi bi-eye me-1"></i> Xem trực tiếp file
                                        </a>
                                        <a class="btn btn-success px-3 rounded-3" href="<?= base_url('user/download.php?id=' . urlencode($viewingEvidence['MaMinhChung'])) ?>" download>
                                            <i class="bi bi-download me-1"></i> Tải về máy
                                        </a>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="p-3 rounded-3 bg-light text-center text-muted border border-dashed">
                                    <i class="bi bi-file-earmark-x fs-3 d-block mb-1 text-secondary"></i>
                                    Chưa có tệp tin đính kèm cho minh chứng này.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="modal-footer border-top bg-white px-4 py-3 d-flex justify-content-between">
                <div class="text-muted small">
                    <i class="bi bi-shield-check text-success me-1"></i>Dữ liệu kiểm định đồng bộ theo CSDL
                </div>
                <div class="d-flex gap-2">
                    <?php if (current_role() === 'admin'): ?>
                        <button type="button" class="btn btn-outline-primary px-3 rounded-3" id="btnEditFromViewModal">
                            <i class="bi bi-pencil-square me-1"></i>Sửa minh chứng
                        </button>
                    <?php endif; ?>
                    <a href="#" id="view_detail_newtab_link" target="_blank" class="btn btn-primary px-3 rounded-3" style="display: <?= !empty($viewingEvidence['TepTin']) ? 'inline-flex' : 'none' ?>;">
                        <i class="bi bi-eye me-1"></i>Xem trực tiếp file
                    </a>
                    <button type="button" class="btn btn-secondary px-4 rounded-3" data-bs-dismiss="modal">Đóng</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    function escapeHtml(text) {
        if (!text) return '';
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.toString().replace(/[&<>"']/g, m => map[m]);
    }

    function getVietnamTimeFormatted() {
        const now = new Date();
        const options = {
            timeZone: 'Asia/Ho_Chi_Minh',
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hour12: false
        };
        try {
            const parts = new Intl.DateTimeFormat('vi-VN', options).formatToParts(now);
            const getVal = type => (parts.find(p => p.type === type) || {}).value || '';
            return `${getVal('day')}/${getVal('month')}/${getVal('year')} ${getVal('hour')}:${getVal('minute')}:${getVal('second')}`;
        } catch (e) {
            return now.toLocaleString('vi-VN');
        }
    }

    setInterval(function () {
        const timeDisplay = document.getElementById('form_display_updated_date');
        if (timeDisplay) {
            timeDisplay.textContent = getVietnamTimeFormatted() + ' (GMT+7)';
        }
    }, 1000);

    const modalFormEl = document.getElementById('evidenceFormModal');
    const modalForm = modalFormEl ? new bootstrap.Modal(modalFormEl) : null;
    const modalViewEl = document.getElementById('evidenceViewModal');
    const modalView = modalViewEl ? new bootstrap.Modal(modalViewEl) : null;

    // Criteria selection in Add/Edit modal
    const criteriaCheckboxes = document.querySelectorAll('.criteria-form-checkbox');
    const selectedCountBadge = document.getElementById('form_selected_criteria_count');
    const criteriaSearchInput = document.getElementById('criteriaSearchInput');

    function updateSelectedCriteriaCount() {
        const checkedCount = document.querySelectorAll('.criteria-form-checkbox:checked').length;
        if (selectedCountBadge) {
            selectedCountBadge.textContent = `Đã chọn: ${checkedCount} tiêu chí`;
        }
    }

    criteriaCheckboxes.forEach(chk => {
        chk.addEventListener('change', updateSelectedCriteriaCount);
    });

    // Select all / deselect in a group
    document.querySelectorAll('.btn-toggle-group-criteria').forEach(btn => {
        btn.addEventListener('click', function () {
            const card = this.closest('.criteria-group-card');
            if (!card) return;
            const groupCheckboxes = card.querySelectorAll('.criteria-form-checkbox');
            const allChecked = Array.from(groupCheckboxes).every(c => c.checked);
            groupCheckboxes.forEach(c => c.checked = !allChecked);
            this.textContent = allChecked ? 'Chọn tất cả' : 'Bỏ chọn tất cả';
            updateSelectedCriteriaCount();
        });
    });

    // Quick filter criteria inside modal
    if (criteriaSearchInput) {
        criteriaSearchInput.addEventListener('input', function () {
            const kw = this.value.toLowerCase().trim();
            document.querySelectorAll('.criteria-group-card').forEach(group => {
                let groupHasMatch = false;
                group.querySelectorAll('.criteria-item-row').forEach(row => {
                    const text = row.textContent.toLowerCase();
                    const matches = kw === '' || text.includes(kw);
                    row.style.display = matches ? 'block' : 'none';
                    if (matches) groupHasMatch = true;
                });
                group.style.display = groupHasMatch ? 'block' : 'none';
            });
        });
    }

    // Reset Form Modal on open for Add New
    const btnOpenAdd = document.getElementById('btnOpenAddModal');
    if (btnOpenAdd) {
        btnOpenAdd.addEventListener('click', function () {
            document.getElementById('formEvidenceModal').reset();
            document.getElementById('form_evidence_id').value = '';
            document.getElementById('form_ma_minh_chung').value = '';
            document.getElementById('form_so_hieu').value = '';
            document.getElementById('form_ten_minh_chung').value = '';
            document.getElementById('form_ngay_ban_hanh').value = '';
            document.getElementById('form_trang_thai').value = '1';
            document.getElementById('form_evidence_file').value = '';
            document.getElementById('evidenceFormModalLabel').innerHTML = '<i class="bi bi-folder-plus fs-5 text-white me-2"></i>Thêm mới Minh chứng';
            document.getElementById('form_file_help_text').innerHTML = 'Hỗ trợ định dạng: <strong>PDF, PNG, JPG, JPEG, WEBP, DOCX</strong> (Tối đa 100MB).';
            
            criteriaCheckboxes.forEach(chk => chk.checked = false);
            updateSelectedCriteriaCount();

            if (criteriaSearchInput) {
                criteriaSearchInput.value = '';
                criteriaSearchInput.dispatchEvent(new Event('input'));
            }
        });
    }

    function openEditModal(data) {
        document.getElementById('evidenceFormModalLabel').innerHTML = '<i class="bi bi-pencil-square fs-5 text-white me-2"></i>Cập nhật thông tin Minh chứng';
        document.getElementById('form_evidence_id').value = data.id || '';
        document.getElementById('form_ma_minh_chung').value = data.id || '';
        document.getElementById('form_so_hieu').value = data.soHieu || '';
        document.getElementById('form_ten_minh_chung').value = data.name || '';
        document.getElementById('form_ngay_ban_hanh').value = data.date || '';
        document.getElementById('form_trang_thai').value = data.status || '1';
        document.getElementById('form_evidence_file').value = '';

        // Check the criteria checkboxes
        const targetIds = Array.isArray(data.criteriaIds) ? data.criteriaIds : [data.criterion];
        criteriaCheckboxes.forEach(chk => {
            chk.checked = targetIds.includes(chk.value);
        });
        updateSelectedCriteriaCount();

        if (criteriaSearchInput) {
            criteriaSearchInput.value = '';
            criteriaSearchInput.dispatchEvent(new Event('input'));
        }

        const helpText = document.getElementById('form_file_help_text');
        if (data.fileName) {
            helpText.innerHTML = '<span class="text-success"><i class="bi bi-file-earmark-check me-1"></i>Tệp tin hiện tại: <strong>' + escapeHtml(data.fileName) + '</strong></span>. Chọn tệp mới để ghi đè (nếu muốn thay đổi).';
        } else {
            helpText.innerHTML = 'Hỗ trợ định dạng: <strong>PDF, PNG, JPG, JPEG, WEBP, DOCX</strong> (Tối đa 100MB).';
        }

        if (modalForm) modalForm.show();
    }

    document.querySelectorAll('.btn-edit-evidence-item').forEach(btn => {
        btn.addEventListener('click', function () {
            let criteriaIds = [];
            try {
                criteriaIds = JSON.parse(this.dataset.criteriaIds || '[]');
            } catch (e) {
                criteriaIds = [];
            }
            openEditModal({
                id: this.dataset.id || '',
                name: this.dataset.name || '',
                soHieu: this.dataset.soHieu || '',
                date: this.dataset.date || '',
                criterion: this.dataset.criterion || '',
                criteriaIds: criteriaIds,
                fileName: this.dataset.fileName || '',
                status: this.dataset.status || '1'
            });
        });
    });

    let currentViewingData = null;

    document.querySelectorAll('.btn-view-evidence-detail').forEach(btn => {
        btn.addEventListener('click', function () {
            const id = this.dataset.id || '';
            const name = this.dataset.name || '';
            const soHieu = this.dataset.soHieu || '';
            const date = this.dataset.dateFormatted || (this.dataset.date || '-');
            const rawDate = this.dataset.date || '';
            const updated = this.dataset.updated || '-';
            const user = this.dataset.user || 'Quản trị viên';
            let criteriaList = [];
            try {
                criteriaList = JSON.parse(this.dataset.criteriaJson || '[]');
            } catch (e) {
                criteriaList = [];
            }
            let criteriaIds = [];
            try {
                criteriaIds = JSON.parse(this.dataset.criteriaIds || '[]');
            } catch (e) {
                criteriaIds = [];
            }

            const file = this.dataset.file || '';
            const fileName = this.dataset.fileName || '';
            const status = this.dataset.status || '1';
            const viewUrl = this.dataset.viewUrl || '';
            const downloadUrl = this.dataset.downloadUrl || '';

            currentViewingData = {
                id: id,
                name: name,
                soHieu: soHieu,
                date: rawDate,
                criteriaIds: criteriaIds,
                fileName: fileName,
                status: status
            };

            document.getElementById('view_evidence_code_title').textContent = id;
            document.getElementById('view_detail_code').textContent = id;
            const soHieuEl = document.getElementById('view_detail_so_hieu');
            if (soHieuEl) {
                soHieuEl.innerHTML = '<i class="bi bi-tag-fill me-1"></i>Số hiệu: ' + escapeHtml(soHieu || 'Không có');
            }
            document.getElementById('view_detail_name').textContent = name;
            document.getElementById('view_detail_date').innerHTML = `<i class="bi bi-calendar3 me-1 text-primary"></i>Ngày ban hành: ${date}`;
            
            const updatedEl = document.getElementById('view_detail_updated_date');
            if (updatedEl) {
                updatedEl.innerHTML = `<i class="bi bi-clock-history me-1 text-info"></i>Ngày cập nhật: ${escapeHtml(updated)}`;
            }
            
            const userEl = document.getElementById('view_detail_user');
            if (userEl) {
                userEl.innerHTML = `<i class="bi bi-person-fill me-1"></i>Người cập nhật: ${escapeHtml(user)}`;
            }

            const statusEl = document.getElementById('view_detail_status');
            if (statusEl) {
                if (status === '1') {
                    statusEl.innerHTML = '<span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 fs-7"><i class="bi bi-eye-fill me-1"></i>Đang hoạt động (Hiển thị)</span>';
                } else {
                    statusEl.innerHTML = '<span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1 fs-7"><i class="bi bi-eye-slash-fill me-1"></i>Không hoạt động (Ẩn)</span>';
                }
            }

            const hierEl = document.getElementById('view_detail_hierarchy');
            const countBadge = document.getElementById('view_detail_criteria_count_badge');
            if (countBadge) {
                countBadge.textContent = `Thuộc ${criteriaList.length} Tiêu chí`;
            }

            if (criteriaList.length > 0) {
                // Group criteria by Standard
                const grouped = {};
                criteriaList.forEach(crit => {
                    const stdKey = crit.standard_id || 'Khác';
                    if (!grouped[stdKey]) {
                        grouped[stdKey] = {
                            standard_id: crit.standard_id || '',
                            standard_name: crit.standard_name || '',
                            set_id: crit.set_id || '',
                            set_name: crit.set_name || '',
                            criteria: []
                        };
                    }
                    grouped[stdKey].criteria.push(crit);
                });

                let hierHtml = '';
                Object.keys(grouped).forEach(sKey => {
                    const grp = grouped[sKey];
                    let critsHtml = '';
                    grp.criteria.forEach(c => {
                        critsHtml += `
                            <div class="p-2 rounded-2 bg-white border border-success-subtle d-flex align-items-center gap-2">
                                <span class="badge bg-success font-monospace px-2 py-1">${escapeHtml(c.id)}</span>
                                <span class="fw-semibold text-dark fs-7">${escapeHtml(c.name || '')}</span>
                            </div>
                        `;
                    });

                    hierHtml += `
                        <div class="p-3 rounded-3 bg-light border mb-2">
                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 pb-2 mb-2 border-bottom">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge bg-primary px-2 py-1 font-monospace fs-7">
                                        <i class="bi bi-folder2 me-1"></i>${escapeHtml(grp.standard_id)}
                                    </span>
                                    <strong class="text-primary fs-7">${escapeHtml(grp.standard_name || 'Tiêu chuẩn ' + grp.standard_id)}</strong>
                                </div>
                                <span class="small text-secondary fw-semibold">
                                    <i class="bi bi-collection-fill text-info me-1"></i>${escapeHtml(grp.set_name || grp.set_id || 'Bộ tiêu chuẩn')}
                                </span>
                            </div>
                            <div class="d-flex flex-column gap-1.5 ps-1">
                                <div class="small text-muted fw-semibold mb-1"><i class="bi bi-list-check text-success me-1"></i>Các Tiêu chí trực thuộc:</div>
                                <div class="d-flex flex-column gap-1.5">
                                    ${critsHtml}
                                </div>
                            </div>
                        </div>
                    `;
                });
                hierEl.innerHTML = hierHtml;
            } else {
                hierEl.innerHTML = '<div class="text-muted p-2 bg-light rounded-3 text-center">Chưa phân loại tiêu chuẩn / tiêu chí</div>';
            }

            const fileContainer = document.getElementById('view_detail_file_container');
            const newTabLink = document.getElementById('view_detail_newtab_link');

            if (file) {
                fileContainer.innerHTML = `
                    <div class="p-3 rounded-3 bg-light border d-flex flex-wrap align-items-center justify-content-between gap-3">
                        <div class="d-flex align-items-center gap-3">
                            <div class="p-3 rounded-3 bg-danger-subtle text-danger fs-3">
                                <i class="bi bi-file-earmark-pdf-fill"></i>
                            </div>
                            <div>
                                <h6 class="fw-bold text-dark mb-1">${escapeHtml(fileName)}</h6>
                                <span class="badge bg-light text-secondary border">Tệp đính kèm văn bản</span>
                            </div>
                        </div>
                        <div class="d-inline-flex gap-2">
                            <a class="btn btn-primary px-3 rounded-3" href="${viewUrl}" target="_blank">
                                <i class="bi bi-eye me-1"></i> Xem trực tiếp file
                            </a>
                            <a class="btn btn-success px-3 rounded-3" href="${downloadUrl}" download>
                                <i class="bi bi-download me-1"></i> Tải về máy
                            </a>
                        </div>
                    </div>
                `;
                newTabLink.href = viewUrl;
                newTabLink.style.display = 'inline-flex';
            } else {
                fileContainer.innerHTML = `
                    <div class="p-3 rounded-3 bg-light text-center text-muted border border-dashed">
                        <i class="bi bi-file-earmark-x fs-3 d-block mb-1 text-secondary"></i>
                        Chưa có tệp tin đính kèm cho minh chứng này.
                    </div>
                `;
                newTabLink.style.display = 'none';
            }

            if (modalView) modalView.show();
        });
    });

    const btnEditFromView = document.getElementById('btnEditFromViewModal');
    if (btnEditFromView) {
        btnEditFromView.addEventListener('click', function () {
            if (modalView) modalView.hide();
            if (currentViewingData) {
                openEditModal(currentViewingData);
            }
        });
    }

    // Xử lý chuyển đổi nhanh trạng thái minh chứng
    document.querySelectorAll('.form-toggle-status').forEach(form => {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            const btn = this.querySelector('.btn-status-toggle');
            const badge = this.querySelector('.status-badge');
            const formData = new FormData(this);

            if (badge) {
                badge.style.opacity = '0.5';
            }

            fetch(window.location.href, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const isNowActive = (parseInt(data.new_status) === 1);
                    btn.dataset.currentStatus = data.new_status;
                    btn.title = isNowActive 
                        ? 'Đang hoạt động (Hiển thị cho Người dùng) - Bấm để chuyển sang Không hoạt động (Ẩn)' 
                        : 'Không hoạt động (Ẩn khỏi Người dùng) - Bấm để chuyển sang Đang hoạt động (Hiển thị)';

                    if (badge) {
                        badge.className = isNowActive 
                            ? 'badge bg-success-subtle text-success border border-success-subtle px-2 py-1 fs-7 status-badge' 
                            : 'badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1 fs-7 status-badge';
                        badge.innerHTML = isNowActive 
                            ? '<i class="bi bi-toggle-on fs-6 me-1"></i>Đang hoạt động' 
                            : '<i class="bi bi-toggle-off fs-6 me-1"></i>Không hoạt động';
                        badge.style.opacity = '1';
                    }

                    const row = form.closest('tr');
                    if (row) {
                        const viewBtn = row.querySelector('.btn-view-evidence-detail');
                        if (viewBtn) viewBtn.dataset.status = data.new_status;
                        const editBtn = row.querySelector('.btn-edit-evidence-item');
                        if (editBtn) editBtn.dataset.status = data.new_status;
                    }

                    const statActive = document.getElementById('statCardActive');
                    const statInactive = document.getElementById('statCardInactive');
                    if (statActive && statInactive) {
                        let activeVal = parseInt(statActive.textContent) || 0;
                        let inactiveVal = parseInt(statInactive.textContent) || 0;
                        if (isNowActive) {
                            statActive.textContent = activeVal + 1;
                            statInactive.textContent = Math.max(0, inactiveVal - 1);
                        } else {
                            statActive.textContent = Math.max(0, activeVal - 1);
                            statInactive.textContent = inactiveVal + 1;
                        }
                    }

                    showFloatingToast(data.message || 'Cập nhật trạng thái thành công', 'success');
                } else {
                    if (badge) badge.style.opacity = '1';
                    alert(data.message || 'Có lỗi xảy ra khi đổi trạng thái.');
                }
            })
            .catch(err => {
                if (badge) badge.style.opacity = '1';
                form.submit();
            });
        });
    });

    function showFloatingToast(message, type = 'success') {
        let toastContainer = document.getElementById('floatingToastContainer');
        if (!toastContainer) {
            toastContainer = document.createElement('div');
            toastContainer.id = 'floatingToastContainer';
            toastContainer.style.cssText = 'position: fixed; bottom: 24px; right: 24px; z-index: 9999; display: flex; flex-direction: column; gap: 8px;';
            document.body.appendChild(toastContainer);
        }

        const toast = document.createElement('div');
        toast.className = `alert alert-${type} shadow-lg rounded-4 d-flex align-items-center gap-2 mb-0 py-2 px-3 border-0`;
        toast.style.cssText = 'min-width: 280px; max-width: 420px; animation: slideInUp 0.3s ease; font-size: 0.9rem; background: #ffffff; border-left: 4px solid ' + (type === 'success' ? '#10b981' : '#ef4444') + ' !important;';
        toast.innerHTML = `
            <i class="bi ${type === 'success' ? 'bi-check-circle-fill text-success fs-5' : 'bi-exclamation-triangle-fill text-danger fs-5'}"></i>
            <div class="flex-grow-1 fw-medium text-dark">${message}</div>
        `;
        toastContainer.appendChild(toast);

        setTimeout(() => {
            toast.style.transition = 'all 0.3s ease';
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(10px)';
            setTimeout(() => toast.remove(), 300);
        }, 2500);
    }

    // ==============================================
    // INITIALIZE HTML BOOTSTRAP TOOLTIPS
    // ==============================================
    const tooltipTriggerList = Array.from(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.forEach(tooltipTriggerEl => {
        new bootstrap.Tooltip(tooltipTriggerEl, {
            html: true,
            boundary: document.body,
            fallbackPlacements: ['top', 'bottom', 'right', 'left']
        });
    });

    // ==============================================
    // SEARCH LOCATION NAVIGATOR & HIGHLIGHT FOCUS
    // ==============================================
    const matchedRows = Array.from(document.querySelectorAll('.search-matched-row'));
    const totalMatches = matchedRows.length;
    let currentMatchIndex = 0;
    const matchBadge = document.getElementById('matchCurrentIndexBadge');
    const btnPrevMatch = document.getElementById('btnPrevMatch');
    const btnNextMatch = document.getElementById('btnNextMatch');

    function focusMatch(index) {
        if (totalMatches === 0) return;
        matchedRows.forEach(r => r.classList.remove('current-focus-match'));
        const targetRow = matchedRows[index];
        if (!targetRow) return;

        targetRow.classList.add('current-focus-match');
        targetRow.scrollIntoView({ behavior: 'smooth', block: 'center' });

        if (matchBadge) {
            matchBadge.textContent = `${index + 1} / ${totalMatches}`;
        }
    }

    if (totalMatches > 0 && <?= json_encode($filterKeyword !== '') ?>) {
        if (matchBadge) {
            matchBadge.textContent = `1 / ${totalMatches}`;
        }
        setTimeout(() => {
            focusMatch(0);
        }, 250);

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
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
