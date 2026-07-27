@extends('layouts.portal')

@section('title', 'Registration Management')

@section('content')
    @include('partials.shell.page-header', [
        'title' => 'Registration Management',
        'subtitle' => 'Review and approve pending vehicle owner registrations',
    ])

    @if (session('success'))
        <div class="mb-4 flex gap-2 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
            <i data-lucide="circle-check" class="h-4 w-4 shrink-0"></i>
            {{ session('success') }}
        </div>
    @endif
    @if (session('error'))
        <div class="mb-4 flex gap-2 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            <i data-lucide="alert-circle" class="h-4 w-4 shrink-0"></i>
            {{ session('error') }}
        </div>
    @endif

    <div class="mb-6 grid grid-cols-1 gap-3 sm:grid-cols-3">
        <a href="{{ route('admin.registrations', ['status' => 'Pending']) }}"
           @class(['rounded-xl border p-5 transition-colors', 'border-blue-500 bg-blue-50' => $statusFilter === 'Pending', 'border-gray-200 bg-white hover:bg-gray-50' => $statusFilter !== 'Pending'])>
            <p class="text-sm text-gray-500">Pending</p>
            <p class="mt-1 text-2xl font-bold text-gray-900">{{ $pendingCount }}</p>
        </a>
        <a href="{{ route('admin.registrations', ['status' => 'Granted']) }}"
           @class(['rounded-xl border p-5 transition-colors', 'border-blue-500 bg-blue-50' => $statusFilter === 'Granted', 'border-gray-200 bg-white hover:bg-gray-50' => $statusFilter !== 'Granted'])>
            <p class="text-sm text-gray-500">Approved</p>
            <p class="mt-1 text-2xl font-bold text-green-700">{{ $approvedCount }}</p>
        </a>
        <a href="{{ route('admin.registrations', ['status' => 'Denied']) }}"
           @class(['rounded-xl border p-5 transition-colors', 'border-blue-500 bg-blue-50' => $statusFilter === 'Denied', 'border-gray-200 bg-white hover:bg-gray-50' => $statusFilter !== 'Denied'])>
            <p class="text-sm text-gray-500">Declined</p>
            <p class="mt-1 text-2xl font-bold text-red-600">{{ $declinedCount }}</p>
        </a>
    </div>

    <div class="space-y-3">
        @forelse ($requests as $row)
            <div class="flex flex-col gap-4 rounded-xl border border-gray-200 bg-white p-5 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h4 class="font-semibold text-gray-900">{{ $row->fullname }}</h4>
                    <p class="text-sm text-gray-500">{{ $row->role?->role_name }} · ID: {{ $row->id_number }}</p>
                    <p class="text-xs text-gray-400">{{ $row->email }}</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    @if ($row->status === 'Pending')
                        <form method="POST" action="{{ route('admin.registrations.approve', $row->id) }}" onsubmit="return confirm('Approve this registration?')">
                            @csrf
                            <button type="submit" class="rounded-lg bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-700">Approve</button>
                        </form>
                        <form method="POST" action="{{ route('admin.registrations.decline', $row->id) }}" class="flex flex-wrap items-center gap-2" onsubmit="return confirm('Decline this registration?')">
                            @csrf
                            <input type="text" name="remarks" placeholder="Decline reason (optional)" maxlength="500"
                                class="min-w-[160px] rounded-lg border border-gray-300 px-3 py-2 text-xs">
                            <button type="submit" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">Decline</button>
                        </form>
                    @endif
                    <a href="{{ route('admin.users.show', ['id' => $row->id, 'from' => 'registrations']) }}"
                       class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                        View Profile
                    </a>
                </div>
            </div>
        @empty
            <div class="rounded-xl border border-dashed border-gray-300 bg-gray-50 px-6 py-12 text-center text-sm text-gray-500">
                No {{ strtolower($statusFilter) }} registrations found.
            </div>
        @endforelse
    </div>
@endsection
