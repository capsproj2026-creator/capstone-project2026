@extends('layouts.user')

@section('title', 'Entry & Exit History')

@section('content')
    @include('partials.shell.page-header', [
        'title' => 'Entry / Exit History',
        'subtitle' => 'Your campus gate access records',
    ])

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-gray-200 bg-gray-50 text-gray-500">
                <tr>
                    <th class="px-6 py-3 font-medium">Log #</th>
                    <th class="px-6 py-3 font-medium">Action</th>
                    <th class="px-6 py-3 font-medium">Date</th>
                    <th class="px-6 py-3 font-medium">Time</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($logs as $log)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 text-gray-600">#{{ $log->daily_log_id ?? $log->id }}</td>
                        <td class="px-6 py-4">
                            <span @class([
                                'inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium',
                                'bg-green-100 text-green-700' => $log->action === 'Entry',
                                'bg-blue-100 text-blue-700' => $log->action === 'Exit',
                            ])>{{ $log->action }}</span>
                        </td>
                        <td class="px-6 py-4">{{ ph_date($log->timestamp) }}</td>
                        <td class="px-6 py-4">{{ ph_time($log->timestamp) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-6 py-10 text-center text-gray-500">No entry or exit records yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        @if ($logs->hasPages())
            <div class="border-t border-gray-200 px-6 py-4">{{ $logs->links() }}</div>
        @endif
    </div>
@endsection
