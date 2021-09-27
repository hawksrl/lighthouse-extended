<?php

namespace Hawk\LighthouseExtended\Schema\Directives\Fields;

use Closure;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Execution\Arguments\ArgumentSet;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\ArgResolver;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class NestedFieldDirective extends BaseDirective implements ArgResolver, FieldResolver
{
    public function name(): string
    {
        return 'nestedField';
    }

    public static function definition(): string
    {
        return /* @lang GraphQL */ <<<'SDL'
"""
"""
directive @nestedField(
  """
  A reference to the resolver function to be used.
  Consists of two parts: a class name and a method name, seperated by an `@` symbol.
  If you pass only a class name, the method name defaults to `__invoke`.
  """
  resolver: String!
) on FIELD_DEFINITION
SDL;
    }

    /**
     * @param mixed $parent The result of the parent resolver.
     * @param mixed|ArgumentSet|ArgumentSet[] $argumentSet The slice of arguments that belongs to this nested resolver.
     * @return mixed
     * @throws \Exception
     */
    public function __invoke($parent, $args)
    {
        $relationName = $this->directiveArgValue('relation')
            // Use the name of the argument if no explicit relation name is given
            ?? $this->nodeName();

        /** @var \Illuminate\Database\Eloquent\Relations\Relation $relation */
        $relation = $parent->{$relationName}();

        /** @var \Illuminate\Database\Eloquent\Model $related */
        // @phpstan-ignore-next-line Relation&Builder mixin not recognized
        $related = $relation->make();

        [$className, $methodName] = $this->getMethodArgumentParts('resolver');

        $resolver = Closure::fromCallable(
        // @phpstan-ignore-next-line this works
            [app($className, [
                'parent' => $parent,
                'parentRelation' => $relation,
            ]), $methodName]
        );

        return $resolver($related, $args);
    }

    public function resolveField(FieldValue $fieldValue): FieldValue
    {
        $modelClass = $this->getModelClass();

        /** @var \Illuminate\Database\Eloquent\Model $model */
        $model = new $modelClass;

        [$className, $methodName] = $this->getMethodArgumentParts('resolver');

        return $fieldValue->setResolver(
            function ($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) use ($model, $className, $methodName) {
                $resolver = Closure::fromCallable(
                // @phpstan-ignore-next-line this works
                    [app($className, [
                        'model' => $model,
                        'args' => $args,
                    ]), $methodName]
                );

                return $resolver($model, $resolveInfo->argumentSet);
            }
        );
    }
}
