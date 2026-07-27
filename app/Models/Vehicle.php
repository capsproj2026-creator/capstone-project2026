<?php

namespace App\Models;

class Vehicle extends MongoModel
{
    protected $collection = 'vehicles';

    public $timestamps = false;

    protected $fillable = ['vehicle_name'];
}
