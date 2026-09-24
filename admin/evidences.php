<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/helpers.php';
require_roles(['admin', 'user']);
require_once __DIR__ . '/../config/database.php';

if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    require_once __DIR__ . '/../includes/data.php';

    $selectedSet      = trim($_GET['set'] ?? '');
    $selectedStandard = trim($_GET['standard'] ?? '');
    $selectedCriterion= trim($_GET['criterion'] ?? '');
    $selectedDept     = trim($_GET['department'] ?? '');
    $searchKeyword    = trim($_GET['search'] ?? $_GET['q'] ?? '');

    if ($selectedSet !== '') {
        $evidences = array_filter($evidences, fn($ev) => ($ev['set_code'] ?? '') === $selectedSet);
    }
    if ($selectedStandard !== '') {
        $evidences = array_filter($evidences, fn($ev) => ($ev['standard_code'] ?? '') === $selectedStandard);
    }
    if ($selectedCriterion !== '') {
        $evidences = array_filter($evidences, fn($ev) => ($ev['criterion_code'] ?? '') === $selectedCriterion);
    }
    if ($selectedDept !== '') {
        $evidences = array_filter($evidences, fn($ev) => ($ev['department'] ?? '') === $selectedDept);
    }
    if ($searchKeyword !== '') {
        $evidences = array_filter($evidences, fn($ev) => 
            search_contains($ev['code'], $searchKeyword) ||
            search_contains($ev['name'], $searchKeyword) ||
            search_contains($ev['issuing_body'], $searchKeyword)
        );
    }

    $exportData = [];
    foreach ($evidences as $ev) {
        $exportData[] = [
            'code'           => $ev['code'],
            'name'           => $ev['name'],
            'set_code'       => $ev['set_code'] ?? '-',
            'standard_code'  => $ev['standard_code'] ?? '-',
            'criterion_code' => $ev['criterion_code'] ?? '-',
            'issuing_body'   => $ev['issuing_body'] ?? '-',
            'issue_date'     => $ev['issue_date'] ?? '-',
            'department'     => $ev['department'] ?? '-',
            'files_count'    => count($ev['files'] ?? []),
        ];
    }

    $columns = [
        ['key' => 'code', 'label' => 'Mã minh chứng', 'align' => 'center', 'width' => '130px'],
        ['key' => 'name', 'label' => 'Tên minh chứng', 'align' => 'left', 'width' => '280px'],
        ['key' => 'set_code', 'label' => 'Bộ TC', 'align' => 'center', 'width' => '90px'],
        ['key' => 'standard_code', 'label' => 'Tiêu chuẩn', 'align' => 'center', 'width' => '90px'],
        ['key' => 'criterion_code', 'label' => 'Tiêu chí', 'align' => 'center', 'width' => '90px'],
        ['key' => 'issuing_body', 'label' => 'Nơi ban hành', 'align' => 'left', 'width' => '180px'],
        ['key' => 'issue_date', 'label' => 'Ngày ban hành', 'align' => 'center', 'width' => '110px'],
        ['key' => 'department', 'label' => 'Đơn vị quản lý', 'align' => 'left', 'width' => '180px'],
        ['key' => 'files_count', 'label' => 'Số tệp', 'align' => 'center', 'width' => '80px'],
    ];

    export_to_excel('danh_sach_minh_chung_' . date('Ymd_His') . '.xls', 'DANH SÁCH MINH CHỨNG KIỂM ĐỊNH', $columns, $exportData);
}

