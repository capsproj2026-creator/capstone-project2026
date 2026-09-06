@extends('layouts.guard')

@section('title', 'AI Parking Monitor')

@push('styles')
<style>
    #camera-expand-body img { transform-origin: center center; will-change: transform; user-select: none; }
</style>
@endpush

@section('content')
    @include('partials.shell.page-header', [
        'title' => 'AI Parking Monitor',
        'subtitle' => 'Live YOLOv9 · cars & motorcycles · parked vehicles · plate scan',
    ])

    @php
        $cameras = collect($registryCameras ?? []);
        $healthById = collect($aiCamerasHealth ?? []);
        $snaps = is_array($aiCameras ?? null) ? $aiCameras : [];
        $primaryAi = $ai ?? null;
        $latestDetections = [];
        foreach ($snaps as $snapCamId => $snap) {
            if (! is_array($snap)) {
                continue;
            }
            foreach ($snap['detections'] ?? [] as $det) {
                if (! is_array($det)) {
                    continue;
                }
                $det['_camera'] = $det['_camera'] ?? $snapCamId;
                $latestDetections[] = $det;
            }
        }
        if ($latestDetections === [] && is_array($primaryAi)) {
            foreach ($primaryAi['detections'] ?? [] as $det) {
                if (! is_array($det)) {
                    continue;
                }
                $det['_camera'] = $det['_camera'] ?? ($primaryAi['camera_id'] ?? '');
                $latestDetections[] = $det;
            }
        }
    @endphp

    @if ($cameras->isEmpty())
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-8 text-center text-sm text-amber-900">
            No AI cameras are configured. Set AI_CAMERA_* values in .env and start the AI parking service.
        </div>
    @else
        <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-medium text-gray-500">Cameras</p>
                <p class="mt-1 text-2xl font-bold text-gray-900">{{ $cameras->count() }}</p>
            </div>
            <div class="rounded-xl border border-sky-200 bg-sky-50 p-4 shadow-sm">
                <p class="text-xs font-medium text-sky-700">Parked</p>
                <p id="ai-parked-count" class="mt-1 text-2xl font-bold text-sky-800">{{ $primaryAi['parked_count'] ?? 0 }}</p>
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
                    if ($health === [] && $healthById->isNotEmpty()) {
                        $health = $healthById->first(fn ($row, $key) => strcasecmp((string) $key, $camId) === 0) ?? [];
                    }
                    $snap = $snaps[$camId] ?? null;
                    if (! is_array($snap)) {
                        foreach ($snaps as $snapKey => $candidate) {
                            if (strcasecmp((string) $snapKey, $camId) === 0 && is_array($candidate)) {
                                $snap = $candidate;
                                break;
                            }
                        }
                    }
                    if (is_array($snap) && isset($snap['camera_id']) && strcasecmp((string) $snap['camera_id'], $camId) !== 0) {
                        $snap = null;
                    }
                    $browserUrl = $health['ai_stream_url'] ?? $health['stream_browser_url'] ?? ($cam['ai_stream_url'] ?? $cam['stream_url'] ?? null);
                    $online = (bool) ($health['connected'] ?? false);
                    $showStats = $online && is_array($snap);
                    $vehicles = $showStats ? (int) ($snap['reported_vehicle_count'] ?? $snap['vehicle_count'] ?? 0) : null;
                    $free = $showStats ? ($snap['available'] ?? null) : null;
                    $used = $showStats ? ($snap['occupied'] ?? null) : null;
                    $capacity = $showStats ? ($snap['capacity'] ?? null) : null;
                    $topDet = data_get($snap, 'detections.0');
                    $plateLine = $showStats
                        ? \App\Support\AiDetectionPresenter::plateLine(is_array($topDet) ? $topDet : null)
                        : '';
                @endphp
                <article class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                    <div class="relative aspect-video bg-[#1a1d23]" data-camera-tile="{{ $camId }}" data-online="{{ $online ? '1' : '0' }}">
                        <span class="camera-clock absolute left-3 top-3 z-10 rounded bg-black/45 px-2 py-0.5 text-xs font-medium text-white tabular-nums">
                            {{ ph_now()->format('g:i:s A') }}
                        </span>
                        <span
                            data-status-badge
                            @class([
                                'absolute right-3 top-3 z-10 rounded-full px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wide',
                                'bg-emerald-500 text-white' => $online,
                                'bg-red-500 text-white' => ! $online,
                            ])
                        >{{ $online ? 'Live' : 'Offline' }}</span>

                        @if ($browserUrl)
                            <img
                                src="{{ $browserUrl }}"
                                alt="{{ $cam['name'] ?? $camId }}"
                                class="absolute inset-0 h-full w-full object-cover {{ $online ? '' : 'hidden' }}"
                                data-stream-img
                                data-camera-stream="{{ $camId }}"
                                decoding="async"
                                loading="eager"
                            >
                            <div data-stream-fallback @class([
                                'absolute inset-0 flex flex-col items-center justify-center gap-2 text-slate-400',
                                'hidden' => $online,
                            ])>
                                <i data-lucide="video-off" class="h-10 w-10 opacity-70"></i>
                                <p class="text-sm font-medium text-slate-300">Camera Offline</p>
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
                        <p
                            class="js-cam-stats mt-2 text-xs text-gray-500 {{ $showStats ? '' : 'hidden' }}"
                            data-camera="{{ $camId }}"
                        >
                            Free:
                            <span class="js-cam-available font-semibold text-green-700" data-camera="{{ $camId }}">{{ $free ?? '—' }}</span><span class="js-cam-capacity text-gray-400" data-camera="{{ $camId }}">@if ($capacity !== null)/{{ $capacity }}@endif</span>
                            · Used:
                            <span class="js-cam-occupied font-semibold text-red-700" data-camera="{{ $camId }}">{{ $used ?? '—' }}</span>
                            · Vehicles:
                            <span class="js-cam-vehicles font-semibold text-gray-800" data-camera="{{ $camId }}">{{ $vehicles ?? '—' }}</span>
                        </p>
                        <p
                            class="js-cam-plate mt-1 truncate text-xs font-medium text-indigo-700 {{ ($showStats && $plateLine !== '') ? '' : 'hidden' }}"
                            data-camera="{{ $camId }}"
                        >{{ $plateLine }}</p>
                    </div>
                </article>
            @endforeach
        </div>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-100 px-4 py-3 flex items-center justify-between gap-2">
                    <h3 class="font-semibold text-gray-900">Latest Detections</h3>
                    <span id="ai-det-count" class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-semibold text-gray-600">{{ count($latestDetections) }}</span>
                </div>
                <ul id="ai-detections" class="max-h-[32rem] divide-y divide-gray-100 overflow-y-auto text-sm">
                    @forelse ($latestDetections as $det)
                        @php
                            $detCam = $det['_camera'] ?? ($primaryAi['camera_id'] ?? '');
                            $plate = ($det['plate_status'] ?? '') === 'unreadable' ? null : ($det['plate'] ?? null);
                            $ownerName = $det['owner_name'] ?? null;
                            $ownerRole = $det['role'] ?? $det['owner_role'] ?? null;
                            $vehicleCropUrl = (! empty($det['track_id']) && $detCam !== '')
                                ? route('guard.ai-parking.vehicle-crop', ['camera' => $detCam, 'track' => $det['track_id']])
                                : null;
                        @endphp
                        <li class="flex items-start gap-3 px-4 py-3">
                            @if ($vehicleCropUrl)
                                <img
                                    src="{{ $vehicleCropUrl }}"
                                    alt="Vehicle"
                                    class="h-20 w-28 shrink-0 rounded-lg border border-gray-200 bg-slate-50 object-cover"
                                    onerror="this.classList.add('hidden')"
                                >
                            @else
                                <div class="flex h-20 w-28 shrink-0 items-center justify-center rounded-lg border border-dashed border-gray-200 bg-gray-50 text-[10px] text-gray-400">No image</div>
                            @endif
                            <div class="min-w-0 flex-1">
                                <p class="font-mono text-base font-bold tracking-wide text-indigo-800">
                                    @if (($det['plate_status'] ?? '') === 'unreadable')
                                        <span class="font-sans text-sm font-semibold text-slate-500">Plate Unreadable</span>
                                    @elseif ($plate)
                                        {{ $plate }}
                                    @else
                                        <span class="font-sans text-sm font-medium text-gray-400">Reading plate…</span>
                                    @endif
                                </p>
                                <p class="mt-1 truncate text-sm font-semibold text-gray-900">
                                    {{ $ownerName ?: ($plate ? 'Unknown Vehicle' : '—') }}
                                </p>
                                <p class="mt-0.5 text-xs text-gray-500">
                                    Role:
                                    <span class="font-medium text-gray-700">{{ $ownerRole ?: ($plate ? 'Unregistered' : '—') }}</span>
                                    @if (! empty($det['class']))
                                        · {{ ucfirst((string) $det['class']) }}
                                    @endif
                                    @if (! empty($detCam))
                                        · {{ $detCam }}
                                    @endif
                                </p>
                            </div>
                            <div class="flex shrink-0 flex-col items-end gap-2">
                                @if (! empty($det['track_id']))
                                    <button
                                        type="button"
                                        class="rounded-lg border border-indigo-200 px-2 py-1 text-[11px] font-semibold text-indigo-700 hover:bg-indigo-50"
                                        data-correct-plate
                                        data-camera="{{ $detCam }}"
                                        data-track="{{ $det['track_id'] }}"
                                        data-plate="{{ $plate ?? '' }}"
                                    >Correct</button>
                                @endif
                                <span class="text-xs text-gray-500">{{ isset($det['confidence']) ? round($det['confidence'] * 100).'%' : '—' }}</span>
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

        {{-- Full-screen expand with zoom --}}
        <div id="camera-expand-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/80 p-2 sm:p-4" role="dialog" aria-modal="true">
            <div class="relative flex h-full w-full max-w-7xl flex-col overflow-hidden rounded-xl bg-black shadow-2xl">
                <div class="flex shrink-0 flex-wrap items-center justify-between gap-2 border-b border-white/10 px-4 py-3">
                    <div class="min-w-0">
                        <p id="camera-expand-title" class="font-semibold text-white">Camera</p>
                        <p class="text-[11px] text-white/50">Scroll or use + / − to zoom. Drag when zoomed.</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button" id="camera-zoom-out" class="rounded-md bg-white/10 px-2 py-1 text-sm font-bold text-white hover:bg-white/20" aria-label="Zoom out">−</button>
                        <span id="camera-zoom-label" class="min-w-[3rem] text-center text-xs font-semibold tabular-nums text-white/80">1.0×</span>
                        <button type="button" id="camera-zoom-in" class="rounded-md bg-white/10 px-2 py-1 text-sm font-bold text-white hover:bg-white/20" aria-label="Zoom in">+</button>
                        <button type="button" id="camera-zoom-reset" class="rounded-md bg-white/10 px-2 py-1 text-xs font-semibold text-white hover:bg-white/20">Reset</button>
                        <button type="button" id="camera-expand-close" class="rounded-md p-1.5 text-white/80 hover:bg-white/10" aria-label="Close">
                            <i data-lucide="x" class="h-5 w-5"></i>
                        </button>
                    </div>
                </div>
                <div id="camera-expand-body" class="relative min-h-0 flex-1 overflow-hidden bg-[#1a1d23]"></div>
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
    const plateCropBase = @json(url('/guard/ai-parking/plate-crop'));
    const vehicleCropBase = @json(url('/guard/ai-parking/vehicle-crop'));
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

    const cropUrlFor = (cam, track, kind = 'vehicle') => {
        const base = kind === 'plate' ? plateCropBase : vehicleCropBase;
        if (!base || !cam || track == null || track === '') return '';
        return `${base}/${encodeURIComponent(cam)}/${encodeURIComponent(String(track))}`;
    };

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
    const zoomInBtn = document.getElementById('camera-zoom-in');
    const zoomOutBtn = document.getElementById('camera-zoom-out');
    const zoomResetBtn = document.getElementById('camera-zoom-reset');
    const zoomLabel = document.getElementById('camera-zoom-label');

    const zoomState = {
        cameraId: '',
        scale: 1,
        panX: 0,
        panY: 0,
        dragging: false,
        lastX: 0,
        lastY: 0,
        img: null,
    };

    const applyZoom = () => {
        if (!zoomState.img) return;
        zoomState.img.style.transform = `translate(${zoomState.panX}px, ${zoomState.panY}px) scale(${zoomState.scale})`;
        if (zoomLabel) zoomLabel.textContent = `${zoomState.scale.toFixed(1)}×`;
    };

    const setZoom = (next, cx = 0, cy = 0) => {
        const prev = zoomState.scale;
        const scale = Math.min(8, Math.max(1, next));
        if (scale === 1) {
            zoomState.scale = 1;
            zoomState.panX = 0;
            zoomState.panY = 0;
            applyZoom();
            return;
        }
        zoomState.panX = (zoomState.panX - cx) * (scale / prev) + cx;
        zoomState.panY = (zoomState.panY - cy) * (scale / prev) + cy;
        zoomState.scale = scale;
        applyZoom();
    };

    const closeModal = () => {
        modal?.classList.add('hidden');
        modal?.classList.remove('flex');
        if (modalBody) modalBody.replaceChildren();
        zoomState.img = null;
        zoomState.cameraId = '';
        zoomState.scale = 1;
        zoomState.panX = 0;
        zoomState.panY = 0;
        if (zoomLabel) zoomLabel.textContent = '1.0×';
    };

    document.querySelectorAll('[data-expand-camera]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const camId = btn.getAttribute('data-expand-camera');
            const tile = btn.closest('[data-camera-tile]');
            const card = btn.closest('article');
            const title = card?.querySelector('.font-semibold')?.textContent?.trim() || camId || 'Camera';
            const stream = tile?.querySelector('[data-stream-img]');
            zoomState.cameraId = camId || '';
            zoomState.scale = 1;
            zoomState.panX = 0;
            zoomState.panY = 0;
            if (modalTitle) modalTitle.textContent = title;
            if (modalBody) {
                modalBody.replaceChildren();
                if (stream && !stream.classList.contains('hidden')) {
                    const clone = stream.cloneNode(true);
                    clone.className = 'h-full w-full cursor-grab object-contain';
                    clone.style.transformOrigin = 'center center';
                    clone.removeAttribute('loading');
                    modalBody.append(clone);
                    zoomState.img = clone;
                    applyZoom();
                } else {
                    zoomState.img = null;
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

    zoomInBtn?.addEventListener('click', () => setZoom(zoomState.scale + 0.5));
    zoomOutBtn?.addEventListener('click', () => setZoom(zoomState.scale - 0.5));
    zoomResetBtn?.addEventListener('click', () => setZoom(1));

    modalBody?.addEventListener('wheel', (e) => {
        if (!zoomState.img || modal?.classList.contains('hidden')) return;
        e.preventDefault();
        const rect = modalBody.getBoundingClientRect();
        const cx = e.clientX - rect.left - rect.width / 2;
        const cy = e.clientY - rect.top - rect.height / 2;
        setZoom(zoomState.scale + (e.deltaY < 0 ? 0.35 : -0.35), cx, cy);
    }, { passive: false });

    modalBody?.addEventListener('pointerdown', (e) => {
        if (!zoomState.img || zoomState.scale <= 1) return;
        zoomState.dragging = true;
        zoomState.lastX = e.clientX;
        zoomState.lastY = e.clientY;
        zoomState.img.classList.remove('cursor-grab');
        zoomState.img.classList.add('cursor-grabbing');
        modalBody.setPointerCapture?.(e.pointerId);
    });
    modalBody?.addEventListener('pointermove', (e) => {
        if (!zoomState.dragging) return;
        zoomState.panX += e.clientX - zoomState.lastX;
        zoomState.panY += e.clientY - zoomState.lastY;
        zoomState.lastX = e.clientX;
        zoomState.lastY = e.clientY;
        applyZoom();
    });
    const stopDrag = () => {
        zoomState.dragging = false;
        zoomState.img?.classList.remove('cursor-grabbing');
        zoomState.img?.classList.add('cursor-grab');
    };
    modalBody?.addEventListener('pointerup', stopDrag);
    modalBody?.addEventListener('pointercancel', stopDrag);

    closeBtn?.addEventListener('click', closeModal);
    modal?.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });
    document.addEventListener('keydown', (e) => {
        if (modal?.classList.contains('hidden')) return;
        if (e.key === 'Escape') closeModal();
        if (e.key === '+' || e.key === '=') setZoom(zoomState.scale + 0.5);
        if (e.key === '-' || e.key === '_') setZoom(zoomState.scale - 0.5);
    });

    document.querySelectorAll('[data-stream-img]').forEach((img) => {
        const tile = img.closest('[data-camera-tile]');
        const setOnline = (online) => {
            if (!tile) return;
            tile.dataset.online = online ? '1' : '0';
            const badge = tile.querySelector('[data-status-badge]');
            if (badge) {
                badge.textContent = online ? 'Live' : 'Offline';
                badge.className = 'absolute right-3 top-3 z-10 rounded-full px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wide '
                    + (online ? 'bg-emerald-500 text-white' : 'bg-red-500 text-white');
            }
            const fb = tile.querySelector('[data-stream-fallback]');
            if (online) {
                img.classList.remove('hidden');
                fb?.classList.add('hidden');
            } else {
                img.classList.add('hidden');
                if (fb) {
                    fb.classList.remove('hidden');
                    const label = fb.querySelector('p');
                    if (label) label.textContent = 'Camera Offline';
                }
            }
        };
        // MJPEG can "load" blank offline placeholder frames — never treat that as Live.
        img.addEventListener('error', () => setOnline(false));
        window.__aiParkingSetCameraOnline = window.__aiParkingSetCameraOnline || {};
        const camId = tile?.getAttribute('data-camera-tile');
        if (camId) {
            window.__aiParkingSetCameraOnline[camId] = setOnline;
        }
    });

    if (window.lucide) window.lucide.createIcons();
    if (!statusUrl) return;

    const available = document.getElementById('ai-available');
    const occupied = document.getElementById('ai-occupied');
    const parkedCount = document.getElementById('ai-parked-count');
    const updatedAt = document.getElementById('ai-updated-at');
    const detectionsList = document.getElementById('ai-detections');
    const detCount = document.getElementById('ai-det-count');
    const eventsList = document.getElementById('ai-events');

    const formatDet = (det) => {
        if (!det) return '—';
        const bits = [];
        if (det.track_id != null) bits.push(`#${det.track_id}`);
        if (det.motion_state === 'parked') bits.push('Parked');
        else if (det.motion_state === 'idle') bits.push('Settling');
        if (det.plate_status === 'unreadable') bits.push('Plate Unreadable');
        else if (det.registered && det.owner_name) bits.push([det.owner_name, det.plate].filter(Boolean).join(' · '));
        else if (det.plate) bits.push(`Unknown · ${det.plate}`);
        else bits.push('Reading plate…');
        return bits.join(' · ');
    };

    const motionLabelFor = (det) => {
        if (det?.motion_state === 'parked') return 'Parked';
        if (det?.motion_state === 'idle') return 'Settling';
        if (det?.motion_label && !String(det.motion_label).toLowerCase().includes('moving')) {
            return det.motion_label;
        }
        return null;
    };

    const motionBadge = (det) => {
        const label = motionLabelFor(det);
        if (!label) return null;
        const span = document.createElement('span');
        span.className = det.motion_state === 'parked'
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
            const healthMap = data.ai_cameras_health || {};
            const ai = data.ai;
            const findByCamera = (map, id) => {
                if (!map || typeof map !== 'object') return null;
                if (map[id]) return map[id];
                const hit = Object.entries(map).find(([key]) => String(key).toLowerCase() === String(id).toLowerCase());
                return hit ? hit[1] : null;
            };

            const updateCameraStats = (id, online, snap) => {
                const statsEl = document.querySelector(`.js-cam-stats[data-camera="${id}"]`);
                const plateLine = document.querySelector(`.js-cam-plate[data-camera="${id}"]`);
                const v = document.querySelector(`.js-cam-vehicles[data-camera="${id}"]`);
                const a = document.querySelector(`.js-cam-available[data-camera="${id}"]`);
                const o = document.querySelector(`.js-cam-occupied[data-camera="${id}"]`);
                const cap = document.querySelector(`.js-cam-capacity[data-camera="${id}"]`);

                const ownSnap = snap
                    && (!snap.camera_id || String(snap.camera_id).toLowerCase() === String(id).toLowerCase())
                    ? snap
                    : null;
                const show = !!(online && ownSnap);

                if (!show) {
                    statsEl?.classList.add('hidden');
                    plateLine?.classList.add('hidden');
                    if (plateLine) plateLine.textContent = '';
                    return;
                }

                statsEl?.classList.remove('hidden');
                if (v) v.textContent = String(ownSnap.reported_vehicle_count ?? ownSnap.vehicle_count ?? 0);
                if (a) a.textContent = ownSnap.available ?? '—';
                if (o) o.textContent = ownSnap.occupied ?? '—';
                if (cap) cap.textContent = (ownSnap.capacity != null && ownSnap.capacity !== '') ? `/${ownSnap.capacity}` : '';
                const plateText = formatDet((ownSnap.detections || [])[0] || null);
                if (plateLine) {
                    plateLine.textContent = plateText;
                    plateLine.classList.toggle('hidden', !plateText);
                }
            };

            document.querySelectorAll('[data-camera-tile]').forEach((tile) => {
                const id = tile.getAttribute('data-camera-tile');
                if (!id) return;
                const health = findByCamera(healthMap, id) || {};
                const online = !!(health.connected || health.stream_reachable);
                const setter = window.__aiParkingSetCameraOnline?.[id];
                if (typeof setter === 'function') {
                    setter(online);
                } else {
                    tile.dataset.online = online ? '1' : '0';
                    const badge = tile.querySelector('[data-status-badge]');
                    if (badge) {
                        badge.textContent = online ? 'Live' : 'Offline';
                        badge.className = 'absolute right-3 top-3 z-10 rounded-full px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wide '
                            + (online ? 'bg-emerald-500 text-white' : 'bg-red-500 text-white');
                    }
                    const img = tile.querySelector('[data-stream-img]');
                    const fb = tile.querySelector('[data-stream-fallback]');
                    if (online) {
                        img?.classList.remove('hidden');
                        fb?.classList.add('hidden');
                    } else {
                        img?.classList.add('hidden');
                        fb?.classList.remove('hidden');
                    }
                }
                updateCameraStats(id, online, findByCamera(cams, id));
            });

            Object.entries(cams).forEach(([id, snap]) => {
                if (id === (ai?.camera_id || '') || Object.keys(cams).length === 1) {
                    if (parkedCount) parkedCount.textContent = String(snap.parked_count ?? 0);
                }
            });

            const allDets = [];
            Object.entries(cams).forEach(([camId, snap]) => {
                const health = findByCamera(healthMap, camId) || {};
                if (!(health.connected || health.stream_reachable)) return;
                if (snap?.camera_id && String(snap.camera_id).toLowerCase() !== String(camId).toLowerCase()) return;
                (snap.detections || []).forEach((det) => allDets.push({ ...det, _camera: camId }));
            });
            if (allDets.length === 0 && ai && (ai.detections || []).length) {
                (ai.detections || []).forEach((det) => allDets.push(det));
            }

            if (!ai && allDets.length === 0) return;

            if (ai) {
                if (available) available.textContent = ai.available ?? '—';
                if (occupied) occupied.textContent = ai.occupied ?? '—';
                if (parkedCount) parkedCount.textContent = String(ai.parked_count ?? 0);
                if (updatedAt) updatedAt.textContent = ai.updated_at_label || data.updated_at;
            }
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
                        li.className = 'flex items-start gap-3 px-4 py-3';
                        const camId = det._camera || (ai?.camera_id || '');
                        const cropSrc = cropUrlFor(camId, det.track_id, 'vehicle');
                        if (cropSrc) {
                            const crop = document.createElement('img');
                            crop.alt = 'Vehicle';
                            crop.className = 'h-20 w-28 shrink-0 rounded-lg border border-gray-200 bg-slate-50 object-cover';
                            crop.src = cropSrc + '?t=' + Date.now();
                            crop.addEventListener('error', () => {
                                const plateSrc = cropUrlFor(camId, det.track_id, 'plate');
                                if (plateSrc && crop.src.indexOf('/plate-crop/') === -1) {
                                    crop.src = plateSrc + '?t=' + Date.now();
                                    return;
                                }
                                crop.classList.add('hidden');
                            });
                            li.append(crop);
                        } else {
                            const placeholder = document.createElement('div');
                            placeholder.className = 'flex h-20 w-28 shrink-0 items-center justify-center rounded-lg border border-dashed border-gray-200 bg-gray-50 text-[10px] text-gray-400';
                            placeholder.textContent = 'No image';
                            li.append(placeholder);
                        }

                        const left = document.createElement('div');
                        left.className = 'min-w-0 flex-1';

                        const plateEl = document.createElement('p');
                        plateEl.className = 'font-mono text-base font-bold tracking-wide text-indigo-800';
                        if (det.plate_status === 'unreadable') {
                            plateEl.innerHTML = '<span class="font-sans text-sm font-semibold text-slate-500">Plate Unreadable</span>';
                        } else if (det.plate) {
                            plateEl.textContent = det.plate;
                        } else {
                            plateEl.innerHTML = '<span class="font-sans text-sm font-medium text-gray-400">Reading plate…</span>';
                        }
                        left.append(plateEl);

                        const nameEl = document.createElement('p');
                        nameEl.className = 'mt-1 truncate text-sm font-semibold text-gray-900';
                        nameEl.textContent = det.owner_name || (det.plate ? 'Unknown Vehicle' : '—');
                        left.append(nameEl);

                        const roleEl = document.createElement('p');
                        roleEl.className = 'mt-0.5 text-xs text-gray-500';
                        const role = det.role || det.owner_role || (det.plate ? 'Unregistered' : '—');
                        const bits = [`Role: ${role}`];
                        if (det.class) bits.push(String(det.class).charAt(0).toUpperCase() + String(det.class).slice(1));
                        if (camId) bits.push(camId);
                        roleEl.textContent = bits.join(' · ');
                        left.append(roleEl);

                        const right = document.createElement('div');
                        right.className = 'flex shrink-0 flex-col items-end gap-2';
                        if (det.track_id != null) {
                            const corr = document.createElement('button');
                            corr.type = 'button';
                            corr.className = 'rounded-lg border border-indigo-200 px-2 py-1 text-[11px] font-semibold text-indigo-700 hover:bg-indigo-50';
                            corr.textContent = 'Correct';
                            corr.dataset.correctPlate = '1';
                            corr.dataset.camera = camId;
                            corr.dataset.track = String(det.track_id);
                            corr.dataset.plate = det.plate || '';
                            right.append(corr);
                        }
                        const conf = document.createElement('span');
                        conf.className = 'text-xs text-gray-500';
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
    window.setInterval(refresh, 1000);
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
