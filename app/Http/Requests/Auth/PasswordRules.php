<?php

namespace App\Http\Requests\Auth;

use Illuminate\Validation\Rules\Password;

final class PasswordRules
{
    /** Bcrypt accepts at most 72 bytes, including multibyte characters. */
    public static function confirmed(): array
    {
        return ['bail', 'required', 'string', 'confirmed', 'max:72', Password::min(12)->mixedCase()->numbers()->symbols(), function ($attribute, $value, $fail): void {
            if (is_string($value) && strlen($value) > 72) {
                $fail('La contraseña debe ocupar como máximo 72 bytes.');
            }
        }];
    }
}
