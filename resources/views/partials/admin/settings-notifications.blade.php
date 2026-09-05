<div class="mb-6 overflow-hidden rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div class="min-w-0">
            <h3 class="text-lg font-semibold text-gray-900">General Information</h3>
            <p class="mt-1 text-sm text-gray-500">Official parking general information shown on the user dashboard. Disable an item to hide it without deleting.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <button
                type="button"
                id="open-add-campus-notice"
                class="inline-flex shrink-0 items-center justify-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-800 hover:bg-gray-50"
            >
                <i data-lucide="plus" class="h-4 w-4"></i>
                Add Item
            </button>
            <button
                type="submit"
                form="campus-notices-save-form"
                class="inline-flex shrink-0 items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700"
            >
                <i data-lucide="save" class="h-4 w-4"></i>
                Save
            </button>
        </div>
    </div>

    <form
        id="campus-notices-save-form"
        method="POST"
        action="{{ route('admin.settings.general.save') }}"
        class="space-y-3"
    >
        @csrf
        @forelse ($generalInfo as $item)
            @php
                $isActive = $item->isActive();
                $description = trim((string) ($item->description ?? ''));
            @endphp
            <div class="flex flex-col gap-4 rounded-xl border border-gray-200 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                <div class="min-w-0 flex-1">
                    <div class="mb-1 flex flex-wrap items-center gap-2">
                        <h4 class="text-sm font-semibold text-gray-900">Item #{{ $item->id }}</h4>
                        <span class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $isActive ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                            {{ $isActive ? 'Enabled' : 'Disabled' }}
                        </span>
                    </div>
                    <p class="text-sm text-gray-600 {{ $isActive ? '' : 'opacity-60' }}">
                        {{ $description !== '' ? $description : 'No description provided' }}
                    </p>
                </div>

                <div class="flex shrink-0 items-center gap-2 sm:gap-3">
                    <label class="relative inline-flex h-6 w-11 cursor-pointer items-center" title="{{ $isActive ? 'Enabled' : 'Disabled' }}">
                        <input
                            type="checkbox"
                            name="active[]"
                            value="{{ $item->id }}"
                            class="peer sr-only"
                            @checked($isActive)
                        >
                        <span class="absolute inset-0 rounded-full bg-gray-300 transition peer-checked:bg-green-500 peer-focus:ring-2 peer-focus:ring-green-500/30"></span>
                        <span class="absolute left-0.5 top-0.5 h-5 w-5 rounded-full bg-white shadow transition peer-checked:translate-x-5"></span>
                    </label>

                    <button
                        type="button"
                        class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50"
                        data-edit-campus-notice
                        data-id="{{ $item->id }}"
                        data-description="{{ $description }}"
                    >
                        Edit
                    </button>

                    <button
                        type="submit"
                        form="campus-notice-delete-{{ $item->id }}"
                        class="rounded-lg border border-gray-200 bg-white p-2 text-gray-500 hover:border-red-200 hover:bg-red-50 hover:text-red-600"
                        title="Delete"
                        onclick="return confirm('Delete this General Information item? This cannot be undone.')"
                    >
                        <i data-lucide="trash-2" class="h-4 w-4"></i>
                    </button>
                </div>
            </div>
        @empty
            <p class="rounded-xl border border-dashed border-gray-200 px-4 py-10 text-center text-sm text-gray-500">
                No General Information items yet. Use Add Item to create one.
            </p>
        @endforelse
    </form>

    @foreach ($generalInfo as $item)
        <form
            id="campus-notice-delete-{{ $item->id }}"
            method="POST"
            action="{{ route('admin.settings.general.destroy', $item->id) }}"
            class="hidden"
        >
            @csrf
            @method('DELETE')
        </form>
    @endforeach
</div>

{{-- Add / Edit Campus Notice Modal --}}
<div id="campus-notice-modal" class="fixed inset-0 z-50 hidden items-start justify-center overflow-y-auto bg-black/50 p-4 sm:items-center">
    <div class="my-8 w-full max-w-lg rounded-xl bg-white p-5 shadow-xl sm:my-4 sm:p-6">
        <div class="mb-4 flex items-center justify-between gap-3">
            <h3 id="campus-notice-modal-title" class="text-lg font-semibold text-gray-900">Add General Information</h3>
            <button type="button" data-close-modal="campus-notice-modal" class="rounded-lg p-1.5 text-gray-500 hover:bg-gray-100">
                <i data-lucide="x" class="h-5 w-5"></i>
            </button>
        </div>

        <form id="campus-notice-form" method="POST" action="{{ route('admin.settings.general.store') }}" class="space-y-4">
            @csrf
            <input type="hidden" name="_method" id="campus-notice-form-method" value="POST">

            <div class="min-w-0">
                <label for="campus_notice_description" class="mb-1.5 block text-sm font-semibold text-gray-800">Information Text</label>
                <textarea
                    name="description"
                    id="campus_notice_description"
                    required
                    rows="5"
                    maxlength="2000"
                    placeholder="e.g. Parking is authorized only in the designated parking areas."
                    class="w-full resize-y rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20"
                ></textarea>
            </div>

            <div class="flex flex-col-reverse justify-end gap-2 sm:flex-row">
                <button type="button" data-close-modal="campus-notice-modal" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                    Cancel
                </button>
                <button type="submit" class="rounded-lg bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-black">
                    Save Item
                </button>
            </div>
        </form>
    </div>
</div>
