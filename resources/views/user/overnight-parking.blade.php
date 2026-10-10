@extends('layouts.user')

@section('title', 'Overnight Parking')

@section('content')
    @include('partials.shell.page-header', [
        'title' => 'Overnight Parking',
        'subtitle' => 'Parking on campus from 10:00 p.m. to 5:00 a.m. needs GSU approval',
    ])

    @if (session('success'))
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>
    @endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <form method="POST" action="{{ route('user.overnight-parking.store') }}" class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm lg:col-span-1">
            @csrf
            <h2 class="text-base font-semibold text-gray-900">Request overnight parking</h2>
            <p class="mt-1 text-xs leading-relaxed text-gray-500">
                Vehicles left inside campus overnight without approval are flagged to Security. Submit your request before 10:00 p.m.
            </p>

            <div class="mt-4 space-y-3">
                <div>
                    <label class="block text-xs font-medium text-gray-700">Vehicle</label>
                    <p class="mt-1 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700"><code>{{ $plate !== '' ? $plate : 'No plate on file' }}</code></p>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="first_night" class="block text-xs font-medium text-gray-700">First night</label>
                        <input id="first_night" type="date" name="first_night" min="{{ $tonight }}" value="{{ old('first_night', $tonight) }}" required
                               class="mt-1 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm shadow-sm">
                    </div>
                    <div>
                        <label for="last_night" class="block text-xs font-medium text-gray-700">Last night</label>
                        <input id="last_night" type="date" name="last_night" min="{{ $tonight }}" value="{{ old('last_night') }}"
                               class="mt-1 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm shadow-sm">
                    </div>
                </div>
                <p class="text-[11px] text-gray-500">Leave "Last night" empty for a single night. Up to {{ $maxNights }} nights per request.</p>
                <div>
                    <label for="area_id" class="block text-xs font-medium text-gray-700">Parking area</label>
                    <select id="area_id" name="area_id" class="mt-1 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm shadow-sm">
                        <option value="">Not sure yet</option>
                        @foreach ($areas as $area)
                            <option value="{{ $area->id }}" @selected((string) old('area_id') === (string) $area->id)>{{ $area->area_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="reason" class="block text-xs font-medium text-gray-700">Reason</label>
                    <textarea id="reason" name="reason" rows="3" maxlength="500" required
                              placeholder="e.g. Out-of-town official travel; vehicle stays on campus"
                              class="mt-1 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm shadow-sm">{{ old('reason') }}</textarea>
                </div>
                <button type="submit" @disabled($plate === '')
                        class="inline-flex w-full items-center justify-center gap-1.5 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                    <i data-lucide="send" class="h-4 w-4"></i>
                    Send to GSU
                </button>
            </div>
        </form>

        <section class="min-w-0 rounded-xl border border-gray-200 bg-white shadow-sm lg:col-span-2">
            <div class="border-b border-gray-100 px-5 py-4">
                <h2 class="text-base font-semibold text-gray-900">My requests</h2>
            </div>
            <div class="divide-y divide-gray-100">
                @forelse ($requests as $req)
                    @php
                        $tone = match ($req->status) {
                            \App\Models\OvernightParkingRequest::STATUS_APPROVED => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                            \App\Models\OvernightParkingRequest::STATUS_DENIED => 'bg-red-50 text-red-700 border-red-200',
                            \App\Models\OvernightParkingRequest::STATUS_PENDING => 'bg-amber-50 text-amber-800 border-amber-200',
                            default => 'bg-gray-50 text-gray-700 border-gray-200',
                        };
                        $canCancel = in_array($req->status, [\App\Models\OvernightParkingRequest::STATUS_PENDING, \App\Models\OvernightParkingRequest::STATUS_APPROVED], true)
                            && (string) $req->last_night >= $tonight;
                    @endphp
                    <div class="flex flex-col gap-2 px-5 py-4 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="text-sm font-semibold text-gray-900">{{ $req->nightsLabel() }}</p>
                                <span class="rounded-full border px-2 py-0.5 text-xs font-medium {{ $tone }}">{{ $req->status }}</span>
                            </div>
                            <p class="mt-1 text-xs text-gray-500">
                                <code class="text-gray-700">{{ $req->plate_number }}</code>
                                @if ($req->area_name) · {{ $req->area_name }} @endif
                                · Sent {{ ph_datetime($req->created_at, 'M j, Y g:i A') }}
                            </p>
                            <p class="mt-1 text-sm text-gray-600">{{ $req->reason }}</p>
                            @if ($req->review_remarks)
                                <p class="mt-1 text-xs text-gray-500">GSU remarks: {{ $req->review_remarks }}</p>
                            @endif
                        </div>
                        @if ($canCancel)
                            <form method="POST" action="{{ route('user.overnight-parking.cancel', $req->id) }}" onsubmit="return confirm('Cancel this request?')">
                                @csrf
                                <button type="submit" class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 shadow-sm hover:bg-gray-50">Cancel</button>
                            </form>
                        @endif
                    </div>
                @empty
                    <p class="px-5 py-12 text-center text-sm text-gray-500">No overnight parking requests yet.</p>
                @endforelse
            </div>
        </section>
    </div>
@endsection
