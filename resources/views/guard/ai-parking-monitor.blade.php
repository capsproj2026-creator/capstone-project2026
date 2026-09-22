@extends('layouts.guard')

@section('title', 'AI Parking Monitor')

@push('styles')
<style>
    [data-camera-tile]:fullscreen,
    [data-camera-tile]:-webkit-full-screen {
        width: 100vw;
        height: 100vh;
        background: #0b0f14;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    [data-camera-tile]:fullscreen [data-stream-img],
    [data-camera-tile]:-webkit-full-screen [data-stream-img] {
        position: static;
        width: 100%;
        height: 100%;
        object-fit: contain;
    }
    [data-fullscreen-camera] i,
    [data-fullscreen-camera] svg { pointer-events: none; }
    @keyframes ai-det-scan-pulse {
        0%, 100% { box-shadow: 0 0 0 0 rgba(99, 102, 241, 0.55); opacity: 1; }
        50% { box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.15); opacity: 0.85; }
    }
    .ai-det-thumb-scanning {
        animation: ai-det-scan-pulse 1.1s ease-in-out infinite;
        outline: 2px solid rgb(129 140 248);
        outline-offset: 1px;
    }
    @keyframes ai-scan-spin {
        to { transform: rotate(360deg); }
    }
    #ai-scan-spinner.ai-scan-active {
        animation: ai-scan-spin 0.8s linear infinite;
    }
</style>
@endpush

