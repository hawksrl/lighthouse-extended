<?php

namespace Tests\Integration\Schema\Directives;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Utils\Enums\FooEnum;
use Tests\Utils\Models\Post;
use Tests\Utils\Models\User;

class CollectionDirectiveTest extends TestCase
{
    protected $user;
    protected $posts;

    public function setUp(): void
    {
        parent::setUp();

        $this->user = factory(User::class)->create();
        $this->posts = factory(Post::class, faker()->numberBetween(33, 66))->create([
            'user_id' => $this->user->id,
        ]);
    }

    /** @test */
    public function it_can_fetch_all_using_a_custom_resolver()
    {
        $this->buildSchema(/** @lang GraphQL */ <<<'GRAPHQL'
            type Post {
                id: ID!
                title: String!
            }
            type Query {
                postsByUser(userId: ID!): [Post!] @collection(resolver: "Tests\\Utils\\Queries\\PostsByUserQuery@resolve")
            }
        GRAPHQL);

        $postsByUserQuery = $this->query('postsByUser', ['userId' => $this->user->id], [
            'data' => [
                'id',
                'title',
            ],
        ]);

        $this->assertEquals(
            $this->posts->pluck('id')->toArray(),
            Arr::pluck($postsByUserQuery->result('data'), 'id'),
        );
    }

    /** @test */
    public function it_can_fetch_all_using_relationship()
    {
        $this->buildSchema(/** @lang GraphQL */ <<<'GRAPHQL'
            type Post {
                id: ID!
                title: String!
            }
            type User {
                id: ID!
                name: String!
                posts: [Post!] @collection(relation: "posts")
            }
            type Query {
                user(id: ID! @eq): User! @find
            }
        GRAPHQL);

        $userQuery = $this->query('user', ['id' => $this->user->id], [
            'id',
            'name',
            'posts' => [
                'data' => [
                    'id',
                    'title',
                ],
            ],
        ]);

        $this->assertEquals($this->user->id, $userQuery->result('id'));
        $this->assertEquals($this->user->name, $userQuery->result('name'));
        $this->assertEquals(
            $this->posts->pluck('id')->toArray(),
            collect($userQuery->result('posts.data'))->pluck('id')->toArray()
        );
    }

    /** @test */
    public function it_can_fetch_all_using_relationship_using_batch_loader()
    {
        $this->buildSchema(/** @lang GraphQL */ <<<'GRAPHQL'
            type Post {
                id: ID!
                title: String!
            }
            type User {
                id: ID!
                name: String!
                posts: [Post!] @collection(relation: "posts")
            }
            type Query {
                users: [User]! @collection(model: "Tests\\Utils\\Models\\User")
            }
        GRAPHQL);

        Post::query()->forceDelete();
        User::query()->forceDelete();

        $this->assertEquals(0, Post::query()->count());
        $this->assertEquals(0, User::query()->count());

        factory(User::class, 3)
            ->create()
            ->each(function ($user) {
                factory(Post::class, 10)->create([
                    'user_id' => $user->id,
                ]);
            });

        $this->assertEquals(3 * 10, Post::query()->count());
        $this->assertEquals(3, User::query()->count());

        config(['lighthouse.batchload_relations' => false]);

        $queryCount = 0;
        DB::listen(function () use (&$queryCount): void {
            $queryCount++;
        });

        $usersQuery = $this->query(
            'users',
            [],
            [
                'data' => [
                    'id',
                    'name',
                    'posts' => [
                        'data' => [
                            'id',
                            'title',
                        ],
                    ],
                ],
            ]
        );

        $this->assertEquals(1 + 3, $queryCount);

        config(['lighthouse.batchload_relations' => true]);

        $queryCount = 0;

        $usersQuery->refetch();

        $this->assertEquals(2, $queryCount);
    }

    /** @test */
    public function it_can_fetch_all_from_a_model()
    {
        $this->buildSchema(/** @lang GraphQL */ <<<'GRAPHQL'
            type Post {
                id: ID!
                title: String!
            }
            type Query {
                posts: [Post!] @collection(model: "Tests\\Utils\\Models\\Post")
            }
        GRAPHQL);

        $postsQuery = $this->query('posts', [], [
            'data' => [
                'id',
                'title',
            ],
        ]);

        $this->assertEquals(
            Post::pluck('id')->toArray(),
            Arr::pluck($postsQuery->result('data'), 'id'),
        );
    }

    /** @test */
    public function it_can_fetch_all_from_a_model_method()
    {
        $this->buildSchema(/** @lang GraphQL */ <<<'GRAPHQL'
            type Post {
                id: ID!
                title: String!
            }
            type User {
                id: ID!
                name: String!
                posts: [Post!] @collection(method: "getAllPosts")
            }
            type Query {
                user(id: ID! @eq): User! @find
            }
        GRAPHQL);

        $userQuery = $this->query('user', ['id' => $this->user->id], [
            'id',
            'name',
            'posts' => [
                'data' => [
                    'id',
                    'title',
                ],
            ],
        ]);
        $this->assertEquals($this->user->id, $userQuery->result('id'));
        $this->assertEquals($this->user->name, $userQuery->result('name'));
        $this->assertEquals(
            Post::pluck('id')->toArray(),
            Arr::pluck($userQuery->result('posts.data'), 'id'),
        );
    }

