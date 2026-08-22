@extends('layouts.guest')

@section('title', 'Register - Smart Campus VMS')

@section('use_campus_bg', '1')

@section('card_width')
    max-w-2xl
@endsection

@section('content')
    <div class="overflow-hidden rounded-2xl border border-white/30 bg-white/95 shadow-2xl backdrop-blur-sm">
        <div class="relative overflow-hidden bg-gradient-to-br from-blue-700 via-blue-800 to-slate-900 px-6 py-8 text-center text-white">
            <div class="pointer-events-none absolute inset-0 opacity-25" style="background-image: radial-gradient(circle at 20% 20%, #fff 0, transparent 40%), radial-gradient(circle at 80% 0%, #93c5fd 0, transparent 35%), radial-gradient(circle at 50% 100%, #1e3a8a 0, transparent 45%);"></div>
            <div class="relative">
                <div class="mx-auto mb-4 flex h-20 w-20 items-center justify-center">
                    @if (! empty($hasCspcLogo) || is_file(public_path('images/cspc-logo.png')))
                        <img src="{{ asset('images/cspc-logo.png') }}" alt="Camarines Sur Polytechnic Colleges" class="h-20 w-20 object-contain drop-shadow-lg">
                    @else
                        <i data-lucide="parking-square" class="h-7 w-7"></i>
                    @endif
                </div>
                <h1 class="text-2xl font-bold">Create Account</h1>
                <p class="mt-1 text-sm text-blue-100">Join the CSPC Vehicle Management System</p>
            </div>
        </div>

        <div class="p-6 sm:p-8">
            @if (!empty($dbError))
                <div class="mb-4 flex gap-2 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    <i data-lucide="database" class="mt-0.5 h-4 w-4 shrink-0"></i>
                    <span>{{ $dbError }}</span>
                </div>
            @endif

            @if ($errors->any() && empty($dbError))
                <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    <p class="font-medium">Please fix the following:</p>
                    <ul class="mt-2 list-inside list-disc space-y-1 text-xs">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if (session('success'))
                <div class="mb-4 flex gap-2 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                    <i data-lucide="circle-check" class="mt-0.5 h-4 w-4 shrink-0"></i>
                    <span>{{ session('success') }}</span>
                </div>
            @endif

            <form method="POST" action="{{ route('register') }}" enctype="multipart/form-data" class="space-y-6" id="register-form" novalidate>
                @csrf
                <input type="hidden" name="reg_category" id="reg_category" value="vehicle">

                <div class="space-y-4 rounded-xl border border-gray-200 bg-gray-50/50 p-4 sm:p-5">
                    <h2 class="text-sm font-semibold text-gray-900">Personal Information</h2>

                    <x-auth.file-input
                        name="profile_pic"
                        id="profile_pic"
                        label="Profile Picture"
                        accept="image/*"
                    />

                    <div>
                        <x-auth.file-input
                            name="id_document"
                            id="id_document"
                            label="Valid ID Upload"
                            required
                            accept="image/*,application/pdf"
                        />
                        <p id="id_scan_status" class="mt-2 hidden text-xs"></p>
                        <p class="mt-1 text-xs text-gray-500">Upload a clear photo of your CSPC ID to auto-fill your name and SN. You can still edit the fields before submitting.</p>
                    </div>

                    <x-auth.input
                        label="Full Name"
                        name="full_name"
                        required
                        placeholder="e.g. John Michael Moral Toldanes"
                        value="{{ old('full_name') }}"
                    />

                    <div>
                        <label for="id_number" class="mb-1.5 block text-sm font-medium text-gray-700">ID Number <span class="text-red-500">*</span></label>
                        <input
                            type="text"
                            name="id_number"
                            id="id_number"
                            value="{{ old('id_number') }}"
                            required
                            inputmode="text"
                            pattern="[A-Za-z0-9]+"
                            maxlength="50"
                            placeholder="e.g. 231002254 (SN on ID)"
                            title="Use letters and numbers only (max 50)"
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 shadow-sm placeholder:text-gray-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 @error('id_number') border-red-500 focus:border-red-500 focus:ring-red-500/20 @enderror"
                        >
                        @error('id_number')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label for="email" class="mb-1.5 block text-sm font-medium text-gray-700">Email Address <span class="text-red-500">*</span></label>
                            <input
                                type="email"
                                name="email"
                                id="email"
                                value="{{ old('email') }}"
                                required
                                autocomplete="email"
                                placeholder="name@example.com"
                                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 shadow-sm placeholder:text-gray-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 @error('email') border-red-500 focus:border-red-500 focus:ring-red-500/20 @enderror"
                            >
                            @error('email')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <x-auth.input label="Phone Number" name="phone_number" required placeholder="09XX XXX XXXX" value="{{ old('phone_number') }}" />
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <x-auth.password-input
                            name="password"
                            label="Password"
                            required
                            autocomplete="new-password"
                            placeholder="{{ $passwordHint ?? '8–15 chars, mixed case, number, symbol' }}"
                        />
                        <x-auth.password-input
                            name="password_confirmation"
                            label="Confirm Password"
                            required
                            autocomplete="new-password"
                            placeholder="Re-enter password"
                        />
                    </div>
                    <p class="text-xs text-gray-500">{{ $passwordHint ?? '' }}</p>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label for="user_type" class="mb-1.5 block text-sm font-medium text-gray-700">User Type <span class="text-red-500">*</span></label>
                            <select
                                name="user_type"
                                id="user_type"
                                required
                                class="w-full cursor-pointer rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 @error('user_type') border-red-500 @enderror"
                            >
                                <option value="" @selected(old('user_type') === '')>Select user type</option>
                                <option value="Student" @selected(old('user_type') === 'Student')>Student</option>
                                <option value="Staff" @selected(old('user_type') === 'Staff')>Faculty / Staff</option>
                            </select>
                            @error('user_type')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="department_code" class="mb-1.5 block text-sm font-medium text-gray-700">Department <span class="text-red-500">*</span></label>
                            <select
                                name="department_code"
                                id="department_code"
                                required
                                class="w-full cursor-pointer rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 @error('department_code') border-red-500 @enderror"
                            >
                                <option value="">Select department</option>
                                @foreach ($departments as $dept)
                                    <option value="{{ $dept->departmentcode }}" @selected(old('department_code') == $dept->departmentcode)>
                                        {{ $dept->departmentname }}
                                    </option>
                                @endforeach
                            </select>
                            @error('department_code')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <div id="vehicle-fields" class="space-y-4 rounded-xl border border-blue-200 bg-blue-50/30 p-4 sm:p-5">
                    <h2 class="flex items-center gap-2 text-sm font-semibold text-gray-900">
                        <i data-lucide="car" class="h-4 w-4 text-blue-600"></i>
                        Vehicle Details
                    </h2>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label for="vehicle_id" class="mb-1.5 block text-sm font-medium text-gray-700">Vehicle Type <span class="text-red-500">*</span></label>
                            <select
                                name="vehicle_id"
                                id="vehicle_id"
                                required
                                class="w-full cursor-pointer rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 @error('vehicle_id') border-red-500 @enderror"
                            >
                                <option value="" @selected(old('vehicle_id') === '' || old('vehicle_id') === null)>Select vehicle type</option>
                                @foreach ($vehicleTypes as $vh)
                                    <option value="{{ $vh->id }}" @selected(old('vehicle_id') == $vh->id)>
                                        {{ $vh->vehicle_name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('vehicle_id')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <x-auth.input label="Plate Number" name="plate_number" required placeholder="ABC-1234" value="{{ old('plate_number') }}" />
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <x-auth.file-input
                            name="driver_license"
                            id="driver_license"
                            label="Driver's License"
                            required
                            accept="image/*"
                        />
                        <x-auth.file-input
                            name="or_cr_photo"
                            id="or_cr_photo"
                            label="OR / CR File (Image or PDF)"
                            required
                            accept="image/*,application/pdf"
                        />
                    </div>
                    <p class="text-xs text-gray-500">Driver's license image and OR/CR document (image or PDF) are required for vehicle owner registration.</p>
                </div>

                <button
                    type="submit"
                    class="flex w-full cursor-pointer items-center justify-center gap-2 rounded-lg bg-blue-600 py-3 text-sm font-semibold text-white transition-colors hover:bg-blue-700"
                >
                    <i data-lucide="user-plus" class="h-4 w-4"></i>
                    Register Account
                </button>
            </form>

            <p class="mt-6 text-center text-sm text-gray-500">
                Already have an account?
                <a href="{{ route('login') }}" class="cursor-pointer font-semibold text-blue-600 hover:text-blue-700 hover:underline">Sign in here</a>
            </p>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('register-form');
            const requiredFields = [
                { id: 'email', label: 'Email address' },
                { id: 'phone_number', label: 'Phone number' },
                { id: 'password', label: 'Password' },
                { id: 'password_confirmation', label: 'Password confirmation' },
                { id: 'plate_number', label: 'Plate number' },
            ];

            if (form) {
                form.addEventListener('submit', function (event) {
                    for (const field of requiredFields) {
                        const input = document.getElementById(field.id);
                        if (!input || String(input.value || '').trim() === '') {
                            event.preventDefault();
                            input?.focus();
                            input?.reportValidity?.();
                            return;
                        }
                    }
                });
            }

            const input = document.getElementById('id_document');
            const statusEl = document.getElementById('id_scan_status');
            const fields = {
                id_number: document.getElementById('id_number'),
                full_name: document.getElementById('full_name'),
            };

            if (!input || !statusEl) {
                return;
            }

            let scanToken = 0;
            let scanning = false;

            const setStatus = (message, tone) => {
                statusEl.textContent = message;
                statusEl.classList.remove('hidden', 'text-blue-700', 'text-green-700', 'text-amber-700', 'text-red-600');
                statusEl.classList.add(
                    tone === 'success' ? 'text-green-700'
                        : tone === 'warning' ? 'text-amber-700'
                            : tone === 'error' ? 'text-red-600'
                                : 'text-blue-700'
                );
            };

            const fillField = (field, value) => {
                if (!field || value == null || value === '') {
                    return;
                }
                field.value = value;
                field.dispatchEvent(new Event('input', { bubbles: true }));
            };

            input.addEventListener('change', async function () {
                const file = input.files?.[0];
                if (!file) {
                    statusEl.classList.add('hidden');
                    return;
                }

                if (scanning) {
                    return;
                }

                if (!file.type.startsWith('image/')) {
                    setStatus('PDF uploaded. Enter your name and SN manually, or upload a JPG/PNG photo for auto-scan.', 'warning');
                    return;
                }

                const token = ++scanToken;
                scanning = true;
                setStatus('Scanning campus ID… this can take up to a minute on the first scan.', 'info');

                const body = new FormData();
                body.append('id_document', file);
                body.append('_token', document.querySelector('meta[name="csrf-token"]')?.content || '');

                try {
                    const response = await fetch(@json(route('register.scan-id')), {
                        method: 'POST',
                        body,
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });

                    let data = {};
                    try {
                        data = await response.json();
                    } catch (parseError) {
                        data = {};
                    }

                    if (token !== scanToken) {
                        return;
                    }

                    if (response.status === 429) {
                        setStatus(data.message || 'Too many scan attempts. Please wait about a minute, then try again.', 'warning');
                        return;
                    }

                    if (!response.ok || !data.ok) {
                        setStatus(data.message || 'Could not scan this photo. Please enter your details manually.', 'warning');
                        return;
                    }

                    fillField(fields.id_number, data.id_number);

                    if (data.full_name && data.name_complete) {
                        fillField(fields.full_name, data.full_name);
                    }

                    const warnings = Array.isArray(data.warnings) ? data.warnings.filter(Boolean) : [];
                    if (warnings.length > 0) {
                        setStatus(`Auto-filled with notes: ${warnings.join(' ')}`, 'warning');
                    } else if (data.full_name && data.name_complete) {
                        setStatus('Full name and SN auto-filled from your ID. Please confirm before submitting.', 'success');
                    } else if (data.id_number) {
                        setStatus('SN auto-filled. Please enter your full name manually.', 'warning');
                    } else {
                        setStatus('Could not scan this photo. Please enter your details manually.', 'warning');
                    }
                } catch (error) {
                    if (token !== scanToken) {
                        return;
                    }
                    setStatus('Scan failed. Please enter your name and SN manually.', 'error');
                } finally {
                    if (token === scanToken) {
                        scanning = false;
                    }
                }
            });
        });
    </script>
@endpush
