@extends('layouts.admin')

@section('title', 'RFID Tag Assignment')

@section('content')
    @php
        $filterLabels = [
            'all' => 'All users',
            'pending' => 'Pending assignment',
            'assigned' => 'RFID assigned',
            'locked' => 'Locked users',
            'denied' => 'Denied users',
        ];
        $activeFilter = $currentFilter ?? 'all';
    @endphp

    @include('partials.shell.page-header', [
        'title' => 'RFID Tag Assignment',
        'subtitle' => 'Assign RFID tags to approved users',
    ])

    @if ($errors->any())
        <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            <p class="font-semibold">RFID approval was not saved:</p>
            <ul class="mt-1 list-disc space-y-1 pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Clickable filter cards --}}
    <div id="rfid-filter-cards" class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5" role="tablist" aria-label="RFID filters">
        @php
            $cards = [
                'all' => ['label' => 'Total Users', 'value' => $stats['total'], 'icon' => 'users', 'tone' => 'blue', 'text' => 'text-gray-900', 'iconBg' => 'bg-blue-50 text-blue-600', 'active' => 'border-blue-300 ring-2 ring-blue-100'],
                'pending' => ['label' => 'Pending Assignment', 'value' => $stats['pending'], 'icon' => 'hash', 'tone' => 'orange', 'text' => 'text-orange-500', 'iconBg' => 'bg-orange-50 text-orange-500', 'active' => 'border-orange-300 ring-2 ring-orange-100'],
                'assigned' => ['label' => 'RFID Assigned', 'value' => $stats['assigned'], 'icon' => 'check-circle', 'tone' => 'emerald', 'text' => 'text-emerald-600', 'iconBg' => 'bg-emerald-50 text-emerald-600', 'active' => 'border-emerald-300 ring-2 ring-emerald-100'],
                'locked' => ['label' => 'Locked Users', 'value' => $stats['locked'], 'icon' => 'lock', 'tone' => 'red', 'text' => 'text-red-600', 'iconBg' => 'bg-red-50 text-red-600', 'active' => 'border-red-300 ring-2 ring-red-100'],
                'denied' => ['label' => 'Denied Users', 'value' => $stats['denied'], 'icon' => 'ban', 'tone' => 'rose', 'text' => 'text-rose-600', 'iconBg' => 'bg-rose-50 text-rose-600', 'active' => 'border-rose-300 ring-2 ring-rose-100'],
            ];
        @endphp

        @foreach ($cards as $key => $card)
            <button
                type="button"
                data-rfid-filter="{{ $key }}"
                @class([
                    'rfid-filter-card flex w-full items-center justify-between rounded-xl border bg-white p-5 text-left shadow-sm transition hover:border-gray-300',
                    $card['active'] => $activeFilter === $key,
                    'border-gray-200' => $activeFilter !== $key,
                ])
                aria-pressed="{{ $activeFilter === $key ? 'true' : 'false' }}"
            >
                <div>
                    <p class="text-sm text-gray-500">{{ $card['label'] }}</p>
                    <p class="mt-1 text-3xl font-bold tracking-tight {{ $card['text'] }}">{{ number_format($card['value']) }}</p>
                </div>
                <div class="flex h-11 w-11 items-center justify-center rounded-xl {{ $card['iconBg'] }}">
                    <i data-lucide="{{ $card['icon'] }}" class="h-5 w-5"></i>
                </div>
            </button>
        @endforeach
    </div>

    {{-- Instant client-side search --}}
    <div class="mb-5">
        <div class="relative">
            <i data-lucide="search" class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400"></i>
            <input
                id="rfid-search"
                type="search"
                value="{{ $search }}"
                placeholder="Search by name, user ID, or plate number..."
                class="w-full rounded-xl border border-gray-200 bg-white py-3 pl-11 pr-4 text-sm text-gray-900 shadow-sm placeholder:text-gray-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20"
            >
        </div>
    </div>

    {{-- Approved Users list (no tabs) --}}
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="border-b border-gray-100 px-5 py-4 sm:px-6">
            <h2 class="text-base font-semibold text-gray-900">Users</h2>
            <p id="rfid-filter-label" class="mt-0.5 text-sm text-gray-500">{{ $filterLabels[$activeFilter] ?? 'All users' }}</p>
        </div>

        <div id="rfid-user-list" class="overflow-x-auto">
            <table class="w-full min-w-[72rem] table-fixed border-collapse text-left">
                <colgroup>
                    <col class="w-[4.25rem]">
                    <col class="w-[14rem]">
                    <col class="w-[16rem]">
                    <col class="w-[12rem]">
                    <col class="w-[9rem]">
                    <col class="w-[9rem]">
                    <col class="w-[8rem]">
                    <col class="w-[10rem]">
                </colgroup>
                <thead>
                    <tr class="border-b border-gray-100 bg-gray-50 text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                        <th scope="col" class="px-4 py-3 sm:px-5">
                            <span class="sr-only">Photo</span>
                        </th>
                        <th scope="col" class="px-3 py-3">Full Name</th>
                        <th scope="col" class="px-3 py-3">Email</th>
                        <th scope="col" class="px-3 py-3">Vehicle</th>
                        <th scope="col" class="px-3 py-3">Phone</th>
                        <th scope="col" class="px-3 py-3">ID Number</th>
                        <th scope="col" class="px-3 py-3">RFID Status</th>
                        <th scope="col" class="px-4 py-3 text-right sm:px-5">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($users as $u)
                        @php
                            /** @var \App\Models\User $u */
                            $roleLabel = $u->displayRoleLabel();
                            $isUnregistered = $u->isUnregisteredStudentFaculty();
                            $isStudent = ! $isUnregistered && strcasecmp($roleLabel, 'Student') === 0;
                            $isStaff = ! $isUnregistered && in_array($roleLabel, ['Staff', 'Faculty'], true);
                            $hasRfid = filled($u->rfid_uid);
                            $gate = $u->Gate_access ?: \App\Models\User::GATE_ACCESS_PENDING;
                            $isDenied = $gate === \App\Models\User::GATE_ACCESS_DENIED || $u->status === \App\Models\User::STATUS_DENIED;
                            $isLocked = $u->isLocked();
                            $vehicleName = $u->vehicleType?->vehicle_name ?: 'Vehicle';
                            $plate = $u->plate_number ?: '—';
                            $phone = $u->phone_number ?: '—';
                            $idNumber = $u->id_number ?: '—';
                            $email = $u->displayEmail();
                            $searchBlob = strtolower(trim(implode(' ', [
                                $u->displayName(),
                                $email,
                                $vehicleName,
                                $plate,
                                $phone,
                                $idNumber,
                                (string) $u->rfid_uid,
                                $roleLabel,
                            ])));
                        @endphp

                        <tr
                            class="rfid-user-row align-middle hover:bg-gray-50/60"
                            data-has-rfid="{{ $hasRfid ? '1' : '0' }}"
                            data-locked="{{ $isLocked ? '1' : '0' }}"
                            data-denied="{{ $isDenied ? '1' : '0' }}"
                            data-search="{{ e($searchBlob) }}"
                        >
                            <td class="px-4 py-4 sm:px-5">
                                <x-portal.avatar :user="$u" size="lg" class="!ring-0" />
                            </td>

                            <td class="px-3 py-4">
                                <p class="truncate text-sm font-semibold text-gray-900" title="{{ $u->displayName() }}">{{ $u->displayName() }}</p>
                                <span @class([
                                    'mt-1 inline-flex rounded-md px-2 py-0.5 text-xs font-medium',
                                    'bg-blue-50 text-blue-700' => $isStudent,
                                    'bg-violet-50 text-violet-700' => $isStaff,
                                    'bg-sky-50 text-sky-800' => $isUnregistered,
                                    'bg-gray-100 text-gray-600' => ! $isStudent && ! $isStaff && ! $isUnregistered,
                                ])>{{ $roleLabel }}</span>
                            </td>

                            <td class="px-3 py-4">
                                <p class="truncate text-sm text-gray-600" title="{{ $email }}">{{ $email }}</p>
                            </td>

                            <td class="px-3 py-4">
                                <p class="truncate text-sm text-gray-600" title="{{ $vehicleName }} · {{ $plate }}">
                                    {{ $vehicleName }} · {{ $plate }}
                                </p>
                            </td>

                            <td class="px-3 py-4">
                                <p class="truncate text-sm text-gray-600">{{ $phone }}</p>
                            </td>

                            <td class="px-3 py-4">
                                <p class="truncate text-sm font-medium text-gray-800">{{ $idNumber }}</p>
                            </td>

                            <td class="px-3 py-4">
                                @if ($isLocked)
                                    <span class="inline-flex rounded-md bg-red-50 px-2 py-0.5 text-xs font-medium text-red-700">Locked</span>
                                @elseif ($isDenied)
                                    <span class="inline-flex rounded-md bg-rose-50 px-2 py-0.5 text-xs font-medium text-rose-700">Denied</span>
                                @elseif ($hasRfid)
                                    <span class="inline-flex rounded-md bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">Assigned</span>
                                @else
                                    <span class="inline-flex rounded-md bg-orange-50 px-2 py-0.5 text-xs font-medium text-orange-700">Pending</span>
                                @endif
                            </td>

                            <td class="px-4 py-4 sm:px-5">
                                <div class="ml-auto flex w-full max-w-[9.5rem] flex-col items-stretch gap-2">
                                    @if ($hasRfid)
                                        <div class="inline-flex w-full items-center justify-center gap-1.5 rounded-lg bg-emerald-50 px-2.5 py-2 text-emerald-700">
                                            <i data-lucide="check-circle" class="h-3.5 w-3.5 shrink-0"></i>
                                            <span class="text-[11px] font-semibold">Tag linked</span>
                                        </div>
                                        @unless ($isLocked)
                                            <button
                                                type="button"
                                                class="js-assign-rfid inline-flex w-full items-center justify-center rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50"
                                                data-user-id="{{ $u->id }}"
                                                data-user-name="{{ $u->displayName() }}"
                                                data-user-id-number="{{ $idNumber }}"
                                                data-vehicle="{{ $vehicleName }}"
                                                data-plate="{{ $plate }}"
                                                data-rfid="{{ $u->rfid_uid }}"
                                                data-mode="update"
                                            >
                                                Update
                                            </button>
                                        @endunless
                                    @elseif (! $isLocked)
                                        <button
                                            type="button"
                                            class="js-assign-rfid inline-flex w-full items-center justify-center rounded-lg bg-blue-600 px-3 py-2 text-xs font-semibold text-white shadow-sm hover:bg-blue-700"
                                            data-user-id="{{ $u->id }}"
                                            data-user-name="{{ $u->displayName() }}"
                                            data-user-id-number="{{ $idNumber }}"
                                            data-vehicle="{{ $vehicleName }}"
                                            data-plate="{{ $plate }}"
                                            data-rfid="{{ $u->rfid_uid }}"
                                            data-mode="assign"
                                        >
                                            Assign RFID
                                        </button>
                                    @endif

                                    @if (! $isDenied)
                                        <form method="POST" action="{{ route('admin.rfid.update') }}" class="w-full">
                                            @csrf
                                            <input type="hidden" name="user_id" value="{{ $u->id }}">
                                            <input type="hidden" name="tab" value="{{ $activeFilter }}" data-rfid-tab-input>
                                            <input type="hidden" name="action" value="deny">
                                            <button type="submit"
                                                class="inline-flex w-full items-center justify-center rounded-lg border border-red-200 px-3 py-2 text-xs font-semibold text-red-600 hover:bg-red-50"
                                                onclick="return confirm('Deny gate access for {{ addslashes($u->displayName()) }}?')">
                                                Deny
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-16 text-center">
                                <p class="font-medium text-gray-700">No approved users found</p>
                                <p class="mt-1 text-sm text-gray-500">Users will appear here when they are ready for RFID assignment.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <div id="rfid-empty-filter" class="hidden px-6 py-16 text-center">
                <p class="font-medium text-gray-700">No users match this filter</p>
                <p class="mt-1 text-sm text-gray-500">Try another statistics card or clear your search.</p>
            </div>
        </div>
    </div>

    {{-- Assign RFID modal --}}
    <div id="assign-rfid-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4" role="dialog" aria-modal="true" aria-labelledby="assign-rfid-title">
        <div class="w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-xl">
            <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4 sm:px-6">
                <h3 id="assign-rfid-title" class="text-lg font-semibold text-gray-900">Assign RFID Tag</h3>
                <button type="button" id="assign-rfid-close" class="flex h-8 w-8 items-center justify-center rounded-full border border-gray-200 text-gray-500 hover:bg-gray-50" aria-label="Close">
                    <i data-lucide="x" class="h-4 w-4"></i>
                </button>
            </div>

            <form id="assign-rfid-form" method="POST" action="#" class="px-5 py-5 sm:px-6">
                @csrf
                <div class="rounded-xl bg-gray-50 p-4">
                    <div class="flex items-center gap-3">
                        <div id="assign-rfid-avatar" class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-blue-600 text-sm font-semibold text-white">—</div>
                        <div class="min-w-0">
                            <p id="assign-rfid-name" class="truncate font-semibold text-gray-900">—</p>
                            <p id="assign-rfid-id" class="truncate text-sm text-gray-500">—</p>
                        </div>
                    </div>
                    <div class="mt-4 grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-xs text-gray-500">Vehicle</p>
                            <p id="assign-rfid-vehicle" class="mt-0.5 text-sm font-semibold text-gray-900">—</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Plate Number</p>
                            <p id="assign-rfid-plate" class="mt-0.5 text-sm font-semibold text-gray-900">—</p>
                        </div>
                    </div>
                </div>

                <div class="mt-5">
                    <label for="assign-rfid-uid" class="mb-1.5 block text-sm font-semibold text-gray-900">
                        RFID Tag Number <span class="text-red-500">*</span>
                    </label>
                    <p id="assign-rfid-current" class="mb-2 hidden text-xs text-emerald-700">
                        Currently linked — enter a new UID only if replacing the tag.
                    </p>
                    <div class="relative">
                        <span class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400">
                            <i data-lucide="hash" class="h-4 w-4"></i>
                        </span>
                        <input
                            id="assign-rfid-uid"
                            type="text"
                            name="rfid_uid"
                            required
                            autocomplete="off"
                            spellcheck="false"
                            placeholder="Enter RFID tag number (e.g., RFID-001)"
                            class="w-full rounded-xl border border-blue-200 bg-white py-2.5 pl-10 pr-3 text-sm text-gray-900 placeholder:text-gray-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20"
                        >
                    </div>
                    <p class="mt-1.5 text-xs text-gray-400">Scan or manually enter the RFID tag number</p>
                </div>

                <div class="mt-4 rounded-xl border border-blue-100 bg-blue-50 px-4 py-3 text-sm text-blue-800">
                    <span class="font-semibold">Note:</span>
                    After assigning the RFID tag, an email notification will be sent to the user confirming their tag activation.
                </div>

                <div class="mt-6 grid grid-cols-2 gap-3">
                    <button type="button" id="assign-rfid-cancel" class="rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-800 hover:bg-gray-50">Cancel</button>
                    <button type="submit" id="assign-rfid-submit" class="rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:bg-gray-300">Assign &amp; Notify</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const labels = @json($filterLabels);
        const activeClasses = {
            all: ['border-blue-300', 'ring-2', 'ring-blue-100'],
            pending: ['border-orange-300', 'ring-2', 'ring-orange-100'],
            assigned: ['border-emerald-300', 'ring-2', 'ring-emerald-100'],
            locked: ['border-red-300', 'ring-2', 'ring-red-100'],
            denied: ['border-rose-300', 'ring-2', 'ring-rose-100'],
        };
        const inactiveBorder = 'border-gray-200';

        let currentFilter = @json($activeFilter);
        const searchInput = document.getElementById('rfid-search');
        const filterLabel = document.getElementById('rfid-filter-label');
        const emptyState = document.getElementById('rfid-empty-filter');
        const cards = Array.from(document.querySelectorAll('[data-rfid-filter]'));
        const rows = Array.from(document.querySelectorAll('.rfid-user-row'));

        const matchesFilter = (row, filter) => {
            const hasRfid = row.dataset.hasRfid === '1';
            const locked = row.dataset.locked === '1';
            const denied = row.dataset.denied === '1';
            if (filter === 'pending') return !hasRfid;
            if (filter === 'assigned') return hasRfid;
            if (filter === 'locked') return locked;
            if (filter === 'denied') return denied;
            return true;
        };

        const applyView = () => {
            const q = (searchInput?.value || '').trim().toLowerCase();
            let visible = 0;

            rows.forEach((row) => {
                const searchOk = !q || (row.dataset.search || '').includes(q);
                const filterOk = matchesFilter(row, currentFilter);
                const show = searchOk && filterOk;
                row.classList.toggle('hidden', !show);
                if (show) visible += 1;
            });

            emptyState?.classList.toggle('hidden', visible > 0 || rows.length === 0);
            if (filterLabel) filterLabel.textContent = labels[currentFilter] || 'All users';

            document.querySelectorAll('[data-rfid-tab-input]').forEach((input) => {
                input.value = currentFilter;
            });

            cards.forEach((card) => {
                const key = card.dataset.rfidFilter;
                const isActive = key === currentFilter;
                card.setAttribute('aria-pressed', isActive ? 'true' : 'false');
                card.classList.remove(inactiveBorder);
                Object.values(activeClasses).flat().forEach((cls) => card.classList.remove(cls));
                if (isActive) {
                    (activeClasses[key] || activeClasses.all).forEach((cls) => card.classList.add(cls));
                } else {
                    card.classList.add(inactiveBorder);
                }
            });

            try {
                const url = new URL(window.location.href);
                url.searchParams.set('tab', currentFilter);
                if (q) url.searchParams.set('search', searchInput.value.trim());
                else url.searchParams.delete('search');
                window.history.replaceState({}, '', url);
            } catch (e) {}
        };

        cards.forEach((card) => {
            card.addEventListener('click', () => {
                currentFilter = card.dataset.rfidFilter || 'all';
                applyView();
            });
        });

        searchInput?.addEventListener('input', applyView);
        applyView();

        // Assign modal
        const modal = document.getElementById('assign-rfid-modal');
        const form = document.getElementById('assign-rfid-form');
        const input = document.getElementById('assign-rfid-uid');
        const submitBtn = document.getElementById('assign-rfid-submit');
        const approveTemplate = @json(route('admin.rfid.approve', ['id' => '__ID__']));

        const initials = (name) => {
            const parts = String(name || '').trim().split(/\s+/).filter(Boolean);
            if (!parts.length) return 'U';
            return parts.slice(0, 2).map((p) => p.charAt(0).toUpperCase()).join('');
        };

        const syncSubmitState = () => {
            if (submitBtn) submitBtn.disabled = !(input?.value || '').trim().length;
        };

        const openModal = (btn) => {
            const id = btn.dataset.userId;
            document.getElementById('assign-rfid-avatar').textContent = initials(btn.dataset.userName);
            document.getElementById('assign-rfid-name').textContent = btn.dataset.userName || 'Unknown';
            document.getElementById('assign-rfid-id').textContent = btn.dataset.userIdNumber || '—';
            document.getElementById('assign-rfid-vehicle').textContent = btn.dataset.vehicle || '—';
            document.getElementById('assign-rfid-plate').textContent = btn.dataset.plate || '—';
            if (form) form.action = approveTemplate.replace('__ID__', encodeURIComponent(id));
            const currentHint = document.getElementById('assign-rfid-current');
            const hasExisting = Boolean((btn.dataset.rfid || '').trim());
            if (currentHint) currentHint.classList.toggle('hidden', !hasExisting);
            if (input) {
                // Keep UID in the input for assignment/update, but do not echo it in the list UI.
                input.value = btn.dataset.rfid || '';
                input.focus();
            }
            if (submitBtn) submitBtn.textContent = btn.dataset.mode === 'update' ? 'Update & Notify' : 'Assign & Notify';
            syncSubmitState();
            modal?.classList.remove('hidden');
            modal?.classList.add('flex');
            if (window.lucide) window.lucide.createIcons();
        };

        const closeModal = () => {
            modal?.classList.add('hidden');
            modal?.classList.remove('flex');
            if (form) form.reset();
            syncSubmitState();
        };

        document.querySelectorAll('.js-assign-rfid').forEach((btn) => {
            btn.addEventListener('click', () => openModal(btn));
        });
        document.getElementById('assign-rfid-close')?.addEventListener('click', closeModal);
        document.getElementById('assign-rfid-cancel')?.addEventListener('click', closeModal);
        modal?.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && modal && !modal.classList.contains('hidden')) closeModal();
        });
        input?.addEventListener('input', syncSubmitState);
        syncSubmitState();
    });
</script>
@endpush
