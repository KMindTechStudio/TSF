const themeToggles = document.querySelectorAll('[data-theme-toggle]');
const sidebarToggles = document.querySelectorAll('[data-sidebar-toggle]');
const appShell = document.querySelector('.app-shell');
const mobileSidebarQuery = window.matchMedia('(max-width: 640px)');

function isMobileSidebar() {
    return mobileSidebarQuery.matches;
}

function applyTheme(theme) {
    document.documentElement.dataset.theme = theme;
    localStorage.setItem('kiemdinh-theme', theme);

    const isDark = theme === 'dark';

    themeToggles.forEach((toggle) => {
        const icon = toggle.querySelector('i');

        if (icon) {
            icon.className = isDark ? 'bi bi-sun' : 'bi bi-moon-stars';
        }
    });
}

if (themeToggles.length) {
    applyTheme(localStorage.getItem('kiemdinh-theme') || 'light');

    themeToggles.forEach((toggle) => {
        toggle.addEventListener('click', () => {
            const nextTheme = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
            applyTheme(nextTheme);
        });
    });
}

function applySidebarState(isCollapsed, persist = !isMobileSidebar()) {
    if (!appShell) {
        return;
    }

    appShell.classList.toggle('sidebar-collapsed', isCollapsed);

    if (persist) {
        localStorage.setItem('kiemdinh-sidebar-collapsed', isCollapsed ? 'true' : 'false');
    }

    document.body.classList.toggle('mobile-sidebar-open', isMobileSidebar() && !isCollapsed);
    sidebarToggles.forEach((toggle) => {
        const icon = toggle.querySelector('i');
        toggle.setAttribute('aria-expanded', isCollapsed ? 'false' : 'true');

        if (toggle.classList.contains('mobile-sidebar-toggle')) {
            toggle.setAttribute('aria-label', isCollapsed ? 'Mở menu' : 'Đóng menu');
        }

        if (icon) {
            if (toggle.classList.contains('mobile-sidebar-toggle')) {
                icon.className = isCollapsed ? 'bi bi-list' : 'bi bi-x-lg';
            } else {
                icon.className = isCollapsed ? 'bi bi-layout-sidebar-inset-reverse' : 'bi bi-layout-sidebar-inset';
            }
        }
    });
}

if (sidebarToggles.length && appShell) {
    const storedSidebarCollapsed = localStorage.getItem('kiemdinh-sidebar-collapsed') === 'true';
    applySidebarState(isMobileSidebar() ? true : storedSidebarCollapsed, false);

    sidebarToggles.forEach((toggle) => {
        toggle.addEventListener('click', () => {
            applySidebarState(!appShell.classList.contains('sidebar-collapsed'));
        });
    });

    mobileSidebarQuery.addEventListener('change', () => {
        const desktopSidebarCollapsed = localStorage.getItem('kiemdinh-sidebar-collapsed') === 'true';
        applySidebarState(isMobileSidebar() ? true : desktopSidebarCollapsed, false);
    });

    document.addEventListener('click', (event) => {
        if (!isMobileSidebar() || appShell.classList.contains('sidebar-collapsed')) {
            return;
        }

        if (!event.target.closest('.sidebar') && !event.target.closest('[data-sidebar-toggle]')) {
            applySidebarState(true);
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && isMobileSidebar() && !appShell.classList.contains('sidebar-collapsed')) {
            applySidebarState(true);
        }
    });
}

const tableSizeButtons = document.querySelectorAll('[data-table-size]');
const compactTableSwitches = document.querySelectorAll('[data-compact-table]');

document.querySelectorAll('.status-update-form .status-select:not([disabled])').forEach((select) => {
    select.addEventListener('change', () => {
        select.form?.submit();
    });
});

function getStatusTone(value) {
    if (['approved', 'complete', 'active', 'active_set', 'success'].includes(value)) {
        return 'success';
    }

    if (['reviewing', 'need_update', 'warning'].includes(value)) {
        return 'warning';
    }

    if (['missing', 'locked', 'inactive', 'inactive_set', 'danger'].includes(value)) {
        return 'danger';
    }

    return 'secondary';
}

function closeStatusDropdowns(exceptDropdown = null) {
    document.querySelectorAll('.status-dropdown.show').forEach((dropdown) => {
        if (dropdown !== exceptDropdown) {
            dropdown.classList.remove('show');
            dropdown.querySelector('.status-dropdown-toggle')?.setAttribute('aria-expanded', 'false');
        }
    });
}

function enhanceStatusSelect(select) {
    if (select.dataset.enhancedStatus === 'true') {
        return;
    }

    select.dataset.enhancedStatus = 'true';

    const dropdown = document.createElement('div');
    dropdown.className = 'status-dropdown';
    select.parentNode.insertBefore(dropdown, select);
    dropdown.appendChild(select);
    select.classList.add('status-select-native-hidden');

    const toggle = document.createElement('button');
    toggle.type = 'button';
    toggle.className = 'status-dropdown-toggle status-select';
    toggle.setAttribute('aria-haspopup', 'listbox');
    toggle.setAttribute('aria-expanded', 'false');

    const menu = document.createElement('div');
    menu.className = 'status-dropdown-menu';
    menu.setAttribute('role', 'listbox');

    dropdown.append(toggle, menu);

    const syncToggleTone = () => {
        Array.from(toggle.classList)
            .filter((className) => className.startsWith('status-select-'))
            .forEach((className) => {
                if (className !== 'status-select') {
                    toggle.classList.remove(className);
                }
            });

        const tone = getStatusTone(select.value);
        toggle.classList.add(`status-select-${tone}`, `status-select-${select.value}`);
    };

    const render = () => {
        const selectedOption = select.options[select.selectedIndex] || select.options[0];
        if (selectedOption && !selectedOption.dataset.originalText) {
            selectedOption.dataset.originalText = selectedOption.textContent;
        }
        const selectedText = selectedOption?.dataset?.originalText || selectedOption?.textContent || '';
        const activeLanguage = document.documentElement.dataset.language || localStorage.getItem('kiemdinh-language') || 'vi';
        syncToggleTone();
        toggle.innerHTML = `<span>${translatePhrase(selectedText, activeLanguage)}</span><i class="bi bi-chevron-down"></i>`;
        menu.innerHTML = '';

        Array.from(select.options).forEach((option) => {
            if (!option.dataset.originalText) {
                option.dataset.originalText = option.textContent;
            }
            const rawText = option.dataset.originalText;
            const tone = getStatusTone(option.value);
            const item = document.createElement('button');
            item.type = 'button';
            item.className = `status-dropdown-option status-dropdown-option-${tone} status-dropdown-option-${option.value}`;
            item.setAttribute('role', 'option');
            item.setAttribute('aria-selected', option.selected ? 'true' : 'false');
            item.innerHTML = `<span>${translatePhrase(rawText, activeLanguage)}</span><i class="bi bi-check-lg"></i>`;
            item.classList.toggle('active', option.selected);

            item.addEventListener('click', () => {
                closeStatusDropdowns();

                if (select.value === option.value) {
                    return;
                }

                select.value = option.value;
                render();
                select.dispatchEvent(new Event('change', { bubbles: true }));
            });

            menu.appendChild(item);
        });
    };

    dropdown.renderStatusDropdown = render;

    toggle.addEventListener('click', (event) => {
        event.stopPropagation();
        const shouldOpen = !dropdown.classList.contains('show');
        closeStatusDropdowns(dropdown);
        dropdown.classList.toggle('show', shouldOpen);
        toggle.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');
    });

    render();
}

