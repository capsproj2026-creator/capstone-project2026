<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 2nd / 3rd offense case endorsed by Security to the GSU (and, for a 3rd offense, by the GSU to the VPAF).
 */
class SanctionEndorsement extends MongoModel
{
    protected $collection = 'sanction_endorsements';

    public $timestamps = false;

    public const STATUS_PENDING_GSU = 'pending_gsu';

    public const STATUS_PENDING_VPAF = 'pending_vpaf';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    /** Closed because the citations behind it were removed. */
    public const STATUS_WITHDRAWN = 'withdrawn';

    /** @var list<string> */
    public const OPEN_STATUSES = [self::STATUS_PENDING_GSU, self::STATUS_PENDING_VPAF];

    protected $fillable = [
        'user_id',
        'offense_level',
        'violation_log_id',
        'plate_number',
        'status',
        'endorsed_by',
        'created_at',
        'gsu_reviewed_by',
        'gsu_reviewed_at',
        'gsu_remarks',
        'vpaf_reviewed_by',
        'vpaf_reviewed_at',
        'vpaf_remarks',
        'effective_from',
        'effective_until',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'offense_level' => 'integer',
            'gsu_reviewed_by' => 'integer',
            'vpaf_reviewed_by' => 'integer',
            'created_at' => 'datetime',
            'gsu_reviewed_at' => 'datetime',
            'vpaf_reviewed_at' => 'datetime',
            'effective_from' => 'datetime',
            'effective_until' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }

    public function offenseLabel(): string
    {
        return $this->offense_level >= 3 ? '3rd Offense' : '2nd Offense';
    }

    public function sanctionLabel(): string
    {
        return $this->offense_level >= 3
            ? 'Revocation of parking privileges'
            : 'Six-month parking permit suspension';
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING_GSU => 'Awaiting GSU review',
            self::STATUS_PENDING_VPAF => 'Awaiting VPAF approval',
            self::STATUS_APPROVED => $this->offense_level >= 3 ? 'Approved by VPAF' : 'Approved by GSU',
            self::STATUS_REJECTED => 'Not approved',
            self::STATUS_WITHDRAWN => 'Withdrawn',
            default => (string) $this->status,
        };
    }
}
