<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Flat plate registry for fast gate / AI OCR lookup (one row per plate).
 */
class RegisteredPlate extends MongoModel
{
    public const OWNER_USER = 'user';

    public const OWNER_VISITOR = 'visitor';

    public const STATUS_ACTIVE = 'Active';

    protected $collection = 'registered_plates';

    public $timestamps = true;

    protected $fillable = [
        'plate_number',
        'plate_display',
        'owner_type',
        'owner_id',
        'user_vehicle_id',
        'visitor_id',
        'vehicle_id',
        'vehicle_type_name',
        'vehicle_model',
        'vehicle_color',
        'owner_name',
        'owner_role',
        'id_number',
        'department',
        'status',
        'is_primary',
    ];

    protected function casts(): array
    {
        return [
            'owner_id' => 'integer',
            'user_vehicle_id' => 'integer',
            'visitor_id' => 'integer',
            'vehicle_id' => 'integer',
            'is_primary' => 'boolean',
        ];
    }

    public function vehicleType(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id');
    }
}
