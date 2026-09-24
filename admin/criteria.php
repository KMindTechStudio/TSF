<?php
require_once __DIR__ . '/../includes/helpers.php';
require_roles(['admin']);
require_once __DIR__ . '/../config/database.php';

if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    require_once __DIR__ . '/../includes/data.php';

    $selectedStandard = trim($_GET['standard'] ?? $_GET['standard_id'] ?? '');
    $searchKeyword = trim($_GET['search'] ?? $_GET['q'] ?? '');

    if ($selectedStandard !== '') {
        $criteria = array_filter($criteria, function ($item) use ($selectedStandard) {
            return ($item['standard_code'] ?? '') === $selectedStandard || ($item['standard_id'] ?? '') === $selectedStandard;
        });
    }

    if ($searchKeyword !== '') {
        $criteria = array_filter($criteria, function ($item) use ($searchKeyword) {
            return search_contains($item['code'] ?? '', $searchKeyword)
                || search_contains($item['name'] ?? '', $searchKeyword)
                || search_contains($item['description'] ?? '', $searchKeyword);
        });
    }

    $columns = [
        ['key' => 'code', 'label' => 'Mã tiêu chí', 'align' => 'center', 'width' => '120px'],
        ['key' => 'name', 'label' => 'Tên tiêu chí', 'align' => 'left', 'width' => '300px'],
        ['key' => 'description', 'label' => 'Nội dung chi tiết', 'align' => 'left', 'width' => '350px'],
        ['key' => 'standard_code', 'label' => 'Thuộc Tiêu chuẩn', 'align' => 'center', 'width' => '140px'],
    ];

    export_to_excel('danh_sach_tieu_chi_' . date('Ymd_His') . '.xls', 'DANH SÁCH TIÊU CHÍ KIỂM ĐỊNH', $columns, array_values($criteria));
}

$pdo = db();
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'save_criterion') {
            $rawId       = trim($_POST['id'] ?? '');
            $maTieuChi   = trim($_POST['ma_tieu_chi'] ?? '');
            $standardId  = trim($_POST['ma_tieu_chuan'] ?? '');
            $name        = trim($_POST['ten_tieu_chi'] ?? '');
            $content     = trim($_POST['noi_dung'] ?? '');
            $status      = (int) ($_POST['trang_thai'] ?? 1);

            if ($standardId === '' || $name === '') {
                throw new RuntimeException('Vui lòng chọn tiêu chuẩn và nhập tên tiêu chí.');
            }

            if ($rawId !== '') {
                if ($maTieuChi === '') {
                    throw new RuntimeException('Vui lòng nhập Mã tiêu chí.');
                }
                if ($maTieuChi !== $rawId) {
                    $chk = $pdo->prepare('SELECT COUNT(*) FROM TieuChi WHERE MaTieuChi = :code');
                    $chk->execute(['code' => $maTieuChi]);
                    if ((int) $chk->fetchColumn() > 0) {
                        throw new RuntimeException('Mã tiêu chí "' . $maTieuChi . '" đã tồn tại. Vui lòng nhập mã khác.');
                    }
                    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
                    $upChild = $pdo->prepare('UPDATE MinhChung SET MaTieuChi = :new_code WHERE MaTieuChi = :old_code');
                    $upChild->execute(['new_code' => $maTieuChi, 'old_code' => $rawId]);
                }

                $stmt = $pdo->prepare('UPDATE TieuChi SET MaTieuChi = :new_code, MaTieuChuan = :standard_id, TenTieuChi = :name, NoiDung = :content, TrangThai = :status WHERE MaTieuChi = :old_code');
                $stmt->execute([
                    'new_code'    => $maTieuChi,
                    'standard_id' => $standardId,
                    'name'        => $name,
                    'content'     => $content,
                    'status'      => $status,
                    'old_code'    => $rawId,
                ]);
                if ($maTieuChi !== $rawId) {
                    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
                }
                log_activity('cap_nhat', 'tieu_chi', 0, $maTieuChi . ' - ' . $name);
                $success = 'Cập nhật tiêu chí thành công.';
            } else {
                if ($maTieuChi === '') {
                    throw new RuntimeException('Vui lòng nhập Mã tiêu chí.');
                }
                $chk = $pdo->prepare('SELECT COUNT(*) FROM TieuChi WHERE MaTieuChi = :code');
                $chk->execute(['code' => $maTieuChi]);
                if ((int) $chk->fetchColumn() > 0) {
                    throw new RuntimeException('Mã tiêu chí "' . $maTieuChi . '" đã tồn tại. Vui lòng nhập mã khác.');
                }

                $stmt = $pdo->prepare('INSERT INTO TieuChi (MaTieuChi, MaTieuChuan, TenTieuChi, NoiDung, TrangThai) VALUES (:code, :standard_id, :name, :content, :status)');
                $stmt->execute([
                    'code'        => $maTieuChi,
                    'standard_id' => $standardId,
                    'name'        => $name,
                    'content'     => $content,
                    'status'      => $status,
                ]);
                log_activity('them_moi', 'tieu_chi', 0, $maTieuChi . ' - ' . $name);
                $success = 'Thêm tiêu chí thành công.';
            }
        }

        if ($action === 'delete_criterion') {
            $id = trim($_POST['id'] ?? '');

            $stmt = $pdo->prepare('DELETE FROM TieuChi WHERE MaTieuChi = :id');
            $stmt->execute(['id' => $id]);
            log_activity('xoa', 'tieu_chi', 0, $id);
            $success = 'Xóa tiêu chí thành công.';
        }
    } catch (Throwable $exception) {
        $error = 'Không thể thực hiện thao tác: ' . $exception->getMessage();
    }
    unset($_GET['create'], $_GET['edit']);
}

