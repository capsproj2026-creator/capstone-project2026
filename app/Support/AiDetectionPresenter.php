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

        $bits = [];
        if (isset($det['track_id']) && $det['track_id'] !== null && $det['track_id'] !== '') {
            $bits[] = '#'.$det['track_id'];
        }

        if (! empty($det['motion_label'])) {
            $bits[] = (string) $det['motion_label'];
        } elseif (($det['motion_state'] ?? '') === 'moving') {
            $bits[] = 'Moving';
        } elseif (($det['motion_state'] ?? '') === 'parked') {
            $bits[] = 'Parked';
        }

        if (($det['plate_status'] ?? '') === 'unreadable') {
            $bits[] = 'Plate Unreadable';
        } elseif (! empty($det['registered']) && ! empty($det['owner_name'])) {
            $parts = [];
            if (! empty($det['is_visitor']) || strcasecmp((string) ($det['role'] ?? ''), 'Visitor') === 0) {
                $parts[] = 'Visitor';
            }
            $parts[] = (string) $det['owner_name'];
            $parts[] = (string) ($det['plate'] ?? '');
            if (! empty($det['purpose'])) {
                $parts[] = (string) $det['purpose'];
            } elseif (! empty($det['vehicle_details'])) {
                $parts[] = (string) $det['vehicle_details'];
            }
            if (! empty($det['registration_status']) && (! empty($det['is_visitor']) || strcasecmp((string) ($det['role'] ?? ''), 'Visitor') === 0)) {
                $parts[] = (string) $det['registration_status'];
            }
            $bits[] = implode(' · ', array_values(array_filter($parts, fn ($p) => trim($p) !== '')));
        } elseif (! empty($det['plate'])) {
            $bits[] = 'Unknown Vehicle · Plate Not Registered ('.$det['plate'].')';
        } else {
            $bits[] = 'Waiting for plate…';
        }

        if (! empty($det['violation_status']) || ! empty($det['violation_flag'])) {
            $bits[] = '⚠ '.((string) ($det['violation_status'] ?? 'violation'));
        }

        return implode(' · ', $bits);
    }
}
