@include('partials.shell.page-header', [
    'title' => 'Access Logs',
    'subtitle' => 'Monitor all entry and exit activities via RFID access',
])

{{-- Summary cards --}}
<div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
        <div>
            <p id="stat-total" class="text-3xl font-bold tracking-tight text-gray-900">{{ number_format($stats['total']) }}</p>
            <p class="mt-1 text-sm text-gray-500">Total Logs</p>
        </div>
        <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-gray-100 text-gray-500">
            <i data-lucide="calendar" class="h-5 w-5"></i>
        </div>
    </div>

    <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
        <div>
            <p id="stat-entries" class="text-3xl font-bold tracking-tight text-blue-600">{{ number_format($stats['entries_granted']) }}</p>
            <p class="mt-1 text-sm text-gray-500">Entries Granted</p>
        </div>
        <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
            <i data-lucide="log-in" class="h-5 w-5"></i>
        </div>
    </div>

    <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
        <div>
            <p id="stat-exits" class="text-3xl font-bold tracking-tight text-purple-600">{{ number_format($stats['exits_granted']) }}</p>
            <p class="mt-1 text-sm text-gray-500">Exits Granted</p>
        </div>
        <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-purple-50 text-purple-600">
            <i data-lucide="log-out" class="h-5 w-5"></i>
        </div>
    </div>

    <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
        <div>
            <p id="stat-denied" class="text-3xl font-bold tracking-tight text-red-600">{{ number_format($stats['access_denied']) }}</p>
            <p class="mt-1 text-sm text-gray-500">Access Denied</p>
        </div>
        <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-red-50 text-red-600">
            <i data-lucide="x-circle" class="h-5 w-5"></i>
        </div>
    </div>
</div>

{{-- Search / filters --}}
<form method="GET" class="mb-6 flex flex-col gap-3 lg:flex-row lg:items-center">
    <div class="relative min-w-0 flex-1">
        <i data-lucide="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400"></i>
        <input
            type="search"
            name="q"
            value="{{ $search }}"
            placeholder="Search by name, Student/Staff, RFID, or gate..."
            class="w-full rounded-lg border border-gray-200 bg-gray-50 py-2.5 pl-10 pr-3 text-sm text-gray-900 placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20"
        >
    </div>

    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3 lg:w-auto lg:min-w-[28rem]">
        <select
            name="type"
            class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm text-gray-800 focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20"
        >
            <option value="all" @selected($typeFilter === 'all')>All Types</option>
            <option value="Student" @selected($typeFilter === 'Student')>Student</option>
            <option value="Staff" @selected($typeFilter === 'Staff')>Staff</option>
            <option value="Visitor" @selected($typeFilter === 'Visitor')>Visitor</option>
        </select>

        <select
            name="direction"
            class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm text-gray-800 focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20"
        >
            <option value="all" @selected($directionFilter === 'all')>All Directions</option>
            <option value="Entry" @selected($directionFilter === 'Entry')>Entry</option>
            <option value="Exit" @selected($directionFilter === 'Exit')>Exit</option>
        </select>

        <select
            name="result"
            class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm text-gray-800 focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20"
        >
            <option value="all" @selected($resultFilter === 'all')>All Results</option>
            <option value="Granted" @selected($resultFilter === 'Granted')>Granted</option>
            <option value="Denied" @selected($resultFilter === 'Denied')>Denied</option>
        </select>
    </div>

    <div class="flex flex-wrap gap-2">
        <button type="submit" class="rounded-lg bg-gray-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-black">
            Search
        </button>
        <a href="{{ $clearRoute }}" class="rounded-lg border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
            Clear
        </a>
    </div>
    @if (($dateFrom ?? '') !== '')
        <input type="hidden" name="date_from" value="{{ $dateFrom }}">
    @endif
    @if (($dateTo ?? '') !== '')
        <input type="hidden" name="date_to" value="{{ $dateTo }}">
    @endif
</form>

