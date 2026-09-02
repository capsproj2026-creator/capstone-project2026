@php
    $routeName = request()->route()->getName();
    $parkingRoute = str_contains($routeName, 'guard.') ? 'guard.parking' : 'admin.parking';
    $isAdminParking = str_starts_with($routeName, 'admin.parking');
    $statsScope = $selectedZone?->area_name ?? 'All Campus';
    $zoneSnapshot = $selectedZone
        ? \App\Services\ParkingZoneSnapshot::fromApp()->forAreaId((int) $selectedZone->id)
        : null;
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
    <div class="rounded-xl border border-blue-200 bg-blue-50 p-4">
        <div class="flex items-center justify-between pb-2">
            <p class="text-xs font-medium text-blue-700">Reserved</p>
            <i data-lucide="bookmark" class="h-4 w-4 text-blue-600"></i>
        </div>
        <p id="stat-res" class="text-2xl font-bold text-blue-800">{{ $stats->res }}</p>
        <p class="mt-1 text-xs text-blue-700">Held slots</p>
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
    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <form method="GET" action="{{ route($parkingRoute) }}" class="w-full lg:max-w-md">
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
        </form>

        <div class="flex flex-col gap-3 sm:items-end">
            <div class="flex flex-wrap gap-2">
                <span class="inline-flex items-center gap-1.5 rounded-full border border-green-200 bg-green-50 px-3 py-1 text-xs font-medium text-green-700">
                    <span class="h-2 w-2 rounded-full bg-green-500"></span>
                    Available
                </span>
                <span class="inline-flex items-center gap-1.5 rounded-full border border-red-200 bg-red-50 px-3 py-1 text-xs font-medium text-red-700">
                    <span class="h-2 w-2 rounded-full bg-red-500"></span>
                    Occupied
                </span>
                <span class="inline-flex items-center gap-1.5 rounded-full border border-blue-200 bg-blue-50 px-3 py-1 text-xs font-medium text-blue-700">
                    <span class="h-2 w-2 rounded-full bg-blue-500"></span>
                    Reserved
                </span>
                <span class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-medium text-slate-700">
                    <span class="h-2 w-2 rounded-full bg-slate-500"></span>
                    Maintenance
                </span>
            </div>
        </div>
    </div>
</div>

