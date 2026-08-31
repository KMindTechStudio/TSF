<?php
require_once __DIR__ . '/../includes/helpers.php';
require_roles(['admin']);
require_once __DIR__ . '/../config/database.php';

$pdo = db();
$success = '';
$error = '';
$currentUserId = $_SESSION['user_id'] ?? 'ND001';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'save_user') {
            $rawId        = trim($_POST['id'] ?? '');
            $maNguoiDung  = trim($_POST['ma_nguoi_dung'] ?? '');
            $fullName     = trim($_POST['ho_ten'] ?? '');
            $department   = trim($_POST['don_vi_cong_tac'] ?? '');
            $email        = trim($_POST['email'] ?? '');
            $phone        = trim($_POST['sdt'] ?? '');
            $username     = trim($_POST['ten_dang_nhap'] ?? '');
            $role         = trim($_POST['vai_tro'] ?? 'user');
            $status       = (int) ($_POST['trang_thai'] ?? 1);
            $password     = $_POST['password'] ?? '';

            if ($fullName === '' || $username === '' || $email === '') {
                throw new RuntimeException('Vui lòng nhập đầy đủ họ tên, tên đăng nhập và email.');
            }

            if (!in_array($role, ['admin', 'user'], true)) {
                throw new RuntimeException('Vai trò không hợp lệ. Chỉ chấp nhận Quản trị viên hoặc Người dùng.');
            }

            $check = $pdo->prepare('SELECT MaNguoiDung FROM NguoiDung WHERE (TenDangNhap = :username OR Email = :email) AND MaNguoiDung <> :id LIMIT 1');
            $check->execute([
                'username' => $username,
                'email'    => $email,
                'id'       => $rawId,
            ]);
            if ($check->fetch()) {
                throw new RuntimeException('Tên đăng nhập hoặc email đã tồn tại.');
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
                    $chk = $pdo->prepare('SELECT COUNT(*) FROM NguoiDung WHERE MaNguoiDung = :code');
                    $chk->execute(['code' => $maNguoiDung]);
                    if ((int) $chk->fetchColumn() > 0) {
                        throw new RuntimeException('Mã người dùng "' . $maNguoiDung . '" đã tồn tại. Vui lòng nhập mã khác.');
                    }
                    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
                    $upMc = $pdo->prepare('UPDATE MinhChung SET MaNguoiDung = :new_code WHERE MaNguoiDung = :old_code');
                    $upMc->execute(['new_code' => $maNguoiDung, 'old_code' => $rawId]);

                    $upTok = $pdo->prepare('UPDATE remember_tokens SET MaNguoiDung = :new_code WHERE MaNguoiDung = :old_code');
                    $upTok->execute(['new_code' => $maNguoiDung, 'old_code' => $rawId]);

                    $upLog = $pdo->prepare('UPDATE audit_logs SET MaNguoiDung = :new_code WHERE MaNguoiDung = :old_code');
                    $upLog->execute(['new_code' => $maNguoiDung, 'old_code' => $rawId]);

                    if (($_SESSION['user_id'] ?? '') === $rawId) {
                        $_SESSION['user_id'] = $maNguoiDung;
                    }
                }

                if ($password !== '') {
                    $stmt = $pdo->prepare("
                        UPDATE NguoiDung
                        SET MaNguoiDung = :new_code,
                            HoTen = :full_name,
                            DonViCongTac = :dept,
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
                        'dept'          => $department,
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
                            DonViCongTac = :dept,
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
                        'dept'          => $department,
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
                    throw new RuntimeException('Vui lòng nhập Mã người dùng.');
                }
                $chk = $pdo->prepare('SELECT COUNT(*) FROM NguoiDung WHERE MaNguoiDung = :code');
                $chk->execute(['code' => $maNguoiDung]);
                if ((int) $chk->fetchColumn() > 0) {
                    throw new RuntimeException('Mã người dùng "' . $maNguoiDung . '" đã tồn tại. Vui lòng nhập mã khác.');
                }

                if ($password === '') {
                    throw new RuntimeException('Vui lòng nhập mật khẩu cho tài khoản mới.');
                }

                $stmt = $pdo->prepare("
                    INSERT INTO NguoiDung (MaNguoiDung, HoTen, DonViCongTac, Email, {$phoneCol}, TenDangNhap, MatKhau, VaiTro, TrangThai)
                    VALUES (:code, :full_name, :dept, :email, :phone, :username, :password_hash, :role, :status)
                ");
                $stmt->execute([
                    'code'          => $maNguoiDung,
                    'full_name'     => $fullName,
                    'dept'          => $department,
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
            $stmtName = $pdo->prepare('SELECT HoTen, TenDangNhap FROM NguoiDung WHERE MaNguoiDung = :id');
            $stmtName->execute(['id' => $id]);
            $uRow = $stmtName->fetch();
            $uName = $uRow ? ($uRow['HoTen'] . ' (' . $uRow['TenDangNhap'] . ')') : ('#' . $id);

            $stmt = $pdo->prepare("UPDATE NguoiDung SET TrangThai = IF(TrangThai = 1, 0, 1) WHERE MaNguoiDung = :id");
            $stmt->execute(['id' => $id]);
            log_activity('cap_nhat_trang_thai', 'nguoi_dung', 0, $uName);
            $success = 'Cập nhật trạng thái tài khoản thành công.';
        }
    } catch (Throwable $exception) {
        $error = 'Không thể thực hiện thao tác: ' . $exception->getMessage();
    }
    unset($_GET['create'], $_GET['edit']);
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
        $matchDept  = search_contains($user['department'], $searchKeyword);
        return $matchCode || $matchName || $matchRole || $matchUser || $matchEmail || $matchPhone || $matchDept;
    });
}