require_once __DIR__ . '/../includes/data.php';

$searchKeyword    = trim($_GET['search'] ?? $_GET['q'] ?? '');
$selectedStandard = trim($_GET['standard_id'] ?? $_GET['standard'] ?? '');
$filterStd        = $selectedStandard;

$filteredCriteria = array_filter($criteria, function ($item) use ($searchKeyword, $filterStd) {
    if ($filterStd !== '' && (string) $item['standard_id'] !== (string) $filterStd) {
        return false;
    }

    if ($searchKeyword !== '') {
        $matchCode = search_contains($item['code'], $searchKeyword);
        $matchName = search_contains($item['name'], $searchKeyword);
        $matchDesc = search_contains($item['description'], $searchKeyword);
        $matchStd  = search_contains($item['standard'], $searchKeyword);
        if (!$matchCode && !$matchName && !$matchDesc && !$matchStd) {
            return false;
        }
    }

    return true;
});

try {
    $standardRows = $pdo->query('SELECT MaTieuChuan AS id, TenTieuChuan AS name FROM TieuChuan ORDER BY MaTieuChuan')->fetchAll();
} catch (Throwable $e) {
    $standardRows = [];
}

$isCreatingCriterion = isset($_GET['create']);
$editId              = $isCreatingCriterion ? '' : trim($_GET['edit'] ?? '');
$editingCriterion    = null;
if ($editId !== '') {
    $stmt = $pdo->prepare('SELECT * FROM TieuChi WHERE MaTieuChi = :id LIMIT 1');
    $stmt->execute(['id' => $editId]);
    $editingCriterion = $stmt->fetch();
}

