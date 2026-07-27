@extends('layouts.portal')

@section('title', 'User Details')

@section('content')
    @php $vehicleRoles = ['Student', 'Staff']; @endphp

    <a href="{{ $backUrl }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-gray-500 hover:text-blue-600">
        <i data-lucide="arrow-left" class="h-4 w-4"></i>
        {{ $backLabel }}
    </a>

    <div class="mt-5 rounded-xl border border-gray-200 bg-white p-6 sm:p-8">
        <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="flex items-center gap-4">
                <img src="{{ $user->profilePictureUrl() }}" alt="{{ $user->fullname }}"
                    class="h-20 w-20 rounded-xl border border-gray-200 object-cover sm:h-24 sm:w-24">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">{{ $user->fullname }}</h1>
                    <p class="text-sm text-gray-500">{{ $user->role?->role_name }} · {{ $user->id_number }}</p>
                </div>
            </div>
            <span @class([
                'inline-flex w-fit rounded-full px-4 py-1.5 text-sm font-semibold',
                'bg-red-100 text-red-700' => $user->isLocked(),
                'bg-green-100 text-green-700' => ! $user->isLocked() && $user->status === 'Granted',
                'bg-amber-100 text-amber-700' => ! $user->isLocked() && $user->status === 'Pending',
                'bg-gray-100 text-gray-700' => ! $user->isLocked() && ! in_array($user->status, ['Granted', 'Pending'], true),
            ])>
                {{ $user->isLocked() ? 'Locked' : $user->status }}
            </span>
        </div>

        <div class="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div class="rounded-lg border border-gray-100 bg-gray-50 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Email</p>
                <p class="mt-1 text-sm font-medium text-gray-900">{{ $user->email }}</p>
            </div>
            <div class="rounded-lg border border-gray-100 bg-gray-50 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Phone</p>
                <p class="mt-1 text-sm font-medium text-gray-900">{{ $user->phone_number }}</p>
            </div>
            <div class="rounded-lg border border-gray-100 bg-gray-50 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Violations</p>
                <p class="mt-1 text-sm font-medium text-gray-900">
                    {{ $user->strike_count }}/{{ \App\Models\User::MAX_STRIKES }}
                    @if ($user->isLocked())
                        <span class="text-red-600">(permanently locked)</span>
                    @endif
                </p>
            </div>
            @if (in_array($user->role?->role_name, $vehicleRoles))
                <div class="rounded-lg border border-gray-100 bg-gray-50 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Plate Number</p>
                    <p class="mt-1 text-sm font-medium text-gray-900">{{ $user->plate_number }}</p>
                </div>
                <div class="rounded-lg border border-gray-100 bg-gray-50 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Department</p>
                    <p class="mt-1 text-sm font-medium text-gray-900">{{ $user->department?->departmentname ?? 'N/A' }}</p>
                </div>
            @endif
        </div>

        @php
            $licenseUrl = $user->uploadedDocumentUrl('driver_license', 'uploads/documents/license');
            $orCrUrl = $user->uploadedDocumentUrl('or_cr_photo', 'uploads/documents/orcr');
        @endphp

        <h2 class="mb-4 text-lg font-semibold text-gray-900">Uploaded Documents</h2>
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <div class="rounded-xl border border-gray-200 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Driver's License</p>
                @if ($licenseUrl)
                    @if ($user->isImageDocument('driver_license'))
                        <img src="{{ $licenseUrl }}" alt="Driver's License" class="mt-3 max-h-64 w-full rounded-lg border border-gray-200 object-contain bg-gray-50">
                    @else
                        <p class="mt-2 text-sm text-gray-600">Document uploaded.</p>
                    @endif
                    <a href="{{ $licenseUrl }}" target="_blank" rel="noopener" class="mt-3 inline-flex items-center gap-1 text-sm font-semibold text-blue-600 hover:underline">
                        Open file <i data-lucide="external-link" class="h-3.5 w-3.5"></i>
                    </a>
                @else
                    <p class="mt-2 text-sm text-gray-400">No file uploaded.</p>
                @endif
            </div>
            <div class="rounded-xl border border-gray-200 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">OR / CR</p>
                @if ($orCrUrl)
                    @if ($user->isImageDocument('or_cr_photo'))
                        <img src="{{ $orCrUrl }}" alt="OR / CR" class="mt-3 max-h-64 w-full rounded-lg border border-gray-200 object-contain bg-gray-50">
                    @else
                        <p class="mt-2 text-sm text-gray-600">PDF/document uploaded.</p>
                    @endif
                    <a href="{{ $orCrUrl }}" target="_blank" rel="noopener" class="mt-3 inline-flex items-center gap-1 text-sm font-semibold text-blue-600 hover:underline">
                        Open file <i data-lucide="external-link" class="h-3.5 w-3.5"></i>
                    </a>
                @else
                    <p class="mt-2 text-sm text-gray-400">No file uploaded.</p>
                @endif
            </div>
        </div>
    </div>
@endsection
