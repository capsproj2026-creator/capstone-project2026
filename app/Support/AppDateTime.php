<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Throwable;

class AppDateTime
{
    public const DEFAULT_TIMEZONE = 'Asia/Manila';

    public static function timezone(): string
    {
        $tz = (string) config('app.timezone', self::DEFAULT_TIMEZONE);

        if ($tz === '' || ! in_array($tz, timezone_identifiers_list(), true)) {
            return self::DEFAULT_TIMEZONE;
        }

        return $tz;
    }

    public static function now(): CarbonInterface
    {
        return Carbon::now(self::timezone());
    }

    public static function parse(mixed $value): ?CarbonInterface
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof CarbonInterface) {
            return $value->copy()->timezone(self::timezone());
        }

        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value)->timezone(self::timezone());
        }

        try {
            return Carbon::parse($value)->timezone(self::timezone());
        } catch (Throwable) {
            return null;
        }
    }

    public static function format(mixed $value, string $format = 'M d, Y g:i A'): string
    {
        $date = self::parse($value);

        return $date?->format($format) ?? '—';
    }
}
