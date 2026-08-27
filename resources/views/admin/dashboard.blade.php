@extends('layouts.admin')

@section('title', 'Admin Dashboard')

@section('content')
    @include('partials.shell.page-header', [
        'title' => 'Dashboard',
        'subtitle' => 'Smart Campus Vehicle Management System Overview',
    ])

    @if ($usersWithSecondStrike->isNotEmpty())
        <div class="mb-6 flex gap-3 rounded-xl border border-orange-200 bg-orange-50 p-4 sm:p-5">
            <i data-lucide="triangle-alert" class="mt-0.5 h-5 w-5 shrink-0 text-orange-500"></i>
            <div class="min-w-0">
                <p class="font-semibold text-orange-900">Users with 2nd Strike Alert</p>
                <p class="mt-1 text-sm text-orange-800">
                    {{ $usersWithSecondStrike->count() }} user(s) currently have 2 violations. One more violation will result in suspension.
                </p>
                <ul class="mt-3 space-y-1 text-sm text-orange-900">
                    @foreach ($usersWithSecondStrike as $atRisk)
                        <li>
                            • {{ $atRisk->name }} ({{ $atRisk->id_number }})
                            — {{ strtolower($atRisk->roleName()) }}
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    {{-- Summary cards --}}
    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="mb-3 flex items-start justify-between">
                <p class="text-sm font-medium text-gray-500">Total Users</p>
                <i data-lucide="users" class="h-5 w-5 text-gray-400"></i>
            </div>
            <p class="text-3xl font-bold tracking-tight text-gray-900">{{ number_format($totalUsers) }}</p>
            <p class="mt-2 text-sm">
                <span class="font-medium text-emerald-600">{{ number_format($activeUsers) }} active</span>
                <span class="text-gray-400"> • </span>
                <span class="font-medium text-red-600">{{ number_format($suspendedUsers) }} suspended</span>
            </p>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="mb-3 flex items-start justify-between">
                <p class="text-sm font-medium text-gray-500">Active Violations</p>
                <i data-lucide="triangle-alert" class="h-5 w-5 text-gray-400"></i>
            </div>
            <p class="text-3xl font-bold tracking-tight text-gray-900">{{ number_format($activeViolations) }}</p>
            <p class="mt-2 text-sm font-medium text-red-600">3-Strike System</p>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="mb-3 flex items-start justify-between">
                <p class="text-sm font-medium text-gray-500">Today's Activity</p>
                <i data-lucide="trending-up" class="h-5 w-5 text-gray-400"></i>
            </div>
            <p class="text-3xl font-bold tracking-tight text-gray-900">{{ number_format($todayActivity) }}</p>
            <p class="mt-2 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-gray-500">
                <span class="inline-flex items-center gap-1">
                    <i data-lucide="log-in" class="h-3.5 w-3.5 text-blue-600"></i>
                    {{ number_format($todayEntries) }} entries
                </span>
                <span class="text-gray-300">•</span>
                <span class="inline-flex items-center gap-1">
                    <i data-lucide="log-out" class="h-3.5 w-3.5 text-purple-600"></i>
                    {{ number_format($todayExits) }} exits
                </span>
            </p>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="mb-3 flex items-start justify-between">
                <p class="text-sm font-medium text-gray-500">Parking</p>
                <i data-lucide="parking-square" class="h-5 w-5 text-gray-400"></i>
            </div>
            <p class="text-3xl font-bold tracking-tight text-gray-900">
                {{ number_format($occupiedSlots) }}/{{ number_format($totalSlots) }}
            </p>
            <p class="mt-2 text-sm font-medium text-blue-600">{{ $parkingAvailablePercent }}% available</p>
        </div>
    </div>

    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="mb-3 flex items-start justify-between">
                <p class="text-sm font-medium text-gray-500">Visitors Today</p>
                <i data-lucide="clipboard-plus" class="h-5 w-5 text-gray-400"></i>
            </div>
            <p class="text-3xl font-bold tracking-tight text-gray-900">{{ number_format($visitorsToday ?? 0) }}</p>
        </div>
        <div class="rounded-xl border border-teal-100 bg-white p-5 shadow-sm">
            <div class="mb-3 flex items-start justify-between">
                <p class="text-sm font-medium text-gray-500">Active Visitors</p>
                <i data-lucide="user-round-check" class="h-5 w-5 text-teal-500"></i>
            </div>
            <p class="text-3xl font-bold tracking-tight text-teal-700">{{ number_format($activeVisitors ?? 0) }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="mb-3 flex items-start justify-between">
                <p class="text-sm font-medium text-gray-500">Completed Visits</p>
                <i data-lucide="history" class="h-5 w-5 text-gray-400"></i>
            </div>
            <p class="text-3xl font-bold tracking-tight text-gray-900">{{ number_format($completedVisits ?? 0) }}</p>
        </div>
        <div class="rounded-xl border border-rose-100 bg-white p-5 shadow-sm">
            <div class="mb-3 flex items-start justify-between">
                <p class="text-sm font-medium text-gray-500">Expired Visitors</p>
                <i data-lucide="clock" class="h-5 w-5 text-rose-500"></i>
            </div>
            <p class="text-3xl font-bold tracking-tight text-rose-700">{{ number_format($expiredVisitors ?? 0) }}</p>
        </div>
    </div>

    {{-- Charts: matched card height --}}
    <div class="mb-6 grid grid-cols-1 gap-4 lg:grid-cols-2 lg:items-stretch">
        <div class="flex h-[380px] flex-col rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            <h3 class="mb-4 shrink-0 text-base font-semibold text-gray-900">Weekly Entry/Exit Trends</h3>
            <div class="relative min-h-0 flex-1">
                <canvas id="chart-weekly-trends"></canvas>
            </div>
        </div>

        <div class="flex h-[380px] flex-col rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            <h3 class="mb-4 shrink-0 text-base font-semibold text-gray-900">Violation Types Distribution</h3>
            <div class="relative min-h-0 flex-1">
                <canvas id="chart-violation-types"></canvas>
            </div>
        </div>
    </div>

    {{-- Recent Violations + Quick Actions --}}
    <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
        <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-5 py-4 sm:px-6">
                <h3 class="text-base font-semibold text-gray-900">Recent Violations</h3>
                <a
                    href="{{ route('admin.violations') }}"
                    class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50"
                >
                    View All
                </a>
            </div>

            <div class="divide-y divide-gray-100">
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
                                <span class="inline-flex rounded-full bg-red-50 px-2.5 py-0.5 text-xs font-semibold lowercase text-red-700">
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
                    <p class="px-5 py-10 text-center text-sm text-gray-500 sm:px-6">No violations recorded yet.</p>
                @endforelse
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
            <h3 class="mb-4 text-base font-semibold text-gray-900">Quick Actions</h3>
            <div class="space-y-3">
                <a
                    href="{{ route('admin.registrations') }}"
                    class="flex items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-3.5 text-sm font-semibold text-gray-800 transition hover:bg-gray-50"
                >
                    <i data-lucide="clipboard-list" class="h-4 w-4 text-gray-500"></i>
                    Registrations
                </a>
                <a
                    href="{{ route('admin.rfid') }}"
                    class="flex items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-3.5 text-sm font-semibold text-gray-800 transition hover:bg-gray-50"
                >
                    <i data-lucide="credit-card" class="h-4 w-4 text-gray-500"></i>
                    RFID Assignment
                </a>
                <a
                    href="{{ route('admin.users') }}"
                    class="flex items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-3.5 text-sm font-semibold text-gray-800 transition hover:bg-gray-50"
                >
                    <i data-lucide="users" class="h-4 w-4 text-gray-500"></i>
                    User Management
                </a>
                <a
                    href="{{ route('admin.reports') }}"
                    class="flex items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-3.5 text-sm font-semibold text-gray-800 transition hover:bg-gray-50"
                >
                    <i data-lucide="bar-chart-3" class="h-4 w-4 text-gray-500"></i>
                    Generate Report
                </a>
                <a
                    href="{{ route('admin.parking') }}"
                    class="flex items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-3.5 text-sm font-semibold text-gray-800 transition hover:bg-gray-50"
                >
                    <i data-lucide="parking-square" class="h-4 w-4 text-gray-500"></i>
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
    if (window.ChartDataLabels) {
        Chart.register(ChartDataLabels);
    }

    const weekly = @json($weeklyTrends);
    const distribution = @json($violationTypeDistribution);

    const weeklyCanvas = document.getElementById('chart-weekly-trends');
    if (weeklyCanvas && window.Chart) {
        const values = weekly.values || [];
        const peak = Math.max(0, ...values);
        const yMax = peak <= 10
            ? Math.max(6, Math.ceil(peak / 2) * 2)
            : Math.max(45, Math.ceil(peak / 45) * 45);
        const yStep = peak <= 10 ? 2 : 45;

        new Chart(weeklyCanvas, {
            type: 'bar',
            data: {
                labels: weekly.labels,
                datasets: [{
                    data: values,
                    backgroundColor: '#8B5CF6',
                    borderRadius: 2,
                    borderSkipped: false,
                    categoryPercentage: 0.65,
                    barPercentage: 0.85,
                    maxBarThickness: 48,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                layout: { padding: { top: 8, right: 12 } },
                plugins: {
                    legend: { display: false },
                    datalabels: { display: false },
                    tooltip: {
                        callbacks: {
                            title: (items) => items[0]?.label ?? '',
                            label: (ctx) => ` ${ctx.parsed.y} entries + exits`,
                        },
                    },
                },
                scales: {
                    x: {
                        grid: {
                            display: true,
                            color: '#E5E7EB',
                            borderDash: [4, 4],
                            drawTicks: false,
                        },
                        ticks: {
                            color: '#6B7280',
                            font: { size: 12 },
                        },
                        border: {
                            display: true,
                            color: '#D1D5DB',
                        },
                    },
                    y: {
                        beginAtZero: true,
                        min: 0,
                        max: yMax,
                        ticks: {
                            color: '#6B7280',
                            font: { size: 12 },
                            stepSize: yStep,
                            precision: 0,
                        },
                        grid: {
                            color: '#E5E7EB',
                            borderDash: [4, 4],
                            drawTicks: false,
                        },
                        border: {
                            display: true,
                            color: '#D1D5DB',
                        },
                    },
                },
            },
        });
    }

    const pieCanvas = document.getElementById('chart-violation-types');
    if (pieCanvas && window.Chart) {
        const colors = distribution.colors || [];
        const labels = distribution.labels || [];
        const percents = distribution.percents || [];
        const hasData = labels[0] !== 'No Data';

        new Chart(pieCanvas, {
            type: 'pie',
            data: {
                labels,
                datasets: [{
                    data: distribution.values,
                    backgroundColor: colors,
                    borderWidth: 2,
                    borderColor: '#ffffff',
                    hoverOffset: 4,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                layout: { padding: 28 },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: (ctx) => {
                                const pct = percents[ctx.dataIndex] ?? 0;
                                return ` ${ctx.label}: ${pct}%`;
                            },
                        },
                    },
                    datalabels: hasData ? {
                        display: true,
                        anchor: 'end',
                        align: 'end',
                        offset: 6,
                        clamp: true,
                        color: (ctx) => colors[ctx.dataIndex] || '#374151',
                        font: {
                            size: 12,
                            weight: '600',
                        },
                        formatter: (value, ctx) => {
                            const label = labels[ctx.dataIndex] || '';
                            const pct = percents[ctx.dataIndex] ?? 0;
                            if (!value || pct <= 0) return '';
                            return `${label}: ${pct}%`;
                        },
                    } : { display: false },
                },
            },
        });
    }
})();
</script>
@endpush
