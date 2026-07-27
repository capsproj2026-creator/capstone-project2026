<?php

namespace App\Support;

class SearchHelper
{
    /**
     * Escape LIKE wildcards for user-supplied search terms.
     */
    public static function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $value);
    }
}
