<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidRut implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Si el RUT está vacío, permitir (es opcional)
        if (empty($value)) {
            return;
        }

        // Limpiar el RUT (eliminar puntos y espacios)
        $rut = str_replace(['.', ' ', '-'], '', $value);

        // Verificar formato básico: 7-9 dígitos + 1 dígito verificador = 8-10 caracteres totales
        if (strlen($rut) < 8 || strlen($rut) > 10) {
            $fail('El RUT debe tener entre 7 y 9 dígitos más el dígito verificador (total: 8 a 10 caracteres).');
            return;
        }

        // Separar número y dígito verificador
        $numero = substr($rut, 0, -1);
        $digitoVerificador = strtoupper(substr($rut, -1));

        // Verificar que el número sea numérico
        if (!is_numeric($numero)) {
            $fail('El RUT debe contener solo números y un dígito verificador.');
            return;
        }

        // Verificar que el dígito verificador sea válido (0-9 o K)
        if (!preg_match('/^[0-9K]$/', $digitoVerificador)) {
            $fail('El dígito verificador del RUT no es válido.');
            return;
        }

        // Calcular el dígito verificador correcto
        $digitoCalculado = $this->calcularDigitoVerificador($numero);

        // Comparar con el dígito verificador proporcionado
        if ($digitoCalculado !== $digitoVerificador) {
            $fail('El RUT ingresado no es válido. El dígito verificador es incorrecto.');
            return;
        }
    }

    /**
     * Calcular el dígito verificador del RUT chileno
     *
     * @param string $numero
     * @return string
     */
    private function calcularDigitoVerificador(string $numero): string
    {
        $suma = 0;
        $multiplicador = 2;

        // Sumar desde la derecha multiplicando por 2, 3, 4, 5, 6, 7 (repetir si es necesario)
        for ($i = strlen($numero) - 1; $i >= 0; $i--) {
            $suma += (int)$numero[$i] * $multiplicador;
            $multiplicador++;
            if ($multiplicador > 7) {
                $multiplicador = 2;
            }
        }

        // Calcular el resto de la división por 11
        $resto = $suma % 11;

        // Calcular el dígito verificador
        $digito = 11 - $resto;

        // Si el resultado es 11, el dígito es 0
        // Si el resultado es 10, el dígito es K
        if ($digito == 11) {
            return '0';
        } elseif ($digito == 10) {
            return 'K';
        } else {
            return (string)$digito;
        }
    }
}
