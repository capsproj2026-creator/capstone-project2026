<?php

namespace App\Support;

use Illuminate\Validation\Rules\Password;

class PasswordRules
{
    /**
     * 15–50 chars with upper, lower, number, and special character.
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
            'min:15',
            'max:50',
            Password::min(15)
                ->max(50)
                ->letters()
                ->mixedCase()
                ->numbers()
                ->symbols(),
        ];
    }

    public static function hint(): string
    {
        return 'Minimum 15 characters, maximum 50, with uppercase, lowercase, a number, and a symbol';
    }
}
