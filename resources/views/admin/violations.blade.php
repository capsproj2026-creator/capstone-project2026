@extends('layouts.admin')

@section('title', 'Violations')

@section('content')
    @include('partials.shell.page-header', [
        'title' => 'Violations',
        'subtitle' => 'Monitor citations and the campus 3-strike policy',
    ])

    {{-- Compact toolbar (keeps existing filters) --}}
    <form method="GET" class="mb-5 flex flex-wrap items-center gap-2">
        <input
            type="search"
            name="q"
            value="{{ $search }}"
            placeholder="Search plate, name, or type..."
            class="min-w-[180px] flex-1 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm shadow-sm"
        >
        <select name="status" class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm shadow-sm">
            <option value="all" @selected($statusFilter === 'all')>All statuses</option>
            <option value="Active" @selected($statusFilter === 'Active')>Active</option>
            <option value="Resolved" @selected($statusFilter === 'Resolved')>Resolved</option>
        </select>
        <select name="type" class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm shadow-sm">
            <option value="all" @selected($typeFilter === 'all')>All Types</option>
            @foreach ($violationTypes as $typeName)
                <option value="{{ $typeName }}" @selected($typeFilter === $typeName)>{{ $typeName }}</option>
            @endforeach
        </select>
        @if (($riskFilter ?? 'all') !== 'all')
            <input type="hidden" name="risk" value="{{ $riskFilter }}">
        @endif
        <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Apply</button>
        @if ($search !== '' || $statusFilter !== 'all' || $typeFilter !== 'all' || ($riskFilter ?? 'all') !== 'all')
            <a href="{{ route('admin.violations') }}" class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-600 hover:bg-gray-50">Clear</a>
        @endif
    </form>

    {{-- Summary cards --}}
    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
        <a href="{{ route('admin.violations', array_filter(['q' => $search ?: null, 'status' => $statusFilter !== 'all' ? $statusFilter : null, 'type' => $typeFilter !== 'all' ? $typeFilter : null])) }}"
           class="flex items-center justify-between rounded-xl border border-gray-200 bg-white p-5 shadow-sm hover:border-gray-300">
            <div>
                <p class="text-sm text-gray-500">Total Violations</p>
                <p class="mt-1 text-3xl font-bold tracking-tight text-gray-900">{{ $stats['total'] }}</p>
            </div>
            <i data-lucide="alert-triangle" class="h-6 w-6 text-gray-400"></i>
        </a>
        <a href="{{ route('admin.violations', array_filter(['q' => $search ?: null, 'status' => $statusFilter !== 'all' ? $statusFilter : null, 'type' => $typeFilter !== 'all' ? $typeFilter : null, 'risk' => 'second'])) }}"
           class="flex items-center justify-between rounded-xl border border-gray-200 bg-white p-5 shadow-sm hover:border-orange-200 {{ ($riskFilter ?? '') === 'second' ? 'ring-2 ring-orange-200' : '' }}">
            <div>
                <p class="text-sm text-gray-500">Users at 2nd Strike</p>
                <p class="mt-1 text-3xl font-bold tracking-tight text-orange-600">{{ $stats['second_strike'] }}</p>
            </div>
            <i data-lucide="shield" class="h-6 w-6 text-orange-500"></i>
        </a>
        <a href="{{ route('admin.violations', array_filter(['q' => $search ?: null, 'status' => $statusFilter !== 'all' ? $statusFilter : null, 'type' => $typeFilter !== 'all' ? $typeFilter : null, 'risk' => 'suspended'])) }}"
           class="flex items-center justify-between rounded-xl border border-gray-200 bg-white p-5 shadow-sm hover:border-red-200 {{ ($riskFilter ?? '') === 'suspended' ? 'ring-2 ring-red-200' : '' }}">
            <div>
                <p class="text-sm text-gray-500">Suspended Users</p>
                <p class="mt-1 text-3xl font-bold tracking-tight text-red-600">{{ $stats['suspended'] }}</p>
            </div>
            <i data-lucide="shield-alert" class="h-6 w-6 text-red-500"></i>
        </a>
    </div>

    <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-3">
        {{-- LEFT: Recent Violations --}}
        <section class="space-y-4 lg:col-span-2">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-lg font-semibold text-gray-900">Recent Violations</h2>
                <form method="GET">
                    @if ($search !== '') <input type="hidden" name="q" value="{{ $search }}"> @endif
                    @if ($statusFilter !== 'all') <input type="hidden" name="status" value="{{ $statusFilter }}"> @endif
                    @if (($riskFilter ?? 'all') !== 'all') <input type="hidden" name="risk" value="{{ $riskFilter }}"> @endif
                    <select name="type" onchange="this.form.submit()" class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-sm text-gray-700 shadow-sm">
                        <option value="all" @selected($typeFilter === 'all')>All Types</option>
                        @foreach ($violationTypes as $typeName)
                            <option value="{{ $typeName }}" @selected($typeFilter === $typeName)>{{ $typeName }}</option>
                        @endforeach
                    </select>
                </form>
            </div>

            @forelse ($logs as $row)
                @php
                    $strikes = min(3, (int) ($row->user?->strike_count ?? 0));
                    $locked = $row->user?->isLocked() ?? ($strikes >= 3);
                    $guardLabel = $guardNames[(string) ($row->guard_id ?? '')] ?? (
                        filled($row->guard_id)
                            ? (str_starts_with((string) $row->guard_id, 'AI-') ? 'AI Camera' : 'Guard #'.$row->guard_id)
                            : '—'
                    );
                    $location = filled($row->area_name)
                        ? (string) $row->area_name
                        : (filled($row->camera_id) ? (string) $row->camera_id : 'Campus');
                    // Inline colors so bars always render (Tailwind may not include dynamic utility classes).
                    $barHex = $strikes >= 3 ? '#ef4444' : ($strikes === 2 ? '#f97316' : ($strikes === 1 ? '#fbbf24' : null));
                    $evidenceUrl = filled($row->evidence_photo)
                        ? route('admin.violations.evidence', ['id' => (string) $row->getKey(), 'index' => 0])
                        : null;
                    $evidenceUrls = \App\Support\ViolationEvidence::urlsFor($row, 'admin.violations.evidence');
                    $detail = [
                        'id' => (string) ($row->getKey() ?? ''),
                        'name' => $row->violator_name,
                        'type' => $row->violation_type,
                        'status' => $row->status ?? 'Active',
                        'plate' => $row->plate_number,
                        'description' => $row->description ?: 'No description provided.',
                        'reported_by' => $guardLabel,
                        'id_number' => $row->id_number,
                        'location' => $location,
                        'camera' => $row->camera_id,
                        'vehicle' => $row->vehicle_details,
                        'evidence_url' => $evidenceUrl,
                        'evidence_urls' => $evidenceUrls,
                        'has_evidence' => count($evidenceUrls) > 0,
                        'datetime' => ph_datetime($row->created_at, 'n/j/Y, g:i:s A'),
                        'strikes' => $strikes,
                        'locked' => $locked,
                    ];
                @endphp

                <article class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="text-base font-semibold text-gray-900">{{ $row->violator_name }}</h3>
                                <span class="rounded-md bg-red-100 px-2 py-0.5 text-xs font-semibold lowercase text-red-700">
                                    {{ $row->violation_type }}
                                </span>
                            </div>
                            <p class="mt-2 text-sm leading-relaxed text-gray-600">
                                {{ $row->description ?: 'No description provided.' }}
                            </p>
                            <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1.5 text-xs text-gray-500">
                                <span class="inline-flex items-center gap-1.5">
                                    <i data-lucide="map-pin" class="h-3.5 w-3.5"></i>
                                    {{ $location }}
                                </span>
                                @if (filled($row->camera_id))
                                    <span class="inline-flex items-center gap-1.5">
                                        <i data-lucide="video" class="h-3.5 w-3.5"></i>
                                        {{ $row->camera_id }}
                                    </span>
                                @endif
                                @if (filled($row->vehicle_details))
                                    <span class="inline-flex items-center gap-1.5">
                                        <i data-lucide="truck" class="h-3.5 w-3.5"></i>
                                        {{ $row->vehicle_details }}
                                    </span>
                                @endif
                                <span class="inline-flex items-center gap-1.5">
                                    <i data-lucide="calendar" class="h-3.5 w-3.5"></i>
                                    {{ ph_datetime($row->created_at, 'n/j/Y, g:i:s A') }}
                                </span>
                                <span class="inline-flex items-center gap-1.5">
                                    <i data-lucide="user" class="h-3.5 w-3.5"></i>
                                    Reported by: {{ $guardLabel }}
                                </span>
                                <span class="inline-flex items-center gap-1.5">
                                    <i data-lucide="car" class="h-3.5 w-3.5"></i>
                                    <code class="text-gray-700">{{ $row->plate_number }}</code>
                                </span>
                            </div>
                            <x-violation.evidence-panel :log="$row" route-name="admin.violations.evidence" class="mt-3" />
                        </div>
                        <button
                            type="button"
                            class="inline-flex shrink-0 items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50"
                            data-violation-detail='@json($detail)'
                        >
                            <i data-lucide="eye" class="h-4 w-4"></i>
                            Details
                        </button>
                    </div>

                    <div class="mt-4 border-t border-gray-100 pt-4">
                        <p @class([
                            'mb-2 text-xs font-semibold',
                            'text-red-600' => $strikes >= 3,
                            'text-orange-600' => $strikes === 2,
                            'text-amber-600' => $strikes === 1,
                            'text-gray-800' => $strikes < 1,
                        ])>{{ $strikes }}/3 Strikes</p>
                        @if ($strikes >= 1)
                            @php($sanctionLabel = \App\Support\ViolationSanctionPresenter::labelForStrike($strikes))
                            @if ($sanctionLabel)
                                <p class="mb-2 text-xs leading-relaxed text-gray-600">{{ $sanctionLabel }}</p>
                            @endif
                        @endif
                        <div class="flex gap-1.5">
                            @for ($i = 1; $i <= 3; $i++)
                                <div
                                    class="h-2.5 flex-1 rounded-full {{ $i > $strikes ? 'bg-gray-200' : '' }}"
                                    @if ($i <= $strikes && $barHex)
                                        style="background-color: {{ $barHex }};"
                                    @endif
                                ></div>
                            @endfor
                        </div>
                        @if ($locked || $strikes >= 3)
                            <p class="mt-2 flex items-center gap-1.5 text-xs font-medium text-red-600">
                                <i data-lucide="lock" class="h-3.5 w-3.5"></i>
                                Account Suspended
                            </p>
                        @elseif ($strikes === 2)
                            <p class="mt-2 flex items-center gap-1.5 text-xs font-medium text-orange-600">
                                <i data-lucide="alert-triangle" class="h-3.5 w-3.5"></i>
                                One more violation will suspend account
                            </p>
                        @endif
                    </div>
                </article>
            @empty
                <div class="rounded-xl border border-dashed border-gray-300 bg-white px-6 py-16 text-center text-sm text-gray-500">
                    No violations found for the current filters.
                </div>
            @endforelse

            @if ($logs->hasPages())
                <div class="rounded-xl border border-gray-200 bg-white px-4 py-3">{{ $logs->links() }}</div>
            @endif
        </section>

        {{-- RIGHT: Side panel --}}
        <aside class="space-y-5 lg:sticky lg:top-24">
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <h3 class="text-base font-semibold text-gray-900">3-Strike System Overview</h3>
                <div class="mt-4 max-h-[24rem] space-y-5 overflow-y-auto">
                    @forelse ($strikeOverview as $user)
                        @php
                            $strikes = min(3, (int) ($user->strike_count ?? 0));
                            $locked = $user->isLocked() || $strikes >= 3;
                            $barHex = $strikes >= 3 ? '#ef4444' : ($strikes === 2 ? '#f97316' : ($strikes === 1 ? '#fbbf24' : null));
                            $badgeClass = $strikes >= 3
                                ? 'bg-red-100 text-red-700'
                                : ($strikes === 2 ? 'bg-orange-100 text-orange-700' : 'bg-amber-100 text-amber-800');
                        @endphp
                        <a href="{{ route('admin.violations', ['q' => $user->fullname]) }}" class="block rounded-lg transition hover:bg-gray-50">
                            <div class="mb-2 flex items-center justify-between gap-2">
                                <p class="truncate text-sm font-semibold text-gray-900">{{ $user->fullname }}</p>
                                <span class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-bold {{ $badgeClass }}">
                                    {{ $strikes }}/3 Strikes
                                </span>
                            </div>
                            @php($overviewSanction = \App\Support\ViolationSanctionPresenter::descriptionForStrike($strikes))
                            @if ($overviewSanction)
                                <p class="mb-2 text-[11px] leading-relaxed text-gray-500">{{ $overviewSanction }}</p>
                            @endif
                            <div class="flex gap-1">
                                @for ($i = 1; $i <= 3; $i++)
                                    <div
                                        class="h-2.5 flex-1 rounded-full {{ $i > $strikes ? 'bg-gray-200' : '' }}"
                                        @if ($i <= $strikes && $barHex)
                                            style="background-color: {{ $barHex }};"
                                        @endif
                                    ></div>
                                @endfor
                            </div>
                            @if ($locked)
                                <p class="mt-2 flex items-center gap-1 text-xs font-medium text-red-600">
                                    <i data-lucide="lock" class="h-3 w-3"></i>
                                    Account Suspended
                                </p>
                            @elseif ($strikes === 2)
                                <p class="mt-2 flex items-center gap-1 text-xs font-medium text-orange-600">
                                    <i data-lucide="alert-triangle" class="h-3 w-3"></i>
                                    One more violation will suspend account
                                </p>
                            @endif
                        </a>
                    @empty
                        <p class="text-sm text-gray-500">No users with strikes yet.</p>
                    @endforelse
                </div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <h3 class="text-base font-semibold text-gray-900">Violation Types</h3>
                <ul class="mt-4 space-y-3">
                    @forelse ($typeCounts as $typeName => $count)
                        <li>
                            <a
                                href="{{ route('admin.violations', array_filter([
                                    'q' => $search !== '' ? $search : null,
                                    'status' => $statusFilter !== 'all' ? $statusFilter : null,
                                    'type' => $typeName,
                                    'risk' => ($riskFilter ?? 'all') !== 'all' ? $riskFilter : null,
                                ])) }}"
                                class="flex items-center justify-between text-sm {{ $typeFilter === $typeName ? 'font-semibold text-blue-700' : 'text-gray-700 hover:text-gray-900' }}"
                            >
                                <span>{{ $typeName }}</span>
                                <span class="inline-flex h-6 min-w-6 items-center justify-center rounded-full bg-gray-100 px-1.5 text-xs font-semibold text-gray-700">
                                    {{ $count }}
                                </span>
                            </a>
                        </li>
                    @empty
                        <li class="text-sm text-gray-500">No types recorded.</li>
                    @endforelse
                </ul>
            </div>
        </aside>
    </div>

    {{-- Details modal --}}
    <div id="violation-detail-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4" role="dialog" aria-modal="true">
        <div class="w-full max-w-lg overflow-hidden rounded-xl bg-white shadow-2xl">
            <div class="flex items-center justify-between border-b border-gray-200 px-5 py-4">
                <h3 class="text-lg font-semibold text-gray-900">Violation Details</h3>
                <button type="button" id="violation-detail-close" class="rounded-md p-1.5 text-gray-500 hover:bg-gray-100" aria-label="Close">
                    <i data-lucide="x" class="h-5 w-5"></i>
                </button>
            </div>
            <dl class="divide-y divide-gray-100 px-5 py-1 text-sm">
                <div class="flex justify-between gap-4 py-3"><dt class="text-gray-500">Violation ID</dt><dd id="vd-id" class="text-right font-medium text-gray-900"></dd></div>
                <div class="flex justify-between gap-4 py-3"><dt class="text-gray-500">User</dt><dd id="vd-name" class="text-right font-medium text-gray-900"></dd></div>
                <div class="flex justify-between gap-4 py-3"><dt class="text-gray-500">Type</dt><dd id="vd-type" class="text-right font-medium lowercase text-red-600"></dd></div>
                <div class="flex justify-between gap-4 py-3"><dt class="text-gray-500">Location</dt><dd id="vd-location" class="text-right font-medium text-gray-900"></dd></div>
                <div class="flex justify-between gap-4 py-3"><dt class="text-gray-500">Date &amp; Time</dt><dd id="vd-datetime" class="text-right font-medium text-gray-900"></dd></div>
                <div class="flex justify-between gap-4 py-3"><dt class="text-gray-500">Reported By</dt><dd id="vd-reported" class="text-right font-medium text-gray-900"></dd></div>
                <div class="flex justify-between gap-4 py-3"><dt class="text-gray-500">Vehicle Plate</dt><dd id="vd-plate" class="text-right font-medium text-gray-900"></dd></div>
                <div class="flex justify-between gap-4 py-3"><dt class="text-gray-500">Strikes</dt><dd id="vd-strikes" class="text-right font-medium text-gray-900"></dd></div>
                <div class="py-3">
                    <dt class="text-gray-500">Description</dt>
                    <dd id="vd-description" class="mt-2 rounded-lg bg-gray-50 px-3 py-2 text-gray-800"></dd>
                </div>
                <div class="py-3">
                    <dt class="mb-2 text-gray-500">Photo Evidence</dt>
                    <dd id="vd-evidence" class="min-h-[2rem]"></dd>
                </div>
            </dl>
            <div class="flex justify-end border-t border-gray-200 px-5 py-4">
                <button type="button" id="violation-detail-close-btn" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-800 hover:bg-gray-50">
                    Close
                </button>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    (() => {
        const modal = document.getElementById('violation-detail-modal');
        const set = (id, value) => {
            const el = document.getElementById(id);
            if (el) el.textContent = value ?? '—';
        };
        const open = (data) => {
            set('vd-id', data.id || '—');
            set('vd-name', data.name);
            set('vd-type', data.type);
            set('vd-location', data.location || 'Campus');
            set('vd-datetime', data.datetime);
            set('vd-reported', data.reported_by);
            set('vd-plate', data.plate);
            set('vd-strikes', `${data.strikes ?? 0}/3${data.locked ? ' (Suspended)' : ''}`);
            set('vd-description', data.description);

            const evidenceEl = document.getElementById('vd-evidence');
            if (evidenceEl) {
                const urls = Array.isArray(data.evidence_urls) ? data.evidence_urls : [];
                if (urls.length) {
                    evidenceEl.innerHTML = `
                        <button type="button"
                            class="inline-flex items-center gap-2 rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-sm font-semibold text-blue-700 hover:bg-blue-100"
                            data-violation-evidence-open
                            data-evidence-urls='${JSON.stringify(urls)}'
                            data-evidence-title="Evidence · ${data.type || 'Violation'}">
                            <i data-lucide="image" class="h-4 w-4"></i>
                            View Evidence${urls.length > 1 ? ` (${urls.length})` : ''}
                        </button>`;
                } else {
                    evidenceEl.innerHTML = '<p class="text-sm italic text-gray-400">No Evidence Available.</p>';
                }
            }

            modal?.classList.remove('hidden');
            modal?.classList.add('flex');
            if (window.lucide) window.lucide.createIcons();
        };
        const close = () => {
            modal?.classList.add('hidden');
            modal?.classList.remove('flex');
        };

        document.querySelectorAll('[data-violation-detail]').forEach((btn) => {
            btn.addEventListener('click', () => {
                try { open(JSON.parse(btn.getAttribute('data-violation-detail') || '{}')); } catch (e) {}
            });
        });

        document.getElementById('violation-detail-close')?.addEventListener('click', close);
        document.getElementById('violation-detail-close-btn')?.addEventListener('click', close);
        modal?.addEventListener('click', (e) => { if (e.target === modal) close(); });
        document.addEventListener('keydown', (e) => { if (e.key === 'Escape') close(); });

        if (window.lucide) window.lucide.createIcons();
    })();
</script>
@endpush
