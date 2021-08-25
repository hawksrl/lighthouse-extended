<?php

namespace Tests\Integration\Directives;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Tests\TestCase;
use Tests\Utils\Models\User;

class UpsertFieldDirectiveTest extends TestCase
{
    /** @test */
    public function it_can_upsert_field()
    {
        $this->schema = "
            input TestMutationInput {
                word: String
            }
            type Mutation {
                testMutation(input: TestMutationInput! @spread): String! @upsertField(class: \"Tests\\\\Utils\\\\Mutations\\\\TestMutation\")
            }
        ".$this->placeholderQuery();

        $word = faker()->word;

        $testMutation = $this->gql()
            ->mutation(
                'testMutation',
                [
                    'input' => [
                        'word' => $word,
                    ],
                ],
                []
            );

        $this->assertEquals(strtoupper($word), $testMutation->result());
    }

    /** @test */
    public function it_can_upsert_with_nested_fields()
    {
        $this->buildSchemaWithPlaceholderQuery(/** @lang GraphQL */ <<<'GRAPHQL'
            type User {
                id: ID!
                name: String
                email: String
                posts: [Post!] @collection(relation: "posts")
            }
            type Post {
                id: ID!
                title: String!
                body: String!
            }
            input UpsertUserInput {
                name: String
                email: String
                posts: UpsertPostsRelation @upsertField(class: "Tests\\Utils\\Mutations\\UpsertUserPostsField", model: "Tests\\Utils\\Models\\Post")
            }
            input UpsertPostsRelation {
                upsert: [UpsertPostInput]!
            }
            input UpsertPostInput {
                title: String
                body: String
            }
            type Mutation {
                upsertUser(input: UpsertUserInput! @spread): User!
                @upsertField(class: "Tests\\Utils\\Mutations\\UpsertUserMutationUsingModelClassAndArgumentSet")
            }
        GRAPHQL);

        $upsertUserMutation = $this->mutation(
            'upsertUser',
            [
                'input' => [
                    'name' => faker()->name,
                    'email' => faker()->email,
                    'posts' => [
                        'upsert' => Collection::times(3)->map(function () {
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
                    'data' => [
                        'id',
                        'title',
                        'body',
                    ],
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
        $this->assertEquals(3, count($upsertUserMutation->result('posts.data')));
        $this->assertEquals(
            collect($upsertUserMutation->variable('input.posts.upsert'))->map(function (array $post) {
                return [
                    'title' => 'overridden title',
                    'body' => Arr::get($post, 'title').Arr::get($post, 'body'),
                ];
            }),
            collect($upsertUserMutation->result('posts.data'))->map(function (array $post) {
                return [
                    'title' => Arr::get($post, 'title'),
                    'body' => Arr::get($post, 'body'),
                ];
            }),
        );
    }
}