{{-- Access Records table --}}
<div class="mb-6 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
    <div class="border-b border-gray-100 px-5 py-4 sm:px-6">
        <h2 id="access-records-heading" class="text-base font-semibold text-gray-900 sm:text-lg">
            Access Records ({{ number_format($logs->total()) }})
        </h2>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-[720px] w-full text-left text-sm">
            <thead class="border-b border-gray-100 bg-gray-50/80 text-xs font-medium uppercase tracking-wide text-gray-500">
                <tr>
                    <th class="whitespace-nowrap px-5 py-3 font-medium sm:px-6">Timestamp</th>
                    <th class="whitespace-nowrap px-5 py-3 font-medium sm:px-6">User</th>
                    <th class="whitespace-nowrap px-5 py-3 font-medium sm:px-6">Type</th>
                    <th class="whitespace-nowrap px-5 py-3 font-medium sm:px-6">Direction</th>
                    <th class="whitespace-nowrap px-5 py-3 font-medium sm:px-6">Gate</th>
                    <th class="whitespace-nowrap px-5 py-3 font-medium sm:px-6">Result</th>
                </tr>
            </thead>
            <tbody id="access-records-body" class="divide-y divide-gray-100">
                @forelse ($logs as $log)
                    @php
                        $role = $log->user?->displayRoleLabel() ?? '—';
                        $granted = $log->accessGranted();
                        $isEntry = ($log->action ?? '') === 'Entry';
                    @endphp
                    <tr class="hover:bg-gray-50/80">
                        <td class="whitespace-nowrap px-5 py-4 text-gray-700 sm:px-6">
                            {{ ph_datetime($log->timestamp, 'n/j/Y, g:i:s A') }}
                        </td>
                        <td class="px-5 py-4 sm:px-6">
                            <div class="min-w-0">
                                <p class="truncate font-semibold text-gray-900">{{ $log->user?->displayName() ?? 'Unknown' }}</p>
                                <p class="text-xs text-gray-400">{{ $log->user?->id_number ?? ($log->user?->id ?? '—') }}</p>
                            </div>
                        </td>
                        <td class="px-5 py-4 sm:px-6">
                            @if ($role === 'Student')
                                <span class="inline-flex rounded-full bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700">Student</span>
                            @elseif ($role === 'Staff')
                                <span class="inline-flex rounded-full bg-purple-50 px-2.5 py-1 text-xs font-semibold text-purple-700">Staff</span>
                            @elseif ($role === 'Visitor')
                                <span class="inline-flex rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">Visitor</span>
                            @elseif ($role === 'Student / Faculty')
                                <span class="inline-flex rounded-full bg-sky-50 px-2.5 py-1 text-xs font-semibold text-sky-800">Student / Faculty</span>
                            @else
                                <span class="inline-flex rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-600">{{ $role }}</span>
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-5 py-4 sm:px-6">
                            @if ($isEntry)
                                <span class="inline-flex items-center gap-1.5 font-medium text-blue-600">
                                    <i data-lucide="log-in" class="h-4 w-4"></i>
                                    Entry
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1.5 font-medium text-purple-600">
                                    <i data-lucide="log-out" class="h-4 w-4"></i>
                                    {{ $log->action ?: '—' }}
                                </span>
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-5 py-4 text-gray-700 sm:px-6">
                            {{ $log->displayGate() }}
                        </td>
                        <td class="px-5 py-4 sm:px-6">
                            @if ($granted)
                                <span class="inline-flex items-center gap-1 rounded-full bg-emerald-500 px-2.5 py-1 text-xs font-semibold text-white">
                                    <i data-lucide="check" class="h-3.5 w-3.5"></i>
                                    Granted
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 rounded-full bg-red-500 px-2.5 py-1 text-xs font-semibold text-white">
                                    <i data-lucide="x" class="h-3.5 w-3.5"></i>
                                    Denied
                                </span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center text-sm text-gray-500">
                            No access logs found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($logs->hasPages())
        <div class="border-t border-gray-100 px-5 py-4 sm:px-6">
            {{ $logs->links() }}
        </div>
    @endif
</div>

