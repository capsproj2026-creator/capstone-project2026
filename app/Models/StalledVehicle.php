<?php

namespace App\Models;

class StalledVehicle extends MongoModel
{
    protected $collection = 'stalled_vehicles';

    public $timestamps = false;

    protected $fillable = ['description', 'status'];

    public function isActive(): bool
    {
        $status = trim((string) ($this->status ?? 'Active'));

        return $status === '' || strcasecmp($status, 'Active') === 0;
    }
}
