<div class="overflow-hidden rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div class="min-w-0">
            <h3 class="text-lg font-semibold text-gray-900">Violation Types</h3>
                    <p class="mt-1 text-sm text-gray-500">Add a campus violation type, then toggle it active. Active types appear when a guard logs a citation.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <button
                type="button"
                id="open-add-violation"
                class="inline-flex shrink-0 items-center justify-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-800 hover:bg-gray-50"
            >
                <i data-lucide="plus" class="h-4 w-4"></i>
                Add Violation
            </button>
            <button
                type="submit"
                form="violation-types-save-form"
                class="inline-flex shrink-0 items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700"
            >
                <i data-lucide="save" class="h-4 w-4"></i>
                Save
            </button>
        </div>
    </div>

    <form
        id="violation-types-save-form"
        method="POST"
        action="{{ route('admin.settings.violations.save') }}"
        class="space-y-3"
    >
        @csrf
        @forelse ($violationTypes as $type)
            @php
                $isActive = strcasecmp((string) ($type->status ?? ''), 'Active') === 0;
                $description = trim((string) ($type->description ?? ''));
            @endphp
            <div class="flex flex-col gap-4 rounded-xl border border-gray-200 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                <div class="min-w-0 flex-1">
                    <div class="mb-1 flex flex-wrap items-center gap-2">
                        <h4 class="text-base font-semibold text-gray-900">{{ $type->violation_name }}</h4>
                        <span class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $isActive ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                            {{ $isActive ? 'Active' : 'Inactive' }}
                        </span>
                    </div>
                    <p class="mt-1 text-sm text-gray-500 {{ $isActive ? '' : 'opacity-60' }}">
                        <span class="font-medium text-gray-600">Description:</span>
                        {{ $description !== '' ? $description : 'No description provided' }}
                    </p>
                </div>

                <div class="flex shrink-0 items-center gap-2 sm:gap-3">
                    <label class="relative inline-flex h-6 w-11 cursor-pointer items-center" title="{{ $isActive ? 'Active' : 'Inactive' }}">
                        <input
                            type="checkbox"
                            name="active[]"
                            value="{{ $type->id }}"
                            class="peer sr-only"
                            @checked($isActive)
                        >
                        <span class="absolute inset-0 rounded-full bg-gray-300 transition peer-checked:bg-green-500 peer-focus:ring-2 peer-focus:ring-green-500/30"></span>
                        <span class="absolute left-0.5 top-0.5 h-5 w-5 rounded-full bg-white shadow transition peer-checked:translate-x-5"></span>
                    </label>

                    <button
                        type="button"
                        class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50"
                        data-edit-violation
                        data-id="{{ $type->id }}"
                        data-name="{{ $type->violation_name }}"
                        data-description="{{ $description }}"
                        data-official="{{ in_array((string) $type->violation_name, \App\Support\TrafficViolations::names(), true) ? '1' : '0' }}"
                    >
                        Edit
                    </button>
                </div>
            </div>
        @empty
            <p class="rounded-xl border border-dashed border-gray-200 px-4 py-10 text-center text-sm text-gray-500">
                No violation types found. Run <code class="text-xs">php artisan db:seed</code> to restore the official list.
            </p>
        @endforelse
    </form>
</div>

{{-- Edit Violation Modal (official names only) --}}
<div id="violation-type-modal" class="fixed inset-0 z-50 hidden items-start justify-center overflow-y-auto bg-black/50 p-4 sm:items-center">
    <div class="my-8 w-full max-w-lg rounded-xl bg-white p-5 shadow-xl sm:my-4 sm:p-6">
        <div class="mb-4 flex items-center justify-between gap-3">
            <h3 id="violation-modal-title" class="text-lg font-semibold text-gray-900">Edit Violation Type</h3>
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
                    class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm text-gray-900 focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20"
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
