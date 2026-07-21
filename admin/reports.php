<?php
require_once __DIR__ . '/../includes/helpers.php';
require_roles(['admin']);
require_once __DIR__ . '/../includes/data.php';

$pageTitle = page_title('Thống kê');
$heading = 'Thống kê phục vụ kiểm định';

// 1. Calculate top metrics
$updated_evidence_count = count($evidences);
$missing_criteria_count = 0;
$need_supplement_count = 0;

foreach ($criteria as $item) {
    if ($item['status'] === 'Cần bổ sung') {
        $need_supplement_count++;
    } elseif ($item['status'] === 'Thiếu minh chứng') {
        $missing_criteria_count++;
    }
}

// 2. Aggregate evidence by year
$evidenceByYear = [];
foreach ($evidences as $e) {
    $y = $e['year'];
    if (!isset($evidenceByYear[$y])) $evidenceByYear[$y] = 0;
    $evidenceByYear[$y]++;
}
ksort($evidenceByYear); // Sort by year

// 3. Aggregate evidence by department
$evidenceByDept = [];
foreach ($evidences as $e) {
    $d = $e['department'];
    if (!isset($evidenceByDept[$d])) $evidenceByDept[$d] = 0;
    $evidenceByDept[$d]++;
}
arsort($evidenceByDept); // Sort by count descending

// 4. Sort criteria by evidence count
$criteriaSorted = $criteria;
usort($criteriaSorted, function($a, $b) {
    return $b['evidences'] <=> $a['evidences']; // Descending
});
$topCriteria = array_slice($criteriaSorted, 0, 5); // Show top 5 for brevity in the panel

// Max values for calculating progress bar percentages (to scale up to 100%)
$maxStandardEvidence = 1;
foreach ($standards as $s) {
    if ($s['evidences'] > $maxStandardEvidence) $maxStandardEvidence = $s['evidences'];
}

$maxYearEvidence = 1;
foreach ($evidenceByYear as $count) {
    if ($count > $maxYearEvidence) $maxYearEvidence = $count;
}

$maxDeptEvidence = 1;
foreach ($evidenceByDept as $count) {
    if ($count > $maxDeptEvidence) $maxDeptEvidence = $count;
}

$maxCriteriaEvidence = 1;
if (!empty($topCriteria)) {
    $maxCriteriaEvidence = $topCriteria[0]['evidences'];
    if ($maxCriteriaEvidence < 1) $maxCriteriaEvidence = 1;
}

include __DIR__ . '/../includes/header.php';
?>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="metric-card metric-green">
            <i class="bi bi-file-earmark-check"></i>
            <span>Minh chứng đã cập nhật</span>
            <strong class="count-up" data-count-to="<?= $updated_evidence_count ?>">0</strong>
            <small class="text-secondary">Tổng số minh chứng trên hệ thống</small>
        </div>
    </div>
    <div class="col-md-4">
        <div class="metric-card metric-amber">
            <i class="bi bi-exclamation-circle"></i>
            <span>Tiêu chí cần bổ sung</span>
            <strong class="count-up" data-count-to="<?= $need_supplement_count ?>">0</strong>
            <small class="text-secondary">Cần cập nhật hoặc rà soát thêm</small>
        </div>
    </div>
    <div class="col-md-4">
        <div class="metric-card metric-red">
            <i class="bi bi-x-circle"></i>
            <span>Tiêu chí thiếu minh chứng</span>
            <strong class="count-up" data-count-to="<?= $missing_criteria_count ?>">0</strong>
            <small class="text-secondary">Ưu tiên xử lý trước đánh giá</small>
        </div>
    </div>
</div>

<div class="d-flex justify-content-end mb-3">
    <a class="btn btn-outline-secondary" href="<?= base_url('admin/reports_export.php') ?>" target="_blank" rel="noopener">
        <i class="bi bi-filetype-pdf me-1"></i> Xuất PDF báo cáo
    </a>
</div>

