<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Employee request to leave a vehicle parked overnight (10:00 p.m. – 5:00 a.m.), approved by the GSU.
 * first_night / last_night are Y-m-d dates; night D runs from D 10:00 p.m. to D+1 5:00 a.m.
 */
class OvernightParkingRequest extends MongoModel
{
    protected $collection = 'overnight_parking_requests';

    public $timestamps = false;

    public const STATUS_PENDING = 'Pending';

    public const STATUS_APPROVED = 'Approved';

    public const STATUS_DENIED = 'Denied';

    public const STATUS_CANCELLED = 'Cancelled';

    protected $fillable = [
        'user_id',
        'plate_number',
        'area_id',
        'area_name',
        'first_night',
        'last_night',
        'reason',
        'status',
        'reviewed_by',
        'reviewed_at',
        'review_remarks',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'area_id' => 'integer',
            'reviewed_by' => 'integer',
            'reviewed_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function coversNight(string $night): bool
    {
        return (string) $this->first_night <= $night && $night <= (string) $this->last_night;
    }

    public function nightsLabel(): string
    {
        $first = ph_date($this->first_night, 'M j, Y');

        if ((string) $this->first_night === (string) $this->last_night) {
            return 'Night of '.$first;
        }

        return 'Nights of '.$first.' to '.ph_date($this->last_night, 'M j, Y');
    }
}
