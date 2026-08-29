<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class Visitor extends MongoModel
{
    protected $collection = 'visitors';

    public const STATUS_WAITING = 'Waiting';

    public const STATUS_INSIDE = 'Inside';

    public const STATUS_OUTSIDE = 'Outside';

    public const STATUS_EXPIRED = 'Expired';

    public const STATUS_COMPLETED = 'Completed';

    public const SOURCE_GUARD = 'guard';

    public const SOURCE_SELF = 'self';

    /** @var list<string> */
    public const ACTIVE_STATUSES = [
        self::STATUS_WAITING,
        self::STATUS_INSIDE,
        self::STATUS_OUTSIDE,
        self::STATUS_EXPIRED,
    ];

    protected $fillable = [
        'first_name',
        'last_name',
        'middle_name',
        'contact_number',
        'email',
        'purpose',
        'office_to_visit',
        'expected_exit_at',
        'plate_number',
        'vehicle_id',
        'vehicle_color',
        'visitor_rfid_card_id',
        'rfid_uid',
        'status',
        'time_in',
        'time_out',
        'registered_by',
        'notes',
        'confirmation_code',
        'registration_source',
        'form_completed_at',
    ];

    protected function casts(): array
    {
        return [
            'vehicle_id' => 'integer',
            'visitor_rfid_card_id' => 'integer',
            'registered_by' => 'integer',
            'expected_exit_at' => 'datetime',
            'time_in' => 'datetime',
            'time_out' => 'datetime',
            'form_completed_at' => 'datetime',
        ];
    }

    public function vehicleType(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id');
    }

    public function rfidCard(): BelongsTo
    {
        return $this->belongsTo(VisitorRfidCard::class, 'visitor_rfid_card_id');
    }

    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    public function displayName(): string
    {
        $parts = array_filter([
            trim((string) $this->first_name),
            trim((string) ($this->middle_name ?? '')),
            trim((string) $this->last_name),
        ]);

        return trim(implode(' ', $parts)) ?: 'Visitor';
    }

    public function isActive(): bool
    {
        return in_array((string) $this->status, self::ACTIVE_STATUSES, true);
    }

    public function isExpiredByTime(?Carbon $at = null): bool
    {
        if (! $this->expected_exit_at) {
            return false;
        }

        return $this->expected_exit_at->lte($at ?? now());
    }

    public function isSelfPreRegistered(): bool
    {
        return (string) ($this->registration_source ?? '') === self::SOURCE_SELF;
    }

    public function durationLabel(): string
    {
        if (! $this->time_in || ! $this->time_out) {
            return '—';
        }

        $minutes = $this->time_in->diffInMinutes($this->time_out);
        $hours = intdiv($minutes, 60);
        $mins = $minutes % 60;

        if ($hours > 0) {
            return "{$hours}h {$mins}m";
        }

        return "{$mins}m";
    }
}
