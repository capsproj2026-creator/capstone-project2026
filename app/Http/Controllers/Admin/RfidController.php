<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GateLog;
use App\Models\User;
use App\Services\NavigationService;
use App\Services\RfidAccessService;
use App\Services\TemporaryRfidService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RfidController extends Controller
{
    public const TAB_ALL = 'all';

    public const TAB_PENDING = 'pending';

    public const TAB_ASSIGNED = 'assigned';

    public const TAB_LOCKED = 'locked';

    public const TAB_DENIED = 'denied';

    /**
     * @return list<string>
     */
    private function allowedTabs(): array
    {
        return [
            self::TAB_ALL,
            self::TAB_PENDING,
            self::TAB_ASSIGNED,
            self::TAB_LOCKED,
            self::TAB_DENIED,
            User::GATE_ACCESS_PENDING,
            User::GATE_ACCESS_GRANTED,
            User::GATE_ACCESS_DENIED,
            User::GATE_ACCESS_LEGACY,
        ];
    }

    private function normalizeTab(string $tab): string
    {
        return match ($tab) {
            self::TAB_ALL, 'All', 'all' => self::TAB_ALL,
            self::TAB_PENDING, User::GATE_ACCESS_PENDING => self::TAB_PENDING,
            self::TAB_ASSIGNED, User::GATE_ACCESS_GRANTED, User::GATE_ACCESS_LEGACY => self::TAB_ASSIGNED,
            self::TAB_LOCKED, User::STATUS_LOCKED, 'Suspended' => self::TAB_LOCKED,
            self::TAB_DENIED, User::GATE_ACCESS_DENIED, User::STATUS_DENIED => self::TAB_DENIED,
            default => self::TAB_ALL,
        };
    }

    private function eligibleQuery(): Builder
    {
        return User::query()
            ->whereIn('user_role_id', [
                NavigationService::ROLE_STUDENT,
                NavigationService::ROLE_STAFF,
            ])
            ->where(function (Builder $q) {
                $q->whereNull('account_type')
                    ->orWhere('account_type', '!=', TemporaryRfidService::ACCOUNT_TEMPORARY);
            });
    }

    /**
     * Card filter semantics (also used for statistics):
     * - pending = no RFID UID
     * - assigned = has RFID UID
     * - locked = locked / suspended account
     * - denied = denied registration or denied gate access
     */
    private function applyCardFilter(Builder $query, string $tab): Builder
    {
        return match ($tab) {
            self::TAB_PENDING => $query->where(function ($q) {
                $q->whereNull('rfid_uid')->orWhere('rfid_uid', '');
            }),
            self::TAB_ASSIGNED => $query
                ->whereNotNull('rfid_uid')
                ->where('rfid_uid', '!=', ''),
            self::TAB_LOCKED => $query->where(function ($q) {
                $q->where('status', User::STATUS_LOCKED)
                    ->orWhere('status', 'Suspended')
                    ->orWhere('strike_count', '>=', User::MAX_STRIKES);
            }),
            self::TAB_DENIED => $query->where(function ($q) {
                $q->where('Gate_access', User::GATE_ACCESS_DENIED)
                    ->orWhere('status', User::STATUS_DENIED);
            }),
            default => $query,
        };
    }

    public function index(Request $request): View
    {
        $filter = $this->normalizeTab((string) $request->query('tab', self::TAB_ALL));
        $search = trim((string) $request->query('search', ''));

        // Load full eligible list so stats cards can filter instantly in the browser.
        $users = $this->eligibleQuery()
            ->with(['department', 'role', 'vehicleType'])
            ->orderByDesc('id')
            ->get();

        $eligible = $this->eligibleQuery();
        $stats = [
            'total' => (clone $eligible)->count(),
            'pending' => $this->applyCardFilter(clone $eligible, self::TAB_PENDING)->count(),
            'assigned' => $this->applyCardFilter(clone $eligible, self::TAB_ASSIGNED)->count(),
            'locked' => $this->applyCardFilter(clone $eligible, self::TAB_LOCKED)->count(),
            'denied' => $this->applyCardFilter(clone $eligible, self::TAB_DENIED)->count(),
        ];

        return view('admin.rfid-assignment', [
            'currentFilter' => $filter,
            'users' => $users,
            'search' => $search,
            'stats' => $stats,
            'latestUnregisteredUrl' => route('admin.rfid.latest-unregistered'),
        ]);
    }

    /**
     * Latest gate tap for an unknown RFID — used to auto-fill Assign RFID modal.
     * Pass ?since=ISO8601 so only taps AFTER the modal opened are returned
     * (avoids filling an old unauthorized scan the admin never just tapped).
     */
    public function latestUnregistered(Request $request, RfidAccessService $rfid): JsonResponse
    {
        $query = GateLog::query()
            ->whereNull('user_id')
            ->whereNull('visitor_id')
            ->whereNotNull('rfid_uid')
            ->where('rfid_uid', '!=', '')
            ->where(function ($q) {
                $q->where('result', RfidAccessService::STATUS_CARD_NOT_REGISTERED)
                    ->orWhere('result', RfidAccessService::STATUS_DENIED);
            });

        $sinceRaw = trim((string) $request->query('since', ''));
        $appliedSince = false;
        if ($sinceRaw !== '') {
            try {
                $since = \Illuminate\Support\Carbon::parse($sinceRaw)->subSeconds(2);
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
            return response()->json(['ok' => true, 'uid' => null]);
        }

        $uid = $rfid->normalizeUid((string) $log->rfid_uid);
        if (strlen($uid) < 6) {
            return response()->json(['ok' => true, 'uid' => null]);
        }

        return response()->json([
            'ok' => true,
            'uid' => $uid,
            'gate_id' => $log->gate_id,
            'action' => $log->action,
            'scanned_at' => $log->timestamp?->toIso8601String(),
            'log_id' => (string) $log->getKey(),
        ]);
    }

    public function approve(Request $request, int $id, RfidAccessService $rfid): RedirectResponse
    {
        $validated = $request->validate([
            'rfid_uid' => ['required', 'string', 'max:64'],
        ], [
            'rfid_uid.required' => 'Enter the RFID UID before approving this request.',
        ]);

        $user = User::query()->findOrFail($id);
        $uid = $rfid->normalizeUid($validated['rfid_uid']);

        if ($user->isTemporaryAccount()) {
            return redirect()
                ->route('admin.rfid', ['tab' => self::TAB_PENDING])
                ->with('error', 'This unregistered student/faculty must complete vehicle registration before RFID can be approved.');
        }

        if (strlen($uid) < 4) {
            return redirect()
                ->route('admin.rfid', ['tab' => self::TAB_PENDING])
                ->withInput()
                ->with('error', 'Enter a valid RFID UID containing at least four hexadecimal characters.');
        }

        if (! in_array((int) $user->user_role_id, [NavigationService::ROLE_STUDENT, NavigationService::ROLE_STAFF], true)) {
            return redirect()
                ->route('admin.rfid', ['tab' => self::TAB_PENDING])
                ->with('error', 'RFID assignment only applies to student and staff accounts.');
        }

        if ($user->isLocked()) {
            return redirect()
                ->route('admin.rfid', ['tab' => self::TAB_LOCKED])
                ->with('error', 'Cannot approve RFID access because this account is locked.');
        }

        if ($user->status === User::STATUS_DENIED) {
            return redirect()
                ->route('admin.rfid', ['tab' => self::TAB_DENIED])
                ->with('error', 'Cannot approve RFID for a denied registration. Re-approve the registration first.');
        }

        $taken = $this->rfidUidTakenByAnother($uid, (int) $user->id);

        if ($taken) {
            return redirect()
                ->route('admin.rfid', ['tab' => self::TAB_PENDING])
                ->withInput()
                ->with('error', 'That RFID UID is already assigned to another user.');
        }

        $updates = [
            'rfid_uid' => $uid,
            'Gate_access' => User::GATE_ACCESS_GRANTED,
        ];
        if ($user->status === User::STATUS_PENDING || $user->status === User::STATUS_GRANTED) {
            $updates['status'] = User::STATUS_GRANTED;
        }

        $user->update($updates);

        $savedUser = User::query()->find($user->id);

        if (! $savedUser
            || $savedUser->rfid_uid !== $uid
            || ! $savedUser->hasGateAccess()) {
            return redirect()
                ->route('admin.rfid', ['tab' => self::TAB_PENDING])
                ->with('error', 'RFID approval could not be saved. Please try again.');
        }

        return redirect()
            ->route('admin.rfid', ['tab' => self::TAB_ASSIGNED])
            ->with('success', "RFID {$uid} approved and linked to {$savedUser->displayName()}.");
    }

    public function update(Request $request, RfidAccessService $rfid): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', Rule::exists(User::class, 'id')],
            'action' => ['required', 'in:grant,deny,assign_uid,restore'],
            'rfid_uid' => ['nullable', 'string', 'max:64'],
            'tab' => ['nullable', 'string', Rule::in($this->allowedTabs())],
        ]);

        $user = User::query()->findOrFail($validated['user_id']);
        $tab = $this->normalizeTab((string) ($validated['tab'] ?? self::TAB_ALL));

        if ($user->isTemporaryAccount()) {
            return redirect()
                ->route('admin.rfid', ['tab' => $tab])
                ->with('error', 'This unregistered student/faculty must complete vehicle registration before RFID can be updated.');
        }

        if (! in_array((int) $user->user_role_id, [NavigationService::ROLE_STUDENT, NavigationService::ROLE_STAFF], true)) {
            return redirect()
                ->route('admin.rfid', ['tab' => $tab])
                ->with('error', 'RFID assignment only applies to student and staff accounts.');
        }

        if (in_array($validated['action'], ['assign_uid', 'grant'], true)) {
            $uid = $rfid->normalizeUid((string) ($validated['rfid_uid'] ?? ''));

            if ($uid === '') {
                return redirect()
                    ->route('admin.rfid', ['tab' => $tab])
                    ->with('error', 'Enter and save a valid RFID UID before granting access.');
            }

            $taken = $this->rfidUidTakenByAnother($uid, (int) $user->id);

            if ($taken) {
                return redirect()
                    ->route('admin.rfid', ['tab' => $tab])
                    ->with('error', 'That RFID UID is already assigned to another user.');
            }

            if ($validated['action'] === 'assign_uid') {
                $user->update(['rfid_uid' => $uid]);

                return redirect()
                    ->route('admin.rfid', ['tab' => $tab])
                    ->with('success', "RFID UID {$uid} assigned to {$user->displayName()}.");
            }
        }

        if ($validated['action'] === 'grant') {
            if ($user->isLocked()) {
                return redirect()
                    ->route('admin.rfid', ['tab' => $tab])
                    ->with('error', 'Cannot grant access: this account is permanently locked due to 3 violations.');
            }

            if ($user->status === User::STATUS_DENIED) {
                return redirect()
                    ->route('admin.rfid', ['tab' => $tab])
                    ->with('error', 'Cannot grant RFID for a denied registration. Re-approve the registration first.');
            }

            $updates = [
                'rfid_uid' => $uid,
                'Gate_access' => User::GATE_ACCESS_GRANTED,
            ];
            if ($user->status === User::STATUS_PENDING || $user->status === User::STATUS_GRANTED) {
                $updates['status'] = User::STATUS_GRANTED;
            }

            $user->update($updates);

            return redirect()
                ->route('admin.rfid', ['tab' => self::TAB_ASSIGNED])
                ->with('success', "RFID {$uid} approved and linked to {$user->displayName()}.");
        }

        if ($validated['action'] === 'deny') {
            $user->update(['Gate_access' => User::GATE_ACCESS_DENIED]);

            return redirect()
                ->route('admin.rfid', ['tab' => $tab])
                ->with('success', "Gate access denied for {$user->displayName()}.");
        }

        if ($validated['action'] === 'restore') {
            if ($user->isLocked()) {
                return redirect()
                    ->route('admin.rfid', ['tab' => $tab])
                    ->with('error', 'Cannot restore access: this account is locked.');
            }

            if ($user->status === User::STATUS_DENIED) {
                return redirect()
                    ->route('admin.rfid', ['tab' => $tab])
                    ->with('error', 'Registration is denied. Re-approve the registration first, then restore gate access.');
            }

            $nextGate = filled($user->rfid_uid)
                ? User::GATE_ACCESS_GRANTED
                : User::GATE_ACCESS_PENDING;

            $user->update(['Gate_access' => $nextGate]);

            return redirect()
                ->route('admin.rfid', ['tab' => $tab])
                ->with('success', "Gate access restored for {$user->displayName()}.");
        }

        return redirect()->route('admin.rfid', ['tab' => $tab]);
    }

    private function rfidUidTakenByAnother(string $uid, int $ignoreUserId): bool
    {
        return User::query()
            ->where('id', '!=', $ignoreUserId)
            ->where(function ($q) use ($uid) {
                $q->where('rfid_uid', $uid)
                    ->orWhere(function ($inner) use ($uid) {
                        $inner->where('temp_rfid_uid', $uid)
                            ->where(function ($type) {
                                $type->whereNull('account_type')
                                    ->orWhere('account_type', '!=', TemporaryRfidService::ACCOUNT_TEMPORARY);
                            });
                    });
            })
            ->exists();
    }
}
