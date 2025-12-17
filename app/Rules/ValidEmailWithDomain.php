<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidEmailWithDomain implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (empty($value)) {
            return; // Permitir valores vacíos si el campo es nullable
        }
        
        // Validar que sea un email válido con filter_var
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $fail('El :attribute debe ser un correo electrónico válido.');
            return;
        }
        
        // Validar que tenga un dominio completo (al menos un punto después del @)
        // Ejemplo válido: usuario@dominio.com, usuario@dominio.cl
        // Ejemplo inválido: usuario@dominio (sin TLD)
        $parts = explode('@', $value);
        if (count($parts) !== 2) {
            $fail('El :attribute debe tener un formato de correo electrónico válido.');
            return;
        }
        
        $domain = $parts[1];
        // El dominio debe tener al menos un punto (ej: dominio.com, dominio.cl)
        if (strpos($domain, '.') === false) {
            $fail('El :attribute debe tener un dominio completo con TLD (ejemplo: usuario@dominio.com o usuario@dominio.cl).');
            return;
        }
        
        // Verificar que después del último punto haya al menos 2 caracteres (TLD)
        $domainParts = explode('.', $domain);
        $tld = end($domainParts);
        if (strlen($tld) < 2) {
            $fail('El :attribute debe tener un dominio completo válido (ejemplo: usuario@dominio.com o usuario@dominio.cl).');
            return;
        }
    }
}
