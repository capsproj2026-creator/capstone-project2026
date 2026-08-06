@extends('layouts.portal')

@section('title', 'User Management')

@section('content')
    @include('partials.shell.page-header', [
        'title' => 'User Management',
        'subtitle' => 'Search, filter, and manage campus portal users',
    ])

    @php
        $statCards = [
            ['label' => 'Students', 'value' => $studentCount, 'icon' => 'graduation-cap', 'text' => 'text-blue-600', 'iconBg' => 'bg-blue-50 text-blue-600'],
            ['label' => 'Staff', 'value' => $staffCount, 'icon' => 'briefcase', 'text' => 'text-violet-600', 'iconBg' => 'bg-violet-50 text-violet-600'],
            ['label' => 'Guards', 'value' => $guardCount, 'icon' => 'shield', 'text' => 'text-slate-700', 'iconBg' => 'bg-slate-100 text-slate-700'],
            ['label' => 'Total users', 'value' => $totalUsers, 'icon' => 'users', 'text' => 'text-gray-900', 'iconBg' => 'bg-gray-100 text-gray-700'],
        ];
    @endphp

    <div class="mb-6 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($statCards as $card)
            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <div>
                    <p class="text-sm text-gray-500">{{ $card['label'] }}</p>
                    <p class="mt-1 text-2xl font-bold tracking-tight {{ $card['text'] }}">{{ number_format($card['value']) }}</p>
                </div>
                <div class="flex h-11 w-11 items-center justify-center rounded-xl {{ $card['iconBg'] }}">
                    <i data-lucide="{{ $card['icon'] }}" class="h-5 w-5"></i>
                </div>
            </div>
        @endforeach
    </div>

    <form method="GET" class="mb-5 rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-6">
            <div class="relative lg:col-span-2">
                <i data-lucide="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400"></i>
                <input type="text" name="search" value="{{ $search }}" placeholder="Search name, ID, plate, email..."
                    class="w-full rounded-lg border border-gray-200 py-2.5 pl-10 pr-3 text-sm text-gray-900 placeholder:text-gray-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
            </div>
            <select name="type" class="rounded-lg border border-gray-200 px-3 py-2.5 text-sm text-gray-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                @foreach (['All', 'Student', 'Staff', 'Guard'] as $type)
                    <option value="{{ $type }}" @selected($typeFilter === $type)>{{ $type === 'All' ? 'All roles' : $type }}</option>
                @endforeach
            </select>
            <select name="status" class="rounded-lg border border-gray-200 px-3 py-2.5 text-sm text-gray-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                @foreach (['All', 'Granted', 'Pending', 'Denied', 'Locked'] as $status)
                    <option value="{{ $status }}" @selected($statusFilter === $status)>{{ $status === 'All' ? 'All statuses' : $status }}</option>
                @endforeach
            </select>
            <select name="sort" class="rounded-lg border border-gray-200 px-3 py-2.5 text-sm text-gray-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                <option value="fullname" @selected($sort === 'fullname')>Sort: Name</option>
                <option value="id_number" @selected($sort === 'id_number')>Sort: ID</option>
                <option value="strike_count" @selected($sort === 'strike_count')>Sort: Strikes</option>
                <option value="status" @selected($sort === 'status')>Sort: Status</option>
            </select>
            <div class="flex gap-2">
                <select name="direction" class="flex-1 rounded-lg border border-gray-200 px-3 py-2.5 text-sm text-gray-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                    <option value="asc" @selected($direction === 'asc')>Asc</option>
                    <option value="desc" @selected($direction === 'desc')>Desc</option>
                </select>
                <button type="submit" class="inline-flex items-center justify-center gap-1.5 rounded-lg bg-gray-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-gray-800">
                    <i data-lucide="filter" class="h-3.5 w-3.5"></i>
                    Apply
                </button>
            </div>
        </div>
        <p class="mt-3 text-xs text-gray-400">Showing <span class="font-medium text-gray-600">{{ $users->total() }}</span> match(es)</p>
    </form>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="border-b border-gray-100 px-5 py-4 sm:px-6">
            <h2 class="text-base font-semibold text-gray-900">Campus users</h2>
            <p class="mt-0.5 text-sm text-gray-500">Click a name or View profile to open details</p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[60rem] table-fixed border-collapse text-left text-sm">
                <colgroup>
                    <col class="w-[15rem]">
                    <col class="w-[7rem]">
                    <col class="w-[9rem]">
                    <col class="w-[15rem]">
                    <col class="w-[6rem]">
                    <col class="w-[7.5rem]">
                    <col class="w-[8rem]">
                </colgroup>
                <thead>
                    <tr class="border-b border-gray-100 bg-gray-50 text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                        <th scope="col" class="px-4 py-3 sm:px-5">User</th>
                        <th scope="col" class="px-3 py-3">Role</th>
                        <th scope="col" class="px-3 py-3">ID Number</th>
                        <th scope="col" class="px-3 py-3">Email</th>
                        <th scope="col" class="px-3 py-3">Strikes</th>
                        <th scope="col" class="px-3 py-3">Status</th>
                        <th scope="col" class="px-4 py-3 text-right sm:px-5">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($users as $u)
                        @php
                            $roleLabel = $u->role?->role_name ?: $u->roleName();
                            $isStudent = strcasecmp((string) $roleLabel, 'Student') === 0;
                            $isStaff = strcasecmp((string) $roleLabel, 'Staff') === 0;
                            $isGuard = strcasecmp((string) $roleLabel, 'Guard') === 0;
                            $email = $u->email ?: '—';
                            $idNumber = $u->id_number ?: '—';
                            $isLocked = $u->isLocked();
                            $profileUrl = route('admin.users.show', ['id' => $u->id, 'from' => 'users']);
                        @endphp
                        <tr class="align-middle hover:bg-gray-50/60">
                            <td class="px-4 py-3.5 sm:px-5">
                                <a href="{{ $profileUrl }}" class="flex min-w-0 items-center gap-3 font-medium text-gray-900 hover:text-blue-600">
                                    <x-portal.avatar :user="$u" size="sm" class="shrink-0 !ring-0" />
                                    <span class="truncate" title="{{ $u->fullname }}">{{ $u->fullname }}</span>
                                </a>
                            </td>
                            <td class="px-3 py-3.5">
                                <span @class([
                                    'inline-flex rounded-md px-2 py-0.5 text-xs font-medium',
                                    'bg-blue-50 text-blue-700' => $isStudent,
                                    'bg-violet-50 text-violet-700' => $isStaff,
                                    'bg-slate-100 text-slate-700' => $isGuard,
                                    'bg-gray-100 text-gray-600' => ! $isStudent && ! $isStaff && ! $isGuard,
                                ])>{{ $roleLabel }}</span>
                            </td>
                            <td class="px-3 py-3.5">
                                <p class="truncate font-medium text-gray-800" title="{{ $idNumber }}">{{ $idNumber }}</p>
                            </td>
                            <td class="min-w-0 px-3 py-3.5">
                                <p class="truncate text-gray-600" title="{{ $email }}">{{ $email }}</p>
                            </td>
                            <td class="px-3 py-3.5">
                                @php $strikes = (int) $u->strike_count; @endphp
                                <span @class([
                                    'inline-flex rounded-md px-2 py-0.5 text-xs font-semibold tabular-nums',
                                    'bg-red-50 text-red-700' => $strikes >= \App\Models\User::MAX_STRIKES || $isLocked,
                                    'bg-amber-50 text-amber-700' => ! $isLocked && $strikes > 0 && $strikes < \App\Models\User::MAX_STRIKES,
                                    'bg-gray-50 text-gray-700' => ! $isLocked && $strikes === 0,
                                ])>{{ $strikes }}/{{ \App\Models\User::MAX_STRIKES }}</span>
                            </td>
                            <td class="px-3 py-3.5">
                                @if ($isLocked)
                                    <span class="inline-flex rounded-full bg-red-50 px-2.5 py-0.5 text-xs font-semibold text-red-700">Locked</span>
                                @elseif ($u->status === 'Granted')
                                    <span class="inline-flex rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700">Granted</span>
                                @elseif ($u->status === 'Pending')
                                    <span class="inline-flex rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-semibold text-amber-700">Pending</span>
                                @elseif (in_array($u->status, ['Denied', 'Declined'], true))
                                    <span class="inline-flex rounded-full bg-rose-50 px-2.5 py-0.5 text-xs font-semibold text-rose-700">{{ $u->status }}</span>
                                @else
                                    <span class="inline-flex rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-semibold text-gray-700">{{ $u->status }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3.5 text-right sm:px-5">
                                <a href="{{ $profileUrl }}"
                                   class="inline-flex items-center justify-center rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50">
                                    View profile
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-16 text-center">
                                <p class="font-medium text-gray-700">No users match your filters</p>
                                <p class="mt-1 text-sm text-gray-500">Try clearing search or changing filters.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($users->hasPages())
            <div class="border-t border-gray-100 px-5 py-4 sm:px-6">
                {{ $users->links() }}
            </div>
        @endif
    </div>
@endsection
