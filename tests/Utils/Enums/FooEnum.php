<?php

namespace Tests\Utils\Enums;

use BenSampo\Enum\Enum;

class FooEnum extends Enum
{
    const FOO = 0;
    const BAR = 1;

    public static function getDescription($value): string
    {
        switch ($value) {
            case self::FOO:
                return 'foo description';
            case self::BAR:
                return 'bar description';
            default:
                return parent::getDescription($value);
        }
    }
}
