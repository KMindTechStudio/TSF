<?php
require_once __DIR__ . '/../includes/helpers.php';
require_roles(['admin']);
require_once __DIR__ . '/../config/database.php';

$pdo = db();

// Auto-migrate: add trang_thai column if not exists
$hasStatus = $pdo->query("SHOW COLUMNS FROM don_vi LIKE 'trang_thai'")->fetch();
if (!$hasStatus) {
    $pdo->exec("ALTER TABLE don_vi ADD COLUMN trang_thai ENUM('active','inactive') NOT NULL DEFAULT 'active'");
}

$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'save_department') {
            $id     = (int) ($_POST['id'] ?? 0);
            $code   = trim($_POST['ma_don_vi'] ?? '');
            $name   = trim($_POST['ten_don_vi'] ?? '');
            $status = in_array($_POST['trang_thai'] ?? '', ['active', 'inactive'], true)
                      ? $_POST['trang_thai'] : 'active';

            if ($code === '' || $name === '') {
                throw new RuntimeException('Vui lòng nhập đầy đủ mã và tên đơn vị.');
            }

            // Check duplicate
            $check = $pdo->prepare('SELECT id FROM don_vi WHERE (ma_don_vi = :code OR ten_don_vi = :name) AND id <> :id LIMIT 1');
            $check->execute(['code' => $code, 'name' => $name, 'id' => $id]);
            if ($check->fetch()) {
                throw new RuntimeException('Mã hoặc tên đơn vị đã tồn tại.');
            }

            if ($id > 0) {
                $stmt = $pdo->prepare('UPDATE don_vi SET ma_don_vi = :code, ten_don_vi = :name, trang_thai = :status WHERE id = :id');
                $stmt->execute(['code' => $code, 'name' => $name, 'status' => $status, 'id' => $id]);
                log_activity('cap_nhat', 'don_vi', $id, $name);
                $success = 'Cập nhật đơn vị thành công.';
            } else {
                $stmt = $pdo->prepare('INSERT INTO don_vi (ma_don_vi, ten_don_vi, trang_thai) VALUES (:code, :name, :status)');
                $stmt->execute(['code' => $code, 'name' => $name, 'status' => $status]);
                $newId = (int) $pdo->lastInsertId();
                log_activity('them_moi', 'don_vi', $newId, $name);
                $success = 'Thêm đơn vị thành công.';
            }
        }

        if ($action === 'update_department_status') {
            $id     = (int) ($_POST['id'] ?? 0);
            $status = in_array($_POST['trang_thai'] ?? '', ['active', 'inactive'], true)
                      ? $_POST['trang_thai'] : null;
            if ($id <= 0 || $status === null) {
                throw new RuntimeException('Trạng thái đơn vị không hợp lệ.');
            }
            $stmt = $pdo->prepare('UPDATE don_vi SET trang_thai = :status WHERE id = :id');
            $stmt->execute(['status' => $status, 'id' => $id]);
            log_activity('cap_nhat_trang_thai', 'don_vi', $id, 'Đơn vị #' . $id, ['trang_thai' => $status]);
            $success = 'Cập nhật trạng thái đơn vị thành công.';
        }

        if ($action === 'delete_department') {
            $id = (int) ($_POST['id'] ?? 0);
            $stmtName = $pdo->prepare('SELECT ten_don_vi FROM don_vi WHERE id = :id');
            $stmtName->execute(['id' => $id]);
            $deptName = $stmtName->fetchColumn() ?: ('#' . $id);

            foreach ([
                ['nguoi_dung',  'id_don_vi'],
                ['tieu_chi',    'id_don_vi'],
                ['minh_chung',  'id_don_vi_phu_trach'],
            ] as [$table, $col]) {
                $chk = $pdo->prepare("SELECT COUNT(*) FROM `$table` WHERE `$col` = :id");
                $chk->execute(['id' => $id]);
                if ((int) $chk->fetchColumn() > 0) {
                    $labels = ['nguoi_dung' => 'tài khoản người dùng', 'tieu_chi' => 'tiêu chí', 'minh_chung' => 'minh chứng'];
                    throw new RuntimeException("Không thể xóa vì đơn vị đang được dùng bời {$labels[$table]}.");
                }
            }

            $stmt = $pdo->prepare('DELETE FROM don_vi WHERE id = :id');
            $stmt->execute(['id' => $id]);
            log_activity('xoa', 'don_vi', $id, $deptName);
            $success = 'Xóa đơn vị thành công.';
        }
    } catch (Throwable $e) {
        $error = 'Không thể thực hiện thao tác: ' . $e->getMessage();
    }
    unset($_GET['create'], $_GET['edit']);
}

require_once __DIR__ . '/../includes/data.php';

