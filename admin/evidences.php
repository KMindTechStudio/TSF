<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/helpers.php';
require_roles(['admin', 'user']);
require_once __DIR__ . '/../config/database.php';

$pdo = db();
$success = '';
$error = '';
$maxUploadMb = 100;
$maxUploadBytes = $maxUploadMb * 1024 * 1024;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (current_role() !== 'admin') {
        $error = 'Tài khoản người dùng chỉ có quyền Xem và Tải minh chứng xuống.';
    } elseif (($_POST['action'] ?? '') === 'delete_evidence') {
    $evidenceId = (int) ($_POST['id'] ?? 0);

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare('SELECT TepTin FROM MinhChung WHERE MaMinhChung = :id');
        $stmt->execute(['id' => $evidenceId]);
        $filePath = $stmt->fetchColumn();

        $pdo->prepare('DELETE FROM download_logs WHERE MaMinhChung = :id')->execute(['id' => $evidenceId]);
        $pdo->prepare('DELETE FROM MinhChung WHERE MaMinhChung = :id')->execute(['id' => $evidenceId]);

        log_activity('xoa', 'minh_chung', $evidenceId, 'MC' . str_pad($evidenceId, 2, '0', STR_PAD_LEFT));

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
    $evidenceId   = (int) ($_POST['id'] ?? 0);
    $title        = trim($_POST['ten_minh_chung'] ?? '');
    $description  = trim($_POST['mo_ta'] ?? '');
    $academicYear = trim($_POST['nam_hoc'] ?? '');
    $typeId       = (int) ($_POST['ma_loai'] ?? 0) ?: null;
    $criteriaId   = (int) ($_POST['ma_tieu_chi'] ?? 0);
    $userId       = (int) ($_POST['ma_nguoi_dung'] ?? 0) ?: ($_SESSION['user_id'] ?? 1);
    $status       = (int) ($_POST['trang_thai'] ?? 1);

    $file = $_FILES['evidence_file'] ?? null;

    if ($title === '' || $criteriaId <= 0) {
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

            if ($evidenceId > 0) {
                if ($relativePath) {
                    $stmt = $pdo->prepare('UPDATE MinhChung SET TenMinhChung = :title, MoTa = :desc, TepTin = :file, NamHoc = :year, TrangThai = :status, MaLoai = :type_id, MaTieuChi = :criteria_id, MaNguoiDung = :user_id WHERE MaMinhChung = :id');
                    $stmt->execute([
                        'title'       => $title,
                        'desc'        => $description,
                        'file'        => $relativePath,
                        'year'        => $academicYear,
                        'status'      => $status,
                        'type_id'     => $typeId,
                        'criteria_id' => $criteriaId,
                        'user_id'     => $userId,
                        'id'          => $evidenceId,
                    ]);
                } else {
                    $stmt = $pdo->prepare('UPDATE MinhChung SET TenMinhChung = :title, MoTa = :desc, NamHoc = :year, TrangThai = :status, MaLoai = :type_id, MaTieuChi = :criteria_id, MaNguoiDung = :user_id WHERE MaMinhChung = :id');
                    $stmt->execute([
                        'title'       => $title,
                        'desc'        => $description,
                        'year'        => $academicYear,
                        'status'      => $status,
                        'type_id'     => $typeId,
                        'criteria_id' => $criteriaId,
                        'user_id'     => $userId,
                        'id'          => $evidenceId,
                    ]);
                }
                log_activity('cap_nhat', 'minh_chung', $evidenceId, $title);
                $success = 'Cập nhật minh chứng thành công.';
            } else {
                $stmt = $pdo->prepare('INSERT INTO MinhChung (TenMinhChung, MoTa, TepTin, NamHoc, TrangThai, MaLoai, MaTieuChi, MaNguoiDung) VALUES (:title, :desc, :file, :year, :status, :type_id, :criteria_id, :user_id)');
                $stmt->execute([
                    'title'       => $title,
                    'desc'        => $description,
                    'file'        => $relativePath,
                    'year'        => $academicYear,
                    'status'      => $status,
                    'type_id'     => $typeId,
                    'criteria_id' => $criteriaId,
                    'user_id'     => $userId,
                ]);
                $newId = (int) $pdo->lastInsertId();
                log_activity('them_moi', 'minh_chung', $newId, $title);
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
$editId = $isCreatingEvidence ? 0 : (int) ($_GET['edit'] ?? 0);
$viewId = (int) ($_GET['view'] ?? 0);

$editingEvidence = null;
if ($editId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM MinhChung WHERE MaMinhChung = :id LIMIT 1');
    $stmt->execute(['id' => $editId]);
    $editingEvidence = $stmt->fetch();
}

$viewingEvidence = null;
if ($viewId > 0) {
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

$pageTitle = page_title('Quản lý minh chứng');
$heading   = 'Quản lý hồ sơ minh chứng';
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
                <?php if (current_role() === 'admin'): ?>
                    <a class="btn btn-primary" href="<?= base_url('admin/evidences.php?create=1') ?>"><i class="bi bi-plus-circle me-1"></i> Thêm mới</a>
                <?php endif; ?>
            </div>
            <div class="table-responsive">
                <table class="table" data-page-size="10">
                    <thead>
                    <tr>
                        <th>Mã minh chứng</th>
                        <th>Tên minh chứng</th>
                        <th>Mô tả</th>
                        <th>Tệp tin</th>
                        <th>Năm học</th>
                        <th>Ngày cập nhật</th>
                        <th>Trạng thái</th>
                        <th>Mã loại</th>
                        <th>Mã tiêu chí</th>
                        <th>Mã người dùng</th>
                        <th class="text-end">Thao tác</th>
                    </tr>
                    </thead>
                    <tbody id="evidencesTableBody">
                    <?php foreach ($evidences as $item): ?>
                        <tr>
                            <td class="fw-bold text-nowrap"><?= htmlspecialchars($item['code']) ?></td>
                            <td class="fw-semibold"><?= htmlspecialchars($item['name']) ?></td>
                            <td style="max-width: 200px;">
                                <div class="text-truncate" title="<?= htmlspecialchars($item['description']) ?>">
                                    <?= htmlspecialchars($item['description'] ?: '-') ?>
                                </div>
                            </td>
                            <td>
                                <?php if (!empty($item['file_path'])): ?>
                                    <code><?= htmlspecialchars(basename($item['file_path'])) ?></code>
                                <?php else: ?>
                                    <span class="text-secondary small">Không có</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($item['year'] ?: '-') ?></td>
                            <td class="text-nowrap"><?= htmlspecialchars($item['updated']) ?></td>
                            <td>
                                <?php if ((int) $item['status_raw'] === 1): ?>
                                    <span class="badge bg-success">Đang hoạt động</span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark">Ngưng áp dụng</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge bg-secondary"><?= htmlspecialchars($item['type_code']) ?></span></td>
                            <td><span class="badge bg-info text-dark"><?= htmlspecialchars($item['criteria_code']) ?></span></td>
                            <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($item['user_code']) ?></span></td>
                            <td class="text-end action-cell">
                                <div class="action-buttons">
                                    <a class="btn btn-sm btn-outline-secondary" href="?view=<?= $item['id'] ?>" title="Xem thông tin minh chứng"><i class="bi bi-eye"></i></a>
                                    <?php if (!empty($item['file_path'])): ?>
                                        <a class="btn btn-sm btn-outline-success" href="<?= base_url('user/download.php?id=' . $item['id']) ?>" title="Tải tài liệu về"><i class="bi bi-download"></i></a>
                                    <?php endif; ?>
                                    <?php if (current_role() === 'admin'): ?>
                                        <a class="btn btn-sm btn-outline-primary" href="?edit=<?= $item['id'] ?>" title="Sửa"><i class="bi bi-pencil"></i></a>
                                        <form method="post" class="d-inline" data-confirm-form="Bạn chắc chắn muốn xóa minh chứng này?">
                                            <input type="hidden" name="action" value="delete_evidence">
                                            <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                            <button class="btn btn-sm btn-outline-danger" type="submit" title="Xóa"><i class="bi bi-trash"></i></button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$evidences): ?>
                        <tr><td colspan="11" class="text-center text-secondary py-4">Không tìm thấy minh chứng phù hợp.</td></tr>
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
                <input type="hidden" name="id" value="<?= (int) ($editingEvidence['MaMinhChung'] ?? 0) ?>">

                <div class="mb-3">
                    <label class="form-label">Tên minh chứng <span class="text-danger">*</span></label>
                    <textarea class="form-control" name="ten_minh_chung" rows="2" placeholder="Nhập tên minh chứng" required><?= htmlspecialchars($editingEvidence['TenMinhChung'] ?? '') ?></textarea>
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
                    <div class="col-md-4">
                        <label class="form-label">Mã loại minh chứng</label>
                        <select class="form-select" name="ma_loai">
                            <option value="">-- Chọn mã loại --</option>
                            <?php foreach ($evidenceTypes as $type): ?>
                                <option value="<?= $type['id'] ?>" <?= (int) ($editingEvidence['MaLoai'] ?? 0) === (int) $type['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars('LMC' . str_pad($type['id'], 2, '0', STR_PAD_LEFT) . ' - ' . $type['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Mã tiêu chí <span class="text-danger">*</span></label>
                        <select class="form-select" name="ma_tieu_chi" required>
                            <?php foreach ($criteria as $item): ?>
                                <option value="<?= $item['id'] ?>" <?= (int) ($editingEvidence['MaTieuChi'] ?? 0) === (int) $item['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($item['code'] . ' - ' . $item['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Mã người dùng</label>
                        <select class="form-select" name="ma_nguoi_dung">
                            <?php foreach ($users as $u): ?>
                                <option value="<?= $u['id'] ?>" <?= (int) ($editingEvidence['MaNguoiDung'] ?? $_SESSION['user_id'] ?? 1) === (int) $u['id'] ? 'selected' : '' ?>>
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
                    <i class="bi bi-file-earmark-text text-primary me-1"></i> Thông tin minh chứng: <code>MC<?= str_pad((int)($viewingEvidence['MaMinhChung'] ?? 0), 2, '0', STR_PAD_LEFT) ?></code>
                </h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-bold">Mã minh chứng</label>
                    <input class="form-control bg-light" value="<?= htmlspecialchars('MC' . str_pad((int)($viewingEvidence['MaMinhChung'] ?? 0), 2, '0', STR_PAD_LEFT)) ?>" readonly>
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
                            <a class="btn btn-outline-success text-nowrap" href="<?= base_url('user/download.php?id=' . (int) $viewingEvidence['MaMinhChung']) ?>" title="Tải tệp tin về">
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
