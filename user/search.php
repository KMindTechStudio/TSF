<?php
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/data.php';

$keyword = trim($_GET['q'] ?? '');
$selectedStandard = trim($_GET['standard'] ?? '');
$selectedYear = trim($_GET['year'] ?? '');
$selectedType = trim($_GET['type'] ?? '');
$downloadError = $_GET['download_error'] ?? '';

$filteredEvidences = array_filter($evidences, function ($item) use ($keyword, $selectedStandard, $selectedYear, $selectedType) {
    $haystack = implode(' ', [
        $item['code'] ?? '',
        $item['name'] ?? '',
        $item['criteria'] ?? '',
        $item['year'] ?? '',
        $item['department'] ?? '',
        $item['type'] ?? '',
        $item['status'] ?? '',
    ]);

    if ($keyword !== '' && !search_contains($haystack, $keyword)) {
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

$pageTitle = page_title('Tra cứu minh chứng');
$heading = 'Tra cứu và khai thác minh chứng';
include __DIR__ . '/../includes/header.php';
?>
<div class="panel mb-4">
    <form class="row g-3 align-items-end" method="get">
        <div class="col-lg-4">
            <label class="form-label">Từ khóa</label>
            <input class="form-control" name="q" value="<?= htmlspecialchars($keyword) ?>" placeholder="Mã minh chứng, tên minh chứng">
        </div>
        <div class="col-lg-2">
            <label class="form-label">Tiêu chuẩn</label>
            <select class="form-select" name="standard">
                <option value="">Tất cả</option>
                <?php foreach ($standards as $standard): ?>
                    <option value="<?= htmlspecialchars($standard['code']) ?>" <?= $selectedStandard === $standard['code'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($standard['code']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-lg-2">
            <label class="form-label">Năm học</label>
            <select class="form-select" name="year">
                <option value="">Tất cả</option>
                <?php foreach ($years as $year): ?>
                    <option value="<?= htmlspecialchars($year) ?>" <?= $selectedYear === $year ? 'selected' : '' ?>>
                        <?= htmlspecialchars($year) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-lg-2">
            <label class="form-label">Loại file</label>
            <select class="form-select" name="type">
                <option value="">Tất cả</option>
                <?php foreach ($types as $type): ?>
                    <option value="<?= htmlspecialchars($type) ?>" <?= strtoupper($selectedType) === strtoupper($type) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($type) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-lg-2">
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
            <?php if ($keyword !== ''): ?>
                <small class="text-secondary">Từ khóa: “<?= htmlspecialchars($keyword) ?>”</small>
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
                    <th>Mã</th>
                    <th>Tên minh chứng</th>
                    <th>Tiêu chí liên quan</th>
                    <th>Năm học</th>
                    <th>Đơn vị</th>
                    <th>Trạng thái</th>
                    <th class="text-end">Thao tác</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($filteredEvidences as $item): ?>
                    <tr>
                        <td class="fw-bold"><?= htmlspecialchars($item['code']) ?></td>
                        <td><?= htmlspecialchars($item['name']) ?><div class="small text-secondary">Định dạng <?= htmlspecialchars($item['type']) ?> · cập nhật <?= htmlspecialchars($item['updated']) ?></div></td>
                        <td><?= htmlspecialchars($item['criteria']) ?></td>
                        <td><?= htmlspecialchars($item['year']) ?></td>
                        <td><?= htmlspecialchars($item['department']) ?></td>
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
