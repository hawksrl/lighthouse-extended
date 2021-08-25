<?php

namespace Hawk\LighthouseExtended\Schema\Directives\Fields;

use GraphQL\Language\AST\TypeDefinitionNode;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\TypeManipulator;

class ExtendDirective extends BaseDirective implements TypeManipulator
{
    public function name(): string
    {
        return 'extend';
    }

    public static function definition(): string
    {
        return /* @lang GraphQL */ <<<'SDL'
"""
Extends a type's fields
"""
directive @extend(
  """
  Type from what it will extend
  """
  type: String!
) on FIELD_DEFINITION
SDL;
    }

    /**
     * Apply manipulations from a type definition node.
     *
     * @param \Nuwave\Lighthouse\Schema\AST\DocumentAST $documentAST
     * @param \GraphQL\Language\AST\TypeDefinitionNode $typeDefinition
     * @return void
     */
    public function manipulateTypeDefinition(DocumentAST &$documentAST, TypeDefinitionNode &$typeDefinition)
    {
        ///** @var TypeDefinitionNode $baseType */
        $baseType = $documentAST->types[$this->directiveArgValue('type')];

        $typeDefinition->fields = $typeDefinition->fields->merge($baseType->fields);
    }
}
