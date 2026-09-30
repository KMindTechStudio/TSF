<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/helpers.php';
require_login();
require_once __DIR__ . '/../config/database.php';

$pdo = db();

// =========================================================================
// 1. XỬ LÝ XUẤT EXCEL ĐA CẤP (HIERARCHICAL EXCEL EXPORT WITH OUTLINE / GROUPING)
// Sổ ra / gập vào 4 cấp: Bộ tiêu chuẩn -> Tiêu chuẩn -> Tiêu chí -> Minh chứng
// =========================================================================
if (($_GET['export'] ?? '') === 'excel') {
    $filter = [
        'q'            => trim($_GET['q'] ?? $_GET['keyword'] ?? $_GET['search'] ?? ''),
        'standard_set' => trim($_GET['standard_set'] ?? $_GET['set'] ?? ''),
        'standard'     => trim($_GET['standard'] ?? ''),
        'criterion'    => trim($_GET['criterion'] ?? ''),
        'evidence'     => trim($_GET['evidence'] ?? ''),
        'status'       => isset($_GET['status']) && $_GET['status'] !== '' ? (int)$_GET['status'] : null,
    ];

    $filename = 'csdl_minh_chung_phan_cap_' . date('Ymd_His') . '.xlsx';
    export_hierarchical_standards_excel($filename, $filter);
    exit;
}

// ==========================================
// SEARCH & FILTER PARAMS
// ==========================================
$searchKeyword        = trim($_GET['q'] ?? $_GET['keyword'] ?? $_GET['search'] ?? '');
$selectedStandardSet  = trim($_GET['standard_set'] ?? $_GET['set'] ?? '');
$selectedStandard     = trim($_GET['standard'] ?? '');
$selectedCriterion    = trim($_GET['criterion'] ?? '');
$selectedEvidence     = trim($_GET['evidence'] ?? '');
$selectedStatus       = isset($_GET['status']) && $_GET['status'] !== '' ? (int)$_GET['status'] : null;

$perPage = 5;
$currentPage = max(1, (int)($_GET['page'] ?? 1));

// Build WHERE conditions
$whereClauses = [];
$queryParams = [];

if ($searchKeyword !== '') {
    $whereClauses[] = "(b.MaBoTieuChuan LIKE :k1 OR b.TenBoTieuChuan LIKE :k2 OR b.ThongTu LIKE :k3)";
    $queryParams['k1'] = "%$searchKeyword%";
    $queryParams['k2'] = "%$searchKeyword%";
    $queryParams['k3'] = "%$searchKeyword%";
}

if ($selectedStandardSet !== '') {
    $whereClauses[] = "b.MaBoTieuChuan = :selected_set";
    $queryParams['selected_set'] = $selectedStandardSet;
}

if ($selectedStandard !== '') {
    $whereClauses[] = "b.MaBoTieuChuan IN (SELECT MaBoTieuChuan FROM TieuChuan WHERE MaTieuChuan = :selected_std)";
    $queryParams['selected_std'] = $selectedStandard;
}

if ($selectedCriterion !== '') {
    $whereClauses[] = "b.MaBoTieuChuan IN (
        SELECT tc.MaBoTieuChuan 
        FROM TieuChuan tc 
        JOIN TieuChi tchi ON tchi.MaTieuChuan = tc.MaTieuChuan 
        WHERE tchi.MaTieuChi = :selected_crit
    )";
    $queryParams['selected_crit'] = $selectedCriterion;
}

if ($selectedEvidence !== '') {
    $whereClauses[] = "b.MaBoTieuChuan IN (
        SELECT DISTINCT COALESCE(m.MaBoTieuChuan, tc.MaBoTieuChuan)
        FROM MinhChung m
        LEFT JOIN TieuChi tchi ON tchi.MaTieuChi = m.MaTieuChi
        LEFT JOIN TieuChuan tc ON tc.MaTieuChuan = tchi.MaTieuChuan
        WHERE m.MaMinhChung = :selected_ev
    )";
    $queryParams['selected_ev'] = $selectedEvidence;
}

if ($selectedStatus !== null) {
    $whereClauses[] = "b.TrangThai = :selected_status";
    $queryParams['selected_status'] = $selectedStatus;
}

$whereSql = !empty($whereClauses) ? ' WHERE ' . implode(' AND ', $whereClauses) : '';

// Count total records
$countQuery = "SELECT COUNT(*) FROM BoTieuChuan b" . $whereSql;
$stmtCount = $pdo->prepare($countQuery);
$stmtCount->execute($queryParams);
$totalRecords = (int)$stmtCount->fetchColumn();
$totalPages = max(1, ceil($totalRecords / $perPage));
if ($currentPage > $totalPages) {
    $currentPage = $totalPages;
}
$offset = ($currentPage - 1) * $perPage;

// 1. Fetch Standard Sets (Paginated)
$querySets = "
    SELECT 
        b.MaBoTieuChuan,
        b.TenBoTieuChuan,
        COALESCE(b.ThongTu, '') AS ThongTu,
        b.NgayBanHanh,
        b.MoTa,
        b.TepTinPDF,
        b.TrangThai,
        COUNT(DISTINCT tc.MaTieuChuan) AS total_standards,
        COUNT(DISTINCT tchi.MaTieuChi) AS total_criteria,
        COUNT(DISTINCT m.MaMinhChung) AS total_evidences
    FROM BoTieuChuan b
    LEFT JOIN TieuChuan tc ON tc.MaBoTieuChuan = b.MaBoTieuChuan
    LEFT JOIN TieuChi tchi ON tchi.MaTieuChuan = tc.MaTieuChuan
    LEFT JOIN MinhChung m ON (m.MaBoTieuChuan = b.MaBoTieuChuan OR m.MaTieuChi = tchi.MaTieuChi)
" . $whereSql . " GROUP BY b.MaBoTieuChuan, b.TenBoTieuChuan, b.ThongTu, b.NgayBanHanh, b.MoTa, b.TepTinPDF, b.TrangThai ORDER BY b.TrangThai DESC, b.MaBoTieuChuan ASC LIMIT :offset, :perPage";

$stmtSets = $pdo->prepare($querySets);
foreach ($queryParams as $k => $v) {
    $stmtSets->bindValue($k, $v);
}
$stmtSets->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
$stmtSets->bindValue(':perPage', (int)$perPage, PDO::PARAM_INT);
$stmtSets->execute();
$standardSetsList = $stmtSets->fetchAll(PDO::FETCH_ASSOC);

// Full list for Filter Dropdowns
$allSetsForFilter = $pdo->query("SELECT MaBoTieuChuan, TenBoTieuChuan, ThongTu FROM BoTieuChuan ORDER BY TrangThai DESC, MaBoTieuChuan ASC")->fetchAll(PDO::FETCH_ASSOC);
$allStandardsForFilter = $pdo->query("SELECT MaTieuChuan, TenTieuChuan, MaBoTieuChuan FROM TieuChuan ORDER BY MaBoTieuChuan ASC, ThuTu ASC, MaTieuChuan ASC")->fetchAll(PDO::FETCH_ASSOC);
$allCriteriaForFilter = $pdo->query("SELECT tchi.MaTieuChi, tchi.TenTieuChi, tchi.MaTieuChuan, tc.MaBoTieuChuan FROM TieuChi tchi LEFT JOIN TieuChuan tc ON tc.MaTieuChuan = tchi.MaTieuChuan ORDER BY tchi.ThuTu ASC, tchi.MaTieuChi ASC")->fetchAll(PDO::FETCH_ASSOC);
$allEvidencesForFilter = $pdo->query("SELECT m.MaMinhChung, m.TenMinhChung, m.MaTieuChi, m.MaBoTieuChuan, tc.MaTieuChuan FROM MinhChung m LEFT JOIN TieuChi tchi ON tchi.MaTieuChi = m.MaTieuChi LEFT JOIN TieuChuan tc ON tc.MaTieuChuan = tchi.MaTieuChuan ORDER BY m.MaMinhChung ASC")->fetchAll(PDO::FETCH_ASSOC);

// Filter query parameters for links
$currentFilterParams = [];
if ($searchKeyword !== '') $currentFilterParams['q'] = $searchKeyword;
if ($selectedStandardSet !== '') $currentFilterParams['standard_set'] = $selectedStandardSet;
if ($selectedStandard !== '') $currentFilterParams['standard'] = $selectedStandard;
if ($selectedCriterion !== '') $currentFilterParams['criterion'] = $selectedCriterion;
if ($selectedEvidence !== '') $currentFilterParams['evidence'] = $selectedEvidence;
if ($selectedStatus !== null) $currentFilterParams['status'] = $selectedStatus;

// 2. Fetch Standards grouped by Set
$stmtAllTC = $pdo->query("
    SELECT 
        tc.MaTieuChuan,
        tc.TenTieuChuan,
        tc.MoTa,
        tc.ThuTu,
        tc.MaBoTieuChuan,
        COUNT(DISTINCT tchi.MaTieuChi) AS total_criteria
    FROM TieuChuan tc
    LEFT JOIN TieuChi tchi ON tchi.MaTieuChuan = tc.MaTieuChuan
    GROUP BY tc.MaTieuChuan, tc.TenTieuChuan, tc.MoTa, tc.ThuTu, tc.MaBoTieuChuan
    ORDER BY tc.ThuTu ASC, tc.MaTieuChuan ASC
");
$allStandardsBySet = [];
foreach ($stmtAllTC->fetchAll(PDO::FETCH_ASSOC) as $tc) {
    $allStandardsBySet[$tc['MaBoTieuChuan']][] = $tc;
}

// 3. Fetch Criteria grouped by Standard
$stmtAllTChi = $pdo->query("
    SELECT 
        tchi.MaTieuChi,
        tchi.TenTieuChi,
        tchi.NoiDung,
        tchi.ThuTu,
        tchi.MaTieuChuan,
        COUNT(DISTINCT m.MaMinhChung) AS total_evidences
    FROM TieuChi tchi
    LEFT JOIN MinhChung m ON m.MaTieuChi = tchi.MaTieuChi
    GROUP BY tchi.MaTieuChi, tchi.TenTieuChi, tchi.NoiDung, tchi.ThuTu, tchi.MaTieuChuan
    ORDER BY tchi.ThuTu ASC, tchi.MaTieuChi ASC
");
$allCriteriaByStandard = [];
foreach ($stmtAllTChi->fetchAll(PDO::FETCH_ASSOC) as $tchi) {
    $allCriteriaByStandard[$tchi['MaTieuChuan']][] = $tchi;
}

// 4. Fetch Evidences grouped by Criterion
$stmtAllMC = $pdo->query("
    SELECT 
        m.MaMinhChung,
        m.TenMinhChung,
        m.NgayBanHanh,
        m.MoTa,
        m.TepTin,
        m.NamHoc,
        m.TrangThai,
        m.MaTieuChi,
        m.MaBoTieuChuan,
        m.NgayCapNhat,
        u.HoTen AS NguoiTao
    FROM MinhChung m
    LEFT JOIN NguoiDung u ON u.MaNguoiDung = m.MaNguoiDung
    ORDER BY m.MaMinhChung ASC
");
$allEvidencesByCriterion = [];
foreach ($stmtAllMC->fetchAll(PDO::FETCH_ASSOC) as $mc) {
    if (!empty($mc['MaTieuChi'])) {
        $allEvidencesByCriterion[$mc['MaTieuChi']][] = $mc;
    }
}

$pageTitle = page_title('Quản lý CSDL Minh chứng');
$heading   = 'Quản lý CSDL Minh chứng';
include __DIR__ . '/../includes/header.php';
?>

<style>
/* ==============================================
   PREMIUM MODERN DESIGN SYSTEM FOR CSDL MINH CHỨNG
================================================= */
.standards-card {
    background: #ffffff;
    border: 1px solid rgba(226, 232, 240, 0.8);
    border-radius: 16px;
    box-shadow: 0 4px 20px rgba(18, 48, 95, 0.05);
    padding: 24px;
    transition: all 0.25s ease;
}
html[data-theme="dark"] .standards-card {
    background: #132744;
    border-color: rgba(255, 255, 255, 0.08);
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.25);
}

/* Interactive Hierarchy Navigation & Target Highlighting */
.interactive-hierarchy-step {
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    user-select: none;
}
.interactive-hierarchy-step:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.16) !important;
    border-color: #2563eb !important;
    background-color: #eff6ff !important;
}
.interactive-tree-item {
    cursor: pointer;
    border-radius: 4px;
    padding: 2px 6px;
    transition: all 0.15s ease-in-out;
    display: block;
    user-select: none;
}
.interactive-tree-item:hover {
    background-color: #dbeafe;
    color: #1d4ed8 !important;
    text-decoration: underline;
}
.interactive-link {
    cursor: pointer;
    transition: all 0.15s ease-in-out;
}
.interactive-link:hover {
    color: #1d4ed8 !important;
    text-decoration: underline;
}
/* Hiệu ứng Đốm sáng & Viền phát quang cực kỳ nổi bật khi nhảy tới dòng */
@keyframes targetRowPulse {
    0% {
        background: linear-gradient(90deg, rgba(254, 240, 138, 0.75) 0%, rgba(191, 219, 254, 0.85) 35%, rgba(219, 234, 254, 0.4) 100%) !important;
        box-shadow: 0 0 0 4px #2563eb, 0 0 28px rgba(37, 99, 235, 0.55), inset 0 0 12px rgba(37, 99, 235, 0.15) !important;
        outline: 2px solid #2563eb !important;
        outline-offset: -1px;
        transform: scale(1.012);
    }
    30% {
        background: linear-gradient(90deg, rgba(254, 240, 138, 0.55) 0%, rgba(191, 219, 254, 0.65) 45%, rgba(219, 234, 254, 0.25) 100%) !important;
        box-shadow: 0 0 0 6px rgba(37, 99, 235, 0.4), 0 0 35px rgba(37, 99, 235, 0.4) !important;
        outline: 2px solid #2563eb !important;
        transform: scale(1.006);
    }
    60% {
        background: rgba(219, 234, 254, 0.3) !important;
        box-shadow: 0 0 0 8px rgba(37, 99, 235, 0.15), 0 0 20px rgba(37, 99, 235, 0.2) !important;
        outline: 2px solid rgba(37, 99, 235, 0.4) !important;
        transform: scale(1);
    }
    100% {
        background: transparent;
        box-shadow: none;
        outline: none;
        transform: scale(1);
    }
}

