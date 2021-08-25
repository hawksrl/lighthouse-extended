<?php

namespace Tests\Integration\Support;

use Hawk\LighthouseExtended\Exceptions\HkMutationException;
use Hawk\LighthouseExtended\Support\Mutations\Mutate;
use Illuminate\Support\Arr;
use Tests\TestCase;
use Tests\Utils\Models\Post;
use Tests\Utils\Mutations\TestMutation;

class MutateTest extends TestCase
{
    /** @test */
    public function it_can_mutate()
    {
        $word = faker()->word;
        $result = Mutate::fromClass(TestMutation::class, [
            'word' => $word,
        ]);
        $this->assertEquals(strtoupper($word), $result);
    }

    /** @test */
    public function it_can_validate_input()
    {
        $this->expectException(HkMutationException::class);
        $result = Mutate::fromClass(TestMutation::class, [
            'word' => null,
        ]);
        $this->assertNull($result);
    }

    /** @test */
    public function it_can_get_instance_of_mutation()
    {
        $this->buildSchemaWithPlaceholderQuery(/** @lang GraphQL */ "
            type Post {
                id: ID!
                title: String!
            }
            input UpsertPostInput {
                id: String
                title: String
            }
            type Mutation {
                upsertPost(input: UpsertPostInput! @spread): Post! @upsert
            }
        ");

        $upsertPostMutation = $this->gql()
            ->mutation(
                'upsertPost',
                [
                    'input' => [
                        'title' => 'foo',
                    ],
                ],
                [
                    'id',
                    'title',
                ]
            );

        $instance = $upsertPostMutation->instance(Post::class);

        $this->assertInstanceOf(Post::class, $instance);

        $this->assertEquals(
            [
                'id' => $upsertPostMutation->result('id'),
                'title' => $upsertPostMutation->result('title'),
            ],
            [
                'id' => $instance->id,
                'title' => $instance->title,
            ]
        );
    }
}
