@php
    $ai = $ai ?? null;
    $showDetections = $showDetections ?? false;
    $areaName = $areaName ?? ($ai['area_name'] ?? 'AI Test Lot');
@endphp

<div class="mb-6 grid grid-cols-2 gap-4 md:grid-cols-5">
    <div class="rounded-xl border border-gray-200 bg-white p-5">
        <p class="text-sm text-gray-500">Vehicles Detected</p>
        <p id="ai-vehicle-count" class="mt-1 text-3xl font-bold text-gray-900">{{ $ai['vehicle_count'] ?? 0 }}</p>
        <p id="ai-mode" class="mt-1 text-xs text-gray-400">Mode: {{ $ai['mode'] ?? 'count' }}</p>
    </div>
    <div class="rounded-xl border border-green-200 bg-green-50 p-5">
        <p class="text-sm text-green-700">Available (AI Lot)</p>
        <p id="ai-available" class="mt-1 text-3xl font-bold text-green-800">{{ $ai['available'] ?? '—' }}</p>
    </div>
    <div class="rounded-xl border border-red-200 bg-red-50 p-5">
        <p class="text-sm text-red-700">Occupied (AI Lot)</p>
        <p id="ai-occupied" class="mt-1 text-3xl font-bold text-red-800">{{ $ai['occupied'] ?? '—' }}</p>
    </div>
    <div class="rounded-xl border border-amber-200 bg-amber-50 p-5">
        <p class="text-sm text-amber-700">Active AI Events</p>
        <p id="ai-event-count" class="mt-1 text-3xl font-bold text-amber-800">{{ count($ai['events'] ?? []) }}</p>
    </div>
    <div class="rounded-xl border border-blue-200 bg-blue-50 p-5">
        <p class="text-sm text-blue-700">Last Update</p>
        <p id="ai-updated-at" class="mt-1 text-lg font-semibold text-blue-800">{{ $ai['updated_at_label'] ?? 'Waiting for AI service' }}</p>
        @if (! empty($parkingUrl))
            <a href="{{ $parkingUrl }}" class="mt-2 inline-block text-xs font-medium text-blue-700 hover:underline">Open {{ $areaName }} →</a>
        @endif
    </div>
</div>

<div class="grid grid-cols-1 gap-6 {{ $showDetections ? 'xl:grid-cols-3' : '' }}">
    <div class="{{ $showDetections ? 'xl:col-span-2' : '' }} relative overflow-hidden rounded-xl border border-gray-800 bg-black">
        <div class="absolute inset-x-0 top-0 z-10 flex items-center justify-between bg-gradient-to-b from-black/80 to-transparent px-4 py-3">
            <div>
                <p class="text-sm font-semibold text-white">{{ $areaName }}</p>
                <p class="text-[11px] text-slate-300">AI monitored · slots, violations, plates</p>
            </div>
            <span id="ai-stream-status" class="rounded px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide bg-emerald-500/20 text-emerald-300">Connecting…</span>
        </div>
        @if ($streamUrl)
            <img
                id="ai-stream"
                src="{{ $streamUrl }}"
                alt="{{ $areaName }}"
                class="max-h-[70vh] w-full object-contain"
                onload="document.getElementById('ai-stream-status').textContent='Live'"
                onerror="document.getElementById('ai-stream-status').textContent='Offline'"
            >
        @else
            <div class="flex h-72 items-center justify-center px-6 text-center text-sm text-gray-300">
                Set AI_PARKING_STREAM_URL in .env and run the Python AI parking service.
            </div>
        @endif
    </div>

    @if ($showDetections)
        <div class="space-y-4">
            <div class="rounded-xl border border-gray-200 bg-white">
                <div class="border-b border-gray-200 px-4 py-3">
                    <h3 class="font-semibold text-gray-900">Latest Detections</h3>
                </div>
                <ul id="ai-detections" class="divide-y divide-gray-100 text-sm max-h-56 overflow-y-auto">
                    @forelse (($ai['detections'] ?? []) as $det)
                        <li class="flex items-center justify-between px-4 py-3">
                            <span class="font-medium text-gray-800">
                                {{ $det['class'] ?? 'vehicle' }}
                                @if (! empty($det['plate']))
                                    <span class="ml-1 text-xs text-indigo-600">[{{ $det['plate'] }}]</span>
                                @endif
                            </span>
                            <span class="text-gray-500">{{ isset($det['confidence']) ? round($det['confidence'] * 100).'%' : '—' }}</span>
                        </li>
                    @empty
                        <li class="px-4 py-8 text-center text-gray-500">No detections yet.</li>
                    @endforelse
                </ul>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white">
                <div class="border-b border-gray-200 px-4 py-3">
                    <h3 class="font-semibold text-gray-900">AI Violation Events</h3>
                </div>
                <ul id="ai-events" class="divide-y divide-gray-100 text-sm max-h-56 overflow-y-auto">
                    @forelse (($ai['events'] ?? []) as $evt)
                        <li class="px-4 py-3">
                            <div class="flex items-center justify-between gap-2">
                                <span class="rounded bg-amber-100 px-2 py-0.5 text-xs font-semibold uppercase text-amber-800">{{ $evt['type'] ?? 'event' }}</span>
                                <span class="text-xs text-gray-500">{{ $evt['zone_id'] ?? '' }}</span>
                            </div>
                            @if (! empty($evt['plate']))
                                <p class="mt-1 text-xs text-gray-600">Plate {{ $evt['plate'] }}</p>
                            @endif
                        </li>
                    @empty
                        <li class="px-4 py-8 text-center text-gray-500">No violation events yet. Calibrate zones to enable rules.</li>
                    @endforelse
                </ul>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white">
                <div class="border-b border-gray-200 px-4 py-3">
                    <h3 class="font-semibold text-gray-900">Slot Map (AI)</h3>
                </div>
                <div id="ai-slots" class="grid grid-cols-4 gap-2 p-3 text-xs">
                    @forelse (($ai['slots'] ?? []) as $slot)
                        <div class="rounded border px-2 py-1.5 text-center font-medium {{ ($slot['occupied'] ?? false) ? 'border-red-300 bg-red-50 text-red-800' : 'border-green-200 bg-green-50 text-green-800' }}">
                            {{ $slot['slot_number'] ?? '—' }}
                        </div>
                    @empty
                        <p class="col-span-4 px-2 py-6 text-center text-gray-500">Calibrate zones.json for per-slot status.</p>
                    @endforelse
                </div>
            </div>
        </div>
    @endif