function initStatusDropdowns() {
    document.querySelectorAll('.status-update-form .status-select:not([disabled])').forEach(enhanceStatusSelect);
}

function refreshStatusDropdowns() {
    document.querySelectorAll('.status-dropdown').forEach((dropdown) => {
        dropdown.renderStatusDropdown?.();
    });
}

document.addEventListener('click', (event) => {
    if (!event.target.closest('.status-dropdown')) {
        closeStatusDropdowns();
    }
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        closeStatusDropdowns();
    }
});

function buildPaginationItems(currentPage, totalPages) {
    if (totalPages <= 7) {
        return Array.from({ length: totalPages }, (_, index) => index + 1);
    }

    const items = [1];
    const start = Math.max(2, currentPage - 1);
    const end = Math.min(totalPages - 1, currentPage + 1);

    if (start > 2) {
        items.push('dots-start');
    }

    for (let page = start; page <= end; page += 1) {
        items.push(page);
    }

    if (end < totalPages - 1) {
        items.push('dots-end');
    }

    items.push(totalPages);
    return items;
}

function initTablePagination() {
    document.querySelectorAll('.table-responsive > table.table').forEach((table) => {
        if (table.dataset.paginated === 'true') {
            return;
        }

        const tbody = table.tBodies[0];

        if (!tbody) {
            return;
        }

        const rows = Array.from(tbody.rows);
        const configuredPageSize = Number(table.dataset.pageSize || 0);

        if (!rows.length) {
            return;
        }

        table.dataset.paginated = 'true';
        let currentPage = 1;
        const tableWrapper = table.closest('.table-responsive');
        tableWrapper?.classList.add('is-paginated-table');
        const pagination = document.createElement('nav');
        pagination.className = 'table-pagination';
        pagination.setAttribute('aria-label', 'Phân trang bảng dữ liệu');
        tableWrapper.insertAdjacentElement('afterend', pagination);

        const firstRowHeight = Math.max(48, Math.ceil(rows[0]?.getBoundingClientRect().height || 56));

        const getPageSize = () => {
            if (configuredPageSize > 0) {
                return configuredPageSize;
            }

            const headerHeight = Math.ceil(table.tHead?.getBoundingClientRect().height || 44);
            const wrapperHeight = Math.ceil(tableWrapper?.clientHeight || 0);
            const availableHeight = wrapperHeight - headerHeight;

            if (availableHeight <= firstRowHeight) {
                return 4;
            }

            return Math.max(4, Math.floor(availableHeight / firstRowHeight));
        };

        const render = () => {
            tbody.querySelectorAll('.pagination-filler-row').forEach((row) => row.remove());

            const activeRows = rows.filter((row) => row.dataset.filteredOut !== 'true' && !row.classList.contains('column-filter-empty-row'));
            const emptyFilterRow = rows.find((row) => row.classList.contains('column-filter-empty-row'));
            const pageSize = getPageSize();
            const totalPages = Math.max(1, Math.ceil(activeRows.length / pageSize));
            currentPage = Math.min(currentPage, totalPages);
            const startIndex = (currentPage - 1) * pageSize;
            const endIndex = startIndex + pageSize;
            const activeLanguage = document.documentElement.dataset.language || localStorage.getItem('kiemdinh-language') || 'vi';
            const headerHeight = Math.ceil(table.tHead?.getBoundingClientRect().height || 44);
            const stableRowHeight = Math.max(firstRowHeight, Number(table.dataset.rowHeight || 0));
            let visibleRows = 0;

            tableWrapper.style.minHeight = `${headerHeight + (pageSize * stableRowHeight)}px`;

            rows.forEach((row) => {
                row.hidden = true;
            });

            activeRows.forEach((row, index) => {
                row.hidden = index < startIndex || index >= endIndex;
                if (!row.hidden) {
                    visibleRows += 1;
                }
            });

            if (emptyFilterRow) {
                emptyFilterRow.hidden = activeRows.length > 0;
            }

            if (activeRows.length > 0 && totalPages > 1 && visibleRows < pageSize) {
                const columnCount = Math.max(1, table.tHead?.rows[0]?.cells.length || rows[0]?.cells.length || 1);
                const fillersNeeded = pageSize - visibleRows;

                for (let index = 0; index < fillersNeeded; index += 1) {
                    const fillerRow = document.createElement('tr');
                    const fillerCell = document.createElement('td');
                    fillerRow.className = 'pagination-filler-row';
                    fillerCell.colSpan = columnCount;
                    fillerCell.innerHTML = '&nbsp;';
                    fillerRow.appendChild(fillerCell);
                    tbody.appendChild(fillerRow);
                }
            }

            pagination.innerHTML = '';

            const createButton = (label, disabled, onClick, isActive = false) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'table-pagination-button';
                button.textContent = label;
                button.disabled = disabled;
                button.classList.toggle('active', isActive);
                button.addEventListener('click', onClick);
                return button;
            };

            if (totalPages > 1) {
                pagination.appendChild(createButton('‹', currentPage === 1, () => {
                    currentPage = Math.max(1, currentPage - 1);
                    render();
                }));

                buildPaginationItems(currentPage, totalPages).forEach((item) => {
                    if (typeof item === 'string') {
                        const dots = document.createElement('span');
                        dots.className = 'table-pagination-info';
                        dots.textContent = '...';
                        pagination.appendChild(dots);
                        return;
                    }

                    pagination.appendChild(createButton(String(item), false, () => {
                        currentPage = item;
                        render();
                    }, item === currentPage));
                });

                pagination.appendChild(createButton('›', currentPage === totalPages, () => {
                    currentPage = Math.min(totalPages, currentPage + 1);
                    render();
                }));
            }

            const info = document.createElement('span');
            info.className = 'table-pagination-info';
            info.textContent = activeLanguage === 'en'
                ? `Page ${currentPage}/${totalPages}`
                : `Trang ${currentPage}/${totalPages}`;
            pagination.appendChild(info);
        };

        pagination.renderPagination = render;
        render();
    });
}

