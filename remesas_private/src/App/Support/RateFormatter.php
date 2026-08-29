<?php

namespace App\Support;

/**
 * Formatea una tasa de cambio con su precisión REAL, sin ceros de relleno.
 *
 * ValorTasa/TasaCapturada guardan hasta 5 decimales exactos (decimal(15,5) /
 * decimal(20,8)) para que 0,54244 y 0,55242 no se confundan al redondear a 2.
 * Pero number_format($v, 5) siempre rellena con ceros (850 → "850,00000"),
 * lo cual es tan confuso como truncar. Esto muestra 2 decimales como piso y
 * hasta 5 si el valor realmente los tiene.
 */
final class RateFormatter
{
    private function __construct()
    {
    }

    public static function format(float $valor, string $decimalSep = ',', string $thousandsSep = '.'): string
    {
        $sinCeros = rtrim(rtrim(number_format($valor, 5, '.', ''), '0'), '.');
        $puntoPos = strpos($sinCeros, '.');
        $decimales = $puntoPos === false ? 0 : strlen($sinCeros) - $puntoPos - 1;
        $decimales = max(2, $decimales);

        return number_format($valor, $decimales, $decimalSep, $thousandsSep);
    }
}
