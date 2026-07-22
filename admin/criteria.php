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
        if ($action === 'save_criterion') {
            $id           = (int) ($_POST['id'] ?? 0);
            $standardId   = (int) ($_POST['id_tieu_chuan'] ?? 0);
            $departmentId = (int) ($_POST['id_don_vi'] ?? 0);
            $code         = trim($_POST['ma_tieu_chi'] ?? '');
            $name         = trim($_POST['ten_tieu_chi'] ?? '');
            $description  = trim($_POST['noi_dung_mo_ta'] ?? '');
            $status       = $_POST['trang_thai'] ?? 'missing';

            if ($standardId <= 0 || $code === '' || $name === '') {
                throw new RuntimeException('Vui lòng nhập đầy đủ tiêu chuẩn, mã và nội dung tiêu chí.');
            }

            $check = $pdo->prepare('SELECT id FROM tieu_chi WHERE (ma_tieu_chi = :code OR ten_tieu_chi = :name) AND id <> :id LIMIT 1');
            $check->execute(['code' => $code, 'name' => $name, 'id' => $id]);
            if ($check->fetch()) {
                throw new RuntimeException('Mã hoặc nội dung tiêu chí đã tồn tại trong hệ thống.');
            }

            if ($id > 0) {
                $stmt = $pdo->prepare('UPDATE tieu_chi SET id_tieu_chuan = :standard_id, id_don_vi = :department_id, ma_tieu_chi = :code, ten_tieu_chi = :name, noi_dung_mo_ta = :description, trang_thai = :status WHERE id = :id');
                $stmt->execute([
                    'standard_id'   => $standardId,
                    'department_id' => $departmentId ?: null,
                    'code'          => $code,
                    'name'          => $name,
                    'description'   => $description,
                    'status'        => $status,
                    'id'            => $id,
                ]);
                log_activity('cap_nhat', 'tieu_chi', $id, $code . ' - ' . $name);
                $success = 'Cập nhật tiêu chí thành công.';
            } else {
                $order = (int) $pdo->query('SELECT COALESCE(MAX(thu_tu_hien_thi), 0) + 1 FROM tieu_chi')->fetchColumn();
                $stmt  = $pdo->prepare('INSERT INTO tieu_chi (id_tieu_chuan, id_don_vi, ma_tieu_chi, ten_tieu_chi, noi_dung_mo_ta, trang_thai, thu_tu_hien_thi) VALUES (:standard_id, :department_id, :code, :name, :description, :status, :display_order)');
                $stmt->execute([
                    'standard_id'   => $standardId,
                    'department_id' => $departmentId ?: null,
                    'code'          => $code,
                    'name'          => $name,
                    'description'   => $description,
                    'status'        => $status,
                    'display_order' => $order,
                ]);
                $newId = (int) $pdo->lastInsertId();
                log_activity('them_moi', 'tieu_chi', $newId, $code . ' - ' . $name);
                $success = 'Thêm tiêu chí thành công.';
            }
        }

        if ($action === 'delete_criterion') {
            $id   = (int) ($_POST['id'] ?? 0);
            $stmtName = $pdo->prepare('SELECT ma_tieu_chi FROM tieu_chi WHERE id = :id');
            $stmtName->execute(['id' => $id]);
            $critCode = $stmtName->fetchColumn() ?: ('#' . $id);

            $stmt = $pdo->prepare('DELETE FROM tieu_chi WHERE id = :id');
            $stmt->execute(['id' => $id]);
            log_activity('xoa', 'tieu_chi', $id, $critCode);
            $success = 'Xóa tiêu chí thành công.';
        }

        if ($action === 'update_criterion_status') {
            $id            = (int) ($_POST['id'] ?? 0);
            $status        = $_POST['trang_thai'] ?? '';
            $statusOptions = ['complete', 'need_update', 'missing', 'du_minh_chung', 'can_bo_sung', 'thieu_minh_chung'];

            if ($id <= 0 || !in_array($status, $statusOptions, true)) {
                throw new RuntimeException('Trạng thái tiêu chí không hợp lệ.');
            }

            $stmt = $pdo->prepare('UPDATE tieu_chi SET trang_thai = :status WHERE id = :id');
            $stmt->execute([
                'status' => $status,
                'id'     => $id,
            ]);
            $success = 'Cập nhật trạng thái tiêu chí thành công.';
        }
    } catch (Throwable $exception) {
        $error = 'Không thể thực hiện thao tác: ' . $exception->getMessage();
    }
    unset($_GET['create'], $_GET['edit']);
}

