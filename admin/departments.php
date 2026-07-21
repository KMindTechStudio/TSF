<?php
require_once __DIR__ . '/../includes/helpers.php';
require_roles(['admin']);
require_once __DIR__ . '/../config/database.php';

$pdo = db();

// Auto-migrate: add status column if not exists
$hasStatus = $pdo->query("SHOW COLUMNS FROM departments LIKE 'status'")->fetch();
if (!$hasStatus) {
    $pdo->exec("ALTER TABLE departments ADD COLUMN status ENUM('active','inactive') NOT NULL DEFAULT 'active' AFTER name");
}

$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'save_department') {
            $id     = (int) ($_POST['id'] ?? 0);
            $code   = trim($_POST['code'] ?? '');
            $name   = trim($_POST['name'] ?? '');
            $status = in_array($_POST['status'] ?? '', ['active', 'inactive'], true)
                      ? $_POST['status'] : 'active';

            if ($code === '' || $name === '') {
                throw new RuntimeException('Vui lòng nhập đầy đủ mã và tên đơn vị.');
            }

            // Check duplicate
            $check = $pdo->prepare('SELECT id FROM departments WHERE (code = :code OR name = :name) AND id <> :id LIMIT 1');
            $check->execute(['code' => $code, 'name' => $name, 'id' => $id]);
            if ($check->fetch()) {
                throw new RuntimeException('Mã hoặc tên đơn vị đã tồn tại.');
            }

            if ($id > 0) {
                $stmt = $pdo->prepare('UPDATE departments SET code = :code, name = :name, status = :status WHERE id = :id');
                $stmt->execute(['code' => $code, 'name' => $name, 'status' => $status, 'id' => $id]);
                $success = 'Cập nhật đơn vị thành công.';
            } else {
                $stmt = $pdo->prepare('INSERT INTO departments (code, name, status) VALUES (:code, :name, :status)');
                $stmt->execute(['code' => $code, 'name' => $name, 'status' => $status]);
                $success = 'Thêm đơn vị thành công.';
            }
        }

        if ($action === 'update_department_status') {
            $id     = (int) ($_POST['id'] ?? 0);
            $status = in_array($_POST['status'] ?? '', ['active', 'inactive'], true)
                      ? $_POST['status'] : null;
            if ($id <= 0 || $status === null) {
                throw new RuntimeException('Trạng thái đơn vị không hợp lệ.');
            }
            $stmt = $pdo->prepare('UPDATE departments SET status = :status WHERE id = :id');
            $stmt->execute(['status' => $status, 'id' => $id]);
            $success = 'Cập nhật trạng thái đơn vị thành công.';
        }

        if ($action === 'delete_department') {
            $id = (int) ($_POST['id'] ?? 0);

            foreach ([
                ['users',    'department_id'],
                ['criteria', 'department_id'],
                ['evidences','issuing_department_id'],
            ] as [$table, $col]) {
                $chk = $pdo->prepare("SELECT COUNT(*) FROM `$table` WHERE `$col` = :id");
                $chk->execute(['id' => $id]);
                if ((int) $chk->fetchColumn() > 0) {
                    $labels = ['users' => 'tài khoản người dùng', 'criteria' => 'tiêu chí', 'evidences' => 'minh chứng'];
                    throw new RuntimeException("Không thể xóa vì đơn vị đang được dùng bởi {$labels[$table]}.");
                }
            }

            $stmt = $pdo->prepare('DELETE FROM departments WHERE id = :id');
            $stmt->execute(['id' => $id]);
            $success = 'Xóa đơn vị thành công.';
        }
    } catch (Throwable $e) {
        $error = 'Không thể thực hiện thao tác: ' . $e->getMessage();
    }
}

require_once __DIR__ . '/../includes/data.php';

$isCreating  = isset($_GET['create']);
$editId      = $isCreating ? 0 : (int) ($_GET['edit'] ?? 0);
$editingItem = null;
if ($editId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM departments WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $editId]);
    $editingItem = $stmt->fetch();
}

$deptRows = $pdo->query('SELECT id, code, name, status FROM departments ORDER BY name')->fetchAll();

