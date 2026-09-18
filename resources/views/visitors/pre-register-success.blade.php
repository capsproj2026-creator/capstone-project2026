@extends('layouts.guest')

@section('title', 'Pre-Registration Complete - Smart Campus VMS')

@section('use_campus_bg', '1')

@section('card_width', 'max-w-2xl')

@section('content')
    @php
        $vehicleName = $visitor->vehicleType?->vehicle_name ?? '—';
        $exitAt = $visitor->expected_exit_at
            ? ph_datetime($visitor->expected_exit_at, 'M j, Y · g:i A')
            : '—';
        $rows = [
            ['label' => 'Full Name', 'value' => $visitor->displayName()],
            ['label' => 'Contact Number', 'value' => $visitor->contact_number ?: '—'],
            ['label' => 'Email', 'value' => $visitor->email ?: '—'],
            ['label' => 'Purpose of Visit', 'value' => $visitor->purpose ?: '—'],
            ['label' => 'Office / Person to Visit', 'value' => $visitor->office_to_visit ?: '—'],
            ['label' => 'Expected Exit', 'value' => $exitAt],
            ['label' => 'Plate Number', 'value' => $visitor->plate_number ?: '—'],
            ['label' => 'Vehicle Type', 'value' => $vehicleName],
            ['label' => 'Vehicle Color', 'value' => $visitor->vehicle_color ?: '—'],
        ];
    @endphp

    <div class="w-full overflow-hidden rounded-2xl border border-white/30 bg-white/95 shadow-2xl backdrop-blur-sm">
        <div class="relative overflow-hidden bg-gradient-to-br from-emerald-600 via-emerald-700 to-slate-900 px-6 py-8 text-center text-white">
            <div class="relative">
                <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-white/15">
                    <i data-lucide="circle-check" class="h-8 w-8"></i>
                </div>
                <h1 class="text-2xl font-bold">You are pre-registered</h1>
                <p class="mt-1 text-sm text-emerald-100">Review your details below, then show this code at the guard booth</p>
            </div>
        </div>

        <div class="w-full p-6 sm:p-8">
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-4 text-center">
                <p class="text-sm font-medium text-emerald-800">Your reference code</p>
                <p class="mt-2 break-all font-mono text-2xl font-bold tracking-wide text-emerald-950 sm:text-3xl">{{ $confirmationCode }}</p>
            </div>

            <div class="mt-6 overflow-hidden rounded-xl border border-gray-200">
                <div class="border-b border-gray-100 bg-gray-50 px-4 py-3">
                    <h2 class="text-sm font-semibold text-gray-900">Registration details</h2>
                    <p class="mt-0.5 text-xs text-gray-500">Information you submitted</p>
                </div>
                <dl class="divide-y divide-gray-100">
                    @foreach ($rows as $row)
                        <div class="grid grid-cols-1 gap-1 px-4 py-3 sm:grid-cols-3 sm:gap-4">
                            <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 sm:text-sm sm:normal-case sm:tracking-normal">{{ $row['label'] }}</dt>
                            <dd class="text-sm font-semibold text-gray-900 sm:col-span-2">{{ $row['value'] }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>

            <div class="mt-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-left text-sm text-amber-900">
                <p class="font-semibold">Next steps</p>
                <ol class="mt-2 list-decimal space-y-1 pl-5">
                    <li>Proceed to the guard booth.</li>
                    <li>Say you already pre-registered and give this reference code.</li>
                    <li>The guard will verify your ID and assign a temporary RFID card.</li>
                </ol>
            </div>

            <a href="{{ route('visitor.pre-register') }}" class="mt-6 inline-flex items-center gap-2 text-sm font-semibold text-blue-600 hover:underline">
                <i data-lucide="plus" class="h-4 w-4"></i>
                Register another visitor
            </a>
        </div>
    </div>
@endsection
