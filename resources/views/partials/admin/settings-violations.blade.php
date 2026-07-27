<div class="overflow-hidden rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div class="min-w-0">
            <h3 class="text-lg font-semibold text-gray-900">Violation Types</h3>
            <p class="mt-1 text-sm text-gray-500">Manage rules and set active status</p>
        </div>
        <button
            type="button"
            id="open-add-violation"
            class="inline-flex shrink-0 items-center justify-center gap-2 rounded-lg bg-gray-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-black"
        >
            <i data-lucide="plus" class="h-4 w-4"></i>
            Add Type
        </button>
    </div>

    <div class="space-y-3">
        @forelse ($violationTypes as $type)
            @php
                $isActive = strcasecmp((string) ($type->status ?? ''), 'Active') === 0;
                $description = trim((string) ($type->description ?? ''));
            @endphp
            <div class="flex flex-col gap-4 rounded-xl border border-gray-200 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                <div class="min-w-0 flex-1">
                    <h4 class="text-base font-semibold text-gray-900">{{ $type->violation_name }}</h4>
                    <p class="mt-1 text-sm text-gray-500">
                        <span class="font-medium text-gray-600">Description:</span>
                        {{ $description !== '' ? $description : 'No description provided' }}
                    </p>
                </div>

                <div class="flex shrink-0 items-center gap-2 sm:gap-3">
                    <form method="POST" action="{{ route('admin.settings.violations.toggle', $type->id) }}" class="inline-flex">
                        @csrf
                        <label class="relative inline-flex h-6 w-11 cursor-pointer items-center" title="{{ $isActive ? 'Active' : 'Inactive' }}">
                            <input
                                type="checkbox"
                                class="peer sr-only"
                                @checked($isActive)
                                onchange="this.form.submit()"
                            >
                            <span class="absolute inset-0 rounded-full bg-gray-300 transition peer-checked:bg-green-500 peer-focus:ring-2 peer-focus:ring-green-500/30"></span>
                            <span class="absolute left-0.5 top-0.5 h-5 w-5 rounded-full bg-white shadow transition peer-checked:translate-x-5"></span>
                        </label>
                    </form>

                    <button
                        type="button"
                        class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50"
                        data-edit-violation
                        data-id="{{ $type->id }}"
                        data-name="{{ $type->violation_name }}"
                        data-description="{{ $description }}"
                    >
                        Edit
                    </button>

                    <form
                        method="POST"
                        action="{{ route('admin.settings.violations.destroy', $type->id) }}"
                        onsubmit="return confirm('Delete this violation type? This cannot be undone.')"
                    >
                        @csrf
                        @method('DELETE')
                        <button
                            type="submit"
                            class="rounded-lg border border-gray-200 bg-white p-2 text-gray-500 hover:border-red-200 hover:bg-red-50 hover:text-red-600"
                            title="Delete"
                        >
                            <i data-lucide="trash-2" class="h-4 w-4"></i>
                        </button>
                    </form>
                </div>
            </div>
        @empty
            <p class="rounded-xl border border-dashed border-gray-200 px-4 py-10 text-center text-sm text-gray-500">
                No violation types yet. Use Add Type to create one.
            </p>
        @endforelse
    </div>
</div>

{{-- Add / Edit Violation Modal --}}
<div id="violation-type-modal" class="fixed inset-0 z-50 hidden items-start justify-center overflow-y-auto bg-black/50 p-4 sm:items-center">
    <div class="my-8 w-full max-w-lg rounded-xl bg-white p-5 shadow-xl sm:my-4 sm:p-6">
        <div class="mb-4 flex items-center justify-between gap-3">
            <h3 id="violation-modal-title" class="text-lg font-semibold text-gray-900">Add Violation Type</h3>
            <button type="button" data-close-modal="violation-type-modal" class="rounded-lg p-1.5 text-gray-500 hover:bg-gray-100">
                <i data-lucide="x" class="h-5 w-5"></i>
            </button>
        </div>

        <form id="violation-type-form" method="POST" action="{{ route('admin.settings.violations.store') }}" class="space-y-4">
            @csrf
            <input type="hidden" name="_method" id="violation-form-method" value="POST">

            <div class="min-w-0">
                <label for="violation_name" class="mb-1.5 block text-sm font-semibold text-gray-800">Violation Name</label>
                <input
                    type="text"
                    name="violation_name"
                    id="violation_name"
                    required
                    maxlength="255"
                    class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20"
                >
            </div>

            <div class="min-w-0">
                <label for="violation_description" class="mb-1.5 block text-sm font-semibold text-gray-800">Description</label>
                <textarea
                    name="description"
                    id="violation_description"
                    required
                    rows="4"
                    maxlength="500"
                    placeholder="e.g. Vehicle exceeded the campus speed limit"
                    class="w-full resize-y rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20"
                ></textarea>
            </div>

            <div class="flex flex-col-reverse justify-end gap-2 sm:flex-row">
                <button type="button" data-close-modal="violation-type-modal" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                    Cancel
                </button>
                <button type="submit" id="violation-modal-submit" class="rounded-lg bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-black">
                    Save Violation
                </button>
            </div>
        </form>
    </div>
</div>
