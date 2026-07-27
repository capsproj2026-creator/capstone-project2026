<?php

namespace App\Models;

class RolePermission extends MongoModel
{
    protected $collection = 'role_permissions';

    public $timestamps = false;

    public const SINGLETON_ID = 1;

    protected $fillable = [
        'matrix',
    ];

    protected function casts(): array
    {
        return [
            'matrix' => 'array',
        ];
    }
}
