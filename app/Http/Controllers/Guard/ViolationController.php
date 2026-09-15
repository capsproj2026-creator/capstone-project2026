<?php

namespace App\Http\Controllers\Guard;

use App\Http\Controllers\Controller;
use App\Mail\VehicleViolationMail;
use App\Models\Notification;
use App\Models\User;
use App\Models\ViolationLog;
use App\Models\ViolationType;
use App\Notifications\AccountLockedNotification;
use App\Services\ViolationEnforcementService;
use App\Support\PlateLookup;
use App\Support\TrafficViolations;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ViolationController extends Controller
{
    public function index(Request $request): View
    {
        TrafficViolations::syncToDatabase();

        $registeredPlates = User::query()
            ->whereNotNull('plate_number')
            ->whereNotIn('plate_number', ['N/A', 'n/a', ''])
            ->orderBy('plate_number')
            ->get(['id', 'plate_number', 'fullname']);

        return view('guard.violations', [
            'logs' => ViolationLog::query()->orderByDesc('created_at')->paginate(25),
            'violationTypes' => ViolationType::query()
                ->where('status', 'Active')
                ->orderBy('id')
                ->get(['violation_name', 'description']),
            'registeredPlates' => $registeredPlates,
            'success' => $request->boolean('success'),
            'error' => $request->query('error'),
        ]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        TrafficViolations::syncToDatabase();

        $activeTypes = array_values(array_unique(array_merge(
            TrafficViolations::names(),
            ViolationType::query()
                ->where('status', 'Active')
                ->orderBy('id')
                ->pluck('violation_name')
                ->all()
        )));

        $validated = $request->validate([
            'plate_number' => ['required', 'string', 'max:32'],
            'violation_types' => ['required', 'array', 'min:1', 'max:4'],
            'violation_types.*' => ['required', 'string', 'max:255', Rule::in($activeTypes)],
            'description' => ['nullable', 'string', 'max:1000'],
            'evidence_photo' => ['nullable', 'image', 'max:5120'],
            'evidence_photos' => ['nullable', 'array', 'max:5'],
            'evidence_photos.*' => ['image', 'max:5120'],
        ]);

        // Exact selected types only — no extras, no duplicates, preserve selection order.
        $types = [];
        foreach ($validated['violation_types'] as $rawType) {
            $name = trim((string) $rawType);
            if ($name === '' || ! in_array($name, $activeTypes, true)) {
                continue;
            }
            if (! in_array($name, $types, true)) {
                $types[] = $name;
            }
        }

        if ($types === []) {
            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'message' => 'Select at least one valid violation type.'], 422);
            }

            return redirect()->route('guard.violations', ['error' => 'invalid_types']);
        }

        $typeLabel = TrafficViolations::displayLabel($types);
        $citationCount = count($types);

        $displayPlate = strtoupper(trim($validated['plate_number']));
        $plateKey = PlateLookup::normalize($displayPlate);

        if ($plateKey === '' || strlen($plateKey) < 2) {
            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'message' => 'Enter a valid plate number.'], 422);
            }

            return redirect()->route('guard.violations', ['error' => 'invalid_plate']);
        }

        $user = PlateLookup::findUser($displayPlate);

        $guardId = auth()->id();
        $title = $citationCount === 1
            ? 'Violation Recorded: '.$typeLabel
            : "{$citationCount} Violations Recorded: ".$typeLabel;

        $evidencePaths = [];
        if ($request->hasFile('evidence_photos')) {
            foreach ($request->file('evidence_photos') as $file) {
                if ($file) {
                    $evidencePaths[] = $file->store('violation-evidence', 'private');
                }
            }
        }
        if ($evidencePaths === [] && $request->hasFile('evidence_photo')) {
            $evidencePaths[] = $request->file('evidence_photo')->store('violation-evidence', 'private');
        }

        $isRegistered = (bool) $user;
        $occurredAt = now();

        // One ViolationLog per selected type so strike / citation counts match selections.
        $logs = [];
        foreach ($types as $singleType) {
            $logs[] = ViolationLog::query()->create([
                'user_id' => $user?->id,
                'violator_name' => $user?->fullname ?: 'Unregistered Vehicle',
                'id_number' => $user?->id_number,
                'user_type' => $user
                    ? (in_array((int) $user->user_role_id, [3, 4], true) ? ($user->roleName() ?: 'Other') : 'Other')
                    : 'Unregistered',
                'plate_number' => $displayPlate,
                'plate_key' => $plateKey,
                'violation_type' => $singleType,
                'violation_types' => [$singleType],
                'description' => $validated['description'] ?? null,
                'evidence_photo' => $evidencePaths[0] ?? null,
                'evidence_photos' => $evidencePaths !== [] ? $evidencePaths : null,
                'guard_id' => (string) $guardId,
                'status' => 'Active',
                'owner_notified_at' => null,
                'created_at' => $occurredAt,
            ]);
        }

        $primaryLog = $logs[0];

        $locked = false;
        $newStrikes = 0;
        $settings = app(\App\Services\SystemSettingService::class);
        $autoLock = $settings->bool('auto_lock_on_3rd_violation', true);
        $sendNotifications = $settings->bool('send_violation_notifications', true);

        if ($user) {
            $newStrikes = app(ViolationEnforcementService::class)->syncStrikesFromLogs($user);
            $user->refresh();

            $message = $citationCount === 1
                ? "Your vehicle ({$displayPlate}) has been cited for {$typeLabel}. Total strikes: {$newStrikes}/".User::MAX_STRIKES.'.'
                : "Your vehicle ({$displayPlate}) has been cited for {$citationCount} violations ({$typeLabel}). Total strikes: {$newStrikes}/".User::MAX_STRIKES.'.';

            $sanction = \App\Support\ViolationSanctionPresenter::labelForStrike($newStrikes);
            if ($sanction) {
                $message .= ' '.$sanction.'.';
            }

            $locked = $autoLock && $newStrikes >= User::MAX_STRIKES;
            if ($locked) {
                $message .= ' Your account has been permanently locked.';
            }

            if ($sendNotifications) {
                Notification::query()->create([
                    'user_id' => $user->id,
                    'sender_id' => $guardId,
                    'title' => $title,
                    'message' => $message,
                    'type' => 'Violation',
                    'violation_log_id' => (string) $primaryLog->getKey(),
                    'is_read' => false,
                    'created_at' => now(),
                ]);

                try {
                    $this->sendViolationMail($user, $primaryLog, $typeLabel, $validated['description'] ?? null);
                    foreach ($logs as $createdLog) {
                        $createdLog->update(['owner_notified_at' => now()]);
                    }

                    if ($locked) {
                        $user->notify(new AccountLockedNotification($newStrikes));
                    }
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        }

        $loggedWord = $citationCount === 1 ? 'violation' : 'violations';
        $successMessage = $isRegistered
            ? ("{$citationCount} {$loggedWord} logged successfully.".($locked ? ' Account locked (3/3 strikes).' : ''))
            : "{$citationCount} {$loggedWord} logged for unregistered plate {$displayPlate}. The owner will be emailed automatically when this plate is registered in the system.";

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => $successMessage,
                'locked' => $locked,
                'unregistered' => ! $isRegistered,
                'count' => $citationCount,
                'log_id' => (string) $primaryLog->getKey(),
                'log_ids' => array_map(static fn (ViolationLog $log) => (string) $log->getKey(), $logs),
            ]);
        }

        return redirect()->route('guard.violations', [
            'success' => 1,
            'count' => $citationCount,
            'locked' => $locked ? 1 : 0,
            'unregistered' => $isRegistered ? 0 : 1,
        ]);
    }

    private function sendViolationMail(
        User $user,
        ViolationLog $log,
        string $violationType,
        ?string $description = null
    ): void {
        $email = trim((string) ($user->email ?? ''));
        if ($email === '' || str_ends_with(strtolower($email), '.invalid')) {
            return;
        }

        $guardName = auth()->user()?->fullname;

        Mail::to($email)->send(new VehicleViolationMail(
            plateNumber: (string) $log->plate_number,
            violationType: $violationType,
            description: filled($description) ? trim($description) : null,
            occurredAt: $log->created_at,
            location: 'Campus',
            reportedBy: $guardName ?: 'Campus Security',
            evidencePaths: $log->evidencePaths(),
            remarks: filled($description) ? trim((string) $description) : null,
            strikeCount: (int) ($user->strike_count ?? 0),
        ));
    }

    public function evidence(string $id, int $index = 0): \Symfony\Component\HttpFoundation\StreamedResponse|\Illuminate\Http\Response
    {
        $log = \App\Support\ViolationEvidence::findAuthorized($id);

        return \App\Support\PrivateEvidence::response(\App\Support\ViolationEvidence::pathAt($log, $index));
    }
}
