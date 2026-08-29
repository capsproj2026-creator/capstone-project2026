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
                    <p class="text-sm text-gray-500">{{ $user->displayRoleLabel() }} · {{ $user->id_number }}</p>
                </div>
            </div>
            <span @class([
                'inline-flex w-fit rounded-full px-4 py-1.5 text-sm font-semibold',
                'bg-red-100 text-red-700' => $user->isLocked(),
                'bg-amber-100 text-amber-700' => ! $user->isLocked() && ($user->isUnregisteredStudentFaculty() || $user->status === 'Pending'),
                'bg-green-100 text-green-700' => ! $user->isLocked() && ! $user->isUnregisteredStudentFaculty() && $user->status === 'Granted',
                'bg-gray-100 text-gray-700' => ! $user->isLocked() && ! $user->isUnregisteredStudentFaculty() && ! in_array($user->status, ['Granted', 'Pending'], true),
            ])>
                {{ $user->isLocked() ? 'Locked' : ($user->isUnregisteredStudentFaculty() ? 'Not registered yet' : $user->status) }}
            </span>
        </div>

        @if ($user->status === \App\Models\User::STATUS_DENIED && filled($user->decline_remarks))
            <div class="mb-8 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">
                <p class="text-xs font-semibold uppercase tracking-wide text-rose-700">Decline reason</p>
                <p class="mt-1">{{ $user->decline_remarks }}</p>
            </div>
        @endif

        <div class="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div class="rounded-lg border border-gray-100 bg-gray-50 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Email</p>
                <p class="mt-1 text-sm font-medium text-gray-900">{{ $user->displayEmail() }}</p>
            </div>
            <div class="rounded-lg border border-gray-100 bg-gray-50 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Phone</p>
                <p class="mt-1 text-sm font-medium text-gray-900">{{ $user->phone_number }}</p>
            </div>
            @if (filled($user->address))
                <div class="rounded-lg border border-gray-100 bg-gray-50 p-4 sm:col-span-2">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Address</p>
                    <p class="mt-1 text-sm font-medium text-gray-900">{{ $user->address }}</p>
                </div>
            @endif
            @if (filled($user->affiliation) || filled($user->ownership_type) || filled($user->usage_type))
                <div class="rounded-lg border border-gray-100 bg-gray-50 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Affiliation</p>
                    <p class="mt-1 text-sm font-medium text-gray-900">
                        {{ $user->affiliation ?? '—' }}
                        @if (filled($user->affiliation_other))
                            ({{ $user->affiliation_other }})
                        @endif
                    </p>
                </div>
                <div class="rounded-lg border border-gray-100 bg-gray-50 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Ownership</p>
                    <p class="mt-1 text-sm font-medium text-gray-900">
                        {{ $user->ownership_type ?? '—' }}
                        @if (filled($user->ownership_other))
                            ({{ $user->ownership_other }})
                        @endif
                    </p>
                </div>
                <div class="rounded-lg border border-gray-100 bg-gray-50 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Usage</p>
                    <p class="mt-1 text-sm font-medium text-gray-900">
                        {{ $user->usage_type ?? '—' }}
                        @if (filled($user->usage_other))
                            ({{ $user->usage_other }})
                        @endif
                    </p>
                </div>
            @endif
            @if (filled($user->course_year_section))
                <div class="rounded-lg border border-gray-100 bg-gray-50 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Course / Year &amp; Section</p>
                    <p class="mt-1 text-sm font-medium text-gray-900">{{ $user->course_year_section }}</p>
                </div>
            @endif
            @if (filled($user->application_date))
                <div class="rounded-lg border border-gray-100 bg-gray-50 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Date of Application</p>
                    <p class="mt-1 text-sm font-medium text-gray-900">{{ $user->application_date?->format('M d, Y') ?? $user->application_date }}</p>
                </div>
            @endif
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
                @if (filled($user->vehicle_model))
                    <div class="rounded-lg border border-gray-100 bg-gray-50 p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Vehicle Model</p>
                        <p class="mt-1 text-sm font-medium text-gray-900">{{ $user->vehicle_model }}</p>
                    </div>
                @endif
                @if (filled($user->vehicle_color))
                    <div class="rounded-lg border border-gray-100 bg-gray-50 p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Color</p>
                        <p class="mt-1 text-sm font-medium text-gray-900">{{ $user->vehicle_color }}</p>
                    </div>
                @endif
                @if (filled($user->driver_license_number))
                    <div class="rounded-lg border border-gray-100 bg-gray-50 p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">License No.</p>
                        <p class="mt-1 text-sm font-medium text-gray-900">{{ $user->driver_license_number }}</p>
                    </div>
                @endif
                <div class="rounded-lg border border-gray-100 bg-gray-50 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Department</p>
                    <p class="mt-1 text-sm font-medium text-gray-900">{{ $user->department?->departmentname ?? 'N/A' }}</p>
                </div>
            @endif
        </div>

        @php
            $idUrl = $user->uploadedDocumentUrl('id_document', 'uploads/documents/id');
            $licenseUrl = $user->uploadedDocumentUrl('driver_license', 'uploads/documents/license');
            $orUrl = $user->uploadedDocumentUrl('lto_or_photo', 'uploads/documents/orcr')
                ?: $user->uploadedDocumentUrl('or_cr_photo', 'uploads/documents/orcr');
            $crUrl = $user->uploadedDocumentUrl('lto_cr_photo', 'uploads/documents/cr');
            $documents = [
                ['label' => 'Valid ID / School ID', 'url' => $idUrl, 'field' => 'id_document'],
                ['label' => "Driver's License", 'url' => $licenseUrl, 'field' => 'driver_license'],
                ['label' => 'LTO Official Receipt (OR)', 'url' => $orUrl, 'field' => filled($user->lto_or_photo) ? 'lto_or_photo' : 'or_cr_photo'],
                ['label' => 'LTO Certificate of Registration (CR)', 'url' => $crUrl, 'field' => 'lto_cr_photo'],
            ];
        @endphp

        <h2 class="mb-4 text-lg font-semibold text-gray-900">Uploaded Documents</h2>
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            @foreach ($documents as $document)
                <div class="rounded-xl border border-gray-200 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $document['label'] }}</p>
                    @if ($document['url'])
                        @if ($user->isImageDocument($document['field']))
                            <img src="{{ $document['url'] }}" alt="{{ $document['label'] }}" class="mt-3 max-h-64 w-full rounded-lg border border-gray-200 object-contain bg-gray-50">
                        @else
                            <p class="mt-2 text-sm text-gray-600">Document uploaded.</p>
                        @endif
                        <a href="{{ $document['url'] }}" target="_blank" rel="noopener" class="mt-3 inline-flex items-center gap-1 text-sm font-semibold text-blue-600 hover:underline">
                            Open file <i data-lucide="external-link" class="h-3.5 w-3.5"></i>
                        </a>
                    @else
                        <p class="mt-2 text-sm text-gray-400">No file uploaded.</p>
                    @endif
                </div>
            @endforeach
        </div>

        @if (($recentGateLogs ?? collect())->isNotEmpty())
            <h2 class="mb-4 mt-8 text-lg font-semibold text-gray-900">Recent gate activity</h2>
            <div class="overflow-hidden rounded-xl border border-gray-200">
                <table class="min-w-full divide-y divide-gray-100 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-2">Time</th>
                            <th class="px-4 py-2">Direction</th>
                            <th class="px-4 py-2">Result</th>
                            <th class="px-4 py-2">RFID</th>
                            <th class="px-4 py-2">Notes</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        @foreach ($recentGateLogs as $log)
                            <tr>
                                <td class="px-4 py-2 text-gray-700">{{ $log->timestamp?->timezone(config('app.timezone'))->format('M j, Y g:i A') ?? '—' }}</td>
                                <td class="px-4 py-2 text-gray-900">{{ $log->action ?: '—' }}</td>
                                <td class="px-4 py-2">{{ $log->displayResultLabel() }}</td>
                                <td class="px-4 py-2 font-mono text-xs text-gray-800">{{ $log->displayRfid() }}</td>
                                <td class="px-4 py-2 text-xs text-gray-500">{{ $log->displayReason() ?: '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
