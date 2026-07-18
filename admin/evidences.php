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
$maxUploadMb = 10;
$maxUploadBytes = $maxUploadMb * 1024 * 1024;
$approvalOptions = [
    'approved' => 'Đã duyệt',
    'reviewing' => 'Chờ rà soát',
    'need_update' => 'Cần bổ sung',
];

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
    $departmentId = (int) ($_POST['department_id'] ?? 0);
    $criteriaIds = array_map('intval', $_POST['criteria_ids'] ?? []);
    $userId = $_SESSION['user_id'] ?? 1;
    $file = $_FILES['evidence_file'] ?? null;
    $extension = $file && !empty($file['name']) ? strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) : '';
    $allowedExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'zip'];

    if ($evidenceId <= 0 || $code === '' || $title === '' || $academicYear === '' || $departmentId <= 0 || empty($criteriaIds)) {
        $error = 'Vui lòng nhập đầy đủ mã, tên, năm học, đơn vị và tiêu chí.';
    } elseif ($file && $file['error'] !== UPLOAD_ERR_NO_FILE && $file['error'] !== UPLOAD_ERR_OK) {
        $error = 'Upload file không thành công. Vui lòng thử lại.';
    } elseif ($file && $file['error'] === UPLOAD_ERR_OK && (int) $file['size'] > $maxUploadBytes) {
        $error = 'Không được upload file quá ' . $maxUploadMb . 'MB.';
    } elseif ($file && $file['error'] === UPLOAD_ERR_OK && !in_array($extension, $allowedExtensions, true)) {
        $error = 'Định dạng file chưa được hỗ trợ. Chỉ nhận PDF, DOC, DOCX, XLS, XLSX, JPG, PNG, ZIP.';
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

            if ($file && $file['error'] === UPLOAD_ERR_OK) {
                $uploadDir = realpath(__DIR__ . '/../uploads/evidences');
                if ($uploadDir === false) {
                    $uploadDir = __DIR__ . '/../uploads/evidences';
                    mkdir($uploadDir, 0777, true);
                }
                $safeCode = preg_replace('/[^A-Za-z0-9_\\-]/', '_', $code);
                $storedName = $safeCode . '_' . date('YmdHis') . '.' . $extension;
                $targetPath = rtrim($uploadDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $storedName;
                if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
                    throw new RuntimeException('Không thể lưu file upload vào thư mục hệ thống.');
                }
                $relativePath = 'uploads/evidences/' . $storedName;
                $fileStmt = $pdo->prepare("
                    INSERT INTO evidence_files (evidence_id, original_name, stored_name, file_path, file_type, file_size, uploaded_by)
                    VALUES (:evidence_id, :original_name, :stored_name, :file_path, :file_type, :file_size, :uploaded_by)
                ");
                $fileStmt->execute([
                    'evidence_id' => $evidenceId,
                    'original_name' => $file['name'],
                    'stored_name' => $storedName,
                    'file_path' => $relativePath,
                    'file_type' => strtoupper($extension),
                    'file_size' => (int) $file['size'],
                    'uploaded_by' => $userId,
                ]);
            }

            $pdo->commit();
            $success = 'Cập nhật minh chứng thành công.';
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
    $departmentId = (int) ($_POST['department_id'] ?? 0);
    $criteriaIds = array_map('intval', $_POST['criteria_ids'] ?? []);
    $userId = $_SESSION['user_id'] ?? 1;

    $allowedExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'zip'];
    $file = $_FILES['evidence_file'] ?? null;
    $extension = $file && !empty($file['name']) ? strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) : '';

    if ($code === '' || $title === '' || $academicYear === '' || $departmentId <= 0 || empty($criteriaIds)) {
        $error = 'Vui lòng nhập đầy đủ mã, tên, năm học, đơn vị và tiêu chí.';
    } elseif (!$file || $file['error'] === UPLOAD_ERR_NO_FILE) {
        $error = 'Vui lòng chọn file minh chứng để upload.';
    } elseif ($file['error'] !== UPLOAD_ERR_OK) {
        $error = 'Upload file không thành công. Vui lòng thử lại.';
    } elseif ((int) $file['size'] > $maxUploadBytes) {
        $error = 'Không được upload file quá ' . $maxUploadMb . 'MB.';
    } elseif (!in_array($extension, $allowedExtensions, true)) {
        $error = 'Định dạng file chưa được hỗ trợ. Chỉ nhận PDF, DOC, DOCX, XLS, XLSX, JPG, PNG, ZIP.';
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
                    issuing_department_id,
                    responsible_user_id,
                    approval_status
                ) VALUES (
                    :code,
                    :title,
                    :description,
                    :academic_year,
                    :issued_date,
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
                'department_id' => $departmentId,
                'user_id' => $userId,
            ]);

            $evidenceId = (int) $pdo->lastInsertId();

            $uploadDir = realpath(__DIR__ . '/../uploads/evidences');
            if ($uploadDir === false) {
                $uploadDir = __DIR__ . '/../uploads/evidences';
                mkdir($uploadDir, 0777, true);
            }

            $safeCode = preg_replace('/[^A-Za-z0-9_\\-]/', '_', $code);
            $storedName = $safeCode . '_' . date('YmdHis') . '.' . $extension;
            $targetPath = rtrim($uploadDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $storedName;

            if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
                throw new RuntimeException('Không thể lưu file upload vào thư mục hệ thống.');
            }

            $relativePath = 'uploads/evidences/' . $storedName;
            $fileStmt = $pdo->prepare("
                INSERT INTO evidence_files (
                    evidence_id,
                    original_name,
                    stored_name,
                    file_path,
                    file_type,
                    file_size,
                    uploaded_by
                ) VALUES (
                    :evidence_id,
                    :original_name,
                    :stored_name,
                    :file_path,
                    :file_type,
                    :file_size,
                    :uploaded_by
                )
            ");
            $fileStmt->execute([
                'evidence_id' => $evidenceId,
                'original_name' => $file['name'],
                'stored_name' => $storedName,
                'file_path' => $relativePath,
                'file_type' => strtoupper($extension),
                'file_size' => (int) $file['size'],
                'uploaded_by' => $userId,
            ]);

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
$editId = (int) ($_GET['edit'] ?? 0);
$editingEvidence = null;
$editingCriteriaIds = [];
if ($editId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM evidences WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $editId]);
    $editingEvidence = $stmt->fetch();

    $stmt = $pdo->prepare('SELECT criteria_id FROM evidence_criteria WHERE evidence_id = :id');
    $stmt->execute(['id' => $editId]);
    $editingCriteriaIds = array_map('intval', array_column($stmt->fetchAll(), 'criteria_id'));
}

