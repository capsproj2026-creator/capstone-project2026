@extends($layout ?? 'layouts.portal')

@section('title', 'Incident Clips')

@section('content')
    @include('partials.shell.page-header', [
        'title' => 'Incident clips',
        'subtitle' => ($canSave ?? false)
            ? 'Short recordings from the last minute of a live camera'
            : 'View clips saved by guards from live cameras',
    ])

    @if (($canSave ?? false) && ! empty($cameras))
        <div class="mb-4 overflow-hidden rounded-xl border border-gray-200 bg-white p-4">
            <p class="text-sm font-semibold text-gray-900">Save a new clip</p>
            <p class="mt-1 text-xs text-gray-500">Uses the last minute of live video from the selected camera.</p>
            <div class="mt-3 flex flex-wrap gap-2">
                @foreach ($cameras as $camera)
                    <form method="POST" action="{{ route($saveRoute) }}">
                        @csrf
                        <input type="hidden" name="camera_id" value="{{ $camera['id'] }}">
                        <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50">
                            <i data-lucide="video" class="h-3.5 w-3.5"></i>
                            Save {{ $camera['id'] }}
                            @if (! empty($camera['location']))
                                <span class="font-normal text-gray-500">({{ $camera['location'] }})</span>
                            @endif
                        </button>
                    </form>
                @endforeach
            </div>
        </div>
    @endif

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-gray-200 bg-gray-50 text-gray-500">
                    <tr>
                        <th class="px-5 py-3 font-medium">Camera</th>
                        <th class="px-5 py-3 font-medium">Location</th>
                        <th class="px-5 py-3 font-medium">Saved</th>
                        <th class="px-5 py-3 font-medium">Source</th>
                        <th class="px-5 py-3 font-medium">Replay</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($clips as $clip)
                        @php
                            $camKey = strtoupper((string) $clip->camera_id);
                            $location = $cameraLocations[$camKey] ?? '—';
                        @endphp
                        <tr>
                            <td class="px-5 py-4 font-medium text-gray-900">{{ $clip->camera_id }}</td>
                            <td class="px-5 py-4 text-gray-600">{{ $location }}</td>
                            <td class="px-5 py-4 text-gray-600">{{ ph_datetime($clip->created_at) }}</td>
                            <td class="px-5 py-4 capitalize text-gray-600">{{ $clip->source }}</td>
                            <td class="px-5 py-4">
                                <video controls preload="metadata" class="max-h-40 rounded-lg bg-black" src="{{ route($playRoute, $clip->id) }}"></video>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center text-sm text-gray-500">No incident clips saved yet.</td>
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
