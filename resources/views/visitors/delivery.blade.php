@extends('layouts.guest')

@section('title', 'Delivery Check-in - Smart Campus VMS')

@section('use_campus_bg', '1')

@section('card_width', 'max-w-xl')

@section('content')
    <div class="guest-card w-full overflow-hidden rounded-2xl border border-white/30 bg-white/95 shadow-2xl backdrop-blur-sm">
        <div class="relative overflow-hidden bg-gradient-to-br from-amber-600 via-amber-700 to-slate-900 px-6 py-8 text-center text-white">
            <div class="relative">
                <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-white/10">
                    <i data-lucide="bike" class="h-8 w-8"></i>
                </div>
                <h1 class="text-2xl font-bold">Delivery check-in</h1>
                <p class="mt-1 text-sm text-amber-100">Five details. The guard will see you in a few seconds.</p>
            </div>
        </div>

        <div class="w-full space-y-6 p-6 sm:p-8">
            @if ($errors->any() && ! old('returning'))
                <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    <ul class="list-disc space-y-1 pl-5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('delivery.store') }}" class="space-y-4" autocomplete="off" id="delivery-form">
                @csrf
                <input type="text" name="website" value="" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true">

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700">Full name <span class="text-red-500">*</span></label>
                    <input type="text" name="full_name" value="{{ old('full_name') }}" required class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-500/20">
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700">Mobile number <span class="text-red-500">*</span></label>
                    <input type="text" name="contact_number" value="{{ old('contact_number') }}" required class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-500/20">
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700">Company <span class="text-red-500">*</span></label>
                    <select name="company" required class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-500/20">
                        <option value="">Select company</option>
                        @foreach ($companies as $company)
                            <option value="{{ $company }}" @selected(old('company') === $company)>{{ $company }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700">Company name, if Other</label>
                    <input type="text" name="company_other" value="{{ old('company_other') }}" class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-500/20">
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700">Motorcycle plate <span class="text-red-500">*</span></label>
                    <input type="text" name="plate_number" value="{{ old('plate_number') }}" required class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm uppercase focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-500/20">
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700">Delivering to <span class="text-red-500">*</span></label>
                    <input type="text" name="recipient" value="{{ old('recipient') }}" required placeholder="Office or person" class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-500/20">
                </div>

                <button type="submit" class="delivery-checkin flex w-full items-center justify-center gap-2 rounded-lg py-3 text-sm font-semibold">
                    <i data-lucide="send" class="h-4 w-4"></i>
                    Check in
                </button>
            </form>

            <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 guest-card">
                <p class="text-sm font-semibold text-gray-900">Been here before?</p>
                <p class="mt-1 text-xs text-gray-500">Enter the same motorcycle plate. Your name and company are reused.</p>
                @if ($errors->has('plate_number') && old('returning'))
                    <p class="mt-2 text-sm text-red-700">{{ $errors->first('plate_number') }}</p>
                @endif
                <form method="POST" action="{{ route('delivery.returning') }}" class="mt-3 flex gap-2">
                    @csrf
                    <input type="hidden" name="returning" value="1">
                    <input type="text" name="plate_number" value="{{ old('returning') ? old('plate_number') : '' }}" required placeholder="Plate number" class="min-w-0 flex-1 rounded-lg border border-gray-200 px-3 py-2.5 text-sm uppercase">
                    <button type="submit" class="rounded-lg bg-slate-900 px-3 py-2.5 text-sm font-semibold text-white hover:bg-slate-800">Find me</button>
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
