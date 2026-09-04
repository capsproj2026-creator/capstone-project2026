@extends('layouts.admin')

@section('title', 'Admin Dashboard')

@section('content')
    @include('partials.shell.page-header', [
        'title' => 'Dashboard',
        'subtitle' => 'Smart Campus Vehicle Management System Overview',
    ])

    @if ($usersWithSecondStrike->isNotEmpty())
        <div id="second-strike-alert" class="mb-6 hidden gap-3 rounded-xl border border-orange-200 bg-orange-50 p-4 sm:p-5" data-second-strike-alert>
            <i data-lucide="triangle-alert" class="mt-0.5 h-5 w-5 shrink-0 text-orange-500"></i>
            <div class="min-w-0 flex-1">
                <div class="flex items-start justify-between gap-3">
                    <p class="font-semibold text-orange-900">Users with 2nd Strike Alert</p>
                    <button type="button" id="second-strike-dismiss" class="rounded-md px-2 py-1 text-xs font-medium text-orange-700 hover:bg-orange-100">Dismiss</button>
                </div>
                <p class="mt-1 text-sm text-orange-800">
                    <span id="second-strike-count">0</span> new second-strike user(s). One more violation will result in suspension.
                </p>
                <ul id="second-strike-list" class="mt-3 space-y-1 text-sm text-orange-900"></ul>
            </div>
        </div>
        @push('scripts')
        <script>
            (() => {
                const STORAGE_KEY = 'admin_seen_second_strike_ids';
                const users = @json($usersWithSecondStrike->map(fn ($u) => [
                    'id' => (string) $u->id,
                    'name' => $u->name,
                    'id_number' => $u->id_number,
                    'role' => strtolower($u->displayRoleLabel()),
                    'updated_at' => optional($u->updated_at)?->toIso8601String(),
                ])->values());
                const alertEl = document.getElementById('second-strike-alert');
                const listEl = document.getElementById('second-strike-list');
                const countEl = document.getElementById('second-strike-count');
                if (!alertEl || !listEl) return;

                const readSeen = () => {
                    try {
                        const raw = sessionStorage.getItem(STORAGE_KEY);
                        const parsed = raw ? JSON.parse(raw) : [];
                        return Array.isArray(parsed) ? parsed.map(String) : [];
                    } catch (e) {
                        return [];
                    }
                };
                const writeSeen = (ids) => {
                    try { sessionStorage.setItem(STORAGE_KEY, JSON.stringify([...new Set(ids.map(String))])); } catch (e) {}
                };

                const seen = new Set(readSeen());
                const fresh = users.filter((u) => !seen.has(String(u.id)));
                if (!fresh.length) return;

                countEl.textContent = String(fresh.length);
                listEl.innerHTML = fresh.map((u) =>
                    `<li>• ${u.name} (${u.id_number || '—'}) — ${u.role || 'user'}</li>`
                ).join('');
                alertEl.classList.remove('hidden');
                alertEl.classList.add('flex');

                const dismiss = () => {
                    writeSeen([...seen, ...fresh.map((u) => u.id)]);
                    alertEl.classList.add('hidden');
                    alertEl.classList.remove('flex');
                };
                document.getElementById('second-strike-dismiss')?.addEventListener('click', dismiss);
                window.setTimeout(dismiss, 10000);
            })();
        </script>
        @endpush
    @endif

    {{-- Summary cards --}}
    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="portal-card p-5">
            <div class="mb-3 flex items-start justify-between">
                <p class="text-sm font-medium portal-muted">Total Users</p>
                <span class="portal-stat-icon portal-stat-icon--blue"><i data-lucide="users" class="h-4 w-4"></i></span>
            </div>
            <p class="portal-stat-value portal-stat-value--success text-3xl font-bold tracking-tight">{{ number_format($totalUsers) }}</p>
            <p class="mt-2 text-sm">
                <span class="font-medium portal-text-success">{{ number_format($activeUsers) }} active</span>
                <span class="text-gray-400 dark:text-slate-600"> • </span>
                <span @class(['font-medium', 'portal-text-alert' => ($suspendedUsers ?? 0) > 0, 'portal-muted' => ($suspendedUsers ?? 0) == 0])>{{ number_format($suspendedUsers) }} suspended</span>
            </p>
        </div>

        <div class="portal-card p-5">
            <div class="mb-3 flex items-start justify-between">
                <p class="text-sm font-medium portal-muted">Active Violations</p>
                <span class="portal-stat-icon portal-stat-icon--red"><i data-lucide="triangle-alert" class="h-4 w-4"></i></span>
            </div>
            <p @class([
                'text-3xl font-bold tracking-tight',
                'portal-stat-value--success' => ($activeViolations ?? 0) == 0,
                'portal-stat-value--alert' => ($activeViolations ?? 0) > 0,
            ])>{{ number_format($activeViolations) }}</p>
            <p class="mt-2 text-sm font-medium portal-muted">3-Strike System</p>
        </div>

        <div class="portal-card p-5">
            <div class="mb-3 flex items-start justify-between">
                <p class="text-sm font-medium portal-muted">Today's Activity</p>
                <span class="portal-stat-icon portal-stat-icon--violet"><i data-lucide="trending-up" class="h-4 w-4"></i></span>
            </div>
            <p class="portal-stat-value portal-stat-value--success text-3xl font-bold tracking-tight">{{ number_format($todayActivity) }}</p>
            <p class="mt-2 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm portal-muted">
                <span class="inline-flex items-center gap-1">
                    <i data-lucide="log-in" class="h-3.5 w-3.5 text-[var(--cspc-action)]"></i>
                    {{ number_format($todayEntries) }} entries
                </span>
                <span class="text-gray-300 dark:text-slate-600">•</span>
                <span class="inline-flex items-center gap-1">
                    <i data-lucide="log-out" class="h-3.5 w-3.5 text-[var(--cspc-pie-1)]"></i>
                    {{ number_format($todayExits) }} exits
                </span>
            </p>
        </div>

        <div class="portal-card p-5">
            <div class="mb-3 flex items-start justify-between">
                <p class="text-sm font-medium portal-muted">Parking</p>
                <span class="portal-stat-icon portal-stat-icon--blue"><i data-lucide="parking-square" class="h-4 w-4"></i></span>
            </div>
            <p class="portal-stat-value text-3xl font-bold tracking-tight">
                {{ number_format($occupiedSlots) }}/{{ number_format($totalSlots) }}
            </p>
            <p class="mt-2 text-sm font-medium portal-muted">{{ $parkingAvailablePercent }}% available</p>
        </div>
    </div>

    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="portal-card p-5">
            <div class="mb-3 flex items-start justify-between">
                <p class="text-sm font-medium portal-muted">Visitors Today</p>
                <span class="portal-stat-icon portal-stat-icon--slate"><i data-lucide="clipboard-plus" class="h-4 w-4"></i></span>
            </div>
            <p class="portal-stat-value text-3xl font-bold tracking-tight">{{ number_format($visitorsToday ?? 0) }}</p>
        </div>
        <div class="portal-card p-5">
            <div class="mb-3 flex items-start justify-between">
                <p class="text-sm font-medium portal-muted">Active Visitors</p>
                <span class="portal-stat-icon portal-stat-icon--emerald"><i data-lucide="user-round-check" class="h-4 w-4"></i></span>
            </div>
            <p class="portal-stat-value portal-stat-value--success text-3xl font-bold tracking-tight">{{ number_format($activeVisitors ?? 0) }}</p>
        </div>
        <div class="portal-card p-5">
            <div class="mb-3 flex items-start justify-between">
                <p class="text-sm font-medium portal-muted">Completed Visits</p>
                <span class="portal-stat-icon portal-stat-icon--slate"><i data-lucide="history" class="h-4 w-4"></i></span>
            </div>
            <p class="portal-stat-value text-3xl font-bold tracking-tight">{{ number_format($completedVisits ?? 0) }}</p>
        </div>
        <div class="portal-card p-5">
            <div class="mb-3 flex items-start justify-between">
                <p class="text-sm font-medium portal-muted">Expired Visitors</p>
                <span class="portal-stat-icon portal-stat-icon--rose"><i data-lucide="clock" class="h-4 w-4"></i></span>
            </div>
            <p @class([
                'text-3xl font-bold tracking-tight',
                'portal-stat-value' => ($expiredVisitors ?? 0) == 0,
                'portal-stat-value--alert' => ($expiredVisitors ?? 0) > 0,
            ])>{{ number_format($expiredVisitors ?? 0) }}</p>
        </div>
    </div>

    {{-- Charts --}}
    <div class="mb-6 grid grid-cols-1 gap-4 lg:grid-cols-2 lg:items-stretch">
        <div class="portal-card flex h-[380px] flex-col p-6">
            <div class="mb-4 flex shrink-0 items-center justify-between gap-3">
                <h3 class="portal-heading text-base font-semibold">Weekly Entry/Exit Trends</h3>
                <div class="hidden items-center gap-3 text-xs portal-muted sm:flex">
                    <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-[#6EE7B7]"></span> Entries</span>
                    <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-[#93C5FD]"></span> Exits</span>
                </div>
            </div>
            <div class="relative min-h-0 flex-1">
                <canvas id="chart-weekly-trends"></canvas>
            </div>
        </div>

        <div class="portal-card flex h-[380px] flex-col p-6">
            <h3 class="portal-heading mb-4 shrink-0 text-base font-semibold">Violation Types Distribution</h3>
            <div class="grid min-h-0 flex-1 grid-cols-1 gap-4 sm:grid-cols-[1fr_auto]">
                <div class="relative min-h-[220px]">
                    <canvas id="chart-violation-types"></canvas>
                    <div class="portal-donut-center" id="violation-donut-center">
                        <span class="portal-donut-center-value" id="violation-donut-total">{{ number_format($activeViolations) }}</span>
                        <span class="portal-donut-center-label">Total Violations</span>
                    </div>
                </div>
                <div class="portal-chart-legend justify-center sm:min-w-[170px]" id="violation-chart-legend"></div>
            </div>
        </div>
    </div>

    {{-- Recent Violations + Quick Actions --}}
    <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
        <div class="portal-card">
            <div class="portal-card-header flex items-center justify-between gap-3 px-5 py-4 sm:px-6">
                <h3 class="portal-heading text-base font-semibold">Recent Violations</h3>
                <a
                    href="{{ route('admin.violations') }}"
                    class="portal-btn-outline rounded-lg px-3 py-1.5 text-sm font-medium text-[var(--portal-text)] hover:bg-[var(--portal-bg-subtle)]"
                >
                    View All
                </a>
            </div>

            <div class="divide-y divide-gray-100 dark:divide-[var(--portal-border)]">
                @forelse ($recentViolations as $violation)
                    @php
                        $typeLabel = strtolower(trim((string) ($violation->violation_type ?? 'violation')));
                        $metaParts = array_filter([
                            $violation->plate_number ? 'Plate '.$violation->plate_number : null,
                            $violation->created_at ? ph_date($violation->created_at, 'n/j/Y') : null,
                        ]);
                    @endphp
                    <div class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-start sm:justify-between sm:px-6">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="font-semibold text-gray-900">{{ $violation->violator_name }}</p>
                                <span class="inline-flex rounded-full bg-[rgba(248,113,113,0.14)] px-2.5 py-0.5 text-xs font-semibold lowercase text-[#F87171]">
                                    {{ $typeLabel }}
                                </span>
                            </div>
                            <p class="mt-1 text-sm text-gray-600">
                                {{ $violation->description ?: 'No description provided.' }}
                            </p>
                            <p class="mt-1.5 text-xs text-gray-400">
                                {{ $metaParts !== [] ? implode(' • ', $metaParts) : 'Campus' }}
                            </p>
                            <x-violation.evidence-panel
                                :log="$violation"
                                route-name="admin.violations.evidence"
                                compact
                                class="mt-2"
                            />
                        </div>
                        <span @class([
                            'inline-flex shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold',
                            'bg-gray-100 text-gray-700' => ($violation->status ?? '') !== 'Resolved',
                            'bg-emerald-50 text-emerald-700' => ($violation->status ?? '') === 'Resolved',
                        ])>
                            {{ ($violation->status ?? '') === 'Resolved' ? 'Resolved' : 'Strike Recorded' }}
                        </span>
                    </div>
                @empty
                    <div class="flex flex-col items-center justify-center px-5 py-16 text-center sm:px-6">
                        <div class="mb-5 flex h-20 w-20 items-center justify-center rounded-2xl border border-[var(--cspc-action)]/20 bg-[rgba(93,159,209,0.1)]">
                            <i data-lucide="clipboard-list" class="h-10 w-10 text-[var(--cspc-action)]"></i>
                        </div>
                        <p class="text-sm font-medium portal-muted">No violations recorded yet.</p>
                    </div>
                @endforelse
            </div>
        </div>

        <div class="portal-card p-5 sm:p-6">
            <h3 class="portal-heading mb-4 text-base font-semibold">Quick Actions</h3>
            <div class="space-y-3">
                <a
                    href="{{ route('admin.registrations') }}"
                    class="portal-btn-gradient flex items-center justify-center gap-2 rounded-xl px-4 py-3.5 text-sm font-semibold"
                >
                    <i data-lucide="user-plus" class="h-4 w-4"></i>
                    Registrations
                </a>
                <a
                    href="{{ route('admin.rfid') }}"
                    class="portal-btn-gradient flex items-center justify-center gap-2 rounded-xl px-4 py-3.5 text-sm font-semibold"
                >
                    <i data-lucide="hash" class="h-4 w-4"></i>
                    RFID Assignment
                </a>
                <a
                    href="{{ route('admin.users') }}"
                    class="portal-btn-gradient flex items-center justify-center gap-2 rounded-xl px-4 py-3.5 text-sm font-semibold"
                >
                    <i data-lucide="users" class="h-4 w-4"></i>
                    User Management
                </a>
                <a
                    href="{{ route('admin.reports') }}"
                    class="portal-btn-gradient flex items-center justify-center gap-2 rounded-xl px-4 py-3.5 text-sm font-semibold"
                >
                    <i data-lucide="bar-chart-3" class="h-4 w-4"></i>
                    Generate Report
                </a>
                <a
                    href="{{ route('admin.parking') }}"
                    class="portal-btn-gradient flex items-center justify-center gap-2 rounded-xl px-4 py-3.5 text-sm font-semibold"
                >
                    <i data-lucide="parking-square" class="h-4 w-4"></i>
                    View Parking Map
                </a>
            </div>

            @if (($pending ?? 0) > 0)
                <p class="mt-4 text-center text-xs text-gray-500">
                    {{ number_format($pending) }} registration{{ $pending === 1 ? '' : 's' }} awaiting review
                </p>
            @endif
        </div>
    </div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.8/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js"></script>
<script>
(() => {
    if (window.lucide) window.lucide.createIcons();
    if (window.ChartDataLabels) Chart.register(ChartDataLabels);

    const isDark = () => document.documentElement.classList.contains('dark');
    const chartGrid = () => (isDark() ? 'rgba(148, 163, 184, 0.1)' : '#E5E7EB');
    const chartTick = () => (isDark() ? '#9ca8b9' : '#6B7280');
    const chartBorder = () => (isDark() ? 'rgba(100, 116, 139, 0.35)' : '#D1D5DB');
    const pieBorder = () => (isDark() ? '#171d28' : '#ffffff');

    const weekly = @json($weeklyTrends);
    const distribution = @json($violationTypeDistribution);

    let weeklyChart = null;
    let pieChart = null;

    const renderViolationLegend = () => {
        const legendEl = document.getElementById('violation-chart-legend');
        if (!legendEl) return;
        const labels = distribution.labels || [];
        const colors = distribution.colors || [];
        const percents = distribution.percents || [];
        legendEl.innerHTML = labels.map((label, i) => `
            <div class="portal-chart-legend-item">
                <span class="portal-chart-legend-dot" style="background:${colors[i] || '#64748b'}"></span>
                <span>${label}: ${percents[i] ?? 0}%</span>
            </div>
        `).join('');
    };
    renderViolationLegend();

    const weeklyCanvas = document.getElementById('chart-weekly-trends');
    if (weeklyCanvas && window.Chart) {
        const buildWeekly = () => {
            const entries = weekly.entries || weekly.values || [];
            const exits = weekly.exits || [];
            const peak = Math.max(0, ...entries, ...exits);
            const yMax = peak <= 10
                ? Math.max(6, Math.ceil(peak / 2) * 2)
                : Math.max(45, Math.ceil(peak / 45) * 45);
            const yStep = peak <= 10 ? 2 : 45;

            const config = {
                type: 'bar',
                data: {
                    labels: weekly.labels,
                    datasets: [
                        {
                            label: 'Entries',
                            data: entries,
                            backgroundColor: isDark() ? 'rgba(110, 231, 183, 0.85)' : '#6EE7B7',
                            borderRadius: 3,
                            borderSkipped: false,
                            maxBarThickness: 22,
                        },
                        {
                            label: 'Exits',
                            data: exits,
                            backgroundColor: isDark() ? 'rgba(147, 197, 253, 0.85)' : '#93C5FD',
                            borderRadius: 3,
                            borderSkipped: false,
                            maxBarThickness: 22,
                        },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    layout: { padding: { top: 8, right: 8 } },
                    plugins: {
                        legend: { display: false },
                        datalabels: { display: false },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                        },
                    },
                    scales: {
                        x: {
                            grid: { display: false, drawTicks: false },
                            ticks: { color: chartTick(), font: { size: 11 } },
                            border: { display: true, color: chartBorder() },
                        },
                        y: {
                            beginAtZero: true,
                            min: 0,
                            max: yMax,
                            ticks: {
                                color: chartTick(),
                                font: { size: 11 },
                                stepSize: yStep,
                                precision: 0,
                            },
                            grid: {
                                color: chartGrid(),
                                borderDash: [4, 4],
                                drawTicks: false,
                            },
                            border: { display: false },
                        },
                    },
                },
            };

            if (weeklyChart) weeklyChart.destroy();
            weeklyChart = new Chart(weeklyCanvas, config);
        };
        buildWeekly();
        window.addEventListener('portal:theme-change', buildWeekly);
    }

    const pieCanvas = document.getElementById('chart-violation-types');
    if (pieCanvas && window.Chart) {
        const buildPie = () => {
            const colors = distribution.colors || [];
            const labels = distribution.labels || [];
            const values = distribution.values || [];
            const total = distribution.total ?? values.reduce((a, b) => a + b, 0);
            const hasData = total > 0;

            const centerTotal = document.getElementById('violation-donut-total');
            if (centerTotal) centerTotal.textContent = String(total);

            const config = {
                type: 'doughnut',
                data: {
                    labels,
                    datasets: [{
                        data: hasData ? values : [1],
                        backgroundColor: hasData ? colors : [isDark() ? 'rgba(51, 65, 85, 0.55)' : '#e2e8f0'],
                        borderWidth: 2,
                        borderColor: pieBorder(),
                        hoverOffset: 4,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '68%',
                    layout: { padding: 8 },
                    plugins: {
                        legend: { display: false },
                        datalabels: { display: false },
                        tooltip: {
                            enabled: hasData,
                            callbacks: {
                                label: (ctx) => {
                                    const pct = distribution.percents?.[ctx.dataIndex] ?? 0;
                                    return ` ${ctx.label}: ${pct}%`;
                                },
                            },
                        },
                    },
                },
            };

            if (pieChart) pieChart.destroy();
            pieChart = new Chart(pieCanvas, config);
        };
        buildPie();
        window.addEventListener('portal:theme-change', buildPie);
    }
})();
</script>
@endpush
