@extends('layouts.admin')

@section('title', 'Reports')

@section('content')
    @php
        $chartPayload = [
            'monthlyAccess' => $monthlyAccessTrends,
            'userDistribution' => $userDistribution,
            'parkingDaily' => $parkingDailyPattern,
            'violationsLocation' => $violationsByLocation,
            'violationTrends' => $violationTrendsByType,
            'peakHours' => $peakEntryExitHours,
        ];
    @endphp

    @include('partials.shell.page-header', [
        'title' => 'Reports',
        'subtitle' => 'Live campus analytics from system records',
    ])

    {{-- Report type + exports --}}
    <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
        <div class="relative" data-report-type-dropdown>
            <button
                type="button"
                id="report-type-trigger"
                class="inline-flex min-w-[220px] items-center justify-between gap-3 rounded-lg border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm font-medium text-gray-900 shadow-sm hover:bg-white"
                aria-haspopup="listbox"
                aria-expanded="false"
            >
                <span id="report-type-label">{{ $reportTypes[$reportType] ?? 'All Reports' }}</span>
                <i data-lucide="chevron-down" class="h-4 w-4 text-gray-400"></i>
            </button>
            <div
                id="report-type-menu"
                class="absolute left-0 z-30 mt-2 hidden w-64 overflow-hidden rounded-xl border border-gray-200 bg-white py-1 shadow-lg"
                role="listbox"
            >
                @foreach ($reportTypes as $value => $label)
                    <button
                        type="button"
                        class="flex w-full items-center justify-between px-4 py-2.5 text-left text-sm text-gray-700 hover:bg-gray-50"
                        data-report-type="{{ $value }}"
                        role="option"
                        @if ($reportType === $value) aria-selected="true" @endif
                    >
                        <span>{{ $label }}</span>
                        <i data-lucide="check" class="h-4 w-4 text-gray-400 {{ $reportType === $value ? '' : 'hidden' }}" data-check-icon></i>
                    </button>
                @endforeach
            </div>
            <input type="hidden" id="report-type-input" value="{{ $reportType }}">
        </div>

        <a
            id="export-pdf-btn"
            href="{{ route('admin.reports.export-pdf', ['type' => $reportType, 'from' => $from, 'to' => $to]) }}"
            class="inline-flex items-center gap-2 rounded-lg bg-gray-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-black"
        >
            <i data-lucide="download" class="h-4 w-4"></i>
            Export PDF
        </a>
        <a
            id="export-excel-btn"
            href="{{ route('admin.reports.export-excel', ['type' => $reportType, 'from' => $from, 'to' => $to]) }}"
            class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-800 hover:bg-gray-50"
        >
            <i data-lucide="file-spreadsheet" class="h-4 w-4"></i>
            Export Excel
        </a>
    </div>

    <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <form method="GET" action="{{ route('admin.reports') }}" class="flex flex-wrap items-end gap-3">
            <input type="hidden" name="type" id="filter-report-type" value="{{ $reportType }}">
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-500">From</label>
                <input type="date" name="from" value="{{ $from }}" class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm shadow-sm">
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-500">To</label>
                <input type="date" name="to" value="{{ $to }}" class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm shadow-sm">
            </div>
            <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                Apply
            </button>
            <a href="{{ route('admin.reports') }}" class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-600 hover:bg-gray-50">
                Reset
            </a>
        </form>
    </div>

    {{-- Summary cards --}}
    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-sm text-gray-500">Total Users</p>
                    <p class="mt-1 text-3xl font-bold tracking-tight text-gray-900">{{ $totalUsers }}</p>
                    <p @class([
                        'mt-2 text-xs font-medium',
                        'text-green-600' => ($summaryTrends['users']['direction'] ?? '') === 'up',
                        'text-red-600' => ($summaryTrends['users']['direction'] ?? '') === 'down',
                        'text-gray-500' => ! in_array($summaryTrends['users']['direction'] ?? '', ['up', 'down'], true),
                    ])>{{ $summaryTrends['users']['label'] ?? '' }}</p>
                </div>
                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-blue-50 text-blue-600">
                    <i data-lucide="trending-up" class="h-5 w-5"></i>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-sm text-gray-500">Total Violations</p>
                    <p class="mt-1 text-3xl font-bold tracking-tight text-gray-900">{{ $totalViolations }}</p>
                    <p @class([
                        'mt-2 text-xs font-medium',
                        'text-red-600' => ($summaryTrends['violations']['direction'] ?? '') === 'up',
                        'text-green-600' => ($summaryTrends['violations']['direction'] ?? '') === 'down',
                        'text-gray-500' => ! in_array($summaryTrends['violations']['direction'] ?? '', ['up', 'down'], true),
                    ])>{{ $summaryTrends['violations']['label'] ?? '' }}</p>
                </div>
                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-red-50 text-red-600">
                    <i data-lucide="file-text" class="h-5 w-5"></i>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-sm text-gray-500">Access Logs</p>
                    <p class="mt-1 text-3xl font-bold tracking-tight text-gray-900">{{ $todayAccessLogs }}</p>
                    <p class="mt-2 text-xs font-medium text-gray-500">{{ $summaryTrends['access']['label'] ?? "Today's activity" }}</p>
                </div>
                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-violet-50 text-violet-600">
                    <i data-lucide="calendar" class="h-5 w-5"></i>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-sm text-gray-500">Parking Utilization</p>
                    <p class="mt-1 text-3xl font-bold tracking-tight text-gray-900">{{ $parkingUtilization }}%</p>
                    <p class="mt-2 text-xs font-medium text-teal-600">{{ $summaryTrends['parking']['label'] ?? '' }}</p>
                </div>
                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-emerald-50 text-emerald-600">
                    <i data-lucide="activity" class="h-5 w-5"></i>
                </div>
            </div>
        </div>
    </div>

    {{-- Charts row 1 --}}
    <div class="mb-6 grid grid-cols-1 gap-6 xl:grid-cols-2">
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <h3 class="mb-4 text-base font-semibold text-gray-900">Monthly Access Trends</h3>
            <div class="h-72">
                <canvas id="chart-monthly-access"></canvas>
            </div>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <h3 class="mb-4 text-base font-semibold text-gray-900">User Distribution</h3>
            <div class="mx-auto h-72 max-w-md">
                <canvas id="chart-user-distribution"></canvas>
            </div>
        </div>
    </div>

    {{-- Charts row 2 --}}
    <div class="mb-6 grid grid-cols-1 gap-6 xl:grid-cols-2">
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <h3 class="mb-4 text-base font-semibold text-gray-900">Parking Utilization — Daily Pattern</h3>
            <div class="h-72">
                <canvas id="chart-parking-daily"></canvas>
            </div>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <h3 class="mb-4 text-base font-semibold text-gray-900">Violations by Location</h3>
            <div class="h-72">
                <canvas id="chart-violations-location"></canvas>
            </div>
        </div>
    </div>

    {{-- Charts row 3 --}}
    <div class="mb-6 grid grid-cols-1 gap-6 xl:grid-cols-2">
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <h3 class="mb-4 text-base font-semibold text-gray-900">Violation Trends by Type</h3>
            <div class="h-72">
                <canvas id="chart-violation-trends"></canvas>
            </div>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <h3 class="mb-4 text-base font-semibold text-gray-900">Peak Entry/Exit Hours</h3>
            <div class="h-72">
                <canvas id="chart-peak-hours"></canvas>
            </div>
        </div>
    </div>

    {{-- Repeat Offenders --}}
    <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
        <h3 class="mb-4 text-base font-semibold text-gray-900">Repeat Offenders</h3>
        <div class="divide-y divide-gray-100">
            @forelse ($repeatOffenders as $offender)
                <div class="flex items-center gap-4 py-4">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-red-100 text-sm font-bold text-red-600">
                        #{{ $offender['rank'] }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-semibold text-gray-900">{{ $offender['name'] }}</p>
                        <p class="truncate text-sm text-gray-500">
                            {{ $offender['id_number'] }} • {{ $offender['user_type'] }}
                        </p>
                    </div>
                    <div class="shrink-0 text-right">
                        <p class="text-2xl font-bold text-red-600">{{ $offender['violations'] }}</p>
                        <p class="text-xs text-gray-500">violations</p>
                    </div>
                </div>
            @empty
                <p class="py-8 text-center text-sm text-gray-500">No repeat offenders recorded yet.</p>
            @endforelse
        </div>
    </div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.8/dist/chart.umd.min.js"></script>
