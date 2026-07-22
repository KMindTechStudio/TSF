<?php
require_once __DIR__ . '/../includes/helpers.php';
require_roles(['admin']);
require_once __DIR__ . '/../config/database.php';

$pdo = db();
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'save_standard') {
            $id            = (int) ($_POST['id'] ?? 0);
            $standardSetId = (int) ($_POST['id_bo_tieu_chuan'] ?? 1);
            $code          = trim($_POST['ma_tieu_chuan'] ?? '');
            $name          = trim($_POST['ten_tieu_chuan'] ?? '');
            $description   = trim($_POST['mo_ta'] ?? '');

            if ($standardSetId <= 0 || $code === '' || $name === '') {
                throw new RuntimeException('Vui lòng nhập đầy đủ bộ tiêu chuẩn, mã và tên tiêu chuẩn.');
            }

            $check = $pdo->prepare('SELECT id FROM tieu_chuan WHERE UPPER(ma_tieu_chuan) = UPPER(:code) AND id <> :id LIMIT 1');
            $check->execute(['code' => $code, 'id' => $id]);
            if ($check->fetch()) {
                throw new RuntimeException('Mã tiêu chuẩn đã tồn tại trong hệ thống.');
            }

            if ($id > 0) {
                $stmt = $pdo->prepare('UPDATE tieu_chuan SET id_bo_tieu_chuan = :set_id, ma_tieu_chuan = :code, ten_tieu_chuan = :name, mo_ta = :description WHERE id = :id');
                $stmt->execute([
                    'set_id'      => $standardSetId,
                    'code'        => $code,
                    'name'        => $name,
                    'description' => $description,
                    'id'          => $id,
                ]);
                log_activity('cap_nhat', 'tieu_chuan', $id, $code . ' - ' . $name);
                $success = 'Cập nhật tiêu chuẩn thành công.';
            } else {
                $order = (int) $pdo->query('SELECT COALESCE(MAX(thu_tu_hien_thi), 0) + 1 FROM tieu_chuan')->fetchColumn();
                $stmt  = $pdo->prepare('INSERT INTO tieu_chuan (id_bo_tieu_chuan, ma_tieu_chuan, ten_tieu_chuan, mo_ta, thu_tu_hien_thi) VALUES (:set_id, :code, :name, :description, :display_order)');
                $stmt->execute([
                    'set_id'        => $standardSetId,
                    'code'          => $code,
                    'name'          => $name,
                    'description'   => $description,
                    'display_order' => $order,
                ]);
                $newId = (int) $pdo->lastInsertId();
                log_activity('them_moi', 'tieu_chuan', $newId, $code . ' - ' . $name);
                $success = 'Thêm tiêu chuẩn thành công.';
            }
        }

        if ($action === 'delete_standard') {
            $id   = (int) ($_POST['id'] ?? 0);
            $stmtName = $pdo->prepare('SELECT ma_tieu_chuan FROM tieu_chuan WHERE id = :id');
            $stmtName->execute(['id' => $id]);
            $stdCode = $stmtName->fetchColumn() ?: ('#' . $id);

            $stmt = $pdo->prepare('DELETE FROM tieu_chuan WHERE id = :id');
            $stmt->execute(['id' => $id]);
            log_activity('xoa', 'tieu_chuan', $id, $stdCode);
            $success = 'Xóa tiêu chuẩn thành công.';
        }
    } catch (Throwable $exception) {
        $error = 'Không thể thực hiện thao tác: ' . $exception->getMessage();
    }
    unset($_GET['create'], $_GET['edit']);
}

require_once __DIR__ . '/../includes/data.php';

$isCreatingStandard = isset($_GET['create']);
$editId             = $isCreatingStandard ? 0 : (int) ($_GET['edit'] ?? 0);
$editingStandard    = null;
if ($editId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM tieu_chuan WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $editId]);
    $editingStandard = $stmt->fetch();
}

