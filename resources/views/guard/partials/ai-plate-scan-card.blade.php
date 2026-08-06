@php
    $registered = ! empty($scan['registered']);
    $unreadable = ($scan['plate_status'] ?? '') === 'unreadable';
    $border = $unreadable ? 'border-slate-300' : ($registered ? 'border-emerald-400' : 'border-amber-400');
    $bg = $unreadable ? 'bg-slate-50' : ($registered ? 'bg-emerald-50' : 'bg-amber-50');
    $profileUrl = $scan['owner_profile_url'] ?? null;
    $ownerName = $registered ? ($scan['owner_name'] ?? 'Registered') : ($unreadable ? 'Plate Unreadable' : 'Unknown Vehicle');
@endphp

<div @class(['overflow-hidden rounded-xl border-2 shadow-sm', $border, $bg])>
    <div class="flex items-start gap-4 p-4">
        <div class="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-full bg-white shadow-inner">
            @if ($registered && $profileUrl)
                <img src="{{ $profileUrl }}" alt="{{ $ownerName }}" class="h-full w-full object-cover">
            @else
                <i data-lucide="{{ $registered ? 'user' : 'car' }}" class="h-7 w-7 {{ $registered ? 'text-emerald-600' : 'text-amber-600' }}"></i>
            @endif
        </div>
        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
                <p class="text-xl font-bold tracking-wide text-gray-900">{{ $scan['plate'] ?? '—' }}</p>
                @if (! empty($scan['camera_id']))
                    <span class="rounded-full bg-white/80 px-2 py-0.5 text-[10px] font-semibold uppercase text-gray-500">{{ $scan['camera_id'] }}</span>
                @endif
            </div>
            <p @class([
                'mt-1 text-lg font-semibold',
                'text-emerald-800' => $registered,
                'text-amber-800' => ! $registered && ! $unreadable,
                'text-slate-600' => $unreadable,
            ])>{{ $ownerName }}</p>
            <p @class([
                'mt-0.5 text-sm font-medium',
                'text-emerald-700' => $registered,
                'text-amber-700' => ! $registered && ! $unreadable,
                'text-slate-500' => $unreadable,
            ])>{{ $scan['registration_status'] ?? ($registered ? 'Registered' : 'Plate Not Registered') }}</p>
        </div>
        @if (isset($scan['confidence']))
            <span class="shrink-0 text-sm text-gray-500">{{ round($scan['confidence'] * 100) }}%</span>
        @endif
    </div>

    @if ($registered)
        <div class="grid grid-cols-2 gap-2 border-t border-white/60 px-4 py-3 text-xs sm:grid-cols-3">
            @if (! empty($scan['owner_role']))
                <div><span class="text-gray-500">Role</span><p class="font-semibold text-gray-900">{{ $scan['owner_role'] }}</p></div>
            @endif
            @if (! empty($scan['owner_id_number']))
                <div><span class="text-gray-500">ID</span><p class="font-semibold text-gray-900">{{ $scan['owner_id_number'] }}</p></div>
            @endif
            @if (! empty($scan['vehicle_details']))
                <div><span class="text-gray-500">Vehicle</span><p class="font-semibold text-gray-900">{{ $scan['vehicle_details'] }}</p></div>
            @endif
            @if (! empty($scan['department']))
                <div><span class="text-gray-500">Department</span><p class="font-semibold text-gray-900">{{ $scan['department'] }}</p></div>
            @endif
            @if (! empty($scan['owner_email']))
                <div class="col-span-2 sm:col-span-1"><span class="text-gray-500">Email</span><p class="truncate font-semibold text-gray-900">{{ $scan['owner_email'] }}</p></div>
            @endif
            @if (! empty($scan['owner_phone']))
                <div><span class="text-gray-500">Phone</span><p class="font-semibold text-gray-900">{{ $scan['owner_phone'] }}</p></div>
            @endif
        </div>
    @endif
</div>
