@extends('layouts.user')

@section('title', 'Parking')

@section('content')
    @include('partials.shell.page-header', [
        'title' => 'Parking',
        'subtitle' => $roleLabel.' parking areas and live availability',
    ])

    <div class="mb-8 grid grid-cols-1 gap-6 md:grid-cols-3">
        <div class="rounded-xl border border-gray-200 bg-white p-6 md:col-span-2">
            <h3 class="mb-2 font-semibold text-gray-900">Campus Parking</h3>
            <p class="text-sm text-gray-600">
                Parking is first-come, first-served in zones available to your account.
                Occupancy for the AI Test Lot updates from the live camera when the YOLOv9 service is running.
            </p>
            @if (! empty($aiSnapshot))
                <p class="mt-3 text-sm text-gray-500">
                    AI Test Lot: <strong class="text-gray-900">{{ $aiSnapshot['occupied'] ?? 0 }}</strong> occupied /
                    <strong class="text-gray-900">{{ $aiSnapshot['available'] ?? 0 }}</strong> available
                    <span class="text-gray-400">· {{ $aiSnapshot['updated_at_label'] ?? '' }}</span>
                </p>
            @endif
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-6">
            <h3 class="mb-2 font-semibold text-gray-900">Violations</h3>
            <p class="text-2xl font-bold text-gray-900">{{ $strikeCount }} / {{ $maxStrikes }}</p>
            <p class="mt-1 text-sm text-gray-500">Strikes on your account</p>
        </div>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white">
        <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4">
            <div>
                <h3 class="font-semibold text-gray-900">{{ $roleLabel }} Zone Availability</h3>
                <p class="mt-1 text-sm text-gray-500">Only parking areas designated for your account type are shown here.</p>
            </div>
            <span id="user-parking-updated" class="text-xs text-gray-400">Live</span>
        </div>
        @if ($zoneStats->isEmpty())
            <div class="px-6 py-10 text-center text-sm text-gray-500">
                No parking areas are currently available for {{ strtolower($roleLabel) }} accounts.
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-gray-200 bg-gray-50 text-gray-500">
                        <tr>
                            <th class="px-6 py-3 font-medium">Zone</th>
                            <th class="px-6 py-3 font-medium">Designation</th>
                            <th class="px-6 py-3 font-medium">Total</th>
                            <th class="px-6 py-3 font-medium">Available</th>
                            <th class="px-6 py-3 font-medium">Occupied</th>
                        </tr>
                    </thead>
                    <tbody id="user-zone-rows" class="divide-y divide-gray-100">
                        @foreach ($zoneStats as $zone)
                            <tr class="hover:bg-gray-50" data-zone-id="{{ $zone['area']->id }}">
                                <td class="px-6 py-4 font-medium text-gray-900">
                                    {{ $zone['area']->area_name }}
                                    @if (! empty($zone['ai_monitored']))
                                        <span class="ml-2 rounded-full bg-blue-100 px-2 py-0.5 text-[10px] font-semibold uppercase text-blue-700">AI</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-gray-500">{{ $zone['area']->designation_notes ?: '—' }}</td>
                                <td class="zone-total px-6 py-4">{{ $zone['total'] }}</td>
                                <td class="zone-available px-6 py-4 text-green-600">{{ $zone['available'] }}</td>
                                <td class="zone-occupied px-6 py-4 text-orange-600">{{ $zone['occupied'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection

@push('scripts')
<script>
    (() => {
        const statusUrl = @json($statusUrl);
        const updated = document.getElementById('user-parking-updated');

        const refresh = async () => {
            if (document.hidden) return;
            try {
                const response = await fetch(statusUrl, { headers: { Accept: 'application/json' }, cache: 'no-store' });
                if (!response.ok) return;
                const data = await response.json();
                (data.zones || []).forEach((zone) => {
                    const row = document.querySelector(`[data-zone-id="${zone.id}"]`);
                    if (!row) return;
                    row.querySelector('.zone-total').textContent = zone.total;
                    row.querySelector('.zone-available').textContent = zone.available;
                    row.querySelector('.zone-occupied').textContent = zone.occupied;
                });
                if (updated) updated.textContent = `Updated ${data.updated_at}`;
            } catch (e) {}
        };

        refresh();
        window.setInterval(refresh, 2500);
    })();
</script>
@endpush
