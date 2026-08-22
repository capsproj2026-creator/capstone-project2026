@props([
    'label',
    'name',
    'type' => 'text',
    'required' => false,
    'placeholder' => '',
    'value' => '',
])

<div {{ $attributes->merge(['class' => 'w-full min-w-0']) }}>
    <label for="{{ $name }}" class="mb-1.5 block text-sm font-medium text-gray-700">
        {{ $label }}
        @if ($required)
            <span class="text-red-500">*</span>
        @endif
    </label>
    <input
        type="{{ $type }}"
        name="{{ $name }}"
        id="{{ $name }}"
        value="{{ old($name, $value) }}"
        @if($required) required @endif
        placeholder="{{ $placeholder }}"
        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 shadow-sm placeholder:text-gray-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 @error($name) border-red-500 focus:border-red-500 focus:ring-red-500/20 @enderror"
    >
    @error($name)
        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
    @enderror
</div>
