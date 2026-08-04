<?php

namespace App\Support;

use Illuminate\Validation\Rules\Password;

class PasswordRules
{
    /**
     * 8–15 chars with upper, lower, number, and special character.
     */
    public static function required(): array
    {
        return array_merge(self::requiredWithoutConfirmed(), ['confirmed']);
    }

    /**
     * Same complexity without confirmed (when confirmation field has another name).
     */
    public static function requiredWithoutConfirmed(): array
    {
        return [
            'required',
            'string',
            'min:8',
            'max:15',
            Password::min(8)
                ->letters()
                ->mixedCase()
                ->numbers()
                ->symbols(),
        ];
    }

    public static function hint(): string
    {
        return '8–15 characters with uppercase, lowercase, number, and special character';
    }
}