function refreshTablePaginations() {
    document.querySelectorAll('.table-pagination').forEach((pagination) => {
        pagination.renderPagination?.();
    });
}

let tablePaginationResizeTimer;
window.addEventListener('resize', () => {
    window.clearTimeout(tablePaginationResizeTimer);
    tablePaginationResizeTimer = window.setTimeout(refreshTablePaginations, 120);
});

function applyTableSize(size) {
    const safeSize = ['small', 'medium', 'large'].includes(size) ? size : 'medium';
    document.documentElement.dataset.tableSize = safeSize;
    localStorage.setItem('kiemdinh-table-size', safeSize);

    tableSizeButtons.forEach((button) => {
        button.classList.toggle('active', button.dataset.tableSize === safeSize);
    });
}

function applyTableDensity(isCompact) {
    document.documentElement.dataset.tableDensity = isCompact ? 'compact' : 'comfortable';
    localStorage.setItem('kiemdinh-table-compact', isCompact ? 'true' : 'false');

    compactTableSwitches.forEach((input) => {
        input.checked = isCompact;
    });
}

if (tableSizeButtons.length) {
    applyTableSize(localStorage.getItem('kiemdinh-table-size') || 'medium');

    tableSizeButtons.forEach((button) => {
        button.addEventListener('click', () => applyTableSize(button.dataset.tableSize));
    });
}

if (compactTableSwitches.length) {
    applyTableDensity(localStorage.getItem('kiemdinh-table-compact') === 'true');

    compactTableSwitches.forEach((input) => {
        input.addEventListener('change', () => applyTableDensity(input.checked));
    });
}

const languageButtons = document.querySelectorAll('[data-language-option]');
const languageToggle = document.querySelector('[data-language-toggle]');
const languagePanel = document.querySelector('[data-language-panel]');
const translations = {
    en: {}
};

const placeholderTranslations = {
    en: {}
};

