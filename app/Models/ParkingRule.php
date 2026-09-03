<?php

namespace App\Models;

class ParkingRule extends MongoModel
{
    protected $collection = 'parking_rules';

    public $timestamps = false;

    protected $fillable = ['description', 'status'];

    public function isActive(): bool
    {
        $status = trim((string) ($this->status ?? 'Active'));

        return $status === '' || strcasecmp($status, 'Active') === 0;
    }
}
