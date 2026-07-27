<?php

namespace App\Models;

class ParkingRule extends MongoModel
{
    protected $collection = 'parking_rules';

    public $timestamps = false;

    protected $fillable = ['description'];
}
