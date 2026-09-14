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

        $activeTypes = ViolationType::query()
            ->where('status', 'Active')
            ->orderBy('id')
            ->pluck('violation_name')
            ->all();

        $validated = $request->validate([
            'plate_number' => ['required', 'string', 'max:32'],
            'violation_types' => ['required', 'array', 'min:1'],
            'violation_types.*' => ['required', 'string', 'max:255', Rule::in($activeTypes)],
            'description' => ['nullable', 'string', 'max:1000'],
            'evidence_photo' => ['nullable', 'image', 'max:5120'],
            'evidence_photos' => ['nullable', 'array', 'max:5'],
            'evidence_photos.*' => ['image', 'max:5120'],
        ]);

        $types = array_values(array_unique(array_map(
            static fn ($t) => trim((string) $t),
            $validated['violation_types']
        )));
        $typeLabel = TrafficViolations::displayLabel($types);

        $plate = strtoupper(trim($validated['plate_number']));
        $rawPlate = trim($validated['plate_number']);

        $user = User::query()
            ->with('role')
            ->whereIn('plate_number', array_unique([$plate, $rawPlate]))
            ->first();

        if (! $user) {
            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'error' => 'plate_not_found', 'message' => 'Plate number not found in registered vehicles.'], 422);
            }

            return redirect()->route('guard.violations', ['error' => 'plate_not_found']);
        }

        $guardId = auth()->id();
        $title = 'Violation Recorded: '.$typeLabel;

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

        $log = ViolationLog::query()->create([
            'user_id' => $user->id,
            'violator_name' => $user->fullname,
            'id_number' => $user->id_number,
            'user_type' => in_array((int) $user->user_role_id, [3, 4], true)
                ? ($user->roleName())
                : 'Other',
            'plate_number' => $validated['plate_number'],
            'violation_type' => $typeLabel,
            'violation_types' => $types,
            'description' => $validated['description'] ?? null,
            'evidence_photo' => $evidencePaths[0] ?? null,
            'evidence_photos' => $evidencePaths !== [] ? $evidencePaths : null,
            'guard_id' => (string) $guardId,
            'status' => 'Active',
            'created_at' => now(),
        ]);

        $newStrikes = app(ViolationEnforcementService::class)->syncStrikesFromLogs($user);
        $user->refresh();

        $settings = app(\App\Services\SystemSettingService::class);
        $autoLock = $settings->bool('auto_lock_on_3rd_violation', true);
        $sendNotifications = $settings->bool('send_violation_notifications', true);

        $message = "Your vehicle ({$validated['plate_number']}) has been cited. Total strikes: {$newStrikes}/".User::MAX_STRIKES.'.';

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
                'violation_log_id' => (string) $log->getKey(),
                'is_read' => false,
                'created_at' => now(),
            ]);

            try {
                $this->sendViolationMail($user, $log, $typeLabel, $validated['description'] ?? null);

                if ($locked) {
                    $user->notify(new AccountLockedNotification($newStrikes));
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => 'Violation logged successfully.'.($locked ? ' Account locked (3/3 strikes).' : ''),
                'locked' => $locked,
                'log_id' => (string) $log->getKey(),
            ]);
        }

        return redirect()->route('guard.violations', [
            'success' => 1,
            'locked' => $locked ? 1 : 0,
        ]);
    }

    private function sendViolationMail(
        User $user,
        ViolationLog $log,
        string $violationType,
        ?string $description = null
    ): void {
        $guardName = auth()->user()?->fullname;

        Mail::to($user->email)->send(new VehicleViolationMail(
            plateNumber: (string) $log->plate_number,
            violationType: $violationType,
            description: filled($description) ? trim($description) : null,
            occurredAt: $log->created_at,
            location: filled($log->area_name) ? (string) $log->area_name : (filled($log->camera_id) ? (string) $log->camera_id : 'Campus'),
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
