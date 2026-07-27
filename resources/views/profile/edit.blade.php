@extends('layouts.portal')

@section('title', 'Account Settings')

@section('content')
    @include('partials.shell.page-header', [
        'title' => 'Account Settings',
        'subtitle' => 'Update your profile details and password',
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

    <div class="mx-auto max-w-5xl space-y-6">
        {{-- Profile header card --}}
        <div class="rounded-xl border border-gray-200 bg-white p-6">
            <div class="flex flex-col gap-5 sm:flex-row sm:items-center">
                <x-portal.avatar :user="$user" size="xl" />
                <div class="min-w-0 flex-1">
                    <h2 class="truncate text-xl font-semibold text-gray-900">{{ $user->fullname }}</h2>
                    <p class="mt-0.5 text-sm text-gray-500">{{ $user->roleName() }} · ID {{ $user->id_number }}</p>
                    <p class="mt-1 truncate text-sm text-gray-500">{{ $user->email }}</p>
                    @if ($user->hasUploadedProfilePicture())
                        <p class="mt-2 inline-flex items-center gap-1.5 text-xs font-medium text-green-700">
                            <i data-lucide="image" class="h-3.5 w-3.5"></i>
                            Custom profile photo active
                        </p>
                    @else
                        <p class="mt-2 text-xs text-gray-400">Using default avatar — upload a photo below</p>
                    @endif
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            {{-- Profile form --}}
            <div class="rounded-xl border border-gray-200 bg-white p-6">
                <h3 class="mb-1 flex items-center gap-2 text-base font-semibold text-gray-900">
                    <i data-lucide="user" class="h-4 w-4 text-blue-600"></i>
                    Profile Information
                </h3>
                <p class="mb-5 text-sm text-gray-500">Your name, contact details, and photo.</p>

                <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="space-y-4">
                    @csrf
                    <input type="hidden" name="update_profile" value="1">
                    <x-auth.input label="Full Name" name="fullname" :value="$user->fullname" required />
                    <x-auth.input label="Email Address" name="email" type="email" :value="$user->email" required />
                    <x-auth.input label="Phone Number" name="phone_number" :value="$user->phone_number" required />
                    <x-auth.file-input name="profile_pic" id="profile_pic" label="Profile Photo" accept="image/*" />
                    <button type="submit" class="w-full rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-700 sm:w-auto">
                        Save Profile
                    </button>
                </form>
            </div>

            {{-- Password form --}}
            <div class="rounded-xl border border-gray-200 bg-white p-6">
                <h3 class="mb-1 flex items-center gap-2 text-base font-semibold text-gray-900">
                    <i data-lucide="lock" class="h-4 w-4 text-blue-600"></i>
                    Change Password
                </h3>
                <p class="mb-5 text-sm text-gray-500">Use a strong password you don’t reuse elsewhere.</p>

                <form method="POST" action="{{ route('profile.update') }}" class="space-y-4">
                    @csrf
                    <input type="hidden" name="change_password" value="1">
                    <x-auth.password-input name="current_password" label="Current Password" autocomplete="current-password" />
                    <x-auth.password-input name="new_password" label="New Password" autocomplete="new-password" placeholder="At least 6 characters" />
                    <x-auth.password-input name="new_password_confirmation" id="new_password_confirmation" label="Confirm New Password" autocomplete="new-password" />
                    <button type="submit" class="w-full rounded-lg bg-gray-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-gray-800 sm:w-auto">
                        Update Password
                    </button>
                </form>
            </div>
        </div>
    </div>
@endsection
