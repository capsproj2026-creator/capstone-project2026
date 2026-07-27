@extends('layouts.portal')

@section('title', 'User Management')

@section('content')
    @include('partials.shell.page-header', [
        'title' => 'User Management',
        'subtitle' => 'Search, filter, and manage campus portal users',
    ])

    <form method="GET" class="mb-6 grid grid-cols-1 gap-3 rounded-xl border border-gray-200 bg-white p-4 sm:grid-cols-2 lg:grid-cols-6">
        <input type="text" name="search" value="{{ $search }}" placeholder="Search name, ID, plate, email..."
            class="rounded-lg border border-gray-300 px-3 py-2 text-sm lg:col-span-2">
        <select name="type" class="rounded-lg border border-gray-300 px-3 py-2 text-sm">
            @foreach (['All', 'Student', 'Staff', 'Guard'] as $type)
                <option value="{{ $type }}" @selected($typeFilter === $type)>{{ $type === 'All' ? 'All roles' : $type }}</option>
            @endforeach
        </select>
        <select name="status" class="rounded-lg border border-gray-300 px-3 py-2 text-sm">
            @foreach (['All', 'Granted', 'Pending', 'Denied', 'Locked'] as $status)
                <option value="{{ $status }}" @selected($statusFilter === $status)>{{ $status === 'All' ? 'All statuses' : $status }}</option>
            @endforeach
        </select>
        <select name="sort" class="rounded-lg border border-gray-300 px-3 py-2 text-sm">
            <option value="fullname" @selected($sort === 'fullname')>Sort: Name</option>
            <option value="id_number" @selected($sort === 'id_number')>Sort: ID</option>
            <option value="strike_count" @selected($sort === 'strike_count')>Sort: Strikes</option>
            <option value="status" @selected($sort === 'status')>Sort: Status</option>
        </select>
        <div class="flex gap-2">
            <select name="direction" class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm">
                <option value="asc" @selected($direction === 'asc')>Asc</option>
                <option value="desc" @selected($direction === 'desc')>Desc</option>
            </select>
            <button type="submit" class="rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-800">Apply</button>
        </div>
    </form>

    <div class="mb-4 flex flex-wrap gap-3 text-sm text-gray-500">
        <span>Students: <strong class="text-gray-900">{{ $studentCount }}</strong></span>
        <span>Staff: <strong class="text-gray-900">{{ $staffCount }}</strong></span>
        <span>Guards: <strong class="text-gray-900">{{ $guardCount }}</strong></span>
        <span>Total: <strong class="text-gray-900">{{ $totalUsers }}</strong></span>
        <span class="text-gray-400">· Showing {{ $users->total() }} match(es)</span>
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[640px] text-left text-sm">
                <thead class="border-b border-gray-200 bg-gray-50 text-gray-500">
                    <tr>
                        <th class="px-6 py-3 font-medium">User</th>
                        <th class="px-6 py-3 font-medium">Role</th>
                        <th class="px-6 py-3 font-medium">ID</th>
                        <th class="px-6 py-3 font-medium">Strikes</th>
                        <th class="px-6 py-3 font-medium">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($users as $u)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4">
                                <a href="{{ route('admin.users.show', ['id' => $u->id, 'from' => 'users']) }}" class="flex items-center gap-3 font-medium text-gray-900 hover:text-blue-600">
                                    <x-portal.avatar :user="$u" size="sm" />
                                    {{ $u->fullname }}
                                </a>
                            </td>
                            <td class="px-6 py-4 text-gray-600">{{ $u->role?->role_name }}</td>
                            <td class="px-6 py-4 text-gray-600">{{ $u->id_number }}</td>
                            <td class="px-6 py-4">{{ $u->strike_count }}/{{ \App\Models\User::MAX_STRIKES }}</td>
                            <td class="px-6 py-4">
                                @if ($u->isLocked())
                                    <span class="font-semibold text-red-600">Locked</span>
                                @else
                                    <span class="text-gray-600">{{ $u->status }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                                No users match your filters. Try clearing search or changing filters.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($users->hasPages())
            <div class="border-t border-gray-200 px-6 py-4">
                {{ $users->links() }}
            </div>
        @endif
    </div>
@endsection
