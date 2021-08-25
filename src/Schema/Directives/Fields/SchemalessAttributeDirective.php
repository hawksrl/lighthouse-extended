<?php

namespace Hawk\LighthouseExtended\Schema\Directives\Fields;

use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;
use Spatie\SchemalessAttributes\SchemalessAttributes;

class SchemalessAttributeDirective extends BaseDirective implements FieldResolver
{
    public function name(): string
    {
        return 'schemalessAttribute';
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
  select: String

  """
  TODO
  """
  source: String
) on FIELD_DEFINITION
SDL;
    }

    public function resolveField(FieldValue $fieldValue): FieldValue
    {
        $select = $this->directiveArgValue('select', $fieldValue->getFieldName());

        $source = $this->directiveArgValue('source', 'extra_attributes');

        return $fieldValue->setResolver(function ($root, array $args) use ($select, $source) {
            if ($source) {
                $root = $root->{$source};
            }

            if ($root instanceof SchemalessAttributes) {
                $value = $root->get($select);
            } else {
                $value = data_get($root, $select);
            }

            return $value;
        });
    }
}
