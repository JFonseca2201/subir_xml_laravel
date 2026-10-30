<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class EcuadorianDocument implements ValidationRule
{
    /**
     * @param string $type 'cedula', 'ruc', or 'any'
     */
    public function __construct(private string $type = 'any') {}

    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $numero = trim((string) $value);
        $len = strlen($numero);

        if (!ctype_digit($numero)) {
            $fail("El campo {$attribute} debe contener solo dígitos numéricos.");
            return;
        }

        if ($this->type === 'cedula' && $len !== 10) {
            $fail("La cédula debe contener exactamente 10 dígitos numéricos.");
            return;
        }

        if ($this->type === 'ruc' && $len !== 13) {
            $fail("El RUC debe contener exactamente 13 dígitos numéricos.");
            return;
        }

        if ($len !== 10 && $len !== 13) {
            $fail("El documento ecuatoriano debe tener 10 (cédula) o 13 (RUC) dígitos.");
            return;
        }

        $provincia = (int) substr($numero, 0, 2);
        if (($provincia < 1 || $provincia > 24) && $provincia !== 30) {
            $fail("Los dos primeros dígitos de {$attribute} no corresponden a una provincia válida de Ecuador.");
            return;
        }

        // Si es RUC, no puede terminar en 000
        if ($len === 13 && substr($numero, 10, 3) === '000') {
            $fail("El RUC no puede terminar en 000.");
            return;
        }

        // Validación algoritmo SRI (Módulo 10 y Módulo 11)
        if ($len === 10) {
            if (!$this->validateModulo10($numero)) {
                $fail("El número de cédula ingresado no es válido según el algoritmo del SRI.");
            }
            return;
        }

        // 13 dígitos: Persona Natural (primeros 10 dígitos cédula válida)
        if ($this->validateModulo10(substr($numero, 0, 10))) {
            return;
        }

        $tercerDigito = (int) $numero[2];

        // RUC de entidad pública (tercer dígito = 6, Módulo 11)
        if ($tercerDigito === 6) {
            if (!$this->validateModulo11($numero, [3, 2, 7, 6, 5, 4, 3, 2], 8)) {
                $fail("El RUC de entidad pública no es válido según el algoritmo del SRI.");
            }
            return;
        }

        // RUC de empresa jurídica / privada (tercer dígito = 9, Módulo 11)
        if ($tercerDigito === 9) {
            if (!$this->validateModulo11($numero, [4, 3, 2, 7, 6, 5, 4, 3, 2], 9)) {
                $fail("El RUC de persona jurídica no es válido según el algoritmo del SRI.");
            }
            return;
        }

        $fail("El RUC ingresado no cumple con la estructura requerida por el SRI.");
    }

    private function validateModulo10(string $cedula): bool
    {
        if (strlen($cedula) !== 10) return false;

        $total = 0;
        for ($i = 0; $i < 9; $i++) {
            $digito = (int) $cedula[$i];
            if ($i % 2 === 0) {
                $digito *= 2;
                if ($digito > 9) {
                    $digito -= 9;
                }
            }
            $total += $digito;
        }

        $decenaSuperior = (int) (ceil($total / 10) * 10);
        $verificadorCalculado = $decenaSuperior - $total;
        if ($verificadorCalculado === 10) {
            $verificadorCalculado = 0;
        }

        return $verificadorCalculado === (int) $cedula[9];
    }

    private function validateModulo11(string $ruc, array $coeficientes, int $posVerificador): bool
    {
        $total = 0;
        $count = count($coeficientes);
        for ($i = 0; $i < $count; $i++) {
            $total += ((int) $ruc[$i]) * $coeficientes[$i];
        }

        $residuo = $total % 11;
        $verificadorCalculado = $residuo === 0 ? 0 : 11 - $residuo;

        return $verificadorCalculado === (int) $ruc[$posVerificador];
    }
}

