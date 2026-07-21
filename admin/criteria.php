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
            $id = (int) ($_POST['id'] ?? 0);
            $standardId = (int) ($_POST['standard_id'] ?? 0);
            $departmentId = (int) ($_POST['department_id'] ?? 0);
            $code = trim($_POST['code'] ?? '');
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $status = $_POST['evidence_status'] ?? 'missing';

            if ($standardId <= 0 || $code === '' || $name === '') {
                throw new RuntimeException('Vui lòng nhập đầy đủ tiêu chuẩn, mã và nội dung tiêu chí.');
            }

            if ($id > 0) {
                $stmt = $pdo->prepare('UPDATE criteria SET standard_id = :standard_id, department_id = :department_id, code = :code, name = :name, description = :description, evidence_status = :status WHERE id = :id');
                $stmt->execute([
                    'standard_id' => $standardId,
                    'department_id' => $departmentId ?: null,
                    'code' => $code,
                    'name' => $name,
                    'description' => $description,
                    'status' => $status,
                    'id' => $id,
                ]);
                $success = 'Cập nhật tiêu chí thành công.';
            } else {
                $order = (int) $pdo->query('SELECT COALESCE(MAX(display_order), 0) + 1 FROM criteria')->fetchColumn();
                $stmt = $pdo->prepare('INSERT INTO criteria (standard_id, department_id, code, name, description, evidence_status, display_order) VALUES (:standard_id, :department_id, :code, :name, :description, :status, :display_order)');
                $stmt->execute([
                    'standard_id' => $standardId,
                    'department_id' => $departmentId ?: null,
                    'code' => $code,
                    'name' => $name,
                    'description' => $description,
                    'status' => $status,
                    'display_order' => $order,
                ]);
                $success = 'Thêm tiêu chí thành công.';
            }
        }

        if ($action === 'delete_criterion') {
            $id = (int) ($_POST['id'] ?? 0);
            $stmt = $pdo->prepare('DELETE FROM criteria WHERE id = :id');
            $stmt->execute(['id' => $id]);
            $success = 'Xóa tiêu chí thành công.';
        }

        if ($action === 'update_criterion_status') {
            $id = (int) ($_POST['id'] ?? 0);
            $status = $_POST['evidence_status'] ?? '';
            $statusOptions = ['complete', 'need_update', 'missing'];

            if ($id <= 0 || !in_array($status, $statusOptions, true)) {
                throw new RuntimeException('Trạng thái tiêu chí không hợp lệ.');
            }

            $stmt = $pdo->prepare('UPDATE criteria SET evidence_status = :status WHERE id = :id');
            $stmt->execute([
                'status' => $status,
                'id' => $id,
            ]);
            $success = 'Cập nhật trạng thái tiêu chí thành công.';
        }
    } catch (Throwable $exception) {
        $error = 'Không thể thực hiện thao tác: ' . $exception->getMessage();
    }
}

require_once __DIR__ . '/../includes/data.php';

$departments = $pdo->query('SELECT id, name FROM departments ORDER BY name')->fetchAll();
$standardRows = $pdo->query('SELECT id, code, name FROM standards ORDER BY display_order, id')->fetchAll();
$isCreatingCriterion = isset($_GET['create']);
$editId = $isCreatingCriterion ? 0 : (int) ($_GET['edit'] ?? 0);
$editingCriterion = null;
if ($editId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM criteria WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $editId]);
    $editingCriterion = $stmt->fetch();
}

$pageTitle = page_title('Quản lý tiêu chí');
$heading = 'Quản lý tiêu chí đánh giá';
include __DIR__ . '/../includes/header.php';
?>
<?php if ($editingCriterion || $isCreatingCriterion): ?><script>document.body.dataset.autoOpenModal = 'criterionFormModal';</script><?php endif; ?>
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
                    <thead><tr><th>Mã</th><th>Tiêu chí</th><th>Tiêu chuẩn</th><th>Đơn vị phụ trách</th><th>Minh chứng</th><th>Trạng thái</th><th class="text-end">Thao tác</th></tr></thead>
                    <tbody>
                    <?php foreach ($criteria as $item): ?>
                        <tr>
                            <td class="fw-bold"><?= htmlspecialchars($item['code']) ?></td>
                            <td><?= htmlspecialchars($item['name']) ?></td>
                            <td><?= htmlspecialchars($item['standard']) ?></td>
                            <td><?= htmlspecialchars($item['owner']) ?></td>
                            <td><?= $item['evidences'] ?></td>
                            <td>
                                <form method="post" class="status-update-form">
                                    <input type="hidden" name="action" value="update_criterion_status">
                                    <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                    <?= status_select($item['status_raw'] ?? 'missing', ['complete' => 'Đủ minh chứng', 'need_update' => 'Cần bổ sung', 'missing' => 'Thiếu minh chứng'], 'evidence_status', false, '', 'Cập nhật trạng thái tiêu chí') ?>
                                </form>
                            </td>
                            <td class="text-end action-cell">
                                <div class="action-buttons">
                                    <a class="btn btn-sm btn-outline-primary" href="?edit=<?= $item['id'] ?>"><i class="bi bi-pencil"></i></a>
                                    <form method="post" class="d-inline" data-confirm-form="Bạn chắc chắn muốn xóa tiêu chí này?">
                                        <input type="hidden" name="action" value="delete_criterion">
                                        <input type="hidden" name="id" value="<?= $item['id'] ?>">
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
                <div class="mb-3"><label class="form-label">Mã tiêu chí</label><input class="form-control" name="code" value="<?= htmlspecialchars($editingCriterion['code'] ?? '') ?>" placeholder="VD: 6.1" required></div>
                <div class="mb-3"><label class="form-label">Thuộc tiêu chuẩn</label><select class="form-select" name="standard_id" required><?php foreach ($standardRows as $standard): ?><option value="<?= $standard['id'] ?>" <?= (int) ($editingCriterion['standard_id'] ?? 0) === (int) $standard['id'] ? 'selected' : '' ?>><?= htmlspecialchars($standard['code'] . ' - ' . $standard['name']) ?></option><?php endforeach; ?></select></div>
                <div class="mb-3"><label class="form-label">Đơn vị phụ trách</label><select class="form-select" name="department_id"><option value="">Chưa phân công</option><?php foreach ($departments as $department): ?><option value="<?= $department['id'] ?>" <?= (int) ($editingCriterion['department_id'] ?? 0) === (int) $department['id'] ? 'selected' : '' ?>><?= htmlspecialchars($department['name']) ?></option><?php endforeach; ?></select></div>
                <div class="mb-3"><label class="form-label">Trạng thái</label><select class="form-select" name="evidence_status"><?php foreach (['complete' => 'Đủ minh chứng', 'need_update' => 'Cần bổ sung', 'missing' => 'Thiếu minh chứng'] as $value => $label): ?><option value="<?= $value ?>" <?= ($editingCriterion['evidence_status'] ?? 'missing') === $value ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></div>
                <div class="mb-3"><label class="form-label">Nội dung tiêu chí</label><textarea class="form-control" name="name" rows="3" required><?= htmlspecialchars($editingCriterion['name'] ?? '') ?></textarea></div>
                <div class="mb-3"><label class="form-label">Mô tả</label><textarea class="form-control" name="description" rows="3"><?= htmlspecialchars($editingCriterion['description'] ?? '') ?></textarea></div>
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
