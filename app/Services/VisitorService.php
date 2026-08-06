<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use App\Models\Visitor;
use App\Models\VisitorRfidCard;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class VisitorService
{
    public function __construct(private readonly RfidAccessService $rfid)
    {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function register(array $data, ?User $actor = null): Visitor
    {
        $visitor = Visitor::query()->create([
            'first_name' => trim((string) $data['first_name']),
            'last_name' => trim((string) $data['last_name']),
            'middle_name' => filled($data['middle_name'] ?? null) ? trim((string) $data['middle_name']) : null,
            'contact_number' => trim((string) $data['contact_number']),
            'email' => filled($data['email'] ?? null) ? trim((string) $data['email']) : null,
            'purpose' => trim((string) $data['purpose']),
            'office_to_visit' => trim((string) $data['office_to_visit']),
            'expected_exit_at' => Carbon::parse($data['expected_exit_at']),
            'plate_number' => strtoupper(trim((string) $data['plate_number'])),
            'vehicle_id' => (int) $data['vehicle_id'],
            'vehicle_color' => trim((string) $data['vehicle_color']),
            'status' => Visitor::STATUS_WAITING,
            'registered_by' => $actor?->id,
            'notes' => filled($data['notes'] ?? null) ? trim((string) $data['notes']) : null,
        ]);

        $uid = trim((string) ($data['rfid_uid'] ?? ''));
        if ($uid !== '') {
            $this->assignRfid($visitor, $uid, $actor);
            $visitor->refresh();
        }

        return $visitor;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Visitor $visitor, array $data): Visitor
    {
        if ($visitor->status === Visitor::STATUS_COMPLETED) {
            throw ValidationException::withMessages([
                'visitor' => 'Completed visits cannot be edited.',
            ]);
        }

        $visitor->update([
            'first_name' => trim((string) ($data['first_name'] ?? $visitor->first_name)),
            'last_name' => trim((string) ($data['last_name'] ?? $visitor->last_name)),
            'middle_name' => array_key_exists('middle_name', $data)
                ? (filled($data['middle_name']) ? trim((string) $data['middle_name']) : null)
                : $visitor->middle_name,
            'contact_number' => trim((string) ($data['contact_number'] ?? $visitor->contact_number)),
            'email' => array_key_exists('email', $data)
                ? (filled($data['email']) ? trim((string) $data['email']) : null)
                : $visitor->email,
            'purpose' => trim((string) ($data['purpose'] ?? $visitor->purpose)),
            'office_to_visit' => trim((string) ($data['office_to_visit'] ?? $visitor->office_to_visit)),
            'expected_exit_at' => isset($data['expected_exit_at'])
                ? Carbon::parse($data['expected_exit_at'])
                : $visitor->expected_exit_at,
            'plate_number' => strtoupper(trim((string) ($data['plate_number'] ?? $visitor->plate_number))),
            'vehicle_id' => (int) ($data['vehicle_id'] ?? $visitor->vehicle_id),
            'vehicle_color' => trim((string) ($data['vehicle_color'] ?? $visitor->vehicle_color)),
        ]);

        if ($visitor->rfidCard && $visitor->expected_exit_at) {
            $visitor->rfidCard->update(['expires_at' => $visitor->expected_exit_at]);
        }

        return $visitor->fresh(['vehicleType', 'rfidCard']) ?? $visitor;
    }

    public function assignRfid(Visitor $visitor, string $rawUid, ?User $actor = null): VisitorRfidCard
    {
        if (in_array($visitor->status, [Visitor::STATUS_COMPLETED], true)) {
            throw ValidationException::withMessages([
                'rfid_uid' => 'Cannot assign RFID to a completed visit.',
            ]);
        }

        $uid = $this->rfid->normalizeUid($rawUid);
        if (strlen($uid) < 4) {
            throw ValidationException::withMessages([
                'rfid_uid' => 'RFID UID must contain at least 4 hex characters.',
            ]);
        }

        if (User::query()->where('rfid_uid', $uid)->exists()) {
            throw ValidationException::withMessages([
                'rfid_uid' => 'This RFID UID is already assigned to a registered user.',
            ]);
        }

        $existing = VisitorRfidCard::query()->where('rfid_uid', $uid)->first();

        if ($existing && ! in_array($existing->status, [
            VisitorRfidCard::STATUS_AVAILABLE,
            VisitorRfidCard::STATUS_RETURNED,
        ], true) && (int) $existing->visitor_id !== (int) $visitor->id) {
            throw ValidationException::withMessages([
                'rfid_uid' => 'This temporary RFID is currently assigned to another visitor.',
            ]);
        }

        if ($visitor->visitor_rfid_card_id && (int) $visitor->visitor_rfid_card_id !== (int) ($existing?->id)) {
            $this->returnRfid($visitor, markCompleted: false, notify: false);
            $visitor->refresh();
        }

        $expiresAt = $visitor->expected_exit_at;

        if ($existing) {
            $existing->update([
                'status' => VisitorRfidCard::STATUS_ASSIGNED,
                'visitor_id' => $visitor->id,
                'assigned_at' => now(),
                'expires_at' => $expiresAt,
                'returned_at' => null,
            ]);
            $card = $existing->fresh() ?? $existing;
        } else {
            $card = VisitorRfidCard::query()->create([
                'rfid_uid' => $uid,
                'status' => VisitorRfidCard::STATUS_ASSIGNED,
                'visitor_id' => $visitor->id,
                'assigned_at' => now(),
                'expires_at' => $expiresAt,
                'returned_at' => null,
                'created_by' => $actor?->id,
            ]);
        }

        $visitor->update([
            'visitor_rfid_card_id' => $card->id,
            'rfid_uid' => $uid,
        ]);

        return $card;
    }

    public function returnRfid(Visitor $visitor, bool $markCompleted = false, bool $notify = true): void
    {
        $card = $visitor->rfidCard
            ?? ($visitor->visitor_rfid_card_id
                ? VisitorRfidCard::query()->find($visitor->visitor_rfid_card_id)
                : null);

        $usedUid = $visitor->rfid_uid ?: $card?->rfid_uid;

        if ($card) {
            $card->update([
                'status' => VisitorRfidCard::STATUS_AVAILABLE,
                'visitor_id' => null,
                'returned_at' => now(),
            ]);
        }

        $updates = [
            'visitor_rfid_card_id' => null,
        ];

        // Keep denormalized UID on completed visits for history; clear only when visit stays active.
        if ($markCompleted) {
            $updates['rfid_uid'] = $usedUid;
            if ($visitor->status !== Visitor::STATUS_COMPLETED) {
                $updates['status'] = Visitor::STATUS_COMPLETED;
                $updates['time_out'] = $visitor->time_out ?? now();
            }
        } else {
            $updates['rfid_uid'] = null;
        }

        $visitor->update($updates);

        if ($notify && $markCompleted) {
            $this->notifyStaff(
                'Visitor checked out',
                "{$visitor->displayName()} ({$visitor->plate_number}) has checked out. Temporary RFID returned.",
                $visitor
            );
        }
    }

    public function markInside(Visitor $visitor): void
    {
        $visitor->update([
            'status' => Visitor::STATUS_INSIDE,
            'time_in' => $visitor->time_in ?? now(),
        ]);

        if ($visitor->rfidCard) {
            $visitor->rfidCard->update(['status' => VisitorRfidCard::STATUS_ACTIVE]);
        }

        $this->notifyStaff(
            'Visitor checked in',
            "{$visitor->displayName()} ({$visitor->plate_number}) entered campus. Purpose: {$visitor->purpose}.",
            $visitor
        );
    }

    public function completeOnExit(Visitor $visitor): void
    {
        $usedUid = $visitor->rfid_uid;

        $visitor->update([
            'status' => Visitor::STATUS_COMPLETED,
            'time_out' => now(),
            'rfid_uid' => $usedUid,
        ]);

        $card = $visitor->rfidCard
            ?? ($visitor->visitor_rfid_card_id
                ? VisitorRfidCard::query()->find($visitor->visitor_rfid_card_id)
                : null);

        if ($card) {
            $card->update([
                'status' => VisitorRfidCard::STATUS_AVAILABLE,
                'visitor_id' => null,
                'returned_at' => now(),
            ]);
        }

        $visitor->update(['visitor_rfid_card_id' => null]);

        $this->notifyStaff(
            'Visitor checked out',
            "{$visitor->displayName()} ({$visitor->plate_number}) exited campus. Visit moved to history.",
            $visitor
        );
    }

    public function expireOverdue(): int
    {
        $count = 0;
        $now = now();

        Visitor::query()
            ->whereIn('status', [
                Visitor::STATUS_WAITING,
                Visitor::STATUS_INSIDE,
                Visitor::STATUS_OUTSIDE,
            ])
            ->where('expected_exit_at', '<=', $now)
            ->orderBy('id')
            ->get()
            ->each(function (Visitor $visitor) use (&$count) {
                $this->expireVisitor($visitor, notify: true);
                $count++;
            });

        return $count;
    }

    public function expireVisitor(Visitor $visitor, bool $notify = true): void
    {
        if ($visitor->status === Visitor::STATUS_COMPLETED) {
            return;
        }

        $visitor->update(['status' => Visitor::STATUS_EXPIRED]);

        if ($visitor->rfidCard) {
            $visitor->rfidCard->update(['status' => VisitorRfidCard::STATUS_EXPIRED]);
        } elseif ($visitor->visitor_rfid_card_id) {
            VisitorRfidCard::query()->where('id', $visitor->visitor_rfid_card_id)->update([
                'status' => VisitorRfidCard::STATUS_EXPIRED,
            ]);
        }

        if ($notify) {
            $this->notifyStaff(
                'Visitor overstay / RFID expired',
                "{$visitor->displayName()} ({$visitor->plate_number}) passed expected exit time. Temporary RFID expired.",
                $visitor
            );
        }
    }

    /**
     * @return Collection<int, Visitor>
     */
    public function activeVisitors(?string $search = null, ?string $status = null): Collection
    {
        $query = Visitor::query()
            ->with(['vehicleType', 'rfidCard'])
            ->whereIn('status', Visitor::ACTIVE_STATUSES)
            ->orderByDesc('id');

        if ($status && in_array($status, Visitor::ACTIVE_STATUSES, true)) {
            $query->where('status', $status);
        }

        $visitors = $query->get();

        if ($search = trim((string) $search)) {
            $q = strtolower($search);
            $visitors = $visitors->filter(function (Visitor $v) use ($q) {
                $blob = strtolower(implode(' ', [
                    $v->displayName(),
                    $v->plate_number,
                    $v->rfid_uid,
                    $v->purpose,
                    $v->office_to_visit,
                    $v->email,
                    $v->contact_number,
                ]));

                return str_contains($blob, $q);
            })->values();
        }

        return $visitors;
    }

    public function notifyStaff(string $title, string $message, ?Visitor $visitor = null): void
    {
        $staffIds = User::query()
            ->whereIn('user_role_id', [
                NavigationService::ROLE_ADMIN,
                NavigationService::ROLE_GUARD,
            ])
            ->where('status', User::STATUS_GRANTED)
            ->pluck('id');

        foreach ($staffIds as $userId) {
            Notification::query()->create([
                'user_id' => (int) $userId,
                'sender_id' => auth()->id(),
                'title' => $title,
                'message' => $message,
                'type' => 'Update',
                'is_read' => false,
                'created_at' => now(),
            ]);
        }
    }
}