<div class="row g-4">
    <!-- Panel 1: Theo tiêu chuẩn -->
    <div class="col-xl-6">
        <div class="panel h-100">
            <h2 class="h5 mb-4">Số lượng minh chứng theo tiêu chuẩn</h2>
            <?php foreach ($standards as $standard): ?>
                <?php $percent = ($maxStandardEvidence > 0) ? min(100, ($standard['evidences'] / $maxStandardEvidence) * 100) : 0; ?>
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="fw-semibold"><?= htmlspecialchars($standard['code']) ?> · <?= htmlspecialchars($standard['name']) ?></span>
                        <span><span class="count-up" data-count-to="<?= $standard['evidences'] ?>">0</span> <span data-i18n="minh chứng">minh chứng</span></span>
                    </div>
                    <div class="progress" style="height: 8px;">
                        <div class="progress-bar bg-success progress-animate" data-progress-to="<?= $percent ?>" style="width: 0%"></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Panel 2: Theo tiêu chí (Top 5) -->
    <div class="col-xl-6">
        <div class="panel h-100">
            <h2 class="h5 mb-4">Số lượng minh chứng theo tiêu chí (Nhiều nhất)</h2>
            <?php foreach ($topCriteria as $item): ?>
                <?php $percent = ($maxCriteriaEvidence > 0) ? min(100, ($item['evidences'] / $maxCriteriaEvidence) * 100) : 0; ?>
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="fw-semibold">Tiêu chí <?= htmlspecialchars($item['code']) ?></span>
                        <span><span class="count-up" data-count-to="<?= $item['evidences'] ?>">0</span> <span data-i18n="minh chứng">minh chứng</span></span>
                    </div>
                    <div class="progress" style="height: 8px;">
                        <div class="progress-bar bg-primary progress-animate" data-progress-to="<?= $percent ?>" style="width: 0%"></div>
                    </div>
                </div>
            <?php endforeach; ?>
            <div class="text-center mt-4">
                <a href="<?= base_url('admin/criteria.php') ?>" class="text-decoration-none small">Xem chi tiết tất cả tiêu chí &rarr;</a>
            </div>
        </div>
    </div>

    <!-- Panel 3: Theo năm học -->
    <div class="col-xl-6">
        <div class="panel h-100">
            <h2 class="h5 mb-4">Số lượng minh chứng theo năm học</h2>
            <?php if (empty($evidenceByYear)): ?>
                <p class="text-secondary small">Chưa có dữ liệu minh chứng.</p>
            <?php endif; ?>
            <?php foreach ($evidenceByYear as $year => $count): ?>
                <?php $percent = ($maxYearEvidence > 0) ? min(100, ($count / $maxYearEvidence) * 100) : 0; ?>
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="fw-semibold">Năm học <?= htmlspecialchars($year) ?></span>
                        <span><span class="count-up" data-count-to="<?= $count ?>">0</span> <span data-i18n="minh chứng">minh chứng</span></span>
                    </div>
                    <div class="progress" style="height: 8px;">
                        <div class="progress-bar bg-info progress-animate" data-progress-to="<?= $percent ?>" style="width: 0%"></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Panel 4: Theo đơn vị -->
    <div class="col-xl-6">
        <div class="panel h-100">
            <h2 class="h5 mb-4">Số lượng minh chứng theo đơn vị</h2>
            <?php if (empty($evidenceByDept)): ?>
                <p class="text-secondary small">Chưa có dữ liệu minh chứng.</p>
            <?php endif; ?>
            <?php foreach ($evidenceByDept as $dept => $count): ?>
                <?php $percent = ($maxDeptEvidence > 0) ? min(100, ($count / $maxDeptEvidence) * 100) : 0; ?>
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="fw-semibold"><?= htmlspecialchars($dept) ?></span>
                        <span><span class="count-up" data-count-to="<?= $count ?>">0</span> <span data-i18n="minh chứng">minh chứng</span></span>
                    </div>
                    <div class="progress" style="height: 8px;">
                        <div class="progress-bar bg-warning progress-animate" data-progress-to="<?= $percent ?>" style="width: 0%"></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
