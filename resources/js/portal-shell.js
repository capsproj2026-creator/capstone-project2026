/**
 * Portal shell: mobile sidebar, profile dropdown, Lucide icons.
 * Matches capstone ui (1) Layout / GuardLayout / UserLayout behavior.
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

function initPortalShell() {
    const root = document.getElementById('portal-root');
    initPasswordToggles(document);

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

    let sidebarOpen = false;

    const setSidebarOpen = (open) => {
        sidebarOpen = open;
        sidebar?.classList.toggle('-translate-x-full', !open);
        sidebar?.classList.toggle('translate-x-0', open);
        overlay?.classList.toggle('hidden', !open);
        menuIconOpen?.classList.toggle('hidden', open);
        menuIconClose?.classList.toggle('hidden', !open);
        menuBtn?.setAttribute('aria-expanded', open ? 'true' : 'false');
    };

    menuBtn?.addEventListener('click', () => setSidebarOpen(!sidebarOpen));
    overlay?.addEventListener('click', () => setSidebarOpen(false));

    sidebar?.querySelectorAll('a').forEach((link) => {
        link.addEventListener('click', () => {
            if (window.innerWidth < 1024) {
                setSidebarOpen(false);
            }
        });
    });

    profileBtn?.addEventListener('click', (e) => {
        e.stopPropagation();
        profileMenu?.classList.toggle('hidden');
        const open = profileMenu && !profileMenu.classList.contains('hidden');
        profileBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
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

    window.addEventListener('resize', () => {
        if (window.innerWidth >= 1024) {
            sidebar?.classList.remove('translate-x-0');
            sidebar?.classList.add('-translate-x-full');
            overlay?.classList.add('hidden');
            menuIconOpen?.classList.remove('hidden');
            menuIconClose?.classList.add('hidden');
            sidebarOpen = false;
            return;
        }

        if (!sidebarOpen) {
            sidebar?.classList.add('-translate-x-full');
            sidebar?.classList.remove('translate-x-0');
            overlay?.classList.add('hidden');
        }
    });

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
