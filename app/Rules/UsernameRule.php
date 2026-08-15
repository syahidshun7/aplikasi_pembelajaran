<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class UsernameRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $username = (string) $value;

        if (! preg_match('/^[a-z0-9._-]{3,32}$/', $username)) {
            $fail('Username hanya boleh memakai huruf kecil, angka, titik, underscore, atau strip. Panjang 3-32 karakter, tanpa spasi/emoji.');
        }
    }
}
