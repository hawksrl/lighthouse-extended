<?php

namespace Hawk\LighthouseExtended\Schema\Directives\Fields;

use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;

class StaticDirective extends BaseDirective implements FieldResolver
{
    public function name(): string
    {
        return 'static';
    }

    public static function definition(): string
    {
        return /* @lang GraphQL */ <<<'SDL'
"""
"""
directive @static(
  """
  Property to call statically.
  """
  property: String
) on FIELD_DEFINITION
SDL;
    }

    public function resolveField(FieldValue $fieldValue): FieldValue
    {
        $property = $this->directiveArgValue('property', $fieldValue->getFieldName());

        return $fieldValue->setResolver(function ($root) use ($property) {
            return $root::$$property;
        });
    }
}