<script>
(() => {
    const chartData = @json($chartPayload);

    const isDark = () => document.documentElement.classList.contains('dark');
    const gridColor = () => (isDark() ? 'rgba(148, 163, 184, 0.1)' : '#E5E7EB');
    const tickColor = () => (isDark() ? '#9ca8b9' : '#9CA3AF');
    const legendColor = () => (isDark() ? '#cbd5e1' : '#6B7280');
    const pieEmpty = () => (isDark() ? 'rgba(51, 65, 85, 0.55)' : '#E5E7EB');
    const reportCharts = [];

    const applyChartTheme = () => {
        reportCharts.forEach((chart) => {
            const scales = chart.options?.scales || {};
            if (scales.x?.grid) scales.x.grid.color = gridColor();
            if (scales.y?.grid) scales.y.grid.color = gridColor();
            if (scales.x?.ticks) scales.x.ticks.color = tickColor();
            if (scales.y?.ticks) scales.y.ticks.color = tickColor();
            const legend = chart.options?.plugins?.legend?.labels;
            if (legend && !legend.generateLabels) legend.color = legendColor();
            if (legend?.generateLabels) legend.color = legendColor();
            const pieColors = chart.data?.datasets?.[0]?.backgroundColor;
            if (Array.isArray(pieColors) && pieColors.length === 1 && (pieColors[0] === '#E5E7EB' || pieColors[0] === pieEmpty() || String(pieColors[0]).includes('51, 65, 85'))) {
                chart.data.datasets[0].backgroundColor = [pieEmpty()];
            }
            chart.update('none');
        });
    };

    const baseOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: { mode: 'index', intersect: false },
        },
        scales: {
            x: {
                grid: { color: gridColor, borderDash: [4, 4] },
                ticks: { color: tickColor, maxRotation: 0, autoSkip: true },
            },
            y: {
                beginAtZero: true,
                grid: { color: gridColor },
                ticks: { color: tickColor, precision: 0 },
            },
        },
    };

    const monthly = chartData.monthlyAccess || { labels: [], entries: [], exits: [] };
    reportCharts.push(new Chart(document.getElementById('chart-monthly-access'), {
        type: 'line',
        data: {
            labels: monthly.labels,
            datasets: [
                {
                    label: 'Entries',
                    data: monthly.entries,
                    borderColor: '#5D9FD1',
                    backgroundColor: 'rgba(93, 159, 209, 0.28)',
                    fill: true,
                    tension: 0.35,
                    pointRadius: 0,
                },
                {
                    label: 'Exits',
                    data: monthly.exits,
                    borderColor: '#93C5FD',
                    backgroundColor: 'rgba(147, 197, 253, 0.35)',
                    fill: true,
                    tension: 0.35,
                    pointRadius: 0,
                },
            ],
        },
        options: {
            ...baseOptions,
            plugins: {
                ...baseOptions.plugins,
                legend: { display: true, position: 'bottom', labels: { boxWidth: 12, color: legendColor() } } },
            },
            scales: {
                ...baseOptions.scales,
                y: { ...baseOptions.scales.y, stacked: false },
            },
        },
    });

    const dist = chartData.userDistribution || { labels: [], values: [], colors: [] };
    const distTotal = (dist.values || []).reduce((a, b) => a + b, 0);
    reportCharts.push(new Chart(document.getElementById('chart-user-distribution'), {
        type: 'pie',
        data: {
            labels: dist.labels,
            datasets: [{
                data: distTotal > 0 ? dist.values : [1],
                backgroundColor: distTotal > 0 ? dist.colors : [pieEmpty()],
                borderWidth: 0,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'right',
                    labels: {
                        color: legendColor(),
                        generateLabels(chart) {
                            const data = chart.data;
                            return (data.labels || []).map((label, i) => {
                                const value = data.datasets[0].data[i] || 0;
                                const pct = distTotal > 0 ? Math.round((value / distTotal) * 100) : 0;
                                return {
                                    text: `${label} (${pct}%)`,
                                    fillStyle: data.datasets[0].backgroundColor[i],
                                    strokeStyle: data.datasets[0].backgroundColor[i],
                                    lineWidth: 0,
                                    hidden: false,
                                    index: i,
                                };
                            });
                        },
                    },
                },
                tooltip: {
                    callbacks: {
                        label(ctx) {
                            const value = ctx.raw || 0;
                            const pct = distTotal > 0 ? Math.round((value / distTotal) * 100) : 0;
                            return ` ${ctx.label}: ${value} (${pct}%)`;
                        },
                    },
                },
            },
        },
    });

    const parking = chartData.parkingDaily || { labels: [], values: [], capacity: 0 };
    reportCharts.push(new Chart(document.getElementById('chart-parking-daily'), {
        type: 'line',
        data: {
            labels: parking.labels,
            datasets: [{
                label: 'Estimated occupancy',
                data: parking.values,
                borderColor: '#F87171',
                backgroundColor: 'rgba(248, 113, 113, 0.45)',
                fill: true,
                tension: 0.4,
                pointRadius: 0,
            }],
        },
        options: {
            ...baseOptions,
            scales: {
                ...baseOptions.scales,
                y: {
                    ...baseOptions.scales.y,
                    suggestedMax: Math.max(5, ...(parking.values || [0]), 10),
                },
            },
        },
    });

    const byLoc = chartData.violationsLocation || { labels: [], values: [] };
    reportCharts.push(new Chart(document.getElementById('chart-violations-location'), {
        type: 'bar',
        data: {
            labels: byLoc.labels.length ? byLoc.labels : ['No data'],
            datasets: [{
                data: byLoc.values.length ? byLoc.values : [0],
                backgroundColor: '#F87171',
                borderRadius: 6,
                maxBarThickness: 48,
            }],
        },
        options: baseOptions,
    });

    const trends = chartData.violationTrends || { labels: [], series: [] };
    reportCharts.push(new Chart(document.getElementById('chart-violation-trends'), {
        type: 'line',
        data: {
            labels: trends.labels,
            datasets: (trends.series || []).map((s) => ({
                label: s.label,
                data: s.data,
                borderColor: s.color,
                backgroundColor: s.color,
                tension: 0.35,
                pointRadius: 4,
                pointHoverRadius: 5,
            })),
        },
        options: {
            ...baseOptions,
            plugins: {
                ...baseOptions.plugins,
                legend: { display: true, position: 'bottom', labels: { boxWidth: 12, color: legendColor() } } },
            },
        },
    });

    const peak = chartData.peakHours || { labels: [], values: [] };
    reportCharts.push(new Chart(document.getElementById('chart-peak-hours'), {
        type: 'bar',
        data: {
            labels: peak.labels,
            datasets: [{
                data: peak.values,
                backgroundColor: '#5D9FD1',
                borderRadius: 4,
                maxBarThickness: 28,
            }],
        },
        options: {
            ...baseOptions,
            scales: {
                ...baseOptions.scales,
                x: {
                    ...baseOptions.scales.x,
                    ticks: { ...baseOptions.scales.x.ticks, maxRotation: 45, minRotation: 0, autoSkip: true, maxTicksLimit: 8 },
                },
            },
        },
    });

    window.addEventListener('portal:theme-change', applyChartTheme);

    if (window.lucide) window.lucide.createIcons();

    // Report type dropdown + export link sync
    const typeInput = document.getElementById('report-type-input');
    const typeLabel = document.getElementById('report-type-label');
    const trigger = document.getElementById('report-type-trigger');
    const menu = document.getElementById('report-type-menu');
    const filterType = document.getElementById('filter-report-type');
    const pdfBtn = document.getElementById('export-pdf-btn');
    const excelBtn = document.getElementById('export-excel-btn');
    const fromVal = @json($from);
    const toVal = @json($to);
    const pdfBase = @json(route('admin.reports.export-pdf'));
    const excelBase = @json(route('admin.reports.export-excel'));

    const syncExports = (type) => {
        const q = new URLSearchParams({ type, from: fromVal, to: toVal });
        if (pdfBtn) pdfBtn.href = `${pdfBase}?${q.toString()}`;
        if (excelBtn) excelBtn.href = `${excelBase}?${q.toString()}`;
        if (filterType) filterType.value = type;
        if (typeInput) typeInput.value = type;
    };

    trigger?.addEventListener('click', (e) => {
        e.stopPropagation();
        const open = !menu?.classList.contains('hidden');
        menu?.classList.toggle('hidden', open);
        trigger.setAttribute('aria-expanded', open ? 'false' : 'true');
    });

    menu?.querySelectorAll('[data-report-type]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const type = btn.getAttribute('data-report-type') || 'all';
            const label = btn.querySelector('span')?.textContent?.trim() || type;
            if (typeLabel) typeLabel.textContent = label;
            menu.querySelectorAll('[data-check-icon]').forEach((icon) => icon.classList.add('hidden'));
            btn.querySelector('[data-check-icon]')?.classList.remove('hidden');
            menu.classList.add('hidden');
            trigger?.setAttribute('aria-expanded', 'false');
            syncExports(type);
        });
    });

    document.addEventListener('click', () => {
        menu?.classList.add('hidden');
        trigger?.setAttribute('aria-expanded', 'false');
    });
})();
</script>
@endpush
