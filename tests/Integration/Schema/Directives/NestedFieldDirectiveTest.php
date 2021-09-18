<?php

namespace Tests\Integration\Directives;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Tests\TestCase;
use Tests\Utils\Models\User;

class NestedFieldDirectiveTest extends TestCase
{
    /** @test */
    public function it_can_set_a_custom_resolver_for_a_nested_field()
    {
        $this->buildSchemaWithPlaceholderQuery(/** @lang GraphQL */ <<<'GRAPHQL'
            type User {
                id: ID!
                name: String
                email: String
                posts: [Post!] @hasMany
            }
            type Post {
                id: ID!
                title: String!
                body: String!
            }
            input UpsertUserInput {
                name: String
                email: String
                posts: UpsertPostsRelation @nestedField(resolver: "Tests\\Utils\\Mutations\\UpsertUserPostsNestedFieldResolver")
            }
            input UpsertPostsRelation {
                syncUpsert: [UpsertPostInput!]
            }
            input UpsertPostInput {
                title: String
                body: String
            }
            type Mutation {
                upsertUser(input: UpsertUserInput! @spread): User! @upsert
            }
        GRAPHQL);

        $upsertUserMutation = $this->mutation(
            'upsertUser',
            [
                'input' => [
                    'name' => faker()->name,
                    'email' => faker()->email,
                    'posts' => [
                        'syncUpsert' => Collection::times(3)->map(function () {
                            return [
                                'title' => faker()->sentence,
                                'body' => faker()->sentence,
                            ];
                        })
                    ],
                ],
            ],
            [
                'id',
                'name',
                'email',
                'posts' => [
                    'id',
                    'title',
                    'body',
                ],
            ]
        );

        $user = User::findOrFail($upsertUserMutation->result('id'));
        $this->assertEquals(
            [
                'name' => $upsertUserMutation->variable('input.name'),
                'email' => $upsertUserMutation->variable('input.email'),
            ],
            [
                'name' => $user->name,
                'email' => $user->email,
            ],
        );
        $this->assertEquals(3, count($upsertUserMutation->result('posts')));
        $this->assertEquals(
            collect($upsertUserMutation->variable('input.posts.syncUpsert'))->map(function (array $post) {
                return [
                    'title' => 'overridden title',
                    'body' => Arr::get($post, 'title').Arr::get($post, 'body'),
                ];
            }),
            collect($upsertUserMutation->result('posts'))->map(function (array $post) {
                return [
                    'title' => Arr::get($post, 'title'),
                    'body' => Arr::get($post, 'body'),
                ];
            }),
        );
    }
}
