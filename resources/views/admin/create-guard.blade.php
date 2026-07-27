@extends('layouts.portal')

@section('title', 'Create Guard Account')

@section('content')
    @include('partials.shell.page-header', [
        'title' => 'Create Guard Account',
        'subtitle' => 'Admin-only registration for guard personnel',
    ])

    <div class="mb-5 flex max-w-3xl items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-amber-900">
        <i data-lucide="shield-alert" class="mt-0.5 h-5 w-5 shrink-0"></i>
        <div>
            <p class="text-sm font-semibold">Admin-only action</p>
            <p class="text-xs text-amber-800">
                Guard accounts are created here and granted active portal/gate access immediately.
            </p>
        </div>
    </div>

    <div class="max-w-3xl rounded-xl border border-gray-200 bg-white p-6 sm:p-7">
        <form method="POST" action="{{ route('admin.guards.store') }}" class="space-y-6">
            @csrf

            <div class="space-y-4 rounded-xl border border-gray-200 bg-gray-50/70 p-4 sm:p-5">
                <h2 class="flex items-center gap-2 text-sm font-semibold text-gray-900">
                    <i data-lucide="user-round" class="h-4 w-4 text-blue-600"></i>
                    Guard Profile
                </h2>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-auth.input label="Full Name" name="fullname" required placeholder="Juan Dela Cruz" />
                    <x-auth.input label="ID Number" name="id_number" required maxlength="50" placeholder="e.g. G2026A001" />
                </div>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-auth.input label="Email Address" name="email" type="email" required placeholder="name@example.com" />
                    <x-auth.input label="Phone Number" name="phone_number" required placeholder="09XX XXX XXXX" />
                </div>
                <p class="text-xs text-gray-500">Use any valid email address. The system checks format and active domain DNS records.</p>
            </div>

            <div class="space-y-4 rounded-xl border border-gray-200 bg-gray-50/70 p-4 sm:p-5">
                <h2 class="flex items-center gap-2 text-sm font-semibold text-gray-900">
                    <i data-lucide="key-round" class="h-4 w-4 text-blue-600"></i>
                    Security
                </h2>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-auth.password-input name="password" label="Password" autocomplete="new-password" />
                    <x-auth.password-input name="password_confirmation" label="Confirm Password" autocomplete="new-password" />
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-3 border-t border-gray-200 pt-4">
                <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-700">
                    <i data-lucide="shield-plus" class="h-4 w-4"></i>
                    Create Guard Account
                </button>
                <a href="{{ route('admin.users') }}" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 px-5 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
                    <i data-lucide="arrow-left" class="h-4 w-4"></i>
                    Back to User Management
                </a>
            </div>
        </form>
    </div>
@endsection
