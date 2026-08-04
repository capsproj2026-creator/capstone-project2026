@extends('layouts.guard')

@section('title', 'AI Parking Monitor')

@section('content')
    @include('partials.shell.page-header', [
        'title' => 'AI Parking Monitor',
        'subtitle' => 'One live feed per camera · real-time YOLOv9 occupancy',
    ])

    @php
        $cameras = collect($registryCameras ?? []);
        $healthById = collect($aiCamerasHealth ?? []);
        $snaps = is_array($aiCameras ?? null) ? $aiCameras : [];
        $primaryAi = $ai ?? null;
    @endphp

    @if ($cameras->isEmpty())
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-8 text-center text-sm text-amber-900">
            No AI cameras are configured. Set AI_CAMERA_* values in .env and start the AI parking service.
        </div>
    @else
        <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-medium text-gray-500">Cameras</p>
                <p class="mt-1 text-2xl font-bold text-gray-900">{{ $cameras->count() }}</p>
            </div>
            <div class="rounded-xl border border-green-200 bg-green-50 p-4 shadow-sm">
                <p class="text-xs font-medium text-green-700">Available (AI)</p>
                <p id="ai-available" class="mt-1 text-2xl font-bold text-green-800">{{ $primaryAi['available'] ?? '—' }}</p>
            </div>
            <div class="rounded-xl border border-red-200 bg-red-50 p-4 shadow-sm">
                <p class="text-xs font-medium text-red-700">Occupied (AI)</p>
                <p id="ai-occupied" class="mt-1 text-2xl font-bold text-red-800">{{ $primaryAi['occupied'] ?? '—' }}</p>
            </div>
            <div class="rounded-xl border border-blue-200 bg-blue-50 p-4 shadow-sm">
                <p class="text-xs font-medium text-blue-700">Last Update</p>
                <p id="ai-updated-at" class="mt-1 text-lg font-semibold text-blue-800">{{ $primaryAi['updated_at_label'] ?? 'Waiting…' }}</p>
            </div>
        </div>

        <div class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($cameras as $cam)
                @php
                    $camId = (string) ($cam['id'] ?? '');
                    $health = $healthById->get($camId, []);
                    $snap = $snaps[$camId] ?? [];
                    $browserUrl = $health['stream_browser_url'] ?? ($cam['stream_url'] ?? null);
                    $online = (bool) ($health['connected'] ?? $health['online'] ?? false);
                @endphp
                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                    <div class="flex items-center justify-between gap-2 border-b border-gray-100 px-4 py-3">
                        <div class="min-w-0">
                            <p class="truncate font-semibold text-gray-900">{{ $cam['name'] ?? $camId }}</p>
                            <p class="truncate text-xs text-gray-500">{{ $camId }} · {{ $cam['location'] ?? 'Campus' }}</p>
                        </div>
                        <span @class([
                            'shrink-0 rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase',
                            'bg-emerald-100 text-emerald-700' => $online,
                            'bg-slate-100 text-slate-600' => ! $online,
                        ])>{{ $online ? 'Live' : 'Offline' }}</span>
                    </div>
                    <div class="bg-black">
                        @if ($browserUrl)
                            <img
                                src="{{ $browserUrl }}"
                                alt="{{ $camId }} live feed"
                                class="max-h-64 w-full object-contain"
                                loading="lazy"
                                data-camera-stream="{{ $camId }}"
                            >
                        @else
                            <div class="flex h-40 items-center justify-center text-xs text-gray-400">Stream URL not configured</div>
                        @endif
                    </div>
                    <div class="grid grid-cols-3 gap-px border-t border-gray-100 bg-gray-100 text-center text-xs">
                        <div class="bg-white px-2 py-3">
                            <p class="text-gray-500">Vehicles</p>
                            <p class="js-cam-vehicles text-lg font-bold text-gray-900" data-camera="{{ $camId }}">{{ $snap['vehicle_count'] ?? 0 }}</p>
                        </div>
                        <div class="bg-white px-2 py-3">
                            <p class="text-green-700">Free</p>
                            <p class="js-cam-available text-lg font-bold text-green-800" data-camera="{{ $camId }}">{{ $snap['available'] ?? '—' }}</p>
                        </div>
                        <div class="bg-white px-2 py-3">
                            <p class="text-red-700">Used</p>
                            <p class="js-cam-occupied text-lg font-bold text-red-800" data-camera="{{ $camId }}">{{ $snap['occupied'] ?? '—' }}</p>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-100 px-4 py-3">
                    <h3 class="font-semibold text-gray-900">Latest Detections</h3>
                </div>
                <ul id="ai-detections" class="max-h-64 divide-y divide-gray-100 overflow-y-auto text-sm">
                    @forelse (($primaryAi['detections'] ?? []) as $det)
                        <li class="flex items-center justify-between gap-3 px-4 py-3">
                            <div class="min-w-0">
                                <p class="font-medium text-gray-800">
                                    {{ $det['class'] ?? 'vehicle' }}
                                    @if (! empty($det['plate']))
                                        <span class="ml-1 text-xs text-indigo-600">[{{ $det['plate'] }}]</span>
                                    @endif
                                </p>
                                @if (! empty($det['owner_name']))
                                    <p class="mt-0.5 truncate text-xs text-emerald-700">
                                        {{ $det['owner_name'] }}
                                        @if (! empty($det['owner_id_number']))
                                            <span class="text-gray-400">· {{ $det['owner_id_number'] }}</span>
                                        @endif
                                    </p>
                                @elseif (! empty($det['plate']))
                                    <p class="mt-0.5 text-xs text-amber-700">Unregistered plate</p>
                                @endif
                            </div>
                            <span class="shrink-0 text-gray-500">{{ isset($det['confidence']) ? round($det['confidence'] * 100).'%' : '—' }}</span>
                        </li>
                    @empty
                        <li class="px-4 py-10 text-center text-gray-500">No detections yet.</li>
                    @endforelse
                </ul>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3">
                    <h3 class="font-semibold text-gray-900">AI Violation Events</h3>
                    @if (! empty($parkingUrl))
                        <a href="{{ $parkingUrl }}" class="cursor-pointer text-xs font-medium text-blue-600 hover:underline">Open parking map →</a>
                    @endif
                </div>
                <ul id="ai-events" class="max-h-64 divide-y divide-gray-100 overflow-y-auto text-sm">
                    @forelse (($primaryAi['events'] ?? []) as $evt)
                        <li class="px-4 py-3">
                            <div class="flex items-center justify-between gap-2">
                                <span class="rounded bg-amber-100 px-2 py-0.5 text-xs font-semibold uppercase text-amber-800">{{ $evt['type'] ?? 'event' }}</span>
                                <span class="text-xs text-gray-500">{{ $evt['zone_id'] ?? '' }}</span>
                            </div>
                            @if (! empty($evt['plate']))
                                <p class="mt-1 text-xs text-gray-600">
                                    Plate {{ $evt['plate'] }}
                                    @if (! empty($evt['owner_name']))
                                        <span class="text-emerald-700">· {{ $evt['owner_name'] }}</span>
                                    @elseif (($evt['registered'] ?? null) === false)
                                        <span class="text-amber-700">· Unregistered</span>
                                    @endif
                                </p>
                            @endif
                        </li>
                    @empty
                        <li class="px-4 py-10 text-center text-gray-500">No violation events yet.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
