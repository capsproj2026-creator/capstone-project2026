<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSuspension extends MongoModel
{
    protected $collection = 'user_suspensions';

    public $timestamps = false;

    public const KIND_SUSPENSION = 'suspension';

    public const KIND_REVOCATION = 'revocation';

    protected $fillable = [
        'user_id',
        'strike_count',
        'is_suspended',
        'suspended_until',
        'kind',
        'endorsement_id',
        'starts_at',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'strike_count' => 'integer',
            'is_suspended' => 'boolean',
            'suspended_until' => 'datetime',
            'endorsement_id' => 'integer',
            'starts_at' => 'datetime',
        ];
    }

    public function isRevocation(): bool
    {
        if ((string) ($this->kind ?? '') === 'revocation') {
            return true;
        }

        return (bool) $this->is_suspended
            && $this->suspended_until === null
            && (string) ($this->kind ?? '') !== 'suspension';
    }

    public function isActive(): bool
    {
        if (! $this->is_suspended) {
            return false;
        }

        if ($this->suspended_until === null) {
            return true;
        }

        return $this->suspended_until->gte(now());
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
