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
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ViolationController extends Controller
{
    public function index(Request $request): View
    {
        $registeredPlates = User::query()
            ->whereNotNull('plate_number')
            ->whereNotIn('plate_number', ['N/A', 'n/a', ''])
            ->orderBy('plate_number')
            ->get(['id', 'plate_number', 'fullname']);

        $settings = app(\App\Services\SystemSettingService::class);

        return view('guard.violations', [
            'logs' => ViolationLog::query()->orderByDesc('created_at')->paginate(25),
            'violationTypes' => ViolationType::query()->where('status', 'Active')->orderBy('id')->pluck('violation_name'),
            'registeredPlates' => $registeredPlates,
            'requirePhotoEvidence' => $settings->bool('require_photo_evidence', false),
            'success' => $request->boolean('success'),
            'error' => $request->query('error'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $settings = app(\App\Services\SystemSettingService::class);
        $requirePhoto = $settings->bool('require_photo_evidence', false);

        $activeTypes = ViolationType::query()
            ->where('status', 'Active')
            ->orderBy('id')
            ->pluck('violation_name')
            ->all();

        $rules = [
            'plate_number' => ['required', 'string', 'max:32'],
            'violation_type' => ['required', 'string', 'max:255', Rule::in($activeTypes)],
            'description' => ['nullable', 'string', 'max:1000'],
        ];

        if ($requirePhoto) {
            $rules['evidence_photo'] = ['required', 'image', 'max:5120'];
        } else {
            $rules['evidence_photo'] = ['nullable', 'image', 'max:5120'];
        }

        $validated = $request->validate($rules);

        $plate = strtoupper(trim($validated['plate_number']));
        $rawPlate = trim($validated['plate_number']);

        $user = User::query()
            ->with('role')
            ->whereIn('plate_number', array_unique([$plate, $rawPlate]))
            ->first();

        if (! $user) {
            return redirect()->route('guard.violations', ['error' => 'plate_not_found']);
        }

        $guardId = auth()->id();
        $title = "Violation Recorded: {$validated['violation_type']}";

        $evidencePath = null;
        if ($request->hasFile('evidence_photo')) {
            $evidencePath = $request->file('evidence_photo')->store('violation-evidence', 'private');
        }

        ViolationLog::query()->create([
            'user_id' => $user->id,
            'violator_name' => $user->fullname,
            'id_number' => $user->id_number,
            'user_type' => in_array((int) $user->user_role_id, [3, 4], true)
                ? ($user->roleName())
                : 'Other',
            'plate_number' => $validated['plate_number'],
            'violation_type' => $validated['violation_type'],
            'description' => $validated['description'],
            'evidence_photo' => $evidencePath,
            'guard_id' => (string) $guardId,
            'status' => 'Active',
            'created_at' => now(),
        ]);

        $newStrikes = app(ViolationEnforcementService::class)->syncStrikesFromLogs($user);
        $user->refresh();

        $autoLock = $settings->bool('auto_lock_on_3rd_violation', true);
        $sendNotifications = $settings->bool('send_violation_notifications', true);

        $message = "Your vehicle ({$validated['plate_number']}) has been cited. Total strikes: {$newStrikes}/".User::MAX_STRIKES.'.';

        if ($autoLock && $newStrikes >= User::MAX_STRIKES) {
            $message .= ' Your account has been permanently locked.';
        }

        if ($sendNotifications) {
            Notification::query()->create([
                'user_id' => $user->id,
                'sender_id' => $guardId,
                'title' => $title,
                'message' => $message,
                'type' => 'Violation',
                'is_read' => false,
                'created_at' => now(),
            ]);

            try {
                $this->logViolation(
                    $user,
                    $plate,
                    $validated['violation_type'],
                    $validated['description'] ?? null
                );

                if ($autoLock && $newStrikes >= User::MAX_STRIKES) {
                    $user->notify(new AccountLockedNotification($newStrikes));
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return redirect()->route('guard.violations', [
            'success' => 1,
            'locked' => ($autoLock && $newStrikes >= User::MAX_STRIKES) ? 1 : 0,
        ]);
    }

    /**
     * Sends the violation alert email to the vehicle owner.
     *
     * VehicleViolationMail exposes $plateNumber, $violationType, and $description to the Blade view.
     * Laravel's Mail facade hands the message to the SMTP driver configured in .env (Brevo).
     */
    private function logViolation(
        User $user,
        string $plateNumber,
        string $violationType,
        ?string $description = null
    ): void {
        Mail::to($user->email)->send(new VehicleViolationMail(
            plateNumber: $plateNumber,
            violationType: $violationType,
            description: filled($description) ? trim($description) : null,
        ));
    }

    public function evidence(string $id): \Symfony\Component\HttpFoundation\StreamedResponse|\Illuminate\Http\Response
    {
        $log = ViolationLog::query()->findOrFail($id);

        return \App\Support\PrivateEvidence::response($log->evidence_photo ?? null);
    }
}
