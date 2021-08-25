<?php

namespace Tests\Integration\Support;

use Tests\TestCase;
use Tests\Utils\Enums\FooEnum;

class EnumInputTest extends TestCase
{
    /** @test */
    public function test_enum_input()
    {
        $this->schema = "
            enum FooEnum {
                FOO @enum(value: 0)
                BAR @enum(value: 1)
            }
            input TestEnumMutationInput {
                foo: FooEnum!
            }
            type Mutation {
                testEnumMutation(input: TestEnumMutationInput!): String! @hkMutation(class: \"Tests\\\\Utils\\\\Mutations\\\\TestEnumMutation\")
            }
        ".$this->placeholderQuery();

        $testEnumMutation = $this->gql()
            ->mutation(
                'testEnumMutation',
                [
                    'input' => [
                        'foo' => \Hawk\LighthouseExtended\Testing\EnumInputTest::fromString('FOO'),
                    ],
                ],
                []
            );

        $this->assertEquals(FooEnum::FOO, $testEnumMutation->result());
    }
}
