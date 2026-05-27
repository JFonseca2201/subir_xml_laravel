<?php
function validateModulo10($cedula)
{
    $total = 0;
    $longitud = strlen($cedula);
    if ($longitud != 10) return false;

    for ($i = 0; $i < 9; $i++) {
        $digito = (int) $cedula[$i];
        if ($i % 2 == 0) { // Posiciones impares (0, 2, 4...) se multiplican por 2
            $digito *= 2;
            if ($digito > 9) {
                $digito -= 9;
            }
        }
        $total += $digito;
    }

    $decenaSuperior = ceil($total / 10) * 10;
    $digitoVerificadorCalculado = $decenaSuperior - $total;
    
    if ($digitoVerificadorCalculado == 10) {
        $digitoVerificadorCalculado = 0;
    }

    $digitoVerificadorReal = (int) $cedula[9];

    return $digitoVerificadorCalculado === $digitoVerificadorReal;
}
echo validateModulo10("1710034065") ? "Valido\n" : "Invalido\n";
echo validateModulo10("0928363728") ? "Valido\n" : "Invalido\n";