</div>

@push('scripts')
<script>
    (() => {
        const statusUrl = @json($statusUrl ?? null);
        if (!statusUrl) return;

        const vehicleCount = document.getElementById('ai-vehicle-count');
        const available = document.getElementById('ai-available');
        const occupied = document.getElementById('ai-occupied');
        const updatedAt = document.getElementById('ai-updated-at');
        const eventCount = document.getElementById('ai-event-count');
        const modeEl = document.getElementById('ai-mode');
        const detectionsList = document.getElementById('ai-detections');
        const eventsList = document.getElementById('ai-events');
        const slotsGrid = document.getElementById('ai-slots');

        const refresh = async () => {
            if (document.hidden) return;
            try {
                const response = await fetch(statusUrl, { headers: { Accept: 'application/json' }, cache: 'no-store' });
                if (!response.ok) return;
                const data = await response.json();
                const ai = data.ai;
                if (!ai) return;

                if (vehicleCount) vehicleCount.textContent = ai.vehicle_count ?? 0;
                if (available) available.textContent = ai.available ?? '—';
                if (occupied) occupied.textContent = ai.occupied ?? '—';
                if (updatedAt) updatedAt.textContent = ai.updated_at_label || data.updated_at;
                if (eventCount) eventCount.textContent = (ai.events || []).length;
                if (modeEl) modeEl.textContent = `Mode: ${ai.mode || 'count'}`;

                if (detectionsList) {
                    detectionsList.replaceChildren();
                    const dets = ai.detections || [];
                    if (!dets.length) {
                        const li = document.createElement('li');
                        li.className = 'px-4 py-8 text-center text-gray-500';
                        li.textContent = 'No detections yet.';
                        detectionsList.append(li);
                    } else {
                        dets.forEach((det) => {
                            const li = document.createElement('li');
                            li.className = 'flex items-center justify-between px-4 py-3';
                            const name = document.createElement('span');
                            name.className = 'font-medium text-gray-800';
                            name.textContent = det.class || 'vehicle';
                            if (det.plate) {
                                const plate = document.createElement('span');
                                plate.className = 'ml-1 text-xs text-indigo-600';
                                plate.textContent = `[${det.plate}]`;
                                name.append(plate);
                            }
                            const conf = document.createElement('span');
                            conf.className = 'text-gray-500';
                            conf.textContent = det.confidence != null ? `${Math.round(det.confidence * 100)}%` : '—';
                            li.append(name, conf);
                            detectionsList.append(li);
                        });
                    }
                }

                if (eventsList) {
                    eventsList.replaceChildren();
                    const evts = ai.events || [];
                    if (!evts.length) {
                        const li = document.createElement('li');
                        li.className = 'px-4 py-8 text-center text-gray-500';
                        li.textContent = 'No violation events yet. Calibrate zones to enable rules.';
                        eventsList.append(li);
                    } else {
                        evts.slice().reverse().forEach((evt) => {
                            const li = document.createElement('li');
                            li.className = 'px-4 py-3';
                            const row = document.createElement('div');
                            row.className = 'flex items-center justify-between gap-2';
                            const badge = document.createElement('span');
                            badge.className = 'rounded bg-amber-100 px-2 py-0.5 text-xs font-semibold uppercase text-amber-800';
                            badge.textContent = evt.type || 'event';
                            const zone = document.createElement('span');
                            zone.className = 'text-xs text-gray-500';
                            zone.textContent = evt.zone_id || '';
                            row.append(badge, zone);
                            li.append(row);
                            if (evt.plate) {
                                const p = document.createElement('p');
                                p.className = 'mt-1 text-xs text-gray-600';
                                p.textContent = `Plate ${evt.plate}`;
                                li.append(p);
                            }
                            eventsList.append(li);
                        });
                    }
                }

                if (slotsGrid) {
                    slotsGrid.replaceChildren();
                    const slots = ai.slots || [];
                    if (!slots.length) {
                        const p = document.createElement('p');
                        p.className = 'col-span-4 px-2 py-6 text-center text-gray-500';
                        p.textContent = 'Calibrate zones.json for per-slot status.';
                        slotsGrid.append(p);
                    } else {
                        slots.forEach((slot) => {
                            const div = document.createElement('div');
                            const occ = !!slot.occupied;
                            div.className = `rounded border px-2 py-1.5 text-center font-medium ${occ ? 'border-red-300 bg-red-50 text-red-800' : 'border-green-200 bg-green-50 text-green-800'}`;
                            div.textContent = slot.slot_number || '—';
                            slotsGrid.append(div);
                        });
                    }
                }
            } catch (e) {}
        };

        refresh();
        window.setInterval(refresh, 2500);
    })();
</script>
@endpush