<div class="mb-8 rounded-xl border border-gray-200 bg-white">
    <div class="flex flex-col gap-1 border-b border-gray-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-lg font-semibold text-gray-900">Slot Map</h2>
            <p class="text-sm text-gray-500">Color only: green available, red occupied, blue reserved, gray maintenance.</p>
        </div>
        <span id="slot-count-badge" class="inline-flex w-fit items-center rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-700">
            {{ $slots->count() }} slot{{ $slots->count() === 1 ? '' : 's' }}
        </span>
    </div>

    @if ($zoneSnapshot)
        <div class="border-b border-gray-100 p-4">
            @include('partials.parking-zone-snapshot', ['snapshot' => $zoneSnapshot])
        </div>
    @endif

    @if (! empty($aiSnapshot) && isset($aiAreaId) && ((string) $zoneFilter === (string) $aiAreaId || $zoneFilter === 'All'))
        <div class="border-b border-blue-100 bg-blue-50 px-5 py-3 text-sm text-blue-800">
            AI camera last reported <strong id="ai-lot-vehicles">{{ $aiSnapshot['vehicle_count'] ?? 0 }}</strong> vehicle(s)
            at <span id="ai-lot-updated">{{ $aiSnapshot['updated_at_label'] ?? '—' }}</span>.
        </div>
        <div class="border-b border-gray-100 px-5 py-3">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">AI plate recognition</p>
            <ul id="ai-parking-detections" class="mt-2 max-h-40 space-y-2 overflow-y-auto text-sm">
                @forelse (($aiSnapshot['detections'] ?? []) as $det)
                    <li class="rounded-lg border border-gray-100 bg-gray-50 px-3 py-2">
                        <p class="font-medium text-gray-800">
                            {{ $det['class'] ?? 'vehicle' }}
                            @php
                                $motionLabel = $det['motion_label'] ?? match ($det['motion_state'] ?? '') {
                                    'moving' => 'Moving',
                                    'parked' => 'Parked',
                                    'idle' => 'Settling',
                                    default => null,
                                };
                            @endphp
                            @if ($motionLabel)
                                <span @class([
                                    'ml-1 rounded px-1.5 py-0.5 text-[10px] font-bold uppercase',
                                    'bg-orange-100 text-orange-700' => ($det['motion_state'] ?? '') === 'moving',
                                    'bg-sky-100 text-sky-700' => ($det['motion_state'] ?? '') === 'parked',
                                    'bg-gray-100 text-gray-600' => ($det['motion_state'] ?? '') === 'idle',
                                ])>{{ $motionLabel }}</span>
                            @endif
                            @if (($det['plate_status'] ?? '') === 'unreadable')
                                <span class="text-slate-500">· Plate Unreadable</span>
                            @elseif (! empty($det['plate']))
                                <span class="text-indigo-600">· {{ $det['plate'] }}</span>
                            @endif
                        </p>
                        @if (($det['plate_status'] ?? '') === 'unreadable')
                            <p class="text-xs text-slate-500">Plate Unreadable</p>
                        @elseif (! empty($det['registered']) && ! empty($det['owner_name']))
                            <p class="text-xs text-emerald-700">
                                {{ $det['owner_name'] }}
                                · {{ $det['registration_status'] ?? 'Registered' }}
                                @if (! empty($det['vehicle_details']))
                                    · {{ $det['vehicle_details'] }}
                                @endif
                            </p>
                        @elseif (! empty($det['plate']))
                            <p class="text-xs text-amber-700">Unknown Vehicle · Plate Not Registered</p>
                        @endif
                    </li>
                @empty
                    <li id="ai-parking-detections-empty" class="text-xs text-gray-500">No plate detections yet.</li>
                @endforelse
            </ul>
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
        <div id="slot-map-grid" class="grid grid-cols-2 gap-3 p-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 2xl:grid-cols-8">
            @foreach ($slots as $slot)
                @php
                    $slotLabel = $slot->slot_number ?: ('SLOT-'.$slot->id);
                    $status = $slot->status ?? 'Available';
                    $statusClass = match ($status) {
                        'Available'   => 'border-green-300 bg-green-500 text-white',
                        'Occupied'    => 'border-red-300 bg-red-500 text-white',
                        'Reserved'    => 'border-blue-300 bg-blue-500 text-white',
                        'Maintenance' => 'border-slate-300 bg-slate-500 text-white',
                        default       => 'border-gray-300 bg-gray-400 text-white',
                    };
                @endphp

                <div
                    data-slot-id="{{ $slot->id }}"
                    data-slot-number="{{ $slotLabel }}"
                    @class([
                        'relative flex min-h-[4rem] items-center justify-center rounded-xl border-2 p-3 text-center shadow-sm transition',
                        $statusClass,
                        $isAdminParking ? 'cursor-pointer hover:ring-2 hover:ring-offset-2 hover:ring-gray-400' : '',
                    ])
                    @if ($isAdminParking)
                        data-admin-slot
                        data-current-status="{{ $status }}"
                        title="{{ $status }} — click to change"
                    @else
                        title="{{ $status }}"
                    @endif
                >
                    <p class="text-base font-bold leading-none tracking-wide">{{ $slotLabel }}</p>
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
        const updateSlotUrl = @json($isAdminParking ? route('admin.parking.slots.update') : null);
        const csrf = @json(csrf_token());
        const statusClasses = {
            Available: ['border-green-300', 'bg-green-500', 'text-white'],
            Occupied: ['border-red-300', 'bg-red-500', 'text-white'],
            Reserved: ['border-blue-300', 'bg-blue-500', 'text-white'],
            Maintenance: ['border-slate-300', 'bg-slate-500', 'text-white'],
        };
        const allCard = Object.values(statusClasses).flat();

        const applySlotStatus = (card, status) => {
            card.classList.remove(...allCard);
            card.classList.add(...(statusClasses[status] || statusClasses.Available));
            card.dataset.currentStatus = status;
            card.title = card.hasAttribute('data-admin-slot')
                ? `${status} — click to change`
                : status;
        };

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
                    const list = document.getElementById('ai-parking-detections');
                    if (list) {
                        list.replaceChildren();
                        const dets = data.ai.detections || [];
                        if (!dets.length) {
                            const empty = document.createElement('li');
                            empty.className = 'text-xs text-gray-500';
                            empty.textContent = 'No plate detections yet.';
                            list.append(empty);
                        } else {
                            dets.forEach((det) => {
                                const li = document.createElement('li');
                                li.className = 'rounded-lg border border-gray-100 bg-gray-50 px-3 py-2';
                                const title = document.createElement('p');
                                title.className = 'font-medium text-gray-800';
                                let titleText = det.class || 'vehicle';
                                if (det.plate_status === 'unreadable') titleText += ' · Plate Unreadable';
                                else if (det.plate) titleText += ` · ${det.plate}`;
                                title.textContent = titleText;
                                li.append(title);
                                const meta = document.createElement('p');
                                meta.className = 'text-xs';
                                if (det.plate_status === 'unreadable') {
                                    meta.className += ' text-slate-500';
                                    meta.textContent = 'Plate Unreadable';
                                } else if (det.registered && det.owner_name) {
                                    meta.className += ' text-emerald-700';
                                    meta.textContent = [
                                        det.owner_name,
                                        det.registration_status || 'Registered',
                                        det.vehicle_details || null,
                                    ].filter(Boolean).join(' · ');
                                } else if (det.plate) {
                                    meta.className += ' text-amber-700';
                                    meta.textContent = 'Unknown Vehicle · Plate Not Registered';
                                } else {
                                    meta.className += ' text-gray-500';
                                    meta.textContent = 'Waiting for plate…';
                                }
                                li.append(meta);
                                list.append(li);
                            });
                        }
                    }
                }

                (data.slots || []).forEach((slot) => {
                    const card = document.querySelector(`[data-slot-id="${slot.id}"]`);
                    if (!card) return;
                    applySlotStatus(card, slot.status || 'Available');
                });
            } catch (e) {}
        };

        if (updateSlotUrl) {
            document.querySelectorAll('[data-admin-slot]').forEach((card) => {
                card.addEventListener('click', async (event) => {
                    if (event.target.closest('form')) return;
                    const statuses = ['Available', 'Occupied', 'Reserved', 'Maintenance'];
                    const current = card.dataset.currentStatus || 'Available';
                    const next = prompt(`Set status for ${card.dataset.slotNumber || 'slot'}:\n${statuses.join(' | ')}`, current);
                    if (!next || !statuses.includes(next)) return;
                    try {
                        const response = await fetch(updateSlotUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                Accept: 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || csrf,
                                'X-XSRF-TOKEN': decodeURIComponent((document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/) || [])[1] || ''),
                            },
                            credentials: 'same-origin',
                            body: JSON.stringify({ slot_id: Number(card.dataset.slotId), status: next }),
                        });
                        if (!response.ok) return;
                        applySlotStatus(card, next);
                        refresh();
                    } catch (e) {}
                });
            });
        }

        refresh();
        window.setInterval(refresh, 5000);
    })();
</script>
@endpush
@endif
