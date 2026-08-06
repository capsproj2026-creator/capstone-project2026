<div
    id="violation-evidence-modal"
    class="fixed inset-0 z-[60] hidden items-center justify-center bg-black/70 p-4 backdrop-blur-[1px]"
    role="dialog"
    aria-modal="true"
    aria-labelledby="violation-evidence-modal-title"
    aria-hidden="true"
>
    <div class="relative flex max-h-[92vh] w-full max-w-4xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
        <div class="flex items-center justify-between border-b border-gray-200 px-5 py-4">
            <div class="min-w-0">
                <h3 id="violation-evidence-modal-title" class="truncate text-lg font-semibold text-gray-900">Violation Evidence</h3>
                <p id="violation-evidence-modal-counter" class="mt-0.5 text-xs text-gray-500"></p>
            </div>
            <button
                type="button"
                id="violation-evidence-modal-close"
                class="rounded-lg p-2 text-gray-500 transition hover:bg-gray-100"
                aria-label="Close evidence preview"
            >
                <i data-lucide="x" class="h-5 w-5"></i>
            </button>
        </div>

        <div class="relative flex min-h-0 flex-1 items-center justify-center bg-gray-950/95 p-4">
            <button
                type="button"
                id="violation-evidence-prev"
                class="absolute left-3 z-10 hidden rounded-full bg-white/90 p-2 text-gray-800 shadow-lg transition hover:bg-white"
                aria-label="Previous image"
            >
                <i data-lucide="chevron-left" class="h-5 w-5"></i>
            </button>

            <img
                id="violation-evidence-image"
                src=""
                alt="Violation evidence"
                class="max-h-[70vh] max-w-full rounded-lg object-contain shadow-lg"
                decoding="async"
            >

            <button
                type="button"
                id="violation-evidence-next"
                class="absolute right-3 z-10 hidden rounded-full bg-white/90 p-2 text-gray-800 shadow-lg transition hover:bg-white"
                aria-label="Next image"
            >
                <i data-lucide="chevron-right" class="h-5 w-5"></i>
            </button>
        </div>

        <div id="violation-evidence-thumbs" class="hidden flex gap-2 overflow-x-auto border-t border-gray-200 bg-gray-50 px-4 py-3"></div>

        <div class="flex justify-end gap-2 border-t border-gray-200 px-5 py-4">
            <a
                id="violation-evidence-open-tab"
                href="#"
                target="_blank"
                rel="noopener"
                class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-800 hover:bg-gray-50"
            >
                <i data-lucide="external-link" class="h-4 w-4"></i>
                Open full size
            </a>
            <button
                type="button"
                id="violation-evidence-close-btn"
                class="rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-800"
            >
                Close
            </button>
        </div>
    </div>
</div>
