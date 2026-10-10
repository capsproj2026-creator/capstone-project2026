@extends($layout)

@section('title', 'Overnight Parking')

@section('content')
<div class="min-w-0 max-w-full">
    @include('partials.shell.page-header', [
        'title' => 'Overnight Parking',
        'subtitle' => 'Parking from 10:00 p.m. to 5:00 a.m. is prohibited unless the GSU approves it',
    ])

    @if (session('success'))
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
    @endif

    {{-- Overnight check --}}
    <section class="mb-6 rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="flex flex-col gap-1 border-b border-gray-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-base font-semibold text-gray-900">Overnight check — night of {{ ph_date($check['night'], 'M j, Y') }}</h2>
                <p class="mt-0.5 text-xs text-gray-500">
                    Registered vehicles whose last gate scan was an entry.
                    @if ($check['in_window'])
                        <strong class="text-red-700">The overnight window is in effect now.</strong>
                    @else
                        The overnight window starts at 10:00 p.m.
                    @endif
                </p>
            </div>
            <div class="flex gap-2 text-xs">
                <span class="rounded-full border px-2.5 py-1 font-semibold {{ $check['unapproved']->isNotEmpty() ? 'bg-red-50 text-red-700 border-red-200' : 'bg-gray-50 text-gray-700 border-gray-200' }}">{{ $check['unapproved']->count() }} without approval</span>
                <span class="rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 font-semibold text-emerald-700">{{ $check['approved']->count() }} approved</span>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-gray-200 bg-gray-50 text-gray-500">
                    <tr>
                        <th class="px-5 py-3 font-medium">Owner</th>
                        <th class="px-5 py-3 font-medium">Plate</th>
                        <th class="px-5 py-3 font-medium">Entered</th>
                        <th class="px-5 py-3 font-medium">Overnight status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($check['unapproved']->concat($check['approved']) as $row)
                        <tr>
                            <td class="px-5 py-3">
                                <p class="font-medium text-gray-900">{{ $row['user']->displayName() }}</p>
                                <p class="text-xs text-gray-500">{{ $row['user']->displayRoleLabel() }}</p>
                            </td>
                            <td class="px-5 py-3"><code class="text-gray-700">{{ $row['user']->plate_number ?: '—' }}</code></td>
                            <td class="px-5 py-3 text-gray-600">{{ $row['entered_at'] ? ph_datetime($row['entered_at'], 'M j, g:i A') : '—' }} <span class="text-xs text-gray-400">{{ $row['gate'] }}</span></td>
                            <td class="px-5 py-3">
                                @if ($row['request'])
                                    <span class="rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">Approved</span>
                                    @if ($row['request']->area_name)
                                        <span class="ml-1 text-xs text-gray-500">{{ $row['request']->area_name }}</span>
                                    @endif
                                @else
                                    <span class="rounded-full border border-red-200 bg-red-50 px-2 py-0.5 text-xs font-medium text-red-700">No approval</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-5 py-10 text-center text-sm text-gray-500">No registered vehicles are inside campus right now.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @if ($isAdmin)
        {{-- GSU approval queue --}}
        <section class="rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="flex flex-col gap-3 border-b border-gray-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-base font-semibold text-gray-900">Employee requests</h2>
                    <p class="mt-0.5 text-xs text-gray-500">Approve or deny on behalf of the GSU.</p>
                </div>
                <div class="flex gap-2 text-sm">
                    <a href="{{ route('admin.overnight-parking', ['tab' => 'pending']) }}"
                       class="rounded-lg border px-3 py-1.5 font-medium {{ $tab === 'pending' ? 'border-blue-300 bg-blue-50 text-blue-700' : 'border-gray-200 bg-white text-gray-700 hover:bg-gray-50' }}">
                        Pending ({{ $pendingCount }})
                    </a>
                    <a href="{{ route('admin.overnight-parking', ['tab' => 'decided']) }}"
                       class="rounded-lg border px-3 py-1.5 font-medium {{ $tab === 'decided' ? 'border-blue-300 bg-blue-50 text-blue-700' : 'border-gray-200 bg-white text-gray-700 hover:bg-gray-50' }}">
                        Decided
                    </a>
                </div>
            </div>

            <div class="divide-y divide-gray-100">
                @forelse ($requests as $req)
                    <div class="px-5 py-4">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="text-sm font-semibold text-gray-900">{{ $req->user?->displayName() ?? 'Unknown employee' }}</p>
                            <code class="text-xs text-gray-700">{{ $req->plate_number }}</code>
                            @if ($req->status !== \App\Models\OvernightParkingRequest::STATUS_PENDING)
                                <span class="rounded-full border px-2 py-0.5 text-xs font-medium {{ $req->status === \App\Models\OvernightParkingRequest::STATUS_APPROVED ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-gray-50 text-gray-700 border-gray-200' }}">{{ $req->status }}</span>
                            @endif
                        </div>
                        <p class="mt-1 text-xs text-gray-500">
                            {{ $req->nightsLabel() }}
                            @if ($req->area_name) · {{ $req->area_name }} @endif
                            · Sent {{ ph_datetime($req->created_at, 'M j, Y g:i A') }}
                        </p>
                        <p class="mt-1 text-sm text-gray-600">{{ $req->reason }}</p>
                        @if ($req->review_remarks)
                            <p class="mt-1 text-xs text-gray-500">Remarks: {{ $req->review_remarks }}</p>
                        @endif

                        @if ($req->status === \App\Models\OvernightParkingRequest::STATUS_PENDING)
                            <form method="POST" action="{{ route('admin.overnight-parking.decide', $req->id) }}" class="mt-3 flex flex-col gap-2 sm:flex-row sm:items-center">
                                @csrf
                                <input type="text" name="remarks" maxlength="500" placeholder="Remarks (optional)"
                                       class="w-full rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-sm shadow-sm sm:flex-1">
                                <button type="submit" name="decision" value="approve"
                                        class="inline-flex items-center justify-center gap-1.5 rounded-lg bg-emerald-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-emerald-700">
                                    <i data-lucide="check" class="h-4 w-4"></i> Approve
                                </button>
                                <button type="submit" name="decision" value="deny"
                                        class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50">
                                    <i data-lucide="x" class="h-4 w-4"></i> Deny
                                </button>
                            </form>
                        @endif
                    </div>
                @empty
                    <p class="px-5 py-12 text-center text-sm text-gray-500">{{ $tab === 'pending' ? 'No pending requests.' : 'No decided requests yet.' }}</p>
                @endforelse
            </div>

            @if ($requests && $requests->hasPages())
                <div class="border-t border-gray-100 px-4 py-3">{{ $requests->links() }}</div>
            @endif
        </section>
    @endif
</div>
@endsection
