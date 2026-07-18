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

    if (['missing', 'locked', 'inactive_set', 'danger'].includes(value)) {
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
        const activeLanguage = document.documentElement.dataset.language || localStorage.getItem('kiemdinh-language') || 'vi';
        syncToggleTone();
        toggle.innerHTML = `<span>${translatePhrase(selectedOption?.textContent || '', activeLanguage)}</span><i class="bi bi-chevron-down"></i>`;
        menu.innerHTML = '';

        Array.from(select.options).forEach((option) => {
            const tone = getStatusTone(option.value);
            const item = document.createElement('button');
            item.type = 'button';
            item.className = `status-dropdown-option status-dropdown-option-${tone} status-dropdown-option-${option.value}`;
            item.setAttribute('role', 'option');
            item.setAttribute('aria-selected', option.selected ? 'true' : 'false');
            item.innerHTML = `<span>${translatePhrase(option.textContent, activeLanguage)}</span><i class="bi bi-check-lg"></i>`;
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

            const pageSize = getPageSize();
            const totalPages = Math.max(1, Math.ceil(rows.length / pageSize));
            currentPage = Math.min(currentPage, totalPages);
            const startIndex = (currentPage - 1) * pageSize;
            const endIndex = startIndex + pageSize;
            const activeLanguage = document.documentElement.dataset.language || localStorage.getItem('kiemdinh-language') || 'vi';
            const headerHeight = Math.ceil(table.tHead?.getBoundingClientRect().height || 44);
            const stableRowHeight = Math.max(firstRowHeight, Number(table.dataset.rowHeight || 0));
            let visibleRows = 0;

            tableWrapper.style.minHeight = `${headerHeight + (pageSize * stableRowHeight)}px`;

            rows.forEach((row, index) => {
                row.hidden = index < startIndex || index >= endIndex;
                if (!row.hidden) {
                    visibleRows += 1;
                }
            });

            if (totalPages > 1 && visibleRows < pageSize) {
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
            info.textContent = `${translatePhrase('Trang', activeLanguage)} ${currentPage}/${totalPages}`;
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
    en: {
        'CSDL minh chá»©ng': 'Evidence Database',
        'CSDL minh chá»©ng kiá»ƒm Ä‘á»‹nh': 'Accreditation Evidence DB',
        'Minh chá»©ng kiá»ƒm Ä‘á»‹nh': 'Accreditation Evidence',
        'MINH CHá»¨NG KIá»‚M Äá»ŠNH': 'ACCREDITATION EVIDENCE',
        'CTÄT ngÃ nh CNTT - FBU': 'IT Program - FBU',
        'Há»‡ thá»‘ng CSDL minh chá»©ng kiá»ƒm Ä‘á»‹nh': 'Accreditation Evidence Database System',
        'Há»‡ thá»‘ng CSDL minh chá»©ng kiá»ƒm Ä‘á»‹nh cháº¥t lÆ°á»£ng CTÄT ngÃ nh CNTT': 'IT Program Quality Accreditation Evidence Database System',
        'Copyright 2026 TrÆ°á»ng Äáº¡i há»c TÃ i chÃ­nh NgÃ¢n hÃ ng HÃ  Ná»™i - Viá»‡n CÃ´ng nghá»‡ thÃ´ng tin': 'Copyright 2026 Hanoi Financial and Banking University - Institute of Information Technology',
        'Kiá»ƒm Ä‘á»‹nh CTÄT CNTT': 'IT Program Accreditation',
        'TrÆ°á»ng Äáº¡i há»c TÃ i chÃ­nh - NgÃ¢n hÃ ng HÃ  Ná»™i': 'Hanoi Financial and Banking University',
        'Tá»•ng quan cÆ¡ sá»Ÿ dá»¯ liá»‡u minh chá»©ng': 'Evidence Database Overview',
        'Quáº£n trá»‹ há»‡ thá»‘ng': 'System Administration',
        'Tá»•ng quan': 'Overview',
        'Quáº£n lÃ½ tiÃªu chuáº©n': 'Manage Standards',
        'Quáº£n lÃ½ tiÃªu chÃ­': 'Manage Criteria',
        'Quáº£n lÃ½ minh chá»©ng': 'Manage Evidence',
        'Quáº£n lÃ½ tÃ i khoáº£n': 'Manage Accounts',
        'Thá»‘ng kÃª': 'Reports',
        'Khai thÃ¡c dá»¯ liá»‡u': 'Data Access',
        'Tra cá»©u minh chá»©ng': 'Search Evidence',
        'Tra cá»©u vÃ  khai thÃ¡c minh chá»©ng': 'Search and Access Evidence',
        'ThÃ´ng tin cÃ¡ nhÃ¢n': 'Profile',
        'Äá»•i máº­t kháº©u': 'Change Password',
        'ÄÄƒng xuáº¥t': 'Sign Out',
        'CÃ i Ä‘áº·t há»‡ thá»‘ng': 'System Settings',
        'Tuá»³ chá»‰nh nhanh giao diá»‡n vÃ  thÃ´ng tin váº­n hÃ nh.': 'Quickly customize the interface and operating information.',
        'Giao diá»‡n': 'Interface',
        'Cháº¿ Ä‘á»™ mÃ u': 'Color Mode',
        'Äá»•i sÃ¡ng/tá»‘i': 'Toggle Light/Dark',
        'Sidebar': 'Sidebar',
        'áº¨n/hiá»‡n': 'Hide/Show',
        'Hiá»ƒn thá»‹ báº£ng': 'Table Display',
        'Cá»¡ chá»¯ dá»¯ liá»‡u': 'Data Font Size',
        'Nhá»': 'Small',
        'Vá»«a': 'Medium',
        'Lá»›n': 'Large',
        'Báº£ng gá»n hÆ¡n': 'Compact Table',
        'ThÃ´ng tin há»‡ thá»‘ng': 'System Information',
        'TrÆ°á»ng': 'School',
        'ChÆ°Æ¡ng trÃ¬nh': 'Program',
        'MÃ£ ngÃ nh': 'Program Code',
        'Chu ká»³ kiá»ƒm Ä‘á»‹nh': 'Accreditation Cycle',
        'Giá»›i háº¡n upload': 'Upload Limit',
        '10MB/tá»‡p minh chá»©ng': '10MB/evidence file',
        'NgÃ´n ngá»¯': 'Language',
        'Chá»n ngÃ´n ngá»¯ hiá»ƒn thá»‹ há»‡ thá»‘ng.': 'Choose the system display language.',
        'Tiáº¿ng Viá»‡t': 'Vietnamese',
        'NgÃ´n ngá»¯ máº·c Ä‘á»‹nh': 'Default language',
        'Privacy & Policy': 'Privacy & Policy',
        'Quy Ä‘á»‹nh báº£o máº­t vÃ  khai thÃ¡c dá»¯ liá»‡u minh chá»©ng.': 'Privacy and evidence data usage policy.',
        'Báº£o máº­t dá»¯ liá»‡u': 'Data Privacy',
        'Minh chá»©ng chá»‰ phá»¥c vá»¥ kiá»ƒm Ä‘á»‹nh cháº¥t lÆ°á»£ng chÆ°Æ¡ng trÃ¬nh Ä‘Ã o táº¡o, khÃ´ng chia sáº» ra ngoÃ i pháº¡m vi Ä‘Æ°á»£c phÃ¢n quyá»n.': 'Evidence is used only for program accreditation and is not shared outside the authorized scope.',
        'Quyá»n truy cáº­p': 'Access Rights',
        'Quáº£n trá»‹ viÃªn quáº£n lÃ½ toÃ n há»‡ thá»‘ng; ngÆ°á»i dÃ¹ng chá»‰ Ä‘Æ°á»£c Ä‘Äƒng nháº­p, tÃ¬m kiáº¿m vÃ  táº£i vá» minh chá»©ng Ä‘Æ°á»£c phÃ©p.': 'Administrators manage the whole system; users may only sign in, search, and download permitted evidence.',
        'Ghi nháº­n lÆ°á»£t táº£i': 'Download Logging',
        'Má»—i lÆ°á»£t táº£i minh chá»©ng Ä‘Æ°á»£c há»‡ thá»‘ng ghi nháº­n gá»“m ngÆ°á»i táº£i, thá»i gian, tá»‡p táº£i vÃ  Ä‘á»‹a chá»‰ truy cáº­p Ä‘á»ƒ phá»¥c vá»¥ truy váº¿t.': 'Each evidence download is logged with downloader, time, file, and access address for traceability.',
        'Quy Ä‘á»‹nh upload': 'Upload Rules',
        'Tá»‡p upload pháº£i Ä‘Ãºng Ä‘á»‹nh dáº¡ng, Ä‘Ãºng tiÃªu chÃ­, Ä‘Ãºng nguá»“n cung cáº¥p vÃ  khÃ´ng vÆ°á»£t quÃ¡ giá»›i háº¡n dung lÆ°á»£ng cá»§a há»‡ thá»‘ng.': 'Uploaded files must match the format, criterion, source, and system size limit.',
        'Má»i tháº¯c máº¯c vá» dá»¯ liá»‡u minh chá»©ng liÃªn há»‡ Khoa CÃ´ng nghá»‡ thÃ´ng tin hoáº·c bá»™ pháº­n Ä‘áº£m báº£o cháº¥t lÆ°á»£ng.': 'For questions about evidence data, contact the IT Faculty or quality assurance unit.',
        'ÄÃ£ hiá»ƒu': 'Got It',
        'XÃ¡c nháº­n thao tÃ¡c': 'Confirm Action',
        'Báº¡n cháº¯c cháº¯n muá»‘n thá»±c hiá»‡n thao tÃ¡c nÃ y?': 'Are you sure you want to perform this action?',
        'Há»§y': 'Cancel',
        'Äá»“ng Ã½': 'Confirm',
        'Báº¡n cÃ³ cháº¯c muá»‘n Ä‘Äƒng xuáº¥t?': 'Are you sure you want to sign out?',
        'PhiÃªn lÃ m viá»‡c hiá»‡n táº¡i sáº½ Ä‘Æ°á»£c káº¿t thÃºc.': 'Your current session will be ended.',
        'á»ž láº¡i': 'Stay',
        'Tá»« khÃ³a': 'Keyword',
        'TiÃªu chuáº©n': 'Standard',
        'TiÃªu chÃ­': 'Criteria',
        'Minh chá»©ng': 'Evidence',
        'Tá»· lá»‡ Ä‘Ã¡p á»©ng': 'Compliance Rate',
        'Bá»™ tiÃªu chuáº©n Ä‘ang Ã¡p dá»¥ng': 'Active standard set',
        'ÄÃ£ phÃ¢n cÃ´ng Ä‘Æ¡n vá»‹ phá»¥ trÃ¡ch': 'Assigned to responsible units',
        'Tá»‡p Ä‘Ã£ Ä‘Æ°á»£c Ä‘Æ°a vÃ o há»‡ thá»‘ng': 'Files added to the system',
        'Theo tiÃªu chÃ­ Ä‘á»§ minh chá»©ng': 'Based on criteria with sufficient evidence',
        'TÃ¬nh tráº¡ng theo tiÃªu chuáº©n': 'Status by Standard',
        'Theo dÃµi má»©c Ä‘á»™ Ä‘áº§y Ä‘á»§ cá»§a há»“ sÆ¡ minh chá»©ng.': 'Track the completeness level of evidence records.',
        'Xem thá»‘ng kÃª': 'View Reports',
        'TÃªn tiÃªu chuáº©n': 'Standard Name',
        'Nháº­t kÃ½ gáº§n Ä‘Ã¢y': 'Recent Activity',
        'Äá»§ minh chá»©ng': 'Sufficient Evidence',
        'Cáº§n bá»• sung': 'Needs Supplement',
        'Thiáº¿u minh chá»©ng': 'Missing Evidence',
        'ÄÃ£ duyá»‡t': 'Approved',
        'Chá» rÃ  soÃ¡t': 'Under Review',
        'Hoáº¡t Ä‘á»™ng': 'Active',
        'Táº¡m khÃ³a': 'Locked',
        'Äang Ã¡p dá»¥ng': 'Active',
        'Má»¥c tiÃªu vÃ  chuáº©n Ä‘áº§u ra cá»§a chÆ°Æ¡ng trÃ¬nh Ä‘Ã o táº¡o': 'Program Objectives and Learning Outcomes',
        'Báº£n mÃ´ táº£ chÆ°Æ¡ng trÃ¬nh Ä‘Ã o táº¡o': 'Program Specification',
        'Cáº¥u trÃºc vÃ  ná»™i dung chÆ°Æ¡ng trÃ¬nh dáº¡y há»c': 'Curriculum Structure and Content',
        'PhÆ°Æ¡ng phÃ¡p tiáº¿p cáº­n trong dáº¡y vÃ  há»c': 'Teaching and Learning Approach',
        'ÄÃ¡nh giÃ¡ káº¿t quáº£ há»c táº­p cá»§a ngÆ°á»i há»c': 'Learner Assessment',
        'NÄƒm há»c': 'Academic Year',
        'Loáº¡i file': 'File Type',
        'Táº¥t cáº£': 'All',
        'TÃ¬m kiáº¿m': 'Search',
        'Káº¿t quáº£ tra cá»©u': 'Search Results',
        'MÃ£': 'Code',
        'TÃªn minh chá»©ng': 'Evidence Name',
        'TiÃªu chÃ­ liÃªn quan': 'Related Criteria',
        'ÄÆ¡n vá»‹': 'Department',
        'Tráº¡ng thÃ¡i': 'Status',
        'Thao tÃ¡c': 'Actions',
        'KhÃ´ng tÃ¬m tháº¥y minh chá»©ng phÃ¹ há»£p. Vui lÃ²ng thá»­ tá»« khÃ³a hoáº·c bá»™ lá»c khÃ¡c.': 'No matching evidence found. Please try another keyword or filter.',
        'minh chá»©ng phÃ¹ há»£p': 'matching evidence',
        'Äá»‹nh dáº¡ng': 'Format',
        'cáº­p nháº­t': 'updated',
        'KhÃ´ng thá»ƒ táº£i minh chá»©ng. Vui lÃ²ng kiá»ƒm tra láº¡i tá»‡p Ä‘Ã­nh kÃ¨m trong há»‡ thá»‘ng.': 'Unable to download evidence. Please check the attached file in the system.',
        'Danh má»¥c minh chá»©ng': 'Evidence List',
        'Upload minh chá»©ng': 'Upload Evidence',
        'ThÃ´ng tin minh chá»©ng': 'Evidence Information',
        'LÆ°u minh chá»©ng': 'Save Evidence',
        'TÃ¬m': 'Search',
        'Quáº£n trá»‹ viÃªn': 'Administrator',
        'NgÆ°á»i dÃ¹ng tra cá»©u': 'Search User'
    }
};

const placeholderTranslations = {
    en: {
        'TÃ¬m mÃ£ minh chá»©ng, tiÃªu chÃ­...': 'Search evidence code, criteria...',
        'MÃ£ minh chá»©ng, tÃªn minh chá»©ng': 'Evidence code, evidence name',
        'VD: MC.01.01.01': 'Ex: MC.01.01.01',
        'Nháº­p mÃ£ hoáº·c tÃªn tiÃªu chÃ­': 'Enter criterion code or name'
    }
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
    'Thống kê': 'Reports',
    'Khai thác dữ liệu': 'Data Access',
    'Tra cứu minh chứng': 'Search Evidence',
    'Tổng quan cơ sở dữ liệu minh chứng': 'Evidence Database Overview',
    'Quản lý tài khoản và phân quyền': 'Account and Permission Management',
    'Danh sách người dùng': 'User List',
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
    'Lưu tài khoản': 'Save Account',
    'Hủy sửa': 'Cancel Edit',
    'Quản trị viên': 'Administrator',
    'Người dùng tra cứu': 'Search User',
    'Hoạt động': 'Active',
    'Tạm khóa': 'Locked',
    'Quản lý hồ sơ minh chứng': 'Evidence Record Management',
    'Danh mục minh chứng': 'Evidence List',
    'Upload minh chứng': 'Upload Evidence',
    'Thông tin minh chứng': 'Evidence Information',
    'Mã minh chứng': 'Evidence Code',
    'Tên minh chứng': 'Evidence Name',
    'Mô tả': 'Description',
    'Năm học': 'Academic Year',
    'Ngày ban hành': 'Issue Date',
    'Đơn vị cung cấp': 'Provider',
    'Gắn tiêu chí': 'Linked Criteria',
    'File đính kèm': 'Attachment',
    'Lưu minh chứng': 'Save Evidence',
    'Đã duyệt': 'Approved',
    'Chờ rà soát': 'Under Review',
    'Cần bổ sung': 'Needs Supplement',
    'Đủ minh chứng': 'Sufficient Evidence',
    'Thiếu minh chứng': 'Missing Evidence',
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
    'Thống kê phục vụ kiểm định': 'Accreditation Reports',
    'Tiêu chí đủ minh chứng': 'Criteria with Sufficient Evidence',
    'Thiếu minh chứng': 'Missing Evidence',
    'Số lượng minh chứng theo tiêu chuẩn': 'Evidence Count by Standard',
    'Tiêu chí cần ưu tiên': 'Priority Criteria',
    'Xuất PDF': 'Export PDF',
    'Tra cứu và khai thác minh chứng': 'Search and Access Evidence',
    'Từ khóa': 'Keyword',
    'Loại file': 'File Type',
    'Tất cả': 'All',
    'Trang': 'Page',
    'Tìm kiếm': 'Search',
    'Tìm': 'Search',
    'Kết quả tra cứu': 'Search Results',
    'Mã': 'Code',
    'Tiêu chí liên quan': 'Related Criteria',
    'Không tìm thấy minh chứng phù hợp. Vui lòng thử từ khóa hoặc bộ lọc khác.': 'No matching evidence found. Please try another keyword or filter.',
    'minh chứng phù hợp': 'matching evidence',
    'Định dạng': 'Format',
    'cập nhật': 'updated',
    'Thông tin cá nhân': 'Profile',
    'Đổi mật khẩu': 'Change Password',
    'Đăng xuất': 'Sign Out',
    'Cài đặt hệ thống': 'System Settings',
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
    'Nhập nội dung tiêu chuẩn': 'Enter standard content',
    'Nhập mã hoặc tên tiêu chí': 'Enter criterion code or name',
    'Nhập mật khẩu hiện tại': 'Enter current password',
    'Nhập mật khẩu mới': 'Enter new password',
    'Xác nhận mật khẩu mới': 'Confirm new password',
    'Mã, tên minh chứng, tiêu chí': 'Code, evidence name, criteria',
    'Chọn đơn vị': 'Select department',
    'Không có tệp nào được chọn': 'No file selected'
});

function compactText(value) {
    return value.replace(/\s+/g, ' ').trim();
}

function translatePhrase(value, language) {
    if (language === 'vi') {
        return value;
    }

    const entries = Object.entries(translations.en).sort((a, b) => b[0].length - a[0].length);
    let translatedValue = value;

    entries.forEach(([source, target]) => {
        translatedValue = translatedValue.split(source).join(target);
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

    const original = compactText(node.originalText);
    const translated = translations[language]?.[original];
    node.nodeValue = language === 'vi' || !translated
        ? node.originalText
        : node.originalText.replace(original, translated);

    if (language !== 'vi' && !translated) {
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
            return ['SCRIPT', 'STYLE', 'TEXTAREA', 'INPUT'].includes(tagName)
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

    languageButtons.forEach((button) => {
        button.classList.toggle('active', button.dataset.languageOption === safeLanguage);
    });

    refreshStatusDropdowns();
    refreshTablePaginations();
}

applyLanguage(localStorage.getItem('kiemdinh-language') || 'vi');
initStatusDropdowns();
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