{{-- Recent Denied Access --}}
<div class="rounded-xl border border-red-100 bg-red-50/70 p-5 sm:p-6">
    <h3 class="mb-4 text-base font-semibold text-red-900">Recent Denied Access</h3>

    <div id="recent-denied-list" class="space-y-3">
        @forelse ($recentDenied as $denied)
            <div class="flex flex-col gap-3 rounded-xl border border-red-100 bg-white p-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0">
                    <p class="font-semibold text-gray-900">{{ $denied->user?->displayName() ?? 'Unknown' }}</p>
                    <p class="mt-0.5 text-sm text-red-600">{{ $denied->displayReason() }}</p>
                    <p class="mt-2 text-xs text-gray-500">
                        {{ ph_datetime($denied->timestamp, 'n/j/Y, g:i:s A') }}
                        <span class="mx-1">•</span>
                        {{ $denied->displayGate() }}
                    </p>
                </div>
                <span class="inline-flex w-fit shrink-0 items-center gap-1 rounded-full bg-red-500 px-3 py-1 text-xs font-semibold text-white">
                    <i data-lucide="x" class="h-3.5 w-3.5"></i>
                    Denied
                </span>
            </div>
        @empty
            <p class="rounded-xl border border-dashed border-red-200 bg-white/70 px-4 py-8 text-center text-sm text-red-800/70">
                No denied access attempts recently.
            </p>
        @endforelse
    </div>
</div>

