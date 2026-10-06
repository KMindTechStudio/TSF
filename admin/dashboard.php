<?php
require_once __DIR__ . '/../includes/helpers.php';
require_roles(['admin']);
require_once __DIR__ . '/../includes/data.php';

// ─── Data Analytics & Metric Calculations ────────────────────────────────────
// 1. Thống kê Người dùng
$totalUsers = count($users);
$adminCount = 0;
$userCount = 0;
$activeUsersCount = 0;
foreach ($users as $u) {
    if (($u['role'] ?? '') === 'admin') $adminCount++;
    else $userCount++;
    if (($u['status_raw'] ?? 0) === 1) $activeUsersCount++;
}
$adminPct = $totalUsers > 0 ? round(($adminCount / $totalUsers) * 100, 1) : 0;
$userPct  = $totalUsers > 0 ? round(($userCount / $totalUsers) * 100, 1) : 0;

// 2. Thống kê Thông tư & Tiêu chuẩn theo Thông tư
$standardsByCircular = [];
foreach ($standardSets as $st) {
    $tt = trim($st['thong_tu'] ?? '');
    if ($tt !== '') {
        $standardsByCircular[$tt] = ($standardsByCircular[$tt] ?? 0) + (int)($st['standard_count'] ?? 0);
    }
}
$totalCirculars = count($standardsByCircular);

// 3. Thống kê Bộ Tiêu chuẩn, Tiêu chuẩn & Tiêu chí
$totalStandardSets = count($standardSets);
$totalStandards = count($standards);
$totalCriteria = count($criteria);
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
    
    // Trích xuất năm ban hành chính xác từ ngày ban hành
    $yr = '';
    if (!empty($ev['issue_date'])) {
        $yr = 'Năm ' . date('Y', strtotime($ev['issue_date']));
    } elseif (!empty($ev['year'])) {
        $yr = 'Năm ' . $ev['year'];
    } else {
        $yr = 'Chưa phân năm';
    }
    $evidencesByYear[$yr] = ($evidencesByYear[$yr] ?? 0) + 1;

    $sName = trim($ev['set_name'] ?? '') ?: (trim($ev['ma_bo_tieu_chuan'] ?? '') ?: 'Chưa gán bộ');
    $evidencesBySet[$sName] = ($evidencesBySet[$sName] ?? 0) + 1;
}
ksort($evidencesByYear);

// Chuẩn bị dữ liệu biểu đồ tương quan: Tiêu chuẩn, Tiêu chí & Minh chứng theo Bộ tiêu chuẩn
$multiSetLabels = [];
$multiStandardsCount = [];
$multiCriteriaCount = [];
$multiEvidencesCount = [];

foreach ($standardSets as $st) {
    $sId = $st['id'];
    $multiSetLabels[] = $st['code'];
    $multiStandardsCount[] = count(array_filter($standards, fn($tc) => ($tc['set_id'] ?? '') === $sId));
    $multiCriteriaCount[] = count(array_filter($criteria, fn($c) => ($c['set_id'] ?? '') === $sId));
    $multiEvidencesCount[] = (int)($st['evidences'] ?? 0);
}
if (empty($multiSetLabels)) {
    $multiSetLabels = ['BTC01', 'BTC02', 'BTC03', 'BTC04', 'BTC05', 'BTC06', 'BTC07', 'BTC08'];
    $multiStandardsCount = [11, 6, 5, 4, 4, 4, 2, 4];
    $multiCriteriaCount  = [13, 7, 6, 4, 4, 4, 2, 4];
    $multiEvidencesCount = [7, 5, 5, 4, 4, 4, 4, 4];
}

$yearLabels = array_keys($evidencesByYear);
$yearData = array_values($evidencesByYear);
if (empty($yearLabels)) {
    $yearLabels = ['Năm 2023', 'Năm 2024'];
    $yearData = [3, 2];
}

// Lấy danh sách Thông tư có tiêu chuẩn tương ứng
$circularLabels = [];
$circularData = [];
foreach ($standardsByCircular as $cName => $cCount) {
    if ($cCount > 0) {
        $circularLabels[] = $cName;
        $circularData[] = $cCount;
    }
}
if (empty($circularLabels)) {
    $circularLabels = ['TT 04/2016/TT-BGDĐT', 'TT 12/2017/TT-BGDĐT', 'AUN-QA v4.0', 'TT 17/2021/TT-BGDĐT'];
    $circularData = [11, 3, 2, 2];
}

$pageTitle = page_title('Dashboard Thống kê');
$heading = 'Dashboard Tổng quan';
include __DIR__ . '/../includes/header.php';
?>

<!-- Load Chart.js & Datalabels Plugin CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js"></script>

<style>
/* ============================================================
   PREMIUM MODERN DASHBOARD STYLES & ENTRANCE ANIMATIONS
============================================================ */

