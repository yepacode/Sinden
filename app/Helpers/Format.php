<?php

namespace App\Helpers;

class Format
{
    /**
     * Formatea una cantidad de forma "inteligente": sin decimales si es un
     * numero entero, y con decimales solo cuando realmente los tiene.
     * Ej: 44 -> "44", 44.5 -> "44.5", 44.50 -> "44.5", 1000 -> "1,000".
     * (El cliente usa decimales, pero no quiere ver ".00" cuando no los pone.)
     * Nomenclatura US en todo el sistema: miles con coma, decimales con punto.
     */
    public static function cantidad($valor): string
    {
        $n = (float) $valor;

        // Entero: sin decimales (con separador de miles).
        if (floor($n) == $n) {
            return number_format($n, 0, '.', ',');
        }

        // Con decimales: mostrar hasta 2 y quitar ceros sobrantes (44.50 -> 44.5).
        $s = number_format($n, 2, '.', ',');
        return rtrim(rtrim($s, '0'), '.');
    }
}
