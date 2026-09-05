@extends('layouts.guest')

@section('title', 'Register - Smart Campus VMS')

@section('use_campus_bg', '1')

@section('card_width')
    max-w-3xl
@endsection

@section('content')
    <div class="overflow-hidden rounded-2xl border border-white/30 bg-white/95 shadow-2xl backdrop-blur-sm">
        <div class="relative overflow-hidden bg-gradient-to-br from-[#1A365D] via-[#122844] to-slate-900 px-6 py-7 text-center text-white sm:px-8">
            <div class="pointer-events-none absolute inset-0 opacity-25" style="background-image: radial-gradient(circle at 20% 20%, #fff 0, transparent 40%), radial-gradient(circle at 80% 0%, #93c5fd 0, transparent 35%), radial-gradient(circle at 50% 100%, #1A365D 0, transparent 45%);"></div>
            <div class="relative">
                <div class="mx-auto mb-3 flex h-16 w-16 items-center justify-center sm:h-20 sm:w-20">
                    @if (! empty($hasCspcLogo) || is_file(public_path('images/cspc-logo.png')))
                        <img src="{{ asset('images/cspc-logo.png') }}" alt="Camarines Sur Polytechnic Colleges" class="h-16 w-16 object-contain drop-shadow-lg sm:h-20 sm:w-20">
                    @else
                        <i data-lucide="parking-square" class="h-7 w-7"></i>
                    @endif
                </div>
                <h1 class="text-2xl font-bold tracking-tight">Vehicle Registration</h1>
                <p class="mt-1 text-sm text-blue-100">CSPC Gate pass for campus vehicles</p>
            </div>
        </div>

        <div class="p-5 sm:p-8">
            @if (!empty($dbError))
                <div class="mb-5 flex gap-2 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    <i data-lucide="database" class="mt-0.5 h-4 w-4 shrink-0"></i>
                    <span>{{ $dbError }}</span>
                </div>
            @endif

            @if ($errors->any() && empty($dbError))
                <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    <p class="font-medium">Please fix the following:</p>
                    <ul class="mt-2 list-inside list-disc space-y-1 text-xs">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if (session('success'))
                <div class="mb-5 flex gap-2 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                    <i data-lucide="circle-check" class="mt-0.5 h-4 w-4 shrink-0"></i>
                    <span>{{ session('success') }}</span>
                </div>
            @endif

            @if (! empty($converting))
                <div class="mb-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">
                    <p class="font-semibold">Unregistered student/faculty — finish this form to keep campus entry.</p>
                    <p class="mt-1 text-xs text-amber-800">
                        RFID {{ $converting->rfid_uid ?: $converting->temp_rfid_uid }} is already linked.
                        @if ($converting->temporary_expires_at)
                            Register before <span id="temp-deadline">{{ $converting->temporary_expires_at->timezone(config('app.timezone'))->format('M j, Y g:i A') }}</span>
                            (<span id="temp-countdown">calculating…</span>).
                        @endif
                    </p>
                </div>
            @endif

            <ol id="register-steps" class="mb-6 grid grid-cols-4 gap-2 sm:gap-3" aria-label="Registration steps">
                <li>
                    <button type="button" data-register-step="1" class="register-step-btn w-full rounded-lg border border-blue-200 bg-blue-50 px-1.5 py-2.5 text-center transition sm:px-2">
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-blue-700">Step 1</p>
                        <p class="mt-0.5 text-[11px] font-medium text-gray-800 sm:text-xs">License</p>
                    </button>
                </li>
                <li>
                    <button type="button" data-register-step="2" class="register-step-btn w-full rounded-lg border border-slate-200 bg-slate-50 px-1.5 py-2.5 text-center transition sm:px-2">
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Step 2</p>
                        <p class="mt-0.5 text-[11px] font-medium text-gray-800 sm:text-xs">Your info</p>
                    </button>
                </li>
                <li>
                    <button type="button" data-register-step="3" class="register-step-btn w-full rounded-lg border border-slate-200 bg-slate-50 px-1.5 py-2.5 text-center transition sm:px-2">
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Step 3</p>
                        <p class="mt-0.5 text-[11px] font-medium text-gray-800 sm:text-xs">Vehicle</p>
                    </button>
                </li>
                <li>
                    <button type="button" data-register-step="4" class="register-step-btn w-full rounded-lg border border-slate-200 bg-slate-50 px-1.5 py-2.5 text-center transition sm:px-2">
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Step 4</p>
                        <p class="mt-0.5 text-[11px] font-medium text-gray-800 sm:text-xs">LTO files</p>
                    </button>
                </li>
            </ol>

            <p id="register-step-hint" class="mb-5 text-center text-xs text-gray-500">Complete each step carefully. You can go back anytime before submitting.</p>

            @php
                $step2Keys = ['profile_pic', 'id_document', 'full_name', 'address', 'phone_number', 'id_number', 'email', 'driver_license_number', 'password', 'user_type', 'department_code'];
                $step3Keys = ['vehicle_id', 'vehicle_model', 'vehicle_color', 'plate_number'];
                $step4Keys = ['lto_or_photo', 'lto_cr_photo'];
                $initialStep = 1;
                if ($errors->hasAny($step4Keys)) {
                    $initialStep = 4;
                } elseif ($errors->hasAny($step3Keys)) {
                    $initialStep = 3;
                } elseif ($errors->hasAny($step2Keys)) {
                    $initialStep = 2;
                } elseif ($errors->has('driver_license')) {
                    $initialStep = 1;
                }
            @endphp

            <form method="POST" action="{{ route('register') }}" enctype="multipart/form-data" class="space-y-0" id="register-form" novalidate data-initial-step="{{ $initialStep }}">
                @csrf
                <input type="hidden" name="reg_category" id="reg_category" value="vehicle">
                @if (! empty($converting))
                    <input type="hidden" name="temp_token" value="{{ old('temp_token', $converting->temp_conversion_token) }}">
                @endif

                <section id="register-step-1" class="register-step-panel space-y-4" data-step="1">
                    <div class="rounded-xl border border-blue-100 bg-blue-50/60 px-4 py-3">
                        <div class="flex items-start gap-3">
                            <span class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-blue-600 text-xs font-bold text-white">1</span>
                            <div>
                                <h2 class="text-base font-semibold text-gray-900">Driver’s license</h2>
                                <p class="mt-0.5 text-sm text-gray-600">Upload a clear photo of the <strong class="font-medium text-gray-800">front</strong> of your license. We’ll try to fill your name, address, and license number — check them in the next step.</p>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-[1fr_auto] sm:items-start">
                        <x-auth.file-input
                            name="driver_license"
                            id="driver_license"
                            label="Driver’s License Photo"
                            required
                            accept="image/*"
                        />
                        <img id="license-preview" alt="Driver’s license preview" class="hidden max-h-36 w-full rounded-xl border border-gray-200 bg-slate-50 object-contain sm:max-w-[11rem]">
                    </div>
                    <p id="license_scan_status" class="hidden text-xs"></p>
                    @error('driver_license')
                        <p class="text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </section>

                <section id="register-step-2" class="register-step-panel hidden space-y-4" data-step="2">
                    <div class="rounded-xl border border-blue-100 bg-blue-50/60 px-4 py-3">
                        <div class="flex items-start gap-3">
                            <span class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-blue-600 text-xs font-bold text-white">2</span>
                            <div>
                                <h2 class="text-base font-semibold text-gray-900">Your information</h2>
                                <p class="mt-0.5 text-sm text-gray-600">Photos, contact details, and campus account. Correct anything the license scan missed.</p>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <x-auth.file-input
                            name="profile_pic"
                            id="profile_pic"
                            label="Profile Picture"
                            required
                            accept="image/*"
                        />
                        <div>
                            <x-auth.file-input
                                name="id_document"
                                id="id_document"
                                label="School ID"
                                required
                                accept="image/*,application/pdf"
                            />
                            <p class="mt-1 text-xs text-gray-500">Clear photo of your school ID. It is saved with your registration and is not scanned.</p>
                        </div>
                    </div>

                    <x-auth.input
                        label="Full Name"
                        name="full_name"
                        required
                        placeholder="e.g. Juan Dela Cruz"
                        value="{{ old('full_name') }}"
                    />

                    <div>
                        <label for="address" class="mb-1.5 block text-sm font-medium text-gray-700">Address <span class="text-red-500">*</span></label>
                        <textarea
                            name="address"
                            id="address"
                            rows="2"
                            required
                            placeholder="House / street, barangay, city, province"
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm uppercase text-gray-900 shadow-sm placeholder:text-gray-400 placeholder:normal-case focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 @error('address') border-red-500 focus:border-red-500 focus:ring-red-500/20 @enderror"
                        >{{ old('address') }}</textarea>
                        <p class="mt-1 text-xs text-gray-500">Street, Barangay, City, Province, and ZIP Code should appear once.</p>
                        @error('address')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <x-auth.input label="Contact Number" name="phone_number" required placeholder="09XX XXX XXXX" value="{{ old('phone_number') }}" />
                        <div>
                            <label for="id_number" class="mb-1.5 block text-sm font-medium text-gray-700">ID Number / SN <span class="text-red-500">*</span></label>
                            <input
                                type="text"
                                name="id_number"
                                id="id_number"
                                value="{{ old('id_number') }}"
                                required
                                inputmode="text"
                                pattern="[A-Za-z0-9]+"
                                maxlength="50"
                                placeholder="e.g. 23100XXXX"
                                title="Use letters and numbers only (max 50)"
                                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 shadow-sm placeholder:text-gray-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 @error('id_number') border-red-500 focus:border-red-500 focus:ring-red-500/20 @enderror"
                            >
                            @error('id_number')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
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
                                placeholder="name@my.cspc.edu.ph"
                                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 shadow-sm placeholder:text-gray-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 @error('email') border-red-500 focus:border-red-500 focus:ring-red-500/20 @enderror"
                            >
                            @error('email')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <x-auth.input
                            label="Driver’s License Number"
                            name="driver_license_number"
                            required
                            placeholder="e.g. N01-12-345678"
                            value="{{ old('driver_license_number') }}"
                        />
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
                </section>

                <section id="register-step-3" class="register-step-panel hidden space-y-4" data-step="3" data-vehicle-fields>
                    <div class="rounded-xl border border-blue-100 bg-blue-50/60 px-4 py-3">
                        <div class="flex items-start gap-3">
                            <span class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-blue-600 text-xs font-bold text-white">3</span>
                            <div>
                                <h2 class="text-base font-semibold text-gray-900">Vehicle</h2>
                                <p class="mt-0.5 text-sm text-gray-600">Use the same details printed on your OR and CR.</p>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label for="vehicle_id" class="mb-1.5 block text-sm font-medium text-gray-700">Type of Vehicle <span class="text-red-500">*</span></label>
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
                        <x-auth.input label="Model" name="vehicle_model" required placeholder="e.g. Honda Click 125" value="{{ old('vehicle_model', $converting?->vehicle_model) }}" />
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <x-auth.input label="Color" name="vehicle_color" required placeholder="e.g. White" value="{{ old('vehicle_color', $converting?->vehicle_color) }}" />
                        <x-auth.input label="Plate Number" name="plate_number" required placeholder="ABC-1234" value="{{ old('plate_number', $converting?->plate_number) }}" />
                    </div>
                    @if (! empty($converting) && filled($converting->rfid_uid ?: $converting->temp_rfid_uid))
                        <p class="text-xs text-gray-500">RFID UID <span class="font-mono font-semibold text-gray-800">{{ $converting->rfid_uid ?: $converting->temp_rfid_uid }}</span> will stay linked after you register.</p>
                    @endif
                </section>

                <section id="register-step-4" class="register-step-panel hidden space-y-4" data-step="4">
                    <div class="rounded-xl border border-blue-100 bg-blue-50/60 px-4 py-3">
                        <div class="flex items-start gap-3">
                            <span class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-blue-600 text-xs font-bold text-white">4</span>
                            <div>
                                <h2 class="text-base font-semibold text-gray-900">LTO documents</h2>
                                <p class="mt-0.5 text-sm text-gray-600">Upload clear photos of your Official Receipt and Certificate of Registration, then submit.</p>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <x-auth.file-input
                                name="lto_or_photo"
                                id="lto_or_photo"
                                label="LTO Official Receipt (OR)"
                                required
                                accept="image/*,application/pdf"
                                capture="environment"
                            />
                            <p id="or_scan_status" class="mt-2 hidden text-xs"></p>
                        </div>
                        <div>
                            <x-auth.file-input
                                name="lto_cr_photo"
                                id="lto_cr_photo"
                                label="LTO Certificate of Registration (CR)"
                                required
                                accept="image/*,application/pdf"
                                capture="environment"
                            />
                            <p id="cr_scan_status" class="mt-2 hidden text-xs"></p>
                        </div>
                    </div>
                </section>

                <div class="mt-8 flex flex-col-reverse gap-3 border-t border-gray-100 pt-6 sm:flex-row sm:items-center sm:justify-between">
                    <button
                        type="button"
                        id="register-back-btn"
                        class="hidden inline-flex items-center justify-center gap-2 rounded-xl border border-gray-300 bg-white px-5 py-3 text-sm font-semibold text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40"
                    >
                        <i data-lucide="arrow-left" class="h-4 w-4"></i>
                        Back
                    </button>

                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                        <p id="register-step-label" class="text-center text-xs text-gray-500 sm:text-right">Step 1 of 4</p>
                        <button
                            type="button"
                            id="register-next-btn"
                            class="inline-flex items-center justify-center gap-2 rounded-xl bg-[#5D9FD1] px-5 py-3 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-[#4A8FC4]"
                        >
                            Next
                            <i data-lucide="arrow-right" class="h-4 w-4"></i>
                        </button>
                        <button
                            type="submit"
                            id="register-submit-btn"
                            class="hidden inline-flex items-center justify-center gap-2 rounded-xl bg-[#5D9FD1] px-5 py-3 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-[#4A8FC4]"
                        >
                            <i data-lucide="{{ ! empty($converting) ? 'clipboard-check' : 'user-plus' }}" class="h-4 w-4"></i>
                            {{ ! empty($converting) ? 'Complete Registration' : 'Submit registration' }}
                        </button>
                    </div>
                </div>
                <p class="mt-3 text-center text-xs text-gray-500">GSU reviews submissions before gate access is granted.</p>
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
            const panels = Array.from(document.querySelectorAll('.register-step-panel'));
            const stepButtons = Array.from(document.querySelectorAll('[data-register-step]'));
            const backBtn = document.getElementById('register-back-btn');
            const nextBtn = document.getElementById('register-next-btn');
            const submitBtn = document.getElementById('register-submit-btn');
            const stepLabel = document.getElementById('register-step-label');
            const totalSteps = panels.length || 4;
            let currentStep = Number(form?.dataset.initialStep || 1);

            const stepFields = {
                1: ['driver_license'],
                2: [
                    'profile_pic', 'id_document', 'full_name', 'address', 'phone_number', 'id_number',
                    'email', 'driver_license_number', 'password', 'password_confirmation', 'user_type', 'department_code',
                ],
                3: ['vehicle_id', 'vehicle_model', 'vehicle_color', 'plate_number'],
                4: ['lto_or_photo', 'lto_cr_photo'],
            };

            const setStatus = (el, message, tone) => {
                if (!el) return;
                el.textContent = message;
                el.classList.remove('hidden', 'text-blue-700', 'text-green-700', 'text-amber-700', 'text-red-600', 'text-gray-500');
                el.classList.add(
                    tone === 'success' ? 'text-green-700'
                        : tone === 'warning' ? 'text-amber-700'
                            : tone === 'error' ? 'text-red-600'
                                : 'text-blue-700'
                );
            };

            const paintStepButtons = (step) => {
                stepButtons.forEach((btn) => {
                    const n = Number(btn.dataset.registerStep);
                    const active = n === step;
                    const done = n < step;
                    btn.classList.toggle('bg-blue-50', active);
                    btn.classList.toggle('border-blue-200', active);
                    btn.classList.toggle('bg-emerald-50', done);
                    btn.classList.toggle('border-emerald-200', done);
                    btn.classList.toggle('bg-slate-50', !active && !done);
                    btn.classList.toggle('border-slate-200', !active && !done);
                    const label = btn.querySelector('p');
                    if (label) {
                        label.className = active
                            ? 'text-[10px] font-semibold uppercase tracking-wide text-blue-700'
                            : done
                                ? 'text-[10px] font-semibold uppercase tracking-wide text-emerald-700'
                                : 'text-[10px] font-semibold uppercase tracking-wide text-slate-500';
                    }
                });
            };

            const showStep = (step) => {
                currentStep = Math.min(Math.max(Number(step) || 1, 1), totalSteps);
                panels.forEach((panel) => {
                    const n = Number(panel.dataset.step);
                    panel.classList.toggle('hidden', n !== currentStep);
                });
                paintStepButtons(currentStep);
                if (stepLabel) stepLabel.textContent = `Step ${currentStep} of ${totalSteps}`;
                if (backBtn) {
                    backBtn.disabled = currentStep <= 1;
                    backBtn.classList.toggle('hidden', currentStep <= 1);
                }
                if (nextBtn) nextBtn.classList.toggle('hidden', currentStep >= totalSteps);
                if (submitBtn) submitBtn.classList.toggle('hidden', currentStep < totalSteps);
                if (window.lucide) window.lucide.createIcons();
                form?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            };

            const fieldValueOk = (el) => {
                if (!el) return false;
                if (el.type === 'file') return el.files && el.files.length > 0;
                return String(el.value || '').trim() !== '';
            };

            const validateStep = (step) => {
                const ids = stepFields[step] || [];
                for (const id of ids) {
                    const el = document.getElementById(id);
                    if (!fieldValueOk(el)) {
                        el?.focus?.();
                        el?.reportValidity?.();
                        if (id === 'driver_license') {
                            setStatus(
                                document.getElementById('license_scan_status'),
                                'Please upload a photo of your driver’s license.',
                                'error'
                            );
                        }
                        return false;
                    }
                    if (typeof el.checkValidity === 'function' && !el.checkValidity()) {
                        el.focus();
                        el.reportValidity();
                        return false;
                    }
                }

                if (step === 2) {
                    const password = document.getElementById('password');
                    const confirm = document.getElementById('password_confirmation');
                    if (password && confirm && password.value !== confirm.value) {
                        confirm.setCustomValidity('Passwords do not match.');
                        confirm.reportValidity();
                        confirm.setCustomValidity('');
                        return false;
                    }
                }

                return true;
            };

            nextBtn?.addEventListener('click', () => {
                if (!validateStep(currentStep)) return;
                showStep(currentStep + 1);
            });

            backBtn?.addEventListener('click', () => {
                showStep(currentStep - 1);
            });

            stepButtons.forEach((btn) => {
                btn.addEventListener('click', () => {
                    const target = Number(btn.dataset.registerStep);
                    if (target === currentStep) return;
                    if (target > currentStep) {
                        for (let s = currentStep; s < target; s += 1) {
                            if (!validateStep(s)) {
                                showStep(s);
                                return;
                            }
                        }
                    }
                    showStep(target);
                });
            });

            form?.addEventListener('submit', (event) => {
                for (let s = 1; s <= totalSteps; s += 1) {
                    if (!validateStep(s)) {
                        event.preventDefault();
                        showStep(s);
                        return;
                    }
                }
            });

            const fillField = (field, value) => {
                if (!field || value == null || value === '') return;
                field.value = field.id === 'address' ? String(value).toUpperCase() : value;
                field.dispatchEvent(new Event('input', { bubbles: true }));
                field.dispatchEvent(new Event('change', { bubbles: true }));
            };

            document.getElementById('address')?.addEventListener('input', function () {
                const start = this.selectionStart;
                const end = this.selectionEnd;
                this.value = String(this.value || '').toUpperCase();
                if (typeof start === 'number' && typeof end === 'number') {
                    this.setSelectionRange(start, end);
                }
            });

            const scanDocument = async ({ input, endpoint, fieldName, statusEl, onSuccess }) => {
                const file = input?.files?.[0];
                if (!file) {
                    statusEl?.classList.add('hidden');
                    return;
                }

                if (!file.type.startsWith('image/')) {
                    setStatus(statusEl, 'PDF uploaded. Enter details manually, or upload a JPG/PNG photo for auto-scan.', 'warning');
                    return;
                }

                setStatus(statusEl, 'Reading your license… this can take a moment.', 'info');

                const body = new FormData();
                body.append(fieldName, file);
                body.append('_token', document.querySelector('meta[name="csrf-token"]')?.content || '');

                try {
                    const response = await fetch(endpoint, {
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

                    if (response.status === 429) {
                        setStatus(statusEl, data.message || 'Too many scan attempts. Please wait about a minute, then try again.', 'warning');
                        return;
                    }

                    if (data.address || data.full_name || data.driver_license_number || data.plate_number || (Array.isArray(data.warnings) && data.warnings.length)) {
                        onSuccess(data);
                        return;
                    }

                    setStatus(statusEl, data.message || 'Could not scan this photo. Please enter your details manually.', 'warning');
                } catch (error) {
                    setStatus(statusEl, 'Scan failed. Please enter your details manually.', 'error');
                }
            };

            const licenseInput = document.getElementById('driver_license');
            const licenseStatus = document.getElementById('license_scan_status');

            const applyLicenseScan = (data) => {
                fillField(document.getElementById('full_name'), data.full_name);
                fillField(document.getElementById('address'), data.address);
                fillField(document.getElementById('phone_number'), data.phone_number);
                fillField(document.getElementById('driver_license_number'), data.driver_license_number);
                if (data.plate_number) {
                    fillField(document.getElementById('plate_number'), data.plate_number);
                }

                const warnings = Array.isArray(data.warnings) ? data.warnings.filter(Boolean) : [];
                if (warnings.length > 0) {
                    setStatus(licenseStatus, `Filled what we could. ${warnings.join(' ')} Click Next to review.`, 'warning');
                } else {
                    setStatus(licenseStatus, 'Details filled from your license. Click Next to review your information.', 'success');
                }
            };

            const licensePreview = document.getElementById('license-preview');
            licenseInput?.addEventListener('change', () => {
                const file = licenseInput.files?.[0];
                if (file && file.type.startsWith('image/') && licensePreview) {
                    licensePreview.src = URL.createObjectURL(file);
                    licensePreview.classList.remove('hidden');
                } else if (licensePreview) {
                    licensePreview.classList.add('hidden');
                    licensePreview.removeAttribute('src');
                }

                scanDocument({
                    input: licenseInput,
                    endpoint: @json(route('register.scan-license')),
                    fieldName: 'driver_license',
                    statusEl: licenseStatus,
                    onSuccess: applyLicenseScan,
                });
            });

            const scanOrCr = (input, kind, statusEl) => {
                const file = input?.files?.[0];
                if (!file || !statusEl) return;
                if (!file.type.startsWith('image/')) {
                    setStatus(statusEl, 'PDF uploaded. Review the file before submitting — auto-check works best with a photo.', 'warning');
                    return;
                }

                setStatus(statusEl, 'Checking document…', 'info');
                const body = new FormData();
                body.append('document', file);
                body.append('kind', kind);
                body.append('plate_number', document.getElementById('plate_number')?.value || '');
                body.append('_token', document.querySelector('meta[name="csrf-token"]')?.content || '');

                fetch(@json(route('register.scan-orcr')), {
                    method: 'POST',
                    body,
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                }).then(async (response) => {
                    let data = {};
                    try {
                        data = await response.json();
                    } catch (parseError) {
                        data = {};
                    }
                    const warnings = Array.isArray(data.warnings) ? data.warnings.filter(Boolean) : [];
                    if (warnings.length) {
                        setStatus(statusEl, warnings.join(' '), 'warning');
                    } else {
                        setStatus(statusEl, data.message || 'Document looks like an LTO file. Please still review before submitting.', 'success');
                    }
                    if (data.plate_number && !document.getElementById('plate_number')?.value) {
                        fillField(document.getElementById('plate_number'), data.plate_number);
                    }
                }).catch(() => {
                    setStatus(statusEl, 'Could not auto-check this file. Review it manually before submitting.', 'warning');
                });
            };

            document.getElementById('lto_or_photo')?.addEventListener('change', function () {
                scanOrCr(this, 'or', document.getElementById('or_scan_status'));
            });
            document.getElementById('lto_cr_photo')?.addEventListener('change', function () {
                scanOrCr(this, 'cr', document.getElementById('cr_scan_status'));
            });

            const countdownEl = document.getElementById('temp-countdown');
            const expiresAt = @json(optional($converting?->temporary_expires_at)->toIso8601String());
            if (countdownEl && expiresAt) {
                const tick = () => {
                    const remaining = new Date(expiresAt).getTime() - Date.now();
                    if (remaining <= 0) {
                        countdownEl.textContent = 'expired — register now to restore access after approval';
                        return;
                    }
                    const hours = Math.floor(remaining / 3600000);
                    const minutes = Math.floor((remaining % 3600000) / 60000);
                    countdownEl.textContent = hours + 'h ' + minutes + 'm remaining';
                };
                tick();
                window.setInterval(tick, 30000);
            }

            showStep(currentStep);
        });
    </script>
@endpush
