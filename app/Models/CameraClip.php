<?php

namespace App\Models;

class CameraClip extends MongoModel
{
    protected $collection = 'camera_clips';

    public $timestamps = false;

    protected $fillable = [
        'camera_id',
        'violation_log_id',
        'file_path',
        'frame_count',
        'source',
        'saved_by',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'frame_count' => 'integer',
            'saved_by' => 'integer',
            'created_at' => 'datetime',
        ];
    }
}
