<?php

namespace App\Models;

class StalledVehicle extends MongoModel
{
    protected $collection = 'stalled_vehicles';

    public $timestamps = false;

    protected $fillable = ['description'];
}
