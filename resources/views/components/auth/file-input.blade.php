@props([
    'name',
    'id' => null,
    'label',
    'required' => false,
    'accept' => 'image/*',
    'multiple' => false,
])

@php
    $inputId = $id ?? preg_replace('/[^A-Za-z0-9_-]/', '_', $name);
@endphp

<div {{ $attributes->merge(['class' => '']) }}>
    <label for="{{ $inputId }}" class="mb-1.5 block text-sm font-medium text-gray-700">
        {{ $label }}
        @if ($required)
            <span class="text-red-500">*</span>
        @endif
    </label>
    <div @class([
        'flex items-center gap-3 rounded-lg border bg-white px-3 py-2 shadow-sm transition-colors',
        'border-gray-300 focus-within:border-blue-500 focus-within:ring-2 focus-within:ring-blue-500/20' => ! $errors->has($name),
        'border-red-500 ring-2 ring-red-500/20' => $errors->has($name),
    ])>
        <label
            for="{{ $inputId }}"
            class="inline-flex shrink-0 cursor-pointer items-center gap-2 rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-blue-700"
        >
            <i data-lucide="upload" class="h-4 w-4"></i>
            Choose {{ $multiple ? 'files' : 'file' }}
        </label>
        <span
            id="{{ $inputId }}_label"
            class="min-w-0 flex-1 truncate text-sm text-gray-500"
            data-file-label
        >No file chosen</span>
        {{-- No "capture" attribute: phones then offer Camera or Gallery/Files in their own chooser. --}}
        <input
            type="file"
            name="{{ $name }}"
            id="{{ $inputId }}"
            accept="{{ $accept }}"
            @if($required) required @endif
            @if($multiple) multiple @endif
            class="sr-only"
            data-file-input
        >
    </div>
    @error($name)
        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
    @enderror
</div>

@once
    <script>
        (() => {
            const renderLabel = (input) => {
                const label = document.getElementById(input.id + '_label');
                if (!label) return;
                const files = input.files ? Array.from(input.files) : [];
                label.textContent = files.length === 0
                    ? 'No file chosen'
                    : files.length === 1 ? files[0].name : files.length + ' files selected';
            };

            document.addEventListener('change', (event) => {
                const el = event.target;
                if (el instanceof HTMLInputElement && el.hasAttribute('data-file-input')) {
                    renderLabel(el);
                }
            });

            document.addEventListener('reset', (event) => {
                const form = event.target;
                setTimeout(() => {
                    form.querySelectorAll?.('[data-file-input]').forEach(renderLabel);
                }, 0);
            });
        })();
    </script>
@endonce
