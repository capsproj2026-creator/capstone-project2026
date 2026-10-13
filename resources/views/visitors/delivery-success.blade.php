@extends('layouts.guest')

@section('title', 'Delivery Checked In - Smart Campus VMS')

@section('use_campus_bg', '1')

@section('card_width', 'max-w-2xl')

@section('content')
    @php
        $visitorName = $visitor->displayName();
        $rows = [
            ['label' => 'Full Name', 'value' => $visitorName],
            ['label' => 'Mobile Number', 'value' => $visitor->contact_number ?: '—'],
            ['label' => 'Company', 'value' => $visitor->notes ?: '—'],
            ['label' => 'Delivering To', 'value' => $visitor->office_to_visit ?: '—'],
            ['label' => 'Motorcycle Plate', 'value' => $visitor->plate_number ?: '—'],
        ];
    @endphp

    <div class="guest-card w-full overflow-hidden rounded-2xl border border-white/30 bg-white/95 shadow-2xl backdrop-blur-sm">
        <div class="relative overflow-hidden bg-gradient-to-br from-emerald-600 via-emerald-700 to-slate-900 px-6 py-8 text-center text-white">
            <div class="relative">
                <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-white/15">
                    <i data-lucide="circle-check" class="h-8 w-8"></i>
                </div>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-emerald-100">Delivery</p>
                <h1 class="mt-2 text-2xl font-bold sm:text-3xl">Thank you, {{ $visitorName }}</h1>
                <p class="mt-2 text-sm text-emerald-100">Show this screen to the guard at the booth.</p>
            </div>
        </div>

        <div class="w-full p-6 sm:p-8">
            <div class="rounded-xl border-2 border-emerald-300 bg-emerald-50 px-4 py-5 text-center shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">For the guard</p>
                <p class="mt-1 text-lg font-bold text-emerald-950 sm:text-xl">{{ $visitorName }}</p>
                <p class="mt-3 text-sm font-medium text-emerald-800">Reference code</p>
                <p class="mt-1 break-all font-mono text-2xl font-bold tracking-wide text-emerald-950 sm:text-3xl">{{ $visitor->confirmation_code }}</p>
                <p class="mt-3 inline-flex items-center rounded-full bg-emerald-600 px-3 py-1 text-xs font-bold uppercase tracking-wide text-white">
                    Waiting at the gate
                </p>
            </div>

            <div class="mt-6 overflow-hidden rounded-xl border border-gray-200">
                <div class="border-b border-gray-100 bg-gray-50 px-4 py-3">
                    <h2 class="text-sm font-semibold text-gray-900">Delivery details</h2>
                    <p class="mt-0.5 text-xs text-gray-500">Information submitted for this delivery</p>
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
                    <li>Stay at the booth and show this confirmation screen.</li>
                    <li>The guard will check your ID and plate.</li>
                    <li>Leave through the gate when the delivery is done.</li>
                </ol>
            </div>
        </div>
    </div>
@endsection
