<?php
require_once __DIR__ . '/../includes/helpers.php';
require_login();
require_roles(['admin']);
require_once __DIR__ . '/../config/database.php';

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $userId = (int) $_SESSION['user_id'];

    $stmt = db()->prepare('SELECT password_hash FROM users WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $userId]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($currentPassword, $user['password_hash'])) {
        $error = 'Mật khẩu hiện tại không chính xác.';
    } elseif (strlen($newPassword) < 6) {
        $error = 'Mật khẩu mới phải có ít nhất 6 ký tự.';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'Mật khẩu xác nhận chưa trùng khớp.';
    } else {
        $update = db()->prepare('UPDATE users SET password_hash = :password_hash, updated_at = NOW() WHERE id = :id');
        $update->execute([
            'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
            'id' => $userId,
        ]);
        $success = 'Cập nhật mật khẩu thành công.';
    }
}

require_once __DIR__ . '/../includes/data.php';
$pageTitle = page_title('Đổi mật khẩu');
$heading = 'Đổi mật khẩu';
include __DIR__ . '/../includes/header.php';
?>
<div class="row g-4">
    <div class="col-xl-5">
        <div class="panel">
            <h2 class="h5 mb-3">Cập nhật mật khẩu</h2>
            <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
            <form method="post">
                <div class="mb-3">
                    <label class="form-label">Mật khẩu hiện tại</label>
                    <input class="form-control" type="password" name="current_password" placeholder="Nhập mật khẩu hiện tại" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Mật khẩu mới</label>
                    <input class="form-control" type="password" name="new_password" placeholder="Nhập mật khẩu mới" required>
                </div>
                <div class="mb-4">
                    <label class="form-label">Nhập lại mật khẩu mới</label>
                    <input class="form-control" type="password" name="confirm_password" placeholder="Xác nhận mật khẩu mới" required>
                </div>
                <button class="btn btn-primary w-100" type="submit">
                    <i class="bi bi-key me-1"></i> Cập nhật mật khẩu
                </button>
            </form>
        </div>
    </div>
    <div class="col-xl-7">
        <div class="panel">
            <h2 class="h5 mb-3">Lưu ý bảo mật</h2>
            <p class="text-secondary mb-0">Mật khẩu nên có tối thiểu 8 ký tự, bao gồm chữ hoa, chữ thường, số và ký tự đặc biệt. Không dùng lại mật khẩu của email hoặc các hệ thống cá nhân.</p>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
