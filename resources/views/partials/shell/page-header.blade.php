@props(['title', 'subtitle' => null])

<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-900 sm:text-3xl">{{ $title }}</h1>
    @if ($subtitle)
        <p class="mt-1 text-sm text-gray-500 sm:text-base">{{ $subtitle }}</p>
    @endif
</div>