Object.assign(translations.en, {
    'Đăng nhập': 'Sign In',
    'Đăng nhập hệ thống': 'System Sign In',
    'Đăng nhập thành công': 'Sign In Successful',
    'Đang chuyển vào hệ thống...': 'Redirecting to the system...',
    'Đề án thạc sĩ': 'Master Thesis',
    'Cơ sở dữ liệu minh chứng phục vụ kiểm định chất lượng CTĐT ngành CNTT': 'Evidence Database for IT Program Quality Accreditation',
    'Chuẩn hóa lưu trữ, tra cứu, thống kê và khai thác minh chứng cho Trường Đại học Tài chính - Ngân hàng Hà Nội.': 'Standardize storage, search, reporting, and access to evidence for Hanoi Financial and Banking University.',
    'Sử dụng tài khoản trong bảng': 'Use an account in the',
    'để truy cập kho minh chứng.': 'table to access the evidence repository.',
    'Tên đăng nhập': 'Username',
    'Mật khẩu': 'Password',
    'Ghi nhớ đăng nhập': 'Remember me',
    'Quên mật khẩu?': 'Forgot password?',
    'Tài khoản mẫu:': 'Sample accounts:',
    'hoặc': 'or',
    'Quên mật khẩu': 'Forgot Password',
    'Nhập email đã đăng ký để nhận mã xác minh.': 'Enter your registered email to receive a verification code.',
    'Nhập mã xác minh đã được gửi về email.': 'Enter the verification code sent to your email.',
    'Mã xác minh hợp lệ. Vui lòng tạo mật khẩu mới.': 'Verification code accepted. Please create a new password.',
    'Hoàn tất đặt lại mật khẩu.': 'Password reset completed.',
    'Email đã đăng ký': 'Registered Email',
    'Gửi mã xác minh': 'Send Verification Code',
    'Mã xác minh': 'Verification Code',
    'Mã xác minh có hiệu lực trong 5 phút.': 'The verification code is valid for 5 minutes.',
    'Gửi lại mã': 'Resend Code',
    'Xác minh': 'Verify',
    'Mật khẩu mới': 'New Password',
    'Nhập lại mật khẩu mới': 'Confirm New Password',
    'Cập nhật mật khẩu': 'Update Password',
    'Quay lại đăng nhập': 'Back to Sign In',
    'Đóng': 'Close',
    'MINH CHỨNG KIỂM ĐỊNH': 'ACCREDITATION EVIDENCE',
    'CTĐT ngành CNTT - FBU': 'IT Program - FBU',
    'Trường Đại học Tài chính - Ngân hàng Hà Nội': 'Hanoi Financial and Banking University',
    'Copyright 2026 Trường Đại học Tài chính Ngân hàng Hà Nội - Viện Công nghệ thông tin': 'Copyright 2026 Hanoi Financial and Banking University - Institute of Information Technology',
    'Quản trị hệ thống': 'System Administration',
    'Tổng quan': 'Overview',
    'Quản lý tiêu chuẩn': 'Manage Standards',
    'Quản lý tiêu chí': 'Manage Criteria',
    'Quản lý minh chứng': 'Manage Evidence',
    'Quản lý tài khoản': 'Manage Accounts',
    'Quản lý đơn vị': 'Manage Departments',
    'Danh sách đơn vị': 'Department List',
    'Mã đơn vị': 'Department Code',
    'Tên đơn vị': 'Department Name',
    'Thêm đơn vị mới': 'Add New Department',
    'Thêm đơn vị': 'Add Department',
    'Sửa đơn vị': 'Edit Department',
    'Lưu thay đổi': 'Save Changes',
    'Khoa, phòng ban, bộ môn tham gia quy trình kiểm định.': 'Faculties, departments, and units participating in accreditation.',
    'Thống kê': 'Reports',
    'Khai thác dữ liệu': 'Data Access',
    'Tra cứu minh chứng': 'Search Evidence',
    'Tổng quan cơ sở dữ liệu minh chứng': 'Evidence Database Overview',
    'Quản lý tài khoản và phân quyền': 'Account and Permission Management',
    'Danh sách người dùng': 'User List',
    'Mã người dùng': 'User Code',
    'Thêm tài khoản': 'Add Account',
    'Cấp tài khoản': 'Create Account',
    'Sửa tài khoản': 'Edit Account',
    'Họ tên': 'Full Name',
    'Tên đăng nhập': 'Username',
    'Email': 'Email',
    'Mật khẩu': 'Password',
    'Vai trò': 'Role',
    'Đơn vị': 'Department',
    'Trạng thái': 'Status',
    'Thao tác': 'Actions',
    'Mã': 'Code',
    'Tải': 'Download',
    'Cập nhật': 'Updated',
    'Đơn vị phụ trách': 'Responsible Dept.',
    'Tra cứu nhanh': 'Quick Search',
    'Minh chứng mới cập nhật': 'Recently Updated Evidence',
    'Lưu tài khoản': 'Save Account',
    'Hủy sửa': 'Cancel Edit',
    'Quản trị viên': 'Administrator',
    'Cán bộ kiểm định': 'Accreditation Staff',
    'Người dùng tra cứu': 'Search User',
    'Đang hoạt động': 'Active',
    'Hoạt động': 'Active',
    'Tạm khóa': 'Locked',
    'Khóa': 'Locked',
    'Quản lý hồ sơ minh chứng': 'Evidence Record Management',
    'Danh mục minh chứng': 'Evidence List',
    'Upload minh chứng': 'Upload Evidence',
    'Thêm mới': 'Add New',
    'Thông tin minh chứng': 'Evidence Information',
    'Mã minh chứng': 'Evidence Code',
    'Tên minh chứng': 'Evidence Name',
    'Mô tả': 'Description',
    'Năm học': 'Academic Year',
    'Ngày ban hành': 'Issue Date',
    'Đơn vị cung cấp': 'Provider',
    'Đơn vị phụ trách': 'Responsible Unit',
    'Gắn tiêu chí': 'Linked Criteria',
    'File đính kèm': 'Attachment',
    'Phiên bản': 'Version',
    'Phiên bản hiện tại:': 'Current version:',
    'Lọc mã': 'Filter code',
    'Lọc tên': 'Filter name',
    'Lọc TC': 'Filter standard',
    'Lọc tiêu chí': 'Filter criteria',
    'Lọc tiêu chuẩn': 'Filter standard',
    'Lọc năm': 'Filter year',
    'Lọc năm học': 'Filter academic year',
    'Lọc file': 'Filter file',
    'Lọc v': 'Filter version',
    'Lọc phiên bản': 'Filter version',
    'Không tìm thấy minh chứng phù hợp với bộ lọc.': 'No evidence matches the filters.',
    'Chọn tệp mới nếu cần cập nhật phiên bản minh chứng.': 'Choose a new file if you need to update the evidence version.',
    'Dung lượng tối đa:': 'Maximum size:',
    'Lưu minh chứng': 'Save Evidence',
    'Đã duyệt': 'Approved',
    'Chờ rà soát': 'Under Review',
    'Cần bổ sung': 'Needs Supplement',
    'Đủ minh chứng': 'Sufficient Evidence',
    'Thiếu minh chứng': 'Missing Evidence',
    'Tiêu chí đủ minh chứng': 'Criteria with Sufficient Evidence',
    'Tiêu chuẩn': 'Standard',
    'Tiêu chí': 'Criteria',
    'Minh chứng': 'Evidence',
    'Tỷ lệ đáp ứng': 'Compliance Rate',
    'Bộ tiêu chuẩn đang áp dụng': 'Active standard set',
    'Đã phân công đơn vị phụ trách': 'Assigned to responsible units',
    'Tệp đã được đưa vào hệ thống': 'Files added to the system',
    'Theo tiêu chí đủ minh chứng': 'Based on sufficient criteria',
    'Tình trạng theo tiêu chuẩn': 'Status by Standard',
    'Theo dõi mức độ đầy đủ của hồ sơ minh chứng.': 'Track the completeness of evidence records.',
    'Xem thống kê': 'View Reports',
    'Tên tiêu chuẩn': 'Standard Name',
    'Nhật ký gần đây': 'Recent Activity',
    'Quản lý tiêu chuẩn kiểm định': 'Manage Accreditation Standards',
    'Quản lý tiêu chí đánh giá': 'Manage Evaluation Criteria',
    'Quản lý minh chứng': 'Manage Evidence',
    'Quản lý tài khoản và phân quyền': 'Manage Accounts and Permissions',
    'Quản lý đơn vị': 'Manage Departments',
    'Thống kê phục vụ kiểm định': 'Accreditation Reports',
    'Tra cứu và khai thác minh chứng': 'Search and Access Evidence',
    'Xuất Excel': 'Export Excel',
    'Tất cả tiêu chuẩn': 'All Standards',
    'Tất cả trạng thái': 'All Statuses',
    'Mục tiêu và chuẩn đầu ra của chương trình đào tạo': 'Program Objectives and Learning Outcomes',
    'Bản mô tả chương trình đào tạo': 'Program Specification',
    'Cấu trúc và nội dung chương trình dạy học': 'Curriculum Structure and Content',
    'Phương pháp tiếp cận trong dạy và học': 'Teaching and Learning Approach',
    'Đánh giá kết quả học tập của người học': 'Learner Assessment',
    'Mục tiêu của CTĐT được xác định rõ ràng': 'Program objectives are clearly defined',
    'Chuẩn đầu ra phản ánh yêu cầu của các bên liên quan': 'Learning outcomes reflect stakeholder requirements',
    'Bản mô tả CTĐT đầy đủ thông tin cần thiết': 'Program specification provides full necessary information',
    'Nội dung học phần cập nhật theo định hướng nghề nghiệp': 'Course content is updated towards career orientation',
    'Hoạt động dạy học thúc đẩy năng lực tự học': 'Teaching activities promote self-learning capacity',
    'Quy trình đánh giá kết quả học tập được công bố': 'Assessment process of learning outcomes is published',
    'Bộ môn Phần mềm': 'Software Engineering Department',
    'Khoa Công nghệ thông tin': 'Faculty of Information Technology',
    'Phòng Đảm bảo chất lượng': 'Quality Assurance Department',
    'Phòng Đào tạo': 'Academic Affairs Department',
    'Phòng Khảo thí': 'Testing & Assessment Department',
    'Chưa phân công': 'Unassigned',
    'Chưa xác định': 'Unassigned',
    'Chưa gắn': 'Not Linked',
    'Quyết định ban hành mục tiêu và chuẩn đầu ra ngành CNTT': 'Decision on Program Objectives and Learning Outcomes for IT',
    'Bản mô tả chương trình đào tạo ngành CNTT': 'IT Program Specification Document',
    'Đề cương chi tiết các học phần chuyên ngành': 'Detailed Syllabi of Specialized Courses',
    'Kế hoạch đổi mới phương pháp dạy học': 'Plan for Teaching Method Innovation',
    'Quy chế đánh giá học phần và ma trận điểm': 'Course Assessment Regulations and Grading Matrix',
    'Số lượng minh chứng theo tiêu chuẩn': 'Evidence Count by Standard',
    'Tiêu chí cần ưu tiên': 'Priority Criteria',
    'Xuất PDF': 'Export PDF',
    'Tra cứu và khai thác minh chứng': 'Search and Access Evidence',
    'Từ khóa': 'Keyword',
    'Loại file': 'File Type',
    'Cập nhật thông tin': 'Update Profile',
    'Cập nhật thông tin cá nhân thành công.': 'Profile updated successfully.',
    'Thông tin hệ thống': 'System Information',
    'Trạng thái tài khoản': 'Account Status',
    'Chương trình đào tạo': 'Training Program',
    'Ảnh đại diện': 'Avatar',
    'Chọn ảnh từ thiết bị. Dung lượng tối đa 2MB.': 'Choose an image from device. Max size 2MB.',
    'Cập nhật mật khẩu': 'Update Password',
    'Cập nhật mật khẩu thành công.': 'Password updated successfully.',
    'Mật khẩu hiện tại không chính xác.': 'Current password is incorrect.',
    'Mật khẩu mới phải có ít nhất 6 ký tự.': 'New password must be at least 6 characters.',
    'Mật khẩu xác nhận chưa trùng khớp.': 'Confirmation password does not match.',
    'Lưu ý bảo mật': 'Security Note',
    'Mật khẩu nên có tối thiểu 8 ký tự, bao gồm chữ hoa, chữ thường, số và ký tự đặc biệt. Không dùng lại mật khẩu của email hoặc các hệ thống cá nhân.': 'Password should be at least 8 characters long, including uppercase, lowercase, numbers, and special characters. Do not reuse email or personal system passwords.',
    'minh chứng phù hợp': 'matching evidence',
    'Định dạng': 'Format',
    'cập nhật': 'updated',
    'Có hồ sơ cơ bản đáp ứng': 'Sufficient basic evidence records',
    'Cần cập nhật hoặc rà soát thêm': 'Needs update or further review',
    'Ưu tiên xử lý trước đánh giá': 'Priority before evaluation',
    'minh chứng': 'evidence',
    'Tất cả đơn vị': 'All Departments',
    'Mã/Tên minh chứng': 'Code/Evidence Name',
    'Mã/Evidence Name': 'Code/Evidence Name',
    'Ngưng áp dụng': 'Inactive',
    'Đang áp dụng': 'Active',
    'Đang hoạt động': 'Active',
    'Khóa': 'Locked',
    'Danh sách tiêu chuẩn': 'Standard List',
    'Danh sách tiêu chí': 'Criteria List',
    'Phụ lục minh chứng phục vụ đoàn đánh giá ngoài': 'Appendix of Evidence for External Assessment Team',
    'Báo cáo tự đánh giá chương trình đào tạo ngành CNTT': 'Self-Assessment Report for IT Training Program',
    'Biên bản kiểm tra định kỳ cơ sở dữ liệu minh chứng': 'Periodic Audit Minutes of Evidence Database',
    'Danh mục phân quyền khai thác kho minh chứng': 'Evidence Repository Permission & Access Catalog',
    'Kế hoạch thu thập minh chứng kiểm định CTĐT CNTT': 'Evidence Collection Plan for IT Accreditation',
    'Biên bản rà soát quy trình chấm thi và phúc khảo': 'Review Minutes of Grading & Re-examination Process',
    'Bảng tổng hợp kết quả đánh giá học phần': 'Summary Sheet of Course Assessment Results',
    'Quy định xây dựng ma trận đề thi học phần': 'Regulations on Course Exam Matrix Development',
    'Danh sách học phần áp dụng blended learning': 'List of Courses Applying Blended Learning',
    'Báo cáo triển khai lớp học dự án ngành CNTT': 'Report on Project-based Learning Implementation in IT',
    'Tất cả đơn vị': 'All Departments',
    'Mã, tên minh chứng, tiêu chí': 'Code, evidence name, criteria',
    'Phụ lục minh chứng phục vụ đoàn đánh giá ngoài Quality Assurance Department': 'Appendix of Evidence for External Assessment Team',
    'Báo cáo tự đánh giá chương trình đào tạo ngành CNTT Quality Assurance Department': 'Self-Assessment Report for IT Training Program',
    'Biên bản kiểm tra định kỳ cơ sở dữ liệu minh chứng Quality Assurance Department': 'Periodic Audit Minutes of Evidence Database',
    'Danh mục phân quyền khai thác kho minh chứng Quality Assurance Department': 'Evidence Repository Permission & Access Catalog',
    'Kế hoạch thu thập minh chứng kiểm định CTĐT CNTT Quality Assurance Department': 'Evidence Collection Plan for IT Accreditation',
    'Biên bản rà soát quy trình chấm thi và phúc khảo Testing & Assessment Department': 'Review Minutes of Grading & Re-examination Process',
    'Bảng tổng hợp kết quả đánh giá học phần Testing & Assessment Department': 'Summary Sheet of Course Assessment Results',
    'Quy định xây dựng ma trận đề thi học phần Testing & Assessment Department': 'Regulations on Course Exam Matrix Development',
    'Danh sách học phần áp dụng blended learning Software Engineering Department': 'List of Courses Applying Blended Learning',
    'Báo cáo triển khai lớp học dự án ngành CNTT Faculty of Information Technology': 'Report on Project-based Learning Implementation in IT',
    'Sửa tiêu chuẩn': 'Edit Standard',
    'Thêm tiêu chuẩn': 'Add Standard',
    'Sửa tiêu chí': 'Edit Criterion',
    'Thêm tiêu chí': 'Add Criterion',
    'Sửa đơn vị': 'Edit Department',
    'Thêm đơn vị': 'Add Department',
    'Thêm đơn vị mới': 'Add New Department',
    'Bộ tiêu chuẩn': 'Standard Set',
    'Bộ tiêu chuẩn đánh giá chất lượng chương trình đào tạo - 2025': 'Program Accreditation Standard Set - 2025',
    'Mã tiêu chuẩn': 'Standard Code',
    'Nội dung tiêu chuẩn': 'Standard Content',
    'Mã tiêu chí': 'Criterion Code',
    'Thuộc tiêu chuẩn': 'Belongs to Standard',
    'Đơn vị phụ trách': 'Responsible Department',
    'Nội dung tiêu chí': 'Criterion Content',
    'Bạn chắc chắn muốn xóa tiêu chuẩn này?': 'Are you sure you want to delete this standard?',
    'Bạn chắc chắn muốn xóa tiêu chí này?': 'Are you sure you want to delete this criterion?',
    'Bạn chắc chắn muốn xóa đơn vị này?': 'Are you sure you want to delete this department?',
    'Bạn chắc chắn muốn mở/khóa tài khoản này?': 'Are you sure you want to lock/unlock this account?',
    'Bạn chắc chắn muốn xóa tài khoản này?': 'Are you sure you want to delete this account?',
    'Lưu tiêu chuẩn': 'Save Standard',
    'Lưu tiêu chí': 'Save Criterion',
    'Lưu đơn vị': 'Save Department',
    'Lưu thay đổi': 'Save Changes',
    'Hủy sửa': 'Cancel Edit',
    'Tùy chỉnh nhanh giao diện và thông tin vận hành.': 'Quickly customize the interface and operating information.',
    'Giao diện': 'Interface',
    'Chế độ màu': 'Color Mode',
    'Đổi sáng/tối': 'Toggle Light/Dark',
    'Ẩn/hiện': 'Hide/Show',
    'Hiển thị bảng': 'Table Display',
    'Cỡ chữ dữ liệu': 'Data Font Size',
    'Nhỏ': 'Small',
    'Vừa': 'Medium',
    'Lớn': 'Large',
    'Bảng gọn hơn': 'Compact Table',
    'Thông tin hệ thống': 'System Information',
    'Trường': 'School',
    'Chương trình': 'Program',
    'Mã ngành': 'Program Code',
    'Chu kỳ kiểm định': 'Accreditation Cycle',
    'Giới hạn upload': 'Upload Limit',
    'Ngôn ngữ': 'Language',
    'Chọn ngôn ngữ hiển thị hệ thống.': 'Choose the system display language.',
    'Tiếng Việt': 'Vietnamese',
    'Ngôn ngữ mặc định': 'Default language',
    'Quy định bảo mật và khai thác dữ liệu minh chứng.': 'Privacy and evidence data usage policy.',
    'Bảo mật dữ liệu': 'Data Privacy',
    'Quyền truy cập': 'Access Rights',
    'Ghi nhận lượt tải': 'Download Logging',
    'Quy định upload': 'Upload Rules',
    'Đã hiểu': 'Got It',
    'Xác nhận thao tác': 'Confirm Action',
    'Bạn chắc chắn muốn thực hiện thao tác này?': 'Are you sure you want to perform this action?',
    'Hủy': 'Cancel',
    'Đồng ý': 'Confirm',
    'Bạn có chắc muốn đăng xuất?': 'Are you sure you want to sign out?',
    'Phiên làm việc hiện tại sẽ được kết thúc.': 'Your current session will be ended.',
    'Ở lại': 'Stay'
});

