@php
    $authUser = auth()->user();
    $profileName = trim($__env->yieldContent('profile_name')) ?: ($authUser?->fullname ?? 'Portal User');
    $profileRole = trim($__env->yieldContent('profile_role')) ?: ($authUser?->roleName() ?? 'Member');
    $profileEmail = trim($__env->yieldContent('profile_email')) ?: ($authUser?->email ?? 'user@campus.edu');
    $profilePhoneRaw = trim($__env->yieldContent('profile_phone'));
    $profilePhone = $profilePhoneRaw !== '' ? $profilePhoneRaw : ($authUser?->phone_number ?? null);
    $profileIdRaw = trim($__env->yieldContent('profile_id'));
    $profileId = $profileIdRaw !== '' ? $profileIdRaw : ($authUser?->id_number ?? null);
    $profileAccent = trim($__env->yieldContent('profile_accent')) ?: 'bg-blue-500';
    $profileInitials = trim($__env->yieldContent('profile_initials')) ?: strtoupper(
        collect(explode(' ', $profileName))->filter()->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->join('') ?: 'U'
    );
    $profileSettingsUrl = Route::has('profile.edit') ? route('profile.edit') : '#';
    $notificationCount = $notificationCount ?? (int) (trim($__env->yieldContent('notification_count')) ?: 0);
    $notificationsUrl = $notificationsUrl ?? null;
    $portalTitle = trim($__env->yieldContent('portal_title')) ?: 'Smart Campus VMS';
    $portalSubtitle = trim($__env->yieldContent('portal_subtitle')) ?: 'Vehicle Management System';
    $portalIcon = trim($__env->yieldContent('portal_icon')) ?: 'parking-square';
    $brandBg = trim($__env->yieldContent('brand_bg')) ?: 'bg-blue-600';
    $navActiveClass = trim($__env->yieldContent('nav_active_class')) ?: 'bg-blue-50 text-blue-700';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="reverb-app-key" content="{{ config('broadcasting.connections.reverb.key') }}">
    <meta name="reverb-host" content="{{ config('broadcasting.connections.reverb.options.host') }}">
    <meta name="reverb-port" content="{{ config('broadcasting.connections.reverb.options.port') }}">
    <meta name="reverb-scheme" content="{{ config('broadcasting.connections.reverb.options.scheme') }}">
    <title>@yield('title', $portalTitle)</title>
    @include('partials.favicon')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@0.487.0/dist/umd/lucide.min.js"></script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="min-h-screen bg-gray-50 font-sans antialiased">
    <div id="portal-root" class="min-h-screen bg-gray-50">
        {{-- Header --}}
        <header class="sticky top-0 z-40 border-b border-gray-200 bg-white">
            <div class="flex items-center justify-between px-3 py-3 sm:px-4">
                <div class="flex items-center gap-2 sm:gap-3">
                    <button
                        type="button"
                        id="portal-menu-btn"
                        class="rounded-lg p-2 hover:bg-gray-100 lg:hidden"
                        aria-label="Toggle navigation"
                    >
                        <i data-lucide="menu" id="portal-menu-icon-open" class="h-5 w-5"></i>
                        <i data-lucide="x" id="portal-menu-icon-close" class="hidden h-5 w-5"></i>
                    </button>
                    <div class="flex items-center gap-2">
                        @if (is_file(public_path('images/cspc-logo.png')))
                            <img src="{{ asset('images/cspc-logo.png') }}" alt="CSPC" class="h-8 w-8 object-contain sm:h-9 sm:w-9">
                        @else
                            <div class="{{ $brandBg }} flex h-7 w-7 items-center justify-center rounded-lg sm:h-8 sm:w-8">
                                <i data-lucide="{{ $portalIcon }}" class="h-4 w-4 text-white sm:h-5 sm:w-5"></i>
                            </div>
                        @endif
                        <div class="hidden sm:block">
                            <h1 class="text-sm font-semibold text-gray-900 sm:text-base">{{ $portalTitle }}</h1>
                            <p class="text-xs text-gray-500">{{ $portalSubtitle }}</p>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-2 sm:gap-3">
                    <div
                        class="hidden text-right sm:block"
                        data-ph-clock
                        data-timezone="{{ $appTimezone ?? 'Asia/Manila' }}"
                        title="Philippine Time ({{ $appTimezone ?? 'Asia/Manila' }})"
                    >
                        <p class="text-sm font-semibold tabular-nums text-gray-900" data-ph-clock-time>—:—:—</p>
                        <p class="text-[11px] text-gray-500" data-ph-clock-date>Asia/Manila</p>
                    </div>

                    @if ($notificationsUrl)
                        <a href="{{ $notificationsUrl }}" class="relative rounded-lg p-2 hover:bg-gray-100" aria-label="Notifications" data-notification-bell>
                            <i data-lucide="bell" class="h-4 w-4 text-gray-600 sm:h-5 sm:w-5"></i>
                            <span
                                id="notification-badge"
                                data-notification-count="{{ $notificationCount }}"
                                @class([
                                    'absolute -right-1 -top-1 flex h-4 w-4 items-center justify-center rounded-full bg-red-500 p-0 text-[10px] font-medium text-white sm:h-5 sm:w-5 sm:text-xs',
                                    'hidden' => $notificationCount < 1,
                                ])
                            >
                                {{ $notificationCount > 9 ? '9+' : $notificationCount }}
                            </span>
                        </a>
                    @endif

                    {{-- Profile dropdown --}}
                    <div class="relative">
                        <button
                            type="button"
                            id="portal-profile-btn"
                            class="flex items-center gap-2 rounded-lg p-1 transition-colors hover:bg-gray-100"
                        >
                            <x-portal.avatar :user="$authUser" size="sm" :accent="$profileAccent" />
                            <div class="hidden min-w-0 max-w-[140px] text-left md:block">
                                <p class="truncate text-sm font-medium text-gray-900">{{ $profileName }}</p>
                                <p class="truncate text-xs text-gray-500">{{ $profileRole }}</p>
                            </div>
                        </button>

                        <div
                            id="portal-profile-menu"
                            class="absolute right-0 z-50 mt-2 hidden w-72 rounded-lg border border-gray-200 bg-white shadow-xl"
                        >
                            <div class="border-b border-gray-200 p-4">
                                <div class="flex items-center gap-3">
                                    <x-portal.avatar :user="$authUser" size="lg" :accent="$profileAccent" />
                                    <div>
                                        <p class="font-semibold text-gray-900">{{ $profileName }}</p>
                                        <p class="text-sm text-gray-500">{{ $profileRole }}</p>
                                    </div>
                                </div>
                            </div>

                            <div class="space-y-3 p-4">
                                <div class="flex items-center gap-3 text-sm">
                                    <i data-lucide="mail" class="h-4 w-4 text-gray-400"></i>
                                    <div>
                                        <p class="text-gray-500">Email</p>
                                        <p class="text-gray-900">{{ $profileEmail }}</p>
                                    </div>
                                </div>
                                @if ($profilePhone)
                                    <div class="flex items-center gap-3 text-sm">
                                        <i data-lucide="phone" class="h-4 w-4 text-gray-400"></i>
                                        <div>
                                            <p class="text-gray-500">Phone</p>
                                            <p class="text-gray-900">{{ $profilePhone }}</p>
                                        </div>
                                    </div>
                                @endif
                                @if ($profileId)
                                    <div class="flex items-center gap-3 text-sm">
                                        <i data-lucide="hash" class="h-4 w-4 text-gray-400"></i>
                                        <div>
                                            <p class="text-gray-500">ID</p>
                                            <p class="text-gray-900">{{ $profileId }}</p>
                                        </div>
                                    </div>
                                @endif
                            </div>

                            <div class="border-t border-gray-200 p-2">
                                <a
                                    href="{{ $profileSettingsUrl }}"
                                    class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm text-gray-700 transition-colors hover:bg-gray-100"
                                >
                                    <i data-lucide="settings" class="h-4 w-4"></i>
                                    Account Settings
                                </a>
                                @if (Route::has('logout'))
                                    <form method="POST" action="{{ route('logout') }}" class="block">
                                        @csrf
                                        <button
                                            type="submit"
                                            class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-left text-sm text-red-600 transition-colors hover:bg-red-50"
                                        >
                                            <i data-lucide="log-out" class="h-4 w-4"></i>
                                            Logout
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <div class="flex">
            {{-- Sidebar: drawer on mobile, persistent on desktop --}}
            <aside
                id="portal-sidebar"
                class="fixed top-[57px] z-30 h-[calc(100vh-57px)] w-64 -translate-x-full overflow-y-auto border-r border-gray-200 bg-white transition-transform duration-300 sm:top-[65px] sm:h-[calc(100vh-65px)] lg:translate-x-0"
            >
                <nav class="space-y-1 p-3 sm:p-4">
                    @yield('navigation')
                </nav>
            </aside>

            {{-- Main content --}}
            <main class="min-w-0 flex-1 p-4 sm:p-6 lg:ml-64 lg:p-8">
                @if (session('success'))
                    <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                        {{ session('success') }}
                    </div>
                @endif
                @if (session('error'))
                    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                        {{ session('error') }}
                    </div>
                @endif
                @if ($errors->any())
                    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                        <p class="font-semibold">Please fix the following:</p>
                        <ul class="mt-1 list-disc pl-5">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @yield('content')
            </main>
        </div>

        <div id="portal-overlay" class="fixed inset-0 z-20 hidden bg-black/50" aria-hidden="true"></div>
    </div>

    {{-- Inline scripts in @stack run before Vite modules; wait for Echo here. --}}
    <script>
        window.whenEchoReady = window.whenEchoReady || function whenEchoReady(callback, timeoutMs) {
            timeoutMs = timeoutMs || 15000;
            if (typeof callback !== 'function') return;
            if (window.Echo) {
                callback(window.Echo);
                return;
            }
            let done = false;
            const finish = (echo) => {
                if (done) return;
                done = true;
                window.removeEventListener('echo:ready', onReady);
                window.clearInterval(poll);
                callback(echo || null);
            };
            const onReady = () => finish(window.Echo);
            window.addEventListener('echo:ready', onReady);
            const started = Date.now();
            const poll = window.setInterval(() => {
                if (window.Echo) finish(window.Echo);
                else if (Date.now() - started >= timeoutMs) finish(null);
            }, 50);
        };
    </script>
    @stack('scripts')
</body>
</html>