.highlight-target-row {
    animation: targetRowPulse 3.5s cubic-bezier(0.2, 0.8, 0.2, 1) forwards;
    position: relative !important;
    z-index: 30 !important;
    border-left: 6px solid #2563eb !important;
    border-radius: 6px;
    transition: border-left 0.3s ease;
}

/* Thẻ định vị nổi (Floating Target Beacon Badge) */
.jump-target-beacon {
    position: absolute;
    top: -14px;
    left: 24px;
    z-index: 999;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 14px;
    background: linear-gradient(135deg, #1e3a8a, #2563eb);
    color: #ffffff;
    font-size: 0.78rem;
    font-weight: 700;
    letter-spacing: 0.3px;
    border-radius: 20px;
    box-shadow: 0 4px 16px rgba(37, 99, 235, 0.5), 0 0 0 2px rgba(255, 255, 255, 0.8);
    pointer-events: none;
    animation: beaconBounce 3.5s ease-out forwards;
}
@keyframes beaconBounce {
    0% {
        opacity: 0;
        transform: translateY(-8px) scale(0.85);
    }
    15% {
        opacity: 1;
        transform: translateY(0) scale(1.05);
    }
    30% {
        transform: translateY(-2px) scale(1);
    }
    75% {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
    100% {
        opacity: 0;
        transform: translateY(-6px) scale(0.9);
    }
}

/* Tree Toggle Button */
.tree-toggle-btn {
    width: 32px;
    height: 32px;
    padding: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    background-color: #f8fafc;
    color: var(--brand, #2f64ad);
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}
.tree-toggle-btn:hover {
    background-color: #eef4fc;
    border-color: var(--brand, #2f64ad);
    transform: scale(1.05);
}
.tree-toggle-btn i {
    display: inline-block;
    transition: transform 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    font-size: 0.9rem;
    line-height: 1;
}
.tree-toggle-btn[aria-expanded="true"] i,
.tree-toggle-btn.is-open i,
.tree-toggle-btn.expanded i {
    transform: rotate(90deg);
}
.tree-toggle-btn[aria-expanded="true"],
.tree-toggle-btn.is-open,
.tree-toggle-btn.expanded {
    background: linear-gradient(135deg, #2f64ad, #174f9a) !important;
    color: #ffffff !important;
    border-color: transparent !important;
    box-shadow: 0 2px 8px rgba(47, 100, 173, 0.35);
}

/* Table Enhancements */
.standards-table {
    border-collapse: separate;
    border-spacing: 0;
}
.standards-table thead th,
.table thead th {
    background: #f8fafc;
    color: #475569;
    font-weight: 600;
    font-size: 0.8125rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 2px solid #e2e8f0;
    padding: 12px 14px;
}
html[data-theme="dark"] .standards-table thead th,
html[data-theme="dark"] .table thead th {
    background: #0f1f38;
    color: #94a3b8;
    border-bottom-color: rgba(255, 255, 255, 0.1);
}
.standards-table tbody tr.table-set-row td {
    padding: 16px 14px;
    border-bottom: 1px solid #f1f5f9;
}
.standards-table tbody tr.table-set-row:hover td {
    background-color: rgba(47, 100, 173, 0.025);
}

/* Nested Containers */
.nested-container {
    background: #f8fafc;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    border-left: 4px solid var(--brand, #2f64ad);
    padding: 18px 20px;
    margin: 8px 12px 14px 12px;
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.03);
}
.nested-criteria-container {
    background: #ffffff;
    border-radius: 10px;
    border: 1px solid #e2e8f0;
    border-left: 3px solid #10b981;
    padding: 14px 16px;
    margin: 6px 0 10px 20px;
}
html[data-theme="dark"] .nested-container {
    background: #0f1f38;
    border-color: rgba(255, 255, 255, 0.1);
}
html[data-theme="dark"] .nested-criteria-container {
    background: #132744;
    border-color: rgba(255, 255, 255, 0.1);
}

/* Custom Modern Pagination */
.modern-pagination-wrapper {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    margin-top: 20px;
    padding-top: 18px;
    border-top: 1px solid #e2e8f0;
}
.modern-pagination {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    list-style: none;
    margin: 0;
    padding: 4px;
    background: #f8fafc;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
}
html[data-theme="dark"] .modern-pagination {
    background: #0f1f38;
    border-color: rgba(255, 255, 255, 0.1);
}
.modern-pagination .page-item .page-link {
    min-width: 36px;
    height: 36px;
    padding: 0 12px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px !important;
    border: 1px solid transparent;
    color: #475569;
    font-weight: 600;
    font-size: 0.875rem;
    background: transparent;
    transition: all 0.2s ease;
    text-decoration: none;
}
html[data-theme="dark"] .modern-pagination .page-item .page-link {
    color: #94a3b8;
}
.modern-pagination .page-item .page-link:hover {
    background: #ffffff;
    color: var(--brand, #2f64ad);
    border-color: #cbd5e1;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.04);
}
html[data-theme="dark"] .modern-pagination .page-item .page-link:hover {
    background: #1e3a66;
    color: #ffffff;
    border-color: rgba(255, 255, 255, 0.15);
}
.modern-pagination .page-item.active .page-link {
    background: linear-gradient(135deg, #2f64ad, #174f9a) !important;
    color: #ffffff !important;
    border-color: transparent !important;
    box-shadow: 0 3px 10px rgba(47, 100, 173, 0.35);
}
.modern-pagination .page-item.disabled .page-link {
    color: #cbd5e1;
    pointer-events: none;
    background: transparent;
}
html[data-theme="dark"] .modern-pagination .page-item.disabled .page-link {
    color: #475569;
}

/* PDF Modal Enhancements */
.pdf-viewer-modal-dialog {
    max-width: 95vw;
    width: 1200px;
    height: 90vh;
}
.pdf-iframe-container {
    width: 100%;
    height: calc(90vh - 65px);
    border: none;
}
.line-clamp-1 {
    display: -webkit-box;
    -webkit-line-clamp: 1;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>

<div class="row g-4">
    <div class="col-12">
        <div class="standards-card">
            <!-- Top Header & Actions -->
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
                <div>
                    <div class="d-flex align-items-center gap-2">
                        <div class="p-2 rounded-3 bg-primary-subtle text-primary">
                            <i class="bi bi-folder2-open fs-4"></i>
                        </div>
                        <div>
                            <h2 class="h5 mb-0 fw-bold text-dark">Quản lý CSDL Minh chứng</h2>
                            <span class="text-muted small">Mô hình cây phân cấp: <strong>Bộ tiêu chuẩn &rarr; Tiêu chuẩn &rarr; Tiêu chí &rarr; Minh chứng</strong></span>
                        </div>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a class="btn btn-outline-secondary d-inline-flex align-items-center gap-2 px-3 py-2 rounded-3" href="<?= base_url('user/search.php?export=excel' . (!empty($currentFilterParams) ? '&' . http_build_query($currentFilterParams) : '')) ?>">
                        <i class="bi bi-file-earmark-excel-fill text-success"></i> Xuất Excel
                    </a>
                </div>
            </div>

            <!-- ======================= BỘ LỌC & TÌM KIẾM TẬP TRUNG THÔNG MINH ======================= -->
            <div class="card border-0 shadow-sm rounded-4 mb-4 filter-panel-card" style="background: var(--surface, #ffffff); border: 1px solid rgba(226, 232, 240, 0.8) !important;">
                <div class="card-body p-4">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 pb-2 border-bottom">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge rounded-pill bg-primary-subtle text-primary px-3 py-2 fs-6">
                                <i class="bi bi-funnel-fill me-1"></i> Bộ lọc & Tìm kiếm tập trung
                            </span>
                            <span class="text-secondary small d-none d-md-inline">
                                Chọn điều kiện hoặc nhập từ khóa rồi nhấn nút <strong>Lọc</strong>
                            </span>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <button type="submit" form="filterForm" class="btn btn-sm btn-primary rounded-pill px-3 shadow-sm d-inline-flex align-items-center gap-1" id="btnSubmitFilterHeader" title="Áp dụng bộ lọc">
                                <i class="bi bi-funnel-fill"></i> Lọc dữ liệu
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3" id="btnResetAll" title="Đặt lại tất cả bộ lọc">
                                <i class="bi bi-arrow-counterclockwise me-1"></i> Đặt lại
                            </button>
                        </div>
                    </div>

                    <form id="filterForm" method="get" action="" class="row g-3">
                        <!-- CÁCH 1: Ô NHẬP TỪ KHÓA TÌM NHANH -->
                        <div class="col-12 col-xxl-3 col-xl-3">
                            <label for="filterKeyword" class="form-label fw-bold small text-primary mb-1 d-flex align-items-center gap-1">
                                <i class="bi bi-search"></i> Tìm kiếm
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0 text-muted">
                                    <i class="bi bi-search"></i>
                                </span>
                                <input type="text" 
                                       class="form-control bg-light border-start-0 border-end-0 ps-0" 
                                       id="filterKeyword" 
                                       name="q"
                                       placeholder="Nhập mã (BTC01...) hoặc tên, số hiệu..." 
                                       value="<?= htmlspecialchars($searchKeyword) ?>"
                                       autocomplete="off">
                                <button class="btn btn-light border border-start-0 text-muted" type="button" id="btnClearKeyword" style="display: <?= $searchKeyword !== '' ? 'block' : 'none' ?>;" title="Xóa từ khóa">
                                    <i class="bi bi-x-circle-fill"></i>
                                </button>
                                <button class="btn btn-primary d-inline-flex align-items-center gap-1" type="submit" title="Tìm kiếm theo từ khóa">
                                    <i class="bi bi-search"></i> Lọc
                                </button>
                            </div>
                            <div class="form-text small text-secondary mt-1">
                                <i class="bi bi-info-circle text-primary"></i> Nhập từ khóa rồi bấm <strong>Lọc</strong> hoặc nhấn Enter
                            </div>
                        </div>

                        <!-- CÁCH 2: CỤM 5 Ô CHỌN NHANH PHÂN CẤP LIÊN KẾT + NÚT LỌC -->
                        <div class="col-12 col-xxl-9 col-xl-9">
                            <label class="form-label fw-bold small text-primary mb-1 d-flex align-items-center gap-1">
                                <i class="bi bi-diagram-3"></i> Tìm kiếm phân cấp
                            </label>
                            <div class="row g-2 align-items-center">
                                <!-- Dropdown 1: Bộ Tiêu chuẩn -->
                                <div class="col-12 col-sm-6 col-md-4 col-xl">
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text bg-light text-muted" title="Lọc theo Bộ Tiêu chuẩn"><i class="bi bi-collection"></i></span>
                                        <select class="form-select form-select-sm" id="filterStandardSet" name="standard_set">
                                            <option value="">-- Tất cả Bộ Tiêu chuẩn (<?= count($allSetsForFilter) ?>) --</option>
                                            <?php foreach ($allSetsForFilter as $stSet): ?>
                                                <option value="<?= htmlspecialchars($stSet['MaBoTieuChuan']) ?>" <?= $selectedStandardSet === (string)$stSet['MaBoTieuChuan'] ? 'selected' : '' ?> title="<?= htmlspecialchars($stSet['TenBoTieuChuan']) ?>">
                                                    <?= htmlspecialchars($stSet['MaBoTieuChuan'] . ' - ' . $stSet['TenBoTieuChuan']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>

                                <!-- Dropdown 2: Tiêu chuẩn -->
                                <div class="col-12 col-sm-6 col-md-4 col-xl">
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text bg-light text-muted" title="Lọc theo Tiêu chuẩn"><i class="bi bi-folder2"></i></span>
                                        <select class="form-select form-select-sm" id="filterStandard" name="standard">
                                            <option value="">-- Tất cả Tiêu chuẩn (<?= count($allStandardsForFilter) ?>) --</option>
                                            <?php foreach ($allStandardsForFilter as $tc): ?>
                                                <option value="<?= htmlspecialchars($tc['MaTieuChuan']) ?>" 
                                                        data-set="<?= htmlspecialchars($tc['MaBoTieuChuan']) ?>"
                                                        <?= $selectedStandard === (string)$tc['MaTieuChuan'] ? 'selected' : '' ?> 
                                                        title="<?= htmlspecialchars($tc['TenTieuChuan']) ?>">
                                                    <?= htmlspecialchars($tc['MaTieuChuan'] . ' - ' . $tc['TenTieuChuan']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>

                                <!-- Dropdown 3: Tiêu chí -->
                                <div class="col-12 col-sm-6 col-md-4 col-xl">
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text bg-light text-muted" title="Lọc theo Tiêu chí"><i class="bi bi-list-task"></i></span>
                                        <select class="form-select form-select-sm" id="filterCriterion" name="criterion">
                                            <option value="">-- Tất cả Tiêu chí (<?= count($allCriteriaForFilter) ?>) --</option>
                                            <?php foreach ($allCriteriaForFilter as $cri): ?>
                                                <option value="<?= htmlspecialchars($cri['MaTieuChi']) ?>" 
                                                        data-standard="<?= htmlspecialchars($cri['MaTieuChuan']) ?>"
                                                        data-set="<?= htmlspecialchars($cri['MaBoTieuChuan']) ?>"
                                                        <?= $selectedCriterion === (string)$cri['MaTieuChi'] ? 'selected' : '' ?> 
                                                        title="<?= htmlspecialchars($cri['TenTieuChi']) ?>">
                                                    <?= htmlspecialchars($cri['MaTieuChi'] . ' - ' . $cri['TenTieuChi']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>

                                <!-- Dropdown 4: Minh chứng -->
                                <div class="col-12 col-sm-6 col-md-4 col-xl">
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text bg-light text-muted" title="Lọc theo Minh chứng"><i class="bi bi-file-earmark-text"></i></span>
                                        <select class="form-select form-select-sm" id="filterEvidence" name="evidence">
                                            <option value="">-- Tất cả Minh chứng (<?= count($allEvidencesForFilter) ?>) --</option>
                                            <?php foreach ($allEvidencesForFilter as $ev): ?>
                                                <option value="<?= htmlspecialchars($ev['MaMinhChung']) ?>" 
                                                        data-criterion="<?= htmlspecialchars($ev['MaTieuChi']) ?>"
                                                        data-standard="<?= htmlspecialchars($ev['MaTieuChuan']) ?>"
                                                        data-set="<?= htmlspecialchars($ev['MaBoTieuChuan']) ?>"
                                                        <?= $selectedEvidence === (string)$ev['MaMinhChung'] ? 'selected' : '' ?> 
                                                        title="<?= htmlspecialchars($ev['TenMinhChung']) ?>">
                                                    <?= htmlspecialchars($ev['MaMinhChung'] . ' - ' . $ev['TenMinhChung']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>

                                <!-- Dropdown 5: Trạng thái -->
                                <div class="col-12 col-sm-6 col-md-4 col-xl">
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text bg-light text-muted" title="Lọc theo Trạng thái"><i class="bi bi-toggle-on"></i></span>
                                        <select class="form-select form-select-sm" id="filterStatus" name="status">
                                            <option value="">-- Tất cả Trạng thái --</option>
                                            <option value="1" <?= $selectedStatus === 1 ? 'selected' : '' ?>>Hoạt động</option>
                                            <option value="0" <?= $selectedStatus === 0 ? 'selected' : '' ?>>Ngừng hoạt động</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Nút Lọc kết quả -->
                                <div class="col-12 col-sm-6 col-md-4 col-xl-auto">
                                    <button type="submit" class="btn btn-primary btn-sm px-3 d-inline-flex align-items-center justify-content-center gap-1 shadow-sm w-100" style="height: 31px;" id="btnApplyFilter" title="Áp dụng lọc">
                                        <i class="bi bi-funnel-fill"></i> Lọc
                                    </button>
                                </div>
                            </div>
                            <div class="form-text small text-secondary mt-1">
                                <i class="bi bi-arrow-repeat text-info"></i> Các ô chọn tự động liên kết danh sách; chọn xong bấm nút <strong>Lọc</strong> để tải kết quả
                            </div>
                        </div>
                    </form>

                    <!-- DẢI CHIPS HIỂN THỊ CÁC TIÊU CHÍ ĐANG LỌC -->
                    <div id="activeFilterTags" class="d-flex flex-wrap align-items-center gap-2 mt-3 pt-3 border-top" style="<?= ($searchKeyword !== '' || $selectedStandardSet !== '' || $selectedStandard !== '' || $selectedCriterion !== '' || $selectedEvidence !== '' || $selectedStatus !== null) ? '' : 'display: none !important;' ?>">
                        <span class="text-secondary small fw-semibold"><i class="bi bi-tags"></i> Đang lọc theo:</span>
                        <div id="tagList" class="d-flex flex-wrap gap-2"></div>
                    </div>
                </div>
            </div>

            <!-- Main Master-Detail Table -->
            <div class="table-responsive border rounded-3 overflow-hidden">
                <table class="table standards-table align-middle mb-0" data-paginated="true" data-no-auto-paginate="true">
                    <thead>
                        <tr>
                            <th class="text-center text-nowrap" style="width: 70px;">STT</th>
                            <th class="text-nowrap" style="min-width: 280px;">Tên bộ tiêu chuẩn</th>
                            <th class="text-nowrap" style="min-width: 170px;">Số hiệu</th>
                            <th class="text-center text-nowrap" style="min-width: 140px;">Ngày ban hành</th>
                            <th class="text-center text-nowrap" style="min-width: 150px;">File dữ liệu</th>
                            <th class="text-center text-nowrap" style="min-width: 140px;">Trạng thái</th>
                            <th class="text-end text-nowrap" style="min-width: 120px;">Chi tiết</th>
                        </tr>
                    </thead>
                    <tbody id="standardSetsTableBody">
                    <?php if (empty($standardSetsList)): ?>
                        <tr>
                            <td colspan="7" class="text-center text-secondary py-5">
                                <i class="bi bi-inbox fs-2 d-block mb-2 text-muted"></i>
                                Không tìm thấy dữ liệu bộ tiêu chuẩn nào
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($standardSetsList as $index => $set): 
                            $setId = $set['MaBoTieuChuan'];
                            $standards = $allStandardsBySet[$setId] ?? [];
                            $isActive = (int)$set['TrangThai'] === 1;
                            $formattedDate = $set['NgayBanHanh'] ? date('d/m/Y', strtotime($set['NgayBanHanh'])) : '-';
                            $sttNumber = $index + 1 + $offset;
                        ?>
                            <!-- LEVEL 1: THÔNG TƯ / BỘ TIÊU CHUẨN ROW -->
                            <tr class="table-set-row <?= $isActive ? 'is-active-set' : '' ?>" id="set-row-<?= htmlspecialchars($setId) ?>">
                                <td class="text-center">
                                    <div class="d-inline-flex align-items-center gap-1">
                                        <button class="btn btn-sm btn-light tree-toggle-btn" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-set-<?= htmlspecialchars($setId) ?>" aria-expanded="false" title="Mở rộng / Thu gọn Tiêu chuẩn con">
                                            <i class="bi bi-chevron-right fs-6"></i>
                                        </button>
                                        <span class="fw-bold text-secondary"><?= $sttNumber ?></span>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-3">
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle py-2 px-2 fs-7 fw-bold text-nowrap"><?= htmlspecialchars($setId) ?></span>
                                        <div class="flex-grow-1">
                                            <a class="fw-bold text-decoration-none text-dark d-block mb-1" href="javascript:void(0)" data-bs-toggle="collapse" data-bs-target="#collapse-set-<?= htmlspecialchars($setId) ?>">
                                                <?= htmlspecialchars($set['TenBoTieuChuan']) ?>
                                            </a>
                                            <?php if ($set['MoTa']): ?>
                                                <small class="text-muted line-clamp-1 mb-1 d-block"><?= htmlspecialchars($set['MoTa']) ?></small>
                                            <?php endif; ?>
                                            <div class="d-flex flex-wrap gap-2 align-items-center">
                                                <span class="badge bg-secondary-subtle text-secondary small"><i class="bi bi-folder2 me-1"></i><?= count($standards) ?> Tiêu chuẩn</span>
                                                <span class="badge bg-secondary-subtle text-secondary small"><i class="bi bi-list-check me-1"></i><?= (int)$set['total_criteria'] ?> Tiêu chí</span>
                                                <span class="badge bg-secondary-subtle text-secondary small"><i class="bi bi-file-earmark-check me-1"></i><?= (int)$set['total_evidences'] ?> Minh chứng</span>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-info-subtle text-dark border border-info-subtle fw-semibold">
                                        <i class="bi bi-file-text me-1"></i><?= htmlspecialchars($set['ThongTu'] ?: 'Chưa nhập số hiệu') ?>
                                    </span>
                                </td>
                                <td class="text-center text-nowrap">
                                    <span class="small text-secondary"><?= htmlspecialchars($formattedDate) ?></span>
                                </td>
                                <td class="text-center">
                                    <?php if (!empty($set['TepTinPDF'])): ?>
                                        <div class="d-inline-flex gap-1">
                                            <a class="btn btn-sm btn-outline-danger" href="<?= base_url('user/view.php?standard_set=' . urlencode($setId)) ?>" target="_blank" title="Xem chi tiết file PDF ở tab mới">
                                                <i class="bi bi-file-earmark-pdf me-1"></i>Xem chi tiết
                                            </a>
                                            <a class="btn btn-sm btn-light text-secondary" href="<?= base_url(htmlspecialchars($set['TepTinPDF'])) ?>" target="_blank" download title="Tải file PDF">
                                                <i class="bi bi-download"></i>
                                            </a>
                                        </div>
                                    <?php else: ?>
                                        <span class="badge bg-light text-muted border">Chưa có file</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge <?= $isActive ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' ?> px-3 py-2 rounded-pill fw-semibold">
                                        <i class="bi bi-<?= $isActive ? 'check-circle-fill' : 'dash-circle' ?> me-1"></i>
                                        <?= $isActive ? 'Hoạt động' : 'Ngừng hoạt động' ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-info btn-view-set-detail" type="button"
                                        data-id="<?= htmlspecialchars($setId) ?>"
                                        data-name="<?= htmlspecialchars($set['TenBoTieuChuan']) ?>"
                                        data-thongtu="<?= htmlspecialchars($set['ThongTu']) ?>"
                                        data-date="<?= htmlspecialchars($set['NgayBanHanh']) ?>"
                                        data-date-formatted="<?= htmlspecialchars($formattedDate) ?>"
                                        data-desc="<?= htmlspecialchars($set['MoTa']) ?>"
                                        data-status="<?= $isActive ? '1' : '0' ?>"
                                        data-status-text="<?= $isActive ? 'Hoạt động' : 'Ngừng hoạt động' ?>"
                                        data-pdf="<?= htmlspecialchars($set['TepTinPDF'] ?? '') ?>"
                                        data-pdf-url="<?= !empty($set['TepTinPDF']) ? base_url(htmlspecialchars($set['TepTinPDF'])) : '' ?>"
                                        data-view-url="<?= base_url('user/view.php?standard_set=' . urlencode($setId)) ?>"
                                        data-standards-count="<?= count($standards) ?>"
                                        data-criteria-count="<?= (int)$set['total_criteria'] ?>"
                                        data-evidences-count="<?= (int)$set['total_evidences'] ?>"
                                        title="Xem chi tiết các trường dữ liệu và file">
                                        <i class="bi bi-eye me-1"></i>Xem
                                    </button>
                                </td>
                            </tr>

                            <!-- LEVEL 2 COLLAPSIBLE CONTAINER: CẤP TIÊU CHUẨN -->
                            <tr class="p-0 border-0">
                                <td colspan="7" class="p-0 border-0">
                                    <div class="collapse" id="collapse-set-<?= htmlspecialchars($setId) ?>">
                                        <div class="nested-container mx-3 my-2 shadow-sm">
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <div class="d-flex align-items-center gap-2">
                                                    <i class="bi bi-diagram-3-fill text-primary"></i>
                                                    <h6 class="mb-0 fw-bold text-dark">Quản lý Tiêu chuẩn</h6>
                                                    <span class="badge bg-primary-subtle text-primary"><?= count($standards) ?> tiêu chuẩn</span>
                                                </div>
                                            </div>

                                            <?php if (empty($standards)): ?>
                                                <div class="text-center py-3 text-muted bg-white rounded border border-dashed">
                                                    <small>Chưa có tiêu chuẩn nào trong bộ này.</small>
                                                </div>
                                            <?php else: ?>
                                                <div class="table-responsive bg-white rounded border">
                                                    <table class="table table-sm align-middle mb-0">
                                                        <thead class="table-light">
                                                            <tr>
                                                                <th style="width: 50px;" class="text-center">Sổ</th>
                                                                <th style="width: 120px;">Mã TC</th>
                                                                <th style="min-width: 250px;">Tên Tiêu chuẩn</th>
                                                                <th style="min-width: 200px;">Mô tả</th>
                                                                <th style="width: 120px;" class="text-center">Số tiêu chí</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php foreach ($standards as $tc): 
                                                                $tcId = $tc['MaTieuChuan'];
                                                                $criteria = $allCriteriaByStandard[$tcId] ?? [];
                                                            ?>
                                                                <tr class="nested-standard-row" id="standard-row-<?= htmlspecialchars($tcId) ?>">
                                                                    <td class="text-center">
                                                                        <button class="btn btn-xs btn-outline-secondary tree-toggle-btn" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-standard-<?= htmlspecialchars($tcId) ?>" aria-expanded="false" title="Mở rộng / Thu gọn Tiêu chí con">
                                                                            <i class="bi bi-chevron-right" style="font-size: 0.75rem;"></i>
                                                                        </button>
                                                                    </td>
                                                                    <td class="fw-bold text-primary"><?= htmlspecialchars($tcId) ?></td>
                                                                    <td class="fw-semibold">
                                                                        <a class="text-decoration-none text-dark" href="javascript:void(0)" data-bs-toggle="collapse" data-bs-target="#collapse-standard-<?= htmlspecialchars($tcId) ?>">
                                                                            <?= htmlspecialchars($tc['TenTieuChuan']) ?>
                                                                        </a>
                                                                    </td>
                                                                    <td class="small text-secondary"><?= htmlspecialchars($tc['MoTa'] ?: '-') ?></td>
                                                                    <td class="text-center">
                                                                        <span class="badge bg-secondary-subtle text-secondary"><?= count($criteria) ?> tiêu chí</span>
                                                                    </td>
                                                                </tr>

                                                                <!-- LEVEL 3 COLLAPSIBLE CONTAINER: CẤP TIÊU CHÍ -->
                                                                <tr class="p-0 border-0">
                                                                    <td colspan="5" class="p-0 border-0">
                                                                        <div class="collapse" id="collapse-standard-<?= htmlspecialchars($tcId) ?>">
                                                                            <div class="p-3 my-1 ms-4 bg-light rounded border">
                                                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                                                    <div class="d-flex align-items-center gap-2">
                                                                                        <i class="bi bi-list-task text-success"></i>
                                                                                        <strong class="small text-dark">Quản lý Tiêu chí</strong>
                                                                                        <span class="badge bg-success-subtle text-success small"><?= count($criteria) ?> tiêu chí</span>
                                                                                    </div>
                                                                                </div>

                                                                                <?php if (empty($criteria)): ?>
                                                                                    <div class="text-center py-2 text-muted small bg-white rounded border border-dashed">
                                                                                        Chưa có tiêu chí nào thuộc tiêu chuẩn này.
                                                                                    </div>
                                                                                <?php else: ?>
                                                                                    <div class="table-responsive bg-white rounded border">
                                                                                        <table class="table table-xs table-hover align-middle mb-0">
                                                                                            <thead class="table-secondary">
                                                                                                <tr>
                                                                                                    <th style="width: 45px;" class="text-center">Sổ</th>
                                                                                                    <th style="width: 110px;">Mã Tiêu chí</th>
                                                                                                    <th style="min-width: 220px;">Tên Tiêu chí</th>
                                                                                                    <th style="min-width: 220px;">Nội dung / Yêu cầu</th>
                                                                                                    <th style="width: 140px;" class="text-center">Minh chứng</th>
                                                                                                </tr>
                                                                                            </thead>
                                                                                            <tbody>
                                                                                                <?php foreach ($criteria as $tchi): 
                                                                                                    $tchiId = $tchi['MaTieuChi'];
                                                                                                    $evidences = $allEvidencesByCriterion[$tchiId] ?? [];
                                                                                                ?>
                                                                                                    <tr class="nested-criterion-row" id="criterion-row-<?= htmlspecialchars($tchiId) ?>">
                                                                                                        <td class="text-center">
                                                                                                            <button class="btn btn-xs btn-outline-secondary tree-toggle-btn" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-criterion-<?= htmlspecialchars($tchiId) ?>" aria-expanded="false" title="Mở rộng / Thu gọn Minh chứng con">
                                                                                                                <i class="bi bi-chevron-right" style="font-size: 0.75rem;"></i>
                                                                                                            </button>
                                                                                                        </td>
                                                                                                        <td class="fw-bold text-success"><?= htmlspecialchars($tchiId) ?></td>
                                                                                                        <td class="fw-semibold">
                                                                                                            <a class="text-decoration-none text-dark" href="javascript:void(0)" data-bs-toggle="collapse" data-bs-target="#collapse-criterion-<?= htmlspecialchars($tchiId) ?>">
                                                                                                                <?= htmlspecialchars($tchi['TenTieuChi']) ?>
                                                                                                            </a>
                                                                                                        </td>
                                                                                                        <td class="small text-secondary"><?= htmlspecialchars($tchi['NoiDung'] ?: '-') ?></td>
                                                                                                        <td class="text-center">
                                                                                                            <span class="badge <?= !empty($evidences) ? 'bg-primary' : 'bg-light text-muted border' ?>">
                                                                                                                <?= count($evidences) ?> minh chứng
                                                                                                            </span>
                                                                                                        </td>
                                                                                                    </tr>

                                                                                                    <!-- LEVEL 4 COLLAPSIBLE CONTAINER: CẤP MINH CHỨNG -->
                                                                                                    <tr class="p-0 border-0">
                                                                                                        <td colspan="5" class="p-0 border-0">
                                                                                                            <div class="collapse" id="collapse-criterion-<?= htmlspecialchars($tchiId) ?>">
                                                                                                                <div class="p-3 my-1 ms-4 bg-white rounded border shadow-sm">
                                                                                                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                                                                                                        <div class="d-flex align-items-center gap-2">
                                                                                                                            <i class="bi bi-files text-primary"></i>
                                                                                                                            <strong class="small text-dark">Quản lý Minh chứng</strong>
                                                                                                                            <span class="badge bg-primary-subtle text-primary small"><?= count($evidences) ?> minh chứng</span>
                                                                                                                        </div>
                                                                                                                    </div>

                                                                                                                    <?php if (empty($evidences)): ?>
                                                                                                                        <div class="text-center py-2 text-muted small bg-light rounded border border-dashed">
                                                                                                                            Chưa có minh chứng nào được gắn cho tiêu chí này.
                                                                                                                        </div>
                                                                                                                    <?php else: ?>
                                                                                                                        <div class="table-responsive bg-white rounded border">
                                                                                                                            <table class="table table-xs align-middle mb-0">
                                                                                                                                <thead class="table-light">
                                                                                                                                    <tr>
                                                                                                                                        <th style="width: 45px;" class="text-center">STT</th>
                                                                                                                                        <th style="width: 90px;">Mã MC</th>
                                                                                                                                        <th style="min-width: 200px;">Tên Minh chứng</th>
                                                                                                                                        <th style="width: 100px;" class="text-center">Năm học</th>
                                                                                                                                        <th style="width: 110px;" class="text-center">Ngày ban hành</th>
                                                                                                                                        <th style="width: 130px;">Tệp đính kèm</th>
                                                                                                                                        <th style="width: 95px;" class="text-center">Trạng thái</th>
                                                                                                                                        <th style="width: 80px;" class="text-end">Chi tiết</th>
                                                                                                                                    </tr>
                                                                                                                                </thead>
                                                                                                                                <tbody>
                                                                                                                                    <?php 
                                                                                                                                    $mcIdx = 1;
                                                                                                                                    foreach ($evidences as $mc): 
                                                                                                                                        $mcId = $mc['MaMinhChung'];
                                                                                                                                        $mcFile = $mc['TepTin'];
                                                                                                                                        $mcIsActive = (int)($mc['TrangThai'] ?? 1) === 1;
                                                                                                                                        $hasFile = !empty($mcFile) && file_exists(__DIR__ . '/../' . $mcFile);
                                                                                                                                        $fileExt = $hasFile ? strtolower(pathinfo($mcFile, PATHINFO_EXTENSION)) : '';
                                                                                                                                        $fileUrl = $hasFile ? base_url($mcFile) : '#';
                                                                                                                                        $downloadUrl = base_url('user/download.php?id=' . urlencode($mcId));
                                                                                                                                    ?>
                                                                                                                                        <tr class="nested-evidence-row" id="evidence-row-<?= htmlspecialchars($mcId) ?>">
                                                                                                                                            <td class="text-center text-muted small"><?= $mcIdx++ ?></td>
                                                                                                                                            <td class="fw-bold text-primary"><?= htmlspecialchars($mcId) ?></td>
                                                                                                                                            <td>
                                                                                                                                                <div class="fw-medium text-dark"><?= htmlspecialchars($mc['TenMinhChung']) ?></div>
                                                                                                                                                <?php if (!empty($mc['MoTa'])): ?>
                                                                                                                                                    <small class="text-muted line-clamp-1"><?= htmlspecialchars($mc['MoTa']) ?></small>
                                                                                                                                                <?php endif; ?>
                                                                                                                                            </td>
                                                                                                                                            <td class="text-center small"><?= htmlspecialchars($mc['NamHoc'] ?: '-') ?></td>
                                                                                                                                            <td class="text-center small"><?= !empty($mc['NgayBanHanh']) ? date('d/m/Y', strtotime($mc['NgayBanHanh'])) : '-' ?></td>
                                                                                                                                            <td>
                                                                                                                                                <?php if ($hasFile): ?>
                                                                                                                                                    <div class="d-inline-flex gap-1 align-items-center">
                                                                                                                                                        <?php if ($fileExt === 'pdf'): ?>
                                                                                                                            <a class="btn btn-xs btn-outline-danger" href="<?= base_url('user/view.php?id=' . urlencode($mcId)) ?>" target="_blank" title="Xem tệp PDF ở tab mới">
                                                                                                                                <i class="bi bi-file-earmark-pdf"></i> Xem
                                                                                                                            </a>
                                                                                                                        <?php else: ?>
                                                                                                                            <a class="btn btn-xs btn-outline-secondary" href="<?= base_url('user/view.php?id=' . urlencode($mcId)) ?>" target="_blank" title="Xem tệp ở tab mới">
                                                                                                                                <i class="bi bi-eye"></i> Xem
                                                                                                                            </a>
                                                                                                                        <?php endif; ?>
                                                                                                                                                        <a href="<?= htmlspecialchars($downloadUrl) ?>" class="btn btn-xs btn-outline-secondary" title="Tải tệp đính kèm">
                                                                                                                                                            <i class="bi bi-download"></i> Tải về
                                                                                                                                                        </a>
                                                                                                                                                        <small class="text-muted font-monospace" style="font-size: 0.7rem;">.<?= htmlspecialchars($fileExt) ?></small>
                                                                                                                                                    </div>
                                                                                                                                                <?php else: ?>
                                                                                                                                                    <span class="text-muted small">Không có file</span>
                                                                                                                                                <?php endif; ?>
                                                                                                                                            </td>
                                                                                                                                            <td class="text-center">
                                                                                                                                                <span class="badge <?= $mcIsActive ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' ?> small">
                                                                                                                                                    <?= $mcIsActive ? 'Hoạt động' : 'Tạm ẩn' ?>
                                                                                                                                                </span>
                                                                                                                                            </td>
                                                                                                                                            <td class="text-end">
                                                                                                                                                <button class="btn btn-xs btn-outline-info btn-view-evidence-detail" type="button"
                                                                                                                                                    data-id="<?= htmlspecialchars($mcId) ?>"
                                                                                                                                                    data-name="<?= htmlspecialchars($mc['TenMinhChung']) ?>"
                                                                                                                                                    data-raw-set-id="<?= htmlspecialchars($setId) ?>"
                                                                                                                                                    data-raw-standard-id="<?= htmlspecialchars($tcId) ?>"
                                                                                                                                                    data-raw-criterion-id="<?= htmlspecialchars($tchiId) ?>"
                                                                                                                                                    data-raw-evidence-id="<?= htmlspecialchars($mcId) ?>"
                                                                                                                                                    data-criterion="<?= htmlspecialchars($tchiId . ' - ' . $tchi['TenTieuChi']) ?>"
                                                                                                                                                    data-standard="<?= htmlspecialchars($tcId . ' - ' . $tc['TenTieuChuan']) ?>"
                                                                                                                                                    data-set="<?= htmlspecialchars($setId . ' - ' . $set['TenBoTieuChuan']) ?>"
                                                                                                                                                    data-date="<?= !empty($mc['NgayBanHanh']) ? date('d/m/Y', strtotime($mc['NgayBanHanh'])) : '-' ?>"
                                                                                                                                                    data-namhoc="<?= htmlspecialchars($mc['NamHoc'] ?: '-') ?>"
                                                                                                                                                    data-desc="<?= htmlspecialchars($mc['MoTa'] ?: '-') ?>"
                                                                                                                                                    data-status="<?= $mcIsActive ? 'Hoạt động' : 'Tạm ẩn' ?>"
                                                                                                                                                    data-creator="<?= htmlspecialchars($mc['NguoiTao'] ?: 'Hệ thống') ?>"
                                                                                                                                                    data-has-file="<?= $hasFile ? '1' : '0' ?>"
                                                                                                                                                    data-file-url="<?= htmlspecialchars($fileUrl) ?>"
                                                                                                                                                    data-download-url="<?= htmlspecialchars($downloadUrl) ?>"
                                                                                                                                                    data-file-name="<?= $hasFile ? htmlspecialchars(basename($mcFile)) : '' ?>"
                                                                                                                                                    data-file-ext="<?= htmlspecialchars($fileExt) ?>"
                                                                                                                                                    title="Xem chi tiết Minh chứng">
                                                                                                                                                    <i class="bi bi-eye"></i>
                                                                                                                                                </button>
                                                                                                                                            </td>
                                                                                                                                        </tr>
                                                                                                                                    <?php endforeach; ?>
                                                                                                                                </tbody>
                                                                                                                            </table>
                                                                                                                        </div>
                                                                                                                    <?php endif; ?>
                                                                                                                </div>
                                                                                                            </div>
                                                                                                        </td>
                                                                                                    </tr>
                                                                                                <?php endforeach; ?>
                                                                                            </tbody>
                                                                                        </table>
                                                                                    </div>
                                                                                <?php endif; ?>
                                                                            </div>
                                                                        </div>
                                                                    </td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Custom Modern Pagination Bar -->
            <?php if ($totalPages > 1): ?>
                <div class="modern-pagination-wrapper">
                    <div class="small text-secondary">
                        Hiển thị <strong><?= min($totalRecords, ($currentPage - 1) * $perPage + 1) ?> - <?= min($totalRecords, $currentPage * $perPage) ?></strong> trên tổng số <strong><?= $totalRecords ?></strong> bộ tiêu chuẩn
                    </div>
                    <nav aria-label="Page navigation">
                        <ul class="modern-pagination">
                            <!-- Prev Page Link -->
                            <li class="page-item <?= $currentPage <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= $currentPage > 1 ? base_url('user/search.php?' . http_build_query(array_merge($currentFilterParams, ['page' => $currentPage - 1]))) : '#' ?>" aria-label="Trang trước">
                                    <i class="bi bi-chevron-left"></i>
                                </a>
                            </li>

                            <?php 
                                $startPage = max(1, $currentPage - 2);
                                $endPage   = min($totalPages, $currentPage + 2);
                                if ($startPage > 1): 
                            ?>
                                <li class="page-item">
                                    <a class="page-link" href="<?= base_url('user/search.php?' . http_build_query(array_merge($currentFilterParams, ['page' => 1]))) ?>">1</a>
                                </li>
                                <?php if ($startPage > 2): ?>
                                    <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
                                <?php endif; ?>
                            <?php endif; ?>

                            <?php for ($p = $startPage; $p <= $endPage; $p++): ?>
                                <li class="page-item <?= $p === $currentPage ? 'active' : '' ?>">
                                    <a class="page-link" href="<?= base_url('user/search.php?' . http_build_query(array_merge($currentFilterParams, ['page' => $p]))) ?>"><?= $p ?></a>
                                </li>
                            <?php endfor; ?>

                            <?php if ($endPage < $totalPages): ?>
                                <?php if ($endPage < $totalPages - 1): ?>
                                    <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
                                <?php endif; ?>
                                <li class="page-item">
                                    <a class="page-link" href="<?= base_url('user/search.php?' . http_build_query(array_merge($currentFilterParams, ['page' => $totalPages]))) ?>"><?= $totalPages ?></a>
                                </li>
                            <?php endif; ?>

                            <!-- Next Page Link -->
                            <li class="page-item <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= $currentPage < $totalPages ? base_url('user/search.php?' . http_build_query(array_merge($currentFilterParams, ['page' => $currentPage + 1]))) : '#' ?>" aria-label="Trang tiếp">
                                    <i class="bi bi-chevron-right"></i>
                                </a>
                            </li>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<!-- ==========================================
     MODAL 1: XEM TRỰC TIẾP FILE PDF
=============================================== -->
<div class="modal fade" id="modalPdfViewer" tabindex="-1" aria-labelledby="modalPdfViewerLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered pdf-viewer-modal-dialog">
        <div class="modal-content h-100">
            <div class="modal-header py-2 bg-dark text-white">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-file-earmark-pdf fs-5 text-danger"></i>
                    <h6 class="modal-title mb-0 text-white line-clamp-1" id="modalPdfViewerLabel">Xem chi tiết PDF</h6>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <a href="#" id="btnPdfOpenNewTab" target="_blank" class="btn btn-sm btn-outline-light" title="Mở trong tab mới">
                        <i class="bi bi-box-arrow-up-right me-1"></i>Mở tab mới
                    </a>
                    <a href="#" id="btnPdfDownload" download class="btn btn-sm btn-outline-light" title="Tải xuống file PDF">
                        <i class="bi bi-download me-1"></i>Tải về
                    </a>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
            </div>
            <div class="modal-body p-0 bg-secondary-subtle">
                <iframe id="pdfViewerIframe" class="pdf-iframe-container" src="about:blank"></iframe>
            </div>
        </div>
    </div>
</div>

<!-- ==========================================
     MODAL 2: XEM CHI TIẾT BỘ TIÊU CHUẨN
=============================================== -->
<div class="modal fade" id="modalViewStandardSetDetails" tabindex="-1" aria-labelledby="modalViewStandardSetLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header text-white py-3 px-4" style="background: linear-gradient(135deg, #1e3a8a, #2563eb);">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-white bg-opacity-20 p-2 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                        <i class="bi bi-folder2-open fs-5 text-white"></i>
                    </div>
                    <div>
                        <h5 class="modal-title mb-0 text-white fw-bold" id="modalViewStandardSetLabel">Chi tiết Bộ Tiêu chuẩn</h5>
                        <small class="text-white text-opacity-75">Thông tin danh mục & tài liệu kiểm định chất lượng</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 bg-light">
                <!-- Hero Header Card -->
                <div class="card border-0 shadow-sm rounded-3 mb-3 bg-white">
                    <div class="card-body p-3 p-md-4">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3 pb-3 border-bottom">
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <span class="badge bg-primary px-3 py-2 fs-6 fw-bold shadow-sm" id="view_set_id_badge">BTC01</span>
                                <h5 class="mb-0 fw-bold text-dark fs-5" id="view_set_title">Tên bộ tiêu chuẩn</h5>
                            </div>
                            <span id="view_set_status_badge" class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 fs-7 fw-semibold">
                                <i class="bi bi-check-circle-fill me-1"></i>Hoạt động
                            </span>
                        </div>

                        <!-- 2-Column Info Grid -->
                        <div class="row g-3">
                            <div class="col-lg-6">
                                <div class="p-3 rounded-3 bg-light border h-100">
                                    <div class="small fw-bold text-muted text-uppercase mb-3"><i class="bi bi-card-checklist me-1 text-primary"></i>Căn cứ pháp lý & Thông tin</div>
                                    <div class="d-flex flex-column gap-2">
                                        <div class="d-flex justify-content-between align-items-center py-1 border-bottom border-light-subtle">
                                            <span class="text-muted small">Mã bộ:</span>
                                            <span class="fw-bold font-monospace text-primary" id="view_set_id"></span>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center py-1 border-bottom border-light-subtle">
                                            <span class="text-muted small">Số hiệu / Thông tư:</span>
                                            <span id="view_set_thongtu" class="badge bg-info-subtle text-dark border border-info-subtle fs-7 fw-semibold"></span>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center py-1 border-bottom border-light-subtle">
                                            <span class="text-muted small">Ngày ban hành:</span>
                                            <span class="fw-semibold text-dark" id="view_set_date"></span>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center py-1">
                                            <span class="text-muted small">Trạng thái:</span>
                                            <span id="view_set_status_text"></span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-6">
                                <div class="p-3 rounded-3 bg-light border h-100 d-flex flex-column justify-content-between">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <div class="small fw-bold text-muted text-uppercase"><i class="bi bi-diagram-3-fill me-1 text-primary"></i>Vị trí & Quy mô phân cấp</div>
                                        <span class="badge bg-primary text-white rounded-pill px-2 py-1" style="font-size: 0.72rem;">
                                            <i class="bi bi-layers me-1"></i>Cấp 1 / 4
                                        </span>
                                    </div>

                                    <!-- 4-Level Visual Pipeline / Hierarchy Flow -->
                                    <div class="p-2 bg-white rounded-3 border shadow-xs mb-2">
                                        <div class="d-flex align-items-center justify-content-between text-center gap-1">
                                            <!-- Cấp 1: Bộ Tiêu chuẩn (Active) -->
                                            <div class="flex-fill p-1 rounded-2 bg-primary-subtle border border-primary-subtle interactive-hierarchy-step" data-modal-type="set" data-jump-level="set" role="button" title="Bấm để nhảy tới vị trí Bộ tiêu chuẩn này trên bảng">
                                                <span class="badge bg-primary text-white rounded-pill px-2 py-0 mb-1" style="font-size: 0.65rem;">Cấp 1</span>
                                                <div class="fw-bold text-primary small text-truncate"><i class="bi bi-folder2-open me-1"></i>Bộ tiêu chuẩn</div>
                                                <div class="text-primary fw-semibold" style="font-size: 0.68rem;">(Đang xem)</div>
                                            </div>

                                            <div class="text-muted"><i class="bi bi-chevron-right text-secondary opacity-50"></i></div>

                                            <!-- Cấp 2: Tiêu chuẩn -->
                                            <div class="flex-fill p-1 rounded-2 bg-light border interactive-hierarchy-step" data-modal-type="set" data-jump-level="standard" role="button" title="Bấm để mở và trỏ tới bảng Tiêu chuẩn con trong danh sách">
                                                <span class="badge bg-secondary-subtle text-secondary rounded-pill px-2 py-0 mb-1" style="font-size: 0.65rem;">Cấp 2</span>
                                                <div class="fw-bold text-dark small text-truncate"><i class="bi bi-folder me-1 text-secondary"></i>Tiêu chuẩn</div>
                                                <div class="fw-bold text-primary fs-7"><span id="view_set_std_num">0</span> <small class="text-muted fw-normal" style="font-size: 0.7rem;">mục</small></div>
                                            </div>

                                            <div class="text-muted"><i class="bi bi-chevron-right text-secondary opacity-50"></i></div>

                                            <!-- Cấp 3: Tiêu chí -->
                                            <div class="flex-fill p-1 rounded-2 bg-light border interactive-hierarchy-step" data-modal-type="set" data-jump-level="criterion" role="button" title="Bấm để mở và trỏ tới bảng Tiêu chí con trong danh sách">
                                                <span class="badge bg-info-subtle text-info rounded-pill px-2 py-0 mb-1" style="font-size: 0.65rem;">Cấp 3</span>
                                                <div class="fw-bold text-dark small text-truncate"><i class="bi bi-list-check me-1 text-info"></i>Tiêu chí</div>
                                                <div class="fw-bold text-info fs-7"><span id="view_set_crit_num">0</span> <small class="text-muted fw-normal" style="font-size: 0.7rem;">mục</small></div>
                                            </div>

                                            <div class="text-muted"><i class="bi bi-chevron-right text-secondary opacity-50"></i></div>

                                            <!-- Cấp 4: Minh chứng -->
                                            <div class="flex-fill p-1 rounded-2 bg-light border interactive-hierarchy-step" data-modal-type="set" data-jump-level="evidence" role="button" title="Bấm để mở và trỏ tới bảng Minh chứng con trong danh sách">
                                                <span class="badge bg-success-subtle text-success rounded-pill px-2 py-0 mb-1" style="font-size: 0.65rem;">Cấp 4</span>
                                                <div class="fw-bold text-dark small text-truncate"><i class="bi bi-file-earmark-check me-1 text-success"></i>Minh chứng</div>
                                                <div class="fw-bold text-success fs-7"><span id="view_set_ev_num">0</span> <small class="text-muted fw-normal" style="font-size: 0.7rem;">mục</small></div>
                                            </div>
                                        </div>
                                        <div class="text-center mt-1">
                                            <small class="text-primary fw-medium" style="font-size: 0.72rem;"><i class="bi bi-cursor-fill me-1"></i>Bấm vào ô bất kỳ để nhảy ngay tới bảng dữ liệu đó</small>
                                        </div>
                                    </div>

                                    <!-- Chi tiết cây phân cấp (Tree Hierarchy Path) -->
                                    <div class="p-2 bg-white rounded-2 border">
                                        <div class="small fw-semibold text-muted text-uppercase mb-1" style="font-size: 0.7rem;">
                                            <i class="bi bi-diagram-2 me-1"></i>Sơ đồ phân cấp (Bấm để nhảy tới bảng):
                                        </div>
                                        <div class="font-monospace text-dark ps-1" style="font-size: 0.76rem; line-height: 1.45;">
                                            <div class="interactive-tree-item text-primary fw-bold text-truncate" data-modal-type="set" data-jump-level="set" role="button" title="Bấm để nhảy tới Bộ tiêu chuẩn">
                                                <i class="bi bi-box-seam me-1"></i>[1] Bộ TC: <span class="fw-normal text-secondary" id="view_set_tree_name">-</span>
                                            </div>
                                            <div class="interactive-tree-item text-secondary ps-3 text-truncate" data-modal-type="set" data-jump-level="standard" role="button" title="Bấm để mở và nhảy tới Tiêu chuẩn">
                                                <i class="bi bi-arrow-return-right me-1 text-muted"></i>[2] <span class="fw-bold text-primary" id="view_set_tree_std">0</span> Tiêu chuẩn trực thuộc &rarr;
                                            </div>
                                            <div class="interactive-tree-item text-secondary ps-4 ms-2 text-truncate" data-modal-type="set" data-jump-level="criterion" role="button" title="Bấm để mở và nhảy tới Tiêu chí">
                                                <i class="bi bi-arrow-return-right me-1 text-muted"></i>[3] <span class="fw-bold text-info" id="view_set_tree_crit">0</span> Tiêu chí trực thuộc &rarr;
                                            </div>
                                            <div class="interactive-tree-item text-success ps-5 ms-3 fw-bold text-truncate" data-modal-type="set" data-jump-level="evidence" role="button" title="Bấm để mở và nhảy tới Minh chứng">
                                                <i class="bi bi-arrow-return-right me-1 text-success"></i>[4] <span class="badge bg-success-subtle text-success px-2 py-0" id="view_set_tree_ev">0 minh chứng</span> &rarr;
                                            </div>
                                        </div>
                                    </div>
                                    <div class="d-none" id="view_set_standards_count"></div>
                                    <div class="d-none" id="view_set_criteria_count"></div>
                                    <div class="d-none" id="view_set_evidences_count"></div>
                                </div>
                            </div>

                            <!-- Mô tả trích yếu -->
                            <div class="col-12">
                                <div class="p-3 rounded-3 bg-light border">
                                    <div class="small fw-bold text-muted text-uppercase mb-2"><i class="bi bi-chat-left-quote me-1 text-primary"></i>Mô tả / Trích yếu nội dung</div>
                                    <div id="view_set_desc" class="text-secondary small" style="white-space: pre-wrap; line-height: 1.6;"></div>
                                </div>
                            </div>

                            <!-- Tệp đính kèm -->
                            <div class="col-12">
                                <div class="small fw-bold text-muted text-uppercase mb-2"><i class="bi bi-paperclip me-1 text-primary"></i>File dữ liệu đính kèm</div>
                                <div id="view_set_file_container">
                                    <!-- Populated via JS -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Preview Area -->
                <div id="view_set_preview_box" class="card border-0 shadow-sm rounded-3 bg-white p-3" style="display: none;">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 pb-2 border-bottom">
                        <div class="fw-bold text-dark"><i class="bi bi-eye-fill me-2 text-primary"></i>Xem trước tài liệu trực tiếp:</div>
                        <a href="#" id="view_set_preview_newtab_link" target="_blank" class="btn btn-sm btn-primary">
                            <i class="bi bi-box-arrow-up-right me-1"></i>Mở toàn màn hình ở tab mới
                        </a>
                    </div>
                    <div id="view_set_preview_content" class="text-center rounded bg-secondary-subtle overflow-hidden" style="min-height: 420px;">
                        <!-- Embed / Image / Message -->
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-white border-top py-2 px-4 d-flex justify-content-between">
                <div></div>
                <div class="d-inline-flex gap-2">
                    <a href="#" id="view_set_btn_open_tab_footer" target="_blank" class="btn btn-primary btn-sm" style="display: none;">
                        <i class="bi bi-box-arrow-up-right me-1"></i>Xem file dữ liệu (Tab mới)
                    </a>
                    <button type="button" class="btn btn-secondary btn-sm px-3" data-bs-dismiss="modal">Đóng</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ==========================================
     MODAL 3: XEM CHI TIẾT MINH CHỨNG
=============================================== -->
<div class="modal fade" id="modalViewEvidenceDetails" tabindex="-1" aria-labelledby="modalViewEvidenceLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header text-white py-3 px-4" style="background: linear-gradient(135deg, #1e3a8a, #2563eb);">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-white bg-opacity-20 p-2 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                        <i class="bi bi-file-earmark-text fs-5 text-white"></i>
                    </div>
                    <div>
                        <h5 class="modal-title mb-0 text-white fw-bold" id="modalViewEvidenceLabel">Chi tiết Minh chứng</h5>
                        <small class="text-white text-opacity-75">Hồ sơ tài liệu minh chứng kiểm định chất lượng</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 bg-light">
                <!-- Hero Header Card -->
                <div class="card border-0 shadow-sm rounded-3 mb-3 bg-white">
                    <div class="card-body p-3 p-md-4">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3 pb-3 border-bottom">
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <span class="badge bg-primary px-3 py-2 fs-6 fw-bold shadow-sm" id="view_ev_id_badge">MC01</span>
                                <h5 class="mb-0 fw-bold text-dark fs-5" id="view_ev_title">Tên minh chứng</h5>
                            </div>
                            <span id="view_ev_status_badge" class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 fs-7 fw-semibold">
                                <i class="bi bi-check-circle-fill me-1"></i>Hoạt động
                            </span>
                        </div>

                        <!-- 2-Column Info Grid -->
                        <div class="row g-3">
                            <div class="col-lg-6">
                                <div class="p-3 rounded-3 bg-light border h-100">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <div class="small fw-bold text-muted text-uppercase"><i class="bi bi-diagram-3-fill me-1 text-primary"></i>Phân cấp trực thuộc</div>
                                        <span class="badge bg-success text-white rounded-pill px-2 py-1" style="font-size: 0.72rem;">
                                            <i class="bi bi-layers me-1"></i>Cấp 4 / 4
                                        </span>
                                    </div>

                                    <!-- 4-Level Pipeline Stepper -->
                                    <div class="p-2 bg-white rounded-3 border shadow-xs mb-2">
                                        <div class="d-flex align-items-center justify-content-between text-center gap-1">
                                            <div class="flex-fill p-1 rounded-2 bg-light border interactive-hierarchy-step" data-modal-type="evidence" data-jump-level="set" role="button" title="Bấm để nhảy tới Bộ tiêu chuẩn chứa minh chứng này">
                                                <span class="badge bg-secondary-subtle text-secondary rounded-pill px-2 py-0 mb-1" style="font-size: 0.65rem;">Cấp 1</span>
                                                <div class="fw-bold text-dark small text-truncate"><i class="bi bi-folder2-open me-1 text-primary"></i>Bộ TC</div>
                                            </div>
                                            <div class="text-muted"><i class="bi bi-chevron-right text-secondary opacity-50"></i></div>
                                            <div class="flex-fill p-1 rounded-2 bg-light border interactive-hierarchy-step" data-modal-type="evidence" data-jump-level="standard" role="button" title="Bấm để nhảy tới Tiêu chuẩn chứa minh chứng này">
                                                <span class="badge bg-secondary-subtle text-secondary rounded-pill px-2 py-0 mb-1" style="font-size: 0.65rem;">Cấp 2</span>
                                                <div class="fw-bold text-dark small text-truncate"><i class="bi bi-folder me-1 text-secondary"></i>Tiêu chuẩn</div>
                                            </div>
                                            <div class="text-muted"><i class="bi bi-chevron-right text-secondary opacity-50"></i></div>
                                            <div class="flex-fill p-1 rounded-2 bg-light border interactive-hierarchy-step" data-modal-type="evidence" data-jump-level="criterion" role="button" title="Bấm để nhảy tới Tiêu chí chứa minh chứng này">
                                                <span class="badge bg-secondary-subtle text-secondary rounded-pill px-2 py-0 mb-1" style="font-size: 0.65rem;">Cấp 3</span>
                                                <div class="fw-bold text-dark small text-truncate"><i class="bi bi-list-check me-1 text-info"></i>Tiêu chí</div>
                                            </div>
                                            <div class="text-muted"><i class="bi bi-chevron-right text-success opacity-75"></i></div>
                                            <div class="flex-fill p-1 rounded-2 bg-success-subtle border border-success-subtle interactive-hierarchy-step" data-modal-type="evidence" data-jump-level="evidence" role="button" title="Bấm để nhảy tới dòng Minh chứng này trên bảng">
                                                <span class="badge bg-success text-white rounded-pill px-2 py-0 mb-1" style="font-size: 0.65rem;">Cấp 4</span>
                                                <div class="fw-bold text-success small text-truncate"><i class="bi bi-file-earmark-check me-1"></i>Minh chứng</div>
                                            </div>
                                        </div>
                                        <div class="text-center mt-1">
                                            <small class="text-primary fw-medium" style="font-size: 0.72rem;"><i class="bi bi-cursor-fill me-1"></i>Bấm vào ô bất kỳ để nhảy ngay tới bảng dữ liệu đó</small>
                                        </div>
                                    </div>

                                    <div class="d-flex flex-column gap-2">
                                        <div class="d-flex justify-content-between align-items-center py-1 border-bottom border-light-subtle">
                                            <span class="text-muted small">Mã minh chứng:</span>
                                            <span class="fw-bold font-monospace text-primary" id="view_ev_id"></span>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center py-1 border-bottom border-light-subtle">
                                            <span class="text-muted small">Bộ tiêu chuẩn:</span>
                                            <span class="fw-semibold text-primary interactive-link text-end" id="view_ev_set" data-modal-type="evidence" data-jump-level="set" role="button" title="Bấm để nhảy tới Bộ tiêu chuẩn này trên bảng"></span>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center py-1 border-bottom border-light-subtle">
                                            <span class="text-muted small">Tiêu chuẩn cha:</span>
                                            <span class="fw-semibold text-primary interactive-link text-end" id="view_ev_standard" data-modal-type="evidence" data-jump-level="standard" role="button" title="Bấm để nhảy tới Tiêu chuẩn này trên bảng"></span>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center py-1">
                                            <span class="text-muted small">Tiêu chí cha:</span>
                                            <span class="fw-semibold text-primary interactive-link text-end" id="view_ev_criterion" data-modal-type="evidence" data-jump-level="criterion" role="button" title="Bấm để nhảy tới Tiêu chí này trên bảng"></span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-6">
                                <div class="p-3 rounded-3 bg-light border h-100">
                                    <div class="small fw-bold text-muted text-uppercase mb-3"><i class="bi bi-info-circle me-1 text-primary"></i>Thông tin ban hành</div>
                                    <div class="d-flex flex-column gap-2">
                                        <div class="d-flex justify-content-between align-items-center py-1 border-bottom border-light-subtle">
                                            <span class="text-muted small">Năm học:</span>
                                            <span class="fw-semibold text-dark" id="view_ev_namhoc"></span>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center py-1 border-bottom border-light-subtle">
                                            <span class="text-muted small">Ngày ban hành:</span>
                                            <span class="fw-semibold text-dark" id="view_ev_date"></span>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center py-1">
                                            <span class="text-muted small">Người tạo / tải lên:</span>
                                            <span class="fw-semibold text-dark" id="view_ev_creator"></span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Mô tả trích yếu -->
                            <div class="col-12">
                                <div class="p-3 rounded-3 bg-light border">
                                    <div class="small fw-bold text-muted text-uppercase mb-2"><i class="bi bi-chat-left-quote me-1 text-primary"></i>Mô tả / Trích yếu nội dung</div>
                                    <div id="view_ev_desc" class="text-secondary small" style="white-space: pre-wrap; line-height: 1.6;"></div>
                                </div>
                            </div>

                            <!-- Tệp đính kèm -->
                            <div class="col-12">
                                <div class="small fw-bold text-muted text-uppercase mb-2"><i class="bi bi-paperclip me-1 text-primary"></i>Tệp đính kèm</div>
                                <div id="view_ev_file_container">
                                    <!-- Populated via JS -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-white border-top py-2 px-4 d-flex justify-content-end">
                <button type="button" class="btn btn-secondary btn-sm px-3" data-bs-dismiss="modal">Đóng</button>
            </div>
        </div>
    </div>
</div>

<script>
window.STANDARD_SETS_FILTER_DATA = <?= json_encode($allSetsForFilter, JSON_UNESCAPED_UNICODE) ?>;
window.STANDARDS_FILTER_DATA     = <?= json_encode($allStandardsForFilter, JSON_UNESCAPED_UNICODE) ?>;
window.CRITERIA_FILTER_DATA      = <?= json_encode($allCriteriaForFilter, JSON_UNESCAPED_UNICODE) ?>;
window.EVIDENCES_FILTER_DATA     = <?= json_encode($allEvidencesForFilter, JSON_UNESCAPED_UNICODE) ?>;

document.addEventListener('DOMContentLoaded', function () {
    // ==============================================
    // 0. BỘ LỌC & TÌM KIẾM TẬP TRUNG THÔNG MINH
    // ==============================================
    const filterForm        = document.getElementById('filterForm');
    const keywordInput      = document.getElementById('filterKeyword');
    const clearKeywordBtn   = document.getElementById('btnClearKeyword');
    const standardSetSelect = document.getElementById('filterStandardSet');
    const standardSelect    = document.getElementById('filterStandard');
    const criterionSelect   = document.getElementById('filterCriterion');
    const evidenceSelect    = document.getElementById('filterEvidence');
    const statusSelect      = document.getElementById('filterStatus');
    const btnResetAll       = document.getElementById('btnResetAll');
    const activeTagsBox     = document.getElementById('activeFilterTags');
    const tagList           = document.getElementById('tagList');

    const ALL_SETS_DATA      = window.STANDARD_SETS_FILTER_DATA || [];
    const ALL_STANDARDS_DATA = window.STANDARDS_FILTER_DATA || [];
    const ALL_CRITERIA_DATA  = window.CRITERIA_FILTER_DATA || [];
    const ALL_EVIDENCES_DATA = window.EVIDENCES_FILTER_DATA || [];

    function escapeHtml(text) {
        if (!text) return '';
        const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return text.toString().replace(/[&<>"']/g, m => map[m]);
    }

    // Cập nhật danh sách Tiêu chuẩn theo Bộ tiêu chuẩn đã chọn
    function updateStandardsDropdown(selectedSetId, currentStdVal = '') {
        if (!standardSelect) return;
        let filtered = ALL_STANDARDS_DATA;
        if (selectedSetId) {
            filtered = ALL_STANDARDS_DATA.filter(tc => tc.MaBoTieuChuan === selectedSetId);
        }

        let html = `<option value="">-- Tất cả Tiêu chuẩn (${filtered.length}) --</option>`;
        filtered.forEach(tc => {
            const isSel = (currentStdVal && currentStdVal === tc.MaTieuChuan) ? 'selected' : '';
            html += `<option value="${escapeHtml(tc.MaTieuChuan)}" data-set="${escapeHtml(tc.MaBoTieuChuan)}" ${isSel} title="${escapeHtml(tc.TenTieuChuan)}">${escapeHtml(tc.MaTieuChuan)} - ${escapeHtml(tc.TenTieuChuan)}</option>`;
        });
        standardSelect.innerHTML = html;
    }

    // Cập nhật danh sách Tiêu chí theo Bộ tiêu chuẩn & Tiêu chuẩn đã chọn
    function updateCriteriaDropdown(selectedSetId, selectedStdId, currentCriVal = '') {
        if (!criterionSelect) return;
        let filtered = ALL_CRITERIA_DATA;
        if (selectedStdId) {
            filtered = filtered.filter(cri => cri.MaTieuChuan === selectedStdId);
        } else if (selectedSetId) {
            filtered = filtered.filter(cri => cri.MaBoTieuChuan === selectedSetId);
        }

        let html = `<option value="">-- Tất cả Tiêu chí (${filtered.length}) --</option>`;
        filtered.forEach(cri => {
            const isSel = (currentCriVal && currentCriVal === cri.MaTieuChi) ? 'selected' : '';
            html += `<option value="${escapeHtml(cri.MaTieuChi)}" data-standard="${escapeHtml(cri.MaTieuChuan)}" data-set="${escapeHtml(cri.MaBoTieuChuan)}" ${isSel} title="${escapeHtml(cri.TenTieuChi)}">${escapeHtml(cri.MaTieuChi)} - ${escapeHtml(cri.TenTieuChi)}</option>`;
        });
        criterionSelect.innerHTML = html;
    }

    // Cập nhật danh sách Minh chứng theo Bộ tiêu chuẩn, Tiêu chuẩn & Tiêu chí đã chọn
    function updateEvidencesDropdown(selectedSetId, selectedStdId, selectedCriId, currentEvVal = '') {
        if (!evidenceSelect) return;
        let filtered = ALL_EVIDENCES_DATA;
        if (selectedCriId) {
            filtered = filtered.filter(ev => ev.MaTieuChi === selectedCriId);
        } else if (selectedStdId) {
            filtered = filtered.filter(ev => ev.MaTieuChuan === selectedStdId);
        } else if (selectedSetId) {
            filtered = filtered.filter(ev => ev.MaBoTieuChuan === selectedSetId);
        }

        let html = `<option value="">-- Tất cả Minh chứng (${filtered.length}) --</option>`;
        filtered.forEach(ev => {
            const isSel = (currentEvVal && currentEvVal === ev.MaMinhChung) ? 'selected' : '';
            html += `<option value="${escapeHtml(ev.MaMinhChung)}" data-criterion="${escapeHtml(ev.MaTieuChi)}" data-standard="${escapeHtml(ev.MaTieuChuan)}" data-set="${escapeHtml(ev.MaBoTieuChuan)}" ${isSel} title="${escapeHtml(ev.TenMinhChung)}">${escapeHtml(ev.MaMinhChung)} - ${escapeHtml(ev.TenMinhChung)}</option>`;
        });
        evidenceSelect.innerHTML = html;
    }

    // Hiển thị Chips lọc đang hoạt động
    function renderActiveFilterTags() {
        if (!activeTagsBox || !tagList) return;
        const kw = keywordInput ? keywordInput.value.trim() : '';
        const setVal = standardSetSelect ? standardSetSelect.value : '';
        const stdVal = standardSelect ? standardSelect.value : '';
        const criVal = criterionSelect ? criterionSelect.value : '';
        const evVal  = evidenceSelect ? evidenceSelect.value : '';
        const statusVal = statusSelect ? statusSelect.value : '';

        let tags = [];

        if (kw) {
            tags.push({
                type: 'keyword',
                label: `Từ khóa: "${kw}"`,
                onRemove: () => {
                    if (keywordInput) keywordInput.value = '';
                    if (clearKeywordBtn) clearKeywordBtn.style.display = 'none';
                    if (filterForm) filterForm.submit();
                }
            });
        }

        if (setVal) {
            const setObj = ALL_SETS_DATA.find(s => s.MaBoTieuChuan === setVal);
            const setLabel = setObj ? `${setObj.MaBoTieuChuan} - ${setObj.TenBoTieuChuan}` : setVal;
            tags.push({
                type: 'set',
                label: `Bộ: ${setLabel}`,
                onRemove: () => {
                    if (standardSetSelect) standardSetSelect.value = '';
                    updateStandardsDropdown('');
                    updateCriteriaDropdown('', '');
                    updateEvidencesDropdown('', '', '');
                    if (filterForm) filterForm.submit();
                }
            });
        }

        if (stdVal) {
            const stdObj = ALL_STANDARDS_DATA.find(tc => tc.MaTieuChuan === stdVal);
            const stdLabel = stdObj ? `${stdObj.MaTieuChuan} - ${stdObj.TenTieuChuan}` : stdVal;
            tags.push({
                type: 'standard',
                label: `Tiêu chuẩn: ${stdLabel}`,
                onRemove: () => {
                    if (standardSelect) standardSelect.value = '';
                    updateCriteriaDropdown(standardSetSelect ? standardSetSelect.value : '', '');
                    updateEvidencesDropdown(standardSetSelect ? standardSetSelect.value : '', '', '');
                    if (filterForm) filterForm.submit();
                }
            });
        }

        if (criVal) {
            const criObj = ALL_CRITERIA_DATA.find(cri => cri.MaTieuChi === criVal);
            const criLabel = criObj ? `${criObj.MaTieuChi} - ${criObj.TenTieuChi}` : criVal;
            tags.push({
                type: 'criterion',
                label: `Tiêu chí: ${criLabel}`,
                onRemove: () => {
                    if (criterionSelect) criterionSelect.value = '';
                    updateEvidencesDropdown(standardSetSelect ? standardSetSelect.value : '', standardSelect ? standardSelect.value : '', '');
                    if (filterForm) filterForm.submit();
                }
            });
        }

        if (evVal) {
            const evObj = ALL_EVIDENCES_DATA.find(ev => ev.MaMinhChung === evVal);
            const evLabel = evObj ? `${evObj.MaMinhChung} - ${evObj.TenMinhChung}` : evVal;
            tags.push({
                type: 'evidence',
                label: `Minh chứng: ${evLabel}`,
                onRemove: () => {
                    if (evidenceSelect) evidenceSelect.value = '';
                    if (filterForm) filterForm.submit();
                }
            });
        }

        if (statusVal !== '') {
            const statusLabel = statusVal === '1' ? 'Đang hoạt động' : 'Ngừng hoạt động';
            tags.push({
                type: 'status',
                label: `Trạng thái: ${statusLabel}`,
                onRemove: () => {
                    if (statusSelect) statusSelect.value = '';
                    if (filterForm) filterForm.submit();
                }
            });
        }

        if (tags.length === 0) {
            activeTagsBox.style.setProperty('display', 'none', 'important');
            tagList.innerHTML = '';
        } else {
            activeTagsBox.style.setProperty('display', 'flex', 'important');
            tagList.innerHTML = tags.map((t, idx) => `
                <span class="badge bg-primary text-white rounded-pill px-3 py-2 d-inline-flex align-items-center gap-1 shadow-xs">
                    <span class="text-truncate" style="max-width: 250px;">${escapeHtml(t.label)}</span>
                    <i class="bi bi-x-circle-fill ms-1" style="cursor: pointer;" role="button" data-tag-idx="${idx}" title="Xóa bộ lọc này"></i>
                </span>
            `).join('');

            tagList.querySelectorAll('[data-tag-idx]').forEach(btn => {
                btn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    const idx = parseInt(this.getAttribute('data-tag-idx'));
                    if (tags[idx] && tags[idx].onRemove) {
                        tags[idx].onRemove();
                    }
                });
            });
        }
    }

    // Khởi tạo các dropdown liên kết theo tham số URL ban đầu
    const initSetVal = standardSetSelect ? standardSetSelect.value : '';
    const initStdVal = standardSelect ? standardSelect.value : '';
    const initCriVal = criterionSelect ? criterionSelect.value : '';
    const initEvVal  = evidenceSelect ? evidenceSelect.value : '';

    if (initSetVal) {
        updateStandardsDropdown(initSetVal, initStdVal);
    }
    if (initSetVal || initStdVal) {
        updateCriteriaDropdown(initSetVal, initStdVal, initCriVal);
    }
    if (initSetVal || initStdVal || initCriVal) {
        updateEvidencesDropdown(initSetVal, initStdVal, initCriVal, initEvVal);
    }

    renderActiveFilterTags();

    // Tìm kiếm từ khóa: hiển thị nút xóa khi có nội dung
    if (keywordInput) {
        keywordInput.addEventListener('input', function () {
            const hasVal = this.value.trim() !== '';
            if (clearKeywordBtn) clearKeywordBtn.style.display = hasVal ? 'block' : 'none';
        });
    }

    if (clearKeywordBtn) {
        clearKeywordBtn.addEventListener('click', function () {
            if (keywordInput) keywordInput.value = '';
            this.style.display = 'none';
            if (filterForm) filterForm.submit();
        });
    }

    // Khi chọn Bộ tiêu chuẩn -> Cập nhật danh sách Tiêu chuẩn, Tiêu chí, Minh chứng
    if (standardSetSelect) {
        standardSetSelect.addEventListener('change', function () {
            const setVal = this.value;
            updateStandardsDropdown(setVal, '');
            updateCriteriaDropdown(setVal, '', '');
            updateEvidencesDropdown(setVal, '', '', '');
        });
    }

    // Khi chọn Tiêu chuẩn -> Tự động sync Bộ tiêu chuẩn nếu cần, cập nhật Tiêu chí & Minh chứng
    if (standardSelect) {
        standardSelect.addEventListener('change', function () {
            const stdVal = this.value;
            if (stdVal) {
                const stdObj = ALL_STANDARDS_DATA.find(tc => tc.MaTieuChuan === stdVal);
                if (stdObj && stdObj.MaBoTieuChuan && standardSetSelect && standardSetSelect.value !== stdObj.MaBoTieuChuan) {
                    standardSetSelect.value = stdObj.MaBoTieuChuan;
                }
            }
            const currentSetVal = standardSetSelect ? standardSetSelect.value : '';
            updateCriteriaDropdown(currentSetVal, stdVal, '');
            updateEvidencesDropdown(currentSetVal, stdVal, '', '');
        });
    }

    // Khi chọn Tiêu chí -> Tự động sync Bộ tiêu chuẩn & Tiêu chuẩn nếu cần, cập nhật Minh chứng
    if (criterionSelect) {
        criterionSelect.addEventListener('change', function () {
            const criVal = this.value;
            if (criVal) {
                const criObj = ALL_CRITERIA_DATA.find(cri => cri.MaTieuChi === criVal);
                if (criObj) {
                    if (criObj.MaBoTieuChuan && standardSetSelect && standardSetSelect.value !== criObj.MaBoTieuChuan) {
                        standardSetSelect.value = criObj.MaBoTieuChuan;
                    }
                    if (criObj.MaTieuChuan && standardSelect && standardSelect.value !== criObj.MaTieuChuan) {
                        standardSelect.value = criObj.MaTieuChuan;
                    }
                }
            }
            const currentSetVal = standardSetSelect ? standardSetSelect.value : '';
            const currentStdVal = standardSelect ? standardSelect.value : '';
            updateEvidencesDropdown(currentSetVal, currentStdVal, criVal, '');
        });
    }

    // Khi chọn Minh chứng -> Tự động sync Bộ tiêu chuẩn, Tiêu chuẩn & Tiêu chí nếu cần
    if (evidenceSelect) {
        evidenceSelect.addEventListener('change', function () {
            const evVal = this.value;
            if (evVal) {
                const evObj = ALL_EVIDENCES_DATA.find(ev => ev.MaMinhChung === evVal);
                if (evObj) {
                    if (evObj.MaBoTieuChuan && standardSetSelect && standardSetSelect.value !== evObj.MaBoTieuChuan) {
                        standardSetSelect.value = evObj.MaBoTieuChuan;
                    }
                    if (evObj.MaTieuChuan && standardSelect && standardSelect.value !== evObj.MaTieuChuan) {
                        standardSelect.value = evObj.MaTieuChuan;
                    }
                    if (evObj.MaTieuChi && criterionSelect && criterionSelect.value !== evObj.MaTieuChi) {
                        criterionSelect.value = evObj.MaTieuChi;
                    }
                }
            }
        });
    }

    // Đặt lại toàn bộ bộ lọc
    if (btnResetAll) {
        btnResetAll.addEventListener('click', function () {
            window.location.href = '<?= base_url('user/search.php') ?>';
        });
    }

    // 1. Setup Tree Toggle Icons & Collapsible Rows
    document.querySelectorAll('.collapse').forEach(collapseEl => {
        const id = collapseEl.id;
        if (!id) return;
        const triggerButtons = document.querySelectorAll('[data-bs-target="#' + id + '"]');

        collapseEl.addEventListener('show.bs.collapse', function (e) {
            if (e.target !== collapseEl) return;
            triggerButtons.forEach(btn => {
                btn.classList.add('is-open', 'expanded');
                btn.setAttribute('aria-expanded', 'true');
            });
        });

        collapseEl.addEventListener('hide.bs.collapse', function (e) {
            if (e.target !== collapseEl) return;
            triggerButtons.forEach(btn => {
                btn.classList.remove('is-open', 'expanded');
                btn.setAttribute('aria-expanded', 'false');
            });
        });
    });

    // Helper safe setters
    const setSafeText = (id, text) => {
        const el = document.getElementById(id);
        if (el) el.textContent = text !== undefined && text !== null ? text : '';
    };
    const setSafeHtml = (id, html) => {
        const el = document.getElementById(id);
        if (el) el.innerHTML = html !== undefined && html !== null ? html : '';
    };

    // 2. Setup PDF Viewer Modal
    const modalPdfEl = document.getElementById('modalPdfViewer');
    const getModalPdfInstance = () => modalPdfEl ? bootstrap.Modal.getOrCreateInstance(modalPdfEl) : null;
    const pdfIframe = document.getElementById('pdfViewerIframe');
    const pdfTitle = document.getElementById('modalPdfViewerLabel');
    const btnOpenTab = document.getElementById('btnPdfOpenNewTab');
    const btnDownload = document.getElementById('btnPdfDownload');

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.btn-view-pdf');
        if (!btn) return;
        e.stopPropagation();
        e.preventDefault();

        const pdfUrl = btn.dataset.pdfUrl;
        const title = btn.dataset.pdfTitle || 'Xem chi tiết tệp PDF';

        if (!pdfUrl) return;

        if (pdfTitle) pdfTitle.textContent = title;
        if (btnOpenTab) btnOpenTab.href = pdfUrl;
        if (btnDownload) btnDownload.href = pdfUrl;

        if (pdfIframe) {
            pdfIframe.src = pdfUrl;
        }

        const modalInstance = getModalPdfInstance();
        if (modalInstance) {
            modalInstance.show();
        }
    });

    if (modalPdfEl) {
        modalPdfEl.addEventListener('hidden.bs.modal', function () {
            if (pdfIframe) pdfIframe.src = 'about:blank';
        });
    }

    // 3. Setup View Standard Set Detail Modal
    const modalViewEl = document.getElementById('modalViewStandardSetDetails');
    const getModalViewSetInstance = () => modalViewEl ? bootstrap.Modal.getOrCreateInstance(modalViewEl) : null;
    let currentViewedSetId = '';

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.btn-view-set-detail');
        if (!btn) return;
        e.stopPropagation();
        e.preventDefault();

        const id = btn.dataset.id || '';
        currentViewedSetId = id;
        const name = btn.dataset.name || '';
        const thongtu = btn.dataset.thongtu || '';
        const dateFormatted = btn.dataset.dateFormatted || '-';
        const desc = btn.dataset.desc || 'Không có mô tả / ghi chú bổ sung.';
        const status = btn.dataset.status || '1';
        const pdf = btn.dataset.pdf || '';
        const pdfUrl = btn.dataset.pdfUrl || '';
        const stdCount = btn.dataset.standardsCount || '0';
        const critCount = btn.dataset.criteriaCount || '0';
        const evCount = btn.dataset.evidencesCount || '0';

        setSafeText('view_set_id_badge', id);
        setSafeText('view_set_id', id);
        setSafeText('view_set_title', name);
        setSafeText('view_set_thongtu', thongtu || 'Chưa nhập số hiệu');
        setSafeText('view_set_date', dateFormatted);

        const isAct = status === '1';
        const statusBadge = document.getElementById('view_set_status_badge');
        if (statusBadge) {
            statusBadge.className = isAct ? 'badge bg-success-subtle text-success border border-success-subtle px-3 py-2 fs-7 fw-semibold' : 'badge bg-secondary-subtle text-secondary border border-secondary-subtle px-3 py-2 fs-7 fw-semibold';
            statusBadge.innerHTML = isAct ? '<i class="bi bi-check-circle-fill me-1"></i>Hoạt động' : '<i class="bi bi-pause-circle-fill me-1"></i>Ngừng hoạt động';
        }
        setSafeHtml('view_set_status_text', isAct ? '<span class="text-success fw-bold"><i class="bi bi-check2-circle me-1"></i>Đang hoạt động (Hiển thị)</span>' : '<span class="text-muted"><i class="bi bi-dash-circle me-1"></i>Ngừng hoạt động</span>');

        setSafeText('view_set_std_num', stdCount);
        setSafeText('view_set_crit_num', critCount);
        setSafeText('view_set_ev_num', evCount);
        setSafeText('view_set_tree_name', name ? `${id} - ${name}` : id);
        setSafeText('view_set_tree_std', stdCount);
        setSafeText('view_set_tree_crit', critCount);
        setSafeText('view_set_tree_ev', `${evCount} minh chứng`);
        setSafeHtml('view_set_standards_count', `<i class="bi bi-folder2 me-1"></i>${stdCount} Tiêu chuẩn`);
        setSafeHtml('view_set_criteria_count', `<i class="bi bi-list-check me-1"></i>${critCount} Tiêu chí`);
        setSafeHtml('view_set_evidences_count', `<i class="bi bi-file-earmark-check me-1"></i>${evCount} Minh chứng`);

        setSafeText('view_set_desc', desc);

        const fileContainer = document.getElementById('view_set_file_container');
        const previewBox = document.getElementById('view_set_preview_box');
        const previewContent = document.getElementById('view_set_preview_content');
        const previewNewtabLink = document.getElementById('view_set_preview_newtab_link');
        const footerOpenTab = document.getElementById('view_set_btn_open_tab_footer');

        if (pdf && pdfUrl) {
            const ext = pdf.split('.').pop().toLowerCase();
            const isPdf = ext === 'pdf';
            const isImage = ['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext);

            if (fileContainer) {
                fileContainer.innerHTML = `
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 p-2 bg-light rounded border">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-${isPdf ? 'file-earmark-pdf-fill text-danger' : (isImage ? 'file-earmark-image-fill text-success' : 'file-earmark-text-fill text-primary')} fs-4"></i>
                            <div>
                                <span class="fw-semibold text-dark text-break">${pdf.split('/').pop()}</span>
                                <div class="small text-muted font-monospace">${pdf}</div>
                            </div>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="${escapeHtml(pdfUrl)}" target="_blank" class="btn btn-sm btn-primary">
                                <i class="bi bi-box-arrow-up-right me-1"></i>Mở file
                            </a>
                            <a href="${escapeHtml(pdfUrl)}" download class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-download me-1"></i>Tải về
                            </a>
                        </div>
                    </div>
                `;
            }

            if (footerOpenTab) {
                footerOpenTab.href = pdfUrl;
                footerOpenTab.style.display = 'inline-block';
            }

            if (isPdf) {
                if (previewBox) previewBox.style.display = 'block';
                if (previewNewtabLink) previewNewtabLink.href = pdfUrl;
                if (previewContent) previewContent.innerHTML = `<iframe src="${escapeHtml(pdfUrl)}" style="width: 100%; height: 400px; border: none; border-radius: 8px;"></iframe>`;
            } else if (isImage) {
                if (previewBox) previewBox.style.display = 'block';
                if (previewNewtabLink) previewNewtabLink.href = pdfUrl;
                if (previewContent) previewContent.innerHTML = `<img src="${escapeHtml(pdfUrl)}" alt="Preview" class="img-fluid rounded shadow-sm" style="max-height: 400px;">`;
            } else {
                if (previewBox) previewBox.style.display = 'none';
                if (previewContent) previewContent.innerHTML = '';
            }
        } else {
            if (fileContainer) {
                fileContainer.innerHTML = `<span class="badge bg-light text-muted border p-2"><i class="bi bi-info-circle me-1"></i>Chưa đính kèm file dữ liệu nào cho bộ tiêu chuẩn này.</span>`;
            }
            if (previewBox) previewBox.style.display = 'none';
            if (previewContent) previewContent.innerHTML = '';
            if (footerOpenTab) footerOpenTab.style.display = 'none';
        }

        const modalInstance = getModalViewSetInstance();
        if (modalInstance) {
            modalInstance.show();
        }
    });

    // 4. Setup View Evidence Detail Modal
    const modalEvViewEl = document.getElementById('modalViewEvidenceDetails');
    const getModalEvViewInstance = () => modalEvViewEl ? bootstrap.Modal.getOrCreateInstance(modalEvViewEl) : null;
    let currentEvidenceContext = { setId: '', standardId: '', criterionId: '', evidenceId: '' };

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.btn-view-evidence-detail');
        if (!btn) return;
        e.stopPropagation();
        e.preventDefault();

        const id = btn.dataset.id || '';
        const name = btn.dataset.name || '';
        const rawSetId = btn.dataset.rawSetId || '';
        const rawStandardId = btn.dataset.rawStandardId || '';
        const rawCriterionId = btn.dataset.rawCriterionId || '';
        const rawEvidenceId = btn.dataset.rawEvidenceId || id;

        currentEvidenceContext = {
            setId: rawSetId,
            standardId: rawStandardId,
            criterionId: rawCriterionId,
            evidenceId: rawEvidenceId
        };

        const set = btn.dataset.set || '-';
        const standard = btn.dataset.standard || '-';
        const criterion = btn.dataset.criterion || '-';
        const namhoc = btn.dataset.namhoc || '-';
        const date = btn.dataset.date || '-';
        const desc = btn.dataset.desc || '-';
        const status = btn.dataset.status || 'Hoạt động';
        const creator = btn.dataset.creator || 'Hệ thống';
        const hasFile = btn.dataset.hasFile === '1';
        const fileUrl = btn.dataset.fileUrl || '#';
        const downloadUrl = btn.dataset.downloadUrl || '#';
        const fileName = btn.dataset.fileName || '';
        const fileExt = btn.dataset.fileExt || '';
        const viewUrl = btn.dataset.viewUrl || (hasFile ? `<?= base_url('user/view.php?id=') ?>${encodeURIComponent(id)}` : '#');

        setSafeText('view_ev_id_badge', id);
        setSafeText('view_ev_id', id);
        setSafeText('view_ev_title', name);
        setSafeText('view_ev_set', set);
        setSafeText('view_ev_standard', standard);
        setSafeText('view_ev_criterion', criterion);
        setSafeText('view_ev_namhoc', namhoc);
        setSafeText('view_ev_date', date);
        setSafeText('view_ev_creator', creator);
        setSafeText('view_ev_desc', desc);

        const isAct = status === 'Hoạt động';
        const statusBadge = document.getElementById('view_ev_status_badge');
        if (statusBadge) {
            statusBadge.className = isAct ? 'badge bg-success-subtle text-success border border-success-subtle px-3 py-2 fs-7 fw-semibold' : 'badge bg-secondary-subtle text-secondary border border-secondary-subtle px-3 py-2 fs-7 fw-semibold';
            statusBadge.innerHTML = isAct ? '<i class="bi bi-check-circle-fill me-1"></i>Hoạt động' : '<i class="bi bi-pause-circle-fill me-1"></i>Tạm ẩn';
        }

        const fileContainer = document.getElementById('view_ev_file_container');
        if (fileContainer) {
            if (hasFile) {
                const isPdf = fileExt === 'pdf';
                fileContainer.innerHTML = `
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 p-2 bg-light rounded border">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-${isPdf ? 'file-earmark-pdf-fill text-danger' : 'file-earmark-text-fill text-primary'} fs-4"></i>
                            <div>
                                <span class="fw-semibold text-dark text-break">${escapeHtml(fileName)}</span>
                                <div class="small text-muted font-monospace">.${escapeHtml(fileExt)}</div>
                            </div>
                        </div>
                        <div class="d-flex gap-2">
                            ${isPdf ? `<a href="${escapeHtml(viewUrl)}" target="_blank" class="btn btn-sm btn-outline-danger" title="Xem file PDF ở tab mới"><i class="bi bi-eye me-1"></i>Xem PDF</a>` : `<a href="${escapeHtml(viewUrl)}" target="_blank" class="btn btn-sm btn-outline-secondary" title="Mở file ở tab mới"><i class="bi bi-box-arrow-up-right me-1"></i>Xem file</a>`}
                            <a href="${escapeHtml(downloadUrl)}" class="btn btn-sm btn-primary">
                                <i class="bi bi-download me-1"></i>Tải về
                            </a>
                        </div>
                    </div>
                `;
            } else {
                fileContainer.innerHTML = `<span class="badge bg-light text-muted border p-2"><i class="bi bi-info-circle me-1"></i>Chưa có tệp đính kèm nào cho minh chứng này.</span>`;
            }
        }

        const modalInstance = getModalEvViewInstance();
        if (modalInstance) {
            modalInstance.show();
        }
    });

    /**
     * Kích hoạt hiệu ứng phát quang đặc biệt & gắn thẻ định vị nổi (Beacon) tại dòng mục tiêu
     */
    function triggerHighlightTarget(targetRow, label) {
        if (!targetRow) return;

        // Cuộn màn hình mượt mà đưa đối tượng vào giữa khung nhìn
        targetRow.scrollIntoView({ behavior: 'smooth', block: 'center' });

        // Kích hoạt hiệu ứng phát sáng & viền xung quanh
        targetRow.classList.remove('highlight-target-row');
        void targetRow.offsetWidth; // Buộc reflow để kích hoạt lại animation
        targetRow.classList.add('highlight-target-row');

        // Gắn thẻ chỉ đường định vị nổi (Floating Locator Beacon)
        const oldBeacon = targetRow.querySelector('.jump-target-beacon');
        if (oldBeacon) oldBeacon.remove();

        const beacon = document.createElement('div');
        beacon.className = 'jump-target-beacon';
        beacon.innerHTML = `<i class="bi bi-geo-alt-fill text-warning"></i><span>Vị trí bạn chọn: <strong>${escapeHtml(label)}</strong></span>`;

        // Đảm bảo phần tử có position tương đối
        if (getComputedStyle(targetRow).position === 'static') {
            targetRow.style.position = 'relative';
        }
        targetRow.appendChild(beacon);

        // Tự động dọn dẹp thẻ beacon sau khi animation kết thúc (3.6 giây)
        setTimeout(() => {
            if (beacon && beacon.parentNode) {
                beacon.remove();
            }
        }, 3600);
    }

    /**
     * Chức năng: Điều hướng & Nhảy trực tiếp đến vị trí dòng tương ứng trên bảng dữ liệu chính
     * --------------------------------------------------------------------------------------
     * @param {string} level - Cấp độ cần nhảy tới: 'set' (Bộ TC), 'standard' (Tiêu chuẩn), 'criterion' (Tiêu chí), 'evidence' (Minh chứng)
     * @param {object} context - Ngữ cảnh chứa các mã ID cha/con và instance Modal hiện tại để đóng lại
     */
    function jumpToHierarchy(level, context) {
        // Đóng modal đang hiển thị
        if (context && context.modalInstance) {
            context.modalInstance.hide();
        }

        const setId = context ? context.setId : '';
        const stdId = context ? context.standardId : '';
        const criId = context ? context.criterionId : '';
        const evId = context ? context.evidenceId : '';

        if (!setId) return;

        // CẤP 1: BỘ TIÊU CHUẨN
        if (level === 'set') {
            const row = document.getElementById('set-row-' + setId);
            if (row) {
                triggerHighlightTarget(row, 'Bộ tiêu chuẩn (Cấp 1)');
            }
            return;
        }

        // CẤP 2: TIÊU CHUẨN (Mở accordion Bộ tiêu chuẩn, cuộn tới dòng Tiêu chuẩn)
        if (level === 'standard') {
            const setCollapse = document.getElementById('collapse-set-' + setId);
            if (setCollapse) {
                bootstrap.Collapse.getOrCreateInstance(setCollapse, { toggle: false }).show();
            }
            setTimeout(() => {
                let targetRow = stdId ? document.getElementById('standard-row-' + stdId) : null;
                if (!targetRow && setCollapse) {
                    targetRow = setCollapse.querySelector('.nested-standard-row') || setCollapse;
                }
                if (targetRow) {
                    triggerHighlightTarget(targetRow, 'Tiêu chuẩn (Cấp 2)');
                }
            }, 250);
            return;
        }

        // CẤP 3: TIÊU CHÍ (Mở accordion Bộ TC -> Tiêu chuẩn, cuộn tới dòng Tiêu chí)
        if (level === 'criterion') {
            const setCollapse = document.getElementById('collapse-set-' + setId);
            if (setCollapse) {
                bootstrap.Collapse.getOrCreateInstance(setCollapse, { toggle: false }).show();
            }
            setTimeout(() => {
                let stdCollapse = stdId ? document.getElementById('collapse-standard-' + stdId) : null;
                if (!stdCollapse && setCollapse) {
                    stdCollapse = setCollapse.querySelector('.collapse[id^="collapse-standard-"]');
                }
                if (stdCollapse) {
                    bootstrap.Collapse.getOrCreateInstance(stdCollapse, { toggle: false }).show();
                }
                setTimeout(() => {
                    let targetRow = criId ? document.getElementById('criterion-row-' + criId) : null;
                    if (!targetRow && setCollapse) {
                        targetRow = setCollapse.querySelector('.nested-criterion-row');
                    }
                    if (targetRow) {
                        triggerHighlightTarget(targetRow, 'Tiêu chí (Cấp 3)');
                    }
                }, 220);
            }, 250);
            return;
        }

        // CẤP 4: MINH CHỨNG (Mở accordion Bộ TC -> Tiêu chuẩn -> Tiêu chí, cuộn tới dòng Minh chứng)
        if (level === 'evidence') {
            const setCollapse = document.getElementById('collapse-set-' + setId);
            if (setCollapse) {
                bootstrap.Collapse.getOrCreateInstance(setCollapse, { toggle: false }).show();
            }
            setTimeout(() => {
                let stdCollapse = stdId ? document.getElementById('collapse-standard-' + stdId) : null;
                if (!stdCollapse && setCollapse) {
                    stdCollapse = setCollapse.querySelector('.collapse[id^="collapse-standard-"]');
                }
                if (stdCollapse) {
                    bootstrap.Collapse.getOrCreateInstance(stdCollapse, { toggle: false }).show();
                }
                setTimeout(() => {
                    let criCollapse = criId ? document.getElementById('collapse-criterion-' + criId) : null;
                    if (!criCollapse && setCollapse) {
                        criCollapse = setCollapse.querySelector('.collapse[id^="collapse-criterion-"]');
                    }
                    if (criCollapse) {
                        bootstrap.Collapse.getOrCreateInstance(criCollapse, { toggle: false }).show();
                    }
                    setTimeout(() => {
                        let targetRow = evId ? document.getElementById('evidence-row-' + evId) : null;
                        if (!targetRow && setCollapse) {
                            targetRow = setCollapse.querySelector('.nested-evidence-row');
                        }
                        if (targetRow) {
                            triggerHighlightTarget(targetRow, 'Minh chứng (Cấp 4)');
                        }
                    }, 220);
                }, 220);
            }, 250);
            return;
        }
    }

    // 6. Đăng ký sự kiện Click cho các thẻ Phân cấp có thể tương tác (Interactive Hierarchy Navigation)
    document.addEventListener('click', function (e) {
        const jumpEl = e.target.closest('[data-jump-level]');
        if (!jumpEl) return;

        const level = jumpEl.dataset.jumpLevel;
        const modalType = jumpEl.dataset.modalType;

        if (modalType === 'set') {
            const modalInstance = getModalViewSetInstance();
            jumpToHierarchy(level, {
                setId: currentViewedSetId,
                modalInstance: modalInstance
            });
        } else if (modalType === 'evidence') {
            const modalInstance = getModalEvViewInstance();
            jumpToHierarchy(level, {
                setId: currentEvidenceContext.setId,
                standardId: currentEvidenceContext.standardId,
                criterionId: currentEvidenceContext.criterionId,
                evidenceId: currentEvidenceContext.evidenceId,
                modalInstance: modalInstance
            });
        }
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
