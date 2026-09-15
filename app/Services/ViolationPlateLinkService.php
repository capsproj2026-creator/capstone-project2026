<?php

namespace App\Services;

use App\Mail\VehicleViolationMail;
use App\Models\Notification;
use App\Models\User;
use App\Models\ViolationLog;
use App\Support\PlateLookup;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Attach orphan (unregistered-plate) citations to an owner when their plate is registered,
 * then email / notify them.
 */
class ViolationPlateLinkService
{
    /**
     * Claim pending violation logs whose plate matches this user and notify once.
     *
     * @return int Number of newly linked (and notified) citations
     */
    public function claimPendingForUser(User $user): int
    {
        $plates = $this->platesForUser($user);
        if ($plates === []) {
            return 0;
        }

        $normalizedKeys = array_values(array_unique(array_filter(array_map(
            static fn (string $p): string => PlateLookup::normalize($p),
            $plates
        ))));

        if ($normalizedKeys === []) {
            return 0;
        }

        $pending = ViolationLog::query()
            ->where(function ($q) {
                $q->whereNull('user_id')
                    ->orWhere('user_id', 0)
                    ->orWhere('user_id', '');
            })
            ->orderBy('created_at')
            ->get();

        $claimed = 0;
        $settings = app(SystemSettingService::class);
        $sendNotifications = $settings->bool('send_violation_notifications', true);

        foreach ($pending as $log) {
            $logKey = trim((string) ($log->plate_key ?? ''));
            if ($logKey === '') {
                $logKey = PlateLookup::normalize((string) ($log->plate_number ?? ''));
            }

            if ($logKey === '' || ! in_array($logKey, $normalizedKeys, true)) {
                continue;
            }

            $alreadyNotified = filled($log->owner_notified_at);

            $log->update([
                'user_id' => $user->id,
                'violator_name' => $user->fullname,
                'id_number' => $user->id_number,
                'user_type' => in_array((int) $user->user_role_id, [3, 4], true)
                    ? ($user->roleName() ?: 'Other')
                    : 'Other',
                'plate_key' => $logKey,
            ]);

            $claimed++;

            if ($alreadyNotified || ! $sendNotifications) {
                continue;
            }

            try {
                $this->notifyOwner($user, $log->fresh() ?? $log);
                $log->update(['owner_notified_at' => now()]);
            } catch (Throwable $e) {
                report($e);
            }
        }

        if ($claimed > 0) {
            app(ViolationEnforcementService::class)->syncStrikesFromLogs($user->fresh() ?? $user);
        }

        return $claimed;
    }

    /**
     * @return list<string>
     */
    private function platesForUser(User $user): array
    {
        $plates = [];

        $primary = trim((string) ($user->plate_number ?? ''));
        if ($primary !== '' && ! in_array(strtoupper($primary), ['N/A', 'NA', 'NONE'], true)) {
            $plates[] = $primary;
        }

        try {
            $vehicles = app(UserVehicleService::class)->listFor($user);
            foreach ($vehicles as $row) {
                $p = trim((string) ($row->plate_number ?? ''));
                if ($p !== '') {
                    $plates[] = $p;
                }
            }
        } catch (Throwable) {
            //
        }

        return $plates;
    }

    private function notifyOwner(User $user, ViolationLog $log): void
    {
        $typeLabel = $log->typeLabel() ?: (string) ($log->violation_type ?? 'Violation');
        $title = 'Violation Recorded: '.$typeLabel;
        $strikes = (int) ($user->fresh()?->strike_count ?? $user->strike_count ?? 0);
        $message = "Your vehicle ({$log->plate_number}) has a campus citation on record. Total strikes: {$strikes}/".User::MAX_STRIKES.'.';

        Notification::query()->create([
            'user_id' => $user->id,
            'sender_id' => $log->guard_id ?: null,
            'title' => $title,
            'message' => $message,
            'type' => 'Violation',
            'violation_log_id' => (string) $log->getKey(),
            'is_read' => false,
            'created_at' => now(),
        ]);

        $email = trim((string) ($user->email ?? ''));
        if ($email === '' || str_ends_with(strtolower($email), '.invalid')) {
            return;
        }

        Mail::to($email)->send(new VehicleViolationMail(
            plateNumber: (string) $log->plate_number,
            violationType: $typeLabel,
            description: filled($log->description) ? trim((string) $log->description) : null,
            occurredAt: $log->created_at,
            location: 'Campus',
            reportedBy: 'Campus Security',
            evidencePaths: $log->evidencePaths(),
            remarks: filled($log->description) ? trim((string) $log->description) : null,
            strikeCount: $strikes,
        ));
    }
}
