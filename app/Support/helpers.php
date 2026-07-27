<?php

use App\Support\AppDateTime;
use Carbon\CarbonInterface;

if (! function_exists('app_timezone')) {
    function app_timezone(): string
    {
        return AppDateTime::timezone();
    }
}

if (! function_exists('ph_now')) {
    function ph_now(): CarbonInterface
    {
        return AppDateTime::now();
    }
}

if (! function_exists('ph_datetime')) {
    function ph_datetime(mixed $value, string $format = 'M d, Y g:i A'): string
    {
        return AppDateTime::format($value, $format);
    }
}

if (! function_exists('ph_date')) {
    function ph_date(mixed $value, string $format = 'M d, Y'): string
    {
        return AppDateTime::format($value, $format);
    }
}

if (! function_exists('ph_time')) {
    function ph_time(mixed $value, string $format = 'g:i A'): string
    {
        return AppDateTime::format($value, $format);
    }
}
