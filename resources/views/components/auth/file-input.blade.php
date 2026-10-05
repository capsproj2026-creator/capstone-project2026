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

    // Android Chrome/Brave open the camera-less Photo Picker when every accepted type is an
    // image. One non-image type brings back the Camera / Media picker chooser. Desktop
    // browsers ignore the unknown type, and the server still validates the upload.
    $acceptTypes = array_filter(array_map('trim', explode(',', $accept)));
    $imageOnly = $acceptTypes !== [] && collect($acceptTypes)->every(
        fn (string $t) => str_starts_with(strtolower($t), 'image/')
            || in_array(strtolower($t), ['.jpg', '.jpeg', '.png', '.gif', '.webp', '.heic', '.heif'], true)
    );
    $acceptAttr = $imageOnly ? $accept.',android/force-camera-workaround' : $accept;
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
            accept="{{ $acceptAttr }}"
            @if($required) required @endif
            @if($multiple) multiple @endif
            @if($imageOnly) data-image-only @endif
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

            const isImage = (file) => file.type
                ? file.type.startsWith('image/')
                : /\.(jpe?g|png|gif|webp|heic|heif)$/i.test(file.name);

            // Capture phase: runs before page listeners (e.g. license OCR) so a non-photo
            // picked through the wider Android chooser never reaches them.
            document.addEventListener('change', (event) => {
                const el = event.target;
                if (!(el instanceof HTMLInputElement) || !el.hasAttribute('data-image-only')) return;
                const files = el.files ? Array.from(el.files) : [];
                if (files.length === 0 || files.every(isImage)) return;

                el.value = '';
                event.stopImmediatePropagation();
                const label = document.getElementById(el.id + '_label');
                if (label) label.textContent = 'Please choose a photo (JPG or PNG).';
            }, true);

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
