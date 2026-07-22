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
        $resetEmail = trim($_POST['reset_email'] ?? '');

        if ($resetEmail === '' || !filter_var($resetEmail, FILTER_VALIDATE_EMAIL)) {
            $resetError = 'Vui lòng nhập email hợp lệ.';
        } else {
            $stmt = db()->prepare('SELECT id, ho_ten AS full_name, email FROM nguoi_dung WHERE email = :email LIMIT 1');
            $stmt->execute(['email' => $resetEmail]);
            $resetUser = $stmt->fetch();

            if (!$resetUser) {
                $resetError = 'Email này chưa được đăng ký trong hệ thống. Vui lòng kiểm tra lại thông tin.';
            } else {
                $code = (string) random_int(100000, 999999);
                $_SESSION['password_reset'] = [
                    'user_id' => (int) $resetUser['id'],
                    'email' => $resetUser['email'],
                    'code_hash' => password_hash($code, PASSWORD_DEFAULT),
                    'expires_at' => time() + 300,
                    'verified' => false,
                ];

                $subject = 'Ma xac minh dat lai mat khau';
                $message = "Xin chao {$resetUser['full_name']},\n\nMa xac minh dat lai mat khau cua ban la: {$code}\nMa co hieu luc trong 5 phut.\n\nHe thong CSDL minh chung kiem dinh.";
                $headers = 'From: no-reply@fbu.edu.vn' . "\r\n" .
                    'Content-Type: text/plain; charset=UTF-8';
                $sent = @mail($resetUser['email'], $subject, $message, $headers);

                $resetStep = 'code';
                $resetSuccess = $sent
                    ? 'Mã xác minh đã được gửi về email đã đăng ký. Vui lòng kiểm tra hộp thư.'
                    : 'XAMPP chưa cấu hình SMTP nên chưa gửi được email. Mã xác minh demo: ' . $code;
            }
        }
    } elseif ($action === 'verify_code') {
        $showResetModal = true;
        $resetStep = 'code';
        $inputCode = trim($_POST['verification_code'] ?? '');
        $resetState = $_SESSION['password_reset'] ?? null;

        if (!$resetState || empty($resetState['code_hash'])) {
            $resetError = 'Phiên xác minh không hợp lệ. Vui lòng nhập lại email.';
            $resetStep = 'email';
        } elseif (($resetState['expires_at'] ?? 0) < time()) {
            unset($_SESSION['password_reset']);
            $resetError = 'Mã xác minh đã hết hạn. Vui lòng yêu cầu mã mới.';
            $resetStep = 'email';
        } elseif (!password_verify($inputCode, $resetState['code_hash'])) {
            $resetError = 'Mã xác minh không chính xác. Vui lòng kiểm tra lại thông tin.';
        } else {
            $_SESSION['password_reset']['verified'] = true;
            $resetStep = 'password';
            $resetSuccess = 'Xác minh thành công. Vui lòng nhập mật khẩu mới.';
        }
    } elseif ($action === 'complete_reset') {
        $showResetModal = true;
        $resetStep = 'password';
        $resetState = $_SESSION['password_reset'] ?? null;
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (!$resetState || empty($resetState['verified'])) {
            $resetError = 'Bạn cần xác minh mã trước khi đặt mật khẩu mới.';
            $resetStep = 'email';
        } elseif (($resetState['expires_at'] ?? 0) < time()) {
            unset($_SESSION['password_reset']);
            $resetError = 'Phiên đặt lại mật khẩu đã hết hạn. Vui lòng yêu cầu mã mới.';
            $resetStep = 'email';
        } elseif (strlen($newPassword) < 6) {
            $resetError = 'Mật khẩu mới phải có ít nhất 6 ký tự.';
        } elseif ($newPassword !== $confirmPassword) {
            $resetError = 'Mật khẩu xác nhận chưa trùng khớp.';
        } else {
            $update = db()->prepare('UPDATE nguoi_dung SET mat_khau_hash = :password_hash, ngay_cap_nhat = NOW() WHERE id = :id');
            $update->execute([
                'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
                'id' => (int) $resetState['user_id'],
            ]);
            unset($_SESSION['password_reset']);
            $resetStep = 'done';
            $resetSuccess = 'Đặt lại mật khẩu thành công. Bạn có thể đăng nhập bằng mật khẩu mới.';
        }
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        $stmt = db()->prepare("
            SELECT u.*, u.mat_khau_hash AS password_hash, u.trang_thai AS status, r.ma_vai_tro AS role_code
            FROM nguoi_dung u
            JOIN vai_tro r ON r.id = u.id_vai_tro
            WHERE u.ten_dang_nhap = :username
            LIMIT 1
        ");
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $error = 'Thông tin đăng nhập không chính xác.';
        } elseif ($user['status'] !== 'active') {
            $error = 'Tài khoản đang bị khóa. Vui lòng liên hệ quản trị viên.';
        } else {
            $_SESSION['user_id'] = (int) $user['id'];
            $_SESSION['role'] = $user['role_code'];
            create_login_token((int) $user['id'], !empty($_POST['remember']));

            $update = db()->prepare('UPDATE nguoi_dung SET dang_nhap_cuoi = NOW() WHERE id = :id');
            $update->execute(['id' => $user['id']]);

            $successRedirect = $user['role_code'] === 'admin'
                ? base_url('admin/dashboard.php')
                : base_url('user/search.php');
        }
    }
}

