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
        'plate_key',
        'violation_type',
        'violation_types',
        'description',
        'evidence_photo',
        'evidence_photos',
        'guard_id',
        'status',
        'owner_notified_at',
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
            'owner_notified_at' => 'datetime',
            'evidence_photos' => 'array',
            'violation_types' => 'array',
        ];
    }

    /**
     * @return list<string>
     */
    public function typeList(): array
    {
        $types = $this->violation_types;
        if (is_array($types) && $types !== []) {
            $list = array_values(array_filter(array_map(
                static fn ($t) => trim((string) $t),
                $types
            )));
            if (count($list) > 1) {
                return array_values(array_unique($list));
            }
            if (count($list) === 1) {
                return $list;
            }
        }

        $single = trim((string) ($this->violation_type ?? ''));
        if ($single !== '' && str_contains($single, ' · ')) {
            return array_values(array_unique(array_filter(array_map(
                'trim',
                explode(' · ', $single)
            ))));
        }

        return $single !== '' ? [$single] : [];
    }

    public function typeLabel(): string
    {
        return \App\Support\TrafficViolations::displayLabel($this->typeList())
            ?: trim((string) ($this->violation_type ?? ''));
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
