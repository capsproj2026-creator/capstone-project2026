@extends('layouts.portal')

@section('title', 'Visitor History')

@section('content')
    @include('partials.shell.page-header', [
        'title' => 'Visitor History',
        'subtitle' => 'Completed visits after exit or RFID return',
    ])

    <form method="GET" class="mb-5">
        <div class="relative">
            <i data-lucide="search" class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400"></i>
            <input type="search" name="search" value="{{ $search }}" placeholder="Search visitor, plate, purpose, office..."
                class="w-full rounded-xl border border-gray-200 bg-white py-3 pl-11 pr-4 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
        </div>
    </form>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[64rem] table-fixed border-collapse text-left text-sm">
                <thead>
                    <tr class="border-b border-gray-100 bg-gray-50 text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                        <th class="px-4 py-3">Visitor</th>
                        <th class="px-3 py-3">Plate</th>
                        <th class="px-3 py-3">Time In</th>
                        <th class="px-3 py-3">Time Out</th>
                        <th class="px-3 py-3">Duration</th>
                        <th class="px-3 py-3">Purpose</th>
                        <th class="px-3 py-3">Office</th>
                        <th class="px-4 py-3">RFID Used</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($visitors as $v)
                        <tr class="align-middle hover:bg-gray-50/60">
                            <td class="px-4 py-3 font-semibold text-gray-900">{{ $v->displayName() }}</td>
                            <td class="px-3 py-3 text-gray-800">{{ $v->plate_number }}</td>
                            <td class="px-3 py-3 text-gray-600">{{ $v->time_in ? $v->time_in->format('M j, g:i A') : '—' }}</td>
                            <td class="px-3 py-3 text-gray-600">{{ $v->time_out ? $v->time_out->format('M j, g:i A') : '—' }}</td>
                            <td class="px-3 py-3 text-gray-600">{{ $v->durationLabel() }}</td>
                            <td class="min-w-0 px-3 py-3"><p class="truncate text-gray-600" title="{{ $v->purpose }}">{{ $v->purpose }}</p></td>
                            <td class="min-w-0 px-3 py-3"><p class="truncate text-gray-600" title="{{ $v->office_to_visit }}">{{ $v->office_to_visit }}</p></td>
                            <td class="px-4 py-3 font-mono text-xs text-gray-700">{{ $v->rfid_uid ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-16 text-center text-sm text-gray-500">No completed visits yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($lastPage > 1)
            <div class="flex items-center justify-between border-t border-gray-100 px-5 py-3 text-sm text-gray-600">
                <span>Page {{ $page }} of {{ $lastPage }} · {{ $total }} total</span>
                <div class="flex gap-2">
                    @if ($page > 1)
                        <a href="{{ route($routePrefix.'.visitors.history', ['page' => $page - 1, 'search' => $search]) }}" class="rounded-lg border border-gray-200 px-3 py-1.5 font-medium hover:bg-gray-50">Prev</a>
                    @endif
                    @if ($page < $lastPage)
                        <a href="{{ route($routePrefix.'.visitors.history', ['page' => $page + 1, 'search' => $search]) }}" class="rounded-lg border border-gray-200 px-3 py-1.5 font-medium hover:bg-gray-50">Next</a>
                    @endif
                </div>
            </div>
        @endif
    </div>
@endsection
