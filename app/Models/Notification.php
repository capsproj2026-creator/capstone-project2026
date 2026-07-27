<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends MongoModel
{
    protected $collection = 'notifications';

    public $timestamps = false;

    const CREATED_AT = 'created_at';

    protected $fillable = [
        'user_id',
        'sender_id',
        'title',
        'message',
        'type',
        'is_read',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'sender_id' => 'integer',
            'is_read' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->where('is_read', false)
                ->orWhere('is_read', 0)
                ->orWhereNull('is_read');
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
