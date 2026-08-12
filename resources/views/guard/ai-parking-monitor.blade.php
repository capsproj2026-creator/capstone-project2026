@extends('layouts.guard')

@section('title', 'AI Parking Monitor')

@section('content')
    @include('partials.shell.page-header', [
        'title' => 'AI Parking Monitor',
        'subtitle' => 'Live YOLOv9 · cars & motorcycles · parked / moving · plate scan',
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
        <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-medium text-gray-500">Cameras</p>
                <p class="mt-1 text-2xl font-bold text-gray-900">{{ $cameras->count() }}</p>
            </div>
            <div class="rounded-xl border border-sky-200 bg-sky-50 p-4 shadow-sm">
                <p class="text-xs font-medium text-sky-700">Parked</p>
                <p id="ai-parked-count" class="mt-1 text-2xl font-bold text-sky-800">{{ $primaryAi['parked_count'] ?? 0 }}</p>
            </div>
            <div class="rounded-xl border border-orange-200 bg-orange-50 p-4 shadow-sm">
                <p class="text-xs font-medium text-orange-700">Moving</p>
                <p id="ai-moving-count" class="mt-1 text-2xl font-bold text-orange-800">{{ $primaryAi['moving_count'] ?? 0 }}</p>
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

        <div class="mb-6 grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($cameras as $cam)
                @php
                    $camId = (string) ($cam['id'] ?? '');
                    $health = $healthById->get($camId, []);
                    $snap = $snaps[$camId] ?? [];
                    $browserUrl = $health['ai_stream_url'] ?? $health['stream_browser_url'] ?? ($cam['ai_stream_url'] ?? $cam['stream_url'] ?? null);
                    $online = (bool) ($health['connected'] ?? $health['stream_reachable'] ?? ! empty($browserUrl));
                    $topDet = data_get($snap, 'detections.0');
                    $plateLine = \App\Support\AiDetectionPresenter::plateLine(is_array($topDet) ? $topDet : null);
                @endphp
                <article class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                    <div class="relative aspect-video bg-[#1a1d23]" data-camera-tile="{{ $camId }}">
                        <span class="camera-clock absolute left-3 top-3 z-10 rounded bg-black/45 px-2 py-0.5 text-xs font-medium text-white tabular-nums">
                            {{ ph_now()->format('g:i:s A') }}
                        </span>
                        <span @class([
                            'absolute right-3 top-3 z-10 rounded-full px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wide',
                            'bg-emerald-500 text-white' => $online,
                            'bg-red-500 text-white' => ! $online,
                        ])>{{ $online ? 'Live' : 'Offline' }}</span>

                        @if ($browserUrl && $online)
                            <img
                                src="{{ $browserUrl }}"
                                alt="{{ $cam['name'] ?? $camId }}"
                                class="absolute inset-0 h-full w-full object-cover"
                                data-stream-img
                                data-camera-stream="{{ $camId }}"
                            >
                            <div data-stream-fallback class="hidden absolute inset-0 flex flex-col items-center justify-center gap-2 text-slate-400">
                                <i data-lucide="camera" class="h-10 w-10 opacity-70"></i>
                                <p class="text-sm font-medium text-slate-300">Connecting…</p>
                            </div>
                        @else
                            <div class="absolute inset-0 flex flex-col items-center justify-center gap-2 text-slate-500">
                                <i data-lucide="video-off" class="h-10 w-10 opacity-60"></i>
                                <p class="text-sm font-medium text-slate-400">Camera Offline</p>
                            </div>
                        @endif

                        <button
                            type="button"
                            class="absolute bottom-3 right-3 z-10 rounded-md bg-black/50 p-1.5 text-white hover:bg-black/70"
                            title="Full screen"
                            data-expand-camera="{{ $camId }}"
                            aria-label="Expand {{ $cam['name'] ?? $camId }}"
                        >
                            <i data-lucide="maximize-2" class="h-4 w-4"></i>
                        </button>
                    </div>

                    <div class="border-t border-gray-100 px-4 py-3">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="truncate font-semibold text-gray-900">{{ $cam['name'] ?? $camId }}</p>
                                <p class="mt-0.5 truncate text-xs text-gray-500">{{ $camId }} · {{ $cam['location'] ?? 'Campus' }}</p>
                            </div>
                            <span class="shrink-0 rounded bg-blue-50 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-blue-700">AI</span>
                        </div>
                        <p class="mt-2 text-xs text-gray-500">
                            Vehicles: <span class="js-cam-vehicles font-semibold text-gray-800" data-camera="{{ $camId }}">{{ $snap['vehicle_count'] ?? 0 }}</span>
                            · Free: <span class="js-cam-available font-semibold text-green-700" data-camera="{{ $camId }}">{{ $snap['available'] ?? '—' }}</span>
                            · Used: <span class="js-cam-occupied font-semibold text-red-700" data-camera="{{ $camId }}">{{ $snap['occupied'] ?? '—' }}</span>
                        </p>
                        <p class="js-cam-plate mt-1 truncate text-xs font-medium text-indigo-700" data-camera="{{ $camId }}">{{ $plateLine }}</p>
                    </div>
                </article>
            @endforeach
        </div>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-100 px-4 py-3 flex items-center justify-between gap-2">
                    <h3 class="font-semibold text-gray-900">Latest Detections</h3>
                    <span id="ai-det-count" class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-semibold text-gray-600">{{ count($primaryAi['detections'] ?? []) }}</span>
                </div>
                <ul id="ai-detections" class="max-h-[32rem] divide-y divide-gray-100 overflow-y-auto text-sm">
                    @forelse (($primaryAi['detections'] ?? []) as $det)
                        <li class="flex items-center justify-between gap-3 px-4 py-3">
                            <div class="min-w-0">
                                <p class="font-medium text-gray-800">
                                    @if (! empty($det['track_id']))
                                        <span class="text-xs text-gray-400">#{{ $det['track_id'] }}</span>
                                    @endif
                                    {{ $det['class'] ?? 'vehicle' }}
                                    @if (($det['class'] ?? '') === 'motorcycle')
                                        <span class="ml-1 rounded bg-emerald-100 px-1.5 py-0.5 text-[10px] font-bold uppercase text-emerald-800">MC</span>
                                    @endif
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
                                        <span class="ml-1 text-xs text-slate-500">[Plate Unreadable]</span>
                                    @elseif (! empty($det['plate']))
                                        <span class="ml-1 rounded bg-indigo-100 px-1.5 py-0.5 text-xs font-bold tracking-wide text-indigo-800">{{ $det['plate'] }}</span>
                                    @endif
                                </p>
                                @if (($det['plate_status'] ?? '') === 'unreadable')
                                    <p class="mt-0.5 text-xs text-slate-500">Plate Unreadable</p>
                                @elseif (! empty($det['registered']) && ! empty($det['owner_name']))
                                    <p class="mt-0.5 truncate text-xs text-emerald-700">{{ $det['owner_name'] }}</p>
                                @elseif (! empty($det['plate']))
                                    <p class="mt-0.5 text-xs text-amber-700">Unknown Vehicle · Plate Not Registered</p>
                                @else
                                    <p class="mt-0.5 text-xs text-gray-400">Scanning plate…</p>
                                @endif
                            </div>
                            <div class="flex shrink-0 items-center gap-2">
                                @if (! empty($det['track_id']))
                                    <button
                                        type="button"
                                        class="rounded-lg border border-indigo-200 px-2 py-1 text-[11px] font-semibold text-indigo-700 hover:bg-indigo-50"
                                        data-correct-plate
                                        data-camera="{{ $det['_camera'] ?? ($primaryAi['camera_id'] ?? '') }}"
                                        data-track="{{ $det['track_id'] }}"
                                        data-plate="{{ $det['plate'] ?? '' }}"
                                    >Correct</button>
                                @endif
                                <span class="text-gray-500">{{ isset($det['confidence']) ? round($det['confidence'] * 100).'%' : '—' }}</span>
                            </div>
                        </li>
                    @empty
                        <li class="px-4 py-10 text-center text-gray-500">No vehicles detected.</li>
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
                                <p class="mt-1 text-xs text-gray-600">Plate {{ $evt['plate'] }}</p>
                            @endif
                        </li>
                    @empty
                        <li class="px-4 py-10 text-center text-gray-500">No violation events yet.</li>
                    @endforelse
                </ul>
            </div>
        </div>

        {{-- Full-screen expand (same pattern as Live Cameras) --}}
        <div id="camera-expand-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/80 p-2 sm:p-4" role="dialog" aria-modal="true">
            <div class="relative flex h-full w-full max-w-7xl flex-col overflow-hidden rounded-xl bg-black shadow-2xl">
                <div class="flex shrink-0 items-center justify-between border-b border-white/10 px-4 py-3">
                    <p id="camera-expand-title" class="font-semibold text-white">Camera</p>
                    <button type="button" id="camera-expand-close" class="rounded-md p-1.5 text-white/80 hover:bg-white/10" aria-label="Close">
                        <i data-lucide="x" class="h-5 w-5"></i>
                    </button>
                </div>
                <div id="camera-expand-body" class="relative min-h-0 flex-1 bg-[#1a1d23]"></div>
            </div>
        </div>
    @endif

    <div id="plate-correct-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4" role="dialog" aria-modal="true">
        <form id="plate-correct-form" class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
            <h3 class="text-lg font-bold text-gray-900">Correct plate number</h3>
            <p class="mt-1 text-sm text-gray-500">Fixes a bad OCR read and looks up the registered owner.</p>
            <input type="hidden" id="plate-correct-camera">
            <input type="hidden" id="plate-correct-track">
            <label class="mt-4 block text-sm font-medium text-gray-700" for="plate-correct-value">Plate</label>
            <input id="plate-correct-value" type="text" required minlength="4" maxlength="32" class="mt-1 w-full rounded-xl border border-gray-200 px-3 py-2 font-mono text-sm uppercase" placeholder="ABC1234 or 0501-0401328">
            <p id="plate-correct-error" class="mt-2 hidden text-sm text-red-600"></p>
            <div class="mt-5 flex justify-end gap-2">
                <button type="button" id="plate-correct-cancel" class="rounded-xl border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-700">Cancel</button>
                <button type="submit" class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white">Save plate</button>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
<script>
(() => {
    const statusUrl = @json($statusUrl ?? null);
    const correctUrl = @json($correctPlateUrl ?? null);
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

    const clocks = () => {
        const label = new Date().toLocaleTimeString([], { hour: 'numeric', minute: '2-digit', second: '2-digit' });
        document.querySelectorAll('.camera-clock').forEach((el) => { el.textContent = label; });
    };
    clocks();
    window.setInterval(clocks, 1000);

    const modal = document.getElementById('camera-expand-modal');
    const modalTitle = document.getElementById('camera-expand-title');
    const modalBody = document.getElementById('camera-expand-body');
    const closeBtn = document.getElementById('camera-expand-close');

    const closeModal = () => {
        modal?.classList.add('hidden');
        modal?.classList.remove('flex');
        if (modalBody) modalBody.replaceChildren();
    };

    document.querySelectorAll('[data-expand-camera]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const camId = btn.getAttribute('data-expand-camera');
            const tile = btn.closest('[data-camera-tile]');
            const card = btn.closest('article');
            const title = card?.querySelector('.font-semibold')?.textContent?.trim() || camId || 'Camera';
            const stream = tile?.querySelector('[data-stream-img]');
            if (modalTitle) modalTitle.textContent = title;
            if (modalBody) {
                modalBody.replaceChildren();
                if (stream && !stream.classList.contains('hidden')) {
                    const clone = stream.cloneNode(true);
                    clone.className = 'h-full w-full object-contain';
                    clone.removeAttribute('loading');
                    modalBody.append(clone);
                } else {
                    const placeholder = document.createElement('div');
                    placeholder.className = 'flex h-full min-h-[50vh] items-center justify-center text-slate-400';
                    placeholder.textContent = 'No live stream available';
                    modalBody.append(placeholder);
                }
            }
            modal?.classList.remove('hidden');
            modal?.classList.add('flex');
            if (window.lucide) window.lucide.createIcons();
        });
    });

    closeBtn?.addEventListener('click', closeModal);
    modal?.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeModal(); });

    document.querySelectorAll('[data-stream-img]').forEach((img) => {
        img.addEventListener('error', () => {
            img.classList.add('hidden');
            const fb = img.closest('[data-camera-tile]')?.querySelector('[data-stream-fallback]');
            if (fb) fb.classList.remove('hidden');
        });
    });

    if (window.lucide) window.lucide.createIcons();
    if (!statusUrl) return;

    const available = document.getElementById('ai-available');
    const occupied = document.getElementById('ai-occupied');
    const parkedCount = document.getElementById('ai-parked-count');
    const movingCount = document.getElementById('ai-moving-count');
    const updatedAt = document.getElementById('ai-updated-at');
    const detectionsList = document.getElementById('ai-detections');
    const detCount = document.getElementById('ai-det-count');
    const eventsList = document.getElementById('ai-events');

    const formatDet = (det) => {
        if (!det) return '—';
        const bits = [];
        if (det.track_id != null) bits.push(`#${det.track_id}`);
        if (det.motion_label) bits.push(det.motion_label);
        else if (det.motion_state === 'moving') bits.push('Moving');
        else if (det.motion_state === 'parked') bits.push('Parked');
        else if (det.motion_state === 'idle') bits.push('Settling');
        if (det.plate_status === 'unreadable') bits.push('Plate Unreadable');
        else if (det.registered && det.owner_name) bits.push([det.owner_name, det.plate].filter(Boolean).join(' · '));
        else if (det.plate) bits.push(`Unknown · ${det.plate}`);
        else bits.push('Scanning plate…');
        return bits.join(' · ');
    };

    const motionLabelFor = (det) => {
        if (det?.motion_label) return det.motion_label;
        if (det?.motion_state === 'moving') return 'Moving';
        if (det?.motion_state === 'parked') return 'Parked';
        if (det?.motion_state === 'idle') return 'Settling';
        return null;
    };

    const motionBadge = (det) => {
        const label = motionLabelFor(det);
        if (!label) return null;
        const span = document.createElement('span');
        span.className = det.motion_state === 'moving'
            ? 'ml-1 rounded px-1.5 py-0.5 text-[10px] font-bold uppercase bg-orange-100 text-orange-700'
            : det.motion_state === 'parked'
                ? 'ml-1 rounded px-1.5 py-0.5 text-[10px] font-bold uppercase bg-sky-100 text-sky-700'
                : 'ml-1 rounded px-1.5 py-0.5 text-[10px] font-bold uppercase bg-gray-100 text-gray-600';
        span.textContent = label;
        return span;
    };

    const refresh = async () => {
        if (document.hidden) return;
        try {
            const response = await fetch(statusUrl, { headers: { Accept: 'application/json' }, cache: 'no-store', credentials: 'same-origin' });
            if (!response.ok) return;
            const data = await response.json();
            const cams = data.ai_cameras || data.cameras || {};
            const ai = data.ai;
            Object.entries(cams).forEach(([id, snap]) => {
                const v = document.querySelector(`.js-cam-vehicles[data-camera="${id}"]`);
                const a = document.querySelector(`.js-cam-available[data-camera="${id}"]`);
                const o = document.querySelector(`.js-cam-occupied[data-camera="${id}"]`);
                const plateLine = document.querySelector(`.js-cam-plate[data-camera="${id}"]`);
                if (v) v.textContent = snap.vehicle_count ?? 0;
                if (a) a.textContent = snap.available ?? '—';
                if (o) o.textContent = snap.occupied ?? '—';
                if (plateLine) plateLine.textContent = formatDet((snap.detections || [])[0] || null);
                if (id === (ai?.camera_id || '') || Object.keys(cams).length === 1) {
                    if (parkedCount) parkedCount.textContent = String(snap.parked_count ?? 0);
                    if (movingCount) movingCount.textContent = String(snap.moving_count ?? 0);
                }
            });

            if (!ai) return;

            const allDets = [];
            Object.entries(cams).forEach(([camId, snap]) => {
                (snap.detections || []).forEach((det) => allDets.push({ ...det, _camera: camId }));
            });
            if (allDets.length === 0 && (ai.detections || []).length) {
                (ai.detections || []).forEach((det) => allDets.push(det));
            }

            if (available) available.textContent = ai.available ?? '—';
            if (occupied) occupied.textContent = ai.occupied ?? '—';
            if (parkedCount) parkedCount.textContent = String(ai.parked_count ?? 0);
            if (movingCount) movingCount.textContent = String(ai.moving_count ?? 0);
            if (updatedAt) updatedAt.textContent = ai.updated_at_label || data.updated_at;
            if (detCount) detCount.textContent = String(allDets.length);

            if (detectionsList) {
                detectionsList.replaceChildren();
                if (!allDets.length) {
                    const li = document.createElement('li');
                    li.className = 'px-4 py-10 text-center text-gray-500';
                    li.textContent = 'No vehicles detected.';
                    detectionsList.append(li);
                } else {
                    allDets.forEach((det) => {
                        const li = document.createElement('li');
                        li.className = 'flex items-center justify-between gap-3 px-4 py-3';
                        const left = document.createElement('div');
                        left.className = 'min-w-0';
                        const name = document.createElement('p');
                        name.className = 'font-medium text-gray-800';
                        if (det.track_id != null) {
                            const tid = document.createElement('span');
                            tid.className = 'text-xs text-gray-400';
                            tid.textContent = `#${det.track_id} `;
                            name.append(tid);
                        }
                            name.append(document.createTextNode(det.class || 'vehicle'));
                            if (det.class === 'motorcycle') {
                                const mc = document.createElement('span');
                                mc.className = 'ml-1 rounded bg-emerald-100 px-1.5 py-0.5 text-[10px] font-bold uppercase text-emerald-800';
                                mc.textContent = 'MC';
                                name.append(mc);
                            }
                            if (det._camera) {
                                const camTag = document.createElement('span');
                                camTag.className = 'ml-1 text-[10px] font-medium text-gray-400';
                                camTag.textContent = `[${det._camera}]`;
                                name.append(camTag);
                            }
                            const badge = motionBadge(det);
                        if (badge) name.append(badge);
                        if (det.plate_status === 'unreadable') {
                            const plate = document.createElement('span');
                            plate.className = 'ml-1 text-xs text-slate-500';
                            plate.textContent = '[Plate Unreadable]';
                            name.append(plate);
                        } else if (det.plate) {
                            const plate = document.createElement('span');
                            plate.className = 'ml-1 rounded bg-indigo-100 px-1.5 py-0.5 text-xs font-bold tracking-wide text-indigo-800';
                            plate.textContent = det.plate;
                            name.append(plate);
                        }
                        left.append(name);
                        const sub = document.createElement('p');
                        sub.className = 'mt-0.5 text-xs';
                        if (det.plate_status === 'unreadable') {
                            sub.className += ' text-slate-500';
                            sub.textContent = 'Plate Unreadable';
                        } else if (det.registered && det.owner_name) {
                            sub.className += ' text-emerald-700';
                            sub.textContent = det.owner_name;
                        } else if (det.plate) {
                            sub.className += ' text-amber-700';
                            sub.textContent = 'Unknown Vehicle · Plate Not Registered';
                        } else {
                            sub.className += ' text-gray-400';
                            sub.textContent = 'Scanning plate…';
                        }
                        left.append(sub);
                        const right = document.createElement('div');
                        right.className = 'flex shrink-0 items-center gap-2';
                        if (det.track_id != null) {
                            const corr = document.createElement('button');
                            corr.type = 'button';
                            corr.className = 'rounded-lg border border-indigo-200 px-2 py-1 text-[11px] font-semibold text-indigo-700 hover:bg-indigo-50';
                            corr.textContent = 'Correct';
                            corr.dataset.correctPlate = '1';
                            corr.dataset.camera = det._camera || (ai?.camera_id || '');
                            corr.dataset.track = String(det.track_id);
                            corr.dataset.plate = det.plate || '';
                            right.append(corr);
                        }
                        const conf = document.createElement('span');
                        conf.className = 'text-gray-500';
                        conf.textContent = det.confidence != null ? `${Math.round(det.confidence * 100)}%` : '—';
                        right.append(conf);
                        li.append(left, right);
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
                            p.textContent = `Plate ${evt.plate}`;
                            li.append(p);
                        }
                        eventsList.append(li);
                    });
                }
            }
        } catch (e) {}
    };

    refresh();
    window.setInterval(refresh, 5000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });

    const plateModal = document.getElementById('plate-correct-modal');
    const plateForm = document.getElementById('plate-correct-form');
    const plateErr = document.getElementById('plate-correct-error');

    const closePlateModal = () => {
        plateModal?.classList.add('hidden');
        plateModal?.classList.remove('flex');
        plateErr?.classList.add('hidden');
    };

    document.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-correct-plate]');
        if (!btn) return;
        document.getElementById('plate-correct-camera').value = btn.dataset.camera || '';
        document.getElementById('plate-correct-track').value = btn.dataset.track || '';
        document.getElementById('plate-correct-value').value = btn.dataset.plate || '';
        plateModal?.classList.remove('hidden');
        plateModal?.classList.add('flex');
        document.getElementById('plate-correct-value')?.focus();
    });

    document.getElementById('plate-correct-cancel')?.addEventListener('click', closePlateModal);
    plateModal?.addEventListener('click', (e) => { if (e.target === plateModal) closePlateModal(); });

    plateForm?.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!correctUrl) return;
        try {
            const res = await fetch(correctUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    camera_id: document.getElementById('plate-correct-camera')?.value,
                    track_id: Number(document.getElementById('plate-correct-track')?.value),
                    plate: document.getElementById('plate-correct-value')?.value,
                }),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) {
                if (plateErr) {
                    plateErr.textContent = data.message || 'Could not save plate.';
                    plateErr.classList.remove('hidden');
                }
                return;
            }
            closePlateModal();
            refresh();
        } catch (err) {
            if (plateErr) {
                plateErr.textContent = 'Network error.';
                plateErr.classList.remove('hidden');
            }
        }
    });
})();
</script>
@endpush
