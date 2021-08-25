<?php

namespace Tests\Integration\Schema\Directives;

use Illuminate\Support\Arr;
use Tests\TestCase;
use Tests\Utils\Models\Post;
use Tests\Utils\Models\User;

class ExtendDirectiveTest extends TestCase
{
    protected $user;
    protected $posts;

    public function setUp(): void
    {
        parent::setUp();

        $this->user = factory(User::class)->create();
        $this->posts = factory(Post::class, 3)->create([
            'user_id' => $this->user->id,
        ]);
    }

    // TODO: Testear mejor esto, haciendolo bien unitario y testeando al nivel de lighthouse.

    /** @test */
    public function it_can_extend_a_type()
    {
        $this->schema = "
            type Post {
                id: ID!
                title: String!
            }
            type ExtendedPost @extend(type: Post) {
                headline: String! @rename(attribute: \"title\")
            }
            type Query {
                postsByUser(userId: ID!): [ExtendedPost!] @collection(resolver: \"Tests\\\\Utils\\\\Queries\\\\PostsByUserQuery@resolve\")
            }
        ";

        $result = $this->gql()
            ->query('postsByUser', ['userId' => $this->user->id], [
                'data' => [
                    'id',
                    'title',
                    'headline',
                ],
            ]);

        foreach ($result->result('data') as $postData) {
            $post = Post::find(Arr::get($postData, 'id'));
            $this->assertNotNull($post);
            $this->assertEquals($post->title, Arr::get($postData, 'title'));
            $this->assertEquals($post->title, Arr::get($postData, 'headline'));
        }
    }
}
