@extends('layouts.guard')

@section('title', 'Violation Records')

@section('content')
    @include('partials.shell.page-header', [
        'title' => 'Violation Records',
        'subtitle' => 'Log citations for registered or unregistered plates',
    ])

    <div id="violation-flash" class="mb-4 hidden"></div>

    @if ($success)
        @php($loggedCount = max(1, (int) request()->query('count', 1)))
        <div class="mb-4 flex gap-2 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
            <i data-lucide="circle-check" class="mt-0.5 h-4 w-4 shrink-0"></i>
            <span>
                @if (request()->boolean('unregistered'))
                    {{ $loggedCount }} {{ $loggedCount === 1 ? 'violation' : 'violations' }} logged for an unregistered plate. The owner will be emailed automatically when that plate is registered.
                @else
                    {{ $loggedCount }} {{ $loggedCount === 1 ? 'violation' : 'violations' }} logged successfully.
                @endif
                @if (request()->boolean('locked'))
                    <strong>The violator's account has been permanently locked (3/3 strikes).</strong>
                @endif
            </span>
        </div>
    @endif
    @if ($error === 'invalid_plate')
        <div class="mb-4 flex gap-2 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            <i data-lucide="alert-circle" class="mt-0.5 h-4 w-4 shrink-0"></i>
            <span>Enter a valid plate number.</span>
        </div>
    @endif

    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-gray-500">{{ $logs->total() }} citation{{ $logs->total() === 1 ? '' : 's' }} on record</p>
        <button
            type="button"
            id="open-violation-modal"
            class="inline-flex cursor-pointer items-center gap-2 rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-blue-700"
        >
            <i data-lucide="plus" class="h-4 w-4"></i>
            Log Violation
        </button>
    </div>

    <div class="mb-6 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
        <div class="border-b border-gray-100 bg-gray-50 px-6 py-4">
            <h3 class="text-sm font-semibold text-gray-900">GSU Offense Levels</h3>
            <p class="mt-1 text-xs text-gray-500">Official sanctions applied as strikes accumulate.</p>
        </div>
        <div class="grid grid-cols-1 gap-3 p-4 sm:grid-cols-3">
            @foreach ([1, 2, 3] as $level)
                <div class="rounded-xl border border-gray-100 bg-gray-50/80 px-4 py-3">
                    <p class="text-xs font-semibold text-gray-800">{{ \App\Support\ViolationSanctionPresenter::nameForStrike($level) ?? ($level.' Offense') }}</p>
                    <p class="mt-1 text-xs leading-relaxed text-gray-600">{{ \App\Support\ViolationSanctionPresenter::descriptionForStrike($level) ?? '—' }}</p>
                </div>
            @endforeach
        </div>
    </div>

    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-gray-200 bg-gray-50 text-gray-500">
                    <tr>
                        <th class="px-6 py-3 font-medium">Plate</th>
                        <th class="px-6 py-3 font-medium">Name</th>
                        <th class="px-6 py-3 font-medium">Type</th>
                        <th class="px-6 py-3 font-medium">Evidence</th>
                        <th class="px-6 py-3 font-medium">Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100" id="violations-table-body">
                    @forelse ($logs as $row)
                        <tr class="hover:bg-gray-50/80">
                            <td class="px-6 py-4"><code class="rounded bg-gray-100 px-1.5 py-0.5 text-xs font-semibold">{{ $row->plate_number }}</code></td>
                            <td class="px-6 py-4 font-medium text-gray-900">
                                {{ $row->violator_name ?: 'Unregistered Vehicle' }}
                                @if (! $row->user_id)
                                    <span class="mt-0.5 block text-xs font-normal text-amber-600">Pending owner registration</span>
                                @elseif (filled($row->vehicle_details))
                                    <p class="mt-0.5 text-xs font-normal text-gray-500">{{ $row->vehicle_details }}</p>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex flex-wrap gap-1">
                                    @foreach ($row->typeList() as $typeName)
                                        <span class="rounded-full bg-red-50 px-2.5 py-1 text-xs font-medium text-red-700">{{ $typeName }}</span>
                                    @endforeach
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <x-violation.evidence-panel :log="$row" route-name="guard.violations.evidence" compact />
                            </td>
                            <td class="px-6 py-4 text-gray-600">{{ ph_datetime($row->created_at) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-6 py-14 text-center text-gray-500">No violations logged yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($logs->hasPages())
            <div class="border-t border-gray-200 px-6 py-4">{{ $logs->links() }}</div>
        @endif
    </div>

    <div id="violationModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4">
        <div class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl bg-white p-6 shadow-xl">
            <h3 class="mb-1 text-lg font-semibold text-gray-900">Log Violation</h3>
            <p class="mb-4 text-sm text-gray-500">Enter any plate — registered owners are notified now; unregistered plates notify when registered.</p>
            <form id="violation-log-form" method="POST" action="{{ route('guard.violations.store') }}" enctype="multipart/form-data" class="space-y-4">
                @csrf
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Plate Number</label>
                    <input
                        type="text"
                        name="plate_number"
                        id="plate_number_input"
                        list="registered-plates-list"
                        required
                        autocomplete="off"
                        placeholder="Enter plate number..."
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 uppercase focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20"
                    >
                    <datalist id="registered-plates-list">
                        @foreach ($registeredPlates as $vehicle)
                            <option value="{{ $vehicle->plate_number }}">{{ $vehicle->fullname }} ({{ $vehicle->plate_number }})</option>
                        @endforeach
                    </datalist>
                    @if ($registeredPlates->isNotEmpty())
                        <details class="mt-2">
                            <summary class="cursor-pointer text-xs font-medium text-blue-600">Browse registered plates</summary>
                            <div class="mt-2 max-h-40 overflow-y-auto rounded-lg border border-gray-200 bg-gray-50 p-2">
                                <ul class="space-y-1 text-xs text-gray-700">
                                    @foreach ($registeredPlates as $vehicle)
                                        <li>
                                            <button
                                                type="button"
                                                class="w-full cursor-pointer rounded px-2 py-1 text-left hover:bg-white"
                                                onclick="document.getElementById('plate_number_input').value = '{{ $vehicle->plate_number }}'"
                                            >
                                                <code class="font-semibold">{{ $vehicle->plate_number }}</code>
                                                — {{ $vehicle->fullname }}
                                            </button>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        </details>
                    @endif
                    <p class="mt-1 text-xs text-gray-500">Unregistered plates are saved and emailed to the owner after they register that plate.</p>
                </div>
                <div>
                    <p class="mb-2 text-sm font-medium text-gray-700">Violation Type <span class="text-red-600">*</span></p>
                    <p class="mb-2 text-xs text-gray-500">Select one or more violations for this citation.</p>
                    <div class="space-y-2 rounded-xl border border-gray-200 bg-gray-50 p-3" id="violation-type-checks">
                        @foreach ($violationTypes as $type)
                            <label class="flex cursor-pointer items-start gap-3 rounded-lg bg-white px-3 py-2.5 shadow-sm ring-1 ring-gray-100 hover:ring-blue-200">
                                <input
                                    type="checkbox"
                                    name="violation_types[]"
                                    value="{{ $type->violation_name }}"
                                    class="mt-1 h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                >
                                <span class="min-w-0">
                                    <span class="block text-sm font-semibold text-gray-900">{{ $type->violation_name }}</span>
                                    @if (filled($type->description))
                                        <span class="mt-0.5 block text-xs text-gray-500">{{ $type->description }}</span>
                                    @endif
                                </span>
                            </label>
                        @endforeach
                    </div>
                    <p id="violation-types-error" class="mt-1 hidden text-xs text-red-600">Select at least one violation type.</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Description <span class="text-red-600">*</span></label>
                    <textarea name="description" rows="3" required minlength="3" class="w-full rounded-lg border border-gray-300 px-3 py-2"></textarea>
                    <p id="violation-description-error" class="mt-1 hidden text-xs text-red-600">Enter a description before submitting.</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Photo Evidence <span class="text-red-600">*</span></label>
                    <input
                        type="file"
                        name="evidence_photos[]"
                        accept="image/*"
                        multiple
                        required
                        class="w-full cursor-pointer rounded-lg border border-gray-300 px-3 py-2 text-sm"
                    >
                    <p class="mt-1 text-xs text-gray-500">Required — upload at least one image (up to 5).</p>
                    <p id="violation-evidence-error" class="mt-1 hidden text-xs text-red-600">Add at least one photo before submitting.</p>
                </div>
                <div class="flex gap-3">
                    <button type="button" id="close-violation-modal" class="flex-1 cursor-pointer rounded-lg border border-gray-300 py-2 text-sm font-medium">Cancel</button>
                    <button type="submit" id="violation-submit-btn" class="flex-1 cursor-pointer rounded-lg bg-blue-600 py-2 text-sm font-medium text-white hover:bg-blue-700">Submit</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
<script>
(() => {
    const modal = document.getElementById('violationModal');
    const form = document.getElementById('violation-log-form');
    const flash = document.getElementById('violation-flash');
    const openBtn = document.getElementById('open-violation-modal');
    const closeBtn = document.getElementById('close-violation-modal');
    const submitBtn = document.getElementById('violation-submit-btn');
    const typesError = document.getElementById('violation-types-error');

    const openModal = () => {
        modal?.classList.remove('hidden');
        modal?.classList.add('flex');
    };
    const closeModal = () => {
        modal?.classList.add('hidden');
        modal?.classList.remove('flex');
    };

    openBtn?.addEventListener('click', openModal);
    closeBtn?.addEventListener('click', closeModal);
    modal?.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });

    const showFlash = (ok, message) => {
        if (!flash) return;
        flash.className = `mb-4 flex gap-2 rounded-xl border px-4 py-3 text-sm ${ok
            ? 'border-green-200 bg-green-50 text-green-800'
            : 'border-red-200 bg-red-50 text-red-800'}`;
        flash.innerHTML = `<span>${message}</span>`;
        flash.classList.remove('hidden');
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    form?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const checked = form.querySelectorAll('input[name="violation_types[]"]:checked');
        const description = (form.querySelector('[name="description"]')?.value || '').trim();
        const evidence = form.querySelector('[name="evidence_photos[]"]');
        const descriptionError = document.getElementById('violation-description-error');
        const evidenceError = document.getElementById('violation-evidence-error');
        if (!checked.length) {
            typesError?.classList.remove('hidden');
            return;
        }
        typesError?.classList.add('hidden');
        if (description.length < 3) {
            descriptionError?.classList.remove('hidden');
            return;
        }
        descriptionError?.classList.add('hidden');
        if (!evidence || !evidence.files || evidence.files.length < 1) {
            evidenceError?.classList.remove('hidden');
            return;
        }
        evidenceError?.classList.add('hidden');

        if (form.dataset.submitting === '1') {
            return;
        }
        form.dataset.submitting = '1';

        const fd = new FormData(form);
        // Keep only checked violation types (prevents stale/duplicate fields).
        fd.delete('violation_types[]');
        checked.forEach((el) => fd.append('violation_types[]', el.value));

        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Saving…';
        }

        try {
            const res = await fetch(form.action, {
                method: 'POST',
                body: fd,
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok || !data.ok) {
                const msg = data.message || 'Unable to log violation.';
                showFlash(false, msg);
                form.dataset.submitting = '0';
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Submit';
                }
                return;
            }
            const savedCount = Number(data.count || checked.length || 1);
            showFlash(true, data.message || `${savedCount} violation${savedCount === 1 ? '' : 's'} logged successfully.`);
            closeModal();
            form.reset();
            window.setTimeout(() => { window.location.reload(); }, 400);
        } catch (err) {
            showFlash(false, 'Network error — please try again.');
            form.dataset.submitting = '0';
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Submit';
            }
        }
    });
})();
</script>
@endpush
