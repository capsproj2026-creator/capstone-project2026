<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VisitorRfidCard extends MongoModel
{
    protected $collection = 'visitor_rfid_cards';

    public const STATUS_AVAILABLE = 'Available';

    public const STATUS_ASSIGNED = 'Assigned';

    public const STATUS_ACTIVE = 'Active';

    public const STATUS_RETURNED = 'Returned';

    public const STATUS_EXPIRED = 'Expired';

    protected $fillable = [
        'rfid_uid',
        'status',
        'visitor_id',
        'assigned_at',
        'expires_at',
        'returned_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'visitor_id' => 'integer',
            'created_by' => 'integer',
            'assigned_at' => 'datetime',
            'expires_at' => 'datetime',
            'returned_at' => 'datetime',
        ];
    }

    public function visitor(): BelongsTo
    {
        return $this->belongsTo(Visitor::class, 'visitor_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isUsableForGate(): bool
    {
        return in_array((string) $this->status, [
            self::STATUS_ASSIGNED,
            self::STATUS_ACTIVE,
        ], true);
    }
}
