<?php
require_once __DIR__ . '/../includes/helpers.php';
require_roles(['admin']);
require_once __DIR__ . '/../config/database.php';

if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    require_once __DIR__ . '/../includes/data.php';

    $searchKeyword = trim($_GET['search'] ?? $_GET['q'] ?? '');
    if ($searchKeyword !== '') {
        $standardSets = array_filter($standardSets, function ($set) use ($searchKeyword) {
            return search_contains($set['code'], $searchKeyword) || search_contains($set['name'], $searchKeyword) || search_contains($set['thong_tu'], $searchKeyword);
        });
    }

    $columns = [
        ['key' => 'code', 'label' => 'Mã bộ tiêu chuẩn', 'align' => 'center', 'width' => '140px'],
        ['key' => 'name', 'label' => 'Tên bộ tiêu chuẩn', 'align' => 'left', 'width' => '280px'],
        ['key' => 'thong_tu', 'label' => 'Thông tư ban hành', 'align' => 'left', 'width' => '200px'],
        ['key' => 'ngay_ban_hanh', 'label' => 'Ngày ban hành', 'align' => 'center', 'width' => '120px'],
        ['key' => 'status', 'label' => 'Trạng thái', 'align' => 'center', 'width' => '130px'],
        ['key' => 'description', 'label' => 'Mô tả', 'align' => 'left', 'width' => '250px'],
    ];

    export_to_excel('danh_sach_bo_tieu_chuan_' . date('Ymd_His') . '.xls', 'DANH SÁCH BỘ TIÊU CHUẨN KIỂM ĐỊNH', $columns, array_values($standardSets));
}

$pdo = db();
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'save_standard_set') {
            $rawId          = trim($_POST['id'] ?? '');
            $maBoTieuChuan  = trim($_POST['ma_bo_tieu_chuan'] ?? '');
            $name           = trim($_POST['ten_bo_tieu_chuan'] ?? '');
            $thongTu        = trim($_POST['thong_tu'] ?? '');
            $ngayBanHanh    = trim($_POST['ngay_ban_hanh'] ?? '');
            $description    = trim($_POST['mo_ta'] ?? '');
            $status         = (int) ($_POST['trang_thai'] ?? 1);

            if ($name === '') {
                throw new RuntimeException('Vui lòng nhập Tên bộ tiêu chuẩn.');
            }

            if ($rawId !== '') {
                if ($maBoTieuChuan === '') {
                    throw new RuntimeException('Vui lòng nhập Mã bộ tiêu chuẩn.');
                }
                if ($maBoTieuChuan !== $rawId) {
                    $chk = $pdo->prepare('SELECT COUNT(*) FROM BoTieuChuan WHERE MaBoTieuChuan = :code');
                    $chk->execute(['code' => $maBoTieuChuan]);
                    if ((int) $chk->fetchColumn() > 0) {
                        throw new RuntimeException('Mã bộ tiêu chuẩn "' . $maBoTieuChuan . '" đã tồn tại. Vui lòng nhập mã khác.');
                    }
                    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
                    $upChild = $pdo->prepare('UPDATE TieuChuan SET MaBoTieuChuan = :new_code WHERE MaBoTieuChuan = :old_code');
                    $upChild->execute(['new_code' => $maBoTieuChuan, 'old_code' => $rawId]);
                }

                $stmt = $pdo->prepare('UPDATE BoTieuChuan SET MaBoTieuChuan = :new_code, TenBoTieuChuan = :name, ThongTu = :thong_tu, NgayBanHanh = :ngay_ban_hanh, MoTa = :description, TrangThai = :status WHERE MaBoTieuChuan = :old_code');
                $stmt->execute([
                    'new_code'     => $maBoTieuChuan,
                    'name'         => $name,
                    'thong_tu'     => $thongTu,
                    'ngay_ban_hanh'=> $ngayBanHanh ?: null,
                    'description'  => $description,
                    'status'       => $status,
                    'old_code'     => $rawId,
                ]);
                if ($maBoTieuChuan !== $rawId) {
                    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
                }
                log_activity('cap_nhat', 'bo_tieu_chuan', 0, $maBoTieuChuan . ' - ' . $name);
                $success = 'Cập nhật bộ tiêu chuẩn thành công.';
            } else {
                if ($maBoTieuChuan === '') {
                    throw new RuntimeException('Vui lòng nhập Mã bộ tiêu chuẩn.');
                }
                $chk = $pdo->prepare('SELECT COUNT(*) FROM BoTieuChuan WHERE MaBoTieuChuan = :code');
                $chk->execute(['code' => $maBoTieuChuan]);
                if ((int) $chk->fetchColumn() > 0) {
                    throw new RuntimeException('Mã bộ tiêu chuẩn "' . $maBoTieuChuan . '" đã tồn tại. Vui lòng nhập mã khác.');
                }

                $stmt = $pdo->prepare('INSERT INTO BoTieuChuan (MaBoTieuChuan, TenBoTieuChuan, ThongTu, NgayBanHanh, MoTa, TrangThai) VALUES (:code, :name, :thong_tu, :ngay_ban_hanh, :description, :status)');
                $stmt->execute([
                    'code'         => $maBoTieuChuan,
                    'name'         => $name,
                    'thong_tu'     => $thongTu,
                    'ngay_ban_hanh'=> $ngayBanHanh ?: null,
                    'description'  => $description,
                    'status'       => $status,
                ]);
                log_activity('them_moi', 'bo_tieu_chuan', 0, $maBoTieuChuan . ' - ' . $name);
                $success = 'Thêm bộ tiêu chuẩn thành công.';
            }
        }

        if ($action === 'delete_standard_set') {
            $id = trim($_POST['id'] ?? '');

            $checkChild = $pdo->prepare('SELECT COUNT(*) FROM TieuChuan WHERE MaBoTieuChuan = :id');
            $checkChild->execute(['id' => $id]);
            if ((int) $checkChild->fetchColumn() > 0) {
                throw new RuntimeException('Không thể xóa bộ tiêu chuẩn này vì đang có các tiêu chuẩn thuộc bộ tiêu chuẩn.');
            }

            $stmt = $pdo->prepare('DELETE FROM BoTieuChuan WHERE MaBoTieuChuan = :id');
            $stmt->execute(['id' => $id]);
            log_activity('xoa', 'bo_tieu_chuan', 0, $id);
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
               search_contains($set['thong_tu'] ?? '', $searchKeyword);
    });
}

