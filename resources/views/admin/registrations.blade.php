@extends('layouts.portal')

@section('title', 'Registration Management')

@section('content')
    @include('partials.shell.page-header', [
        'title' => 'Registration Management',
        'subtitle' => 'Review and approve pending vehicle owner registrations',
    ])

    @error('remarks')
        <div class="mb-4 flex gap-2 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            <i data-lucide="alert-circle" class="h-4 w-4 shrink-0"></i>
            {{ $message }}
        </div>
    @enderror

    @php
        $statusCards = [
            'All' => [
                'label' => 'All Users',
                'count' => $allCount,
                'icon' => 'users',
                'text' => 'text-gray-900',
                'iconBg' => 'bg-blue-50 text-blue-600',
                'active' => 'border-blue-300 ring-2 ring-blue-100 bg-blue-50/40',
            ],
            'Pending' => [
                'label' => 'Pending',
                'count' => $pendingCount,
                'icon' => 'clock',
                'text' => 'text-amber-600',
                'iconBg' => 'bg-amber-50 text-amber-600',
                'active' => 'border-amber-300 ring-2 ring-amber-100 bg-amber-50/40',
            ],
            'Granted' => [
                'label' => 'Approved',
                'count' => $approvedCount,
                'icon' => 'check-circle',
                'text' => 'text-emerald-600',
                'iconBg' => 'bg-emerald-50 text-emerald-600',
                'active' => 'border-emerald-300 ring-2 ring-emerald-100 bg-emerald-50/40',
            ],
            'Denied' => [
                'label' => 'Declined',
                'count' => $declinedCount,
                'icon' => 'x-circle',
                'text' => 'text-rose-600',
                'iconBg' => 'bg-rose-50 text-rose-600',
                'active' => 'border-rose-300 ring-2 ring-rose-100 bg-rose-50/40',
            ],
        ];
    @endphp

    <div class="mb-6 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($statusCards as $statusKey => $card)
            <a href="{{ route('admin.registrations', ['status' => $statusKey]) }}"
               @class([
                   'flex items-center justify-between rounded-xl border bg-white p-5 shadow-sm transition hover:border-gray-300',
                   $card['active'] => $statusFilter === $statusKey,
                   'border-gray-200' => $statusFilter !== $statusKey,
               ])>
                <div>
                    <p class="text-sm text-gray-500">{{ $card['label'] }}</p>
                    <p class="mt-1 text-2xl font-bold tracking-tight {{ $card['text'] }}">{{ $card['count'] }}</p>
                </div>
                <div class="flex h-11 w-11 items-center justify-center rounded-xl {{ $card['iconBg'] }}">
                    <i data-lucide="{{ $card['icon'] }}" class="h-5 w-5"></i>
                </div>
            </a>
        @endforeach
    </div>

    <div class="mb-5">
        <div class="relative">
            <i data-lucide="search" class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400"></i>
            <input
                id="reg-search"
                type="search"
                placeholder="Search by name, email, ID, or plate..."
                class="w-full rounded-xl border border-gray-200 bg-white py-3 pl-11 pr-4 text-sm text-gray-900 shadow-sm placeholder:text-gray-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20"
            >
        </div>
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="border-b border-gray-100 px-5 py-4 sm:px-6">
            <h2 class="text-base font-semibold text-gray-900">
                {{ $statusFilter === 'All' ? 'All registrations' : (($statusCards[$statusFilter]['label'] ?? $statusFilter).' registrations') }}
            </h2>
            <p class="mt-0.5 text-sm text-gray-500">Review applicant details and take action</p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[56rem] table-fixed border-collapse text-left text-sm">
                <colgroup>
                    <col class="w-[14rem]">
                    <col class="w-[7rem]">
                    <col class="w-[9rem]">
                    <col class="w-[16rem]">
                    <col class="w-[10rem]">
                    <col class="w-[7rem]">
                    <col class="w-[12rem]">
                </colgroup>
                <thead>
                    <tr class="border-b border-gray-100 bg-gray-50 text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                        <th scope="col" class="px-4 py-3 sm:px-5">Name</th>
                        <th scope="col" class="px-3 py-3">Role</th>
                        <th scope="col" class="px-3 py-3">ID</th>
                        <th scope="col" class="px-3 py-3">Email</th>
                        <th scope="col" class="px-3 py-3">Registered</th>
                        <th scope="col" class="px-3 py-3">Status</th>
                        <th scope="col" class="px-4 py-3 text-right sm:px-5">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($requests as $row)
                        @php
                            $isIncompleteTemp = $row->isTemporaryAccount();
                            $isUnregistered = $row->isUnregisteredStudentFaculty();
                            $roleLabel = $row->displayRoleLabel();
                            $isStudent = ! $isUnregistered && (strcasecmp((string) $roleLabel, 'Student') === 0);
                            $isStaff = ! $isUnregistered && in_array($roleLabel, ['Staff', 'Faculty'], true);
                            $email = $row->displayEmail();
                            if ($isIncompleteTemp || str_ends_with(strtolower((string) $email), '.invalid')) {
                                $email = '—';
                            }
                            $idNumber = $row->id_number ?: '—';
                            $plate = $row->plate_number ?: '';
                            $registeredAt = $row->created_at ? ph_datetime($row->created_at, 'M j, Y g:i A') : '—';
                            $registeredDate = $row->created_at ? ph_date($row->created_at, 'M j, Y') : '—';
                            $registeredTime = $row->created_at ? ph_time($row->created_at, 'g:i A') : '';
                            $searchBlob = strtolower(trim(implode(' ', [
                                $row->fullname,
                                $email,
                                $idNumber,
                                $plate,
                                $roleLabel,
                            ])));
                        @endphp
                        <tr class="reg-row align-middle hover:bg-gray-50/60" data-search="{{ e($searchBlob) }}">
                            <td class="px-4 py-3 sm:px-5">
                                <div class="flex min-w-0 items-center gap-3">
                                    <x-portal.avatar :user="$row" size="sm" class="shrink-0 !ring-0" />
                                    <span class="truncate font-semibold text-gray-900" title="{{ $row->fullname }}">{{ $row->fullname }}</span>
                                </div>
                            </td>
                            <td class="px-3 py-3">
                                <span @class([
                                    'inline-flex rounded-md px-2 py-0.5 text-xs font-medium',
                                    'bg-blue-50 text-blue-700' => $isStudent,
                                    'bg-violet-50 text-violet-700' => $isStaff,
                                    'bg-sky-50 text-sky-800' => $isUnregistered,
                                    'bg-gray-100 text-gray-600' => ! $isStudent && ! $isStaff && ! $isUnregistered,
                                ])>{{ $roleLabel }}</span>
                            </td>
                            <td class="px-3 py-3">
                                <p class="truncate font-medium text-gray-800" title="{{ $idNumber }}">{{ $idNumber }}</p>
                            </td>
                            <td class="min-w-0 px-3 py-3">
                                <p class="truncate text-gray-600" title="{{ $email }}">{{ $email }}</p>
                            </td>
                            <td class="px-3 py-3 text-gray-600" title="{{ $registeredAt }}">
                                @if ($registeredTime !== '')
                                    <p class="whitespace-nowrap font-medium text-gray-800">{{ $registeredDate }}</p>
                                    <p class="mt-0.5 whitespace-nowrap text-xs text-gray-500">{{ $registeredTime }}</p>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-3 py-3">
                                @if ($isIncompleteTemp)
                                    <span class="inline-flex rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-semibold text-amber-700">Not registered yet</span>
                                @elseif ($row->status === 'Pending' && $row->resubmitted_at)
                                    <span class="inline-flex rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-semibold text-blue-700">Resubmitted</span>
                                @elseif ($row->status === 'Pending')
                                    <span class="inline-flex rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-semibold text-amber-700">Pending</span>
                                @elseif ($row->status === 'Granted')
                                    <span class="inline-flex rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700">Approved</span>
                                @elseif ($row->isRemedialDeclined())
                                    <span class="inline-flex rounded-full bg-orange-50 px-2.5 py-0.5 text-xs font-semibold text-orange-700">Needs Correction</span>
                                @else
                                    <span class="inline-flex rounded-full bg-rose-50 px-2.5 py-0.5 text-xs font-semibold text-rose-700">Declined</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 sm:px-5">
                                <div class="flex flex-col items-stretch gap-2 sm:items-end">
                                    <div class="flex flex-wrap justify-end gap-2">
                                        <a href="{{ route('admin.users.show', ['id' => $row->id, 'from' => 'registrations']) }}"
                                           class="inline-flex items-center justify-center rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50">
                                            View profile
                                        </a>
                                        @if ($row->status === 'Pending' && ! $isIncompleteTemp)
                                            {{-- TODO payment gate: hide/disable Approve until $row->payment_status === 'paid'
                                            @if (($row->payment_status ?? null) !== 'paid')
                                                <button type="button" disabled class="inline-flex cursor-not-allowed items-center justify-center rounded-lg bg-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-600">Approve (awaiting payment)</button>
                                            @else
                                            --}}
                                            <form method="POST" action="{{ route('admin.registrations.approve', $row->id) }}" onsubmit="return confirm('Approve this registration?')">
                                                @csrf
                                                <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">Approve</button>
                                            </form>
                                            {{-- @endif --}}
                                        @endif
                                    </div>
                                    @if ($row->status === 'Pending')
                                        <button
                                            type="button"
                                            class="js-open-decline inline-flex items-center justify-center rounded-lg bg-rose-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-rose-700"
                                            data-decline-url="{{ route('admin.registrations.decline', $row->id) }}"
                                            data-decline-name="{{ $row->fullname }}"
                                        >
                                            Decline
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center text-sm text-gray-500">
                                No {{ strtolower($statusCards[$statusFilter]['label'] ?? $statusFilter) }} registrations found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <div id="reg-empty-search" class="hidden px-6 py-12 text-center text-sm text-gray-500">
                No registrations match your search.
            </div>
        </div>
    </div>

    <div id="decline-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4" role="dialog" aria-modal="true" aria-labelledby="decline-modal-title">
        <form id="decline-form" method="POST" action="" class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
            @csrf
            <h3 id="decline-modal-title" class="text-lg font-bold text-gray-900">Decline registration</h3>
            <p id="decline-modal-subtitle" class="mt-1 text-sm text-gray-500">A reason is required and will be included in the email and in-app notice.</p>
            <label class="mt-4 block text-sm font-medium text-gray-700" for="decline-remarks">
                Reason / remarks <span class="text-rose-600">*</span>
            </label>
            <textarea
                id="decline-remarks"
                name="remarks"
                rows="3"
                minlength="3"
                maxlength="500"
                required
                class="mt-1 w-full rounded-xl border border-gray-200 px-3 py-2 text-sm"
                placeholder="e.g. Unreadable OR/CR, expired license"
            ></textarea>
            <p id="decline-remarks-error" class="mt-1 hidden text-sm text-rose-600">Please enter a reason (at least 3 characters) before declining.</p>

            <fieldset class="mt-4 space-y-2">
                <legend class="text-sm font-medium text-gray-700">Decline type</legend>
                <label class="flex items-start gap-2 text-sm text-gray-700">
                    <input type="radio" name="decline_type" value="remedial" class="mt-1" checked>
                    <span><span class="font-medium">Remedial</span> — user can sign in, fix documents, and may get temporary gate access</span>
                </label>
                <label class="flex items-start gap-2 text-sm text-gray-700">
                    <input type="radio" name="decline_type" value="final" class="mt-1">
                    <span><span class="font-medium">Final</span> — no portal or gate access (3-day re-register cooldown)</span>
                </label>
            </fieldset>

            <div class="mt-4">
                <label class="block text-sm font-medium text-gray-700" for="decline-category">Issue category</label>
                <select id="decline-category" name="decline_category" class="mt-1 w-full rounded-xl border border-gray-200 px-3 py-2 text-sm">
                    <option value="documents_illegible">Documents not readable</option>
                    <option value="license_expired">Driver's license expired</option>
                    <option value="or_cr_invalid">OR/CR invalid or expired</option>
                    <option value="other">Other</option>
                </select>
            </div>

            <label id="decline-temp-gate-wrap" class="mt-4 flex items-start gap-2 text-sm text-gray-700">
                <input type="hidden" name="allow_temp_gate" value="0">
                <input type="checkbox" id="decline-allow-temp-gate" name="allow_temp_gate" value="1" checked class="mt-1">
                <span>Allow temporary campus access while correcting documents</span>
            </label>

            <div class="mt-5 flex justify-end gap-2">
                <button type="button" id="decline-cancel" class="rounded-xl border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Cancel</button>
                <button type="submit" class="rounded-xl bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-700">Confirm Decline</button>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const searchInput = document.getElementById('reg-search');
        const rows = Array.from(document.querySelectorAll('.reg-row'));
        const emptySearch = document.getElementById('reg-empty-search');

        const applySearch = () => {
            const q = (searchInput?.value || '').trim().toLowerCase();
            let visible = 0;
            rows.forEach((row) => {
                const show = !q || (row.dataset.search || '').includes(q);
                row.classList.toggle('hidden', !show);
                if (show) visible += 1;
            });
            emptySearch?.classList.toggle('hidden', visible > 0 || rows.length === 0);
        };
        searchInput?.addEventListener('input', applySearch);

        const modal = document.getElementById('decline-modal');
        const form = document.getElementById('decline-form');
        const subtitle = document.getElementById('decline-modal-subtitle');
        const remarks = document.getElementById('decline-remarks');
        const remarksError = document.getElementById('decline-remarks-error');
        const declineTypeRadios = Array.from(document.querySelectorAll('input[name="decline_type"]'));
        const tempGateWrap = document.getElementById('decline-temp-gate-wrap');
        const tempGateCheckbox = document.getElementById('decline-allow-temp-gate');
        const categorySelect = document.getElementById('decline-category');

        const syncDeclineTypeUi = () => {
            const isFinal = declineTypeRadios.find((r) => r.checked)?.value === 'final';
            tempGateWrap?.classList.toggle('hidden', isFinal);
            categorySelect?.toggleAttribute('disabled', isFinal);
            if (isFinal && tempGateCheckbox) {
                tempGateCheckbox.checked = false;
            }
        };
        declineTypeRadios.forEach((radio) => radio.addEventListener('change', syncDeclineTypeUi));
        syncDeclineTypeUi();

        const setRemarksError = (show) => {
            remarksError?.classList.toggle('hidden', !show);
            remarks?.classList.toggle('border-rose-400', show);
            remarks?.setAttribute('aria-invalid', show ? 'true' : 'false');
        };
        const closeModal = () => {
            modal?.classList.add('hidden');
            modal?.classList.remove('flex');
            if (remarks) remarks.value = '';
            setRemarksError(false);
        };
        document.querySelectorAll('.js-open-decline').forEach((button) => {
            button.addEventListener('click', () => {
                if (!form) return;
                form.action = button.dataset.declineUrl || '';
                if (subtitle) {
                    const name = button.dataset.declineName || 'this applicant';
                    subtitle.textContent = `Decline ${name}? A reason is required and will be included in the email and in-app notice.`;
                }
                setRemarksError(false);
                modal?.classList.remove('hidden');
                modal?.classList.add('flex');
                remarks?.focus();
            });
        });
        form?.addEventListener('submit', (event) => {
            const value = (remarks?.value || '').trim();
            if (value.length < 3) {
                event.preventDefault();
                if (remarks) remarks.value = value;
                setRemarksError(true);
                remarks?.focus();
            }
        });
        remarks?.addEventListener('input', () => {
            if ((remarks.value || '').trim().length >= 3) {
                setRemarksError(false);
            }
        });
        document.getElementById('decline-cancel')?.addEventListener('click', closeModal);
        modal?.addEventListener('click', (event) => {
            if (event.target === modal) closeModal();
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') closeModal();
        });
    });
</script>
@endpush
