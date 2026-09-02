@extends('layouts.portal')

@section('title', 'Parking Zones & Spaces')

@section('content')
    @include('partials.shell.page-header', [
        'title' => 'Parking — Zones & Spaces',
        'subtitle' => 'Add or remove parking areas and individual spaces. AI-monitored lots cannot be deleted.',
    ])

    @include('partials.admin.parking-nav', ['active' => 'layout'])

    @php
        $protectedAreaIds = $protectedAreaIds ?? [];
        $occupiedByArea = collect($occupiedByArea ?? []);
        $layoutService = $layoutService ?? app(\App\Services\ParkingLayoutService::class);
        $lotSnapshots = collect(\App\Services\ParkingZoneSnapshot::fromApp()->all())->keyBy('area_id');
        $zoneSnapshot = $selectedZone ? $lotSnapshots->get((int) $selectedZone->id) : null;
    @endphp

    <div class="mb-6 rounded-xl border border-gray-200 bg-white p-4 sm:p-5">
        <h2 class="text-base font-semibold text-gray-900">Add parking area</h2>
        <p class="mt-1 text-sm text-gray-600">Creates a new zone and its parking spaces. Slot labels use the prefix (example: NB-1).</p>
        <form method="POST" action="{{ route('admin.parking.areas.store') }}" class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @csrf
            <div class="sm:col-span-2">
                <label class="mb-1.5 block text-sm font-medium text-gray-700">Area name</label>
                <input type="text" name="area_name" value="{{ old('area_name') }}" required maxlength="120"
                    class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700">Slot prefix</label>
                <input type="text" name="slot_prefix" value="{{ old('slot_prefix') }}" required maxlength="6"
                    placeholder="NB"
                    class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm uppercase focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700">Spaces</label>
                <input type="number" name="slot_count" value="{{ old('slot_count', 10) }}" min="1" max="200" required
                    class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
            </div>
            <div class="sm:col-span-2 lg:col-span-4">
                <label class="mb-1.5 block text-sm font-medium text-gray-700">Designation notes</label>
                <input type="text" name="designation_notes" value="{{ old('designation_notes') }}" maxlength="255"
                    placeholder="Students / Faculty / Visitors"
                    class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
            </div>
            <div class="sm:col-span-2 lg:col-span-3">
                <p class="mb-1.5 text-sm font-medium text-gray-700">Who can see this zone?</p>
                <div class="flex flex-wrap gap-2">
                    @foreach (['Student' => 'Students', 'Staff' => 'Faculty / Staff', 'Visitor' => 'Visitors'] as $role => $label)
                        <label class="cursor-pointer">
                            <input type="checkbox" name="allowed_roles[]" value="{{ $role }}" class="peer sr-only"
                                @checked(in_array($role, old('allowed_roles', ['Student', 'Staff']), true))>
                            <span class="inline-flex rounded-full border border-gray-200 bg-gray-50 px-3 py-1.5 text-sm font-medium text-gray-600 peer-checked:border-blue-600 peer-checked:bg-blue-600 peer-checked:text-white">{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
            <div class="flex items-end">
                <label class="mb-1 inline-flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" name="is_visible" value="1" @checked(old('is_visible', true)) class="rounded border-gray-300 text-blue-600">
                    Visible in portal
                </label>
            </div>
            <div class="sm:col-span-2 lg:col-span-4">
                <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700">
                    <i data-lucide="plus" class="h-4 w-4"></i>
                    Add parking area
                </button>
            </div>
        </form>
    </div>

    <div class="mb-6 grid gap-4 lg:grid-cols-3">
        <div class="rounded-xl border border-gray-200 bg-white lg:col-span-1">
            <div class="border-b border-gray-100 px-4 py-3">
                <h2 class="text-sm font-semibold text-gray-900">Parking areas</h2>
                <p class="mt-0.5 text-xs text-gray-500">Select a zone to add or remove its spaces.</p>
            </div>
            <div class="max-h-[32rem] divide-y divide-gray-100 overflow-y-auto">
                @forelse ($zones as $zone)
                    @php
                        $isSelected = $selectedZone && (int) $selectedZone->id === (int) $zone->id;
                        $isProtected = in_array((int) $zone->id, $protectedAreaIds, true);
                        $occupied = (int) $occupiedByArea->get((int) $zone->id, 0);
                        $prefix = $layoutService->slotPrefix($zone);
                    @endphp
                    <div @class(['p-4', 'bg-blue-50/60' => $isSelected])>
                        <div class="flex items-start justify-between gap-2">
                            <a href="{{ route('admin.parking.layout', ['zone_id' => $zone->id]) }}" class="min-w-0">
                                <p class="font-semibold text-gray-900">
                                    <span class="mr-1 text-xs font-semibold text-gray-400">#{{ $zone->id }}</span>
                                    {{ $zone->area_name }}
                                </p>
                                <p class="mt-0.5 text-xs text-gray-500">
                                    Prefix {{ $prefix }} · {{ $zone->capacity ?? 0 }} spaces
                                    @if ($occupied > 0)
                                        · {{ $occupied }} occupied
                                    @endif
                                </p>
                                @if ($isProtected)
                                    <span class="mt-1 inline-flex rounded-full bg-indigo-50 px-2 py-0.5 text-[11px] font-semibold text-indigo-700">AI monitored</span>
                                @endif
                                @if ($lotSnapshots->has((int) $zone->id))
                                    <img src="{{ asset($lotSnapshots->get((int) $zone->id)['path']) }}" alt="" class="parking-zone-thumb mt-2 h-16 w-full rounded-md object-contain">
                                @endif
                            </a>
                            @if ($isProtected)
                                <span class="shrink-0 text-[11px] font-medium text-gray-400">Locked</span>
                            @else
                                <form method="POST" action="{{ route('admin.parking.areas.destroy', $zone->id) }}"
                                      onsubmit="return confirm('Remove parking area {{ $zone->area_name }} and all of its spaces?')">
                                    @csrf
                                    <button type="submit" class="rounded-lg border border-rose-200 bg-white px-2.5 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-50">
                                        Remove
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="px-4 py-8 text-center text-sm text-gray-500">No parking areas yet. Add one above.</p>
                @endforelse
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white lg:col-span-2">
            <div class="flex flex-col gap-3 border-b border-gray-100 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-sm font-semibold text-gray-900">
                        {{ $selectedZone?->area_name ?? 'Parking spaces' }}
                    </h2>
                    <p class="mt-0.5 text-xs text-gray-500">Occupied spaces cannot be removed.</p>
                </div>
                @if ($selectedZone)
                    <form method="POST" action="{{ route('admin.parking.slots.store') }}" class="flex flex-wrap items-end gap-2">
                        @csrf
                        <input type="hidden" name="area_id" value="{{ $selectedZone->id }}">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-600">Add spaces</label>
                            <input type="number" name="slot_count" value="1" min="1" max="50"
                                class="w-24 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                        </div>
                        <button type="submit" class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                            Add
                        </button>
                    </form>
                @endif
            </div>

            @if ($zoneSnapshot)
                <div class="border-b border-gray-100 p-4">
                    @include('partials.parking-zone-snapshot', ['snapshot' => $zoneSnapshot])
                </div>
            @endif

            @if (! $selectedZone)
                <p class="px-4 py-12 text-center text-sm text-gray-500">Add a parking area to manage its spaces.</p>
            @elseif ($slots->isEmpty())
                <p class="px-4 py-12 text-center text-sm text-gray-500">This area has no spaces yet. Add some using the form above.</p>
            @else
                <div class="grid grid-cols-2 gap-2 p-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5">
                    @foreach ($slots as $slot)
                        @php
                            $slotLabel = $slot->slot_number ?: ('SLOT-'.$slot->id);
                            $occupied = $slot->isOccupied();
                        @endphp
                        <div @class([
                            'flex items-center justify-between gap-2 rounded-lg border px-3 py-2 text-sm',
                            'border-red-200 bg-red-50 text-red-800' => $occupied,
                            'border-gray-200 bg-gray-50 text-gray-800' => ! $occupied,
                        ])>
                            <span class="font-semibold">{{ $slotLabel }}</span>
                            @if ($occupied)
                                <span class="text-[11px] font-medium">Busy</span>
                            @else
                                <form method="POST" action="{{ route('admin.parking.slots.destroy', $slot->id) }}"
                                      onsubmit="return confirm('Remove parking space {{ $slotLabel }}?')">
                                    @csrf
                                    <button type="submit" class="text-xs font-semibold text-rose-700 hover:underline">Remove</button>
                                </form>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
@endsection
