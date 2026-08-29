@props([
    'href' => '#',
    'icon' => 'circle',
    'label' => '',
    'active' => false,
    'activeClass' => '',
])

@php
    $base = 'portal-nav-item group relative flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-all duration-200 outline-none focus-visible:ring-2 focus-visible:ring-[var(--cspc-action)]/40 focus-visible:ring-offset-1';
    if ($active) {
        $state = $activeClass !== ''
            ? $activeClass
            : 'portal-nav-item--active';
    } else {
        $state = 'text-[var(--portal-text)] hover:bg-[rgba(93,159,209,0.18)] hover:text-[var(--portal-text)] dark:text-slate-400 dark:hover:bg-slate-800/60 dark:hover:text-slate-200';
    }
@endphp

<a
    href="{{ $href }}"
    @if ($active) aria-current="page" @endif
    {{ $attributes->merge(['class' => trim("$base $state")]) }}
>
    <span
        @class([
            'portal-nav-icon-wrap flex h-9 w-9 shrink-0 items-center justify-center rounded-lg transition-colors',
            'bg-white/20 text-white' => $active,
            'bg-white/70 text-[var(--portal-text)] group-hover:bg-white group-hover:text-[var(--cspc-navy)] dark:bg-slate-800/80 dark:text-slate-400 dark:group-hover:bg-slate-700/80 dark:group-hover:text-slate-200' => ! $active,
        ])
    >
        <i data-lucide="{{ $icon }}" class="h-[18px] w-[18px]"></i>
    </span>
    <span class="truncate tracking-tight">{{ $label }}</span>
</a>
