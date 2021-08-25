<?php

namespace Hawk\LighthouseExtended\Testing;

class EnumInputTest
{
    /**
     * Agrega unos tokens que permiten, más adelante, limpiarlos para utilizar `$value` como un enum.
     * Esto soluciona el problema de querer usar un `enum` en los `inputs` de las `mutations` en `Lighthouse`, ya que,
     * al pasar de array a JSON, el valor del 'enum' se convierte a `string` (sino, el JSON sería inválido).
     * @param string $value
     * @return string
     */
    public static function fromString(string $value)
    {
        return '\enum\\' . $value . '\enum\\';
    }
}
