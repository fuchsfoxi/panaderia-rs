<?php

namespace App\Rules;

use App\Actions\Produccion\ValidarTurnoUsuario;
use App\Models\Usuario;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class TurnoPermitidoUsuario implements ValidationRule
{
    public function __construct(private ?Usuario $usuario) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_scalar($value) && filter_var($value, FILTER_VALIDATE_INT) !== false) {
            if ($error = app(ValidarTurnoUsuario::class)->error($this->usuario, (int) $value)) {
                $fail($error);
            }
        }
    }
}
