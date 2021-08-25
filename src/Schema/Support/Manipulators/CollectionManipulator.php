<?php

namespace Hawk\LighthouseExtended\Schema\Support\Manipulators;

use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\Parser;
use Hawk\LighthouseExtended\Schema\Types\CollectionField;
use Illuminate\Support\Str;
use Nuwave\Lighthouse\Pagination\PaginationManipulator;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;

class CollectionManipulator extends PaginationManipulator
{
    /**
     * Transform the definition for a field to a field with pagination.
     *
     * This makes either an offset-based Paginator or a cursor-based Connection.
     * The types in between are automatically generated and applied to the schema.
     *
     * @param  \GraphQL\Language\AST\FieldDefinitionNode  $fieldDefinition
     * @param  \GraphQL\Language\AST\ObjectTypeDefinitionNode  $parentType
     * @param  \Nuwave\Lighthouse\Schema\AST\DocumentAST  $documentAST
     * @return void
     * @throws \Nuwave\Lighthouse\Exceptions\DefinitionException
     */
    public function transformToCollectionField(
        FieldDefinitionNode &$fieldDefinition,
        ObjectTypeDefinitionNode &$parentType,
        DocumentAST &$documentAST
    ): void {
        $this->registerCollection( $fieldDefinition, $parentType, $documentAST);
    }

    /**
     * Register collection w/ schema.
     *
     * @param  FieldDefinitionNode  $fieldDefinition
     * @param  ObjectTypeDefinitionNode  $parentType
     * @param  \Nuwave\Lighthouse\Schema\AST\DocumentAST  $documentAST
     * @return void
     * @throws \Nuwave\Lighthouse\Exceptions\DefinitionException
     */
    public function registerCollection(
        FieldDefinitionNode &$fieldDefinition,
        ObjectTypeDefinitionNode &$parentType,
        DocumentAST &$documentAST
    ): void {
        $fieldTypeName = ASTHelper::getUnderlyingTypeName($fieldDefinition);
        $fieldTypeName = Str::before($fieldTypeName, 'Collection');
        $collectionTypeName = "{$fieldTypeName}Collection";
        $collectionFieldClassName = addslashes(CollectionField::class);

        $paginatorType = Parser::objectTypeDefinition("
            type $collectionTypeName {
                data: [$fieldTypeName!]! @field(resolver: \"{$collectionFieldClassName}@dataResolver\")
            }
        ");
        $this->addPaginationWrapperType($paginatorType);

        $fieldDefinition->type = Parser::namedType($collectionTypeName);
        $parentType->fields [] = $fieldDefinition;
    }
}
