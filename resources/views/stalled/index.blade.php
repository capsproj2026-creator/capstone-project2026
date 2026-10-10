@extends($layout)

@section('title', 'Stalled Vehicles')

@section('content')
<div class="min-w-0 max-w-full">
    @include('partials.shell.page-header', [
        'title' => 'Stalled Vehicles',
        'subtitle' => 'Report stalled vehicles to the GSU. Owners get a '.\App\Models\StalledVehicleReport::GRACE_HOURS.'-hour grace period, then '.\App\Models\StalledVehicleReport::TOW_HOURS.' hours to tow.',
    ])

    @if (session('success'))
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>
    @endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <form method="POST" action="{{ route($prefix.'.stalled-vehicles.store') }}" class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            @csrf
            <h2 class="text-base font-semibold text-gray-900">Report a stalled vehicle</h2>
            <p class="mt-1 text-xs leading-relaxed text-gray-500">A vehicle with a lost or broken key counts as stalled. The registered owner is notified right away.</p>

            <div class="mt-4 space-y-3">
                <div>
                    <label for="plate_number" class="block text-xs font-medium text-gray-700">Plate number</label>
                    <input id="plate_number" name="plate_number" value="{{ old('plate_number') }}" required maxlength="20" autocomplete="off"
                           class="mt-1 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm uppercase shadow-sm">
                </div>
                <div>
                    <label for="owner_name" class="block text-xs font-medium text-gray-700">Owner name</label>
                    <input id="owner_name" name="owner_name" value="{{ old('owner_name') }}" maxlength="120"
                           placeholder="Filled in automatically for registered plates"
                           class="mt-1 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm shadow-sm">
                </div>
                <div>
                    <label for="location" class="block text-xs font-medium text-gray-700">Location</label>
                    <input id="location" name="location" value="{{ old('location') }}" required maxlength="160"
                           placeholder="e.g. Duran Hall parking, slot 4"
                           class="mt-1 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm shadow-sm">
                </div>
                <div>
                    <label for="cause" class="block text-xs font-medium text-gray-700">Reason</label>
                    <select id="cause" name="cause" class="mt-1 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm shadow-sm">
                        <option value="{{ \App\Models\StalledVehicleReport::CAUSE_STALLED }}" @selected(old('cause') !== \App\Models\StalledVehicleReport::CAUSE_KEY)>Stalled / will not start</option>
                        <option value="{{ \App\Models\StalledVehicleReport::CAUSE_KEY }}" @selected(old('cause') === \App\Models\StalledVehicleReport::CAUSE_KEY)>Lost or broken key</option>
                    </select>
                </div>
                <div>
                    <label for="notes" class="block text-xs font-medium text-gray-700">Notes (optional)</label>
                    <textarea id="notes" name="notes" rows="2" maxlength="500"
                              class="mt-1 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm shadow-sm">{{ old('notes') }}</textarea>
                </div>
                <button type="submit" class="inline-flex w-full items-center justify-center gap-1.5 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                    <i data-lucide="wrench" class="h-4 w-4"></i>
                    Log stalled vehicle
                </button>
            </div>
        </form>

        <section class="min-w-0 space-y-4 lg:col-span-2">
            <h2 class="text-lg font-semibold text-gray-900">Active reports ({{ $active->count() }})</h2>

            @forelse ($active as $report)
                @php
                    $stage = $report->stage();
                    [$stageLabel, $stageTone, $deadline, $deadlineLabel] = match ($stage) {
                        'grace' => ['Grace period', 'bg-amber-50 text-amber-800 border-amber-200', $report->graceEndsAt(), 'Grace ends'],
                        'tow' => ['Tow required', 'bg-orange-50 text-orange-700 border-orange-200', $report->towDeadline(), 'Tow by'],
                        default => ['Tow overdue', 'bg-red-50 text-red-700 border-red-200', $report->towDeadline(), 'Tow deadline was'],
                    };
                @endphp
                <article class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <code class="text-base font-semibold text-gray-900">{{ $report->plate_number }}</code>
                                <span class="rounded-full border px-2 py-0.5 text-xs font-semibold {{ $stageTone }}">{{ $stageLabel }}</span>
                            </div>
                            <p class="mt-1 text-sm text-gray-700">{{ $report->owner_name }}{{ $report->user_id ? '' : ' (unregistered)' }}</p>
                            <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-500">
                                <span class="inline-flex items-center gap-1.5"><i data-lucide="map-pin" class="h-3.5 w-3.5"></i>{{ $report->location }}</span>
                                <span class="inline-flex items-center gap-1.5"><i data-lucide="wrench" class="h-3.5 w-3.5"></i>{{ $report->causeLabel() }}</span>
                                <span class="inline-flex items-center gap-1.5"><i data-lucide="calendar" class="h-3.5 w-3.5"></i>Reported {{ ph_datetime($report->reported_at, 'M j, g:i A') }} by {{ $staffNames[(int) $report->reported_by] ?? 'Security' }}</span>
                            </div>
                            @if ($report->notes)
                                <p class="mt-2 text-sm text-gray-600">{{ $report->notes }}</p>
                            @endif
                            <p class="mt-2 text-sm font-medium {{ $stage === 'overdue' ? 'text-red-700' : 'text-gray-800' }}">
                                {{ $deadlineLabel }} {{ ph_datetime($deadline, 'M j, g:i A') }}
                                @if ($stage !== 'overdue')
                                    <span class="text-xs font-normal text-gray-500" data-countdown="{{ $deadline->toIso8601String() }}"></span>
                                @endif
                            </p>
                        </div>
                        <form method="POST" action="{{ route($prefix.'.stalled-vehicles.removed', $report->id) }}" class="flex shrink-0 flex-col gap-2" style="min-width: 13rem;"
                              onsubmit="return confirm('Mark {{ $report->plate_number }} as removed from campus?')">
                            @csrf
                            <input type="text" name="removal_notes" maxlength="300" placeholder="Repaired, towed, key found…"
                                   class="w-full rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs shadow-sm">
                            <button type="submit" class="inline-flex items-center justify-center gap-1.5 rounded-lg bg-emerald-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-emerald-700">
                                <i data-lucide="check" class="h-4 w-4"></i>
                                Mark removed
                            </button>
                        </form>
                    </div>
                </article>
            @empty
                <div class="rounded-xl border border-dashed border-gray-300 bg-white px-6 py-12 text-center text-sm text-gray-500">No stalled vehicles on campus.</div>
            @endforelse

            @if ($removed->isNotEmpty())
                <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
                    <h3 class="border-b border-gray-100 px-5 py-3 text-sm font-semibold text-gray-900">Recently removed</h3>
                    <ul class="divide-y divide-gray-100 text-sm">
                        @foreach ($removed as $report)
                            <li class="flex flex-col gap-1 px-5 py-3 sm:flex-row sm:items-center sm:justify-between">
                                <span><code class="text-gray-800">{{ $report->plate_number }}</code> · {{ $report->owner_name }} · {{ $report->location }}</span>
                                <span class="text-xs text-gray-500">
                                    Removed {{ ph_datetime($report->removed_at, 'M j, g:i A') }}
                                    @if ($report->removed_at && $report->removed_at->gt($report->towDeadline())) <span class="font-medium text-red-700">(after deadline)</span> @endif
                                    @if ($report->removal_notes) — {{ $report->removal_notes }} @endif
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </section>
    </div>
</div>

<script>
    (function () {
        const nodes = document.querySelectorAll('[data-countdown]');
        if (!nodes.length) return;
        const tick = () => {
            const now = Date.now();
            nodes.forEach((el) => {
                const ms = new Date(el.dataset.countdown).getTime() - now;
                if (ms <= 0) { el.textContent = '(time is up — refresh)'; return; }
                const h = Math.floor(ms / 3600000);
                const m = Math.floor((ms % 3600000) / 60000);
                el.textContent = `(${h}h ${m}m left)`;
            });
        };
        tick();
        setInterval(tick, 30000);
    })();
</script>
@endsection
