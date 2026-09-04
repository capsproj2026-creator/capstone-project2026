@extends('layouts.admin')

@section('title', 'System Settings')

@section('content')
    @php
        $tabs = [
            'general' => ['label' => 'General', 'icon' => 'settings'],
            'admins' => ['label' => 'Admin Users', 'icon' => 'shield'],
            'notifications' => ['label' => 'Notifications', 'icon' => 'bell'],
            'violations' => ['label' => 'Violations', 'icon' => 'triangle-alert'],
            'access' => ['label' => 'Access Rules', 'icon' => 'key-round'],
        ];
    @endphp

    <div class="mx-auto w-full max-w-none pb-10">
        <div class="settings-sticky-header sticky z-10">
            @include('partials.shell.page-header', [
                'title' => 'System Settings',
                'subtitle' => 'Configure system preferences and access rules',
            ])

            {{-- Segmented settings sub-navbar --}}
            <div class="settings-subnav">
            <nav class="settings-subnav__track">
                @foreach ($tabs as $key => $tab)
                    <a
                        href="{{ route('admin.settings', ['section' => $key]) }}"
                        @class([
                            'settings-subnav__tab',
                            'settings-subnav__tab--active' => $section === $key,
                        ])
                    >
                        <i data-lucide="{{ $tab['icon'] }}" class="h-4 w-4 shrink-0"></i>
                        <span>{{ $tab['label'] }}</span>
                    </a>
                @endforeach
            </nav>
        </div>
        </div>

        @if ($section === 'general')
            <form method="POST" action="{{ route('admin.settings.system') }}" class="mb-6">
                @csrf
                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                    <h3 class="text-lg font-semibold text-gray-900">System Information</h3>
                    <p class="mt-1 text-sm text-gray-500">Basic system configuration and information</p>

                    <div class="mt-6 grid grid-cols-1 gap-5 md:grid-cols-2">
                        <div class="min-w-0">
                            <label class="mb-1.5 block text-sm font-semibold text-gray-800">Campus Name</label>
                            <input
                                type="text"
                                name="campus_name"
                                value="{{ old('campus_name', $systemSettings['campus_name']) }}"
                                required
                                class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm text-gray-900 focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20"
                            >
                        </div>
                        <div class="min-w-0">
                            <label class="mb-1.5 block text-sm font-semibold text-gray-800">Timezone</label>
                            <select
                                name="timezone"
                                required
                                class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm text-gray-900 focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20"
                            >
                                @foreach ($timezoneOptions as $tz)
                                    <option value="{{ $tz['value'] }}" @selected(old('timezone', $systemSettings['timezone']) === $tz['value'])>
                                        {{ $tz['label'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="min-w-0">
                            <label class="mb-1.5 block text-sm font-semibold text-gray-800">Contact Email</label>
                            <input
                                type="email"
                                name="contact_email"
                                value="{{ old('contact_email', $systemSettings['contact_email']) }}"
                                required
                                class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm text-gray-900 focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20"
                            >
                        </div>
                        <div class="min-w-0">
                            <label class="mb-1.5 block text-sm font-semibold text-gray-800">Contact Phone</label>
                            <input
                                type="text"
                                name="contact_phone"
                                value="{{ old('contact_phone', $systemSettings['contact_phone']) }}"
                                class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm text-gray-900 focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20"
                            >
                        </div>
                    </div>

                    <button type="submit" class="mt-6 inline-flex items-center gap-2 rounded-lg bg-gray-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-black">
                        <i data-lucide="save" class="h-4 w-4"></i>
                        Save Changes
                    </button>
                </div>
            </form>

            <form method="POST" action="{{ route('admin.settings.preferences') }}">
                @csrf
                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                    <h3 class="text-lg font-semibold text-gray-900">System Preferences</h3>
                    <p class="mt-1 text-sm text-gray-500">Control enforcement and notification behavior</p>

                    <div class="mt-6 divide-y divide-gray-100">
                        @php
                            $prefs = [
                                [
                                    'name' => 'auto_lock_on_3rd_violation',
                                    'title' => 'Auto-Lock on 3rd Violation',
                                    'desc' => 'Automatically suspend accounts after 3 violations.',
                                    'checked' => $systemSettings['auto_lock_on_3rd_violation'],
                                ],
                                [
                                    'name' => 'send_violation_notifications',
                                    'title' => 'Send Violation Notifications',
                                    'desc' => 'Automatically notify users when violations are logged.',
                                    'checked' => $systemSettings['send_violation_notifications'],
                                ],
                                [
                                    'name' => 'enable_visitor_time_limits',
                                    'title' => 'Enable Visitor Time Limits',
                                    'desc' => 'Enforce parking time limits for visitors.',
                                    'checked' => $systemSettings['enable_visitor_time_limits'],
                                ],
                                [
                                    'name' => 'require_photo_evidence',
                                    'title' => 'Require Photo Evidence',
                                    'desc' => 'Require photo upload for violation logging.',
                                    'checked' => $systemSettings['require_photo_evidence'],
                                ],
                            ];
                        @endphp

                        @foreach ($prefs as $pref)
                            <div class="flex items-start justify-between gap-4 py-4 sm:items-center">
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-semibold text-gray-900">{{ $pref['title'] }}</p>
                                    <p class="mt-0.5 text-sm text-gray-500">{{ $pref['desc'] }}</p>
                                </div>
                                <label class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer items-center">
                                    <input type="checkbox" name="{{ $pref['name'] }}" value="1" class="peer sr-only" @checked(old($pref['name'], $pref['checked']))>
                                    <span class="absolute inset-0 rounded-full bg-gray-200 transition peer-checked:bg-gray-900 peer-focus:ring-2 peer-focus:ring-gray-900/20"></span>
                                    <span class="absolute left-0.5 top-0.5 h-5 w-5 rounded-full bg-white shadow transition peer-checked:translate-x-5"></span>
                                </label>
                            </div>
                        @endforeach
                    </div>

                    <button type="submit" class="mt-4 inline-flex items-center gap-2 rounded-lg bg-gray-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-black">
                        <i data-lucide="save" class="h-4 w-4"></i>
                        Save Preferences
                    </button>
                </div>
            </form>

        @elseif ($section === 'admins')
            @include('partials.admin.settings-admins')

        @elseif ($section === 'notifications')
            <form method="POST" action="{{ route('admin.settings.general') }}" class="mb-6">
                @csrf
                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                    <h3 class="text-lg font-semibold text-gray-900">Campus Notices</h3>
                    <p class="mt-1 mb-5 text-sm text-gray-500">Messages shown to users on their dashboard</p>

                    <div class="space-y-4">
                        @forelse ($generalInfo as $item)
                            <div class="min-w-0">
                                <label class="mb-1 block text-xs font-medium uppercase tracking-wide text-gray-500">Notice #{{ $item->id }}</label>
                                <textarea name="descriptions[{{ $item->id }}]" rows="3"
                                    class="w-full resize-y rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20">{{ old("descriptions.{$item->id}", $item->description) }}</textarea>
                            </div>
                        @empty
                            <p class="text-sm text-gray-500">No campus notices yet.</p>
                        @endforelse
                    </div>

                    @if ($generalInfo->isNotEmpty())
                        <button type="submit" class="mt-5 inline-flex items-center gap-2 rounded-lg bg-gray-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-black">
                            <i data-lucide="save" class="h-4 w-4"></i>
                            Save Notices
                        </button>
                    @endif
                </div>
            </form>

            <div class="mb-6 overflow-hidden rounded-xl border border-dashed border-gray-300 bg-white p-5 shadow-sm">
                <h4 class="mb-3 text-sm font-semibold text-gray-900">Add Campus Notice</h4>
                <form method="POST" action="{{ route('admin.settings.general.store') }}" class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    @csrf
                    <input type="text" name="description" required placeholder="New policy or notice text..."
                        class="min-w-0 w-full flex-1 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm">
                    <button type="submit" class="shrink-0 rounded-lg bg-blue-600 px-5 py-2 text-sm font-semibold text-white hover:bg-blue-700">Add Notice</button>
                </form>
            </div>

            @if ($stalledVehicles->isNotEmpty())
                <div class="overflow-hidden rounded-xl border border-amber-200 bg-amber-50/40 p-5 shadow-sm sm:p-6">
                    <h3 class="mb-3 flex items-center gap-2 text-base font-semibold text-gray-900">
                        <i data-lucide="clock" class="h-4 w-4 shrink-0 text-amber-600"></i>
                        Stalled Vehicle Thresholds
                    </h3>
                    <ul class="space-y-2 text-sm text-gray-700">
                        @foreach ($stalledVehicles as $vehicle)
                            <li class="break-words rounded-lg bg-white px-3 py-2">{{ $vehicle->description ?? 'Threshold #'.$vehicle->id }}</li>
                        @endforeach
                    </ul>
                    <p class="mt-3 text-xs text-gray-500">
                        Applied when “Enable Visitor Time Limits” is on in General preferences.
                    </p>
                </div>
            @endif

        @elseif ($section === 'violations')
            @include('partials.admin.settings-violations')

        @else
            @include('partials.admin.settings-parking-rules')

            <div class="pb-4">
                @include('partials.admin.zone-access-settings', ['zones' => $zones])
            </div>
        @endif
    </div>
@endsection

@push('scripts')
<script>
(() => {
    if (window.lucide) window.lucide.createIcons();

    const openModal = (id) => {
        const el = document.getElementById(id);
        if (!el) return;
        el.classList.remove('hidden');
        el.classList.add('flex');
        document.body.classList.add('overflow-hidden');
        if (window.lucide) window.lucide.createIcons();
    };
    const closeModal = (id) => {
        const el = document.getElementById(id);
        if (!el) return;
        el.classList.add('hidden');
        el.classList.remove('flex');
        document.body.classList.remove('overflow-hidden');
    };

    document.getElementById('open-create-admin')?.addEventListener('click', () => openModal('create-admin-modal'));
    document.getElementById('open-create-guard')?.addEventListener('click', () => openModal('create-guard-modal'));

    const violationForm = document.getElementById('violation-type-form');
    const violationMethod = document.getElementById('violation-form-method');
    const violationTitle = document.getElementById('violation-modal-title');
    const violationName = document.getElementById('violation_name');
    const violationDesc = document.getElementById('violation_description');
    const storeUrl = @json(route('admin.settings.violations.store'));
    const updateUrlTemplate = @json(route('admin.settings.violations.update', ['id' => '__ID__']));

    const openAddViolation = () => {
        if (!violationForm) return;
        violationForm.action = storeUrl;
        if (violationMethod) violationMethod.value = 'POST';
        if (violationTitle) violationTitle.textContent = 'Add Violation Type';
        if (violationName) violationName.value = '';
        if (violationDesc) violationDesc.value = '';
        openModal('violation-type-modal');
        violationName?.focus();
    };

    const openEditViolation = (btn) => {
        if (!violationForm) return;
        const id = btn.getAttribute('data-id');
        violationForm.action = updateUrlTemplate.replace('__ID__', encodeURIComponent(id));
        if (violationMethod) violationMethod.value = 'PUT';
        if (violationTitle) violationTitle.textContent = 'Edit Violation Type';
        if (violationName) violationName.value = btn.getAttribute('data-name') || '';
        if (violationDesc) violationDesc.value = btn.getAttribute('data-description') || '';
        openModal('violation-type-modal');
        violationName?.focus();
    };

    document.getElementById('open-add-violation')?.addEventListener('click', openAddViolation);
    document.querySelectorAll('[data-edit-violation]').forEach((btn) => {
        btn.addEventListener('click', () => openEditViolation(btn));
    });

    const parkingRuleForm = document.getElementById('parking-rule-form');
    const parkingRuleMethod = document.getElementById('parking-rule-form-method');
    const parkingRuleTitle = document.getElementById('parking-rule-modal-title');
    const parkingRuleDesc = document.getElementById('parking_rule_description');
    const parkingStoreUrl = @json(route('admin.settings.parking.store'));
    const parkingUpdateUrlTemplate = @json(route('admin.settings.parking.update', ['id' => '__ID__']));

    const openAddParkingRule = () => {
        if (!parkingRuleForm) return;
        parkingRuleForm.action = parkingStoreUrl;
        if (parkingRuleMethod) parkingRuleMethod.value = 'POST';
        if (parkingRuleTitle) parkingRuleTitle.textContent = 'Add Parking Access Rule';
        if (parkingRuleDesc) parkingRuleDesc.value = '';
        openModal('parking-rule-modal');
        parkingRuleDesc?.focus();
    };

    const openEditParkingRule = (btn) => {
        if (!parkingRuleForm) return;
        const id = btn.getAttribute('data-id');
        parkingRuleForm.action = parkingUpdateUrlTemplate.replace('__ID__', encodeURIComponent(id));
        if (parkingRuleMethod) parkingRuleMethod.value = 'PUT';
        if (parkingRuleTitle) parkingRuleTitle.textContent = 'Edit Parking Access Rule';
        if (parkingRuleDesc) parkingRuleDesc.value = btn.getAttribute('data-description') || '';
        openModal('parking-rule-modal');
        parkingRuleDesc?.focus();
    };

    document.getElementById('open-add-parking-rule')?.addEventListener('click', openAddParkingRule);
    document.querySelectorAll('[data-edit-parking-rule]').forEach((btn) => {
        btn.addEventListener('click', () => openEditParkingRule(btn));
    });

    document.querySelectorAll('[data-close-modal]').forEach((btn) => {
        btn.addEventListener('click', () => closeModal(btn.getAttribute('data-close-modal')));
    });
    ['create-admin-modal', 'create-guard-modal', 'violation-type-modal', 'parking-rule-modal'].forEach((id) => {
        document.getElementById(id)?.addEventListener('click', (e) => {
            if (e.target.id === id) closeModal(id);
        });
    });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeModal('create-admin-modal');
            closeModal('create-guard-modal');
            closeModal('violation-type-modal');
            closeModal('parking-rule-modal');
        }
    });
})();
</script>
@endpush
