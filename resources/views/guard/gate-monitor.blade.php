@extends('layouts.guard')

@section('title', 'Live Gate Monitor')

@section('content')
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        @include('partials.shell.page-header', [
            'title' => 'Live Gate Monitor',
            'subtitle' => 'Displays user info when they pass through the gate',
        ])
        <button
            type="button"
            id="gate-fullscreen-toggle"
            class="inline-flex shrink-0 items-center gap-2 self-start rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 shadow-sm transition hover:border-emerald-300 hover:bg-emerald-50 hover:text-emerald-700"
            aria-pressed="false"
            aria-label="Enter fullscreen"
        >
            <i data-lucide="maximize" class="h-4 w-4" id="gate-fullscreen-icon"></i>
            <span id="gate-fullscreen-label">Full Screen</span>
        </button>
    </div>

    @if (session('error'))
        <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
    @endif
    @if (session('success'))
        <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
    @endif

    {{-- Summary cards (normal view only — hidden in fullscreen) --}}
    <div id="gate-monitor-stats" class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <div>
                <p class="text-sm text-gray-500">Total Entries</p>
                <p id="today-entries" class="mt-1 text-3xl font-bold tracking-tight text-emerald-600">{{ $todayEntries }}</p>
            </div>
            <div class="flex h-11 w-11 items-center justify-center rounded-full bg-emerald-100 text-emerald-600">
                <i data-lucide="arrow-down" class="h-5 w-5"></i>
            </div>
        </div>

        <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <div>
                <p class="text-sm text-gray-500">Total Exits</p>
                <p id="today-exits" class="mt-1 text-3xl font-bold tracking-tight text-blue-600">{{ $todayExits }}</p>
            </div>
            <div class="flex h-11 w-11 items-center justify-center rounded-full bg-blue-100 text-blue-600">
                <i data-lucide="arrow-up" class="h-5 w-5"></i>
            </div>
        </div>

        <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <div>
                <p class="text-sm text-gray-500">Status</p>
                <div class="mt-2 flex items-center gap-2">
                    <span id="live-indicator" class="h-2.5 w-2.5 animate-pulse rounded-full bg-emerald-500"></span>
                    <p id="live-status" class="text-lg font-semibold text-gray-900">Active</p>
                </div>
            </div>
            <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-gray-100 text-gray-400">
                <i data-lucide="scan" class="h-5 w-5"></i>
            </div>
        </div>
    </div>

    {{-- Live scan stage (fullscreen target) --}}
    <div id="gate-monitor-stage" class="gate-monitor-stage rounded-2xl border border-gray-200 bg-white p-4 shadow-sm sm:p-6 lg:p-8">
        <div class="mb-4 flex items-center justify-between gap-3 sm:mb-6">
            <p id="server-clock" class="rounded-full bg-emerald-500 px-4 py-1.5 text-sm font-semibold text-white tabular-nums">
                {{ now()->format('g:i:s A') }}
            </p>
            <div class="flex items-center gap-3">
                <p id="last-updated" class="hidden text-xs text-gray-400 sm:block">Waiting for the next update</p>
                <button
                    type="button"
                    id="gate-fullscreen-exit"
                    class="hidden items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-600 shadow-sm hover:bg-gray-50"
                    aria-label="Exit fullscreen"
                >
                    <i data-lucide="minimize" class="h-3.5 w-3.5"></i>
                    Exit
                </button>
            </div>
        </div>

        <div id="gate-monitor-display" class="relative flex min-h-[22rem] flex-col items-center justify-center sm:min-h-[26rem]">
            {{-- Waiting state --}}
            <div id="waiting-state" class="flex w-full max-w-lg flex-col items-center px-4 py-8 text-center">
                <div class="mb-5 flex h-20 w-20 items-center justify-center rounded-2xl bg-gray-100 text-gray-300">
                    <i data-lucide="scan" class="h-10 w-10"></i>
                </div>
                <h2 class="text-2xl font-bold text-gray-900 sm:text-3xl">Waiting for RFID...</h2>
                <p class="mt-2 max-w-md text-sm text-gray-500 sm:text-base">System ready to scan RFID tags from the ESP32 / RC522 gate readers</p>
            </div>

            {{-- Active scan card --}}
            <div id="scan-card" class="mx-auto hidden w-full max-w-md">
                <div id="scan-card-inner" class="overflow-hidden rounded-2xl border-4 border-gray-200 bg-white shadow-lg transition-colors">
                    <div class="px-6 pb-2 pt-8 text-center sm:px-8">
                        <div id="scan-avatar-wrap" class="relative mx-auto h-20 w-20 shrink-0 overflow-hidden rounded-full bg-blue-600 sm:h-24 sm:w-24">
                            <img
                                id="scan-avatar-img"
                                src=""
                                alt=""
                                class="hidden h-full w-full object-cover"
                            >
                            <div id="scan-avatar-initials" class="flex h-full w-full items-center justify-center text-2xl font-bold text-white sm:text-3xl">—</div>
                        </div>
                        <h3 id="scan-name" class="mt-4 text-2xl font-bold text-gray-900 sm:text-3xl">—</h3>
                        <span id="scan-role" class="mt-2 inline-flex rounded-full bg-gray-100 px-3 py-1 text-sm font-medium text-gray-600">—</span>
                    </div>

                    <div class="space-y-3 px-5 pb-5 sm:px-6">
                        <div class="rounded-xl bg-gray-50 px-4 py-3">
                            <div class="flex items-center gap-2 text-xs font-medium text-gray-500">
                                <i data-lucide="car" class="h-3.5 w-3.5"></i>
                                Vehicle
                            </div>
                            <p id="scan-plate" class="mt-1 text-xl font-bold tracking-wide text-gray-900 sm:text-2xl">—</p>
                        </div>

                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div class="rounded-xl bg-gray-50 px-4 py-3">
                                <p class="text-xs font-medium text-gray-500">RFID UID</p>
                                <p id="scan-rfid" class="mt-1 truncate font-mono text-sm font-semibold text-gray-900">—</p>
                            </div>
                            <div class="rounded-xl bg-gray-50 px-4 py-3">
                                <p class="text-xs font-medium text-gray-500">Gate</p>
                                <p id="scan-gate" class="mt-1 truncate text-sm font-semibold text-gray-900">—</p>
                            </div>
                            <div class="rounded-xl bg-gray-50 px-4 py-3">
                                <p class="text-xs font-medium text-gray-500">Direction</p>
                                <p id="scan-direction" class="mt-1 text-sm font-semibold text-gray-900">—</p>
                            </div>
                            <div class="rounded-xl bg-gray-50 px-4 py-3">
                                <p class="text-xs font-medium text-gray-500">Timestamp</p>
                                <p id="scan-time" class="mt-1 text-sm font-semibold text-gray-900">—</p>
                            </div>
                        </div>
                    </div>

                    <div id="scan-status-bar" class="flex items-center justify-center gap-2 border-t border-gray-100 bg-gray-50 px-4 py-4 text-sm font-semibold text-gray-500 sm:text-base">
                        <span id="scan-status-icon" class="flex h-6 w-6 items-center justify-center rounded-full bg-gray-400 text-white">
                            <i data-lucide="check" class="h-3.5 w-3.5"></i>
                        </span>
                        <span id="scan-status-label">—</span>
                    </div>
                    <p id="scan-reason" class="hidden border-t border-red-100 bg-red-50 px-4 pb-4 text-center text-xs text-red-600 sm:text-sm"></p>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