$pageTitle = page_title('Quản lý tiêu chuẩn');
$heading   = 'Quản lý tiêu chuẩn kiểm định';
include __DIR__ . '/../includes/header.php';
?>
<?php if (($editingStandard || $isCreatingStandard) && !$success && !$error): ?><script>document.body.dataset.autoOpenModal = 'standardFormModal';</script><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="row g-4 standards-layout">
    <div class="col-12">
        <div class="panel standards-list-panel">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="h5 mb-0">Danh sách tiêu chuẩn</h2>
                <div class="d-flex gap-2">
                    <a class="btn btn-primary" href="<?= base_url('admin/standards.php?create=1') ?>"><i class="bi bi-plus-circle me-1"></i> Thêm mới</a>
                    <button class="btn btn-outline-secondary" type="button"><i class="bi bi-file-earmark-spreadsheet me-1"></i> Xuất Excel</button>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table" data-page-size="10">
                    <thead><tr><th>Mã</th><th>Tên tiêu chuẩn</th><th>Tiêu chí</th><th>Trạng thái</th><th class="text-end">Thao tác</th></tr></thead>
                    <tbody>
                    <?php foreach ($standards as $standard): ?>
                        <tr>
                            <td class="fw-bold"><?= htmlspecialchars($standard['code']) ?></td>
                            <td><?= htmlspecialchars($standard['name']) ?></td>
                            <td><?= $standard['criteria'] ?></td>
                            <td><?= readonly_status_select($standard['status']) ?></td>
                            <td class="text-end action-cell">
                                <div class="action-buttons">
                                    <a class="btn btn-sm btn-outline-primary" href="?edit=<?= $standard['id'] ?>"><i class="bi bi-pencil"></i></a>
                                    <form method="post" class="d-inline" data-confirm-form="Bạn chắc chắn muốn xóa tiêu chuẩn này?">
                                        <input type="hidden" name="action" value="delete_standard">
                                        <input type="hidden" name="id" value="<?= $standard['id'] ?>">
                                        <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade management-form-modal" id="standardFormModal" tabindex="-1" aria-labelledby="standardFormModalLabel" aria-hidden="true" <?= ($editingStandard || $isCreatingStandard) ? 'data-auto-open-modal' : '' ?>>
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5" id="standardFormModalLabel"><?= $editingStandard ? 'Sửa tiêu chuẩn' : 'Thêm tiêu chuẩn' ?></h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <div class="modal-body">
                <form method="post">
                    <input type="hidden" name="action" value="save_standard">
                    <input type="hidden" name="id" value="<?= (int) ($editingStandard['id'] ?? 0) ?>">
                    <div class="mb-3">
                        <label class="form-label">Mã tiêu chuẩn</label>
                        <input class="form-control" name="ma_tieu_chuan" value="<?= htmlspecialchars($editingStandard['ma_tieu_chuan'] ?? '') ?>" placeholder="VD: TC06" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tên tiêu chuẩn</label>
                        <textarea class="form-control" name="ten_tieu_chuan" rows="3" placeholder="Nhập nội dung tiêu chuẩn" required><?= htmlspecialchars($editingStandard['ten_tieu_chuan'] ?? '') ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Mô tả</label>
                        <textarea class="form-control" name="mo_ta" rows="3"><?= htmlspecialchars($editingStandard['mo_ta'] ?? '') ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Bộ tiêu chuẩn</label>
                        <select class="form-select" name="id_bo_tieu_chuan" required>
                            <?php foreach ($standardSets as $set): ?>
                                <option value="<?= $set['id'] ?>" <?= (int) ($editingStandard['id_bo_tieu_chuan'] ?? 1) === (int) $set['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($set['name'] . ' - ' . $set['version']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="d-flex gap-2 justify-content-end">
                        <?php if ($editingStandard): ?>
                            <a class="btn btn-outline-secondary" href="<?= base_url('admin/standards.php') ?>">Hủy sửa</a>
                        <?php else: ?>
                            <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Hủy</button>
                        <?php endif; ?>
                        <button class="btn btn-primary" type="submit"><i class="bi bi-save me-1"></i> Lưu tiêu chuẩn</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
