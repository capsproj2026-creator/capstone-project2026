<?php

namespace App\Models;

class PasswordResetToken extends MongoModel
{
    protected $collection = 'password_reset_tokens';

    public $timestamps = false;

    protected $fillable = [
        'email',
        'token',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }
}
