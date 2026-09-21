<?php

namespace App\Support;

/**
 * Shared labels for AI parking detections across Live Cameras / monitor UIs.
 */
class AiDetectionPresenter
{
    /**
     * Plate / wait text when OCR has not produced a plate yet.
     * Loading ("Reading plate…") only while the vehicle is moving.
     *
     * @param  array<string, mixed>|null  $det
     */
    public static function unresolvedPlateLabel(?array $det): string
    {
        $motion = strtolower((string) ($det['motion_state'] ?? ''));

        return match ($motion) {
            'moving' => 'Reading plate…',
            'parked' => '—',
            'idle' => '—',
            default => '—',
        };
    }

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

        if (! empty($det['class']) && $det['class'] !== 'vehicle') {
            $bits[] = ucfirst((string) $det['class']);
        }

        if (($det['motion_state'] ?? '') === 'parked') {
            $bits[] = 'Parked';
        } elseif (! empty($det['motion_label'])) {
            $ml = strtolower((string) $det['motion_label']);
            if (! str_contains($ml, 'moving') && ! str_contains($ml, 'waiting') && ! str_contains($ml, 'settling')) {
                $bits[] = (string) $det['motion_label'];
            }
        }

        if (($det['plate_status'] ?? '') === 'unreadable') {
            $bits[] = 'Plate Unreadable';
        } elseif (($det['plate_status'] ?? '') === 'not_read') {
            $bits[] = 'Plate Not Read';
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
            $bits[] = (string) $det['plate'];
            $bits[] = 'Unknown Vehicle';
        } else {
            $bits[] = self::unresolvedPlateLabel($det);
        }

        if (in_array(($det['plate_status'] ?? ''), ['unreadable', 'not_read'], true)) {
            $bits[] = 'Unknown';
        }

        if (! empty($det['violation_status']) || ! empty($det['violation_flag'])) {
            $bits[] = '⚠ '.((string) ($det['violation_status'] ?? 'Wrong Parking'));
            if (! empty($det['violation_reason']) && ($det['violation_reason'] !== ($det['violation_status'] ?? ''))) {
                $bits[] = (string) $det['violation_reason'];
            }
        }

        return implode(' · ', $bits);
    }
}
