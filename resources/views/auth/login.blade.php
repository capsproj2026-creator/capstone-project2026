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

            @if (! empty($googleSignInEnabled))
                <div class="relative my-6">
                    <div class="absolute inset-0 flex items-center" aria-hidden="true">
                        <div class="w-full border-t border-gray-200"></div>
                    </div>
                    <div class="relative flex justify-center text-xs uppercase tracking-wide">
                        <span class="bg-white/95 px-3 text-gray-400">Or</span>
                    </div>
                </div>

                <a
                    href="{{ route('auth.google') }}"
                    class="flex w-full items-center justify-center gap-3 rounded-lg border border-gray-300 bg-white px-4 py-3 text-sm font-medium text-gray-700 shadow-sm transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" class="h-5 w-5 shrink-0" aria-hidden="true">
                        <path fill="#FFC107" d="M43.611 20.083H42V20H24v8h11.303C33.654 32.657 29.227 36 24 36c-6.627 0-12-5.373-12-12s5.373-12 12-12c3.059 0 5.842 1.154 7.961 3.039l5.657-5.657C34.046 6.053 29.268 4 24 4 12.955 4 4 12.955 4 24s8.955 20 20 20 20-8.955 20-20c0-1.341-.138-2.65-.389-3.917z"/>
                        <path fill="#FF3D00" d="M6.306 14.691l6.571 4.819C14.655 15.108 18.961 12 24 12c3.059 0 5.842 1.154 7.961 3.039l5.657-5.657C34.046 6.053 29.268 4 24 4 16.318 4 9.656 8.337 6.306 14.691z"/>
                        <path fill="#4CAF50" d="M24 44c5.166 0 9.86-1.977 13.409-5.192l-6.19-5.238C29.211 35.091 26.715 36 24 36c-5.202 0-9.619-3.317-11.283-7.946l-6.522 5.025C9.505 39.556 16.227 44 24 44z"/>
                        <path fill="#1976D2" d="M43.611 20.083H42V20H24v8h11.303a12.04 12.04 0 0 1-4.087 5.571l.003-.002 6.19 5.238C36.971 39.205 44 34 44 24c0-1.341-.138-2.65-.389-3.917z"/>
                    </svg>
                    Continue with Google
                </a>
                <p class="mt-2 text-center text-xs text-gray-500">Use your @my.cspc.edu.ph Google account. You must already be registered.</p>
            @endif

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