@section('content')
    @include('partials.shell.page-header', [
        'title' => 'AI Parking Monitor',
        'subtitle' => 'Live YOLOv9 · cars & motorcycles · parked vehicles · plate scan',
    ])

    <div id="ai-scan-status" class="mb-4 flex items-center gap-2 text-sm text-gray-600" aria-live="polite">
        <span
            id="ai-scan-spinner"
            class="inline-block h-3.5 w-3.5 shrink-0 rounded-full border-2 border-slate-300 border-t-indigo-500 opacity-40"
            aria-hidden="true"
        ></span>
        <span id="ai-scan-label">Monitoring — waiting for vehicle movement</span>
    </div>

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
                // Latest Detections: parked / tracked vehicles (include plate failures).
                $status = strtolower((string) ($det['plate_status'] ?? ''));
                $plate = trim((string) ($det['plate'] ?? ''));
                $hasTrack = isset($det['track_id']) && $det['track_id'] !== null && $det['track_id'] !== '';
                if ($plate === '' && ! in_array($status, ['unreadable', 'not_read', 'ok', 'pending'], true) && ! $hasTrack) {
                    continue;
                }
                if ($plate === '' && ! $hasTrack && ! in_array($status, ['unreadable', 'not_read'], true)) {
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
                $status = strtolower((string) ($det['plate_status'] ?? ''));
                $plate = trim((string) ($det['plate'] ?? ''));
                $hasTrack = isset($det['track_id']) && $det['track_id'] !== null && $det['track_id'] !== '';
                if ($plate === '' && ! $hasTrack && ! in_array($status, ['unreadable', 'not_read'], true)) {
                    continue;
                }
                $det['_camera'] = $det['_camera'] ?? ($primaryAi['camera_id'] ?? '');
                $latestDetections[] = $det;
            }
        }

        // Header totals cover every camera. Areas are deduped so two cameras on the
        // same parking lot do not double-count its slots.
        $totalAvailable = null;
        $totalOccupied = null;
        $totalParked = 0;
        $countedAreas = [];
        foreach ($snaps as $snapCamId => $snap) {
            if (! is_array($snap)) {
                continue;
            }
            $totalParked += (int) ($snap['parked_count'] ?? 0);
            $areaKey = $snap['area_id'] ?? ('cam:'.$snapCamId);
            if (isset($countedAreas[$areaKey])) {
                continue;
            }
            $countedAreas[$areaKey] = true;
            if (array_key_exists('available', $snap) && $snap['available'] !== null && $snap['available'] !== '') {
                $totalAvailable = (int) $totalAvailable + (int) $snap['available'];
            }
            if (array_key_exists('occupied', $snap) && $snap['occupied'] !== null && $snap['occupied'] !== '') {
                $totalOccupied = (int) $totalOccupied + (int) $snap['occupied'];
            }
        }
        if ($countedAreas === [] && is_array($primaryAi)) {
            $totalAvailable = $primaryAi['available'] ?? null;
            $totalOccupied = $primaryAi['occupied'] ?? null;
            $totalParked = (int) ($primaryAi['parked_count'] ?? 0);
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
                <p id="ai-parked-count" class="mt-1 text-2xl font-bold text-sky-800">{{ $totalParked }}</p>
            </div>
            <div class="rounded-xl border border-green-200 bg-green-50 p-4 shadow-sm">
                <p class="text-xs font-medium text-green-700">Available</p>
                <p id="ai-available" class="mt-1 text-2xl font-bold text-green-800">{{ $totalAvailable ?? '—' }}</p>
            </div>
            <div class="rounded-xl border border-red-200 bg-red-50 p-4 shadow-sm">
                <p class="text-xs font-medium text-red-700">Occupied</p>
                <p id="ai-occupied" class="mt-1 text-2xl font-bold text-red-800">{{ $totalOccupied ?? '—' }}</p>
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
                    <div class="relative aspect-video bg-[#1a1d23]" data-camera-tile="{{ $camId }}" data-camera-id="{{ $camId }}" data-online="{{ $online ? '1' : '0' }}">
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
                            @php
                                $snapshotUrl = preg_replace('#/stream\.mjpg(\?.*)?$#i', '/snapshot.jpg$1', (string) $browserUrl) ?: (string) $browserUrl;
                            @endphp
                            {{-- Finite JPEG snapshot first (tab can finish load); JS upgrades to continuous MJPEG after window load. --}}
                            <img
                                src="{{ $snapshotUrl }}"
                                data-stream-src="{{ $browserUrl }}"
                                data-snapshot-src="{{ $snapshotUrl }}"
                                alt="{{ $cam['name'] ?? $camId }}"
                                class="absolute inset-0 z-[1] h-full w-full object-cover {{ $online ? '' : 'hidden' }}"
                                data-stream-img
                                data-camera-stream="{{ $camId }}"
                                decoding="async"
                            >
                            <div data-stream-fallback @class([
                                'absolute inset-0 z-[2] flex flex-col items-center justify-center gap-2 text-slate-400',
                                'hidden' => $online,
                            ])>
                                <i data-lucide="video-off" class="h-10 w-10 opacity-70"></i>
                                <p class="text-sm font-medium text-slate-300">{{ $online ? 'Connecting…' : 'Camera Offline' }}</p>
                            </div>
                        @else
                            <div class="absolute inset-0 flex flex-col items-center justify-center gap-2 text-slate-500">
                                <i data-lucide="video-off" class="h-10 w-10 opacity-60"></i>
                                <p class="text-sm font-medium text-slate-400">Camera Offline</p>
                            </div>
                        @endif

                        <button
                            type="button"
                            class="absolute bottom-3 right-3 z-30 rounded-md bg-black/50 p-1.5 text-white hover:bg-black/70"
                            title="Full screen"
                            data-fullscreen-camera="{{ $camId }}"
                            data-camera-id="{{ $camId }}"
                            aria-label="Fullscreen {{ $cam['name'] ?? $camId }}"
                        >
                            <i data-lucide="maximize-2" class="pointer-events-none h-4 w-4"></i>
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
                            $plate = in_array(($det['plate_status'] ?? ''), ['unreadable', 'not_read'], true) ? null : ($det['plate'] ?? null);
                            $ownerName = $det['owner_name'] ?? null;
                            $ownerRole = $det['role'] ?? $det['owner_role'] ?? null;
                                    @endphp
                        <li class="flex items-start gap-3 px-4 py-3">
                            @php
                                $thumbB64 = $det['thumb_jpeg_base64'] ?? null;
                                $aiOrigin = rtrim((string) ($aiCropOrigin ?? ''), '/');
                                $plateCropUrl = null;
                                $vehicleCropUrl = null;
                                if (! empty($det['track_id']) && $detCam !== '') {
                                    if ($aiOrigin !== '') {
                                        $plateCropUrl = $aiOrigin.'/'.$detCam.'/plate-crop/'.$det['track_id'].'.jpg';
                                        $vehicleCropUrl = $aiOrigin.'/'.$detCam.'/vehicle-crop/'.$det['track_id'].'.jpg';
                                    } else {
                                        $plateCropUrl = route('guard.ai-parking.plate-crop', ['camera' => $detCam, 'track' => $det['track_id']]);
                                        $vehicleCropUrl = route('guard.ai-parking.vehicle-crop', ['camera' => $detCam, 'track' => $det['track_id']]);
                                    }
                                }
                                $thumbSrc = $thumbB64
                                    ? 'data:image/jpeg;base64,'.$thumbB64
                                    : ($vehicleCropUrl ?: $plateCropUrl);
                            @endphp
                            @if ($thumbSrc)
                                <img
                                    src="{{ $thumbSrc }}"
                                    alt="{{ ($det['motion_state'] ?? '') === 'moving' ? 'Scanning vehicle' : 'Vehicle' }}"
                                    @class([
                                        'h-20 w-28 shrink-0 rounded-lg border border-gray-200 bg-slate-900 object-cover',
                                        'ai-det-thumb-scanning' => ($det['motion_state'] ?? '') === 'moving',
                                    ])
                                    data-det-thumb
                                    data-plate-crop="{{ $plateCropUrl }}"
                                    data-vehicle-crop="{{ $vehicleCropUrl }}"
                                    data-motion="{{ $det['motion_state'] ?? '' }}"
                                    onerror="window.aiDetThumbFallback && window.aiDetThumbFallback(this)"
                                >
                            @else
                                <div class="flex h-20 w-28 shrink-0 items-center justify-center rounded-lg border border-dashed border-gray-200 bg-gray-50 text-[10px] text-gray-400">No image</div>
                            @endif
                            <div class="min-w-0 flex-1">
                                <p class="font-mono text-base font-bold tracking-wide text-indigo-800">
                                    @if (($det['plate_status'] ?? '') === 'unreadable')
                                        <span class="font-sans text-sm font-semibold text-slate-500">Plate Unreadable</span>
                                    @elseif (($det['plate_status'] ?? '') === 'not_read')
                                        <span class="font-sans text-sm font-semibold text-slate-500">Plate Not Read</span>
                                    @elseif ($plate)
                                        {{ $plate }}
                                    @else
                                        @php
                                            $waitLabel = \App\Support\AiDetectionPresenter::unresolvedPlateLabel($det);
                                            $isScanning = $waitLabel !== '—';
                                        @endphp
                                        <span @class([
                                            'font-sans text-sm font-medium',
                                            'text-indigo-500 animate-pulse' => $isScanning,
                                            'text-gray-400' => ! $isScanning,
                                        ])>{{ $waitLabel }}</span>
                                    @endif
                                </p>
                                <p class="mt-0.5 text-xs text-gray-500">
                                    @php
                                        $motionState = (string) ($det['motion_state'] ?? '');
                                        $motionLabel = match ($motionState) {
                                            'moving' => 'Moving',
                                            'parked' => 'Parked',
                                            default => null,
                                        };
                                    @endphp
                                    @if ($motionLabel)
                                        <span @class([
                                            'font-medium',
                                            'text-orange-700' => ($det['motion_state'] ?? '') === 'moving',
                                            'text-sky-700' => ($det['motion_state'] ?? '') !== 'moving',
                                        ])>{{ $motionLabel }}</span>
                                        ·
                                @endif
                                    Role:
                                    <span class="font-medium text-gray-700">
                                        @if (in_array(($det['plate_status'] ?? ''), ['unreadable', 'not_read'], true) && ! $plate)
                                            —
                                        @elseif ($ownerRole)
                                            {{ $ownerRole }}
                                        @elseif ($plate && (($det['registered'] ?? null) === false || ($det['registration_status'] ?? '') === 'unregistered'))
                                            Unregistered
                                        @elseif ($plate)
                                            Unregistered
                                        @else
                                            —
                                        @endif
                                    </span>
                                    @if (! empty($det['vehicle_type']) || ! empty($det['class']))
                                        · {{ ucfirst((string) ($det['vehicle_type'] ?? $det['class'])) }}
                                @endif
                                    @if (! empty($detCam))
                                        · {{ $detCam }}
                                    @endif
                                    @if (! empty($det['track_id']))
                                        · #{{ $det['track_id'] }}
                                    @endif
                                    @if (! empty($det['violation_flag']) || ! empty($det['violation_status']))
                                        · <span class="font-semibold text-amber-700">⚠ {{ $det['violation_status'] ?? 'Wrong Parking' }}</span>
                                        @if (! empty($det['violation_reason']))
                                            <span class="text-amber-600">({{ $det['violation_reason'] }})</span>
                                        @endif
                                    @endif
                                </p>
                            </div>
                            <div class="flex shrink-0 flex-col items-end gap-2">
                                @php
                                    $ownerBadge = $ownerName
                                        ?: (($det['owner_label'] ?? null) ?: null)
                                        ?: (($plate || in_array(($det['plate_status'] ?? ''), ['unreadable', 'not_read'], true))
                                            ? 'Unknown'
                                            : (($det['motion_state'] ?? '') === 'moving' ? '…' : '—'));
                                    $isKnownOwner = filled($ownerName);
                                    $needsManualPlate = in_array(($det['plate_status'] ?? ''), ['unreadable', 'not_read'], true) && ! $plate;
                                    if ($needsManualPlate) {
                                        $ownerBadge = 'Enter Plate Number';
                                    }
                                @endphp
                                @if (! empty($det['track_id']))
                                    <button
                                        type="button"
                                        class="max-w-[9rem] truncate rounded-lg border px-2 py-1 text-[11px] font-semibold {{ $isKnownOwner ? 'border-emerald-200 bg-emerald-50 text-emerald-800 hover:bg-emerald-100' : ($needsManualPlate ? 'border-amber-200 bg-amber-50 text-amber-800 hover:bg-amber-100' : 'border-slate-200 bg-slate-50 text-slate-600 hover:bg-slate-100') }}"
                                        title="{{ $needsManualPlate ? 'Enter plate manually — same DB lookup as OCR' : ($isKnownOwner ? 'Registered owner — click to fix plate if wrong' : 'Not in database — click to enter plate') }}"
                                        data-correct-plate
                                        data-camera="{{ $detCam }}"
                                        data-track="{{ $det['track_id'] }}"
                                        data-session="{{ $det['recognition_session_id'] ?? '' }}"
                                        data-plate="{{ $plate ?? '' }}"
                                        data-manual="{{ $needsManualPlate ? '1' : '0' }}"
                                    >{{ $ownerBadge }}</button>
                                @else
                                    <span class="max-w-[9rem] truncate rounded-lg border px-2 py-1 text-[11px] font-semibold {{ $isKnownOwner ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-slate-200 bg-slate-50 text-slate-600' }}">{{ $ownerBadge }}</span>
                                @endif
                                <span class="text-xs text-gray-500">{{ isset($det['confidence']) ? round($det['confidence'] * 100).'%' : '—' }}</span>
                            </div>
                        </li>
                    @empty
                        <li class="px-4 py-10 text-center text-gray-500">No plate numbers scanned yet.</li>
                    @endforelse
                </ul>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3">
                    <h3 class="font-semibold text-gray-900">AI Violation Events</h3>
                    <span class="text-[11px] text-gray-400">Today</span>
                </div>
                <ul id="ai-events" class="max-h-96 divide-y divide-gray-100 overflow-y-auto text-sm">
                    @php
                        $dayEvents = $aiDayEvents ?? ($primaryAi['events'] ?? []);
                    @endphp
                    @forelse ($dayEvents as $evt)
                        <li class="px-4 py-3">
                            <div class="flex items-center justify-between gap-2">
                                <span class="rounded bg-amber-100 px-2 py-0.5 text-xs font-semibold uppercase text-amber-800">{{ $evt['violation_type'] ?? $evt['violation_status'] ?? 'Wrong Parking' }}</span>
                                <span class="text-xs text-gray-500">{{ $evt['logged_at_label'] ?? ($evt['zone_id'] ?? '') }}</span>
                            </div>
                            @if (! empty($evt['owner_name']) || ! empty($evt['owner_label']))
                                <p class="mt-1 text-sm font-semibold text-gray-900">{{ $evt['owner_name'] ?? $evt['owner_label'] }}</p>
                            @endif
                            @if (! empty($evt['plate']))
                                <p class="mt-0.5 text-xs text-gray-600">Plate {{ $evt['plate'] }}</p>
                            @endif
                            @if (! empty($evt['reason']) || ! empty($evt['violation_reason']))
                                <p class="mt-0.5 text-xs text-amber-700">{{ $evt['reason'] ?? $evt['violation_reason'] }}</p>
                            @endif
                            @if (! empty($evt['camera_id']))
                                <p class="mt-0.5 text-[11px] text-gray-400">{{ $evt['camera_id'] }}</p>
                            @endif
                        </li>
                    @empty
                        <li class="px-4 py-10 text-center text-gray-500">No violation events yet today.</li>
                    @endforelse
                </ul>
            </div>
        </div>

        {{-- Full-screen expand with zoom --}}
        <div id="camera-expand-modal" class="fixed inset-0 z-[200] hidden items-center justify-center bg-black/80 p-2 sm:p-4" role="dialog" aria-modal="true">
            <div class="relative flex h-[min(92vh,900px)] w-full max-w-7xl flex-col overflow-hidden rounded-xl bg-black shadow-2xl">
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
                            <i data-lucide="x" class="pointer-events-none h-5 w-5"></i>
                        </button>
                    </div>
                </div>
                <div id="camera-expand-body" class="relative min-h-0 flex-1 overflow-hidden bg-[#1a1d23]"></div>
            </div>
        </div>
    @endif

    <div id="plate-correct-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4" role="dialog" aria-modal="true">
        <form id="plate-correct-form" class="w-full max-w-md rounded-2xl border border-gray-200 bg-white p-6 shadow-xl dark:border-slate-700 dark:bg-slate-900">
            <h3 class="text-lg font-bold text-gray-900 dark:text-white" id="plate-correct-title">Enter plate number</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-slate-400" id="plate-correct-help">Lookup uses the same registered-vehicle database as automatic OCR.</p>
            <div id="plate-correct-meta" class="mt-3 hidden rounded-xl border border-gray-100 bg-gray-50 p-3 text-xs text-gray-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300">
                <div class="flex gap-3">
                    <img id="plate-correct-thumb" alt="Vehicle" class="hidden h-16 w-24 shrink-0 rounded-lg border border-gray-200 object-cover dark:border-slate-600">
                    <div class="min-w-0 space-y-0.5">
                        <p id="plate-correct-meta-camera"></p>
                        <p id="plate-correct-meta-type"></p>
                        <p id="plate-correct-meta-track"></p>
                    </div>
                </div>
            </div>
            <input type="hidden" id="plate-correct-camera">
            <input type="hidden" id="plate-correct-track">
            <input type="hidden" id="plate-correct-session">
            <label class="mt-4 block text-sm font-medium text-gray-700 dark:text-slate-300" for="plate-correct-value">Plate</label>
            <div class="relative mt-1">
                <input id="plate-correct-value" type="text" required minlength="4" maxlength="32" autocomplete="off" class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2 font-mono text-sm uppercase text-gray-900 dark:border-slate-600 dark:bg-slate-800 dark:text-white" placeholder="ABC1234 or 0501-0401328">
            </div>
            <p id="plate-correct-error" class="mt-2 hidden text-sm text-red-600 dark:text-red-400"></p>
            <div class="mt-5 flex justify-end gap-2">
                <button type="button" id="plate-correct-cancel" class="rounded-xl border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-700 dark:border-slate-600 dark:text-slate-200">Cancel</button>
                <button type="submit" id="plate-correct-save" class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-60">Save plate</button>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
<script>
(() => {
    const statusUrl = @json($statusUrl ?? null);
    const correctUrl = @json($correctPlateUrl ?? null);
    const aiCropOrigin = @json($aiCropOrigin ?? null);
    const plateCropBase = @json(url('/guard/ai-parking/plate-crop'));
    const vehicleCropBase = @json(url('/guard/ai-parking/vehicle-crop'));
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

    const cropUrlFor = (cam, track, kind = 'vehicle') => {
        if (!cam || track == null || track === '') return '';
        const cropKind = kind === 'plate' ? 'plate-crop' : 'vehicle-crop';
        // Prefer direct AI service URL so php artisan serve is not flooded with crop proxies.
        if (aiCropOrigin) {
            return `${aiCropOrigin.replace(/\/$/, '')}/${encodeURIComponent(cam)}/${cropKind}/${encodeURIComponent(String(track))}.jpg`;
        }
        const base = kind === 'plate' ? plateCropBase : vehicleCropBase;
        if (!base) return '';
        return `${base}/${encodeURIComponent(cam)}/${encodeURIComponent(String(track))}`;
    };

    window.aiDetThumbFallback = (img) => {
        if (!img || img.dataset.fallbackDone === '1') return;
        const plate = img.dataset.plateCrop || '';
        const vehicle = img.dataset.vehicleCrop || '';
        const cur = (img.getAttribute('src') || '').split('?')[0];
        if (plate && cur.indexOf('plate-crop') === -1 && !cur.startsWith('data:')) {
            img.src = plate;
            return;
        }
        if (vehicle && cur.indexOf('vehicle-crop') === -1) {
            img.src = vehicle;
            return;
        }
        img.dataset.fallbackDone = '1';
        const ph = document.createElement('div');
        ph.className = 'flex h-20 w-28 shrink-0 items-center justify-center rounded-lg border border-dashed border-gray-200 bg-gray-50 text-[10px] text-gray-400';
        ph.textContent = 'No image';
        img.replaceWith(ph);
    };

    const motionLabelFor = (det) => {
        if (det?.motion_state === 'moving') return 'Moving';
        if (det?.motion_state === 'parked') return 'Parked';
        if (det?.motion_label && !String(det.motion_label).toLowerCase().includes('moving')
            && !String(det.motion_label).toLowerCase().includes('waiting')) {
            return det.motion_label;
        }
        return null;
    };

    const unresolvedPlateLabel = (det) => {
        const status = String(det?.plate_status || '').toLowerCase();
        const attempts = Number(det?.ocr_attempts || 0);
        const max = Number(det?.ocr_max_attempts || 10) || 10;
        if (attempts > 0 && status !== 'ok' && status !== 'not_read' && status !== 'unreadable' && !det?.plate) {
            const shown = Math.min(Math.max(attempts, 1), max);
            return `Scanning... ${shown}/${max}`;
        }
        if (status === 'pending') return 'Scanning...';
        return '—';
    };

    const isMovingDet = (det) => String(det?.motion_state || '').toLowerCase() === 'moving';

    const setPageScanStatus = (dets) => {
        const spinner = document.getElementById('ai-scan-spinner');
        const label = document.getElementById('ai-scan-label');
        if (!spinner || !label) return;
        const list = dets || [];
        const moving = list.some((d) => isMovingDet(d));
        const pendingStrict = list.some((d) => String(d?.plate_status || '').toLowerCase() === 'pending' && !d?.plate);
        const hasPlate = list.some((d) => !!(d?.plate));
        const active = moving || pendingStrict;
        spinner.classList.toggle('ai-scan-active', active);
        spinner.classList.toggle('opacity-40', !active);
        spinner.classList.toggle('opacity-100', active);
        let text = 'Monitoring — waiting for vehicle movement';
        let cls = 'text-gray-600';
        if (moving && pendingStrict) {
            text = 'Vehicle movement detected — scanning plate';
            cls = 'text-indigo-600 font-medium';
        } else if (moving) {
            text = 'Vehicle movement detected';
            cls = 'text-indigo-600 font-medium';
        } else if (pendingStrict) {
            text = 'Vehicle detected — scanning plate';
            cls = 'text-indigo-600 font-medium';
        } else if (hasPlate) {
            text = 'Monitoring — plate identified (OCR idle until movement)';
            cls = 'text-gray-600';
        } else if (list.length) {
            text = 'Monitoring — waiting for vehicle movement';
            cls = 'text-gray-600';
        }
        label.textContent = text;
        label.className = cls;
        const key = text + '|' + list.length + '|' + (moving ? 1 : 0) + '|' + (pendingStrict ? 1 : 0);
        if (window.__aiScanDebugKey !== key) {
            window.__aiScanDebugKey = key;
            console.debug('[AI-Monitor]', text, { moving, pendingStrict, hasPlate, n: list.length });
        }
    };

    const ensureDetThumb = (li, det, camId) => {
        const plateUrl = cropUrlFor(camId, det.track_id, 'plate');
        const vehicleUrl = cropUrlFor(camId, det.track_id, 'vehicle');
        const dataUri = det.thumb_jpeg_base64
            ? ('data:image/jpeg;base64,' + det.thumb_jpeg_base64)
            : '';
        // Prefer embedded thumb first (survives track-id churn / crop 404s), then live crop URLs.
        const stableSrc = dataUri || vehicleUrl || plateUrl;
        let crop = li.querySelector('[data-det-thumb]');
        if (!stableSrc) {
            if (crop) crop.remove();
            let ph = li.querySelector('[data-det-thumb-empty]');
            if (!ph) {
                ph = document.createElement('div');
                ph.dataset.detThumbEmpty = '1';
                ph.className = 'flex h-20 w-28 shrink-0 items-center justify-center rounded-lg border border-dashed border-gray-200 bg-gray-50 text-[10px] text-gray-400';
                ph.textContent = 'No image';
                li.prepend(ph);
            }
            return;
        }
        li.querySelector('[data-det-thumb-empty]')?.remove();
        if (!crop) {
            crop = document.createElement('img');
            crop.alt = 'Vehicle';
            crop.dataset.detThumb = '1';
            crop.className = 'h-20 w-28 shrink-0 rounded-lg border border-gray-200 bg-slate-900 object-cover';
            crop.addEventListener('error', () => {
                if (crop.dataset.fallbackTried !== '1' && dataUri && crop.src !== dataUri) {
                    crop.dataset.fallbackTried = '1';
                    crop.src = dataUri;
                    return;
                }
                window.aiDetThumbFallback(crop);
            });
            li.prepend(crop);
        }
        if (plateUrl) crop.dataset.plateCrop = plateUrl;
        if (vehicleUrl) crop.dataset.vehicleCrop = vehicleUrl;
        if (dataUri) crop.dataset.dataUri = dataUri;
        const moving = isMovingDet(det);
        crop.dataset.motion = det.motion_state || '';
        crop.alt = moving ? 'Scanning vehicle' : 'Vehicle';
        crop.classList.toggle('ai-det-thumb-scanning', moving);
        // Only set src when the track/URL actually changes — prevents blink on every refresh.
        // While moving, refresh crop occasionally so the photo tracks motion.
        const bustKey = moving
            ? `${stableSrc}|m|${Math.floor(Date.now() / 2000)}`
            : stableSrc;
        if (crop.dataset.stableSrc !== bustKey) {
            crop.dataset.stableSrc = bustKey;
            crop.dataset.fallbackDone = '0';
            crop.dataset.fallbackTried = '0';
            if (moving && !dataUri && (vehicleUrl || plateUrl)) {
                const base = vehicleUrl || plateUrl;
                crop.src = `${base}${base.includes('?') ? '&' : '?'}t=${Date.now()}`;
            } else {
                crop.src = stableSrc;
            }
        }
    };

    const ownerBadgeFor = (det) => {
        const ownerName = (det.owner_name || '').trim();
        const hasPlate = !!det.plate || det.plate_status === 'unreadable' || det.plate_status === 'not_read';
        const waiting = hasPlate
            ? 'Unknown'
            : (isMovingDet(det) ? '…' : '—');
        const ownerBadge = ownerName
            || (det.owner_label && det.owner_label !== 'Unknown Vehicle' ? det.owner_label : '')
            || waiting;
        return { ownerName, ownerBadge, isKnownOwner: !!ownerName };
    };

    const updateDetRow = (li, det, camId) => {
        ensureDetThumb(li, det, camId);

        let left = li.querySelector('[data-det-left]');
        if (!left) {
            left = document.createElement('div');
            left.dataset.detLeft = '1';
            left.className = 'min-w-0 flex-1';
            const plateEl = document.createElement('p');
            plateEl.dataset.detPlate = '1';
            plateEl.className = 'font-mono text-base font-bold tracking-wide text-indigo-800';
            const roleEl = document.createElement('p');
            roleEl.dataset.detRole = '1';
            roleEl.className = 'mt-0.5 text-xs text-gray-500';
            left.append(plateEl, roleEl);
            li.append(left);
        }

        const plateEl = left.querySelector('[data-det-plate]');
        if (det.plate_status === 'unreadable') {
            plateEl.innerHTML = '<span class="font-sans text-sm font-semibold text-slate-500">Plate Unreadable</span>';
        } else if (det.plate_status === 'not_read') {
            plateEl.innerHTML = '<span class="font-sans text-sm font-semibold text-slate-500">Plate Not Read</span>';
        } else if (det.plate) {
            plateEl.textContent = det.plate;
        } else {
            const wait = unresolvedPlateLabel(det);
            const scanning = wait !== '—';
            plateEl.innerHTML = scanning
                ? `<span class="font-sans text-sm font-medium text-indigo-500 animate-pulse">${wait}</span>`
                : `<span class="font-sans text-sm font-medium text-gray-400">${wait}</span>`;
        }

        const roleEl = left.querySelector('[data-det-role]');
        const role = (() => {
            if (det.role || det.owner_role) return det.role || det.owner_role;
            if ((det.plate_status === 'not_read' || det.plate_status === 'unreadable') && !det.plate) return '—';
            if (det.plate && (det.registered === false || det.registration_status === 'unregistered')) return 'Unregistered';
            if (det.plate) return 'Unregistered';
            return '—';
        })();
        const bits = [];
        const motion = motionLabelFor(det);
        if (motion) bits.push(motion);
        bits.push(`Role: ${role}`);
        if (det.class) bits.push(String(det.class).charAt(0).toUpperCase() + String(det.class).slice(1));
        if (camId) bits.push(camId);
        if (det.violation_flag || det.violation_status) {
            bits.push(`⚠ ${det.violation_status || 'Wrong Parking'}`);
            if (det.violation_reason && det.violation_reason !== det.violation_status) {
                bits.push(String(det.violation_reason));
            }
        }
        roleEl.textContent = bits.join(' · ');

        const { ownerBadge, isKnownOwner } = ownerBadgeFor(det);
        const needsManualPlate = (det.plate_status === 'not_read' || det.plate_status === 'unreadable') && !det.plate;
        const badgeText = needsManualPlate ? 'Enter Plate Number' : ownerBadge;
        let right = li.querySelector('[data-det-right]');
        if (!right) {
            right = document.createElement('div');
            right.dataset.detRight = '1';
            right.className = 'flex shrink-0 flex-col items-end gap-2';
            li.append(right);
        }

        let corr = right.querySelector('[data-correct-plate], [data-det-owner]');
        if (det.track_id != null) {
            if (!corr || corr.tagName !== 'BUTTON') {
                corr?.remove();
                corr = document.createElement('button');
                corr.type = 'button';
                corr.dataset.correctPlate = '1';
                right.prepend(corr);
            }
            corr.className = isKnownOwner
                ? 'max-w-[9rem] truncate rounded-lg border border-emerald-200 bg-emerald-50 px-2 py-1 text-[11px] font-semibold text-emerald-800 hover:bg-emerald-100'
                : (needsManualPlate
                    ? 'max-w-[9rem] truncate rounded-lg border border-amber-200 bg-amber-50 px-2 py-1 text-[11px] font-semibold text-amber-800 hover:bg-amber-100'
                    : 'max-w-[9rem] truncate rounded-lg border border-slate-200 bg-slate-50 px-2 py-1 text-[11px] font-semibold text-slate-600 hover:bg-slate-100');
            corr.textContent = badgeText;
            corr.title = needsManualPlate
                ? 'Enter plate manually — same DB lookup as OCR'
                : (isKnownOwner
                    ? 'Registered owner — click to fix plate if wrong'
                    : 'Not in database — click to enter plate');
            corr.dataset.camera = camId;
            corr.dataset.track = String(det.track_id);
            corr.dataset.plate = det.plate || '';
            corr.dataset.vehicleType = det.vehicle_type || det.class || '';
            if (det.recognition_session_id != null) {
                corr.dataset.session = String(det.recognition_session_id);
            } else {
                delete corr.dataset.session;
            }
            corr.dataset.manual = needsManualPlate ? '1' : '0';
        } else {
            if (!corr || corr.tagName === 'BUTTON') {
                corr?.remove();
                corr = document.createElement('span');
                corr.dataset.detOwner = '1';
                right.prepend(corr);
            }
            corr.className = isKnownOwner
                ? 'max-w-[9rem] truncate rounded-lg border border-emerald-200 bg-emerald-50 px-2 py-1 text-[11px] font-semibold text-emerald-800'
                : 'max-w-[9rem] truncate rounded-lg border border-slate-200 bg-slate-50 px-2 py-1 text-[11px] font-semibold text-slate-600';
            corr.textContent = ownerBadge;
        }

        let conf = right.querySelector('[data-det-conf]');
        if (!conf) {
            conf = document.createElement('span');
            conf.dataset.detConf = '1';
            conf.className = 'text-xs text-gray-500';
            right.append(conf);
        }
        conf.textContent = det.confidence != null ? `${Math.round(det.confidence * 100)}%` : '—';
    };

    const renderDetections = (allDets, ai) => {
        if (!detectionsList) return;
        setPageScanStatus(allDets);
        if (!allDets.length) {
            detectionsList.replaceChildren();
            const li = document.createElement('li');
            li.className = 'px-4 py-10 text-center text-gray-500';
            li.textContent = 'No plate numbers scanned yet.';
            detectionsList.append(li);
            return;
        }

        const existing = new Map();
        detectionsList.querySelectorAll('li[data-det-key]').forEach((li) => {
            existing.set(li.dataset.detKey, li);
        });
        // Drop empty-state row if present.
        detectionsList.querySelectorAll('li:not([data-det-key])').forEach((li) => li.remove());

        const seen = new Set();
        allDets.forEach((det, index) => {
            const camId = det._camera || (ai?.camera_id || '');
            const sessionKey = det.parking_session_id || det.recognition_session_id || '';
            const key = sessionKey
                ? `${camId}:s:${sessionKey}`
                : `${camId}:${det.track_id != null ? det.track_id : 'i' + index}`;
            seen.add(key);
            let li = existing.get(key);
            if (!li) {
                li = document.createElement('li');
                li.dataset.detKey = key;
                li.className = 'flex items-start gap-3 px-4 py-3';
            }
            updateDetRow(li, det, camId);
            detectionsList.append(li);
        });

        existing.forEach((li, key) => {
            if (!seen.has(key)) li.remove();
        });
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

    const streamUrlOf = (img) => (img?.getAttribute('data-stream-src') || img?.dataset?.streamSrc || '').trim();
    const snapshotUrlOf = (img) => (img?.getAttribute('data-snapshot-src') || img?.dataset?.snapshotSrc || '').trim();

    const setTileOnline = (tile, online, reason = '') => {
        if (!tile) return;
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
            if (fb) {
                fb.classList.remove('hidden');
                const labelEl = fb.querySelector('p');
                if (labelEl) labelEl.textContent = reason || 'Camera Offline';
            }
        }
        if (reason) console.warn('[AI-Monitor] camera', tile.getAttribute('data-camera-tile'), online ? 'online' : 'offline', reason);
    };

    const upgradeToLiveMjpeg = (img) => {
        const live = streamUrlOf(img);
        if (!live) return;
        if (img.dataset.liveAttached === '1' && (img.getAttribute('src') || '').includes('stream.mjpg')) return;
        img.dataset.liveAttached = '1';
        img.src = live;
        console.debug('[AI-Monitor] MJPEG attached', img.getAttribute('data-camera-stream'), live);
    };

    const attachLiveStreamsAfterLoad = () => {
        document.querySelectorAll('[data-stream-img][data-stream-src]').forEach((img) => {
            const tile = img.closest('[data-camera-tile]');
            if (tile && tile.dataset.online === '0') return;
            upgradeToLiveMjpeg(img);
        });
    };

    if (document.readyState === 'complete') {
        attachLiveStreamsAfterLoad();
    } else {
        window.addEventListener('load', attachLiveStreamsAfterLoad, { once: true });
    }

    document.querySelectorAll('[data-stream-img]').forEach((img) => {
        const tile = img.closest('[data-camera-tile]');
        let retryTimer = null;
        const camId = tile?.getAttribute('data-camera-tile') || img.getAttribute('data-camera-stream') || '?';

        img.addEventListener('error', () => {
            const src = img.getAttribute('src') || '';
            console.warn('[AI-Monitor] stream error', camId, src);
            if (src.includes('snapshot.jpg') && streamUrlOf(img)) {
                upgradeToLiveMjpeg(img);
                return;
            }
            setTileOnline(tile, false, 'Stream unavailable');
            if (retryTimer) return;
            retryTimer = window.setTimeout(() => {
                retryTimer = null;
                const snap = snapshotUrlOf(img);
                const live = streamUrlOf(img);
                img.dataset.liveAttached = '0';
                if (snap) img.src = snap + (snap.includes('?') ? '&' : '?') + 't=' + Date.now();
                else if (live) img.src = live;
                setTileOnline(tile, true, 'retry');
            }, 5000);
        });

        window.__aiParkingSetCameraOnline = window.__aiParkingSetCameraOnline || {};
        if (camId && camId !== '?') {
            window.__aiParkingSetCameraOnline[camId] = (online) => {
                setTileOnline(tile, online);
                if (online) upgradeToLiveMjpeg(img);
            };
        }
    });

    const requestFs = (el) => {
        if (!el) return Promise.reject(new Error('missing element'));
        if (typeof el.requestFullscreen === 'function') return el.requestFullscreen();
        if (typeof el.webkitRequestFullscreen === 'function') return el.webkitRequestFullscreen();
        if (typeof el.msRequestFullscreen === 'function') return el.msRequestFullscreen();
        return Promise.reject(new Error('Fullscreen API unavailable'));
    };
    const exitFs = () => {
        if (document.exitFullscreen) return document.exitFullscreen();
        if (document.webkitExitFullscreen) return document.webkitExitFullscreen();
        return Promise.resolve();
    };
    const currentFsEl = () => document.fullscreenElement || document.webkitFullscreenElement || null;

    const openExpandFallback = (tile, camId) => {
        if (!modal || !modalBody) {
            console.error('[AI-Monitor] Expand fallback unavailable');
            return;
        }
        const srcImg = tile.querySelector('[data-stream-img]');
        const live = streamUrlOf(srcImg) || snapshotUrlOf(srcImg);
        if (!live) {
            console.error('[AI-Monitor] No stream for fullscreen', camId);
            return;
        }
        modalBody.replaceChildren();
        const img = document.createElement('img');
        img.src = live;
        img.alt = camId || 'Camera';
        img.className = 'h-full w-full cursor-grab object-contain';
        img.draggable = false;
        zoomState.img = img;
        zoomState.cameraId = camId || '';
        zoomState.scale = 1;
        zoomState.panX = 0;
        zoomState.panY = 0;
        applyZoom();
        modalBody.append(img);
        if (modalTitle) modalTitle.textContent = camId || 'Camera';
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    };

    // Native Fullscreen API from the user gesture — never defer into setTimeout/AJAX.
    document.addEventListener('click', (e) => {
        const btn = e.target.closest?.('[data-fullscreen-camera]');
        if (!btn) return;
        e.preventDefault();
        e.stopPropagation();
        const tile = btn.closest('[data-camera-tile], [data-camera-id]');
        if (!tile) {
            console.error('[AI-Monitor] Camera container not found');
            return;
        }
        const camId = btn.getAttribute('data-fullscreen-camera')
            || tile.getAttribute('data-camera-tile')
            || tile.getAttribute('data-camera-id')
            || '';
        const fsEl = currentFsEl();
        if (fsEl === tile) {
            exitFs().catch((err) => console.warn('[AI-Monitor] exitFullscreen', err));
            return;
        }
        if (fsEl && fsEl !== tile) {
            // Switch cameras: exit then enter in the same gesture chain when possible.
            Promise.resolve(exitFs()).finally(() => {
                // After exit, gesture may be gone — use expand modal fallback.
                openExpandFallback(tile, camId);
            });
            return;
        }
        requestFs(tile).then(() => {
            const img = tile.querySelector('[data-stream-img]');
            if (img) upgradeToLiveMjpeg(img);
        }).catch((err) => {
            console.warn('[AI-Monitor] requestFullscreen failed — using expand modal', err);
            openExpandFallback(tile, camId);
        });
    });

    closeBtn?.addEventListener('click', closeModal);
    zoomInBtn?.addEventListener('click', () => setZoom(zoomState.scale + 0.25));
    zoomOutBtn?.addEventListener('click', () => setZoom(zoomState.scale - 0.25));
    zoomResetBtn?.addEventListener('click', () => setZoom(1));
    modal?.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && modal && !modal.classList.contains('hidden')) closeModal();
    });
    document.addEventListener('fullscreenchange', () => {
        const active = currentFsEl();
        document.querySelectorAll('[data-fullscreen-camera]').forEach((b) => {
            const t = b.closest('[data-camera-tile]');
            b.setAttribute('aria-pressed', active && t === active ? 'true' : 'false');
        });
    });

    // Plate correction must work even if status polling URL is missing.
    const plateModal = document.getElementById('plate-correct-modal');
    const plateForm = document.getElementById('plate-correct-form');
    const plateErr = document.getElementById('plate-correct-error');
    const plateSaveBtn = document.getElementById('plate-correct-save');
    const plateMeta = document.getElementById('plate-correct-meta');
    const plateThumb = document.getElementById('plate-correct-thumb');

    const closePlateModal = () => {
        plateModal?.classList.add('hidden');
        plateModal?.classList.remove('flex');
        plateErr?.classList.add('hidden');
        if (plateSaveBtn) {
            plateSaveBtn.disabled = false;
            plateSaveBtn.textContent = 'Save plate';
        }
    };

    const showPlateError = (msg) => {
        if (!plateErr) return;
        plateErr.textContent = msg || 'Could not save plate.';
        plateErr.classList.remove('hidden');
    };

    document.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-correct-plate]');
        if (!btn) return;
        e.preventDefault();
        e.stopPropagation();
        document.getElementById('plate-correct-camera').value = btn.dataset.camera || '';
        document.getElementById('plate-correct-track').value = btn.dataset.track || '';
        document.getElementById('plate-correct-session').value = btn.dataset.session || '';
        document.getElementById('plate-correct-value').value = btn.dataset.plate || '';
        const manual = btn.dataset.manual === '1';
        const title = document.getElementById('plate-correct-title');
        const help = document.getElementById('plate-correct-help');
        if (title) title.textContent = manual ? 'Enter plate number' : 'Fix plate number';
        if (help) {
            help.textContent = manual
                ? 'OCR could not read this plate. Enter it manually — lookup uses the same registered-vehicle database.'
                : 'Override a bad OCR read. Owner is looked up automatically from the database.';
        }
        const cam = btn.dataset.camera || '';
        const track = btn.dataset.track || '';
        const vType = btn.dataset.vehicleType || '';
        const metaCam = document.getElementById('plate-correct-meta-camera');
        const metaType = document.getElementById('plate-correct-meta-type');
        const metaTrack = document.getElementById('plate-correct-meta-track');
        if (metaCam) metaCam.textContent = cam ? `Camera: ${cam}` : '';
        if (metaType) metaType.textContent = vType ? `Vehicle: ${vType}` : '';
        if (metaTrack) {
            const bits = [];
            if (track) bits.push(`Track #${track}`);
            if (btn.dataset.session) bits.push(`Session ${btn.dataset.session}`);
            metaTrack.textContent = bits.join(' · ');
        }
        if (plateMeta) plateMeta.classList.remove('hidden');
        if (plateThumb) {
            const thumbSrc = cropUrlFor(cam, track, 'vehicle') || cropUrlFor(cam, track, 'plate');
            if (thumbSrc) {
                plateThumb.src = thumbSrc;
                plateThumb.classList.remove('hidden');
            } else {
                plateThumb.classList.add('hidden');
            }
        }
        plateErr?.classList.add('hidden');
        plateModal?.classList.remove('hidden');
        plateModal?.classList.add('flex');
        document.getElementById('plate-correct-value')?.focus();
    });

    document.getElementById('plate-correct-cancel')?.addEventListener('click', closePlateModal);
    plateModal?.addEventListener('click', (e) => { if (e.target === plateModal) closePlateModal(); });

    plateForm?.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!correctUrl && !aiCropOrigin) {
            showPlateError('Save URL is missing. Refresh the page and try again.');
            return;
        }
        const cameraId = (document.getElementById('plate-correct-camera')?.value || '').trim();
        const trackRaw = (document.getElementById('plate-correct-track')?.value || '').trim();
        const sessionRaw = (document.getElementById('plate-correct-session')?.value || '').trim();
        const plateRaw = (document.getElementById('plate-correct-value')?.value || '').trim();
        const plate = plateRaw.toUpperCase().replace(/[^A-Z0-9]/g, '');
        const trackId = trackRaw === '' ? null : Number(trackRaw);
        const sessionId = sessionRaw === '' ? null : Number(sessionRaw);

        if (!cameraId) {
            showPlateError('Missing camera for this detection.');
            return;
        }
        if ((trackId == null || Number.isNaN(trackId)) && (sessionId == null || Number.isNaN(sessionId))) {
            showPlateError('Missing vehicle track. Wait for a fresh detection and try again.');
            return;
        }
        if (plate.length < 4) {
            showPlateError('Enter at least 4 letters/digits for the plate.');
            return;
        }

        if (plateSaveBtn) {
            plateSaveBtn.disabled = true;
            plateSaveBtn.textContent = 'Saving…';
        }
        plateErr?.classList.add('hidden');

        const body = { camera_id: cameraId, plate };
        if (trackId != null && !Number.isNaN(trackId)) body.track_id = trackId;
        if (sessionId != null && !Number.isNaN(sessionId)) body.recognition_session_id = sessionId;

        let aiLocked = false;
        if (aiCropOrigin) {
            const aiController = new AbortController();
            const aiTimer = window.setTimeout(() => aiController.abort(), 5000);
            try {
                const aiRes = await fetch(`${String(aiCropOrigin).replace(/\/$/, '')}/correct-plate`, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                    },
                    signal: aiController.signal,
                    body: JSON.stringify(body),
                });
                const aiData = await aiRes.json().catch(() => ({}));
                aiLocked = aiRes.ok && !!aiData.ok;
            } catch (_) {
                aiLocked = false;
            } finally {
                window.clearTimeout(aiTimer);
            }
        }

        const finishOk = () => {
            closePlateModal();
            if (typeof window.__aiParkingRefresh === 'function') window.__aiParkingRefresh();
        };

        if (!correctUrl) {
            if (aiLocked) finishOk();
            else showPlateError('Could not lock plate on the AI camera service.');
            if (plateSaveBtn) {
                plateSaveBtn.disabled = false;
                plateSaveBtn.textContent = 'Save plate';
            }
            return;
        }

        const controller = new AbortController();
        const timer = window.setTimeout(() => controller.abort(), 12000);
        try {
            const res = await fetch(correctUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                signal: controller.signal,
                body: JSON.stringify(body),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) {
                if (aiLocked) {
                    finishOk();
                    return;
                }
                const fieldMsg = data?.errors
                    ? Object.values(data.errors).flat().find(Boolean)
                    : null;
                showPlateError(fieldMsg || data.message || 'Could not save plate.');
                return;
            }
            finishOk();
        } catch (err) {
            if (aiLocked) {
                finishOk();
                return;
            }
            const timedOut = err && (err.name === 'AbortError' || /aborted/i.test(String(err.message || '')));
            showPlateError(timedOut
                ? 'Save timed out — Laravel may be busy. Wait a few seconds and try again.'
                : 'Network error. Check that the website is responding, then try again.');
        } finally {
            window.clearTimeout(timer);
            if (plateSaveBtn) {
                plateSaveBtn.disabled = false;
                plateSaveBtn.textContent = 'Save plate';
            }
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
        const vType = det.vehicle_type || det.class;
        if (vType) bits.push(String(vType).charAt(0).toUpperCase() + String(vType).slice(1));
        const motion = motionLabelFor(det);
        if (motion) bits.push(motion);
        if (det.plate_status === 'unreadable') bits.push('Plate Unreadable');
        else if (det.plate_status === 'not_read') bits.push('Plate Not Read');
        else if (det.plate) bits.push(String(det.plate));
        else bits.push(unresolvedPlateLabel(det));
        if (det.owner_name) bits.push(String(det.owner_name));
        else if (det.plate && (det.registered === false || det.registration_status === 'unregistered')) bits.push('Unregistered');
        else if (det.plate) bits.push('Unregistered');
        return bits.join(' · ');
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

            // Header totals span every camera; areas are deduped so shared lots
            // are not counted twice.
            const totals = { available: null, occupied: null, parked: 0 };
            const countedAreas = new Set();
            Object.entries(cams).forEach(([camId, snap]) => {
                if (!snap || typeof snap !== 'object') return;
                totals.parked += Number(snap.parked_count ?? 0) || 0;
                const areaKey = snap.area_id != null ? `area:${snap.area_id}` : `cam:${camId}`;
                if (countedAreas.has(areaKey)) return;
                countedAreas.add(areaKey);
                if (snap.available != null && snap.available !== '') {
                    totals.available = (totals.available ?? 0) + (Number(snap.available) || 0);
                }
                if (snap.occupied != null && snap.occupied !== '') {
                    totals.occupied = (totals.occupied ?? 0) + (Number(snap.occupied) || 0);
                }
            });
            if (countedAreas.size === 0 && ai) {
                totals.available = ai.available ?? null;
                totals.occupied = ai.occupied ?? null;
                totals.parked = Number(ai.parked_count ?? 0) || 0;
            }
            if (available) available.textContent = totals.available ?? '—';
            if (occupied) occupied.textContent = totals.occupied ?? '—';
            if (parkedCount) parkedCount.textContent = String(totals.parked);

            const isVisibleDet = (det) => {
                if (!det) return false;
                if (det.track_id != null && det.track_id !== '') return true;
                const plate = String(det.plate || '').trim();
                const status = String(det.plate_status || '').toLowerCase();
                if (plate) return true;
                return ['unreadable', 'not_read', 'pending', 'ok'].includes(status);
            };

            const allDets = [];
            Object.entries(cams).forEach(([camId, snap]) => {
                const health = findByCamera(healthMap, camId) || {};
                if (!(health.connected || health.stream_reachable)) return;
                if (snap?.camera_id && String(snap.camera_id).toLowerCase() !== String(camId).toLowerCase()) return;
                (snap.detections || []).forEach((det) => {
                    if (!isVisibleDet(det)) return;
                    allDets.push({ ...det, _camera: camId });
                });
            });
            if (allDets.length === 0 && ai && (ai.detections || []).length) {
                (ai.detections || []).forEach((det) => {
                    if (!isVisibleDet(det)) return;
                    allDets.push(det);
                });
            }

            if (!ai && allDets.length === 0) return;

            if (ai && updatedAt) {
                updatedAt.textContent = ai.updated_at_label || data.updated_at;
            }
            if (detCount) detCount.textContent = String(allDets.length);

            if (detectionsList) {
                renderDetections(allDets, ai);
            }

            if (eventsList) {
                eventsList.replaceChildren();
                const evts = Array.isArray(data.ai_day_events) && data.ai_day_events.length
                    ? data.ai_day_events
                    : (ai?.events || []);
                if (!evts.length) {
                    const li = document.createElement('li');
                    li.className = 'px-4 py-10 text-center text-gray-500';
                    li.textContent = 'No violation events yet today.';
                    eventsList.append(li);
                } else {
                    const existingEvt = new Map();
                    eventsList.querySelectorAll('li[data-evt-key]').forEach((li) => {
                        existingEvt.set(li.dataset.evtKey, li);
                    });
                    eventsList.querySelectorAll('li:not([data-evt-key])').forEach((li) => li.remove());
                    const seenEvt = new Set();
                    evts.forEach((evt) => {
                        const evtKey = evt.vehicle_event_id
                            || evt.parking_session_id
                            || evt.violation_log_id
                            || `${evt.camera_id || ''}:${evt.type || ''}:${evt.zone_id || ''}:${evt.track_id || ''}:${evt.plate || ''}`;
                        seenEvt.add(String(evtKey));
                        let li = existingEvt.get(String(evtKey));
                        if (!li) {
                            li = document.createElement('li');
                            li.dataset.evtKey = String(evtKey);
                            li.className = 'px-4 py-3';
                        } else {
                            li.replaceChildren();
                        }
                        const row = document.createElement('div');
                        row.className = 'flex items-center justify-between gap-2';
                        const badge = document.createElement('span');
                        badge.className = 'rounded bg-amber-100 px-2 py-0.5 text-xs font-semibold uppercase text-amber-800';
                        badge.textContent = (evt.violation_type || evt.violation_status || 'Wrong Parking');
                        const zone = document.createElement('span');
                        zone.className = 'text-xs text-gray-500';
                        zone.textContent = evt.logged_at_label || '';
                        row.append(badge, zone);
                        li.append(row);

                        const plateStatus = String(evt.plate_status || '').toLowerCase();
                        const plateReadable = !!(evt.plate);
                        const plateLabel = plateReadable
                            ? String(evt.plate)
                            : ((plateStatus === 'unreadable' || plateStatus === 'not_read' || !evt.plate) ? 'Unknown' : 'Unknown');
                        const owner = (evt.owner_name || evt.owner_label || '').trim();
                        const ownerLabel = owner && owner.toLowerCase() !== 'unknown vehicle'
                            ? owner
                            : 'Unknown';
                        const reg = evt.registration_status
                            || (plateReadable
                                ? ((evt.registered === false || !owner || ownerLabel === 'Unknown') ? 'Unregistered' : 'Registered')
                                : 'Unknown');
                        const vType = evt.vehicle_type || evt.vehicle_details || evt.class || 'Unknown';
                        const role = evt.owner_role || evt.role || '—';
                        const schoolId = evt.owner_id_number || evt.id_number || '—';
                        const lines = [
                            `Vehicle: ${String(vType).charAt(0).toUpperCase()}${String(vType).slice(1)}`,
                            `Plate: ${plateLabel}`,
                            `Owner: ${ownerLabel}`,
                            `Role: ${role}`,
                            `ID: ${schoolId}`,
                            evt.camera_id ? `Camera: ${evt.camera_id}` : null,
                            evt.zone_id ? `Zone: ${evt.zone_id}` : null,
                            `Registration: ${reg}`,
                        ].filter(Boolean);
                        lines.forEach((text) => {
                            const p = document.createElement('p');
                            p.className = 'mt-0.5 text-xs text-gray-600';
                            p.textContent = text;
                            li.append(p);
                        });
                        const reason = evt.reason || evt.violation_reason || null;
                        if (reason) {
                            const r = document.createElement('p');
                            r.className = 'mt-0.5 text-xs text-amber-700';
                            r.textContent = reason;
                            li.append(r);
                        }
                        eventsList.append(li);
                    });
                    existingEvt.forEach((li, key) => {
                        if (!seenEvt.has(key)) li.remove();
                    });
                }
            }
            }
        } catch (e) {}
    };

    refresh();
    window.__aiParkingRefresh = refresh;
    window.setInterval(refresh, 2500);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });

    const prependAiEvent = (evt) => {
        if (!eventsList || !evt) return;
        const empty = eventsList.querySelector('li.text-center');
        if (empty) empty.remove();
        const li = document.createElement('li');
        li.className = 'px-4 py-3';
        li.dataset.wsEvent = evt.event || 'event';
        const row = document.createElement('div');
        row.className = 'flex items-center justify-between gap-2';
        const badge = document.createElement('span');
        badge.className = 'rounded bg-amber-100 px-2 py-0.5 text-xs font-semibold uppercase text-amber-800';
        badge.textContent = evt.violationType || evt.event || 'AI event';
        const source = document.createElement('span');
        source.className = 'text-xs text-gray-500';
        source.textContent = evt.detectionSource || evt.source || '';
        row.append(badge, source);
        li.append(row);
        const plate = evt.plateNumber || evt.plate || '';
        const role = evt.userRole || evt.role || '';
        const vType = evt.vehicleType || '';
        const lines = [
            plate ? `Plate Number: ${plate}` : null,
            role ? `Role: ${role}` : null,
            vType ? `Vehicle Type: ${vType}` : null,
            evt.violationType ? `Violation: ${evt.violationType}` : null,
            evt.violationReason ? `Reason: ${evt.violationReason}` : null,
            (evt.detectionSource || evt.source) ? `Source: ${evt.detectionSource || evt.source}` : null,
        ].filter(Boolean);
        lines.forEach((text) => {
            const p = document.createElement('p');
            p.className = 'mt-0.5 text-xs text-gray-600';
            p.textContent = text;
            li.append(p);
        });
        eventsList.prepend(li);
        while (eventsList.children.length > 20) {
            eventsList.lastElementChild?.remove();
        }
    };

    const subscribeAiParking = (echo) => {
        if (!echo) return;
        try {
            echo.private('ai.parking').listen('.AiParkingRealtime', (payload) => {
                const eventName = payload?.event || '';
                const data = payload?.data || {};
                if (eventName === 'violation_created' || eventName === 'violation_detected') {
                    prependAiEvent({
                        event: eventName,
                        plateNumber: data.plateNumber || 'UNKNOWN',
                        userRole: data.userRole,
                        vehicleType: data.vehicleType,
                        violationType: data.violationType || 'Wrong Parking',
                        violationReason: data.violationReason || data.reason || null,
                        detectionSource: data.detectionSource || 'AI',
                    });
                } else if (eventName === 'plate_manual_entry') {
                    prependAiEvent({
                        event: eventName,
                        plateNumber: data.plateNumber,
                        userRole: data.userRole,
                        vehicleType: data.vehicleType,
                        violationType: 'Manual plate',
                        detectionSource: 'MANUAL',
                        source: 'MANUAL',
                    });
                    refresh();
                } else if (eventName === 'plate_not_read') {
                    prependAiEvent({
                        event: eventName,
                        plateNumber: 'UNKNOWN',
                        violationType: 'Plate not read',
                        detectionSource: 'AI',
                    });
                }
            });
        } catch (e) {}
    };
    if (typeof window.whenEchoReady === 'function') {
        window.whenEchoReady(subscribeAiParking);
    }
})();
</script>
@endpush
