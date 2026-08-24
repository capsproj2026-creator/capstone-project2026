<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserVehicle extends MongoModel
{
    protected $collection = 'user_vehicles';

    public $timestamps = true;

    protected $fillable = [
        'user_id',
        'vehicle_id',
        'plate_number',
        'is_primary',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'vehicle_id' => 'integer',
            'is_primary' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function vehicleType(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id');
    }
}
