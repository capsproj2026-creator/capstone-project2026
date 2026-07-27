<?php

namespace App\Models;

class Department extends MongoModel
{
    protected $collection = 'departments';

    public $timestamps = false;

    protected $fillable = ['departmentcode', 'departmentname'];
}
