<?php

namespace Tests\Integration\Schema\Directives;

use Illuminate\Support\Collection;
use Tests\TestCase;
use Tests\Utils\Models\User;

class SchemalessAttributeDirectiveTest extends TestCase
{
    protected $user;

    public function setUp(): void
    {
        parent::setUp();
        $this->user = factory(User::class)->create([
            'extra_attributes' => [
                'foo' => 'bar',
                'age' => 22,
                'phones' => Collection::times(3)->map(function () {
                    return faker()->phoneNumber;
                }),
                'addresses' => Collection::times(3)->map(function () {
                    return [
                        'address' => faker()->address,
                        'city' => faker()->city,
                        'country' => faker()->country,
                    ];
                }),
            ],
        ]);
    }

    /** @test */
    public function it_can_query_schemaless_attributes()
    {
        $this->buildSchema(/** @lang GraphQL */ <<<'GRAPHQL'
        type UserAddress {
            address: String!
            city: String!
            country: String!
        }
        type User {
            id: ID!
            name: String!
            foo: String! @schemalessAttribute
            customFoo: String! @schemalessAttribute(select: "foo")
            age: Int! @schemalessAttribute
            phones: [String!] @schemalessAttribute
            addresses: [UserAddress!] @schemalessAttribute
        }
        type Query {
            user(id: ID! @eq): User @find
        }
        GRAPHQL);

        $userQuery = $this->query(
            'user',
            ['id' => $this->user->id],
            [
                'id',
                'name',
                'foo',
                'customFoo',
                'age',
                'phones',
                'addresses' => [
                    'address',
                    'city',
                    'country',
                ],
            ]);

        $this->assertEquals($this->user->id, $userQuery->result('id'));
        $this->assertEquals($this->user->name, $userQuery->result('name'));
        $this->assertEquals($this->user->extra_attributes->foo, $userQuery->result('foo'));
        $this->assertEquals($this->user->extra_attributes->foo, $userQuery->result('customFoo'));
        $this->assertEquals($this->user->extra_attributes->age, $userQuery->result('age'));
        $this->assertEquals($this->user->extra_attributes->phones, $userQuery->result('phones'));
        $this->assertEquals($this->user->extra_attributes->addresses, $userQuery->result('addresses'));
    }
}
