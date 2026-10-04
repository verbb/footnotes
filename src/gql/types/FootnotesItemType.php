<?php
namespace verbb\footnotes\gql\types;

use craft\gql\base\ObjectType;
use craft\gql\GqlEntityRegistry;
use GraphQL\Type\Definition\Type;

final class FootnotesItemType extends ObjectType
{
    // Static Methods
    // =========================================================================

    public static function getType(): Type
    {
        return GqlEntityRegistry::getOrCreate('FootnotesItem', fn() => new self());
    }


    // Public Methods
    // =========================================================================

    public function __construct()
    {
        parent::__construct([
            'name' => 'FootnotesItem',
            'fields' => fn() => [
                'number' => [
                    'type' => Type::nonNull(Type::int()),
                    'description' => 'Footnote number (1-based), matching the superscripts in the processed HTML.',
                ],
                'text' => [
                    'type' => Type::nonNull(Type::string()),
                    'description' => 'Plain definition text for canonical notes. Legacy inline content retains its historical HTML string.',
                ],
                'html' => [
                    'type' => Type::string(),
                    'description' => 'Rich definition HTML when the field uses canonical document-native footnotes.',
                ],
                'id' => [
                    'type' => Type::string(),
                    'description' => 'Stable definition identity when the field uses canonical document-native footnotes.',
                ],
                'referenceAnchorId' => [
                    'type' => Type::nonNull(Type::string()),
                    'description' => 'ID on the in-text reference when anchor links are enabled (e.g. `fnref:1` or `fnref:entry-12.1` when scoped).',
                ],
                'referenceAnchorIds' => [
                    'type' => Type::listOf(Type::nonNull(Type::string())),
                    'description' => 'Every in-text reference ID for a definition. Canonical repeated references contain more than one value.',
                ],
                'listAnchorId' => [
                    'type' => Type::nonNull(Type::string()),
                    'description' => 'Fragment id for the list row / target (e.g. `footnote-1` or `footnote-a1b2c3d4e5f6g7h8.1`).',
                ],
                'numberMarkup' => [
                    'type' => Type::string(),
                    'description' => 'When anchor links are enabled, HTML for the list marker (like Twig’s `number` key). Otherwise null—use `number` instead.',
                ],
            ],
        ]);
    }
}
