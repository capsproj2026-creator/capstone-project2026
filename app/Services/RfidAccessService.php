<?php

namespace App\Services;

use App\Events\GateScanProcessed;
use App\Models\GateLog;
use App\Models\ParkingArea;
use App\Models\ParkingSlot;
use App\Models\User;
use App\Models\Visitor;
use App\Models\VisitorRfidCard;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class RfidAccessService
{
    public const STATUS_GRANTED = 'Access Granted';

    public const STATUS_DENIED = 'Access Denied';

    public const STATUS_ALREADY_INSIDE = 'Already Inside';

    public const STATUS_ALREADY_OUTSIDE = 'Already Outside';

    public const STATUS_CARD_NOT_REGISTERED = 'Card Not Registered';

    /**
     * Process an RFID tap from an ESP32 gate reader.
     *
     * @return array{
     *     status: string,
     *     code: string,
     *     granted: bool,
     *     action: string|null,
     *     gate_id: string,
     *     message: string,
     *     user: array<string, mixed>|null,
     *     log_id: mixed,
     *     open_shared_boom: bool
     * }
     */
    public function process(string $uid, string $gateId, string $direction): array
    {
        $uid = $this->normalizeUid($uid);
        $gateId = trim($gateId);
        $direction = $this->normalizeDirection($direction);

        if ($this->isEmergencyUid($uid)) {
            return $this->processEmergencyCard($uid, $gateId, $direction);
        }

        $user = User::query()
            ->with(['role', 'vehicleType'])
            ->where('rfid_uid', $uid)
            ->first();

        if ($user) {
            if ($user->isRemedialDeclined() && app(RemedialRfidService::class)->canUseGate($user)) {
                return $this->processRemedialUser($user, $uid, $gateId, $direction);
            }

            if ($user->isTemporaryAccount()) {
                return $this->processTemporaryUser($user, $uid, $gateId, $direction);
            }

            return $this->processUser($user, $uid, $gateId, $direction);
        }

        $card = VisitorRfidCard::query()
            ->where('rfid_uid', $uid)
            ->first();

        if ($card) {
            return $this->processVisitorCard($card, $uid, $gateId, $direction);
        }

        return $this->processUnknownCard($uid, $gateId, $direction);
    }

    /**
     * Guard typed a plate we already matched to this user. Applies the same
     * entry and exit rules as a card tap without looking the card up again.
     *
     * @return array<string, mixed>
     */
    public function grantResolvedUser(User $user, string $gateId, string $direction): array
    {
        $uid = $this->normalizeUid((string) ($user->rfid_uid ?? ''));
        if ($uid === '') {
            $uid = 'MANUALPLATE';
        }

        $gateId = trim($gateId);
        $direction = $this->normalizeDirection($direction);

        if ($user->isRemedialDeclined() && app(RemedialRfidService::class)->canUseGate($user)) {
            return $this->processRemedialUser($user, $uid, $gateId, $direction);
        }

        if ($user->isTemporaryAccount()) {
            return $this->processTemporaryUser($user, $uid, $gateId, $direction);
        }

        return $this->processUser($user, $uid, $gateId, $direction);
    }

    private function isEmergencyUid(string $uid): bool
    {
        $raw = (string) config('services.rfid.emergency_uids', '');
        if ($raw === '' || $uid === '') {
            return false;
        }

        $listed = preg_split('/[\s,]+/', $raw) ?: [];

        return in_array($uid, array_map(fn ($item) => $this->normalizeUid((string) $item), $listed), true);
    }

    /**
     * Spare card: open the boom without changing who is inside or outside.
     * Entry tap opens the local servo. Exit tap asks the Entry board to open it.
     */
    private function processEmergencyCard(string $uid, string $gateId, string $direction): array
    {
        $hardware = app(GateHardwareService::class);
        $shared = $hardware->normalizeGateId((string) config('services.rfid.shared_boom_gate_id', 'GATE-IN-1')) ?? 'GATE-IN-1';
        $from = $hardware->normalizeGateId($gateId) ?? strtoupper(trim($gateId));
        $holdMs = (int) config('services.rfid.emergency_hold_ms', 20000);
        $reason = 'Emergency card — boom open, no entry or exit recorded';
        $queued = false;

        if ($from !== $shared) {
            $queued = $hardware->queueOpenCommand($shared, $reason, $holdMs);
        }

        $log = GateLog::query()->create([
            'user_id' => null,
            'visitor_id' => null,
            'action' => GateHardwareService::ACTION_OVERRIDE,
            'gate_id' => $from !== '' ? $from : $gateId,
            'rfid_uid' => $uid,
            'result' => self::STATUS_GRANTED,
            'reason' => $reason,
            'timestamp' => now(),
        ]);

        $this->broadcastGrantedScan($log, $uid);

        return $this->response(
            self::STATUS_GRANTED,
            'emergency_open',
            true,
            GateHardwareService::ACTION_OVERRIDE,
            $from !== '' ? $from : $gateId,
            $reason,
            null,
            $log->id,
            $queued,
            $holdMs
        );
    }

    private function processUnknownCard(string $uid, string $gateId, string $direction): array
    {
        // Unregistered RFID: log with null user fields — never create placeholder users.
        $log = $this->logDeniedAttempt(
            null,
            null,
            $uid,
            $gateId,
            $direction,
            self::STATUS_CARD_NOT_REGISTERED,
            'RFID card is not registered in the system.'
        );

        return $this->response(
            self::STATUS_CARD_NOT_REGISTERED,
            'card_not_registered',
            false,
            $direction,
            $gateId,
            'RFID card is not registered in the system.',
            null,
            $log->id
        );
    }

    private function processRemedialUser(User $user, string $uid, string $gateId, string $direction): array
    {
        $remedial = app(RemedialRfidService::class);

        if ($user->remedialAccessExpired()) {
            $reason = RemedialRfidService::EXPIRED_MESSAGE;
            $log = $this->logDeniedAttempt($user->fresh() ?? $user, null, $uid, $gateId, $direction, self::STATUS_DENIED, $reason);

            return $this->response(self::STATUS_DENIED, 'access_denied', false, $direction, $gateId, $reason, $this->userPayload($user), $log->id);
        }

        if (! $remedial->canUseGate($user)) {
            $reason = RemedialRfidService::GATE_DISABLED_MESSAGE;
            $log = $this->logDeniedAttempt($user, null, $uid, $gateId, $direction, self::STATUS_DENIED, $reason);

            return $this->response(self::STATUS_DENIED, 'access_denied', false, $direction, $gateId, $reason, $this->userPayload($user), $log->id);
        }

        if ($user->isLocked()) {
            $reason = $user->loginBlockedReason() ?? 'Account is not active.';
            $log = $this->logDeniedAttempt($user, null, $uid, $gateId, $direction, self::STATUS_DENIED, $reason);

            return $this->response(self::STATUS_DENIED, 'access_denied', false, $direction, $gateId, $reason, $this->userPayload($user), $log->id);
        }

        $lastAction = $this->lastActionForUser($user);

        if ($direction === 'Entry' && $lastAction === 'Entry') {
            $log = $this->logDeniedAttempt($user, null, $uid, $gateId, $direction, self::STATUS_ALREADY_INSIDE, 'Vehicle is already inside campus.');

            return $this->response(self::STATUS_ALREADY_INSIDE, 'already_inside', false, $direction, $gateId, 'Vehicle is already inside campus.', $this->userPayload($user), $log->id);
        }

        if ($remedial->oneEntryOnly() && $direction === 'Entry' && $lastAction === 'Exit') {
            $reason = RemedialRfidService::ONE_TIME_MESSAGE;
            $log = $this->logDeniedAttempt($user, null, $uid, $gateId, $direction, self::STATUS_DENIED, $reason);

            return $this->response(self::STATUS_DENIED, 'access_denied', false, $direction, $gateId, $reason, $this->userPayload($user), $log->id);
        }

        if ($direction === 'Exit' && ($lastAction === null || $lastAction === 'Exit')) {
            $reason = RemedialRfidService::EXIT_ONLY_AFTER_ENTRY;
            $log = $this->logDeniedAttempt($user, null, $uid, $gateId, $direction, self::STATUS_ALREADY_OUTSIDE, $reason);

            return $this->response(self::STATUS_ALREADY_OUTSIDE, 'already_outside', false, $direction, $gateId, $reason, $this->userPayload($user), $log->id);
        }

        $reason = RemedialRfidService::GRANT_REASON;
        $log = GateLog::query()->create([
            'user_id' => $user->id,
            'visitor_id' => null,
            'action' => $direction,
            'gate_id' => $gateId,
            'rfid_uid' => $uid,
            'result' => self::STATUS_GRANTED,
            'reason' => $reason,
            'timestamp' => now(),
        ]);

        $this->syncUserParkingOccupancy($user, $direction);
        $this->broadcastGrantedScan($log, $uid);
        $sharedQueued = app(GateHardwareService::class)->notifySharedBoomAfterGrant($gateId, $direction);

        return $this->response(
            self::STATUS_GRANTED,
            'access_granted',
            true,
            $direction,
            $gateId,
            $reason,
            $this->userPayload($user),
            $log->id,
            $sharedQueued
        );
    }

    private function processTemporaryUser(User $user, string $uid, string $gateId, string $direction): array
    {
        $temps = app(TemporaryRfidService::class);

        if ($user->temporaryAccessExpired()) {
            $temps->expireAndUnbind($user);
            $reason = TemporaryRfidService::EXPIRED_MESSAGE;
            $log = $this->logDeniedAttempt($user->fresh() ?? $user, null, $uid, $gateId, $direction, self::STATUS_DENIED, $reason);

            return $this->response(self::STATUS_DENIED, 'access_denied', false, $direction, $gateId, $reason, $this->userPayload($user), $log->id);
        }

        $temps->clearPlaceholderEmail($user);

        if ($user->isLocked()) {
            $reason = $user->loginBlockedReason() ?? 'Account is not active.';
            $log = $this->logDeniedAttempt($user, null, $uid, $gateId, $direction, self::STATUS_DENIED, $reason);

            return $this->response(self::STATUS_DENIED, 'access_denied', false, $direction, $gateId, $reason, $this->userPayload($user), $log->id);
        }

        $lastAction = $this->lastActionForUser($user);

        if ($direction === 'Entry' && $lastAction === 'Entry') {
            $log = $this->logDeniedAttempt($user, null, $uid, $gateId, $direction, self::STATUS_ALREADY_INSIDE, 'Vehicle is already inside campus.');

            return $this->response(self::STATUS_ALREADY_INSIDE, 'already_inside', false, $direction, $gateId, 'Vehicle is already inside campus.', $this->userPayload($user), $log->id);
        }

        if ($direction === 'Entry' && $lastAction === 'Exit') {
            $reason = TemporaryRfidService::ONE_TIME_MESSAGE;
            $log = $this->logDeniedAttempt($user, null, $uid, $gateId, $direction, self::STATUS_DENIED, $reason);

            return $this->response(self::STATUS_DENIED, 'access_denied', false, $direction, $gateId, $reason, $this->userPayload($user), $log->id);
        }

        if ($direction === 'Exit' && ($lastAction === null || $lastAction === 'Exit')) {
            $reason = TemporaryRfidService::EXIT_ONLY_AFTER_ENTRY;
            $log = $this->logDeniedAttempt($user, null, $uid, $gateId, $direction, self::STATUS_ALREADY_OUTSIDE, $reason);

            return $this->response(self::STATUS_ALREADY_OUTSIDE, 'already_outside', false, $direction, $gateId, $reason, $this->userPayload($user), $log->id);
        }

        $reason = $temps->grantReason();
        $log = GateLog::query()->create([
            'user_id' => $user->id,
            'visitor_id' => null,
            'action' => $direction,
            'gate_id' => $gateId,
            'rfid_uid' => $uid,
            'result' => self::STATUS_GRANTED,
            'reason' => $reason,
            'timestamp' => now(),
        ]);

        $this->syncUserParkingOccupancy($user, $direction);
        $this->broadcastGrantedScan($log, $uid);
        $sharedQueued = app(GateHardwareService::class)->notifySharedBoomAfterGrant($gateId, $direction);

        return $this->response(
            self::STATUS_GRANTED,
            'access_granted',
            true,
            $direction,
            $gateId,
            $reason,
            $this->userPayload($user),
            $log->id,
            $sharedQueued
        );
    }

    private function processUser(User $user, string $uid, string $gateId, string $direction): array
    {
        if (! $this->isAccountActive($user)) {
            $reason = $user->loginBlockedReason() ?? 'Account is not active.';
            $log = $this->logDeniedAttempt($user, null, $uid, $gateId, $direction, self::STATUS_DENIED, $reason);

            return $this->response(
                self::STATUS_DENIED,
                'access_denied',
                false,
                $direction,
                $gateId,
                $reason,
                $this->userPayload($user),
                $log->id
            );
        }

        if (! $user->isCampusVehicleOwner()) {
            $log = $this->logDeniedAttempt($user, null, $uid, $gateId, $direction, self::STATUS_DENIED, 'Only vehicle owners may use the RFID gate.');

            return $this->response(self::STATUS_DENIED, 'access_denied', false, $direction, $gateId, 'Only vehicle owners may use the RFID gate.', $this->userPayload($user), $log->id);
        }

        if (! $this->hasRegisteredVehicle($user)) {
            $log = $this->logDeniedAttempt($user, null, $uid, $gateId, $direction, self::STATUS_DENIED, 'No registered vehicle found for this account.');

            return $this->response(self::STATUS_DENIED, 'access_denied', false, $direction, $gateId, 'No registered vehicle found for this account.', $this->userPayload($user), $log->id);
        }

        $sanctionReason = $direction === 'Entry' ? $user->parkingSanctionReason() : null;
        if ($sanctionReason !== null) {
            $log = $this->logDeniedAttempt($user, null, $uid, $gateId, $direction, self::STATUS_DENIED, $sanctionReason);

            return $this->response(self::STATUS_DENIED, 'access_denied', false, $direction, $gateId, $sanctionReason, $this->userPayload($user), $log->id);
        }

        if (! $user->hasGateAccess()) {
            $log = $this->logDeniedAttempt($user, null, $uid, $gateId, $direction, self::STATUS_DENIED, 'Gate / RFID access has not been granted.');

            return $this->response(self::STATUS_DENIED, 'access_denied', false, $direction, $gateId, 'Gate / RFID access has not been granted.', $this->userPayload($user), $log->id);
        }

        $lastAction = $this->lastActionForUser($user);

        if ($direction === 'Entry' && $lastAction === 'Entry') {
            $log = $this->logDeniedAttempt($user, null, $uid, $gateId, $direction, self::STATUS_ALREADY_INSIDE, 'Vehicle is already inside campus.');

            return $this->response(self::STATUS_ALREADY_INSIDE, 'already_inside', false, $direction, $gateId, 'Vehicle is already inside campus.', $this->userPayload($user), $log->id);
        }

        if ($direction === 'Exit' && ($lastAction === null || $lastAction === 'Exit')
            && ! (bool) config('services.rfid.allow_exit_without_entry')) {
            $log = $this->logDeniedAttempt($user, null, $uid, $gateId, $direction, self::STATUS_ALREADY_OUTSIDE, 'Vehicle is already outside campus.');

            return $this->response(self::STATUS_ALREADY_OUTSIDE, 'already_outside', false, $direction, $gateId, 'Vehicle is already outside campus.', $this->userPayload($user), $log->id);
        }

        $log = GateLog::query()->create([
            'user_id' => $user->id,
            'visitor_id' => null,
            'action' => $direction,
            'gate_id' => $gateId,
            'rfid_uid' => $uid,
            'result' => self::STATUS_GRANTED,
            'timestamp' => now(),
        ]);

        $this->syncUserParkingOccupancy($user, $direction);
        $this->broadcastGrantedScan($log, $uid);
        $sharedQueued = app(GateHardwareService::class)->notifySharedBoomAfterGrant($gateId, $direction);

        return $this->response(
            self::STATUS_GRANTED,
            'access_granted',
            true,
            $direction,
            $gateId,
            "{$direction} granted for {$user->fullname}.",
            $this->userPayload($user),
            $log->id,
            $sharedQueued
        );
    }

    private function processVisitorCard(VisitorRfidCard $card, string $uid, string $gateId, string $direction): array
    {
        $visitorService = app(VisitorService::class);

        $visitor = $card->visitor_id
            ? Visitor::query()->with('vehicleType')->find($card->visitor_id)
            : null;

        if (! $visitor || ! $visitor->isActive()) {
            $log = $this->logDeniedAttempt(null, $visitor?->id, $uid, $gateId, $direction, self::STATUS_DENIED, 'Temporary RFID is not linked to an active visitor.');

            return $this->response(self::STATUS_DENIED, 'access_denied', false, $direction, $gateId, 'Temporary RFID is not linked to an active visitor.', $this->visitorPayload($visitor), $log->id);
        }

        if (! $card->isUsableForGate()) {
            $reason = $card->status === VisitorRfidCard::STATUS_EXPIRED
                ? 'Temporary RFID has expired.'
                : 'Temporary RFID is not active for gate access.';
            $log = $this->logDeniedAttempt(null, $visitor->id, $uid, $gateId, $direction, self::STATUS_DENIED, $reason);

            return $this->response(self::STATUS_DENIED, 'access_denied', false, $direction, $gateId, $reason, $this->visitorPayload($visitor), $log->id);
        }

        if ($visitor->isExpiredByTime() || ($card->expires_at && $card->expires_at->lte(now()))) {
            $visitorService->expireVisitor($visitor, notify: true);
            $log = $this->logDeniedAttempt(null, $visitor->id, $uid, $gateId, $direction, self::STATUS_DENIED, 'Visitor visit / temporary RFID has expired.');

            return $this->response(self::STATUS_DENIED, 'access_denied', false, $direction, $gateId, 'Visitor visit / temporary RFID has expired.', $this->visitorPayload($visitor->fresh()), $log->id);
        }

        if ($visitor->status === Visitor::STATUS_EXPIRED) {
            $log = $this->logDeniedAttempt(null, $visitor->id, $uid, $gateId, $direction, self::STATUS_DENIED, 'Visitor visit has expired.');

            return $this->response(self::STATUS_DENIED, 'access_denied', false, $direction, $gateId, 'Visitor visit has expired.', $this->visitorPayload($visitor), $log->id);
        }

        $plate = trim((string) ($visitor->plate_number ?? ''));
        if ($plate === '' || in_array(strtoupper($plate), ['N/A', 'NA', 'NONE'], true)) {
            $log = $this->logDeniedAttempt(null, $visitor->id, $uid, $gateId, $direction, self::STATUS_DENIED, 'No vehicle plate on this visitor registration.');

            return $this->response(self::STATUS_DENIED, 'access_denied', false, $direction, $gateId, 'No vehicle plate on this visitor registration.', $this->visitorPayload($visitor), $log->id);
        }

        $lastAction = $this->lastActionForVisitor($visitor);

        if ($direction === 'Entry' && $lastAction === 'Entry') {
            $log = $this->logDeniedAttempt(null, $visitor->id, $uid, $gateId, $direction, self::STATUS_ALREADY_INSIDE, 'Visitor vehicle is already inside campus.');

            return $this->response(self::STATUS_ALREADY_INSIDE, 'already_inside', false, $direction, $gateId, 'Visitor vehicle is already inside campus.', $this->visitorPayload($visitor), $log->id);
        }

        if ($direction === 'Exit' && ($lastAction === null || $lastAction === 'Exit')
            && ! (bool) config('services.rfid.allow_exit_without_entry')) {
            $log = $this->logDeniedAttempt(null, $visitor->id, $uid, $gateId, $direction, self::STATUS_ALREADY_OUTSIDE, 'Visitor vehicle is already outside campus.');

            return $this->response(self::STATUS_ALREADY_OUTSIDE, 'already_outside', false, $direction, $gateId, 'Visitor vehicle is already outside campus.', $this->visitorPayload($visitor), $log->id);
        }

        $log = GateLog::query()->create([
            'user_id' => null,
            'visitor_id' => $visitor->id,
            'action' => $direction,
            'gate_id' => $gateId,
            'rfid_uid' => $uid,
            'result' => self::STATUS_GRANTED,
            'timestamp' => now(),
        ]);

        if ($direction === 'Entry') {
            $visitorService->markInside($visitor);
            $this->syncVisitorParkingOccupancy($visitor, 'Entry');
        } else {
            $this->syncVisitorParkingOccupancy($visitor, 'Exit');
            $visitorService->recordCampusExit($visitor);
        }

        $this->broadcastGrantedScan($log->fresh(['visitor', 'user']) ?? $log, $uid);
        $sharedQueued = app(GateHardwareService::class)->notifySharedBoomAfterGrant($gateId, $direction);

        return $this->response(
            self::STATUS_GRANTED,
            'access_granted',
            true,
            $direction,
            $gateId,
            "{$direction} granted for visitor {$visitor->displayName()}.",
            $this->visitorPayload($visitor->fresh(['vehicleType'])),
            $log->id,
            $sharedQueued
        );
    }

    public function normalizeUid(string $uid): string
    {
        $uid = preg_replace('/^\s*UID\s*:\s*/i', '', $uid) ?? $uid;
        $uid = strtoupper(trim($uid));
        $uid = preg_replace('/[^A-F0-9]/', '', $uid) ?? '';

        return $uid;
    }

    public const ENROLL_CACHE_KEY = 'rfid:enroll:latest';

    /**
     * Remember a desk-reader tap for the assignment screens.
     * This is not a gate transaction: no access log and no live-monitor event.
     *
     * @return array{ok: bool, granted: bool, code: string, status: string, message: string, uid?: string}
     */
    public function rememberEnrollmentTap(string $uid): array
    {
        $uid = $this->normalizeUid($uid);
        if (strlen($uid) < 6) {
            return [
                'ok' => false,
                'granted' => false,
                'code' => 'invalid_uid',
                'status' => 'UID not captured',
                'message' => 'The card UID must contain at least six hexadecimal characters.',
            ];
        }

        Cache::put(self::ENROLL_CACHE_KEY, [
            'uid' => $uid,
            'scanned_at' => now()->toIso8601String(),
            'id' => (string) Str::uuid(),
        ], now()->addMinutes(3));

        return [
            'ok' => true,
            'granted' => false,
            'code' => 'uid_captured',
            'status' => 'UID captured',
            'message' => 'UID saved for assignment. This scan is not shown on the live gate monitor.',
            'uid' => $uid,
        ];
    }

    /**
     * Latest desk-reader tap, for RFID assignment screens.
     * Pass ?since=ISO8601 so only taps after the screen started listening are returned.
     *
     * @return array{ok: bool, uid: string|null, scanned_at?: string|null, log_id?: string}
     */
    public function latestEnrollmentTap(Request $request): array
    {
        $tap = Cache::get(self::ENROLL_CACHE_KEY);
        if (! is_array($tap)) {
            return ['ok' => true, 'uid' => null];
        }

        $uid = $this->normalizeUid((string) ($tap['uid'] ?? ''));
        $scannedAt = trim((string) ($tap['scanned_at'] ?? ''));
        if (strlen($uid) < 6 || $scannedAt === '') {
            return ['ok' => true, 'uid' => null];
        }

        $sinceRaw = trim((string) $request->query('since', ''));
        if ($sinceRaw !== '') {
            try {
                $since = Carbon::parse($sinceRaw)->subSeconds(2);
                if (Carbon::parse($scannedAt)->lt($since)) {
                    return ['ok' => true, 'uid' => null];
                }
            } catch (\Throwable) {
                // Ignore a bad timestamp and return the current tap.
            }
        }

        return [
            'ok' => true,
            'uid' => $uid,
            'scanned_at' => $scannedAt,
            'log_id' => (string) ($tap['id'] ?? $uid),
        ];
    }

    /**
     * Latest unknown gate tap, for RFID assignment screens.
     * Pass ?since=ISO8601 so only taps after the screen started listening are returned.
     *
     * @return array{ok: bool, uid: string|null, gate_id?: string|null, action?: string|null, scanned_at?: string|null, log_id?: string}
     */
    public function latestUnknownTap(Request $request): array
    {
        $query = GateLog::query()
            ->whereNull('user_id')
            ->whereNull('visitor_id')
            ->whereNotNull('rfid_uid')
            ->where('rfid_uid', '!=', '')
            ->where(function ($q) {
                $q->where('result', self::STATUS_CARD_NOT_REGISTERED)
                    ->orWhere('result', self::STATUS_DENIED);
            });

        $sinceRaw = trim((string) $request->query('since', ''));
        $appliedSince = false;
        if ($sinceRaw !== '') {
            try {
                $since = Carbon::parse($sinceRaw)->subSeconds(2);
                $query->where('timestamp', '>=', $since);
                $appliedSince = true;
            } catch (\Throwable) {
                $appliedSince = false;
            }
        }
        if (! $appliedSince) {
            $query->where('timestamp', '>=', now()->subMinutes(2));
        }

        $log = $query->orderByDesc('timestamp')->first();
        if (! $log) {
            return ['ok' => true, 'uid' => null];
        }

        $uid = $this->normalizeUid((string) $log->rfid_uid);
        if (strlen($uid) < 6) {
            return ['ok' => true, 'uid' => null];
        }

        return [
            'ok' => true,
            'uid' => $uid,
            'gate_id' => $log->gate_id,
            'action' => $log->action,
            'scanned_at' => $log->timestamp?->toIso8601String(),
            'log_id' => (string) $log->getKey(),
        ];
    }

    private function normalizeDirection(string $direction): string
    {
        $direction = Str::title(strtolower(trim($direction)));

        return in_array($direction, ['Entry', 'Exit'], true) ? $direction : 'Entry';
    }

    private function isAccountActive(User $user): bool
    {
        return $user->canAccessPortal()
            && $user->hasVerifiedEmail()
            && ! $user->isLocked();
    }

    private function hasRegisteredVehicle(User $user): bool
    {
        $plate = trim((string) ($user->plate_number ?? ''));

        return $user->vehicle_id
            && $plate !== ''
            && ! in_array(strtoupper($plate), ['N/A', 'NA', 'NONE'], true);
    }

    private function lastActionForUser(User $user): ?string
    {
        $last = GateLog::query()
            ->where('user_id', $user->id)
            ->whereIn('action', ['Entry', 'Exit'])
            ->where(function ($q) {
                $q->whereNull('result')
                    ->orWhere('result', self::STATUS_GRANTED);
            })
            ->orderByDesc('timestamp')
            ->value('action');

        return $last ? (string) $last : null;
    }

    private function lastActionForVisitor(Visitor $visitor): ?string
    {
        $last = GateLog::query()
            ->where('visitor_id', $visitor->id)
            ->whereIn('action', ['Entry', 'Exit'])
            ->where(function ($q) {
                $q->whereNull('result')
                    ->orWhere('result', self::STATUS_GRANTED);
            })
            ->orderByDesc('timestamp')
            ->value('action');

        return $last ? (string) $last : null;
    }

    private function syncUserParkingOccupancy(User $user, string $action): void
    {
        $slot = ParkingSlot::query()->where('parked_user_id', $user->id)->first();

        if ($action === 'Entry' && $slot) {
            $slot->update(['status' => 'Occupied']);
        }

        if ($action === 'Exit' && $slot) {
            $slot->update(['status' => 'Available', 'parked_user_id' => null]);
        }
    }

    private function syncVisitorParkingOccupancy(Visitor $visitor, string $action): void
    {
        $slot = ParkingSlot::query()->where('parked_visitor_id', $visitor->id)->first();

        if ($action === 'Entry') {
            if ($slot) {
                $slot->update(['status' => 'Occupied']);

                return;
            }

            $visitorAreas = ParkingArea::query()->orderBy('id')->get()
                ->filter(fn (ParkingArea $area) => in_array('Visitor', $area->getAllowedRoles(), true)
                    || in_array('Visitors', $area->getAllowedRoles(), true));

            foreach ($visitorAreas as $area) {
                $available = ParkingSlot::sortNaturally(
                    ParkingSlot::query()
                        ->where('area_id', $area->id)
                        ->where('status', 'Available')
                        ->whereNull('parked_user_id')
                        ->where(function ($q) {
                            $q->whereNull('parked_visitor_id')->orWhere('parked_visitor_id', 0);
                        })
                        ->get()
                )->first();

                if ($available) {
                    $available->update([
                        'status' => 'Occupied',
                        'parked_visitor_id' => $visitor->id,
                        'parked_user_id' => null,
                    ]);

                    return;
                }
            }

            return;
        }

        if ($action === 'Exit' && $slot) {
            $slot->update([
                'status' => 'Available',
                'parked_visitor_id' => null,
            ]);
        }
    }

    private function logDeniedAttempt(?User $user, ?int $visitorId, string $uid, string $gateId, string $direction, string $result, string $reason = ''): GateLog
    {
        $log = GateLog::query()->create([
            'user_id' => $user?->id,
            'visitor_id' => $visitorId,
            'action' => $direction,
            'gate_id' => $gateId,
            'rfid_uid' => $uid,
            'result' => $result,
            'reason' => $reason,
            'timestamp' => now(),
        ]);

        // Hold-on-reader bounce after a grant often creates Already Inside/Outside within
        // ~1–2s. Still log it for audit, but do not push a second profile flash to the
        // live gate monitor (ESP32 firmware also suppresses the second POST).
        if ($this->shouldBroadcastDeniedScan($uid, $result)) {
            GateScanProcessed::dispatchFromLog($log);
        }

        return $log;
    }

    private function broadcastGrantedScan(GateLog $log, string $uid): void
    {
        if ($uid !== '') {
            Cache::put($this->recentGrantCacheKey($uid), 1, now()->addSeconds(3));
        }

        GateScanProcessed::dispatchFromLog($log);
    }

    private function shouldBroadcastDeniedScan(string $uid, string $result): bool
    {
        if (! in_array($result, [self::STATUS_ALREADY_INSIDE, self::STATUS_ALREADY_OUTSIDE], true)) {
            return true;
        }

        if ($uid === '') {
            return true;
        }

        // Prefer the short-lived cache flag set on grant (fast + reliable across Mongo
        // timestamp quirks). Fall back to a recent grant row if the cache was cleared.
        if (Cache::has($this->recentGrantCacheKey($uid))) {
            return false;
        }

        return ! GateLog::query()
            ->where('rfid_uid', $uid)
            ->where('result', self::STATUS_GRANTED)
            ->where('timestamp', '>=', now()->subSeconds(3))
            ->exists();
    }

    private function recentGrantCacheKey(string $uid): string
    {
        return 'rfid:recent_grant:'.strtoupper(trim($uid));
    }

    /**
     * @param  array<string, mixed>|null  $user
     * @return array<string, mixed>
     */
    private function response(
        string $status,
        string $code,
        bool $granted,
        ?string $action,
        string $gateId,
        string $message,
        ?array $user,
        mixed $logId = null,
        bool $openSharedBoom = false,
        ?int $holdMs = null
    ): array {
        $shared = strtoupper(trim((string) config('services.rfid.shared_boom_gate_id', 'GATE-IN-1')));

        return [
            'status' => $status,
            'code' => $code,
            'granted' => $granted,
            'action' => $action,
            'gate_id' => $gateId,
            'message' => $message,
            'user' => $user,
            'log_id' => $logId,
            'open_shared_boom' => $openSharedBoom,
            'shared_boom_gate_id' => $shared !== '' ? $shared : 'GATE-IN-1',
            'hold_ms' => $holdMs,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function userPayload(User $user): array
    {
        $temps = app(TemporaryRfidService::class);
        $isTemporary = $user->isTemporaryAccount();
        $isRemedial = $user->isRemedialDeclined();

        return [
            'id' => $user->id,
            'name' => $user->name,
            'id_number' => $user->id_number,
            'plate_number' => $user->plate_number,
            'role' => $isTemporary ? $user->gateRoleLabel() : $user->roleName(),
            'is_visitor' => false,
            'is_temporary' => $isTemporary,
            'is_remedial' => $isRemedial,
            'temporary_expires_at' => $isTemporary ? $user->temporary_expires_at?->toIso8601String() : null,
            'remedial_expires_at' => $isRemedial ? $user->remedial_expires_at?->toIso8601String() : null,
            'register_url' => $isTemporary ? $temps->registrationUrl($user) : null,
            'fix_documents_url' => $isRemedial ? route('user.registration.fix') : null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function visitorPayload(?Visitor $visitor): ?array
    {
        if (! $visitor) {
            return null;
        }

        return [
            'id' => $visitor->id,
            'name' => $visitor->displayName(),
            'id_number' => null,
            'plate_number' => $visitor->plate_number,
            'role' => 'Visitor',
            'purpose' => $visitor->purpose,
            'is_visitor' => true,
            'status' => $visitor->status,
        ];
    }
}
