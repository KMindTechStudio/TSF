<?php
require_once __DIR__ . '/../includes/helpers.php';
require_roles(['admin']);
require_once __DIR__ . '/../includes/data.php';
$pageTitle = page_title('Tổng quan');
$heading = 'Tổng quan cơ sở dữ liệu minh chứng';
include __DIR__ . '/../includes/header.php';
?>
<div class="row g-3 mb-4">
    <div class="col-md-6 col-xl-3"><div class="metric-card metric-blue"><i class="bi bi-diagram-3"></i><span>Tiêu chuẩn</span><strong class="count-up" data-count-to="<?= count($standards) ?>">0</strong><small class="text-secondary">Bộ tiêu chuẩn đang áp dụng</small></div></div>
    <div class="col-md-6 col-xl-3"><div class="metric-card metric-amber"><i class="bi bi-list-check"></i><span>Tiêu chí</span><strong class="count-up" data-count-to="<?= count($criteria) ?>">0</strong><small class="text-secondary">Đã phân công đơn vị phụ trách</small></div></div>
    <div class="col-md-6 col-xl-3"><div class="metric-card metric-red"><i class="bi bi-folder2-open"></i><span>Minh chứng</span><strong class="count-up" data-count-to="<?= count($evidences) ?>">0</strong><small class="text-secondary">Tệp đã được đưa vào hệ thống</small></div></div>
    <div class="col-md-6 col-xl-3"><div class="metric-card metric-green"><i class="bi bi-shield-check"></i><span>Tỷ lệ đáp ứng</span><strong><span class="count-up" data-count-to="72">0</span>%</strong><small class="text-secondary">Theo tiêu chí đủ minh chứng</small></div></div>
</div>
<div class="row g-4">
    <div class="col-xl-8"><div class="panel">
        <div class="d-flex justify-content-between align-items-center mb-3"><div><h2 class="h5 mb-1">Tình trạng theo tiêu chuẩn</h2><p class="text-secondary mb-0">Theo dõi mức độ đầy đủ của hồ sơ minh chứng.</p></div><a class="btn btn-outline-success" href="<?= base_url('admin/reports.php') ?>"><i class="bi bi-bar-chart me-1"></i> Xem thống kê</a></div>
        <div class="table-responsive"><table class="table"><thead><tr><th>Mã</th><th>Tên tiêu chuẩn</th><th>Tiêu chí</th><th>Minh chứng</th><th>Trạng thái</th></tr></thead><tbody>
        <?php foreach ($standards as $standard): ?><tr><td class="fw-bold"><?= htmlspecialchars($standard['code']) ?></td><td><?= htmlspecialchars($standard['name']) ?></td><td><?= $standard['criteria'] ?></td><td><?= $standard['evidences'] ?></td><td><?= readonly_status_select($standard['status']) ?></td></tr><?php endforeach; ?>
        </tbody></table></div>
    </div></div>
    <div class="col-xl-4"><div class="panel h-100"><h2 class="h5 mb-3">Nhật ký gần đây</h2><ul class="timeline"><?php foreach ($activityLogs as $log): ?><li><strong><?= htmlspecialchars($log['action']) ?></strong><div class="small text-secondary"><?= htmlspecialchars($log['actor']) ?> · <?= htmlspecialchars($log['time']) ?></div></li><?php endforeach; ?></ul></div></div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