    /** @test */
    public function it_can_fetch_all_using_schemaless_attribute()
    {
        $phones = Collection::times(faker()->numberBetween(3, 7))->map(function () {
            return [
                'number' => faker()->phoneNumber,
            ];
        });
        $this->user->extra_attributes->set('phones', $phones);
        $this->user->save();

        $this->buildSchema(/** @lang GraphQL */ <<<'GRAPHQL'
            type Phone {
                number: String!
            }
            type User {
                id: ID!
                name: String!
                phones: [Phone!] @collection(schemalessAttribute: "phones")
            }
            type Query {
                user(id: ID! @eq): User! @find
            }
        GRAPHQL);

        $userQuery = $this->query('user', ['id' => $this->user->id], [
            'id',
            'name',
            'phones' => [
                'data' => [
                    'number',
                ],
            ],
        ]);
        $this->assertEquals($this->user->id, $userQuery->result('id'));
        $this->assertEquals($this->user->name, $userQuery->result('name'));
        $this->assertEquals(
            $phones->pluck('number')->toArray(),
            Arr::pluck($userQuery->result('phones.data'), 'number'),
        );
    }

    /** @test */
    public function it_can_fetch_all_using_enum()
    {
        $this->buildSchema(/** @lang GraphQL */ <<<'GRAPHQL'
            type Foo {
                value: String
                description: String
            }
            type Query {
                allFoos: Foo! @collection(enum: "Tests\\Utils\\Enums\\FooEnum")
            }
        GRAPHQL);

        $allFoosQuery = $this->gql()
            ->query('allFoos', [], [
                'data' => [
                    'value',
                    'description',
                ],
            ]);

        $this->assertEquals(
            collect(FooEnum::getInstances())->map(function (FooEnum $enum) {
                return [
                    'value' => $enum->value,
                    'description' => $enum->description,
                ];
            })->values(),
            collect($allFoosQuery->result('data'))
        );
    }

    /** @test */
    public function it_can_fetch_paginated_using_a_custom_resolver()
    {
        $this->buildSchema(/** @lang GraphQL */ <<<'GRAPHQL'
            type Post {
                id: ID!
                title: String!
            }
            type Query {
                postsByUserPaginated(userId: ID!): [Post!] @collection(
                    resolver: "Tests\\Utils\\Queries\\PostsByUserPaginatedQuery@resolve"
                    type: "paginator"
                )
            }
        GRAPHQL);

        $postsByUserPaginatedQuery = $this->query(
            'postsByUserPaginated',
            ['userId' => $this->user->id, 'first' => 5],
            [
                'data' => [
                    'id',
                    'title',
                ],
            ]
        );

        $this->assertEquals(
            $this->user->posts()->take(5)->pluck('id')->toArray(),
            collect($postsByUserPaginatedQuery->result('data'))->pluck('id')->toArray()
        );
    }

    /** @test */
    public function it_can_fetch_paginated_from_a_model()
    {
        $this->buildSchema(/** @lang GraphQL */ <<<'GRAPHQL'
            type Post {
                id: ID!
                title: String!
            }
            type Query {
                posts: [Post!] @collection(
                    model: "Tests\\Utils\\Models\\Post"
                    type: "paginator"
                )
            }
        GRAPHQL);

        $postsQuery = $this->query('posts', ['first' => 15], [
            'data' => [
                'id',
                'title',
            ],
        ]);

        $this->assertEquals(
            Post::take(15)->pluck('id')->toArray(),
            Arr::pluck($postsQuery->result('data'), 'id'),
        );
    }

    /** @test */
    public function it_can_fetch_paginated_with_defaultCount()
    {
        $this->buildSchema(/** @lang GraphQL */ <<<'GRAPHQL'
            type Post {
                id: ID!
                title: String!
            }
            type Query {
                postsByUserPaginated(userId: ID!): [Post!] @collection(
                    resolver: "Tests\\Utils\\Queries\\PostsByUserPaginatedQuery@resolve"
                    type: "paginator"
                    defaultCount: 15
                )
            }
        GRAPHQL);

        $postsByUserPaginatedQuery = $this->query(
            'postsByUserPaginated',
            [
                'userId' => $this->user->id,
            ],
            [
                'data' => [
                    'id',
                    'title',
                ],
            ]
        );

        $this->assertEquals(
            $this->user->posts()->take(15)->pluck('id')->toArray(),
            Arr::pluck($postsByUserPaginatedQuery->result('data'), 'id'),
        );
    }

    public function it_can_fetch_paginated_from_relationship()
    {
        // TODO: Test CollectionDirective@paginatorRelationshipTypeResolver.
    }

    /** @test */
    public function it_can_fetch_plain_using_a_custom_resolver()
    {
        $this->buildSchema(/** @lang GraphQL */ <<<'GRAPHQL'
            type Post {
                id: ID!
                title: String!
            }
            type Query {
                postsByUser(userId: ID!): [Post!] @collection(resolver: "Tests\\Utils\\Queries\\PostsByUserQuery@resolve", type: "plain")
            }
        GRAPHQL);

        $postsByUserQuery = $this->query('postsByUser', ['userId' => $this->user->id], [
            'id',
            'title',
        ]);

        $this->assertEquals(
            $this->posts->pluck('id')->toArray(),
            Arr::pluck($postsByUserQuery->result(), 'id'),
        );
    }
}
