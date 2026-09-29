<?php
require_once __DIR__ . '/../includes/helpers.php';
require_roles(['admin']);
require_once __DIR__ . '/../config/database.php';

if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    require_once __DIR__ . '/../includes/data.php';

    $selectedRole     = trim($_GET['role'] ?? '');
    $searchKeyword    = trim($_GET['search'] ?? $_GET['q'] ?? '');

    if ($selectedRole !== '') {
        $users = array_filter($users, fn($u) => $u['role_raw'] === $selectedRole);
    }
    if ($searchKeyword !== '') {
        $users = array_filter($users, fn($u) => 
            search_contains($u['code'], $searchKeyword) ||
            search_contains($u['name'], $searchKeyword) ||
            search_contains($u['email'], $searchKeyword) ||
            search_contains($u['username'], $searchKeyword)
        );
    }

    $columns = [
        ['key' => 'code', 'label' => 'Mã người dùng', 'align' => 'center', 'width' => '130px'],
        ['key' => 'name', 'label' => 'Họ và tên', 'align' => 'left', 'width' => '220px'],
        ['key' => 'email', 'label' => 'Email', 'align' => 'left', 'width' => '220px'],
        ['key' => 'phone', 'label' => 'Số điện thoại', 'align' => 'center', 'width' => '120px'],
        ['key' => 'username', 'label' => 'Tên đăng nhập', 'align' => 'center', 'width' => '130px'],
        ['key' => 'role_name', 'label' => 'Vai trò', 'align' => 'center', 'width' => '130px'],
        ['key' => 'status', 'label' => 'Trạng thái', 'align' => 'center', 'width' => '130px'],
    ];

    export_to_excel('danh_sach_nguoi_dung_' . date('Ymd_His') . '.xls', 'DANH SÁCH NGƯỜI DÙNG HỆ THỐNG', $columns, array_values($users));
}

$pdo = db();
$success = '';
$error = '';
$currentUserId = $_SESSION['user_id'] ?? 'ND001';
$failedFormData = null;

