<?php

namespace Hawk\LighthouseExtended\Schema\Directives\Fields;

use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Type\Definition\ResolveInfo;
use Hawk\LighthouseExtended\Schema\Support\Manipulators\CollectionManipulator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Nuwave\Lighthouse\Exceptions\DirectiveException;
use Nuwave\Lighthouse\Execution\BatchLoader\BatchLoaderRegistry;
use Nuwave\Lighthouse\Execution\BatchLoader\RelationBatchLoader;
use Nuwave\Lighthouse\Execution\ModelsLoader\SimpleModelsLoader;
use Nuwave\Lighthouse\Pagination\PaginationArgs;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\Directives\RelationDirective;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldManipulator;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Nuwave\Lighthouse\Support\Utils;

class CollectionDirective extends RelationDirective implements FieldResolver, FieldManipulator
{
    public function name(): string
    {
        return 'collection';
    }

    public static function definition(): string
    {
        return /* @lang GraphQL */ <<<'SDL'
"""
Like `@paginate`, but it allows to include all records (keeping the same shape of a `@paginate` result, i.e. records
inside `data` field).
Moreover, it allows to fetch records from multiple custom and opinionated sources.
"""
directive @collection(
  """
  Type of pagination: it can be `all` (no pagination), or `paginator`.
  """
  type: String

  """
  Model class.
  """
  model: String

  """
  Model's relationship as resolver.
  """
  relation: String

  """
  Model's method as resolver.
  """
  method: String

  """
  Model's schemalessAttribute as resolver.
  """
  schemalessAttribute: String

  """
  Enum class to use as resolver (Expects a BenSampo\Enum\Enum one).
  """
  enum: String

  """
  Custom class resolver.
  """
  resolver: String

  """
  Scopes to add to eloquent's queries.
  """
  scopes: String

  """
  Additional data to add.
  """
  args: String
) on FIELD_DEFINITION
SDL;
    }

    /**
     * @param  \Nuwave\Lighthouse\Schema\AST\DocumentAST  $documentAST
     * @param  \GraphQL\Language\AST\FieldDefinitionNode  $fieldDefinition
     * @param  \GraphQL\Language\AST\ObjectTypeDefinitionNode  $parentType
     * @return void
     * @throws \Nuwave\Lighthouse\Exceptions\DefinitionException
     */
    public function manipulateFieldDefinition(DocumentAST &$documentAST, FieldDefinitionNode &$fieldDefinition, ObjectTypeDefinitionNode &$parentType): void
    {
        $collectionType = $this->directiveArgValue('type', 'all');

        if ($collectionType === 'all') {
            $collectionManipulator = new CollectionManipulator($documentAST);
            $collectionManipulator->transformToCollectionField(
                $fieldDefinition,
                $parentType,
                $documentAST
            );
        } else {
            parent::manipulateFieldDefinition($documentAST, $fieldDefinition, $parentType);
        }
    }

    /**
     * Resolve the field directive.
     *
     * @param FieldValue $fieldValue
     *
     * @return FieldValue
     * @throws DirectiveException
     */
    public function resolveField(FieldValue $fieldValue): FieldValue
    {
        $paginationType = $this->directiveArgValue('type', 'all');

        if ($paginationType === 'all') {
            if ($this->directiveArgValue('model')) {
                $modelClass = $this->getModelClass();
                return $this->resolveWithModel($fieldValue, $modelClass);
            }

            if ($relationName = $this->directiveArgValue('relation')) {
                return $this->resolveWithModelRelationship($fieldValue, $relationName);
            }

            if ($methodName = $this->directiveArgValue('method')) {
                return $this->resolveWithModelMethod($fieldValue, $methodName);
            }

            if ($schemalessAttribute = $this->directiveArgValue('schemalessAttribute')) {
                return $this->resolveWithModelSchemalessAttribute($fieldValue, $schemalessAttribute);
            }

            if ($enumClass = $this->directiveArgValue('enum')) {
                return $this->resolveWithEnumm($fieldValue, $enumClass);
            }
        } elseif ($paginationType === 'paginator') {
            if ($relationName = $this->directiveArgValue('relation')) {
                return $this->paginatorResolveWithModelRelationship($fieldValue, $relationName);
            }

            if ($this->directiveArgValue('model')) {
                $modelClass = $this->getModelClass();
                return $this->paginatorResolveWithModel($fieldValue, $modelClass);
            }
        }

        return $this->resolveWithCustomResolver($fieldValue);
    }

    protected function resolveWithModel(FieldValue $fieldValue, $modelClass): FieldValue
    {
        return $fieldValue->setResolver(
            function ($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) use ($modelClass, $fieldValue) {
                return $resolveInfo
                    ->argumentSet
                    ->enhanceBuilder(
                        $modelClass::query(),
                        $this->directiveArgValue('scopes', [])
                    )
                    ->get();
            }
        );
    }

