@extends('layouts.user')

@section('title', 'Parking')

@section('content')
    @include('partials.shell.page-header', [
        'title' => 'Parking',
        'subtitle' => $roleLabel.' parking map · live color status',
    ])

    <div class="mb-6 grid grid-cols-1 gap-4 lg:grid-cols-4">
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm lg:col-span-3">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">Campus Parking Map</h3>
                    <p class="mt-1 text-sm text-gray-600">
                        Slot IDs only. Status is shown by color and updates in real time across Admin, Guard, and User.
                    </p>
                </div>
                <span id="user-parking-updated" class="rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-600">Live</span>
            </div>
            <div class="mt-4 flex flex-wrap gap-2">
                <span class="inline-flex items-center gap-1.5 rounded-full border border-green-200 bg-green-50 px-3 py-1 text-xs font-medium text-green-700">
                    <span class="h-2.5 w-2.5 rounded-full bg-green-500"></span> Available
                </span>
                <span class="inline-flex items-center gap-1.5 rounded-full border border-red-200 bg-red-50 px-3 py-1 text-xs font-medium text-red-700">
                    <span class="h-2.5 w-2.5 rounded-full bg-red-500"></span> Occupied
                </span>
                <span class="inline-flex items-center gap-1.5 rounded-full border border-blue-200 bg-blue-50 px-3 py-1 text-xs font-medium text-blue-700">
                    <span class="h-2.5 w-2.5 rounded-full bg-blue-500"></span> Reserved
                </span>
                <span class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-medium text-slate-700">
                    <span class="h-2.5 w-2.5 rounded-full bg-slate-500"></span> Maintenance
                </span>
            </div>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-gradient-to-br from-slate-900 to-blue-900 p-5 text-white shadow-sm">
            <p class="text-xs font-medium text-blue-100">Violations</p>
            <p class="mt-2 text-3xl font-bold">{{ $strikeCount }} / {{ $maxStrikes }}</p>
            <p class="mt-1 text-sm text-blue-100/80">Strikes on your account</p>
        </div>
    </div>

    @if ($zoneStats->isEmpty())
        <div class="rounded-2xl border border-gray-200 bg-white px-6 py-14 text-center text-sm text-gray-500 shadow-sm">
            No parking areas are currently available for {{ strtolower($roleLabel) }} accounts.
        </div>
    @else
        @php
            $lotSnapshots = collect(\App\Services\ParkingZoneSnapshot::fromApp()->all())->keyBy('area_id');
        @endphp
        <div id="user-zone-maps" class="space-y-5">
            @foreach ($zoneStats as $zone)
                <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm" data-zone-id="{{ $zone['area']->id }}">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 bg-gray-50/80 px-5 py-4">
                        <div>
                            <h3 class="font-semibold text-gray-900">
                                {{ $zone['area']->area_name }}
                                @if (! empty($zone['ai_monitored']))
                                    <span class="ml-2 rounded-full bg-blue-100 px-2 py-0.5 text-[10px] font-semibold uppercase text-blue-700">AI</span>
                                @endif
                                @if (! empty($zone['hidden']))
                                    <span class="ml-2 rounded-full bg-slate-200 px-2 py-0.5 text-[10px] font-semibold uppercase text-slate-700">Maintenance</span>
                                @endif
                            </h3>
                            <p class="text-xs text-gray-500">{{ $zone['area']->designation_notes ?: 'Campus parking zone' }}</p>
                        </div>
                        <div class="flex gap-3 text-xs font-medium">
                            <span class="text-green-700"><span class="zone-available">{{ $zone['available'] }}</span> free</span>
                            <span class="text-red-700"><span class="zone-occupied">{{ $zone['occupied'] }}</span> used</span>
                            <span class="text-gray-500"><span class="zone-total">{{ $zone['total'] }}</span> total</span>
                        </div>
                    </div>
                    @php
                        $lotSnapshot = $lotSnapshots->get((int) $zone['area']->id);
                    @endphp
                    @if ($lotSnapshot)
                        <div class="border-b border-gray-100 p-4">
                            @include('partials.parking-zone-snapshot', ['snapshot' => $lotSnapshot, 'compact' => true])
                        </div>
                    @endif
                    <div class="zone-slot-grid grid grid-cols-3 gap-2 p-4 sm:grid-cols-4 md:grid-cols-6 lg:grid-cols-8">
                        @foreach ($zone['slots'] as $slot)
                            @php
                                $status = $slot->status ?? 'Available';
                                $tone = match ($status) {
                                    'Available' => 'bg-green-500 border-green-300',
                                    'Occupied' => 'bg-red-500 border-red-300',
                                    'Reserved' => 'bg-blue-500 border-blue-300',
                                    'Maintenance' => 'bg-slate-500 border-slate-300',
                                    default => 'bg-gray-400 border-gray-300',
                                };
                            @endphp
                            <div
                                data-slot-id="{{ $slot->id }}"
                                data-status="{{ $status }}"
                                class="flex min-h-[3.25rem] items-center justify-center rounded-lg border-2 px-2 py-2 text-center text-xs font-bold text-white {{ $tone }}"
                                title="{{ $status }}"
                            >{{ $slot->slot_number }}</div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @endif
@endsection

@push('scripts')
<script>
(() => {
    const statusUrl = @json($statusUrl);
    const updated = document.getElementById('user-parking-updated');
    const tones = {
        Available: ['bg-green-500', 'border-green-300'],
        Occupied: ['bg-red-500', 'border-red-300'],
        Reserved: ['bg-blue-500', 'border-blue-300'],
        Maintenance: ['bg-slate-500', 'border-slate-300'],
    };
    const allTones = Object.values(tones).flat();

    const refresh = async () => {
        if (document.hidden) return;
        try {
            const response = await fetch(statusUrl, { headers: { Accept: 'application/json' }, cache: 'no-store', credentials: 'same-origin' });
            if (!response.ok) return;
            const data = await response.json();
            (data.zones || []).forEach((zone) => {
                const card = document.querySelector(`[data-zone-id="${zone.id}"]`);
                if (!card) return;
                card.querySelector('.zone-total')?.replaceChildren(document.createTextNode(zone.total ?? 0));
                card.querySelector('.zone-available')?.replaceChildren(document.createTextNode(zone.available ?? 0));
                card.querySelector('.zone-occupied')?.replaceChildren(document.createTextNode(zone.occupied ?? 0));
                (zone.slots || []).forEach((slot) => {
                    const tile = card.querySelector(`[data-slot-id="${slot.id}"]`);
                    if (!tile) return;
                    const status = slot.status || 'Available';
                    tile.classList.remove(...allTones);
                    tile.classList.add(...(tones[status] || tones.Available));
                    tile.dataset.status = status;
                    tile.title = status;
                });
            });
            if (updated) updated.textContent = `Updated ${data.updated_at}`;
        } catch (e) {}
    };

    refresh();
    window.setInterval(refresh, 5000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
})();
</script>
@endpush
