@extends('layouts.guard')

@section('title', 'Violation Records')

@section('content')
    @include('partials.shell.page-header', [
        'title' => 'Violation Records',
        'subtitle' => 'Select a registered plate to log a citation',
    ])

    @if ($success)
        <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
            Violation logged successfully.
            @if (request()->boolean('locked'))
                <strong>The violator's account has been permanently locked (3/3 strikes).</strong>
            @endif
        </div>
    @endif
    @if ($error === 'plate_not_found')
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">Plate number not found in registered vehicles.</div>
    @endif

    <div class="mb-6 flex justify-end">
        <button
            type="button"
            onclick="document.getElementById('violationModal').classList.remove('hidden'); document.getElementById('violationModal').classList.add('flex')"
            class="rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-blue-700"
        >
            Log Violation
        </button>
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-gray-200 bg-gray-50 text-gray-500">
                <tr>
                    <th class="px-6 py-3 font-medium">Plate</th>
                    <th class="px-6 py-3 font-medium">Name</th>
                    <th class="px-6 py-3 font-medium">Type</th>
                    <th class="px-6 py-3 font-medium">Date</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($logs as $row)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4"><code>{{ $row->plate_number }}</code></td>
                        <td class="px-6 py-4">{{ $row->violator_name }}</td>
                        <td class="px-6 py-4 text-red-600">{{ $row->violation_type }}</td>
                        <td class="px-6 py-4 text-gray-600">{{ ph_datetime($row->created_at) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-6 py-10 text-center text-gray-500">No violations logged.</td></tr>
                @endforelse
            </tbody>
        </table>
        @if ($logs->hasPages())
            <div class="border-t border-gray-200 px-6 py-4">{{ $logs->links() }}</div>
        @endif
    </div>

    <div id="violationModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4">
        <div class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-xl bg-white p-6 shadow-xl">
            <h3 class="mb-1 text-lg font-semibold text-gray-900">Log Violation</h3>
            <p class="mb-4 text-sm text-gray-500">{{ $registeredPlates->count() }} registered plate(s) on campus</p>
            <form method="POST" action="{{ route('guard.violations.store') }}" enctype="multipart/form-data" class="space-y-4">
                @csrf
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Plate Number</label>
                    <input
                        type="text"
                        name="plate_number"
                        id="plate_number_input"
                        list="registered-plates-list"
                        required
                        autocomplete="off"
                        placeholder="Search or select plate..."
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 uppercase focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20"
                    >
                    <datalist id="registered-plates-list">
                        @foreach ($registeredPlates as $vehicle)
                            <option value="{{ $vehicle->plate_number }}">{{ $vehicle->fullname }} ({{ $vehicle->plate_number }})</option>
                        @endforeach
                    </datalist>
                    @if ($registeredPlates->isNotEmpty())
                        <details class="mt-2">
                            <summary class="cursor-pointer text-xs font-medium text-blue-600">Browse all registered plates</summary>
                            <div class="mt-2 max-h-40 overflow-y-auto rounded-lg border border-gray-200 bg-gray-50 p-2">
                                <ul class="space-y-1 text-xs text-gray-700">
                                    @foreach ($registeredPlates as $vehicle)
                                        <li>
                                            <button
                                                type="button"
                                                class="w-full rounded px-2 py-1 text-left hover:bg-white"
                                                onclick="document.getElementById('plate_number_input').value = '{{ $vehicle->plate_number }}'"
                                            >
                                                <code class="font-semibold">{{ $vehicle->plate_number }}</code>
                                                — {{ $vehicle->fullname }}
                                            </button>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        </details>
                    @else
                        <p class="mt-1 text-xs text-amber-600">No registered plates found. Users must register a vehicle first.</p>
                    @endif
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Violation Type</label>
                    <select name="violation_type" required class="w-full rounded-lg border border-gray-300 px-3 py-2">
                        <option value="">Select type...</option>
                        @foreach ($violationTypes as $type)
                            <option value="{{ $type }}">{{ $type }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Description</label>
                    <textarea name="description" rows="3" class="w-full rounded-lg border border-gray-300 px-3 py-2"></textarea>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">
                        Photo Evidence
                        @if (! empty($requirePhotoEvidence))
                            <span class="text-red-600">*</span>
                        @endif
                    </label>
                    <input
                        type="file"
                        name="evidence_photo"
                        accept="image/*"
                        @if (! empty($requirePhotoEvidence)) required @endif
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
                    >
                    <p class="mt-1 text-xs text-gray-500">
                        @if (! empty($requirePhotoEvidence))
                            Required by system settings.
                        @else
                            Optional unless enabled in System Settings.
                        @endif
                    </p>
                </div>
                <div class="flex gap-3">
                    <button type="button" onclick="document.getElementById('violationModal').classList.add('hidden'); document.getElementById('violationModal').classList.remove('flex')" class="flex-1 rounded-lg border border-gray-300 py-2 text-sm font-medium">Cancel</button>
                    <button type="submit" class="flex-1 rounded-lg bg-blue-600 py-2 text-sm font-medium text-white hover:bg-blue-700">Submit</button>
                </div>
            </form>
        </div>
    </div>
@endsection