$pageTitle = page_title('Quản lý đơn vị');
$heading   = 'Quản lý đơn vị';
include __DIR__ . '/../includes/header.php';
?>
<?php if ($editingItem || $isCreating): ?><script>document.body.dataset.autoOpenModal = 'departmentFormModal';</script><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="panel">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="h5 mb-1">Danh sách đơn vị</h2>
            <p class="text-secondary mb-0 small">Khoa, phòng ban, bộ môn tham gia quy trình kiểm định.</p>
        </div>
        <a class="btn btn-primary" href="<?= base_url('admin/departments.php?create=1') ?>">
            <i class="bi bi-plus-circle me-1"></i> Thêm mới
        </a>
    </div>

    <div class="table-responsive">
        <table class="table" data-page-size="10">
            <thead>
                <tr>
                    <th>Mã đơn vị</th>
                    <th>Tên đơn vị</th>
                    <th>Trạng thái</th>
                    <th class="text-end">Thao tác</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($deptRows as $dept): ?>
                <tr>
                    <td class="fw-bold"><?= htmlspecialchars($dept['code']) ?></td>
                    <td><?= htmlspecialchars($dept['name']) ?></td>
                    <td>
                        <form method="post" class="status-update-form">
                            <input type="hidden" name="action" value="update_department_status">
                            <input type="hidden" name="id" value="<?= $dept['id'] ?>">
                            <?= status_select(
                                $dept['status'],
                                ['active' => 'Đang áp dụng', 'inactive' => 'Ngưng áp dụng'],
                                'status',
                                false,
                                '',
                                'Cập nhật trạng thái đơn vị'
                            ) ?>
                        </form>
                    </td>
                    <td class="text-end action-cell">
                        <div class="action-buttons">
                            <a class="btn btn-sm btn-outline-primary" href="?edit=<?= $dept['id'] ?>" title="Sửa">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <form method="post" class="d-inline"
                                  data-confirm-form="Bạn chắc chắn muốn xóa đơn vị «<?= htmlspecialchars($dept['name']) ?>»?">
                                <input type="hidden" name="action" value="delete_department">
                                <input type="hidden" name="id" value="<?= $dept['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger" type="submit" title="Xóa">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($deptRows)): ?>
                <tr><td colspan="4" class="text-center text-secondary py-4">Chưa có đơn vị nào.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Department Form Modal -->
<div class="modal fade management-form-modal" id="departmentFormModal" tabindex="-1"
     aria-labelledby="departmentFormModalLabel" aria-hidden="true"
     <?= ($editingItem || $isCreating) ? 'data-auto-open-modal' : '' ?>>
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5" id="departmentFormModalLabel">
                    <?= $editingItem ? 'Sửa đơn vị' : 'Thêm đơn vị mới' ?>
                </h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <div class="modal-body">
                <form method="post">
                    <input type="hidden" name="action" value="save_department">
                    <input type="hidden" name="id" value="<?= (int) ($editingItem['id'] ?? 0) ?>">

                    <div class="mb-3">
                        <label class="form-label">Mã đơn vị <span class="text-danger">*</span></label>
                        <input class="form-control" name="code"
                               value="<?= htmlspecialchars($editingItem['code'] ?? '') ?>"
                               placeholder="VD: KHOA_CNTT" required>
                        <div class="form-text">Không dấu cách, dùng để tra cứu nhanh.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Tên đơn vị <span class="text-danger">*</span></label>
                        <input class="form-control" name="name"
                               value="<?= htmlspecialchars($editingItem['name'] ?? '') ?>"
                               placeholder="VD: Khoa Công nghệ thông tin" required>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Trạng thái</label>
                        <select class="form-select" name="status">
                            <option value="active" <?= ($editingItem['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Đang áp dụng</option>
                            <option value="inactive" <?= ($editingItem['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Ngưng áp dụng</option>
                        </select>
                    </div>

                    <div class="d-flex gap-2 justify-content-end">
                        <?php if ($editingItem): ?>
                            <a class="btn btn-outline-secondary" href="<?= base_url('admin/departments.php') ?>">Hủy sửa</a>
                        <?php else: ?>
                            <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Hủy</button>
                        <?php endif; ?>
                        <button class="btn btn-primary" type="submit">
                            <i class="bi bi-save me-1"></i>
                            <?= $editingItem ? 'Lưu thay đổi' : 'Thêm đơn vị' ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
