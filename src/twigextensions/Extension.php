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
     * @param array $options
     * @return string
     */
    public function filterFootnotes(string $value = null, array $options = []): string
    {
        return Footnotes::$plugin->getService()->filter($value, $options);
    }

    /**
     * Checks if any footnotes exist.
     *
     * @return bool
     */
    public function footnotesExist(): bool
    {
        return Footnotes::$plugin->getService()->exist();
    }

    /**
     * Returns all footnotes.
     *
     * @return string[]
     */
    public function getFootnotes(array $options = []): array
    {
        return Footnotes::$plugin->getService()->get($options);
    }
}
