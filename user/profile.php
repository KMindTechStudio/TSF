<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/helpers.php';
require_login();
require_once __DIR__ . '/../config/database.php';

$pdo = db();
$userId = (int) $_SESSION['user_id'];
$success = '';
$error = '';
$avatarMaxMb = 100;
$avatarMaxBytes = $avatarMaxMb * 1024 * 1024;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_avatar') {
        $avatarFile = $_FILES['avatar_file'] ?? null;
        if (!$avatarFile || $avatarFile['error'] === UPLOAD_ERR_NO_FILE) {
            $error = 'Vui lòng chọn tệp ảnh mới để cập nhật ảnh đại diện.';
        } else {
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
                    $stmt = $pdo->prepare("UPDATE nguoi_dung SET duong_dan_anh_dai_dien = :avatar_path WHERE id = :id");
                    $stmt->execute(['avatar_path' => $avatarPath, 'id' => $userId]);
                    $success = 'Cập nhật ảnh đại diện thành công.';
                }
            }
        }
    }

    if ($action === 'change_password') {
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if ($newPassword === '') {
            $error = 'Vui lòng nhập mật khẩu mới.';
        } elseif (strlen($newPassword) < 6) {
            $error = 'Mật khẩu mới phải có ít nhất 6 ký tự.';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'Mật khẩu xác nhận chưa trùng khớp.';
        } else {
            $stmt = $pdo->prepare("UPDATE nguoi_dung SET mat_khau_hash = :password_hash WHERE id = :id");
            $stmt->execute([
                'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
                'id' => $userId,
            ]);
            $success = 'Cập nhật mật khẩu thành công.';
        }
    }
}

require_once __DIR__ . '/../includes/data.php';

$stmt = $pdo->prepare("
    SELECT u.*,
           u.id_don_vi AS department_id,
           u.ten_dang_nhap AS username,
           u.trang_thai AS status,
           u.ho_ten AS full_name,
           u.duong_dan_anh_dai_dien AS avatar_path,
           r.ten_vai_tro AS role_name,
           d.ten_don_vi AS department_name
    FROM nguoi_dung u
    JOIN vai_tro r ON r.id = u.id_vai_tro
    LEFT JOIN don_vi d ON d.id = u.id_don_vi
    WHERE u.id = :id
    LIMIT 1
");
$stmt->execute(['id' => $userId]);
$profile = $stmt->fetch();

$pageTitle = page_title('Thông tin cá nhân');
$heading = 'Thông tin cá nhân';
include __DIR__ . '/../includes/header.php';
?>
<div class="row g-4 profile-layout">
    <div class="col-xl-5">
        <div class="panel h-100">
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
                <input type="hidden" name="action" value="update_avatar">
                <div class="mb-3">
                    <label class="form-label">Ảnh đại diện</label>
                    <input class="form-control" name="avatar_file" type="file" accept=".jpg,.jpeg,.png,.webp,.gif">
                    <div class="form-text">Chọn ảnh từ thiết bị. Dung lượng tối đa <?= $avatarMaxMb ?>MB.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Họ tên</label>
                    <input class="form-control bg-light" value="<?= htmlspecialchars($profile['full_name']) ?>" readonly title="Chỉ Quản trị viên mới có quyền đổi họ tên">
                </div>
                <div class="mb-3">
                    <label class="form-label">Tên đăng nhập</label>
                    <input class="form-control bg-light" value="<?= htmlspecialchars($profile['username']) ?>" readonly title="Không thể thay đổi tên đăng nhập">
                </div>
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input class="form-control bg-light" value="<?= htmlspecialchars($profile['email']) ?>" readonly title="Chỉ Quản trị viên mới có quyền đổi email">
                </div>
                <div class="mb-4">
                    <label class="form-label">Vai trò</label>
                    <input class="form-control bg-light" value="<?= htmlspecialchars($profile['role_name']) ?>" readonly>
                </div>

                <button class="btn btn-primary w-100" type="submit">
                    <i class="bi bi-save me-1"></i> Lưu ảnh đại diện
                </button>
            </form>
        </div>
    </div>
    <div class="col-xl-7">
        <div class="panel h-100">
            <h2 class="h5 mb-3">Thông tin hệ thống</h2>
            <div class="profile-info-table">
                <table class="table">
                    <tbody>
                    <tr>
                        <th style="width: 220px;">Mã người dùng</th>
                        <td><?= htmlspecialchars($profile['ma_nguoi_dung'] ?? ('ND' . str_pad($profile['id'], 3, '0', STR_PAD_LEFT))) ?></td>
                    </tr>
                    <tr>
                        <th>Tên đăng nhập</th>
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
                        <th>Trường</th>
                        <td><?= htmlspecialchars($trainingProgram['school']) ?></td>
                    </tr>
                    </tbody>
                </table>
            </div>

            <hr class="my-4">
            <h2 class="h5 mb-3"><i class="bi bi-shield-lock me-1"></i> Đổi mật khẩu</h2>
            <form method="post">
                <input type="hidden" name="action" value="change_password">
                <div class="mb-3">
                    <label class="form-label">Mật khẩu mới</label>
                    <input class="form-control" type="password" name="new_password" placeholder="Nhập mật khẩu mới (ít nhất 6 ký tự)" required>
                </div>
                <div class="mb-4">
                    <label class="form-label">Nhập lại mật khẩu mới</label>
                    <input class="form-control" type="password" name="confirm_password" placeholder="Xác nhận lại mật khẩu mới" required>
                </div>
                <button class="btn btn-primary" type="submit">
                    <i class="bi bi-key me-1"></i> Cập nhật mật khẩu
                </button>
            </form>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
