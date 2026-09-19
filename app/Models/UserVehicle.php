<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserVehicle extends MongoModel
{
    /** Maximum vehicles a student/staff member may register. */
    public const MAX_PER_USER = 5;

    protected $collection = 'user_vehicles';

    public $timestamps = true;

    protected $fillable = [
        'user_id',
        'vehicle_id',
        'plate_number',
        'vehicle_model',
        'vehicle_color',
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
