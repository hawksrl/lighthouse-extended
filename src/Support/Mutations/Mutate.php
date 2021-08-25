<?php

namespace Hawk\LighthouseExtended\Support\Mutations;

use Hawk\LighthouseExtended\Support\ArgumentSetBuilder;

class Mutate
{
    public static function fromClass(string $class, array $input, array $context = [])
    {
        $mutation = app($class, [
            'input' => $input,
            'argumentSet' => ArgumentSetBuilder::build($input),
        ]);

        $mutation->validate();

        return $mutation->mutate();
    }
}
