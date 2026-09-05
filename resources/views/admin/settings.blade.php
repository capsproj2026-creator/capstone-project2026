@extends('layouts.admin')

@section('title', 'System Settings')

@section('content')
    @php
        $tabs = [
            'general' => ['label' => 'General', 'icon' => 'settings'],
            'admins' => ['label' => 'Admin Users', 'icon' => 'shield'],
            'notifications' => ['label' => 'Notifications', 'icon' => 'info'],
            'violations' => ['label' => 'Violations', 'icon' => 'triangle-alert'],
            'access' => ['label' => 'Access Rules', 'icon' => 'key-round'],
            'policy' => ['label' => 'Policy', 'icon' => 'book-open'],
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
            @include('partials.admin.settings-notifications')

        @elseif ($section === 'violations')
            @include('partials.admin.settings-violations')

        @elseif ($section === 'policy')
            @include('partials.admin.settings-policy')

        @else
            @include('partials.admin.settings-parking-rules')
            @include('partials.admin.settings-stalled-vehicles')

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

    const campusNoticeForm = document.getElementById('campus-notice-form');
    const campusNoticeMethod = document.getElementById('campus-notice-form-method');
    const campusNoticeTitle = document.getElementById('campus-notice-modal-title');
    const campusNoticeDesc = document.getElementById('campus_notice_description');
    const campusNoticeStoreUrl = @json(route('admin.settings.general.store'));
    const campusNoticeUpdateUrlTemplate = @json(route('admin.settings.general.update', ['id' => '__ID__']));

    const openAddCampusNotice = () => {
        if (!campusNoticeForm) return;
        campusNoticeForm.action = campusNoticeStoreUrl;
        if (campusNoticeMethod) campusNoticeMethod.value = 'POST';
        if (campusNoticeTitle) campusNoticeTitle.textContent = 'Add General Information';
        if (campusNoticeDesc) campusNoticeDesc.value = '';
        openModal('campus-notice-modal');
        campusNoticeDesc?.focus();
    };

    const openEditCampusNotice = (btn) => {
        if (!campusNoticeForm) return;
        const id = btn.getAttribute('data-id');
        campusNoticeForm.action = campusNoticeUpdateUrlTemplate.replace('__ID__', encodeURIComponent(id));
        if (campusNoticeMethod) campusNoticeMethod.value = 'PUT';
        if (campusNoticeTitle) campusNoticeTitle.textContent = 'Edit General Information';
        if (campusNoticeDesc) campusNoticeDesc.value = btn.getAttribute('data-description') || '';
        openModal('campus-notice-modal');
        campusNoticeDesc?.focus();
    };

    document.getElementById('open-add-campus-notice')?.addEventListener('click', openAddCampusNotice);
    document.querySelectorAll('[data-edit-campus-notice]').forEach((btn) => {
        btn.addEventListener('click', () => openEditCampusNotice(btn));
    });

    const stalledForm = document.getElementById('stalled-vehicle-form');
    const stalledMethod = document.getElementById('stalled-vehicle-form-method');
    const stalledTitle = document.getElementById('stalled-vehicle-modal-title');
    const stalledDesc = document.getElementById('stalled_vehicle_description');
    const stalledStoreUrl = @json(route('admin.settings.stalled.store'));
    const stalledUpdateUrlTemplate = @json(route('admin.settings.stalled.update', ['id' => '__ID__']));

    const openAddStalledVehicle = () => {
        if (!stalledForm) return;
        stalledForm.action = stalledStoreUrl;
        if (stalledMethod) stalledMethod.value = 'POST';
        if (stalledTitle) stalledTitle.textContent = 'Add Stalled Vehicles Item';
        if (stalledDesc) stalledDesc.value = '';
        openModal('stalled-vehicle-modal');
        stalledDesc?.focus();
    };

    const openEditStalledVehicle = (btn) => {
        if (!stalledForm) return;
        const id = btn.getAttribute('data-id');
        stalledForm.action = stalledUpdateUrlTemplate.replace('__ID__', encodeURIComponent(id));
        if (stalledMethod) stalledMethod.value = 'PUT';
        if (stalledTitle) stalledTitle.textContent = 'Edit Stalled Vehicles Item';
        if (stalledDesc) stalledDesc.value = btn.getAttribute('data-description') || '';
        openModal('stalled-vehicle-modal');
        stalledDesc?.focus();
    };

    document.getElementById('open-add-stalled-vehicle')?.addEventListener('click', openAddStalledVehicle);
    document.querySelectorAll('[data-edit-stalled-vehicle]').forEach((btn) => {
        btn.addEventListener('click', () => openEditStalledVehicle(btn));
    });

    document.querySelectorAll('[data-close-modal]').forEach((btn) => {
        btn.addEventListener('click', () => closeModal(btn.getAttribute('data-close-modal')));
    });
    ['create-admin-modal', 'create-guard-modal', 'violation-type-modal', 'parking-rule-modal', 'campus-notice-modal', 'stalled-vehicle-modal'].forEach((id) => {
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
            closeModal('campus-notice-modal');
            closeModal('stalled-vehicle-modal');
        }
    });
})();
</script>
@endpush
