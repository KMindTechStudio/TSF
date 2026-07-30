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
            $standardSetId = (int) ($_POST['ma_bo_tieu_chuan'] ?? 1);
            $name          = trim($_POST['ten_tieu_chuan'] ?? '');
            $description   = trim($_POST['mo_ta'] ?? '');
            $order         = (int) ($_POST['thu_tu'] ?? 0);
            $status        = (int) ($_POST['trang_thai'] ?? 1);

            if ($standardSetId <= 0 || $name === '') {
                throw new RuntimeException('Vui lòng chọn bộ tiêu chuẩn và nhập tên tiêu chuẩn.');
            }

            if ($id > 0) {
                $stmt = $pdo->prepare('UPDATE TieuChuan SET MaBoTieuChuan = :set_id, TenTieuChuan = :name, MoTa = :description, ThuTu = :thu_tu, TrangThai = :status WHERE MaTieuChuan = :id');
                $stmt->execute([
                    'set_id'      => $standardSetId,
                    'name'        => $name,
                    'description' => $description,
                    'thu_tu'      => $order,
                    'status'      => $status,
                    'id'          => $id,
                ]);
                log_activity('cap_nhat', 'tieu_chuan', $id, 'TC' . str_pad($id, 2, '0', STR_PAD_LEFT) . ' - ' . $name);
                $success = 'Cập nhật tiêu chuẩn thành công.';
            } else {
                if ($order <= 0) {
                    $order = (int) $pdo->query('SELECT COALESCE(MAX(ThuTu), 0) + 1 FROM TieuChuan')->fetchColumn();
                }
                $stmt = $pdo->prepare('INSERT INTO TieuChuan (MaBoTieuChuan, TenTieuChuan, MoTa, ThuTu, TrangThai) VALUES (:set_id, :name, :description, :thu_tu, :status)');
                $stmt->execute([
                    'set_id'      => $standardSetId,
                    'name'        => $name,
                    'description' => $description,
                    'thu_tu'      => $order,
                    'status'      => $status,
                ]);
                $newId = (int) $pdo->lastInsertId();
                log_activity('them_moi', 'tieu_chuan', $newId, 'TC' . str_pad($newId, 2, '0', STR_PAD_LEFT) . ' - ' . $name);
                $success = 'Thêm tiêu chuẩn thành công.';
            }
        }

        if ($action === 'delete_standard') {
            $id = (int) ($_POST['id'] ?? 0);
            $stdCode = 'TC' . str_pad($id, 2, '0', STR_PAD_LEFT);

            $checkChild = $pdo->prepare('SELECT COUNT(*) FROM TieuChi WHERE MaTieuChuan = :id');
            $checkChild->execute(['id' => $id]);
            if ((int) $checkChild->fetchColumn() > 0) {
                throw new RuntimeException('Không thể xóa tiêu chuẩn này vì đang có các tiêu chí trực thuộc.');
            }

            $stmt = $pdo->prepare('DELETE FROM TieuChuan WHERE MaTieuChuan = :id');
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

$searchKeyword = trim($_GET['search'] ?? $_GET['q'] ?? '');
if ($searchKeyword !== '') {
    $standards = array_filter($standards, function ($std) use ($searchKeyword) {
        return search_contains($std['code'], $searchKeyword) || search_contains($std['name'], $searchKeyword);
    });
}

$isCreatingStandard = isset($_GET['create']);
$editId             = $isCreatingStandard ? 0 : (int) ($_GET['edit'] ?? 0);
$editingStandard    = null;
if ($editId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM TieuChuan WHERE MaTieuChuan = :id LIMIT 1');
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
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
                <h2 class="h5 mb-0">Danh sách tiêu chuẩn</h2>
                <div class="d-flex gap-2">
                    <a class="btn btn-primary" href="<?= base_url('admin/standards.php?create=1') ?>"><i class="bi bi-plus-circle me-1"></i> Thêm mới</a>
                    <button class="btn btn-outline-secondary" type="button"><i class="bi bi-file-earmark-spreadsheet me-1"></i> Xuất Excel</button>
                </div>
            </div>
            <form method="get" action="" class="mb-3" id="standardSearchForm">
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" id="standardSearchInput" class="form-control" placeholder="Nhập mã hoặc tên tiêu chuẩn để tìm kiếm..." value="<?= htmlspecialchars($searchKeyword) ?>">
                    <?php if ($searchKeyword !== ''): ?>
                        <a href="<?= base_url('admin/standards.php') ?>" class="btn btn-outline-secondary" title="Xóa tìm kiếm"><i class="bi bi-x-lg"></i></a>
                    <?php endif; ?>
                    <button class="btn btn-primary" type="submit">Tìm kiếm</button>
                </div>
            </form>
            <div class="table-responsive">
                <table class="table" data-page-size="10">
                    <thead>
                        <tr>
                            <th>Mã tiêu chuẩn</th>
                            <th>Tên tiêu chuẩn</th>
                            <th>Mô tả</th>
                            <th>Thứ tự</th>
                            <th>Mã bộ tiêu chuẩn</th>
                            <th>Trạng thái</th>
                            <th class="text-end">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody id="standardsTableBody">
                    <?php foreach ($standards as $standard): ?>
                        <tr>
                            <td class="fw-bold"><?= htmlspecialchars($standard['code']) ?></td>
                            <td class="fw-semibold"><?= htmlspecialchars($standard['name']) ?></td>
                            <td style="max-width: 250px;">
                                <div class="text-truncate" title="<?= htmlspecialchars($standard['description']) ?>">
                                    <?= htmlspecialchars($standard['description'] ?: '-') ?>
                                </div>
                            </td>
                            <td><?= $standard['order'] ?></td>
                            <td><span class="badge bg-secondary"><?= htmlspecialchars($standard['set_code']) ?></span></td>
                            <td>
                                <?php if (($standard['status_raw'] ?? '') === 'active' || $standard['status'] === 'Đang hoạt động'): ?>
                                    <span class="badge bg-success">Đang hoạt động</span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark">Ngưng áp dụng</span>
                                <?php endif; ?>
                            </td>
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
                    <tr id="noDataRow" class="<?= !empty($standards) ? 'd-none' : '' ?>">
                        <td colspan="7" class="text-center text-secondary py-4">Không có dữ liệu được ghi</td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('standardSearchInput');
    const tableBody = document.getElementById('standardsTableBody');
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
            const descCell = row.cells[2]?.textContent || '';
            const setCodeCell = row.cells[4]?.textContent || '';
            const textToMatch = normalizeText(codeCell + ' ' + nameCell + ' ' + descCell + ' ' + setCodeCell);

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
                    <input type="hidden" name="id" value="<?= (int) ($editingStandard['MaTieuChuan'] ?? 0) ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Tên tiêu chuẩn <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="ten_tieu_chuan" rows="2" placeholder="Nhập tên tiêu chuẩn" required><?= htmlspecialchars($editingStandard['TenTieuChuan'] ?? '') ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Mô tả</label>
                        <textarea class="form-control" name="mo_ta" rows="2" placeholder="Nhập mô tả tiêu chuẩn"><?= htmlspecialchars($editingStandard['MoTa'] ?? '') ?></textarea>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Thứ tự</label>
                            <input class="form-control" type="number" name="thu_tu" value="<?= (int) ($editingStandard['ThuTu'] ?? 0) ?>" placeholder="VD: 1">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Bộ tiêu chuẩn <span class="text-danger">*</span></label>
                            <select class="form-select" name="ma_bo_tieu_chuan" required>
                                <?php foreach ($standardSets as $set): ?>
                                    <option value="<?= $set['id'] ?>" <?= (int) ($editingStandard['MaBoTieuChuan'] ?? 1) === (int) $set['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($set['code'] . ' - ' . $set['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Trạng thái</label>
                        <select class="form-select" name="trang_thai">
                            <option value="1" <?= (int) ($editingStandard['TrangThai'] ?? 1) === 1 ? 'selected' : '' ?>>Đang hoạt động</option>
                            <option value="0" <?= (int) ($editingStandard['TrangThai'] ?? 1) === 0 ? 'selected' : '' ?>>Ngưng áp dụng</option>
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
