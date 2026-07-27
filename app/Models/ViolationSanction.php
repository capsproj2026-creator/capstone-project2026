<?php

namespace App\Models;

class ViolationSanction extends MongoModel
{
    protected $collection = 'violation_sanctions';

    public $timestamps = false;

    protected $fillable = ['sanctions_name', 'description'];
}
