<?php

namespace Hawk\LighthouseExtended\Schema\Directives\Fields;

use Closure;
use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Support\Facades\Auth;
use Nuwave\Lighthouse\Exceptions\AuthorizationException;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class PermissionsDirective extends BaseDirective implements FieldMiddleware
{
    public function name(): string
    {
        return 'permissions';
    }

    public static function definition(): string
    {
        return /* @lang GraphQL */ <<<'SDL'
"""
Directive to use with `spatie/laravel-permissions`.
"""
directive @permissions(
  """
  Specify the permissions that the user exactly has to have all of them to passes.
  """
  includes: [String!]

  """
  The user has to have at least one of these permissions to passes.
  """
  any: [String!]

  """
  The use has to have at least one of these roles to passses.
  """
  roles: [String!]
) on FIELD_DEFINITION
SDL;
    }

    public function handleField(FieldValue $fieldValue, Closure $next): FieldValue
    {
        $previousResolver = $fieldValue->getResolver();
        $permissions = $this->directiveArgValue('includes');
        $anyPermissions = $this->directiveArgValue('any');
        $roles = $this->directiveArgValue('roles');

        return $next($fieldValue->setResolver(
            function ($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) use ($previousResolver, $permissions, $anyPermissions, $roles) {
                $args = func_get_args();
                $user = Auth::user();

                $authorized = $this->authorized($user, $permissions, $anyPermissions, $roles);

                if (! $authorized) {
                    throw new AuthorizationException(
                        "You are not authorized to access {$this->definitionNode->name->value}"
                    );
                }

                return call_user_func_array($previousResolver, $args);
            }
        ));
    }

    protected function authorized($user, $permissions, $anyPermissions, $roles)
    {
        if (! $user) {
            return false;
        }

        if ($permissions) {
            $permissions = is_array($permissions) ? $permissions : explode('|', $permissions);
            foreach ($permissions as $permission) {
                if (! $user->can($permission)) {
                    return false;
                    break;
                }
            }
        }

        if ($anyPermissions) {
            return array_filter($anyPermissions, function ($permission) use ($user) {
                return $user->can($permission);
            });
        }

        if ($roles) {
            if (! $user->hasAllRoles($roles)) {
                return false;
            }
        }

        return true;
    }
}
