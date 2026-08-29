@php
    $roleName = auth()->user()?->roleName() ?? 'Member';
    $navItems = \App\Services\NavigationService::routesForRole($roleName);
    $activeClass = \App\Services\NavigationService::navActiveClassForRole($roleName);
@endphp

<div class="mb-4 border-b border-[var(--portal-border)] px-1 pb-4">
    <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-[var(--portal-text-subtle)]">Navigation</p>
    <p class="mt-1 truncate text-sm font-semibold text-[var(--cspc-navy)] dark:text-blue-300">{{ $roleName }} portal</p>
</div>

<div class="flex min-h-0 flex-1 flex-col space-y-1">
    @foreach ($navItems as $item)
        @php
            $routePattern = str_ends_with($item['route'], '.dashboard')
                ? $item['route']
                : $item['route'].'*';
        @endphp
        <x-portal.nav-item
            href="{{ route($item['route']) }}"
            :icon="$item['icon']"
            :label="$item['label']"
            :active="request()->routeIs($routePattern)"
            :active-class="$activeClass"
        />
    @endforeach
</div>

<div class="portal-sidebar-gate-art" aria-hidden="true">
    <svg viewBox="0 0 240 100" class="mx-auto w-full max-w-[220px]" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M8 78h224" stroke="url(#gateGrad)" stroke-width="1.5" stroke-linecap="round" opacity="0.6"/>
        <path d="M40 78V42l28-14 22 8 38-18 36 12 28 18v32H40z" stroke="#8b5cf6" stroke-width="1.2" opacity="0.55"/>
        <path d="M68 78V58h18v20M108 78V48h20v30M148 78V62h16v16" stroke="#3b82f6" stroke-width="1" opacity="0.5"/>
        <rect x="118" y="52" width="4" height="26" fill="#3b82f6" opacity="0.35"/>
        <path d="M132 68h32a6 6 0 0 1 6 6v4H126v-4a6 6 0 0 1 6-6z" stroke="#60a5fa" stroke-width="1.2" fill="rgba(59,130,246,0.08)"/>
        <circle cx="148" cy="72" r="5" stroke="#a78bfa" stroke-width="1" fill="rgba(139,92,246,0.15)"/>
        <defs>
            <linearGradient id="gateGrad" x1="8" y1="78" x2="232" y2="78" gradientUnits="userSpaceOnUse">
                <stop stop-color="#3b82f6"/>
                <stop offset="1" stop-color="#8b5cf6"/>
            </linearGradient>
        </defs>
    </svg>
</div>

<div class="mt-4 border-t border-[var(--portal-border)] px-1 pt-4">
    <div class="flex items-start gap-2.5 rounded-xl bg-[var(--portal-bg-subtle)] px-3 py-3 dark:bg-slate-800/40">
        @if (is_file(public_path('images/cspc-logo.png')))
            <img src="{{ asset('images/cspc-logo.png') }}" alt="" class="mt-0.5 h-8 w-8 shrink-0 object-contain opacity-90" aria-hidden="true">
        @endif
        <div class="min-w-0">
            <p class="text-xs font-semibold leading-snug text-[var(--cspc-navy)] dark:text-blue-200">Camarines Sur Polytechnic Colleges</p>
            <p class="portal-muted mt-0.5 text-[11px] leading-snug">Smart Campus Vehicle Management</p>
        </div>
    </div>
</div>
