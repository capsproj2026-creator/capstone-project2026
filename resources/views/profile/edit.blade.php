@extends('layouts.portal')

@section('title', 'Account Settings')

@section('content')
    @include('partials.shell.page-header', [
        'title' => 'Account Settings',
        'subtitle' => ($canManageVehicles ?? false)
            ? 'Update your profile, vehicles, and password'
            : 'Update your profile and password',
    ])

    <div class="mx-auto w-full max-w-none space-y-6">
        <div class="rounded-xl border border-gray-200 bg-white p-6">
            <div class="flex flex-col gap-5 sm:flex-row sm:items-center">
                <x-portal.avatar :user="$user" size="xl" />
                <div class="min-w-0 flex-1">
                    <h2 class="truncate text-xl font-semibold text-gray-900">{{ $user->fullname }}</h2>
                    <p class="mt-0.5 text-sm text-gray-500">{{ $user->displayRoleLabel() }} · ID {{ $user->id_number }}</p>
                    <p class="mt-1 truncate text-sm text-gray-500">{{ $user->email }}</p>
                    @if ($userVehicles->isNotEmpty())
                        <div class="mt-2 flex flex-wrap gap-2">
                            @foreach ($userVehicles as $uv)
                                <span @class([
                                    'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold',
                                    'bg-blue-50 text-blue-700' => $uv->is_primary,
                                    'bg-gray-100 text-gray-700' => ! $uv->is_primary,
                                ])>
                                    <i data-lucide="car" class="h-3.5 w-3.5"></i>
                                    {{ $uv->plate_number }}
                                    @if ($uv->vehicleType)
                                        · {{ $uv->vehicleType->vehicle_name }}
                                    @endif
                                    @if ($uv->is_primary)
                                        · Primary
                                    @endif
                                </span>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
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
                    <div>
                        <label for="address" class="mb-1.5 block text-sm font-medium text-gray-700">Address</label>
                        <textarea name="address" id="address" rows="2" maxlength="255"
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm uppercase text-gray-900 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">{{ old('address', $user->address) }}</textarea>
                    </div>
                    <x-auth.file-input name="profile_pic" id="profile_pic" label="Profile Photo" accept="image/*" />
                    <button type="submit" class="w-full rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-700 sm:w-auto">
                        Save Profile
                    </button>
                </form>
            </div>

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

        {{-- Vehicles (students/staff only) --}}
        @if ($canManageVehicles ?? false)
        <div class="rounded-xl border border-gray-200 bg-white p-6">
            <div class="mb-5">
                <h3 class="flex items-center gap-2 text-base font-semibold text-gray-900">
                    <i data-lucide="car" class="h-4 w-4 text-blue-600"></i>
                    Registered Vehicles
                </h3>
                <p class="mt-1 text-sm text-gray-500">You can register multiple vehicles. The primary vehicle is used for gate access and plate lookup.</p>
            </div>

            @if ($userVehicles->isNotEmpty())
                <div class="mb-6 space-y-4">
                    @foreach ($userVehicles as $uv)
                        <div @class([
                            'rounded-xl border p-4',
                            'border-blue-200 bg-blue-50/40' => $uv->is_primary,
                            'border-gray-200 bg-gray-50/50' => ! $uv->is_primary,
                        ])>
                            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                                <div class="flex items-center gap-2">
                                    <span class="font-mono text-sm font-bold text-gray-900">{{ $uv->plate_number }}</span>
                                    @if ($uv->is_primary)
                                        <span class="rounded-full bg-blue-600 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-white">Primary</span>
                                    @endif
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    @unless ($uv->is_primary)
                                        <form method="POST" action="{{ route('profile.update') }}">
                                            @csrf
                                            <input type="hidden" name="make_primary_vehicle" value="1">
                                            <input type="hidden" name="user_vehicle_id" value="{{ $uv->id }}">
                                            <button type="submit" class="rounded-lg border border-blue-200 bg-white px-3 py-1.5 text-xs font-semibold text-blue-700 hover:bg-blue-50">Set Primary</button>
                                        </form>
                                    @endunless
                                    <form method="POST" action="{{ route('profile.update') }}" onsubmit="return confirm('Remove this vehicle?');">
                                        @csrf
                                        <input type="hidden" name="remove_vehicle" value="1">
                                        <input type="hidden" name="user_vehicle_id" value="{{ $uv->id }}">
                                        <button type="submit" class="rounded-lg border border-red-200 bg-white px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-50">Remove</button>
                                    </form>
                                </div>
                            </div>

                            <form method="POST" action="{{ route('profile.update') }}" class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                @csrf
                                <input type="hidden" name="update_vehicle" value="1">
                                <input type="hidden" name="user_vehicle_id" value="{{ $uv->id }}">
                                <div>
                                    <label class="mb-1 block text-xs font-medium text-gray-600">Vehicle Type</label>
                                    <select name="vehicle_id" required class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm">
                                        @foreach ($vehicles as $vehicle)
                                            <option value="{{ $vehicle->id }}" @selected((string) $uv->vehicle_id === (string) $vehicle->id)>{{ $vehicle->vehicle_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-medium text-gray-600">Plate Number</label>
                                    <input type="text" name="plate_number" value="{{ $uv->plate_number }}" required maxlength="20" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm uppercase">
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-medium text-gray-600">Model</label>
                                    <input type="text" name="vehicle_model" value="{{ $uv->vehicle_model ?? $user->vehicle_model }}" maxlength="80" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm">
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-medium text-gray-600">Color</label>
                                    <input type="text" name="vehicle_color" value="{{ $uv->vehicle_color ?? $user->vehicle_color }}" maxlength="40" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm">
                                </div>
                                <div class="sm:col-span-2 lg:col-span-4 flex items-end">
                                    <button type="submit" class="w-full rounded-lg bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800 sm:w-auto">Update Vehicle</button>
                                </div>
                            </form>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="mb-5 rounded-lg border border-dashed border-gray-200 bg-gray-50 px-4 py-6 text-center text-sm text-gray-500">No vehicles registered yet. Add your first vehicle below.</p>
            @endif

            <div class="rounded-xl border border-blue-100 bg-blue-50/50 p-4">
                <h4 class="mb-3 flex items-center gap-2 text-sm font-semibold text-blue-900">
                    <i data-lucide="plus-circle" class="h-4 w-4"></i>
                    Add Vehicle
                </h4>
                <form method="POST" action="{{ route('profile.update') }}" class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    @csrf
                    <input type="hidden" name="add_vehicle" value="1">
                    <div>
                        <label for="add_vehicle_id" class="mb-1 block text-xs font-medium text-gray-700">Vehicle Type <span class="text-red-500">*</span></label>
                        <select name="vehicle_id" id="add_vehicle_id" required class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm">
                            <option value="">Select type</option>
                            @foreach ($vehicles as $vehicle)
                                <option value="{{ $vehicle->id }}" @selected((string) old('vehicle_id') === (string) $vehicle->id)>{{ $vehicle->vehicle_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="add_plate_number" class="mb-1 block text-xs font-medium text-gray-700">Plate Number <span class="text-red-500">*</span></label>
                        <input type="text" name="plate_number" id="add_plate_number" value="{{ old('plate_number') }}" required maxlength="20" placeholder="ABC-1234" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm uppercase">
                    </div>
                    <div>
                        <label for="add_vehicle_model" class="mb-1 block text-xs font-medium text-gray-700">Model</label>
                        <input type="text" name="vehicle_model" id="add_vehicle_model" value="{{ old('vehicle_model') }}" maxlength="80" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm">
                    </div>
                    <div>
                        <label for="add_vehicle_color" class="mb-1 block text-xs font-medium text-gray-700">Color</label>
                        <input type="text" name="vehicle_color" id="add_vehicle_color" value="{{ old('vehicle_color') }}" maxlength="40" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm">
                    </div>
                    <div class="sm:col-span-2 lg:col-span-4 flex items-end">
                        <button type="submit" class="w-full rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700 sm:w-auto">Add Vehicle</button>
                    </div>
                </form>
            </div>
        </div>
        @endif
    </div>
@endsection
