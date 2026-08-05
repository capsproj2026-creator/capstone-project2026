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
        $state = 'text-slate-600 hover:bg-slate-100/90 hover:text-[var(--cspc-navy)]';
    }
@endphp

<a
    href="{{ $href }}"
    @if ($active) aria-current="page" @endif
    {{ $attributes->merge(['class' => trim("$base $state")]) }}
>
    @if ($active)
        <span class="absolute left-0 top-1/2 h-7 w-[3px] -translate-y-1/2 rounded-r-full bg-[var(--cspc-gold)]" aria-hidden="true"></span>
    @endif
    <span
        @class([
            'flex h-9 w-9 shrink-0 items-center justify-center rounded-lg transition-colors',
            'bg-[var(--cspc-navy)] text-white shadow-sm' => $active,
            'bg-slate-100 text-slate-500 group-hover:bg-[var(--cspc-navy-soft)] group-hover:text-[var(--cspc-navy)]' => ! $active,
        ])
    >
        <i data-lucide="{{ $icon }}" class="h-[18px] w-[18px]"></i>
    </span>
    <span class="truncate tracking-tight">{{ $label }}</span>
</a>
