<?php

namespace Tests\Utils\Mutations;

use Hawk\LighthouseExtended\Support\Mutations\HkMutation;

class TestMutationWithMergeRules extends HkMutation
{
    public function authorize()
    {
        return true;
    }

    public function setRules()
    {
        $this->rules = [
            'a' => [
                'nested' => [
                    'rule' => [
                        'required' => 'Please fill this field',
                    ],
                ],
            ],
        ];

        if ($this->input('shouldMerge')) {
            $this->mergeRules([
                'a' => [
                    'nested' => [
                        'rule' => [
                            'min:100' => 'It should be greather than 100',
                        ],
                    ],
                ],
            ]);
        }
    }

    public function mutate(): string
    {
        return strtoupper($this->input('a.nested.rule'));
    }
}
