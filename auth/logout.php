<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/helpers.php';

clear_login_token();

$_SESSION = [];
session_destroy();

header('Location: login.php');
exit;
?>
