@extends('layouts.portal')

@section('title', $pageTitle ?? 'Visitors')

@section('content')
    @include('partials.shell.page-header', [
        'title' => $pageTitle ?? 'Visitors',
        'subtitle' => $pageSubtitle ?? 'QR signups waiting at the gate, visitors on campus, and overdue visits',
    ])

    @php
        $viewFilter = $viewFilter ?? 'all';
        $viewLinks = [
            'all' => 'All',
            'qr' => 'QR code',
            'delivery' => 'Delivery',
            'campus' => 'On campus',
            'overdue' => 'Overdue',
        ];
    @endphp
    <div class="mb-4 flex flex-wrap gap-2">
        @foreach ($viewLinks as $key => $label)
            <a href="{{ route($routePrefix.'.visitors.active', array_filter(['view' => $key, 'search' => $search, 'status' => $statusFilter !== 'All' ? $statusFilter : null])) }}"
                @class([
                    'rounded-full px-3 py-1.5 text-sm font-semibold',
                    'bg-gray-900 text-white' => $viewFilter === $key,
                    'bg-white text-gray-700 ring-1 ring-gray-200 hover:bg-gray-50' => $viewFilter !== $key,
                ])>{{ $label }}</a>
        @endforeach
    </div>

    <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <form method="GET" class="flex flex-1 flex-col gap-3 sm:flex-row">
            <input type="hidden" name="view" value="{{ $viewFilter }}">
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
            <div class="flex flex-col gap-2 sm:items-end">
                <a href="{{ route($routePrefix.'.visitors.register') }}" class="inline-flex items-center justify-center rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700">
                    Register Visitor
                </a>
                <form method="POST" action="{{ route($routePrefix.'.visitors.delivery-return') }}" class="flex gap-2">
                    @csrf
                    <input type="text" name="plate_number" placeholder="Returning rider plate" required class="w-40 rounded-lg border border-amber-200 px-3 py-2 text-xs uppercase">
                    <button type="submit" class="rounded-lg bg-amber-600 px-3 py-2 text-xs font-semibold text-white hover:bg-amber-700">Check in</button>
                </form>
                @error('plate_number')
                    <p class="text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>
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
                                @if ($v->isDelivery())
                                    <span class="mt-1 inline-flex rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-amber-800">Delivery{{ $v->notes ? ' · '.$v->notes : '' }}</span>
                                @elseif ($v->isSelfPreRegistered())
                                    <span class="mt-1 inline-flex rounded-full bg-indigo-50 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-indigo-700">QR code</span>
                                @else
                                    <span class="mt-1 inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-600">Gate</span>
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
                            <td colspan="{{ ($canManage ?? false) ? 10 : 9 }}" class="px-6 py-16 text-center text-sm text-gray-500">No visitors in this list.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <script>
        (() => {
            const liveUrl = @json(route($routePrefix.'.visitors.live', request()->query()));
            let signature = null;
            let waitingToRefresh = false;

            const isTyping = () => {
                const field = document.activeElement;
                if (!field) {
                    return false;
                }
                return ['INPUT', 'TEXTAREA', 'SELECT'].includes(field.tagName);
            };

            const refresh = () => window.location.reload();

            const tick = async () => {
                try {
                    const response = await fetch(liveUrl, {
                        headers: { Accept: 'application/json' },
                        credentials: 'same-origin',
                        cache: 'no-store',
                    });
                    if (!response.ok) {
                        return;
                    }
                    const data = await response.json();
                    if (signature === null) {
                        signature = data.signature;
                        return;
                    }
                    if (data.signature === signature) {
                        return;
                    }
                    if (isTyping()) {
                        waitingToRefresh = true;
                        return;
                    }
                    refresh();
                } catch (error) {
                    // Keep the current list on screen if a poll fails.
                }
            };

            window.setInterval(tick, 3000);
            document.addEventListener('focusout', () => {
                if (waitingToRefresh) {
                    refresh();
                }
            });
        })();
    </script>
@endsection
