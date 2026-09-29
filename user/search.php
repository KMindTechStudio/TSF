<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/data.php';

// Nhận tham số tìm kiếm từ URL
$searchKeyword  = trim($_GET['q'] ?? $_GET['keyword'] ?? '');
$selectedStandard = trim($_GET['standard'] ?? '');
$selectedCriterion = trim($_GET['criterion'] ?? '');
$selectedEvidence = trim($_GET['evidence'] ?? '');
$downloadError  = $_GET['download_error'] ?? '';

// Lọc sơ bộ phía Server để đảm bảo SSR / SEO / Reload trang chuẩn xác
$filteredEvidences = array_filter($evidences, function ($item) use ($searchKeyword, $selectedStandard, $selectedCriterion, $selectedEvidence) {
    if ($searchKeyword !== '') {
        $found = search_contains($item['code'] ?? '', $searchKeyword) 
              || search_contains($item['name'] ?? '', $searchKeyword)
              || search_contains($item['description'] ?? '', $searchKeyword);
        if (!$found) {
            return false;
        }
    }

    if ($selectedStandard !== '' && ($item['ma_tieu_chuan'] ?? '') !== $selectedStandard) {
        return false;
    }

    if ($selectedCriterion !== '' && ($item['ma_tieu_chi'] ?? '') !== $selectedCriterion) {
        return false;
    }

    if ($selectedEvidence !== '' && ($item['code'] ?? '') !== $selectedEvidence && ($item['id'] ?? '') !== $selectedEvidence) {
        return false;
    }

    return true;
});

$filteredEvidences = array_values($filteredEvidences);

$pageTitle = page_title('CSDL Minh chứng');
$heading = 'CSDL Minh chứng';
include __DIR__ . '/../includes/header.php';
?>

