@php
    $roleName = auth()->user()?->roleName() ?? 'Member';
    $navItems = \App\Services\NavigationService::routesForRole($roleName);
    $activeClass = \App\Services\NavigationService::navActiveClassForRole($roleName);
@endphp

<div class="mb-4 border-b border-slate-200/80 px-1 pb-4">
    <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-400">Navigation</p>
    <p class="mt-1 truncate text-sm font-semibold text-[var(--cspc-navy)]">{{ $roleName }} portal</p>
</div>

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

<div class="mt-auto border-t border-slate-200/80 px-1 pt-4">
    <div class="flex items-start gap-2.5 rounded-xl bg-slate-50 px-3 py-3">
        @if (is_file(public_path('images/cspc-logo.png')))
            <img src="{{ asset('images/cspc-logo.png') }}" alt="" class="mt-0.5 h-8 w-8 shrink-0 object-contain" aria-hidden="true">
        @endif
        <div class="min-w-0">
            <p class="text-xs font-semibold leading-snug text-[var(--cspc-navy)]">Camarines Sur Polytechnic Colleges</p>
            <p class="mt-0.5 text-[11px] leading-snug text-slate-500">Smart Campus Vehicle Management</p>
        </div>
    </div>
</div>
