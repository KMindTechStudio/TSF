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
            $rawId         = trim($_POST['id'] ?? '');
            $maTieuChuan   = trim($_POST['ma_tieu_chuan'] ?? '');
            $standardSetId = trim($_POST['ma_bo_tieu_chuan'] ?? '');
            $name          = trim($_POST['ten_tieu_chuan'] ?? '');
            $description   = trim($_POST['mo_ta'] ?? '');
            $status        = (int) ($_POST['trang_thai'] ?? 1);

            if ($standardSetId === '' || $name === '') {
                throw new RuntimeException('Vui lòng chọn bộ tiêu chuẩn và nhập tên tiêu chuẩn.');
            }

            if ($rawId !== '') {
                if ($maTieuChuan === '') {
                    throw new RuntimeException('Vui lòng nhập Mã tiêu chuẩn.');
                }
                if ($maTieuChuan !== $rawId) {
                    $chk = $pdo->prepare('SELECT COUNT(*) FROM TieuChuan WHERE MaTieuChuan = :code');
                    $chk->execute(['code' => $maTieuChuan]);
                    if ((int) $chk->fetchColumn() > 0) {
                        throw new RuntimeException('Mã tiêu chuẩn "' . $maTieuChuan . '" đã tồn tại. Vui lòng nhập mã khác.');
                    }
                    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
                    $upChild = $pdo->prepare('UPDATE TieuChi SET MaTieuChuan = :new_code WHERE MaTieuChuan = :old_code');
                    $upChild->execute(['new_code' => $maTieuChuan, 'old_code' => $rawId]);
                }

                $stmt = $pdo->prepare('UPDATE TieuChuan SET MaTieuChuan = :new_code, MaBoTieuChuan = :set_id, TenTieuChuan = :name, MoTa = :description, TrangThai = :status WHERE MaTieuChuan = :old_code');
                $stmt->execute([
                    'new_code'    => $maTieuChuan,
                    'set_id'      => $standardSetId,
                    'name'        => $name,
                    'description' => $description,
                    'status'      => $status,
                    'old_code'    => $rawId,
                ]);
                if ($maTieuChuan !== $rawId) {
                    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
                }
                log_activity('cap_nhat', 'tieu_chuan', 0, $maTieuChuan . ' - ' . $name);
                $success = 'Cập nhật tiêu chuẩn thành công.';
            } else {
                if ($maTieuChuan === '') {
                    throw new RuntimeException('Vui lòng nhập Mã tiêu chuẩn.');
                }
                $chk = $pdo->prepare('SELECT COUNT(*) FROM TieuChuan WHERE MaTieuChuan = :code');
                $chk->execute(['code' => $maTieuChuan]);
                if ((int) $chk->fetchColumn() > 0) {
                    throw new RuntimeException('Mã tiêu chuẩn "' . $maTieuChuan . '" đã tồn tại. Vui lòng nhập mã khác.');
                }

                $stmt = $pdo->prepare('INSERT INTO TieuChuan (MaTieuChuan, MaBoTieuChuan, TenTieuChuan, MoTa, TrangThai) VALUES (:code, :set_id, :name, :description, :status)');
                $stmt->execute([
                    'code'        => $maTieuChuan,
                    'set_id'      => $standardSetId,
                    'name'        => $name,
                    'description' => $description,
                    'status'      => $status,
                ]);
                log_activity('them_moi', 'tieu_chuan', 0, $maTieuChuan . ' - ' . $name);
                $success = 'Thêm tiêu chuẩn thành công.';
            }
        }

        if ($action === 'delete_standard') {
            $id = trim($_POST['id'] ?? '');

            $checkChild = $pdo->prepare('SELECT COUNT(*) FROM TieuChi WHERE MaTieuChuan = :id');
            $checkChild->execute(['id' => $id]);
            if ((int) $checkChild->fetchColumn() > 0) {
                throw new RuntimeException('Không thể xóa tiêu chuẩn này vì đang có các tiêu chí trực thuộc.');
            }

            $stmt = $pdo->prepare('DELETE FROM TieuChuan WHERE MaTieuChuan = :id');
            $stmt->execute(['id' => $id]);
            log_activity('xoa', 'tieu_chuan', 0, $id);
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
$editId             = $isCreatingStandard ? '' : trim($_GET['edit'] ?? '');
$editingStandard    = null;
if ($editId !== '') {
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
                            <th class="text-nowrap" style="width: 120px;">Mã tiêu chuẩn</th>
                            <th style="min-width: 220px;">Tên tiêu chuẩn</th>
                            <th style="min-width: 220px;">Mô tả</th>
                            <th class="text-nowrap" style="width: 140px;">Mã bộ tiêu chuẩn</th>
                            <th class="text-end text-nowrap action-cell" style="width: 90px;">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody id="standardsTableBody">
                    <?php foreach ($standards as $standard): ?>
                        <tr>
                            <td class="fw-bold text-nowrap"><?= htmlspecialchars($standard['code']) ?></td>
                            <td class="fw-semibold"><?= htmlspecialchars($standard['name']) ?></td>
                            <td>
                                <div class="line-clamp-2 text-secondary" title="<?= htmlspecialchars($standard['description']) ?>">
                                    <?= htmlspecialchars($standard['description'] ?: '-') ?>
                                </div>
                            </td>
                            <td class="text-nowrap"><span class="badge bg-secondary"><?= htmlspecialchars($standard['set_code']) ?></span></td>
                            <td class="text-end action-cell">
                                <div class="action-buttons">
                                    <a class="btn btn-sm btn-outline-primary" href="?edit=<?= $standard['id'] ?>" title="Sửa"><i class="bi bi-pencil"></i></a>
                                    <form method="post" class="d-inline" data-confirm-form="Bạn chắc chắn muốn xóa tiêu chuẩn này?">
                                        <input type="hidden" name="action" value="delete_standard">
                                        <input type="hidden" name="id" value="<?= $standard['id'] ?>">
                                        <button class="btn btn-sm btn-outline-danger" type="submit" title="Xóa"><i class="bi bi-trash"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <tr id="noDataRow" class="<?= !empty($standards) ? 'd-none' : '' ?>">
                        <td colspan="5" class="text-center text-secondary py-4">Không có dữ liệu được ghi</td>
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
                    <input type="hidden" name="id" value="<?= htmlspecialchars($editingStandard['MaTieuChuan'] ?? '') ?>">
                    
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Mã tiêu chuẩn <span class="text-danger">*</span></label>
                            <input class="form-control" name="ma_tieu_chuan" value="<?= htmlspecialchars($editingStandard['MaTieuChuan'] ?? '') ?>" placeholder="VD: TC01 hoặc TC-01" required>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Tên tiêu chuẩn <span class="text-danger">*</span></label>
                            <textarea class="form-control" name="ten_tieu_chuan" rows="2" placeholder="Nhập tên tiêu chuẩn" required><?= htmlspecialchars($editingStandard['TenTieuChuan'] ?? '') ?></textarea>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Mô tả</label>
                        <textarea class="form-control" name="mo_ta" rows="2" placeholder="Nhập mô tả tiêu chuẩn"><?= htmlspecialchars($editingStandard['MoTa'] ?? '') ?></textarea>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Bộ tiêu chuẩn (Mã bộ tiêu chuẩn) <span class="text-danger">*</span></label>
                            <select class="form-select" name="ma_bo_tieu_chuan" required>
                                <?php foreach ($standardSets as $set): ?>
                                    <option value="<?= htmlspecialchars($set['id']) ?>" <?= (string) ($editingStandard['MaBoTieuChuan'] ?? '') === (string) $set['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($set['code'] . ' - ' . $set['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Trạng thái</label>
                            <select class="form-select" name="trang_thai">
                                <option value="1" <?= (int) ($editingStandard['TrangThai'] ?? 1) === 1 ? 'selected' : '' ?>>Đang hoạt động</option>
                                <option value="0" <?= (int) ($editingStandard['TrangThai'] ?? 1) === 0 ? 'selected' : '' ?>>Ngưng áp dụng</option>
                            </select>
                        </div>
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
