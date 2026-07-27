@extends('layouts.user')

@section('title', 'User Dashboard')

@section('content')
    @php
        $initials = strtoupper(collect(explode(' ', $user->fullname ?? 'U'))->filter()->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->join(''));
    @endphp

    @include('partials.shell.page-header', [
        'title' => 'Dashboard',
        'subtitle' => 'Welcome back, '.$user->fullname,
    ])

    <div class="mb-6 rounded-xl border border-gray-200 bg-white p-6">
        <div class="mb-6 flex items-center gap-4">
            <x-portal.avatar :user="$user" size="xl" class="bg-gradient-to-br from-purple-500 to-purple-700" />
            <div>
                <h3 class="text-lg font-semibold text-gray-900">{{ $user->fullname }}</h3>
                <p class="text-gray-500">ID: {{ $user->id_number }} · {{ $user->role?->role_name }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div class="flex items-center gap-3 rounded-lg bg-gray-50 p-4">
                <i data-lucide="car" class="h-5 w-5 text-purple-600"></i>
                <div>
                    <p class="text-sm text-gray-500">Vehicle</p>
                    <p class="font-semibold text-gray-900">{{ $user->plate_number ?? '—' }}</p>
                </div>
            </div>
            <div class="flex items-center gap-3 rounded-lg bg-gray-50 p-4">
                <i data-lucide="alert-circle" class="h-5 w-5 text-orange-600"></i>
                <div>
                    <p class="text-sm text-gray-500">Violations</p>
                    <p class="font-semibold text-gray-900">{{ $strikeCount }} / {{ $maxStrikes }}</p>
                </div>
            </div>
        </div>
        <p class="mt-4 text-sm text-gray-500">Gate access: <strong class="text-gray-900">{{ $gateAccess }}</strong></p>
    </div>

    <div class="mb-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
        <div class="rounded-xl border border-gray-200 bg-white">
            <div class="border-b border-gray-200 p-6">
                <div class="flex items-center gap-3">
                    <i data-lucide="file-text" class="h-6 w-6 text-purple-600"></i>
                    <h3 class="font-semibold text-gray-900">General Information</h3>
                </div>
            </div>
            <div class="space-y-3 p-6">
                @forelse ($generalInfo as $info)
                    <div class="flex gap-3 rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-900">
                        <i data-lucide="circle-check" class="mt-0.5 h-5 w-5 shrink-0 text-green-600"></i>
                        <span>{{ $info->description }}</span>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">No general information available.</p>
                @endforelse
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white">
            <div class="border-b border-gray-200 p-6">
                <div class="flex items-center gap-3">
                    <i data-lucide="parking-square" class="h-6 w-6 text-purple-600"></i>
                    <h3 class="font-semibold text-gray-900">Official Parking Rules</h3>
                </div>
            </div>
            <div class="space-y-3 p-6">
                @forelse ($parkingRules as $rule)
                    <div class="flex gap-3">
                        <i data-lucide="circle-check" class="mt-0.5 h-5 w-5 shrink-0 text-green-600"></i>
                        <p class="text-sm text-gray-600">{{ $rule->description }}</p>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">No rules posted yet.</p>
                @endforelse
            </div>
        </div>
    </div>

    @if ($recentGateLogs->isNotEmpty())
        <div class="mb-6 rounded-xl border border-gray-200 bg-white">
            <div class="border-b border-gray-200 px-6 py-4 flex items-center justify-between">
                <h3 class="font-semibold text-gray-900">Recent Entry / Exit</h3>
                <a href="{{ route('user.entry-exit') }}" class="text-sm text-purple-600 hover:underline">View all</a>
            </div>
            <div class="divide-y divide-gray-100">
                @foreach ($recentGateLogs as $log)
                    <div class="flex items-center justify-between px-6 py-3">
                        <span class="font-medium text-gray-900">{{ $log->action }}</span>
                        <span class="text-sm text-gray-500">{{ ph_datetime($log->timestamp) }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="rounded-xl border border-purple-200 bg-purple-50 p-6">
        <h4 class="mb-2 font-semibold text-purple-900">Important Notice</h4>
        <p class="text-sm text-purple-700">
        Failure to comply with campus parking policies may result in appropriate disciplinary action or loss of parking privileges.
        Always park only in designated parking areas, observe the 15 kph speed limit, and follow all campus parking and traffic regulations.
        </p>
    </div>
@endsection
