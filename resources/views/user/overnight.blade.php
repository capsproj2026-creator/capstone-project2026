@extends('layouts.user')

@section('title', 'Overnight Permit')

@section('content')
    @include('partials.shell.page-header', [
        'title' => 'Overnight parking',
        'subtitle' => 'Campus policy blocks overnight stays unless GSU approves proof that the stay is valid',
    ])

    <div class="mb-6 max-w-3xl rounded-xl border border-gray-200 bg-white p-6">
        <form method="POST" action="{{ route('user.overnight.store') }}" enctype="multipart/form-data" class="space-y-4">
            @csrf
            <p class="text-sm text-gray-600">Plate on file: <span class="font-semibold text-gray-900">{{ $plate ?: 'Not set' }}</span></p>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-500">Starts</label>
                    <input type="datetime-local" name="starts_at" required value="{{ old('starts_at') }}" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-500">Ends</label>
                    <input type="datetime-local" name="ends_at" required value="{{ old('ends_at') }}" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                </div>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-500">Why the stay is needed</label>
                <textarea name="reason" required minlength="5" maxlength="500" rows="3" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">{{ old('reason') }}</textarea>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-500">Proof</label>
                <input type="file" name="proof" required accept=".jpg,.jpeg,.png,.pdf" class="block w-full text-sm text-gray-700">
                <p class="mt-1 text-xs text-gray-500">Upload a travel order or other document that shows the overnight stay is allowed.</p>
            </div>
            @if ($errors->any())
                <p class="text-sm text-rose-700">{{ $errors->first() }}</p>
            @endif
            <button type="submit" class="inline-flex items-center rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700">Submit request</button>
        </form>
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-gray-200 bg-gray-50 text-gray-500">
                <tr>
                    <th class="px-5 py-3 font-medium">Window</th>
                    <th class="px-5 py-3 font-medium">Status</th>
                    <th class="px-5 py-3 font-medium">Note</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($permits as $permit)
                    <tr>
                        <td class="px-5 py-4 text-gray-700">{{ ph_datetime($permit->starts_at) }} – {{ ph_datetime($permit->ends_at) }}</td>
                        <td class="px-5 py-4 capitalize text-gray-800">{{ $permit->status }}</td>
                        <td class="px-5 py-4 text-gray-600">{{ $permit->review_note ?: $permit->reason }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="px-6 py-10 text-center text-sm text-gray-500">No requests yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
