<?php
namespace verbb\footnotes\services;

use verbb\footnotes\Footnotes;
use verbb\footnotes\models\Settings;

use craft\base\Component;
use craft\helpers\Html;

use craft\htmlfield\HtmlFieldData;
use craft\redactor\FieldData;

use InvalidArgumentException;

class Service extends Component
{
    // Properties
    // =========================================================================

    protected array $footnotes = [];
    protected Settings $settings;


    // Public Methods
    // =========================================================================

    public function init(): void
    {
        // Reset footnotes array
        $this->set();

        // Get plugin settings
        $this->settings = Footnotes::$plugin->getSettings();
    }

    /**
     * Sets the footnotes or resets them if no value given.
     *
     * @param string[] $footnotes
     */
    public function set(array $footnotes = []): void
    {
        $this->footnotes = $footnotes;
    }

    /**
     * Filters the given string and extracts all substrings
     * within &lt;sup&gt; tags. Replaces those substrings with
     * footnote indexes that reference to the corresponding
     * (extracted) strings.
     *
     * For getting the strings these footnote indexes refer to
     * use the get() method.
     *
     * @param string|FieldData|HtmlFieldData|null $string
     * @param array $options
     * @return string
     *
     * @see get()
     */
    public function filter(FieldData|HtmlFieldData|string|null $string, array $options = []): string
    {
        $string = $this->normalizeRichTextString($string);

        //  empty fields return NULL instead of an empty string --> nothing to do for us here, therefore just return an empty string
        if ($string === '') {
            return '';
        }

        return $this->transformFootnoteHtml($string, $this->footnotes, $options);
    }

    /**
     * Parses footnote markup in isolation (does not use or mutate the shared Twig request footnote list).
     *
     * @return array{html: string, items: array<int, array{number: int, text: string, referenceAnchorId: string, listAnchorId: string, numberMarkup: ?string}>}
     */
    public function parseForGraphql(string $html, array $options = []): array
    {
        if ($html === '') {
            return [
                'html' => '',
                'items' => [],
            ];
        }

        $footnotes = [];
        $html = $this->transformFootnoteHtml($html, $footnotes, $options);

        $items = [];
        foreach ($footnotes as $key => $footnote) {
            $number = $key + 1;
            $items[] = [
                'number' => $number,
                'text' => $footnote,
                'referenceAnchorId' => 'fnref:' . $number,
                'listAnchorId' => 'footnote-' . $number,
                'numberMarkup' => $this->footnoteListNumberMarkup($number, $options),
            ];
        }

        return [
            'html' => $html,
            'items' => $items,
        ];
    }

    /**
     * Structured footnotes for templates (anchor IDs, list targets).
     *
     * @param array<string, mixed> $options
     * @return list<array{number: int, text: string, numberHtml: string, listAnchorId: string, referenceAnchorId: string}>
     */
    public function getItems(array $options = []): array
    {
        $items = [];

        foreach ($this->footnotes as $key => $footnote) {
            $number = $key + 1;
            $scope = $footnote['scope'];
            $text = $footnote['text'];

            if ($this->settings->enableAnchorLinks) {
                $anchorAttributes = $options['anchorAttributes'] ?? [];
                $listId = $this->listAnchorId($scope, $number);
                $anchorAttrs = array_merge_recursive($anchorAttributes, ['name' => $listId]);

                $numberHtml = Html::tag('a', (string) $number, $anchorAttrs);
            } else {
                $numberHtml = (string) $number;
            }

            $items[] = [
                'number' => $number,
                'text' => $text,
                'numberHtml' => $numberHtml,
                'listAnchorId' => $this->listAnchorId($scope, $number),
                'referenceAnchorId' => $this->referenceAnchorId($scope, $number),
            ];
        }

        return $items;
    }

    /**
     * @param string|FieldData|HtmlFieldData|null $value
     */
    private function normalizeRichTextString(mixed $value): string
    {
        if ($value instanceof FieldData || $value instanceof HtmlFieldData) {
            $value = $value->getParsedContent();
        }

        if ($value === null || $value === '') {
            return '';
        }

        if (!is_string($value)) {
            throw new InvalidArgumentException('expected value of type string, ' . FieldData::class . ', or ' . HtmlFieldData::class . ', but ' . (is_object($value) ? get_class($value) : gettype($value)) . ' given');
        }

        return $value;
    }

