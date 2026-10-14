@extends('layouts.vpaf')

@section('title', 'VPAF Dashboard')

@section('content')
    @include('partials.shell.page-header', [
        'title' => 'Dashboard',
        'subtitle' => 'Third-offense cases the GSU has endorsed for your approval',
    ])

    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
        <a href="{{ route('vpaf.endorsements') }}" class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm hover:border-blue-300">
            <p class="text-sm text-gray-500">Waiting for you</p>
            <p class="mt-1 text-3xl font-bold text-gray-900">{{ $pendingCount }}</p>
        </a>
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <p class="text-sm text-gray-500">Decided 3rd offenses</p>
            <p class="mt-1 text-3xl font-bold text-gray-900">{{ $decidedCount }}</p>
        </div>
    </div>

    <section class="rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="border-b border-gray-100 px-5 py-4">
            <h2 class="text-base font-semibold text-gray-900">New endorsements</h2>
        </div>
        <div class="divide-y divide-gray-100">
            @forelse ($pending as $endorsement)
                <a href="{{ route('vpaf.endorsements') }}" class="flex items-center justify-between gap-3 px-5 py-4 hover:bg-gray-50">
                    <div>
                        <p class="text-sm font-semibold text-gray-900">{{ $endorsement->user?->displayName() ?? 'Unknown owner' }}</p>
                        <p class="mt-0.5 text-xs text-gray-500">{{ $endorsement->plate_number ?: 'No plate' }} · {{ $endorsement->sanctionLabel() }}</p>
                    </div>
                    <span class="text-xs font-semibold text-blue-700">Review</span>
                </a>
            @empty
                <p class="px-5 py-10 text-center text-sm text-gray-500">Nothing is waiting. You will get a notification when the GSU endorses a 3rd offense.</p>
            @endforelse
        </div>
    </section>
@endsection
