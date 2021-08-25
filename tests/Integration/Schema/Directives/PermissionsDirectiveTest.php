<?php

namespace Tests\Integration\Schema\Directives;

use Hawk\LighthouseExtended\Testing\TestCollection;
use Illuminate\Support\Arr;
use Nuwave\Lighthouse\Exceptions\AuthorizationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use Tests\Utils\Models\User;

class PermissionsDirectiveTest extends TestCase
{
    protected $users;
    protected $fooUser;
    protected $barRole;

    public function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'bar']);
        Permission::create(['name' => 'create']);
        Permission::create(['name' => 'edit']);
        Permission::create(['name' => 'delete']);

        $this->users = factory(User::class, 10)->create();
        $this->barRole = Role::findByName('bar');
        $this->fooUser = User::create(['name' => 'foo'])
            ->assignRole($this->barRole)
            ->givePermissionTo([
                Permission::findByName('create'),
                Permission::findByName('edit'),
            ]);
    }

    /** @test */
    public function it_throws_if_is_not_authenticated()
    {
        $this->schema = "
        type User {
            id: ID!
            name: String!
        }

        type Query {
            users: [User!]
            @permissions(roles: \"otherRole\")
            @collection(model: \"Tests\\\\Utils\\\\Models\\\\User\")
        }
        ";

        $this->expectUnauthorized();
    }

    /** @test */
    public function it_passes_if_has_roles()
    {
        $this->be($this->fooUser);

        $this->schema = "
        type User {
            id: ID!
            name: String!
        }

        type Query {
            users: [User!]
            @permissions(roles: \"bar\")
            @collection(model: \"Tests\\\\Utils\\\\Models\\\\User\")
        }
        ";

        $this->expectAuthorized();
    }

    /** @test */
    public function it_throws_if_does_not_have_roles()
    {
        $this->be($this->fooUser);

        $this->schema = "
        type User {
            id: ID!
            name: String!
        }

        type Query {
            users: [User!]
            @permissions(roles: \"otherRole\")
            @collection(model: \"Tests\\\\Utils\\\\Models\\\\User\")
        }
        ";

        $this->expectUnauthorized();
    }

    /** @test */
    public function it_passes_if_has_all_permissions()
    {
        $this->be($this->fooUser);

        $this->schema = "
        type User {
            id: ID!
            name: String!
        }

        type Query {
            users: [User!]
            @permissions(includes: [\"create\", \"edit\"])
            @collection(model: \"Tests\\\\Utils\\\\Models\\\\User\")
        }
        ";

        $this->expectAuthorized();
    }

    /** @test */
    public function it_throws_if_does_not_have_all_permissions()
    {
        $this->be($this->fooUser);

        $this->schema = "
        type User {
            id: ID!
            name: String!
        }

        type Query {
            users: [User!]
            @permissions(includes: [\"create\", \"edit\", \"delete\"])
            @collection(model: \"Tests\\\\Utils\\\\Models\\\\User\")
        }
        ";

        $this->expectUnauthorized();
    }

    /** @test */
    public function it_passes_if_has_some_permissions()
    {
        $this->be($this->fooUser);

        $this->schema = "
        type User {
            id: ID!
            name: String!
        }

        type Query {
            users: [User!]
            @permissions(includes: [\"create\"])
            @collection(model: \"Tests\\\\Utils\\\\Models\\\\User\")
        }
        ";

        $this->expectAuthorized();
    }

    /** @test */
    public function it_passes_if_has_any_permission()
    {
        $this->be($this->fooUser);

        $this->schema = "
        type User {
            id: ID!
            name: String!
        }

        type Query {
            users: [User!]
            @permissions(any: [\"create\", \"delete\"])
            @collection(model: \"Tests\\\\Utils\\\\Models\\\\User\")
        }
        ";

        $this->expectAuthorized();
    }

    /** @test */
    public function it_throws_if_does_not_have_any_permission()
    {
        $this->be($this->fooUser);

        $this->schema = "
        type User {
            id: ID!
            name: String!
        }

        type Query {
            users: [User!]
            @permissions(any: [\"other-permission\", \"delete\"])
            @collection(model: \"Tests\\\\Utils\\\\Models\\\\User\")
        }
        ";

        $this->expectUnauthorized();
    }

    protected function expectAuthorized()
    {
        $usersQuery = $this->query('users', [], [
            'data' => [
                'id',
                'name',
            ],
        ]);

        $this->assertEquals(
            User::pluck('id')->toArray(),
            Arr::pluck($usersQuery->result('data'), 'id'),
        );
    }

    protected function expectUnauthorized()
    {
        $this->expectException(AuthorizationException::class);
        $errors = $this->query('users', [], [
            'data' => [
                'id',
                'name',
            ],
        ])->getErrors();
        $this->assertEquals(
            'You are not authorized to access users',
            Arr::get($errors, '0.message')
        );
    }
}