$searchKeyword = trim($_GET['q'] ?? '');
$selectedYear = trim($_GET['year'] ?? '');
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

$filteredEvidences = array_values(array_filter($evidences, function ($item) use ($searchKeyword, $selectedYear, $selectedDepartmentName, $selectedStatus) {
    $haystack = implode(' ', [
        $item['code'] ?? '',
        $item['name'] ?? '',
        $item['criteria'] ?? '',
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
        <div class="col-md-3">
            <label class="form-label">Đơn vị cung cấp</label>
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
        <div class="col-md-2">
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
    <div class="col-xl-8">
        <div class="panel evidences-list-panel">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="h5 mb-0">Danh mục minh chứng</h2>
                <a class="btn btn-primary" href="#evidence-form"><i class="bi bi-cloud-arrow-up me-1"></i> Upload minh chứng</a>
            </div>
            <div class="text-secondary mb-3"><?= count($filteredEvidences) ?> minh chứng phù hợp</div>
            <div class="table-responsive">
                <table class="table" data-page-size="10" data-row-height="62">
                    <thead>
                    <tr>
                        <th>Mã</th>
                        <th>Tên minh chứng</th>
                        <th>Tiêu chí</th>
                        <th>Năm học</th>
                        <th>File</th>
                        <th>Trạng thái</th>
                        <th class="text-end">Thao tác</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($filteredEvidences as $item): ?>
                        <tr>
                            <td class="fw-bold"><?= htmlspecialchars($item['code']) ?></td>
                            <td class="evidence-title-cell">
                                <div class="evidence-title"><?= htmlspecialchars($item['name']) ?></div>
                                <div class="evidence-owner small text-secondary"><?= htmlspecialchars($item['department']) ?></div>
                            </td>
                            <td><?= htmlspecialchars($item['criteria']) ?></td>
                            <td><?= htmlspecialchars($item['year']) ?></td>
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
                                    <a class="btn btn-sm btn-outline-primary" href="?edit=<?= $item['id'] ?>#evidence-form"><i class="bi bi-pencil"></i></a>
                                    <form method="post" class="d-inline" data-confirm-form="Bạn chắc chắn muốn xóa minh chứng này?">
                                        <input type="hidden" name="action" value="delete_evidence">
                                        <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                        <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$filteredEvidences): ?>
                        <tr>
                            <td colspan="7" class="text-center text-secondary">Không tìm thấy minh chứng phù hợp.</td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="panel evidence-form-panel" id="evidence-form">
            <h2 class="h5 mb-3"><?= $editingEvidence ? 'Sửa minh chứng' : 'Thông tin minh chứng' ?></h2>
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
                    <div class="col-md-6">
                        <label class="form-label">Năm học</label>
                        <input class="form-control" name="academic_year" value="<?= htmlspecialchars($editingEvidence['academic_year'] ?? '') ?>" placeholder="2025-2026" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Ngày ban hành</label>
                        <input class="form-control" name="issued_date" value="<?= htmlspecialchars($editingEvidence['issued_date'] ?? '') ?>" type="date">
                    </div>
                </div>
                <div class="mb-3 mt-3">
                    <label class="form-label">Đơn vị cung cấp</label>
                    <select class="form-select" name="department_id" required>
                        <option value="">Chọn đơn vị</option>
                        <?php foreach ($departments as $department): ?>
                            <option value="<?= $department['id'] ?>" <?= (int) ($editingEvidence['issuing_department_id'] ?? 0) === (int) $department['id'] ? 'selected' : '' ?>><?= htmlspecialchars($department['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Gắn tiêu chí</label>
                    <select class="form-select" name="criteria_ids[]" multiple size="5" required>
                        <?php foreach ($criteria as $item): ?>
                            <option value="<?= $item['id'] ?>" <?= in_array((int) $item['id'], $editingCriteriaIds, true) ? 'selected' : '' ?>><?= htmlspecialchars($item['code'] . ' - ' . $item['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">Giữ Ctrl để chọn nhiều tiêu chí.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label">File đính kèm</label>
                    <input class="form-control" id="evidenceFile" name="evidence_file" type="file" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.zip" data-max-mb="<?= $maxUploadMb ?>" <?= $editingEvidence ? '' : 'required' ?>>
                    <div class="form-text">Dung lượng tối đa: <?= $maxUploadMb ?>MB.</div>
                    <div class="alert alert-danger mt-2 d-none" id="fileSizeAlert">
                        Không được upload file quá <?= $maxUploadMb ?>MB.
                    </div>
                </div>
                <button class="btn btn-primary w-100" type="submit">
                    <i class="bi bi-save me-1"></i> Lưu minh chứng
                </button>
                <?php if ($editingEvidence): ?><a class="btn btn-outline-secondary w-100 mt-2" href="<?= base_url('admin/evidences.php') ?>">Hủy sửa</a><?php endif; ?>
            </form>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
