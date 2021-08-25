<?php

namespace Tests\Integration\Schema\Directives;

use Hawk\LighthouseExtended\Exceptions\HkMutationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Exceptions\DirectiveException;
use Tests\TestCase;
use Tests\Utils\Models\User;

class HkMutationDirectiveTest extends TestCase
{
    /** @test */
    public function it_can_mutate()
    {
        $this->buildSchemaWithPlaceholderQuery(/** @lang GraphQL */ <<<'GRAPHQL'
            input TestMutationInput {
                word: String
            }
            type Mutation {
                testMutation(input: TestMutationInput!): String! @hkMutation(class: "Tests\\Utils\\Mutations\\TestMutation")
            }
        GRAPHQL);

        $word = faker()->word;

        $testMutation = $this->mutation(
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
    public function it_attaches_the_model_class_to_mutation()
    {
        $this->rethrowGraphQLErrors();
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
                posts: UpsertPostsRelation
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
                @hkMutation(class: "Tests\\Utils\\Mutations\\UpsertUserMutationUsingModelClassAndArgumentSet")
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
                    'title' => Arr::get($post, 'title'),
                    'body' => Arr::get($post, 'body'),
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

    /** @test */
    public function it_can_validate_input()
    {
        $this->buildSchemaWithPlaceholderQuery(/** @lang GraphQL */ <<<'GRAPHQL'
            input TestMutationInput {
                word: String
            }
            type Mutation {
                testMutation(input: TestMutationInput!): String! @hkMutation(class: "Tests\\Utils\\Mutations\\TestMutation")
            }
        GRAPHQL);

        $this->expectException(HkMutationException::class);

        $result = $this->mutation(
            'testMutation',
            [
                'input' => [
                    'word' => null,
                ],
            ],
            []
        );

        $this->assertEquals('Please enter a word', Arr::get($result->getErrors(), '0.extensions.validation.word.0'));

        $this->assertNull($result->getData());
    }

    /** @test */
    public function it_throws_if_no_class_defined()
    {
        $this->expectException(DirectiveException::class);

        $this->buildSchemaWithPlaceholderQuery(/** @lang GraphQL */ <<<'GRAPHQL'
            input TestMutationInput {
                word: String
            }
            type Mutation {
                testMutation(input: TestMutationInput!): String! @hkMutation
            }
        GRAPHQL);

        $result = $this->mutation(
            'testMutation',
            [
                'input' => [
                    'word' => null,
                ],
            ],
            []
        );
        $this->assertNull($result->getData());
    }

    /** @test */
    public function it_throws_if_class_does_not_exist()
    {
        $this->expectException(DirectiveException::class);

        $this->buildSchemaWithPlaceholderQuery(/** @lang GraphQL */ <<<'GRAPHQL'
            input TestMutationInput {
                word: String
            }
            type Mutation {
                testMutation(input: TestMutationInput!): String! @hkMutation(class: "Foo\\Bar\\InexistentClass")
            }
        GRAPHQL);

        $result = $this->mutation(
            'testMutation',
            [
                'input' => [
                    'word' => null,
                ],
            ],
            []
        );
        $this->assertNull($result->getData());
    }
}
