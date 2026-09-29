<?php
require_once __DIR__ . '/../includes/helpers.php';
require_roles(['admin']);
require_once __DIR__ . '/../includes/data.php';

// ─── Data Analytics & Metric Calculations ────────────────────────────────────
$totalUsers = count($users);
$adminCount = 0;
$userCount = 0;
$activeUsersCount = 0;
foreach ($users as $u) {
    if (($u['role'] ?? '') === 'admin') $adminCount++;
    else $userCount++;
    if (($u['status_raw'] ?? 0) === 1) $activeUsersCount++;
}

// 2. Thống kê Thông tư
$circularMap = [];
foreach ($standardSets as $st) {
    $tt = trim($st['thong_tu'] ?? '');
    if ($tt !== '') {
        $circularMap[$tt] = ($circularMap[$tt] ?? 0) + 1;
    }
}
$totalCirculars = count($circularMap);

// 3. Thống kê Bộ Tiêu chuẩn & Tiêu chí
$totalStandardSets = count($standardSets);
$activeSetsCount = 0;
foreach ($standardSets as $st) {
    if (($st['status_raw'] ?? '') === 'active') $activeSetsCount++;
}

// 4. Thống kê Minh chứng
$totalEvidences = count($evidences);
$evidencesWithFiles = 0;
$evidencesByYear = [];
$evidencesBySet = [];

foreach ($evidences as $ev) {
    if (!empty($ev['file_path'])) $evidencesWithFiles++;
    
    $yr = trim($ev['year'] ?? '') ?: 'Chưa phân năm';
    $evidencesByYear[$yr] = ($evidencesByYear[$yr] ?? 0) + 1;

    $sName = trim($ev['set_name'] ?? '') ?: (trim($ev['ma_bo_tieu_chuan'] ?? '') ?: 'Chưa gán bộ');
    $evidencesBySet[$sName] = ($evidencesBySet[$sName] ?? 0) + 1;
}

// Chuẩn bị dữ liệu cho Chart JS
$setLabels = [];
$setEvidenceCounts = [];
foreach ($standardSets as $st) {
    $setLabels[] = $st['code'];
    $setEvidenceCounts[] = $st['evidences'] ?? 0;
}
if (empty($setLabels)) {
    $setLabels = ['Bộ TC 01', 'Bộ TC 02', 'Bộ TC 03', 'Bộ TC 04'];
    $setEvidenceCounts = [0, 0, 0, 0];
}

$yearLabels = array_keys($evidencesByYear);
$yearData = array_values($evidencesByYear);
if (empty($yearLabels)) {
    $yearLabels = ['2023-2024', '2024-2025', '2025-2026', '2026-2027'];
    $yearData = [0, 0, 0, 0];
}

$circularLabels = array_keys($circularMap);
$circularData = array_values($circularMap);
if (empty($circularLabels)) {
    $circularLabels = ['TT 04/2016/TT-BGDĐT', 'TT 12/2017/TT-BGDĐT', 'QĐ 78/QĐ-BGDĐT'];
    $circularData = [1, 1, 1];
}

// 1. Thống kê Người dùng
$adminPct = $totalUsers > 0 ? round(($adminCount / $totalUsers) * 100, 1) : 0;
$userPct  = $totalUsers > 0 ? round(($userCount / $totalUsers) * 100, 1) : 0;

$pageTitle = page_title('Dashboard Thống kê');
$heading = 'Dashboard Tổng quan';
include __DIR__ . '/../includes/header.php';
?>

<!-- Load Chart.js & Datalabels Plugin CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js"></script>

