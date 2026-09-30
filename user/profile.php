<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/helpers.php';
require_login();
require_once __DIR__ . '/../config/database.php';

$pdo = db();
$userId = trim((string)($_SESSION['user_id'] ?? 'ND001'));
$success = '';
$error = '';
$activeEdit = '';
$avatarMaxMb = 20;
$avatarMaxBytes = $avatarMaxMb * 1024 * 1024;

// Ensure table cau_hinh exists
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS cau_hinh (
            khoa VARCHAR(100) NOT NULL PRIMARY KEY,
            gia_tri TEXT NULL,
            ngay_cap_nhat TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
} catch (Throwable $e) {
    // Ignore if already exists
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── 1. Cập nhật thông tin cá nhân & Ảnh đại diện ─────────────────────────
    if ($action === 'update_profile') {
        $fullName = trim($_POST['full_name'] ?? '');
        $phone    = trim($_POST['phone'] ?? '');

        if ($fullName === '') {
            $error = 'Họ và tên không được để trống.';
            $activeEdit = 'profile';
        } else {
            $newAvatarPath = null;
            $avatarFile = $_FILES['avatar_file'] ?? null;

            if ($avatarFile && $avatarFile['error'] !== UPLOAD_ERR_NO_FILE) {
                $allowedAvatarExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
                $extension = strtolower(pathinfo($avatarFile['name'], PATHINFO_EXTENSION));

                if ($avatarFile['error'] !== UPLOAD_ERR_OK) {
                    $error = 'Upload ảnh đại diện không thành công. Vui lòng thử lại.';
                    $activeEdit = 'profile';
                } elseif ((int) $avatarFile['size'] > $avatarMaxBytes) {
                    $error = 'Không được upload ảnh đại diện quá ' . $avatarMaxMb . 'MB.';
                    $activeEdit = 'profile';
                } elseif (!in_array($extension, $allowedAvatarExtensions, true)) {
                    $error = 'Ảnh đại diện chỉ chấp nhận định dạng JPG, PNG, WebP hoặc GIF.';
                    $activeEdit = 'profile';
                } else {
                    $avatarDir = realpath(__DIR__ . '/../uploads/avatars');
                    if ($avatarDir === false) {
                        $avatarDir = __DIR__ . '/../uploads/avatars';
                        mkdir($avatarDir, 0777, true);
                    }

                    $storedName = 'avatar_user_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $userId) . '_' . date('YmdHis') . '.' . $extension;
                    $targetPath = rtrim($avatarDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $storedName;

                    if (!move_uploaded_file($avatarFile['tmp_name'], $targetPath)) {
                        $error = 'Không thể lưu ảnh đại diện vào hệ thống. Vui lòng kiểm tra quyền ghi thư mục.';
                        $activeEdit = 'profile';
                    } else {
                        $newAvatarPath = 'uploads/avatars/' . $storedName;
                    }
                }
            }

            if (!$error) {
                if ($newAvatarPath !== null) {
                    $stmt = $pdo->prepare("
                        UPDATE NguoiDung 
                        SET HoTen = :full_name, SoDienThoai = :phone, DuongDanAnhDaiDien = :avatar 
                        WHERE MaNguoiDung = :id
                    ");
                    $stmt->execute([
                        'full_name' => $fullName,
                        'phone'     => $phone,
                        'avatar'    => $newAvatarPath,
                        'id'        => $userId
                    ]);
                } else {
                    $stmt = $pdo->prepare("
                        UPDATE NguoiDung 
                        SET HoTen = :full_name, SoDienThoai = :phone 
                        WHERE MaNguoiDung = :id
                    ");
                    $stmt->execute([
                        'full_name' => $fullName,
                        'phone'     => $phone,
                        'id'        => $userId
                    ]);
                }
                $_SESSION['user_name'] = $fullName;
                log_activity('cap_nhat', 'nguoi_dung', 0, 'Cập nhật thông tin cá nhân tài khoản ' . $userId);
                $success = 'Cập nhật thông tin cá nhân thành công.';
            }
        }
    }

    // ── 2. Cập nhật thông tin Hệ thống & Đào tạo ──────────────────────────────
    if ($action === 'update_system_info') {
        $school      = trim($_POST['school'] ?? '');
        $faculty     = trim($_POST['faculty'] ?? '');
        $programName = trim($_POST['program_name'] ?? '');
        $programCode = trim($_POST['program_code'] ?? '');
        $degree      = trim($_POST['degree'] ?? '');
        $cycle       = trim($_POST['cycle'] ?? '');

        if ($school === '') {
            $error = 'Tên đơn vị chủ quản không được để trống.';
            $activeEdit = 'system';
        } elseif ($programName === '') {
            $error = 'Tên chương trình đào tạo không được để trống.';
            $activeEdit = 'system';
        } else {
            $settingsData = [
                'school'       => $school,
                'faculty'      => $faculty,
                'program_name' => $programName,
                'program_code' => $programCode,
                'degree'       => $degree,
                'cycle'        => $cycle,
            ];

            foreach ($settingsData as $key => $val) {
                $stmt = $pdo->prepare("
                    INSERT INTO cau_hinh (khoa, gia_tri) 
                    VALUES (:k, :v) 
                    ON DUPLICATE KEY UPDATE gia_tri = :v2
                ");
                $stmt->execute(['k' => $key, 'v' => $val, 'v2' => $val]);
            }

            log_activity('cap_nhat', 'he_thong', 0, 'Cập nhật cấu hình hệ thống & CTĐT bởi ' . $userId);
            $success = 'Cập nhật thông tin hệ thống & đào tạo thành công.';
        }
    }

    // ── 3. Đổi mật khẩu ──────────────────────────────────────────────────────
    if ($action === 'change_password') {
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if ($newPassword === '') {
            $error = 'Vui lòng nhập mật khẩu mới.';
        } elseif (strlen($newPassword) < 6) {
            $error = 'Mật khẩu mới phải có độ dài ít nhất 6 ký tự.';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'Mật khẩu xác nhận không trùng khớp với mật khẩu mới.';
        } else {
            $stmt = $pdo->prepare("UPDATE NguoiDung SET MatKhau = :password_hash WHERE MaNguoiDung = :id");
            $stmt->execute([
                'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
                'id' => $userId,
            ]);
            log_activity('cap_nhat', 'nguoi_dung', 0, 'Đổi mật khẩu tài khoản ' . $userId);
            $success = 'Cập nhật mật khẩu tài khoản thành công.';
        }
    }
}

require_once __DIR__ . '/../includes/data.php';

$stmt = $pdo->prepare("
    SELECT u.*,
           u.TenDangNhap AS username,
           u.TrangThai AS status,
           u.HoTen AS full_name,
           u.Email AS email,
           u.SoDienThoai AS phone,
           u.DuongDanAnhDaiDien AS avatar_path,
           u.VaiTro AS role_code,
           u.NgayTao AS created_at
    FROM NguoiDung u
    WHERE u.MaNguoiDung = :id
    LIMIT 1
");
$stmt->execute(['id' => $userId]);
$profile = $stmt->fetch();

if (!$profile) {
    $profile = [
        'MaNguoiDung' => $userId,
        'username'    => $currentUser['username'] ?? 'admin',
        'full_name'   => $currentUser['name'] ?? 'Quản trị viên',
        'email'       => $currentUser['email'] ?? 'admin@fbu.edu.vn',
        'phone'       => '',
        'role_code'   => $currentUser['role'] ?? 'admin',
        'avatar_path' => $currentUser['avatar'] ?? null,
        'status'      => 1,
        'created_at'  => date('Y-m-d H:i:s')
    ];
}
$isAdmin = ($profile['role_code'] ?? 'user') === 'admin';
$roleName = $isAdmin ? 'Quản trị viên' : 'Người dùng / Cán bộ';
$accountStatus = (int)($profile['status'] ?? 1) === 1 ? 'Đang hoạt động' : 'Ngưng áp dụng';

$pageTitle = page_title('Thông tin cá nhân');
$heading = 'Thông tin cá nhân';
include __DIR__ . '/../includes/header.php';
?>

<style>
/* ============================================================
   PREMIUM REDESIGNED PROFILE STYLES WITH EDIT TOGGLES
============================================================ */

/* Entrance Animation */
@keyframes profileFadeInUp {
    from {
        opacity: 0;
        transform: translateY(18px) scale(0.99);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

.profile-anim-card {
    animation: profileFadeInUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) both;
}

.delay-card-1 { animation-delay: 0.08s; }
.delay-card-2 { animation-delay: 0.16s; }
.delay-card-3 { animation-delay: 0.24s; }

/* Main Profile Cards */
.profile-card {
    background: #ffffff;
    border: 1px solid rgba(226, 232, 240, 0.85);
    border-radius: 18px;
    padding: 26px 28px;
    box-shadow: 0 4px 20px rgba(18, 48, 95, 0.04);
    transition: all 0.25s ease;
    height: 100%;
}
.profile-card:hover {
    box-shadow: 0 10px 28px rgba(18, 48, 95, 0.08);
}
html[data-theme="dark"] .profile-card {
    background: #132744;
    border-color: rgba(255, 255, 255, 0.08);
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.25);
}

/* Avatar Upload Circle */
.profile-avatar-wrapper {
    position: relative;
    width: 115px;
    height: 115px;
    margin: 0 auto 16px auto;
}
.profile-avatar-img {
    width: 115px;
    height: 115px;
    border-radius: 50%;
    object-fit: cover;
    border: 4px solid #ffffff;
    box-shadow: 0 6px 18px rgba(18, 48, 95, 0.15);
    display: block;
}
html[data-theme="dark"] .profile-avatar-img {
    border-color: #1a365d;
    box-shadow: 0 6px 18px rgba(0, 0, 0, 0.35);
}

.profile-avatar-placeholder {
    width: 115px;
    height: 115px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    font-size: 2.5rem;
    color: #ffffff;
    border: 4px solid #ffffff;
    box-shadow: 0 6px 18px rgba(18, 48, 95, 0.15);
}
.avatar-bg-admin { background: linear-gradient(135deg, #ef4444, #b91c1c); }
.avatar-bg-user  { background: linear-gradient(135deg, #3b82f6, #1d4ed8); }

.avatar-upload-badge {
    position: absolute;
    bottom: 2px;
    right: 2px;
    width: 36px;
    height: 36px;
    background: var(--brand, #2f64ad);
    color: #ffffff;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    cursor: pointer;
    border: 3px solid #ffffff;
    box-shadow: 0 3px 10px rgba(0, 0, 0, 0.25);
    transition: all 0.2s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.avatar-upload-badge:hover {
    transform: scale(1.12);
    background: #1e4b8a;
}
html[data-theme="dark"] .avatar-upload-badge {
    border-color: #132744;
}

/* Form Styles */
.form-section-title {
    font-size: 1.05rem;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 4px;
}
html[data-theme="dark"] .form-section-title {
    color: #e2e8f0;
}

.profile-field-label {
    font-size: 0.85rem;
    font-weight: 600;
    color: #475569;
    margin-bottom: 6px;
    display: flex;
    align-items: center;
    gap: 6px;
}
html[data-theme="dark"] .profile-field-label {
    color: #94a3b8;
}

.profile-input-group .form-control,
.profile-input-group .form-select {
    border-radius: 10px;
    border: 1px solid #cbd5e1;
    padding: 10px 14px;
    font-size: 0.92rem;
    transition: all 0.2s ease;
}
.profile-input-group .form-control:focus,
.profile-input-group .form-select:focus {
    border-color: var(--brand, #2f64ad);
    box-shadow: 0 0 0 3px rgba(47, 100, 173, 0.15);
}
html[data-theme="dark"] .profile-input-group .form-control,
html[data-theme="dark"] .profile-input-group .form-select {
    background-color: #0d1e36;
    border-color: rgba(255, 255, 255, 0.15);
    color: #f1f5f9;
}
html[data-theme="dark"] .profile-input-group .form-control:focus,
html[data-theme="dark"] .profile-input-group .form-select:focus {
    border-color: #58b7e6;
    box-shadow: 0 0 0 3px rgba(88, 183, 230, 0.2);
}

/* Info Tiles Grid */
.info-tile {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 16px 18px;
    display: flex;
    align-items: flex-start;
    gap: 14px;
    transition: all 0.2s ease;
    height: 100%;
}
.info-tile:hover {
    background: #f1f5f9;
    border-color: #cbd5e1;
    transform: translateY(-2px);
}
html[data-theme="dark"] .info-tile {
    background: #1a365d;
    border-color: rgba(255, 255, 255, 0.08);
}
html[data-theme="dark"] .info-tile:hover {
    background: #234677;
    border-color: rgba(88, 183, 230, 0.3);
}

.info-tile-icon {
    width: 42px;
    height: 42px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    flex-shrink: 0;
}
.icon-blue   { background: rgba(37, 99, 235, 0.12); color: #2563eb; }
.icon-purple { background: rgba(124, 58, 237, 0.12); color: #7c3aed; }
.icon-emerald{ background: rgba(16, 185, 129, 0.12); color: #10b981; }
.icon-amber  { background: rgba(245, 158, 11, 0.12); color: #d97706; }
.icon-cyan   { background: rgba(6, 182, 212, 0.12); color: #0891b2; }

.btn-brand {
    background-color: var(--brand, #2f64ad);
    color: #ffffff;
    border: none;
}
.btn-brand:hover {
    background-color: #1e4b8a;
    color: #ffffff;
}

/* Edit Toggle Container Transitions */
.mode-container {
    transition: opacity 0.25s ease;
}
</style>

<!-- Alert Messages -->
<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show rounded-3 shadow-xs mb-4 p-3 profile-anim-card" role="alert">
        <i class="bi bi-check-circle-fill me-2 fs-5 align-middle"></i>
        <?= htmlspecialchars($success) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Đóng"></button>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show rounded-3 shadow-xs mb-4 p-3 profile-anim-card" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2 fs-5 align-middle"></i>
        <?= htmlspecialchars($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Đóng"></button>
    </div>
<?php endif; ?>

<div class="row g-4 profile-layout">
    <!-- ─── 1. LEFT COLUMN: PROFILE IDENTITY (VIEW / EDIT MODE) ──────────────── -->
    <div class="col-12 col-lg-5 col-xl-4">
        <div class="profile-card profile-anim-card delay-card-1">

            <!-- ── 1.A VIEW MODE (Default) ────────────────────────────────── -->
            <div id="personalInfoViewMode" class="mode-container <?= $activeEdit === 'profile' ? 'd-none' : '' ?>">
                <!-- Avatar Display -->
                <div class="text-center mb-3">
                    <div class="profile-avatar-wrapper">
                        <?php if (!empty($profile['avatar_path']) && file_exists(__DIR__ . '/../' . $profile['avatar_path'])): ?>
                            <img src="<?= base_url(htmlspecialchars($profile['avatar_path'])) ?>" alt="<?= htmlspecialchars($profile['full_name']) ?>" class="profile-avatar-img">
                        <?php else: ?>
                            <div class="profile-avatar-placeholder <?= $isAdmin ? 'avatar-bg-admin' : 'avatar-bg-user' ?>">
                                <?= user_initials($profile['full_name']) ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <h2 class="h5 fw-bold mb-1 text-dark"><?= htmlspecialchars($profile['full_name']) ?></h2>
                    
                    <div class="d-flex flex-wrap justify-content-center gap-2 mb-3">
                        <?php if ($isAdmin): ?>
                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-3 py-1.5 small fw-semibold">
                                <i class="bi bi-shield-lock-fill me-1"></i>Quản trị viên
                            </span>
                        <?php else: ?>
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-3 py-1.5 small fw-semibold">
                                <i class="bi bi-person-badge-fill me-1"></i>Người dùng
                            </span>
                        <?php endif; ?>

                        <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3 py-1.5 small fw-semibold">
                            <i class="bi bi-check-circle-fill me-1"></i><?= $accountStatus ?>
                        </span>
                    </div>
                </div>

                <!-- Personal Info Details List -->
                <div class="border-top pt-3 mb-4">
                    <div class="d-flex align-items-center justify-content-between py-2 border-bottom">
                        <span class="text-muted small"><i class="bi bi-person-lines-fill text-primary me-2"></i>Họ và tên</span>
                        <span class="fw-bold text-dark"><?= htmlspecialchars($profile['full_name']) ?></span>
                    </div>
                    <div class="d-flex align-items-center justify-content-between py-2 border-bottom">
                        <span class="text-muted small"><i class="bi bi-telephone-fill text-success me-2"></i>Số điện thoại</span>
                        <span class="fw-semibold <?= !empty($profile['phone']) ? 'text-dark font-monospace' : 'text-muted fst-italic' ?>">
                            <?= !empty($profile['phone']) ? htmlspecialchars($profile['phone']) : 'Chưa cập nhật' ?>
                        </span>
                    </div>
                    <div class="d-flex align-items-center justify-content-between py-2 border-bottom">
                        <span class="text-muted small"><i class="bi bi-envelope-fill text-primary me-2"></i>Email liên hệ</span>
                        <span class="small text-dark font-monospace"><?= htmlspecialchars($profile['email'] ?? 'Chưa cập nhật') ?></span>
                    </div>
                    <div class="d-flex align-items-center justify-content-between py-2 border-bottom">
                        <span class="text-muted small"><i class="bi bi-qr-code text-secondary me-2"></i>Mã tài khoản</span>
                        <span class="fw-bold text-dark font-monospace"><?= htmlspecialchars($profile['MaNguoiDung']) ?></span>
                    </div>
                    <div class="d-flex align-items-center justify-content-between py-2 border-bottom">
                        <span class="text-muted small"><i class="bi bi-person-badge text-secondary me-2"></i>Tên đăng nhập</span>
                        <code class="px-2 py-0.5 bg-light border rounded text-primary fw-bold small"><?= htmlspecialchars($profile['username']) ?></code>
                    </div>
                    <div class="d-flex align-items-center justify-content-between py-2">
                        <span class="text-muted small"><i class="bi bi-calendar-check text-secondary me-2"></i>Ngày khởi tạo</span>
                        <span class="small text-secondary"><?= !empty($profile['created_at']) ? date('d/m/Y', strtotime($profile['created_at'])) : date('d/m/Y') ?></span>
                    </div>
                </div>

                <!-- Edit Trigger Button -->
                <button type="button" class="btn btn-outline-primary w-100 d-inline-flex align-items-center justify-content-center gap-2 py-2.5 rounded-3 shadow-xs" id="btnOpenEditProfile">
                    <i class="bi bi-pencil-square fs-6"></i>
                    <span class="fw-semibold">Chỉnh sửa thông tin cá nhân</span>
                </button>
            </div>

            <!-- ── 1.B EDIT MODE (Toggled) ─────────────────────────────────── -->
            <div id="personalInfoEditMode" class="mode-container <?= $activeEdit === 'profile' ? '' : 'd-none' ?>">
                <form method="post" enctype="multipart/form-data" id="profileForm">
                    <input type="hidden" name="action" value="update_profile">
                    
                    <!-- Avatar Preview with Edit Badge -->
                    <div class="text-center mb-3">
                        <div class="profile-avatar-wrapper">
                            <?php if (!empty($profile['avatar_path']) && file_exists(__DIR__ . '/../' . $profile['avatar_path'])): ?>
                                <img src="<?= base_url(htmlspecialchars($profile['avatar_path'])) ?>" alt="<?= htmlspecialchars($profile['full_name']) ?>" class="profile-avatar-img" id="avatarPreviewImg">
                            <?php else: ?>
                                <div class="profile-avatar-placeholder <?= $isAdmin ? 'avatar-bg-admin' : 'avatar-bg-user' ?>" id="avatarPlaceholder">
                                    <?= user_initials($profile['full_name']) ?>
                                </div>
                                <img src="" alt="" class="profile-avatar-img d-none" id="avatarPreviewImg">
                            <?php endif; ?>
                            
                            <label for="avatarFileInput" class="avatar-upload-badge" title="Bấm để chọn ảnh mới từ thiết bị">
                                <i class="bi bi-camera-fill"></i>
                            </label>
                            <input type="file" name="avatar_file" id="avatarFileInput" class="d-none" accept=".jpg,.jpeg,.png,.webp,.gif">
                        </div>

                        <div class="small text-muted mb-2">Bấm vào biểu tượng camera để đổi ảnh đại diện</div>

                        <!-- Selected File Info Banner (Hidden by default until file picked) -->
                        <div id="fileSelectionAlert" class="alert alert-info py-2 px-3 small rounded-3 mb-3 d-none text-start">
                            <div class="d-flex align-items-center justify-content-between">
                                <span class="text-truncate me-2" id="selectedFileName"><i class="bi bi-image me-1"></i>Ảnh đã chọn</span>
                                <button type="button" class="btn-close btn-sm p-0" id="btnCancelAvatarSelection" aria-label="Hủy"></button>
                            </div>
                        </div>
                    </div>

                    <!-- Editable Personal Info Fields -->
                    <div class="border-top pt-3 mb-4">
                        <div class="form-section-title mb-3 d-flex align-items-center gap-2">
                            <i class="bi bi-pencil-square text-primary"></i> Chỉnh sửa thông tin
                        </div>

                        <!-- Họ và tên -->
                        <div class="mb-3 profile-input-group">
                            <label class="profile-field-label">
                                <i class="bi bi-person-fill text-primary"></i> Họ và tên <span class="text-danger">*</span>
                            </label>
                            <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($profile['full_name']) ?>" placeholder="Nhập họ và tên" required>
                        </div>

                        <!-- Số điện thoại (User Requested) -->
                        <div class="mb-3 profile-input-group">
                            <label class="profile-field-label">
                                <i class="bi bi-telephone-fill text-success"></i> Số điện thoại
                            </label>
                            <input type="tel" name="phone" class="form-control font-monospace" value="<?= htmlspecialchars($profile['phone'] ?? '') ?>" placeholder="Ví dụ: 0912345678">
                        </div>

                        <!-- Email liên hệ (Cố định theo tài khoản) -->
                        <div class="mb-3 profile-input-group">
                            <div class="d-flex align-items-center justify-content-between mb-1">
                                <label class="profile-field-label mb-0">
                                    <i class="bi bi-envelope-fill text-primary"></i> Email liên hệ
                                </label>
                                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle font-monospace py-0.5 px-2" style="font-size: 0.72rem;">
                                    <i class="bi bi-lock-fill me-1"></i>Cố định
                                </span>
                            </div>
                            <input type="email" class="form-control font-monospace bg-light text-muted" value="<?= htmlspecialchars($profile['email'] ?? '') ?>" readonly disabled style="cursor: not-allowed;">
                            <div class="form-text text-muted" style="font-size: 0.75rem;">
                                <i class="bi bi-info-circle me-1"></i>Email gắn liền với định danh tài khoản, không thể tự chỉnh sửa.
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons for Profile Updates -->
                    <div class="d-flex flex-column gap-2">
                        <button class="btn btn-primary w-100 d-inline-flex align-items-center justify-content-center gap-2 py-2.5 shadow-sm" type="submit" id="btnSaveProfile">
                            <i class="bi bi-floppy2-fill fs-6"></i>
                            <span class="fw-semibold">Lưu thông tin cá nhân</span>
                        </button>
                        <button type="button" class="btn btn-light border w-100 d-inline-flex align-items-center justify-content-center gap-2 py-2 text-secondary" id="btnCancelEditProfile">
                            <i class="bi bi-x-circle"></i>
                            <span>Hủy bỏ</span>
                        </button>
                    </div>
                </form>
            </div>

        </div>
    </div>

    <!-- ─── 2. RIGHT COLUMN: SYSTEM/TRAINING INFO (VIEW / EDIT) & PASSWORD ───── -->
    <div class="col-12 col-lg-7 col-xl-8">
        <div class="d-flex flex-column gap-4">

            <!-- ── Card 1: Thông tin hệ thống & Đào tạo ───────────────────────── -->
            <div class="profile-card profile-anim-card delay-card-2">

                <!-- ── 1.A SYSTEM INFO VIEW MODE (Default) ───────────────────── -->
                <div id="systemInfoViewMode" class="mode-container <?= $activeEdit === 'system' ? 'd-none' : '' ?>">
                    <div class="d-flex align-items-center justify-content-between pb-3 border-bottom mb-3">
                        <div>
                            <h2 class="h5 fw-bold mb-1 text-dark d-flex align-items-center gap-2">
                                <i class="bi bi-buildings-fill text-primary"></i> Thông tin hệ thống &amp; Đào tạo
                            </h2>
                            <p class="text-secondary small mb-0">Cấu hình chương trình đào tạo và thông tin đơn vị quản lý.</p>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3 py-1.5 d-inline-flex align-items-center gap-1.5 fw-semibold shadow-xs" id="btnOpenEditSystem">
                            <i class="bi bi-pencil-square"></i>
                            <span>Chỉnh sửa</span>
                        </button>
                    </div>

                    <div class="row g-3">
                        <!-- Tile 1: Trường -->
                        <div class="col-12 col-md-6">
                            <div class="info-tile">
                                <div class="info-tile-icon icon-blue">
                                    <i class="bi bi-mortarboard-fill"></i>
                                </div>
                                <div class="min-width-0">
                                    <div class="text-muted small fw-medium">Đơn vị chủ quản</div>
                                    <div class="fw-bold text-dark text-truncate" title="<?= htmlspecialchars($trainingProgram['school']) ?>">
                                        <?= htmlspecialchars($trainingProgram['school']) ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Tile 2: Khoa / Viện -->
                        <div class="col-12 col-md-6">
                            <div class="info-tile">
                                <div class="info-tile-icon icon-purple">
                                    <i class="bi bi-laptop-fill"></i>
                                </div>
                                <div class="min-width-0">
                                    <div class="text-muted small fw-medium">Khoa chuyên môn</div>
                                    <div class="fw-bold text-dark"><?= htmlspecialchars($trainingProgram['faculty'] ?? 'Khoa Công nghệ thông tin') ?></div>
                                </div>
                            </div>
                        </div>

                        <!-- Tile 3: CTĐT & Mã ngành -->
                        <div class="col-12 col-md-6">
                            <div class="info-tile">
                                <div class="info-tile-icon icon-emerald">
                                    <i class="bi bi-book-half"></i>
                                </div>
                                <div class="min-width-0">
                                    <div class="text-muted small fw-medium">Chương trình đào tạo</div>
                                    <div class="fw-bold text-dark">
                                        <?= htmlspecialchars($trainingProgram['name']) ?> 
                                        <span class="badge bg-light text-primary border font-monospace ms-1"><?= htmlspecialchars($trainingProgram['code']) ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Tile 4: Bậc đào tạo -->
                        <div class="col-12 col-md-6">
                            <div class="info-tile">
                                <div class="info-tile-icon icon-cyan">
                                    <i class="bi bi-award-fill"></i>
                                </div>
                                <div class="min-width-0">
                                    <div class="text-muted small fw-medium">Trình độ / Bậc đào tạo</div>
                                    <div class="fw-bold text-dark"><?= htmlspecialchars($trainingProgram['degree'] ?? 'Cử nhân') ?></div>
                                </div>
                            </div>
                        </div>

                        <!-- Tile 5: Chu kỳ kiểm định -->
                        <div class="col-12 col-md-6">
                            <div class="info-tile">
                                <div class="info-tile-icon icon-amber">
                                    <i class="bi bi-clock-history"></i>
                                </div>
                                <div class="min-width-0">
                                    <div class="text-muted small fw-medium">Chu kỳ kiểm định</div>
                                    <div class="fw-bold text-dark font-monospace"><?= htmlspecialchars($trainingProgram['cycle'] ?? '2026-2031') ?></div>
                                </div>
                            </div>
                        </div>

                        <!-- Tile 6: Phân hệ hệ thống -->
                        <div class="col-12 col-md-6">
                            <div class="info-tile">
                                <div class="info-tile-icon icon-blue">
                                    <i class="bi bi-shield-check"></i>
                                </div>
                                <div class="min-width-0">
                                    <div class="text-muted small fw-medium">Tiêu chuẩn áp dụng</div>
                                    <div class="fw-bold text-dark">Bộ tiêu chuẩn MOET &amp; AUN-QA</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ── 1.B SYSTEM INFO EDIT MODE (Toggled) ──────────────────── -->
                <div id="systemInfoEditMode" class="mode-container <?= $activeEdit === 'system' ? '' : 'd-none' ?>">
                    <div class="d-flex align-items-center justify-content-between pb-3 border-bottom mb-3">
                        <div>
                            <h2 class="h5 fw-bold mb-1 text-dark d-flex align-items-center gap-2">
                                <i class="bi bi-pencil-square text-primary"></i> Chỉnh sửa thông tin hệ thống &amp; Đào tạo
                            </h2>
                            <p class="text-secondary small mb-0">Cập nhật thông tin đơn vị chủ quản, khoa chuyên môn và chương trình đào tạo.</p>
                        </div>
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-1.5 rounded-pill small fw-semibold">
                            <i class="bi bi-gear-fill me-1"></i>Chế độ chỉnh sửa
                        </span>
                    </div>

                    <form method="post" id="systemInfoForm">
                        <input type="hidden" name="action" value="update_system_info">

                        <div class="row g-3 mb-4">
                            <!-- Trường / Đơn vị chủ quản -->
                            <div class="col-12 col-md-6 profile-input-group">
                                <label class="profile-field-label">
                                    <i class="bi bi-mortarboard-fill text-primary"></i> Đơn vị chủ quản <span class="text-danger">*</span>
                                </label>
                                <input type="text" name="school" class="form-control" value="<?= htmlspecialchars($trainingProgram['school'] ?? 'Trường Đại học Tài chính - Ngân hàng Hà Nội') ?>" placeholder="Nhập tên trường / cơ sở đào tạo" required>
                            </div>

                            <!-- Khoa / Viện chuyên môn -->
                            <div class="col-12 col-md-6 profile-input-group">
                                <label class="profile-field-label">
                                    <i class="bi bi-laptop-fill text-purple"></i> Khoa chuyên môn <span class="text-danger">*</span>
                                </label>
                                <input type="text" name="faculty" class="form-control" value="<?= htmlspecialchars($trainingProgram['faculty'] ?? 'Khoa Công nghệ thông tin') ?>" placeholder="Nhập tên khoa phụ trách" required>
                            </div>

                            <!-- Chương trình đào tạo -->
                            <div class="col-12 col-md-6 profile-input-group">
                                <label class="profile-field-label">
                                    <i class="bi bi-book-half text-emerald"></i> Tên chương trình đào tạo <span class="text-danger">*</span>
                                </label>
                                <input type="text" name="program_name" class="form-control" value="<?= htmlspecialchars($trainingProgram['name'] ?? 'Công nghệ thông tin') ?>" placeholder="Nhập tên ngành / CTĐT" required>
                            </div>

                            <!-- Mã ngành đào tạo -->
                            <div class="col-12 col-md-6 profile-input-group">
                                <label class="profile-field-label">
                                    <i class="bi bi-upc-scan text-primary"></i> Mã ngành đào tạo
                                </label>
                                <input type="text" name="program_code" class="form-control font-monospace" value="<?= htmlspecialchars($trainingProgram['code'] ?? '7480201') ?>" placeholder="Ví dụ: 7480201">
                            </div>

                            <!-- Bậc / Trình độ đào tạo -->
                            <div class="col-12 col-md-6 profile-input-group">
                                <label class="profile-field-label">
                                    <i class="bi bi-award-fill text-info"></i> Bậc đào tạo
                                </label>
                                <input type="text" name="degree" class="form-control" value="<?= htmlspecialchars($trainingProgram['degree'] ?? 'Cử nhân') ?>" placeholder="Ví dụ: Cử nhân, Kỹ sư, Thạc sĩ">
                            </div>

                            <!-- Chu kỳ kiểm định -->
                            <div class="col-12 col-md-6 profile-input-group">
                                <label class="profile-field-label">
                                    <i class="bi bi-clock-history text-amber"></i> Chu kỳ kiểm định
                                </label>
                                <input type="text" name="cycle" class="form-control font-monospace" value="<?= htmlspecialchars($trainingProgram['cycle'] ?? '2026-2031') ?>" placeholder="Ví dụ: 2026-2031">
                            </div>
                        </div>

                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 pt-3 border-top">
                            <button type="button" class="btn btn-light border d-inline-flex align-items-center gap-2 px-3 py-2 text-secondary" id="btnCancelEditSystem">
                                <i class="bi bi-x-circle"></i>
                                <span>Hủy bỏ</span>
                            </button>
                            <button class="btn btn-brand d-inline-flex align-items-center gap-2 px-4 py-2.5 shadow-sm" type="submit" id="btnSaveSystemInfo">
                                <i class="bi bi-cloud-check-fill fs-6"></i>
                                <span class="fw-semibold">Lưu thông tin hệ thống &amp; Đào tạo</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- ── Card 2: Bảo mật & Đổi mật khẩu ─────────────────────────────── -->
            <div class="profile-card profile-anim-card delay-card-3">
                <div class="d-flex align-items-center justify-content-between pb-3 border-bottom mb-3">
                    <div>
                        <h2 class="h5 fw-bold mb-1 text-dark d-flex align-items-center gap-2">
                            <i class="bi bi-shield-lock-fill text-danger"></i> Bảo mật tài khoản &amp; Đổi mật khẩu
                        </h2>
                        <p class="text-secondary small mb-0">Cập nhật mật khẩu mới định kỳ để bảo vệ tài khoản cá nhân.</p>
                    </div>
                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-3 py-1.5 rounded-pill small">Mật khẩu</span>
                </div>

                <form method="post" id="changePasswordForm">
                    <input type="hidden" name="action" value="change_password">
                    
                    <div class="row g-3 mb-4">
                        <!-- Mật khẩu mới -->
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold small text-dark">Mật khẩu mới <span class="text-danger">*</span></label>
                            <div class="input-group profile-input-group">
                                <input class="form-control" type="password" name="new_password" id="inputNewPassword" placeholder="Nhập ít nhất 6 ký tự" required autocomplete="new-password">
                                <button class="btn btn-outline-secondary border-start-0" type="button" id="toggleNewPwdBtn" title="Hiện / Ẩn mật khẩu">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Nhập lại mật khẩu mới -->
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold small text-dark">Xác nhận mật khẩu mới <span class="text-danger">*</span></label>
                            <div class="input-group profile-input-group">
                                <input class="form-control" type="password" name="confirm_password" id="inputConfirmPassword" placeholder="Nhập lại mật khẩu" required autocomplete="new-password">
                                <button class="btn btn-outline-secondary border-start-0" type="button" id="toggleConfirmPwdBtn" title="Hiện / Ẩn mật khẩu">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Password match feedback -->
                    <div id="pwdMatchFeedback" class="small mb-3 d-none"></div>

                    <div class="d-flex justify-content-end">
                        <button class="btn btn-danger d-inline-flex align-items-center gap-2 px-4 py-2 shadow-sm" type="submit" id="btnSubmitPassword">
                            <i class="bi bi-key-fill fs-6"></i>
                            <span>Cập nhật mật khẩu</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // 1. Toggle Edit Mode for Personal Info (Left Column)
    const viewProfile = document.getElementById('personalInfoViewMode');
    const editProfile = document.getElementById('personalInfoEditMode');
    const btnOpenEditProfile = document.getElementById('btnOpenEditProfile');
    const btnCancelEditProfile = document.getElementById('btnCancelEditProfile');

    if (btnOpenEditProfile && viewProfile && editProfile) {
        btnOpenEditProfile.addEventListener('click', function () {
            viewProfile.classList.add('d-none');
            editProfile.classList.remove('d-none');
        });
    }

    if (btnCancelEditProfile && viewProfile && editProfile) {
        btnCancelEditProfile.addEventListener('click', function () {
            editProfile.classList.add('d-none');
            viewProfile.classList.remove('d-none');
        });
    }

    // 2. Toggle Edit Mode for System & Training Info (Right Column)
    const viewSystem = document.getElementById('systemInfoViewMode');
    const editSystem = document.getElementById('systemInfoEditMode');
    const btnOpenEditSystem = document.getElementById('btnOpenEditSystem');
    const btnCancelEditSystem = document.getElementById('btnCancelEditSystem');

    if (btnOpenEditSystem && viewSystem && editSystem) {
        btnOpenEditSystem.addEventListener('click', function () {
            viewSystem.classList.add('d-none');
            editSystem.classList.remove('d-none');
        });
    }

    if (btnCancelEditSystem && viewSystem && editSystem) {
        btnCancelEditSystem.addEventListener('click', function () {
            editSystem.classList.add('d-none');
            viewSystem.classList.remove('d-none');
        });
    }

    // 3. Live Avatar File Selection & Preview
    const avatarInput = document.getElementById('avatarFileInput');
    const avatarPreviewImg = document.getElementById('avatarPreviewImg');
    const avatarPlaceholder = document.getElementById('avatarPlaceholder');
    const fileSelectionAlert = document.getElementById('fileSelectionAlert');
    const selectedFileName = document.getElementById('selectedFileName');
    const btnCancelAvatar = document.getElementById('btnCancelAvatarSelection');

    if (avatarInput) {
        avatarInput.addEventListener('change', function (e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function (event) {
                    if (avatarPreviewImg) {
                        avatarPreviewImg.src = event.target.result;
                        avatarPreviewImg.classList.remove('d-none');
                    }
                    if (avatarPlaceholder) {
                        avatarPlaceholder.classList.add('d-none');
                    }
                };
                reader.readAsDataURL(file);

                if (selectedFileName) {
                    selectedFileName.innerHTML = `<i class="bi bi-image-fill text-primary me-1"></i><strong>${file.name}</strong> (${(file.size / 1024).toFixed(0)} KB)`;
                }
                if (fileSelectionAlert) {
                    fileSelectionAlert.classList.remove('d-none');
                }
            }
        });
    }

    if (btnCancelAvatar && avatarInput) {
        btnCancelAvatar.addEventListener('click', function () {
            avatarInput.value = '';
            if (fileSelectionAlert) fileSelectionAlert.classList.add('d-none');
            window.location.reload();
        });
    }

    // 4. Toggle Show/Hide Password
    function setupPwdToggle(inputId, btnId) {
        const input = document.getElementById(inputId);
        const btn = document.getElementById(btnId);
        if (input && btn) {
            btn.addEventListener('click', function () {
                const isPwd = input.type === 'password';
                input.type = isPwd ? 'text' : 'password';
                const icon = btn.querySelector('i');
                if (icon) {
                    icon.className = isPwd ? 'bi bi-eye-slash' : 'bi bi-eye';
                }
            });
        }
    }
    setupPwdToggle('inputNewPassword', 'toggleNewPwdBtn');
    setupPwdToggle('inputConfirmPassword', 'toggleConfirmPwdBtn');

    // 5. Real-time Password Confirmation Check
    const newPwd = document.getElementById('inputNewPassword');
    const confirmPwd = document.getElementById('inputConfirmPassword');
    const feedback = document.getElementById('pwdMatchFeedback');

    function checkPasswordMatch() {
        if (!newPwd || !confirmPwd || !feedback) return;
        const val1 = newPwd.value;
        const val2 = confirmPwd.value;

        if (val2 === '') {
            feedback.classList.add('d-none');
            return;
        }

        feedback.classList.remove('d-none');
        if (val1 === val2) {
            feedback.className = 'small mb-3 text-success fw-semibold';
            feedback.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i>Mật khẩu xác nhận trùng khớp.';
        } else {
            feedback.className = 'small mb-3 text-danger fw-semibold';
            feedback.innerHTML = '<i class="bi bi-exclamation-circle-fill me-1"></i>Mật khẩu xác nhận chưa trùng khớp.';
        }
    }

    if (newPwd && confirmPwd) {
        newPwd.addEventListener('input', checkPasswordMatch);
        confirmPwd.addEventListener('input', checkPasswordMatch);
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
