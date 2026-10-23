<?php

namespace App\Rules;

use App\Models\Turno;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class TurnoPermitidoProduccion implements ValidationRule
{
    public function __construct(private string $familia) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $nombres = $this->familia === 'Pan' ? ['Mañana', 'Noche'] : ['Mañana'];
        if (! Turno::whereKey($value)->whereIn('nombre_turnos', $nombres)->exists()) {
            $fail($this->familia === 'Pan'
                ? 'Pan solo puede registrarse en Mañana o Noche.'
                : "$this->familia solo puede registrarse en Mañana.");
        }
    }
}
