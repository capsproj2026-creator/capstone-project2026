<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GeneralInformation;
use App\Models\Notification;
use App\Models\ParkingArea;
use App\Models\ParkingRule;
use App\Models\StalledVehicle;
use App\Models\User;
use App\Models\ViolationType;
use App\Services\NavigationService;
use App\Services\RolePermissionService;
use App\Services\SequenceService;
use App\Services\SystemSettingService;
use App\Support\CampusParkingPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SettingsController extends Controller
{
    /** @var list<string> */
    private const SECTIONS = ['general', 'admins', 'notifications', 'violations', 'access', 'policy'];

    public function index(Request $request, SystemSettingService $settings, RolePermissionService $permissions): View
    {
        $section = (string) $request->query('section', 'general');

        if ($section === 'parking') {
            $section = 'access';
        }

        if (! in_array($section, self::SECTIONS, true)) {
            $section = 'general';
        }

        return view('admin.settings', [
            'section' => $section,
            'systemSettings' => $settings->all(),
            'timezoneOptions' => $settings->timezoneOptions(),
            'generalInfo' => GeneralInformation::query()->orderBy('id')->get(),
            'parkingRules' => ParkingRule::query()->orderBy('id')->get(),
            'stalledVehicles' => StalledVehicle::query()->orderBy('id')->get(),
            'violationTypes' => ViolationType::query()->orderBy('id')->get(),
            'policyStaticSections' => CampusParkingPolicy::staticSections(),
            'adminUsers' => User::query()
                ->where('user_role_id', NavigationService::ROLE_ADMIN)
                ->orderBy('name')
                ->get(),
            'staffUsers' => User::query()
                ->with('role')
                ->whereIn('user_role_id', [
                    NavigationService::ROLE_ADMIN,
                    NavigationService::ROLE_GUARD,
                ])
                ->orderBy('user_role_id')
                ->orderBy('name')
                ->get(),
            'rolePermissionService' => $permissions,
            'zones' => ParkingArea::query()->orderBy('area_name')->get(),
        ]);
    }

    public function updateSystemInfo(Request $request, SystemSettingService $settings): RedirectResponse
    {
        $validated = $request->validate([
            'campus_name' => ['required', 'string', 'max:255'],
            'timezone' => ['required', 'timezone:all'],
            'contact_email' => ['required', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
        ]);

        $settings->update($validated);

        $timezone = $validated['timezone'];
        config(['app.timezone' => $timezone]);
        date_default_timezone_set($timezone);

        return back()->with('success', 'System information updated.');
    }

    public function updatePreferences(Request $request, SystemSettingService $settings): RedirectResponse
    {
        $settings->update([
            'auto_lock_on_3rd_violation' => $request->boolean('auto_lock_on_3rd_violation'),
            'send_violation_notifications' => $request->boolean('send_violation_notifications'),
            'enable_visitor_time_limits' => $request->boolean('enable_visitor_time_limits'),
            'require_photo_evidence' => $request->boolean('require_photo_evidence'),
        ]);

        return back()->with('success', 'System preferences updated.');
    }

    public function updateGeneral(Request $request): RedirectResponse
    {
        return $this->saveGeneralInfo($request);
    }

    public function saveGeneralInfo(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'active' => ['nullable', 'array'],
            'active.*' => ['integer'],
        ]);

        $activeIds = collect($validated['active'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        GeneralInformation::query()
            ->orderBy('id')
            ->get()
            ->each(function (GeneralInformation $info) use ($activeIds): void {
                $shouldBeActive = in_array((int) $info->id, $activeIds, true);
                $nextStatus = $shouldBeActive ? 'Active' : 'Inactive';

                if (($info->isActive() && $shouldBeActive) || (! $info->isActive() && ! $shouldBeActive)) {
                    return;
                }

                $info->update(['status' => $nextStatus]);
            });

        return redirect()
            ->route('admin.settings', ['section' => 'notifications'])
            ->with('success', 'General Information saved.');
    }

    public function storeGeneralInfo(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'description' => ['required', 'string', 'max:2000'],
        ]);

        $description = trim($validated['description']);

        GeneralInformation::query()->create([
            'id' => SequenceService::next('general_informations'),
            'description' => $description,
            'status' => 'Active',
        ]);

        $this->fanOutCampusNotice('General Information Updated', $description);

        return redirect()
            ->route('admin.settings', ['section' => 'notifications'])
            ->with('success', 'General Information item added.');
    }

    public function updateGeneralInfo(Request $request, int $id): RedirectResponse
    {
        $info = GeneralInformation::query()->where('id', $id)->firstOrFail();

        $validated = $request->validate([
            'description' => ['required', 'string', 'max:2000'],
        ]);

        $description = trim($validated['description']);

        if ((string) $info->description !== $description) {
            $info->update(['description' => $description]);
            $this->fanOutCampusNotice('General Information Updated', $description);
        }

        return redirect()
            ->route('admin.settings', ['section' => 'notifications'])
            ->with('success', 'General Information item updated.');
    }

    public function destroyGeneralInfo(int $id): RedirectResponse
    {
        $info = GeneralInformation::query()->where('id', $id)->firstOrFail();
        $info->delete();

        return redirect()
            ->route('admin.settings', ['section' => 'notifications'])
            ->with('success', 'General Information item deleted.');
    }

    private function fanOutCampusNotice(string $title, string $message): void
    {
        $message = trim($message);
        if ($message === '') {
            return;
        }

        $userIds = User::query()
            ->whereIn('user_role_id', [
                NavigationService::ROLE_STUDENT,
                NavigationService::ROLE_STAFF,
            ])
            ->where('status', User::STATUS_GRANTED)
            ->pluck('id');

        $senderId = auth()->id();
        $now = now();

        foreach ($userIds as $userId) {
            Notification::query()->create([
                'user_id' => (int) $userId,
                'sender_id' => $senderId,
                'title' => $title,
                'message' => $message,
                'type' => 'General',
                'is_read' => false,
                'created_at' => $now,
            ]);
        }
    }

    public function storeAdmin(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => [
                'required',
                'email:rfc,dns',
                'max:100',
                Rule::unique(User::class, 'email'),
            ],
            'phone_number' => ['nullable', 'string', 'max:20'],
            'id_number' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9]+$/', Rule::unique(User::class, 'id_number')],
            'job_title' => ['nullable', 'string', Rule::in(['Admin', 'Security Head'])],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        User::query()->create([
            'id' => SequenceService::next('users'),
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone_number' => $validated['phone_number'] ?? '',
            'password' => Hash::make($validated['password']),
            'user_role_id' => NavigationService::ROLE_ADMIN,
            'job_title' => $validated['job_title'] ?? 'Admin',
            'id_number' => $validated['id_number'],
            'plate_number' => 'N/A',
            'profile_pic' => 'default_avatar.png',
            'driver_license' => 'N/A',
            'or_cr_photo' => 'N/A',
            'status' => User::STATUS_GRANTED,
            'Gate_access' => User::GATE_ACCESS_GRANTED,
            'strike_count' => 0,
            'email_verified_at' => now(),
            'created_at' => now(),
        ]);

        return redirect()
            ->route('admin.settings', ['section' => 'admins'])
            ->with('success', 'Admin user created.');
    }

    public function destroyStaffUser(Request $request, int $id): RedirectResponse
    {
        $user = User::query()
            ->whereIn('user_role_id', [
                NavigationService::ROLE_ADMIN,
                NavigationService::ROLE_GUARD,
            ])
            ->where('id', $id)
            ->firstOrFail();

        if ((int) $user->id === (int) $request->user()->id) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        if ((int) $user->user_role_id === NavigationService::ROLE_ADMIN) {
            $adminCount = User::query()
                ->where('user_role_id', NavigationService::ROLE_ADMIN)
                ->count();

            if ($adminCount <= 1) {
                return back()->with('error', 'At least one admin account must remain.');
            }
        }

        $user->delete();

        return redirect()
            ->route('admin.settings', ['section' => 'admins'])
            ->with('success', 'User removed.');
    }

    public function storeParkingRule(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'description' => ['required', 'string', 'max:2000'],
        ]);

        ParkingRule::query()->create([
            'id' => SequenceService::next('parking_rules'),
            'description' => $validated['description'],
            'status' => 'Active',
        ]);

        return redirect()
            ->route('admin.settings', ['section' => 'access'])
            ->with('success', 'Parking access rule added.');
    }

    public function updateParkingRule(Request $request, int $id): RedirectResponse
    {
        $rule = ParkingRule::query()->where('id', $id)->firstOrFail();

        $validated = $request->validate([
            'description' => ['required', 'string', 'max:2000'],
        ]);

        $rule->update([
            'description' => $validated['description'],
        ]);

        return redirect()
            ->route('admin.settings', ['section' => 'access'])
            ->with('success', 'Parking access rule updated.');
    }

    public function toggleParkingRule(int $id): RedirectResponse
    {
        $rule = ParkingRule::query()->where('id', $id)->firstOrFail();

        $rule->update([
            'status' => $rule->isActive() ? 'Inactive' : 'Active',
        ]);

        return redirect()
            ->route('admin.settings', ['section' => 'access'])
            ->with('success', 'Parking access rule status updated.');
    }

    public function saveParkingRules(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'active' => ['nullable', 'array'],
            'active.*' => ['integer'],
        ]);

        $activeIds = collect($validated['active'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        ParkingRule::query()
            ->orderBy('id')
            ->get()
            ->each(function (ParkingRule $rule) use ($activeIds): void {
                $shouldBeActive = in_array((int) $rule->id, $activeIds, true);
                $nextStatus = $shouldBeActive ? 'Active' : 'Inactive';

                if (($rule->isActive() && $shouldBeActive) || (! $rule->isActive() && ! $shouldBeActive)) {
                    return;
                }

                $rule->update(['status' => $nextStatus]);
            });

        return redirect()
            ->route('admin.settings', ['section' => 'access'])
            ->with('success', 'Parking access rules saved.');
    }

    public function destroyParkingRule(int $id): RedirectResponse
    {
        $rule = ParkingRule::query()->where('id', $id)->firstOrFail();
        $rule->delete();

        return redirect()
            ->route('admin.settings', ['section' => 'access'])
            ->with('success', 'Parking access rule deleted.');
    }

    public function storeStalledVehicle(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'description' => ['required', 'string', 'max:2000'],
        ]);

        StalledVehicle::query()->create([
            'id' => SequenceService::next('stalled_vehicles'),
            'description' => trim($validated['description']),
            'status' => 'Active',
        ]);

        return redirect()
            ->route('admin.settings', ['section' => 'access'])
            ->with('success', 'Stalled Vehicles item added.');
    }

    public function updateStalledVehicle(Request $request, int $id): RedirectResponse
    {
        $item = StalledVehicle::query()->where('id', $id)->firstOrFail();

        $validated = $request->validate([
            'description' => ['required', 'string', 'max:2000'],
        ]);

        $item->update([
            'description' => trim($validated['description']),
        ]);

        return redirect()
            ->route('admin.settings', ['section' => 'access'])
            ->with('success', 'Stalled Vehicles item updated.');
    }

    public function saveStalledVehicles(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'active' => ['nullable', 'array'],
            'active.*' => ['integer'],
        ]);

        $activeIds = collect($validated['active'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        StalledVehicle::query()
            ->orderBy('id')
            ->get()
            ->each(function (StalledVehicle $item) use ($activeIds): void {
                $shouldBeActive = in_array((int) $item->id, $activeIds, true);
                $nextStatus = $shouldBeActive ? 'Active' : 'Inactive';

                if (($item->isActive() && $shouldBeActive) || (! $item->isActive() && ! $shouldBeActive)) {
                    return;
                }

                $item->update(['status' => $nextStatus]);
            });

        return redirect()
            ->route('admin.settings', ['section' => 'access'])
            ->with('success', 'Stalled Vehicles saved.');
    }

    public function destroyStalledVehicle(int $id): RedirectResponse
    {
        $item = StalledVehicle::query()->where('id', $id)->firstOrFail();
        $item->delete();

        return redirect()
            ->route('admin.settings', ['section' => 'access'])
            ->with('success', 'Stalled Vehicles item deleted.');
    }

    public function storeViolationType(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'violation_name' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:500'],
        ]);

        ViolationType::query()->create([
            'id' => SequenceService::next('violation_types'),
            'violation_name' => $validated['violation_name'],
            'description' => $validated['description'],
            'status' => 'Active',
        ]);

        return redirect()
            ->route('admin.settings', ['section' => 'violations'])
            ->with('success', 'Violation type added.');
    }

    public function updateViolationType(Request $request, int $id): RedirectResponse
    {
        $type = ViolationType::query()->where('id', $id)->firstOrFail();

        $validated = $request->validate([
            'violation_name' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:500'],
        ]);

        $type->update([
            'violation_name' => $validated['violation_name'],
            'description' => $validated['description'],
        ]);

        return redirect()
            ->route('admin.settings', ['section' => 'violations'])
            ->with('success', 'Violation type updated.');
    }

    public function toggleViolationType(int $id): RedirectResponse
    {
        $type = ViolationType::query()->where('id', $id)->firstOrFail();
        $isActive = strcasecmp((string) ($type->status ?? ''), 'Active') === 0;

        $type->update([
            'status' => $isActive ? 'Inactive' : 'Active',
        ]);

        return redirect()
            ->route('admin.settings', ['section' => 'violations'])
            ->with('success', 'Violation type status updated.');
    }

    public function saveViolationTypes(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'active' => ['nullable', 'array'],
            'active.*' => ['integer'],
        ]);

        $activeIds = collect($validated['active'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        ViolationType::query()
            ->orderBy('id')
            ->get()
            ->each(function (ViolationType $type) use ($activeIds): void {
                $isActive = strcasecmp((string) ($type->status ?? ''), 'Active') === 0;
                $shouldBeActive = in_array((int) $type->id, $activeIds, true);
                $nextStatus = $shouldBeActive ? 'Active' : 'Inactive';

                if (($isActive && $shouldBeActive) || (! $isActive && ! $shouldBeActive)) {
                    return;
                }

                $type->update(['status' => $nextStatus]);
            });

        return redirect()
            ->route('admin.settings', ['section' => 'violations'])
            ->with('success', 'Violation types saved.');
    }

    public function destroyViolationType(int $id): RedirectResponse
    {
        $type = ViolationType::query()->where('id', $id)->firstOrFail();
        $type->delete();

        return redirect()
            ->route('admin.settings', ['section' => 'violations'])
            ->with('success', 'Violation type deleted.');
    }
}
