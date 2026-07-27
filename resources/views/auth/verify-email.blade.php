@extends('layouts.guest')

@section('title', 'Verify Email - Smart Campus VMS')

@section('card_width', 'max-w-md')

@section('content')
    <div class="w-full overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-lg">
        <div class="bg-gradient-to-br from-blue-600 to-blue-800 px-6 py-8 text-center text-white">
            <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-white/20">
                <i data-lucide="mail-check" class="h-6 w-6"></i>
            </div>
            <h1 class="text-2xl font-bold">Registration Successful!</h1>
            <p class="mt-1 text-sm text-blue-100">One more step before you can continue</p>
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

            <div class="mb-6 rounded-xl border border-blue-100 bg-blue-50/60 px-4 py-4 text-sm text-gray-700">
                <p class="font-medium text-gray-900">We've sent a verification email to:</p>
                <p class="mt-1 break-all font-semibold text-blue-700">{{ $email }}</p>
                <p class="mt-3 text-gray-600">
                    Please check your inbox and click the verification link before logging in.
                </p>
            </div>

            <form method="POST" action="{{ route('verification.send') }}" class="space-y-3">
                @csrf
                <button type="submit" class="flex w-full items-center justify-center gap-2 rounded-lg bg-blue-600 py-3 text-sm font-semibold text-white transition-colors hover:bg-blue-700">
                    <i data-lucide="send" class="h-4 w-4"></i>
                    Resend Verification Email
                </button>
            </form>

            <form method="POST" action="{{ route('logout') }}" class="mt-3">
                @csrf
                <button type="submit" class="flex w-full items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-3 text-sm font-semibold text-gray-700 transition hover:bg-gray-50">
                    <i data-lucide="log-out" class="h-4 w-4"></i>
                    Logout
                </button>
            </form>

            <p class="mt-6 text-center text-xs text-gray-500">
                Didn't get the email? Check your spam folder, then use Resend above.
            </p>
        </div>
    </div>
@endsection