Object.assign(placeholderTranslations.en, {
    'admin': 'admin',
    '••••••••': '••••••••',
    'Nhập 6 số': 'Enter 6 digits',
    'Tìm mã minh chứng, tiêu chí...': 'Search evidence code, criteria...',
    'Mã minh chứng, tên minh chứng': 'Evidence code, evidence name',
    'VD: MC.01.01.01': 'Ex: MC.01.01.01',
    'VD: TC06': 'Ex: TC06',
    'VD: 6.1': 'Ex: 6.1',
    'Lọc': 'Filter',
    'Tìm kiếm': 'Search',
    'Tìm': 'Search',
    'Tất cả': 'All',
    'Trang': 'Page',
    'Trang 1/1': 'Page 1/1',
    'Trang 1/3': 'Page 1/3',
    'Nhập mã hoặc tên tiêu chí': 'Enter criterion code or name',
    'Lọc mã': 'Filter code',
    'Lọc tên': 'Filter name',
    'Lọc TC': 'Filter standard',
    'Lọc tiêu chí': 'Filter criteria',
    'Lọc tiêu chuẩn': 'Filter standard',
    'Lọc năm': 'Filter year',
    'Lọc năm học': 'Filter academic year',
    'Lọc file': 'Filter file',
    'Lọc v': 'Filter version',
    'Lọc phiên bản': 'Filter version',
    'Không có tệp nào được chọn': 'No file selected'
});

