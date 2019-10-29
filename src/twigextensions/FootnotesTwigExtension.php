<?php

namespace vierbeuter\footnotes\twigextensions;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;
use vierbeuter\footnotes\FootnotesPlugin;

/**
 * The FootnotesTwigExtension class extends all Twig templates with custom filters and functions.
 *
 * @package vierbeuter\footnotes\twigextensions
 */
class FootnotesTwigExtension extends AbstractExtension
{

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
    public function filterFootnotes($value = null): string
    {
        $footnotes = FootnotesPlugin::getInstance()->footnotes;

        return $footnotes->filter($value);
    }

    /**
     * Checks if any footnotes exist.
     *
     * @return bool
     */
    public function footnotesExist(): bool
    {
        $footnotes = FootnotesPlugin::getInstance()->footnotes;

        return $footnotes->exist();
    }

    /**
     * Returns all footnotes.
     *
     * @return string[]
     */
    public function getFootnotes(): array
    {
        $footnotes = FootnotesPlugin::getInstance()->footnotes;

        return $footnotes->get();
    }
}
