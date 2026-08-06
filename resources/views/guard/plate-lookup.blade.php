@extends('layouts.guard')

@section('title', 'Plate Lookup')

@section('content')
    @include('partials.shell.page-header', [
        'title' => 'Plate Lookup',
        'subtitle' => 'Scan or enter a plate number to identify the vehicle owner',
    ])

    <div class="mx-auto max-w-2xl">
        <form id="plate-lookup-form" method="post" action="{{ route('guard.plate-lookup') }}" class="mb-6 rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            @csrf
            <label for="plate" class="block text-sm font-medium text-gray-700">Plate number</label>
            <div class="mt-2 flex flex-col gap-3 sm:flex-row">
                <input
                    type="text"
                    id="plate"
                    name="plate"
                    value="{{ old('plate', $plate) }}"
                    placeholder="e.g. ABC-1234"
                    autocomplete="off"
                    autofocus
                    class="flex-1 rounded-lg border border-gray-300 px-4 py-3 text-lg font-semibold uppercase tracking-wide text-gray-900 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-200"
                >
                <button
                    type="submit"
                    class="inline-flex shrink-0 items-center justify-center gap-2 rounded-lg bg-emerald-600 px-6 py-3 text-sm font-semibold text-white hover:bg-emerald-700"
                >
                    <i data-lucide="scan" class="h-4 w-4"></i>
                    Lookup
                </button>
            </div>
            <p class="mt-2 text-xs text-gray-500">Hyphens and spaces are ignored. Matches students, staff, and active visitors.</p>
        </form>

        <div id="plate-result">
            @if ($result)
                @include('guard.partials.plate-lookup-result', ['result' => $result])
            @endif
        </div>
    </div>
@endsection

@push('scripts')
<script>
(() => {
    const form = document.getElementById('plate-lookup-form');
    const plateInput = document.getElementById('plate');
    const resultWrap = document.getElementById('plate-result');
    const lookupUrl = @json(route('guard.plate-lookup.lookup'));

    const renderResult = (data) => {
        const registered = !!data.registered;
        const unreadable = !data.plate;
        const border = registered ? 'border-emerald-300' : 'border-amber-300';
        const bg = registered ? 'bg-emerald-50' : 'bg-amber-50';
        const title = registered
            ? (data.owner_name || 'Registered Vehicle')
            : (unreadable ? 'Enter a plate number' : 'Unknown Vehicle');
        const status = data.registration_status || (registered ? 'Registered' : 'Plate Not Registered');

        let details = '';
        if (registered) {
            const rows = [
                ['Role', data.role],
                ['ID Number', data.id_number],
                ['Vehicle', data.vehicle_details],
                ['Department', data.department],
                ['Purpose', data.purpose],
            ].filter(([, v]) => v);
            details = rows.map(([label, value]) => `
                <div class="rounded-lg bg-white/70 px-4 py-3">
                    <p class="text-xs font-medium text-gray-500">${label}</p>
                    <p class="mt-1 text-sm font-semibold text-gray-900">${value}</p>
                </div>
            `).join('');
        }

        resultWrap.innerHTML = `
            <div class="overflow-hidden rounded-2xl border-2 ${border} ${bg} shadow-sm">
                <div class="px-6 py-8 text-center">
                    <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Plate</p>
                    <p class="mt-1 text-3xl font-bold tracking-wide text-gray-900">${data.plate || '—'}</p>
                    <h2 class="mt-4 text-2xl font-bold ${registered ? 'text-emerald-800' : 'text-amber-800'}">${title}</h2>
                    <p class="mt-2 text-sm font-medium ${registered ? 'text-emerald-700' : 'text-amber-700'}">${status}</p>
                </div>
                ${details ? `<div class="grid grid-cols-1 gap-3 border-t border-white/60 px-5 py-5 sm:grid-cols-2">${details}</div>` : ''}
            </div>
        `;
    };

    form?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const plate = (plateInput?.value || '').trim();
        if (!plate) return;

        try {
            const response = await fetch(lookupUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': @json(csrf_token()),
                },
                body: JSON.stringify({ plate }),
                credentials: 'same-origin',
            });
            if (!response.ok) return;
            const payload = await response.json();
            if (payload?.data) renderResult(payload.data);
        } catch (e) {
            form.submit();
        }
    });

    if (window.lucide) window.lucide.createIcons();
})();
</script>
@endpush