require_once __DIR__ . '/../includes/data.php';

$departments  = $pdo->query("SELECT id, ten_don_vi AS name FROM don_vi WHERE trang_thai = 'active' ORDER BY ten_don_vi")->fetchAll();
$standardRows = $pdo->query('SELECT id, ma_tieu_chuan AS code, ten_tieu_chuan AS name FROM tieu_chuan ORDER BY thu_tu_hien_thi, id')->fetchAll();

$isCreatingCriterion = isset($_GET['create']);
$editId              = $isCreatingCriterion ? 0 : (int) ($_GET['edit'] ?? 0);
$editingCriterion    = null;
if ($editId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM tieu_chi WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $editId]);
    $editingCriterion = $stmt->fetch();
}

$pageTitle = page_title('Quản lý tiêu chí');
$heading   = 'Quản lý tiêu chí đánh giá';
include __DIR__ . '/../includes/header.php';
?>
<?php if (($editingCriterion || $isCreatingCriterion) && !$success && !$error): ?><script>document.body.dataset.autoOpenModal = 'criterionFormModal';</script><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="panel mb-4">
    <form class="row g-3 align-items-end">
        <div class="col-md-3"><label class="form-label">Tiêu chuẩn</label><select class="form-select"><option>Tất cả tiêu chuẩn</option><?php foreach ($standards as $standard): ?><option><?= htmlspecialchars($standard['code']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-3"><label class="form-label">Trạng thái</label><select class="form-select"><option>Tất cả trạng thái</option><option>Đủ minh chứng</option><option>Cần bổ sung</option><option>Thiếu minh chứng</option></select></div>
        <div class="col-md-4"><label class="form-label">Từ khóa</label><input class="form-control" placeholder="Nhập mã hoặc tên tiêu chí"></div>
        <div class="col-md-2"><button class="btn btn-primary w-100" type="button"><i class="bi bi-funnel me-1"></i> Lọc</button></div>
    </form>
</div>

<div class="row g-4 criteria-layout">
    <div class="col-12">
        <div class="panel criteria-list-panel">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="h5 mb-0">Danh sách tiêu chí</h2>
                <a class="btn btn-primary" href="<?= base_url('admin/criteria.php?create=1') ?>"><i class="bi bi-plus-circle me-1"></i> Thêm mới</a>
            </div>
            <div class="table-responsive">
                <table class="table" data-page-size="10">
                    <thead>
                        <tr>
                            <th>Mã tiêu chí</th>
                            <th>Tên tiêu chí</th>
                            <th>Thuộc tiêu chuẩn</th>
                            <th>Nội dung mô tả</th>
                            <th>Trạng thái</th>
                            <th class="text-end">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($criteria as $item): ?>
                        <tr>
                            <td class="fw-bold text-nowrap"><?= htmlspecialchars($item['code']) ?></td>
                            <td><?= htmlspecialchars($item['name']) ?></td>
                            <td class="text-nowrap"><?= htmlspecialchars($item['standard']) ?></td>
                            <td style="max-width: 350px;">
                                <div class="text-truncate" title="<?= htmlspecialchars($item['description'] ?? '') ?>">
                                    <?= htmlspecialchars($item['description'] !== '' ? $item['description'] : '-') ?>
                                </div>
                            </td>
                            <td>
                                <form method="post" class="status-update-form">
                                    <input type="hidden" name="action" value="update_criterion_status">
                                    <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                    <?= status_select($item['status_raw'] ?? 'missing', ['complete' => 'Đủ minh chứng', 'need_update' => 'Cần bổ sung', 'missing' => 'Thiếu minh chứng'], 'trang_thai', false, '', 'Cập nhật trạng thái tiêu chí') ?>
                                </form>
                            </td>
                            <td class="text-end action-cell">
                                <div class="action-buttons">
                                    <a class="btn btn-sm btn-outline-primary" href="?edit=<?= $item['id'] ?>" title="Sửa"><i class="bi bi-pencil"></i></a>
                                    <form method="post" class="d-inline" data-confirm-form="Bạn chắc chắn muốn xóa tiêu chí này?">
                                        <input type="hidden" name="action" value="delete_criterion">
                                        <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                        <button class="btn btn-sm btn-outline-danger" type="submit" title="Xóa"><i class="bi bi-trash"></i></button>
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

<div class="modal fade management-form-modal" id="criterionFormModal" tabindex="-1" aria-labelledby="criterionFormModalLabel" aria-hidden="true" <?= ($editingCriterion || $isCreatingCriterion) ? 'data-auto-open-modal' : '' ?>>
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5" id="criterionFormModalLabel"><?= $editingCriterion ? 'Sửa tiêu chí' : 'Thêm tiêu chí' ?></h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <div class="modal-body">
            <form method="post">
                <input type="hidden" name="action" value="save_criterion">
                <input type="hidden" name="id" value="<?= (int) ($editingCriterion['id'] ?? 0) ?>">
                <div class="mb-3"><label class="form-label">Mã tiêu chí</label><input class="form-control" name="ma_tieu_chi" value="<?= htmlspecialchars($editingCriterion['ma_tieu_chi'] ?? '') ?>" placeholder="VD: 6.1" required></div>
                <div class="mb-3"><label class="form-label">Tên tiêu chí</label><textarea class="form-control" name="ten_tieu_chi" rows="3" placeholder="Nhập tên/nội dung tiêu chí" required><?= htmlspecialchars($editingCriterion['ten_tieu_chi'] ?? '') ?></textarea></div>
                <div class="mb-3"><label class="form-label">Thuộc tiêu chuẩn</label><select class="form-select" name="id_tieu_chuan" required><?php foreach ($standardRows as $standard): ?><option value="<?= $standard['id'] ?>" <?= (int) ($editingCriterion['id_tieu_chuan'] ?? 0) === (int) $standard['id'] ? 'selected' : '' ?>><?= htmlspecialchars($standard['code'] . ' - ' . $standard['name']) ?></option><?php endforeach; ?></select></div>
                <div class="mb-3"><label class="form-label">Nội dung mô tả</label><textarea class="form-control" name="noi_dung_mo_ta" rows="3" placeholder="Nhập nội dung mô tả tiêu chí"><?= htmlspecialchars($editingCriterion['noi_dung_mo_ta'] ?? '') ?></textarea></div>
                <div class="mb-3"><label class="form-label">Trạng thái</label><select class="form-select" name="trang_thai"><?php foreach (['complete' => 'Đủ minh chứng', 'need_update' => 'Cần bổ sung', 'missing' => 'Thiếu minh chứng'] as $value => $label): ?><option value="<?= $value ?>" <?= ($editingCriterion['trang_thai'] ?? 'missing') === $value ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></div>
                <div class="d-flex gap-2 justify-content-end">
                    <?php if ($editingCriterion): ?><a class="btn btn-outline-secondary" href="<?= base_url('admin/criteria.php') ?>">Hủy sửa</a><?php else: ?><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Hủy</button><?php endif; ?>
                    <button class="btn btn-primary" type="submit"><i class="bi bi-save me-1"></i> Lưu tiêu chí</button>
                </div>
            </form>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
