<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Smart Campus VMS')</title>
    @include('partials.favicon')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@0.487.0/dist/umd/lucide.min.js"></script>
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

    <div class="relative flex min-h-screen flex-col items-center justify-center px-4 py-10">
        <a href="{{ route('home') }}" @class([
            'mb-8 flex cursor-pointer items-center gap-3 no-underline',
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

        <div @class(['w-full', trim($__env->yieldContent('card_width')) ?: 'max-w-md'])>
            @yield('content')
        </div>

        <p @class([
            'mt-8 text-center text-xs',
            'text-blue-100/70' => $useCampusBg,
            'text-gray-400' => ! $useCampusBg,
        ])>&copy; {{ date('Y') }} Smart Campus Security Department</p>
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
