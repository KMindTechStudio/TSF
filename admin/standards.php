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
            $id = (int) ($_POST['id'] ?? 0);
            $standardSetId = (int) ($_POST['standard_set_id'] ?? 0);
            $code = trim($_POST['code'] ?? '');
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');

            if ($standardSetId <= 0 || $code === '' || $name === '') {
                throw new RuntimeException('Vui lòng nhập đầy đủ bộ tiêu chuẩn, mã và tên tiêu chuẩn.');
            }

            if ($id > 0) {
                $stmt = $pdo->prepare('UPDATE standards SET standard_set_id = :set_id, code = :code, name = :name, description = :description WHERE id = :id');
                $stmt->execute([
                    'set_id' => $standardSetId,
                    'code' => $code,
                    'name' => $name,
                    'description' => $description,
                    'id' => $id,
                ]);
                $success = 'Cập nhật tiêu chuẩn thành công.';
            } else {
                $order = (int) $pdo->query('SELECT COALESCE(MAX(display_order), 0) + 1 FROM standards')->fetchColumn();
                $stmt = $pdo->prepare('INSERT INTO standards (standard_set_id, code, name, description, display_order) VALUES (:set_id, :code, :name, :description, :display_order)');
                $stmt->execute([
                    'set_id' => $standardSetId,
                    'code' => $code,
                    'name' => $name,
                    'description' => $description,
                    'display_order' => $order,
                ]);
                $success = 'Thêm tiêu chuẩn thành công.';
            }
        }

        if ($action === 'delete_standard') {
            $id = (int) ($_POST['id'] ?? 0);
            $stmt = $pdo->prepare('DELETE FROM standards WHERE id = :id');
            $stmt->execute(['id' => $id]);
            $success = 'Xóa tiêu chuẩn thành công.';
        }
    } catch (Throwable $exception) {
        $error = 'Không thể thực hiện thao tác: ' . $exception->getMessage();
    }
}

require_once __DIR__ . '/../includes/data.php';

$editId = (int) ($_GET['edit'] ?? 0);
$editingStandard = null;
if ($editId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM standards WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $editId]);
    $editingStandard = $stmt->fetch();
}

$pageTitle = page_title('Quản lý tiêu chuẩn');
$heading = 'Quản lý tiêu chuẩn kiểm định';
include __DIR__ . '/../includes/header.php';
?>
<?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="row g-4 standards-layout">
    <div class="col-xl-4">
        <div class="panel standard-form-panel">
            <h2 class="h5 mb-3"><?= $editingStandard ? 'Sửa tiêu chuẩn' : 'Thêm tiêu chuẩn' ?></h2>
            <form method="post">
                <input type="hidden" name="action" value="save_standard">
                <input type="hidden" name="id" value="<?= (int) ($editingStandard['id'] ?? 0) ?>">
                <div class="mb-3">
                    <label class="form-label">Mã tiêu chuẩn</label>
                    <input class="form-control" name="code" value="<?= htmlspecialchars($editingStandard['code'] ?? '') ?>" placeholder="VD: TC06" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Tên tiêu chuẩn</label>
                    <textarea class="form-control" name="name" rows="3" placeholder="Nhập nội dung tiêu chuẩn" required><?= htmlspecialchars($editingStandard['name'] ?? '') ?></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Mô tả</label>
                    <textarea class="form-control" name="description" rows="3"><?= htmlspecialchars($editingStandard['description'] ?? '') ?></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Bộ tiêu chuẩn</label>
                    <select class="form-select" name="standard_set_id" required>
                        <?php foreach ($standardSets as $set): ?>
                            <option value="<?= $set['id'] ?>" <?= (int) ($editingStandard['standard_set_id'] ?? 1) === (int) $set['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($set['name'] . ' - ' . $set['version']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button class="btn btn-primary w-100" type="submit"><i class="bi bi-save me-1"></i> Lưu tiêu chuẩn</button>
                <?php if ($editingStandard): ?>
                    <a class="btn btn-outline-secondary w-100 mt-2" href="<?= base_url('admin/standards.php') ?>">Hủy sửa</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <div class="col-xl-8">
        <div class="panel standards-list-panel">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="h5 mb-0">Danh sách tiêu chuẩn</h2>
                <button class="btn btn-outline-secondary" type="button"><i class="bi bi-file-earmark-spreadsheet me-1"></i> Xuất Excel</button>
            </div>
            <div class="table-responsive">
                <table class="table" data-page-size="5">
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
<?php include __DIR__ . '/../includes/footer.php'; ?>
