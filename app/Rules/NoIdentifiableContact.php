<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class NoIdentifiableContact implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        $hasEmail = preg_match('/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/i', $value) === 1;
        $hasPhone = preg_match('/(?<!\d)(?:\+?\d[\s().-]*){10,15}(?!\d)/', $value) === 1;

        if ($hasEmail || $hasPhone) {
            $fail('El contenido parece incluir un correo o teléfono. Retíralo antes de publicar para proteger la privacidad del paciente.');
        }
    }
}
