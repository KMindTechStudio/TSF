<?php
require_once __DIR__ . '/../includes/helpers.php';
require_roles(['admin']);
require_once __DIR__ . '/../config/database.php';

$pdo = db();
$success = '';
$error = '';
$currentUserId = $_SESSION['user_id'] ?? 1;

function ensure_user_code_column(PDO $pdo): void
{
    static $checked = false;

    if ($checked) {
        return;
    }

    $column = $pdo->query("SHOW COLUMNS FROM users LIKE 'user_code'")->fetch();
    if (!$column) {
        $pdo->exec("ALTER TABLE users ADD COLUMN user_code VARCHAR(50) NULL AFTER id");
        $pdo->exec("UPDATE users SET user_code = CONCAT('ND', LPAD(id, 3, '0')) WHERE user_code IS NULL OR user_code = ''");
    }

    $checked = true;
}

function default_user_email(string $username, int $id = 0): string
{
    $safeUsername = strtolower(preg_replace('/[^a-z0-9._-]+/i', '', $username));
    $safeUsername = $safeUsername !== '' ? $safeUsername : 'user' . ($id > 0 ? $id : time());

    return $safeUsername . '@fbu.edu.vn';
}

ensure_user_code_column($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'save_user') {
            $id = (int) ($_POST['id'] ?? 0);
            $userCode = trim($_POST['user_code'] ?? '');
            $fullName = trim($_POST['full_name'] ?? '');
            $username = trim($_POST['username'] ?? '');
            $roleId = (int) ($_POST['role_id'] ?? 0);
            $departmentId = (int) ($_POST['department_id'] ?? 0);
            $status = $_POST['status'] ?? 'active';
            $password = $_POST['password'] ?? '';

            if ($userCode === '' || $fullName === '' || $username === '' || $roleId <= 0 || $departmentId <= 0) {
                throw new RuntimeException('Vui lòng nhập đầy đủ mã người dùng, họ tên, đơn vị, vai trò và tên đăng nhập.');
            }

            if (!in_array($status, ['active', 'locked'], true)) {
                throw new RuntimeException('Trạng thái tài khoản không hợp lệ.');
            }

            $check = $pdo->prepare('SELECT id FROM users WHERE (user_code = :user_code OR username = :username) AND id <> :id LIMIT 1');
            $check->execute([
                'user_code' => $userCode,
                'username' => $username,
                'id' => $id,
            ]);
            if ($check->fetch()) {
                throw new RuntimeException('Mã người dùng hoặc tên đăng nhập đã tồn tại.');
            }

            if ($id > 0) {
                $emailStmt = $pdo->prepare('SELECT email FROM users WHERE id = :id LIMIT 1');
                $emailStmt->execute(['id' => $id]);
                $currentEmail = $emailStmt->fetchColumn() ?: default_user_email($username, $id);

                if ($password !== '') {
                    $stmt = $pdo->prepare("
                        UPDATE users
                        SET user_code = :user_code,
                            role_id = :role_id,
                            department_id = :department_id,
                            full_name = :full_name,
                            username = :username,
                            email = :email,
                            password_hash = :password_hash,
                            status = :status
                        WHERE id = :id
                    ");
                    $stmt->execute([
                        'user_code' => $userCode,
                        'role_id' => $roleId,
                        'department_id' => $departmentId,
                        'full_name' => $fullName,
                        'username' => $username,
                        'email' => $currentEmail,
                        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                        'status' => $status,
                        'id' => $id,
                    ]);
                    $success = 'Cập nhật thông tin và đặt lại mật khẩu thành công.';
                } else {
                    $stmt = $pdo->prepare("
                        UPDATE users
                        SET user_code = :user_code,
                            role_id = :role_id,
                            department_id = :department_id,
                            full_name = :full_name,
                            username = :username,
                            email = :email,
                            status = :status
                        WHERE id = :id
                    ");
                    $stmt->execute([
                        'user_code' => $userCode,
                        'role_id' => $roleId,
                        'department_id' => $departmentId,
                        'full_name' => $fullName,
                        'username' => $username,
                        'email' => $currentEmail,
                        'status' => $status,
                        'id' => $id,
                    ]);
                    $success = 'Cập nhật thông tin tài khoản thành công.';
                }
            } else {
                if ($password === '') {
                    throw new RuntimeException('Vui lòng nhập mật khẩu cho tài khoản mới.');
                }

                $stmt = $pdo->prepare("
                    INSERT INTO users (user_code, role_id, department_id, full_name, username, email, password_hash, status)
                    VALUES (:user_code, :role_id, :department_id, :full_name, :username, :email, :password_hash, :status)
                ");
                $stmt->execute([
                    'user_code' => $userCode,
                    'role_id' => $roleId,
                    'department_id' => $departmentId,
                    'full_name' => $fullName,
                    'username' => $username,
                    'email' => default_user_email($username),
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                    'status' => $status,
                ]);
                $success = 'Tạo tài khoản mới thành công.';
            }
        }

        if ($action === 'delete_user') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id === (int) $currentUserId) {
                throw new RuntimeException('Không thể xóa tài khoản đang đăng nhập.');
            }
            $stmt = $pdo->prepare('DELETE FROM users WHERE id = :id');
            $stmt->execute(['id' => $id]);
            $success = 'Xóa tài khoản thành công.';
        }

        if ($action === 'toggle_user') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id === (int) $currentUserId) {
                throw new RuntimeException('Không thể khóa tài khoản đang đăng nhập.');
            }
            $stmt = $pdo->prepare("UPDATE users SET status = IF(status = 'active', 'locked', 'active') WHERE id = :id");
            $stmt->execute(['id' => $id]);
            $success = 'Cập nhật trạng thái tài khoản thành công.';
        }

        if ($action === 'update_user_status') {
            $id = (int) ($_POST['id'] ?? 0);
            $status = $_POST['status'] ?? '';

            if ($id === (int) $currentUserId && $status === 'locked') {
                throw new RuntimeException('Không thể khóa tài khoản đang đăng nhập.');
            }

            if ($id <= 0 || !in_array($status, ['active', 'locked'], true)) {
                throw new RuntimeException('Trạng thái tài khoản không hợp lệ.');
            }

            $stmt = $pdo->prepare('UPDATE users SET status = :status WHERE id = :id');
            $stmt->execute([
                'status' => $status,
                'id' => $id,
            ]);
            $success = 'Cập nhật trạng thái tài khoản thành công.';
        }
    } catch (Throwable $exception) {
        $error = 'Không thể thực hiện thao tác: ' . $exception->getMessage();
    }
}

