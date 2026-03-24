<?php
namespace verbb\footnotes\gql\types;

use craft\gql\base\ObjectType;
use craft\gql\GqlEntityRegistry;
use GraphQL\Type\Definition\Type;

final class FootnotesProcessedType extends ObjectType
{
    // Static Methods
    // =========================================================================

    public static function getType(): Type
    {
        return GqlEntityRegistry::getOrCreate('FootnotesProcessed', fn() => new self());
    }


    // Public Methods
    // =========================================================================

    public function __construct()
    {
        parent::__construct([
            'name' => 'FootnotesProcessed',
            'fields' => fn() => [
                'html' => [
                    'type' => Type::nonNull(Type::string()),
                    'description' => 'Body HTML with `<sup class="footnote">` replaced by numbered references.',
                ],
                'items' => [
                    'type' => Type::nonNull(Type::listOf(Type::nonNull(FootnotesItemType::getType()))),
                    'description' => 'Ordered footnotes for rendering a list below the content.',
                ],
            ],
        ]);
    }
}
