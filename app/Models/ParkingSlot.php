<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParkingSlot extends MongoModel
{
    protected $collection = 'parking_slots';

    public $timestamps = false;

    protected $fillable = [
        'area_id',
        'slot_number',
        'status',
        'parked_user_id',
        'parked_visitor_id',
    ];

    protected function casts(): array
    {
        return [
            'area_id' => 'integer',
            'parked_user_id' => 'integer',
            'parked_visitor_id' => 'integer',
        ];
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(ParkingArea::class, 'area_id');
    }

    public function parkedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'parked_user_id');
    }

    public function parkedVisitor(): BelongsTo
    {
        return $this->belongsTo(Visitor::class, 'parked_visitor_id');
    }

    public function isOccupied(): bool
    {
        if (($this->status ?? '') === 'Occupied') {
            return true;
        }

        return (int) ($this->parked_user_id ?? 0) > 0
            || (int) ($this->parked_visitor_id ?? 0) > 0;
    }
}
