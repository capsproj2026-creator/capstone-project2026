<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSuspension extends MongoModel
{
    protected $collection = 'user_suspensions';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'strike_count',
        'is_suspended',
        'suspended_until',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'strike_count' => 'integer',
            'is_suspended' => 'boolean',
            'suspended_until' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
