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
            $id           = (int) ($_POST['id'] ?? 0);
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
                'id'       => $id,
            ]);
            if ($check->fetch()) {
                throw new RuntimeException('Tên đăng nhập hoặc email đã tồn tại.');
            }

            if ($id > 0) {
                if ($password !== '') {
                    $stmt = $pdo->prepare("
                        UPDATE NguoiDung
                        SET HoTen = :full_name,
                            DonViCongTac = :dept,
                            Email = :email,
                            SDT = :phone,
                            TenDangNhap = :username,
                            MatKhau = :password_hash,
                            VaiTro = :role,
                            TrangThai = :status
                        WHERE MaNguoiDung = :id
                    ");
                    $stmt->execute([
                        'full_name'     => $fullName,
                        'dept'          => $department,
                        'email'         => $email,
                        'phone'         => $phone,
                        'username'      => $username,
                        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                        'role'          => $role,
                        'status'        => $status,
                        'id'            => $id,
                    ]);
                    log_activity('cap_nhat', 'nguoi_dung', $id, $fullName . ' (' . $username . ')');
                    $success = 'Cập nhật thông tin và mật khẩu tài khoản thành công.';
                } else {
                    $stmt = $pdo->prepare("
                        UPDATE NguoiDung
                        SET HoTen = :full_name,
                            DonViCongTac = :dept,
                            Email = :email,
                            SDT = :phone,
                            TenDangNhap = :username,
                            VaiTro = :role,
                            TrangThai = :status
                        WHERE MaNguoiDung = :id
                    ");
                    $stmt->execute([
                        'full_name'     => $fullName,
                        'dept'          => $department,
                        'email'         => $email,
                        'phone'         => $phone,
                        'username'      => $username,
                        'role'          => $role,
                        'status'        => $status,
                        'id'            => $id,
                    ]);
                    log_activity('cap_nhat', 'nguoi_dung', $id, $fullName . ' (' . $username . ')');
                    $success = 'Cập nhật thông tin tài khoản thành công.';
                }
            } else {
                if ($password === '') {
                    throw new RuntimeException('Vui lòng nhập mật khẩu cho tài khoản mới.');
                }

                $stmt = $pdo->prepare("
                    INSERT INTO NguoiDung (HoTen, DonViCongTac, Email, SDT, TenDangNhap, MatKhau, VaiTro, TrangThai)
                    VALUES (:full_name, :dept, :email, :phone, :username, :password_hash, :role, :status)
                ");
                $stmt->execute([
                    'full_name'     => $fullName,
                    'dept'          => $department,
                    'email'         => $email,
                    'phone'         => $phone,
                    'username'      => $username,
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                    'role'          => $role,
                    'status'        => $status,
                ]);
                $newId = (int) $pdo->lastInsertId();
                log_activity('them_moi', 'nguoi_dung', $newId, $fullName . ' (' . $username . ')');
                $success = 'Tạo tài khoản mới thành công.';
            }
        }

        if ($action === 'delete_user') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id === (int) $currentUserId) {
                throw new RuntimeException('Không thể xóa tài khoản đang đăng nhập.');
            }
            $stmtName = $pdo->prepare('SELECT HoTen, TenDangNhap FROM NguoiDung WHERE MaNguoiDung = :id');
            $stmtName->execute(['id' => $id]);
            $uRow = $stmtName->fetch();
            $uName = $uRow ? ($uRow['HoTen'] . ' (' . $uRow['TenDangNhap'] . ')') : ('#' . $id);

            $stmt = $pdo->prepare('DELETE FROM NguoiDung WHERE MaNguoiDung = :id');
            $stmt->execute(['id' => $id]);
            log_activity('xoa', 'nguoi_dung', $id, $uName);
            $success = 'Xóa tài khoản thành công.';
        }

        if ($action === 'toggle_user') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id === (int) $currentUserId) {
                throw new RuntimeException('Không thể khóa tài khoản đang đăng nhập.');
            }
            $stmtName = $pdo->prepare('SELECT HoTen, TenDangNhap FROM NguoiDung WHERE MaNguoiDung = :id');
            $stmtName->execute(['id' => $id]);
            $uRow = $stmtName->fetch();
            $uName = $uRow ? ($uRow['HoTen'] . ' (' . $uRow['TenDangNhap'] . ')') : ('#' . $id);

            $stmt = $pdo->prepare("UPDATE NguoiDung SET TrangThai = IF(TrangThai = 1, 0, 1) WHERE MaNguoiDung = :id");
            $stmt->execute(['id' => $id]);
            log_activity('cap_nhat_trang_thai', 'nguoi_dung', $id, $uName);
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
$editId         = $isCreatingUser ? 0 : (int) ($_GET['edit'] ?? 0);
$editingUser    = null;
if ($editId > 0) {
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
                        <th>Mã người dùng</th>
                        <th>Họ tên</th>
                        <th>Đơn vị công tác</th>
                        <th>Email</th>
                        <th>Số điện thoại</th>
                        <th>Tên đăng nhập</th>
                        <th>Mật khẩu</th>
                        <th>Vai trò</th>
                        <th>Trạng thái</th>
                        <th class="text-end">Thao tác</th>
                    </tr>
                    </thead>
                    <tbody id="usersTableBody">
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td class="fw-bold text-nowrap"><?= htmlspecialchars($user['code']) ?></td>
                            <td class="fw-semibold text-nowrap"><?= htmlspecialchars($user['name']) ?></td>
                            <td><?= htmlspecialchars($user['department'] ?: '-') ?></td>
                            <td><?= htmlspecialchars($user['email']) ?></td>
                            <td><?= htmlspecialchars($user['phone'] ?: '-') ?></td>
                            <td><code><?= htmlspecialchars($user['username']) ?></code></td>
                            <td><span class="text-muted">••••••••</span></td>
                            <td>
                                <?php if ($user['role_code'] === 'admin'): ?>
                                    <span class="badge bg-danger">Quản trị viên</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Người dùng</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ((int) $user['status_raw'] === 1): ?>
                                    <span class="badge bg-success">Đang hoạt động</span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark">Ngưng áp dụng</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end action-cell">
                                <div class="action-buttons">
                                    <a class="btn btn-sm btn-outline-primary" href="?edit=<?= $user['id'] ?>" title="Sửa tài khoản"><i class="bi bi-pencil"></i></a>
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
                        <td colspan="10" class="text-center text-secondary py-4">Không có dữ liệu được ghi</td>
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
                    <input type="hidden" name="id" value="<?= (int) ($editingUser['MaNguoiDung'] ?? 0) ?>">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Họ tên <span class="text-danger">*</span></label>
                            <input class="form-control" name="ho_ten" value="<?= htmlspecialchars($editingUser['HoTen'] ?? '') ?>" placeholder="Nhập họ tên" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Đơn vị công tác</label>
                            <input class="form-control" name="don_vi_cong_tac" value="<?= htmlspecialchars($editingUser['DonViCongTac'] ?? '') ?>" placeholder="VD: Khoa CNTT">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email <span class="text-danger">*</span></label>
                            <input class="form-control" type="email" name="email" value="<?= htmlspecialchars($editingUser['Email'] ?? '') ?>" placeholder="example@fbu.edu.vn" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Số điện thoại</label>
                            <input class="form-control" name="sdt" value="<?= htmlspecialchars($editingUser['SDT'] ?? '') ?>" placeholder="VD: 0912345678">
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
