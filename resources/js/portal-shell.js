/**
 * Portal shell: collapsible sidebar, profile dropdown, auto-collapse on menus, Lucide icons.
 */
function initPasswordToggles(root = document) {
    root.querySelectorAll('[data-password-toggle]').forEach((button) => {
        if (button.dataset.toggleBound === '1') {
            return;
        }
        button.dataset.toggleBound = '1';
        button.addEventListener('click', (event) => {
            event.preventDefault();
            const inputId = button.getAttribute('data-password-toggle');
            const input = document.getElementById(inputId);
            if (!input) return;

            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            button.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
            button.innerHTML = `<i data-lucide="${show ? 'eye-off' : 'eye'}" class="h-4 w-4"></i>`;
            if (window.lucide?.createIcons) {
                window.lucide.createIcons();
            }
        });
    });
}

window.initPasswordToggles = initPasswordToggles;

const SIDEBAR_SCROLL_KEY = 'portal-sidebar-scroll';
const THEME_STORAGE_KEY = 'portal-theme';

function syncThemeToggleIcons() {
    const isDark = document.documentElement.classList.contains('dark');
    const darkIcon = document.getElementById('portal-theme-icon-dark');
    const lightIcon = document.getElementById('portal-theme-icon-light');
    darkIcon?.classList.toggle('hidden', isDark);
    lightIcon?.classList.toggle('hidden', !isDark);
}

function applyPortalTheme(mode) {
    const root = document.documentElement;
    if (mode === 'dark') {
        root.classList.add('dark');
    } else {
        root.classList.remove('dark');
    }
    try {
        localStorage.setItem(THEME_STORAGE_KEY, mode);
    } catch (e) {
        // ignore
    }
    syncThemeToggleIcons();
    window.dispatchEvent(new CustomEvent('portal:theme-change', { detail: { mode } }));
}

function initPortalTheme() {
    const toggle = document.getElementById('portal-theme-toggle');
    syncThemeToggleIcons();
    toggle?.addEventListener('click', () => {
        const next = document.documentElement.classList.contains('dark') ? 'light' : 'dark';
        applyPortalTheme(next);
        if (window.lucide?.createIcons) {
            window.lucide.createIcons();
        }
    });
}

window.applyPortalTheme = applyPortalTheme;
window.isPortalDarkTheme = () => document.documentElement.classList.contains('dark');

