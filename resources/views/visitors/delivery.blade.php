@extends('layouts.guest')

@section('title', 'Delivery Check-in - Smart Campus VMS')

@section('use_campus_bg', '1')

@section('card_width', 'max-w-2xl')

@section('content')
    <div class="guest-card w-full overflow-hidden rounded-2xl border border-white/30 bg-white/95 shadow-2xl backdrop-blur-sm">
        <div class="relative overflow-hidden bg-gradient-to-br from-[#1A365D] via-[#122844] to-slate-900 px-6 py-8 text-center text-white">
            <div class="pointer-events-none absolute inset-0 opacity-25" style="background-image: radial-gradient(circle at 20% 20%, #fff 0, transparent 40%), radial-gradient(circle at 80% 0%, #93c5fd 0, transparent 35%);"></div>
            <div class="relative">
                <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-white/10">
                    <i data-lucide="bike" class="h-8 w-8"></i>
                </div>
                <h1 class="text-2xl font-bold">Delivery Registration</h1>
                <p class="mt-1 text-sm text-blue-100">Check in at the gate. The guard will see you in a few seconds.</p>
            </div>
        </div>

        <div class="w-full p-6 sm:p-8">
            @if ($errors->any() && ! old('returning'))
                <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    <ul class="list-disc space-y-1 pl-5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('delivery.store') }}" class="space-y-6" autocomplete="off" id="delivery-form">
                @csrf
                <input type="text" name="website" value="" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true">

                <div class="space-y-4 rounded-xl border border-gray-200 bg-gray-50/50 p-4 sm:p-5">
                    <h2 class="text-sm font-semibold text-gray-900">Personal Information</h2>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700">Full Name <span class="text-red-500">*</span></label>
                            <input type="text" name="full_name" value="{{ old('full_name') }}" required class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700">Mobile Number <span class="text-red-500">*</span></label>
                            <input type="text" name="contact_number" value="{{ old('contact_number') }}" required class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                        </div>
                    </div>
                </div>

                <div class="space-y-4 rounded-xl border border-gray-200 bg-gray-50/50 p-4 sm:p-5">
                    <h2 class="text-sm font-semibold text-gray-900">Delivery Information</h2>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700">Company <span class="text-red-500">*</span></label>
                            <select name="company" required class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                                <option value="">Select company</option>
                                @foreach ($companies as $company)
                                    <option value="{{ $company }}" @selected(old('company') === $company)>{{ $company }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700">Company Name, if Other</label>
                            <input type="text" name="company_other" value="{{ old('company_other') }}" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="mb-1.5 block text-sm font-medium text-gray-700">Delivering To <span class="text-red-500">*</span></label>
                            <input type="text" name="recipient" value="{{ old('recipient') }}" required placeholder="Office or person" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-900 placeholder:text-gray-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                        </div>
                    </div>
                </div>

                <div class="space-y-4 rounded-xl border border-gray-200 bg-gray-50/50 p-4 sm:p-5">
                    <h2 class="text-sm font-semibold text-gray-900">Vehicle Information</h2>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700">Motorcycle Plate <span class="text-red-500">*</span></label>
                        <input type="text" name="plate_number" value="{{ old('returning') ? '' : old('plate_number') }}" required class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm uppercase text-gray-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                    </div>
                </div>

                <p class="text-xs text-gray-500">Show the reference code to the guard at the booth. Leave through the gate when the delivery is done.</p>

                <button type="submit" class="flex w-full items-center justify-center gap-2 rounded-lg bg-[#5D9FD1] py-3 text-sm font-semibold text-white hover:bg-[#4A8FC4]">
                    <i data-lucide="send" class="h-4 w-4"></i>
                    Submit Check-in
                </button>
            </form>

            <div class="mt-6 space-y-3 rounded-xl border border-gray-200 bg-gray-50/50 p-4 sm:p-5">
                <h2 class="text-sm font-semibold text-gray-900">Been here before?</h2>
                <p class="text-xs text-gray-500">Enter the same motorcycle plate. Your name and company are reused.</p>
                @if ($errors->has('plate_number') && old('returning'))
                    <p class="text-sm text-red-700">{{ $errors->first('plate_number') }}</p>
                @endif
                <form method="POST" action="{{ route('delivery.returning') }}" class="flex flex-col gap-2 sm:flex-row">
                    @csrf
                    <input type="hidden" name="returning" value="1">
                    <input type="text" name="plate_number" value="{{ old('returning') ? old('plate_number') : '' }}" required placeholder="Plate number" class="min-w-0 flex-1 rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm uppercase text-gray-900 placeholder:text-gray-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                    <button type="submit" class="rounded-lg bg-[#1A365D] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#122844]">Find me</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        window.addEventListener('pageshow', function (event) {
            var nav = performance.getEntriesByType('navigation')[0];
            var fromHistory = event.persisted || (nav && nav.type === 'back_forward');
            if (!fromHistory) {
                return;
            }
            document.querySelectorAll('input, select, textarea').forEach(function (field) {
                if (field.type === 'hidden' || field.name === '_token') {
                    return;
                }
                if (field.tagName === 'SELECT') {
                    field.selectedIndex = 0;
                    return;
                }
                field.value = '';
            });
        });
    </script>
@endsection
