<?php
namespace verbb\footnotes\twigextensions;

use verbb\footnotes\Footnotes;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class Extension extends AbstractExtension
{
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
            new TwigFunction('footnotes', [$this, 'getFootnotes'], ['is_safe' => ['html']]),
        ];
    }

    /**
     * Filters the given string for footnotes and replaces them with a number.
     *
     * @param string|null $value
     *
     * @return string
     */
    public function filterFootnotes(string $value = null): string
    {
        return Footnotes::$plugin->footnotes->filter($value);
    }

    /**
     * Checks if any footnotes exist.
     *
     * @return bool
     */
    public function footnotesExist(): bool
    {
        return Footnotes::$plugin->footnotes->exist();
    }

    /**
     * Returns all footnotes.
     *
     * @return string[]
     */
    public function getFootnotes(): array
    {
        return Footnotes::$plugin->footnotes->get();
    }
}
