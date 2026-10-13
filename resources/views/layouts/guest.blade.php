<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <script>
        (function () {
            try {
                var theme = localStorage.getItem('portal-theme') || 'system';
                var prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                if (theme === 'dark' || (theme !== 'light' && prefersDark)) {
                    document.documentElement.classList.add('dark');
                }
            } catch (e) { /* ignore */ }
        })();
    </script>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Smart Campus VMS')</title>
    <style>
        html.dark .guest-card {
            background-color: #171d28 !important;
            border-color: rgba(255, 255, 255, 0.08) !important;
            color: #e7edf5;
        }
        html.dark { color-scheme: dark; }
        html.dark .guest-card .bg-white\/95,
        html.dark .guest-card .bg-white,
        html.dark .guest-card .bg-gray-50,
        html.dark .guest-card .bg-gray-50\/50,
        html.dark .guest-card .bg-amber-50,
        html.dark .guest-card .bg-emerald-50,
        html.dark .guest-card .bg-red-50,
        html.dark .guest-card .bg-green-50 {
            background-color: #121820 !important;
        }
        html.dark .guest-card,
        html.dark .guest-card .text-gray-900,
        html.dark .guest-card .text-gray-800,
        html.dark .guest-card .text-emerald-950,
        html.dark .guest-card .text-amber-950,
        html.dark .guest-card .text-amber-900 {
            color: #e7edf5;
        }
        html.dark .guest-card .text-gray-700,
        html.dark .guest-card .text-gray-600,
        html.dark .guest-card .text-gray-500,
        html.dark .guest-card .text-emerald-800,
        html.dark .guest-card .text-emerald-700,
        html.dark .guest-card .text-amber-800,
        html.dark .guest-card .text-red-800,
        html.dark .guest-card .text-red-700,
        html.dark .guest-card .text-green-800 {
            color: #cbd5e1 !important;
        }
        html.dark .guest-card .border-gray-200,
        html.dark .guest-card .border-gray-100,
        html.dark .guest-card .border-gray-300,
        html.dark .guest-card .border-emerald-300,
        html.dark .guest-card .border-amber-200,
        html.dark .guest-card .border-red-200,
        html.dark .guest-card .border-green-200,
        html.dark .guest-card .divide-gray-100 > :not([hidden]) ~ :not([hidden]) {
            border-color: rgba(255, 255, 255, 0.1) !important;
        }
        html.dark .guest-card input:not([type='checkbox']):not([type='radio']):not([type='hidden']),
        html.dark .guest-card select,
        html.dark .guest-card textarea {
            background-color: #0f1724 !important;
            border-color: rgba(255, 255, 255, 0.14) !important;
            color: #e7edf5 !important;
        }
        html.dark .guest-card input::placeholder,
        html.dark .guest-card textarea::placeholder {
            color: #94a3b8 !important;
        }
        html.dark .guest-card a {
            color: #93c5fd;
        }
        .delivery-checkin {
            background-color: #1A365D;
            color: #ffffff;
        }
        .delivery-checkin:hover {
            background-color: #122844;
            color: #ffffff;
        }
    </style>
    @include('partials.favicon')
    <script src="{{ asset('vendor/lucide.min.js') }}"></script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
@php
    $hasCampusBg = is_file(public_path('images/cspc-campus-bg.png'));
    $hasLogo = is_file(public_path('images/cspc-logo.png'));
    $useCampusBg = $hasCampusBg && (bool) trim($__env->yieldContent('use_campus_bg'));
@endphp
<body @class([
    'min-h-screen font-sans antialiased',
    'relative' => $useCampusBg,
]) @if (! $useCampusBg) style="background: linear-gradient(160deg, #eff6ff 0%, #f8fafc 42%, #e2e8f0 100%);" @endif>
    @if ($useCampusBg)
        <div class="fixed inset-0 -z-10 bg-cover bg-center bg-no-repeat" style="background-image: url('{{ asset('images/cspc-campus-bg.png') }}');"></div>
        <div class="fixed inset-0 -z-10 bg-slate-950/55"></div>
        <div class="fixed inset-0 -z-10 bg-gradient-to-br from-blue-950/40 via-transparent to-slate-900/50"></div>
    @else
        <div class="pointer-events-none fixed inset-0 opacity-40" style="background-image: radial-gradient(circle at 12% 18%, rgba(37,99,235,.18), transparent 28%), radial-gradient(circle at 88% 10%, rgba(29,78,216,.12), transparent 24%), radial-gradient(circle at 70% 85%, rgba(59,130,246,.10), transparent 30%);"></div>
    @endif

    <div @class([
        'relative flex min-h-screen flex-col items-center px-4 py-8 sm:py-10',
        'justify-start' => trim($__env->yieldContent('guest_align')) === 'start',
        'justify-center' => trim($__env->yieldContent('guest_align')) !== 'start',
    ])>
        <div @class(['w-full', trim($__env->yieldContent('card_width')) ?: 'max-w-md'])>
        <div class="relative z-30 mb-6 flex items-center justify-between gap-3">
        <a href="{{ route('home') }}" @class([
            'flex min-w-0 cursor-pointer items-center gap-3 no-underline',
            'text-white' => $useCampusBg,
            'text-gray-900' => ! $useCampusBg,
        ])>
            <div @class([
                'flex h-12 w-12 items-center justify-center overflow-hidden',
                'rounded-full' => $hasLogo,
                'rounded-xl bg-blue-600' => ! $hasLogo,
            ])>
                @if ($hasLogo)
                    <img src="{{ asset('images/cspc-logo.png') }}" alt="CSPC" class="h-12 w-12 object-contain drop-shadow-md">
                @else
                    <i data-lucide="parking-square" class="h-5 w-5 text-white"></i>
                @endif
            </div>
            <div>
                <p class="text-sm font-semibold leading-tight">Smart Campus VMS</p>
                <p @class(['text-xs', 'text-blue-100/90' => $useCampusBg, 'text-gray-500' => ! $useCampusBg])>Camarines Sur Polytechnic Colleges</p>
            </div>
        </a>
        @include('partials.theme-menu')
        </div>

            @yield('content')

        @include('partials.public-footer', ['tone' => $useCampusBg ? 'on-photo' : 'on-light'])
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (window.lucide?.createIcons) {
                lucide.createIcons();
            }
            if (window.initPasswordToggles) {
                window.initPasswordToggles();
            }
        });
    </script>
    @stack('scripts')
</body>
</html>