<style>
    .gate-monitor-stage:fullscreen,
    .gate-monitor-stage:-webkit-full-screen {
        display: flex;
        flex-direction: column;
        width: 100%;
        height: 100%;
        margin: 0;
        border: none;
        border-radius: 0;
        background: #fff;
        padding: 1.5rem 2rem 2rem;
        overflow: hidden;
    }

    .gate-monitor-stage:fullscreen #gate-monitor-display,
    .gate-monitor-stage:-webkit-full-screen #gate-monitor-display {
        flex: 1;
        min-height: 0;
        justify-content: center;
    }

    .gate-monitor-stage:fullscreen #waiting-state,
    .gate-monitor-stage:-webkit-full-screen #waiting-state {
        padding-top: 2rem;
        padding-bottom: 2rem;
    }

    .gate-monitor-stage:fullscreen #waiting-state h2,
    .gate-monitor-stage:-webkit-full-screen #waiting-state h2 {
        font-size: 2.75rem;
    }

    .gate-monitor-stage:fullscreen #scan-card,
    .gate-monitor-stage:-webkit-full-screen #scan-card {
        max-width: 36rem;
    }

    .gate-monitor-stage:fullscreen #scan-avatar-wrap,
    .gate-monitor-stage:-webkit-full-screen #scan-avatar-wrap {
        height: 6.5rem;
        width: 6.5rem;
    }

    .gate-monitor-stage:fullscreen #scan-name,
    .gate-monitor-stage:-webkit-full-screen #scan-name {
        font-size: 2.25rem;
    }

    .gate-monitor-stage:fullscreen #scan-plate,
    .gate-monitor-stage:-webkit-full-screen #scan-plate {
        font-size: 1.875rem;
    }

    .gate-monitor-stage:fullscreen #last-updated,
    .gate-monitor-stage:-webkit-full-screen #last-updated {
        display: block;
    }