function compactText(value) {
    return value.replace(/\s+/g, ' ').trim();
}

function normalizeSearchText(value) {
    return value
        .toString()
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/đ/g, 'd')
        .replace(/\s+/g, ' ')
        .trim();
}

function translatePhrase(value, language) {
    if (language === 'vi' || !value) {
        return value;
    }

    const clean = compactText(value);
    if (translations.en[clean]) {
        return value.replace(clean, translations.en[clean]);
    }

    const entries = Object.entries(translations.en).sort((a, b) => b[0].length - a[0].length);
    let translatedValue = value;

    entries.forEach(([source, target]) => {
        if (translatedValue.includes(source)) {
            translatedValue = translatedValue.split(source).join(target);
        }
    });

    return translatedValue;
}

function translateTextNode(node, language) {
    if (!node.nodeValue || compactText(node.nodeValue) === '') {
        return;
    }

    if (!node.originalText) {
        node.originalText = node.nodeValue;
    }

    if (language === 'vi') {
        node.nodeValue = node.originalText;
        return;
    }

    const originalClean = compactText(node.originalText);
    const exactMatch = translations[language]?.[originalClean];

    if (exactMatch) {
        node.nodeValue = node.originalText.replace(originalClean, exactMatch);
    } else {
        node.nodeValue = translatePhrase(node.originalText, language);
    }
}

