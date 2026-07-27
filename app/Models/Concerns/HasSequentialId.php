<?php

namespace App\Models\Concerns;

use App\Services\SequenceService;

trait HasSequentialId
{
    public static function bootHasSequentialId(): void
    {
        static::creating(function ($model) {
            $keyName = $model->getKeyName();
            // If the caller already provided a numeric id, do not override it.
            if ($model->getAttribute($keyName) === null) {
                $model->setAttribute(
                    $model->getKeyName(),
                    SequenceService::next($model->getTable())
                );
            }
        });
    }
}
