<?php

namespace App\Models;

use App\Models\Concerns\HasSequentialId;
use MongoDB\Laravel\Eloquent\Model;

abstract class MongoModel extends Model
{
    use HasSequentialId;

    protected $connection = 'mongodb';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'int';
}