function translateElementAttribute(element, attributeName, language) {
    const currentValue = element.getAttribute(attributeName);

    if (!currentValue || compactText(currentValue) === '') {
        return;
    }

    const originalKey = `original${attributeName.replace(/(^|-)([a-z])/g, (_, __, letter) => letter.toUpperCase())}`;

    if (!element.dataset[originalKey]) {
        element.dataset[originalKey] = currentValue;
    }

    const originalValue = element.dataset[originalKey];
    element.setAttribute(attributeName, language === 'vi'
        ? originalValue
        : translatePhrase(originalValue, language));
}

function applyLanguage(language) {
    const safeLanguage = language === 'en' ? 'en' : 'vi';
    document.documentElement.lang = safeLanguage;
    document.documentElement.dataset.language = safeLanguage;
    localStorage.setItem('kiemdinh-language', safeLanguage);

    if (!document.documentElement.dataset.originalTitle) {
        document.documentElement.dataset.originalTitle = document.title;
    }
    document.title = translatePhrase(document.documentElement.dataset.originalTitle, safeLanguage);

    const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT, {
        acceptNode(node) {
            const tagName = node.parentElement?.tagName;
            return ['SCRIPT', 'STYLE'].includes(tagName)
                ? NodeFilter.FILTER_REJECT
                : NodeFilter.FILTER_ACCEPT;
        }
    });

    const nodes = [];
    while (walker.nextNode()) {
        nodes.push(walker.currentNode);
    }
    nodes.forEach((node) => translateTextNode(node, safeLanguage));

    document.querySelectorAll('input[placeholder], textarea[placeholder]').forEach((field) => {
        if (!field.dataset.originalPlaceholder) {
            field.dataset.originalPlaceholder = field.getAttribute('placeholder') || '';
        }

        const original = compactText(field.dataset.originalPlaceholder);
        field.setAttribute('placeholder', safeLanguage === 'vi'
            ? field.dataset.originalPlaceholder
            : (placeholderTranslations.en[original] || field.dataset.originalPlaceholder));
    });

    document.querySelectorAll('[title]').forEach((element) => {
        translateElementAttribute(element, 'title', safeLanguage);
    });

    document.querySelectorAll('[aria-label]').forEach((element) => {
        translateElementAttribute(element, 'aria-label', safeLanguage);
    });

    // Translate all <option> elements (TreeWalker misses these in some browsers)
    document.querySelectorAll('select option').forEach((option) => {
        if (!option.dataset.originalText) {
            option.dataset.originalText = option.textContent;
        }
        const raw = option.dataset.originalText;
        const cleaned = compactText(raw);
        option.textContent = safeLanguage === 'vi'
            ? raw
            : (translations.en[cleaned] || placeholderTranslations.en[cleaned] || raw);
    });

    // Translate <button> elements — handles buttons that contain <i> icons
    // by only touching direct text nodes within the button
    document.querySelectorAll('button').forEach((btn) => {
        if (!btn.dataset.originalButtonText) {
            // collect concatenated text from direct text nodes only
            const textParts = Array.from(btn.childNodes)
                .filter(n => n.nodeType === Node.TEXT_NODE)
                .map(n => n.nodeValue);
            const combined = compactText(textParts.join(''));
            if (combined) {
                btn.dataset.originalButtonText = combined;
                btn.dataset.originalButtonNodes = textParts.length;
            }
        }
        const original = btn.dataset.originalButtonText;
        if (!original || compactText(original) === '') return;

        const translated = safeLanguage === 'vi'
            ? original
            : (translations.en[compactText(original)] || placeholderTranslations.en[compactText(original)] || original);

        // replace text content of direct text nodes only (preserve icon children)
        let textNodeIndex = 0;
        btn.childNodes.forEach((n) => {
            if (n.nodeType === Node.TEXT_NODE && compactText(n.nodeValue) !== '') {
                n.nodeValue = textNodeIndex === 0 ? (n.nodeValue.trimStart() ? ' ' + translated : '') : '';
                textNodeIndex++;
            }
        });
    });

    languageButtons.forEach((button) => {
        button.classList.toggle('active', button.dataset.languageOption === safeLanguage);
    });

    refreshStatusDropdowns();
    refreshTablePaginations();
}

applyLanguage(localStorage.getItem('kiemdinh-language') || 'vi');
initStatusDropdowns();
initColumnFilters();
initCriteriaSearch();
initTablePagination();
refreshStatusDropdowns();
refreshTablePaginations();

languageButtons.forEach((button) => {
    button.addEventListener('click', () => {
        applyLanguage(button.dataset.languageOption);
        languagePanel?.classList.remove('show');
        languageToggle?.setAttribute('aria-expanded', 'false');
    });
});

if (languageToggle && languagePanel) {
    languageToggle.addEventListener('click', (event) => {
        event.stopPropagation();
        const isOpen = languagePanel.classList.toggle('show');
        languageToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });

    languagePanel.addEventListener('click', (event) => {
        event.stopPropagation();
    });

    document.addEventListener('click', () => {
        languagePanel.classList.remove('show');
        languageToggle.setAttribute('aria-expanded', 'false');
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            languagePanel.classList.remove('show');
            languageToggle.setAttribute('aria-expanded', 'false');
        }
    });
}

const actionConfirmElement = document.getElementById('actionConfirmModal');
const actionConfirmMessage = actionConfirmElement?.querySelector('[data-confirm-message]');
const actionConfirmAccept = actionConfirmElement?.querySelector('[data-confirm-accept]');
const actionConfirmModal = actionConfirmElement && window.bootstrap
    ? new bootstrap.Modal(actionConfirmElement)
    : null;
let pendingConfirmAction = null;

function showActionConfirm(message, onAccept) {
    if (!actionConfirmModal || !actionConfirmMessage || !actionConfirmAccept) {
        if (window.confirm(message)) {
            onAccept();
        }
        return;
    }

    actionConfirmMessage.textContent = message;
    pendingConfirmAction = onAccept;
    actionConfirmModal.show();
}

if (actionConfirmAccept) {
    actionConfirmAccept.addEventListener('click', () => {
        const action = pendingConfirmAction;
        pendingConfirmAction = null;
        actionConfirmModal.hide();

        if (action) {
            action();
        }
    });
}

