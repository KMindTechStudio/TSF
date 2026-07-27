<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/helpers.php';
require_roles(['admin']);
require_once __DIR__ . '/../config/database.php';

$pdo = db();
$success = '';
$error = '';
$maxUploadMb = 100;
$maxUploadBytes = $maxUploadMb * 1024 * 1024;
$allowedExtensions = ['zip', 'pdf', 'docx', 'xlsx', 'jpg', 'jpeg', 'png'];
$maxSizeLimitsMb = [
    'zip'  => 100,
    'pdf'  => 50,
    'docx' => 20,
    'xlsx' => 20,
    'jpg'  => 10,
    'jpeg' => 10,
    'png'  => 10,
];
$approvalOptions = [
    'approved' => 'Đã duyệt',
    'reviewing' => 'Chờ rà soát',
    'need_update' => 'Cần bổ sung',
];

function ensure_evidence_file_version_column(PDO $pdo): void
{
    static $checked = false;

    if ($checked) {
        return;
    }

    $column = $pdo->query("SHOW COLUMNS FROM file_minh_chung LIKE 'so_phien_ban'")->fetch();
    if (!$column) {
        $pdo->exec('ALTER TABLE file_minh_chung ADD COLUMN so_phien_ban INT NOT NULL DEFAULT 1');
    }

    $checked = true;
}

function validate_academic_year(string $year): bool
{
    if (!preg_match('/^(\d{4})-(\d{4})$/', $year, $matches)) {
        return false;
    }
    $startYear = (int) $matches[1];
    $endYear = (int) $matches[2];
    return $endYear === ($startYear + 1);
}