$isCreating  = isset($_GET['create']);
$editId      = $isCreating ? 0 : (int) ($_GET['edit'] ?? 0);
$editingItem = null;
if ($editId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM don_vi WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $editId]);
    $editingItem = $stmt->fetch();
}

$deptRows = $pdo->query('SELECT id, ma_don_vi AS code, ten_don_vi AS name, trang_thai AS status FROM don_vi ORDER BY ten_don_vi')->fetchAll();

$searchKeyword = trim($_GET['search'] ?? $_GET['q'] ?? '');
if ($searchKeyword !== '') {
    $deptRows = array_filter($deptRows, function ($dept) use ($searchKeyword) {
        return search_contains($dept['code'], $searchKeyword) || search_contains($dept['name'], $searchKeyword);
    });
}

$pageTitle = page_title('Quản lý đơn vị');
$heading   = 'Quản lý đơn vị';
include __DIR__ . '/../includes/header.php';
?>
<?php if (($editingItem || $isCreating) && !$success && !$error): ?><script>document.body.dataset.autoOpenModal = 'departmentFormModal';</script><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="panel">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
        <div>
            <h2 class="h5 mb-1">Danh sách đơn vị</h2>
            <p class="text-secondary mb-0 small">Khoa, phòng ban, bộ môn tham gia quy trình kiểm định.</p>
        </div>
        <a class="btn btn-primary" href="<?= base_url('admin/departments.php?create=1') ?>">
            <i class="bi bi-plus-circle me-1"></i> Thêm mới
        </a>
    </div>

    <form method="get" action="" class="mb-3" id="departmentSearchForm">
        <div class="input-group">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input type="text" name="search" id="departmentSearchInput" class="form-control" placeholder="Nhập mã hoặc tên đơn vị để tìm kiếm..." value="<?= htmlspecialchars($searchKeyword) ?>">
            <?php if ($searchKeyword !== ''): ?>
                <a href="<?= base_url('admin/departments.php') ?>" class="btn btn-outline-secondary" title="Xóa tìm kiếm"><i class="bi bi-x-lg"></i></a>
            <?php endif; ?>
            <button class="btn btn-primary" type="submit">Tìm kiếm</button>
        </div>
    </form>

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
            <tbody id="departmentsTableBody">
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
                                'trang_thai',
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
            <tr id="noDataRow" class="<?= !empty($deptRows) ? 'd-none' : '' ?>">
                <td colspan="4" class="text-center text-secondary py-4">Không có dữ liệu được ghi</td>
            </tr>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('departmentSearchInput');
    const tableBody = document.getElementById('departmentsTableBody');
    const noDataRow = document.getElementById('noDataRow');
    if (!searchInput || !tableBody) return;

    function normalizeText(str) {
        return (str || '')
            .toString()
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/đ/g, 'd')
            .replace(/\s+/g, ' ')
            .trim();
    }

    function filterTable() {
        const query = normalizeText(searchInput.value);
        const rows = tableBody.querySelectorAll('tr:not(#noDataRow)');
        let visibleCount = 0;

        rows.forEach(row => {
            const codeCell = row.cells[0]?.textContent || '';
            const nameCell = row.cells[1]?.textContent || '';
            const textToMatch = normalizeText(codeCell + ' ' + nameCell);

            if (query === '' || textToMatch.includes(query)) {
                row.dataset.filteredOut = 'false';
                visibleCount++;
            } else {
                row.dataset.filteredOut = 'true';
            }
        });

        if (noDataRow) {
            if (visibleCount === 0) {
                noDataRow.classList.remove('d-none');
            } else {
                noDataRow.classList.add('d-none');
            }
        }

        if (typeof refreshTablePaginations === 'function') {
            refreshTablePaginations();
        }
    }

    searchInput.addEventListener('input', filterTable);
});
</script>

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
                        <input class="form-control" name="ma_don_vi"
                               value="<?= htmlspecialchars($editingItem['ma_don_vi'] ?? '') ?>"
                               placeholder="VD: KHOA_CNTT" required>
                        <div class="form-text">Không dấu cách, dùng để tìm kiếm nhanh.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Tên đơn vị <span class="text-danger">*</span></label>
                        <input class="form-control" name="ten_don_vi"
                               value="<?= htmlspecialchars($editingItem['ten_don_vi'] ?? '') ?>"
                               placeholder="VD: Khoa Công nghệ thông tin" required>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Trạng thái</label>
                        <select class="form-select" name="trang_thai">
                            <option value="active" <?= ($editingItem['trang_thai'] ?? 'active') === 'active' ? 'selected' : '' ?>>Đang áp dụng</option>
                            <option value="inactive" <?= ($editingItem['trang_thai'] ?? '') === 'inactive' ? 'selected' : '' ?>>Ngưng áp dụng</option>
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
