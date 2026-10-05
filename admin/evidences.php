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
            $haystack = implode(' ', [
                $ev['code'] ?? '',
                $ev['name'] ?? '',
                $ev['so_hieu'] ?? '',
                $ev['criterion_name'] ?? '',
                $ev['standard_name'] ?? '',
                $ev['set_name'] ?? '',
                $ev['issue_date_formatted'] ?? '',
                $ev['issue_date'] ?? '',
                $ev['updated'] ?? '',
                $ev['user_name'] ?? '',
                $ev['file_path'] ?? '',
            ]);
            if (!search_contains($haystack, $searchKeyword)) return false;
        }
        if ($filterStandardSet !== '' && ($ev['ma_bo_tieu_chuan'] ?? '') !== $filterStandardSet) return false;
        if ($filterStandard !== '' && ($ev['ma_tieu_chuan'] ?? '') !== $filterStandard) return false;
        if ($filterCriterion !== '' && ($ev['ma_tieu_chi'] ?? '') !== $filterCriterion) return false;
        if ($filterStatus !== '' && (string)($ev['status_raw'] ?? 1) !== $filterStatus) return false;
        if ($filterHasFile === 'yes' && empty($ev['file_path'])) return false;
        if ($filterHasFile === 'no' && !empty($ev['file_path'])) return false;
        return true;
    });

    $exportData = [];
    $stt = 1;
    foreach ($exportList as $ev) {
        $exportData[] = [
            'stt'          => $stt++,
            'code'         => $ev['code'],
            'name'         => $ev['name'],
            'so_hieu'      => !empty($ev['so_hieu']) ? $ev['so_hieu'] : '-',
            'standard'     => $ev['standard_name'] ? ($ev['ma_tieu_chuan'] . ' - ' . $ev['standard_name']) : '-',
            'criterion'    => $ev['criterion_name'] ? ($ev['ma_tieu_chi'] . ' - ' . $ev['criterion_name']) : '-',
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

                // 1. Xóa nhật ký tải tệp tin liên quan
                $pdo->prepare('DELETE FROM download_logs WHERE MaMinhChung = :id')->execute(['id' => $evidenceId]);

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
        $maTieuChi      = trim($_POST['ma_tieu_chi'] ?? '') ?: null;
        $userId         = $_SESSION['user_id'] ?? (function_exists('current_user') ? current_user()['id'] : 'ND001');
        $status         = isset($_POST['trang_thai']) ? (int) $_POST['trang_thai'] : 1;
        $status         = in_array($status, [0, 1], true) ? $status : 1;

        // Tự động tìm Bộ tiêu chuẩn từ Tiêu chí được chọn
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
if ($editId !== '') {
    $stmt = $pdo->prepare('
        SELECT m.*, u.HoTen AS user_name, u.TenDangNhap AS username, u.VaiTro AS user_role
        FROM MinhChung m
        LEFT JOIN NguoiDung u ON u.MaNguoiDung = m.MaNguoiDung
        WHERE m.MaMinhChung = :id LIMIT 1
    ');
    $stmt->execute(['id' => $editId]);
    $editingEvidence = $stmt->fetch();
}

$viewingEvidence = null;
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
        $haystack = implode(' ', [
            $item['code'] ?? '',
            $item['name'] ?? '',
            $item['so_hieu'] ?? '',
            $item['criterion_name'] ?? '',
            $item['standard_name'] ?? '',
            $item['set_name'] ?? '',
            $item['issue_date_formatted'] ?? '',
            $item['issue_date'] ?? '',
            $item['file_path'] ?? '',
        ]);
        if (!search_contains($haystack, $filterKeyword)) {
            return false;
        }
    }

    if ($filterStandardSet !== '' && ($item['ma_bo_tieu_chuan'] ?? '') !== $filterStandardSet) {
        return false;
    }

    if ($filterStandard !== '' && ($item['ma_tieu_chuan'] ?? '') !== $filterStandard) {
        return false;
    }

    if ($filterCriterion !== '' && ($item['ma_tieu_chi'] ?? '') !== $filterCriterion) {
        return false;
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
.table thead th {
    white-space: nowrap !important;
    background-color: #f8fafc !important;
    color: #475569;
    font-weight: 700;
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    padding-top: 14px;
    padding-bottom: 14px;
}
.table tbody tr {
    transition: background-color 0.15s ease;
}
.table tbody tr:hover {
    background-color: #f1f5f9 !important;
}
.evidence-title-cell {
    word-break: break-word;
    line-height: 1.5;
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
    transform: scale(1.08);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
}
.btn-status-toggle:active .status-badge {
    transform: scale(0.95);
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
                            <span class="text-muted small">Quản lý các hồ sơ, văn bản minh chứng phục vụ kiểm định</span>
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
                            <div class="form-text small text-secondary mt-1"><i class="bi bi-lightning-charge text-warning"></i> Nhấn Enter hoặc nút Tìm kiếm bên phải</div>
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
                            <div class="d-flex justify-content-between align-items-center mt-2">
                                <span class="form-text small text-secondary"><i class="bi bi-funnel text-info"></i> Tự động lọc theo Tiêu chuẩn &amp; Tiêu chí đã chọn</span>
                                <button class="btn btn-sm btn-primary px-4 rounded-pill shadow-xs" type="submit"><i class="bi bi-search me-1"></i> Áp dụng</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Evidence Table -->
            <div class="table-responsive border rounded-3 overflow-hidden shadow-xs">
                <table class="table align-middle mb-0 table-hover" data-page-size="10" style="width: 100%;">
                    <thead class="table-light">
                        <tr>
                            <th class="text-center text-nowrap" style="width: 50px;">STT</th>
                            <th class="text-nowrap" style="width: 110px;">Mã minh chứng</th>
                            <th style="width: 25%; min-width: 230px;">Tên minh chứng</th>
                            <th class="text-center text-nowrap" style="width: 130px;">Số hiệu</th>
                            <th style="width: 18%; min-width: 180px;">Tiêu chuẩn &amp; Tiêu chí</th>
                            <th class="text-center text-nowrap" style="width: 115px;">Ngày ban hành</th>
                            <th class="text-center text-nowrap" style="width: 130px;">Ngày cập nhật</th>
                            <th class="text-nowrap" style="width: 140px;">Người cập nhật</th>
                            <th class="text-center text-nowrap" style="width: 120px;">File đính kèm</th>
                            <th class="text-center text-nowrap" style="width: 125px;">Trạng thái</th>
                            <th class="text-end text-nowrap" style="width: 110px;">Thao tác</th>
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
                        <tr>
                            <td class="text-center text-secondary fw-semibold"><?= $stt++ ?></td>
                            <td>
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1 fs-7 fw-bold font-monospace"><?= htmlspecialchars($item['code']) ?></span>
                            </td>
                            <td class="evidence-title-cell">
                                <div class="fw-semibold text-dark fs-7" title="<?= htmlspecialchars($item['name']) ?>">
                                    <?= htmlspecialchars($item['name']) ?>
                                </div>
                            </td>
                            <td class="text-center text-nowrap">
                                <?php if (!empty($item['so_hieu'])): ?>
                                    <span class="badge bg-light text-dark border font-monospace px-2 py-1 fs-7" title="Số hiệu văn bản: <?= htmlspecialchars($item['so_hieu']) ?>">
                                        <?= htmlspecialchars($item['so_hieu']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted small">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($item['ma_tieu_chi'])): ?>
                                    <div class="d-flex flex-column gap-1">
                                        <?php if (!empty($item['set_name'])): ?>
                                            <div class="small text-secondary fw-bold d-flex align-items-center gap-1 text-truncate" style="font-size: 0.72rem;" title="Bộ tiêu chuẩn: <?= htmlspecialchars($item['set_name']) ?>">
                                                <i class="bi bi-collection-fill text-primary" style="font-size: 0.75rem;"></i>
                                                <span class="text-truncate"><?= htmlspecialchars($item['ma_bo_tieu_chuan'] . ($item['set_name'] ? ' - ' . $item['set_name'] : '')) ?></span>
                                            </div>
                                        <?php endif; ?>
                                        <span class="badge bg-primary-subtle text-primary text-truncate d-inline-block text-start w-100" title="Tiêu chuẩn: <?= htmlspecialchars($item['standard_name']) ?>">
                                            <i class="bi bi-folder2 me-1"></i><?= htmlspecialchars($item['ma_tieu_chuan'] . ($item['standard_name'] ? ' - ' . $item['standard_name'] : '')) ?>
                                        </span>
                                        <span class="badge bg-success-subtle text-success text-truncate d-inline-block text-start w-100" title="Tiêu chí: <?= htmlspecialchars($item['criterion_name']) ?>">
                                            <i class="bi bi-list-check me-1"></i><?= htmlspecialchars($item['ma_tieu_chi'] . ($item['criterion_name'] ? ' - ' . $item['criterion_name'] : '')) ?>
                                        </span>
                                    </div>
                                <?php else: ?>
                                    <span class="badge bg-light text-muted border">Chưa phân loại</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center text-nowrap">
                                <span class="badge bg-light text-secondary border px-2 py-1 fs-7">
                                    <i class="bi bi-calendar3 me-1 text-primary"></i><?= htmlspecialchars($formattedDate) ?>
                                </span>
                            </td>
                            <td class="text-center text-nowrap">
                                <span class="badge bg-light text-secondary border px-2 py-1 fs-7" title="Thời gian cập nhật gần nhất">
                                    <i class="bi bi-clock-history me-1 text-info"></i><?= htmlspecialchars($formattedUpdated) ?>
                                </span>
                            </td>
                            <td class="text-nowrap">
                                <div class="d-flex flex-column">
                                    <span class="fw-semibold text-dark fs-7 d-flex align-items-center gap-1" title="<?= htmlspecialchars($updaterName) ?>">
                                        <i class="bi bi-person-fill text-primary" style="font-size: 0.85rem;"></i>
                                        <span class="text-truncate" style="max-width: 130px;"><?= htmlspecialchars($updaterName) ?></span>
                                    </span>
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-1.5 py-0 mt-0.5 align-self-start" style="font-size: 0.65rem;">
                                        <?= htmlspecialchars($updaterRole) ?>
                                    </span>
                                </div>
                            </td>
                            <td class="text-center text-nowrap">
                                <?php if (!empty($item['file_path'])): ?>
                                    <div class="d-inline-flex gap-1 align-items-center">
                                        <a class="btn btn-sm btn-outline-danger px-2 py-1 fs-7" href="<?= base_url('user/view.php?id=' . urlencode($item['id'])) ?>" target="_blank" title="Xem trực tiếp file ở tab mới">
                                            <i class="bi bi-eye me-1"></i>Xem file
                                        </a>
                                        <a class="btn btn-sm btn-light border text-secondary px-2 py-1 fs-7" href="<?= base_url('user/download.php?id=' . urlencode($item['id'])) ?>" title="Tải file về máy">
                                            <i class="bi bi-download"></i>
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <span class="badge bg-light text-muted border px-2 py-1 fs-7">Chưa có file</span>
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
                                            title="<?= ((int)($item['status_raw'] ?? 1) === 1) ? 'Đang hoạt động (Hiển thị cho Người dùng) - Bấm để chuyển sang Không hoạt động (Ẩn)' : 'Không hoạt động (Ẩn khỏi Người dùng) - Bấm để chuyển sang Đang hoạt động (Hiển thị)' ?>">
                                            <?php if ((int)($item['status_raw'] ?? 1) === 1): ?>
                                                 <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 fs-7 status-badge" style="cursor: pointer;">
                                                    <i class="bi bi-toggle-on fs-6 me-1"></i>Đang hoạt động
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1 fs-7 status-badge" style="cursor: pointer;">
                                                    <i class="bi bi-toggle-off fs-6 me-1"></i>Không hoạt động
                                                </span>
                                            <?php endif; ?>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <?php if ((int)($item['status_raw'] ?? 1) === 1): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 fs-7" title="Hiển thị cho Người dùng xem">
                                            <i class="bi bi-eye-fill me-1"></i>Đang hoạt động
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1 fs-7" title="Chỉ Quản trị viên thấy, ẩn với Người dùng">
                                            <i class="bi bi-eye-slash-fill me-1"></i>Không hoạt động
                                        </span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td class="text-end text-nowrap">
                                <div class="d-inline-flex gap-1">
                                    <button type="button" class="btn btn-sm btn-outline-info btn-view-evidence-detail"
                                        data-id="<?= htmlspecialchars($item['code']) ?>"
                                        data-name="<?= htmlspecialchars($item['name']) ?>"
                                        data-so-hieu="<?= htmlspecialchars($item['so_hieu'] ?? '') ?>"
                                        data-date="<?= htmlspecialchars($item['issue_date'] ?? '') ?>"
                                        data-date-formatted="<?= htmlspecialchars($formattedDate) ?>"
                                        data-updated="<?= htmlspecialchars($formattedUpdated) ?>"
                                        data-user="<?= htmlspecialchars($updaterName) ?>"
                                        data-user-role="<?= htmlspecialchars($updaterRole) ?>"
                                        data-username="<?= htmlspecialchars($item['username'] ?? '') ?>"
                                        data-criterion="<?= htmlspecialchars($item['ma_tieu_chi'] ?? '') ?>"
                                        data-criterion-name="<?= htmlspecialchars($item['criterion_name'] ?? '') ?>"
                                        data-standard="<?= htmlspecialchars($item['ma_tieu_chuan'] ?? '') ?>"
                                        data-standard-name="<?= htmlspecialchars($item['standard_name'] ?? '') ?>"
                                        data-set="<?= htmlspecialchars($item['ma_bo_tieu_chuan'] ?? '') ?>"
                                        data-set-name="<?= htmlspecialchars($item['set_name'] ?? '') ?>"
                                        data-file="<?= htmlspecialchars($item['file_path'] ?? '') ?>"
                                        data-file-name="<?= htmlspecialchars(basename($item['file_path'] ?? '')) ?>"
                                        data-status="<?= (int)($item['status_raw'] ?? 1) ?>"
                                        data-view-url="<?= base_url('user/view.php?id=' . urlencode($item['id'])) ?>"
                                        data-download-url="<?= base_url('user/download.php?id=' . urlencode($item['id'])) ?>"
                                        title="Xem chi tiết minh chứng">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                    <?php if (current_role() === 'admin'): ?>
                                        <button type="button" class="btn btn-sm btn-outline-primary btn-edit-evidence-item"
                                            data-id="<?= htmlspecialchars($item['code']) ?>"
                                            data-name="<?= htmlspecialchars($item['name']) ?>"
                                            data-so-hieu="<?= htmlspecialchars($item['so_hieu'] ?? '') ?>"
                                            data-date="<?= htmlspecialchars($item['issue_date'] ?? '') ?>"
                                            data-updated="<?= htmlspecialchars($formattedUpdated) ?>"
                                            data-user="<?= htmlspecialchars($updaterName) ?>"
                                            data-user-role="<?= htmlspecialchars($updaterRole) ?>"
                                            data-username="<?= htmlspecialchars($item['username'] ?? '') ?>"
                                            data-criterion="<?= htmlspecialchars($item['ma_tieu_chi'] ?? '') ?>"
                                            data-file="<?= htmlspecialchars($item['file_path'] ?? '') ?>"
                                            data-file-name="<?= htmlspecialchars(basename($item['file_path'] ?? '')) ?>"
                                            data-status="<?= (int)($item['status_raw'] ?? 1) ?>"
                                            title="Sửa minh chứng">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <form method="post" class="d-inline" data-confirm-form="Bạn có chắc chắn muốn xóa hoàn toàn minh chứng <?= htmlspecialchars($item['code']) ?> và tệp đính kèm khỏi hệ thống? Thao tác này sẽ xóa vĩnh viễn và không thể khôi phục.">
                                            <input type="hidden" name="action" value="delete_evidence">
                                            <input type="hidden" name="id" value="<?= htmlspecialchars($item['id']) ?>">
                                            <button class="btn btn-sm btn-outline-danger" type="submit" title="Xóa hoàn toàn minh chứng"><i class="bi bi-trash"></i></button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($filteredEvidences)): ?>
                        <tr><td colspan="11" class="text-center text-secondary py-5"><i class="bi bi-inbox fs-2 d-block mb-2 text-muted"></i>Không tìm thấy dữ liệu minh chứng nào phù hợp với điều kiện lọc.</td></tr>
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
                            <label class="form-label fw-bold">Số hiệu văn bản</label>
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

                        <div class="col-md-6">
                            <label class="form-label fw-bold"><i class="bi bi-clock-history text-success me-1"></i>Ngày cập nhật (Tự động)</label>
                            <div class="p-2.5 rounded bg-light border d-flex align-items-center justify-content-between" style="min-height: 42px;">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="bi bi-calendar2-check-fill text-success"></i>
                                    <span class="fw-bold text-dark small font-monospace" id="form_display_updated_date"><?= date('d/m/Y H:i:s') ?> (GMT+7)</span>
                                </div>
                                <span class="badge bg-info-subtle text-info border border-info-subtle" style="font-size: 0.72rem;"><i class="bi bi-globe-asia-australia me-1"></i>Giờ Việt Nam</span>
                            </div>
                            <div class="form-text small text-muted">Tự động lấy theo thời gian thực tế tại Việt Nam khi lưu.</div>
                        </div>

                        <!-- Cụm chọn Tiêu chí -> Tự động hiển thị Tiêu chuẩn -->
                        <div class="col-md-6">
                            <label class="form-label fw-bold"><i class="bi bi-list-check text-success me-1"></i>Chọn Tiêu chí</label>
                            <select class="form-select" name="ma_tieu_chi" id="form_ma_tieu_chi">
                                <option value="">-- Chưa chọn tiêu chí --</option>
                                <?php 
                                $groupedCriteria = [];
                                foreach ($criteria as $cr) {
                                    $groupKey = ($cr['standard_id'] ? ($cr['standard_id'] . ' - ' . $cr['standard_name']) : 'Khác');
                                    $groupedCriteria[$groupKey][] = $cr;
                                }
                                foreach ($groupedCriteria as $grp => $crList):
                                ?>
                                    <optgroup label="<?= htmlspecialchars($grp) ?>">
                                        <?php foreach ($crList as $cr): ?>
                                            <option value="<?= htmlspecialchars($cr['id']) ?>" 
                                                    data-std-id="<?= htmlspecialchars($cr['standard_id']) ?>"
                                                    data-std-name="<?= htmlspecialchars($cr['standard_name']) ?>"
                                                    data-set-id="<?= htmlspecialchars($cr['set_id']) ?>"
                                                    data-set-name="<?= htmlspecialchars($cr['set_name']) ?>"
                                                    <?= (string)($editingEvidence['MaTieuChi'] ?? '') === (string)$cr['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($cr['id'] . ' - ' . $cr['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text small text-muted">Chọn tiêu chí đánh giá để liên kết minh chứng.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold"><i class="bi bi-diagram-3-fill text-primary me-1"></i>Tiêu chuẩn trực thuộc (Tự động)</label>
                            <div id="form_auto_standard_box" class="p-2 rounded bg-light border text-muted small" style="min-height: 38px; display: flex; align-items: center;">
                                <span id="form_auto_standard_text"><i class="bi bi-info-circle me-1"></i>Tự động hiển thị khi chọn tiêu chí bên cạnh</span>
                            </div>
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
                                <h6 class="fw-bold mb-0 text-dark">Sơ đồ phân cấp đánh giá (Cây tiêu chuẩn)</h6>
                            </div>
                            <span class="badge bg-primary-subtle text-primary small">4 Cấp độ kiểm định</span>
                        </div>
                        <div id="view_detail_hierarchy">
                            <?php if (!empty($viewingEvidence['MaTieuChi'])): ?>
                                <div class="row g-2 align-items-stretch">
                                    <div class="col-12 col-md-6 col-xl-3">
                                        <div class="p-3 rounded-3 h-100 border d-flex flex-column" style="background: #f0f7ff; border-color: #bfdbfe !important;">
                                            <div class="d-flex align-items-center gap-2 mb-2 text-primary fw-bold" style="font-size: 0.72rem; letter-spacing: 0.04em;">
                                                <i class="bi bi-collection-fill"></i> 1. BỘ TIÊU CHUẨN
                                            </div>
                                            <div class="fw-bold text-dark fs-7 flex-grow-1">
                                                <?= htmlspecialchars($viewingEvidence['set_name'] ?: ($viewingEvidence['MaBoTieuChuan'] ?? 'Bộ tiêu chuẩn chung')) ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6 col-xl-3">
                                        <div class="p-3 rounded-3 h-100 border d-flex flex-column" style="background: #f0fdf4; border-color: #bbf7d0 !important;">
                                            <div class="d-flex align-items-center gap-2 mb-2 text-success fw-bold" style="font-size: 0.72rem; letter-spacing: 0.04em;">
                                                <i class="bi bi-folder2-open"></i> 2. TIÊU CHUẨN
                                            </div>
                                            <div class="fw-bold text-dark fs-7 flex-grow-1">
                                                <?= htmlspecialchars(($viewingEvidence['MaTieuChuan'] ?? '') . ' - ' . ($viewingEvidence['standard_name'] ?? '')) ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6 col-xl-3">
                                        <div class="p-3 rounded-3 h-100 border d-flex flex-column" style="background: #fdf4ff; border-color: #f5d0fe !important;">
                                            <div class="d-flex align-items-center gap-2 mb-2 fw-bold" style="font-size: 0.72rem; letter-spacing: 0.04em; color: #9333ea;">
                                                <i class="bi bi-list-check"></i> 3. TIÊU CHÍ
                                            </div>
                                            <div class="fw-bold text-dark fs-7 flex-grow-1">
                                                <?= htmlspecialchars(($viewingEvidence['MaTieuChi'] ?? '') . ' - ' . ($viewingEvidence['criterion_name'] ?? '')) ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6 col-xl-3">
                                        <div class="p-3 rounded-3 h-100 border d-flex flex-column" style="background: #fffbeb; border-color: #fde68a !important;">
                                            <div class="d-flex align-items-center gap-2 mb-2 fw-bold" style="font-size: 0.72rem; letter-spacing: 0.04em; color: #d97706;">
                                                <i class="bi bi-file-earmark-check-fill"></i> 4. MINH CHỨNG
                                            </div>
                                            <div class="fw-bold text-dark fs-7 flex-grow-1">
                                                <span class="badge bg-warning text-dark me-1 font-monospace"><?= htmlspecialchars($viewingEvidence['MaMinhChung'] ?? '') ?></span>
                                                <span class="text-truncate d-inline-block align-middle" style="max-width: 140px;"><?= htmlspecialchars($viewingEvidence['TenMinhChung'] ?? '') ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <span class="text-muted">Chưa phân loại tiêu chuẩn / tiêu chí</span>
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
    // Helper escape chuỗi an toàn
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

    // Helper format thời gian thực tại Việt Nam (GMT+7)
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

    // Cập nhật đồng hồ thời gian thực tại Việt Nam trên form mỗi giây
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

    const selectTieuChi = document.getElementById('form_ma_tieu_chi');
    const autoStdBox = document.getElementById('form_auto_standard_box');
    const autoStdText = document.getElementById('form_auto_standard_text');

    function updateAutoStandardDisplay() {
        if (!selectTieuChi || !autoStdText) return;
        const selOption = selectTieuChi.options[selectTieuChi.selectedIndex];
        if (selOption && selOption.value) {
            const stdId = selOption.dataset.stdId || '';
            const stdName = selOption.dataset.stdName || '';
            const setId = selOption.dataset.setId || '';
            const setName = selOption.dataset.setName || '';
            
            let stdHtml = `<strong class="text-primary"><i class="bi bi-folder2-open me-1"></i>${stdId ? stdId + ' - ' : ''}${stdName}</strong>`;
            if (setId) {
                stdHtml += ` <div class="text-muted mt-1" style="font-size: 0.75rem;"><i class="bi bi-collection me-1"></i>Bộ tiêu chuẩn: ${setId}${setName ? ' - ' + setName : ''}</div>`;
            }
            autoStdText.innerHTML = stdHtml;
            if (autoStdBox) autoStdBox.className = 'p-2 rounded bg-primary-subtle border border-primary-subtle text-dark small';
        } else {
            autoStdText.innerHTML = '<span class="text-muted"><i class="bi bi-info-circle me-1"></i>Tự động hiển thị khi chọn tiêu chí bên cạnh</span>';
            if (autoStdBox) autoStdBox.className = 'p-2 rounded bg-light border text-muted small';
        }
    }

    if (selectTieuChi) {
        selectTieuChi.addEventListener('change', updateAutoStandardDisplay);
        // Initial call on page load if editing
        updateAutoStandardDisplay();
    }

    // Reset Form Modal on open/close for Add New
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
            
            const timeDisplay = document.getElementById('form_display_updated_date');
            if (timeDisplay) {
                timeDisplay.textContent = getVietnamTimeFormatted() + ' (GMT+7)';
            }
            if (selectTieuChi) selectTieuChi.value = '';
            updateAutoStandardDisplay();
        });
    }

    // Helper function to populate and open Edit Form
    function openEditModal(data) {
        document.getElementById('evidenceFormModalLabel').innerHTML = '<i class="bi bi-pencil-square fs-5 text-white me-2"></i>Cập nhật thông tin Minh chứng';
        document.getElementById('form_evidence_id').value = data.id || '';
        document.getElementById('form_ma_minh_chung').value = data.id || '';
        document.getElementById('form_so_hieu').value = data.soHieu || '';
        document.getElementById('form_ten_minh_chung').value = data.name || '';
        document.getElementById('form_ngay_ban_hanh').value = data.date || '';
        document.getElementById('form_trang_thai').value = data.status || '1';
        document.getElementById('form_evidence_file').value = '';

        const timeDisplay = document.getElementById('form_display_updated_date');
        if (timeDisplay) {
            timeDisplay.textContent = getVietnamTimeFormatted() + ' (GMT+7)';
        }

        if (selectTieuChi) {
            selectTieuChi.value = data.criterion || '';
            updateAutoStandardDisplay();
        }

        const helpText = document.getElementById('form_file_help_text');
        if (data.fileName) {
            helpText.innerHTML = '<span class="text-success"><i class="bi bi-file-earmark-check me-1"></i>Tệp tin hiện tại: <strong>' + data.fileName + '</strong></span>. Chọn tệp mới để ghi đè (nếu muốn thay đổi).';
        } else {
            helpText.innerHTML = 'Hỗ trợ định dạng: <strong>PDF, PNG, JPG, JPEG, WEBP, DOCX</strong> (Tối đa 100MB).';
        }

        if (modalForm) modalForm.show();
    }

    // Edit Item Click in Table
    document.querySelectorAll('.btn-edit-evidence-item').forEach(btn => {
        btn.addEventListener('click', function () {
            openEditModal({
                id: this.dataset.id || '',
                name: this.dataset.name || '',
                soHieu: this.dataset.soHieu || '',
                date: this.dataset.date || '',
                criterion: this.dataset.criterion || '',
                fileName: this.dataset.fileName || '',
                status: this.dataset.status || '1'
            });
        });
    });

    let currentViewingData = null;

    // View Item Click
    document.querySelectorAll('.btn-view-evidence-detail').forEach(btn => {
        btn.addEventListener('click', function () {
            const id = this.dataset.id || '';
            const name = this.dataset.name || '';
            const soHieu = this.dataset.soHieu || '';
            const date = this.dataset.dateFormatted || (this.dataset.date || '-');
            const rawDate = this.dataset.date || '';
            const updated = this.dataset.updated || '-';
            const user = this.dataset.user || 'Quản trị viên';
            const criterion = this.dataset.criterion || '';
            const criterionName = this.dataset.criterionName || '';
            const standard = this.dataset.standard || '';
            const standardName = this.dataset.standardName || '';
            const setName = this.dataset.setName || '';
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
                criterion: criterion,
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
            if (criterion || standard) {
                hierEl.innerHTML = `
                    <div class="row g-2 align-items-stretch">
                        <div class="col-12 col-md-6 col-xl-3">
                            <div class="p-3 rounded-3 h-100 border d-flex flex-column" style="background: #f0f7ff; border-color: #bfdbfe !important;">
                                <div class="d-flex align-items-center gap-2 mb-2 text-primary fw-bold" style="font-size: 0.72rem; letter-spacing: 0.04em;">
                                    <i class="bi bi-collection-fill"></i> 1. BỘ TIÊU CHUẨN
                                </div>
                                <div class="fw-bold text-dark fs-7 flex-grow-1" title="${escapeHtml(setName || '')}">
                                    ${escapeHtml(setName || 'Bộ tiêu chuẩn chung')}
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-md-6 col-xl-3">
                            <div class="p-3 rounded-3 h-100 border d-flex flex-column" style="background: #f0fdf4; border-color: #bbf7d0 !important;">
                                <div class="d-flex align-items-center gap-2 mb-2 text-success fw-bold" style="font-size: 0.72rem; letter-spacing: 0.04em;">
                                    <i class="bi bi-folder2-open"></i> 2. TIÊU CHUẨN
                                </div>
                                <div class="fw-bold text-dark fs-7 flex-grow-1" title="${escapeHtml((standard ? standard + ' - ' : '') + (standardName || ''))}">
                                    ${standard ? `<span class="badge bg-success-subtle text-success me-1 font-monospace">${escapeHtml(standard)}</span>` : ''}${escapeHtml(standardName || 'Chưa phân loại')}
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-md-6 col-xl-3">
                            <div class="p-3 rounded-3 h-100 border d-flex flex-column" style="background: #fdf4ff; border-color: #f5d0fe !important;">
                                <div class="d-flex align-items-center gap-2 mb-2 fw-bold" style="font-size: 0.72rem; letter-spacing: 0.04em; color: #9333ea;">
                                    <i class="bi bi-list-check"></i> 3. TIÊU CHÍ
                                </div>
                                <div class="fw-bold text-dark fs-7 flex-grow-1" title="${escapeHtml((criterion ? criterion + ' - ' : '') + (criterionName || ''))}">
                                    ${criterion ? `<span class="badge me-1 font-monospace" style="background: #f3e8ff; color: #9333ea;">${escapeHtml(criterion)}</span>` : ''}${escapeHtml(criterionName || 'Chưa phân loại')}
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-md-6 col-xl-3">
                            <div class="p-3 rounded-3 h-100 border d-flex flex-column" style="background: #fffbeb; border-color: #fde68a !important;">
                                <div class="d-flex align-items-center gap-2 mb-2 fw-bold" style="font-size: 0.72rem; letter-spacing: 0.04em; color: #d97706;">
                                    <i class="bi bi-file-earmark-check-fill"></i> 4. MINH CHỨNG
                                </div>
                                <div class="fw-bold text-dark fs-7 flex-grow-1">
                                    <span class="badge bg-warning text-dark me-1 font-monospace">${escapeHtml(id)}</span>
                                    <span class="text-truncate d-inline-block align-middle" style="max-width: 140px;" title="${escapeHtml(name)}">${escapeHtml(name)}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
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

    // Xử lý chuyển đổi nhanh trạng thái minh chứng (Đang hoạt động <-> Không hoạt động) trực tiếp trên bảng
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

                    // Cập nhật lại data-status trên nút Xem và Sửa của hàng này
                    const row = form.closest('tr');
                    if (row) {
                        const viewBtn = row.querySelector('.btn-view-evidence-detail');
                        if (viewBtn) viewBtn.dataset.status = data.new_status;
                        const editBtn = row.querySelector('.btn-edit-evidence-item');
                        if (editBtn) editBtn.dataset.status = data.new_status;
                    }

                    // Cập nhật lại số lượng trên thẻ thống kê Stat Cards
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

                    // Hiển thị toast thông báo nhanh
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

    // Helper hiển thị floating toast đẹp mắt
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
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