<script>
(() => {
    const statusUrl = @json($statusUrl ?? null);
    if (!statusUrl) return;

    const available = document.getElementById('ai-available');
    const occupied = document.getElementById('ai-occupied');
    const updatedAt = document.getElementById('ai-updated-at');
    const detectionsList = document.getElementById('ai-detections');
    const eventsList = document.getElementById('ai-events');

    const refresh = async () => {
        if (document.hidden) return;
        try {
            const response = await fetch(statusUrl, { headers: { Accept: 'application/json' }, cache: 'no-store', credentials: 'same-origin' });
            if (!response.ok) return;
            const data = await response.json();
            const cams = data.ai_cameras || data.cameras || {};
            Object.entries(cams).forEach(([id, snap]) => {
                const v = document.querySelector(`.js-cam-vehicles[data-camera="${id}"]`);
                const a = document.querySelector(`.js-cam-available[data-camera="${id}"]`);
                const o = document.querySelector(`.js-cam-occupied[data-camera="${id}"]`);
                if (v) v.textContent = snap.vehicle_count ?? 0;
                if (a) a.textContent = snap.available ?? '—';
                if (o) o.textContent = snap.occupied ?? '—';
            });

            const ai = data.ai;
            if (ai) {
                if (available) available.textContent = ai.available ?? '—';
                if (occupied) occupied.textContent = ai.occupied ?? '—';
                if (updatedAt) updatedAt.textContent = ai.updated_at_label || data.updated_at;

                if (detectionsList) {
                    detectionsList.replaceChildren();
                    const dets = ai.detections || [];
                    if (!dets.length) {
                        const li = document.createElement('li');
                        li.className = 'px-4 py-10 text-center text-gray-500';
                        li.textContent = 'No detections yet.';
                        detectionsList.append(li);
                    } else {
                        dets.forEach((det) => {
                            const li = document.createElement('li');
                            li.className = 'flex items-center justify-between gap-3 px-4 py-3';
                            const left = document.createElement('div');
                            left.className = 'min-w-0';
                            const name = document.createElement('p');
                            name.className = 'font-medium text-gray-800';
                            name.textContent = det.class || 'vehicle';
                            if (det.plate) {
                                const plate = document.createElement('span');
                                plate.className = 'ml-1 text-xs text-indigo-600';
                                plate.textContent = `[${det.plate}]`;
                                name.append(plate);
                            }
                            left.append(name);
                            if (det.owner_name) {
                                const owner = document.createElement('p');
                                owner.className = 'mt-0.5 truncate text-xs text-emerald-700';
                                owner.textContent = det.owner_id_number
                                    ? `${det.owner_name} · ${det.owner_id_number}`
                                    : det.owner_name;
                                left.append(owner);
                            } else if (det.plate) {
                                const unknown = document.createElement('p');
                                unknown.className = 'mt-0.5 text-xs text-amber-700';
                                unknown.textContent = 'Unregistered plate';
                                left.append(unknown);
                            }
                            const conf = document.createElement('span');
                            conf.className = 'shrink-0 text-gray-500';
                            conf.textContent = det.confidence != null ? `${Math.round(det.confidence * 100)}%` : '—';
                            li.append(left, conf);
                            detectionsList.append(li);
                        });
                    }
                }

                if (eventsList) {
                    eventsList.replaceChildren();
                    const evts = ai.events || [];
                    if (!evts.length) {
                        const li = document.createElement('li');
                        li.className = 'px-4 py-10 text-center text-gray-500';
                        li.textContent = 'No violation events yet.';
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
                                let label = `Plate ${evt.plate}`;
                                if (evt.owner_name) {
                                    label += ` · ${evt.owner_name}`;
                                } else if (evt.registered === false) {
                                    label += ' · Unregistered';
                                }
                                p.textContent = label;
                                li.append(p);
                            }
                            eventsList.append(li);
                        });
                    }
                }
            }
        } catch (e) {}
    };

    refresh();
    window.setInterval(refresh, 5000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
})();
</script>
@endpush
