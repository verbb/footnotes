<?php
namespace verbb\footnotes\web\twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class Extension extends AbstractExtension
{
    // Traits
    // =========================================================================

    use LegacyFootnotesTrait;


    // Public Methods
    // =========================================================================

    public function getName(): string
    {
        return 'Footnotes';
    }

    /**
     * Returns a list of filters to add to the existing list.
     *
     * @return array An array of filters
     */
    public function getFilters(): array
    {
        return [
            new TwigFilter('footnotes', [$this, 'filterFootnotes'], ['is_safe' => ['html']]),
        ];
    }

    /**
     * Returns a list of functions to add to the existing list.
     *
     * @return array An array of functions
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('footnotes_exist', [$this, 'footnotesExist']),
            new TwigFunction('footnotes_set', [$this, 'setFootnotes']),
            new TwigFunction('footnotes', [$this, 'getFootnotes'], ['is_safe' => ['html']]),
            new TwigFunction('footnotes_items', [$this, 'getFootnotesItems']),
        ];
    }

}