</style>
@endpush

@push('scripts')
<script>
    (() => {
        const IDLE_MS = 5000;

        const entries = document.getElementById('today-entries');
        const exits = document.getElementById('today-exits');
        const indicator = document.getElementById('live-indicator');
        const liveStatus = document.getElementById('live-status');
        const lastUpdated = document.getElementById('last-updated');
        const waitingState = document.getElementById('waiting-state');
        const scanCard = document.getElementById('scan-card');
        const scanCardInner = document.getElementById('scan-card-inner');
        const serverClock = document.getElementById('server-clock');
        const avatarImg = document.getElementById('scan-avatar-img');
        const avatarInitials = document.getElementById('scan-avatar-initials');
        const stage = document.getElementById('gate-monitor-stage');

        let idleTimer = null;
        let knownLatestId = '';

        const roleClasses = (role) => {
            const r = String(role || '').toLowerCase();
            if (r === 'student') return 'bg-blue-50 text-blue-700';
            if (r === 'staff') return 'bg-violet-50 text-violet-700';
            return 'bg-gray-100 text-gray-600';
        };

        const setText = (id, value) => {
            const el = document.getElementById(id);
            if (el) el.textContent = value ?? '—';
        };

        const updateAvatar = (latest) => {
            const url = latest?.profile_picture_url || '';
            const initials = latest?.initials || 'U';

            if (avatarInitials) {
                avatarInitials.textContent = initials;
                avatarInitials.classList.remove('hidden');
            }

            if (url && avatarImg) {
                avatarImg.alt = latest?.name || 'User';
                avatarImg.classList.add('hidden');
                avatarImg.onload = () => {
                    avatarImg.classList.remove('hidden');
                    avatarInitials?.classList.add('hidden');
                };
                avatarImg.onerror = () => {
                    avatarImg.classList.add('hidden');
                    avatarInitials?.classList.remove('hidden');
                };
                avatarImg.src = `${url}${url.includes('?') ? '&' : '?'}t=${Date.now()}`;
                if (avatarImg.complete && avatarImg.naturalWidth > 0) {
                    avatarImg.classList.remove('hidden');
                    avatarInitials?.classList.add('hidden');
                }
                return;
            }

            avatarImg?.classList.add('hidden');
        };

        const showWaiting = () => {
            waitingState?.classList.remove('hidden');
            scanCard?.classList.add('hidden');
            if (idleTimer) {
                clearTimeout(idleTimer);
                idleTimer = null;
            }
        };

        const showScanCard = (latest) => {
            waitingState?.classList.add('hidden');
            scanCard?.classList.remove('hidden');

            updateAvatar(latest);
            setText('scan-name', latest.name);
            setText('scan-role', latest.role);
            setText('scan-plate', latest.plate_number || '—');
            setText('scan-rfid', latest.rfid_uid_full || latest.rfid_uid || '—');
            setText('scan-gate', latest.gate_label || latest.gate_id || '—');
            setText('scan-direction', latest.action || '—');
            setText('scan-time', latest.time || '—');
            setText('scan-status-label', latest.status_label || latest.result);

            const roleEl = document.getElementById('scan-role');
            if (roleEl) {
                roleEl.className = `mt-2 inline-flex rounded-full px-3 py-1 text-sm font-medium ${roleClasses(latest.role)}`;
            }

            const granted = !!latest.granted;
            if (scanCardInner) {
                scanCardInner.className = `overflow-hidden rounded-2xl border-4 bg-white shadow-lg transition-colors ${
                    granted ? 'border-emerald-500' : 'border-red-500'
                }`;
                scanCardInner.classList.add('ring-4', granted ? 'ring-emerald-200' : 'ring-red-200');
                window.setTimeout(() => {
                    scanCardInner.classList.remove('ring-4', 'ring-emerald-200', 'ring-red-200');
                }, 1200);
            }

            const statusBar = document.getElementById('scan-status-bar');
            if (statusBar) {
                statusBar.className = `flex items-center justify-center gap-2 border-t px-4 py-4 text-sm font-semibold sm:text-base ${
                    granted
                        ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                        : 'border-red-200 bg-red-50 text-red-700'
                }`;
            }

            const statusIcon = document.getElementById('scan-status-icon');
            if (statusIcon) {
                statusIcon.className = `flex h-6 w-6 items-center justify-center rounded-full ${
                    granted ? 'bg-emerald-500 text-white' : 'bg-red-500 text-white'
                }`;
                statusIcon.innerHTML = granted
                    ? '<i data-lucide="check" class="h-3.5 w-3.5"></i>'
                    : '<i data-lucide="x" class="h-3.5 w-3.5"></i>';
            }

            const reasonEl = document.getElementById('scan-reason');
            if (reasonEl) {
                const reason = (!granted && latest.reason) ? latest.reason : '';
                reasonEl.textContent = reason;
                reasonEl.classList.toggle('hidden', reason === '');
            }

            if (idleTimer) clearTimeout(idleTimer);
            idleTimer = window.setTimeout(showWaiting, IDLE_MS);

            if (window.lucide) window.lucide.createIcons();
        };

        const setConnectionState = (online, updatedAt = null) => {
            if (indicator) {
                indicator.className = `h-2.5 w-2.5 rounded-full ${online ? 'animate-pulse bg-emerald-500' : 'bg-red-500'}`;
            }
            if (liveStatus) {
                liveStatus.textContent = online ? 'Active' : 'Offline';
                liveStatus.className = `text-lg font-semibold ${online ? 'text-gray-900' : 'text-red-600'}`;
            }
            if (lastUpdated) {
                lastUpdated.textContent = online
                    ? (updatedAt ? `Last scan ${updatedAt}` : 'Listening for RFID…')
                    : 'Realtime disconnected — check Reverb';
                lastUpdated.classList.remove('hidden');
            }
        };

        const handleScan = (scan) => {
            if (!scan?.id) return;
            knownLatestId = String(scan.id);
            if (entries && scan.today_entries != null) {
                entries.textContent = scan.today_entries;
            } else if (entries && scan.granted && scan.action === 'Entry') {
                entries.textContent = String(Number(entries.textContent || 0) + 1);
            }
            if (exits && scan.today_exits != null) {
                exits.textContent = scan.today_exits;
            } else if (exits && scan.granted && scan.action === 'Exit') {
                exits.textContent = String(Number(exits.textContent || 0) + 1);
            }
            showScanCard(scan);
            setConnectionState(true, scan.time || null);
        };

        showWaiting();
        setConnectionState(false);

        const subscribeGateScans = (echo) => {
            if (!echo) {
                if (liveStatus) liveStatus.textContent = 'Echo offline';
                if (lastUpdated) {
                    lastUpdated.textContent = 'Build assets with VITE_REVERB_* and run php artisan reverb:start';
                    lastUpdated.classList.remove('hidden');
                }
                return;
            }

            echo.private('gate.scans')
                .listen('.GateScanProcessed', (scan) => handleScan(scan))
                .error(() => setConnectionState(false));

            const connector = echo.connector?.pusher;
            connector?.connection?.bind('connected', () => setConnectionState(true));
            connector?.connection?.bind('disconnected', () => setConnectionState(false));
            connector?.connection?.bind('unavailable', () => setConnectionState(false));
            connector?.connection?.bind('failed', () => setConnectionState(false));
            connector?.connection?.bind('error', () => setConnectionState(false));

            if (connector?.connection?.state === 'connected') {
                setConnectionState(true);
            }
        };

        // Vite loads app.js as a deferred module; whenEchoReady waits for it.
        window.whenEchoReady(subscribeGateScans);

        window.setInterval(() => {
            if (!serverClock || document.hidden) return;
            try {
                serverClock.textContent = new Date().toLocaleTimeString('en-US', {
                    hour: 'numeric',
                    minute: '2-digit',
                    second: '2-digit',
                    hour12: true,
                    timeZone: @json(config('app.timezone')),
                });
            } catch (e) {}
        }, 1000);

        if (window.lucide) window.lucide.createIcons();

        const fsToggle = document.getElementById('gate-fullscreen-toggle');
        const fsExit = document.getElementById('gate-fullscreen-exit');
        const fsIcon = document.getElementById('gate-fullscreen-icon');
        const fsLabel = document.getElementById('gate-fullscreen-label');

        const fsElement = () => (
            document.fullscreenElement
            || document.webkitFullscreenElement
            || null
        );

        const isFullscreen = () => fsElement() === stage;

        const requestFs = (el) => (
            el.requestFullscreen?.()
            || el.webkitRequestFullscreen?.()
            || Promise.reject(new Error('Fullscreen not supported'))
        );

        const exitFs = () => (
            document.exitFullscreen?.()
            || document.webkitExitFullscreen?.()
            || Promise.resolve()
        );

        const syncFullscreenUi = () => {
            const active = isFullscreen();
            fsToggle?.setAttribute('aria-pressed', active ? 'true' : 'false');
            fsToggle?.setAttribute('aria-label', active ? 'Exit fullscreen' : 'Enter fullscreen');
            if (fsLabel) fsLabel.textContent = active ? 'Exit Full Screen' : 'Full Screen';
            if (fsIcon) fsIcon.setAttribute('data-lucide', active ? 'minimize' : 'maximize');
            fsExit?.classList.toggle('hidden', !active);
            fsExit?.classList.toggle('inline-flex', active);
            if (window.lucide) window.lucide.createIcons();
        };

        fsToggle?.addEventListener('click', async () => {
            try {
                if (isFullscreen()) await exitFs();
                else if (stage) await requestFs(stage);
            } catch (e) {
                // ignore unsupported or blocked fullscreen
            }
        });

        fsExit?.addEventListener('click', () => {
            if (isFullscreen()) exitFs();
        });

        document.addEventListener('fullscreenchange', syncFullscreenUi);
        document.addEventListener('webkitfullscreenchange', syncFullscreenUi);
        syncFullscreenUi();
    })();
</script>
@endpush
