<aside class="sidebar">
    <a class="brand" href="<?= base_url(current_role() === 'viewer' ? 'user/search.php' : 'admin/dashboard.php') ?>">
        <span class="brand-mark"><img src="<?= base_url('assets/images/fbu-logo.png') ?>" alt="FBU"></span>
        <span>
            <strong>MINH CHỨNG KIỂM ĐỊNH</strong>
            <small>CTĐT ngành CNTT - FBU</small>
        </span>
    </a>

    <?php if (user_can_manage_accreditation()): ?>
        <nav class="nav-group">
            <p>Quản trị hệ thống</p>
            <a class="<?= is_active('dashboard.php') ?>" href="<?= base_url('admin/dashboard.php') ?>"><i class="bi bi-speedometer2"></i> Tổng quan</a>
            <a class="<?= is_active('standards.php') ?>" href="<?= base_url('admin/standards.php') ?>"><i class="bi bi-diagram-3"></i> Quản lý tiêu chuẩn</a>
            <a class="<?= is_active('criteria.php') ?>" href="<?= base_url('admin/criteria.php') ?>"><i class="bi bi-list-check"></i> Quản lý tiêu chí</a>
            <a class="<?= is_active('evidences.php') ?>" href="<?= base_url('admin/evidences.php') ?>"><i class="bi bi-folder2-open"></i> Quản lý minh chứng</a>
            <?php if (user_can_manage_accounts()): ?>
                <a class="<?= is_active('departments.php') ?>" href="<?= base_url('admin/departments.php') ?>"><i class="bi bi-building"></i> Quản lý đơn vị</a>
                <a class="<?= is_active('users.php') ?>" href="<?= base_url('admin/users.php') ?>"><i class="bi bi-people"></i> Quản lý tài khoản</a>
            <?php endif; ?>
            <a class="<?= is_active('reports.php') ?>" href="<?= base_url('admin/reports.php') ?>"><i class="bi bi-bar-chart"></i> Thống kê</a>
        </nav>
    <?php endif; ?>

    <nav class="nav-group">
        <p>Khai thác dữ liệu</p>
        <a class="<?= is_active('search.php') ?>" href="<?= base_url('user/search.php') ?>"><i class="bi bi-search"></i> Tra cứu minh chứng</a>
    </nav>

    <div class="sidebar-footer">
        <div class="sidebar-tools">
        <button class="sidebar-icon-button theme-toggle" type="button" data-theme-toggle aria-label="Đổi giao diện sáng tối" title="Dark/Light mode">
            <i class="bi bi-moon-stars"></i>
        </button>
        <button class="sidebar-icon-button sidebar-collapse-toggle" type="button" data-sidebar-toggle aria-label="Ẩn/hiện sidebar" title="Ẩn/hiện sidebar">
            <i class="bi bi-layout-sidebar-inset"></i>
        </button>
        <button class="sidebar-icon-button sidebar-language-toggle" type="button" data-language-toggle aria-expanded="false" aria-controls="languagePanel" aria-label="Đổi ngôn ngữ" title="Language">
            <i class="bi bi-translate"></i>
        </button>
        <button class="sidebar-icon-button sidebar-settings-toggle" type="button" data-bs-toggle="offcanvas" data-bs-target="#settingsPanel" aria-controls="settingsPanel" aria-label="Cài đặt" title="Cài đặt">
            <i class="bi bi-gear"></i>
        </button>
        <button class="sidebar-icon-button sidebar-policy-toggle" type="button" data-bs-toggle="modal" data-bs-target="#privacyPolicyModal" aria-label="Privacy &amp; Policy" title="Privacy &amp; Policy">
            <i class="bi bi-question-circle"></i>
        </button>
        <div class="language-panel" id="languagePanel" data-language-panel>
            <button class="language-panel-option" type="button" data-language-option="vi">
                <span>VI</span>
                <strong>Tiếng Việt</strong>
                <i class="bi bi-check-lg"></i>
            </button>
            <button class="language-panel-option" type="button" data-language-option="en">
                <span>EN</span>
                <strong>English</strong>
                <i class="bi bi-check-lg"></i>
            </button>
        </div>
        </div>
    </div>
</aside>
