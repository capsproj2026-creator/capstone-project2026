@extends('layouts.guard')

@section('title', 'Guard Dashboard')

@section('content')
    @include('partials.shell.page-header', [
        'title' => 'Dashboard',
        'subtitle' => 'Monitor and control vehicle access',
    ])

    <div class="mb-8 grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-xl border border-gray-200 bg-white p-6">
            <div class="mb-2 flex items-center justify-between">
                <span class="text-gray-600">Vehicles Inside</span>
                <i data-lucide="car" class="h-5 w-5 text-green-600"></i>
            </div>
            <div class="text-3xl font-semibold text-gray-900">{{ $vehiclesInside }}</div>
            <p class="mt-1 text-sm text-gray-500">Occupied parking slots</p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-6">
            <div class="mb-2 flex items-center justify-between">
                <span class="text-gray-600">Today's Entries</span>
                <i data-lucide="clock" class="h-5 w-5 text-blue-600"></i>
            </div>
            <div class="text-3xl font-semibold text-gray-900">{{ $todayEntries }}</div>
            <p class="mt-1 text-sm text-gray-500">{{ $pending }} pending registrations</p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-6">
            <div class="mb-2 flex items-center justify-between">
                <span class="text-gray-600">Active Violations</span>
                <i data-lucide="triangle-alert" class="h-5 w-5 text-orange-600"></i>
            </div>
            <div class="text-3xl font-semibold text-gray-900">{{ $activeViolations }}</div>
            <p class="mt-1 text-sm text-gray-500">Requires attention</p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-6">
            <div class="mb-2 flex items-center justify-between">
                <span class="text-gray-600">Available Slots</span>
                <i data-lucide="circle-check" class="h-5 w-5 text-purple-600"></i>
            </div>
            <div class="text-3xl font-semibold text-gray-900">{{ $availableSlots }}</div>
            <p class="mt-1 text-sm text-gray-500">Out of {{ $totalSlots }} total</p>
        </div>
    </div>

    <div class="mb-8 grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-xl border border-teal-100 bg-white p-6">
            <div class="mb-2 flex items-center justify-between">
                <span class="text-gray-600">Active Visitors</span>
                <i data-lucide="user-round-check" class="h-5 w-5 text-teal-600"></i>
            </div>
            <div class="text-3xl font-semibold text-teal-700">{{ number_format($activeVisitors ?? 0) }}</div>
        </div>
        <div class="rounded-xl border border-amber-100 bg-white p-6">
            <div class="mb-2 flex items-center justify-between">
                <span class="text-gray-600">Waiting Visitors</span>
                <i data-lucide="clock" class="h-5 w-5 text-amber-600"></i>
            </div>
            <div class="text-3xl font-semibold text-amber-700">{{ number_format($waitingVisitors ?? 0) }}</div>
        </div>
        <div class="rounded-xl border border-blue-100 bg-white p-6">
            <div class="mb-2 flex items-center justify-between">
                <span class="text-gray-600">RFID Returns</span>
                <i data-lucide="hash" class="h-5 w-5 text-blue-600"></i>
            </div>
            <div class="text-3xl font-semibold text-blue-700">{{ number_format($rfidReturnsPending ?? 0) }}</div>
            <p class="mt-1 text-sm text-gray-500">Cards still assigned</p>
        </div>
        <div class="rounded-xl border border-rose-100 bg-white p-6">
            <div class="mb-2 flex items-center justify-between">
                <span class="text-gray-600">Expired Visitors</span>
                <i data-lucide="ban" class="h-5 w-5 text-rose-600"></i>
            </div>
            <div class="text-3xl font-semibold text-rose-700">{{ number_format($expiredVisitors ?? 0) }}</div>
        </div>
    </div>

    <div class="mb-8 grid grid-cols-1 gap-6 md:grid-cols-2">
        <div class="rounded-xl border border-gray-200 bg-white p-6">
            <div class="mb-4 flex items-center gap-3">
                <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-green-100">
                    <i data-lucide="scan" class="h-6 w-6 text-green-600"></i>
                </div>
                <div>
                    <h3 class="font-semibold text-gray-900">RFID Scan</h3>
                    <p class="text-sm text-gray-500">Scan vehicle for entry/exit</p>
                </div>
            </div>
            <a href="{{ route('guard.gate') }}" class="block w-full rounded-lg bg-gradient-to-r from-green-500 to-green-700 py-3 text-center text-white transition-all hover:shadow-lg">
                Open Live Gate Monitor
            </a>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-6">
            <div class="mb-4 flex items-center gap-3">
                <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-orange-100">
                    <i data-lucide="triangle-alert" class="h-6 w-6 text-orange-600"></i>
                </div>
                <div>
                    <h3 class="font-semibold text-gray-900">Report Violation</h3>
                    <p class="text-sm text-gray-500">Log parking or access violation</p>
                </div>
            </div>
            <a href="{{ route('guard.violations') }}" class="block w-full rounded-lg bg-gradient-to-r from-orange-500 to-orange-700 py-3 text-center text-white transition-all hover:shadow-lg">
                Report Violation
            </a>
        </div>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="border-b border-gray-200 px-5 py-4 sm:px-6">
            <h3 class="font-semibold text-gray-900">Recent Access Activity</h3>
            <p class="mt-0.5 text-sm text-gray-500">Latest gate entries and exits</p>
        </div>
        <div class="divide-y divide-gray-100">
            @forelse ($recentGateActivity as $log)
                @php
                    $granted = method_exists($log, 'accessGranted') ? $log->accessGranted() : true;
                    $isEntry = ($log->action ?? '') === 'Entry';
                    $personName = $log->visitor?->displayName() ?? $log->user?->Name ?? 'Unknown';
                    $plate = $log->visitor?->plate_number ?? $log->user?->plate_number ?? '—';
                    $gate = method_exists($log, 'displayGate') ? $log->displayGate() : ($log->gate_id ?? null);
                @endphp
                <div class="flex flex-wrap items-center gap-x-6 gap-y-3 px-5 py-4 hover:bg-gray-50/80 sm:px-6">
                    <div class="flex min-w-0 flex-[1_1_14rem] items-start gap-3">
                        <div @class([
                            'flex h-10 w-10 shrink-0 items-center justify-center rounded-lg',
                            'bg-green-100' => $isEntry,
                            'bg-blue-100' => ! $isEntry,
                        ])>
                            <i data-lucide="{{ $isEntry ? 'log-in' : 'log-out' }}" @class([
                                'h-5 w-5',
                                'text-green-600' => $isEntry,
                                'text-blue-600' => ! $isEntry,
                            ])></i>
                        </div>
                        <div class="min-w-0">
                            <p class="truncate font-semibold text-gray-900">{{ $plate }}</p>
                            <p class="mt-0.5 truncate text-sm text-gray-600">
                                {{ $personName }}
                                @if ($log->visitor)
                                    <span class="ml-1 inline-flex rounded bg-teal-50 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-teal-700">Visitor</span>
                                @endif
                            </p>
                            @if ($gate)
                                <p class="mt-1 flex items-center gap-1 text-xs text-gray-500">
                                    <i data-lucide="door-open" class="h-3.5 w-3.5"></i>
                                    {{ $gate }}
                                </p>
                            @endif
                        </div>
                    </div>

                    <div class="flex shrink-0 flex-wrap items-center gap-2">
                        <span @class([
                            'inline-flex rounded-full px-2.5 py-1 text-xs font-semibold',
                            'bg-green-100 text-green-700' => $isEntry,
                            'bg-blue-100 text-blue-700' => ! $isEntry,
                        ])>{{ $log->action ?: '—' }}</span>
                        @if ($granted)
                            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-500 px-2.5 py-1 text-xs font-semibold text-white">
                                <i data-lucide="check" class="h-3 w-3"></i>
                                Granted
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 rounded-full bg-red-500 px-2.5 py-1 text-xs font-semibold text-white">
                                <i data-lucide="x" class="h-3 w-3"></i>
                                Denied
                            </span>
                        @endif
                    </div>

                    <div class="min-w-[7.5rem] shrink-0 sm:text-right">
                        <p class="text-sm font-medium text-gray-700">{{ ph_datetime($log->timestamp, 'M j, g:i A') }}</p>
                        <p class="text-xs text-gray-500">{{ $log->timestamp?->diffForHumans() }}</p>
                    </div>
                </div>
            @empty
                <p class="px-5 py-8 text-center text-sm text-gray-500 sm:px-6">No gate activity recorded yet.</p>
            @endforelse
        </div>
    </div>

    @if (($recentViolationLogs ?? collect())->isNotEmpty())
        <div class="mt-8 rounded-xl border border-gray-200 bg-white">
            <div class="flex items-center justify-between border-b border-gray-200 p-6">
                <h3 class="font-semibold text-gray-900">Recent Violations</h3>
                <a href="{{ route('guard.violations') }}" class="text-sm font-medium text-orange-600 hover:underline">View all</a>
            </div>
            <div class="divide-y divide-gray-200">
                @foreach ($recentViolationLogs as $violation)
                    <div class="flex flex-wrap items-start justify-between gap-4 p-4">
                        <div class="min-w-0">
                            <p class="font-medium text-gray-900">{{ $violation->violator_name }}</p>
                            <p class="text-sm text-red-600">{{ $violation->violation_type }}</p>
                            <p class="mt-1 text-xs text-gray-500">{{ ph_datetime($violation->created_at) }}</p>
                        </div>
                        <x-violation.evidence-panel
                            :log="$violation"
                            route-name="guard.violations.evidence"
                            compact
                        />
                    </div>
                @endforeach
            </div>
        </div>
    @endif
@endsection
