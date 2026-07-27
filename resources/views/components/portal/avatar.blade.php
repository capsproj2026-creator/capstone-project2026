@props([
    'user' => null,
    'size' => 'md',
    'accent' => 'bg-blue-500',
])

@php
    $user ??= auth()->user();
        $initials = strtoupper(
            collect(explode(' ', $user?->displayName() ?? $user?->name ?? 'U'))->filter()->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->join('') ?: 'U'
        );
    $sizeClasses = match ($size) {
        'sm' => 'h-8 w-8 text-xs',
        'lg' => 'h-12 w-12 text-base',
        'xl' => 'h-16 w-16 text-lg',
        default => 'h-10 w-10 text-sm',
    };
    $showPhoto = $user?->hasUploadedProfilePicture() ?? false;
    $uid = 'avatar-'.($user?->id ?? 'guest').'-'.uniqid();
@endphp

<div {{ $attributes->merge(['class' => "{$sizeClasses} relative shrink-0 overflow-hidden rounded-full ring-2 ring-white"]) }}>
    @if ($showPhoto)
        <img
            src="{{ $user->profilePictureUrl() }}"
            alt="{{ $user->displayName() }}"
            class="h-full w-full object-cover"
            loading="lazy"
            onerror="this.classList.add('hidden'); document.getElementById('{{ $uid }}').classList.remove('hidden');"
        >
    @endif
    <div
        id="{{ $uid }}"
        @class([
            'absolute inset-0 flex items-center justify-center text-white',
            $accent,
            'hidden' => $showPhoto,
        ])
    >
        {{ $initials }}
    </div>
</div>
