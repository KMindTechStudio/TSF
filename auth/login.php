<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../config/database.php';

$appName = 'Hệ thống CSDL minh chứng kiểm định chất lượng CTĐT ngành CNTT';
$error = '';
$successRedirect = '';
$resetError = '';
$resetSuccess = '';
$showResetModal = false;
$resetStep = 'email';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'login';

    if ($action === 'forgot_request') {
        $showResetModal = true;
        $resetStep = 'email';
        $email = trim($_POST['reset_email'] ?? '');

        if ($email === '') {
            $resetError = 'Vui lòng nhập địa chỉ email.';
        } else {
            $stmt = db()->prepare('SELECT MaNguoiDung AS id, HoTen AS full_name, TenDangNhap AS username, Email FROM NguoiDung WHERE LOWER(Email) = LOWER(:email) LIMIT 1');
            $stmt->execute(['email' => $email]);
            $resetUser = $stmt->fetch();

            if (!$resetUser) {
                $resetError = 'email không khớp trong hệ thống';
            } else {
                $defaultPassword = '123456';
                $passwordHash = password_hash($defaultPassword, PASSWORD_DEFAULT);
                $update = db()->prepare('UPDATE NguoiDung SET MatKhau = :password_hash WHERE MaNguoiDung = :id');
                $update->execute([
                    'password_hash' => $passwordHash,
                    'id'            => $resetUser['id'],
                ]);
                log_activity('reset_mat_khau', 'nguoi_dung', 0, 'Reset mật khẩu về mặc định 123456 cho: ' . $resetUser['full_name'] . ' (' . $resetUser['username'] . ')');

                $resetStep = 'done';
                $resetSuccess = 'Đặt lại mật khẩu thành công! Tài khoản <strong>' . htmlspecialchars($resetUser['username']) . '</strong> (' . htmlspecialchars($resetUser['full_name']) . ') đã được reset về mật khẩu mặc định: <strong>' . $defaultPassword . '</strong>. Bạn có thể sử dụng mật khẩu này để đăng nhập ngay.';
                $_POST['username'] = $resetUser['username'];
            }
        }
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        $stmt = db()->prepare("
            SELECT MaNguoiDung AS id, MatKhau AS password_hash, TrangThai AS status, VaiTro AS role_code
            FROM NguoiDung
            WHERE TenDangNhap = :username
            LIMIT 1
        ");
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $error = 'Thông tin đăng nhập không chính xác.';
        } elseif ((int) $user['status'] !== 1) {
            $error = 'Tài khoản đang bị khóa. Vui lòng liên hệ quản trị viên.';
        } else {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role_code'];
            create_login_token($user['id'], !empty($_POST['remember']));
            log_activity('dang_nhap', 'he_thong', 0, 'Đăng nhập hệ thống');

            $update = db()->prepare('UPDATE NguoiDung SET DangNhapCuoi = NOW() WHERE MaNguoiDung = :id');
            $update->execute(['id' => $user['id']]);

            $successRedirect = $user['role_code'] === 'admin'
                ? base_url('admin/dashboard.php')
                : base_url('user/search.php');
        }
    }
}