$pageTitle = page_title('Quản lý tiêu chí');
$heading   = 'Quản lý tiêu chí';
include __DIR__ . '/../includes/header.php';
?>
<?php if (($editingCriterion || $isCreatingCriterion) && !$success && !$error): ?><script>document.body.dataset.autoOpenModal = 'criterionFormModal';</script><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="panel mb-4">
    <form class="row g-3 align-items-end" method="get" action="" id="criteriaFilterForm">
        <div class="col-md-4">
            <label class="form-label">Tiêu chuẩn</label>
            <select class="form-select" name="standard" id="filterStandard">
                <option value="">Tất cả tiêu chuẩn</option>
                <?php foreach ($standards as $standard): ?>
                    <option value="<?= htmlspecialchars($standard['code']) ?>" <?= ($selectedStandard === $standard['code']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($standard['code'] . ' - ' . $standard['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-5">
            <label class="form-label">Từ khóa</label>
            <input class="form-control" name="search" id="criterionSearchInput" placeholder="Nhập tên tiêu chí hoặc mô tả nội dung..." value="<?= htmlspecialchars($searchKeyword) ?>">
        </div>
        <div class="col-md-3">
            <button class="btn btn-primary w-100" type="submit"><i class="bi bi-search me-1"></i> Tìm kiếm</button>
        </div>
    </form>
</div>

<div class="row g-4 criteria-layout">
    <div class="col-12">
        <div class="panel criteria-list-panel">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="h5 mb-0">Danh sách tiêu chí</h2>
                <div class="d-flex gap-2">
                    <a class="btn btn-primary" href="<?= base_url('admin/criteria.php?create=1') ?>"><i class="bi bi-plus-circle me-1"></i> Thêm mới</a>
                    <a class="btn btn-outline-secondary" id="exportExcelBtn" href="<?= base_url('admin/criteria.php?export=excel' . ($selectedStandard ? '&standard=' . urlencode($selectedStandard) : '') . ($searchKeyword ? '&search=' . urlencode($searchKeyword) : '')) ?>"><i class="bi bi-file-earmark-spreadsheet me-1"></i> Xuất Excel</a>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table" data-page-size="10">
                    <thead>
                        <tr>
                            <th class="text-nowrap" style="width: 120px;">Mã tiêu chí</th>
                            <th style="min-width: 220px;">Tên tiêu chí</th>
                            <th style="min-width: 220px;">Nội dung</th>
                            <th class="text-nowrap" style="width: 140px;">Mã tiêu chuẩn</th>
                            <th class="text-end text-nowrap action-cell" style="width: 90px;">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody id="criteriaTableBody">
                    <?php foreach ($filteredCriteria as $item): ?>
                        <tr>
                            <td class="fw-bold text-nowrap"><?= htmlspecialchars($item['code']) ?></td>
                            <td class="fw-semibold"><?= htmlspecialchars($item['name']) ?></td>
                            <td>
                                <div class="line-clamp-2 text-secondary" title="<?= htmlspecialchars($item['description'] ?? '') ?>">
                                    <?= htmlspecialchars($item['description'] !== '' ? $item['description'] : '-') ?>
                                </div>
                            </td>
                            <td class="text-nowrap"><span class="badge bg-secondary"><?= htmlspecialchars($item['standard_code']) ?></span></td>
                            <td class="text-end action-cell">
                                <div class="action-buttons">
                                    <a class="btn btn-sm btn-outline-primary" href="?edit=<?= urlencode($item['id']) ?>" title="Sửa"><i class="bi bi-pencil"></i></a>
                                    <form method="post" class="d-inline" data-confirm-form="Bạn chắc chắn muốn xóa tiêu chí này?">
                                        <input type="hidden" name="action" value="delete_criterion">
                                        <input type="hidden" name="id" value="<?= htmlspecialchars($item['id']) ?>">
                                        <button class="btn btn-sm btn-outline-danger" type="submit" title="Xóa"><i class="bi bi-trash"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <tr id="noDataRow" class="<?= !empty($criteria) ? 'd-none' : '' ?>">
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
    const searchInput = document.getElementById('criterionSearchInput');
    const filterStandard = document.getElementById('filterStandard');
    const tableBody = document.getElementById('criteriaTableBody');
    const noDataRow = document.getElementById('noDataRow');
    if (!tableBody) return;

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
        const query = normalizeText(searchInput ? searchInput.value : '');
        const selectedStd = normalizeText(filterStandard ? filterStandard.value : '');
        const rows = tableBody.querySelectorAll('tr:not(#noDataRow)');
        let visibleCount = 0;

        rows.forEach(row => {
            const codeCell = row.cells[0]?.textContent || '';
            const nameCell = row.cells[1]?.textContent || '';
            const descCell = row.cells[2]?.textContent || '';
            const stdCell  = row.cells[4]?.textContent || '';

            const textToMatch = normalizeText(codeCell + ' ' + nameCell + ' ' + descCell + ' ' + stdCell);
            const stdText = normalizeText(stdCell);

            const matchesQuery = query === '' || textToMatch.includes(query);
            const matchesStd   = selectedStd === '' || stdText.includes(selectedStd);

            if (matchesQuery && matchesStd) {
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

    if (searchInput) searchInput.addEventListener('input', filterTable);
    if (filterStandard) filterStandard.addEventListener('change', filterTable);

    const exportBtn = document.getElementById('exportExcelBtn');
    if (exportBtn) {
        exportBtn.addEventListener('click', function (e) {
            e.preventDefault();
            const stdVal = filterStandard ? filterStandard.value.trim() : '';
            const searchVal = searchInput ? searchInput.value.trim() : '';
            let url = '<?= base_url('admin/criteria.php?export=excel') ?>';
            if (stdVal) url += '&standard=' + encodeURIComponent(stdVal);
            if (searchVal) url += '&search=' + encodeURIComponent(searchVal);
            window.location.href = url;
        });
    }
});
</script>

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
                <input type="hidden" name="id" value="<?= htmlspecialchars($editingCriterion['MaTieuChi'] ?? '') ?>">
                
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label class="form-label">Mã tiêu chí <span class="text-danger">*</span></label>
                        <input class="form-control" name="ma_tieu_chi" value="<?= htmlspecialchars($editingCriterion['MaTieuChi'] ?? '') ?>" placeholder="VD: TC01.1 hoặc TCHI01" required>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">Tên tiêu chí <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="ten_tieu_chi" rows="2" placeholder="Nhập tên tiêu chí" required><?= htmlspecialchars($editingCriterion['TenTieuChi'] ?? '') ?></textarea>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Nội dung</label>
                    <textarea class="form-control" name="noi_dung" rows="3" placeholder="Nhập nội dung mô tả tiêu chí"><?= htmlspecialchars($editingCriterion['NoiDung'] ?? '') ?></textarea>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Tiêu chuẩn (Mã tiêu chuẩn) <span class="text-danger">*</span></label>
                        <select class="form-select" name="ma_tieu_chuan" required>
                            <?php foreach ($standardRows as $standard): ?>
                                <option value="<?= htmlspecialchars($standard['id']) ?>" <?= (string) ($editingCriterion['MaTieuChuan'] ?? '') === (string) $standard['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($standard['id'] . ' - ' . $standard['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Trạng thái</label>
                        <select class="form-select" name="trang_thai">
                            <option value="1" <?= (int) ($editingCriterion['TrangThai'] ?? 1) === 1 ? 'selected' : '' ?>>Đang hoạt động</option>
                            <option value="0" <?= (int) ($editingCriterion['TrangThai'] ?? 1) === 0 ? 'selected' : '' ?>>Ngưng áp dụng</option>
                        </select>
                    </div>
                </div>

                <div class="d-flex gap-2 justify-content-end">
                    <?php if ($editingCriterion): ?>
                        <a class="btn btn-outline-secondary" href="<?= base_url('admin/criteria.php') ?>">Hủy sửa</a>
                    <?php else: ?>
                        <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Hủy</button>
                    <?php endif; ?>
                    <button class="btn btn-primary" type="submit"><i class="bi bi-save me-1"></i> Lưu tiêu chí</button>
                </div>
            </form>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
