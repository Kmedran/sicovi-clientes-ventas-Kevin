<?php

namespace App\Support;

use Illuminate\Support\Str;

/** Username construction shared by model creation and the initial data migration. */
final class Usernames
{
    public static function normalize(string $value): string
    {
        return strtolower(trim($value));
    }

    public static function isValid(string $value): bool
    {
        return preg_match('/\A[a-z0-9][a-z0-9._-]{2,39}\z/D', $value) === 1;
    }

    public static function fromEmail(string $email): string
    {
        $local = explode('@', $email, 2)[0];
        $base = strtolower(Str::ascii($local, 'es'));
        $base = preg_replace('/[^a-z0-9._-]+/', '-', $base);
        $base = ltrim($base, '._-');
        if (strlen($base) < 3) {
            $base = 'usuario'.($base === '' ? '' : '-'.$base);
        }

        return substr($base, 0, 40);
    }

    /** @param callable(string): bool $isTaken */
    public static function available(string $base, callable $isTaken): string
    {
        $candidate = $base;
        $suffix = 2;
        while ($isTaken($candidate)) {
            $ending = '-'.$suffix++;
            $candidate = substr($base, 0, 40 - strlen($ending)).$ending;
        }

        return $candidate;
    }
}
