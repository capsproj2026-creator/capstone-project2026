@extends('layouts.portal')

@section('title', 'Register Visitor')

@section('content')
    @include('partials.shell.page-header', [
        'title' => 'Register Visitor',
        'subtitle' => 'Register a campus visitor and optionally assign a temporary RFID card',
    ])

    @if ($errors->any())
        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            <ul class="list-disc space-y-1 pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="mb-6 rounded-xl border border-blue-100 bg-blue-50/60 p-5 shadow-sm">
        <div class="flex items-start gap-4">
            <div class="shrink-0 rounded-lg border border-white bg-white p-2 shadow-sm">
                {!! $preRegisterQrSvg !!}
            </div>
            <div class="min-w-0 flex-1">
                <h2 class="text-sm font-semibold text-gray-900">Visitor self pre-registration</h2>
                <p class="mt-1 text-sm text-gray-600">Print this QR at the entrance. Visitors scan it to submit their details before reaching the booth.</p>
                <p class="mt-2 break-all font-mono text-xs text-gray-500">{{ $preRegisterUrl }}</p>
                <a href="{{ $preRegisterQrUrl }}" download="visitor-pre-register-qr.svg" class="mt-3 inline-flex items-center gap-1.5 text-sm font-semibold text-blue-700 hover:underline">
                    <i data-lucide="download" class="h-4 w-4"></i>
                    Download QR (SVG)
                </a>
            </div>
        </div>
    </div>

    <form method="POST" action="{{ route($routePrefix.'.visitors.store') }}" class="space-y-6">
        @csrf

        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
            <h2 class="text-base font-semibold text-gray-900">Personal Information</h2>
            <p class="mt-0.5 text-sm text-gray-500">Visitor identity and contact details</p>
            <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700">First Name <span class="text-red-500">*</span></label>
                    <input type="text" name="first_name" value="{{ old('first_name') }}" required
                        class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700">Middle Name</label>
                    <input type="text" name="middle_name" value="{{ old('middle_name') }}"
                        class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700">Last Name <span class="text-red-500">*</span></label>
                    <input type="text" name="last_name" value="{{ old('last_name') }}" required
                        class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700">Contact Number <span class="text-red-500">*</span></label>
                    <input type="text" name="contact_number" value="{{ old('contact_number') }}" required
                        class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                </div>
                <div class="sm:col-span-2">
                    <label class="mb-1.5 block text-sm font-medium text-gray-700">Email</label>
                    <input type="email" name="email" value="{{ old('email') }}"
                        class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
            <h2 class="text-base font-semibold text-gray-900">Visit Information</h2>
            <p class="mt-0.5 text-sm text-gray-500">Purpose and expected departure</p>
            <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="mb-1.5 block text-sm font-medium text-gray-700">Purpose of Visit <span class="text-red-500">*</span></label>
                    <input type="text" name="purpose" value="{{ old('purpose') }}" required
                        class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700">Office / Person to Visit <span class="text-red-500">*</span></label>
                    <input type="text" name="office_to_visit" value="{{ old('office_to_visit') }}" required
                        class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700">Expected Exit Time <span class="text-red-500">*</span></label>
                    <input type="datetime-local" name="expected_exit_at" value="{{ old('expected_exit_at') }}" required
                        class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
            <h2 class="text-base font-semibold text-gray-900">Vehicle Information</h2>
            <p class="mt-0.5 text-sm text-gray-500">Plate and vehicle details for gate / YOLO matching</p>
            <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700">Plate Number <span class="text-red-500">*</span></label>
                    <input type="text" name="plate_number" value="{{ old('plate_number') }}" required
                        class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm uppercase focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700">Vehicle Type <span class="text-red-500">*</span></label>
                    <select name="vehicle_id" required
                        class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                        <option value="">Select type</option>
                        @foreach ($vehicles as $vehicle)
                            <option value="{{ $vehicle->id }}" @selected((string) old('vehicle_id') === (string) $vehicle->id)>{{ $vehicle->vehicle_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700">Vehicle Color <span class="text-red-500">*</span></label>
                    <input type="text" name="vehicle_color" value="{{ old('vehicle_color') }}" required
                        class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
            <h2 class="text-base font-semibold text-gray-900">Temporary RFID</h2>
            <p class="mt-0.5 text-sm text-gray-500">Leave blank for “No RFID Assigned”. You can assign later from Active Visitors.</p>
            <div class="mt-5">
                <label class="mb-1.5 block text-sm font-medium text-gray-700">RFID UID</label>
                <div class="relative">
                    <i data-lucide="hash" class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400"></i>
                    <input type="text" name="rfid_uid" value="{{ old('rfid_uid') }}" placeholder="No RFID Assigned (optional)"
                        class="w-full rounded-lg border border-gray-200 py-2.5 pl-10 pr-3 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                </div>
            </div>
        </div>

        <div class="flex flex-wrap items-center justify-end gap-3">
            <a href="{{ route($routePrefix.'.visitors.active') }}" class="rounded-lg border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50">Cancel</a>
            <button type="submit" class="rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-700">Register Visitor</button>
        </div>
    </form>
@endsection
