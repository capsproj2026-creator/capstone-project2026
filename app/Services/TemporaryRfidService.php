<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * One-time gate pass for a student or faculty member who has not registered yet.
 * Unknown Entry UIDs create a pending Student/Staff placeholder (not a visitor).
 * Visitors use VisitorRfidCard instead.
 */
class TemporaryRfidService
{
    public const ACCOUNT_TEMPORARY = 'temporary';

    public const ACCOUNT_FULL = 'full';

    public const GRANT_REASON = 'Unregistered student/faculty — complete vehicle registration within %d hours';

    public const EXPIRED_MESSAGE = 'Registration window expired. Complete student/faculty registration to enter campus.';

    public const LIMIT_MESSAGE = 'Unregistered student/faculty entry limit reached. Complete registration before entering campus.';

    public const ONE_TIME_MESSAGE = 'This unregistered pass was one-time only. Complete registration to re-enter.';

    public const EXIT_ONLY_AFTER_ENTRY = 'Exit is allowed after a temporary entry. This card is not registered for a new visit.';

    public const PLACEHOLDER_NAME = 'Unregistered Student / Faculty';

    public function enabled(): bool
    {
        return (bool) config('services.rfid.temp_access_enabled');
    }

    public function hours(): int
    {
        return max(1, (int) config('services.rfid.temp_access_hours', 5));
    }

    public function maxAccounts(): int
    {
        return max(1, (int) config('services.rfid.temp_access_max', 3));
    }

    public function identityKeyForUid(string $uid): string
    {
        return hash('sha256', 'rfid:'.$uid);
    }

    public function countForIdentity(string $key): int
    {
        return User::query()
            ->where('temp_identity_key', $key)
            ->where('account_type', self::ACCOUNT_TEMPORARY)
            ->count();
    }

    public function remainingForIdentity(string $key): int
    {
        return max(0, $this->maxAccounts() - $this->countForIdentity($key));
    }

    public function findByConversionToken(?string $token): ?User
    {
        $token = trim((string) $token);
        if ($token === '') {
            return null;
        }

        return User::query()
            ->where('temp_conversion_token', $token)
            ->where('account_type', self::ACCOUNT_TEMPORARY)
            ->first();
    }

    public function grantReason(): string
    {
        return sprintf(self::GRANT_REASON, $this->hours());
    }

    public function registrationUrl(?User $user): ?string
    {
        if (! $user) {
            return null;
        }

        $token = trim((string) ($user->temp_conversion_token ?? ''));
        if ($token === '') {
            return null;
        }

        return route('register', ['temp' => $token]);
    }

    /**
     * Unbind the RFID so the same card can request another temporary account (counts toward the max).
     */
    public function expireAndUnbind(User $user): void
    {
        if (! $user->isTemporaryAccount()) {
            return;
        }

        $user->update([
            'Gate_access' => User::GATE_ACCESS_DENIED,
            'rfid_uid' => null,
        ]);
    }

    public function clearPlaceholderEmail(User $user): void
    {
        if (! $user->isTemporaryAccount()) {
            return;
        }

        $email = strtolower(trim((string) ($user->email ?? '')));
        if ($email !== '' && ! str_ends_with($email, '.invalid')) {
            return;
        }

        if ($email === '' && $user->email === null) {
            return;
        }

        if (method_exists($user, 'unset')) {
            $user->unset('email');
        } else {
            $user->update(['email' => null]);
        }
        $user->email = null;
    }

    public function liveUid(User $user): ?string
    {
        $uid = trim((string) ($user->rfid_uid ?: $user->temp_rfid_uid ?: ''));

        return $uid !== '' ? $uid : null;
    }

    public function createForUid(string $uid): User
    {
        $key = $this->identityKeyForUid($uid);
        $existing = $this->countForIdentity($key);
        if ($existing >= $this->maxAccounts()) {
            throw new \RuntimeException(self::LIMIT_MESSAGE);
        }

        $sequence = $existing + 1;
        $short = strtoupper(substr(preg_replace('/[^A-F0-9]/', '', $uid) ?: $uid, -6));

        return User::query()->create([
            'id' => SequenceService::next('users'),
            'fullname' => self::PLACEHOLDER_NAME,
            'password' => Hash::make(Str::random(40)),
            'phone_number' => '',
            'user_role_id' => NavigationService::ROLE_STUDENT,
            'id_number' => 'TEMP'.$short.$sequence.Str::upper(Str::random(3)),
            'status' => User::STATUS_PENDING,
            'Gate_access' => User::GATE_ACCESS_GRANTED,
            'rfid_uid' => $uid,
            'temp_rfid_uid' => $uid,
            'strike_count' => 0,
            'account_type' => self::ACCOUNT_TEMPORARY,
            'temporary_expires_at' => now()->addHours($this->hours()),
            'temporary_sequence' => $sequence,
            'temp_identity_key' => $key,
            'temp_conversion_token' => Str::random(40),
            'email_verified_at' => null,
            'created_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function convertToFull(User $user, array $payload): User
    {
        $payload['account_type'] = self::ACCOUNT_FULL;
        $payload['temporary_expires_at'] = null;
        $payload['temp_conversion_token'] = null;
        $payload['status'] = User::STATUS_PENDING;
        $payload['Gate_access'] = User::GATE_ACCESS_PENDING;
        $payload['declined_at'] = null;
        $payload['registration_state'] = User::REGISTRATION_PENDING;
        $payload['decline_remarks'] = null;

        $roleId = (int) ($payload['user_role_id'] ?? $user->user_role_id ?? NavigationService::ROLE_STUDENT);
        if (! in_array($roleId, [NavigationService::ROLE_STUDENT, NavigationService::ROLE_STAFF], true)) {
            $roleId = NavigationService::ROLE_STUDENT;
        }
        $payload['user_role_id'] = $roleId;

        if (! array_key_exists('rfid_uid', $payload)) {
            $payload['rfid_uid'] = $this->claimUidForConversion($user);
        }

        $user->update($payload);

        return $user->fresh() ?? $user;
    }

    private function claimUidForConversion(User $user): ?string
    {
        $restoreUid = $this->liveUid($user);
        if ($restoreUid === null) {
            return $user->rfid_uid;
        }

        $holder = User::query()
            ->where('rfid_uid', $restoreUid)
            ->where('id', '!=', $user->id)
            ->first();

        if (! $holder) {
            return $restoreUid;
        }

        if ($holder->isTemporaryAccount()) {
            $holder->update(['rfid_uid' => null]);

            return $restoreUid;
        }

        return $user->rfid_uid;
    }
}