$isCreatingUser = isset($_GET['create']);
$editId         = $isCreatingUser ? '' : trim($_GET['edit'] ?? '');
$editingUser    = null;
if ($editId !== '') {
    $stmt = $pdo->prepare('SELECT * FROM NguoiDung WHERE MaNguoiDung = :id LIMIT 1');
    $stmt->execute(['id' => $editId]);
    $editingUser = $stmt->fetch();
}

$pageTitle = page_title('Quản lý người dùng');
$heading   = 'Quản lý người dùng';
include __DIR__ . '/../includes/header.php';
?>
<?php if (($editingUser || $isCreatingUser) && !$success && !$error): ?><script>document.body.dataset.autoOpenModal = 'accountFormModal';</script><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="row g-4 accounts-layout">
    <div class="col-12">
        <div class="panel accounts-list-panel">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
                <h2 class="h5 mb-0">Danh sách người dùng</h2>
                <a class="btn btn-primary" href="<?= base_url('admin/users.php?create=1') ?>"><i class="bi bi-plus-circle me-1"></i> Thêm mới</a>
            </div>
            <form method="get" action="" class="mb-3" id="userSearchForm">
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" id="userSearchInput" class="form-control" placeholder="Nhập mã, họ tên, đơn vị, email, sdt hoặc tên đăng nhập..." value="<?= htmlspecialchars($searchKeyword) ?>">
                    <?php if ($searchKeyword !== ''): ?>
                        <a href="<?= base_url('admin/users.php') ?>" class="btn btn-outline-secondary" title="Xóa tìm kiếm"><i class="bi bi-x-lg"></i></a>
                    <?php endif; ?>
                    <button class="btn btn-primary" type="submit">Tìm kiếm</button>
                </div>
            </form>
            <div class="table-responsive">
                <table class="table" data-page-size="10">
                    <thead>
                    <tr>
                        <th class="text-nowrap" style="width: 120px;">Mã người dùng</th>
                        <th style="min-width: 180px;">Họ tên</th>
                        <th style="min-width: 180px;">Email</th>
                        <th class="text-nowrap" style="width: 120px;">Số điện thoại</th>
                        <th class="text-nowrap" style="width: 130px;">Tên đăng nhập</th>
                        <th class="text-nowrap" style="width: 100px;">Mật khẩu</th>
                        <th class="text-nowrap" style="width: 110px;">Vai trò</th>
                        <th class="text-nowrap" style="width: 120px;">Trạng thái</th>
                        <th class="text-end text-nowrap action-cell" style="width: 90px;">Thao tác</th>
                    </tr>
                    </thead>
                    <tbody id="usersTableBody">
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td class="fw-bold text-nowrap"><?= htmlspecialchars($user['code']) ?></td>
                            <td class="fw-semibold text-nowrap"><?= htmlspecialchars($user['name']) ?></td>
                            <td class="text-nowrap"><?= htmlspecialchars($user['email']) ?></td>
                            <td class="text-nowrap"><?= htmlspecialchars($user['phone'] ?: '-') ?></td>
                            <td><code><?= htmlspecialchars($user['username']) ?></code></td>
                            <td><span class="text-muted">••••••••</span></td>
                            <td class="text-nowrap">
                                <?php if ($user['role_code'] === 'admin'): ?>
                                    <span class="badge bg-danger">Quản trị viên</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Người dùng</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-nowrap">
                                <?php if ((int) $user['status_raw'] === 1): ?>
                                    <span class="badge bg-success">Đang hoạt động</span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark">Ngưng áp dụng</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end action-cell">
                                <div class="action-buttons">
                                    <a class="btn btn-sm btn-outline-primary" href="?edit=<?= $user['id'] ?>" title="Sửa"><i class="bi bi-pencil"></i></a>
                                    <form method="post" class="d-inline" data-confirm-form="Bạn chắc chắn muốn xóa người dùng này?">
                                        <input type="hidden" name="action" value="delete_user">
                                        <input type="hidden" name="id" value="<?= $user['id'] ?>">
                                        <button class="btn btn-sm btn-outline-danger" type="submit" title="Xóa"><i class="bi bi-trash"></i></button>
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
            const deptCell  = row.cells[2]?.textContent || '';
            const emailCell = row.cells[3]?.textContent || '';
            const phoneCell = row.cells[4]?.textContent || '';
            const userCell  = row.cells[5]?.textContent || '';
            const textToMatch = normalizeText(codeCell + ' ' + nameCell + ' ' + deptCell + ' ' + emailCell + ' ' + phoneCell + ' ' + userCell);

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
});
</script>

