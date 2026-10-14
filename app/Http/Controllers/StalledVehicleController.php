<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\StalledVehicleReport;
use App\Models\User;
use App\Services\NavigationService;
use App\Services\RegistrationAlertService;
use App\Support\PlateLookup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Stalled vehicles reported to the GSU through Security: 12-hour grace period, then 3 hours to tow.
 */
class StalledVehicleController extends Controller
{
    public function index(Request $request): View
    {
        $pending = StalledVehicleReport::query()
            ->where('status', StalledVehicleReport::STATUS_PENDING)
            ->orderBy('reported_at')
            ->get();

        $active = StalledVehicleReport::query()
            ->where('status', StalledVehicleReport::STATUS_ACTIVE)
            ->orderBy('reported_at')
            ->get();

        $this->notifyGraceEnded($active);

        $removed = StalledVehicleReport::query()
            ->where('status', StalledVehicleReport::STATUS_REMOVED)
            ->orderByDesc('removed_at')
            ->limit(15)
            ->get();

        $staffIds = $pending->pluck('reported_by')
            ->concat($active->pluck('reported_by'))
            ->concat($removed->pluck('reported_by'))
            ->concat($removed->pluck('removed_by'))
            ->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();
        $staffNames = $staffIds === []
            ? []
            : User::query()->whereIn('id', $staffIds)->get(['id', 'fullname'])
                ->mapWithKeys(fn (User $u) => [(int) $u->id => $u->fullname])->all();

        $prefix = $this->routePrefix($request);

        return view('stalled.index', [
            'layout' => $prefix === 'admin' ? 'layouts.admin' : 'layouts.guard',
            'prefix' => $prefix,
            'canReport' => $prefix === 'guard',
            'pending' => $pending,
            'active' => $active,
            'removed' => $removed,
            'staffNames' => $staffNames,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->routeIs('guard.*'), 403, 'Only a guard can file a stalled vehicle report.');

        $data = $request->validate([
            'plate_number' => ['required', 'string', 'max:20'],
            'owner_name' => ['nullable', 'string', 'max:120'],
            'location' => ['required', 'string', 'max:160'],
            'cause' => ['required', Rule::in([
                StalledVehicleReport::CAUSE_STALLED,
                StalledVehicleReport::CAUSE_KEY,
                StalledVehicleReport::CAUSE_OTHER,
            ])],
            'other_reason' => ['nullable', 'required_if:cause,'.StalledVehicleReport::CAUSE_OTHER, 'string', 'min:3', 'max:200'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $plate = strtoupper(trim($data['plate_number']));
        $owner = PlateLookup::findUser($plate);
        $ownerName = $owner?->displayName() ?: trim((string) ($data['owner_name'] ?? ''));

        if ($ownerName === '') {
            return back()->withInput()->withErrors(['owner_name' => 'This plate is not registered. Enter the owner\'s name.']);
        }

        $alreadyOpen = StalledVehicleReport::query()
            ->whereIn('status', [StalledVehicleReport::STATUS_PENDING, StalledVehicleReport::STATUS_ACTIVE])
            ->where('plate_number', $plate)
            ->exists();
        if ($alreadyOpen) {
            return back()->withInput()->withErrors(['plate_number' => 'This vehicle already has an open stalled report.']);
        }

        $report = StalledVehicleReport::query()->create([
            'user_id' => $owner?->id,
            'owner_name' => $ownerName,
            'plate_number' => $plate,
            'location' => trim($data['location']),
            'cause' => $data['cause'],
            'other_reason' => $data['cause'] === StalledVehicleReport::CAUSE_OTHER ? trim((string) $data['other_reason']) : null,
            'notes' => filled($data['notes'] ?? null) ? trim($data['notes']) : null,
            'reported_by' => (int) $request->user()->id,
            'reported_at' => now(),
            'status' => StalledVehicleReport::STATUS_PENDING,
        ]);

        app(RegistrationAlertService::class)->notifyAdmins(
            $request->user(),
            'Stalled vehicle needs approval',
            "{$ownerName} ({$plate}) was reported at {$report->location}: {$report->causeLabel()}. Approve or decline it under Stalled Vehicles."
        );

        return redirect()->route('guard.stalled-vehicles')
            ->with('success', "Report for {$plate} was sent to the admin for approval.");
    }

    public function approve(Request $request, int $id): RedirectResponse
    {
        abort_unless($request->routeIs('admin.*'), 403);

        $report = StalledVehicleReport::query()->findOrFail($id);
        if ($report->status !== StalledVehicleReport::STATUS_PENDING) {
            return back()->with('error', 'This report is no longer waiting for approval.');
        }

        $report->update([
            'status' => StalledVehicleReport::STATUS_ACTIVE,
            'reported_at' => now(),
        ]);
        $report->refresh();

        $owner = $report->user_id ? User::query()->find((int) $report->user_id) : null;
        if ($owner) {
            $this->notifyOwner($owner, 'Stalled Vehicle Reported',
                "Your vehicle ({$report->plate_number}) was reported as stalled at {$report->location} ({$report->causeLabel()}). "
                .'You have '.StalledVehicleReport::GRACE_HOURS.' hours, until '.ph_datetime($report->graceEndsAt(), 'M j, g:i A')
                .', to have it repaired or moved. After that it must be towed within '.StalledVehicleReport::TOW_HOURS.' hours.');
        }

        return back()->with('success', "Approved. Grace period for {$report->plate_number} ends ".ph_datetime($report->graceEndsAt(), 'M j, g:i A').'.');
    }

    public function decline(Request $request, int $id): RedirectResponse
    {
        abort_unless($request->routeIs('admin.*'), 403);

        $data = $request->validate([
            'decline_reason' => ['required', 'string', 'min:3', 'max:300'],
        ]);

        $report = StalledVehicleReport::query()->findOrFail($id);
        if ($report->status !== StalledVehicleReport::STATUS_PENDING) {
            return back()->with('error', 'This report is no longer waiting for approval.');
        }

        $reason = trim($data['decline_reason']);
        $report->update([
            'status' => StalledVehicleReport::STATUS_DECLINED,
            'decline_reason' => $reason,
        ]);

        $guard = $report->reported_by ? User::query()->find((int) $report->reported_by) : null;
        if ($guard && (int) $guard->user_role_id === NavigationService::ROLE_GUARD) {
            $this->notifyOwner($guard, 'Stalled report declined',
                "The admin declined the stalled report for {$report->plate_number}. Reason: {$reason}");
        }

        return back()->with('success', "Declined the report for {$report->plate_number}.");
    }

    public function markRemoved(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate([
            'removal_notes' => ['nullable', 'string', 'max:300'],
        ]);

        $report = StalledVehicleReport::query()->findOrFail($id);
        if (! $report->isActive()) {
            return back()->with('error', 'This report is already closed.');
        }

        $report->update([
            'status' => StalledVehicleReport::STATUS_REMOVED,
            'removed_at' => now(),
            'removed_by' => (int) $request->user()->id,
            'removal_notes' => filled($data['removal_notes'] ?? null) ? trim($data['removal_notes']) : null,
        ]);

        $owner = $report->user_id ? User::query()->find((int) $report->user_id) : null;
        if ($owner) {
            $note = filled($report->removal_notes) ? ' Note: '.$report->removal_notes : '';
            $this->notifyOwner($owner, 'Stalled Vehicle Removed',
                "Your vehicle ({$report->plate_number}) was marked as removed from campus.".$note);
        }

        return back()->with('success', "{$report->plate_number} marked as removed from campus. The owner was notified.");
    }

    /**
     * There is no scheduler on the server, so owners are told the grace period ended the next time this page loads.
     *
     * @param  \Illuminate\Support\Collection<int, StalledVehicleReport>  $reports
     */
    private function notifyGraceEnded($reports): void
    {
        foreach ($reports as $report) {
            if ($report->grace_notified_at || $report->stage() === 'grace' || ! $report->user_id) {
                continue;
            }

            $owner = User::query()->find((int) $report->user_id);
            if ($owner) {
                $this->notifyOwner($owner, 'Stalled Vehicle: Tow Required',
                    'The '.StalledVehicleReport::GRACE_HOURS."-hour grace period for your vehicle ({$report->plate_number}) at {$report->location} has ended. "
                    .'It must be towed off campus by '.ph_datetime($report->towDeadline(), 'M j, g:i A').'.');
            }

            $report->update(['grace_notified_at' => now()]);
        }
    }

    private function notifyOwner(User $owner, string $title, string $message): void
    {
        Notification::query()->create([
            'user_id' => (int) $owner->id,
            'sender_id' => auth()->id(),
            'title' => $title,
            'message' => $message,
            'type' => 'Parking',
            'is_read' => false,
            'created_at' => now(),
        ]);

        Cache::forget('layout.unread_notifications.'.$owner->id.'.'.(int) $owner->user_role_id);
    }

    private function routePrefix(Request $request): string
    {
        return $request->routeIs('admin.*') ? 'admin' : 'guard';
    }
}
