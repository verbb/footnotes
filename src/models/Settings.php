<?php
namespace verbb\footnotes\models;

use craft\base\Model;

use InvalidArgumentException;

class Settings extends Model
{
    // Constants
    // =========================================================================

    public const REFERENCE_STYLE_BRACKETS = 'brackets';
    public const REFERENCE_STYLE_PLAIN = 'plain';


    // Properties
    // =========================================================================

    public bool $enableAnchorLinks = false;
    public bool $enableDuplicateFootnotes = false;
    public string $referenceStyle = self::REFERENCE_STYLE_PLAIN;


    // Public Methods
    // =========================================================================

    public function rules(): array
    {
        return [
            [['referenceStyle'], 'in', 'range' => [self::REFERENCE_STYLE_PLAIN, self::REFERENCE_STYLE_BRACKETS]],
        ];
    }

    public function getReferenceStyle(): string
    {
        return self::normalizeReferenceStyle($this->referenceStyle);
    }

    public static function normalizeReferenceStyle(mixed $referenceStyle): string
    {
        if (!is_string($referenceStyle) || !in_array($referenceStyle, [self::REFERENCE_STYLE_PLAIN, self::REFERENCE_STYLE_BRACKETS], true)) {
            throw new InvalidArgumentException('Footnote referenceStyle must be either "plain" or "brackets".');
        }

        return $referenceStyle;
    }
}
