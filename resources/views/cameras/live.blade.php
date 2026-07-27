@extends($layout ?? 'layouts.portal')

@section('title', 'Live Cameras')

@section('content')
    @include('partials.shell.page-header', [
        'title' => 'Live Camera Feeds',
        'subtitle' => 'Monitor campus security cameras in real-time',
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
            @php($isOnline = ! empty($camera['online']))
            <article class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                <div class="relative aspect-video bg-[#1a1d23]" data-camera-tile="{{ $camera['id'] }}">
                    {{-- Timestamp --}}
                    <span class="camera-clock absolute left-3 top-3 z-10 rounded bg-black/45 px-2 py-0.5 text-xs font-medium text-white tabular-nums">
                        {{ ph_now()->format('g:i:s A') }}
                    </span>

                    {{-- Status badge --}}
                    <span @class([
                        'absolute right-3 top-3 z-10 rounded-full px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wide',
                        'bg-emerald-500 text-white' => $isOnline,
                        'bg-red-500 text-white' => ! $isOnline,
                    ])>
                        {{ $isOnline ? 'Online' : 'Offline' }}
                    </span>

                    @if (! empty($camera['stream_url']) && $isOnline)
                        <img
                            src="{{ $camera['stream_url'] }}"
                            alt="{{ $camera['name'] }}"
                            class="absolute inset-0 h-full w-full object-cover"
                            data-stream-img
                            onload="this.closest('[data-camera-tile]').querySelector('[data-stream-fallback]')?.classList.add('hidden')"
                            onerror="this.classList.add('hidden'); const fb=this.closest('[data-camera-tile]')?.querySelector('[data-stream-fallback]'); if(fb){ fb.classList.remove('hidden'); fb.querySelector('p').textContent='Stream offline'; }"
                        >
                        <div data-stream-fallback class="hidden absolute inset-0 flex flex-col items-center justify-center gap-2 text-slate-400">
                            <i data-lucide="camera" class="h-10 w-10 opacity-70"></i>
                            <p class="text-sm font-medium text-slate-300">Connecting…</p>
                        </div>
                    @elseif ($isOnline)
                        <div class="absolute inset-0 flex flex-col items-center justify-center gap-2 text-slate-400">
                            <i data-lucide="camera" class="h-10 w-10 opacity-70"></i>
                            <p class="text-sm font-medium text-slate-300">Live Feed Active</p>
                            <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-red-400">
                                <span class="h-2 w-2 animate-pulse rounded-full bg-red-500"></span>
                                LIVE
                            </span>
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
                        @if (! empty($camera['ai_monitored']))
                            <span class="shrink-0 rounded bg-blue-50 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-blue-700">AI</span>
                        @endif
                    </div>
                    @if (! empty($camera['parking_url']))
                        <a href="{{ $camera['parking_url'] }}" class="mt-2 inline-block text-xs font-medium text-blue-600 hover:underline">
                            Open parking →
                        </a>
                    @endif
                </div>
            </article>
        @endforeach
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

        // Expand modal
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
                const tile = btn.closest('[data-camera-tile]');
                const card = btn.closest('article');
                const title = card?.querySelector('.font-semibold')?.textContent?.trim() || 'Camera';
                const stream = tile?.querySelector('[data-stream-img]');
                if (modalTitle) modalTitle.textContent = title;
                if (modalBody) {
                    modalBody.replaceChildren();
                    if (stream && !stream.classList.contains('hidden')) {
                        const clone = stream.cloneNode(true);
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
    })();
</script>
@endpush