<!-- ======================= THANH BỘ LỌC TẬP TRUNG THÔNG MINH ======================= -->
<div class="card border-0 shadow-sm rounded-4 mb-4 filter-panel-card" style="background: var(--surface, #ffffff);">
    <div class="card-body p-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 pb-2 border-bottom">
            <div class="d-flex align-items-center gap-2">
                <span class="badge rounded-pill bg-primary-subtle text-primary px-3 py-2 fs-6">
                    <i class="bi bi-funnel-fill me-1"></i> Bộ lọc & Tìm kiếm tập trung
                </span>
                <span class="text-secondary small d-none d-md-inline">
                    Lọc tự động ngay khi nhập hoặc chọn điều kiện
                </span>
            </div>
            <div class="d-flex align-items-center gap-2">
                <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3" id="btnResetAll" title="Đặt lại tất cả bộ lọc">
                    <i class="bi bi-arrow-counterclockwise me-1"></i> Đặt lại
                </button>
            </div>
        </div>

        <form id="filterForm" onsubmit="return false;" class="row g-3">
            <!-- CÁCH 1: Ô NHẬP TỪ KHÓA TÌM NHANH (MÃ HOẶC TÊN MINH CHỨNG) -->
            <div class="col-12 col-xl-4">
                <label for="filterKeyword" class="form-label fw-bold small text-primary mb-1 d-flex align-items-center gap-1">
                    <i class="bi bi-search"></i> Cách 1: Ô nhập từ khóa (Mã / Tên minh chứng)
                </label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0 text-muted">
                        <i class="bi bi-search"></i>
                    </span>
                    <input type="text" 
                           class="form-control bg-light border-start-0 border-end-0 ps-0" 
                           id="filterKeyword" 
                           name="keyword"
                           placeholder="Nhập mã (MC01...) hoặc tên minh chứng..." 
                           value="<?= htmlspecialchars($searchKeyword) ?>"
                           autocomplete="off">
                    <button class="btn btn-light border border-start-0 text-muted" type="button" id="btnClearKeyword" style="display: <?= $searchKeyword !== '' ? 'block' : 'none' ?>;" title="Xóa từ khóa">
                        <i class="bi bi-x-circle-fill"></i>
                    </button>
                </div>
                <div class="form-text small text-secondary mt-1">
                    <i class="bi bi-lightning-charge text-warning"></i> Tự động tìm kiếm ngay khi gõ
                </div>
            </div>

            <!-- CÁCH 2: CỤM 3 Ô CHỌN NHANH (DROPDOWN SONG SONG & LIÊN KẾT) -->
            <div class="col-12 col-xl-8">
                <label class="form-label fw-bold small text-primary mb-1 d-flex align-items-center gap-1">
                    <i class="bi bi-diagram-3"></i> Cách 2: Ô chọn nhanh phân cấp (Tiêu chuẩn &rarr; Tiêu chí &rarr; Minh chứng)
                </label>
                <div class="row g-2">
                    <!-- Dropdown 1: Tiêu chuẩn -->
                    <div class="col-12 col-md-4">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light text-muted" title="Lọc theo Tiêu chuẩn"><i class="bi bi-bookmark-check"></i></span>
                            <select class="form-select form-select-sm" id="filterStandard" name="standard">
                                <option value="">-- Tất cả Tiêu chuẩn (<?= count($standards) ?>) --</option>
                                <?php foreach ($standards as $std): ?>
                                    <option value="<?= htmlspecialchars($std['id']) ?>" <?= $selectedStandard === (string)$std['id'] ? 'selected' : '' ?> title="<?= htmlspecialchars($std['name']) ?>">
                                        <?= htmlspecialchars($std['code'] . ' - ' . $std['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Dropdown 2: Tiêu chí (Tự động lọc theo Tiêu chuẩn đã chọn) -->
                    <div class="col-12 col-md-4">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light text-muted" title="Lọc theo Tiêu chí"><i class="bi bi-list-check"></i></span>
                            <select class="form-select form-select-sm" id="filterCriterion" name="criterion">
                                <option value="">-- Tất cả Tiêu chí (<?= count($criteria) ?>) --</option>
                                <?php foreach ($criteria as $cr): ?>
                                    <option value="<?= htmlspecialchars($cr['id']) ?>" 
                                            data-standard="<?= htmlspecialchars($cr['standard_id']) ?>"
                                            <?= $selectedCriterion === (string)$cr['id'] ? 'selected' : '' ?> 
                                            title="<?= htmlspecialchars($cr['name']) ?>">
                                        <?= htmlspecialchars($cr['code'] . ' - ' . $cr['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Dropdown 3: Minh chứng (Tự động lọc theo Tiêu chuẩn / Tiêu chí đã chọn) -->
                    <div class="col-12 col-md-4">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light text-muted" title="Lọc theo Minh chứng"><i class="bi bi-file-earmark-text"></i></span>
                            <select class="form-select form-select-sm" id="filterEvidence" name="evidence">
                                <option value="">-- Chọn Minh chứng (<?= count($evidences) ?>) --</option>
                                <?php foreach ($evidences as $ev): ?>
                                    <option value="<?= htmlspecialchars($ev['code']) ?>" 
                                            data-standard="<?= htmlspecialchars($ev['ma_tieu_chuan']) ?>"
                                            data-criterion="<?= htmlspecialchars($ev['ma_tieu_chi']) ?>"
                                            <?= ($selectedEvidence === (string)$ev['code'] || $selectedEvidence === (string)$ev['id']) ? 'selected' : '' ?>
                                            title="<?= htmlspecialchars($ev['name']) ?>">
                                        <?= htmlspecialchars($ev['code'] . ' - ' . $ev['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="form-text small text-secondary mt-1">
                    <i class="bi bi-arrow-repeat text-info"></i> Các ô chọn tự động liên kết và lọc danh sách tương ứng
                </div>
            </div>
        </form>

        <!-- DẢI CHIPS HIỂN THỊ CÁC TIÊU CHÍ ĐANG LỌC -->
        <div id="activeFilterTags" class="d-flex flex-wrap align-items-center gap-2 mt-3 pt-3 border-top" style="display: none !important;">
            <span class="text-secondary small fw-semibold"><i class="bi bi-tags"></i> Đang lọc theo:</span>
            <div id="tagList" class="d-flex flex-wrap gap-2"></div>
        </div>
    </div>
</div>

<?php if ($downloadError): ?>
    <div class="alert alert-warning alert-dismissible fade show rounded-3" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        Không thể tải minh chứng. Vui lòng kiểm tra lại tệp đính kèm trong hệ thống.
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- ======================= BẢNG KẾT QUẢ TÌM KIẾM ======================= -->
<div class="panel shadow-sm rounded-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h2 class="h5 mb-0 d-flex align-items-center gap-2">
                <span>Kết quả tìm kiếm</span>
                <span id="resultsCountBadge" class="badge bg-primary-subtle text-primary rounded-pill fs-7">
                    <?= count($filteredEvidences) ?> minh chứng
                </span>
            </h2>
            <div id="filterSummaryText" class="small text-secondary mt-1"></div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="text-secondary small" id="liveMatchInfo">
                Tìm thấy <strong><?= count($filteredEvidences) ?></strong> minh chứng phù hợp
            </span>
        </div>
    </div>

    <!-- Empty state container -->
    <div id="noResultsAlert" class="alert alert-warning rounded-3 text-center py-4 mb-0" style="display: <?= empty($filteredEvidences) ? 'block' : 'none' ?>;">
        <i class="bi bi-search text-warning fs-1 d-block mb-2"></i>
        <h5 class="fw-bold mb-1">Không tìm thấy minh chứng phù hợp</h5>
        <p class="text-secondary mb-3">Vui lòng thử thay đổi từ khóa hoặc chọn điều kiện bộ lọc khác.</p>
        <button type="button" class="btn btn-sm btn-primary rounded-pill px-4" id="btnResetEmptyState">
            <i class="bi bi-arrow-counterclockwise me-1"></i> Xóa tất cả bộ lọc
        </button>
    </div>

    <!-- Table container -->
    <div class="table-responsive" id="tableContainer" style="display: <?= empty($filteredEvidences) ? 'none' : 'block' ?>;">
        <table class="table align-middle" id="evidenceTable" data-page-size="10">
            <thead>
                <tr>
                    <th class="text-nowrap" style="width: 110px;">Mã MC</th>
                    <th style="min-width: 220px; max-width: 280px;">Tên minh chứng</th>
                    <th style="min-width: 180px; max-width: 220px;">Tiêu chuẩn & Tiêu chí</th>
                    <th class="text-nowrap" style="min-width: 110px; max-width: 140px;">Tệp tin</th>
                    <th class="text-nowrap" style="width: 90px;">Năm học</th>
                    <th class="text-nowrap" style="width: 130px;">Ngày cập nhật</th>
                    <th class="text-nowrap" style="width: 110px;">Trạng thái</th>
                    <th class="text-end text-nowrap action-cell" style="width: 110px;">Thao tác</th>
                </tr>
            </thead>
            <tbody id="evidenceTableBody">
                <?php foreach ($filteredEvidences as $item): ?>
                    <tr data-id="<?= htmlspecialchars($item['id']) ?>"
                        data-code="<?= htmlspecialchars($item['code']) ?>"
                        data-name="<?= htmlspecialchars($item['name']) ?>"
                        data-desc="<?= htmlspecialchars($item['description']) ?>"
                        data-standard="<?= htmlspecialchars($item['ma_tieu_chuan']) ?>"
                        data-criterion="<?= htmlspecialchars($item['ma_tieu_chi']) ?>">
                        <td class="fw-bold text-nowrap text-primary">
                            <span class="badge bg-light text-primary border font-monospace"><?= htmlspecialchars($item['code']) ?></span>
                        </td>
                        <td class="fw-semibold" style="min-width: 220px; max-width: 280px;">
                            <div class="line-clamp-2 evidence-name-text" title="<?= htmlspecialchars($item['name']) ?>">
                                <?= htmlspecialchars($item['name']) ?>
                            </div>
                            <?php if (!empty($item['description'])): ?>
                                <small class="text-muted line-clamp-1 d-block mt-1" title="<?= htmlspecialchars($item['description']) ?>">
                                    <?= htmlspecialchars($item['description']) ?>
                                </small>
                            <?php endif; ?>
                        </td>
                        <td style="min-width: 180px; max-width: 220px;">
                            <div class="d-flex flex-column gap-1">
                                <?php if (!empty($item['ma_tieu_chuan'])): ?>
                                    <span class="badge bg-primary-subtle text-primary text-truncate d-inline-block text-start w-100" title="<?= htmlspecialchars($item['standard_name'] ? ($item['ma_tieu_chuan'] . ' - ' . $item['standard_name']) : $item['ma_tieu_chuan']) ?>">
                                        <i class="bi bi-bookmark-fill me-1"></i><?= htmlspecialchars($item['ma_tieu_chuan'] . ($item['standard_name'] ? (' - ' . $item['standard_name']) : '')) ?>
                                    </span>
                                <?php endif; ?>
                                <?php if (!empty($item['ma_tieu_chi'])): ?>
                                    <span class="badge bg-info-subtle text-dark text-truncate d-inline-block text-start w-100" title="<?= htmlspecialchars($item['criterion_name'] ? ($item['ma_tieu_chi'] . ' - ' . $item['criterion_name']) : $item['ma_tieu_chi']) ?>">
                                        <i class="bi bi-list-check me-1"></i><?= htmlspecialchars($item['ma_tieu_chi'] . ($item['criterion_name'] ? (' - ' . $item['criterion_name']) : '')) ?>
                                    </span>
                                <?php endif; ?>
                                <?php if (empty($item['ma_tieu_chuan']) && empty($item['ma_tieu_chi'])): ?>
                                    <span class="text-secondary small">Chưa phân loại</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="text-nowrap">
                            <?php if (!empty($item['file_path'])): ?>
                                <span class="badge bg-light text-dark border d-inline-flex align-items-center gap-1 text-truncate" style="max-width: 140px;" title="<?= htmlspecialchars(basename($item['file_path'])) ?>">
                                    <i class="bi bi-file-earmark-pdf text-danger"></i>
                                    <span class="text-truncate"><?= htmlspecialchars(basename($item['file_path'])) ?></span>
                                </span>
                            <?php else: ?>
                                <span class="text-secondary small">Không có</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-nowrap">
                            <span class="badge bg-secondary-subtle text-secondary"><?= htmlspecialchars($item['year'] ?: '-') ?></span>
                        </td>
                        <td class="text-nowrap small text-secondary">
                            <i class="bi bi-clock me-1"></i><?= htmlspecialchars($item['updated'] ?: '-') ?>
                        </td>
                        <td class="text-nowrap">
                            <?php if ((int) ($item['status_raw'] ?? 1) === 1): ?>
                                <span class="badge bg-success-subtle text-success border border-success-subtle">Đang hoạt động</span>
                            <?php else: ?>
                                <span class="badge bg-warning-subtle text-warning border border-warning-subtle">Ngưng áp dụng</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end action-cell">
                            <div class="action-buttons d-flex justify-content-end gap-1">
                                <button type="button" class="btn btn-sm btn-outline-primary btn-view-evidence" data-evidence-id="<?= htmlspecialchars($item['id']) ?>" title="Xem chi tiết minh chứng">
                                    <i class="bi bi-eye"></i>
                                </button>
                                <?php if (!empty($item['file_path'])): ?>
                                    <a class="btn btn-sm btn-outline-success" href="<?= base_url('user/download.php?id=' . urlencode($item['id'])) ?>" title="Tải tệp đính kèm về">
                                        <i class="bi bi-download"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ======================= MODAL XEM CHI TIẾT MINH CHỨNG ======================= -->
<div class="modal fade" id="evidenceDetailModal" tabindex="-1" aria-labelledby="evidenceDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-bottom pb-3">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-primary-subtle text-primary p-2 rounded-3 fs-6">
                        <i class="bi bi-file-earmark-text"></i>
                    </span>
                    <div>
                        <h5 class="modal-title fw-bold mb-0" id="evidenceDetailModalLabel">Thông tin chi tiết Minh chứng</h5>
                        <small class="text-secondary" id="modalEvidenceCodeSubtitle"></small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-bold small text-muted">Mã minh chứng</label>
                        <input class="form-control bg-light fw-bold text-primary font-monospace" id="modalCode" readonly>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold small text-muted">Năm học</label>
                        <input class="form-control bg-light" id="modalYear" readonly>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold small text-muted">Trạng thái</label>
                        <input class="form-control bg-light fw-semibold" id="modalStatus" readonly>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-bold small text-muted">Tên minh chứng</label>
                        <textarea class="form-control bg-light fw-semibold" id="modalName" rows="2" readonly></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-muted">Tiêu chuẩn</label>
                        <input class="form-control bg-light" id="modalStandard" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-muted">Tiêu chí</label>
                        <input class="form-control bg-light" id="modalCriterion" readonly>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-bold small text-muted">Mô tả chi tiết</label>
                        <textarea class="form-control bg-light" id="modalDesc" rows="3" readonly></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-muted">Ngày cập nhật</label>
                        <input class="form-control bg-light" id="modalUpdated" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-muted">Người cập nhật</label>
                        <input class="form-control bg-light" id="modalUser" readonly>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-bold small text-muted">Tệp tin đính kèm</label>
                        <div class="d-flex align-items-center gap-2" id="modalFileWrapper">
                            <input class="form-control bg-light" id="modalFileName" readonly>
                            <a id="modalDownloadBtn" class="btn btn-success text-nowrap px-3" href="#" title="Tải tệp tin về">
                                <i class="bi bi-download me-1"></i> Tải về
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-top pt-3">
                <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Đóng</button>
            </div>
        </div>
    </div>
</div>

<!-- ======================= CLIENT DATA & SMART FILTER ENGINE ======================= -->
<script>
window.EVIDENCES_DATA = <?= json_encode($evidences, JSON_UNESCAPED_UNICODE) ?>;
window.STANDARDS_DATA = <?= json_encode($standards, JSON_UNESCAPED_UNICODE) ?>;
window.CRITERIA_DATA  = <?= json_encode($criteria, JSON_UNESCAPED_UNICODE) ?>;

(function() {
    // Utility chuẩn hóa tiếng Việt để tìm kiếm không dấu
    function removeVietnameseTones(str) {
        if (!str) return '';
        str = str.toLowerCase();
        str = str.replace(/à|á|ạ|ả|ã|â|ầ|ấ|ậ|ẩ|ẫ|ă|ằ|ắ|ặ|ẳ|ẵ/g, 'a');
        str = str.replace(/è|é|ẹ|ẻ|ẽ|ê|ề|ế|ệ|ể|ễ/g, 'e');
        str = str.replace(/ì|í|ị|ỉ|ĩ/g, 'i');
        str = str.replace(/ò|ó|ọ|ỏ|õ|ô|ồ|ố|ộ|ổ|ỗ|ơ|ờ|ớ|ợ|ở|ỡ/g, 'o');
        str = str.replace(/ù|ú|ụ|ủ|ũ|ư|ừ|ứ|ự|ử|ữ/g, 'u');
        str = str.replace(/ỳ|ý|ỵ|ỷ|ỹ/g, 'y');
        str = str.replace(/đ/g, 'd');
        return str;
    }

    const keywordInput    = document.getElementById('filterKeyword');
    const clearKeywordBtn = document.getElementById('btnClearKeyword');
    const standardSelect  = document.getElementById('filterStandard');
    const criterionSelect = document.getElementById('filterCriterion');
    const evidenceSelect  = document.getElementById('filterEvidence');
    const btnResetAll     = document.getElementById('btnResetAll');
    const btnResetEmpty   = document.getElementById('btnResetEmptyState');
    const activeTagsBox   = document.getElementById('activeFilterTags');
    const tagList         = document.getElementById('tagList');
    
    const tableContainer  = document.getElementById('tableContainer');
    const tableBody       = document.getElementById('evidenceTableBody');
    const noResultsAlert  = document.getElementById('noResultsAlert');
    const resultsCountBadge = document.getElementById('resultsCountBadge');
    const liveMatchInfo   = document.getElementById('liveMatchInfo');
    const filterSummaryText = document.getElementById('filterSummaryText');

    let debounceTimer = null;

    // 1. CẬP NHẬT DANH SÁCH DROPDOWN TIÊU CHÍ KHI CHỌN TIÊU CHUẨN
    function updateCriteriaDropdown(selectedStandard, keepSelectedVal = null) {
        const currentVal = keepSelectedVal !== null ? keepSelectedVal : criterionSelect.value;
        criterionSelect.innerHTML = '<option value="">-- Tất cả Tiêu chí --</option>';

        const filteredCriteria = window.CRITERIA_DATA.filter(cr => {
            if (!selectedStandard) return true;
            return cr.standard_id === selectedStandard;
        });

        filteredCriteria.forEach(cr => {
            const opt = document.createElement('option');
            opt.value = cr.id;
            opt.setAttribute('data-standard', cr.standard_id);
            opt.textContent = `${cr.code} - ${cr.name}`;
            opt.title = cr.name;
            if (cr.id === currentVal) {
                opt.selected = true;
            }
            criterionSelect.appendChild(opt);
        });

        // Nếu giá trị đang chọn không còn hợp lệ trong danh sách mới thì reset
        if (currentVal && !filteredCriteria.some(c => c.id === currentVal)) {
            criterionSelect.value = '';
        }
    }

    // 2. CẬP NHẬT DANH SÁCH DROPDOWN MINH CHỨNG KHI CHỌN TIÊU CHUẨN HOẶC TIÊU CHÍ
    function updateEvidenceDropdown(selectedStandard, selectedCriterion, keepSelectedVal = null) {
        const currentVal = keepSelectedVal !== null ? keepSelectedVal : evidenceSelect.value;
        evidenceSelect.innerHTML = '<option value="">-- Chọn Minh chứng --</option>';

        const filteredEvidences = window.EVIDENCES_DATA.filter(ev => {
            if (selectedStandard && ev.ma_tieu_chuan !== selectedStandard) return false;
            if (selectedCriterion && ev.ma_tieu_chi !== selectedCriterion) return false;
            return true;
        });

        filteredEvidences.forEach(ev => {
            const opt = document.createElement('option');
            opt.value = ev.code;
            opt.setAttribute('data-standard', ev.ma_tieu_chuan || '');
            opt.setAttribute('data-criterion', ev.ma_tieu_chi || '');
            opt.textContent = `${ev.code} - ${ev.name}`;
            opt.title = ev.name;
            if (ev.code === currentVal) {
                opt.selected = true;
            }
            evidenceSelect.appendChild(opt);
        });

        if (currentVal && !filteredEvidences.some(e => e.code === currentVal)) {
            evidenceSelect.value = '';
        }
    }

    // 3. THỰC HIỆN LỌC DỮ LIỆU TẬP TRUNG (CLIENT-SIDE ENGINE)
    function executeFilter(updateUrl = true) {
        const rawKeyword   = keywordInput.value.trim();
        const normKeyword  = removeVietnameseTones(rawKeyword);
        const selStandard  = standardSelect.value.trim();
        const selCriterion = criterionSelect.value.trim();
        const selEvidence  = evidenceSelect.value.trim();

        // Hiển thị / Ẩn nút xóa từ khóa
        if (clearKeywordBtn) {
            clearKeywordBtn.style.display = rawKeyword !== '' ? 'block' : 'none';
        }

        // Cập nhật URLSearchParams
        if (updateUrl && window.history && window.history.replaceState) {
            const params = new URLSearchParams();
            if (rawKeyword) params.set('q', rawKeyword);
            if (selStandard) params.set('standard', selStandard);
            if (selCriterion) params.set('criterion', selCriterion);
            if (selEvidence) params.set('evidence', selEvidence);
            const newUrl = window.location.pathname + (params.toString() ? ('?' + params.toString()) : '');
            window.history.replaceState({}, '', newUrl);
        }

        // Lọc danh sách dữ liệu
        const matched = window.EVIDENCES_DATA.filter(ev => {
            // Lọc theo từ khóa (Mã hoặc Tên minh chứng, hoặc Mô tả)
            if (normKeyword !== '') {
                const codeMatch = removeVietnameseTones(ev.code || '').includes(normKeyword);
                const nameMatch = removeVietnameseTones(ev.name || '').includes(normKeyword);
                const descMatch = removeVietnameseTones(ev.description || '').includes(normKeyword);
                if (!codeMatch && !nameMatch && !descMatch) {
                    return false;
                }
            }

            // Lọc theo Tiêu chuẩn
            if (selStandard !== '' && ev.ma_tieu_chuan !== selStandard) {
                return false;
            }

            // Lọc theo Tiêu chí
            if (selCriterion !== '' && ev.ma_tieu_chi !== selCriterion) {
                return false;
            }

            // Lọc theo Minh chứng
            if (selEvidence !== '' && ev.code !== selEvidence && ev.id !== selEvidence) {
                return false;
            }

            return true;
        });

        // Cập nhật số lượng
        const count = matched.length;
        if (resultsCountBadge) resultsCountBadge.textContent = `${count} minh chứng`;
        if (liveMatchInfo) liveMatchInfo.innerHTML = `Tìm thấy <strong>${count}</strong> minh chứng phù hợp`;

        // Cập nhật Active Filter Tags (Chips)
        renderActiveFilterTags(rawKeyword, selStandard, selCriterion, selEvidence);

        // Render bảng kết quả
        if (count === 0) {
            tableContainer.style.display = 'none';
            noResultsAlert.style.display = 'block';
        } else {
            tableContainer.style.display = 'block';
            noResultsAlert.style.display = 'none';
            renderTableRows(matched, rawKeyword);
        }
    }

    // 4. RENDER CÁC HÀNG TRONG BẢNG KẾT QUẢ VỚI HIGHLIGHT
    function renderTableRows(items, keyword) {
        let html = '';
        items.forEach(item => {
            const fileName = item.file_path ? item.file_path.split('/').pop().split('\\').pop() : '';
            const isOnline = parseInt(item.status_raw) === 1;

            let displayName = escapeHtml(item.name);
            let displayCode = escapeHtml(item.code);

            if (keyword) {
                const re = new RegExp('(' + escapeRegex(keyword) + ')', 'gi');
                displayName = displayName.replace(re, '<mark class="p-0 bg-warning text-dark fw-bold">$1</mark>');
                displayCode = displayCode.replace(re, '<mark class="p-0 bg-warning text-dark fw-bold">$1</mark>');
            }

            html += `
                <tr data-id="${escapeHtml(item.id)}"
                    data-code="${escapeHtml(item.code)}"
                    data-name="${escapeHtml(item.name)}"
                    data-desc="${escapeHtml(item.description || '')}"
                    data-standard="${escapeHtml(item.ma_tieu_chuan || '')}"
                    data-criterion="${escapeHtml(item.ma_tieu_chi || '')}">
                    <td class="fw-bold text-nowrap text-primary">
                        <span class="badge bg-light text-primary border font-monospace">${displayCode}</span>
                    </td>
                    <td class="fw-semibold" style="min-width: 220px; max-width: 280px;">
                        <div class="line-clamp-2 evidence-name-text" title="${escapeHtml(item.name)}">
                            ${displayName}
                        </div>
                        ${item.description ? `<small class="text-muted line-clamp-1 d-block mt-1" title="${escapeHtml(item.description)}">${escapeHtml(item.description)}</small>` : ''}
                    </td>
                    <td style="min-width: 180px; max-width: 220px;">
                        <div class="d-flex flex-column gap-1">
                            ${item.ma_tieu_chuan ? `
                                <span class="badge bg-primary-subtle text-primary text-truncate d-inline-block text-start w-100" title="${escapeHtml((item.ma_tieu_chuan + (item.standard_name ? ' - ' + item.standard_name : '')))}">
                                    <i class="bi bi-bookmark-fill me-1"></i>${escapeHtml(item.ma_tieu_chuan + (item.standard_name ? ' - ' + item.standard_name : ''))}
                                </span>
                            ` : ''}
                            ${item.ma_tieu_chi ? `
                                <span class="badge bg-info-subtle text-dark text-truncate d-inline-block text-start w-100" title="${escapeHtml((item.ma_tieu_chi + (item.criterion_name ? ' - ' + item.criterion_name : '')))}">
                                    <i class="bi bi-list-check me-1"></i>${escapeHtml(item.ma_tieu_chi + (item.criterion_name ? ' - ' + item.criterion_name : ''))}
                                </span>
                            ` : ''}
                            ${(!item.ma_tieu_chuan && !item.ma_tieu_chi) ? `<span class="text-secondary small">Chưa phân loại</span>` : ''}
                        </div>
                    </td>
                    <td class="text-nowrap">
                        ${fileName ? `
                            <span class="badge bg-light text-dark border d-inline-flex align-items-center gap-1 text-truncate" style="max-width: 140px;" title="${escapeHtml(fileName)}">
                                <i class="bi bi-file-earmark-pdf text-danger"></i>
                                <span class="text-truncate">${escapeHtml(fileName)}</span>
                            </span>
                        ` : `<span class="text-secondary small">Không có</span>`}
                    </td>
                    <td class="text-nowrap">
                        <span class="badge bg-secondary-subtle text-secondary">${escapeHtml(item.year || '-')}</span>
                    </td>
                    <td class="text-nowrap small text-secondary">
                        <i class="bi bi-clock me-1"></i>${escapeHtml(item.updated || '-')}
                    </td>
                    <td class="text-nowrap">
                        ${isOnline 
                            ? '<span class="badge bg-success-subtle text-success border border-success-subtle">Đang hoạt động</span>'
                            : '<span class="badge bg-warning-subtle text-warning border border-warning-subtle">Ngưng áp dụng</span>'}
                    </td>
                    <td class="text-end action-cell">
                        <div class="action-buttons d-flex justify-content-end gap-1">
                            <button type="button" class="btn btn-sm btn-outline-primary btn-view-evidence" data-evidence-id="${escapeHtml(item.id)}" title="Xem chi tiết minh chứng">
                                <i class="bi bi-eye"></i>
                            </button>
                            ${item.file_path ? `
                                <a class="btn btn-sm btn-outline-success" href="<?= base_url('user/download.php?id=') ?>${encodeURIComponent(item.id)}" title="Tải tệp đính kèm về">
                                    <i class="bi bi-download"></i>
                                </a>
                            ` : ''}
                        </div>
                    </td>
                </tr>
            `;
        });
        tableBody.innerHTML = html;
        bindViewButtons();
    }

    // 5. RENDER ACTIVE FILTER TAGS (CHIPS)
    function renderActiveFilterTags(keyword, standard, criterion, evidence) {
        let tags = [];

        if (keyword) {
            tags.push({
                type: 'keyword',
                label: `Từ khóa: "${escapeHtml(keyword)}"`,
                onRemove: () => {
                    keywordInput.value = '';
                    executeFilter();
                }
            });
        }

        if (standard) {
            const stdObj = window.STANDARDS_DATA.find(s => s.id === standard);
            const stdLabel = stdObj ? `${stdObj.code} - ${stdObj.name}` : standard;
            tags.push({
                type: 'standard',
                label: `Tiêu chuẩn: ${escapeHtml(stdLabel)}`,
                onRemove: () => {
                    standardSelect.value = '';
                    updateCriteriaDropdown('');
                    updateEvidenceDropdown('', criterionSelect.value);
                    executeFilter();
                }
            });
        }

        if (criterion) {
            const crObj = window.CRITERIA_DATA.find(c => c.id === criterion);
            const crLabel = crObj ? `${crObj.code} - ${crObj.name}` : criterion;
            tags.push({
                type: 'criterion',
                label: `Tiêu chí: ${escapeHtml(crLabel)}`,
                onRemove: () => {
                    criterionSelect.value = '';
                    updateEvidenceDropdown(standardSelect.value, '');
                    executeFilter();
                }
            });
        }

        if (evidence) {
            const evObj = window.EVIDENCES_DATA.find(e => e.code === evidence || e.id === evidence);
            const evLabel = evObj ? `${evObj.code} - ${evObj.name}` : evidence;
            tags.push({
                type: 'evidence',
                label: `Minh chứng: ${escapeHtml(evLabel)}`,
                onRemove: () => {
                    evidenceSelect.value = '';
                    executeFilter();
                }
            });
        }

        if (tags.length === 0) {
            activeTagsBox.style.setProperty('display', 'none', 'important');
            tagList.innerHTML = '';
        } else {
            activeTagsBox.style.setProperty('display', 'flex', 'important');
            tagList.innerHTML = tags.map((t, idx) => `
                <span class="badge bg-primary text-white rounded-pill px-3 py-2 d-inline-flex align-items-center gap-1">
                    <span class="text-truncate" style="max-width: 250px;">${t.label}</span>
                    <i class="bi bi-x-circle-fill ms-1 cursor-pointer" role="button" data-tag-idx="${idx}" title="Xóa bộ lọc này"></i>
                </span>
            `).join('');

            // Gán sự kiện click xóa từng tag
            tagList.querySelectorAll('[data-tag-idx]').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const idx = parseInt(this.getAttribute('data-tag-idx'));
                    if (tags[idx] && tags[idx].onRemove) {
                        tags[idx].onRemove();
                    }
                });
            });
        }
    }

    // 6. GẮN SỰ KIỆN TƯƠNG TÁC TỰ ĐỘNG
    // A. Gõ từ khóa -> Lọc tự động với Debounce 200ms
    keywordInput.addEventListener('input', function() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            executeFilter();
        }, 200);
    });

    if (clearKeywordBtn) {
        clearKeywordBtn.addEventListener('click', function() {
            keywordInput.value = '';
            executeFilter();
            keywordInput.focus();
        });
    }

    // B. Chọn Tiêu chuẩn -> Cập nhật Tiêu chí & Minh chứng tương ứng, Lọc ngay lập tức
    standardSelect.addEventListener('change', function() {
        const stdVal = this.value;
        updateCriteriaDropdown(stdVal);
        updateEvidenceDropdown(stdVal, criterionSelect.value);
        executeFilter();
    });

    // C. Chọn Tiêu chí -> Tự động sync Tiêu chuẩn nếu cần, Cập nhật Minh chứng, Lọc ngay lập tức
    criterionSelect.addEventListener('change', function() {
        const crVal = this.value;
        if (crVal) {
            const crObj = window.CRITERIA_DATA.find(c => c.id === crVal);
            if (crObj && crObj.standard_id && (!standardSelect.value || standardSelect.value !== crObj.standard_id)) {
                standardSelect.value = crObj.standard_id;
                updateCriteriaDropdown(crObj.standard_id, crVal);
            }
        }
        updateEvidenceDropdown(standardSelect.value, crVal);
        executeFilter();
    });

    // D. Chọn Minh chứng -> Lọc ngay lập tức
    evidenceSelect.addEventListener('change', function() {
        const evVal = this.value;
        if (evVal) {
            const evObj = window.EVIDENCES_DATA.find(e => e.code === evVal || e.id === evVal);
            if (evObj) {
                if (evObj.ma_tieu_chuan && (!standardSelect.value || standardSelect.value !== evObj.ma_tieu_chuan)) {
                    standardSelect.value = evObj.ma_tieu_chuan;
                    updateCriteriaDropdown(evObj.ma_tieu_chuan, evObj.ma_tieu_chi);
                }
                if (evObj.ma_tieu_chi && (!criterionSelect.value || criterionSelect.value !== evObj.ma_tieu_chi)) {
                    criterionSelect.value = evObj.ma_tieu_chi;
                }
            }
        }
        executeFilter();
    });

    // E. Nút Đặt lại toàn bộ
    function resetAllFilters() {
        keywordInput.value = '';
        standardSelect.value = '';
        updateCriteriaDropdown('');
        criterionSelect.value = '';
        updateEvidenceDropdown('', '');
        evidenceSelect.value = '';
        executeFilter();
    }

    if (btnResetAll) btnResetAll.addEventListener('click', resetAllFilters);
    if (btnResetEmpty) btnResetEmpty.addEventListener('click', resetAllFilters);

    // 7. XỬ LÝ MODAL XEM CHI TIẾT MINH CHỨNG
    const detailModalEl = document.getElementById('evidenceDetailModal');
    let detailModal = null;

    function bindViewButtons() {
        document.querySelectorAll('.btn-view-evidence').forEach(btn => {
            btn.addEventListener('click', function() {
                const id = this.getAttribute('data-evidence-id');
                const item = window.EVIDENCES_DATA.find(e => e.id === id || e.code === id);
                if (!item) return;

                document.getElementById('modalEvidenceCodeSubtitle').textContent = `Mã: ${item.code}`;
                document.getElementById('modalCode').value = item.code || '';
                document.getElementById('modalName').value = item.name || '';
                document.getElementById('modalYear').value = item.year || '-';
                document.getElementById('modalDesc').value = item.description || 'Chưa có mô tả chi tiết';
                document.getElementById('modalStandard').value = item.ma_tieu_chuan ? (item.ma_tieu_chuan + (item.standard_name ? ' - ' + item.standard_name : '')) : 'Chưa phân loại';
                document.getElementById('modalCriterion').value = item.ma_tieu_chi ? (item.ma_tieu_chi + (item.criterion_name ? ' - ' + item.criterion_name : '')) : 'Chưa phân loại';
                document.getElementById('modalUpdated').value = item.updated || '-';
                document.getElementById('modalUser').value = item.user_name || 'Hệ thống';

                const statusInput = document.getElementById('modalStatus');
                const isOnline = parseInt(item.status_raw) === 1;
                statusInput.value = isOnline ? 'Đang hoạt động' : 'Ngưng áp dụng';
                statusInput.className = `form-control bg-light fw-bold ${isOnline ? 'text-success' : 'text-warning'}`;

                const fileName = item.file_path ? item.file_path.split('/').pop().split('\\').pop() : '';
                document.getElementById('modalFileName').value = fileName || 'Không có tệp đính kèm';

                const downloadBtn = document.getElementById('modalDownloadBtn');
                if (item.file_path) {
                    downloadBtn.style.display = 'inline-flex';
                    downloadBtn.href = `<?= base_url('user/download.php?id=') ?>${encodeURIComponent(item.id)}`;
                } else {
                    downloadBtn.style.display = 'none';
                }

                if (!detailModal && window.bootstrap && window.bootstrap.Modal) {
                    detailModal = new bootstrap.Modal(detailModalEl);
                }
                if (detailModal) {
                    detailModal.show();
                }
            });
        });
    }

    // Helper escape chuỗi an toàn
    function escapeHtml(text) {
        if (!text) return '';
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.toString().replace(/[&<>"']/g, m => map[m]);
    }

    function escapeRegex(string) {
        return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }

    // Khởi tạo ban đầu
    bindViewButtons();
    if (standardSelect.value) {
        updateCriteriaDropdown(standardSelect.value, criterionSelect.value);
        updateEvidenceDropdown(standardSelect.value, criterionSelect.value, evidenceSelect.value);
    }
    renderActiveFilterTags(keywordInput.value.trim(), standardSelect.value, criterionSelect.value, evidenceSelect.value);
})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