$resetEmailValue = htmlspecialchars($_POST['reset_email'] ?? '', ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Đăng nhập | <?= htmlspecialchars($appName) ?></title>
    <link rel="icon" type="image/png" href="<?= base_url('assets/images/fbu-logo.png?v=2') ?>">
    <link rel="shortcut icon" type="image/png" href="<?= base_url('assets/images/fbu-logo.png?v=2') ?>">
    <link rel="apple-touch-icon" href="<?= base_url('assets/images/fbu-logo.png?v=2') ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= base_url('assets/css/app.css?v=10') ?>" rel="stylesheet">
</head>
<body>
<main class="login-page">
    <section class="login-hero">
        <img class="login-logo" src="<?= base_url('assets/images/fbu-logo.png') ?>" alt="FBU">
        <span class="badge text-bg-light text-dark mb-3 align-self-start">Đề án thạc sĩ</span>
        <h1>Cơ sở dữ liệu minh chứng phục vụ kiểm định chất lượng CTĐT ngành CNTT</h1>
        <p>Chuẩn hóa lưu trữ, tìm kiếm, thống kê và khai thác minh chứng cho Trường Đại học Tài chính - Ngân hàng Hà Nội.</p>
    </section>
    <section class="login-card-wrap">
        <form class="login-card" action="<?= base_url('auth/login.php') ?>" method="post">
            <input type="hidden" name="action" value="login">
            <div class="mb-4">
                <h2 class="h4 mb-1">Đăng nhập hệ thống</h2>
                <p class="text-secondary mb-0">Sử dụng tài khoản trong bảng <code>users</code> để truy cập kho minh chứng.</p>
            </div>

            <div class="mb-3">
                <label class="form-label">Tên đăng nhập</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                    <input class="form-control" name="username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" placeholder="admin" required>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Mật khẩu</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                    <input class="form-control" type="password" name="password" placeholder="••••••••" required>
                </div>
            </div>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="remember" name="remember" value="1">
                    <label class="form-check-label" for="remember">Ghi nhớ đăng nhập</label>
                </div>
                <a href="#" class="text-success fw-semibold" data-bs-toggle="modal" data-bs-target="#forgotPasswordModal">Quên mật khẩu?</a>
            </div>
            <button class="btn btn-primary w-100" type="submit">
                <i class="bi bi-box-arrow-in-right me-1"></i> Đăng nhập
            </button>
            <?php if ($error): ?>
                <div class="alert alert-danger mt-3 mb-0">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
        </form>
    </section>
</main>

<div class="modal fade forgot-password-modal" id="forgotPasswordModal" tabindex="-1" aria-labelledby="forgotPasswordModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h2 class="modal-title h5" id="forgotPasswordModalLabel">Quên mật khẩu</h2>
                    <p class="text-secondary mb-0 small">
                        <?= $resetStep === 'done' ? 'Đặt lại mật khẩu thành công.' : 'Nhập email đã đăng ký để nhận mã xác minh.' ?>
                    </p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <div class="modal-body">
                <?php if ($resetError): ?>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i>
                        <?= htmlspecialchars($resetError) ?>
                    </div>
                <?php endif; ?>
                <?php if ($resetSuccess): ?>
                    <div class="alert alert-success">
                        <i class="bi bi-check-circle-fill me-1"></i>
                        <?= $resetSuccess ?>
                    </div>
                <?php endif; ?>

                <?php if ($resetStep !== 'done'): ?>
                    <form action="<?= base_url('auth/login.php') ?>" method="post">
                        <input type="hidden" name="action" value="forgot_request">
                        <div class="mb-3">
                            <label class="form-label">Email đã đăng ký</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                <input class="form-control" type="email" name="reset_email" value="<?= $resetEmailValue ?>" placeholder="Nhập email tài khoản" required>
                            </div>
                        </div>
                        <div class="d-flex justify-content-end gap-2">
                            <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Hủy</button>
                            <button class="btn btn-primary" type="submit">
                                <i class="bi bi-send me-1"></i> Gửi mã xác minh
                            </button>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="text-end mt-3">
                        <button class="btn btn-primary" type="button" data-bs-dismiss="modal">
                            <i class="bi bi-box-arrow-in-right me-1"></i> Quay lại đăng nhập
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php if ($successRedirect): ?>
<div class="modal fade login-success-modal" id="loginSuccessModal" tabindex="-1" aria-labelledby="loginSuccessModalLabel" aria-hidden="true" data-redirect="<?= htmlspecialchars($successRedirect, ENT_QUOTES, 'UTF-8') ?>">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-body text-center">
                <div class="login-success-icon">
                    <i class="bi bi-check2-circle"></i>
                </div>
                <h2 class="h5 mb-2" id="loginSuccessModalLabel">Đăng nhập thành công</h2>
                <p class="text-secondary mb-0">Đang chuyển vào hệ thống...</p>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= base_url('assets/js/app.js?v=14') ?>"></script>
<?php if ($showResetModal): ?>
<script>
    const forgotPasswordModal = document.getElementById('forgotPasswordModal');
    new bootstrap.Modal(forgotPasswordModal).show();
</script>
<?php endif; ?>
<?php if ($successRedirect): ?>
<script>
    const loginSuccessModal = document.getElementById('loginSuccessModal');
    const redirectUrl = loginSuccessModal.dataset.redirect;
    const modal = new bootstrap.Modal(loginSuccessModal, {
        backdrop: 'static',
        keyboard: false
    });

    modal.show();
    setTimeout(() => {
        window.location.href = redirectUrl;
    }, 1100);
</script>
<?php endif; ?>
</body>
</html>
