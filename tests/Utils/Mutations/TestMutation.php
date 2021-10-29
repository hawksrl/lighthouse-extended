<?php

namespace Tests\Utils\Mutations;

use Hawk\LighthouseExtended\Support\Mutations\HkMutation;

class TestMutation extends HkMutation
{
    public function authorize()
    {
        return true;
    }

    public function setRules()
    {
        $this->rules = [
            'word' => [
                'required' => 'Please enter a word',
            ],
        ];
    }

    public function moreValidation()
    {
        // More custom validations.
        // $this->addError('key', 'message');
    }

    public function mutate(): string
    {
        return strtoupper($this->input('word'));
    }
}
