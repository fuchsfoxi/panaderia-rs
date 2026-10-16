<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class PasswordUsuario implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && (mb_strlen($value) < 8 || strlen($value) > 72 || str_contains($value, "\0"))) {
            $fail('La contraseña debe tener al menos 8 caracteres, hasta 72 bytes y ningún carácter nulo.');
        }
    }
}
