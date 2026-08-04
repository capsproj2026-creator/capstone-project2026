<?php

namespace App\Support;

/**
 * Shared labels for AI parking detections across Live Cameras / monitor UIs.
 */
class AiDetectionPresenter
{
    /**
     * @param  array<string, mixed>|null  $det
     */
    public static function plateLine(?array $det): string
    {
        if ($det === null) {
            return '—';
        }

        if (($det['plate_status'] ?? '') === 'unreadable') {
            return 'Plate Unreadable';
        }

        if (! empty($det['registered']) && ! empty($det['owner_name'])) {
            $parts = [
                (string) $det['owner_name'],
                (string) ($det['plate'] ?? ''),
            ];
            if (! empty($det['vehicle_details'])) {
                $parts[] = (string) $det['vehicle_details'];
            }

            return implode(' · ', array_values(array_filter($parts, fn ($p) => trim($p) !== '')));
        }

        if (! empty($det['plate'])) {
            return 'Unknown Vehicle · Plate Not Registered ('.$det['plate'].')';
        }

        return 'Waiting for plate…';
    }
}