// AJAX Endpoint for real-time validation of MaNguoiDung, Email & Username uniqueness
if (isset($_GET['ajax']) && $_GET['ajax'] === 'check_duplicate') {
    header('Content-Type: application/json; charset=utf-8');
    $field = trim($_GET['field'] ?? '');
    $val = trim($_GET['value'] ?? '');
    $excludeId = trim($_GET['id'] ?? '');

    $res = ['exists' => false, 'message' => ''];

    if ($field === 'code') {
        if ($val === '') {
            $res = ['exists' => false, 'message' => ''];
        } else {
            $stmt = $pdo->prepare('SELECT MaNguoiDung, HoTen FROM NguoiDung WHERE LOWER(MaNguoiDung) = LOWER(:val) AND MaNguoiDung <> :id LIMIT 1');
            $stmt->execute(['val' => $val, 'id' => $excludeId]);
            $found = $stmt->fetch();
            if ($found) {
                $res = [
                    'exists' => true,
                    'message' => 'Mã tài khoản này đã tồn tại trong hệ thống (thuộc về: ' . htmlspecialchars($found['HoTen']) . '). Vui lòng nhập mã khác.'
                ];
            } else {
                $res = ['exists' => false, 'message' => 'Mã tài khoản hợp lệ.'];
            }
        }
    } elseif ($field === 'email') {
        if ($val === '') {
            $res = ['exists' => false, 'message' => ''];
        } elseif (!filter_var($val, FILTER_VALIDATE_EMAIL)) {
            $res = ['exists' => true, 'invalid_format' => true, 'message' => 'Định dạng email không hợp lệ (VD: user@fbu.edu.vn).'];
        } else {
            $stmt = $pdo->prepare('SELECT MaNguoiDung, HoTen FROM NguoiDung WHERE LOWER(Email) = LOWER(:val) AND MaNguoiDung <> :id LIMIT 1');
            $stmt->execute(['val' => $val, 'id' => $excludeId]);
            $found = $stmt->fetch();
            if ($found) {
                $res = [
                    'exists' => true,
                    'message' => 'Email này đã tồn tại trong hệ thống (thuộc về: ' . htmlspecialchars($found['HoTen']) . '). Vui lòng nhập email khác.'
                ];
            } else {
                $res = ['exists' => false, 'message' => 'Email hợp lệ và có thể sử dụng.'];
            }
        }
    } elseif ($field === 'username') {
        if ($val === '') {
            $res = ['exists' => false, 'message' => ''];
        } else {
            $stmt = $pdo->prepare('SELECT MaNguoiDung, HoTen FROM NguoiDung WHERE LOWER(TenDangNhap) = LOWER(:val) AND MaNguoiDung <> :id LIMIT 1');
            $stmt->execute(['val' => $val, 'id' => $excludeId]);
            $found = $stmt->fetch();
            if ($found) {
                $res = [
                    'exists' => true,
                    'message' => 'Tên đăng nhập này đã tồn tại trong hệ thống (thuộc về: ' . htmlspecialchars($found['HoTen']) . '). Vui lòng chọn tên khác.'
                ];
            } else {
                $res = ['exists' => false, 'message' => 'Tên đăng nhập hợp lệ.'];
            }
        }
    }
    echo json_encode($res, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'save_user') {
            $rawId        = trim($_POST['id'] ?? '');
            $maNguoiDung  = trim($_POST['ma_nguoi_dung'] ?? '');
            $fullName     = trim($_POST['ho_ten'] ?? '');
            $email        = trim($_POST['email'] ?? '');
            $phone        = trim($_POST['sdt'] ?? '');
            $username     = trim($_POST['ten_dang_nhap'] ?? '');
            $role         = trim($_POST['vai_tro'] ?? 'user');
            $status       = (int) ($_POST['trang_thai'] ?? 1);
            $password     = $_POST['password'] ?? '';

            if ($fullName === '' || $username === '' || $email === '') {
                throw new RuntimeException('Vui lòng nhập đầy đủ họ tên, tên đăng nhập và email.');
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Địa chỉ email "' . htmlspecialchars($email) . '" không đúng định dạng. Vui lòng kiểm tra lại (VD: example@fbu.edu.vn).');
            }

            if (!in_array($role, ['admin', 'user'], true)) {
                throw new RuntimeException('Vai trò không hợp lệ. Chỉ chấp nhận Quản trị viên hoặc Người dùng.');
            }

            // Kiểm tra tính duy nhất của Email
            $checkEmail = $pdo->prepare('SELECT MaNguoiDung, HoTen FROM NguoiDung WHERE LOWER(Email) = LOWER(:email) AND MaNguoiDung <> :id LIMIT 1');
            $checkEmail->execute([
                'email' => $email,
                'id'    => $rawId,
            ]);
            $foundEmailUser = $checkEmail->fetch();
            if ($foundEmailUser) {
                throw new RuntimeException('Email "' . htmlspecialchars($email) . '" đã tồn tại trong hệ thống (được sử dụng bởi: ' . htmlspecialchars($foundEmailUser['HoTen']) . '). Mỗi người dùng phải có một email duy nhất, không được trùng lặp.');
            }

            // Kiểm tra tính duy nhất của Tên đăng nhập
            $checkUsername = $pdo->prepare('SELECT MaNguoiDung FROM NguoiDung WHERE LOWER(TenDangNhap) = LOWER(:username) AND MaNguoiDung <> :id LIMIT 1');
            $checkUsername->execute([
                'username' => $username,
                'id'       => $rawId,
            ]);
            if ($checkUsername->fetch()) {
                throw new RuntimeException('Tên đăng nhập "' . htmlspecialchars($username) . '" đã tồn tại trong hệ thống. Vui lòng chọn tên đăng nhập khác.');
            }

            $phoneCol = 'SoDienThoai';
            try {
                $pdo->query("SELECT SoDienThoai FROM NguoiDung LIMIT 1");
            } catch (Throwable $t) {
                $phoneCol = 'SDT';
            }

            if ($rawId !== '') {
                if ($maNguoiDung === '') {
                    throw new RuntimeException('Vui lòng nhập Mã người dùng.');
                }
                if ($maNguoiDung !== $rawId) {
                    $chk = $pdo->prepare('SELECT MaNguoiDung, HoTen FROM NguoiDung WHERE LOWER(MaNguoiDung) = LOWER(:code) AND MaNguoiDung <> :old_id LIMIT 1');
                    $chk->execute(['code' => $maNguoiDung, 'old_id' => $rawId]);
                    $foundCodeUser = $chk->fetch();
                    if ($foundCodeUser) {
                        throw new RuntimeException('Mã người dùng "' . htmlspecialchars($maNguoiDung) . '" đã tồn tại trong hệ thống (thuộc về: ' . htmlspecialchars($foundCodeUser['HoTen']) . '). Vui lòng nhập mã khác.');
                    }
                    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
                    $upMc = $pdo->prepare('UPDATE MinhChung SET MaNguoiDung = :new_code WHERE MaNguoiDung = :old_code');
                    $upMc->execute(['new_code' => $maNguoiDung, 'old_code' => $rawId]);

                    $upTok = $pdo->prepare('UPDATE remember_tokens SET MaNguoiDung = :new_code WHERE MaNguoiDung = :old_code');
                    $upTok->execute(['new_code' => $maNguoiDung, 'old_code' => $rawId]);

                    $upLog = $pdo->prepare('UPDATE audit_logs SET MaNguoiDung = :new_code WHERE MaNguoiDung = :old_code');
                    $upLog->execute(['new_code' => $maNguoiDung, 'old_code' => $rawId]);

                    $upDl = $pdo->prepare('UPDATE download_logs SET MaNguoiDung = :new_code WHERE MaNguoiDung = :old_code');
                    $upDl->execute(['new_code' => $maNguoiDung, 'old_code' => $rawId]);

                    if (($_SESSION['user_id'] ?? '') === $rawId) {
                        $_SESSION['user_id'] = $maNguoiDung;
                    }
                }

                if ($password !== '') {
                    $stmt = $pdo->prepare("
                        UPDATE NguoiDung
                        SET MaNguoiDung = :new_code,
                            HoTen = :full_name,
                            Email = :email,
                            {$phoneCol} = :phone,
                            TenDangNhap = :username,
                            MatKhau = :password_hash,
                            VaiTro = :role,
                            TrangThai = :status
                        WHERE MaNguoiDung = :old_code
                    ");
                    $stmt->execute([
                        'new_code'      => $maNguoiDung,
                        'full_name'     => $fullName,
                        'email'         => $email,
                        'phone'         => $phone,
                        'username'      => $username,
                        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                        'role'          => $role,
                        'status'        => $status,
                        'old_code'      => $rawId,
                    ]);
                    if ($maNguoiDung !== $rawId) {
                        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
                    }
                    log_activity('cap_nhat', 'nguoi_dung', 0, $maNguoiDung . ' - ' . $fullName);
                    $success = 'Cập nhật thông tin và mật khẩu tài khoản thành công.';
                } else {
                    $stmt = $pdo->prepare("
                        UPDATE NguoiDung
                        SET MaNguoiDung = :new_code,
                            HoTen = :full_name,
                            Email = :email,
                            {$phoneCol} = :phone,
                            TenDangNhap = :username,
                            VaiTro = :role,
                            TrangThai = :status
                        WHERE MaNguoiDung = :old_code
                    ");
                    $stmt->execute([
                        'new_code'      => $maNguoiDung,
                        'full_name'     => $fullName,
                        'email'         => $email,
                        'phone'         => $phone,
                        'username'      => $username,
                        'role'          => $role,
                        'status'        => $status,
                        'old_code'      => $rawId,
                    ]);
                    if ($maNguoiDung !== $rawId) {
                        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
                    }
                    log_activity('cap_nhat', 'nguoi_dung', 0, $maNguoiDung . ' - ' . $fullName);
                    $success = 'Cập nhật thông tin tài khoản thành công.';
                }
            } else {
                if ($maNguoiDung === '') {
                    $maxStmt = $pdo->query("SELECT MaNguoiDung FROM NguoiDung WHERE MaNguoiDung LIKE 'ND%' ORDER BY LENGTH(MaNguoiDung) DESC, MaNguoiDung DESC LIMIT 1");
                    $lastCode = $maxStmt->fetchColumn();
                    if ($lastCode && preg_match('/ND(\d+)/i', $lastCode, $m)) {
                        $nextNum = (int)$m[1] + 1;
                        $maNguoiDung = 'ND' . str_pad($nextNum, 3, '0', STR_PAD_LEFT);
                    } else {
                        $maNguoiDung = 'ND001';
                    }
                }
                $chk = $pdo->prepare('SELECT MaNguoiDung, HoTen FROM NguoiDung WHERE LOWER(MaNguoiDung) = LOWER(:code) LIMIT 1');
                $chk->execute(['code' => $maNguoiDung]);
                $foundCodeUser = $chk->fetch();
                if ($foundCodeUser) {
                    throw new RuntimeException('Mã người dùng "' . htmlspecialchars($maNguoiDung) . '" đã tồn tại trong hệ thống (thuộc về: ' . htmlspecialchars($foundCodeUser['HoTen']) . '). Vui lòng nhập mã khác.');
                }

                if ($password === '') {
                    throw new RuntimeException('Vui lòng nhập mật khẩu cho tài khoản mới.');
                }

                $stmt = $pdo->prepare("
                    INSERT INTO NguoiDung (MaNguoiDung, HoTen, Email, {$phoneCol}, TenDangNhap, MatKhau, VaiTro, TrangThai)
                    VALUES (:code, :full_name, :email, :phone, :username, :password_hash, :role, :status)
                ");
                $stmt->execute([
                    'code'          => $maNguoiDung,
                    'full_name'     => $fullName,
                    'email'         => $email,
                    'phone'         => $phone,
                    'username'      => $username,
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                    'role'          => $role,
                    'status'        => $status,
                ]);
                log_activity('them_moi', 'nguoi_dung', 0, $maNguoiDung . ' - ' . $fullName);
                $success = 'Tạo tài khoản mới thành công.';
            }
        }

        if ($action === 'delete_user') {
            $id = trim($_POST['id'] ?? '');
            if ($id === (string) $currentUserId) {
                throw new RuntimeException('Không thể xóa tài khoản đang đăng nhập.');
            }
            $stmtName = $pdo->prepare('SELECT HoTen, TenDangNhap FROM NguoiDung WHERE MaNguoiDung = :id');
            $stmtName->execute(['id' => $id]);
            $uRow = $stmtName->fetch();
            $uName = $uRow ? ($uRow['HoTen'] . ' (' . $uRow['TenDangNhap'] . ')') : ('#' . $id);

            $stmt = $pdo->prepare('DELETE FROM NguoiDung WHERE MaNguoiDung = :id');
            $stmt->execute(['id' => $id]);
            log_activity('xoa', 'nguoi_dung', 0, $uName);
            $success = 'Xóa tài khoản thành công.';
        }

        if ($action === 'toggle_user') {
            $id = trim($_POST['id'] ?? '');
            if ($id === (string) $currentUserId) {
                throw new RuntimeException('Không thể khóa tài khoản đang đăng nhập.');
            }
            $stmtName = $pdo->prepare('SELECT HoTen, TenDangNhap, TrangThai FROM NguoiDung WHERE MaNguoiDung = :id');
            $stmtName->execute(['id' => $id]);
            $uRow = $stmtName->fetch();
            if (!$uRow) {
                throw new RuntimeException('Tài khoản không tồn tại.');
            }
            $uName = $uRow['HoTen'] . ' (' . $uRow['TenDangNhap'] . ')';
            $newStatus = (int) $uRow['TrangThai'] === 1 ? 0 : 1;

            $stmt = $pdo->prepare("UPDATE NguoiDung SET TrangThai = :status WHERE MaNguoiDung = :id");
            $stmt->execute(['status' => $newStatus, 'id' => $id]);
            $statusText = $newStatus === 1 ? 'Đang hoạt động' : 'Ngưng áp dụng';
            log_activity('cap_nhat_trang_thai', 'nguoi_dung', 0, $uName . ' -> ' . $statusText);

            $successMsg = 'Đã chuyển trạng thái tài khoản "' . $uRow['TenDangNhap'] . '" sang: ' . $statusText . '.';

            if (isset($_POST['ajax']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')) {
                header('Content-Type: application/json; charset=utf-8');
                $activeCount = (int) $pdo->query("SELECT COUNT(*) FROM NguoiDung WHERE TrangThai = 1")->fetchColumn();
                echo json_encode([
                    'success' => true,
                    'new_status' => $newStatus,
                    'status_text' => $statusText,
                    'active_count' => $activeCount,
                    'message' => $successMsg
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $success = $successMsg;
        }
    } catch (PDOException $exception) {
        if (isset($_POST['ajax']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => $exception->getMessage()], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if ($exception->getCode() == 23000) {
            $msg = $exception->getMessage();
            if (stripos($msg, 'PRIMARY') !== false || stripos($msg, 'MaNguoiDung') !== false) {
                $error = 'Không thể lưu: Mã người dùng này đã tồn tại trong hệ thống. Vui lòng nhập mã khác.';
            } elseif (stripos($msg, 'Email') !== false || stripos($msg, 'key \'Email\'') !== false) {
                $error = 'Không thể lưu: Email này đã tồn tại trong hệ thống. Mỗi người dùng phải có một email duy nhất.';
            } elseif (stripos($msg, 'TenDangNhap') !== false) {
                $error = 'Không thể lưu: Tên đăng nhập này đã tồn tại trong hệ thống. Vui lòng chọn tên khác.';
            } else {
                $error = 'Không thể thực hiện thao tác: Dữ liệu (Mã tài khoản, Email hoặc Tên đăng nhập) đã bị trùng lặp trong hệ thống.';
            }
        } else {
            $error = 'Lỗi cơ sở dữ liệu: ' . $exception->getMessage();
        }
        if (($action ?? '') === 'save_user') {
            $failedFormData = $_POST;
        }
    } catch (Throwable $exception) {
        if (isset($_POST['ajax']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => $exception->getMessage()], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $error = 'Không thể thực hiện thao tác: ' . $exception->getMessage();
        if (($action ?? '') === 'save_user') {
            $failedFormData = $_POST;
        }
    }
    if ($success) {
        unset($_GET['create'], $_GET['edit']);
    }
}

require_once __DIR__ . '/../includes/data.php';

$searchKeyword = trim($_GET['search'] ?? $_GET['q'] ?? '');
if ($searchKeyword !== '') {
    $users = array_filter($users, function ($user) use ($searchKeyword) {
        $matchCode  = search_contains($user['code'], $searchKeyword);
        $matchName  = search_contains($user['name'], $searchKeyword);
        $matchRole  = search_contains($user['role'], $searchKeyword);
        $matchUser  = search_contains($user['username'], $searchKeyword);
        $matchEmail = search_contains($user['email'], $searchKeyword);
        $matchPhone = search_contains($user['phone'], $searchKeyword);
        return $matchCode || $matchName || $matchRole || $matchUser || $matchEmail || $matchPhone;
    });
}

$isCreatingUser = isset($_GET['create']) || ($error !== '' && !empty($failedFormData) && empty($failedFormData['id']));
$editId         = trim($_GET['edit'] ?? ($failedFormData['id'] ?? ''));
$editingUser    = null;
if ($editId !== '') {
    $stmt = $pdo->prepare('SELECT * FROM NguoiDung WHERE MaNguoiDung = :id LIMIT 1');
    $stmt->execute(['id' => $editId]);
    $editingUser = $stmt->fetch();
}

$maxStmt = $pdo->query("SELECT MaNguoiDung FROM NguoiDung WHERE MaNguoiDung LIKE 'ND%' ORDER BY LENGTH(MaNguoiDung) DESC, MaNguoiDung DESC LIMIT 1");
$lastCode = $maxStmt->fetchColumn();
if ($lastCode && preg_match('/ND(\d+)/i', $lastCode, $m)) {
    $nextNum = (int)$m[1] + 1;
    $suggestedUserCode = 'ND' . str_pad($nextNum, 3, '0', STR_PAD_LEFT);
} else {
    $suggestedUserCode = 'ND001';
}

$formValues = [
    'MaNguoiDung' => $failedFormData['ma_nguoi_dung'] ?? ($editingUser['MaNguoiDung'] ?? ($isCreatingUser ? $suggestedUserCode : '')),
    'HoTen'       => $failedFormData['ho_ten'] ?? ($editingUser['HoTen'] ?? ''),
    'Email'       => $failedFormData['email'] ?? ($editingUser['Email'] ?? ''),
    'SoDienThoai' => $failedFormData['sdt'] ?? ($editingUser['SoDienThoai'] ?? ($editingUser['SDT'] ?? '')),
    'TenDangNhap' => $failedFormData['ten_dang_nhap'] ?? ($editingUser['TenDangNhap'] ?? ''),
    'VaiTro'      => $failedFormData['vai_tro'] ?? ($editingUser['VaiTro'] ?? 'user'),
    'TrangThai'   => isset($failedFormData['trang_thai']) ? (int)$failedFormData['trang_thai'] : (int)($editingUser['TrangThai'] ?? 1),
    'id'          => $failedFormData['id'] ?? ($editingUser['MaNguoiDung'] ?? ''),
];
$shouldOpenModal = ($editingUser || $isCreatingUser || !empty($failedFormData));

$totalUsersCount   = count($users);
$adminUsersCount   = 0;
$regularUsersCount = 0;
$activeUsersCount  = 0;

foreach ($users as $u) {
    if (($u['role_code'] ?? '') === 'admin' || ($u['role'] ?? '') === 'admin') {
        $adminUsersCount++;
    } else {
        $regularUsersCount++;
    }
    if ((int) ($u['status_raw'] ?? 1) === 1) {
        $activeUsersCount++;
    }
}

$pageTitle = page_title('Quản lý người dùng');
$heading   = 'Quản lý người dùng';
include __DIR__ . '/../includes/header.php';
?>
<?php if ($shouldOpenModal && !$success): ?><script>document.body.dataset.autoOpenModal = 'accountFormModal';</script><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success alert-dismissible fade show" role="alert"><?= htmlspecialchars($success) ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Đóng"></button></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger alert-dismissible fade show" role="alert"><?= htmlspecialchars($error) ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Đóng"></button></div><?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="metric-card metric-blue">
            <i class="bi bi-people-fill"></i>
            <span>Tổng tài khoản</span>
            <strong class="count-up" data-count-to="<?= $totalUsersCount ?>"><?= $totalUsersCount ?></strong>
            <small class="text-secondary">Tài khoản trên hệ thống</small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="metric-card metric-red">
            <i class="bi bi-shield-lock-fill"></i>
            <span>Quản trị viên</span>
            <strong class="count-up" data-count-to="<?= $adminUsersCount ?>"><?= $adminUsersCount ?></strong>
            <small class="text-secondary">Quyền quản trị cao nhất</small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="metric-card metric-amber">
            <i class="bi bi-person-badge-fill"></i>
            <span>Người dùng</span>
            <strong class="count-up" data-count-to="<?= $regularUsersCount ?>"><?= $regularUsersCount ?></strong>
            <small class="text-secondary">Khai thác dữ liệu CSDL</small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="metric-card metric-green">
            <i class="bi bi-check-circle-fill"></i>
            <span>Đang hoạt động</span>
            <strong class="count-up" data-count-to="<?= $activeUsersCount ?>"><?= $activeUsersCount ?></strong>
            <small class="text-secondary">Trạng thái kích hoạt</small>
        </div>
    </div>
</div>

<div class="row g-4 accounts-layout">
    <div class="col-12">
        <div class="panel accounts-list-panel">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
                <div>
                    <h2 class="h5 mb-1">Danh sách người dùng</h2>
                    <p class="text-secondary small mb-0">Quản lý tài khoản, phân quyền và trạng thái người dùng trong hệ thống.</p>
                </div>
                <div class="d-flex gap-2">
                    <a class="btn btn-primary" href="<?= base_url('admin/users.php?create=1') ?>"><i class="bi bi-plus-circle me-1"></i> Thêm người dùng</a>
                    <a class="btn btn-outline-success" id="exportExcelBtn" href="<?= base_url('admin/users.php?export=excel' . ($searchKeyword !== '' ? '&search=' . urlencode($searchKeyword) : '')) ?>"><i class="bi bi-file-earmark-excel me-1"></i> Xuất Excel</a>
                </div>
            </div>
            <form method="get" action="" class="mb-3" id="userSearchForm">
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" id="userSearchInput" class="form-control" placeholder="Nhập mã, họ tên, email, sdt hoặc tên đăng nhập..." value="<?= htmlspecialchars($searchKeyword) ?>">
                    <?php if ($searchKeyword !== ''): ?>
                        <a href="<?= base_url('admin/users.php') ?>" class="btn btn-outline-secondary" title="Xóa tìm kiếm"><i class="bi bi-x-lg"></i></a>
                    <?php endif; ?>
                    <button class="btn btn-primary" type="submit">Tìm kiếm</button>
                </div>
            </form>
            <div class="table-responsive">
                <table class="table align-middle" data-page-size="10">
                    <thead>
                    <tr>
                        <th class="text-nowrap" style="width: 120px;">Mã tài khoản</th>
                        <th style="min-width: 200px;">Họ tên & Người dùng</th>
                        <th style="min-width: 200px;">Email liên hệ</th>
                        <th class="text-nowrap" style="width: 140px;">Số điện thoại</th>
                        <th class="text-nowrap" style="width: 140px;">Tên đăng nhập</th>
                        <th class="text-nowrap" style="width: 120px;">Mật khẩu</th>
                        <th class="text-nowrap" style="width: 130px;">Vai trò</th>
                        <th class="text-nowrap" style="width: 130px;">Trạng thái</th>
                        <th class="text-end text-nowrap action-cell" style="width: 100px;">Thao tác</th>
                    </tr>
                    </thead>
                    <tbody id="usersTableBody">
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td class="fw-bold text-nowrap text-primary"><?= htmlspecialchars($user['code']) ?></td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <?= avatar_html($user['avatar'] ?? null, $user['name'], 'avatar avatar-sm') ?>
                                    <span class="fw-semibold text-dark"><?= htmlspecialchars($user['name']) ?></span>
                                </div>
                            </td>
                            <td class="text-nowrap"><i class="bi bi-envelope text-muted me-1"></i><?= htmlspecialchars($user['email']) ?></td>
                            <td class="text-nowrap"><?php if ($user['phone']): ?><i class="bi bi-telephone text-muted me-1"></i><?= htmlspecialchars($user['phone']) ?><?php else: ?><span class="text-muted">-</span><?php endif; ?></td>
                            <td><code class="px-2 py-1 bg-light border rounded text-primary fw-bold"><?= htmlspecialchars($user['username']) ?></code></td>
                            <td class="text-nowrap">
                                <div class="d-inline-flex align-items-center gap-1">
                                    <span class="text-muted pwd-text" data-masked="true" data-plain="<?= htmlspecialchars($user['username'] === 'admin' ? 'admin123' : '123456') ?>">••••••••</span>
                                    <button type="button" class="btn btn-sm btn-light border-0 p-1 text-secondary toggle-pwd-btn" title="Ẩn/Hiện mật khẩu">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                            </td>
                            <td class="text-nowrap">
                                <?php if ($user['role_code'] === 'admin'): ?>
                                    <span class="badge text-bg-danger px-2.5 py-1.5"><i class="bi bi-shield-lock-fill me-1"></i>Quản trị viên</span>
                                <?php else: ?>
                                    <span class="badge text-bg-secondary px-2.5 py-1.5"><i class="bi bi-person-fill me-1"></i>Người dùng</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-nowrap status-cell">
                                <?php if ($user['id'] === (string) $currentUserId): ?>
                                    <span class="badge text-bg-success px-2.5 py-1.5" title="Tài khoản của bạn (Đang đăng nhập)">
                                        <i class="bi bi-check-circle-fill me-1"></i>Đang hoạt động
                                    </span>
                                <?php else: ?>
                                    <form method="post" class="d-inline toggle-status-form">
                                        <input type="hidden" name="action" value="toggle_user">
                                        <input type="hidden" name="id" value="<?= htmlspecialchars($user['id']) ?>">
                                        <button type="button" class="btn p-0 border-0 bg-transparent toggle-status-btn"
                                                data-user-id="<?= htmlspecialchars($user['id']) ?>"
                                                data-status="<?= (int) $user['status_raw'] ?>"
                                                title="Bấm để <?= (int) $user['status_raw'] === 1 ? 'ngưng áp dụng tài khoản' : 'kích hoạt lại tài khoản' ?>">
                                            <?php if ((int) $user['status_raw'] === 1): ?>
                                                <span class="badge text-bg-success px-2.5 py-1.5 status-badge" style="cursor: pointer; transition: all 0.2s ease;">
                                                    <i class="bi bi-check-circle-fill me-1"></i><span class="status-label">Đang hoạt động</span>
                                                    <i class="bi bi-arrow-repeat ms-1 opacity-75 small"></i>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge text-bg-warning text-dark px-2.5 py-1.5 status-badge" style="cursor: pointer; transition: all 0.2s ease;">
                                                    <i class="bi bi-dash-circle-fill me-1"></i><span class="status-label">Ngưng áp dụng</span>
                                                    <i class="bi bi-arrow-repeat ms-1 opacity-75 small"></i>
                                                </span>
                                            <?php endif; ?>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                            <td class="text-end action-cell">
                                <div class="action-buttons d-flex justify-content-end gap-1">
                                    <?php if ($user['id'] !== (string) $currentUserId): ?>
                                        <button class="btn btn-sm btn-outline-<?= (int) $user['status_raw'] === 1 ? 'warning' : 'success' ?> toggle-action-btn"
                                                type="button"
                                                data-user-id="<?= htmlspecialchars($user['id']) ?>"
                                                data-status="<?= (int) $user['status_raw'] ?>"
                                                title="<?= (int) $user['status_raw'] === 1 ? 'Khóa / Ngưng áp dụng' : 'Kích hoạt tài khoản' ?>">
                                            <i class="bi bi-<?= (int) $user['status_raw'] === 1 ? 'lock' : 'unlock' ?>"></i>
                                        </button>
                                    <?php endif; ?>
                                    <a class="btn btn-sm btn-outline-primary" href="?edit=<?= $user['id'] ?>" title="Sửa tài khoản"><i class="bi bi-pencil"></i></a>
                                    <form method="post" class="d-inline" data-confirm-form="Bạn chắc chắn muốn xóa người dùng này?">
                                        <input type="hidden" name="action" value="delete_user">
                                        <input type="hidden" name="id" value="<?= $user['id'] ?>">
                                        <button class="btn btn-sm btn-outline-danger" type="submit" title="Xóa tài khoản"><i class="bi bi-trash"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <tr id="noDataRow" class="<?= !empty($users) ? 'd-none' : '' ?>">
                        <td colspan="9" class="text-center text-secondary py-4">Không có dữ liệu được ghi</td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('userSearchInput');
    const tableBody = document.getElementById('usersTableBody');
    const noDataRow = document.getElementById('noDataRow');
    if (!searchInput || !tableBody) return;

    function normalizeText(str) {
        return (str || '')
            .toString()
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/đ/g, 'd')
            .replace(/\s+/g, ' ')
            .trim();
    }

    function filterTable() {
        const query = normalizeText(searchInput.value);
        const rows = tableBody.querySelectorAll('tr:not(#noDataRow)');
        let visibleCount = 0;

        rows.forEach(row => {
            const codeCell  = row.cells[0]?.textContent || '';
            const nameCell  = row.cells[1]?.textContent || '';
            const emailCell = row.cells[2]?.textContent || '';
            const phoneCell = row.cells[3]?.textContent || '';
            const userCell  = row.cells[4]?.textContent || '';
            const textToMatch = normalizeText(codeCell + ' ' + nameCell + ' ' + emailCell + ' ' + phoneCell + ' ' + userCell);

            if (query === '' || textToMatch.includes(query)) {
                row.dataset.filteredOut = 'false';
                visibleCount++;
            } else {
                row.dataset.filteredOut = 'true';
            }
        });

        if (noDataRow) {
            if (visibleCount === 0) {
                noDataRow.classList.remove('d-none');
            } else {
                noDataRow.classList.add('d-none');
            }
        }

        if (typeof refreshTablePaginations === 'function') {
            refreshTablePaginations();
        }
    }

    searchInput.addEventListener('input', filterTable);

    document.querySelectorAll('.toggle-pwd-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            const parent = this.closest('div');
            const textSpan = parent?.querySelector('.pwd-text');
            const icon = this.querySelector('i');
            if (!textSpan) return;
            const isMasked = textSpan.dataset.masked !== 'false';

            if (isMasked) {
                textSpan.dataset.masked = 'false';
                textSpan.textContent = textSpan.dataset.plain || '123456';
                textSpan.classList.remove('text-muted');
                textSpan.classList.add('fw-semibold', 'text-primary');
                if (icon) icon.className = 'bi bi-eye-slash';
            } else {
                textSpan.dataset.masked = 'true';
                textSpan.textContent = '••••••••';
                textSpan.classList.remove('fw-semibold', 'text-primary');
                textSpan.classList.add('text-muted');
                if (icon) icon.className = 'bi bi-eye';
            }
        });
    });

    const formPwdInput = document.getElementById('userFormPassword');
    const formPwdToggleBtn = document.getElementById('toggleUserFormPasswordBtn');
    if (formPwdInput && formPwdToggleBtn) {
        formPwdToggleBtn.addEventListener('click', function () {
            const isPassword = formPwdInput.type === 'password';
            formPwdInput.type = isPassword ? 'text' : 'password';
            const icon = formPwdToggleBtn.querySelector('i');
            if (icon) {
                icon.className = isPassword ? 'bi bi-eye-slash' : 'bi bi-eye';
            }
        });
    }

    // Live validation for Code, Email and Username uniqueness
    const codeInput = document.getElementById('userFormCode');
    const codeFeedback = document.getElementById('userCodeFeedback');
    const emailInput = document.getElementById('userFormEmail');
    const emailFeedback = document.getElementById('userEmailFeedback');
    const usernameInput = document.getElementById('userFormUsername');
    const usernameFeedback = document.getElementById('userUsernameFeedback');
    const userIdInput = document.getElementById('userFormId');
    const userForm = document.getElementById('userAccountForm');

    let codeTimer = null;
    let emailTimer = null;
    let usernameTimer = null;
    let isCodeDuplicate = false;
    let isEmailDuplicate = false;
    let isUsernameDuplicate = false;

    function checkCodeUnique() {
        if (!codeInput) return;
        const val = codeInput.value.trim();
        const currentId = userIdInput ? userIdInput.value.trim() : '';

        if (val === '') {
            codeInput.classList.remove('is-invalid', 'is-valid');
            isCodeDuplicate = false;
            return;
        }

        fetch(`<?= base_url('admin/users.php') ?>?ajax=check_duplicate&field=code&value=${encodeURIComponent(val)}&id=${encodeURIComponent(currentId)}`)
            .then(res => res.json())
            .then(data => {
                if (data.exists) {
                    codeInput.classList.add('is-invalid');
                    codeInput.classList.remove('is-valid');
                    if (codeFeedback) codeFeedback.textContent = data.message || 'Mã người dùng này đã tồn tại trong hệ thống.';
                    isCodeDuplicate = true;
                } else {
                    codeInput.classList.remove('is-invalid');
                    codeInput.classList.add('is-valid');
                    isCodeDuplicate = false;
                }
            })
            .catch(() => {
                isCodeDuplicate = false;
            });
    }

    function checkEmailUnique() {
        if (!emailInput) return;
        const val = emailInput.value.trim();
        const currentId = userIdInput ? userIdInput.value.trim() : '';

        if (val === '') {
            emailInput.classList.remove('is-invalid', 'is-valid');
            isEmailDuplicate = false;
            return;
        }

        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(val)) {
            emailInput.classList.add('is-invalid');
            emailInput.classList.remove('is-valid');
            if (emailFeedback) emailFeedback.textContent = 'Định dạng email chưa hợp lệ (VD: example@fbu.edu.vn).';
            isEmailDuplicate = true;
            return;
        }

        fetch(`<?= base_url('admin/users.php') ?>?ajax=check_duplicate&field=email&value=${encodeURIComponent(val)}&id=${encodeURIComponent(currentId)}`)
            .then(res => res.json())
            .then(data => {
                if (data.exists) {
                    emailInput.classList.add('is-invalid');
                    emailInput.classList.remove('is-valid');
                    if (emailFeedback) emailFeedback.textContent = data.message || 'Email này đã tồn tại trong hệ thống. Vui lòng nhập email khác.';
                    isEmailDuplicate = true;
                } else {
                    emailInput.classList.remove('is-invalid');
                    emailInput.classList.add('is-valid');
                    isEmailDuplicate = false;
                }
            })
            .catch(() => {
                isEmailDuplicate = false;
            });
    }

    function checkUsernameUnique() {
        if (!usernameInput) return;
        const val = usernameInput.value.trim();
        const currentId = userIdInput ? userIdInput.value.trim() : '';

        if (val === '') {
            usernameInput.classList.remove('is-invalid', 'is-valid');
            isUsernameDuplicate = false;
            return;
        }

        fetch(`<?= base_url('admin/users.php') ?>?ajax=check_duplicate&field=username&value=${encodeURIComponent(val)}&id=${encodeURIComponent(currentId)}`)
            .then(res => res.json())
            .then(data => {
                if (data.exists) {
                    usernameInput.classList.add('is-invalid');
                    usernameInput.classList.remove('is-valid');
                    if (usernameFeedback) usernameFeedback.textContent = data.message || 'Tên đăng nhập này đã tồn tại trong hệ thống.';
                    isUsernameDuplicate = true;
                } else {
                    usernameInput.classList.remove('is-invalid');
                    usernameInput.classList.add('is-valid');
                    isUsernameDuplicate = false;
                }
            })
            .catch(() => {
                isUsernameDuplicate = false;
            });
    }

    if (codeInput) {
        codeInput.addEventListener('input', function () {
            clearTimeout(codeTimer);
            codeTimer = setTimeout(checkCodeUnique, 350);
        });
        codeInput.addEventListener('blur', checkCodeUnique);
    }

    if (emailInput) {
        emailInput.addEventListener('input', function () {
            clearTimeout(emailTimer);
            emailTimer = setTimeout(checkEmailUnique, 350);
        });
        emailInput.addEventListener('blur', checkEmailUnique);
    }

    if (usernameInput) {
        usernameInput.addEventListener('input', function () {
            clearTimeout(usernameTimer);
            usernameTimer = setTimeout(checkUsernameUnique, 350);
        });
        usernameInput.addEventListener('blur', checkUsernameUnique);
    }

    if (userForm) {
        userForm.addEventListener('submit', function (e) {
            if (isCodeDuplicate) {
                e.preventDefault();
                codeInput.focus();
                alert('Mã người dùng đã tồn tại trong hệ thống. Vui lòng chọn mã khác!');
                return false;
            }
            if (isEmailDuplicate) {
                e.preventDefault();
                emailInput.focus();
                alert('Email đã tồn tại trong hệ thống hoặc không hợp lệ. Vui lòng kiểm tra lại!');
                return false;
            }
            if (isUsernameDuplicate) {
                e.preventDefault();
                usernameInput.focus();
                alert('Tên đăng nhập đã tồn tại trong hệ thống. Vui lòng chọn tên khác!');
                return false;
            }
        });
    }

    // Live AJAX status toggling
    function handleToggleStatus(userId, btnElement) {
        if (!userId) return;
        if (btnElement) btnElement.disabled = true;

        const formData = new FormData();
        formData.append('action', 'toggle_user');
        formData.append('id', userId);
        formData.append('ajax', '1');

        fetch('<?= base_url('admin/users.php') ?>', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(res => res.json())
        .then(data => {
            if (btnElement) btnElement.disabled = false;
            if (data.success) {
                // Update table row
                const rows = document.querySelectorAll('#usersTableBody tr');
                rows.forEach(row => {
                    const statusBtn = row.querySelector(`.toggle-status-btn[data-user-id="${userId}"]`);
                    const actionBtn = row.querySelector(`.toggle-action-btn[data-user-id="${userId}"]`);
                    if (statusBtn) {
                        statusBtn.dataset.status = data.new_status;
                        statusBtn.title = data.new_status === 1 ? 'Bấm để ngưng áp dụng tài khoản' : 'Bấm để kích hoạt lại tài khoản';
                        const badge = statusBtn.querySelector('.status-badge');
                        if (badge) {
                            if (data.new_status === 1) {
                                badge.className = 'badge text-bg-success px-2.5 py-1.5 status-badge';
                                badge.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i><span class="status-label">Đang hoạt động</span> <i class="bi bi-arrow-repeat ms-1 opacity-75 small"></i>';
                            } else {
                                badge.className = 'badge text-bg-warning text-dark px-2.5 py-1.5 status-badge';
                                badge.innerHTML = '<i class="bi bi-dash-circle-fill me-1"></i><span class="status-label">Ngưng áp dụng</span> <i class="bi bi-arrow-repeat ms-1 opacity-75 small"></i>';
                            }
                        }
                    }
                    if (actionBtn) {
                        actionBtn.dataset.status = data.new_status;
                        actionBtn.title = data.new_status === 1 ? 'Khóa / Ngưng áp dụng' : 'Kích hoạt tài khoản';
                        actionBtn.className = `btn btn-sm btn-outline-${data.new_status === 1 ? 'warning' : 'success'} toggle-action-btn`;
                        actionBtn.innerHTML = `<i class="bi bi-${data.new_status === 1 ? 'lock' : 'unlock'}"></i>`;
                    }
                });

                // Update active metric card count if present
                const activeCard = document.querySelector('.metric-green .count-up');
                if (activeCard && typeof data.active_count !== 'undefined') {
                    activeCard.textContent = data.active_count;
                    activeCard.dataset.countTo = data.active_count;
                }

                // Show toast notification
                showStatusToast(data.message || 'Cập nhật trạng thái thành công!');
            } else {
                alert(data.message || 'Có lỗi xảy ra khi cập nhật trạng thái.');
            }
        })
        .catch(err => {
            if (btnElement) btnElement.disabled = false;
            const form = btnElement?.closest('form');
            if (form) form.submit();
        });
    }

    document.addEventListener('click', function (e) {
        const toggleBtn = e.target.closest('.toggle-status-btn, .toggle-action-btn');
        if (toggleBtn) {
            e.preventDefault();
            const userId = toggleBtn.dataset.userId;
            handleToggleStatus(userId, toggleBtn);
        }
    });

    function showStatusToast(message) {
        let toastContainer = document.getElementById('userToastContainer');
        if (!toastContainer) {
            toastContainer = document.createElement('div');
            toastContainer.id = 'userToastContainer';
            toastContainer.className = 'position-fixed bottom-0 end-0 p-3';
            toastContainer.style.zIndex = '9999';
            document.body.appendChild(toastContainer);
        }

        const toastEl = document.createElement('div');
        toastEl.className = 'toast align-items-center text-bg-dark border-0 show shadow';
        toastEl.setAttribute('role', 'alert');
        toastEl.setAttribute('aria-live', 'assertive');
        toastEl.setAttribute('aria-atomic', 'true');
        toastEl.innerHTML = `
            <div class="d-flex">
                <div class="toast-body d-flex align-items-center gap-2">
                    <i class="bi bi-check-circle-fill text-success fs-5"></i>
                    <span>${message}</span>
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Đóng"></button>
            </div>
        `;
        toastContainer.appendChild(toastEl);
        setTimeout(() => {
            toastEl.classList.remove('show');
            setTimeout(() => toastEl.remove(), 400);
        }, 3000);
    }
});
</script>

<div class="modal fade management-form-modal" id="accountFormModal" tabindex="-1" aria-labelledby="accountFormModalLabel" aria-hidden="true" <?= $shouldOpenModal ? 'data-auto-open-modal' : '' ?>>
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5" id="accountFormModalLabel"><?= ($editingUser || (!empty($failedFormData) && !empty($failedFormData['id']))) ? 'Cập nhật người dùng' : 'Tạo người dùng mới' ?></h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <div class="modal-body">
                <?php if ($error && !empty($failedFormData)): ?>
                    <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($error) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Đóng"></button>
                    </div>
                <?php endif; ?>
                <form method="post" id="userAccountForm">
                    <input type="hidden" name="action" value="save_user">
                    <input type="hidden" name="id" id="userFormId" value="<?= htmlspecialchars($formValues['id']) ?>">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label" for="userFormCode">Mã người dùng <span class="text-danger">*</span> <span class="badge bg-light text-primary border ms-1"><i class="bi bi-shield-check me-1"></i>Duy nhất</span></label>
                            <input class="form-control" id="userFormCode" name="ma_nguoi_dung" value="<?= htmlspecialchars($formValues['MaNguoiDung']) ?>" placeholder="VD: <?= htmlspecialchars($suggestedUserCode) ?>" required autocomplete="off">
                            <div class="invalid-feedback" id="userCodeFeedback">Mã người dùng này đã tồn tại trong hệ thống.</div>
                            <div class="form-text text-muted small"><i class="bi bi-info-circle me-1"></i>Mã định danh duy nhất (PK).</div>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Họ tên <span class="text-danger">*</span></label>
                            <input class="form-control" name="ho_ten" value="<?= htmlspecialchars($formValues['HoTen']) ?>" placeholder="Nhập họ tên" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="userFormEmail">Email <span class="text-danger">*</span> <span class="badge bg-light text-primary border ms-1"><i class="bi bi-shield-check me-1"></i>Duy nhất</span></label>
                            <input class="form-control" type="email" id="userFormEmail" name="email" value="<?= htmlspecialchars($formValues['Email']) ?>" placeholder="example@fbu.edu.vn" required autocomplete="off">
                            <div class="invalid-feedback" id="userEmailFeedback">Email này đã tồn tại trong hệ thống.</div>
                            <div class="form-text text-muted small" id="userEmailHelper"><i class="bi bi-info-circle me-1"></i>Email duy nhất trong toàn hệ thống.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Số điện thoại</label>
                            <input class="form-control" name="sdt" value="<?= htmlspecialchars($formValues['SoDienThoai']) ?>" placeholder="VD: 0912345678">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="userFormUsername">Tên đăng nhập <span class="text-danger">*</span> <span class="badge bg-light text-primary border ms-1"><i class="bi bi-shield-check me-1"></i>Duy nhất</span></label>
                            <input class="form-control" id="userFormUsername" name="ten_dang_nhap" value="<?= htmlspecialchars($formValues['TenDangNhap']) ?>" placeholder="Nhập tên đăng nhập" required autocomplete="off">
                            <div class="invalid-feedback" id="userUsernameFeedback">Tên đăng nhập này đã tồn tại trong hệ thống.</div>
                            <div class="form-text text-muted small"><i class="bi bi-info-circle me-1"></i>Tên đăng nhập duy nhất để truy cập.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><?= ($editingUser || (!empty($failedFormData) && !empty($failedFormData['id']))) ? 'Mật khẩu mới' : 'Mật khẩu' ?> <?= ($editingUser || (!empty($failedFormData) && !empty($failedFormData['id']))) ? '' : '<span class="text-danger">*</span>' ?></label>
                            <div class="input-group">
                                <input class="form-control" name="password" id="userFormPassword" type="password" placeholder="<?= ($editingUser || (!empty($failedFormData) && !empty($failedFormData['id']))) ? 'Để trống nếu giữ nguyên' : 'Nhập mật khẩu' ?>" <?= ($editingUser || (!empty($failedFormData) && !empty($failedFormData['id']))) ? '' : 'required' ?>>
                                <button class="btn btn-outline-secondary" type="button" id="toggleUserFormPasswordBtn" title="Hiện/Ẩn mật khẩu">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Vai trò</label>
                            <select class="form-select" name="vai_tro">
                                <option value="admin" <?= ($formValues['VaiTro']) === 'admin' ? 'selected' : '' ?>>Quản trị viên</option>
                                <option value="user" <?= ($formValues['VaiTro']) === 'user' ? 'selected' : '' ?>>Người dùng</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Trạng thái</label>
                            <select class="form-select" name="trang_thai">
                                <option value="1" <?= (int) ($formValues['TrangThai']) === 1 ? 'selected' : '' ?>>Đang hoạt động</option>
                                <option value="0" <?= (int) ($formValues['TrangThai']) === 0 ? 'selected' : '' ?>>Ngưng áp dụng</option>
                            </select>
                        </div>
                    </div>
                    <div class="d-flex gap-2 justify-content-end mt-4">
                        <?php if ($editingUser || (!empty($failedFormData) && !empty($failedFormData['id']))): ?>
                            <a class="btn btn-outline-secondary" href="<?= base_url('admin/users.php') ?>">Hủy sửa</a>
                        <?php else: ?>
                            <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Hủy</button>
                        <?php endif; ?>
                        <button class="btn btn-primary" id="saveUserSubmitBtn" type="submit"><i class="bi bi-save me-1"></i> Lưu người dùng</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
