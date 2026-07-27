<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Smart Campus VMS')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@0.487.0/dist/umd/lucide.min.js"></script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="min-h-screen bg-gray-50 font-sans antialiased">
    <div class="flex min-h-screen flex-col items-center justify-center px-4 py-10">
        <a href="{{ route('home') }}" class="mb-8 flex items-center gap-3 text-gray-900 no-underline">
            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-600">
                <i data-lucide="parking-square" class="h-5 w-5 text-white"></i>
            </div>
            <div>
                <p class="text-sm font-semibold leading-tight">Smart Campus VMS</p>
                <p class="text-xs text-gray-500">Vehicle Management System</p>
            </div>
        </a>

        <div @class(['w-full', trim($__env->yieldContent('card_width')) ?: 'max-w-md'])>
            @yield('content')
        </div>

        <p class="mt-8 text-center text-xs text-gray-400">&copy; {{ date('Y') }} Smart Campus Security Department</p>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (window.lucide?.createIcons) {
                lucide.createIcons();
            }

            document.querySelectorAll('[data-password-toggle]').forEach(function (button) {
                button.addEventListener('click', function () {
                    const inputId = button.getAttribute('data-password-toggle');
                    const input = document.getElementById(inputId);
                    if (!input) return;

                    const show = input.type === 'password';
                    input.type = show ? 'text' : 'password';
                    button.setAttribute('aria-label', show ? 'Hide password' : 'Show password');

                    const icon = button.querySelector('[data-lucide]');
                    if (icon) {
                        icon.setAttribute('data-lucide', show ? 'eye-off' : 'eye');
                        lucide.createIcons();
                    }
                });
            });
        });
    </script>
    @stack('scripts')
</body>
</html>
