@php
    $roleName = auth()->user()?->roleName() ?? 'Member';
    $navItems = \App\Services\NavigationService::routesForRole($roleName);
    $activeClass = \App\Services\NavigationService::navActiveClassForRole($roleName);
@endphp

<div class="portal-sidebar-header shrink-0 border-b border-[var(--portal-border)] px-1 pb-4">
    <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-[var(--portal-text-subtle)]">Navigation</p>
    <p class="mt-1 truncate text-sm font-semibold text-[var(--cspc-navy)] dark:text-blue-300">{{ $roleName }} portal</p>
</div>

<div class="portal-sidebar-nav-scroll min-h-0 flex-1 overflow-y-auto overscroll-contain py-3">
    <div class="space-y-1">
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
</div>

<div class="portal-sidebar-footer shrink-0 border-t border-[var(--portal-border)] px-1 pt-3">
    <div class="flex items-center gap-2.5 rounded-xl bg-[var(--portal-bg-subtle)] px-3 py-3 dark:bg-slate-800/40">
        @if (is_file(public_path('images/cspc-logo.png')))
            <img src="{{ asset('images/cspc-logo.png') }}" alt="" class="h-9 w-9 shrink-0 object-contain opacity-90" aria-hidden="true">
        @endif
        <div class="min-w-0">
            <p class="text-xs font-semibold leading-snug text-[var(--cspc-navy)] dark:text-blue-200">Camarines Sur Polytechnic Colleges</p>
            <p class="portal-muted mt-0.5 text-[11px] leading-snug">Smart Campus Vehicle Management</p>
        </div>
    </div>
</div>
