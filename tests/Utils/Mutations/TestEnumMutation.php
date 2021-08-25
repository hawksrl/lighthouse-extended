<?php

namespace Tests\Utils\Mutations;

use Hawk\LighthouseExtended\Support\Mutations\HkMutation;
use Tests\Utils\Enums\FooEnum;

class TestEnumMutation extends HkMutation
{
    public function authorize()
    {
        return true;
    }

    public function setRules()
    {
        $this->rules = [
            'foo' => [
                'required' => 'Please specify an enum',
            ],
        ];
    }

    public function mutate(): string
    {
        return FooEnum::coerce($this->input('foo'));
    }
}