function store_evidence_file(PDO $pdo, int $evidenceId, string $code, array $file, int $userId, array $allowedExtensions, ?int $requestedVersion = null): int
{
    global $maxSizeLimitsMb, $maxUploadMb;
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $maxMb = $maxSizeLimitsMb[$extension] ?? $maxUploadMb;
    $maxBytes = $maxMb * 1024 * 1024;

    if (!in_array($extension, $allowedExtensions, true)) {
        throw new RuntimeException('Định dạng file chưa được hỗ trợ. Chỉ nhận ZIP, PDF, DOCX, XLSX, JPG, PNG.');
    }
    if ((int) $file['size'] > $maxBytes) {
        throw new RuntimeException('Dung lượng tệp .' . strtoupper($extension) . ' vượt quá giới hạn tối đa (' . $maxMb . 'MB).');
    }

    ensure_evidence_file_version_column($pdo);

    if ($requestedVersion !== null && $requestedVersion > 0) {
        $versionNo = $requestedVersion;
    } else {
        $versionStmt = $pdo->prepare('SELECT COALESCE(MAX(so_phien_ban), 0) + 1 FROM file_minh_chung WHERE id_minh_chung = :evidence_id');
        $versionStmt->execute(['evidence_id' => $evidenceId]);
        $versionNo = (int) $versionStmt->fetchColumn();
    }

    $uploadDir = realpath(__DIR__ . '/../uploads/evidences');
    if ($uploadDir === false) {
        $uploadDir = __DIR__ . '/../uploads/evidences';
        mkdir($uploadDir, 0777, true);
    }

    $safeCode = preg_replace('/[^A-Za-z0-9_\\-]/', '_', $code);
    $storedName = $safeCode . '_v' . $versionNo . '_' . date('YmdHis') . '.' . $extension;
    $targetPath = rtrim($uploadDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $storedName;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        throw new RuntimeException('Không thể lưu file upload vào thư mục hệ thống.');
    }

    $relativePath = 'uploads/evidences/' . $storedName;
    $fileStmt = $pdo->prepare("
        INSERT INTO file_minh_chung (id_minh_chung, ten_goc, ten_luu_tru, duong_dan, loai_file, kich_thuoc, so_phien_ban, nguoi_tai_len)
        VALUES (:evidence_id, :original_name, :stored_name, :file_path, :file_type, :file_size, :version_no, :uploaded_by)
    ");
    $fileStmt->execute([
        'evidence_id' => $evidenceId,
        'original_name' => $file['name'],
        'stored_name' => $storedName,
        'file_path' => $relativePath,
        'file_type' => strtoupper($extension),
        'file_size' => (int) $file['size'],
        'version_no' => $versionNo,
        'uploaded_by' => $userId,
    ]);

    return $versionNo;
}

ensure_evidence_file_version_column($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_evidence_status') {
    $evidenceId = (int) ($_POST['id'] ?? 0);
    $status = $_POST['approval_status'] ?? '';
    $userId = $_SESSION['user_id'] ?? 1;

    try {
        if ($evidenceId <= 0 || !array_key_exists($status, $approvalOptions)) {
            throw new RuntimeException('Trạng thái minh chứng không hợp lệ.');
        }

        $stmt = $pdo->prepare('UPDATE minh_chung SET trang_thai_duyet = :status, ngay_cap_nhat = NOW() WHERE id = :id');
        $stmt->execute([
            'status' => $status,
            'id' => $evidenceId,
        ]);

        $logStmt = $pdo->prepare("
            INSERT INTO audit_logs (id_nguoi_dung, hanh_dong, phan_he, ten_ban_ghi, id_ban_ghi, gia_tri_moi, dia_chi_ip)
            VALUES (:user_id, 'cap_nhat_trang_thai', 'minh_chung', :record_name, :record_id, JSON_OBJECT('trang_thai_duyet', :status), :ip_address)
        ");
        $logStmt->execute([
            'user_id'     => $userId,
            'record_name' => 'Minh chứng #' . $evidenceId,
            'record_id'   => $evidenceId,
            'status'      => $status,
            'ip_address'  => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
        ]);

        $success = 'Cập nhật trạng thái minh chứng thành công.';
    } catch (Throwable $exception) {
        $error = 'Không thể cập nhật trạng thái: ' . $exception->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_evidence') {
    $evidenceId = (int) ($_POST['id'] ?? 0);

    try {
        $pdo->beginTransaction();

        $fileStmt = $pdo->prepare('SELECT id, duong_dan AS file_path FROM file_minh_chung WHERE id_minh_chung = :id');
        $fileStmt->execute(['id' => $evidenceId]);
        $files = $fileStmt->fetchAll();
        $fileIds = array_column($files, 'id');

        if ($fileIds) {
            $placeholders = implode(',', array_fill(0, count($fileIds), '?'));
            $pdo->prepare("DELETE FROM download_logs WHERE id_file_minh_chung IN ($placeholders)")->execute($fileIds);
        }

        $evStmt = $pdo->prepare('SELECT ma_minh_chung FROM minh_chung WHERE id = :id');
        $evStmt->execute(['id' => $evidenceId]);
        $evCode = $evStmt->fetchColumn() ?: ('#' . $evidenceId);

        $pdo->prepare('DELETE FROM minh_chung_tieu_chi WHERE id_minh_chung = :id')->execute(['id' => $evidenceId]);
        $pdo->prepare('DELETE FROM file_minh_chung WHERE id_minh_chung = :id')->execute(['id' => $evidenceId]);
        $pdo->prepare('DELETE FROM audit_logs WHERE phan_he = :module AND id_ban_ghi = :id')->execute(['module' => 'minh_chung', 'id' => $evidenceId]);
        $pdo->prepare('DELETE FROM minh_chung WHERE id = :id')->execute(['id' => $evidenceId]);

        log_activity('xoa', 'minh_chung', $evidenceId, $evCode);

        $pdo->commit();

        foreach ($files as $fileRow) {
            $fullPath = realpath(__DIR__ . '/../' . $fileRow['file_path']);
            if ($fullPath && strpos($fullPath, realpath(__DIR__ . '/../uploads/evidences')) === 0 && is_file($fullPath)) {
                @unlink($fullPath);
            }
        }

        $success = 'Xóa minh chứng thành công.';
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = 'Không thể xóa minh chứng: ' . $exception->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_evidence') {
    $evidenceId = (int) ($_POST['id'] ?? 0);
    $code = trim($_POST['code'] ?? '');
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $academicYear = trim($_POST['academic_year'] ?? '');
    $issuedDate = trim($_POST['issued_date'] ?? '');
    $evidenceType = trim($_POST['evidence_type'] ?? 'Minh chứng chính');
    $departmentId = (int) ($_POST['department_id'] ?? 0) ?: null;
    $criteriaIds = array_map('intval', $_POST['criteria_ids'] ?? []);
    $versionNo = (int) ($_POST['version_no'] ?? 0);
    $userId = $_SESSION['user_id'] ?? 1;
    $file = $_FILES['evidence_file'] ?? null;
    $extension = $file && !empty($file['name']) ? strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) : '';

    $extMaxMb = $extension ? ($maxSizeLimitsMb[$extension] ?? $maxUploadMb) : $maxUploadMb;
    $extMaxBytes = $extMaxMb * 1024 * 1024;

    if ($evidenceId <= 0 || $code === '' || $title === '' || $academicYear === '' || empty($criteriaIds)) {
        $error = 'Vui lòng nhập đầy đủ mã, tên, năm học và tiêu chí.';
    } elseif (!validate_academic_year($academicYear)) {
        $error = 'Năm học phải đúng định dạng YYYY-YYYY (Ví dụ: 2025-2026, 2026-2027).';
    } elseif ($versionNo <= 0) {
        $error = 'Vui lòng nhập phiên bản minh chứng hợp lệ.';
    } elseif ($file && $file['error'] !== UPLOAD_ERR_NO_FILE && $file['error'] !== UPLOAD_ERR_OK) {
        $error = 'Upload file không thành công. Vui lòng thử lại.';
    } elseif ($file && $file['error'] === UPLOAD_ERR_OK && !in_array($extension, $allowedExtensions, true)) {
        $error = 'Định dạng file chưa được hỗ trợ. Chỉ nhận ZIP, PDF, DOCX, XLSX, JPG, PNG.';
    } elseif ($file && $file['error'] === UPLOAD_ERR_OK && (int) $file['size'] > $extMaxBytes) {
        $error = 'Tệp .' . strtoupper($extension) . ' vượt quá dung lượng tối đa cho phép (' . $extMaxMb . 'MB).';
    } else {
        try {
            $pdo->beginTransaction();

            $check = $pdo->prepare('SELECT id FROM minh_chung WHERE LOWER(ma_minh_chung) = LOWER(:code) AND id <> :id LIMIT 1');
            $check->execute(['code' => $code, 'id' => $evidenceId]);
            if ($check->fetch()) {
                throw new RuntimeException('Mã minh chứng "' . $code . '" đã tồn tại trong hệ thống. Vui lòng nhập mã khác.');
            }

            $stmt = $pdo->prepare("
                UPDATE minh_chung
                SET ma_minh_chung = :code,
                    tieu_de = :title,
                    mo_ta = :description,
                    nam_hoc = :academic_year,
                    ngay_ban_hanh = :issued_date,
                    loai_minh_chung = :evidence_type,
                    id_don_vi_phu_trach = :department_id,
                    id_nguoi_phu_trach = :user_id
                WHERE id = :id
            ");
            $stmt->execute([
                'code' => $code,
                'title' => $title,
                'description' => $description,
                'academic_year' => $academicYear,
                'issued_date' => $issuedDate !== '' ? $issuedDate : null,
                'evidence_type' => $evidenceType !== '' ? $evidenceType : 'Minh chứng chính',
                'department_id' => $departmentId,
                'user_id' => $userId,
                'id' => $evidenceId,
            ]);

            $pdo->prepare('DELETE FROM minh_chung_tieu_chi WHERE id_minh_chung = :id')->execute(['id' => $evidenceId]);
            $linkStmt = $pdo->prepare('INSERT INTO minh_chung_tieu_chi (id_minh_chung, id_tieu_chi) VALUES (:evidence_id, :criteria_id)');
            foreach (array_unique($criteriaIds) as $criteriaId) {
                $linkStmt->execute([
                    'evidence_id' => $evidenceId,
                    'criteria_id' => $criteriaId,
                ]);
            }

            $newVersion = null;
            if ($file && $file['error'] === UPLOAD_ERR_OK) {
                $newVersion = store_evidence_file($pdo, $evidenceId, $code, $file, $userId, $allowedExtensions, $versionNo);
            } else {
                $latestFileStmt = $pdo->prepare('
                    UPDATE file_minh_chung
                    SET so_phien_ban = :version_no
                    WHERE id = (
                        SELECT latest_id FROM (
                            SELECT id AS latest_id
                            FROM file_minh_chung
                            WHERE id_minh_chung = :evidence_id
                            ORDER BY so_phien_ban DESC, ngay_tai_len DESC, id DESC
                            LIMIT 1
                        ) latest_file
                    )
                ');
                $latestFileStmt->execute([
                    'version_no' => $versionNo,
                    'evidence_id' => $evidenceId,
                ]);
            }

            $pdo->commit();
            log_activity('cap_nhat', 'minh_chung', $evidenceId, $code . ' - ' . $title);
            $success = $newVersion
                ? 'Cập nhật minh chứng thành công. Tệp mới đã được lưu là phiên bản v' . $newVersion . '.'
                : 'Cập nhật minh chứng thành công.';
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = $exception->getMessage();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_evidence') {
    $code = trim($_POST['code'] ?? '');
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $academicYear = trim($_POST['academic_year'] ?? '');
    $issuedDate = trim($_POST['issued_date'] ?? '');
    $evidenceType = trim($_POST['evidence_type'] ?? 'Minh chứng chính');
    $departmentId = (int) ($_POST['department_id'] ?? 0) ?: null;
    $criteriaIds = array_map('intval', $_POST['criteria_ids'] ?? []);
    $versionNo = (int) ($_POST['version_no'] ?? 1);
    $userId = $_SESSION['user_id'] ?? 1;

    $file = $_FILES['evidence_file'] ?? null;
    $extension = $file && !empty($file['name']) ? strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) : '';

    $extMaxMb = $extension ? ($maxSizeLimitsMb[$extension] ?? $maxUploadMb) : $maxUploadMb;
    $extMaxBytes = $extMaxMb * 1024 * 1024;

    if ($code === '' || $title === '' || $academicYear === '' || empty($criteriaIds)) {
        $error = 'Vui lòng nhập đầy đủ mã, tên, năm học và tiêu chí.';
    } elseif (!validate_academic_year($academicYear)) {
        $error = 'Năm học phải đúng định dạng YYYY-YYYY (Ví dụ: 2025-2026, 2026-2027).';
    } elseif ($versionNo <= 0) {
        $error = 'Vui lòng nhập phiên bản minh chứng hợp lệ.';
    } elseif (!$file || $file['error'] === UPLOAD_ERR_NO_FILE) {
        $error = 'Vui lòng chọn file minh chứng để upload.';
    } elseif ($file['error'] !== UPLOAD_ERR_OK) {
        $error = 'Upload file không thành công. Vui lòng thử lại.';
    } elseif (!in_array($extension, $allowedExtensions, true)) {
        $error = 'Định dạng file chưa được hỗ trợ. Chỉ nhận ZIP, PDF, DOCX, XLSX, JPG, PNG.';
    } elseif ((int) $file['size'] > $extMaxBytes) {
        $error = 'Tệp .' . strtoupper($extension) . ' vượt quá dung lượng tối đa cho phép (' . $extMaxMb . 'MB).';
    } else {
        try {
            $pdo->beginTransaction();

            $check = $pdo->prepare('SELECT id FROM minh_chung WHERE LOWER(ma_minh_chung) = LOWER(:code) LIMIT 1');
            $check->execute(['code' => $code]);
            if ($check->fetch()) {
                throw new RuntimeException('Mã minh chứng "' . $code . '" đã tồn tại trong hệ thống. Vui lòng nhập mã khác.');
            }

            $stmt = $pdo->prepare("
                INSERT INTO minh_chung (
                    ma_minh_chung,
                    tieu_de,
                    mo_ta,
                    nam_hoc,
                    ngay_ban_hanh,
                    loai_minh_chung,
                    id_don_vi_phu_trach,
                    id_nguoi_phu_trach,
                    trang_thai_duyet
                ) VALUES (
                    :code,
                    :title,
                    :description,
                    :academic_year,
                    :issued_date,
                    :evidence_type,
                    :department_id,
                    :user_id,
                    'reviewing'
                )
            ");
            $stmt->execute([
                'code' => $code,
                'title' => $title,
                'description' => $description,
                'academic_year' => $academicYear,
                'issued_date' => $issuedDate !== '' ? $issuedDate : null,
                'evidence_type' => $evidenceType !== '' ? $evidenceType : 'Minh chứng chính',
                'department_id' => $departmentId,
                'user_id' => $userId,
            ]);

            $evidenceId = (int) $pdo->lastInsertId();

            store_evidence_file($pdo, $evidenceId, $code, $file, $userId, $allowedExtensions, $versionNo);

            $linkStmt = $pdo->prepare('INSERT INTO minh_chung_tieu_chi (id_minh_chung, id_tieu_chi) VALUES (:evidence_id, :criteria_id)');
            foreach (array_unique($criteriaIds) as $criteriaId) {
                $linkStmt->execute([
                    'evidence_id' => $evidenceId,
                    'criteria_id' => $criteriaId,
                ]);
            }

            $logStmt = $pdo->prepare("
                INSERT INTO audit_logs (id_nguoi_dung, hanh_dong, phan_he, ten_ban_ghi, id_ban_ghi, gia_tri_moi, dia_chi_ip)
                VALUES (:user_id, 'them_moi', 'minh_chung', :record_name, :record_id, JSON_OBJECT('ma_minh_chung', :code), :ip_address)
            ");
            $logStmt->execute([
                'user_id'     => $userId,
                'record_name' => $code . ' - ' . $title,
                'record_id'   => $evidenceId,
                'code'        => $code,
                'ip_address'  => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            ]);

            $pdo->commit();
            $success = 'Upload và lưu minh chứng thành công.';
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = $exception->getMessage();
        }
    }
}
if ($success) {
    unset($_GET['create'], $_GET['edit']);
}

require_once __DIR__ . '/../includes/data.php';

$isCreatingEvidence = isset($_GET['create']);
$editId = $isCreatingEvidence ? 0 : (int) ($_GET['edit'] ?? 0);
$editingEvidence = null;
$editingCriteriaIds = [];
$editingLatestFile = null;
$editingVersionNo = 1;
if ($editId > 0) {
    $stmt = $pdo->prepare('SELECT *, ma_minh_chung AS code, tieu_de AS title, mo_ta AS description, nam_hoc AS academic_year, ngay_ban_hanh AS issued_date, loai_minh_chung AS evidence_type, id_don_vi_phu_trach AS issuing_department_id, id_nguoi_phu_trach AS responsible_user_id, trang_thai_duyet AS approval_status FROM minh_chung WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $editId]);
    $editingEvidence = $stmt->fetch();

    $stmt = $pdo->prepare('SELECT id_tieu_chi AS criteria_id FROM minh_chung_tieu_chi WHERE id_minh_chung = :id');
    $stmt->execute(['id' => $editId]);
    $editingCriteriaIds = array_map('intval', array_column($stmt->fetchAll(), 'criteria_id'));

    $stmt = $pdo->prepare('
        SELECT id, ten_goc AS original_name, loai_file AS file_type, kich_thuoc AS file_size, so_phien_ban AS version_no, ngay_tai_len AS uploaded_at
        FROM file_minh_chung
        WHERE id_minh_chung = :id
        ORDER BY so_phien_ban DESC, ngay_tai_len DESC, id DESC
        LIMIT 1
    ');
    $stmt->execute(['id' => $editId]);
    $editingLatestFile = $stmt->fetch();
    $editingVersionNo = (int) ($editingLatestFile['version_no'] ?? 1);
}

$searchKeyword = trim($_GET['q'] ?? '');
$selectedYear = trim($_GET['year'] ?? '');
$selectedStandard = trim($_GET['standard'] ?? '');
$selectedStatus = trim($_GET['status'] ?? '');

$evidenceYears = array_values(array_unique(array_filter(array_column($evidences, 'year'))));
sort($evidenceYears);
$evidenceStatuses = array_values(array_unique(array_filter(array_column($evidences, 'status'))));

$filteredEvidences = array_values(array_filter($evidences, function ($item) use ($searchKeyword, $selectedYear, $selectedStandard, $selectedStatus) {
    $haystack = implode(' ', [
        $item['code'] ?? '',
        $item['name'] ?? '',
        $item['criteria'] ?? '',
        $item['standards'] ?? '',
        $item['year'] ?? '',
        $item['type'] ?? '',
        $item['status'] ?? '',
    ]);

    if ($searchKeyword !== '' && !search_contains($haystack, $searchKeyword)) {
        return false;
    }

    if ($selectedYear !== '' && ($item['year'] ?? '') !== $selectedYear) {
        return false;
    }

    if ($selectedStandard !== '' && !search_contains($item['standards'] ?? '', $selectedStandard)) {
        return false;
    }

    if ($selectedStatus !== '' && ($item['status'] ?? '') !== $selectedStatus) {
        return false;
    }

    return true;
}));
$pageTitle = page_title('Quản lý minh chứng');
$heading = 'Quản lý hồ sơ minh chứng';
include __DIR__ . '/../includes/header.php';
?>
<?php if (($editingEvidence || $isCreatingEvidence) && !$success && !$error): ?><script>document.body.dataset.autoOpenModal = 'evidenceFormModal';</script><?php endif; ?>
<div class="panel mb-4">
    <form class="row g-3 align-items-end" method="get">
        <div class="col-xl-4 col-lg-4 col-md-4">
            <label class="form-label">Mã/Tên minh chứng</label>
            <input class="form-control" name="q" value="<?= htmlspecialchars($searchKeyword) ?>" placeholder="VD: MC.01.01.01">
        </div>
        <div class="col-xl-2 col-lg-2 col-md-4">
            <label class="form-label">Năm học</label>
            <select class="form-select" name="year">
                <option value="">Tất cả</option>
                <?php foreach ($evidenceYears as $year): ?>
                    <option value="<?= htmlspecialchars($year) ?>" <?= $selectedYear === $year ? 'selected' : '' ?>><?= htmlspecialchars($year) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-xl-2 col-lg-2 col-md-4">
            <label class="form-label">Tiêu chuẩn</label>
            <select class="form-select" name="standard">
                <option value="">Tất cả</option>
                <?php foreach ($standards as $standard): ?>
                    <option value="<?= htmlspecialchars($standard['code']) ?>" <?= $selectedStandard === $standard['code'] ? 'selected' : '' ?>><?= htmlspecialchars($standard['code']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-xl-2 col-lg-2 col-md-4">
            <label class="form-label">Trạng thái</label>
            <select class="form-select" name="status">
                <option value="">Tất cả</option>
                <?php foreach ($evidenceStatuses as $status): ?>
                    <option value="<?= htmlspecialchars($status) ?>" <?= $selectedStatus === $status ? 'selected' : '' ?>><?= htmlspecialchars($status) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-xl-2 col-lg-2 col-md-4">
            <button class="btn btn-primary w-100 text-nowrap" type="submit"><i class="bi bi-search me-1"></i> Tìm kiếm</button>
        </div>
    </form>
</div>

<?php if ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="row g-4 evidences-layout">
    <div class="col-12">
        <div class="panel evidences-list-panel">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="h5 mb-0">Danh mục minh chứng</h2>
                <a class="btn btn-primary" href="<?= base_url('admin/evidences.php?create=1') ?>"><i class="bi bi-plus-circle me-1"></i> Thêm mới</a>
            </div>
            <div class="text-secondary mb-3"><?= count($filteredEvidences) ?> minh chứng phù hợp</div>
            <div class="table-responsive">
                <table class="table" data-page-size="10" data-row-height="62" data-column-filters>
                    <thead>
                    <tr>
                        <th>
                            <span class="column-filter-head">
                                <span>Mã minh chứng</span>
                                <button class="column-filter-toggle" type="button" data-column-filter-toggle title="Lọc mã"><i class="bi bi-funnel"></i></button>
                                <span class="column-filter-menu"><input class="form-control form-control-sm" data-column-filter="0" placeholder="Lọc mã"></span>
                            </span>
                        </th>
                        <th>
                            <span class="column-filter-head">
                                <span>Tên minh chứng</span>
                                <button class="column-filter-toggle" type="button" data-column-filter-toggle title="Lọc tên"><i class="bi bi-funnel"></i></button>
                                <span class="column-filter-menu"><input class="form-control form-control-sm" data-column-filter="1" placeholder="Lọc tên"></span>
                            </span>
                        </th>
                        <th>
                            <span class="column-filter-head">
                                <span>Mô tả</span>
                                <button class="column-filter-toggle" type="button" data-column-filter-toggle title="Lọc mô tả"><i class="bi bi-funnel"></i></button>
                                <span class="column-filter-menu"><input class="form-control form-control-sm" data-column-filter="2" placeholder="Lọc mô tả"></span>
                            </span>
                        </th>
                        <th>
                            <span class="column-filter-head">
                                <span>Năm học</span>
                                <button class="column-filter-toggle" type="button" data-column-filter-toggle title="Lọc năm học"><i class="bi bi-funnel"></i></button>
                                <span class="column-filter-menu"><input class="form-control form-control-sm" data-column-filter="3" placeholder="Lọc năm"></span>
                            </span>
                        </th>
                        <th>
                            <span class="column-filter-head">
                                <span>Ngày ban hành</span>
                                <button class="column-filter-toggle" type="button" data-column-filter-toggle title="Lọc ngày ban hành"><i class="bi bi-funnel"></i></button>
                                <span class="column-filter-menu"><input class="form-control form-control-sm" data-column-filter="4" placeholder="Lọc ngày"></span>
                            </span>
                        </th>
                        <th>
                            <span class="column-filter-head">
                                <span>Thuộc tiêu chí</span>
                                <button class="column-filter-toggle" type="button" data-column-filter-toggle title="Lọc tiêu chí"><i class="bi bi-funnel"></i></button>
                                <span class="column-filter-menu"><input class="form-control form-control-sm" data-column-filter="5" placeholder="Lọc tiêu chí"></span>
                            </span>
                        </th>
                        <th>
                            <span class="column-filter-head">
                                <span>Loại minh chứng</span>
                                <button class="column-filter-toggle" type="button" data-column-filter-toggle title="Lọc loại minh chứng"><i class="bi bi-funnel"></i></button>
                                <span class="column-filter-menu"><input class="form-control form-control-sm" data-column-filter="6" placeholder="Lọc loại"></span>
                            </span>
                        </th>
                        <th>
                            <span class="column-filter-head">
                                <span>File đính kèm</span>
                                <button class="column-filter-toggle" type="button" data-column-filter-toggle title="Lọc file"><i class="bi bi-funnel"></i></button>
                                <span class="column-filter-menu"><input class="form-control form-control-sm" data-column-filter="7" placeholder="Lọc file"></span>
                            </span>
                        </th>
                        <th>Trạng thái</th>
                        <th class="text-end">Thao tác</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($filteredEvidences as $item): ?>
                        <tr>
                            <td class="fw-bold"><?= htmlspecialchars($item['code']) ?></td>
                            <td style="max-width: 250px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?= htmlspecialchars($item['name']) ?>">
                                <?= htmlspecialchars($item['name']) ?>
                            </td>
                            <td style="max-width: 250px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?= htmlspecialchars($item['description']) ?>">
                                <?= htmlspecialchars($item['description']) ?>
                            </td>
                            <td><?= htmlspecialchars($item['year']) ?></td>
                            <td><?= htmlspecialchars($item['issued_date'] ? date('d/m/Y', strtotime($item['issued_date'])) : '') ?></td>
                            <td><?= htmlspecialchars($item['criteria']) ?></td>
                            <td><?= htmlspecialchars($item['evidence_type']) ?></td>
                            <td><span class="badge text-bg-light text-dark"><?= htmlspecialchars($item['type']) ?></span></td>
                            <td>
                                <form method="post" class="status-update-form">
                                    <input type="hidden" name="action" value="update_evidence_status">
                                    <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                                    <select class="form-select form-select-sm status-select status-select-<?= htmlspecialchars($item['status_raw'] ?? 'reviewing') ?>" name="approval_status" onchange="this.form.submit()" aria-label="Cập nhật trạng thái minh chứng">
                                        <?php foreach ($approvalOptions as $value => $label): ?>
                                            <option value="<?= htmlspecialchars($value) ?>" <?= ($item['status_raw'] ?? '') === $value ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($label) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </form>
                            </td>
                            <td class="text-end action-cell">
                                <div class="action-buttons">
                                    <?php if (!empty($item['file_id'])): ?>
                                        <a class="btn btn-sm btn-outline-secondary" href="<?= base_url('user/view.php?id=' . (int) $item['file_id']) ?>" target="_blank" rel="noopener" title="Xem minh chứng"><i class="bi bi-eye"></i></a>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-outline-secondary" type="button" disabled><i class="bi bi-eye"></i></button>
                                    <?php endif; ?>
                                    <a class="btn btn-sm btn-outline-primary" href="?edit=<?= $item['id'] ?>"><i class="bi bi-pencil"></i></a>
                                    <form method="post" class="d-inline" data-confirm-form="Bạn chắc chắn muốn xóa minh chứng này?">
                                        <input type="hidden" name="action" value="delete_evidence">
                                        <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                        <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="column-filter-empty-row" hidden>
                        <td colspan="10" class="text-center text-secondary">Không tìm thấy minh chứng phù hợp với bộ lọc.</td>
                    </tr>
                    <?php if (!$filteredEvidences): ?>
                        <tr>
                            <td colspan="10" class="text-center text-secondary">Không tìm thấy minh chứng phù hợp.</td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade management-form-modal" id="evidenceFormModal" tabindex="-1" aria-labelledby="evidenceFormModalLabel" aria-hidden="true" <?= ($editingEvidence || $isCreatingEvidence) ? 'data-auto-open-modal' : '' ?>>
    <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5" id="evidenceFormModalLabel"><?= $editingEvidence ? 'Sửa minh chứng' : 'Thêm mới minh chứng' ?></h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <div class="modal-body">
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="<?= $editingEvidence ? 'update_evidence' : 'create_evidence' ?>">
                <input type="hidden" name="id" value="<?= (int) ($editingEvidence['id'] ?? 0) ?>">

                <div class="mb-3">
                    <label class="form-label">Mã minh chứng</label>
                    <input class="form-control" name="code" value="<?= htmlspecialchars($editingEvidence['code'] ?? '') ?>" placeholder="MC.06.01.01" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Tên minh chứng</label>
                    <textarea class="form-control" name="title" rows="3" required><?= htmlspecialchars($editingEvidence['title'] ?? '') ?></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Mô tả</label>
                    <textarea class="form-control" name="description" rows="2"><?= htmlspecialchars($editingEvidence['description'] ?? '') ?></textarea>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Năm học <span class="text-danger">*</span></label>
                        <input class="form-control" name="academic_year" value="<?= htmlspecialchars($editingEvidence['academic_year'] ?? '') ?>" placeholder="Ví dụ: 2025-2026" pattern="\d{4}-\d{4}" title="Định dạng: YYYY-YYYY (ví dụ: 2025-2026)" required>
                        <div class="form-text">Định dạng chuẩn: YYYY-YYYY (VD: 2025-2026)</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Ngày ban hành</label>
                        <input class="form-control" name="issued_date" value="<?= htmlspecialchars($editingEvidence['issued_date'] ?? '') ?>" type="date">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Thuộc tiêu chí</label>
                    <input class="form-control form-control-sm mb-2" type="search" data-criteria-search placeholder="Nhập mã hoặc tên tiêu chí">
                    <select class="form-select" name="criteria_ids[]" multiple size="5" required data-criteria-select>
                        <?php foreach ($criteria as $item): ?>
                            <option value="<?= $item['id'] ?>" <?= in_array((int) $item['id'], $editingCriteriaIds, true) ? 'selected' : '' ?>><?= htmlspecialchars($item['code'] . ' - ' . $item['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">Giữ Ctrl để chọn nhiều tiêu chí.</div>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-md-12">
                        <label class="form-label">Loại minh chứng</label>
                        <select class="form-select" name="evidence_type">
                            <?php $currentEvType = $editingEvidence['evidence_type'] ?? 'Minh chứng chính'; ?>
                            <option value="Minh chứng chính" <?= $currentEvType === 'Minh chứng chính' ? 'selected' : '' ?>>Minh chứng chính</option>
                            <option value="Minh chứng bổ sung" <?= $currentEvType === 'Minh chứng bổ sung' ? 'selected' : '' ?>>Minh chứng bổ sung</option>
                            <option value="Minh chứng tham khảo" <?= $currentEvType === 'Minh chứng tham khảo' ? 'selected' : '' ?>>Minh chứng tham khảo</option>
                        </select>
                    </div>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-md-8">
                        <label class="form-label">File đính kèm</label>
                        <input class="form-control" id="evidenceFile" name="evidence_file" type="file" accept=".zip,.pdf,.docx,.xlsx,.jpg,.jpeg,.png" <?= $editingEvidence ? '' : 'required' ?> title="Chỉ hỗ trợ ZIP, PDF, DOCX, XLSX, JPG, PNG">
                        <?php if ($editingLatestFile): ?>
                            <div class="small text-secondary mt-2">
                                Phiên bản hiện tại: v<?= (int) $editingLatestFile['version_no'] ?> · <?= htmlspecialchars($editingLatestFile['original_name']) ?> · <?= htmlspecialchars($editingLatestFile['file_type']) ?>
                            </div>
                        <?php endif; ?>
                        <div class="form-text mt-1">
                            <?= $editingEvidence ? 'Chọn tệp mới nếu cần cập nhật phiên bản minh chứng. <br>' : '' ?>
                            <strong>Giới hạn dung lượng:</strong> ZIP (100MB) | PDF (50MB) | DOCX / XLSX (20MB) | JPG / PNG (10MB)
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Phiên bản</label>
                        <input class="form-control" name="version_no" value="<?= (int) ($editingEvidence ? $editingVersionNo : 1) ?>" type="number" min="1" step="1" required>
                    </div>
                </div>
                <div class="d-flex gap-2 justify-content-end">
                    <?php if ($editingEvidence): ?><a class="btn btn-outline-secondary" href="<?= base_url('admin/evidences.php') ?>">Hủy sửa</a><?php else: ?><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Hủy</button><?php endif; ?>
                    <button class="btn btn-primary" type="submit">
                        <i class="bi bi-save me-1"></i> Lưu minh chứng
                    </button>
                </div>
            </form>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>

