<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\NavigationService;
use App\Services\RfidAccessService;
use App\Support\SearchHelper;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RfidController extends Controller
{
    public function index(Request $request): View
    {
        $tab = $request->query('tab', 'all');
        $allowedTabs = [
            'all',
            User::GATE_ACCESS_PENDING,
            User::GATE_ACCESS_GRANTED,
            User::GATE_ACCESS_DENIED,
            User::GATE_ACCESS_LEGACY,
        ];

        if (! in_array($tab, $allowedTabs, true)) {
            $tab = 'all';
        }

        $search = trim((string) $request->query('search', ''));
        $query = User::query()
            ->with(['department', 'role', 'vehicleType'])
            ->whereIn('user_role_id', [
                NavigationService::ROLE_STUDENT,
                NavigationService::ROLE_STAFF,
            ]);

        if ($tab === User::GATE_ACCESS_PENDING) {
            $query->where(function ($q) {
                $q->whereNull('Gate_access')
                    ->orWhere('Gate_access', '')
                    ->orWhere('Gate_access', User::GATE_ACCESS_PENDING);
            });
        } elseif ($tab === User::GATE_ACCESS_GRANTED) {
            $query->whereIn('Gate_access', [User::GATE_ACCESS_GRANTED, User::GATE_ACCESS_LEGACY]);
        } elseif ($tab === User::GATE_ACCESS_DENIED) {
            $query->where('Gate_access', User::GATE_ACCESS_DENIED);
        } elseif ($tab === User::GATE_ACCESS_LEGACY) {
            $query->where('Gate_access', User::GATE_ACCESS_LEGACY);
        }
        // tab === 'all' → no Gate_access filter

        if ($search !== '') {
            $term = SearchHelper::escapeLike($search);
            $query->where(function ($q) use ($term) {
                $q->where('fullname', 'like', "%{$term}%")
                    ->orWhere('name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%")
                    ->orWhere('id_number', 'like', "%{$term}%")
                    ->orWhere('plate_number', 'like', "%{$term}%")
                    ->orWhere('phone_number', 'like', "%{$term}%")
                    ->orWhere('rfid_uid', 'like', "%{$term}%");
            });
        }

        $eligible = User::query()->whereIn('user_role_id', [
            NavigationService::ROLE_STUDENT,
            NavigationService::ROLE_STAFF,
        ]);

        $tabCounts = [
            User::GATE_ACCESS_PENDING => (clone $eligible)
                ->where(function ($q) {
                    $q->whereNull('Gate_access')
                        ->orWhere('Gate_access', '')
                        ->orWhere('Gate_access', User::GATE_ACCESS_PENDING);
                })
                ->count(),
            User::GATE_ACCESS_GRANTED => (clone $eligible)
                ->whereIn('Gate_access', [User::GATE_ACCESS_GRANTED, User::GATE_ACCESS_LEGACY])
                ->count(),
            User::GATE_ACCESS_DENIED => (clone $eligible)
                ->where('Gate_access', User::GATE_ACCESS_DENIED)
                ->count(),
        ];

        return view('admin.rfid-assignment', [
            'currentTab' => $tab,
            'users' => $query->orderByDesc('id')->paginate(12)->withQueryString(),
            'search' => $search,
            'tabCounts' => $tabCounts,
            'stats' => [
                'total' => (clone $eligible)->count(),
                'pending' => $tabCounts[User::GATE_ACCESS_PENDING],
                'assigned' => $tabCounts[User::GATE_ACCESS_GRANTED],
            ],
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

        if (strlen($uid) < 4) {
            return redirect()
                ->route('admin.rfid', ['tab' => User::GATE_ACCESS_PENDING])
                ->withInput()
                ->with('error', 'Enter a valid RFID UID containing at least four hexadecimal characters.');
        }

        if (! in_array((int) $user->user_role_id, [NavigationService::ROLE_STUDENT, NavigationService::ROLE_STAFF], true)) {
            return redirect()
                ->route('admin.rfid', ['tab' => User::GATE_ACCESS_PENDING])
                ->with('error', 'RFID assignment only applies to student and staff accounts.');
        }

        if ($user->isLocked()) {
            return redirect()
                ->route('admin.rfid', ['tab' => User::GATE_ACCESS_PENDING])
                ->with('error', 'Cannot approve RFID access because this account is locked.');
        }

        if ($user->status === User::STATUS_DENIED) {
            return redirect()
                ->route('admin.rfid', ['tab' => User::GATE_ACCESS_PENDING])
                ->with('error', 'Cannot approve RFID for a denied registration. Re-approve the registration first.');
        }

        $taken = User::query()
            ->where('rfid_uid', $uid)
            ->where('id', '!=', $user->id)
            ->exists();

        if ($taken) {
            return redirect()
                ->route('admin.rfid', ['tab' => User::GATE_ACCESS_PENDING])
                ->withInput()
                ->with('error', 'That RFID UID is already assigned to another user.');
        }

        // Gate access only; portal status stays Pending until registration approve,
        // or remains Granted if already approved. Never revive Denied/Locked.
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
                ->route('admin.rfid', ['tab' => User::GATE_ACCESS_PENDING])
                ->with('error', 'RFID approval could not be saved. Please try again.');
        }

        return redirect()
            ->route('admin.rfid', ['tab' => User::GATE_ACCESS_GRANTED])
            ->with('success', "RFID {$uid} approved and linked to {$savedUser->displayName()}.");
    }

    public function update(Request $request, RfidAccessService $rfid): RedirectResponse
    {
        $allowedTabs = [
            'all',
            User::GATE_ACCESS_PENDING,
            User::GATE_ACCESS_GRANTED,
            User::GATE_ACCESS_DENIED,
            User::GATE_ACCESS_LEGACY,
        ];

        $validated = $request->validate([
            'user_id' => ['required', 'integer', Rule::exists(User::class, 'id')],
            'action' => ['required', 'in:grant,deny,assign_uid'],
            'rfid_uid' => ['nullable', 'string', 'max:64'],
            'tab' => ['nullable', 'string', Rule::in($allowedTabs)],
        ]);

        $user = User::query()->findOrFail($validated['user_id']);
        $tab = $validated['tab'] ?? 'all';

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

            $taken = User::query()
                ->where('rfid_uid', $uid)
                ->where('id', '!=', $user->id)
                ->exists();

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
                ->route('admin.rfid', ['tab' => User::GATE_ACCESS_GRANTED])
                ->with('success', "RFID {$uid} approved and linked to {$user->displayName()}.");
        }

        if ($validated['action'] === 'deny') {
            $user->update(['Gate_access' => User::GATE_ACCESS_DENIED]);

            return redirect()
                ->route('admin.rfid', ['tab' => $tab])
                ->with('success', "Gate access denied for {$user->displayName()}.");
        }

        return redirect()->route('admin.rfid', ['tab' => $tab]);
    }
}