/* Entrance Stagger Animations & Scroll Reveal */
@keyframes dashboardFadeInUp {
    from {
        opacity: 0;
        transform: translateY(22px) scale(0.985);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

@keyframes panelChildFadeIn {
    from {
        opacity: 0;
        transform: translateY(14px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.anim-fade-up {
    opacity: 0;
    animation: dashboardFadeInUp 0.65s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

/* Scroll-triggered reveal */
.scroll-reveal {
    opacity: 0;
    transform: translateY(28px) scale(0.99);
    transition: opacity 0.75s cubic-bezier(0.16, 1, 0.3, 1), transform 0.75s cubic-bezier(0.16, 1, 0.3, 1);
    will-change: opacity, transform;
}
.scroll-reveal.is-revealed {
    opacity: 1;
    transform: translateY(0) scale(1);
}

/* Panel internal child elements stagger */
.panel-child-anim {
    opacity: 0;
    transform: translateY(12px);
    transition: opacity 0.55s cubic-bezier(0.16, 1, 0.3, 1), transform 0.55s cubic-bezier(0.16, 1, 0.3, 1);
    will-change: opacity, transform;
}
.is-revealed .panel-child-anim,
.anim-fade-up .panel-child-anim {
    opacity: 1;
    transform: translateY(0);
}

.stagger-1 { transition-delay: 0.08s; }
.stagger-2 { transition-delay: 0.16s; }
.stagger-3 { transition-delay: 0.24s; }
.stagger-4 { transition-delay: 0.32s; }

.delay-0 { animation-delay: 0.05s; }
.delay-1 { animation-delay: 0.12s; }
.delay-2 { animation-delay: 0.20s; }
.delay-3 { animation-delay: 0.28s; }
.delay-4 { animation-delay: 0.36s; }
.delay-5 { animation-delay: 0.44s; }
.delay-6 { animation-delay: 0.54s; }

/* Welcome Banner */
.dashboard-welcome-banner {
    background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 50%, #3b82f6 100%);
    border-radius: 18px;
    padding: 24px 28px;
    color: #ffffff;
    box-shadow: 0 10px 25px -5px rgba(37, 99, 235, 0.25);
    position: relative;
    overflow: hidden;
    margin-bottom: 24px;
}
.dashboard-welcome-banner::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -10%;
    width: 380px;
    height: 380px;
    background: radial-gradient(circle, rgba(255, 255, 255, 0.15) 0%, rgba(255, 255, 255, 0) 70%);
    border-radius: 50%;
    pointer-events: none;
}
.dashboard-welcome-banner::after {
    content: '';
    position: absolute;
    bottom: -60%;
    right: 15%;
    width: 260px;
    height: 260px;
    background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, rgba(255, 255, 255, 0) 70%);
    border-radius: 50%;
    pointer-events: none;
}
html[data-theme="dark"] .dashboard-welcome-banner {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #1e3a8a 100%);
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.4);
}

/* Metric KPI Cards */
.kpi-card {
    background: #ffffff;
    border: 1px solid rgba(226, 232, 240, 0.85);
    border-radius: 16px;
    padding: 20px 22px;
    box-shadow: 0 4px 20px rgba(18, 48, 95, 0.04);
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
    height: 100%;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}
.kpi-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 14px 28px rgba(18, 48, 95, 0.09);
    border-color: rgba(37, 99, 235, 0.3);
}
html[data-theme="dark"] .kpi-card {
    background: #132744;
    border-color: rgba(255, 255, 255, 0.08);
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.25);
}
html[data-theme="dark"] .kpi-card:hover {
    border-color: rgba(88, 183, 230, 0.4);
}

.kpi-icon-wrapper {
    width: 48px;
    height: 48px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.4rem;
    flex-shrink: 0;
}
.kpi-icon-blue    { background: rgba(37, 99, 235, 0.12); color: #2563eb; }
.kpi-icon-purple  { background: rgba(124, 58, 237, 0.12); color: #7c3aed; }
.kpi-icon-emerald { background: rgba(16, 185, 129, 0.12); color: #10b981; }
.kpi-icon-amber   { background: rgba(245, 158, 11, 0.12); color: #d97706; }
.kpi-icon-cyan    { background: rgba(6, 182, 212, 0.12); color: #0891b2; }

html[data-theme="dark"] .kpi-icon-blue    { background: rgba(59, 130, 246, 0.2); color: #60a5fa; }
html[data-theme="dark"] .kpi-icon-purple  { background: rgba(167, 139, 250, 0.2); color: #a78bfa; }
html[data-theme="dark"] .kpi-icon-emerald { background: rgba(16, 185, 129, 0.2); color: #34d399; }
html[data-theme="dark"] .kpi-icon-amber   { background: rgba(245, 158, 11, 0.2); color: #fbbf24; }
html[data-theme="dark"] .kpi-icon-cyan    { background: rgba(6, 182, 212, 0.2); color: #22d3ee; }

.kpi-clickable-card {
    text-decoration: none !important;
    color: inherit !important;
    cursor: pointer;
    display: flex;
    flex-direction: column;
    height: 100%;
}
.kpi-clickable-card:hover .kpi-card {
    transform: translateY(-5px);
    box-shadow: 0 14px 28px rgba(18, 48, 95, 0.12);
    border-color: #2563eb;
}
.kpi-clickable-card:hover .kpi-title {
    color: #2563eb !important;
}
html[data-theme="dark"] .kpi-clickable-card:hover .kpi-card {
    border-color: #60a5fa;
    box-shadow: 0 14px 28px rgba(0, 0, 0, 0.4);
}
html[data-theme="dark"] .kpi-clickable-card:hover .kpi-title {
    color: #60a5fa !important;
}

@media (min-width: 1200px) {
    .row-cols-xl-5 > * {
        flex: 0 0 auto;
        width: 20%;
    }
}

.kpi-value {
    font-size: 2.1rem;
    font-weight: 800;
    line-height: 1.1;
    color: var(--ink, #0f172a);
    letter-spacing: -0.02em;
}
html[data-theme="dark"] .kpi-value {
    color: #f8fafc;
}

.kpi-title {
    font-size: 0.8rem;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 6px;
}
html[data-theme="dark"] .kpi-title {
    color: #94a3b8;
}

/* Modern Chart Panels */
.chart-panel-card {
    background: #ffffff;
    border: 1px solid rgba(226, 232, 240, 0.85);
    border-radius: 16px;
    padding: 22px 24px;
    box-shadow: 0 4px 20px rgba(18, 48, 95, 0.04);
    transition: all 0.25s ease;
    height: 100%;
    display: flex;
    flex-direction: column;
}
.chart-panel-card:hover {
    box-shadow: 0 10px 28px rgba(18, 48, 95, 0.07);
}
html[data-theme="dark"] .chart-panel-card {
    background: #132744;
    border-color: rgba(255, 255, 255, 0.08);
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.25);
}

.chart-header-wrap {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 18px;
    padding-bottom: 14px;
    border-bottom: 1px solid #f1f5f9;
}
html[data-theme="dark"] .chart-header-wrap {
    border-bottom-color: rgba(255, 255, 255, 0.06);
}

.chart-heading-title {
    font-size: 1rem;
    font-weight: 700;
    color: #1e293b;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
}
html[data-theme="dark"] .chart-heading-title {
    color: #f1f5f9;
}

.chart-type-pill {
    font-size: 0.75rem;
    font-weight: 600;
    padding: 4px 10px;
    border-radius: 20px;
    background: #f8fafc;
    color: #64748b;
    border: 1px solid #e2e8f0;
}
html[data-theme="dark"] .chart-type-pill {
    background: #1e293b;
    color: #94a3b8;
    border-color: rgba(255, 255, 255, 0.1);
}

.chart-body-box {
    position: relative;
    width: 100%;
    flex-grow: 1;
    min-height: 270px;
}

/* User Role Breakdown Mini Cards */
.role-breakdown-card {
    padding: 10px 14px;
    border-radius: 12px;
    border: 1px solid transparent;
    transition: all 0.2s ease;
}
.role-breakdown-admin {
    background: rgba(239, 68, 68, 0.06);
    border-color: rgba(239, 68, 68, 0.2);
}
.role-breakdown-user {
    background: rgba(37, 99, 235, 0.06);
    border-color: rgba(37, 99, 235, 0.2);
}

/* Interactive Line Toggle Buttons */
.line-toggle-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 11px;
    border-radius: 20px;
    font-size: 0.76rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    border: 1px solid var(--line-color, #2563eb);
    background: transparent;
    color: var(--line-color, #2563eb);
}
.line-toggle-btn.active {
    background: var(--line-bg, rgba(37, 99, 235, 0.12));
    color: var(--line-color, #2563eb);
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
}
.line-toggle-btn:not(.active) {
    border-color: #cbd5e1;
    color: #94a3b8;
    background: transparent;
    opacity: 0.65;
    text-decoration: line-through;
}
html[data-theme="dark"] .line-toggle-btn:not(.active) {
    border-color: rgba(255, 255, 255, 0.15);
    color: #64748b;
}
.line-toggle-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: var(--line-color, #2563eb);
    display: inline-block;
    flex-shrink: 0;
}
.line-toggle-btn:not(.active) .line-toggle-dot {
    background: #94a3b8 !important;
}
</style>

<!-- ─── 1. TOP WELCOME & OVERVIEW BANNER ───────────────────────────────────── -->
<div class="dashboard-welcome-banner anim-fade-up delay-0">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 position-relative" style="z-index: 1;">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="badge bg-white text-primary rounded-pill fw-bold px-3 py-1 shadow-xs" style="font-size: 0.78rem;">
                    <i class="bi bi-shield-check me-1"></i>Hệ thống Quản trị
                </span>
                <span class="text-white-50 small">•</span>
                <span class="text-white-50 small"><?= date('d/m/Y') ?></span>
            </div>
            <h1 class="h4 fw-bold mb-1 text-white">
                Chào mừng trở lại, <?= htmlspecialchars($_SESSION['user_name'] ?? 'Quản trị viên') ?> 👋
            </h1>
            <p class="text-white-50 small mb-0">
                Tổng quan thống kê dữ liệu minh chứng kiểm định chất lượng chương trình đào tạo FBU.
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-light text-primary fw-semibold d-inline-flex align-items-center gap-2 shadow-xs px-3 py-2 rounded-3" href="<?= base_url('admin/standard_sets.php') ?>">
                <i class="bi bi-collection text-primary fs-6"></i>
                <span>Bộ tiêu chuẩn</span>
            </a>
            <a class="btn btn-outline-light d-inline-flex align-items-center gap-2 px-3 py-2 rounded-3" href="<?= base_url('admin/evidences.php') ?>">
                <i class="bi bi-folder-check fs-6"></i>
                <span>Minh chứng</span>
            </a>
            <a class="btn btn-outline-light d-inline-flex align-items-center gap-2 px-3 py-2 rounded-3" href="<?= base_url('admin/users.php') ?>">
                <i class="bi bi-people fs-6"></i>
                <span>Người dùng</span>
            </a>
        </div>
    </div>
</div>

<!-- ─── 2. KEY METRIC STATS CARDS (5 KPIs VỚI LIÊN KẾT ĐIỀU HƯỚNG TRỰC TIẾP) ─── -->
<div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-xl-5 g-3 mb-4">
    <!-- Card 1: Người dùng -->
    <div class="col anim-fade-up delay-1">
        <a href="<?= base_url('admin/users.php') ?>" class="kpi-clickable-card" title="Bấm để mở trang Quản lý Người dùng">
            <div class="kpi-card">
                <div>
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div class="kpi-icon-wrapper kpi-icon-blue">
                            <i class="bi bi-people-fill"></i>
                        </div>
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2.5 py-1 small fw-semibold">
                            <i class="bi bi-arrow-right-short"></i><?= $activeUsersCount ?> Online
                        </span>
                    </div>
                    <div class="kpi-title d-flex justify-content-between align-items-center">
                        <span>Tổng số người dùng</span>
                        <i class="bi bi-box-arrow-up-right small text-muted"></i>
                    </div>
                    <div class="kpi-value count-up mb-2" data-count-to="<?= $totalUsers ?>">0</div>
                </div>
                <div class="pt-2 border-top d-flex justify-content-between text-secondary small">
                    <span><i class="bi bi-shield-lock-fill text-danger me-1"></i><strong><?= $adminCount ?></strong> Admin</span>
                    <span><i class="bi bi-person-badge-fill text-primary me-1"></i><strong><?= $userCount ?></strong> User</span>
                </div>
            </div>
        </a>
    </div>

    <!-- Card 2: Bộ Tiêu chuẩn động -->
    <div class="col anim-fade-up delay-2">
        <a href="<?= base_url('admin/standard_sets.php') ?>" class="kpi-clickable-card" title="Bấm để mở trang Quản lý Bộ tiêu chuẩn">
            <div class="kpi-card">
                <div>
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div class="kpi-icon-wrapper kpi-icon-emerald">
                            <i class="bi bi-collection-fill"></i>
                        </div>
                        <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2.5 py-1 small fw-semibold">
                            <i class="bi bi-toggle-on me-1"></i><?= $activeSetsCount ?> Áp dụng
                        </span>
                    </div>
                    <div class="kpi-title d-flex justify-content-between align-items-center">
                        <span>Bộ tiêu chuẩn động</span>
                        <i class="bi bi-box-arrow-up-right small text-muted"></i>
                    </div>
                    <div class="kpi-value count-up mb-2" data-count-to="<?= $totalStandardSets ?>">0</div>
                </div>
                <div class="pt-2 border-top d-flex justify-content-between text-secondary small">
                    <span><i class="bi bi-file-earmark-ruled text-success me-1"></i>Căn cứ</span>
                    <span><strong><?= $totalCirculars ?></strong> Quy chuẩn</span>
                </div>
            </div>
        </a>
    </div>

    <!-- Card 3: Tổng số Tiêu chuẩn -->
    <div class="col anim-fade-up delay-3">
        <a href="<?= base_url('admin/standard_sets.php') ?>" class="kpi-clickable-card" title="Bấm để xem danh sách Tiêu chuẩn trong các bộ">
            <div class="kpi-card">
                <div>
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div class="kpi-icon-wrapper kpi-icon-cyan">
                            <i class="bi bi-folder2-open"></i>
                        </div>
                        <span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill px-2.5 py-1 small fw-semibold">
                            <i class="bi bi-layers-fill me-1"></i>Cấp 2
                        </span>
                    </div>
                    <div class="kpi-title d-flex justify-content-between align-items-center">
                        <span>Tổng số tiêu chuẩn</span>
                        <i class="bi bi-box-arrow-up-right small text-muted"></i>
                    </div>
                    <div class="kpi-value count-up mb-2" data-count-to="<?= $totalStandards ?>">0</div>
                </div>
                <div class="pt-2 border-top d-flex justify-content-between text-secondary small">
                    <span><i class="bi bi-collection text-info me-1"></i>Phân bổ</span>
                    <span><strong><?= $totalStandardSets ?></strong> Bộ tiêu chuẩn</span>
                </div>
            </div>
        </a>
    </div>

    <!-- Card 4: Tổng số Tiêu chí -->
    <div class="col anim-fade-up delay-4">
        <a href="<?= base_url('admin/standard_sets.php') ?>" class="kpi-clickable-card" title="Bấm để xem danh sách Tiêu chí đánh giá">
            <div class="kpi-card">
                <div>
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div class="kpi-icon-wrapper kpi-icon-purple">
                            <i class="bi bi-list-check"></i>
                        </div>
                        <span class="badge bg-purple-subtle text-purple border border-purple-subtle rounded-pill px-2.5 py-1 small fw-semibold" style="background-color: rgba(124, 58, 237, 0.1); color: #7c3aed; border-color: rgba(124, 58, 237, 0.2) !important;">
                            <i class="bi bi-check2-all me-1"></i>Cấp 3
                        </span>
                    </div>
                    <div class="kpi-title d-flex justify-content-between align-items-center">
                        <span>Tổng số tiêu chí</span>
                        <i class="bi bi-box-arrow-up-right small text-muted"></i>
                    </div>
                    <div class="kpi-value count-up mb-2" data-count-to="<?= $totalCriteria ?>">0</div>
                </div>
                <div class="pt-2 border-top d-flex justify-content-between text-secondary small">
                    <span><i class="bi bi-diagram-3 me-1" style="color: #7c3aed !important;"></i>Trực thuộc</span>
                    <span><strong><?= $totalStandards ?></strong> Tiêu chuẩn</span>
                </div>
            </div>
        </a>
    </div>

    <!-- Card 5: Tổng số Minh chứng -->
    <div class="col anim-fade-up delay-5">
        <a href="<?= base_url('admin/evidences.php') ?>" class="kpi-clickable-card" title="Bấm để mở trang Cập nhật Minh chứng">
            <div class="kpi-card">
                <div>
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div class="kpi-icon-wrapper kpi-icon-amber">
                            <i class="bi bi-folder-check"></i>
                        </div>
                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2.5 py-1 small fw-semibold">
                            <i class="bi bi-file-earmark-arrow-up-fill me-1"></i><?= $evidencesWithFiles ?> có tệp
                        </span>
                    </div>
                    <div class="kpi-title d-flex justify-content-between align-items-center">
                        <span>Tổng số minh chứng</span>
                        <i class="bi bi-box-arrow-up-right small text-muted"></i>
                    </div>
                    <div class="kpi-value count-up mb-2" data-count-to="<?= $totalEvidences ?>">0</div>
                </div>
                <div class="pt-2 border-top d-flex justify-content-between text-secondary small">
                    <span><i class="bi bi-database-check text-warning me-1"></i>Kho CSDL</span>
                    <span class="text-success fw-bold"><?= $totalEvidences > 0 ? round(($evidencesWithFiles / $totalEvidences) * 100) : 0 ?>% Đầy đủ</span>
                </div>
            </div>
        </a>
    </div>
</div>

<!-- ─── 3. CHARTS ROW 1: BAR CHART & DOUGHNUT CHART ────────────────────────── -->
<div class="row g-4 mb-4">
    <!-- Chart 1: Multi-Line Chart (Tương quan Tiêu chuẩn, Tiêu chí & Minh chứng) -->
    <div class="col-12 col-lg-8 scroll-reveal anim-fade-up delay-5">
        <div class="chart-panel-card">
            <div class="chart-header-wrap flex-wrap gap-2 panel-child-anim stagger-1">
                <div>
                    <h3 class="chart-heading-title">
                        <i class="bi bi-graph-up text-primary"></i>
                        Tương quan Tiêu chuẩn, Tiêu chí &amp; Minh chứng
                    </h3>
                    <p class="text-muted small mb-0 mt-0.5">So sánh số lượng Tiêu chuẩn, Tiêu chí và Minh chứng theo từng bộ tiêu chuẩn</p>
                </div>
                <!-- Interactive Toggle Toolbar -->
                <div class="d-flex flex-wrap align-items-center gap-1.5" id="lineToggleGroup">
                    <span class="text-muted small me-1 d-none d-sm-inline" style="font-size: 0.76rem;"><i class="bi bi-sliders me-1"></i>Bật/tắt:</span>
                    <button type="button" class="line-toggle-btn active" data-dataset-index="0" style="--line-color: #2563eb; --line-bg: rgba(37, 99, 235, 0.12);" title="Bật / tắt đường Tiêu chuẩn">
                        <span class="line-toggle-dot"></span>
                        <span>Tiêu chuẩn</span>
                    </button>
                    <button type="button" class="line-toggle-btn active" data-dataset-index="1" style="--line-color: #8b5cf6; --line-bg: rgba(139, 92, 246, 0.12);" title="Bật / tắt đường Tiêu chí">
                        <span class="line-toggle-dot"></span>
                        <span>Tiêu chí</span>
                    </button>
                    <button type="button" class="line-toggle-btn active" data-dataset-index="2" style="--line-color: #10b981; --line-bg: rgba(16, 185, 129, 0.12);" title="Bật / tắt đường Minh chứng">
                        <span class="line-toggle-dot"></span>
                        <span>Minh chứng</span>
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary py-1 px-2 line-toggle-all-btn" style="font-size: 0.74rem; border-radius: 20px;" title="Hiện / Ẩn tất cả các đường">
                        <i class="bi bi-check2-all me-1"></i>Tất cả
                    </button>
                </div>
            </div>
            <div class="chart-body-box panel-child-anim stagger-2" style="height: 300px;">
                <canvas id="multiLineComparisonChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Chart 2: Doughnut Chart (Cơ cấu Người dùng) -->
    <div class="col-12 col-lg-4 scroll-reveal anim-fade-up delay-5">
        <div class="chart-panel-card">
            <div class="chart-header-wrap panel-child-anim stagger-1">
                <div>
                    <h3 class="chart-heading-title">
                        <i class="bi bi-pie-chart-fill text-info"></i>
                        Cơ cấu Người dùng
                    </h3>
                    <p class="text-muted small mb-0 mt-0.5">Phân bổ tỷ trọng vai trò tài khoản</p>
                </div>

            </div>
            <div class="chart-body-box panel-child-anim stagger-2" style="height: 215px;">
                <canvas id="doughnutUserChart"></canvas>
            </div>
            
            <!-- Breakdown Cards -->
            <div class="row g-2 mt-3 pt-3 border-top panel-child-anim stagger-3">
                <div class="col-6">
                    <div class="role-breakdown-card role-breakdown-admin text-center">
                        <small class="d-block text-danger fw-bold text-truncate mb-1">
                            <i class="bi bi-shield-lock-fill me-1"></i>Quản trị viên
                        </small>
                        <div class="fw-bold fs-5 text-danger">
                            <?= $adminCount ?> <span class="fs-7 text-secondary fw-medium">(<?= $adminPct ?>%)</span>
                        </div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="role-breakdown-card role-breakdown-user text-center">
                        <small class="d-block text-primary fw-bold text-truncate mb-1">
                            <i class="bi bi-person-fill me-1"></i>Người dùng
                        </small>
                        <div class="fw-bold fs-5 text-primary">
                            <?= $userCount ?> <span class="fs-7 text-secondary fw-medium">(<?= $userPct ?>%)</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ─── 4. CHARTS ROW 2: AREA CHART & POLAR CHART ──────────────────────────── -->
<div class="row g-4 mb-4">
    <!-- Chart 3: Area Line Chart (Xu hướng tích lũy theo năm học) -->
    <div class="col-12 col-lg-6 scroll-reveal anim-fade-up delay-6">
        <div class="chart-panel-card">
            <div class="chart-header-wrap panel-child-anim stagger-1">
                <div>
                    <h3 class="chart-heading-title">
                        <i class="bi bi-graph-up-arrow text-success"></i>
                        Xu hướng Minh chứng theo Năm ban hành
                    </h3>
                    <p class="text-muted small mb-0 mt-0.5">Tiến độ và số lượng tài liệu qua các niên khóa</p>
                </div>
            </div>
            <div class="chart-body-box panel-child-anim stagger-2" style="height: 275px;">
                <canvas id="areaTrendChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Chart 4: Polar Area Chart (Tỷ trọng theo Thông tư) -->
    <div class="col-12 col-lg-6 scroll-reveal anim-fade-up delay-6">
        <div class="chart-panel-card">
            <div class="chart-header-wrap panel-child-anim stagger-1">
                <div>
                    <h3 class="chart-heading-title">
                        <i class="bi bi-radar text-warning"></i>
                        Phân bổ Tiêu chuẩn theo Thông tư
                    </h3>
                    <p class="text-muted small mb-0 mt-0.5">Số lượng tiêu chuẩn trực thuộc các thông tư quy định</p>
                </div>
            </div>
            <div class="chart-body-box panel-child-anim stagger-2" style="height: 275px;">
                <canvas id="polarCircularChart"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- ─── CHART.JS INITIALIZATION SCRIPTS ────────────────────────────────────── -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const isDarkMode = document.documentElement.dataset.theme === 'dark';
    const textColor = isDarkMode ? '#cbd5e1' : '#475569';
    const gridColor = isDarkMode ? 'rgba(255, 255, 255, 0.07)' : 'rgba(0, 0, 0, 0.05)';

    Chart.defaults.color = textColor;
    Chart.defaults.font.family = 'system-ui, -apple-system, "Segoe UI", Roboto, sans-serif';

    // 1. Multi-Line Chart: Tiêu chuẩn, Tiêu chí & Minh chứng theo Bộ tiêu chuẩn
    const multiLineCtx = document.getElementById('multiLineComparisonChart');
    let multiLineChart = null;
    if (multiLineCtx) {
        multiLineChart = new Chart(multiLineCtx, {
            type: 'line',
            plugins: [ChartDataLabels],
            data: {
                labels: <?= json_encode($multiSetLabels, JSON_UNESCAPED_UNICODE) ?>,
                datasets: [
                    {
                        label: 'Số lượng Tiêu chuẩn',
                        data: <?= json_encode($multiStandardsCount) ?>,
                        borderColor: '#2563eb',
                        backgroundColor: 'rgba(37, 99, 235, 0.08)',
                        borderWidth: 2.5,
                        tension: 0.35,
                        pointBackgroundColor: '#2563eb',
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2,
                        pointRadius: 4.5,
                        pointHoverRadius: 7,
                        fill: false
                    },
                    {
                        label: 'Số lượng Tiêu chí',
                        data: <?= json_encode($multiCriteriaCount) ?>,
                        borderColor: '#8b5cf6',
                        backgroundColor: 'rgba(139, 92, 246, 0.08)',
                        borderWidth: 2.5,
                        tension: 0.35,
                        pointBackgroundColor: '#8b5cf6',
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2,
                        pointRadius: 4.5,
                        pointHoverRadius: 7,
                        fill: false
                    },
                    {
                        label: 'Số lượng Minh chứng',
                        data: <?= json_encode($multiEvidencesCount) ?>,
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16, 185, 129, 0.08)',
                        borderWidth: 2.5,
                        tension: 0.35,
                        pointBackgroundColor: '#10b981',
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2,
                        pointRadius: 4.5,
                        pointHoverRadius: 7,
                        fill: false
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                layout: {
                    padding: {
                        top: 20,
                        right: 15
                    }
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        align: 'end',
                        onClick: function(e, legendItem, legend) {
                            const index = legendItem.datasetIndex;
                            const ci = legend.chart;
                            const isVisible = ci.isDatasetVisible(index);
                            ci.setDatasetVisibility(index, !isVisible);
                            ci.update();

                            // Sync toolbar buttons
                            const btn = document.querySelector(`.line-toggle-btn[data-dataset-index="${index}"]`);
                            if (btn) btn.classList.toggle('active', !isVisible);
                        },
                        labels: {
                            boxWidth: 10,
                            boxHeight: 10,
                            borderRadius: 5,
                            usePointStyle: true,
                            pointStyle: 'circle',
                            padding: 14,
                            font: {
                                size: 11,
                                weight: '600'
                            }
                        }
                    },
                    datalabels: {
                        display: function(context) {
                            return context.chart.isDatasetVisible(context.datasetIndex);
                        },
                        align: 'top',
                        offset: 3,
                        color: function(context) {
                            return context.dataset.borderColor;
                        },
                        font: {
                            weight: 'bold',
                            size: 11
                        },
                        formatter: function(value) {
                            return value !== null && value !== undefined ? value : 0;
                        }
                    },
                    tooltip: {
                        padding: 12,
                        cornerRadius: 10,
                        backgroundColor: isDarkMode ? '#1e293b' : 'rgba(15, 23, 42, 0.92)',
                        titleFont: { weight: 'bold', size: 13 },
                        bodyFont: { size: 12 },
                        boxPadding: 4
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { font: { size: 11, weight: '600' } }
                    },
                    y: {
                        beginAtZero: true,
                        grace: '15%',
                        grid: { color: gridColor },
                        ticks: { stepSize: 2, font: { size: 11 } }
                    }
                }
            }
        });

        // Xử lý sự kiện click cho các nút bật/tắt đường hiển thị
        const lineToggleBtns = document.querySelectorAll('.line-toggle-btn');
        const toggleAllBtn = document.querySelector('.line-toggle-all-btn');

        lineToggleBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                const idx = parseInt(this.dataset.datasetIndex, 10);
                const isVisible = multiLineChart.isDatasetVisible(idx);
                multiLineChart.setDatasetVisibility(idx, !isVisible);
                multiLineChart.update();
                this.classList.toggle('active', !isVisible);
            });
        });

        if (toggleAllBtn) {
            toggleAllBtn.addEventListener('click', function() {
                let anyVisible = false;
                for (let i = 0; i < 3; i++) {
                    if (multiLineChart.isDatasetVisible(i)) {
                        anyVisible = true;
                        break;
                    }
                }
                const targetState = !anyVisible;
                for (let i = 0; i < 3; i++) {
                    multiLineChart.setDatasetVisibility(i, targetState);
                }
                multiLineChart.update();
                lineToggleBtns.forEach(btn => btn.classList.toggle('active', targetState));
            });
        }
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
            ctx.font = '800 22px system-ui, -apple-system, sans-serif';
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
                    borderColor: isDarkMode ? '#132744' : '#ffffff',
                    borderWidth: 3,
                    hoverOffset: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                layout: {
                    padding: 6
                },
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            boxWidth: 10,
                            padding: 10,
                            font: {
                                size: 11,
                                weight: '600'
                            }
                        }
                    },
                    tooltip: {
                        padding: 10,
                        cornerRadius: 8,
                        backgroundColor: isDarkMode ? '#1e293b' : 'rgba(15, 23, 42, 0.9)',
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
                            size: 11
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
                    label: 'Minh chứng đã thu thập',
                    data: <?= json_encode($yearData) ?>,
                    fill: true,
                    backgroundColor: 'rgba(16, 185, 129, 0.15)',
                    borderColor: '#10b981',
                    borderWidth: 3,
                    tension: 0.4,
                    pointBackgroundColor: '#10b981',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    pointHoverRadius: 7
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: {
                    duration: 1200,
                    easing: 'easeOutQuart'
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        padding: 10,
                        cornerRadius: 8,
                        backgroundColor: isDarkMode ? '#1e293b' : 'rgba(15, 23, 42, 0.9)'
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { font: { size: 11, weight: '600' } }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: gridColor },
                        ticks: { stepSize: 1, font: { size: 11 } }
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
                    borderColor: isDarkMode ? '#132744' : '#ffffff',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: {
                    animateRotate: true,
                    animateScale: true,
                    duration: 1300,
                    easing: 'easeOutQuart'
                },
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            boxWidth: 10,
                            padding: 8,
                            font: { size: 11, weight: '500' }
                        }
                    },
                    tooltip: {
                        padding: 10,
                        cornerRadius: 8,
                        backgroundColor: isDarkMode ? '#1e293b' : 'rgba(15, 23, 42, 0.9)'
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

    // 5. Hiệu ứng đếm số mượt mà (Count-Up Animation)
    function animateSingleCountUp(el) {
        if (el.classList.contains('counted')) return;
        el.classList.add('counted');

        const target = parseInt(el.dataset.countTo || el.textContent || '0', 10);
        if (isNaN(target) || target === 0) {
            el.textContent = '0';
            return;
        }
        
        let start = 0;
        const duration = 1200; // ms
        const startTime = performance.now();

        function updateCount(currentTime) {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);
            // Ease-out cubic formula
            const easeOut = 1 - Math.pow(1 - progress, 3);
            const currentVal = Math.round(start + (target - start) * easeOut);
            el.textContent = currentVal;

            if (progress < 1) {
                requestAnimationFrame(updateCount);
            } else {
                el.textContent = target;
            }
        }
        requestAnimationFrame(updateCount);
    }

    function animateAllCountUps() {
        document.querySelectorAll('.count-up').forEach(el => animateSingleCountUp(el));
    }

    // 6. Hiệu ứng cuộn hiển thị dần nội dung (IntersectionObserver Scroll Reveal)
    if ('IntersectionObserver' in window) {
        const revealElements = document.querySelectorAll('.scroll-reveal, .kpi-card');
        const observer = new IntersectionObserver((entries, obs) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-revealed');
                    // Đếm số khi thẻ xuất hiện trong tầm nhìn
                    const counters = entry.target.querySelectorAll('.count-up');
                    counters.forEach(c => animateSingleCountUp(c));
                    obs.unobserve(entry.target);
                }
            });
        }, {
            threshold: 0.08,
            rootMargin: '0px 0px -30px 0px'
        });

        revealElements.forEach(el => observer.observe(el));
    } else {
        document.querySelectorAll('.scroll-reveal').forEach(el => el.classList.add('is-revealed'));
        animateAllCountUps();
    }

    // Khởi động đếm số cho các thẻ nhìn thấy đầu trang
    animateAllCountUps();
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