    /**
     * @param array<int, string> $footnotes
     */
    private function transformFootnoteHtml(string $string, array &$footnotes, array $options = []): string
    {
        //  extract the contents of all occurrences of <sup> tags
        preg_match_all('#<sup class="footnote".*?>(.*?)</sup>#', $string, $matches);

        //  collect the footnotes and replace them with numbers
        $footnotesWithSup = reset($matches);
        $footnoteTexts = next($matches);

        foreach ($footnotesWithSup as $key => $footnote) {
            $number = $this->addFootnoteTo($footnotes, $footnoteTexts[$key]);
            $replaceWith = $number;

            //  add anchor link
            if ($this->settings->enableAnchorLinks) {
                $anchorAttributes = $options['anchorAttributes'] ?? [];
                $anchorAttrs = array_merge_recursive($anchorAttributes, ['id' => 'fnref:' . $number, 'href' => '#footnote-' . $number]);

                $replaceWith = Html::tag('a', $replaceWith, $anchorAttrs);
            }

            $superscriptAttributes = $options['superscriptAttributes'] ?? [];
            $superscriptAttrs = array_merge_recursive($superscriptAttributes, ['class' => 'footnote']);

            $replaceWith = Html::tag('sup', $replaceWith, $superscriptAttrs);

            //  check if "duplicate footnotes" feature is enabled to search'n'replace differently
            if ($this->settings->enableDuplicateFootnotes) {
                //  replace first footnote only (ignore any other identical ones)
                $string = substr_replace($string, $replaceWith, strpos($string, $footnote), strlen($footnote));
            } else {
                //  replace all footnotes of same text
                $string = str_replace($footnote, $replaceWith, $string);
            }
        }

        //  enable multiple, comma-separated footnotes
        //  like "<sup>2, 3</sup>" instead of having "<sup>2</sup><sup>3</sup>"

        //  therefore find all closing </sup> followed by opening <sup> tags (eventually divided by whitespaces)
        preg_match_all('#</sup>\s*<sup class="footnote".*?>#', $string, $matches);
        $footnotesCloseAndOpen = reset($matches);

        //  iterate all found "</sup><sup>" (including those with whitespaces such as "</sup> <sup>" or even "</sup>  	 <sup>")
        foreach ($footnotesCloseAndOpen as $footnoteCloseAndOpen) {
            //  replace with just a comma
            $string = str_replace($footnoteCloseAndOpen, ', ', $string);
        }

        return $string;
    }

    private function footnoteListNumberMarkup(int $number, array $options): ?string
    {
        if (!$this->settings->enableAnchorLinks) {
            return null;
        }

        $anchorAttributes = $options['anchorAttributes'] ?? [];
        $anchorAttrs = array_merge_recursive($anchorAttributes, ['name' => 'footnote-' . $number]);

        return Html::tag('a', (string)$number, $anchorAttrs);
    }

    /**
     * @param array<int, string> $footnotes
     */
    private function addFootnoteTo(array &$footnotes, string $footnote): int
    {
        if ($this->settings->enableDuplicateFootnotes) {
            return array_push($footnotes, $footnote);
        }

        $key = array_search($footnote, $footnotes);

        if ($key === false) {
            $footnotes[] = $footnote;
            $key = array_search($footnote, $footnotes);
        }

        return $key + 1;
    }

    /**
     * Adds the given footnote text and returns its number.
     *
     * @param string $footnote
     *
     * @return int
     */
    public function add(string $footnote): int
    {
        return $this->addFootnoteTo($this->footnotes, $footnote);
    }

    /**
     * Checks if any footnote exists.
     *
     * In other words: The method returns if the collection
     * of footnotes is non-empty.
     *
     * @return bool
     */
    public function exist(): bool
    {
        return !empty($this->footnotes);
    }

    /**
     * Returns all footnotes which could be collected by using
     * the filter() method.
     *
     * The footnotes' array keys begin with 1.
     *
     * @return string[]
     *
     * @see filter()
     */
    public function get(array $options = []): array
    {
        $result = [];

        foreach ($this->footnotes as $key => $footnote) {
            $number = $key + 1;

            if ($this->settings->enableAnchorLinks) {
                $anchorAttributes = $options['anchorAttributes'] ?? [];
                $anchorAttrs = array_merge_recursive($anchorAttributes, ['name' => 'footnote-' . $number]);

                $number = Html::tag('a', $number, $anchorAttrs);
            }

            $result[$number] = $footnote;
        }

        return $result;
    }
}
