@php
    $routeName = request()->route()->getName();
    $parkingRoute = str_contains($routeName, 'guard.') ? 'guard.parking' : 'admin.parking';
    $isAdminParking = str_starts_with($routeName, 'admin.parking');
    $statsScope = $selectedZone?->area_name ?? 'All Campus';
@endphp

@include('partials.shell.page-header', [
    'title' => 'Parking Management',
    'subtitle' => $isAdminParking
        ? 'Monitor live slot availability and occupancy across campus zones.'
        : 'View real-time parking availability by zone.',
])

@if ($isAdminParking)
    @include('partials.admin.parking-nav', ['active' => 'overview'])
@endif

    @if (session('success'))
    <div class="mb-6 flex gap-2 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
        <i data-lucide="circle-check" class="h-4 w-4 shrink-0"></i>
        {{ session('success') }}
    </div>
@endif

<div class="mb-6 grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-3 xl:grid-cols-6">
    <div class="rounded-xl border border-gray-200 bg-white p-4">
        <div class="flex items-center justify-between pb-2">
            <p class="text-xs font-medium text-gray-500">Total Slots</p>
            <i data-lucide="grid-3x3" class="h-4 w-4 text-gray-500"></i>
        </div>
        <p id="stat-total" class="text-2xl font-bold text-gray-900">{{ $stats->total }}</p>
        <p class="mt-1 text-xs text-gray-500">{{ $statsScope }}</p>
    </div>
    <div class="rounded-xl border border-green-200 bg-green-50 p-4">
        <div class="flex items-center justify-between pb-2">
            <p class="text-xs font-medium text-green-700">Available</p>
            <i data-lucide="circle-check" class="h-4 w-4 text-green-600"></i>
        </div>
        <p id="stat-avail" class="text-2xl font-bold text-green-800">{{ $stats->avail }}</p>
        <p class="mt-1 text-xs text-green-700">Ready to use</p>
    </div>
    <div class="rounded-xl border border-red-200 bg-red-50 p-4">
        <div class="flex items-center justify-between pb-2">
            <p class="text-xs font-medium text-red-700">Occupied</p>
            <i data-lucide="car" class="h-4 w-4 text-red-600"></i>
        </div>
        <p id="stat-occ" class="text-2xl font-bold text-red-800">{{ $stats->occ }}</p>
        <p class="mt-1 text-xs text-red-700">Currently in use</p>
    </div>
    <div class="rounded-xl border border-amber-200 bg-amber-50 p-4">
        <div class="flex items-center justify-between pb-2">
            <p class="text-xs font-medium text-amber-700">Reserved</p>
            <i data-lucide="bookmark" class="h-4 w-4 text-amber-600"></i>
        </div>
        <p id="stat-res" class="text-2xl font-bold text-amber-800">{{ $stats->res }}</p>
        <p class="mt-1 text-xs text-amber-700">Held slots</p>
    </div>
    <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
        <div class="flex items-center justify-between pb-2">
            <p class="text-xs font-medium text-slate-700">Maintenance</p>
            <i data-lucide="wrench" class="h-4 w-4 text-slate-600"></i>
        </div>
        <p id="stat-maint" class="text-2xl font-bold text-slate-800">{{ $stats->maint }}</p>
        <p class="mt-1 text-xs text-slate-600">Unavailable</p>
    </div>
    <div class="rounded-xl border border-blue-200 bg-blue-50 p-4">
        <div class="flex items-center justify-between pb-2">
            <p class="text-xs font-medium text-blue-700">Occupancy</p>
            <i data-lucide="percent" class="h-4 w-4 text-blue-600"></i>
        </div>
        <p id="stat-occupancy" class="text-2xl font-bold text-blue-800">{{ $occupancyRate }}%</p>
        <p class="mt-1 text-xs text-blue-700">Of filtered slots</p>
    </div>
</div>

