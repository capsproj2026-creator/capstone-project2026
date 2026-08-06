@props([
    'log',
    'routeName',
    'compact' => false,
    'label' => null,
])

@php
    /** @var \App\Models\ViolationLog $log */
    $urls = \App\Support\ViolationEvidence::urlsFor($log, $routeName);
    $count = count($urls);
    $title = $label ?: ('Evidence · '.$log->violation_type);
    $thumbClass = $compact ? 'h-12 w-16 object-cover' : 'h-20 w-28 object-cover';
    $buttonLabel = $count > 1 ? "View Evidence ({$count})" : 'View Evidence';
@endphp

<div {{ $attributes->merge(['class' => 'violation-evidence-panel']) }} data-violation-evidence-panel>
    @if ($count > 0)
        <div class="flex flex-wrap items-center gap-3">
            <button
                type="button"
                class="group relative overflow-hidden rounded-lg border border-gray-200 bg-gray-50 shadow-sm transition hover:border-blue-300 hover:shadow"
                data-violation-evidence-open
                data-evidence-urls='@json($urls)'
                data-evidence-title="{{ $title }}"
                aria-label="View violation evidence"
            >
                <img
                    src="{{ $urls[0] }}"
                    alt="Violation evidence thumbnail"
                    class="{{ $thumbClass }}"
                    loading="lazy"
                    decoding="async"
                >
                @if ($count > 1)
                    <span class="absolute bottom-1 right-1 rounded bg-black/65 px-1.5 py-0.5 text-[10px] font-semibold text-white">
                        +{{ $count - 1 }}
                    </span>
                @endif
            </button>
            <button
                type="button"
                class="inline-flex items-center gap-1.5 rounded-lg border border-blue-200 bg-blue-50 px-3 py-1.5 text-xs font-semibold text-blue-700 transition hover:bg-blue-100"
                data-violation-evidence-open
                data-evidence-urls='@json($urls)'
                data-evidence-title="{{ $title }}"
            >
                <i data-lucide="image" class="h-3.5 w-3.5"></i>
                {{ $buttonLabel }}
            </button>
        </div>
    @else
        <p class="text-xs italic text-gray-400">No Evidence Available.</p>
    @endif
</div>
