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
                    'description' => 'Footnote text from the editor (may include HTML entities).',
                ],
                'referenceAnchorId' => [
                    'type' => Type::nonNull(Type::string()),
                    'description' => 'ID used on the in-article reference link when anchor links are enabled (e.g. `fnref:1`).',
                ],
                'listAnchorId' => [
                    'type' => Type::nonNull(Type::string()),
                    'description' => 'Suggested fragment / list item anchor for this footnote (e.g. `footnote-1`).',
                ],
                'numberMarkup' => [
                    'type' => Type::string(),
                    'description' => 'When anchor links are enabled, HTML for the list marker (like Twig’s `number` key). Otherwise null—use `number` instead.',
                ],
            ],
        ]);
    }
}