require_once __DIR__ . '/../includes/data.php';

$roleRows = $pdo->query('SELECT id, code, name FROM roles ORDER BY id')->fetchAll();
$departments = $pdo->query('SELECT id, name FROM departments ORDER BY name')->fetchAll();
$isCreatingUser = isset($_GET['create']);
$editId = $isCreatingUser ? 0 : (int) ($_GET['edit'] ?? 0);
$editingUser = null;
if ($editId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $editId]);
    $editingUser = $stmt->fetch();
}

$pageTitle = page_title('Quản lý tài khoản');
$heading = 'Quản lý tài khoản và phân quyền';
include __DIR__ . '/../includes/header.php';
?>
<?php if ($editingUser || $isCreatingUser): ?><script>document.body.dataset.autoOpenModal = 'accountFormModal';</script><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="row g-4 accounts-layout">
    <div class="col-12">
        <div class="panel accounts-list-panel">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="h5 mb-0">Danh sách người dùng</h2>
                <a class="btn btn-primary" href="<?= base_url('admin/users.php?create=1') ?>"><i class="bi bi-plus-circle me-1"></i> Thêm mới</a>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                    <tr>
                        <th>Mã người dùng</th>
                        <th>Họ tên</th>
                        <th>Đơn vị</th>
                        <th>Vai trò</th>
                        <th>Tên đăng nhập</th>
                        <th>Trạng thái</th>
                        <th class="text-end">Thao tác</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td class="fw-bold"><?= htmlspecialchars($user['code']) ?></td>
                            <td class="fw-semibold"><?= htmlspecialchars($user['name']) ?></td>
                            <td><?= htmlspecialchars($user['department']) ?></td>
                            <td><?= htmlspecialchars($user['role']) ?></td>
                            <td><?= htmlspecialchars($user['username']) ?></td>
                            <td>
                                <form method="post" class="status-update-form">
                                    <input type="hidden" name="action" value="update_user_status">
                                    <input type="hidden" name="id" value="<?= $user['id'] ?>">
                                    <?= status_select($user['status_raw'] ?? 'active', ['active' => 'Đang hoạt động', 'locked' => 'Khóa'], 'status', (int) $user['id'] === (int) $currentUserId, '', 'Cập nhật trạng thái tài khoản') ?>
                                </form>
                            </td>
                            <td class="text-end action-cell">
                                <div class="action-buttons">
                                    <a class="btn btn-sm btn-outline-primary" href="?edit=<?= $user['id'] ?>" title="Cập nhật thông tin tài khoản"><i class="bi bi-pencil"></i></a>
                                    <form method="post" class="d-inline" data-confirm-form="Bạn chắc chắn muốn mở/khóa tài khoản này?">
                                        <input type="hidden" name="action" value="toggle_user">
                                        <input type="hidden" name="id" value="<?= $user['id'] ?>">
                                        <button class="btn btn-sm btn-outline-warning" type="submit" title="Mở/khóa tài khoản"><i class="bi bi-lock"></i></button>
                                    </form>
                                    <form method="post" class="d-inline" data-confirm-form="Bạn chắc chắn muốn xóa tài khoản này?">
                                        <input type="hidden" name="action" value="delete_user">
                                        <input type="hidden" name="id" value="<?= $user['id'] ?>">
                                        <button class="btn btn-sm btn-outline-danger" type="submit" title="Xóa tài khoản"><i class="bi bi-trash"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade management-form-modal" id="accountFormModal" tabindex="-1" aria-labelledby="accountFormModalLabel" aria-hidden="true" <?= ($editingUser || $isCreatingUser) ? 'data-auto-open-modal' : '' ?>>
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5" id="accountFormModalLabel"><?= $editingUser ? 'Cập nhật tài khoản' : 'Tạo tài khoản mới' ?></h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <div class="modal-body">
                <form method="post">
                    <input type="hidden" name="action" value="save_user">
                    <input type="hidden" name="id" value="<?= (int) ($editingUser['id'] ?? 0) ?>">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Mã người dùng</label>
                            <input class="form-control" name="user_code" value="<?= htmlspecialchars($editingUser['user_code'] ?? '') ?>" placeholder="VD: ND001" required>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Họ tên</label>
                            <input class="form-control" name="full_name" value="<?= htmlspecialchars($editingUser['full_name'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Đơn vị</label>
                            <select class="form-select" name="department_id" required>
                                <option value="">Chọn đơn vị</option>
                                <?php foreach ($departments as $department): ?>
                                    <option value="<?= $department['id'] ?>" <?= (int) ($editingUser['department_id'] ?? 0) === (int) $department['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($department['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Vai trò</label>
                            <select class="form-select" name="role_id" required>
                                <?php foreach ($roleRows as $role): ?>
                                    <option value="<?= $role['id'] ?>" <?= (int) ($editingUser['role_id'] ?? 0) === (int) $role['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($role['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Tên đăng nhập</label>
                            <input class="form-control" name="username" value="<?= htmlspecialchars($editingUser['username'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><?= $editingUser ? 'Mật khẩu mới' : 'Mật khẩu' ?></label>
                            <input class="form-control" name="password" type="password" <?= $editingUser ? '' : 'required' ?>>
                            <?php if ($editingUser): ?><div class="form-text">Để trống nếu không đặt lại mật khẩu.</div><?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Trạng thái</label>
                            <select class="form-select" name="status">
                                <option value="active" <?= ($editingUser['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Đang hoạt động</option>
                                <option value="locked" <?= ($editingUser['status'] ?? '') === 'locked' ? 'selected' : '' ?>>Khóa</option>
                            </select>
                        </div>
                    </div>
                    <div class="d-flex gap-2 justify-content-end mt-4">
                        <?php if ($editingUser): ?>
                            <a class="btn btn-outline-secondary" href="<?= base_url('admin/users.php') ?>">Hủy sửa</a>
                        <?php else: ?>
                            <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Hủy</button>
                        <?php endif; ?>
                        <button class="btn btn-primary" type="submit"><i class="bi bi-save me-1"></i> Lưu tài khoản</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
