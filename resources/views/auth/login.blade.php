@extends('layouts.guest')

@section('title', 'Sign In - Smart Campus VMS')

@section('use_campus_bg', '1')

@section('card_width', 'max-w-md')

@section('content')
    <div class="w-full overflow-hidden rounded-2xl border border-white/30 bg-white/95 shadow-2xl backdrop-blur-sm">
        <div class="relative overflow-hidden bg-gradient-to-br from-blue-700 via-blue-800 to-slate-900 px-6 py-8 text-center text-white">
            <div class="pointer-events-none absolute inset-0 opacity-25" style="background-image: radial-gradient(circle at 20% 20%, #fff 0, transparent 40%), radial-gradient(circle at 80% 0%, #93c5fd 0, transparent 35%), radial-gradient(circle at 50% 100%, #1e3a8a 0, transparent 45%);"></div>
            <div class="relative">
                <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center">
                    @if (is_file(public_path('images/cspc-logo.png')))
                        <img src="{{ asset('images/cspc-logo.png') }}" alt="CSPC" class="h-16 w-16 object-contain drop-shadow-lg">
                    @else
                        <i data-lucide="parking-square" class="h-6 w-6"></i>
                    @endif
                </div>
                <h1 class="text-2xl font-bold">Welcome back</h1>
                <p class="mt-1 text-sm text-blue-100">Sign in to your campus vehicle portal</p>
            </div>
        </div>

        <div class="w-full p-6 sm:p-8">
            @if (session('error'))
                <div class="mb-4 flex gap-2 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    <i data-lucide="alert-circle" class="mt-0.5 h-4 w-4 shrink-0"></i>
                    <span>{{ session('error') }}</span>
                </div>
            @endif

            @if (session('success'))
                <div class="mb-4 flex gap-2 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                    <i data-lucide="circle-check" class="mt-0.5 h-4 w-4 shrink-0"></i>
                    <span>{{ session('success') }}</span>
                </div>
            @endif

            @if ($errors->any() && ! session('error'))
                <div class="mb-4 flex gap-2 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    <i data-lucide="alert-circle" class="mt-0.5 h-4 w-4 shrink-0"></i>
                    <span>{{ $errors->first() }}</span>
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}" class="w-full space-y-5">
                @csrf
                <x-auth.input class="w-full" label="Email Address" name="email" type="email" required placeholder="name@my.cspc.edu.ph" />
                <x-auth.password-input class="w-full" name="password" label="Password" autocomplete="current-password" placeholder="Your password" />
                <button type="submit" class="flex w-full items-center justify-center gap-2 rounded-lg bg-blue-600 py-3 text-sm font-semibold text-white transition-colors hover:bg-blue-700">
                    <i data-lucide="log-in" class="h-4 w-4"></i>
                    Sign In
                </button>
            </form>

            <p class="mt-6 text-center text-sm text-gray-500">
                Don't have an account?
                <a href="{{ route('register') }}" class="font-semibold text-blue-600 hover:underline">Register here</a>
            </p>
            <p class="mt-2 text-center text-sm text-gray-500">
                Need to verify your email?
                <a href="{{ route('login') }}" class="font-semibold text-blue-600 hover:underline">Sign in</a>
                to resend the verification link.
            </p>
        </div>
    </div>
@endsection
