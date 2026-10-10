<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Stalled vehicle reported to the GSU through Security: 12-hour grace period, then 3 hours to tow.
 */
class StalledVehicleReport extends MongoModel
{
    protected $collection = 'stalled_vehicle_reports';

    public $timestamps = false;

    public const GRACE_HOURS = 12;

    public const TOW_HOURS = 3;

    public const STATUS_ACTIVE = 'Active';

    public const STATUS_REMOVED = 'Removed';

    public const CAUSE_STALLED = 'stalled';

    public const CAUSE_KEY = 'key';

    protected $fillable = [
        'user_id',
        'owner_name',
        'plate_number',
        'location',
        'cause',
        'notes',
        'reported_by',
        'reported_at',
        'status',
        'grace_notified_at',
        'removed_at',
        'removed_by',
        'removal_notes',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'reported_by' => 'integer',
            'removed_by' => 'integer',
            'reported_at' => 'datetime',
            'grace_notified_at' => 'datetime',
            'removed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function causeLabel(): string
    {
        return $this->cause === self::CAUSE_KEY ? 'Lost or broken key' : 'Stalled / will not start';
    }

    public function graceEndsAt(): CarbonInterface
    {
        return $this->reported_at->copy()->addHours(self::GRACE_HOURS);
    }

    public function towDeadline(): CarbonInterface
    {
        return $this->graceEndsAt()->copy()->addHours(self::TOW_HOURS);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * @return 'grace'|'tow'|'overdue'|'removed'
     */
    public function stage(?CarbonInterface $now = null): string
    {
        if (! $this->isActive()) {
            return 'removed';
        }

        $now ??= now();

        if ($now->lt($this->graceEndsAt())) {
            return 'grace';
        }

        return $now->lt($this->towDeadline()) ? 'tow' : 'overdue';
    }
}
