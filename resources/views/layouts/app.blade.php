@php
    $authUser = auth()->user();
    $profileName = trim($__env->yieldContent('profile_name')) ?: ($authUser?->fullname ?? 'Portal User');
    $profileRole = trim($__env->yieldContent('profile_role')) ?: ($authUser?->roleName() ?? 'Member');
    $profileEmail = trim($__env->yieldContent('profile_email')) ?: ($authUser?->email ?? 'user@campus.edu');
    $profilePhoneRaw = trim($__env->yieldContent('profile_phone'));
    $profilePhone = $profilePhoneRaw !== '' ? $profilePhoneRaw : ($authUser?->phone_number ?? null);
    $profileIdRaw = trim($__env->yieldContent('profile_id'));
    $profileId = $profileIdRaw !== '' ? $profileIdRaw : ($authUser?->id_number ?? null);
    $profileAccent = trim($__env->yieldContent('profile_accent')) ?: 'bg-[var(--cspc-navy)]';
    $profileInitials = trim($__env->yieldContent('profile_initials')) ?: strtoupper(
        collect(explode(' ', $profileName))->filter()->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->join('') ?: 'U'
    );
    $profileSettingsUrl = Route::has('profile.edit') ? route('profile.edit') : '#';
    $notificationCount = $notificationCount ?? (int) (trim($__env->yieldContent('notification_count')) ?: 0);
    $notificationsUrl = $notificationsUrl ?? null;
    $portalTitle = trim($__env->yieldContent('portal_title')) ?: 'Smart Campus VMS';
    $portalSubtitle = trim($__env->yieldContent('portal_subtitle')) ?: 'Vehicle Management System';
    $portalIcon = trim($__env->yieldContent('portal_icon')) ?: 'parking-square';
    $brandBg = trim($__env->yieldContent('brand_bg')) ?: 'bg-[var(--cspc-navy)]';
    $navActiveClass = trim($__env->yieldContent('nav_active_class')) ?: 'portal-nav-item--active bg-[var(--cspc-navy-soft)] text-[var(--cspc-navy)] shadow-sm';
    $hasCspcLogo = is_file(public_path('images/cspc-logo.png'));
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
<body class="min-h-screen bg-slate-50 font-sans antialiased text-slate-900">
    <div id="portal-root" class="min-h-screen bg-slate-50">
        {{-- Navbar --}}
        <header class="portal-header sticky top-0 z-40 border-b border-slate-200/80 bg-white/95 shadow-sm backdrop-blur-md">
            <div class="flex h-full items-center justify-between gap-3 px-3 sm:px-5 lg:px-6">
                <div class="flex min-w-0 items-center gap-2.5 sm:gap-3">
                    <button
                        type="button"
                        id="portal-menu-btn"
                        class="inline-flex h-10 w-10 items-center justify-center rounded-xl text-slate-600 transition-colors hover:bg-slate-100 hover:text-[var(--cspc-navy)] lg:hidden"
                        aria-label="Toggle navigation"
                        aria-controls="portal-sidebar"
                        aria-expanded="false"
                    >
                        <i data-lucide="menu" id="portal-menu-icon-open" class="h-5 w-5"></i>
                        <i data-lucide="x" id="portal-menu-icon-close" class="hidden h-5 w-5"></i>
                    </button>

                    <div class="flex min-w-0 items-center gap-3">
                        @if ($hasCspcLogo)
                            <img
                                src="{{ asset('images/cspc-logo.png') }}"
                                alt="Camarines Sur Polytechnic Colleges"
                                class="h-10 w-10 shrink-0 object-contain sm:h-11 sm:w-11"
                            >
                        @else
                            <div class="{{ $brandBg }} flex h-10 w-10 shrink-0 items-center justify-center rounded-xl sm:h-11 sm:w-11">
                                <i data-lucide="{{ $portalIcon }}" class="h-5 w-5 text-white"></i>
                            </div>
                        @endif
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-x-2 gap-y-0.5">
                                <h1 class="truncate text-sm font-bold tracking-tight text-[var(--cspc-navy)] sm:text-[15px]">
                                    {{ $portalTitle }}
                                </h1>
                                <span class="hidden rounded-full bg-[var(--cspc-gold-soft)] px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-[var(--cspc-navy-deep)] sm:inline-block">
                                    CSPC
                                </span>
                            </div>
                            <p class="truncate text-xs text-slate-500">{{ $portalSubtitle }}</p>
                        </div>
                    </div>
                </div>

                <div class="flex shrink-0 items-center gap-1.5 sm:gap-3">
                    <div
                        class="hidden items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-1.5 sm:flex"
                        data-ph-clock
                        data-timezone="{{ $appTimezone ?? 'Asia/Manila' }}"
                        title="Philippine Time ({{ $appTimezone ?? 'Asia/Manila' }})"
                    >
                        <i data-lucide="clock-3" class="h-3.5 w-3.5 text-[var(--cspc-navy)]"></i>
                        <div class="text-right leading-tight">
                            <p class="text-sm font-semibold tabular-nums text-slate-900" data-ph-clock-time>—:—:—</p>
                            <p class="text-[10px] font-medium text-slate-500" data-ph-clock-date>Asia/Manila</p>
                        </div>
                    </div>

                    @if ($notificationsUrl)
                        <a
                            href="{{ $notificationsUrl }}"
                            class="relative inline-flex h-10 w-10 items-center justify-center rounded-xl text-slate-600 transition-colors hover:bg-slate-100 hover:text-[var(--cspc-navy)]"
                            aria-label="Notifications"
                            data-notification-bell
                        >
                            <i data-lucide="bell" class="h-5 w-5"></i>
                            <span
                                id="notification-badge"
                                data-notification-count="{{ $notificationCount }}"
                                @class([
                                    'absolute right-1 top-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-semibold text-white',
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
                            class="flex items-center gap-2 rounded-xl border border-transparent p-1 pr-1.5 transition-colors hover:border-slate-200 hover:bg-slate-50 sm:pr-2.5"
                            aria-haspopup="menu"
                            aria-expanded="false"
                        >
                            <x-portal.avatar :user="$authUser" size="sm" :accent="$profileAccent" />
                            <div class="hidden min-w-0 max-w-[150px] text-left md:block">
                                <p class="truncate text-sm font-semibold text-slate-900">{{ $profileName }}</p>
                                <p class="truncate text-xs text-slate-500">{{ $profileRole }}</p>
                            </div>
                            <i data-lucide="chevron-down" class="hidden h-4 w-4 text-slate-400 md:block"></i>
                        </button>

                        <div
                            id="portal-profile-menu"
                            class="absolute right-0 z-50 mt-2 hidden w-80 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl shadow-slate-900/10"
                            role="menu"
                        >
                            <div class="border-b border-slate-100 bg-gradient-to-br from-[var(--cspc-navy)] to-[var(--cspc-navy-deep)] px-4 py-4 text-white">
                                <div class="flex items-center gap-3">
                                    <x-portal.avatar :user="$authUser" size="lg" :accent="$profileAccent" />
                                    <div class="min-w-0">
                                        <p class="truncate font-semibold">{{ $profileName }}</p>
                                        <p class="truncate text-sm text-blue-100">{{ $profileRole }}</p>
                                    </div>
                                </div>
                            </div>

                            <div class="space-y-3 p-4">
                                <div class="flex items-center gap-3 text-sm">
                                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-slate-100 text-slate-500">
                                        <i data-lucide="mail" class="h-4 w-4"></i>
                                    </span>
                                    <div class="min-w-0">
                                        <p class="text-xs text-slate-500">Email</p>
                                        <p class="truncate font-medium text-slate-900">{{ $profileEmail }}</p>
                                    </div>
                                </div>
                                @if ($profilePhone)
                                    <div class="flex items-center gap-3 text-sm">
                                        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-slate-100 text-slate-500">
                                            <i data-lucide="phone" class="h-4 w-4"></i>
                                        </span>
                                        <div class="min-w-0">
                                            <p class="text-xs text-slate-500">Phone</p>
                                            <p class="truncate font-medium text-slate-900">{{ $profilePhone }}</p>
                                        </div>
                                    </div>
                                @endif
                                @if ($profileId)
                                    <div class="flex items-center gap-3 text-sm">
                                        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-slate-100 text-slate-500">
                                            <i data-lucide="hash" class="h-4 w-4"></i>
                                        </span>
                                        <div class="min-w-0">
                                            <p class="text-xs text-slate-500">ID</p>
                                            <p class="truncate font-medium text-slate-900">{{ $profileId }}</p>
                                        </div>
                                    </div>
                                @endif
                            </div>

                            <div class="space-y-1 border-t border-slate-100 p-2">
                                <a
                                    href="{{ $profileSettingsUrl }}"
                                    class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-50 hover:text-[var(--cspc-navy)]"
                                    role="menuitem"
                                >
                                    <i data-lucide="settings" class="h-4 w-4"></i>
                                    Account Settings
                                </a>
                                @if (Route::has('logout'))
                                    <form method="POST" action="{{ route('logout') }}" class="block">
                                        @csrf
                                        <button
                                            type="submit"
                                            class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left text-sm font-medium text-red-600 transition-colors hover:bg-red-50"
                                            role="menuitem"
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
            <div class="pointer-events-none absolute inset-x-0 bottom-0 h-[3px] bg-gradient-to-r from-[var(--cspc-navy)] via-[var(--cspc-gold)] to-[var(--cspc-navy)]" aria-hidden="true"></div>
        </header>

        <div class="flex">
            {{-- Sidebar: drawer on mobile, persistent on desktop --}}
            <aside
                id="portal-sidebar"
                class="portal-sidebar fixed z-30 flex w-[17.5rem] -translate-x-full flex-col overflow-y-auto border-r border-slate-200/80 bg-white shadow-[4px_0_24px_-12px_rgba(15,39,79,0.18)] transition-transform duration-300 ease-out lg:translate-x-0"
            >
                <nav class="flex min-h-full flex-1 flex-col p-3 sm:p-4" aria-label="Primary">
                    @yield('navigation')
                </nav>
            </aside>

            {{-- Main content --}}
            <main class="min-w-0 flex-1 p-4 sm:p-6 lg:ml-[17.5rem] lg:p-8">
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

        <div id="portal-overlay" class="fixed inset-0 z-20 hidden bg-[var(--cspc-navy-deep)]/45 backdrop-blur-[1px]" aria-hidden="true"></div>
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