@push('scripts')
<script>
    (() => {
        if (window.lucide) window.lucide.createIcons();

        const eventsUrl = @json($eventsRoute);
        const clearRoute = @json($clearRoute);
        let lastId = @json(optional($logs->first())->getKey() ? (string) $logs->first()->getKey() : null);
        let busy = false;
        let searchTimer = null;
        let requestId = 0;
        const form = document.querySelector('form[method="GET"]');
        const searchInput = form?.querySelector('input[name="q"]');

        const esc = (value) => String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');

        const roleBadge = (role) => {
            const r = String(role || '');
            if (r === 'Student') return '<span class="inline-flex rounded-full bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700">Student</span>';
            if (r === 'Staff') return '<span class="inline-flex rounded-full bg-purple-50 px-2.5 py-1 text-xs font-semibold text-purple-700">Staff</span>';
            if (r === 'Visitor') return '<span class="inline-flex rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">Visitor</span>';
            if (r === 'Student / Faculty') return '<span class="inline-flex rounded-full bg-sky-50 px-2.5 py-1 text-xs font-semibold text-sky-800">Student / Faculty</span>';
            return `<span class="inline-flex rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-600">${esc(r) || '—'}</span>`;
        };

        const resultBadge = (granted) => granted
            ? `<span class="inline-flex items-center gap-1 rounded-full bg-emerald-500 px-2.5 py-1 text-xs font-semibold text-white"><i data-lucide="check" class="h-3.5 w-3.5"></i> Granted</span>`
            : `<span class="inline-flex items-center gap-1 rounded-full bg-red-500 px-2.5 py-1 text-xs font-semibold text-white"><i data-lucide="x" class="h-3.5 w-3.5"></i> Denied</span>`;

        const directionCell = (action) => action === 'Entry'
            ? `<span class="inline-flex items-center gap-1.5 font-medium text-blue-600"><i data-lucide="log-in" class="h-4 w-4"></i> Entry</span>`
            : `<span class="inline-flex items-center gap-1.5 font-medium text-purple-600"><i data-lucide="log-out" class="h-4 w-4"></i> ${esc(action || '—')}</span>`;

        const currentParams = () => {
            const params = new URLSearchParams(new FormData(form));
            [...params.entries()].forEach(([key, value]) => {
                if (String(value).trim() === '') {
                    params.delete(key);
                }
            });
            return params;
        };

        const renderLogs = (logs) => {
            const tbody = document.querySelector('#access-records-body');
            if (!tbody) return;

            if (!logs.length) {
                tbody.innerHTML = `<tr><td colspan="6" class="px-6 py-12 text-center text-sm text-gray-500">No access logs found.</td></tr>`;
                return;
            }

            tbody.innerHTML = logs.map((log) => `
                <tr class="hover:bg-gray-50/80">
                    <td class="whitespace-nowrap px-5 py-4 text-gray-700 sm:px-6">${esc(log.timestamp)}</td>
                    <td class="px-5 py-4 sm:px-6">
                        <div class="min-w-0">
                            <p class="truncate font-semibold text-gray-900">${esc(log.name)}</p>
                            <p class="text-xs text-gray-400">${esc(log.id_number ?? '—')}</p>
                        </div>
                    </td>
                    <td class="px-5 py-4 sm:px-6">${roleBadge(log.role)}</td>
                    <td class="whitespace-nowrap px-5 py-4 sm:px-6">${directionCell(log.action)}</td>
                    <td class="whitespace-nowrap px-5 py-4 text-gray-700 sm:px-6">${esc(log.gate)}</td>
                    <td class="px-5 py-4 sm:px-6">${resultBadge(log.granted)}</td>
                </tr>
            `).join('');

            if (window.lucide) window.lucide.createIcons();
        };

        const renderDenied = (rows) => {
            const box = document.getElementById('recent-denied-list');
            if (!box) return;

            if (!rows.length) {
                box.innerHTML = `<p class="rounded-xl border border-dashed border-red-200 bg-white/70 px-4 py-8 text-center text-sm text-red-800/70">No denied access attempts recently.</p>`;
                return;
            }

            box.innerHTML = rows.map((denied) => `
                <div class="flex flex-col gap-3 rounded-xl border border-red-100 bg-white p-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0">
                        <p class="font-semibold text-gray-900">${esc(denied.name)}</p>
                        <p class="mt-0.5 text-sm text-red-600">${esc(denied.reason || 'Access denied')}</p>
                        <p class="mt-2 text-xs text-gray-500">
                            ${esc(denied.timestamp)}
                            <span class="mx-1">•</span>
                            ${esc(denied.gate)}
                        </p>
                    </div>
                    <span class="inline-flex w-fit shrink-0 items-center gap-1 rounded-full bg-red-500 px-3 py-1 text-xs font-semibold text-white">
                        <i data-lucide="x" class="h-3.5 w-3.5"></i>
                        Denied
                    </span>
                </div>
            `).join('');

            if (window.lucide) window.lucide.createIcons();
        };

        const updateStats = (stats) => {
            if (!stats) return;
            const map = {
                'stat-total': stats.total,
                'stat-entries': stats.entries_granted,
                'stat-exits': stats.exits_granted,
                'stat-denied': stats.access_denied,
            };
            Object.entries(map).forEach(([id, value]) => {
                const el = document.getElementById(id);
                if (el) el.textContent = Number(value || 0).toLocaleString();
            });

            const heading = document.getElementById('access-records-heading');
            if (heading) {
                heading.textContent = `Access Records (${Number(stats.total || 0).toLocaleString()})`;
            }
        };

        const fetchResults = async ({ force = false } = {}) => {
            if (!form) return;
            if (busy && !force) return;

            const params = currentParams();
            const queryKey = params.toString();
            const thisRequest = ++requestId;
            busy = true;

            try {
                const response = await fetch(`${eventsUrl}?${queryKey}`, {
                    headers: { Accept: 'application/json' },
                    cache: 'no-store',
                });
                if (!response.ok) return;
                if (thisRequest !== requestId) return;

                const data = await response.json();
                lastId = data.newest_id || lastId;
                updateStats(data.stats);
                renderLogs(data.logs || []);
                renderDenied(data.recent_denied || []);

                const nextUrl = queryKey ? `${clearRoute}?${queryKey}` : clearRoute;
                window.history.replaceState({}, '', nextUrl);
            } catch (e) {
                // keep UI usable
            } finally {
                if (thisRequest === requestId) {
                    busy = false;
                }
            }
        };

        const scheduleSearch = () => {
            window.clearTimeout(searchTimer);
            searchTimer = window.setTimeout(() => fetchResults({ force: true }), 250);
        };

        if (form) {
            if (searchInput) {
                searchInput.addEventListener('input', scheduleSearch);
            }
            form.querySelectorAll('select[name="type"], select[name="direction"], select[name="result"]').forEach((select) => {
                select.removeAttribute('onchange');
                select.addEventListener('change', () => fetchResults({ force: true }));
            });
            form.addEventListener('submit', (event) => {
                event.preventDefault();
                window.clearTimeout(searchTimer);
                fetchResults({ force: true });
            });
        }

        const onGateScan = (scan) => {
            if (!scan?.id || String(scan.id) === String(lastId)) {
                return;
            }

            const params = form ? currentParams() : new URLSearchParams();
            const q = (params.get('q') || '').trim().toLowerCase();
            const type = params.get('type') || 'all';
            const direction = params.get('direction') || 'all';
            const result = params.get('result') || 'all';

            const matchesType = type === 'all' || String(scan.role || '') === type;
            const matchesDirection = direction === 'all' || String(scan.action || '') === direction;
            const matchesResult = result === 'all'
                || (result === 'Granted' && !!scan.granted)
                || (result === 'Denied' && !scan.granted);
            const hay = [
                scan.name,
                scan.id_number,
                scan.rfid_uid_full,
                scan.rfid_uid,
                scan.gate_label,
                scan.gate_id,
                scan.plate_number,
            ].map((v) => String(v || '').toLowerCase()).join(' ');
            const matchesQ = q === '' || hay.includes(q);

            lastId = String(scan.id);

            const bump = (id) => {
                const el = document.getElementById(id);
                if (!el) return;
                el.textContent = (Number(String(el.textContent).replace(/,/g, '')) + 1).toLocaleString();
            };

            bump('stat-total');
            const heading = document.getElementById('access-records-heading');
            const totalEl = document.getElementById('stat-total');
            if (heading && totalEl) {
                heading.textContent = `Access Records (${totalEl.textContent})`;
            }
            if (scan.granted && scan.action === 'Entry') bump('stat-entries');
            if (scan.granted && scan.action === 'Exit') bump('stat-exits');
            if (!scan.granted) bump('stat-denied');

            if (matchesType && matchesDirection && matchesResult && matchesQ) {
                const tbody = document.querySelector('#access-records-body');
                if (tbody) {
                    if (tbody.querySelector('td[colspan]')) {
                        tbody.innerHTML = '';
                    }
                    const gate = scan.gate_label || scan.gate_id || '—';
                    tbody.insertAdjacentHTML('afterbegin', `
                        <tr class="hover:bg-gray-50/80">
                            <td class="whitespace-nowrap px-5 py-4 text-gray-700 sm:px-6">${esc(scan.timestamp)}</td>
                            <td class="px-5 py-4 sm:px-6">
                                <div class="min-w-0">
                                    <p class="truncate font-semibold text-gray-900">${esc(scan.name)}</p>
                                    <p class="text-xs text-gray-400">${esc(scan.id_number ?? '—')}</p>
                                </div>
                            </td>
                            <td class="px-5 py-4 sm:px-6">${roleBadge(scan.role)}</td>
                            <td class="whitespace-nowrap px-5 py-4 sm:px-6">${directionCell(scan.action)}</td>
                            <td class="whitespace-nowrap px-5 py-4 text-gray-700 sm:px-6">${esc(gate)}</td>
                            <td class="px-5 py-4 sm:px-6">${resultBadge(!!scan.granted)}</td>
                        </tr>
                    `);
                    while (tbody.querySelectorAll('tr').length > 30) {
                        tbody.removeChild(tbody.lastElementChild);
                    }
                    if (window.lucide) window.lucide.createIcons();
                }
            }

            if (!scan.granted) {
                const box = document.getElementById('recent-denied-list');
                if (box) {
                    if (box.querySelector('p.rounded-xl')) {
                        box.innerHTML = '';
                    }
                    box.insertAdjacentHTML('afterbegin', `
                        <div class="flex flex-col gap-3 rounded-xl border border-red-100 bg-white p-4 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0">
                                <p class="font-semibold text-gray-900">${esc(scan.name)}</p>
                                <p class="mt-0.5 text-sm text-red-600">${esc(scan.reason || 'Access denied')}</p>
                                <p class="mt-2 text-xs text-gray-500">
                                    ${esc(scan.timestamp)}
                                    <span class="mx-1">•</span>
                                    ${esc(scan.gate_label || scan.gate_id || '—')}
                                </p>
                            </div>
                            <span class="inline-flex w-fit shrink-0 items-center gap-1 rounded-full bg-red-500 px-3 py-1 text-xs font-semibold text-white">
                                <i data-lucide="x" class="h-3.5 w-3.5"></i>
                                Denied
                            </span>
                        </div>
                    `);
                    while (box.children.length > 5) {
                        box.removeChild(box.lastElementChild);
                    }
                    if (window.lucide) window.lucide.createIcons();
                }
            }
        };

        const subscribeAccessLogs = (echo) => {
            if (!echo) return;
            echo.private('gate.scans').listen('.GateScanProcessed', onGateScan);
        };

        if (typeof window.whenEchoReady === 'function') {
            window.whenEchoReady(subscribeAccessLogs);
        } else {
            window.addEventListener('echo:ready', () => subscribeAccessLogs(window.Echo), { once: true });
        }
    })();
</script>
@endpush
