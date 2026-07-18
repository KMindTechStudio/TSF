<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/helpers.php';
require_login();
require_roles(['admin']);
require_once __DIR__ . '/../config/database.php';

$pdo = db();
$userId = (int) $_SESSION['user_id'];
$success = '';
$error = '';
$avatarMaxMb = 2;
$avatarMaxBytes = $avatarMaxMb * 1024 * 1024;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $departmentId = (int) ($_POST['department_id'] ?? 0);
    $avatarPath = null;
    $avatarFile = $_FILES['avatar_file'] ?? null;

    if ($fullName === '' || $email === '' || $departmentId <= 0) {
        $error = 'Vui lòng nhập đầy đủ họ tên, email và đơn vị.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email chưa đúng định dạng.';
    } else {
        $check = $pdo->prepare('SELECT id FROM users WHERE email = :email AND id <> :id LIMIT 1');
        $check->execute([
            'email' => $email,
            'id' => $userId,
        ]);

        if ($check->fetch()) {
            $error = 'Email này đã được tài khoản khác sử dụng.';
        }
    }

    if (!$error && $avatarFile && $avatarFile['error'] !== UPLOAD_ERR_NO_FILE) {
        $allowedAvatarExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        $extension = strtolower(pathinfo($avatarFile['name'], PATHINFO_EXTENSION));

        if ($avatarFile['error'] !== UPLOAD_ERR_OK) {
            $error = 'Upload ảnh đại diện không thành công. Vui lòng thử lại.';
        } elseif ((int) $avatarFile['size'] > $avatarMaxBytes) {
            $error = 'Không được upload ảnh đại diện quá ' . $avatarMaxMb . 'MB.';
        } elseif (!in_array($extension, $allowedAvatarExtensions, true)) {
            $error = 'Ảnh đại diện chỉ nhận JPG, PNG, WebP hoặc GIF.';
        } else {
            $avatarDir = realpath(__DIR__ . '/../uploads/avatars');
            if ($avatarDir === false) {
                $avatarDir = __DIR__ . '/../uploads/avatars';
                mkdir($avatarDir, 0777, true);
            }

            $storedName = 'avatar_user_' . $userId . '_' . date('YmdHis') . '.' . $extension;
            $targetPath = rtrim($avatarDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $storedName;

            if (!move_uploaded_file($avatarFile['tmp_name'], $targetPath)) {
                $error = 'Không thể lưu ảnh đại diện vào hệ thống.';
            } else {
                $avatarPath = 'uploads/avatars/' . $storedName;
            }
        }
    }

    if (!$error) {
        if ($avatarPath) {
            $stmt = $pdo->prepare("
                UPDATE users
                SET full_name = :full_name,
                    email = :email,
                    department_id = :department_id,
                    avatar_path = :avatar_path
                WHERE id = :id
            ");
            $stmt->execute([
                'full_name' => $fullName,
                'email' => $email,
                'department_id' => $departmentId,
                'avatar_path' => $avatarPath,
                'id' => $userId,
            ]);
        } else {
            $stmt = $pdo->prepare("
                UPDATE users
                SET full_name = :full_name,
                    email = :email,
                    department_id = :department_id
                WHERE id = :id
            ");
            $stmt->execute([
                'full_name' => $fullName,
                'email' => $email,
                'department_id' => $departmentId,
                'id' => $userId,
            ]);
        }

        $success = 'Cập nhật thông tin cá nhân thành công.';
    }
}

require_once __DIR__ . '/../includes/data.php';

$stmt = $pdo->prepare("
    SELECT u.*, r.name AS role_name, d.name AS department_name
    FROM users u
    JOIN roles r ON r.id = u.role_id
    LEFT JOIN departments d ON d.id = u.department_id
    WHERE u.id = :id
    LIMIT 1
");
$stmt->execute(['id' => $userId]);
$profile = $stmt->fetch();

$departments = $pdo->query('SELECT id, name FROM departments ORDER BY name')->fetchAll();

$pageTitle = page_title('Thông tin cá nhân');
$heading = 'Thông tin cá nhân';
include __DIR__ . '/../includes/header.php';
?>
<div class="row g-4">
    <div class="col-xl-5">
        <div class="panel">
            <div class="d-flex align-items-center gap-3 mb-4">
                <?= avatar_html($profile['avatar_path'] ?? null, $profile['full_name'], 'avatar profile-avatar') ?>
                <div>
                    <h2 class="h5 mb-1"><?= htmlspecialchars($profile['full_name']) ?></h2>
                    <p class="text-secondary mb-0"><?= htmlspecialchars($profile['role_name']) ?></p>
                </div>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data">
                <div class="mb-3">
                    <label class="form-label">Ảnh đại diện</label>
                    <input class="form-control" name="avatar_file" type="file" accept=".jpg,.jpeg,.png,.webp,.gif">
                    <div class="form-text">Chọn ảnh từ thiết bị. Dung lượng tối đa <?= $avatarMaxMb ?>MB.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Họ tên</label>
                    <input class="form-control" name="full_name" value="<?= htmlspecialchars($profile['full_name']) ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input class="form-control" type="email" name="email" value="<?= htmlspecialchars($profile['email']) ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Đơn vị</label>
                    <select class="form-select" name="department_id" required>
                        <?php foreach ($departments as $department): ?>
                            <option value="<?= $department['id'] ?>" <?= (int) $profile['department_id'] === (int) $department['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($department['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-4">
                    <label class="form-label">Vai trò</label>
                    <input class="form-control" value="<?= htmlspecialchars($profile['role_name']) ?>" readonly>
                </div>
                <button class="btn btn-primary w-100" type="submit">
                    <i class="bi bi-save me-1"></i> Cập nhật thông tin
                </button>
            </form>
        </div>
    </div>
    <div class="col-xl-7">
        <div class="panel">
            <h2 class="h5 mb-3">Thông tin hệ thống</h2>
            <div class="table-responsive">
                <table class="table">
                    <tbody>
                    <tr>
                        <th style="width: 220px;">Tên đăng nhập</th>
                        <td><?= htmlspecialchars($profile['username']) ?></td>
                    </tr>
                    <tr>
                        <th>Trạng thái tài khoản</th>
                        <td><?= readonly_status_select(vn_user_status($profile['status'])) ?></td>
                    </tr>
                    <tr>
                        <th>Chương trình đào tạo</th>
                        <td><?= htmlspecialchars($trainingProgram['name']) ?></td>
                    </tr>
                    <tr>
                        <th>Mã ngành</th>
                        <td><?= htmlspecialchars($trainingProgram['code']) ?></td>
                    </tr>
                    <tr>
                        <th>Chu kỳ kiểm định</th>
                        <td><?= htmlspecialchars($trainingProgram['cycle']) ?></td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
