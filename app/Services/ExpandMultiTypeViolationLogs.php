<?php

namespace App\Services;

use App\Models\User;
use App\Models\ViolationLog;

/**
 * Repair legacy citations that stored multiple types in a single ViolationLog.
 * Each selected type must be its own log so strike counts stay accurate.
 */
class ExpandMultiTypeViolationLogs
{
    /**
     * @return array{expanded: int, deleted: int, users_synced: int}
     */
    public function run(): array
    {
        $expanded = 0;
        $deleted = 0;
        $touchedUserIds = [];

        $candidates = ViolationLog::query()->orderBy('created_at')->get();

        foreach ($candidates as $log) {
            $types = $this->extractTypes($log);

            if (count($types) <= 1) {
                if (count($types) === 1) {
                    $single = $types[0];
                    $currentTypes = is_array($log->violation_types) ? array_values($log->violation_types) : [];
                    if ((string) ($log->violation_type ?? '') !== $single || $currentTypes !== [$single]) {
                        $log->update([
                            'violation_type' => $single,
                            'violation_types' => [$single],
                        ]);
                    }
                }

                continue;
            }

            $base = [
                'user_id' => $log->user_id,
                'violator_name' => $log->violator_name,
                'id_number' => $log->id_number,
                'user_type' => $log->user_type,
                'plate_number' => $log->plate_number,
                'plate_key' => $log->plate_key,
                'description' => $log->description,
                'evidence_photo' => $log->evidence_photo,
                'evidence_photos' => $log->evidence_photos,
                'guard_id' => $log->guard_id,
                'status' => $log->status ?: 'Active',
                'owner_notified_at' => $log->owner_notified_at,
                'created_at' => $log->created_at,
                'camera_id' => $log->camera_id,
                'area_id' => $log->area_id,
                'area_name' => $log->area_name,
                'vehicle_details' => $log->vehicle_details,
                'track_id' => $log->track_id,
                'confidence' => $log->confidence,
            ];

            foreach ($types as $type) {
                ViolationLog::query()->create(array_merge($base, [
                    'violation_type' => $type,
                    'violation_types' => [$type],
                ]));
                $expanded++;
            }

            if ($log->user_id) {
                $touchedUserIds[] = (int) $log->user_id;
            }

            $log->delete();
            $deleted++;
        }

        $enforcement = app(ViolationEnforcementService::class);
        $synced = 0;

        // Reconcile every user who has violation logs or a non-zero strike_count.
        $userIds = ViolationLog::query()
            ->whereNotNull('user_id')
            ->where('user_id', '!=', 0)
            ->where('user_id', '!=', '')
            ->get(['user_id'])
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $userIds = array_values(array_unique(array_merge($userIds, $touchedUserIds)));

        foreach ($userIds as $userId) {
            $user = User::query()->find($userId);
            if (! $user) {
                continue;
            }
            $enforcement->syncStrikesFromLogs($user);
            $synced++;
        }

        return [
            'expanded' => $expanded,
            'deleted' => $deleted,
            'users_synced' => $synced,
        ];
    }

    /**
     * @return list<string>
     */
    private function extractTypes(ViolationLog $log): array
    {
        $fromArray = [];
        if (is_array($log->violation_types)) {
            foreach ($log->violation_types as $t) {
                $name = trim((string) $t);
                if ($name !== '') {
                    $fromArray[] = $name;
                }
            }
        }

        $fromArray = array_values(array_unique($fromArray));
        if (count($fromArray) > 1) {
            return $fromArray;
        }

        $label = trim((string) ($log->violation_type ?? ''));
        if ($label !== '' && str_contains($label, ' · ')) {
            return array_values(array_unique(array_filter(array_map(
                'trim',
                explode(' · ', $label)
            ))));
        }

        if (count($fromArray) === 1) {
            return $fromArray;
        }

        return $label !== '' ? [$label] : [];
    }
}
