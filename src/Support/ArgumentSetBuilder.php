<?php

namespace Hawk\LighthouseExtended\Support;

use Nuwave\Lighthouse\Execution\Arguments\Argument;
use Nuwave\Lighthouse\Execution\Arguments\ArgumentSet;
use Nuwave\Lighthouse\Support\Utils;

class ArgumentSetBuilder
{
    public static function build($args)
    {
        if (!$args instanceof ArgumentSet) {
            $copy = $args;
            $args = (new ArgumentSet());
            $args->arguments = $copy;
        }

        return Utils::applyEach(function (ArgumentSet $arg) {
            $arg->arguments = array_filter(
                array_map(function ($value) {
                    if (!$value) {
                        return null;
                    }
                    return static::parseValue($value);
                }, $arg->arguments),
                function ($value) {
                    return $value != null;
                },
            );
            return $arg;
        }, $args);
    }

    static function parseValue($value)
    {
        if (is_array($value)) {
            $argumentSet = new ArgumentSet();
            $argumentSet->arguments = array_map(function ($arg) {
                return static::parseValue($arg);
            }, $value);

            $value = new Argument();
            $value->value = $argumentSet;
        }

        if (is_scalar($value)) {
            $copy = $value;
            $value = new Argument();
            $value->value = $copy;
        }

        return $value;
    }
}