<style>
/* Custom Dashboard Enhancements */
.dashboard-stat-card {
    position: relative;
    border-radius: 12px;
    padding: 20px;
    background: var(--surface, #ffffff);
    border: 1px solid var(--line, #e2e8f0);
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.01);
    transition: all 0.25s ease;
    overflow: hidden;
    height: 100%;
}
.dashboard-stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.08), 0 10px 10px -5px rgba(0, 0, 0, 0.02);
}
.stat-card-icon {
    width: 52px;
    height: 52px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 26px;
    margin-bottom: 14px;
}
.stat-card-blue .stat-card-icon { background: linear-gradient(135deg, rgba(37, 99, 235, 0.15), rgba(59, 130, 246, 0.25)); color: #2563eb; }
.stat-card-indigo .stat-card-icon { background: linear-gradient(135deg, rgba(99, 102, 241, 0.15), rgba(129, 140, 248, 0.25)); color: #6366f1; }
.stat-card-emerald .stat-card-icon { background: linear-gradient(135deg, rgba(16, 185, 129, 0.15), rgba(52, 211, 153, 0.25)); color: #10b981; }
.stat-card-rose .stat-card-icon { background: linear-gradient(135deg, rgba(244, 63, 94, 0.15), rgba(251, 113, 133, 0.25)); color: #f43f5e; }

.stat-card-value {
    font-size: 34px;
    font-weight: 800;
    line-height: 1.1;
    color: var(--ink, #0f172a);
    margin-bottom: 6px;
    font-family: inherit;
}
.stat-card-title {
    font-size: 14px;
    font-weight: 700;
    color: var(--muted, #64748b);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 8px;
}
.stat-card-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 12px;
    font-weight: 600;
    padding: 3px 8px;
    border-radius: 6px;
}
.chart-panel {
    border-radius: 12px;
    padding: 20px;
    background: var(--surface, #ffffff);
    border: 1px solid var(--line, #e2e8f0);
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05);
    height: 100%;
}
.chart-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
    padding-bottom: 12px;
    border-bottom: 1px solid var(--line, #e2e8f0);
}
.chart-title {
    font-size: 16px;
    font-weight: 700;
    margin: 0;
    color: var(--ink, #0f172a);
    display: flex;
    align-items: center;
    gap: 8px;
}
.chart-container-box {
    position: relative;
    width: 100%;
    min-height: 260px;
}
</style>

<!-- ─── ROW 1: 4 DESIGN METRIC CARDS ───────────────────────────────────────── -->
<div class="row g-3 mb-4">
    <!-- Card 1: Tổng số người dùng -->
    <div class="col-sm-6 col-xl-3">
        <div class="dashboard-stat-card stat-card-blue">
            <div class="d-flex justify-content-between align-items-start">
                <div class="stat-card-icon">
                    <i class="bi bi-people-fill"></i>
                </div>
                <span class="stat-card-badge bg-primary-subtle text-primary border border-primary-subtle">
                    <i class="bi bi-person-check-fill"></i> <?= $activeUsersCount ?> hoạt động
                </span>
            </div>
            <div class="stat-card-title">Tổng số người dùng</div>
            <div class="stat-card-value count-up" data-count-to="<?= $totalUsers ?>">0</div>
            <div class="text-secondary small d-flex justify-content-between pt-2 border-top">
                <span><i class="bi bi-shield-lock-fill text-danger me-1"></i><?= $adminCount ?> Admin</span>
                <span><i class="bi bi-person-fill text-primary me-1"></i><?= $userCount ?> User</span>
            </div>
        </div>
    </div>

    <!-- Card 2: Số thông tư -->
    <div class="col-sm-6 col-xl-3">
        <div class="dashboard-stat-card stat-card-indigo">
            <div class="d-flex justify-content-between align-items-start">
                <div class="stat-card-icon">
                    <i class="bi bi-journal-bookmark-fill"></i>
                </div>
                <span class="stat-card-badge bg-indigo-subtle text-indigo border border-indigo-subtle" style="background-color: #e0e7ff; color: #4338ca;">
                    <i class="bi bi-award-fill"></i> Ban hành
                </span>
            </div>
            <div class="stat-card-title">Số Thông tư áp dụng</div>
            <div class="stat-card-value count-up" data-count-to="<?= $totalCirculars ?>">0</div>
            <div class="text-secondary small pt-2 border-top text-truncate" title="<?= implode(', ', array_keys($circularMap)) ?>">
                <i class="bi bi-file-earmark-check text-indigo me-1"></i><?= !empty($circularMap) ? count($circularMap) . ' văn bản quy chuẩn' : 'Chưa có thông tư' ?>
            </div>
        </div>
    </div>

    <!-- Card 3: Tổng số tiêu chí / Bộ tiêu chuẩn -->
    <div class="col-sm-6 col-xl-3">
        <div class="dashboard-stat-card stat-card-emerald">
            <div class="d-flex justify-content-between align-items-start">
                <div class="stat-card-icon">
                    <i class="bi bi-collection-fill"></i>
                </div>
                <span class="stat-card-badge bg-success-subtle text-success border border-success-subtle">
                    <i class="bi bi-check-circle-fill"></i> Chuẩn kiểm định
                </span>
            </div>
            <div class="stat-card-title">Bộ Tiêu chuẩn & Tiêu chí</div>
            <div class="stat-card-value count-up" data-count-to="<?= $totalStandardSets ?>">0</div>
            <div class="text-secondary small pt-2 border-top d-flex justify-content-between">
                <span><i class="bi bi-layers-fill text-success me-1"></i><?= $activeSetsCount ?> Đang áp dụng</span>
                <span>Bộ TC động</span>
            </div>
        </div>
    </div>

    <!-- Card 4: Tổng số minh chứng -->
    <div class="col-sm-6 col-xl-3">
        <div class="dashboard-stat-card stat-card-rose">
            <div class="d-flex justify-content-between align-items-start">
                <div class="stat-card-icon">
                    <i class="bi bi-folder-check"></i>
                </div>
                <span class="stat-card-badge bg-danger-subtle text-danger border border-danger-subtle">
                    <i class="bi bi-file-earmark-arrow-up-fill"></i> <?= $evidencesWithFiles ?> có tệp
                </span>
            </div>
            <div class="stat-card-title">Tổng số minh chứng</div>
            <div class="stat-card-value count-up" data-count-to="<?= $totalEvidences ?>">0</div>
            <div class="text-secondary small pt-2 border-top d-flex justify-content-between">
                <span><i class="bi bi-database-check text-danger me-1"></i>Kho CSDL</span>
                <span><?= $totalEvidences > 0 ? round(($evidencesWithFiles / $totalEvidences) * 100) : 0 ?>% Đầy đủ tệp</span>
            </div>
        </div>
    </div>
</div>

<!-- ─── ROW 2: CHARTS SECTION (MULTI-STYLE) ────────────────────────────────── -->
<div class="row g-4 mb-4">
    <!-- Chart 1: Bar Chart (Minh chứng theo Bộ tiêu chuẩn) -->
    <div class="col-lg-8">
        <div class="chart-panel">
            <div class="chart-header">
                <h3 class="chart-title">
                    <i class="bi bi-bar-chart-line-fill text-primary"></i>
                    Phân bổ Minh chứng theo Bộ Tiêu chuẩn
                </h3>
                <span class="badge bg-light text-dark border">Cột đa sắc (Bar Chart)</span>
            </div>
            <div class="chart-container-box" style="height: 290px;">
                <canvas id="barEvidenceChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Chart 2: Doughnut Chart (Cơ cấu Người dùng) -->
    <div class="col-lg-4">
        <div class="chart-panel d-flex flex-column justify-content-between">
            <div>
                <div class="chart-header">
                    <h3 class="chart-title">
                        <i class="bi bi-pie-chart-fill text-info"></i>
                        Cơ cấu Người dùng
                    </h3>
                    <span class="badge bg-light text-dark border">Vành khuyên (Doughnut)</span>
                </div>
                <div class="chart-container-box" style="height: 235px;">
                    <canvas id="doughnutUserChart"></canvas>
                </div>
            </div>
            
            <!-- Hiển thị số lượng và tỷ lệ % chi tiết từng mảng màu -->
            <div class="row g-2 mt-2 pt-2 border-top">
                <div class="col-6">
                    <div class="p-2 rounded-3 text-center border" style="background: rgba(239, 68, 68, 0.08); border-color: rgba(239, 68, 68, 0.25) !important;">
                        <small class="d-block text-danger fw-bold text-truncate mb-1">
                            <i class="bi bi-shield-lock-fill me-1"></i>Quản trị viên
                        </small>
                        <div class="fw-bold fs-5 text-danger">
                            <?= $adminCount ?> <span class="fs-7 text-secondary fw-semibold">(<?= $adminPct ?>%)</span>
                        </div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="p-2 rounded-3 text-center border" style="background: rgba(37, 99, 235, 0.08); border-color: rgba(37, 99, 235, 0.25) !important;">
                        <small class="d-block text-primary fw-bold text-truncate mb-1">
                            <i class="bi bi-person-fill me-1"></i>Người dùng
                        </small>
                        <div class="fw-bold fs-5 text-primary">
                            <?= $userCount ?> <span class="fs-7 text-secondary fw-semibold">(<?= $userPct ?>%)</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ─── ROW 3: CHARTS SECTION (AREA & POLAR STYLES) ────────────────────────── -->
<div class="row g-4 mb-4">
    <!-- Chart 3: Area Line Chart (Xu hướng tích lũy theo năm học) -->
    <div class="col-lg-6">
        <div class="chart-panel">
            <div class="chart-header">
                <h3 class="chart-title">
                    <i class="bi bi-graph-up-arrow text-success"></i>
                    Xu hướng Minh chứng theo Năm học
                </h3>
                <span class="badge bg-light text-dark border">Miền sóng (Area Chart)</span>
            </div>
            <div class="chart-container-box" style="height: 270px;">
                <canvas id="areaTrendChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Chart 4: Polar Area / Radar Chart (Tỷ trọng theo Thông tư) -->
    <div class="col-lg-6">
        <div class="chart-panel">
            <div class="chart-header">
                <h3 class="chart-title">
                    <i class="bi bi-radar text-warning"></i>
                    Phân bổ Tiêu chuẩn theo Thông tư
                </h3>
                <span class="badge bg-light text-dark border">Đa giác cực (Polar Area)</span>
            </div>
            <div class="chart-container-box" style="height: 270px;">
                <canvas id="polarCircularChart"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- ─── ROW 4: DATA TABLE & RECENT AUDIT LOGS ──────────────────────────────── -->
<div class="row g-4">
    <!-- Table: Tình trạng Bộ tiêu chuẩn động -->
    <div class="col-xl-8">
        <div class="panel">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h2 class="h5 mb-1"><i class="bi bi-table me-2 text-primary"></i>Danh mục Bộ Tiêu chuẩn động & Thông tư</h2>
                    <p class="text-secondary mb-0">Theo dõi thông tư ban hành và số lượng hồ sơ minh chứng tương ứng.</p>
                </div>
                <a class="btn btn-outline-primary btn-sm" href="<?= base_url('admin/standard_sets.php') ?>">
                    <i class="bi bi-collection me-1"></i> Quản lý Bộ tiêu chuẩn
                </a>
            </div>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th style="width: 120px;">Mã bộ</th>
                            <th>Tên bộ tiêu chuẩn</th>
                            <th>Thông tư</th>
                            <th>Ngày ban hành</th>
                            <th class="text-center" style="width: 110px;">Minh chứng</th>
                            <th>Trạng thái</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($standardSets)): ?>
                        <tr>
                            <td colspan="6" class="text-center text-secondary py-4">Chưa có bộ tiêu chuẩn nào trong hệ thống</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($standardSets as $set): ?>
                            <tr>
                                <td class="fw-bold"><span class="badge bg-light text-dark border"><?= htmlspecialchars($set['code']) ?></span></td>
                                <td class="fw-semibold"><?= htmlspecialchars($set['name']) ?></td>
                                <td><span class="badge bg-secondary"><?= htmlspecialchars($set['thong_tu'] ?: 'Chưa có thông tư') ?></span></td>
                                <td class="small text-secondary"><?= htmlspecialchars($set['ngay_ban_hanh'] ?: '-') ?></td>
                                <td class="text-center">
                                    <span class="badge <?= $set['evidences'] > 0 ? 'bg-primary' : 'bg-light text-dark border' ?> px-2 py-1">
                                        <?= $set['evidences'] ?> tệp
                                    </span>
                                </td>
                                <td><?= readonly_status_select($set['status']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Panel: Nhật ký hoạt động gần đây -->
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

<!-- ─── CHART.JS INITIALIZATION SCRIPTS ────────────────────────────────────── -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const isDarkMode = document.documentElement.dataset.theme === 'dark';
    const textColor = isDarkMode ? '#cbd5e1' : '#475569';
    const gridColor = isDarkMode ? 'rgba(255, 255, 255, 0.08)' : 'rgba(0, 0, 0, 0.06)';

    Chart.defaults.color = textColor;
    Chart.defaults.font.family = 'system-ui, -apple-system, sans-serif';

    // 1. Bar Chart: Minh chứng theo Bộ tiêu chuẩn
    const barCtx = document.getElementById('barEvidenceChart');
    if (barCtx) {
        new Chart(barCtx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($setLabels, JSON_UNESCAPED_UNICODE) ?>,
                datasets: [{
                    label: 'Số lượng minh chứng',
                    data: <?= json_encode($setEvidenceCounts) ?>,
                    backgroundColor: [
                        'rgba(59, 130, 246, 0.8)',
                        'rgba(16, 185, 129, 0.8)',
                        'rgba(245, 158, 11, 0.8)',
                        'rgba(239, 68, 68, 0.8)',
                        'rgba(139, 92, 246, 0.8)',
                        'rgba(14, 165, 233, 0.8)'
                    ],
                    borderColor: [
                        '#2563eb',
                        '#059669',
                        '#d97706',
                        '#dc2626',
                        '#7c3aed',
                        '#0284c7'
                    ],
                    borderWidth: 1.5,
                    borderRadius: 6,
                    borderSkipped: false,
                    maxBarThickness: 45
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        padding: 10,
                        cornerRadius: 8
                    }
                },
                scales: {
                    x: {
                        grid: { display: false }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: gridColor },
                        ticks: { stepSize: 1 }
                    }
                }
            }
        });
    }

    // Tắt datalabels mặc định cho các chart khác
    if (typeof ChartDataLabels !== 'undefined') {
        Chart.defaults.plugins.datalabels = { display: false };
    }

    // Custom Plugin: Vẽ số đếm tổng ở giữa tâm hình Vành khuyên
    const doughnutCenterPlugin = {
        id: 'doughnutCenterText',
        afterDraw: function(chart) {
            if (chart.config.type !== 'doughnut') return;
            const width = chart.width;
            const height = chart.chartArea ? (chart.chartArea.top + (chart.chartArea.bottom - chart.chartArea.top) / 2) : (chart.height / 2);
            const ctx = chart.ctx;
            ctx.save();

            const total = <?= (int)$totalUsers ?>;
            
            // Subtitle
            ctx.font = '600 11px system-ui, -apple-system, sans-serif';
            ctx.fillStyle = isDarkMode ? '#94a3b8' : '#64748b';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText('TỔNG SỐ', width / 2, height - 10);

            // Number
            ctx.font = '800 20px system-ui, -apple-system, sans-serif';
            ctx.fillStyle = isDarkMode ? '#f8fafc' : '#0f172a';
            ctx.fillText(total.toString(), width / 2, height + 12);
            ctx.restore();
        }
    };

    // 2. Doughnut Chart: Cơ cấu Người dùng
    const doughnutCtx = document.getElementById('doughnutUserChart');
    if (doughnutCtx) {
        new Chart(doughnutCtx, {
            type: 'doughnut',
            plugins: [ChartDataLabels, doughnutCenterPlugin],
            data: {
                labels: [
                    'Quản trị viên (Admin): <?= $adminCount ?> (<?= $adminPct ?>%)', 
                    'Người dùng (User): <?= $userCount ?> (<?= $userPct ?>%)'
                ],
                datasets: [{
                    data: [<?= $adminCount ?>, <?= $userCount ?>],
                    backgroundColor: [
                        'rgba(239, 68, 68, 0.9)',
                        'rgba(37, 99, 235, 0.9)'
                    ],
                    borderColor: isDarkMode ? '#1e293b' : '#ffffff',
                    borderWidth: 2,
                    hoverOffset: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '65%',
                layout: {
                    padding: 6
                },
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            boxWidth: 12,
                            padding: 10,
                            font: {
                                size: 11,
                                weight: '600'
                            }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const val = context.raw || 0;
                                const total = <?= max(1, $totalUsers) ?>;
                                const pct = ((val / total) * 100).toFixed(1);
                                return ` ${context.label.split(':')[0]}: ${val} tài khoản (${pct}%)`;
                            }
                        }
                    },
                    datalabels: {
                        display: true,
                        color: '#ffffff',
                        font: {
                            weight: 'bold',
                            size: 12
                        },
                        formatter: function(value, context) {
                            if (!value || value === 0) return '';
                            const total = <?= max(1, $totalUsers) ?>;
                            const pct = ((value / total) * 100).toFixed(0);
                            return `${value}\n(${pct}%)`;
                        },
                        textAlign: 'center',
                        textShadowBlur: 4,
                        textShadowColor: 'rgba(0, 0, 0, 0.5)'
                    }
                }
            }
        });
    }

    // 3. Area Trend Chart: Minh chứng theo Năm học
    const areaCtx = document.getElementById('areaTrendChart');
    if (areaCtx) {
        new Chart(areaCtx, {
            type: 'line',
            data: {
                labels: <?= json_encode($yearLabels, JSON_UNESCAPED_UNICODE) ?>,
                datasets: [{
                    label: 'Minh chứng đã nhập',
                    data: <?= json_encode($yearData) ?>,
                    fill: true,
                    backgroundColor: 'rgba(16, 185, 129, 0.18)',
                    borderColor: '#10b981',
                    borderWidth: 3,
                    tension: 0.4,
                    pointBackgroundColor: '#10b981',
                    pointBorderColor: '#ffffff',
                    pointRadius: 5,
                    pointHoverRadius: 7
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    x: {
                        grid: { display: false }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: gridColor },
                        ticks: { stepSize: 1 }
                    }
                }
            }
        });
    }

    // 4. Polar Area Chart: Phân bổ Thông tư
    const polarCtx = document.getElementById('polarCircularChart');
    if (polarCtx) {
        new Chart(polarCtx, {
            type: 'polarArea',
            data: {
                labels: <?= json_encode($circularLabels, JSON_UNESCAPED_UNICODE) ?>,
                datasets: [{
                    data: <?= json_encode($circularData) ?>,
                    backgroundColor: [
                        'rgba(99, 102, 241, 0.75)',
                        'rgba(245, 158, 11, 0.75)',
                        'rgba(14, 165, 233, 0.75)',
                        'rgba(236, 72, 153, 0.75)',
                        'rgba(34, 197, 94, 0.75)'
                    ],
                    borderColor: isDarkMode ? '#1e293b' : '#ffffff',
                    borderWidth: 1.5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            boxWidth: 12,
                            padding: 10
                        }
                    }
                },
                scales: {
                    r: {
                        grid: { color: gridColor },
                        ticks: { display: false }
                    }
                }
            }
        });
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
