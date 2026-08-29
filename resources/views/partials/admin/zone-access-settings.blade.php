@php
    $visibleZoneCount = $zones->filter(fn ($zone) => $zone->isVisibleToUsers())->count();
    $hiddenZoneCount = $zones->count() - $visibleZoneCount;
@endphp

@if (session('success'))
    <div class="mb-6 flex gap-2 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
        <i data-lucide="circle-check" class="h-4 w-4 shrink-0"></i>
        {{ session('success') }}
    </div>
@endif

<div class="rounded-xl border border-blue-200 bg-white shadow-sm">
    <div class="border-b border-blue-100 bg-blue-50/60 px-5 py-4 sm:px-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <h2 class="flex items-center gap-2 text-lg font-semibold text-gray-900">
                    <i data-lucide="shield-check" class="h-5 w-5 text-blue-600"></i>
                    Zone Access Settings
                </h2>
                <p class="mt-1 max-w-2xl text-sm text-gray-600">
                    Toggle visibility and choose which roles can see each parking zone in the user portal.
                </p>
                @if ($zones->isNotEmpty())
                    <div class="mt-3 flex flex-wrap gap-2">
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-green-100 px-3 py-1 text-xs font-medium text-green-800">
                            <span class="h-2 w-2 rounded-full bg-green-500"></span>
                            {{ $visibleZoneCount }} visible
                        </span>
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-red-100 px-3 py-1 text-xs font-medium text-red-800">
                            <span class="h-2 w-2 rounded-full bg-red-500"></span>
                            {{ $hiddenZoneCount }} hidden
                        </span>
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-700">
                            {{ $zones->count() }} total zones
                        </span>
                    </div>
                @endif
            </div>
            @if ($zones->isNotEmpty())
                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" id="zone-show-all"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50">
                        <i data-lucide="eye" class="h-3.5 w-3.5"></i>
                        Show all
                    </button>
                    <button type="button" id="zone-hide-all"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50">
                        <i data-lucide="eye-off" class="h-3.5 w-3.5"></i>
                        Hide all
                    </button>
                </div>
            @endif
        </div>
    </div>

    @if ($zones->isEmpty())
        <div class="px-5 py-12 text-center sm:px-6">
            <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-gray-100">
                <i data-lucide="map-pin-off" class="h-6 w-6 text-gray-400"></i>
            </div>
            <p class="text-sm font-medium text-gray-900">No parking zones configured yet</p>
        </div>
    @else
        <form method="POST" action="{{ route('admin.parking.areas.update') }}" id="zone-access-form">
            @csrf
            <div class="grid gap-4 p-4 sm:p-5 lg:grid-cols-2">
                @foreach ($zones as $zone)
                    @php
                        $allowedRoles = $zone->getAllowedRoles();
                        $isVisible = $zone->isVisibleToUsers();
                    @endphp
                    <div @class([
                        'zone-card rounded-xl border p-4 transition-colors',
                        'border-green-200 bg-green-50/40' => $isVisible,
                        'border-gray-200 bg-gray-50/80' => ! $isVisible,
                    ]) data-zone-card>
                            <div class="mb-4 flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="font-semibold text-gray-900">{{ $zone->area_name }}</h3>
                                    <span @class([
                                        'zone-status-badge rounded-full px-2.5 py-0.5 text-[11px] font-semibold uppercase',
                                        'bg-green-100 text-green-700' => $isVisible,
                                        'bg-red-100 text-red-700' => ! $isVisible,
                                    ])>{{ $isVisible ? 'Live' : 'Hidden' }}</span>
                                </div>
                                <p class="mt-1 text-sm text-gray-600">{{ $zone->designation_notes ?: 'No designation notes.' }}</p>
                                <p class="mt-2 text-xs text-gray-500">{{ $zone->capacity ?? '—' }} slot capacity</p>
                            </div>
                            <label class="relative inline-flex shrink-0 cursor-pointer items-center" title="Show in user portal">
                                <input type="checkbox" name="visible[{{ $zone->id }}]" value="1" @checked($isVisible)
                                    class="zone-visible-toggle peer sr-only" data-zone-visible>
                                <span class="h-7 w-12 rounded-full bg-gray-300 transition-colors peer-checked:bg-green-500"></span>
                                <span class="absolute left-0.5 top-0.5 h-6 w-6 rounded-full bg-white shadow transition-transform peer-checked:translate-x-5"></span>
                            </label>
                        </div>
                        <div class="rounded-lg border border-white/80 bg-white p-3">
                            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">Who can see this zone?</p>
                            <div class="flex flex-wrap gap-2">
                                <label class="cursor-pointer">
                                    <input type="checkbox" name="roles[{{ $zone->id }}][]" value="Student"
                                        @checked(in_array('Student', $allowedRoles, true)) class="peer sr-only">
                                    <span class="inline-flex items-center gap-1.5 rounded-full border border-gray-200 bg-gray-50 px-3 py-1.5 text-sm font-medium text-gray-600 peer-checked:border-blue-600 peer-checked:bg-blue-600 peer-checked:text-white">
                                        Students
                                    </span>
                                </label>
                                <label class="cursor-pointer">
                                    <input type="checkbox" name="roles[{{ $zone->id }}][]" value="Staff"
                                        @checked(in_array('Staff', $allowedRoles, true)) class="peer sr-only">
                                    <span class="inline-flex items-center gap-1.5 rounded-full border border-gray-200 bg-gray-50 px-3 py-1.5 text-sm font-medium text-gray-600 peer-checked:border-indigo-600 peer-checked:bg-indigo-600 peer-checked:text-white">
                                        Faculty / Staff
                                    </span>
                                </label>
                                <label class="cursor-pointer">
                                    <input type="checkbox" name="roles[{{ $zone->id }}][]" value="Visitor"
                                        @checked(in_array('Visitor', $allowedRoles, true)) class="peer sr-only">
                                    <span class="inline-flex items-center gap-1.5 rounded-full border border-gray-200 bg-gray-50 px-3 py-1.5 text-sm font-medium text-gray-600 peer-checked:border-emerald-600 peer-checked:bg-emerald-600 peer-checked:text-white">
                                        Visitors
                                    </span>
                                </label>
                            </div>
                            @error("zone_{$zone->id}")
                                <p class="mt-2 text-xs font-medium text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="sticky bottom-0 z-10 flex flex-col gap-3 border-t border-gray-200 bg-white/95 px-5 py-4 backdrop-blur sm:flex-row sm:items-center sm:justify-between sm:px-6">
                <p class="text-sm text-gray-500">Changes apply after you save. Hidden zones remain visible to admins only.</p>
                <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-700 sm:w-auto">
                    <i data-lucide="save" class="h-4 w-4"></i>
                    Save Zone Access
                </button>
            </div>
        </form>
        @push('scripts')
        <script>
            (() => {
                const form = document.getElementById('zone-access-form');
                if (!form) return;
                const toggles = form.querySelectorAll('[data-zone-visible]');
                const sync = (t) => {
                    const card = t.closest('[data-zone-card]');
                    const badge = card?.querySelector('.zone-status-badge');
                    if (!card || !badge) return;
                    const on = t.checked;
                    card.classList.toggle('border-green-200', on);
                    card.classList.toggle('bg-green-50/40', on);
                    card.classList.toggle('border-gray-200', !on);
                    card.classList.toggle('bg-gray-50/80', !on);
                    badge.textContent = on ? 'Live' : 'Hidden';
                    badge.className = 'zone-status-badge rounded-full px-2.5 py-0.5 text-[11px] font-semibold uppercase ' + (on ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700');
                };
                toggles.forEach(t => { t.addEventListener('change', () => sync(t)); });
                document.getElementById('zone-show-all')?.addEventListener('click', () => toggles.forEach(t => { t.checked = true; sync(t); }));
                document.getElementById('zone-hide-all')?.addEventListener('click', () => toggles.forEach(t => { t.checked = false; sync(t); }));
            })();
        </script>
        @endpush
    @endif
</div>
