<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/helpers.php';

log_activity('dang_xuat', 'he_thong', 0, 'Đăng xuất khỏi hệ thống');

clear_login_token();

$_SESSION = [];
session_destroy();

header('Location: login.php');
exit;
?>
