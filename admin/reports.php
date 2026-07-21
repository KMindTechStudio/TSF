<?php
require_once __DIR__ . '/../includes/helpers.php';
require_roles(['admin']);
require_once __DIR__ . '/../includes/data.php';
$pageTitle = page_title('Thống kê');
$heading = 'Thống kê phục vụ kiểm định';
$complete = 0;
$need = 0;
$missing = 0;
foreach ($criteria as $item) {
    if ($item['status'] === 'Đủ minh chứng') {
        $complete++;
    } elseif ($item['status'] === 'Cần bổ sung') {
        $need++;
    } else {
        $missing++;
    }
}
include __DIR__ . '/../includes/header.php';
?>
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="metric-card metric-green">
            <i class="bi bi-check-circle"></i>
            <span>Tiêu chí đủ minh chứng</span>
            <strong class="count-up" data-count-to="<?= $complete ?>">0</strong>
            <small class="text-secondary">Có hồ sơ cơ bản đáp ứng</small>
        </div>
    </div>
    <div class="col-md-4">
        <div class="metric-card metric-amber">
            <i class="bi bi-exclamation-circle"></i>
            <span>Cần bổ sung</span>
            <strong class="count-up" data-count-to="<?= $need ?>">0</strong>
            <small class="text-secondary">Cần cập nhật hoặc rà soát thêm</small>
        </div>
    </div>
    <div class="col-md-4">
        <div class="metric-card metric-red">
            <i class="bi bi-x-circle"></i>
            <span>Thiếu minh chứng</span>
            <strong class="count-up" data-count-to="<?= $missing ?>">0</strong>
            <small class="text-secondary">Ưu tiên xử lý trước đánh giá</small>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-xl-7">
        <div class="panel">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="h5 mb-0">Số lượng minh chứng theo tiêu chuẩn</h2>
                <a class="btn btn-outline-secondary" href="<?= base_url('admin/reports_export.php') ?>" target="_blank" rel="noopener">
                    <i class="bi bi-filetype-pdf me-1"></i> Xuất PDF
                </a>
            </div>
            <?php foreach ($standards as $standard): ?>
                <?php $percent = min(100, $standard['evidences'] * 18); ?>
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="fw-semibold"><?= htmlspecialchars($standard['code']) ?> · <?= htmlspecialchars($standard['name']) ?></span>
                        <span><span class="count-up" data-count-to="<?= $standard['evidences'] ?>">0</span> <span data-i18n="minh chứng">minh chứng</span></span>
                    </div>
                    <div class="progress">
                        <div class="progress-bar bg-success progress-animate" data-progress-to="<?= $percent ?>" style="width: 0%"></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="col-xl-5">
        <div class="panel">
            <h2 class="h5 mb-3">Tiêu chí cần ưu tiên</h2>
            <div class="list-group list-group-flush priority-list">
                <?php foreach ($criteria as $item): ?>
                    <?php if ($item['status'] !== 'Đủ minh chứng'): ?>
                        <div class="list-group-item px-0 priority-item">
                            <div class="priority-row">
                                <div class="priority-content">
                                    <strong><?= htmlspecialchars($item['code']) ?></strong>
                                    <div><?= htmlspecialchars($item['name']) ?></div>
                                    <small class="text-secondary"><?= htmlspecialchars($item['owner']) ?></small>
                                </div>
                                <?= readonly_status_select($item['status']) ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
