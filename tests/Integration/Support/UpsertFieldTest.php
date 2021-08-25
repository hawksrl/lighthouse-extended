<?php

namespace Tests\Integration\Support;

use Hawk\LighthouseExtended\Support\ArgumentSetBuilder;
use Hawk\LighthouseExtended\Support\Mutations\UpsertField;
use Nuwave\Lighthouse\Execution\Arguments\ArgumentSet;
use Tests\TestCase;
use Tests\Utils\Models\Post;
use Tests\Utils\Models\User;
use Tests\Utils\Mutations\TestMutation;
use Tests\Utils\Mutations\UpsertUserMutationUsingModelClassAndArgumentSet;

class UpsertFieldTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->user = factory(User::class)->create();
        $this->posts = factory(Post::class, faker()->numberBetween(33, 66))->create([
            'user_id' => $this->user->id,
        ]);
    }

    /** @test */
    public function it_can_upsert_field()
    {
        $word = faker()->word;

        $args = ArgumentSetBuilder::build([
            'word' => $word,
        ]);

        $upsert = (new UpsertField(TestMutation::class));
        $result = $upsert(null, $args);

        $this->assertEquals(strtoupper($word), $result);
    }

    /** @test */
    public function it_can_upsert_a_model()
    {
        $upsert = (new UpsertField(UpsertUserMutationUsingModelClassAndArgumentSet::class, User::class));
        $argumentSet = ArgumentSetBuilder::build([
            'name' => faker()->name,
            'email' => faker()->email,
        ]);
        $result = $upsert(null, $argumentSet);

        $user = User::findOrFail($result->id);

        $this->assertEquals(
            [
                'name' => $result->name,
                'email' => $result->email,
            ],
            [
                'name' => $user->name,
                'email' => $user->email,
            ]
        );
    }
}
