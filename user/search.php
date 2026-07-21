<?php
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/data.php';

$searchCode = trim($_GET['code'] ?? '');
$searchName = trim($_GET['name'] ?? '');
$selectedStandard = trim($_GET['standard'] ?? '');
$selectedCriterion = trim($_GET['criterion'] ?? '');
$selectedDepartment = trim($_GET['department'] ?? '');
$selectedYear = trim($_GET['year'] ?? '');
$selectedType = trim($_GET['type'] ?? '');
$downloadError = $_GET['download_error'] ?? '';

$filteredEvidences = array_filter($evidences, function ($item) use ($searchCode, $searchName, $selectedStandard, $selectedCriterion, $selectedDepartment, $selectedYear, $selectedType, $criteria) {
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

    if ($selectedDepartment !== '' && ($item['department'] ?? '') !== $selectedDepartment) {
        return false;
    }

    if ($selectedYear !== '' && ($item['year'] ?? '') !== $selectedYear) {
        return false;
    }

    if ($selectedType !== '' && strtoupper($item['type'] ?? '') !== strtoupper($selectedType)) {
        return false;
    }

    return true;
});

$filteredEvidences = array_values($filteredEvidences);
$years = array_values(array_unique(array_filter(array_column($evidences, 'year'))));
$types = array_values(array_unique(array_filter(array_column($evidences, 'type'))));
$departmentsList = array_values(array_unique(array_filter(array_column($evidences, 'department'))));

$pageTitle = page_title('Tra cứu minh chứng');
$heading = 'Tra cứu và khai thác minh chứng';
include __DIR__ . '/../includes/header.php';
?>
<div class="panel mb-4">
    <form class="row g-3 align-items-end" method="get">
        <div class="col-md-6 col-lg-3">
            <label class="form-label">Mã minh chứng</label>
            <input class="form-control" name="code" value="<?= htmlspecialchars($searchCode) ?>" placeholder="Mã minh chứng">
        </div>
        <div class="col-md-6 col-lg-3">
            <label class="form-label">Tên minh chứng</label>
            <input class="form-control" name="name" value="<?= htmlspecialchars($searchName) ?>" placeholder="Tên minh chứng">
        </div>
        <div class="col-md-6 col-lg-3">
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
            <label class="form-label">Đơn vị</label>
            <select class="form-select" name="department">
                <option value="">Tất cả đơn vị</option>
                <?php foreach ($departmentsList as $dept): ?>
                    <option value="<?= htmlspecialchars($dept) ?>" <?= $selectedDepartment === $dept ? 'selected' : '' ?>>
                        <?= htmlspecialchars($dept) ?>
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
            <h2 class="h5 mb-0">Kết quả tra cứu</h2>
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
                    <th>Mã minh chứng</th>
                    <th>Tên minh chứng</th>
                    <th>Mô tả</th>
                    <th>Năm học</th>
                    <th>Ngày ban hành</th>
                    <th>Thuộc tiêu chí</th>
                    <th>Đơn vị cung cấp</th>
                    <th>Loại minh chứng</th>
                    <th>File đính kèm</th>
                    <th>Trạng thái</th>
                    <th class="text-end">Thao tác</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($filteredEvidences as $item): ?>
                    <tr>
                        <td class="fw-bold"><?= htmlspecialchars($item['code']) ?></td>
                        <td style="max-width: 250px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?= htmlspecialchars($item['name']) ?>">
                            <?= htmlspecialchars($item['name']) ?>
                        </td>
                        <td style="max-width: 250px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?= htmlspecialchars($item['description']) ?>">
                            <?= htmlspecialchars($item['description']) ?>
                        </td>
                        <td><?= htmlspecialchars($item['year']) ?></td>
                        <td><?= htmlspecialchars($item['issued_date'] ? date('d/m/Y', strtotime($item['issued_date'])) : '') ?></td>
                        <td><?= htmlspecialchars($item['criteria']) ?></td>
                        <td><?= htmlspecialchars($item['department']) ?></td>
                        <td><?= htmlspecialchars($item['evidence_type']) ?></td>
                        <td><span class="badge text-bg-light text-dark"><?= htmlspecialchars($item['type']) ?></span></td>
                        <td><?= readonly_status_select($item['status']) ?></td>
                        <td class="text-end">
                            <div class="action-buttons">
                                <?php if (!empty($item['file_id'])): ?>
                                    <a class="btn btn-sm btn-outline-secondary" href="<?= base_url('user/view.php?id=' . (int) $item['file_id']) ?>" target="_blank" rel="noopener" title="Xem minh chứng"><i class="bi bi-eye"></i></a>
                                    <a class="btn btn-sm btn-outline-success" href="<?= base_url('user/download.php?id=' . (int) $item['file_id']) ?>"><i class="bi bi-download"></i></a>
                                <?php else: ?>
                                    <button class="btn btn-sm btn-outline-secondary" type="button" disabled><i class="bi bi-eye"></i></button>
                                    <button class="btn btn-sm btn-outline-secondary" type="button" disabled><i class="bi bi-download"></i></button>
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
