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
        if ($action === 'save_standard_set') {
            $id          = (int) ($_POST['id'] ?? 0);
            $name        = trim($_POST['ten_bo_tieu_chuan'] ?? '');
            $issuingBody = trim($_POST['co_quan_ban_hanh'] ?? '');
            $version     = (int) ($_POST['nam_ban_hanh'] ?? date('Y'));
            $description = trim($_POST['mo_ta'] ?? '');
            $status      = (int) ($_POST['trang_thai'] ?? 1);

            if ($name === '') {
                throw new RuntimeException('Vui lòng nhập Tên bộ tiêu chuẩn.');
            }

            if ($id > 0) {
                $stmt = $pdo->prepare('UPDATE BoTieuChuan SET TenBoTieuChuan = :name, CoQuanBanHanh = :issuing_body, NamBanHanh = :version, MoTa = :description, TrangThai = :status WHERE MaBoTieuChuan = :id');
                $stmt->execute([
                    'name'         => $name,
                    'issuing_body' => $issuingBody,
                    'version'      => $version,
                    'description'  => $description,
                    'status'       => $status,
                    'id'           => $id,
                ]);
                log_activity('cap_nhat', 'bo_tieu_chuan', $id, 'BTC' . str_pad($id, 2, '0', STR_PAD_LEFT) . ' - ' . $name);
                $success = 'Cập nhật bộ tiêu chuẩn thành công.';
            } else {
                $stmt = $pdo->prepare('INSERT INTO BoTieuChuan (TenBoTieuChuan, CoQuanBanHanh, NamBanHanh, MoTa, TrangThai) VALUES (:name, :issuing_body, :version, :description, :status)');
                $stmt->execute([
                    'name'         => $name,
                    'issuing_body' => $issuingBody,
                    'version'      => $version,
                    'description'  => $description,
                    'status'       => $status,
                ]);
                $newId = (int) $pdo->lastInsertId();
                log_activity('them_moi', 'bo_tieu_chuan', $newId, 'BTC' . str_pad($newId, 2, '0', STR_PAD_LEFT) . ' - ' . $name);
                $success = 'Thêm bộ tiêu chuẩn thành công.';
            }
        }

        if ($action === 'delete_standard_set') {
            $id = (int) ($_POST['id'] ?? 0);

            $checkChild = $pdo->prepare('SELECT COUNT(*) FROM TieuChuan WHERE MaBoTieuChuan = :id');
            $checkChild->execute(['id' => $id]);
            if ((int) $checkChild->fetchColumn() > 0) {
                throw new RuntimeException('Không thể xóa bộ tiêu chuẩn này vì đang có các tiêu chuẩn thuộc bộ tiêu chuẩn.');
            }

            $setCode = 'BTC' . str_pad($id, 2, '0', STR_PAD_LEFT);

            $stmt = $pdo->prepare('DELETE FROM BoTieuChuan WHERE MaBoTieuChuan = :id');
            $stmt->execute(['id' => $id]);
            log_activity('xoa', 'bo_tieu_chuan', $id, $setCode);
            $success = 'Xóa bộ tiêu chuẩn thành công.';
        }
    } catch (Throwable $exception) {
        $error = 'Không thể thực hiện thao tác: ' . $exception->getMessage();
    }
    unset($_GET['create'], $_GET['edit']);
}

require_once __DIR__ . '/../includes/data.php';

$searchKeyword = trim($_GET['search'] ?? $_GET['q'] ?? '');
if ($searchKeyword !== '') {
    $standardSets = array_filter($standardSets, function ($set) use ($searchKeyword) {
        return search_contains($set['code'] ?? '', $searchKeyword) || 
               search_contains($set['name'] ?? '', $searchKeyword) ||
               search_contains($set['version'] ?? '', $searchKeyword);
    });
}

$isCreatingSet = isset($_GET['create']);
$editId        = $isCreatingSet ? 0 : (int) ($_GET['edit'] ?? 0);
$editingSet    = null;
if ($editId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM BoTieuChuan WHERE MaBoTieuChuan = :id LIMIT 1');
    $stmt->execute(['id' => $editId]);
    $editingSet = $stmt->fetch();
}

