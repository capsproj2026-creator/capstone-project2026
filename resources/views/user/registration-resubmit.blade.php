@extends('layouts.user')

@section('title', 'Fix Registration Documents')

@section('content')
    @include('partials.shell.page-header', [
        'title' => 'Fix Registration Documents',
        'subtitle' => 'Upload clear copies of your license, OR, CR, and valid ID',
    ])

    <div class="mb-6 rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-950">
        <p class="font-semibold">Your registration needs correction</p>
        @if ($user->declineCategoryLabel())
            <p class="mt-1">Issue: {{ $user->declineCategoryLabel() }}</p>
        @endif
        @if (filled($user->decline_remarks))
            <p class="mt-1">{{ $user->decline_remarks }}</p>
        @endif
        @if ($user->remedial_expires_at && ! $user->remedialAccessExpired())
            <p class="mt-2 text-xs text-amber-800">
                Temporary gate access until {{ $user->remedial_expires_at->timezone(config('app.timezone'))->format('M j, Y g:i A') }}.
            </p>
        @endif
    </div>

    @if ($errors->any())
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            <ul class="list-inside list-disc space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('user.registration.resubmit') }}" enctype="multipart/form-data" class="max-w-2xl space-y-5 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
        @csrf

        <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700">Driver's License <span class="text-red-500">*</span></label>
            <input type="file" name="driver_license" accept="image/*" required class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
        </div>

        <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700">LTO Official Receipt (OR) <span class="text-red-500">*</span></label>
            <input type="file" name="lto_or_photo" accept="image/*,application/pdf" required class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
        </div>

        <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700">LTO Certificate of Registration (CR) <span class="text-red-500">*</span></label>
            <input type="file" name="lto_cr_photo" accept="image/*,application/pdf" required class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
        </div>

        <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700">Valid ID / School ID <span class="text-red-500">*</span></label>
            <input type="file" name="id_document" accept="image/*,application/pdf" required class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
        </div>

        <div class="flex flex-wrap gap-3 pt-2">
            <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700">
                Submit for Review
            </button>
            <a href="{{ route('user.dashboard') }}" class="rounded-lg border border-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                Back to Dashboard
            </a>
        </div>
    </form>
@endsection
