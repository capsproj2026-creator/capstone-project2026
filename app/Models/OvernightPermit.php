<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OvernightPermit extends MongoModel
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $collection = 'overnight_permits';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'plate_number',
        'starts_at',
        'ends_at',
        'reason',
        'proof_path',
        'status',
        'reviewed_by',
        'review_note',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'reviewed_by' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function coversNow(): bool
    {
        if ($this->status !== self::STATUS_APPROVED || $this->starts_at === null || $this->ends_at === null) {
            return false;
        }

        return $this->starts_at->lte(now()) && $this->ends_at->gte(now());
    }
}
