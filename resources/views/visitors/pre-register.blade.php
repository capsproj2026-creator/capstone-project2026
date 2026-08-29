@extends('layouts.guest')

@section('title', 'Visitor Pre-Registration - Smart Campus VMS')

@section('use_campus_bg', '1')

@section('card_width', 'max-w-2xl')

@section('content')
    <div class="w-full overflow-hidden rounded-2xl border border-white/30 bg-white/95 shadow-2xl backdrop-blur-sm">
        <div class="relative overflow-hidden bg-gradient-to-br from-[#1A365D] via-[#122844] to-slate-900 px-6 py-8 text-center text-white">
            <div class="pointer-events-none absolute inset-0 opacity-25" style="background-image: radial-gradient(circle at 20% 20%, #fff 0, transparent 40%), radial-gradient(circle at 80% 0%, #93c5fd 0, transparent 35%);"></div>
            <div class="relative">
                <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-white/10">
                    <i data-lucide="clipboard-list" class="h-8 w-8"></i>
                </div>
                <h1 class="text-2xl font-bold">Visitor Registration</h1>
                <p class="mt-1 text-sm text-blue-100">Fill this form before the booth, or after you enter campus — within {{ (int) config('services.visitor_pre_register.post_entry_hours', 5) }} hours of entry</p>
            </div>
        </div>

        <div class="w-full p-6 sm:p-8">
            @if ($errors->any())
                <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    <ul class="list-disc space-y-1 pl-5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('visitor.pre-register.store') }}" class="space-y-6">
                @csrf
                <input type="text" name="website" value="" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true">

                <div class="space-y-4 rounded-xl border border-gray-200 bg-gray-50/50 p-4 sm:p-5">
                    <h2 class="text-sm font-semibold text-gray-900">Personal Information</h2>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700">First Name <span class="text-red-500">*</span></label>
                            <input type="text" name="first_name" value="{{ old('first_name') }}" required class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700">Middle Name</label>
                            <input type="text" name="middle_name" value="{{ old('middle_name') }}" class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700">Last Name <span class="text-red-500">*</span></label>
                            <input type="text" name="last_name" value="{{ old('last_name') }}" required class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700">Contact Number <span class="text-red-500">*</span></label>
                            <input type="text" name="contact_number" value="{{ old('contact_number') }}" required class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="mb-1.5 block text-sm font-medium text-gray-700">Email</label>
                            <input type="email" name="email" value="{{ old('email') }}" class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                        </div>
                    </div>
                </div>

                <div class="space-y-4 rounded-xl border border-gray-200 bg-gray-50/50 p-4 sm:p-5">
                    <h2 class="text-sm font-semibold text-gray-900">Visit Information</h2>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label class="mb-1.5 block text-sm font-medium text-gray-700">Purpose of Visit <span class="text-red-500">*</span></label>
                            <input type="text" name="purpose" value="{{ old('purpose') }}" required class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700">Office / Person to Visit <span class="text-red-500">*</span></label>
                            <input type="text" name="office_to_visit" value="{{ old('office_to_visit') }}" required class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700">Expected Exit Time <span class="text-red-500">*</span></label>
                            <input type="datetime-local" name="expected_exit_at" value="{{ old('expected_exit_at') }}" required class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                        </div>
                    </div>
                </div>

                <div class="space-y-4 rounded-xl border border-gray-200 bg-gray-50/50 p-4 sm:p-5">
                    <h2 class="text-sm font-semibold text-gray-900">Vehicle Information</h2>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700">Plate Number <span class="text-red-500">*</span></label>
                            <input type="text" name="plate_number" value="{{ old('plate_number') }}" required class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm uppercase focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700">Vehicle Type <span class="text-red-500">*</span></label>
                            <select name="vehicle_id" required class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                                <option value="">Select type</option>
                                @foreach ($vehicles as $vehicle)
                                    <option value="{{ $vehicle->id }}" @selected((string) old('vehicle_id') === (string) $vehicle->id)>{{ $vehicle->vehicle_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700">Vehicle Color <span class="text-red-500">*</span></label>
                            <input type="text" name="vehicle_color" value="{{ old('vehicle_color') }}" required class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                        </div>
                    </div>
                </div>

                <p class="text-xs text-gray-500">Before arrival, show the reference code at the booth. If you are already on campus, submit this form with the same plate number within {{ (int) config('services.visitor_pre_register.post_entry_hours', 5) }} hours of entry.</p>

                <button type="submit" class="flex w-full items-center justify-center gap-2 rounded-lg bg-[#5D9FD1] py-3 text-sm font-semibold text-white hover:bg-[#4A8FC4]">
                    <i data-lucide="send" class="h-4 w-4"></i>
                    Submit Pre-Registration
                </button>
            </form>
        </div>
    </div>
@endsection
