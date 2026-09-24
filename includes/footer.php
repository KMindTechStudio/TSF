        </section>
        <footer class="main-footer">
            <i class="bi bi-c-circle"></i>
            <span>Copyright 2026 Trường Đại học Tài chính Ngân hàng Hà Nội - Viện Công nghệ thông tin</span>
        </footer>
    </main>
</div>
<div class="offcanvas offcanvas-end settings-panel" tabindex="-1" id="settingsPanel" aria-labelledby="settingsPanelLabel">
    <div class="offcanvas-header">
        <div>
            <h2 class="h5 mb-1" id="settingsPanelLabel">Cài đặt hệ thống</h2>
            <p class="text-secondary mb-0 small">Tuỳ chỉnh nhanh giao diện và thông tin vận hành.</p>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Đóng"></button>
    </div>
    <div class="offcanvas-body">
        <div class="settings-section">
            <h3>Giao diện</h3>
            <div class="setting-row">
                <span>Chế độ màu</span>
                <button class="btn btn-outline-primary btn-sm" type="button" data-theme-toggle>
                    <i class="bi bi-moon-stars me-1"></i> Đổi sáng/tối
                </button>
            </div>
            <div class="setting-row">
                <span>Sidebar</span>
                <button class="btn btn-outline-primary btn-sm" type="button" data-sidebar-toggle>
                    <i class="bi bi-layout-sidebar-inset me-1"></i> Ẩn/hiện
                </button>
            </div>
        </div>

        <div class="settings-section">
            <h3>Hiển thị bảng</h3>
            <div class="setting-control">
                <label class="form-label">Cỡ chữ dữ liệu</label>
                <div class="btn-group w-100" role="group" aria-label="Cỡ chữ bảng">
                    <button class="btn btn-outline-secondary" type="button" data-table-size="small">Nhỏ</button>
                    <button class="btn btn-outline-secondary" type="button" data-table-size="medium">Vừa</button>
                    <button class="btn btn-outline-secondary" type="button" data-table-size="large">Lớn</button>
                </div>
            </div>
            <div class="form-check form-switch mt-3">
                <input class="form-check-input" type="checkbox" role="switch" id="compactTableSwitch" data-compact-table>
                <label class="form-check-label" for="compactTableSwitch">Bảng gọn hơn</label>
            </div>
        </div>

        <div class="settings-section">
            <h3>Thông tin hệ thống</h3>
            <dl class="settings-info">
                <dt>Trường</dt>
                <dd><?= htmlspecialchars($trainingProgram['school'] ?? 'Trường Đại học Tài chính - Ngân hàng Hà Nội') ?></dd>
                <dt>Chương trình</dt>
                <dd><?= htmlspecialchars($trainingProgram['name'] ?? 'Công nghệ thông tin') ?></dd>
                <dt>Mã ngành</dt>
                <dd><?= htmlspecialchars($trainingProgram['code'] ?? '7480201') ?></dd>
                <dt>Chu kỳ kiểm định</dt>
                <dd><?= htmlspecialchars($trainingProgram['cycle'] ?? 'Chu kỳ kiểm định 2026-2031') ?></dd>
                <dt>Giới hạn upload</dt>
                <dd>100MB/tệp minh chứng</dd>
            </dl>
        </div>
    </div>
</div>

<div class="modal fade policy-modal" id="privacyPolicyModal" tabindex="-1" aria-labelledby="privacyPolicyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h2 class="modal-title h5" id="privacyPolicyModalLabel">Privacy & Policy</h2>
                    <p class="text-secondary mb-0 small">Quy định bảo mật và khai thác dữ liệu minh chứng.</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <div class="modal-body">
                <div class="policy-grid">
                    <section>
                        <i class="bi bi-shield-lock"></i>
                        <h3>Bảo mật dữ liệu</h3>
                        <p>Minh chứng chỉ phục vụ kiểm định chất lượng chương trình đào tạo, không chia sẻ ra ngoài phạm vi được phân quyền.</p>
                    </section>
                    <section>
                        <i class="bi bi-person-check"></i>
                        <h3>Quyền truy cập</h3>
                        <p>Quản trị viên quản lý toàn hệ thống; người dùng chỉ được đăng nhập, tìm kiếm và tải về minh chứng được phép.</p>
                    </section>
                    <section>
                        <i class="bi bi-download"></i>
                        <h3>Ghi nhận lượt tải</h3>
                        <p>Mỗi lượt tải minh chứng được hệ thống ghi nhận gồm người tải, thời gian, tệp tải và địa chỉ truy cập để phục vụ truy vết.</p>
                    </section>
                    <section>
                        <i class="bi bi-cloud-arrow-up"></i>
                        <h3>Quy định upload</h3>
                        <p>Tệp upload phải đúng định dạng, đúng tiêu chí, đúng nguồn cung cấp và không vượt quá giới hạn dung lượng của hệ thống.</p>
                    </section>
                </div>
                <div class="alert alert-info mb-0">
                    <i class="bi bi-info-circle me-1"></i>
                    Mọi thắc mắc về dữ liệu minh chứng liên hệ Khoa Công nghệ thông tin hoặc bộ phận đảm bảo chất lượng.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Đã hiểu</button>
            </div>
        </div>
    </div>
</div>
<div class="modal fade action-confirm-modal" id="actionConfirmModal" tabindex="-1" aria-labelledby="actionConfirmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-body text-center">
                <div class="action-confirm-icon">
                    <i class="bi bi-question-lg"></i>
                </div>
                <h2 class="h5 mb-2" id="actionConfirmModalLabel">Xác nhận thao tác</h2>
                <p class="text-secondary mb-4" data-confirm-message>Bạn chắc chắn muốn thực hiện thao tác này?</p>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary flex-fill" data-bs-dismiss="modal">Hủy</button>
                    <button type="button" class="btn btn-danger flex-fill" data-confirm-accept>Đồng ý</button>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="modal fade logout-modal" id="logoutConfirmModal" tabindex="-1" aria-labelledby="logoutConfirmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-body text-center">
                <div class="logout-modal-icon">
                    <i class="bi bi-box-arrow-right"></i>
                </div>
                <h2 class="h5 mb-2" id="logoutConfirmModalLabel">Bạn có chắc muốn đăng xuất?</h2>
                <p class="text-secondary mb-4">Phiên làm việc hiện tại sẽ được kết thúc.</p>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary flex-fill" data-bs-dismiss="modal">Ở lại</button>
                    <a class="btn btn-danger flex-fill" href="<?= base_url('auth/logout.php') ?>">Đăng xuất</a>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= base_url('assets/js/app.js?v=16') ?>"></script>
</body>
</html>
