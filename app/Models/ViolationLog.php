<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ViolationLog extends MongoModel
{
    protected $collection = 'violations_log';

    public $timestamps = false;

    const CREATED_AT = 'created_at';

    protected $fillable = [
        'user_id',
        'violator_name',
        'id_number',
        'user_type',
        'plate_number',
        'violation_type',
        'description',
        'evidence_photo',
        'evidence_photos',
        'guard_id',
        'status',
        'created_at',
        'camera_id',
        'area_id',
        'area_name',
        'vehicle_details',
        'track_id',
        'confidence',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'area_id' => 'integer',
            'track_id' => 'integer',
            'confidence' => 'float',
            'created_at' => 'datetime',
            'evidence_photos' => 'array',
        ];
    }

    /**
     * @return list<string>
     */
    public function evidencePaths(): array
    {
        return \App\Support\ViolationEvidence::pathsFor($this);
    }

    public function hasEvidence(): bool
    {
        return \App\Support\ViolationEvidence::hasEvidence($this);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
