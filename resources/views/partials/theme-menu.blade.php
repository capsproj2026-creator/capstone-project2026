<style>
    [data-theme-choice][aria-checked="true"] {
        font-weight: 700;
        background: rgba(93, 159, 209, 0.16);
    }
</style>
<div class="relative shrink-0" data-theme-menu>
    <button
        type="button"
        data-theme-toggle
        class="portal-theme-toggle inline-flex h-10 w-10 items-center justify-center rounded-xl shadow-sm"
        aria-haspopup="menu"
        aria-expanded="false"
        aria-label="Color theme"
        title="Light, dark, or match this device"
    >
        <i data-lucide="sun-moon" class="h-[18px] w-[18px]"></i>
    </button>
    <div
        data-theme-panel
        class="guest-card absolute right-0 z-[80] mt-2 hidden w-44 overflow-hidden rounded-xl border border-gray-200 bg-white py-1 text-left shadow-lg"
        role="menu"
        aria-label="Color theme"
    >
        <button type="button" data-theme-choice="light" class="flex w-full items-center gap-2 px-3 py-2.5 text-left text-sm text-gray-800 hover:bg-gray-50" role="menuitemradio" aria-checked="false">
            <i data-lucide="sun" class="h-4 w-4 shrink-0"></i>
            Light
        </button>
        <button type="button" data-theme-choice="dark" class="flex w-full items-center gap-2 px-3 py-2.5 text-left text-sm text-gray-800 hover:bg-gray-50" role="menuitemradio" aria-checked="false">
            <i data-lucide="moon" class="h-4 w-4 shrink-0"></i>
            Dark
        </button>
        <button type="button" data-theme-choice="system" class="flex w-full items-center gap-2 px-3 py-2.5 text-left text-sm text-gray-800 hover:bg-gray-50" role="menuitemradio" aria-checked="false">
            <i data-lucide="monitor" class="h-4 w-4 shrink-0"></i>
            System
        </button>
    </div>
</div>
<script>
    (function () {
        if (window.__portalThemeMenu) return;
        window.__portalThemeMenu = true;
        var key = 'portal-theme';
        var media = window.matchMedia('(prefers-color-scheme: dark)');

        function stored() {
            try {
                var value = localStorage.getItem(key);
                return value === 'light' || value === 'dark' || value === 'system' ? value : 'system';
            } catch (e) {
                return 'system';
            }
        }

        function resolveDark(mode) {
            if (mode === 'dark') return true;
            if (mode === 'light') return false;
            return media.matches;
        }

        function apply(mode, persist) {
            if (mode !== 'light' && mode !== 'dark') mode = 'system';
            document.documentElement.classList.toggle('dark', resolveDark(mode));
            document.documentElement.setAttribute('data-theme', mode);
            if (persist) {
                try { localStorage.setItem(key, mode); } catch (e) { /* ignore */ }
            }
            document.querySelectorAll('[data-theme-choice]').forEach(function (button) {
                var on = button.getAttribute('data-theme-choice') === mode;
                button.setAttribute('aria-checked', on ? 'true' : 'false');
            });
            window.dispatchEvent(new CustomEvent('portal:theme-change', { detail: { mode: mode } }));
            if (window.lucide && window.lucide.createIcons) window.lucide.createIcons();
        }

        window.applyPortalTheme = function (mode) { apply(mode, true); };

        media.addEventListener('change', function () {
            if (stored() === 'system') apply('system', false);
        });

        document.addEventListener('DOMContentLoaded', function () {
            apply(stored(), false);
            document.querySelectorAll('[data-theme-menu]').forEach(function (root) {
                var button = root.querySelector('[data-theme-toggle]');
                var panel = root.querySelector('[data-theme-panel]');
                if (!button || !panel) return;
                button.addEventListener('click', function (event) {
                    event.stopPropagation();
                    var willOpen = panel.classList.contains('hidden');
                    document.querySelectorAll('[data-theme-panel]').forEach(function (other) {
                        other.classList.add('hidden');
                    });
                    panel.classList.toggle('hidden', !willOpen);
                    button.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
                });
                panel.querySelectorAll('[data-theme-choice]').forEach(function (choice) {
                    choice.addEventListener('click', function (event) {
                        event.stopPropagation();
                        apply(choice.getAttribute('data-theme-choice'), true);
                        panel.classList.add('hidden');
                        button.setAttribute('aria-expanded', 'false');
                    });
                });
            });
            document.addEventListener('click', function () {
                document.querySelectorAll('[data-theme-panel]').forEach(function (panel) {
                    panel.classList.add('hidden');
                });
                document.querySelectorAll('[data-theme-toggle]').forEach(function (button) {
                    button.setAttribute('aria-expanded', 'false');
                });
            });
        });
    })();
</script>
