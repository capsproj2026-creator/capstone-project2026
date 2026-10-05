@props([
    'name',
    'id' => null,
    'label',
    'required' => false,
    'accept' => 'image/*',
    // Camera used by the phone "Take photo" button: "environment" (back) or "user" (selfie).
    'capture' => 'environment',
    'multiple' => false,
    'maxFiles' => null,
])

@php
    $inputId = $id ?? preg_replace('/[^A-Za-z0-9_-]/', '_', $name);
    $acceptsImages = str_contains($accept, 'image');
@endphp

<div {{ $attributes->merge(['class' => '']) }}>
    <label for="{{ $inputId }}" class="mb-1.5 block text-sm font-medium text-gray-700">
        {{ $label }}
        @if ($required)
            <span class="text-red-500">*</span>
        @endif
    </label>
    <div @class([
        'flex flex-wrap items-center gap-2 rounded-lg border bg-white px-3 py-2 shadow-sm transition-colors sm:gap-3',
        'border-gray-300 focus-within:border-blue-500 focus-within:ring-2 focus-within:ring-blue-500/20' => ! $errors->has($name),
        'border-red-500 ring-2 ring-red-500/20' => $errors->has($name),
    ])>
        @if ($acceptsImages)
            {{-- Phones/tablets only: the main picker below always offers the gallery. --}}
            <label
                for="{{ $inputId }}_camera"
                class="hidden shrink-0 cursor-pointer items-center gap-2 rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-blue-700 pointer-coarse:inline-flex"
            >
                <i data-lucide="camera" class="h-4 w-4"></i>
                Take photo
            </label>
        @endif
        <label
            for="{{ $inputId }}"
            @class([
                'inline-flex shrink-0 cursor-pointer items-center gap-2 rounded-md px-4 py-2 text-sm font-medium transition-colors',
                'bg-blue-600 text-white hover:bg-blue-700',
                'pointer-coarse:border pointer-coarse:border-blue-600 pointer-coarse:bg-white pointer-coarse:text-blue-700 pointer-coarse:hover:bg-blue-50' => $acceptsImages,
            ])
        >
            <i data-lucide="{{ $acceptsImages ? 'image' : 'upload' }}" class="h-4 w-4"></i>
            @if ($acceptsImages)
                <span class="pointer-coarse:hidden">Choose {{ $multiple ? 'files' : 'file' }}</span>
                <span class="hidden pointer-coarse:inline">Gallery</span>
            @else
                Choose {{ $multiple ? 'files' : 'file' }}
            @endif
        </label>
        <span
            id="{{ $inputId }}_label"
            class="min-w-0 flex-1 basis-full truncate text-sm text-gray-500 sm:basis-auto"
            data-file-label
        >No file chosen</span>
        <input
            type="file"
            name="{{ $name }}"
            id="{{ $inputId }}"
            accept="{{ $accept }}"
            @if($required) required @endif
            @if($multiple) multiple @endif
            @if($maxFiles) data-max-files="{{ (int) $maxFiles }}" @endif
            class="sr-only"
            data-file-input
        >
        @if ($acceptsImages)
            <input
                type="file"
                id="{{ $inputId }}_camera"
                accept="image/*"
                capture="{{ $capture ?: 'environment' }}"
                class="sr-only"
                tabindex="-1"
                aria-hidden="true"
                data-camera-for="{{ $inputId }}"
            >
        @endif
    </div>
    @error($name)
        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
    @enderror
</div>

@once
    <script>
        (() => {
            const labelFor = (input) => document.getElementById(input.id + '_label');

            const renderLabel = (input) => {
                const label = labelFor(input);
                if (!label) return;
                const files = input.files ? Array.from(input.files) : [];
                label.textContent = files.length === 0
                    ? 'No file chosen'
                    : files.length === 1 ? files[0].name : files.length + ' files selected';
            };

            // Move the camera shot into the real (named) input so forms, validation,
            // and existing change listeners (OCR scans, previews) keep working.
            const adoptCameraFiles = (camera) => {
                const main = document.getElementById(camera.dataset.cameraFor);
                if (!main || !camera.files || camera.files.length === 0) return;

                const picked = Array.from(camera.files);
                let files = main.multiple ? Array.from(main.files || []).concat(picked) : picked.slice(0, 1);
                const max = parseInt(main.dataset.maxFiles || '', 10);
                if (max > 0) files = files.slice(-max);

                try {
                    const dt = new DataTransfer();
                    files.forEach((file) => dt.items.add(file));
                    main.files = dt.files;
                    camera.value = '';
                } catch (e) {
                    // Old browsers without DataTransfer: submit the camera input instead.
                    camera.name = main.name;
                    camera.required = main.required;
                    main.removeAttribute('name');
                    main.required = false;
                    const label = labelFor(main);
                    if (label) label.textContent = picked[0].name;
                    return;
                }
                main.dispatchEvent(new Event('change', { bubbles: true }));
            };

            document.addEventListener('change', (event) => {
                const el = event.target;
                if (!(el instanceof HTMLInputElement)) return;
                if (el.dataset.cameraFor !== undefined) {
                    adoptCameraFiles(el);
                } else if (el.hasAttribute('data-file-input')) {
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
