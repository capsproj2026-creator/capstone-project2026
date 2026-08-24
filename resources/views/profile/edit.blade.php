@extends('layouts.portal')

@section('title', 'Account Settings')

@section('content')
    @include('partials.shell.page-header', [
        'title' => 'Account Settings',
        'subtitle' => 'Update your profile, vehicle, and password',
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
    @if ($errors->any())
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            <ul class="list-disc space-y-1 pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
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
                    @if ($user->plate_number)
                        <p class="mt-2 inline-flex items-center gap-1.5 rounded-full bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700">
                            <i data-lucide="car" class="h-3.5 w-3.5"></i>
                            {{ $user->plate_number }}
                            @if ($user->vehicleType)
                                · {{ $user->vehicleType->vehicle_name }}
                            @endif
                        </p>
                    @endif
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
                <p class="mb-5 text-sm text-gray-500">{{ \App\Support\PasswordRules::hint() }}. Must differ from your current password.</p>

                <form method="POST" action="{{ route('profile.update') }}" class="space-y-4">
                    @csrf
                    <input type="hidden" name="change_password" value="1">
                    <x-auth.password-input name="current_password" label="Current Password" autocomplete="current-password" />
                    <x-auth.password-input name="new_password" label="New Password" autocomplete="new-password" placeholder="{{ \App\Support\PasswordRules::hint() }}" />
                    <x-auth.password-input name="new_password_confirmation" id="new_password_confirmation" label="Confirm New Password" autocomplete="new-password" />
                    <button type="submit" class="w-full rounded-lg bg-gray-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-gray-800 sm:w-auto">
                        Update Password
                    </button>
                </form>
            </div>
        </div>

        {{-- Vehicle --}}
        <div class="rounded-xl border border-gray-200 bg-white p-6">
            <div class="mb-5 flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h3 class="flex items-center gap-2 text-base font-semibold text-gray-900">
                        <i data-lucide="car" class="h-4 w-4 text-blue-600"></i>
                        Registered Vehicle
                    </h3>
                    <p class="mt-1 text-sm text-gray-500">Add or update the vehicle linked to your campus account (one vehicle per account).</p>
                </div>
                @if ($user->plate_number)
                    <div class="rounded-lg border border-blue-100 bg-blue-50 px-3 py-2 text-sm">
                        <p class="text-xs font-medium uppercase tracking-wide text-blue-600">Current</p>
                        <p class="font-semibold text-blue-900">{{ $user->plate_number }}</p>
                        <p class="text-xs text-blue-700">{{ $user->vehicleType->vehicle_name ?? 'Type not set' }}</p>
                    </div>
                @endif
            </div>

            <form method="POST" action="{{ route('profile.update') }}" class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                @csrf
                <input type="hidden" name="update_vehicle" value="1">
                <div>
                    <label for="vehicle_id" class="mb-1.5 block text-sm font-medium text-gray-700">Vehicle Type <span class="text-red-500">*</span></label>
                    <select
                        name="vehicle_id"
                        id="vehicle_id"
                        required
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 @error('vehicle_id') border-red-500 @enderror"
                    >
                        <option value="">Select vehicle type</option>
                        @foreach ($vehicles as $vehicle)
                            <option value="{{ $vehicle->id }}" @selected((string) old('vehicle_id', $user->vehicle_id) === (string) $vehicle->id)>
                                {{ $vehicle->vehicle_name }}
                            </option>
                        @endforeach
                    </select>
                    @error('vehicle_id')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="plate_number" class="mb-1.5 block text-sm font-medium text-gray-700">Plate Number <span class="text-red-500">*</span></label>
                    <input
                        type="text"
                        name="plate_number"
                        id="plate_number"
                        value="{{ old('plate_number', $user->plate_number) }}"
                        required
                        maxlength="20"
                        placeholder="ABC-1234"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm uppercase focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 @error('plate_number') border-red-500 @enderror"
                    >
                    @error('plate_number')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <div class="flex flex-wrap items-center gap-3 sm:col-span-2">
                    <button type="submit" class="rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-700">
                        {{ $user->plate_number ? 'Update Vehicle' : 'Save Vehicle' }}
                    </button>
                </div>
            </form>

            @if ($user->plate_number)
                <form method="POST" action="{{ route('profile.update') }}" class="mt-4 border-t border-gray-100 pt-4" onsubmit="return confirm('Remove this vehicle from your account?');">
                    @csrf
                    <input type="hidden" name="remove_vehicle" value="1">
                    <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg border border-red-200 px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50">
                        <i data-lucide="trash-2" class="h-4 w-4"></i>
                        Remove Vehicle
                    </button>
                </form>
            @endif
        </div>
    </div>
@endsection
