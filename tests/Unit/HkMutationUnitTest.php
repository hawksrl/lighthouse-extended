<?php

namespace Tests\Unit;

use Tests\TestCase;
use Tests\Utils\Mutations\TestMutationWithMergeRules;

class HkMutationUnitTest extends TestCase
{
    /** @test */
    public function it_can_merge_rules()
    {
        $mutation = app(TestMutationWithMergeRules::class, [
            'input' => [
                'a' => [
                    'nested' => [
                        'rule' => null,
                    ],
                ],
            ],
        ]);

        $mutation->setRules();

        $this->assertEquals(
            [
                'a.nested.rule' => 'required',
            ],
            $mutation->rules()
        );

        $mutationWithMerge = app(TestMutationWithMergeRules::class, [
            'input' => [
                'shouldMerge' => true,
                'a' => [
                    'nested' => [
                        'rule' => null,
                    ],
                ],
            ],
        ]);

        $mutationWithMerge->setRules();

        $this->assertEquals(
            [
                'a.nested.rule' => 'required|min:100',
            ],
            $mutationWithMerge->rules()
        );
    }
}