$resetEmailValue = htmlspecialchars($_SESSION['password_reset']['email'] ?? ($_POST['reset_email'] ?? ''), ENT_QUOTES, 'UTF-8');
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
        <p>Chuẩn hóa lưu trữ, tra cứu, thống kê và khai thác minh chứng cho Trường Đại học Tài chính - Ngân hàng Hà Nội.</p>
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
            <p class="small text-secondary mt-3 mb-0">Tài khoản mẫu: <strong>admin / 123456</strong> hoặc <strong>kiemdinhtt / 123456</strong>.</p>
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
                        <?php if ($resetStep === 'email'): ?>
                            Nhập email đã đăng ký để nhận mã xác minh.
                        <?php elseif ($resetStep === 'code'): ?>
                            Nhập mã xác minh đã được gửi về email.
                        <?php elseif ($resetStep === 'password'): ?>
                            Mã xác minh hợp lệ. Vui lòng tạo mật khẩu mới.
                        <?php else: ?>
                            Hoàn tất đặt lại mật khẩu.
                        <?php endif; ?>
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
                        <?= htmlspecialchars($resetSuccess) ?>
                    </div>
                <?php endif; ?>

                <?php if ($resetStep === 'email'): ?>
                    <form action="<?= base_url('auth/login.php') ?>" method="post">
                        <input type="hidden" name="action" value="forgot_request">
                        <div class="mb-3">
                            <label class="form-label">Email đã đăng ký</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                <input class="form-control" type="email" name="reset_email" value="<?= $resetEmailValue ?>" required>
                            </div>
                        </div>
                        <div class="d-flex justify-content-end gap-2">
                            <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Hủy</button>
                            <button class="btn btn-primary" type="submit">
                                <i class="bi bi-send me-1"></i> Gửi mã xác minh
                            </button>
                        </div>
                    </form>
                <?php elseif ($resetStep === 'code'): ?>
                    <form action="<?= base_url('auth/login.php') ?>" method="post">
                        <input type="hidden" name="action" value="verify_code">
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input class="form-control" value="<?= $resetEmailValue ?>" disabled>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Mã xác minh</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-shield-check"></i></span>
                                <input class="form-control verification-code-input" name="verification_code" inputmode="numeric" maxlength="6" placeholder="Nhập 6 số" required>
                            </div>
                            <div class="form-text">Mã xác minh có hiệu lực trong 5 phút.</div>
                        </div>
                        <div class="d-flex justify-content-between gap-2">
                            <button class="btn btn-outline-secondary" type="submit" name="action" value="forgot_request">
                                <i class="bi bi-arrow-repeat me-1"></i> Gửi lại mã
                            </button>
                            <button class="btn btn-primary" type="submit">
                                <i class="bi bi-check2-circle me-1"></i> Xác minh
                            </button>
                        </div>
                        <input type="hidden" name="reset_email" value="<?= $resetEmailValue ?>">
                    </form>
                <?php elseif ($resetStep === 'password'): ?>
                    <form action="<?= base_url('auth/login.php') ?>" method="post">
                        <input type="hidden" name="action" value="complete_reset">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Mật khẩu mới</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                    <input class="form-control" type="password" name="new_password" minlength="6" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Nhập lại mật khẩu mới</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-shield-lock"></i></span>
                                    <input class="form-control" type="password" name="confirm_password" minlength="6" required>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex justify-content-end gap-2 mt-4">
                            <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Hủy</button>
                            <button class="btn btn-primary" type="submit">
                                <i class="bi bi-key me-1"></i> Cập nhật mật khẩu
                            </button>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="text-end">
                        <button class="btn btn-primary" type="button" data-bs-dismiss="modal">Quay lại đăng nhập</button>
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
