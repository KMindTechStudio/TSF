<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/data.php';
$pageTitle = $pageTitle ?? $appName;
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="icon" type="image/png" href="<?= base_url('assets/images/fbu-logo.png?v=2') ?>">
    <link rel="shortcut icon" type="image/png" href="<?= base_url('assets/images/fbu-logo.png?v=2') ?>">
    <link rel="apple-touch-icon" href="<?= base_url('assets/images/fbu-logo.png?v=2') ?>">
    <script>
        (function () {
            var theme = localStorage.getItem('kiemdinh-theme') || 'light';
            document.documentElement.dataset.theme = theme;
        })();
    </script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= base_url('assets/css/app.css?v=24') ?>" rel="stylesheet">
</head>
<body>
<div class="app-shell">
    <?php include __DIR__ . '/sidebar.php'; ?>
    <main class="main-content">
        <header class="topbar">
            <button class="mobile-sidebar-toggle" type="button" data-sidebar-toggle aria-label="Mở menu">
                <i class="bi bi-list"></i>
            </button>
            <div class="topbar-title">
                <p class="eyebrow mb-1"><?= htmlspecialchars($trainingProgram['school']) ?></p>
                <h1 class="h4 mb-0"><?= htmlspecialchars($heading ?? $pageTitle) ?></h1>
            </div>
            <div class="topbar-actions">
                <form class="search-box" action="<?= base_url('user/search.php') ?>" method="get">
                    <i class="bi bi-search"></i>
                    <input type="search" name="q" value="<?= htmlspecialchars($_GET['q'] ?? '') ?>" placeholder="Tìm mã minh chứng, tiêu chí...">
                </form>

                <div class="dropdown user-menu">
                    <button class="user-chip" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <?= avatar_html($currentUser['avatar'] ?? null, $currentUser['name']) ?>
                        <span class="user-chip-text">
                            <strong><?= htmlspecialchars($currentUser['name']) ?></strong>
                            <small><?= htmlspecialchars($roles[$currentUser['role']] ?? 'Người dùng') ?></small>
                        </span>
                        <i class="bi bi-chevron-down user-chip-icon"></i>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end user-dropdown">
                        <div class="user-dropdown-head">
                            <?= avatar_html($currentUser['avatar'] ?? null, $currentUser['name']) ?>
                            <span>
                                <strong><?= htmlspecialchars($currentUser['name']) ?></strong>
                                <small><?= htmlspecialchars($currentUser['department']) ?></small>
                            </span>
                        </div>
                        <a class="dropdown-item" href="<?= base_url('user/profile.php') ?>">
                            <i class="bi bi-person-badge"></i>
                            Thông tin cá nhân
                        </a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item text-danger" href="#" data-bs-toggle="modal" data-bs-target="#logoutConfirmModal">
                            <i class="bi bi-box-arrow-right"></i>
                            Đăng xuất
                        </a>
                    </div>
                </div>
            </div>
        </header>
        <section class="content-wrap">
