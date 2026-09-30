<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/helpers.php';
require_login();
require_once __DIR__ . '/../config/database.php';

$pdo = db();

// ==========================================
// EXPORT EXCEL
// ==========================================
if (($_GET['export'] ?? '') === 'excel') {
    $searchExport   = trim($_GET['q'] ?? $_GET['keyword'] ?? $_GET['search'] ?? '');
    $setExport      = trim($_GET['standard_set'] ?? $_GET['set'] ?? '');
    $stdExport      = trim($_GET['standard'] ?? '');
    $criExport      = trim($_GET['criterion'] ?? '');
    $evExport       = trim($_GET['evidence'] ?? '');
    $statusExport   = isset($_GET['status']) && $_GET['status'] !== '' ? (int)$_GET['status'] : null;

    $exportClauses = [];
    $exportParams  = [];

    if ($searchExport !== '') {
        $exportClauses[] = "(b.MaBoTieuChuan LIKE :k1 OR b.TenBoTieuChuan LIKE :k2 OR b.ThongTu LIKE :k3)";
        $exportParams['k1'] = "%$searchExport%";
        $exportParams['k2'] = "%$searchExport%";
        $exportParams['k3'] = "%$searchExport%";
    }
    if ($setExport !== '') {
        $exportClauses[] = "b.MaBoTieuChuan = :selected_set";
        $exportParams['selected_set'] = $setExport;
    }
    if ($stdExport !== '') {
        $exportClauses[] = "b.MaBoTieuChuan IN (SELECT MaBoTieuChuan FROM TieuChuan WHERE MaTieuChuan = :selected_std)";
        $exportParams['selected_std'] = $stdExport;
    }
    if ($criExport !== '') {
        $exportClauses[] = "b.MaBoTieuChuan IN (SELECT tc.MaBoTieuChuan FROM TieuChuan tc JOIN TieuChi tchi ON tchi.MaTieuChuan = tc.MaTieuChuan WHERE tchi.MaTieuChi = :selected_crit)";
        $exportParams['selected_crit'] = $criExport;
    }
    if ($evExport !== '') {
        $exportClauses[] = "b.MaBoTieuChuan IN (SELECT DISTINCT COALESCE(m.MaBoTieuChuan, tc.MaBoTieuChuan) FROM MinhChung m LEFT JOIN TieuChi tchi ON tchi.MaTieuChi = m.MaTieuChi LEFT JOIN TieuChuan tc ON tc.MaTieuChuan = tchi.MaTieuChuan WHERE m.MaMinhChung = :selected_ev)";
        $exportParams['selected_ev'] = $evExport;
    }
    if ($statusExport !== null) {
        $exportClauses[] = "b.TrangThai = :selected_status";
        $exportParams['selected_status'] = $statusExport;
    }

    $exportWhere = !empty($exportClauses) ? ' WHERE ' . implode(' AND ', $exportClauses) : '';
    $exportStmt = $pdo->prepare("SELECT b.MaBoTieuChuan, b.TenBoTieuChuan, COALESCE(b.ThongTu, '') AS ThongTu, b.NgayBanHanh, b.MoTa, b.TrangThai FROM BoTieuChuan b" . $exportWhere . " ORDER BY b.TrangThai DESC, b.MaBoTieuChuan ASC");
    $exportStmt->execute($exportParams);
    $exportData = $exportStmt->fetchAll(PDO::FETCH_ASSOC);

    $exportRows = [];
    $stt = 1;
    foreach ($exportData as $row) {
        $exportRows[] = [
            'stt'           => $stt++,
            'code'          => $row['MaBoTieuChuan'],
            'name'          => $row['TenBoTieuChuan'],
            'thong_tu'      => $row['ThongTu'] ?: 'N/A',
            'ngay_ban_hanh' => $row['NgayBanHanh'] ? date('d/m/Y', strtotime($row['NgayBanHanh'])) : '-',
            'status'        => (int)$row['TrangThai'] === 1 ? 'Hoạt động' : 'Ngừng hoạt động',
            'mo_ta'         => $row['MoTa'] ?: '-',
        ];
    }

    $columns = [
        ['key' => 'stt', 'label' => 'STT', 'align' => 'center', 'width' => '60px'],
        ['key' => 'code', 'label' => 'Mã bộ tiêu chuẩn', 'align' => 'center', 'width' => '140px'],
        ['key' => 'name', 'label' => 'Tên bộ tiêu chuẩn', 'align' => 'left', 'width' => '320px'],
        ['key' => 'thong_tu', 'label' => 'Số hiệu / Thông tư', 'align' => 'left', 'width' => '200px'],
        ['key' => 'ngay_ban_hanh', 'label' => 'Ngày ban hành', 'align' => 'center', 'width' => '120px'],
        ['key' => 'status', 'label' => 'Trạng thái', 'align' => 'center', 'width' => '130px'],
        ['key' => 'mo_ta', 'label' => 'Mô tả', 'align' => 'left', 'width' => '280px'],
    ];

    export_to_excel('csdl_minh_chung_' . date('Ymd_His') . '.xls', 'DANH SÁCH CƠ SỞ DỮ LIỆU MINH CHỨNG', $columns, $exportRows);
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
                                            <button type="button" class="btn btn-sm btn-outline-danger btn-view-pdf" data-pdf-url="<?= base_url(htmlspecialchars($set['TepTinPDF'])) ?>" data-pdf-title="<?= htmlspecialchars($set['ThongTu'] . ' - ' . $set['TenBoTieuChuan']) ?>" title="Xem chi tiết file PDF trực tiếp">
                                                <i class="bi bi-file-earmark-pdf me-1"></i>Xem chi tiết
                                            </button>
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
                                                                                                                                                             <button class="btn btn-xs btn-outline-danger btn-view-pdf" type="button" data-pdf-url="<?= htmlspecialchars($fileUrl) ?>" data-pdf-title="<?= htmlspecialchars($mc['TenMinhChung']) ?>" title="Xem tệp PDF">
                                                                                                                                                                 <i class="bi bi-file-earmark-pdf"></i> Xem
                                                                                                                                                             </button>
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
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white py-3">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-info-circle-fill fs-5 text-white"></i>
                    <h5 class="modal-title mb-0 text-white fw-bold" id="modalViewStandardSetLabel">Chi tiết Bộ Tiêu chuẩn</h5>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <!-- Header Banner -->
                <div class="p-3 mb-3 rounded-3 bg-light border d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-primary px-3 py-2 fs-6 fw-bold" id="view_set_id_badge">BTC01</span>
                        <h6 class="mb-0 fw-bold text-dark fs-6" id="view_set_title">Tên bộ tiêu chuẩn</h6>
                    </div>
                    <span id="view_set_status_badge" class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 fs-7 fw-semibold">
                        <i class="bi bi-check-circle-fill me-1"></i>Hoạt động
                    </span>
                </div>

                <!-- Detail Fields Table -->
                <div class="table-responsive mb-3">
                    <table class="table table-bordered align-middle mb-0">
                        <tbody>
                            <tr>
                                <th style="width: 28%;" class="bg-light text-secondary"><i class="bi bi-upc-scan me-2 text-primary"></i>Mã bộ tiêu chuẩn</th>
                                <td id="view_set_id" class="fw-bold text-primary"></td>
                            </tr>
                            <tr>
                                <th class="bg-light text-secondary"><i class="bi bi-bookmark-star me-2 text-primary"></i>Tên bộ tiêu chuẩn</th>
                                <td id="view_set_name" class="fw-semibold text-dark"></td>
                            </tr>
                            <tr>
                                <th class="bg-light text-secondary"><i class="bi bi-file-earmark-text me-2 text-primary"></i>Số hiệu / Thông tư</th>
                                <td><span id="view_set_thongtu" class="badge bg-info-subtle text-dark border border-info-subtle fs-7 fw-semibold"></span></td>
                            </tr>
                            <tr>
                                <th class="bg-light text-secondary"><i class="bi bi-calendar-event me-2 text-primary"></i>Ngày ban hành</th>
                                <td id="view_set_date" class="text-secondary"></td>
                            </tr>
                            <tr>
                                <th class="bg-light text-secondary"><i class="bi bi-toggle-on me-2 text-primary"></i>Trạng thái</th>
                                <td id="view_set_status_text"></td>
                            </tr>
                            <tr>
                                <th class="bg-light text-secondary"><i class="bi bi-diagram-3 me-2 text-primary"></i>Cấu trúc phân cấp</th>
                                <td>
                                    <div class="d-flex flex-wrap gap-2">
                                        <span class="badge bg-secondary-subtle text-secondary py-2 px-3" id="view_set_standards_count"><i class="bi bi-folder2 me-1"></i>0 Tiêu chuẩn</span>
                                        <span class="badge bg-secondary-subtle text-secondary py-2 px-3" id="view_set_criteria_count"><i class="bi bi-list-check me-1"></i>0 Tiêu chí</span>
                                        <span class="badge bg-secondary-subtle text-secondary py-2 px-3" id="view_set_evidences_count"><i class="bi bi-file-earmark-check me-1"></i>0 Minh chứng</span>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <th class="bg-light text-secondary"><i class="bi bi-card-text me-2 text-primary"></i>Mô tả / Ghi chú</th>
                                <td><div id="view_set_desc" class="text-secondary text-break" style="white-space: pre-wrap;"></div></td>
                            </tr>
                            <tr>
                                <th class="bg-light text-secondary"><i class="bi bi-paperclip me-2 text-primary"></i>File dữ liệu đính kèm</th>
                                <td>
                                    <div id="view_set_file_container">
                                        <!-- Populated via JS -->
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Preview Area -->
                <div id="view_set_preview_box" class="card border shadow-none bg-light p-3" style="display: none;">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                        <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-eye-fill me-2 text-primary"></i>Xem trước File dữ liệu:</h6>
                        <a href="#" id="view_set_preview_newtab_link" target="_blank" class="btn btn-sm btn-primary">
                            <i class="bi bi-box-arrow-up-right me-1"></i>Mở toàn màn hình ở tab mới
                        </a>
                    </div>
                    <div id="view_set_preview_content" class="text-center bg-white rounded border p-2" style="min-height: 200px;">
                        <!-- Embed / Image / Message -->
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light py-2 d-flex justify-content-between">
                <div></div>
                <div class="d-inline-flex gap-2">
                    <a href="#" id="view_set_btn_open_tab_footer" target="_blank" class="btn btn-primary btn-sm" style="display: none;">
                        <i class="bi bi-box-arrow-up-right me-1"></i>Xem file dữ liệu (Tab mới)
                    </a>
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Đóng</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ==========================================
     MODAL 3: XEM CHI TIẾT MINH CHỨNG
=============================================== -->
<div class="modal fade" id="modalViewEvidenceDetails" tabindex="-1" aria-labelledby="modalViewEvidenceLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white py-3">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-file-earmark-text-fill fs-5 text-white"></i>
                    <h5 class="modal-title mb-0 text-white fw-bold" id="modalViewEvidenceLabel">Chi tiết Minh chứng</h5>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <!-- Header Banner -->
                <div class="p-3 mb-3 rounded-3 bg-light border d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-primary px-3 py-2 fs-6 fw-bold" id="view_ev_id_badge">MC01</span>
                        <h6 class="mb-0 fw-bold text-dark fs-6" id="view_ev_title">Tên minh chứng</h6>
                    </div>
                    <span id="view_ev_status_badge" class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 fs-7 fw-semibold">
                        <i class="bi bi-check-circle-fill me-1"></i>Hoạt động
                    </span>
                </div>

                <!-- Detail Fields Table -->
                <div class="table-responsive mb-3">
                    <table class="table table-bordered align-middle mb-0">
                        <tbody>
                            <tr>
                                <th style="width: 28%;" class="bg-light text-secondary"><i class="bi bi-upc me-2 text-primary"></i>Mã minh chứng</th>
                                <td id="view_ev_id" class="fw-bold text-primary"></td>
                            </tr>
                            <tr>
                                <th class="bg-light text-secondary"><i class="bi bi-card-heading me-2 text-primary"></i>Tên minh chứng</th>
                                <td id="view_ev_name" class="fw-semibold text-dark"></td>
                            </tr>
                            <tr>
                                <th class="bg-light text-secondary"><i class="bi bi-collection me-2 text-primary"></i>Bộ tiêu chuẩn</th>
                                <td id="view_ev_set" class="text-secondary"></td>
                            </tr>
                            <tr>
                                <th class="bg-light text-secondary"><i class="bi bi-folder2 me-2 text-primary"></i>Tiêu chuẩn cha</th>
                                <td id="view_ev_standard" class="text-secondary"></td>
                            </tr>
                            <tr>
                                <th class="bg-light text-secondary"><i class="bi bi-list-task me-2 text-primary"></i>Tiêu chí cha</th>
                                <td id="view_ev_criterion" class="text-secondary fw-medium"></td>
                            </tr>
                            <tr>
                                <th class="bg-light text-secondary"><i class="bi bi-calendar3 me-2 text-primary"></i>Năm học</th>
                                <td id="view_ev_namhoc" class="text-secondary"></td>
                            </tr>
                            <tr>
                                <th class="bg-light text-secondary"><i class="bi bi-calendar-event me-2 text-primary"></i>Ngày ban hành</th>
                                <td id="view_ev_date" class="text-secondary"></td>
                            </tr>
                            <tr>
                                <th class="bg-light text-secondary"><i class="bi bi-person me-2 text-primary"></i>Người tạo</th>
                                <td id="view_ev_creator" class="text-secondary"></td>
                            </tr>
                            <tr>
                                <th class="bg-light text-secondary"><i class="bi bi-card-text me-2 text-primary"></i>Mô tả / Trích yếu</th>
                                <td><div id="view_ev_desc" class="text-secondary text-break" style="white-space: pre-wrap;"></div></td>
                            </tr>
                            <tr>
                                <th class="bg-light text-secondary"><i class="bi bi-paperclip me-2 text-primary"></i>Tệp đính kèm</th>
                                <td>
                                    <div id="view_ev_file_container">
                                        <!-- Populated via JS -->
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer bg-light py-2 d-flex justify-content-between">
                <div></div>
                <div class="d-inline-flex gap-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Đóng</button>
                </div>
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

    // 2. Setup PDF Viewer Modal
    const modalPdfEl = document.getElementById('modalPdfViewer');
    const modalPdf = modalPdfEl ? new bootstrap.Modal(modalPdfEl) : null;
    const pdfIframe = document.getElementById('pdfViewerIframe');
    const pdfTitle = document.getElementById('modalPdfViewerLabel');
    const btnOpenTab = document.getElementById('btnPdfOpenNewTab');
    const btnDownload = document.getElementById('btnPdfDownload');

    document.querySelectorAll('.btn-view-pdf').forEach(btn => {
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            const pdfUrl = this.dataset.pdfUrl;
            const title = this.dataset.pdfTitle || 'Xem chi tiết tệp PDF';

            if (!pdfUrl) return;

            if (pdfTitle) pdfTitle.textContent = title;
            if (btnOpenTab) btnOpenTab.href = pdfUrl;
            if (btnDownload) btnDownload.href = pdfUrl;

            if (pdfIframe) {
                pdfIframe.src = pdfUrl;
            }

            if (modalPdf) {
                modalPdf.show();
            }
        });
    });

    if (modalPdfEl) {
        modalPdfEl.addEventListener('hidden.bs.modal', function () {
            if (pdfIframe) pdfIframe.src = 'about:blank';
        });
    }

    // 3. Setup View Standard Set Detail Modal
    const modalViewEl = document.getElementById('modalViewStandardSetDetails');
    const modalView = modalViewEl ? new bootstrap.Modal(modalViewEl) : null;

    document.querySelectorAll('.btn-view-set-detail').forEach(btn => {
        btn.addEventListener('click', function () {
            const id = this.dataset.id || '';
            const name = this.dataset.name || '';
            const thongtu = this.dataset.thongtu || '';
            const dateFormatted = this.dataset.dateFormatted || '-';
            const desc = this.dataset.desc || 'Không có mô tả / ghi chú bổ sung.';
            const status = this.dataset.status || '1';
            const pdf = this.dataset.pdf || '';
            const pdfUrl = this.dataset.pdfUrl || '';
            const stdCount = this.dataset.standardsCount || '0';
            const critCount = this.dataset.criteriaCount || '0';
            const evCount = this.dataset.evidencesCount || '0';

            document.getElementById('view_set_id_badge').textContent = id;
            document.getElementById('view_set_id').textContent = id;
            document.getElementById('view_set_title').textContent = name;
            document.getElementById('view_set_name').textContent = name;
            document.getElementById('view_set_thongtu').textContent = thongtu || 'Chưa nhập số hiệu';
            document.getElementById('view_set_date').textContent = dateFormatted;

            const isAct = status === '1';
            const statusBadge = document.getElementById('view_set_status_badge');
            statusBadge.className = isAct ? 'badge bg-success-subtle text-success border border-success-subtle px-3 py-2 fs-7 fw-semibold' : 'badge bg-secondary-subtle text-secondary border border-secondary-subtle px-3 py-2 fs-7 fw-semibold';
            statusBadge.innerHTML = isAct ? '<i class="bi bi-check-circle-fill me-1"></i>Hoạt động' : '<i class="bi bi-pause-circle-fill me-1"></i>Ngừng hoạt động';
            document.getElementById('view_set_status_text').innerHTML = isAct ? '<span class="text-success fw-bold"><i class="bi bi-check2-circle me-1"></i>Đang hoạt động (Hiển thị)</span>' : '<span class="text-muted"><i class="bi bi-dash-circle me-1"></i>Ngừng hoạt động</span>';

            document.getElementById('view_set_standards_count').innerHTML = `<i class="bi bi-folder2 me-1"></i>${stdCount} Tiêu chuẩn`;
            document.getElementById('view_set_criteria_count').innerHTML = `<i class="bi bi-list-check me-1"></i>${critCount} Tiêu chí`;
            document.getElementById('view_set_evidences_count').innerHTML = `<i class="bi bi-file-earmark-check me-1"></i>${evCount} Minh chứng`;

            document.getElementById('view_set_desc').textContent = desc;

            const fileContainer = document.getElementById('view_set_file_container');
            const previewBox = document.getElementById('view_set_preview_box');
            const previewContent = document.getElementById('view_set_preview_content');
            const previewNewtabLink = document.getElementById('view_set_preview_newtab_link');
            const footerOpenTab = document.getElementById('view_set_btn_open_tab_footer');

            if (pdf && pdfUrl) {
                const ext = pdf.split('.').pop().toLowerCase();
                const isPdf = ext === 'pdf';
                const isImage = ['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext);

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

                if (footerOpenTab) {
                    footerOpenTab.href = pdfUrl;
                    footerOpenTab.style.display = 'inline-block';
                }

                if (isPdf) {
                    previewBox.style.display = 'block';
                    previewNewtabLink.href = pdfUrl;
                    previewContent.innerHTML = `<iframe src="${escapeHtml(pdfUrl)}" style="width: 100%; height: 400px; border: none; border-radius: 8px;"></iframe>`;
                } else if (isImage) {
                    previewBox.style.display = 'block';
                    previewNewtabLink.href = pdfUrl;
                    previewContent.innerHTML = `<img src="${escapeHtml(pdfUrl)}" alt="Preview" class="img-fluid rounded shadow-sm" style="max-height: 400px;">`;
                } else {
                    previewBox.style.display = 'none';
                    previewContent.innerHTML = '';
                }
            } else {
                fileContainer.innerHTML = `<span class="badge bg-light text-muted border p-2"><i class="bi bi-info-circle me-1"></i>Chưa đính kèm file dữ liệu nào cho bộ tiêu chuẩn này.</span>`;
                previewBox.style.display = 'none';
                previewContent.innerHTML = '';
                if (footerOpenTab) footerOpenTab.style.display = 'none';
            }

            if (modalView) modalView.show();
        });
    });

    // 4. Setup View Evidence Detail Modal
    const modalEvViewEl = document.getElementById('modalViewEvidenceDetails');
    const modalEvView = modalEvViewEl ? new bootstrap.Modal(modalEvViewEl) : null;

    document.querySelectorAll('.btn-view-evidence-detail').forEach(btn => {
        btn.addEventListener('click', function () {
            const id = this.dataset.id || '';
            const name = this.dataset.name || '';
            const set = this.dataset.set || '-';
            const standard = this.dataset.standard || '-';
            const criterion = this.dataset.criterion || '-';
            const namhoc = this.dataset.namhoc || '-';
            const date = this.dataset.date || '-';
            const desc = this.dataset.desc || '-';
            const status = this.dataset.status || 'Hoạt động';
            const creator = this.dataset.creator || 'Hệ thống';
            const hasFile = this.dataset.hasFile === '1';
            const fileUrl = this.dataset.fileUrl || '#';
            const downloadUrl = this.dataset.downloadUrl || '#';
            const fileName = this.dataset.fileName || '';
            const fileExt = this.dataset.fileExt || '';

            document.getElementById('view_ev_id_badge').textContent = id;
            document.getElementById('view_ev_id').textContent = id;
            document.getElementById('view_ev_title').textContent = name;
            document.getElementById('view_ev_name').textContent = name;
            document.getElementById('view_ev_set').textContent = set;
            document.getElementById('view_ev_standard').textContent = standard;
            document.getElementById('view_ev_criterion').textContent = criterion;
            document.getElementById('view_ev_namhoc').textContent = namhoc;
            document.getElementById('view_ev_date').textContent = date;
            document.getElementById('view_ev_creator').textContent = creator;
            document.getElementById('view_ev_desc').textContent = desc;

            const isAct = status === 'Hoạt động';
            const statusBadge = document.getElementById('view_ev_status_badge');
            statusBadge.className = isAct ? 'badge bg-success-subtle text-success border border-success-subtle px-3 py-2 fs-7 fw-semibold' : 'badge bg-secondary-subtle text-secondary border border-secondary-subtle px-3 py-2 fs-7 fw-semibold';
            statusBadge.innerHTML = isAct ? '<i class="bi bi-check-circle-fill me-1"></i>Hoạt động' : '<i class="bi bi-pause-circle-fill me-1"></i>Tạm ẩn';

            const fileContainer = document.getElementById('view_ev_file_container');
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
                            ${isPdf ? `<button type="button" class="btn btn-sm btn-outline-danger btn-view-pdf" data-pdf-url="${escapeHtml(fileUrl)}" data-pdf-title="${escapeHtml(name)}"><i class="bi bi-eye me-1"></i>Xem PDF</button>` : ''}
                            <a href="${escapeHtml(downloadUrl)}" class="btn btn-sm btn-primary">
                                <i class="bi bi-download me-1"></i>Tải về
                            </a>
                        </div>
                    </div>
                `;
            } else {
                fileContainer.innerHTML = `<span class="badge bg-light text-muted border p-2"><i class="bi bi-info-circle me-1"></i>Chưa có tệp đính kèm nào cho minh chứng này.</span>`;
            }

            if (modalEvView) modalEvView.show();
        });
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
