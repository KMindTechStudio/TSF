<?php
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/data.php';

$searchCode = trim($_GET['code'] ?? '');
$searchName = trim($_GET['name'] ?? '');
$selectedStandard = trim($_GET['standard'] ?? '');
$selectedCriterion = trim($_GET['criterion'] ?? '');
$selectedYear = trim($_GET['year'] ?? '');
$selectedType = trim($_GET['type'] ?? '');
$downloadError = $_GET['download_error'] ?? '';

$filteredEvidences = array_filter($evidences, function ($item) use ($searchCode, $searchName, $selectedStandard, $selectedCriterion, $selectedYear, $selectedType, $criteria) {
    if ($searchCode !== '' && !search_contains($item['code'] ?? '', $searchCode)) {
        return false;
    }

    if ($searchName !== '' && !search_contains($item['name'] ?? '', $searchName)) {
        return false;
    }

    if ($selectedStandard !== '') {
        $standardCriteriaCodes = [];
        foreach ($criteria as $criterion) {
            if ($criterion['standard'] === $selectedStandard) {
                $standardCriteriaCodes[] = $criterion['code'];
            }
        }

        $matchedStandard = false;
        foreach ($standardCriteriaCodes as $code) {
            if (search_contains($item['criteria'], $code)) {
                $matchedStandard = true;
                break;
            }
        }

        if (!$matchedStandard) {
            return false;
        }
    }

    if ($selectedCriterion !== '' && !search_contains($item['criteria'] ?? '', $selectedCriterion)) {
        return false;
    }

    if ($selectedYear !== '' && ($item['year'] ?? '') !== $selectedYear) {
        return false;
    }

    if ($selectedType !== '' && search_contains($item['type_name'] ?? '', $selectedType) === false) {
        return false;
    }

    return true;
});

$filteredEvidences = array_values($filteredEvidences);
$years = array_values(array_unique(array_filter(array_column($evidences, 'year'))));
$types = array_values(array_unique(array_filter(array_column($evidences, 'type_name'))));

