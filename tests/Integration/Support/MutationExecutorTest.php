<?php

namespace Tests\Integration\Support;

use Hawk\LighthouseExtended\Support\ArgumentSetBuilder;
use Hawk\LighthouseExtended\Support\Mutations\MutationExecutor;
use Tests\TestCase;
use Tests\Utils\Models\Post;

class MutationExecutorTest extends TestCase
{
    /** @test */
    public function it_can_mutate()
    {
        $this->schema = "
            input TestMutationInput {
                word: String
            }
            type Mutation {
                testMutation(input: TestMutationInput!): String! @upsertField(class: \"Tests\\\\Utils\\\\Mutations\\\\TestMutation\")
            }
        ".$this->placeholderQuery();

        $title = faker()->words(10, true);

        $post = MutationExecutor::executeUpsert(new Post(), ArgumentSetBuilder::build([
            'title' => $title
        ]));

        $this->assertInstanceOf(Post::class, $post);
        $this->assertEquals($title, $post->title);
    }
}