<div class="mb-6 rounded-xl border border-gray-200 bg-white p-4 sm:p-5">
    <form method="GET" action="{{ route($parkingRoute) }}" class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div class="w-full lg:max-w-md">
            <label for="zone_id" class="mb-1.5 block text-sm font-medium text-gray-700">Parking Zone</label>
            <select
                name="zone_id"
                id="zone_id"
                onchange="this.form.submit()"
                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20"
            >
                <option value="All" @selected($zoneFilter === 'All')>All Campus Zones</option>
                @foreach ($zones as $zone)
                    <option value="{{ $zone->id }}" @selected((string) $zoneFilter === (string) $zone->id)>
                        {{ $zone->area_name }}@if (isset($aiAreaId) && (int) $zone->id === (int) $aiAreaId) (AI monitored)@endif
                    </option>
                @endforeach
            </select>
            <p class="mt-1.5 text-xs text-gray-500">
                @if ($selectedZone)
                    Showing {{ $slots->count() }} slot(s) in {{ $selectedZone->area_name }}.
                    @if (isset($aiAreaId) && (int) $selectedZone->id === (int) $aiAreaId)
                        <span class="font-medium text-blue-700">YOLOv9 occupancy active.</span>
                    @endif
                @else
                    Showing all {{ $slots->count() }} slot(s) across campus.
                @endif
            </p>
        </div>

        <div class="flex flex-wrap gap-2">
            <span class="inline-flex items-center gap-1.5 rounded-full border border-green-200 bg-green-50 px-3 py-1 text-xs font-medium text-green-700">
                <span class="h-2 w-2 rounded-full bg-green-500"></span>
                Available
            </span>
            <span class="inline-flex items-center gap-1.5 rounded-full border border-red-200 bg-red-50 px-3 py-1 text-xs font-medium text-red-700">
                <span class="h-2 w-2 rounded-full bg-red-500"></span>
                Occupied
            </span>
            <span class="inline-flex items-center gap-1.5 rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-xs font-medium text-amber-700">
                <span class="h-2 w-2 rounded-full bg-amber-500"></span>
                Reserved
            </span>
            <span class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-medium text-slate-700">
                <span class="h-2 w-2 rounded-full bg-slate-500"></span>
                Maintenance
            </span>
        </div>
    </form>
</div>

<div class="mb-8 rounded-xl border border-gray-200 bg-white">
    <div class="flex flex-col gap-1 border-b border-gray-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-lg font-semibold text-gray-900">Slot Map</h2>
            <p class="text-sm text-gray-500">Click a zone above to focus on one area. Occupied slots show who is parked when available.</p>
        </div>
        <span id="slot-count-badge" class="inline-flex w-fit items-center rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-700">
            {{ $slots->count() }} slot{{ $slots->count() === 1 ? '' : 's' }}
        </span>
    </div>

    @if (! empty($aiSnapshot) && isset($aiAreaId) && ((string) $zoneFilter === (string) $aiAreaId || $zoneFilter === 'All'))
        <div class="border-b border-blue-100 bg-blue-50 px-5 py-3 text-sm text-blue-800">
            AI camera last reported <strong id="ai-lot-vehicles">{{ $aiSnapshot['vehicle_count'] ?? 0 }}</strong> vehicle(s)
            at <span id="ai-lot-updated">{{ $aiSnapshot['updated_at_label'] ?? '—' }}</span>.
        </div>
    @endif

    @if ($slots->isEmpty())
        <div class="flex flex-col items-center justify-center px-6 py-16 text-center">
            <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-gray-100">
                <i data-lucide="parking-square" class="h-7 w-7 text-gray-400"></i>
            </div>
            <p class="text-base font-medium text-gray-900">No parking slots found</p>
            <p class="mt-1 max-w-md text-sm text-gray-500">
                @if ($selectedZone)
                    This zone has no slots yet. Try selecting a different zone or view all campus zones.
                @else
                    No parking slots are configured in the system yet.
                @endif
            </p>
        </div>
    @else
        @php
            // Track the incremental numbers for each unique zone prefix (e.g., TA, FO, AD)
            $zoneCounters = [];
        @endphp

        <div id="slot-map-grid" class="grid grid-cols-2 gap-3 p-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 2xl:grid-cols-8">
            @foreach ($slots as $slot)
                @php
                    $prefix = str_contains($slot->slot_number, '-') ? explode('-', $slot->slot_number)[0] : 'SLOT';
                    $zoneCounters[$prefix] = ($zoneCounters[$prefix] ?? 0) + 1;
                    $incrementedSlotNumber = $prefix . '-' . $zoneCounters[$prefix];

                    $status = $slot->status ?? 'Available';
                    $statusClass = match ($status) {
                        'Available'   => 'border-green-200 bg-green-50 text-green-800',
                        'Occupied'    => 'border-red-200 bg-red-50 text-red-800',
                        'Reserved'    => 'border-amber-200 bg-amber-50 text-amber-800',
                        'Maintenance' => 'border-slate-200 bg-slate-50 text-slate-700',
                        default       => 'border-gray-200 bg-gray-50 text-gray-700',
                    };
                    $badgeClass = match ($status) {
                        'Available'   => 'bg-green-100 text-green-700',
                        'Occupied'    => 'bg-red-100 text-red-700',
                        'Reserved'    => 'bg-amber-100 text-amber-700',
                        'Maintenance' => 'bg-slate-100 text-slate-700',
                        default       => 'bg-gray-100 text-gray-700',
                    };
                @endphp
                
                <div
                    data-slot-id="{{ $slot->id }}"
                    @class([
                        'rounded-xl border p-3 transition-shadow hover:shadow-sm',
                        $statusClass,
                    ])
                >
                    <div class="mb-2 flex items-start justify-between gap-2">
                        <p class="text-base font-bold leading-none">{{ $incrementedSlotNumber }}</p>
                        <span data-slot-status @class(['rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide', $badgeClass])>
                            {{ $status }}
                        </span>
                    </div>

                    @if (isset($zoneFilter) && $zoneFilter === 'All')
                        <p class="mb-2 truncate text-xs text-gray-600">
                            {{ $slot->area?->area_name ?? $slot->description ?? 'Unknown zone' }}
                            @if (isset($aiAreaId) && (int) $slot->area_id === (int) $aiAreaId)
                                <span class="text-blue-600">· AI</span>
                            @endif
                        </p>
                    @endif

                    <div data-slot-detail>
                        @if ($status === 'Occupied' && $slot->parkedUser)
                            <div class="rounded-lg bg-white/70 px-2 py-1.5 text-xs text-gray-700">
                                <p class="truncate font-medium">{{ $slot->parkedUser->fullname }}</p>
                                <p class="truncate text-[11px] text-gray-500">{{ $slot->parkedUser->id_number }}</p>
                            </div>
                        @elseif ($status === 'Occupied')
                            <p class="text-xs text-gray-600">Occupant not recorded</p>
                        @elseif ($status === 'Available')
                            <p class="text-xs text-green-700">Open for parking</p>
                        @elseif ($status === 'Reserved')
                            <p class="text-xs text-amber-700">Temporarily held</p>
                        @else
                            <p class="text-xs text-slate-600">Under maintenance</p>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>

