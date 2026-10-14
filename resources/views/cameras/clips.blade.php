@extends('layouts.portal')

@section('title', 'Incident Clips')

@section('content')
    @include('partials.shell.page-header', [
        'title' => 'Incident clips',
        'subtitle' => 'Short recordings from the last minute of a live camera',
    ])

    @include('partials.shell.flash')

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-gray-200 bg-gray-50 text-gray-500">
                    <tr>
                        <th class="px-5 py-3 font-medium">Camera</th>
                        <th class="px-5 py-3 font-medium">Saved</th>
                        <th class="px-5 py-3 font-medium">Source</th>
                        <th class="px-5 py-3 font-medium">Replay</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($clips as $clip)
                        <tr>
                            <td class="px-5 py-4 font-medium text-gray-900">{{ $clip->camera_id }}</td>
                            <td class="px-5 py-4 text-gray-600">{{ ph_datetime($clip->created_at) }}</td>
                            <td class="px-5 py-4 capitalize text-gray-600">{{ $clip->source }}</td>
                            <td class="px-5 py-4">
                                <video controls preload="metadata" class="max-h-40 rounded-lg bg-black" src="{{ route($playRoute, $clip->id) }}"></video>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-12 text-center text-sm text-gray-500">No incident clips saved yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($clips->hasPages())
        <div class="mt-4">{{ $clips->links() }}</div>
    @endif
@endsection
