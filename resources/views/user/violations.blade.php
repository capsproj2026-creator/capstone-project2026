@extends('layouts.user')

@section('title', 'My Violations')

@section('content')
    @include('partials.shell.page-header', [
        'title' => 'My Violations',
        'subtitle' => 'Citations recorded against your registered vehicle',
    ])

    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <p class="text-sm text-gray-500">Strike Count</p>
            <p class="mt-1 text-3xl font-bold text-gray-900">{{ $strikeCount }} / {{ $maxStrikes }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <p class="text-sm text-gray-500">Total Citations</p>
            <p class="mt-1 text-3xl font-bold text-gray-900">{{ $logs->total() }}</p>
        </div>
    </div>

    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-gray-200 bg-gray-50 text-gray-500">
                    <tr>
                        <th class="px-6 py-3 font-medium">Type</th>
                        <th class="px-6 py-3 font-medium">Description</th>
                        <th class="px-6 py-3 font-medium">Location</th>
                        <th class="px-6 py-3 font-medium">Evidence</th>
                        <th class="px-6 py-3 font-medium">Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($logs as $row)
                        <tr class="hover:bg-gray-50/80">
                            <td class="px-6 py-4">
                                <span class="rounded-full bg-red-50 px-2.5 py-1 text-xs font-medium text-red-700">{{ $row->violation_type }}</span>
                            </td>
                            <td class="px-6 py-4 text-gray-700">{{ $row->description ?: 'No description provided.' }}</td>
                            <td class="px-6 py-4 text-xs text-gray-600">
                                {{ $row->area_name ?: ($row->camera_id ?: 'Campus') }}
                            </td>
                            <td class="px-6 py-4">
                                <x-violation.evidence-panel :log="$row" route-name="user.violations.evidence" compact />
                            </td>
                            <td class="px-6 py-4 text-gray-600">{{ ph_datetime($row->created_at) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-14 text-center text-gray-500">No violations on your record.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($logs->hasPages())
            <div class="border-t border-gray-200 px-6 py-4">{{ $logs->links() }}</div>
        @endif
    </div>
@endsection
