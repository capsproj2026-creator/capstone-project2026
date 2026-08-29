@props([
    'href' => '#',
    'icon' => 'circle',
    'label' => '',
    'active' => false,
    'activeClass' => '',
])

@php
    $base = 'portal-nav-item group relative flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-all duration-200 outline-none focus-visible:ring-2 focus-visible:ring-[var(--cspc-navy)]/30 focus-visible:ring-offset-1';
    if ($active) {
        $state = $activeClass !== ''
            ? $activeClass
            : 'portal-nav-item--active bg-[var(--cspc-navy-soft)] text-[var(--cspc-navy)] shadow-sm';
    } else {
        $state = 'text-slate-600 hover:bg-slate-100/90 hover:text-[var(--cspc-navy)] dark:text-slate-400 dark:hover:bg-slate-800/60 dark:hover:text-slate-200';
    }
@endphp

<a
    href="{{ $href }}"
    @if ($active) aria-current="page" @endif
    {{ $attributes->merge(['class' => trim("$base $state")]) }}
>
    @if ($active)
        <span class="absolute left-0 top-1/2 h-7 w-[3px] -translate-y-1/2 rounded-r-full bg-[var(--cspc-gold)] dark:bg-gradient-to-b dark:from-blue-400 dark:to-violet-500" aria-hidden="true"></span>
    @endif
    <span
        @class([
            'portal-nav-icon-wrap flex h-9 w-9 shrink-0 items-center justify-center rounded-lg transition-colors',
            'bg-[var(--cspc-navy)] text-white shadow-sm dark:bg-gradient-to-br dark:from-blue-500 dark:to-violet-600' => $active,
            'bg-slate-100 text-slate-500 group-hover:bg-[var(--cspc-navy-soft)] group-hover:text-[var(--cspc-navy)] dark:bg-slate-800/80 dark:text-slate-400 dark:group-hover:bg-slate-700/80 dark:group-hover:text-slate-200' => ! $active,
        ])
    >
        <i data-lucide="{{ $icon }}" class="h-[18px] w-[18px]"></i>
    </span>
    <span class="truncate tracking-tight">{{ $label }}</span>
</a>
