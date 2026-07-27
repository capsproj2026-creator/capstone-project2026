<?php

namespace App\Models;

class ViolationType extends MongoModel
{
    protected $collection = 'violation_types';

    public $timestamps = false;

    protected $fillable = ['violation_name', 'description', 'status'];
}