function openAutoManagementModal() {
    const targetedModalId = document.body?.dataset.autoOpenModal;
    if (targetedModalId && window.bootstrap) {
        const targetedModal = document.getElementById(targetedModalId);
        if (targetedModal) {
            bootstrap.Modal.getOrCreateInstance(targetedModal).show();
            return;
        }
    }

    document.querySelectorAll('[data-auto-open-modal]').forEach((element) => {
        if (window.bootstrap) {
            bootstrap.Modal.getOrCreateInstance(element).show();
        }
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', openAutoManagementModal);
} else {
    openAutoManagementModal();
}

document.querySelectorAll('[data-confirm]').forEach((button) => {
    button.addEventListener('click', (event) => {
        event.preventDefault();
        showActionConfirm(button.dataset.confirm || 'Báº¡n cháº¯c cháº¯n muá»‘n thá»±c hiá»‡n thao tÃ¡c nÃ y?', () => {
            window.location.href = button.href;
        });
    });
});

document.querySelectorAll('[data-confirm-form]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        if (form.dataset.confirmed === 'true') {
            form.dataset.confirmed = 'false';
            return;
        }

        event.preventDefault();
        showActionConfirm(form.dataset.confirmForm || 'Báº¡n cháº¯c cháº¯n muá»‘n thá»±c hiá»‡n thao tÃ¡c nÃ y?', () => {
            form.dataset.confirmed = 'true';
            form.requestSubmit();
        });
    });
});

function initColumnFilters() {
    document.querySelectorAll('table[data-column-filters]').forEach((table) => {
        const filters = Array.from(table.querySelectorAll('[data-column-filter]'));
        const toggles = Array.from(table.querySelectorAll('[data-column-filter-toggle]'));
        const tbody = table.tBodies[0];

        if (!filters.length || !tbody) {
            return;
        }

        const rows = Array.from(tbody.rows).filter((row) => !row.classList.contains('column-filter-empty-row'));

        const applyFilters = () => {
            const activeFilters = filters
                .map((filter) => ({
                    index: Number(filter.dataset.columnFilter),
                    value: normalizeSearchText(filter.value || '')
                }))
                .filter((filter) => filter.value !== '');

            rows.forEach((row) => {
                const isMatch = activeFilters.every((filter) => {
                    const cellText = normalizeSearchText(row.cells[filter.index]?.textContent || '');
                    return cellText.includes(filter.value);
                });

                row.dataset.filteredOut = isMatch ? 'false' : 'true';
            });

            filters.forEach((filter) => {
                const toggle = filter.closest('.column-filter-head')?.querySelector('[data-column-filter-toggle]');
                toggle?.classList.toggle('is-active', filter.value.trim() !== '');
            });

            const pagination = table.closest('.table-responsive')?.nextElementSibling;
            if (pagination?.renderPagination) {
                pagination.renderPagination();
            }
        };

        filters.forEach((filter) => {
            filter.addEventListener('input', applyFilters);
            filter.addEventListener('click', (event) => event.stopPropagation());
            filter.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    filter.closest('.column-filter-head')?.classList.remove('show');
                }
            });
        });

        toggles.forEach((toggle) => {
            toggle.addEventListener('click', (event) => {
                event.stopPropagation();
                const head = toggle.closest('.column-filter-head');
                const shouldShow = !head?.classList.contains('show');

                table.querySelectorAll('.column-filter-head.show').forEach((openHead) => {
                    openHead.classList.remove('show');
                });

                if (head && shouldShow) {
                    head.classList.add('show');
                    head.querySelector('[data-column-filter]')?.focus();
                }
            });
        });
    });
}

function initCriteriaSearch() {
    const searchInput = document.querySelector('[data-criteria-search]');
    const criteriaSelect = document.querySelector('[data-criteria-select]');

    if (!searchInput || !criteriaSelect) {
        return;
    }

    searchInput.addEventListener('input', () => {
        const keyword = normalizeSearchText(searchInput.value || '');

        Array.from(criteriaSelect.options).forEach((option) => {
            const isSelected = option.selected;
            const isMatch = keyword === '' || normalizeSearchText(option.textContent || '').includes(keyword);
            option.hidden = !isMatch && !isSelected;
        });
    });
}

document.addEventListener('click', () => {
    document.querySelectorAll('.column-filter-head.show').forEach((head) => {
        head.classList.remove('show');
    });
});

const evidenceFileInput = document.querySelector('#evidenceFile');
const fileSizeAlert = document.querySelector('#fileSizeAlert');

if (evidenceFileInput && fileSizeAlert) {
    const form = evidenceFileInput.closest('form');
    const submitButton = form?.querySelector('button[type="submit"]');
    const maxMb = Number(evidenceFileInput.dataset.maxMb || 10);
    const maxBytes = maxMb * 1024 * 1024;

    evidenceFileInput.addEventListener('change', () => {
        const file = evidenceFileInput.files[0];
        const isTooLarge = file && file.size > maxBytes;

        fileSizeAlert.classList.toggle('d-none', !isTooLarge);
        if (submitButton) {
            submitButton.disabled = Boolean(isTooLarge);
        }
    });
}

const easeOutCubic = (value) => 1 - Math.pow(1 - value, 3);

function animateCount(element) {
    const target = Number(element.dataset.countTo || 0);
    const duration = Number(element.dataset.duration || 900);
    const startTime = performance.now();

    const tick = (now) => {
        const progress = Math.min((now - startTime) / duration, 1);
        const current = Math.round(target * easeOutCubic(progress));
        element.textContent = current.toLocaleString('vi-VN');

        if (progress < 1) {
            requestAnimationFrame(tick);
        }
    };

    requestAnimationFrame(tick);
}

function animateProgress(element) {
    const target = Math.max(0, Math.min(100, Number(element.dataset.progressTo || 0)));
    requestAnimationFrame(() => {
        element.style.width = `${target}%`;
    });
}

const animatedElements = document.querySelectorAll('.count-up, .progress-animate');

if (animatedElements.length) {
    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting || entry.target.dataset.animated === 'true') {
                return;
            }

            entry.target.dataset.animated = 'true';

            if (entry.target.classList.contains('count-up')) {
                animateCount(entry.target);
            }

            if (entry.target.classList.contains('progress-animate')) {
                animateProgress(entry.target);
            }
        });
    }, { threshold: 0.35 });

    animatedElements.forEach((element) => observer.observe(element));
}
