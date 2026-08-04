@props([
    'name' => 'password',
    'id' => null,
    'label' => 'Password',
    'required' => true,
    'autocomplete' => 'current-password',
    'placeholder' => '',
])

@php
    $inputId = $id ?? $name;
@endphp

<div {{ $attributes->merge(['class' => 'w-full min-w-0']) }}>
    <label for="{{ $inputId }}" class="mb-1.5 block text-sm font-medium text-gray-700">{{ $label }}</label>
    <div class="relative">
        <input
            type="password"
            name="{{ $name }}"
            id="{{ $inputId }}"
            @if($required) required @endif
            autocomplete="{{ $autocomplete }}"
            placeholder="{{ $placeholder }}"
            class="w-full rounded-lg border border-gray-300 bg-white py-2.5 pl-3 pr-11 text-sm text-gray-900 shadow-sm placeholder:text-gray-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 @error($name) border-red-500 focus:border-red-500 focus:ring-red-500/20 @enderror"
        >
        <button
            type="button"
            data-password-toggle="{{ $inputId }}"
            class="absolute right-2 top-1/2 -translate-y-1/2 cursor-pointer rounded-md p-1.5 text-gray-500 hover:bg-gray-100 hover:text-gray-700"
            aria-label="Show password"
        >
            <i data-lucide="eye" class="h-4 w-4"></i>
        </button>
    </div>
    @error($name)
        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
    @enderror
</div>
