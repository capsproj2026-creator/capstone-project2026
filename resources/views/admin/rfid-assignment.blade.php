@extends('layouts.admin')

@section('title', 'RFID Tag Assignment')

@section('content')
    @include('partials.shell.page-header', [
        'title' => 'RFID Tag Assignment',
        'subtitle' => 'Assign RFID tags to approved users',
    ])

    @if (session('error'))
        <div class="mb-5 flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            <span class="mt-0.5 inline-block h-2 w-2 shrink-0 rounded-full bg-red-500"></span>
            <span>{{ session('error') }}</span>
        </div>
    @endif
    @if (session('success'))
        <div class="mb-5 flex items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            <span class="mt-0.5 inline-block h-2 w-2 shrink-0 rounded-full bg-emerald-500"></span>
            <span>{{ session('success') }}</span>
        </div>
    @endif
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

    {{-- Summary cards (clickable filters) --}}
    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
        <a href="{{ route('admin.rfid', array_filter(['tab' => 'all', 'search' => $search ?: null])) }}"
            @class([
                'flex items-center justify-between rounded-xl border bg-white p-5 shadow-sm transition hover:border-gray-300',
                'border-blue-300 ring-2 ring-blue-100' => $currentTab === 'all',
                'border-gray-200' => $currentTab !== 'all',
            ])>
            <div>
                <p class="text-sm text-gray-500">Total Users</p>
                <p class="mt-1 text-3xl font-bold tracking-tight text-gray-900">{{ number_format($stats['total']) }}</p>
            </div>
            <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                <i data-lucide="users" class="h-5 w-5"></i>
            </div>
        </a>

        <a href="{{ route('admin.rfid', array_filter(['tab' => 'Pending', 'search' => $search ?: null])) }}"
            @class([
                'flex items-center justify-between rounded-xl border bg-white p-5 shadow-sm transition hover:border-gray-300',
                'border-orange-300 ring-2 ring-orange-100' => $currentTab === 'Pending',
                'border-gray-200' => $currentTab !== 'Pending',
            ])>
            <div>
                <p class="text-sm text-gray-500">Pending Assignment</p>
                <p class="mt-1 text-3xl font-bold tracking-tight text-orange-500">{{ number_format($stats['pending']) }}</p>
            </div>
            <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-orange-50 text-orange-500">
                <i data-lucide="hash" class="h-5 w-5"></i>
            </div>
        </a>

        <a href="{{ route('admin.rfid', array_filter(['tab' => 'Granted', 'search' => $search ?: null])) }}"
            @class([
                'flex items-center justify-between rounded-xl border bg-white p-5 shadow-sm transition hover:border-gray-300',
                'border-emerald-300 ring-2 ring-emerald-100' => in_array($currentTab, ['Granted', 'Access'], true),
                'border-gray-200' => ! in_array($currentTab, ['Granted', 'Access'], true),
            ])>
            <div>
                <p class="text-sm text-gray-500">RFID Assigned</p>
                <p class="mt-1 text-3xl font-bold tracking-tight text-emerald-600">{{ number_format($stats['assigned']) }}</p>
            </div>
            <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                <i data-lucide="check-circle" class="h-5 w-5"></i>
            </div>
        </a>
    </div>

    {{-- Search --}}
    <form method="GET" action="{{ route('admin.rfid') }}" class="mb-6">
        <input type="hidden" name="tab" value="{{ $currentTab }}">
        <div class="relative">
            <i data-lucide="search" class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400"></i>
            <input
                type="search"
                name="search"
                value="{{ $search }}"
                placeholder="Search by name, user ID, or plate number..."
                class="w-full rounded-xl border border-gray-200 bg-white py-3 pl-11 pr-4 text-sm text-gray-900 shadow-sm placeholder:text-gray-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20"
            >
        </div>
    </form>

    {{-- Approved Users list --}}
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="border-b border-gray-100 px-5 py-4 sm:px-6">
            <h2 class="text-base font-semibold text-gray-900">Approved Users</h2>
        </div>

        <div class="divide-y divide-gray-100">
            @forelse ($users as $u)
                @php
                    /** @var \App\Models\User $u */
                    $roleLabel = $u->roleName();
                    $isStudent = strcasecmp($roleLabel, 'Student') === 0;
                    $isStaff = strcasecmp($roleLabel, 'Staff') === 0;
                    $gate = $u->Gate_access ?: \App\Models\User::GATE_ACCESS_PENDING;
                    $isAssigned = $u->hasGateAccess() && filled($u->rfid_uid);
                    $isDenied = $gate === \App\Models\User::GATE_ACCESS_DENIED;
                    $vehicleName = $u->vehicleType?->vehicle_name ?: 'Vehicle';
                    $plate = $u->plate_number ?: '—';
                    $phone = $u->phone_number ?: '—';
                    $idNumber = $u->id_number ?: '—';
                @endphp

                <div class="flex flex-col gap-4 px-5 py-5 sm:px-6 lg:flex-row lg:items-center lg:justify-between">
                    <div class="flex min-w-0 flex-1 gap-3 sm:gap-4">
                        <x-portal.avatar :user="$u" size="lg" class="!ring-0" />

                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="truncate text-base font-semibold text-gray-900">{{ $u->displayName() }}</p>
                                <span @class([
                                    'inline-flex rounded-md px-2 py-0.5 text-xs font-medium',
                                    'bg-blue-50 text-blue-700' => $isStudent,
                                    'bg-violet-50 text-violet-700' => $isStaff,
                                    'bg-gray-100 text-gray-600' => ! $isStudent && ! $isStaff,
                                ])>
                                    {{ $roleLabel }}
                                </span>
                                @if ($isAssigned)
                                    <span class="inline-flex rounded-md bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">
                                        RFID Assigned
                                    </span>
                                @elseif ($isDenied)
                                    <span class="inline-flex rounded-md bg-red-50 px-2 py-0.5 text-xs font-medium text-red-700">
                                        Denied
                                    </span>
                                @else
                                    <span class="inline-flex rounded-md bg-orange-50 px-2 py-0.5 text-xs font-medium text-orange-700">
                                        Pending
                                    </span>
                                @endif
                                @if ($u->isLocked())
                                    <span class="inline-flex rounded-md bg-red-50 px-2 py-0.5 text-xs font-medium text-red-700">
                                        Locked
                                    </span>
                                @endif
                            </div>

                            <div class="mt-2 grid grid-cols-1 gap-x-8 gap-y-1.5 text-sm text-gray-500 sm:grid-cols-2 xl:grid-cols-3">
                                <div class="min-w-0 space-y-1.5">
                                    <p class="flex min-w-0 items-center gap-2">
                                        <i data-lucide="mail" class="h-3.5 w-3.5 shrink-0 text-gray-400"></i>
                                        <span class="truncate">{{ $u->email ?: '—' }}</span>
                                    </p>
                                    <p class="flex min-w-0 items-center gap-2">
                                        <i data-lucide="car" class="h-3.5 w-3.5 shrink-0 text-gray-400"></i>
                                        <span class="truncate">{{ $vehicleName }} - {{ $plate }}</span>
                                    </p>
                                </div>
                                <div class="min-w-0">
                                    <p class="flex min-w-0 items-center gap-2">
                                        <i data-lucide="phone" class="h-3.5 w-3.5 shrink-0 text-gray-400"></i>
                                        <span class="truncate">{{ $phone }}</span>
                                    </p>
                                </div>
                                <div class="min-w-0">
                                    <p class="flex min-w-0 items-center gap-2">
                                        <i data-lucide="hash" class="h-3.5 w-3.5 shrink-0 text-gray-400"></i>
                                        <span class="truncate"># {{ $idNumber }}</span>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex shrink-0 flex-wrap items-center gap-2 sm:justify-end lg:pl-4">
                        @if ($isAssigned)
                            <div class="flex items-center gap-2 rounded-lg bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-700">
                                <i data-lucide="check-circle" class="h-4 w-4 shrink-0"></i>
                                <span class="max-w-[10rem] truncate font-mono text-xs sm:max-w-[14rem] sm:text-sm">RFID: {{ $u->rfid_uid }}</span>
                            </div>
                            <button
                                type="button"
                                class="js-assign-rfid rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs font-semibold text-gray-600 hover:bg-gray-50"
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
                        @elseif (! $u->isLocked())
                            <button
                                type="button"
                                class="js-assign-rfid inline-flex items-center justify-center rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500/30"
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
                            <form method="POST" action="{{ route('admin.rfid.update') }}" class="inline">
                                @csrf
                                <input type="hidden" name="user_id" value="{{ $u->id }}">
                                <input type="hidden" name="tab" value="{{ $currentTab }}">
                                <input type="hidden" name="action" value="deny">
                                <button type="submit"
                                    class="rounded-lg border border-red-200 px-3 py-2 text-xs font-semibold text-red-600 hover:bg-red-50"
                                    onclick="return confirm('Deny gate access for {{ addslashes($u->displayName()) }}?')">
                                    Deny
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            @empty
                <div class="px-6 py-16 text-center">
                    <p class="font-medium text-gray-700">No approved users found</p>
                    <p class="mt-1 text-sm text-gray-500">
                        {{ $search !== '' ? 'Try clearing your search.' : 'Users will appear here when they are ready for RFID assignment.' }}
                    </p>
                </div>
            @endforelse
        </div>

        @if ($users->hasPages())
            <div class="border-t border-gray-100 px-5 py-4">
                {{ $users->links() }}
            </div>
        @endif
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
                        <div id="assign-rfid-avatar" class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-blue-600 text-sm font-semibold text-white">
                            —
                        </div>
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
                    <button type="button" id="assign-rfid-cancel"
                        class="rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-800 hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit" id="assign-rfid-submit"
                        class="rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:bg-gray-300">
                        Assign &amp; Notify
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
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
            const hasValue = (input?.value || '').trim().length > 0;
            if (submitBtn) submitBtn.disabled = !hasValue;
        };

        const openModal = (btn) => {
            const id = btn.dataset.userId;
            const name = btn.dataset.userName || 'Unknown';
            const idNumber = btn.dataset.userIdNumber || '—';
            const vehicle = btn.dataset.vehicle || '—';
            const plate = btn.dataset.plate || '—';
            const rfid = btn.dataset.rfid || '';
            const mode = btn.dataset.mode || 'assign';

            document.getElementById('assign-rfid-avatar').textContent = initials(name);
            document.getElementById('assign-rfid-name').textContent = name;
            document.getElementById('assign-rfid-id').textContent = idNumber;
            document.getElementById('assign-rfid-vehicle').textContent = vehicle;
            document.getElementById('assign-rfid-plate').textContent = plate;

            if (form) {
                form.action = approveTemplate.replace('__ID__', encodeURIComponent(id));
            }

            if (input) {
                input.value = rfid;
                input.focus();
            }

            if (submitBtn) {
                submitBtn.textContent = mode === 'update' ? 'Update & Notify' : 'Assign & Notify';
            }

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
        modal?.addEventListener('click', (e) => {
            if (e.target === modal) closeModal();
        });
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && modal && !modal.classList.contains('hidden')) {
                closeModal();
            }
        });

        input?.addEventListener('input', syncSubmitState);
        syncSubmitState();
    });
</script>
@endpush