$pdo = db();
$success = '';
$error = '';
$maxUploadMb = 100;
$maxUploadBytes = $maxUploadMb * 1024 * 1024;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (current_role() !== 'admin') {
        $error = 'Tài khoản người dùng chỉ có quyền Xem và Tải minh chứng xuống.';
    } elseif (($_POST['action'] ?? '') === 'delete_evidence') {
    $evidenceId = trim($_POST['id'] ?? '');

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare('SELECT TepTin FROM MinhChung WHERE MaMinhChung = :id');
        $stmt->execute(['id' => $evidenceId]);
        $filePath = $stmt->fetchColumn();

        $pdo->prepare('DELETE FROM download_logs WHERE MaMinhChung = :id')->execute(['id' => $evidenceId]);
        $pdo->prepare('DELETE FROM MinhChung WHERE MaMinhChung = :id')->execute(['id' => $evidenceId]);

        log_activity('xoa', 'minh_chung', 0, $evidenceId);

        $pdo->commit();

        if ($filePath) {
            $fullPath = realpath(__DIR__ . '/../' . $filePath);
            if ($fullPath && is_file($fullPath)) {
                @unlink($fullPath);
            }
        }

        $success = 'Xóa minh chứng thành công.';
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = 'Không thể xóa minh chứng: ' . $exception->getMessage();
    }
    } elseif (($_POST['action'] ?? '') === 'save_evidence') {
    $rawId        = trim($_POST['id'] ?? '');
    $maMinhChung  = trim($_POST['ma_minh_chung'] ?? '');
    $title        = trim($_POST['ten_minh_chung'] ?? '');
    $description  = trim($_POST['mo_ta'] ?? '');
    $academicYear = trim($_POST['nam_hoc'] ?? '');
    $typeId       = (int) ($_POST['ma_loai'] ?? 0) ?: null;
    $criteriaId   = trim($_POST['ma_tieu_chi'] ?? '');
    $userId       = trim($_POST['ma_nguoi_dung'] ?? '') ?: ($_SESSION['user_id'] ?? 'ND001');
    $status       = (int) ($_POST['trang_thai'] ?? 1);

    $file = $_FILES['evidence_file'] ?? null;

    if ($title === '' || $criteriaId === '') {
        $error = 'Vui lòng nhập tên minh chứng và chọn mã tiêu chí.';
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
                if ($maMinhChung === '') {
                    throw new RuntimeException('Vui lòng nhập Mã minh chứng.');
                }
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
                    $stmt = $pdo->prepare('UPDATE MinhChung SET MaMinhChung = :new_code, TenMinhChung = :title, MoTa = :desc, TepTin = :file, NamHoc = :year, TrangThai = :status, MaLoai = :type_id, MaTieuChi = :criteria_id, MaNguoiDung = :user_id WHERE MaMinhChung = :old_code');
                    $stmt->execute([
                        'new_code'    => $maMinhChung,
                        'title'       => $title,
                        'desc'        => $description,
                        'file'        => $relativePath,
                        'year'        => $academicYear,
                        'status'      => $status,
                        'type_id'     => $typeId,
                        'criteria_id' => $criteriaId,
                        'user_id'     => $userId,
                        'old_code'    => $rawId,
                    ]);
                } else {
                    $stmt = $pdo->prepare('UPDATE MinhChung SET MaMinhChung = :new_code, TenMinhChung = :title, MoTa = :desc, NamHoc = :year, TrangThai = :status, MaLoai = :type_id, MaTieuChi = :criteria_id, MaNguoiDung = :user_id WHERE MaMinhChung = :old_code');
                    $stmt->execute([
                        'new_code'    => $maMinhChung,
                        'title'       => $title,
                        'desc'        => $description,
                        'year'        => $academicYear,
                        'status'      => $status,
                        'type_id'     => $typeId,
                        'criteria_id' => $criteriaId,
                        'user_id'     => $userId,
                        'old_code'    => $rawId,
                    ]);
                }
                if ($maMinhChung !== $rawId) {
                    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
                }
                log_activity('cap_nhat', 'minh_chung', 0, $maMinhChung . ' - ' . $title);
                $success = 'Cập nhật minh chứng thành công.';
            } else {
                if ($maMinhChung === '') {
                    throw new RuntimeException('Vui lòng nhập Mã minh chứng.');
                }
                $chk = $pdo->prepare('SELECT COUNT(*) FROM MinhChung WHERE MaMinhChung = :code');
                $chk->execute(['code' => $maMinhChung]);
                if ((int) $chk->fetchColumn() > 0) {
                    throw new RuntimeException('Mã minh chứng "' . $maMinhChung . '" đã tồn tại. Vui lòng nhập mã khác.');
                }

                $stmt = $pdo->prepare('INSERT INTO MinhChung (MaMinhChung, TenMinhChung, MoTa, TepTin, NamHoc, TrangThai, MaLoai, MaTieuChi, MaNguoiDung) VALUES (:code, :title, :desc, :file, :year, :status, :type_id, :criteria_id, :user_id)');
                $stmt->execute([
                    'code'        => $maMinhChung,
                    'title'       => $title,
                    'desc'        => $description,
                    'file'        => $relativePath,
                    'year'        => $academicYear,
                    'status'      => $status,
                    'type_id'     => $typeId,
                    'criteria_id' => $criteriaId,
                    'user_id'     => $userId,
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

require_once __DIR__ . '/../includes/data.php';

$isCreatingEvidence = isset($_GET['create']);
$editId = $isCreatingEvidence ? '' : trim($_GET['edit'] ?? '');
$viewId = trim($_GET['view'] ?? '');

$editingEvidence = null;
if ($editId !== '') {
    $stmt = $pdo->prepare('SELECT * FROM MinhChung WHERE MaMinhChung = :id LIMIT 1');
    $stmt->execute(['id' => $editId]);
    $editingEvidence = $stmt->fetch();
}

$viewingEvidence = null;
if ($viewId !== '') {
    $stmt = $pdo->prepare('SELECT * FROM MinhChung WHERE MaMinhChung = :id LIMIT 1');
    $stmt->execute(['id' => $viewId]);
    $viewingEvidence = $stmt->fetch();
}

$searchKeyword = trim($_GET['search'] ?? $_GET['q'] ?? '');
if ($searchKeyword !== '') {
    $evidences = array_filter($evidences, function ($item) use ($searchKeyword) {
        $haystack = implode(' ', [
            $item['code'] ?? '',
            $item['name'] ?? '',
            $item['description'] ?? '',
            $item['file_path'] ?? '',
            $item['year'] ?? '',
            $item['type_code'] ?? '',
            $item['criteria_code'] ?? '',
            $item['user_code'] ?? '',
            $item['user_name'] ?? '',
        ]);

        return search_contains($haystack, $searchKeyword);
    });
}

$pageTitle = page_title(current_role() === 'admin' ? 'Quản lý minh chứng' : 'CSDL Minh chứng');
$heading   = current_role() === 'admin' ? 'Quản lý minh chứng' : 'CSDL Minh chứng';
include __DIR__ . '/../includes/header.php';
?>
<?php if (($editingEvidence || $isCreatingEvidence) && !$success && !$error): ?><script>document.body.dataset.autoOpenModal = 'evidenceFormModal';</script><?php endif; ?>
<?php if ($viewingEvidence): ?><script>document.body.dataset.autoOpenModal = 'evidenceViewModal';</script><?php endif; ?>
<div class="panel mb-4">
    <form class="row g-3 align-items-end" method="get">
        <div class="col-md-9">
            <label class="form-label">Từ khóa tìm kiếm</label>
            <input class="form-control" name="q" value="<?= htmlspecialchars($searchKeyword) ?>" placeholder="Nhập mã, tên minh chứng, mô tả, tệp tin, năm học...">
        </div>
        <div class="col-md-3">
            <button class="btn btn-primary w-100 text-nowrap" type="submit"><i class="bi bi-search me-1"></i> Tìm kiếm</button>
        </div>
    </form>
</div>

<?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="row g-4 evidences-layout">
    <div class="col-12">
        <div class="panel evidences-list-panel">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="h5 mb-0">Danh mục minh chứng</h2>
                <div class="d-flex gap-2">
                    <?php if (current_role() === 'admin'): ?>
                        <a class="btn btn-primary" href="<?= base_url('admin/evidences.php?create=1') ?>"><i class="bi bi-plus-circle me-1"></i> Thêm mới</a>
                    <?php endif; ?>
                    <a class="btn btn-outline-secondary" id="exportExcelBtn" href="<?= base_url('admin/evidences.php?export=excel' . ($searchKeyword ? '&search=' . urlencode($searchKeyword) : '')) ?>"><i class="bi bi-file-earmark-spreadsheet me-1"></i> Xuất Excel</a>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table" data-page-size="10">
                    <thead>
                    <tr>
                        <th class="text-nowrap" style="width: 110px;">Mã minh chứng</th>
                        <th style="min-width: 180px; max-width: 240px;">Tên minh chứng</th>
                        <th style="min-width: 180px; max-width: 240px;">Mô tả</th>
                        <th class="text-nowrap" style="min-width: 110px; max-width: 130px;">Tệp tin</th>
                        <th class="text-nowrap" style="width: 80px;">Năm học</th>
                        <th class="text-nowrap" style="width: 120px;">Ngày cập nhật</th>
                        <th class="text-nowrap" style="width: 110px;">Trạng thái</th>
                        <th class="text-nowrap" style="width: 85px;">Mã tiêu chí</th>
                        <th class="text-nowrap" style="width: 85px;">Mã người dùng</th>
                        <th class="text-end text-nowrap action-cell" style="width: 100px;">Thao tác</th>
                    </tr>
                    </thead>
                    <tbody id="evidencesTableBody">
                    <?php foreach ($evidences as $item): ?>
                        <tr>
                            <td class="fw-bold text-nowrap"><?= htmlspecialchars($item['code']) ?></td>
                            <td class="fw-semibold" style="min-width: 180px; max-width: 240px;">
                                <div class="line-clamp-2" title="<?= htmlspecialchars($item['name']) ?>">
                                    <?= htmlspecialchars($item['name']) ?>
                                </div>
                            </td>
                            <td style="min-width: 180px; max-width: 240px;">
                                <div class="line-clamp-2 text-secondary" title="<?= htmlspecialchars($item['description']) ?>">
                                    <?= htmlspecialchars($item['description'] ?: '-') ?>
                                </div>
                            </td>
                            <td class="text-nowrap">
                                <?php if (!empty($item['file_path'])): ?>
                                    <code class="d-inline-block text-truncate align-middle" style="max-width: 130px;" title="<?= htmlspecialchars(basename($item['file_path'])) ?>">
                                        <?= htmlspecialchars(basename($item['file_path'])) ?>
                                    </code>
                                <?php else: ?>
                                    <span class="text-secondary small">Không có</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-nowrap"><?= htmlspecialchars($item['year'] ?: '-') ?></td>
                            <td class="text-nowrap small text-secondary"><?= htmlspecialchars($item['updated'] ?: '-') ?></td>
                            <td class="text-nowrap">
                                <?php if ((int) $item['status_raw'] === 1): ?>
                                    <span class="badge bg-success">Đang hoạt động</span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark">Ngưng áp dụng</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-nowrap"><span class="badge bg-info text-dark"><?= htmlspecialchars($item['criteria_code']) ?></span></td>
                            <td class="text-nowrap"><span class="badge bg-light text-dark border"><?= htmlspecialchars($item['user_code']) ?></span></td>
                            <td class="text-end action-cell">
                                <div class="action-buttons">
                                    <a class="btn btn-sm btn-outline-secondary" href="?view=<?= urlencode($item['id']) ?>" title="Xem thông tin minh chứng"><i class="bi bi-eye"></i></a>
                                    <?php if (!empty($item['file_path'])): ?>
                                        <a class="btn btn-sm btn-outline-success" href="<?= base_url('user/download.php?id=' . urlencode($item['id'])) ?>" title="Tải tài liệu về"><i class="bi bi-download"></i></a>
                                    <?php endif; ?>
                                    <?php if (current_role() === 'admin'): ?>
                                        <a class="btn btn-sm btn-outline-primary" href="?edit=<?= urlencode($item['id']) ?>" title="Sửa"><i class="bi bi-pencil"></i></a>
                                        <form method="post" class="d-inline" data-confirm-form="Bạn chắc chắn muốn xóa minh chứng này?">
                                            <input type="hidden" name="action" value="delete_evidence">
                                            <input type="hidden" name="id" value="<?= htmlspecialchars($item['id']) ?>">
                                            <button class="btn btn-sm btn-outline-danger" type="submit" title="Xóa"><i class="bi bi-trash"></i></button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$evidences): ?>
                        <tr><td colspan="10" class="text-center text-secondary py-4">Không tìm thấy minh chứng phù hợp.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade management-form-modal" id="evidenceFormModal" tabindex="-1" aria-labelledby="evidenceFormModalLabel" aria-hidden="true" <?= ($editingEvidence || $isCreatingEvidence) ? 'data-auto-open-modal' : '' ?>>
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5" id="evidenceFormModalLabel"><?= $editingEvidence ? 'Sửa minh chứng' : 'Thêm mới minh chứng' ?></h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <div class="modal-body">
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="save_evidence">
                <input type="hidden" name="id" value="<?= htmlspecialchars($editingEvidence['MaMinhChung'] ?? '') ?>">

                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label class="form-label">Mã minh chứng <span class="text-danger">*</span></label>
                        <input class="form-control" name="ma_minh_chung" value="<?= htmlspecialchars($editingEvidence['MaMinhChung'] ?? '') ?>" placeholder="VD: MC01 hoặc MC-01" required>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">Tên minh chứng <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="ten_minh_chung" rows="2" placeholder="Nhập tên minh chứng" required><?= htmlspecialchars($editingEvidence['TenMinhChung'] ?? '') ?></textarea>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Mô tả</label>
                    <textarea class="form-control" name="mo_ta" rows="2" placeholder="Nhập mô tả minh chứng"><?= htmlspecialchars($editingEvidence['MoTa'] ?? '') ?></textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label">Tệp tin đính kèm (Tối đa 100MB)</label>
                    <input class="form-control" type="file" name="evidence_file">
                    <?php if (!empty($editingEvidence['TepTin'])): ?>
                        <div class="form-text mt-1 text-truncate">
                            Tệp tin hiện tại: <code><?= htmlspecialchars($editingEvidence['TepTin']) ?></code>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Năm học</label>
                        <input class="form-control" name="nam_hoc" value="<?= htmlspecialchars($editingEvidence['NamHoc'] ?? '') ?>" placeholder="Ví dụ: 2025-2026">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Trạng thái</label>
                        <select class="form-select" name="trang_thai">
                            <option value="1" <?= (int) ($editingEvidence['TrangThai'] ?? 1) === 1 ? 'selected' : '' ?>>Đang hoạt động</option>
                            <option value="0" <?= (int) ($editingEvidence['TrangThai'] ?? 1) === 0 ? 'selected' : '' ?>>Ngưng áp dụng</option>
                        </select>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Mã tiêu chí <span class="text-danger">*</span></label>
                        <select class="form-select" name="ma_tieu_chi" required>
                            <?php foreach ($criteria as $item): ?>
                                <option value="<?= htmlspecialchars($item['id']) ?>" <?= (string) ($editingEvidence['MaTieuChi'] ?? '') === (string) $item['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($item['code'] . ' - ' . $item['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Mã người dùng <span class="text-danger">*</span></label>
                        <select class="form-select" name="ma_nguoi_dung" required>
                            <?php foreach ($users as $u): ?>
                                <option value="<?= htmlspecialchars($u['id']) ?>" <?= (string) ($editingEvidence['MaNguoiDung'] ?? $_SESSION['user_id'] ?? 'ND001') === (string) $u['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($u['code'] . ' - ' . $u['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="d-flex gap-2 justify-content-end">
                    <?php if ($editingEvidence): ?><a class="btn btn-outline-secondary" href="<?= base_url('admin/evidences.php') ?>">Hủy sửa</a><?php else: ?><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Hủy</button><?php endif; ?>
                    <button class="btn btn-primary" type="submit">
                        <i class="bi bi-save me-1"></i> Lưu minh chứng
                    </button>
                </div>
            </form>
            </div>
        </div>
    </div>
</div>
<div class="modal fade management-form-modal" id="evidenceViewModal" tabindex="-1" aria-labelledby="evidenceViewModalLabel" aria-hidden="true" <?= $viewingEvidence ? 'data-auto-open-modal' : '' ?>>
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5" id="evidenceViewModalLabel">
                    <i class="bi bi-file-earmark-text text-primary me-1"></i> Thông tin minh chứng: <code><?= htmlspecialchars($viewingEvidence['MaMinhChung'] ?? '') ?></code>
                </h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-bold">Mã minh chứng</label>
                    <input class="form-control bg-light" value="<?= htmlspecialchars($viewingEvidence['MaMinhChung'] ?? '') ?>" readonly>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Tên minh chứng</label>
                    <textarea class="form-control bg-light" rows="2" readonly><?= htmlspecialchars($viewingEvidence['TenMinhChung'] ?? '') ?></textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Mô tả</label>
                    <textarea class="form-control bg-light" rows="2" readonly><?= htmlspecialchars($viewingEvidence['MoTa'] ?? 'Chưa có mô tả') ?></textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Tệp tin</label>
                    <div class="d-flex align-items-center gap-2">
                        <input class="form-control bg-light" value="<?= htmlspecialchars(basename($viewingEvidence['TepTin'] ?? 'Không có tệp')) ?>" readonly>
                        <?php if (!empty($viewingEvidence['TepTin'])): ?>
                            <a class="btn btn-outline-success text-nowrap" href="<?= base_url('user/download.php?id=' . urlencode($viewingEvidence['MaMinhChung'] ?? '')) ?>" title="Tải tệp tin về">
                                <i class="bi bi-download me-1"></i> Tải về
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Năm học</label>
                        <input class="form-control bg-light" value="<?= htmlspecialchars($viewingEvidence['NamHoc'] ?? '-') ?>" readonly>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Ngày cập nhật</label>
                        <input class="form-control bg-light" value="<?= htmlspecialchars($viewingEvidence['NgayCapNhat'] ? date('d/m/Y H:i', strtotime($viewingEvidence['NgayCapNhat'])) : '-') ?>" readonly>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Trạng thái</label>
                        <input class="form-control bg-light fw-semibold <?= (int)($viewingEvidence['TrangThai'] ?? 1) === 1 ? 'text-success' : 'text-warning' ?>" value="<?= (int)($viewingEvidence['TrangThai'] ?? 1) === 1 ? 'Đang hoạt động' : 'Ngưng áp dụng' ?>" readonly>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Mã loại minh chứng</label>
                        <input class="form-control bg-light" value="<?= htmlspecialchars($viewingEvidence['MaLoai'] ? ('LMC' . str_pad($viewingEvidence['MaLoai'], 2, '0', STR_PAD_LEFT)) : 'N/A') ?>" readonly>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Mã tiêu chí</label>
                        <input class="form-control bg-light" value="<?= htmlspecialchars($viewingEvidence['MaTieuChi'] ? ('TC' . str_pad($viewingEvidence['MaTieuChi'], 2, '0', STR_PAD_LEFT)) : 'N/A') ?>" readonly>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Mã người dùng</label>
                        <input class="form-control bg-light" value="<?= htmlspecialchars($viewingEvidence['MaNguoiDung'] ? ('ND' . str_pad($viewingEvidence['MaNguoiDung'], 3, '0', STR_PAD_LEFT)) : 'N/A') ?>" readonly>
                    </div>
                </div>

                <div class="d-flex gap-2 justify-content-end mt-4">
                    <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Đóng</button>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
