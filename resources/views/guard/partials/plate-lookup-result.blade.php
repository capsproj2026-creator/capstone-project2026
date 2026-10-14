@php
    $registered = ! empty($result['registered']);
    $border = $registered ? 'border-emerald-300' : 'border-amber-300';
    $bg = $registered ? 'bg-emerald-50' : 'bg-amber-50';
    $title = $registered
        ? ($result['owner_name'] ?? 'Registered Vehicle')
        : 'Unknown Vehicle';
    $status = $result['registration_status'] ?? ($registered ? 'Registered' : 'Plate Not Registered');
@endphp

<div class="overflow-hidden rounded-2xl border-2 {{ $border }} {{ $bg }} shadow-sm">
    <div class="px-6 py-8 text-center">
        <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Plate</p>
        <p class="mt-1 text-3xl font-bold tracking-wide text-gray-900">{{ $result['plate'] ?? '—' }}</p>
        <h2 @class([
            'mt-4 text-2xl font-bold',
            'text-emerald-800' => $registered,
            'text-amber-800' => ! $registered,
        ])>{{ $title }}</h2>
        <p @class([
            'mt-2 text-sm font-medium',
            'text-emerald-700' => $registered,
            'text-amber-700' => ! $registered,
        ])>{{ $status }}</p>
    </div>

    @if ($registered)
        <div class="grid grid-cols-1 gap-3 border-t border-white/60 px-5 py-5 sm:grid-cols-2">
            @if (! empty($result['role']))
                <div class="rounded-lg bg-white/70 px-4 py-3">
                    <p class="text-xs font-medium text-gray-500">Role</p>
                    <p class="mt-1 text-sm font-semibold text-gray-900">{{ $result['role'] }}</p>
                </div>
            @endif
            @if (! empty($result['id_number']))
                <div class="rounded-lg bg-white/70 px-4 py-3">
                    <p class="text-xs font-medium text-gray-500">ID Number</p>
                    <p class="mt-1 text-sm font-semibold text-gray-900">{{ $result['id_number'] }}</p>
                </div>
            @endif
            @if (! empty($result['vehicle_details']))
                <div class="rounded-lg bg-white/70 px-4 py-3">
                    <p class="text-xs font-medium text-gray-500">Vehicle</p>
                    <p class="mt-1 text-sm font-semibold text-gray-900">{{ $result['vehicle_details'] }}</p>
                </div>
            @endif
            @if (! empty($result['department']))
                <div class="rounded-lg bg-white/70 px-4 py-3">
                    <p class="text-xs font-medium text-gray-500">Department</p>
                    <p class="mt-1 text-sm font-semibold text-gray-900">{{ $result['department'] }}</p>
                </div>
            @endif
            @if (! empty($result['purpose']))
                <div class="rounded-lg bg-white/70 px-4 py-3">
                    <p class="text-xs font-medium text-gray-500">Purpose</p>
                    <p class="mt-1 text-sm font-semibold text-gray-900">{{ $result['purpose'] }}</p>
                </div>
            @endif
            <div class="rounded-lg bg-white/70 px-4 py-3 sm:col-span-2">
                <p class="text-xs font-medium text-gray-500">Overnight stay</p>
                @if (! empty($result['overnight_valid']))
                    <p class="mt-1 text-sm font-semibold text-emerald-800">Allowed until {{ $result['overnight_window'] ?? 'the approved window ends' }}</p>
                @elseif (($result['overnight_status'] ?? '') === 'pending')
                    <p class="mt-1 text-sm font-semibold text-amber-800">Proof is uploaded, but GSU has not approved it yet.</p>
                @else
                    <p class="mt-1 text-sm font-semibold text-rose-800">Not allowed. Overnight parking needs an approved permit and proof.</p>
                @endif
                @if (! empty($result['overnight_reason']))
                    <p class="mt-1 text-xs text-gray-600">{{ $result['overnight_reason'] }}</p>
                @endif
                @if (! empty($result['overnight_permit_id']))
                    <a href="{{ route('guard.overnight.proof', $result['overnight_permit_id']) }}" class="mt-2 inline-block text-xs font-semibold text-blue-700 hover:underline">Open proof</a>
                @endif
            </div>
        </div>
    @endif
</div>
