<?php
require_once __DIR__ . '/../includes/helpers.php';
require_roles(['admin']);
require_once __DIR__ . '/../config/database.php';

$pdo = db();
$success = '';
$error = '';
$currentUserId = $_SESSION['user_id'] ?? 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'save_user') {
            $id = (int) ($_POST['id'] ?? 0);
            $fullName = trim($_POST['full_name'] ?? '');
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $roleId = (int) ($_POST['role_id'] ?? 0);
            $departmentId = (int) ($_POST['department_id'] ?? 0);
            $status = $_POST['status'] ?? 'active';
            $password = $_POST['password'] ?? '';

            if ($fullName === '' || $username === '' || $email === '' || $roleId <= 0) {
                throw new RuntimeException('Vui lòng nhập đầy đủ họ tên, tên đăng nhập, email và vai trò.');
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Email chưa đúng định dạng.');
            }

            if ($id > 0) {
                if ($password !== '') {
                    $stmt = $pdo->prepare('UPDATE users SET role_id = :role_id, department_id = :department_id, full_name = :full_name, username = :username, email = :email, password_hash = :password_hash, status = :status WHERE id = :id');
                    $stmt->execute([
                        'role_id' => $roleId,
                        'department_id' => $departmentId ?: null,
                        'full_name' => $fullName,
                        'username' => $username,
                        'email' => $email,
                        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                        'status' => $status,
                        'id' => $id,
                    ]);
                } else {
                    $stmt = $pdo->prepare('UPDATE users SET role_id = :role_id, department_id = :department_id, full_name = :full_name, username = :username, email = :email, status = :status WHERE id = :id');
                    $stmt->execute([
                        'role_id' => $roleId,
                        'department_id' => $departmentId ?: null,
                        'full_name' => $fullName,
                        'username' => $username,
                        'email' => $email,
                        'status' => $status,
                        'id' => $id,
                    ]);
                }
                $success = 'Cập nhật tài khoản thành công.';
            } else {
                if ($password === '') {
                    throw new RuntimeException('Vui lòng nhập mật khẩu cho tài khoản mới.');
                }
                $stmt = $pdo->prepare('INSERT INTO users (role_id, department_id, full_name, username, email, password_hash, status) VALUES (:role_id, :department_id, :full_name, :username, :email, :password_hash, :status)');
                $stmt->execute([
                    'role_id' => $roleId,
                    'department_id' => $departmentId ?: null,
                    'full_name' => $fullName,
                    'username' => $username,
                    'email' => $email,
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                    'status' => $status,
                ]);
                $success = 'Thêm tài khoản thành công.';
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

$roleRows = $pdo->query("SELECT id, code, name FROM roles WHERE code IN ('admin', 'viewer') ORDER BY id")->fetchAll();
$departments = $pdo->query('SELECT id, name FROM departments ORDER BY name')->fetchAll();
$editId = (int) ($_GET['edit'] ?? 0);
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
<?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="row g-4 accounts-layout">
    <div class="col-xl-8">
        <div class="panel accounts-list-panel">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="h5 mb-0">Danh sách người dùng</h2>
                <a class="btn btn-primary" href="<?= base_url('admin/users.php') ?>"><i class="bi bi-person-plus me-1"></i> Thêm tài khoản</a>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead><tr><th>Họ tên</th><th>Tên đăng nhập</th><th>Email</th><th>Vai trò</th><th>Đơn vị</th><th>Trạng thái</th><th class="text-end">Thao tác</th></tr></thead>
                    <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td class="fw-semibold"><?= htmlspecialchars($user['name']) ?></td>
                            <td><?= htmlspecialchars($user['username']) ?></td>
                            <td><?= htmlspecialchars($user['email']) ?></td>
                            <td><?= htmlspecialchars($user['role']) ?></td>
                            <td><?= htmlspecialchars($user['department']) ?></td>
                            <td>
                                <form method="post" class="status-update-form">
                                    <input type="hidden" name="action" value="update_user_status">
                                    <input type="hidden" name="id" value="<?= $user['id'] ?>">
                                    <?= status_select($user['status_raw'] ?? 'active', ['active' => 'Hoạt động', 'locked' => 'Tạm khóa'], 'status', (int) $user['id'] === (int) $currentUserId, '', 'Cập nhật trạng thái tài khoản') ?>
                                </form>
                            </td>
                            <td class="text-end action-cell">
                                <div class="action-buttons">
                                    <a class="btn btn-sm btn-outline-primary" href="?edit=<?= $user['id'] ?>"><i class="bi bi-pencil"></i></a>
                                    <form method="post" class="d-inline" data-confirm-form="Bạn chắc chắn muốn đổi trạng thái tài khoản này?">
                                        <input type="hidden" name="action" value="toggle_user">
                                        <input type="hidden" name="id" value="<?= $user['id'] ?>">
                                        <button class="btn btn-sm btn-outline-warning" type="submit"><i class="bi bi-lock"></i></button>
                                    </form>
                                    <form method="post" class="d-inline" data-confirm-form="Bạn chắc chắn muốn xóa tài khoản này?">
                                        <input type="hidden" name="action" value="delete_user">
                                        <input type="hidden" name="id" value="<?= $user['id'] ?>">
                                        <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i></button>
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
    <div class="col-xl-4">
        <div class="panel account-form-panel">
            <h2 class="h5 mb-3"><?= $editingUser ? 'Sửa tài khoản' : 'Cấp tài khoản' ?></h2>
            <form method="post">
                <input type="hidden" name="action" value="save_user">
                <input type="hidden" name="id" value="<?= (int) ($editingUser['id'] ?? 0) ?>">
                <div class="mb-3"><label class="form-label">Họ tên</label><input class="form-control" name="full_name" value="<?= htmlspecialchars($editingUser['full_name'] ?? '') ?>" required></div>
                <div class="mb-3"><label class="form-label">Tên đăng nhập</label><input class="form-control" name="username" value="<?= htmlspecialchars($editingUser['username'] ?? '') ?>" required></div>
                <div class="mb-3"><label class="form-label">Email</label><input class="form-control" name="email" type="email" value="<?= htmlspecialchars($editingUser['email'] ?? '') ?>" required></div>
                <div class="mb-3"><label class="form-label">Mật khẩu <?= $editingUser ? '(để trống nếu không đổi)' : '' ?></label><input class="form-control" name="password" type="password" <?= $editingUser ? '' : 'required' ?>></div>
                <div class="mb-3"><label class="form-label">Vai trò</label><select class="form-select" name="role_id" required><?php foreach ($roleRows as $role): ?><option value="<?= $role['id'] ?>" <?= (int) ($editingUser['role_id'] ?? 0) === (int) $role['id'] ? 'selected' : '' ?>><?= htmlspecialchars($role['name']) ?></option><?php endforeach; ?></select></div>
                <div class="mb-3"><label class="form-label">Đơn vị</label><select class="form-select" name="department_id"><option value="">Chưa phân đơn vị</option><?php foreach ($departments as $department): ?><option value="<?= $department['id'] ?>" <?= (int) ($editingUser['department_id'] ?? 0) === (int) $department['id'] ? 'selected' : '' ?>><?= htmlspecialchars($department['name']) ?></option><?php endforeach; ?></select></div>
                <div class="mb-3"><label class="form-label">Trạng thái</label><select class="form-select" name="status"><option value="active" <?= ($editingUser['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Hoạt động</option><option value="locked" <?= ($editingUser['status'] ?? '') === 'locked' ? 'selected' : '' ?>>Tạm khóa</option></select></div>
                <button class="btn btn-primary w-100" type="submit"><i class="bi bi-save me-1"></i> Lưu tài khoản</button>
                <?php if ($editingUser): ?><a class="btn btn-outline-secondary w-100 mt-2" href="<?= base_url('admin/users.php') ?>">Hủy sửa</a><?php endif; ?>
            </form>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
