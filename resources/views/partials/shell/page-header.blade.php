<div class="mb-6">
    <h1 class="portal-heading text-2xl font-bold sm:text-3xl">{{ $title }}</h1>
    @if ($subtitle)
        <p class="portal-muted mt-1 text-sm sm:text-base">{{ $subtitle }}</p>
    @endif
</div>

@if ($showFlash ?? true)
    @include('partials.shell.flash')
@endif
