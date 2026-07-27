@props([
    'href' => '#',
    'icon' => 'circle',
    'label' => '',
    'active' => false,
    'activeClass' => 'bg-blue-50 text-blue-700',
])

<a
    href="{{ $href }}"
    {{ $attributes->merge([
        'class' => 'flex items-center gap-3 px-3 py-2.5 rounded-lg transition-colors ' . ($active
            ? $activeClass
            : 'text-gray-700 hover:bg-gray-100'),
    ]) }}
>
    <i data-lucide="{{ $icon }}" class="w-5 h-5 shrink-0"></i>
    <span class="font-medium text-sm sm:text-base">{{ $label }}</span>
</a>
