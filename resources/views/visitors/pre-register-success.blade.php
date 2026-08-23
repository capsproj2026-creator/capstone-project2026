@extends('layouts.guest')

@section('title', 'Pre-Registration Complete - Smart Campus VMS')

@section('use_campus_bg', '1')

@section('card_width', 'max-w-md')

@section('content')
    <div class="w-full overflow-hidden rounded-2xl border border-white/30 bg-white/95 shadow-2xl backdrop-blur-sm">
        <div class="relative overflow-hidden bg-gradient-to-br from-emerald-600 via-emerald-700 to-slate-900 px-6 py-8 text-center text-white">
            <div class="relative">
                <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-white/15">
                    <i data-lucide="circle-check" class="h-8 w-8"></i>
                </div>
                <h1 class="text-2xl font-bold">You are pre-registered</h1>
                <p class="mt-1 text-sm text-emerald-100">Go to the guard booth and show this code</p>
            </div>
        </div>

        <div class="w-full p-6 text-center sm:p-8">
            <p class="text-sm font-medium text-gray-500">Your reference code</p>
            <p class="mt-2 break-all font-mono text-2xl font-bold tracking-wide text-gray-900 sm:text-3xl">{{ $confirmationCode }}</p>

            <div class="mt-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-left text-sm text-amber-900">
                <p class="font-semibold">Next steps</p>
                <ol class="mt-2 list-decimal space-y-1 pl-5">
                    <li>Proceed to the guard booth.</li>
                    <li>Say you already pre-registered and give this code.</li>
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