@if (! empty($statusUrl))
@push('scripts')
<script>
    (() => {
        const statusUrl = @json($statusUrl);
        const statusClasses = {
            Available: ['border-green-200', 'bg-green-50', 'text-green-800'],
            Occupied: ['border-red-200', 'bg-red-50', 'text-red-800'],
            Reserved: ['border-amber-200', 'bg-amber-50', 'text-amber-800'],
            Maintenance: ['border-slate-200', 'bg-slate-50', 'text-slate-700'],
        };
        const badgeClasses = {
            Available: ['bg-green-100', 'text-green-700'],
            Occupied: ['bg-red-100', 'text-red-700'],
            Reserved: ['bg-amber-100', 'text-amber-700'],
            Maintenance: ['bg-slate-100', 'text-slate-700'],
        };
        const allCard = Object.values(statusClasses).flat();
        const allBadge = Object.values(badgeClasses).flat();

        const refresh = async () => {
            if (document.hidden) return;
            try {
                const response = await fetch(statusUrl, { headers: { Accept: 'application/json' }, cache: 'no-store' });
                if (!response.ok) return;
                const data = await response.json();
                const stats = data.stats || {};
                const setText = (id, value) => {
                    const node = document.getElementById(id);
                    if (node) node.textContent = value;
                };
                setText('stat-total', stats.total ?? 0);
                setText('stat-avail', stats.avail ?? 0);
                setText('stat-occ', stats.occ ?? 0);
                setText('stat-res', stats.res ?? 0);
                setText('stat-maint', stats.maint ?? 0);
                setText('stat-occupancy', `${data.occupancy_rate ?? 0}%`);
                setText('slot-count-badge', `${stats.total ?? 0} slot${(stats.total ?? 0) === 1 ? '' : 's'}`);
                if (data.ai) {
                    setText('ai-lot-vehicles', data.ai.vehicle_count ?? 0);
                    setText('ai-lot-updated', data.ai.updated_at_label || data.updated_at);
                }

                (data.slots || []).forEach((slot) => {
                    const card = document.querySelector(`[data-slot-id="${slot.id}"]`);
                    if (!card) return;
                    const status = slot.status || 'Available';
                    card.classList.remove(...allCard);
                    card.classList.add(...(statusClasses[status] || statusClasses.Available));
                    const badge = card.querySelector('[data-slot-status]');
                    if (badge) {
                        badge.classList.remove(...allBadge);
                        badge.classList.add(...(badgeClasses[status] || badgeClasses.Available));
                        badge.textContent = status;
                    }
                    const detail = card.querySelector('[data-slot-detail]');
                    if (detail) {
                        if (status === 'Occupied' && slot.parked_user) {
                            detail.innerHTML = `<div class="rounded-lg bg-white/70 px-2 py-1.5 text-xs text-gray-700"><p class="truncate font-medium"></p><p class="truncate text-[11px] text-gray-500"></p></div>`;
                            detail.querySelector('p').textContent = slot.parked_user;
                            detail.querySelectorAll('p')[1].textContent = slot.parked_id_number || '';
                        } else if (status === 'Occupied') {
                            detail.innerHTML = '<p class="text-xs text-gray-600">Occupant not recorded</p>';
                        } else if (status === 'Available') {
                            detail.innerHTML = '<p class="text-xs text-green-700">Open for parking</p>';
                        } else if (status === 'Reserved') {
                            detail.innerHTML = '<p class="text-xs text-amber-700">Temporarily held</p>';
                        } else {
                            detail.innerHTML = '<p class="text-xs text-slate-600">Under maintenance</p>';
                        }
                    }
                });
            } catch (e) {}
        };

        refresh();
        window.setInterval(refresh, 2500);
    })();
</script>
@endpush
@endif