<div class="modal fade management-form-modal" id="accountFormModal" tabindex="-1" aria-labelledby="accountFormModalLabel" aria-hidden="true" <?= ($editingUser || $isCreatingUser) ? 'data-auto-open-modal' : '' ?>>
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5" id="accountFormModalLabel"><?= $editingUser ? 'Cập nhật người dùng' : 'Tạo người dùng mới' ?></h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <div class="modal-body">
                <form method="post">
                    <input type="hidden" name="action" value="save_user">
                    <input type="hidden" name="id" value="<?= htmlspecialchars($editingUser['MaNguoiDung'] ?? '') ?>">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Mã người dùng <span class="text-danger">*</span></label>
                            <input class="form-control" name="ma_nguoi_dung" value="<?= htmlspecialchars($editingUser['MaNguoiDung'] ?? '') ?>" placeholder="VD: ND001 hoặc ND-ADMIN" required>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Họ tên <span class="text-danger">*</span></label>
                            <input class="form-control" name="ho_ten" value="<?= htmlspecialchars($editingUser['HoTen'] ?? '') ?>" placeholder="Nhập họ tên" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email <span class="text-danger">*</span></label>
                            <input class="form-control" type="email" name="email" value="<?= htmlspecialchars($editingUser['Email'] ?? '') ?>" placeholder="example@fbu.edu.vn" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Số điện thoại</label>
                            <input class="form-control" name="sdt" value="<?= htmlspecialchars($editingUser['SoDienThoai'] ?? ($editingUser['SDT'] ?? '')) ?>" placeholder="VD: 0912345678">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Tên đăng nhập <span class="text-danger">*</span></label>
                            <input class="form-control" name="ten_dang_nhap" value="<?= htmlspecialchars($editingUser['TenDangNhap'] ?? '') ?>" placeholder="Nhập tên đăng nhập" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><?= $editingUser ? 'Mật khẩu mới' : 'Mật khẩu' ?> <?= $editingUser ? '' : '<span class="text-danger">*</span>' ?></label>
                            <input class="form-control" name="password" type="password" placeholder="<?= $editingUser ? 'Để trống nếu giữ nguyên' : 'Nhập mật khẩu' ?>" <?= $editingUser ? '' : 'required' ?>>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Vai trò</label>
                            <select class="form-select" name="vai_tro">
                                <option value="admin" <?= ($editingUser['VaiTro'] ?? 'user') === 'admin' ? 'selected' : '' ?>>Quản trị viên</option>
                                <option value="user" <?= ($editingUser['VaiTro'] ?? 'user') === 'user' ? 'selected' : '' ?>>Người dùng</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Trạng thái</label>
                            <select class="form-select" name="trang_thai">
                                <option value="1" <?= (int) ($editingUser['TrangThai'] ?? 1) === 1 ? 'selected' : '' ?>>Đang hoạt động</option>
                                <option value="0" <?= (int) ($editingUser['TrangThai'] ?? 1) === 0 ? 'selected' : '' ?>>Ngưng áp dụng</option>
                            </select>
                        </div>
                    </div>
                    <div class="d-flex gap-2 justify-content-end mt-4">
                        <?php if ($editingUser): ?>
                            <a class="btn btn-outline-secondary" href="<?= base_url('admin/users.php') ?>">Hủy sửa</a>
                        <?php else: ?>
                            <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Hủy</button>
                        <?php endif; ?>
                        <button class="btn btn-primary" type="submit"><i class="bi bi-save me-1"></i> Lưu người dùng</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
