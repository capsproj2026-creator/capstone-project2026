@extends('layouts.portal')

@section('title', $pageTitle ?? 'Active Visitors')

@section('content')
    @include('partials.shell.page-header', [
        'title' => $pageTitle ?? 'Active Visitors',
        'subtitle' => $pageSubtitle ?? 'Visitors currently registered, on campus, or overdue',
    ])

    @if (session('success'))
        <div class="mb-4 flex gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            <i data-lucide="circle-check" class="h-4 w-4 shrink-0"></i>
            {{ session('success') }}
        </div>
    @endif
    @if ($errors->any())
        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            <ul class="list-disc space-y-1 pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <form method="GET" class="flex flex-1 flex-col gap-3 sm:flex-row">
            <div class="relative flex-1">
                <i data-lucide="search" class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400"></i>
                <input type="search" name="search" value="{{ $search }}" placeholder="Search name, plate, ref code, RFID, purpose..."
                    class="w-full rounded-xl border border-gray-200 bg-white py-2.5 pl-11 pr-4 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
            </div>
            <select name="status" class="rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm shadow-sm">
                <option value="">All statuses</option>
                @foreach (['Waiting', 'Inside', 'Outside', 'Expired'] as $st)
                    <option value="{{ $st }}" @selected($statusFilter === $st)>{{ $st === 'Inside' ? 'Inside Campus' : $st }}</option>
                @endforeach
            </select>
            <button type="submit" class="rounded-xl bg-gray-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-gray-800">Filter</button>
        </form>
        @if ($canManage ?? false)
            <a href="{{ route($routePrefix.'.visitors.register') }}" class="inline-flex items-center justify-center rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700">
                Register Visitor
            </a>
        @endif
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[78rem] table-fixed border-collapse text-left text-sm">
                <thead>
                    <tr class="border-b border-gray-100 bg-gray-50 text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                        <th class="px-4 py-3">Visitor</th>
                        <th class="px-3 py-3">Ref code</th>
                        <th class="px-3 py-3">Plate</th>
                        <th class="px-3 py-3">RFID</th>
                        <th class="px-3 py-3">Purpose</th>
                        <th class="px-3 py-3">Office</th>
                        <th class="px-3 py-3">Time In</th>
                        <th class="px-3 py-3">Expected Exit</th>
                        <th class="px-3 py-3">Status</th>
                        @if ($canManage ?? false)
                            <th class="px-4 py-3 text-right">Actions</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($visitors as $v)
                        <tr class="align-middle hover:bg-gray-50/60">
                            <td class="px-4 py-3">
                                <p class="truncate font-semibold text-gray-900" title="{{ $v->displayName() }}">{{ $v->displayName() }}</p>
                                <p class="truncate text-xs text-gray-500">{{ $v->contact_number }}</p>
                                @if ($v->isSelfPreRegistered())
                                    <span class="mt-1 inline-flex rounded-full bg-indigo-50 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-indigo-700">Pre-registered online</span>
                                @endif
                            </td>
                            <td class="px-3 py-3">
                                @if ($v->confirmation_code)
                                    <span class="font-mono text-xs font-semibold text-gray-800">{{ $v->confirmation_code }}</span>
                                @else
                                    <span class="text-xs text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-3 py-3 font-medium text-gray-800">{{ $v->plate_number }}</td>
                            <td class="px-3 py-3">
                                @if ($v->rfid_uid)
                                    <span class="font-mono text-xs font-semibold text-gray-800">{{ $v->rfid_uid }}</span>
                                @else
                                    <span class="text-xs text-gray-400">No RFID</span>
                                @endif
                            </td>
                            <td class="min-w-0 px-3 py-3"><p class="truncate text-gray-600" title="{{ $v->purpose }}">{{ $v->purpose }}</p></td>
                            <td class="min-w-0 px-3 py-3"><p class="truncate text-gray-600" title="{{ $v->office_to_visit }}">{{ $v->office_to_visit }}</p></td>
                            <td class="px-3 py-3 text-gray-600">{{ $v->time_in ? $v->time_in->format('M j, g:i A') : '—' }}</td>
                            <td class="px-3 py-3 text-gray-600">{{ $v->expected_exit_at?->format('M j, g:i A') ?? '—' }}</td>
                            <td class="px-3 py-3">
                                @php
                                    $statusLabel = $v->status === 'Inside' ? 'Inside Campus' : $v->status;
                                @endphp
                                <span @class([
                                    'inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold',
                                    'bg-amber-50 text-amber-700' => $v->status === 'Waiting',
                                    'bg-emerald-50 text-emerald-700' => $v->status === 'Inside',
                                    'bg-slate-100 text-slate-700' => $v->status === 'Outside',
                                    'bg-rose-50 text-rose-700' => $v->status === 'Expired',
                                ])>{{ $statusLabel }}</span>
                            </td>
                            @if ($canManage ?? false)
                                <td class="px-4 py-3">
                                    <div class="flex flex-col items-end gap-2">
                                        @unless ($v->rfid_uid)
                                            <form method="POST" action="{{ route($routePrefix.'.visitors.assign-rfid', $v->id) }}" class="flex w-full max-w-[14rem] gap-1">
                                                @csrf
                                                <input type="text" name="rfid_uid" placeholder="RFID UID" required
                                                    class="min-w-0 flex-1 rounded-lg border border-gray-200 px-2 py-1.5 text-xs">
                                                <button type="submit" class="rounded-lg bg-blue-600 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-blue-700">Assign</button>
                                            </form>
                                        @else
                                            <form method="POST" action="{{ route($routePrefix.'.visitors.return-rfid', $v->id) }}" onsubmit="return confirm('Return this temporary RFID?')">
                                                @csrf
                                                <button type="submit" class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50">Return RFID</button>
                                            </form>
                                        @endunless
                                        @if ($v->status !== 'Completed')
                                            <form method="POST" action="{{ route($routePrefix.'.visitors.mark-exited', $v->id) }}" onsubmit="return confirm('Mark this visitor as exited? This cannot be undone.')">
                                                @csrf
                                                <button type="submit" class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-800 hover:bg-emerald-100">
                                                    Done / Mark as Exited
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ ($canManage ?? false) ? 10 : 9 }}" class="px-6 py-16 text-center text-sm text-gray-500">No active visitors found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
