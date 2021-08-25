<?php

namespace Hawk\LighthouseExtended\Schema\Directives\Fields;

use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use Nuwave\Lighthouse\Exceptions\DirectiveException;
use Nuwave\Lighthouse\Execution\Arguments\SaveModel;
use Nuwave\Lighthouse\Execution\Arguments\UpsertModel;
use Nuwave\Lighthouse\Schema\Directives\MutationExecutorDirective;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class HkMutationDirective extends MutationExecutorDirective
{
    public function name(): string
    {
        return 'hkMutation';
    }

    public static function definition(): string
    {
        return /* @lang GraphQL */ <<<'SDL'
"""
Executes an HkMutation.
"""
directive @hkMutation(
  """
  Specify the class name of the mutator to use.
  """
  class: String

  """
  Specify the class name of the model to use.
  """
  model: String
) on FIELD_DEFINITION
SDL;
    }

    public function resolveField(FieldValue $fieldValue): FieldValue
    {
        return $fieldValue->setResolver(function ($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) {
            $mutatorClassName = $this->getMutatorClass();

            /** @var \Hawk\LighthouseExtended\Support\Mutations\Mutator $mutator */
            $mutator = app($mutatorClassName, [
                'input' => Arr::get($args, 'input', $args),
                'argumentSet' => $resolveInfo->argumentSet,
            ]);

            $mutator->validate();

            /** @var \Illuminate\Database\Eloquent\Model $model */
            $model = null;
            try {
                $modelClass = $this->getModelClass();
                $mutator->withModelClass($modelClass);
                $model = new $modelClass;
            } catch (\Exception $exception) { }

            $executeMutation = function () use ($args, $mutator) {
                $result = $mutator->mutate();
                return $result instanceof Model ? $result->refresh() : $result;
            };

            return config('lighthouse.transactional_mutations', true)
                ? $this->databaseManager->connection($model ? $model->getConnectionName() : Config::get('database.default'))->transaction($executeMutation)
                : $executeMutation();
        });
    }

    protected function makeExecutionFunction(?Relation $parentRelation = null): callable
    {
        return new UpsertModel(new SaveModel($parentRelation));
    }

    private function getMutatorClass()
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
}
