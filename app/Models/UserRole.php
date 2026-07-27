<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class UserRole extends MongoModel
{
    protected $collection = 'user_roles';

    public $timestamps = false;

    protected $fillable = ['role_name'];

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'user_role_id');
    }
}
