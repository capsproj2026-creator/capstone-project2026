@extends($layout ?? 'layouts.portal')

@section('title', 'Live Cameras')

@section('content')
    @include('partials.shell.page-header', [
        'title' => 'Live Camera Feeds',
        'subtitle' => 'Clean live CCTV feeds — no AI overlay',
    ])

    @php
        $stats = $cameraStats ?? [
            'total' => count($cameras ?? []),
            'online' => collect($cameras ?? [])->where('online', true)->count(),
            'offline' => collect($cameras ?? [])->where('online', false)->count(),
        ];
    @endphp

    {{-- Status summary (Figma) --}}
    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <div>
                <p class="text-sm text-gray-500">Total Cameras</p>
                <p class="mt-1 text-3xl font-bold text-gray-900">{{ $stats['total'] }}</p>
            </div>
            <div class="flex h-11 w-11 items-center justify-center rounded-full bg-blue-50 text-blue-600">
                <i data-lucide="camera" class="h-5 w-5"></i>
            </div>
        </div>
        <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <div>
                <p class="text-sm text-gray-500">Online</p>
                <p id="cam-online-count" class="mt-1 text-3xl font-bold text-emerald-600">{{ $stats['online'] }}</p>
            </div>
            <div class="flex h-11 w-11 items-center justify-center rounded-full bg-emerald-50 text-emerald-600">
                <i data-lucide="check-circle-2" class="h-5 w-5"></i>
            </div>
        </div>
        <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <div>
                <p class="text-sm text-gray-500">Offline</p>
                <p id="cam-offline-count" class="mt-1 text-3xl font-bold text-red-600">{{ $stats['offline'] }}</p>
            </div>
            <div class="flex h-11 w-11 items-center justify-center rounded-full bg-red-50 text-red-600">
                <i data-lucide="alert-triangle" class="h-5 w-5"></i>
            </div>
        </div>
    </div>

    {{-- Camera grid --}}
    <div class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-3">
        @foreach ($cameras as $camera)
            @php
                $whepUrl = $camera['whep_url'] ?? null;
                $useWebRtc = filled($whepUrl);
                $isOnline = ! empty($camera['online']);
                $hasStream = $useWebRtc || ! empty($camera['stream_url']);
                $initialStatus = $useWebRtc ? 'CONNECTING' : ($isOnline ? 'LIVE' : 'OFFLINE');
            @endphp
            <article class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm" data-camera-card>
                <div class="relative aspect-video bg-[#1a1d23]" data-camera-tile="{{ $camera['id'] }}" data-online="{{ ($useWebRtc || $isOnline) ? '1' : '0' }}" @if($useWebRtc) data-whep-url="{{ $whepUrl }}" @endif>
                    {{-- Timestamp --}}
                    <span class="camera-clock absolute left-3 top-3 z-10 rounded bg-black/45 px-2 py-0.5 text-xs font-medium text-white tabular-nums">
                        {{ ph_now()->format('g:i:s A') }}
                    </span>

                    {{-- Status badge --}}
                    <span
                        data-status-badge
                        @class([
                            'absolute right-3 top-3 z-10 rounded-full px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wide',
                            'bg-amber-500 text-white' => $initialStatus === 'CONNECTING',
                            'bg-emerald-500 text-white' => $initialStatus === 'LIVE',
                            'bg-red-500 text-white' => $initialStatus === 'OFFLINE',
                        ])
                    >
                        {{ $initialStatus }}
                    </span>

                    @if ($useWebRtc)
                        <video
                            data-webrtc-video
                            autoplay
                            muted
                            playsinline
                            class="absolute inset-0 h-full w-full object-cover"
                        ></video>
                        <div data-stream-fallback class="absolute inset-0 flex flex-col items-center justify-center gap-2 text-slate-400">
                            <i data-lucide="loader-circle" class="h-10 w-10 opacity-70"></i>
                            <p class="text-sm font-medium text-slate-300">Connecting…</p>
                        </div>
                    @elseif ($hasStream)
                        {{-- Tiny finished placeholder so the tab can complete load; MJPEG is attached in JS after load. --}}
                        <img
                            src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7"
                            data-stream-src="{{ $camera['stream_url'] }}"
                            data-stream-pending="1"
                            alt="{{ $camera['name'] }}"
                            class="absolute inset-0 h-full w-full object-cover {{ $isOnline ? '' : 'hidden' }}"
                            data-stream-img
                            decoding="async"
                        >
                        <div data-stream-fallback @class([
                            'absolute inset-0 flex flex-col items-center justify-center gap-2 text-slate-400',
                            'hidden' => $isOnline,
                        ])>
                            <i data-lucide="video-off" class="h-10 w-10 opacity-70"></i>
                            <p class="text-sm font-medium text-slate-300">{{ $isOnline ? 'Connecting…' : 'Camera Offline' }}</p>
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
                        title="Expand"
                        data-expand-camera="{{ $camera['id'] }}"
                        aria-label="Expand {{ $camera['name'] }}"
                    >
                        <i data-lucide="maximize-2" class="h-4 w-4"></i>
                    </button>
                </div>

                <div class="border-t border-gray-100 px-4 py-3">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="truncate font-semibold text-gray-900">{{ $camera['name'] }}</p>
                            <p class="mt-1 flex items-center gap-1.5 text-sm text-gray-500">
                                <i data-lucide="map-pin" class="h-3.5 w-3.5 shrink-0"></i>
                                <span class="truncate">{{ $camera['location'] ?? $camera['subtitle'] ?? 'Campus' }}</span>
                            </p>
                        </div>
                        <span data-live-chip @class([
                            'shrink-0 rounded px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide',
                            'bg-amber-50 text-amber-700' => $initialStatus === 'CONNECTING',
                            'bg-emerald-50 text-emerald-700' => $initialStatus === 'LIVE',
                            'bg-red-50 text-red-700' => $initialStatus === 'OFFLINE',
                        ])>{{ $initialStatus }}</span>
                    </div>
                    @if (! empty($camera['parking_url']))
                        <a href="{{ $camera['parking_url'] }}" class="mt-2 inline-block text-xs font-medium text-blue-600 hover:underline">
                            Open parking map →
                        </a>
                    @endif
                </div>
            </article>
        @endforeach

        @if (empty($cameras))
            <div class="col-span-full rounded-xl border border-dashed border-gray-300 bg-white px-6 py-12 text-center">
                <i data-lucide="video-off" class="mx-auto h-10 w-10 text-gray-400"></i>
                <p class="mt-3 text-base font-semibold text-gray-800">No CCTV cameras enabled</p>
                <p class="mt-1 text-sm text-gray-500">
                    Set <code class="rounded bg-gray-100 px-1">AI_CAMERA_1_ENABLED=true</code> in
                    <code class="rounded bg-gray-100 px-1">.env</code>, then restart Laravel
                    (<code class="rounded bg-gray-100 px-1">php artisan config:clear</code>).
                </p>
                <p class="mt-2 text-sm text-gray-500">
                    CCTV is under sidebar <strong>Live Cameras</strong> (not the home Dashboard).
                </p>
            </div>
        @endif
    </div>

    {{-- Expand modal --}}
    <div id="camera-expand-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/70 p-4" role="dialog" aria-modal="true">
        <div class="relative w-full max-w-5xl overflow-hidden rounded-xl bg-black shadow-2xl">
            <div class="flex items-center justify-between border-b border-white/10 px-4 py-3">
                <p id="camera-expand-title" class="font-semibold text-white">Camera</p>
                <button type="button" id="camera-expand-close" class="rounded-md p-1.5 text-white/80 hover:bg-white/10" aria-label="Close">
                    <i data-lucide="x" class="h-5 w-5"></i>
                </button>
            </div>
            <div id="camera-expand-body" class="relative aspect-video bg-[#1a1d23]"></div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    (() => {
        // Live clocks on each tile
        const clocks = () => {
            const now = new Date();
            const label = now.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit', second: '2-digit' });
            document.querySelectorAll('.camera-clock').forEach((el) => { el.textContent = label; });
        };
        clocks();
        window.setInterval(clocks, 1000);

        const statusClass = (status) => {
            if (status === 'LIVE') return 'bg-emerald-500 text-white';
            if (status === 'CONNECTING' || status === 'RECONNECTING') return 'bg-amber-500 text-white';
            return 'bg-red-500 text-white';
        };
        const chipClass = (status) => {
            if (status === 'LIVE') return 'bg-emerald-50 text-emerald-700';
            if (status === 'CONNECTING' || status === 'RECONNECTING') return 'bg-amber-50 text-amber-700';
            return 'bg-red-50 text-red-700';
        };
        const setStatus = (tile, status, detail) => {
            if (!tile) return;
            tile.dataset.online = status === 'LIVE' ? '1' : '0';
            const badge = tile.querySelector('[data-status-badge]');
            if (badge) {
                badge.textContent = status;
                badge.className = 'absolute right-3 top-3 z-10 rounded-full px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wide ' + statusClass(status);
            }
            const chip = tile.closest('[data-camera-card]')?.querySelector('[data-live-chip]');
            if (chip) {
                chip.textContent = status;
                chip.className = 'shrink-0 rounded px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide ' + chipClass(status);
            }
            const fb = tile.querySelector('[data-stream-fallback]');
            const video = tile.querySelector('[data-webrtc-video]');
            if (status === 'LIVE') {
                video?.classList.remove('hidden');
                fb?.classList.add('hidden');
            } else if (fb) {
                fb.classList.remove('hidden');
                const label = fb.querySelector('p');
                if (label) label.textContent = detail || (status === 'RECONNECTING' ? 'Reconnecting…' : 'Unable to connect to camera. Retrying…');
            }
            const tiles = document.querySelectorAll('[data-camera-tile]');
            let onlineCount = 0;
            tiles.forEach((t) => { if (t.dataset.online === '1') onlineCount += 1; });
            const onlineEl = document.getElementById('cam-online-count');
            const offlineEl = document.getElementById('cam-offline-count');
            if (onlineEl) onlineEl.textContent = String(onlineCount);
            if (offlineEl) offlineEl.textContent = String(Math.max(0, tiles.length - onlineCount));
        };

        const waitForIce = (pc) => new Promise((resolve) => {
            if (pc.iceGatheringState === 'complete') {
                resolve();
                return;
            }
            const timer = window.setTimeout(() => resolve(), 2000);
            pc.addEventListener('icegatheringstatechange', () => {
                if (pc.iceGatheringState === 'complete') {
                    window.clearTimeout(timer);
                    resolve();
                }
            });
        });

        const connectWhep = async (video, whepUrl) => {
            const pc = new RTCPeerConnection({ iceServers: [{ urls: 'stun:stun.l.google.com:19302' }] });
            pc.addTransceiver('video', { direction: 'recvonly' });
            pc.addTransceiver('audio', { direction: 'recvonly' });
            pc.addEventListener('track', (event) => {
                const [stream] = event.streams;
                if (stream) video.srcObject = stream;
            });
            const offer = await pc.createOffer();
            await pc.setLocalDescription(offer);
            await waitForIce(pc);
            const response = await fetch(whepUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/sdp' },
                body: pc.localDescription?.sdp || '',
            });
            if (!response.ok) {
                pc.close();
                throw new Error('WebRTC connect failed (' + response.status + ')');
            }
            const answer = await response.text();
            await pc.setRemoteDescription({ type: 'answer', sdp: answer });
            const location = response.headers.get('Location');
            return { pc, sessionUrl: location };
        };

        const startTile = (tile) => {
            const whepUrl = tile?.dataset.whepUrl || '';
            const video = tile?.querySelector('[data-webrtc-video]');
            if (!tile || !video || !whepUrl || tile.dataset.whepLoop === '1') return;
            tile.dataset.whepLoop = '1';
            let session = null;
            let stopped = false;
            let generation = 0;
            const stopSession = () => {
                session?.pc?.close();
                if (session?.sessionUrl) {
                    fetch(session.sessionUrl, { method: 'DELETE' }).catch(() => {});
                }
                session = null;
                video.srcObject = null;
            };
            const loop = async (reconnect) => {
                if (stopped) return;
                const token = ++generation;
                setStatus(tile, reconnect ? 'RECONNECTING' : 'CONNECTING');
                try {
                    stopSession();
                    const next = await connectWhep(video, whepUrl);
                    if (stopped || token !== generation) {
                        next.pc.close();
                        return;
                    }
                    session = next;
                    setStatus(tile, 'LIVE');
                    session.pc.addEventListener('connectionstatechange', () => {
                        const state = session?.pc?.connectionState;
                        if (stopped || token !== generation) return;
                        if (state !== 'failed' && state !== 'disconnected') return;
                        setStatus(tile, 'RECONNECTING', 'Unable to connect to camera. Retrying…');
                        window.setTimeout(() => loop(true), 3000);
                    });
                } catch (error) {
                    if (stopped || token !== generation) return;
                    setStatus(tile, 'OFFLINE', 'Unable to connect to camera. Retrying…');
                    window.setTimeout(() => loop(true), 5000);
                }
            };
            tile._stopWhep = () => { stopped = true; stopSession(); };
            loop(false);
        };

        document.querySelectorAll('[data-whep-url]').forEach((tile) => startTile(tile));

        // Expand modal
        const modal = document.getElementById('camera-expand-modal');
        const modalTitle = document.getElementById('camera-expand-title');
        const modalBody = document.getElementById('camera-expand-body');
        const closeBtn = document.getElementById('camera-expand-close');

        const ensureStreamAttached = (img) => {
            if (!img) return false;
            const url = img.getAttribute('data-stream-src') || img.dataset.streamSrc || '';
            if (!url) return false;
            const current = img.getAttribute('src') || '';
            if (current === url || (current.startsWith(url) && !img.hasAttribute('data-stream-pending'))) {
                img.removeAttribute('data-stream-pending');
                return true;
            }
            img.removeAttribute('data-stream-pending');
            img.src = url;
            return true;
        };

        let modalSession = null;
        const closeModal = () => {
            modalSession?.pc?.close();
            if (modalSession?.sessionUrl) {
                fetch(modalSession.sessionUrl, { method: 'DELETE' }).catch(() => {});
            }
            modalSession = null;
            modal?.classList.add('hidden');
            modal?.classList.remove('flex');
            if (modalBody) modalBody.replaceChildren();
        };

        document.querySelectorAll('[data-expand-camera]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const tile = btn.closest('[data-camera-tile]');
                const card = btn.closest('article');
                const title = card?.querySelector('.font-semibold')?.textContent?.trim() || 'Camera';
                const stream = tile?.querySelector('[data-stream-img]');
                const whepUrl = tile?.dataset.whepUrl || '';
                if (modalTitle) modalTitle.textContent = title;
                if (modalBody) {
                    modalBody.replaceChildren();
                    if (whepUrl) {
                        const video = document.createElement('video');
                        video.autoplay = true;
                        video.muted = true;
                        video.playsInline = true;
                        video.className = 'h-full w-full object-contain';
                        modalBody.append(video);
                        connectWhep(video, whepUrl).then((session) => { modalSession = session; }).catch(() => {
                            modalBody.textContent = 'Unable to connect to camera.';
                        });
                    } else if (stream && (stream.getAttribute('data-stream-src') || '')) {
                        const streamUrl = stream.getAttribute('data-stream-src') || '';
                        ensureStreamAttached(stream);
                        const clone = document.createElement('img');
                        clone.src = streamUrl;
                        clone.alt = stream.getAttribute('alt') || title;
                        clone.className = 'h-full w-full object-contain';
                        modalBody.append(clone);
                    } else {
                        const placeholder = document.createElement('div');
                        placeholder.className = 'flex h-full items-center justify-center text-slate-400';
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
        modal?.addEventListener('click', (e) => {
            if (e.target === modal) closeModal();
        });
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeModal();
        });

        if (window.lucide) window.lucide.createIcons();

        const setCameraOnline = (tile, online) => {
            if (!tile) return;
            tile.dataset.online = online ? '1' : '0';
            const badge = tile.querySelector('[data-status-badge]');
            if (badge) {
                badge.textContent = online ? 'Online' : 'Offline';
                badge.className = 'absolute right-3 top-3 z-10 rounded-full px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wide '
                    + (online ? 'bg-emerald-500 text-white' : 'bg-red-500 text-white');
            }
            const chip = tile.closest('[data-camera-card]')?.querySelector('[data-live-chip]');
            if (chip) {
                chip.textContent = online ? 'Live' : 'Offline';
                chip.className = 'shrink-0 rounded px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide '
                    + (online ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700');
            }
            const fb = tile.querySelector('[data-stream-fallback]');
            const img = tile.querySelector('[data-stream-img]');
            if (online) {
                ensureStreamAttached(img);
                img?.classList.remove('hidden');
                fb?.classList.add('hidden');
            } else {
                img?.classList.add('hidden');
                if (fb) {
                    fb.classList.remove('hidden');
                    const label = fb.querySelector('p');
                    if (label) label.textContent = 'Camera Offline';
                }
            }
            const refreshCounts = () => {
                const tiles = document.querySelectorAll('[data-camera-tile]');
                let onlineCount = 0;
                tiles.forEach((t) => { if (t.dataset.online === '1') onlineCount += 1; });
                const onlineEl = document.getElementById('cam-online-count');
                const offlineEl = document.getElementById('cam-offline-count');
                if (onlineEl) onlineEl.textContent = String(onlineCount);
                if (offlineEl) offlineEl.textContent = String(Math.max(0, tiles.length - onlineCount));
            };
            refreshCounts();
        };

        const attachDeferredStreams = () => {
            document.querySelectorAll('[data-stream-img][data-stream-src]').forEach((img) => {
                ensureStreamAttached(img);
            });
        };
        const scheduleStreamAttach = () => {
            attachDeferredStreams();
            window.setTimeout(attachDeferredStreams, 0);
            window.setTimeout(attachDeferredStreams, 250);
        };
        if (document.readyState === 'complete') {
            scheduleStreamAttach();
        } else {
            window.addEventListener('load', scheduleStreamAttach, { once: true });
            window.setTimeout(scheduleStreamAttach, 1500);
        }

        document.querySelectorAll('[data-stream-img]').forEach((img) => {
            const base = img.getAttribute('data-stream-src') || img.dataset.streamSrc;
            if (!base) return;
            const tile = img.closest('[data-camera-tile]');
            let retryTimer = null;
            const reload = () => {
                const url = new URL(base, window.location.origin);
                url.searchParams.set('t', String(Date.now()));
                if (tile?.dataset.online === '1') {
                    img.classList.remove('hidden');
                }
                img.removeAttribute('data-stream-pending');
                img.src = url.toString();
            };
            img.addEventListener('load', () => {
                const src = img.getAttribute('src') || '';
                if (src.startsWith('data:')) return;
                setCameraOnline(tile, true);
            });
            img.addEventListener('error', () => {
                if (img.hasAttribute('data-stream-pending')) return;
                const src = img.getAttribute('src') || '';
                if (src.startsWith('data:')) return;
                setCameraOnline(tile, false);
                if (retryTimer) return;
                retryTimer = window.setTimeout(() => {
                    retryTimer = null;
                    reload();
                }, 5000);
            });
        });
    })();
</script>
@endpush