    protected function resolveWithModelRelationship(FieldValue $fieldValue, string $relationName): FieldValue
    {
        return $fieldValue->setResolver(
            function (Model $parent, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) use ($relationName) {
                $decorateBuilder = function ($builder) use ($resolveInfo) {
                    $resolveInfo
                        ->argumentSet
                        ->enhanceBuilder($builder, $this->directiveArgValue('scopes', []));
                };

                /** @var \Nuwave\Lighthouse\Pagination\PaginationArgs|null $paginationArgs */
                $paginationArgs = null;
                if ($paginationType = $this->paginationType()) {
                    $paginationArgs = PaginationArgs::extractArgs($args, $paginationType, $this->paginationMaxCount());
                }

                if (config('lighthouse.batchload_relations')) {
                    /** @var \Nuwave\Lighthouse\Execution\BatchLoader\RelationBatchLoader $relationBatchLoader */
                    $relationBatchLoader = BatchLoaderRegistry::instance(
                        $this->qualifyPath($args, $resolveInfo),
                        function () use ($relationName, $decorateBuilder, $paginationArgs): RelationBatchLoader {
                            $modelsLoader = new SimpleModelsLoader($relationName, $decorateBuilder);

                            return new RelationBatchLoader($modelsLoader);
                        }
                    );

                    return $relationBatchLoader->load($parent);
                } else {
                    /** @var \Illuminate\Database\Eloquent\Relations\Relation $relation */
                    $relation = $parent->{$relationName}();

                    $decorateBuilder($relation);

                    if ($paginationArgs) {
                        $relation = $paginationArgs->applyToBuilder($relation);
                    }

                    return $relation->getResults();
                }
            });
    }

    protected function resolveWithModelMethod(FieldValue $fieldValue, string $methodName): FieldValue
    {
        return $fieldValue->setResolver(
            function ($root) use ($methodName) {
                return $root->{$methodName}();
            }
        );
    }

    protected function resolveWithModelSchemalessAttribute($fieldValue, string $schemalessAttribute): FieldValue
    {
        return $fieldValue->setResolver(
            function ($root) use ($schemalessAttribute) {
                return collect($root->extra_attributes->get($schemalessAttribute));
            }
        );
    }

    protected function resolveWithEnumm(FieldValue $fieldValue, string $enumClass): FieldValue
    {
        return $fieldValue->setResolver(
            function () use ($enumClass) {
                return collect($enumClass::getInstances());
            }
        );
    }

    protected function paginatorResolveWithModel(FieldValue $fieldValue, $modelClass): FieldValue
    {
        return $fieldValue->setResolver(
            function ($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) use ($modelClass): Paginator {
                if ($this->directiveHasArgument('builder')) {
                    $query = call_user_func(
                        $this->getResolverFromArgument('builder'),
                        $root,
                        $args,
                        $context,
                        $resolveInfo
                    );
                } else {
                    $query = $modelClass::query();
                }

                $query = $resolveInfo
                    ->argumentSet
                    ->enhanceBuilder(
                        $query,
                        $this->directiveArgValue('scopes', [])
                    );

                return PaginationArgs
                    ::extractArgs($args, $this->paginationType(), $this->paginationMaxCount())
                    ->applyToBuilder($query);
            }
        );
    }

    protected function paginatorResolveWithModelRelationship(FieldValue $fieldValue, string $relationName): FieldValue
    {
        return $fieldValue->setResolver(
            function (Model $parent, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) use ($relationName) {
                $query = $resolveInfo
                    ->argumentSet
                    ->enhanceBuilder(
                        $parent->{$relationName}(),
                        $this->directiveArgValue('scopes', [])
                    );

                return PaginationArgs
                    ::extractArgs($args, $this->paginationType(), $this->paginationMaxCount())
                    ->applyToBuilder($query);
            }
        );
    }

    protected function resolveWithCustomResolver(FieldValue $fieldValue): FieldValue
    {
        [$className, $methodName] = $this->getMethodArgumentParts('resolver');
        $namespacedClassName = $this->namespaceClassName(
            $className,
            $fieldValue->defaultNamespacesForParent()
        );
        $resolver = Utils::constructResolver($namespacedClassName, $methodName);
        $additionalData = $this->getAdditionalData();

        return $fieldValue->setResolver(
            function ($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) use ($resolver, $additionalData) {
                return $resolver(
                    $root,
                    array_merge($args, ['directive' => $additionalData]),
                    $context,
                    $resolveInfo
                );
            }
        );
    }

    protected function getAdditionalData()
    {
        return $this->directiveArgValue('args', []);
    }

    /**
     * Get the model class from the `model` argument of the field.
     *
     * This works differently as in other directives, so we define a separate function for it.
     *
     * @return string
     * @throws \Nuwave\Lighthouse\Exceptions\DirectiveException
     */
    protected function getModelClass(string $argumentName = 'model'): string
    {
        $model = $this->directiveArgValue($argumentName);

        // Fallback to using information from the schema definition as the model name
        if (! $model) {
            $model = ASTHelper::getUnderlyingTypeName($this->definitionNode);

            // Cut the added type suffix to get the base model class name
            $model = Str::before($model, 'Collection');
            $model = Str::before($model, 'Paginator');
            $model = Str::before($model, 'Connection');
        }

        if (! $model) {
            throw new DirectiveException(
                "A `model` argument must be assigned to the '{$this->name()}' directive on '{$this->definitionNode->name->value}"
            );
        }

        return $this->namespaceModelClass($model);
    }
}
