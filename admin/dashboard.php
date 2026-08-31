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
    <div class="col-md-6 col-xl-3"><div class="metric-card metric-amber"><i class="bi bi-list-check"></i><span>Tiêu chí</span><strong class="count-up" data-count-to="<?= count($criteria) ?>">0</strong><small class="text-secondary">Tổng số tiêu chí đánh giá</small></div></div>
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
    <div class="col-xl-4">
        <div class="panel h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="h5 mb-0"><i class="bi bi-clock-history me-2 text-primary"></i>Nhật ký gần đây</h2>
                <span class="badge bg-light text-dark border"><?= count($activityLogs) ?> hoạt động</span>
            </div>
            
            <?php if (empty($activityLogs)): ?>
                <div class="text-center text-secondary py-4">Chưa có nhật ký hoạt động</div>
            <?php else: ?>
                <ul class="list-group list-group-flush border-top">
                    <?php foreach ($activityLogs as $log): ?>
                        <li class="list-group-item px-0 py-3 border-bottom">
                            <div class="d-flex align-items-start gap-2">
                                <span class="badge <?= $log['badge_class'] ?> p-2 rounded-circle fs-6">
                                    <i class="bi <?= $log['icon'] ?>"></i>
                                </span>
                                <div class="flex-grow-1 min-width-0">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <strong class="text-dark small me-2"><?= htmlspecialchars($log['action_name']) ?></strong>
                                        <span class="badge bg-light text-secondary border fs-7" style="font-size: 0.725rem;">
                                            <?= htmlspecialchars($log['time']) ?>
                                        </span>
                                    </div>
                                    <?php if (!empty($log['detail'])): ?>
                                        <div class="text-secondary small mb-1 text-truncate" title="<?= htmlspecialchars($log['detail']) ?>">
                                            <code><?= htmlspecialchars($log['detail']) ?></code>
                                        </div>
                                    <?php endif; ?>
                                    <div class="d-flex align-items-center gap-1 text-muted" style="font-size: 0.78rem;">
                                        <i class="bi bi-person-circle"></i>
                                        <span><?= htmlspecialchars($log['actor']) ?></span>
                                        <span class="mx-1">•</span>
                                        <?php if ($log['role_code'] === 'admin'): ?>
                                            <span class="text-danger fw-semibold">Quản trị viên</span>
                                        <?php else: ?>
                                            <span class="text-primary fw-semibold">Người dùng</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