$pageTitle = page_title('Quản lý bộ tiêu chuẩn');
$heading   = 'Quản lý bộ tiêu chuẩn kiểm định';
include __DIR__ . '/../includes/header.php';
?>
<?php if (($editingSet || $isCreatingSet) && !$success && !$error): ?><script>document.body.dataset.autoOpenModal = 'standardSetFormModal';</script><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="row g-4 standards-layout">
    <div class="col-12">
        <div class="panel standards-list-panel">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
                <h2 class="h5 mb-0">Danh sách bộ tiêu chuẩn</h2>
                <div class="d-flex gap-2">
                    <a class="btn btn-primary" href="<?= base_url('admin/standard_sets.php?create=1') ?>"><i class="bi bi-plus-circle me-1"></i> Thêm mới</a>
                    <button class="btn btn-outline-secondary" type="button"><i class="bi bi-file-earmark-spreadsheet me-1"></i> Xuất Excel</button>
                </div>
            </div>
            <form method="get" action="" class="mb-3" id="standardSetSearchForm">
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" id="standardSetSearchInput" class="form-control" placeholder="Nhập mã, tên bộ tiêu chuẩn hoặc năm phát hành để tìm kiếm..." value="<?= htmlspecialchars($searchKeyword) ?>">
                    <?php if ($searchKeyword !== ''): ?>
                        <a href="<?= base_url('admin/standard_sets.php') ?>" class="btn btn-outline-secondary" title="Xóa tìm kiếm"><i class="bi bi-x-lg"></i></a>
                    <?php endif; ?>
                    <button class="btn btn-primary" type="submit">Tìm kiếm</button>
                </div>
            </form>
            <div class="table-responsive">
                <table class="table" data-page-size="10">
                    <thead>
                        <tr>
                            <th>Mã bộ</th>
                            <th>Tên bộ tiêu chuẩn</th>
                            <th>Cơ quan ban hành</th>
                            <th>Năm ban hành</th>
                            <th>Mô tả</th>
                            <th>Trạng thái</th>
                            <th class="text-end">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody id="standardSetsTableBody">
                    <?php foreach ($standardSets as $set): ?>
                        <tr>
                            <td class="fw-bold"><?= htmlspecialchars($set['code']) ?></td>
                            <td class="fw-semibold"><?= htmlspecialchars($set['name']) ?></td>
                            <td><?= htmlspecialchars($set['issuing_body'] ?: 'Chưa cập nhật') ?></td>
                            <td><?= htmlspecialchars($set['year']) ?></td>
                            <td style="max-width: 250px;">
                                <div class="text-truncate" title="<?= htmlspecialchars($set['description']) ?>">
                                    <?= htmlspecialchars($set['description'] ?: '-') ?>
                                </div>
                            </td>
                            <td>
                                <?php if (($set['status_raw'] ?? '') === 'active' || $set['status'] === 'Đang hoạt động'): ?>
                                    <span class="badge bg-success">Đang hoạt động</span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark">Ngưng áp dụng</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end action-cell">
                                <div class="action-buttons">
                                    <a class="btn btn-sm btn-outline-primary" href="?edit=<?= $set['id'] ?>"><i class="bi bi-pencil"></i></a>
                                    <form method="post" class="d-inline" data-confirm-form="Bạn chắc chắn muốn xóa bộ tiêu chuẩn này?">
                                        <input type="hidden" name="action" value="delete_standard_set">
                                        <input type="hidden" name="id" value="<?= $set['id'] ?>">
                                        <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <tr id="noDataRow" class="<?= !empty($standardSets) ? 'd-none' : '' ?>">
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
    const searchInput = document.getElementById('standardSetSearchInput');
    const tableBody = document.getElementById('standardSetsTableBody');
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
            const issuingCell = row.cells[2]?.textContent || '';
            const yearCell = row.cells[3]?.textContent || '';
            const descCell = row.cells[4]?.textContent || '';
            const textToMatch = normalizeText(codeCell + ' ' + nameCell + ' ' + issuingCell + ' ' + yearCell + ' ' + descCell);

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

<div class="modal fade management-form-modal" id="standardSetFormModal" tabindex="-1" aria-labelledby="standardSetFormModalLabel" aria-hidden="true" <?= ($editingSet || $isCreatingSet) ? 'data-auto-open-modal' : '' ?>>
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5" id="standardSetFormModalLabel"><?= $editingSet ? 'Sửa bộ tiêu chuẩn' : 'Thêm bộ tiêu chuẩn' ?></h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <div class="modal-body">
                <form method="post">
                    <input type="hidden" name="action" value="save_standard_set">
                    <input type="hidden" name="id" value="<?= (int) ($editingSet['MaBoTieuChuan'] ?? 0) ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Tên bộ tiêu chuẩn <span class="text-danger">*</span></label>
                        <input class="form-control" name="ten_bo_tieu_chuan" value="<?= htmlspecialchars($editingSet['TenBoTieuChuan'] ?? '') ?>" placeholder="Nhập tên bộ tiêu chuẩn" required>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Cơ quan ban hành</label>
                            <input class="form-control" name="co_quan_ban_hanh" value="<?= htmlspecialchars($editingSet['CoQuanBanHanh'] ?? 'Bộ Giáo dục và Đào tạo') ?>" placeholder="VD: Bộ Giáo dục và Đào tạo">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Năm ban hành</label>
                            <input class="form-control" type="number" name="nam_ban_hanh" value="<?= htmlspecialchars($editingSet['NamBanHanh'] ?? date('Y')) ?>" placeholder="VD: 2025" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Mô tả</label>
                        <textarea class="form-control" name="mo_ta" rows="3" placeholder="Nhập mô tả bộ tiêu chuẩn"><?= htmlspecialchars($editingSet['MoTa'] ?? '') ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Trạng thái</label>
                        <select class="form-select" name="trang_thai">
                            <option value="1" <?= (int) ($editingSet['TrangThai'] ?? 1) === 1 ? 'selected' : '' ?>>Đang hoạt động</option>
                            <option value="0" <?= (int) ($editingSet['TrangThai'] ?? 1) === 0 ? 'selected' : '' ?>>Ngưng áp dụng</option>
                        </select>
                    </div>

                    <div class="d-flex gap-2 justify-content-end">
                        <?php if ($editingSet): ?>
                            <a class="btn btn-outline-secondary" href="<?= base_url('admin/standard_sets.php') ?>">Hủy sửa</a>
                        <?php else: ?>
                            <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Hủy</button>
                        <?php endif; ?>
                        <button class="btn btn-primary" type="submit"><i class="bi bi-save me-1"></i> Lưu bộ tiêu chuẩn</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
