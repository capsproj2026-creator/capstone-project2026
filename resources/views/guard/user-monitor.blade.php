@extends('layouts.guard')

@section('title', 'User Monitor')

@section('content')
    @include('partials.shell.page-header', [
        'title' => 'User Monitor',
        'subtitle' => 'Campus students and staff with gate access status',
    ])

    <form method="GET" class="mb-6 flex flex-wrap gap-3">
        <input type="search" name="q" value="{{ $search }}" placeholder="Search name, plate, or ID..." class="min-w-[200px] flex-1 rounded-lg border border-gray-300 px-4 py-2 text-sm">
        <select name="access" class="rounded-lg border border-gray-300 px-4 py-2 text-sm">
            <option value="all" @selected($accessFilter === 'all')>All access</option>
            <option value="Granted" @selected($accessFilter === 'Granted')>Granted</option>
            <option value="Denied" @selected($accessFilter === 'Denied')>Denied</option>
            <option value="Pending" @selected($accessFilter === 'Pending')>Pending</option>
        </select>
        <button type="submit" class="rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700">Filter</button>
    </form>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-gray-200 bg-gray-50 text-gray-500">
                <tr>
                    <th class="px-6 py-3 font-medium">Name</th>
                    <th class="px-6 py-3 font-medium">ID</th>
                    <th class="px-6 py-3 font-medium">Plate</th>
                    <th class="px-6 py-3 font-medium">Role</th>
                    <th class="px-6 py-3 font-medium">Gate Access</th>
                    <th class="px-6 py-3 font-medium">Strikes</th>
                    <th class="px-6 py-3 font-medium">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($users as $campusUser)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 font-medium text-gray-900">{{ $campusUser->fullname }}</td>
                        <td class="px-6 py-4 text-gray-600">{{ $campusUser->id_number }}</td>
                        <td class="px-6 py-4"><code>{{ $campusUser->plate_number ?? '—' }}</code></td>
                        <td class="px-6 py-4">{{ $campusUser->displayRoleLabel() }}</td>
                        <td class="px-6 py-4">
                            @php
                                $gateAccess = $campusUser->Gate_access ?: '';
                                if ($campusUser->status === \App\Models\User::STATUS_DENIED || $gateAccess === 'Denied') {
                                    $gateLabel = 'Denied';
                                    $gateTone = 'bg-red-100 text-red-700';
                                } elseif (in_array($gateAccess, ['Granted', 'Access'], true)) {
                                    $gateLabel = 'Granted';
                                    $gateTone = 'bg-green-100 text-green-700';
                                } else {
                                    $gateLabel = 'Pending';
                                    $gateTone = 'bg-amber-100 text-amber-700';
                                }
                            @endphp
                            <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $gateTone }}">{{ $gateLabel }}</span>
                        </td>
                        <td class="px-6 py-4">{{ $campusUser->strike_count ?? 0 }}/{{ \App\Models\User::MAX_STRIKES }}</td>
                        <td class="px-6 py-4">{{ $campusUser->status }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-6 py-10 text-center text-gray-500">No users found.</td></tr>
                @endforelse
            </tbody>
        </table>
        @if ($users->hasPages())
            <div class="border-t border-gray-200 px-6 py-4">{{ $users->links() }}</div>
        @endif
    </div>
@endsection
