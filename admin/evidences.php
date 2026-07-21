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
$allowedExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'rtf', 'jpg', 'jpeg', 'png', 'zip', 'rar'];
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

    $column = $pdo->query("SHOW COLUMNS FROM evidence_files LIKE 'version_no'")->fetch();
    if (!$column) {
        $pdo->exec('ALTER TABLE evidence_files ADD COLUMN version_no INT NOT NULL DEFAULT 1 AFTER file_size');
    }

    $checked = true;
}

function store_evidence_file(PDO $pdo, int $evidenceId, string $code, array $file, int $userId, array $allowedExtensions, ?int $requestedVersion = null): int
{
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, $allowedExtensions, true)) {
        throw new RuntimeException('Định dạng file chưa được hỗ trợ. Chỉ nhận PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, TXT, RTF, JPG, PNG, ZIP, RAR.');
    }

    ensure_evidence_file_version_column($pdo);

    if ($requestedVersion !== null && $requestedVersion > 0) {
        $versionNo = $requestedVersion;
    } else {
        $versionStmt = $pdo->prepare('SELECT COALESCE(MAX(version_no), 0) + 1 FROM evidence_files WHERE evidence_id = :evidence_id');
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
        INSERT INTO evidence_files (evidence_id, original_name, stored_name, file_path, file_type, file_size, version_no, uploaded_by)
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

        $stmt = $pdo->prepare('UPDATE evidences SET approval_status = :status, updated_at = NOW() WHERE id = :id');
        $stmt->execute([
            'status' => $status,
            'id' => $evidenceId,
        ]);

        $logStmt = $pdo->prepare("
            INSERT INTO audit_logs (user_id, action, module, record_id, new_value, ip_address)
            VALUES (:user_id, 'status_update', 'evidences', :record_id, JSON_OBJECT('approval_status', :status), :ip_address)
        ");
        $logStmt->execute([
            'user_id' => $userId,
            'record_id' => $evidenceId,
            'status' => $status,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
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

        $fileStmt = $pdo->prepare('SELECT id, file_path FROM evidence_files WHERE evidence_id = :id');
        $fileStmt->execute(['id' => $evidenceId]);
        $files = $fileStmt->fetchAll();
        $fileIds = array_column($files, 'id');

        if ($fileIds) {
            $placeholders = implode(',', array_fill(0, count($fileIds), '?'));
            $pdo->prepare("DELETE FROM download_logs WHERE evidence_file_id IN ($placeholders)")->execute($fileIds);
        }

        $pdo->prepare('DELETE FROM evidence_criteria WHERE evidence_id = :id')->execute(['id' => $evidenceId]);
        $pdo->prepare('DELETE FROM evidence_files WHERE evidence_id = :id')->execute(['id' => $evidenceId]);
        $pdo->prepare('DELETE FROM audit_logs WHERE module = :module AND record_id = :id')->execute(['module' => 'evidences', 'id' => $evidenceId]);
        $pdo->prepare('DELETE FROM evidences WHERE id = :id')->execute(['id' => $evidenceId]);

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
    $departmentId = (int) ($_POST['department_id'] ?? 0);
    $criteriaIds = array_map('intval', $_POST['criteria_ids'] ?? []);
    $versionNo = (int) ($_POST['version_no'] ?? 0);
    $userId = $_SESSION['user_id'] ?? 1;
    $file = $_FILES['evidence_file'] ?? null;
    $extension = $file && !empty($file['name']) ? strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) : '';

    if ($evidenceId <= 0 || $code === '' || $title === '' || $academicYear === '' || $departmentId <= 0 || empty($criteriaIds)) {
        $error = 'Vui lòng nhập đầy đủ mã, tên, năm học, đơn vị và tiêu chí.';
    } elseif ($versionNo <= 0) {
        $error = 'Vui lòng nhập phiên bản minh chứng hợp lệ.';
    } elseif ($file && $file['error'] !== UPLOAD_ERR_NO_FILE && $file['error'] !== UPLOAD_ERR_OK) {
        $error = 'Upload file không thành công. Vui lòng thử lại.';
    } elseif ($file && $file['error'] === UPLOAD_ERR_OK && (int) $file['size'] > $maxUploadBytes) {
        $error = 'Không được upload file quá ' . $maxUploadMb . 'MB.';
    } elseif ($file && $file['error'] === UPLOAD_ERR_OK && !in_array($extension, $allowedExtensions, true)) {
        $error = 'Định dạng file chưa được hỗ trợ. Chỉ nhận PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, TXT, RTF, JPG, PNG, ZIP, RAR.';
    } else {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                UPDATE evidences
                SET code = :code,
                    title = :title,
                    description = :description,
                    academic_year = :academic_year,
                    issued_date = :issued_date,
                    evidence_type = :evidence_type,
                    issuing_department_id = :department_id,
                    responsible_user_id = :user_id
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

            $pdo->prepare('DELETE FROM evidence_criteria WHERE evidence_id = :id')->execute(['id' => $evidenceId]);
            $linkStmt = $pdo->prepare('INSERT INTO evidence_criteria (evidence_id, criteria_id) VALUES (:evidence_id, :criteria_id)');
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
                    UPDATE evidence_files
                    SET version_no = :version_no
                    WHERE id = (
                        SELECT latest_id FROM (
                            SELECT id AS latest_id
                            FROM evidence_files
                            WHERE evidence_id = :evidence_id
                            ORDER BY version_no DESC, uploaded_at DESC, id DESC
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
    $departmentId = (int) ($_POST['department_id'] ?? 0);
    $criteriaIds = array_map('intval', $_POST['criteria_ids'] ?? []);
    $versionNo = (int) ($_POST['version_no'] ?? 1);
    $userId = $_SESSION['user_id'] ?? 1;

    $file = $_FILES['evidence_file'] ?? null;
    $extension = $file && !empty($file['name']) ? strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) : '';

    if ($code === '' || $title === '' || $academicYear === '' || $departmentId <= 0 || empty($criteriaIds)) {
        $error = 'Vui lòng nhập đầy đủ mã, tên, năm học, đơn vị và tiêu chí.';
    } elseif ($versionNo <= 0) {
        $error = 'Vui lòng nhập phiên bản minh chứng hợp lệ.';
    } elseif (!$file || $file['error'] === UPLOAD_ERR_NO_FILE) {
        $error = 'Vui lòng chọn file minh chứng để upload.';
    } elseif ($file['error'] !== UPLOAD_ERR_OK) {
        $error = 'Upload file không thành công. Vui lòng thử lại.';
    } elseif ((int) $file['size'] > $maxUploadBytes) {
        $error = 'Không được upload file quá ' . $maxUploadMb . 'MB.';
    } elseif (!in_array($extension, $allowedExtensions, true)) {
        $error = 'Định dạng file chưa được hỗ trợ. Chỉ nhận PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, TXT, RTF, JPG, PNG, ZIP, RAR.';
    } else {
        try {
            $pdo->beginTransaction();

            $check = $pdo->prepare('SELECT id FROM evidences WHERE code = :code LIMIT 1');
            $check->execute(['code' => $code]);
            if ($check->fetch()) {
                throw new RuntimeException('Mã minh chứng đã tồn tại.');
            }

            $stmt = $pdo->prepare("
                INSERT INTO evidences (
                    code,
                    title,
                    description,
                    academic_year,
                    issued_date,
                    evidence_type,
                    issuing_department_id,
                    responsible_user_id,
                    approval_status
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

            $linkStmt = $pdo->prepare('INSERT INTO evidence_criteria (evidence_id, criteria_id) VALUES (:evidence_id, :criteria_id)');
            foreach (array_unique($criteriaIds) as $criteriaId) {
                $linkStmt->execute([
                    'evidence_id' => $evidenceId,
                    'criteria_id' => $criteriaId,
                ]);
            }

            $logStmt = $pdo->prepare("
                INSERT INTO audit_logs (user_id, action, module, record_id, new_value, ip_address)
                VALUES (:user_id, 'create', 'evidences', :record_id, JSON_OBJECT('code', :code), :ip_address)
            ");
            $logStmt->execute([
                'user_id' => $userId,
                'record_id' => $evidenceId,
                'code' => $code,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
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

require_once __DIR__ . '/../includes/data.php';

$departments = $pdo->query('SELECT id, name FROM departments ORDER BY name')->fetchAll();
$isCreatingEvidence = isset($_GET['create']);
$editId = $isCreatingEvidence ? 0 : (int) ($_GET['edit'] ?? 0);
$editingEvidence = null;
$editingCriteriaIds = [];
$editingLatestFile = null;
$editingVersionNo = 1;
if ($editId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM evidences WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $editId]);
    $editingEvidence = $stmt->fetch();

    $stmt = $pdo->prepare('SELECT criteria_id FROM evidence_criteria WHERE evidence_id = :id');
    $stmt->execute(['id' => $editId]);
    $editingCriteriaIds = array_map('intval', array_column($stmt->fetchAll(), 'criteria_id'));

    $stmt = $pdo->prepare('
        SELECT id, original_name, file_type, file_size, version_no, uploaded_at
        FROM evidence_files
        WHERE evidence_id = :id
        ORDER BY version_no DESC, uploaded_at DESC, id DESC
        LIMIT 1
    ');
    $stmt->execute(['id' => $editId]);
    $editingLatestFile = $stmt->fetch();
    $editingVersionNo = (int) ($editingLatestFile['version_no'] ?? 1);
}

$searchKeyword = trim($_GET['q'] ?? '');
$selectedYear = trim($_GET['year'] ?? '');
$selectedStandard = trim($_GET['standard'] ?? '');
$selectedDepartmentId = (int) ($_GET['department_id'] ?? 0);
$selectedStatus = trim($_GET['status'] ?? '');
$selectedDepartmentName = '';
foreach ($departments as $department) {
    if ((int) $department['id'] === $selectedDepartmentId) {
        $selectedDepartmentName = $department['name'];
        break;
    }
}

$evidenceYears = array_values(array_unique(array_filter(array_column($evidences, 'year'))));
sort($evidenceYears);
$evidenceStatuses = array_values(array_unique(array_filter(array_column($evidences, 'status'))));

$filteredEvidences = array_values(array_filter($evidences, function ($item) use ($searchKeyword, $selectedYear, $selectedStandard, $selectedDepartmentName, $selectedStatus) {
    $haystack = implode(' ', [
        $item['code'] ?? '',
        $item['name'] ?? '',
        $item['criteria'] ?? '',
        $item['standards'] ?? '',
        $item['year'] ?? '',
        $item['department'] ?? '',
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

    if ($selectedDepartmentName !== '' && ($item['department'] ?? '') !== $selectedDepartmentName) {
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
<?php if ($editingEvidence || $isCreatingEvidence): ?><script>document.body.dataset.autoOpenModal = 'evidenceFormModal';</script><?php endif; ?>
<div class="panel mb-4">
    <form class="row g-3 align-items-end" method="get">
        <div class="col-md-3">
            <label class="form-label">Mã/Tên minh chứng</label>
            <input class="form-control" name="q" value="<?= htmlspecialchars($searchKeyword) ?>" placeholder="VD: MC.01.01.01">
        </div>
        <div class="col-md-2">
            <label class="form-label">Năm học</label>
            <select class="form-select" name="year">
                <option value="">Tất cả</option>
                <?php foreach ($evidenceYears as $year): ?>
                    <option value="<?= htmlspecialchars($year) ?>" <?= $selectedYear === $year ? 'selected' : '' ?>><?= htmlspecialchars($year) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">Tiêu chuẩn</label>
            <select class="form-select" name="standard">
                <option value="">Tất cả</option>
                <?php foreach ($standards as $standard): ?>
                    <option value="<?= htmlspecialchars($standard['code']) ?>" <?= $selectedStandard === $standard['code'] ? 'selected' : '' ?>><?= htmlspecialchars($standard['code']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">Đơn vị phụ trách</label>
            <select class="form-select" name="department_id">
                <option value="">Tất cả đơn vị</option>
                <?php foreach ($departments as $department): ?>
                    <option value="<?= (int) $department['id'] ?>" <?= $selectedDepartmentId === (int) $department['id'] ? 'selected' : '' ?>><?= htmlspecialchars($department['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">Trạng thái</label>
            <select class="form-select" name="status">
                <option value="">Tất cả</option>
                <?php foreach ($evidenceStatuses as $status): ?>
                    <option value="<?= htmlspecialchars($status) ?>" <?= $selectedStatus === $status ? 'selected' : '' ?>><?= htmlspecialchars($status) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-1">
            <button class="btn btn-primary w-100" type="submit"><i class="bi bi-search me-1"></i> Tìm</button>
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
                                <span>Đơn vị cung cấp</span>
                                <button class="column-filter-toggle" type="button" data-column-filter-toggle title="Lọc đơn vị cung cấp"><i class="bi bi-funnel"></i></button>
                                <span class="column-filter-menu"><input class="form-control form-control-sm" data-column-filter="6" placeholder="Lọc đơn vị"></span>
                            </span>
                        </th>
                        <th>
                            <span class="column-filter-head">
                                <span>Loại minh chứng</span>
                                <button class="column-filter-toggle" type="button" data-column-filter-toggle title="Lọc loại minh chứng"><i class="bi bi-funnel"></i></button>
                                <span class="column-filter-menu"><input class="form-control form-control-sm" data-column-filter="7" placeholder="Lọc loại"></span>
                            </span>
                        </th>
                        <th>
                            <span class="column-filter-head">
                                <span>File đính kèm</span>
                                <button class="column-filter-toggle" type="button" data-column-filter-toggle title="Lọc file"><i class="bi bi-funnel"></i></button>
                                <span class="column-filter-menu"><input class="form-control form-control-sm" data-column-filter="8" placeholder="Lọc file"></span>
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
                            <td><?= htmlspecialchars($item['department']) ?></td>
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
                        <td colspan="9" class="text-center text-secondary">Không tìm thấy minh chứng phù hợp với bộ lọc.</td>
                    </tr>
                    <?php if (!$filteredEvidences): ?>
                        <tr>
                            <td colspan="9" class="text-center text-secondary">Không tìm thấy minh chứng phù hợp.</td>
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
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Năm học</label>
                        <input class="form-control" name="academic_year" value="<?= htmlspecialchars($editingEvidence['academic_year'] ?? '') ?>" placeholder="2025-2026" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Ngày ban hành</label>
                        <input class="form-control" name="issued_date" value="<?= htmlspecialchars($editingEvidence['issued_date'] ?? '') ?>" type="date">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Loại minh chứng</label>
                        <select class="form-select" name="evidence_type">
                            <?php $currentEvType = $editingEvidence['evidence_type'] ?? 'Minh chứng chính'; ?>
                            <option value="Minh chứng chính" <?= $currentEvType === 'Minh chứng chính' ? 'selected' : '' ?>>Minh chứng chính</option>
                            <option value="Minh chứng bổ sung" <?= $currentEvType === 'Minh chứng bổ sung' ? 'selected' : '' ?>>Minh chứng bổ sung</option>
                            <option value="Minh chứng tham khảo" <?= $currentEvType === 'Minh chứng tham khảo' ? 'selected' : '' ?>>Minh chứng tham khảo</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Phiên bản</label>
                        <input class="form-control" name="version_no" value="<?= (int) ($editingEvidence ? $editingVersionNo : 1) ?>" type="number" min="1" step="1" required>
                    </div>
                </div>
                <div class="mb-3 mt-3">
                    <label class="form-label">Đơn vị cung cấp / phụ trách</label>
                    <select class="form-select" name="department_id" required>
                        <option value="">Chọn đơn vị</option>
                        <?php foreach ($departments as $department): ?>
                            <option value="<?= $department['id'] ?>" <?= (int) ($editingEvidence['issuing_department_id'] ?? 0) === (int) $department['id'] ? 'selected' : '' ?>><?= htmlspecialchars($department['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Gắn tiêu chí</label>
                    <input class="form-control form-control-sm mb-2" type="search" data-criteria-search placeholder="Nhập mã hoặc tên tiêu chí">
                    <select class="form-select" name="criteria_ids[]" multiple size="5" required data-criteria-select>
                        <?php foreach ($criteria as $item): ?>
                            <option value="<?= $item['id'] ?>" <?= in_array((int) $item['id'], $editingCriteriaIds, true) ? 'selected' : '' ?>><?= htmlspecialchars($item['code'] . ' - ' . $item['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">Giữ Ctrl để chọn nhiều tiêu chí.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label">File đính kèm</label>
                    <input class="form-control" id="evidenceFile" name="evidence_file" type="file" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.rtf,.jpg,.jpeg,.png,.zip,.rar" data-max-mb="<?= $maxUploadMb ?>" <?= $editingEvidence ? '' : 'required' ?>>
                    <?php if ($editingLatestFile): ?>
                        <div class="small text-secondary mt-2">
                            Phiên bản hiện tại: v<?= (int) $editingLatestFile['version_no'] ?> · <?= htmlspecialchars($editingLatestFile['original_name']) ?> · <?= htmlspecialchars($editingLatestFile['file_type']) ?>
                        </div>
                    <?php endif; ?>
                    <div class="form-text"><?= $editingEvidence ? 'Chọn tệp mới nếu cần cập nhật phiên bản minh chứng. ' : '' ?>Dung lượng tối đa: <?= $maxUploadMb ?>MB.</div>
                    <div class="alert alert-danger mt-2 d-none" id="fileSizeAlert">
                        Không được upload file quá <?= $maxUploadMb ?>MB.
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
