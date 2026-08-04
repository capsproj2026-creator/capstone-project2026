@extends('layouts.user')

@section('title', 'Entry & Exit History')

@section('content')
    @include('partials.shell.page-header', [
        'title' => 'Entry / Exit History',
        'subtitle' => 'Your campus gate access records',
    ])

    <form method="GET" class="mb-6 flex flex-col gap-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm sm:flex-row sm:items-end">
        <div class="min-w-0 flex-1">
            <label for="action" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500">Direction</label>
            <select id="action" name="action" class="w-full cursor-pointer rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm text-gray-800 focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                <option value="all" @selected($actionFilter === 'all')>All</option>
                <option value="Entry" @selected($actionFilter === 'Entry')>Entry</option>
                <option value="Exit" @selected($actionFilter === 'Exit')>Exit</option>
            </select>
        </div>
        <div class="min-w-0 flex-1">
            <label for="date_from" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500">From</label>
            <input type="date" id="date_from" name="date_from" value="{{ $dateFrom }}" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20">
        </div>
        <div class="min-w-0 flex-1">
            <label for="date_to" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500">To</label>
            <input type="date" id="date_to" name="date_to" value="{{ $dateTo }}" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20">
        </div>
        <div class="flex gap-2">
            <button type="submit" class="cursor-pointer rounded-lg bg-gray-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-black">Filter</button>
            <a href="{{ route('user.entry-exit') }}" class="cursor-pointer rounded-lg border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50">Clear</a>
        </div>
    </form>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="border-b border-gray-100 px-5 py-4 sm:px-6">
            <h2 class="text-base font-semibold text-gray-900">Access Records ({{ number_format($logs->total()) }})</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-[640px] w-full text-left text-sm">
                <thead class="border-b border-gray-100 bg-gray-50/80 text-xs font-medium uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-5 py-3 font-medium sm:px-6">Log #</th>
                        <th class="px-5 py-3 font-medium sm:px-6">Direction</th>
                        <th class="px-5 py-3 font-medium sm:px-6">Gate</th>
                        <th class="px-5 py-3 font-medium sm:px-6">Result</th>
                        <th class="px-5 py-3 font-medium sm:px-6">Date</th>
                        <th class="px-5 py-3 font-medium sm:px-6">Time</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($logs as $log)
                        @php
                            $granted = $log->accessGranted();
                            $isEntry = ($log->action ?? '') === 'Entry';
                        @endphp
                        <tr class="hover:bg-gray-50/80">
                            <td class="whitespace-nowrap px-5 py-4 text-gray-600 sm:px-6">#{{ $log->daily_log_id ?? $log->id }}</td>
                            <td class="whitespace-nowrap px-5 py-4 sm:px-6">
                                @if ($isEntry)
                                    <span class="inline-flex items-center gap-1.5 font-medium text-blue-600">
                                        <i data-lucide="log-in" class="h-4 w-4"></i> Entry
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 font-medium text-purple-600">
                                        <i data-lucide="log-out" class="h-4 w-4"></i> {{ $log->action ?: '—' }}
                                    </span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-5 py-4 text-gray-700 sm:px-6">{{ $log->displayGate() }}</td>
                            <td class="px-5 py-4 sm:px-6">
                                @if ($granted)
                                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-500 px-2.5 py-1 text-xs font-semibold text-white">Granted</span>
                                @else
                                    <span class="inline-flex items-center gap-1 rounded-full bg-red-500 px-2.5 py-1 text-xs font-semibold text-white">Denied</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-5 py-4 text-gray-700 sm:px-6">{{ ph_date($log->timestamp) }}</td>
                            <td class="whitespace-nowrap px-5 py-4 text-gray-700 sm:px-6">{{ ph_time($log->timestamp) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-sm text-gray-500">
                                No entry or exit records found for this filter.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($logs->hasPages())
            <div class="border-t border-gray-100 px-5 py-4 sm:px-6">{{ $logs->links() }}</div>
        @endif
    </div>
@endsection