function initPortalShell() {
    const root = document.getElementById('portal-root');
    initPasswordToggles(document);
    initPortalTheme();

    if (!root) {
        return;
    }

    const sidebar = document.getElementById('portal-sidebar');
    const overlay = document.getElementById('portal-overlay');
    const menuBtn = document.getElementById('portal-menu-btn');
    const menuIconOpen = document.getElementById('portal-menu-icon-open');
    const menuIconClose = document.getElementById('portal-menu-icon-close');
    const profileBtn = document.getElementById('portal-profile-btn');
    const profileMenu = document.getElementById('portal-profile-menu');
    const main = document.getElementById('portal-main');

    const saveSidebarScroll = () => {
        if (!sidebar) return;
        try {
            sessionStorage.setItem(SIDEBAR_SCROLL_KEY, String(sidebar.scrollTop));
        } catch (e) {
            // ignore
        }
    };

    const restoreSidebarScroll = () => {
        if (!sidebar) return;
        try {
            const y = parseInt(sessionStorage.getItem(SIDEBAR_SCROLL_KEY) || '0', 10);
            if (Number.isFinite(y) && y > 0) {
                sidebar.scrollTop = y;
            }
        } catch (e) {
            // ignore
        }
    };

    let sidebarOpen = false;

    const syncToggleIcons = (open) => {
        menuIconOpen?.classList.toggle('hidden', !open);
        menuIconClose?.classList.toggle('hidden', open);
        menuBtn?.setAttribute('aria-expanded', open ? 'true' : 'false');
        menuBtn?.setAttribute('aria-label', open ? 'Hide navigation sidebar' : 'Show navigation sidebar');
    };

    const setSidebarOpen = (open, { restoreScroll = false } = {}) => {
        if (open === sidebarOpen && !restoreScroll) {
            syncToggleIcons(open);
            overlay?.classList.toggle('portal-overlay-active', open);
            return;
        }

        if (!open && sidebarOpen) {
            saveSidebarScroll();
        }

        sidebarOpen = open;
        root.classList.toggle('portal-sidebar-open', open);
        root.classList.toggle('portal-sidebar-closed', !open);
        overlay?.classList.toggle('portal-overlay-active', open);

        syncToggleIcons(open);

        if (open && restoreScroll) {
            requestAnimationFrame(() => restoreSidebarScroll());
        }
    };

    const collapseSidebar = () => {
        if (sidebarOpen) {
            setSidebarOpen(false);
        }
    };

    /** Close the overlay drawer when a dropdown / menu / modal opens. */
    const collapseSidebarForMenu = () => {
        collapseSidebar();
    };

    setSidebarOpen(sidebarOpen, { restoreScroll: sidebarOpen });

    menuBtn?.addEventListener('click', () => {
        const next = !sidebarOpen;
        setSidebarOpen(next, { restoreScroll: next });
    });

    overlay?.addEventListener('click', () => setSidebarOpen(false));

    sidebar?.querySelectorAll('a[href]').forEach((link) => {
        link.addEventListener('click', () => {
            if (link.getAttribute('aria-current') === 'page') {
                return;
            }
            setSidebarOpen(false);
        });
    });

    profileBtn?.addEventListener('click', (e) => {
        e.stopPropagation();
        const willOpen = profileMenu?.classList.contains('hidden');
        profileMenu?.classList.toggle('hidden');
        const open = profileMenu && !profileMenu.classList.contains('hidden');
        profileBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (willOpen && open) {
            collapseSidebarForMenu();
        }
    });

    document.addEventListener('click', (e) => {
        if (
            profileMenu &&
            !profileMenu.classList.contains('hidden') &&
            !profileMenu.contains(e.target) &&
            !profileBtn?.contains(e.target)
        ) {
            profileMenu.classList.add('hidden');
            profileBtn?.setAttribute('aria-expanded', 'false');
        }
    });

    // In-page dropdowns / modals / details — collapse sidebar for more space.
    main?.addEventListener(
        'toggle',
        (e) => {
            if (e.target instanceof HTMLDetailsElement && e.target.open) {
                collapseSidebarForMenu();
            }
        },
        true
    );

    document.addEventListener('click', (e) => {
        const trigger = e.target.closest(
            '[data-portal-collapse-sidebar], [data-violation-detail], #report-type-trigger, [aria-haspopup="listbox"]'
        );
        if (trigger) {
            collapseSidebarForMenu();
        }
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && sidebarOpen) {
            setSidebarOpen(false);
        }
    });

    window.addEventListener(
        'portal:collapse-sidebar',
        () => collapseSidebarForMenu()
    );

    if (window.lucide?.createIcons) {
        window.lucide.createIcons();
    }

    initPhilippineClock();
}

function initPhilippineClock() {
    const nodes = document.querySelectorAll('[data-ph-clock]');
    if (!nodes.length) {
        return;
    }

    const tick = () => {
        nodes.forEach((root) => {
            const timezone = root.getAttribute('data-timezone') || 'Asia/Manila';
            const now = new Date();

            const timeEl = root.querySelector('[data-ph-clock-time]');
            const dateEl = root.querySelector('[data-ph-clock-date]');

            try {
                if (timeEl) {
                    timeEl.textContent = new Intl.DateTimeFormat('en-PH', {
                        timeZone: timezone,
                        hour: 'numeric',
                        minute: '2-digit',
                        second: '2-digit',
                        hour12: true,
                    }).format(now);
                }

                if (dateEl) {
                    dateEl.textContent = new Intl.DateTimeFormat('en-PH', {
                        timeZone: timezone,
                        weekday: 'short',
                        month: 'short',
                        day: 'numeric',
                        year: 'numeric',
                    }).format(now);
                }
            } catch (e) {
                if (timeEl) {
                    timeEl.textContent = now.toLocaleTimeString();
                }
            }
        });
    };

    tick();
    window.setInterval(tick, 1000);
}

document.addEventListener('DOMContentLoaded', initPortalShell);
