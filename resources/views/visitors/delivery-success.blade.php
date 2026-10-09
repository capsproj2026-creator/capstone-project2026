@extends('layouts.guest')

@section('title', 'Delivery Checked In - Smart Campus VMS')

@section('use_campus_bg', '1')

@section('card_width', 'max-w-xl')

@section('content')
    <div class="guest-card w-full overflow-hidden rounded-2xl border border-white/30 bg-white/95 shadow-2xl backdrop-blur-sm">
        <div class="bg-gradient-to-br from-amber-600 via-amber-700 to-slate-900 px-6 py-8 text-center text-white">
            <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-white/15">
                <i data-lucide="circle-check" class="h-8 w-8"></i>
            </div>
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-amber-100">Delivery</p>
            <h1 class="mt-2 text-2xl font-bold">Show this to the guard</h1>
            <p class="mt-2 text-sm text-amber-100">{{ $visitor->displayName() }}</p>
        </div>
        <div class="space-y-4 p-6 sm:p-8">
            <div class="rounded-xl border-2 border-amber-300 bg-amber-50 px-4 py-5 text-center">
                <p class="text-sm font-medium text-amber-800">Reference code</p>
                <p class="mt-1 font-mono text-2xl font-bold tracking-wide text-amber-950">{{ $visitor->confirmation_code }}</p>
                <p class="mt-3 inline-flex rounded-full bg-amber-600 px-3 py-1 text-xs font-bold uppercase tracking-wide text-white">Waiting at the gate</p>
            </div>
            <dl class="divide-y divide-gray-100 rounded-xl border border-gray-200">
                @foreach ([
                    'Company' => $visitor->notes ?: '—',
                    'Plate' => $visitor->plate_number,
                    'Delivering to' => $visitor->office_to_visit,
                    'Mobile' => $visitor->contact_number,
                ] as $label => $value)
                    <div class="grid grid-cols-1 gap-1 px-4 py-3 sm:grid-cols-3">
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ $label }}</dt>
                        <dd class="text-sm font-semibold text-gray-900 sm:col-span-2">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>
            <p class="text-sm text-gray-600">Stay at the booth until the guard checks your ID and plate. Leave through the gate when the delivery is done.</p>
        </div>
    </div>
@endsection