$isCreatingSet = isset($_GET['create']);
$editId        = $isCreatingSet ? '' : trim($_GET['edit'] ?? '');
$editingSet    = null;
if ($editId !== '') {
    $stmt = $pdo->prepare('SELECT * FROM BoTieuChuan WHERE MaBoTieuChuan = :id LIMIT 1');
    $stmt->execute(['id' => $editId]);
    $editingSet = $stmt->fetch();
}

$pageTitle = page_title('Quản lý bộ tiêu chuẩn');
$heading   = 'Quản lý bộ tiêu chuẩn';
include __DIR__ . '/../includes/header.php';
?>
<?php if (($editingSet || $isCreatingSet) && !$success && !$error): ?><script>document.body.dataset.autoOpenModal = 'standardSetFormModal';</script><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="row g-4 standards-layout">
    <div class="col-12">
        <div class="panel standard-sets-list-panel">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
                <h2 class="h5 mb-0">Danh sách bộ tiêu chuẩn</h2>
                <div class="d-flex gap-2">
                    <a class="btn btn-primary" href="<?= base_url('admin/standard_sets.php?create=1') ?>"><i class="bi bi-plus-circle me-1"></i> Thêm mới</a>
                    <a class="btn btn-outline-secondary" id="exportExcelBtn" href="<?= base_url('admin/standard_sets.php?export=excel' . ($searchKeyword !== '' ? '&search=' . urlencode($searchKeyword) : '')) ?>"><i class="bi bi-file-earmark-spreadsheet me-1"></i> Xuất Excel</a>
                </div>
            </div>
            <form method="get" action="" class="mb-3" id="standardSetSearchForm">
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" id="standardSetSearchInput" class="form-control" placeholder="Nhập mã, tên bộ tiêu chuẩn hoặc thông tư để tìm kiếm..." value="<?= htmlspecialchars($searchKeyword) ?>">
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
                            <th class="text-nowrap" style="width: 130px;">Mã bộ tiêu chuẩn</th>
                            <th style="min-width: 200px;">Tên bộ tiêu chuẩn</th>
                            <th class="text-nowrap" style="min-width: 180px;">Thông tư</th>
                            <th class="text-nowrap" style="width: 130px;">Ngày ban hành</th>
                            <th style="min-width: 200px;">Mô tả</th>
                            <th class="text-end text-nowrap action-cell" style="width: 90px;">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody id="standardSetsTableBody">
                    <?php foreach ($standardSets as $set): ?>
                        <tr>
                            <td class="fw-bold text-nowrap"><?= htmlspecialchars($set['code']) ?></td>
                            <td class="fw-semibold"><?= htmlspecialchars($set['name']) ?></td>
                            <td class="text-nowrap"><span class="badge bg-secondary"><?= htmlspecialchars($set['thong_tu']) ?></span></td>
                            <td class="text-nowrap"><?= htmlspecialchars($set['ngay_ban_hanh']) ?></td>
                            <td>
                                <div class="line-clamp-2 text-secondary" title="<?= htmlspecialchars($set['description']) ?>">
                                    <?= htmlspecialchars($set['description'] ?: '-') ?>
                                </div>
                            </td>
                            <td class="text-end action-cell">
                                <div class="action-buttons">
                                    <a class="btn btn-sm btn-outline-primary" href="?edit=<?= $set['id'] ?>" title="Sửa"><i class="bi bi-pencil"></i></a>
                                    <form method="post" class="d-inline" data-confirm-form="Bạn chắc chắn muốn xóa bộ tiêu chuẩn này?">
                                        <input type="hidden" name="action" value="delete_standard_set">
                                        <input type="hidden" name="id" value="<?= $set['id'] ?>">
                                        <button class="btn btn-sm btn-outline-danger" type="submit" title="Xóa"><i class="bi bi-trash"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <tr id="noDataRow" class="<?= !empty($standardSets) ? 'd-none' : '' ?>">
                        <td colspan="6" class="text-center text-secondary py-4">Không có dữ liệu được ghi</td>
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
            const ttCell   = row.cells[2]?.textContent || '';
            const dateCell = row.cells[3]?.textContent || '';
            const descCell = row.cells[4]?.textContent || '';
            const textToMatch = normalizeText(codeCell + ' ' + nameCell + ' ' + ttCell + ' ' + dateCell + ' ' + descCell);

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

    const exportBtn = document.getElementById('exportExcelBtn');
    if (exportBtn && searchInput) {
        exportBtn.addEventListener('click', function (e) {
            e.preventDefault();
            const keyword = searchInput.value.trim();
            let url = '<?= base_url('admin/standard_sets.php?export=excel') ?>';
            if (keyword) {
                url += '&search=' + encodeURIComponent(keyword);
            }
            window.location.href = url;
        });
    }
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
                    <input type="hidden" name="id" value="<?= htmlspecialchars($editingSet['MaBoTieuChuan'] ?? '') ?>">
                    
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Mã bộ tiêu chuẩn <span class="text-danger">*</span></label>
                            <input class="form-control" name="ma_bo_tieu_chuan" value="<?= htmlspecialchars($editingSet['MaBoTieuChuan'] ?? '') ?>" placeholder="VD: BTC01 hoặc BTC-2025" required>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Tên bộ tiêu chuẩn <span class="text-danger">*</span></label>
                            <input class="form-control" name="ten_bo_tieu_chuan" value="<?= htmlspecialchars($editingSet['TenBoTieuChuan'] ?? '') ?>" placeholder="Nhập tên bộ tiêu chuẩn" required>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Thông tư</label>
                            <input class="form-control" name="thong_tu" value="<?= htmlspecialchars($editingSet['ThongTu'] ?? ($editingSet['CoQuanBanHanh'] ?? 'Thông tư 04/2016/TT-BGDĐT')) ?>" placeholder="VD: Thông tư 04/2016/TT-BGDĐT">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Ngày ban hành</label>
                            <input class="form-control" type="date" name="ngay_ban_hanh" value="<?= htmlspecialchars($editingSet['NgayBanHanh'] ?? '2025-01-15') ?>">
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
