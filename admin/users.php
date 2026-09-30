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

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
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

<style>
/* ============================================================
   PREMIUM REDESIGNED STYLES FOR USER MANAGEMENT SYSTEM
============================================================ */

/* Metric Stats Cards */
.user-stat-card {
    background: #ffffff;
    border: 1px solid rgba(226, 232, 240, 0.8);
    border-radius: 16px;
    padding: 22px 24px;
    box-shadow: 0 4px 18px rgba(18, 48, 95, 0.04);
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
    cursor: pointer;
    user-select: none;
}
.user-stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 28px rgba(18, 48, 95, 0.1);
    border-color: rgba(47, 100, 173, 0.35);
}
.user-stat-card.active-filter-card {
    border-color: var(--brand, #2f64ad) !important;
    background: linear-gradient(180deg, #ffffff 0%, #f0f7ff 100%) !important;
    box-shadow: 0 0 0 3px rgba(47, 100, 173, 0.15), 0 8px 20px rgba(47, 100, 173, 0.12) !important;
}
html[data-theme="dark"] .user-stat-card {
    background: #132744;
    border-color: rgba(255, 255, 255, 0.08);
    box-shadow: 0 4px 18px rgba(0, 0, 0, 0.25);
}
html[data-theme="dark"] .user-stat-card:hover {
    border-color: rgba(88, 183, 230, 0.4);
}
html[data-theme="dark"] .user-stat-card.active-filter-card {
    border-color: #58b7e6 !important;
    background: #19355c !important;
    box-shadow: 0 0 0 3px rgba(88, 183, 230, 0.25) !important;
}

.stat-icon-wrapper {
    width: 52px;
    height: 52px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    flex-shrink: 0;
    margin-left: 12px;
}
.stat-icon-blue   { background: rgba(37, 99, 235, 0.12); color: #2563eb; }
.stat-icon-red    { background: rgba(220, 38, 38, 0.12); color: #dc2626; }
.stat-icon-amber  { background: rgba(217, 119, 6, 0.12); color: #d97706; }
.stat-icon-green  { background: rgba(16, 185, 129, 0.12); color: #10b981; }

html[data-theme="dark"] .stat-icon-blue   { background: rgba(59, 130, 246, 0.2); color: #60a5fa; }
html[data-theme="dark"] .stat-icon-red    { background: rgba(239, 68, 68, 0.2); color: #f87171; }
html[data-theme="dark"] .stat-icon-amber  { background: rgba(245, 158, 11, 0.2); color: #fbbf24; }
html[data-theme="dark"] .stat-icon-green  { background: rgba(16, 185, 129, 0.2); color: #34d399; }

/* Main Users Table Card */
.user-management-card {
    background: #ffffff;
    border: 1px solid rgba(226, 232, 240, 0.8);
    border-radius: 16px;
    box-shadow: 0 4px 20px rgba(18, 48, 95, 0.05);
    padding: 20px 22px;
    transition: all 0.2s ease;
}
html[data-theme="dark"] .user-management-card {
    background: #132744;
    border-color: rgba(255, 255, 255, 0.08);
}

/* User Filter Pills */
.filter-pill-btn {
    border-radius: 20px;
    padding: 6px 14px;
    font-size: 0.82rem;
    font-weight: 600;
    transition: all 0.2s ease;
    border: 1px solid rgba(203, 213, 225, 0.8);
    background: #f8fafc;
    color: #475569;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.filter-pill-btn:hover {
    background: #f1f5f9;
    color: #1e293b;
    border-color: #94a3b8;
}
.filter-pill-btn.active {
    background: var(--brand, #2f64ad);
    color: #ffffff !important;
    border-color: var(--brand, #2f64ad);
    box-shadow: 0 2px 8px rgba(47, 100, 173, 0.3);
}
html[data-theme="dark"] .filter-pill-btn {
    background: #1a365d;
    border-color: rgba(255, 255, 255, 0.12);
    color: #94a3b8;
}
html[data-theme="dark"] .filter-pill-btn:hover {
    background: #234677;
    color: #ffffff;
}
html[data-theme="dark"] .filter-pill-btn.active {
    background: #58b7e6;
    color: #06152a !important;
    border-color: #58b7e6;
}

/* User Table Elements with Compact & Balanced Proportions */
.users-table thead th {
    background: #f8fafc;
    color: #64748b;
    font-size: 0.76rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    padding: 12px 10px;
    border-bottom: 2px solid #e2e8f0;
}
html[data-theme="dark"] .users-table thead th {
    background: #0d1e36;
    color: #94a3b8;
    border-bottom-color: rgba(255, 255, 255, 0.08);
}
.users-table tbody td {
    padding: 12px 10px;
    vertical-align: middle;
    border-bottom: 1px solid #f1f5f9;
    font-size: 0.88rem;
}
html[data-theme="dark"] .users-table tbody td {
    border-bottom-color: rgba(255, 255, 255, 0.05);
}
.users-table tbody tr {
    transition: background-color 0.18s ease;
}
.users-table tbody tr:hover {
    background-color: #f8fbff !important;
}
html[data-theme="dark"] .users-table tbody tr:hover {
    background-color: rgba(88, 183, 230, 0.06) !important;
}

/* User Identity Display */
.user-avatar-circle {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.88rem;
    color: #ffffff;
    flex-shrink: 0;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.12);
}
.avatar-bg-admin { background: linear-gradient(135deg, #ef4444, #b91c1c); }
.avatar-bg-user  { background: linear-gradient(135deg, #3b82f6, #1d4ed8); }

.copy-code-badge {
    cursor: pointer;
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    transition: all 0.2s ease;
    padding: 4px 8px !important;
    font-size: 0.8rem !important;
}
.copy-code-badge:hover {
    transform: scale(1.04);
    box-shadow: 0 2px 6px rgba(37, 99, 235, 0.25);
}

/* Action Icon Buttons */
.user-action-btn {
    width: 30px;
    height: 30px;
    padding: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 7px;
    font-size: 0.85rem;
    transition: all 0.2s ease;
}
.user-action-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.12);
}

.action-buttons {
    gap: 4px !important;
}
</style>

<?php if ($shouldOpenModal && !$success): ?><script>document.body.dataset.autoOpenModal = 'accountFormModal';</script><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success alert-dismissible fade show rounded-3 shadow-xs mb-4 p-3" role="alert"><i class="bi bi-check-circle-fill me-2 fs-5 align-middle"></i><?= htmlspecialchars($success) ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Đóng"></button></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger alert-dismissible fade show rounded-3 shadow-xs mb-4 p-3" role="alert"><i class="bi bi-exclamation-triangle-fill me-2 fs-5 align-middle"></i><?= htmlspecialchars($error) ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Đóng"></button></div><?php endif; ?>

<!-- 1. TOP METRIC STATS CARDS -->
<div class="row g-3 mb-4">
    <!-- Card 1: Tổng người dùng -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="user-stat-card" data-quick-filter="all" title="Bấm để hiển thị toàn bộ người dùng">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="text-secondary small fw-bold text-uppercase">Tổng người dùng</span>
                <div class="stat-icon-wrapper stat-icon-blue">
                    <i class="bi bi-people-fill"></i>
                </div>
            </div>
            <div class="d-flex align-items-baseline gap-2">
                <h2 class="h3 fw-bolder mb-0 text-dark count-up" data-count-to="<?= $totalUsersCount ?>"><?= $totalUsersCount ?></h2>
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill small px-2.5 py-1">Hệ thống</span>
            </div>
            <p class="text-muted small mb-0 mt-3 d-flex align-items-center">
                <i class="bi bi-person-check text-primary me-2 fs-6"></i>
                <span>Toàn bộ tài khoản trong CSDL</span>
            </p>
        </div>
    </div>

    <!-- Card 2: Quản trị viên -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="user-stat-card" data-quick-filter="role-admin" title="Bấm để chỉ lọc Quản trị viên">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="text-secondary small fw-bold text-uppercase">Quản trị viên</span>
                <div class="stat-icon-wrapper stat-icon-red">
                    <i class="bi bi-shield-lock-fill"></i>
                </div>
            </div>
            <div class="d-flex align-items-baseline gap-2">
                <h2 class="h3 fw-bolder mb-0 text-danger count-up" data-count-to="<?= $adminUsersCount ?>"><?= $adminUsersCount ?></h2>
                <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill small px-2.5 py-1">Admin</span>
            </div>
            <p class="text-muted small mb-0 mt-3 d-flex align-items-center">
                <i class="bi bi-shield-check text-danger me-2 fs-6"></i>
                <span>Toàn quyền quản trị CSDL</span>
            </p>
        </div>
    </div>

    <!-- Card 3: Người dùng / Giảng viên -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="user-stat-card" data-quick-filter="role-user" title="Bấm để chỉ lọc Người dùng">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="text-secondary small fw-bold text-uppercase">Người dùng / GV</span>
                <div class="stat-icon-wrapper stat-icon-amber">
                    <i class="bi bi-person-badge-fill"></i>
                </div>
            </div>
            <div class="d-flex align-items-baseline gap-2">
                <h2 class="h3 fw-bolder mb-0 text-warning count-up" data-count-to="<?= $regularUsersCount ?>"><?= $regularUsersCount ?></h2>
                <span class="badge bg-warning-subtle text-dark border border-warning-subtle rounded-pill small px-2.5 py-1">User</span>
            </div>
            <p class="text-muted small mb-0 mt-3 d-flex align-items-center">
                <i class="bi bi-eye text-warning me-2 fs-6"></i>
                <span>Khai thác, xem & tra cứu minh chứng</span>
            </p>
        </div>
    </div>

    <!-- Card 4: Đang kích hoạt -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="user-stat-card" data-quick-filter="status-active" title="Bấm để chỉ lọc tài khoản Đang hoạt động">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="text-secondary small fw-bold text-uppercase">Đang kích hoạt</span>
                <div class="stat-icon-wrapper stat-icon-green">
                    <i class="bi bi-check-circle-fill"></i>
                </div>
            </div>
            <div class="d-flex align-items-baseline gap-2">
                <h2 class="h3 fw-bolder mb-0 text-success count-up" data-count-to="<?= $activeUsersCount ?>"><?= $activeUsersCount ?></h2>
                <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill small px-2.5 py-1">Hoạt động</span>
            </div>
            <p class="text-muted small mb-0 mt-3 d-flex align-items-center">
                <i class="bi bi-toggle-on text-success me-2 fs-6"></i>
                <span>Tài khoản được phép truy cập</span>
            </p>
        </div>
    </div>
</div>

<!-- 2. MAIN USER MANAGEMENT CARD -->
<div class="user-management-card mb-4">
    <!-- Header: Title & Actions -->
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 pb-3 border-bottom mb-4">
        <div>
            <h2 class="h5 fw-bold mb-1 text-dark d-flex align-items-center gap-2">
                <i class="bi bi-people text-primary fs-5"></i> Danh sách người dùng hệ thống
            </h2>
            <p class="text-secondary small mb-0">Quản lý tài khoản cán bộ giảng viên, phân quyền vai trò và trạng thái hoạt động.</p>
        </div>
        <div class="d-flex gap-3">
            <a class="btn btn-primary d-inline-flex align-items-center gap-2 shadow-sm px-3.5 py-2" href="<?= base_url('admin/users.php?create=1') ?>">
                <i class="bi bi-person-plus-fill fs-6"></i>
                <span>Thêm người dùng mới</span>
            </a>
            <a class="btn btn-outline-success d-inline-flex align-items-center gap-2 px-3.5 py-2" id="exportExcelBtn" href="<?= base_url('admin/users.php?export=excel' . ($searchKeyword !== '' ? '&search=' . urlencode($searchKeyword) : '')) ?>">
                <i class="bi bi-file-earmark-excel-fill fs-6"></i>
                <span>Xuất Excel</span>
            </a>
        </div>
    </div>

    <!-- Filter & Search Toolbar -->
    <div class="row g-3 align-items-center mb-4">
        <!-- Search Input -->
        <div class="col-12 col-md-5 col-lg-4">
            <div class="input-group">
                <span class="input-group-text bg-light border-end-0 text-muted px-3"><i class="bi bi-search"></i></span>
                <input type="text" name="search" id="userSearchInput" class="form-control bg-light border-start-0 ps-1 py-2" placeholder="Tìm theo mã, tên, email, sđt, username..." value="<?= htmlspecialchars($searchKeyword) ?>" autocomplete="off">
                <button class="btn btn-light border border-start-0 text-muted px-3" type="button" id="btnClearSearch" style="display: <?= $searchKeyword !== '' ? 'block' : 'none' ?>;" title="Xóa tìm kiếm">
                    <i class="bi bi-x-circle-fill"></i>
                </button>
            </div>
        </div>

        <!-- Role Filter Tabs -->
        <div class="col-12 col-md-7 col-lg-5">
            <div class="d-flex flex-wrap align-items-center gap-2">
                <span class="text-muted small me-1 fw-semibold"><i class="bi bi-funnel me-1"></i>Lọc:</span>
                <button type="button" class="filter-pill-btn active" data-filter-type="role" data-filter-value="all">
                    <span>Tất cả (<span id="roleCountAll"><?= $totalUsersCount ?></span>)</span>
                </button>
                <button type="button" class="filter-pill-btn" data-filter-type="role" data-filter-value="admin">
                    <i class="bi bi-shield-lock text-danger"></i>
                    <span>Quản trị (<span id="roleCountAdmin"><?= $adminUsersCount ?></span>)</span>
                </button>
                <button type="button" class="filter-pill-btn" data-filter-type="role" data-filter-value="user">
                    <i class="bi bi-person text-primary"></i>
                    <span>Người dùng (<span id="roleCountUser"><?= $regularUsersCount ?></span>)</span>
                </button>
            </div>
        </div>

        <!-- Result Counter Badge -->
        <div class="col-12 col-lg-3 text-lg-end">
            <span class="badge bg-light text-secondary border px-3 py-2 fw-semibold d-inline-flex align-items-center gap-2" id="tableFilterCount">
                <i class="bi bi-check2-circle text-success fs-6"></i>
                <span>Hiển thị <strong class="text-dark" id="visibleCountDisplay"><?= count($users) ?></strong> / <?= $totalUsersCount ?> tài khoản</span>
            </span>
        </div>
    </div>

    <!-- Responsive Table -->
    <div class="table-responsive border rounded-3 overflow-hidden">
        <table class="table users-table align-middle mb-0" data-page-size="10">
            <thead>
                <tr>
                    <th class="text-center text-nowrap" style="width: 45px;">STT</th>
                    <th class="text-nowrap" style="width: 95px;">Mã TK</th>
                    <th style="min-width: 175px;">Họ tên & Người dùng</th>
                    <th style="min-width: 165px;">Email liên hệ</th>
                    <th class="text-nowrap" style="width: 110px;">Số điện thoại</th>
                    <th class="text-nowrap" style="width: 105px;">Tên đăng nhập</th>
                    <th class="text-nowrap" style="width: 95px;">Mật khẩu</th>
                    <th class="text-center text-nowrap" style="width: 110px;">Vai trò</th>
                    <th class="text-center text-nowrap" style="width: 125px;">Trạng thái</th>
                    <th class="text-end text-nowrap action-cell" style="width: 95px;">Thao tác</th>
                </tr>
            </thead>
            <tbody id="usersTableBody">
            <?php 
            $stt = 1;
            foreach ($users as $user): 
                $isAdmin = ($user['role_code'] === 'admin');
                $isActive = (int)($user['status_raw'] ?? 1) === 1;
                $isMe = ($user['id'] === (string)$currentUserId);
            ?>
                <tr data-user-role="<?= $isAdmin ? 'admin' : 'user' ?>" data-user-status="<?= $isActive ? '1' : '0' ?>" id="user-row-<?= htmlspecialchars($user['id']) ?>">
                    <td class="text-center text-muted small fw-medium"><?= $stt++ ?></td>
                    <td>
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2.5 py-1.5 fw-bold copy-code-badge d-inline-flex align-items-center gap-1.5" data-clipboard-text="<?= htmlspecialchars($user['code']) ?>" title="Bấm để sao chép mã">
                            <span><?= htmlspecialchars($user['code']) ?></span>
                            <i class="bi bi-copy opacity-50" style="font-size: 0.72rem;"></i>
                        </span>
                    </td>
                    <td>
                        <div class="d-flex align-items-center gap-2.5">
                            <div class="user-avatar-circle <?= $isAdmin ? 'avatar-bg-admin' : 'avatar-bg-user' ?>" title="<?= htmlspecialchars($user['name']) ?>">
                                <?= user_initials($user['name']) ?>
                            </div>
                            <div>
                                <div class="fw-bold text-dark d-flex align-items-center gap-1.5 mb-0.5">
                                    <span><?= htmlspecialchars($user['name']) ?></span>
                                    <?php if ($isMe): ?>
                                        <span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill" style="font-size: 0.65rem; padding: 2px 6px;">Bạn</span>
                                    <?php endif; ?>
                                </div>
                                <div class="text-muted small d-flex align-items-center" style="font-size: 0.78rem;">
                                    <i class="bi bi-shield-check text-secondary me-1"></i>
                                    <span><?= $isAdmin ? 'Quản trị viên' : 'Cán bộ / GV' ?></span>
                                </div>
                            </div>
                        </div>
                    </td>
                    <td class="text-nowrap">
                        <a href="mailto:<?= htmlspecialchars($user['email']) ?>" class="text-decoration-none text-dark d-inline-flex align-items-center hover-primary" title="Gửi email" style="gap: 5px;">
                            <i class="bi bi-envelope text-primary"></i>
                            <span class="small font-monospace"><?= htmlspecialchars($user['email']) ?></span>
                        </a>
                    </td>
                    <td class="text-nowrap">
                        <?php if ($user['phone']): ?>
                            <a href="tel:<?= htmlspecialchars($user['phone']) ?>" class="text-decoration-none text-dark d-inline-flex align-items-center small font-monospace" title="Gọi điện" style="gap: 5px;">
                                <i class="bi bi-telephone text-success"></i>
                                <span><?= htmlspecialchars($user['phone']) ?></span>
                            </a>
                        <?php else: ?>
                            <span class="text-muted small ms-2">-</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <code class="px-2 py-1 bg-light border rounded text-primary fw-bold small"><?= htmlspecialchars($user['username']) ?></code>
                    </td>
                    <td class="text-nowrap">
                        <div class="d-inline-flex align-items-center gap-1.5 bg-light px-2 py-1 rounded-3 border">
                            <span class="text-muted font-monospace pwd-text small" data-masked="true" data-plain="123456">••••••</span>
                            <button type="button" class="btn btn-sm btn-link p-0 text-secondary toggle-pwd-btn ms-1" title="Hiện / Ẩn mật khẩu">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </td>
                    <td class="text-center text-nowrap">
                        <?php if ($isAdmin): ?>
                            <span class="badge bg-danger text-white px-2.5 py-1.5 rounded-pill shadow-xs d-inline-flex align-items-center gap-1.5" style="font-size: 0.78rem;">
                                <i class="bi bi-shield-lock-fill"></i>
                                <span>Quản trị</span>
                            </span>
                        <?php else: ?>
                            <span class="badge bg-secondary-subtle text-secondary border px-2.5 py-1.5 rounded-pill d-inline-flex align-items-center gap-1.5" style="font-size: 0.78rem;">
                                <i class="bi bi-person-fill"></i>
                                <span>Người dùng</span>
                            </span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center text-nowrap status-cell">
                        <?php if ($isMe): ?>
                            <span class="badge bg-success-subtle text-success border border-success-subtle px-2.5 py-1.5 rounded-pill d-inline-flex align-items-center gap-1.5" style="font-size: 0.78rem;" title="Tài khoản đang đăng nhập">
                                <i class="bi bi-check-circle-fill"></i>
                                <span>Hoạt động</span>
                            </span>
                        <?php else: ?>
                            <form method="post" class="d-inline toggle-status-form">
                                <input type="hidden" name="action" value="toggle_user">
                                <input type="hidden" name="id" value="<?= htmlspecialchars($user['id']) ?>">
                                <button type="button" class="btn p-0 border-0 bg-transparent toggle-status-btn"
                                        data-user-id="<?= htmlspecialchars($user['id']) ?>"
                                        data-status="<?= (int) $user['status_raw'] ?>"
                                        title="Bấm để <?= $isActive ? 'ngưng áp dụng tài khoản' : 'kích hoạt lại tài khoản' ?>">
                                    <?php if ($isActive): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle px-2.5 py-1.5 rounded-pill status-badge d-inline-flex align-items-center gap-1.5" style="font-size: 0.78rem; cursor: pointer; transition: all 0.2s ease;">
                                            <i class="bi bi-check-circle-fill text-success"></i>
                                            <span class="status-label">Hoạt động</span>
                                            <i class="bi bi-arrow-repeat opacity-50 ms-1 small"></i>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-2.5 py-1.5 rounded-pill status-badge d-inline-flex align-items-center gap-1.5" style="font-size: 0.78rem; cursor: pointer; transition: all 0.2s ease;">
                                            <i class="bi bi-dash-circle-fill text-warning"></i>
                                            <span class="status-label">Khóa</span>
                                            <i class="bi bi-arrow-repeat opacity-50 ms-1 small"></i>
                                        </span>
                                    <?php endif; ?>
                                </button>
                            </form>
                        <?php endif; ?>
                    </td>
                    <td class="text-end action-cell">
                        <div class="action-buttons d-inline-flex justify-content-end gap-1">
                            <?php if (!$isMe): ?>
                                <button class="btn btn-sm btn-outline-<?= $isActive ? 'warning' : 'success' ?> user-action-btn toggle-action-btn"
                                        type="button"
                                        data-user-id="<?= htmlspecialchars($user['id']) ?>"
                                        data-status="<?= (int) $user['status_raw'] ?>"
                                        title="<?= $isActive ? 'Khóa / Ngưng áp dụng' : 'Kích hoạt tài khoản' ?>">
                                    <i class="bi bi-<?= $isActive ? 'lock' : 'unlock' ?>"></i>
                                </button>
                            <?php endif; ?>
                            <a class="btn btn-sm btn-outline-primary user-action-btn" href="?edit=<?= $user['id'] ?>" title="Chỉnh sửa thông tin tài khoản">
                                <i class="bi bi-pencil-square"></i>
                            </a>
                            <?php if (!$isMe): ?>
                                <form method="post" class="d-inline" data-confirm-form="Bạn chắc chắn muốn xóa vĩnh viễn tài khoản người dùng '<?= htmlspecialchars($user['name']) ?>'? Thao tác này không thể hoàn tác.">
                                    <input type="hidden" name="action" value="delete_user">
                                    <input type="hidden" name="id" value="<?= $user['id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger user-action-btn" type="submit" title="Xóa tài khoản này">
                                        <i class="bi bi-trash3"></i>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <tr id="noDataRow" class="<?= !empty($users) ? 'd-none' : '' ?>">
                <td colspan="10" class="text-center text-secondary py-5">
                    <i class="bi bi-person-x fs-2 d-block mb-2 text-muted"></i>
                    Không tìm thấy tài khoản người dùng nào phù hợp với điều kiện tìm kiếm
                </td>
            </tr>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('userSearchInput');
    const clearSearchBtn = document.getElementById('btnClearSearch');
    const tableBody = document.getElementById('usersTableBody');
    const noDataRow = document.getElementById('noDataRow');
    const visibleCountDisplay = document.getElementById('visibleCountDisplay');
    const filterPills = document.querySelectorAll('.filter-pill-btn[data-filter-type="role"]');
    const quickStatCards = document.querySelectorAll('.user-stat-card[data-quick-filter]');

    let currentRoleFilter = 'all';

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

    function applyFilters() {
        const query = normalizeText(searchInput ? searchInput.value : '');
        const rows = tableBody ? tableBody.querySelectorAll('tr:not(#noDataRow)') : [];
        let visibleCount = 0;

        rows.forEach(row => {
            const role = row.dataset.userRole || '';
            const status = row.dataset.userStatus || '';
            const textToMatch = normalizeText(row.textContent || '');

            const matchesSearch = (query === '' || textToMatch.includes(query));
            let matchesRole = true;

            if (currentRoleFilter === 'admin') {
                matchesRole = (role === 'admin');
            } else if (currentRoleFilter === 'user') {
                matchesRole = (role === 'user');
            } else if (currentRoleFilter === 'status-active') {
                matchesRole = (status === '1');
            }

            if (matchesSearch && matchesRole) {
                row.dataset.filteredOut = 'false';
                row.style.display = '';
                visibleCount++;
            } else {
                row.dataset.filteredOut = 'true';
                row.style.display = 'none';
            }
        });

        if (noDataRow) {
            noDataRow.classList.toggle('d-none', visibleCount > 0);
        }
        if (visibleCountDisplay) {
            visibleCountDisplay.textContent = visibleCount;
        }
        if (clearSearchBtn) {
            clearSearchBtn.style.display = (searchInput && searchInput.value.trim() !== '') ? 'block' : 'none';
        }
    }

    if (searchInput) {
        searchInput.addEventListener('input', applyFilters);
    }
    if (clearSearchBtn) {
        clearSearchBtn.addEventListener('click', function () {
            if (searchInput) {
                searchInput.value = '';
                applyFilters();
                searchInput.focus();
            }
        });
    }

    // Role Filter Pill buttons
    filterPills.forEach(pill => {
        pill.addEventListener('click', function () {
            filterPills.forEach(p => p.classList.remove('active'));
            this.classList.add('active');
            currentRoleFilter = this.dataset.filterValue;

            // Sync stat card highlight
            quickStatCards.forEach(c => c.classList.remove('active-filter-card'));
            applyFilters();
        });
    });

    // Quick Stat Cards filter click
    quickStatCards.forEach(card => {
        card.addEventListener('click', function () {
            const filterType = this.dataset.quickFilter;
            quickStatCards.forEach(c => c.classList.remove('active-filter-card'));
            this.classList.add('active-filter-card');

            if (filterType === 'all') {
                currentRoleFilter = 'all';
                filterPills.forEach(p => p.classList.toggle('active', p.dataset.filterValue === 'all'));
            } else if (filterType === 'role-admin') {
                currentRoleFilter = 'admin';
                filterPills.forEach(p => p.classList.toggle('active', p.dataset.filterValue === 'admin'));
            } else if (filterType === 'role-user') {
                currentRoleFilter = 'user';
                filterPills.forEach(p => p.classList.toggle('active', p.dataset.filterValue === 'user'));
            } else if (filterType === 'status-active') {
                currentRoleFilter = 'status-active';
                filterPills.forEach(p => p.classList.remove('active'));
            }
            applyFilters();
        });
    });

    // Copy code to clipboard
    document.querySelectorAll('.copy-code-badge').forEach(badge => {
        badge.addEventListener('click', function () {
            const text = this.dataset.clipboardText;
            if (text && navigator.clipboard) {
                navigator.clipboard.writeText(text);
                const originalHtml = this.innerHTML;
                this.innerHTML = `<span>${text}</span> <i class="bi bi-check-lg text-success ms-1"></i>`;
                setTimeout(() => {
                    this.innerHTML = originalHtml;
                }, 1500);
            }
        });
    });

    // Toggle password view in table
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
                textSpan.classList.add('fw-bold', 'text-primary');
                if (icon) icon.className = 'bi bi-eye-slash text-primary';
            } else {
                textSpan.dataset.masked = 'true';
                textSpan.textContent = '••••••••';
                textSpan.classList.remove('fw-bold', 'text-primary');
                textSpan.classList.add('text-muted');
                if (icon) icon.className = 'bi bi-eye';
            }
        });
    });

    // Modal Password Show/Hide Toggle & Random Generator
    const formPwdInput = document.getElementById('userFormPassword');
    const formPwdToggleBtn = document.getElementById('toggleUserFormPasswordBtn');
    const btnGenPwd = document.getElementById('btnGenerateRandomPwd');

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

    if (btnGenPwd && formPwdInput) {
        btnGenPwd.addEventListener('click', function () {
            const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789!@#$';
            let randomPwd = '';
            for (let i = 0; i < 10; i++) {
                randomPwd += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            formPwdInput.value = randomPwd;
            formPwdInput.type = 'text';
            if (formPwdToggleBtn) {
                const icon = formPwdToggleBtn.querySelector('i');
                if (icon) icon.className = 'bi bi-eye-slash';
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
                const rows = document.querySelectorAll('#usersTableBody tr');
                rows.forEach(row => {
                    const statusBtn = row.querySelector(`.toggle-status-btn[data-user-id="${userId}"]`);
                    const actionBtn = row.querySelector(`.toggle-action-btn[data-user-id="${userId}"]`);
                    if (statusBtn) {
                        row.dataset.userStatus = data.new_status ? '1' : '0';
                        statusBtn.dataset.status = data.new_status;
                        statusBtn.title = data.new_status === 1 ? 'Bấm để ngưng áp dụng tài khoản' : 'Bấm để kích hoạt lại tài khoản';
                        const badge = statusBtn.querySelector('.status-badge');
                        if (badge) {
                            if (data.new_status === 1) {
                                badge.className = 'badge bg-success-subtle text-success border border-success-subtle px-3 py-2 rounded-pill status-badge d-inline-flex align-items-center gap-2';
                                badge.innerHTML = '<i class="bi bi-check-circle-fill text-success"></i><span class="status-label">Đang hoạt động</span> <i class="bi bi-arrow-repeat opacity-50 ms-1 small"></i>';
                            } else {
                                badge.className = 'badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-3 py-2 rounded-pill status-badge d-inline-flex align-items-center gap-2';
                                badge.innerHTML = '<i class="bi bi-dash-circle-fill text-warning"></i><span class="status-label">Ngưng áp dụng</span> <i class="bi bi-arrow-repeat opacity-50 ms-1 small"></i>';
                            }
                        }
                    }
                    if (actionBtn) {
                        actionBtn.dataset.status = data.new_status;
                        actionBtn.title = data.new_status === 1 ? 'Khóa / Ngưng áp dụng' : 'Kích hoạt tài khoản';
                        actionBtn.className = `btn btn-sm btn-outline-${data.new_status === 1 ? 'warning' : 'success'} user-action-btn toggle-action-btn`;
                        actionBtn.innerHTML = `<i class="bi bi-${data.new_status === 1 ? 'lock' : 'unlock'}"></i>`;
                    }
                });

                // Update active metric card count if present
                const activeCard = document.querySelector('.user-stat-card[data-quick-filter="status-active"] .count-up');
                if (activeCard && typeof data.active_count !== 'undefined') {
                    activeCard.textContent = data.active_count;
                    activeCard.dataset.countTo = data.active_count;
                }

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
        toastEl.className = 'toast align-items-center text-bg-dark border-0 show shadow-lg rounded-3';
        toastEl.setAttribute('role', 'alert');
        toastEl.setAttribute('aria-live', 'assertive');
        toastEl.setAttribute('aria-atomic', 'true');
        toastEl.innerHTML = `
            <div class="d-flex">
                <div class="toast-body d-flex align-items-center gap-2.5 py-2.5 px-3">
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

<!-- MODAL: TẠO / SỬA NGƯỜI DÙNG -->
<div class="modal fade management-form-modal" id="accountFormModal" tabindex="-1" aria-labelledby="accountFormModalLabel" aria-hidden="true" <?= $shouldOpenModal ? 'data-auto-open-modal' : '' ?>>
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content rounded-4 border-0 shadow-lg overflow-hidden">
            <div class="modal-header bg-light border-bottom py-3 px-4">
                <div class="d-flex align-items-center gap-2.5">
                    <div class="stat-icon-wrapper stat-icon-blue ms-0 me-2" style="width: 40px; height: 40px; font-size: 1.2rem;">
                        <i class="bi bi-person-gear"></i>
                    </div>
                    <h2 class="modal-title h5 fw-bold mb-0 text-dark" id="accountFormModalLabel">
                        <?= ($editingUser || (!empty($failedFormData) && !empty($failedFormData['id']))) ? 'Cập nhật thông tin người dùng' : 'Tạo mới người dùng hệ thống' ?>
                    </h2>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <div class="modal-body p-4">
                <?php if ($error && !empty($failedFormData)): ?>
                    <div class="alert alert-danger alert-dismissible fade show mb-3 rounded-3 p-3" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2 fs-5 align-middle"></i><?= htmlspecialchars($error) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Đóng"></button>
                    </div>
                <?php endif; ?>
                <form method="post" id="userAccountForm">
                    <input type="hidden" name="action" value="save_user">
                    <input type="hidden" name="id" id="userFormId" value="<?= htmlspecialchars($formValues['id']) ?>">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small mb-1.5" for="userFormCode">Mã tài khoản <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted px-3"><i class="bi bi-upc-scan"></i></span>
                                <input class="form-control py-2" id="userFormCode" name="ma_nguoi_dung" value="<?= htmlspecialchars($formValues['MaNguoiDung']) ?>" placeholder="VD: <?= htmlspecialchars($suggestedUserCode) ?>" required autocomplete="off">
                            </div>
                            <div class="invalid-feedback" id="userCodeFeedback">Mã người dùng này đã tồn tại trong hệ thống.</div>
                            <div class="form-text text-muted small mt-1"><i class="bi bi-info-circle me-1"></i>Mã định danh duy nhất (PK).</div>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label fw-semibold small mb-1.5">Họ và tên <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted px-3"><i class="bi bi-person"></i></span>
                                <input class="form-control py-2" name="ho_ten" value="<?= htmlspecialchars($formValues['HoTen']) ?>" placeholder="Nhập họ tên (VD: PGS.TS. Lê Hoàng Nam)" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small mb-1.5" for="userFormEmail">Email liên hệ <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted px-3"><i class="bi bi-envelope"></i></span>
                                <input class="form-control py-2" type="email" id="userFormEmail" name="email" value="<?= htmlspecialchars($formValues['Email']) ?>" placeholder="example@fbu.edu.vn" required autocomplete="off">
                            </div>
                            <div class="invalid-feedback" id="userEmailFeedback">Email này đã tồn tại trong hệ thống.</div>
                            <div class="form-text text-muted small mt-1" id="userEmailHelper"><i class="bi bi-shield-check me-1"></i>Email duy nhất trong toàn hệ thống.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small mb-1.5">Số điện thoại</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted px-3"><i class="bi bi-telephone"></i></span>
                                <input class="form-control py-2" name="sdt" value="<?= htmlspecialchars($formValues['SoDienThoai']) ?>" placeholder="VD: 0912345678">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small mb-1.5" for="userFormUsername">Tên đăng nhập <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted px-3"><i class="bi bi-person-badge"></i></span>
                                <input class="form-control py-2" id="userFormUsername" name="ten_dang_nhap" value="<?= htmlspecialchars($formValues['TenDangNhap']) ?>" placeholder="Nhập tên đăng nhập" required autocomplete="off">
                            </div>
                            <div class="invalid-feedback" id="userUsernameFeedback">Tên đăng nhập này đã tồn tại trong hệ thống.</div>
                            <div class="form-text text-muted small mt-1"><i class="bi bi-info-circle me-1"></i>Dùng để đăng nhập vào hệ thống.</div>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex justify-content-between align-items-center mb-1.5">
                                <label class="form-label fw-semibold small mb-0"><?= ($editingUser || (!empty($failedFormData) && !empty($failedFormData['id']))) ? 'Mật khẩu mới' : 'Mật khẩu' ?> <?= ($editingUser || (!empty($failedFormData) && !empty($failedFormData['id']))) ? '' : '<span class="text-danger">*</span>' ?></label>
                                <button type="button" class="btn btn-link btn-xs p-0 text-decoration-none" id="btnGenerateRandomPwd" style="font-size: 0.8rem;">
                                    <i class="bi bi-magic me-1"></i>Tạo ngẫu nhiên
                                </button>
                            </div>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted px-3"><i class="bi bi-key"></i></span>
                                <input class="form-control py-2" name="password" id="userFormPassword" type="password" placeholder="<?= ($editingUser || (!empty($failedFormData) && !empty($failedFormData['id']))) ? 'Để trống nếu giữ nguyên' : 'Nhập mật khẩu' ?>" <?= ($editingUser || (!empty($failedFormData) && !empty($failedFormData['id']))) ? '' : 'required' ?>>
                                <button class="btn btn-outline-secondary px-3" type="button" id="toggleUserFormPasswordBtn" title="Hiện/Ẩn mật khẩu">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small mb-1.5">Phân quyền vai trò</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted px-3"><i class="bi bi-shield-check"></i></span>
                                <select class="form-select py-2" name="vai_tro">
                                    <option value="admin" <?= ($formValues['VaiTro']) === 'admin' ? 'selected' : '' ?>>Quản trị viên (Toàn quyền)</option>
                                    <option value="user" <?= ($formValues['VaiTro']) === 'user' ? 'selected' : '' ?>>Người dùng (Xem & tra cứu CSDL)</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small mb-1.5">Trạng thái tài khoản</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted px-3"><i class="bi bi-toggle-on"></i></span>
                                <select class="form-select py-2" name="trang_thai">
                                    <option value="1" <?= (int) ($formValues['TrangThai']) === 1 ? 'selected' : '' ?>>Đang hoạt động (Kích hoạt)</option>
                                    <option value="0" <?= (int) ($formValues['TrangThai']) === 0 ? 'selected' : '' ?>>Ngưng áp dụng (Tạm khóa)</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex gap-3 justify-content-end mt-4 pt-3 border-top">
                        <?php if ($editingUser || (!empty($failedFormData) && !empty($failedFormData['id']))): ?>
                            <a class="btn btn-outline-secondary px-3.5 py-2" href="<?= base_url('admin/users.php') ?>">Hủy sửa</a>
                        <?php else: ?>
                            <button class="btn btn-outline-secondary px-3.5 py-2" type="button" data-bs-dismiss="modal">Đóng</button>
                        <?php endif; ?>
                        <button class="btn btn-primary px-4 py-2 shadow-sm d-inline-flex align-items-center gap-2" id="saveUserSubmitBtn" type="submit">
                            <i class="bi bi-check2-circle fs-6"></i>
                            <span>Lưu thông tin người dùng</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
