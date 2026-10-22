@extends($layout ?? 'layouts.portal')

@section('title', 'Incident Clips')

@section('content')
    @include('partials.shell.page-header', [
        'title' => 'Incident clips',
        'subtitle' => ($canSave ?? false)
            ? 'Replay the last minute of a live camera, or browse clips saved by guards and AI'
            : 'Browse clips saved by guards and AI from live cameras',
    ])

    @include('partials.shell.flash')

    @php
        $stats = $clipStats ?? [
            'total' => $clips->total(),
            'ai' => 0,
            'manual' => 0,
        ];
    @endphp

    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <div>
                <p class="text-sm text-gray-500">Total clips</p>
                <p class="mt-1 text-3xl font-bold text-gray-900">{{ $stats['total'] }}</p>
            </div>
            <div class="flex h-11 w-11 items-center justify-center rounded-full bg-blue-50 text-blue-600">
                <i data-lucide="film" class="h-5 w-5"></i>
            </div>
        </div>
        <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <div>
                <p class="text-sm text-gray-500">AI saved</p>
                <p class="mt-1 text-3xl font-bold text-violet-600">{{ $stats['ai'] }}</p>
            </div>
            <div class="flex h-11 w-11 items-center justify-center rounded-full bg-violet-50 text-violet-600">
                <i data-lucide="bot" class="h-5 w-5"></i>
            </div>
        </div>
        <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <div>
                <p class="text-sm text-gray-500">Guard saved</p>
                <p class="mt-1 text-3xl font-bold text-emerald-600">{{ $stats['manual'] }}</p>
            </div>
            <div class="flex h-11 w-11 items-center justify-center rounded-full bg-emerald-50 text-emerald-600">
                <i data-lucide="shield" class="h-5 w-5"></i>
            </div>
        </div>
    </div>

    @if (($canSave ?? false) && ! empty($cameras))
        <div class="mb-6 overflow-hidden rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-gray-900">Save a new clip</p>
                    <p class="mt-1 text-xs text-gray-500">Records about the last minute of live video from the selected camera.</p>
                </div>
                <form method="POST" action="{{ route($saveRoute) }}" class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row sm:items-center">
                    @csrf
                    <label class="sr-only" for="clip-camera-id">Camera</label>
                    <select
                        id="clip-camera-id"
                        name="camera_id"
                        required
                        class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 sm:min-w-[14rem]"
                    >
                        <option value="" disabled selected>Select camera</option>
                        @foreach ($cameras as $camera)
                            <option value="{{ $camera['id'] }}">
                                {{ $camera['id'] }}
                                @if (! empty($camera['location']))
                                    — {{ $camera['location'] }}
                                @endif
                            </option>
                        @endforeach
                    </select>
                    <button
                        type="submit"
                        class="inline-flex items-center justify-center gap-1.5 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700"
                    >
                        <i data-lucide="video" class="h-4 w-4"></i>
                        Save clip
                    </button>
                </form>
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($clips as $clip)
            @php
                $camKey = strtoupper((string) $clip->camera_id);
                $location = $cameraLocations[$camKey] ?? 'Campus';
                $isAi = strtolower((string) $clip->source) === 'ai';
                $playUrl = route($playRoute, $clip->id);
            @endphp
            <article class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                <div class="relative aspect-video bg-[#1a1d23]">
                    <video
                        controls
                        preload="metadata"
                        playsinline
                        class="h-full w-full object-contain"
                        src="{{ $playUrl }}"
                    ></video>
                    <span
                        @class([
                            'absolute left-3 top-3 z-10 rounded-full px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-white',
                            'bg-violet-600' => $isAi,
                            'bg-emerald-600' => ! $isAi,
                        ])
                    >
                        {{ $isAi ? 'AI' : 'Guard' }}
                    </span>
                    <button
                        type="button"
                        class="absolute right-3 top-3 z-10 inline-flex h-8 w-8 items-center justify-center rounded-full bg-black/50 text-white hover:bg-black/70"
                        data-clip-expand
                        data-src="{{ $playUrl }}"
                        data-title="{{ $clip->camera_id }} · {{ $location }}"
                        data-meta="{{ ph_datetime($clip->created_at) }} · {{ $isAi ? 'AI auto-save' : 'Guard manual' }}"
                        aria-label="Expand clip"
                    >
                        <i data-lucide="maximize-2" class="h-4 w-4"></i>
                    </button>
                </div>
                <div class="space-y-2 p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate font-semibold text-gray-900">{{ $clip->camera_id }}</p>
                            <p class="mt-1 flex items-center gap-1.5 text-sm text-gray-500">
                                <i data-lucide="map-pin" class="h-3.5 w-3.5 shrink-0"></i>
                                <span class="truncate">{{ $location }}</span>
                            </p>
                        </div>
                        @if (! empty($clip->frame_count))
                            <span class="shrink-0 rounded bg-gray-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-gray-600">
                                {{ number_format((int) $clip->frame_count) }} frames
                            </span>
                        @endif
                    </div>
                    <p class="flex items-center gap-1.5 text-xs text-gray-500">
                        <i data-lucide="clock" class="h-3.5 w-3.5 shrink-0"></i>
                        {{ ph_datetime($clip->created_at) }}
                    </p>
                </div>
            </article>
        @empty
            <div class="col-span-full rounded-xl border border-dashed border-gray-300 bg-white px-6 py-12 text-center">
                <i data-lucide="film" class="mx-auto h-10 w-10 text-gray-400"></i>
                <p class="mt-3 text-base font-semibold text-gray-800">No incident clips yet</p>
                <p class="mt-1 text-sm text-gray-500">
                    @if ($canSave ?? false)
                        Save one from a live camera above, or wait for AI to auto-save after a citation.
                    @else
                        Clips appear here when a guard saves one or AI auto-saves after a citation.
                    @endif
                </p>
            </div>
        @endforelse
    </div>

    @if ($clips->hasPages())
        <div class="mt-6">{{ $clips->links() }}</div>
    @endif

    <div id="clip-expand-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/70 p-4" role="dialog" aria-modal="true" aria-labelledby="clip-expand-title">
        <div class="flex max-h-[92vh] w-full max-w-5xl flex-col overflow-hidden rounded-xl bg-white shadow-2xl">
            <div class="flex items-start justify-between gap-3 border-b border-gray-200 px-4 py-3">
                <div class="min-w-0">
                    <p id="clip-expand-title" class="truncate text-sm font-semibold text-gray-900"></p>
                    <p id="clip-expand-meta" class="mt-0.5 truncate text-xs text-gray-500"></p>
                </div>
                <button type="button" id="clip-expand-close" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100 hover:text-gray-800" aria-label="Close">
                    <i data-lucide="x" class="h-4 w-4"></i>
                </button>
            </div>
            <div class="bg-[#1a1d23]">
                <video id="clip-expand-video" controls playsinline preload="metadata" class="max-h-[75vh] w-full object-contain"></video>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
(() => {
    const modal = document.getElementById('clip-expand-modal');
    const video = document.getElementById('clip-expand-video');
    const titleEl = document.getElementById('clip-expand-title');
    const metaEl = document.getElementById('clip-expand-meta');
    const closeBtn = document.getElementById('clip-expand-close');
    if (!modal || !video) return;

    const open = (btn) => {
        titleEl.textContent = btn.dataset.title || 'Incident clip';
        metaEl.textContent = btn.dataset.meta || '';
        video.src = btn.dataset.src || '';
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        video.play().catch(() => {});
        if (window.lucide) window.lucide.createIcons();
    };

    const close = () => {
        video.pause();
        video.removeAttribute('src');
        video.load();
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    };

    document.querySelectorAll('[data-clip-expand]').forEach((btn) => {
        btn.addEventListener('click', () => open(btn));
    });
    closeBtn?.addEventListener('click', close);
    modal.addEventListener('click', (e) => {
        if (e.target === modal) close();
    });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !modal.classList.contains('hidden')) close();
    });
})();
</script>
@endpush
