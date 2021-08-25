<?php

namespace Hawk\LighthouseExtended\Schema\Directives\Fields;

use GraphQL\Type\Definition\ResolveInfo;
use Hawk\LighthouseExtended\Support\Mutations\UpsertField;
use Illuminate\Database\DatabaseManager;
use Nuwave\Lighthouse\Exceptions\DirectiveException;
use Nuwave\Lighthouse\Execution\Arguments\ArgumentSet;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\ArgResolver;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class UpsertFieldDirective extends BaseDirective implements FieldResolver, ArgResolver
{
    /**
     * The database manager.
     *
     * @var \Illuminate\Database\DatabaseManager
     */
    protected $databaseManager;

    protected string|null $modelClass = null;

    public function name(): string
    {
        return 'upsertField';
    }

    public static function definition(): string
    {
        return /* @lang GraphQL */ <<<'SDL'
"""
"""
directive @upsertField(
  """
  Resolver class name to use.
  """
  class: String

  """
  Specify the class name of the model to use.
  """
  model: String
) on FIELD_DEFINITION
SDL;
    }

    /**
     * UpdateDirective constructor.
     *
     * @param  \Illuminate\Database\DatabaseManager  $databaseManager
     * @param  \Nuwave\Lighthouse\Support\Contracts\GlobalId  $globalId
     * @return void
     */
    public function __construct(DatabaseManager $databaseManager)
    {
        $this->databaseManager = $databaseManager;
    }

    /**
     * @param mixed $parent The result of the parent resolver.
     * @param mixed|ArgumentSet|ArgumentSet[] $argumentSet The slice of arguments that belongs to this nested resolver.
     * @return mixed
     * @throws \Exception
     */
    public function __invoke($parent, $argumentSet)
    {
        $this->tryToGetModelClass();
        $upsert = new UpsertField($this->getMutatorClass(), $this->modelClass);
        return $upsert($parent, $argumentSet);
    }

    public function resolveField(FieldValue $fieldValue): FieldValue
    {
        return $fieldValue->setResolver(
            function ($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) {
                /** @var \Illuminate\Database\Eloquent\Model $model */
                $model = $this->modelClass ? new $this->modelClass : null;

                $executeMutation = function () use ($root, $resolveInfo) {
                    return $this($root, $resolveInfo->argumentSet);
                };

                return config('lighthouse.transactional_mutations', true)
                    ? $this->databaseManager->connection($model ? $model->getConnectionName() : config('database.default'))->transaction($executeMutation)
                    : $executeMutation();
            }
        );
    }

    private function getMutatorClass(): string
    {
        $class = $this->directiveArgValue('class');

        if (! $class) {
            throw new DirectiveException("Missing class argument for the {$this->definitionNode->name->value} mutation");
        }

        if (! class_exists($class)) {
            throw new DirectiveException("The class {$class} doesn't exists for the {$this->definitionNode->name->value} mutation");
        }

        return $class;
    }

    private function tryToGetModelClass(): void
    {
        try {
            $this->modelClass = $this->getModelClass();
        } catch (\Exception $exception) { }
    }
}
