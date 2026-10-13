<?php

namespace App\Models;

/**
 * One live occupancy snapshot per , shared through MongoDB.
 * The campus PC writes it. The public site reads it.
 */
class AiParkingSnapshot extends MongoModel
{
    protected $collection = 'ai_parking_snapshots';

    public $timestamps = false;

    protected $fillable = [
        '_id',
        'payload',
    ];
}
