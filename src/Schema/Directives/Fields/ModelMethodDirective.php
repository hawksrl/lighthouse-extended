<?php

namespace Hawk\LighthouseExtended\Schema\Directives\Fields;

use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;

class ModelMethodDirective extends BaseDirective implements FieldResolver
{
    public function name(): string
    {
        return 'modelMethod';
    }

    public static function definition(): string
    {
        return /* @lang GraphQL */ <<<'SDL'
"""
"""
directive @schemalessAttribute(
  """
  TODO
  """
  passArgs: Boolean
) on FIELD_DEFINITION
SDL;
    }

    public function resolveField(FieldValue $fieldValue): FieldValue
    {
        $method = $this->directiveArgValue(
            'method',
            $this->definitionNode->name->value
        );

        $passArgs = $this->directiveArgValue('passArgs', true);

        return $fieldValue->setResolver(
            function ($root, array $args) use ($method, $passArgs) {
                if ($passArgs) {
                    return call_user_func([$root, $method], $args);
                } else {
                    return call_user_func([$root, $method]);
                }
            }
        );
    }
}