$pageTitle = page_title('Tìm kiếm minh chứng');
$heading = 'Tìm kiếm và khai thác minh chứng';
include __DIR__ . '/../includes/header.php';
?>
<div class="panel mb-4">
    <form class="row g-3 align-items-end" method="get">
        <div class="col-md-6 col-lg-4">
            <label class="form-label">Mã minh chứng</label>
            <input class="form-control" name="code" value="<?= htmlspecialchars($searchCode) ?>" placeholder="Mã minh chứng">
        </div>
        <div class="col-md-6 col-lg-4">
            <label class="form-label">Tên minh chứng</label>
            <input class="form-control" name="name" value="<?= htmlspecialchars($searchName) ?>" placeholder="Tên minh chứng">
        </div>
        <div class="col-md-6 col-lg-4">
            <label class="form-label">Tiêu chuẩn</label>
            <select class="form-select" name="standard">
                <option value="">Tất cả tiêu chuẩn</option>
                <?php foreach ($standards as $standard): ?>
                    <option value="<?= htmlspecialchars($standard['code']) ?>" <?= $selectedStandard === $standard['code'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($standard['code']) ?> - <?= htmlspecialchars($standard['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-6 col-lg-3">
            <label class="form-label">Tiêu chí</label>
            <select class="form-select" name="criterion">
                <option value="">Tất cả tiêu chí</option>
                <?php foreach ($criteria as $criterion): ?>
                    <option value="<?= htmlspecialchars($criterion['code']) ?>" <?= $selectedCriterion === $criterion['code'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($criterion['code']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-6 col-lg-3">
            <label class="form-label">Năm học</label>
            <select class="form-select" name="year">
                <option value="">Tất cả năm học</option>
                <?php foreach ($years as $year): ?>
                    <option value="<?= htmlspecialchars($year) ?>" <?= $selectedYear === $year ? 'selected' : '' ?>>
                        <?= htmlspecialchars($year) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-6 col-lg-3">
            <label class="form-label">Loại file</label>
            <select class="form-select" name="type">
                <option value="">Tất cả loại file</option>
                <?php foreach ($types as $type): ?>
                    <option value="<?= htmlspecialchars($type) ?>" <?= strtoupper($selectedType) === strtoupper($type) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($type) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-6 col-lg-3">
            <button class="btn btn-primary w-100" type="submit"><i class="bi bi-search me-1"></i> Tìm kiếm</button>
        </div>
    </form>
</div>

<?php if ($downloadError): ?>
    <div class="alert alert-warning">
        Không thể tải minh chứng. Vui lòng kiểm tra lại tệp đính kèm trong hệ thống.
    </div>
<?php endif; ?>

<div class="panel">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="h5 mb-0">Kết quả tìm kiếm</h2>
            <?php if ($searchCode !== '' || $searchName !== ''): ?>
                <small class="text-secondary">
                    <?php 
                    $filters = [];
                    if ($searchCode !== '') $filters[] = "Mã: “" . htmlspecialchars($searchCode) . "”";
                    if ($searchName !== '') $filters[] = "Tên: “" . htmlspecialchars($searchName) . "”";
                    echo implode(' | ', $filters);
                    ?>
                </small>
            <?php endif; ?>
        </div>
        <span class="text-secondary"><?= count($filteredEvidences) ?> minh chứng phù hợp</span>
    </div>

    <?php if (empty($filteredEvidences)): ?>
        <div class="alert alert-warning mb-0">
            Không tìm thấy minh chứng phù hợp. Vui lòng thử từ khóa hoặc bộ lọc khác.
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table" data-page-size="10">
                <thead>
                <tr>
                    <th class="text-nowrap" style="width: 110px;">Mã minh chứng</th>
                    <th style="min-width: 180px; max-width: 240px;">Tên minh chứng</th>
                    <th style="min-width: 180px; max-width: 240px;">Mô tả</th>
                    <th class="text-nowrap" style="min-width: 110px; max-width: 130px;">Tệp tin</th>
                    <th class="text-nowrap" style="width: 80px;">Năm học</th>
                    <th class="text-nowrap" style="width: 120px;">Ngày cập nhật</th>
                    <th class="text-nowrap" style="width: 110px;">Trạng thái</th>
                    <th class="text-nowrap" style="width: 85px;">Mã tiêu chí</th>
                    <th class="text-nowrap" style="width: 85px;">Mã người dùng</th>
                    <th class="text-end text-nowrap action-cell" style="width: 100px;">Thao tác</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($filteredEvidences as $item): ?>
                    <tr>
                        <td class="fw-bold text-nowrap"><?= htmlspecialchars($item['code']) ?></td>
                        <td class="fw-semibold" style="min-width: 180px; max-width: 240px;">
                            <div class="line-clamp-2" title="<?= htmlspecialchars($item['name']) ?>">
                                <?= htmlspecialchars($item['name']) ?>
                            </div>
                        </td>
                        <td style="min-width: 180px; max-width: 240px;">
                            <div class="line-clamp-2 text-secondary" title="<?= htmlspecialchars($item['description']) ?>">
                                <?= htmlspecialchars($item['description'] ?: '-') ?>
                            </div>
                        </td>
                        <td class="text-nowrap">
                            <?php if (!empty($item['file_path'])): ?>
                                <code class="d-inline-block text-truncate align-middle" style="max-width: 130px;" title="<?= htmlspecialchars(basename($item['file_path'])) ?>">
                                    <?= htmlspecialchars(basename($item['file_path'])) ?>
                                </code>
                            <?php else: ?>
                                <span class="text-secondary small">Không có</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-nowrap"><?= htmlspecialchars($item['year'] ?: '-') ?></td>
                        <td class="text-nowrap small text-secondary"><?= htmlspecialchars($item['updated'] ?: '-') ?></td>
                        <td class="text-nowrap">
                            <?php if ((int) ($item['status_raw'] ?? 1) === 1): ?>
                                <span class="badge bg-success">Đang áp dụng</span>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark">Ngưng áp dụng</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-nowrap"><span class="badge bg-info text-dark"><?= htmlspecialchars($item['criteria_code'] ?? 'N/A') ?></span></td>
                        <td class="text-nowrap"><span class="badge bg-light text-dark border"><?= htmlspecialchars($item['user_code'] ?? 'N/A') ?></span></td>
                        <td class="text-end action-cell">
                            <div class="action-buttons">
                                <a class="btn btn-sm btn-outline-secondary" href="<?= base_url('admin/evidences.php?view=' . urlencode($item['id'])) ?>" title="Xem thông tin minh chứng"><i class="bi bi-eye"></i></a>
                                <?php if (!empty($item['file_path'])): ?>
                                    <a class="btn btn-sm btn-outline-success" href="<?= base_url('user/download.php?id=' . urlencode($item['id'])) ?>" title="Tải tài liệu về"><i class="bi bi-download"></i></a>
                                <?php endif; ?>
                                <?php if (current_role() === 'admin'): ?>
                                    <a class="btn btn-sm btn-outline-primary" href="<?= base_url('admin/evidences.php?edit=' . urlencode($item['id'])) ?>" title="Sửa minh chứng"><i class="bi bi-pencil"></i></a>
                                    <form method="post" action="<?= base_url('admin/evidences.php') ?>" class="d-inline" data-confirm-form="Bạn chắc chắn muốn xóa minh chứng này?">
                                        <input type="hidden" name="action" value="delete_evidence">
                                        <input type="hidden" name="id" value="<?= htmlspecialchars($item['id']) ?>">
                                        <button class="btn btn-sm btn-outline-danger" type="submit" title="Xóa minh chứng"><i class="bi bi-trash"></i></button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>

