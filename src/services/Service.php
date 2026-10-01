<?php
namespace verbb\footnotes\services;

use verbb\footnotes\Footnotes;
use verbb\footnotes\models\Settings;

use craft\base\Component;
use craft\helpers\Html;

use craft\htmlfield\HtmlFieldData;
use craft\redactor\FieldData;

use InvalidArgumentException;
use RuntimeException;

class Service extends Component
{
    // Constants
    // =========================================================================

    private const ADJACENT_FOOTNOTE_PATTERN = '#</sup>\s*+<sup class="footnote"[^>\n]*+>#';
    private const FOOTNOTE_CLOSING = '</sup>';
    private const FOOTNOTE_OPENING = '<sup class="footnote"';


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
        $this->footnotes = [];

        foreach ($footnotes as $item) {
            if (is_string($item)) {
                $this->footnotes[] = ['text' => $item, 'scope' => ''];
            } elseif (is_array($item) && isset($item['text'])) {
                $this->footnotes[] = [
                    'text' => (string) $item['text'],
                    'scope' => (string) ($item['scope'] ?? ''),
                ];
            }
        }
    }

    /**
     * Filters the given string and extracts all substrings
     * within &lt;sup&gt; tags. Replaces those substrings with
     * footnote indexes that reference to the corresponding
     * (extracted) strings.
     *
     * @param string|FieldData|HtmlFieldData|null $string
     * @param array<string, mixed> $options
     * @return string
     *
     * @see get()
     *
     * Options:
     * - `anchorScope` (string): stable fragment prefix for this body (e.g. `'entry-' ~ entry.id`). When omitted, a unique token is generated per filter call. Pass an empty string for legacy, unscoped fragments.
     */
    public function filter(FieldData|HtmlFieldData|string|null $string, array $options = []): string
    {
        $string = $this->normalizeRichTextString($string);

        //  empty fields return NULL instead of an empty string --> nothing to do for us here, therefore just return an empty string
        if ($string === '') {
            return '';
        }

        $scope = $this->resolveAnchorScope($options);

        return $this->transformFootnoteHtml($string, $this->footnotes, $options, $scope);
    }

    /**
     * Parses footnote markup in isolation (does not use or mutate the shared Twig request footnote list).
     *
     * @param array<string, mixed> $options
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
        $scope = $this->resolveAnchorScope($options);
        $html = $this->transformFootnoteHtml($html, $footnotes, $options, $scope);

        $items = [];

        foreach ($footnotes as $key => $footnote) {
            $number = $key + 1;
            $itemScope = $footnote['scope'];
            $items[] = [
                'number' => $number,
                'text' => $footnote['text'],
                'referenceAnchorId' => $this->referenceAnchorId($itemScope, $number),
                'listAnchorId' => $this->listAnchorId($itemScope, $number),
                'numberMarkup' => $this->footnoteListNumberMarkup($itemScope, $number, $options),
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
     * @param array<string, mixed> $options
     */
    private function resolveAnchorScope(array $options): string
    {
        if (array_key_exists('anchorScope', $options)) {
            return (string) $options['anchorScope'];
        }

        return $this->generateAnchorScopeToken();
    }

    private function generateAnchorScopeToken(): string
    {
        return bin2hex(random_bytes(8));
    }

    private function referenceAnchorId(string $scope, int $number): string
    {
        if ($scope === '') {
            return 'fnref:' . $number;
        }

        return 'fnref:' . $scope . '.' . $number;
    }

    private function listAnchorId(string $scope, int $number): string
    {
        if ($scope === '') {
            return 'footnote-' . $number;
        }

        return 'footnote-' . $scope . '.' . $number;
    }

    /**
     * @param list<array{text: string, scope: string}> $footnotes
     * @param array<string, mixed> $options
     */
    private function transformFootnoteHtml(string $string, array &$footnotes, array $options, string $scope): string
    {
        $footnoteNumbers = [];

        if (!$this->settings->enableDuplicateFootnotes) {
            // Twig can pre-seed this collection or call the filter several times. Index the existing
            // rows once, retaining the first number and its original scope exactly as addFootnoteTo().
            foreach ($footnotes as $key => $footnote) {
                $lookupKey = 's:' . $footnote['text'];

                if (!isset($footnoteNumbers[$lookupKey])) {
                    $footnoteNumbers[$lookupKey] = [
                        'number' => $key + 1,
                        'scope' => $footnote['scope'],
                    ];
                }
            }
        }

        $transformed = '';
        $lineStart = 0;

        // Split on line feeds once before looking for delimiters. The historical regex did not
        // recognise a marker across a line feed, and bounding every search to one line prevents a
        // series of malformed lines from repeatedly scanning the document's shrinking tail.
        while (($lineBreak = strpos($string, "\n", $lineStart)) !== false) {
            $line = substr($string, $lineStart, $lineBreak - $lineStart);
            $transformed .= $this->_transformFootnoteLine($line, $footnotes, $footnoteNumbers, $options, $scope) . "\n";
            $lineStart = $lineBreak + 1;
        }

        $transformed .= $this->_transformFootnoteLine(substr($string, $lineStart), $footnotes, $footnoteNumbers, $options, $scope);

        // This bounded expression only joins already-rendered adjacent markers; it does not parse
        // their contents. The possessive ranges prevent malformed suffixes from triggering retries.
        $transformed = preg_replace(self::ADJACENT_FOOTNOTE_PATTERN, ', ', $transformed);

        if ($transformed === null) {
            throw new RuntimeException('Unable to combine adjacent footnote markers: ' . preg_last_error_msg());
        }

        return $transformed;
    }

    /**
     * @param list<array{text: string, scope: string}> $footnotes
     * @param array<string, array{number: int, scope: string}> $footnoteNumbers
     * @param array<string, mixed> $options
     */
    private function _transformFootnoteLine(string $line, array &$footnotes, array &$footnoteNumbers, array $options, string $scope): string
    {
        $output = '';
        $copyOffset = 0;
        $searchOffset = 0;
        $openingLength = strlen(self::FOOTNOTE_OPENING);
        $closingLength = strlen(self::FOOTNOTE_CLOSING);

        // Walk left to right and copy each unchanged range once. If the first candidate cannot find
        // its opening bracket or closing tag, no later candidate on this line can complete either,
        // so the scan stops instead of retrying each suffix of malformed input.
        while (($openingStart = strpos($line, self::FOOTNOTE_OPENING, $searchOffset)) !== false) {
            $openingEnd = strpos($line, '>', $openingStart + $openingLength);

            if ($openingEnd === false) {
                break;
            }

            $contentStart = $openingEnd + 1;
            $closingStart = strpos($line, self::FOOTNOTE_CLOSING, $contentStart);

            if ($closingStart === false) {
                break;
            }

            $footnote = substr($line, $contentStart, $closingStart - $contentStart);

            if ($this->settings->enableDuplicateFootnotes) {
                $footnotes[] = ['text' => $footnote, 'scope' => $scope];
                $number = count($footnotes);
                $resolvedScope = $scope;
            } else {
                // Prefixing the text prevents PHP from coercing numeric footnotes into integer keys.
                $lookupKey = 's:' . $footnote;
                $resolved = $footnoteNumbers[$lookupKey] ?? null;

                if ($resolved === null) {
                    $footnotes[] = ['text' => $footnote, 'scope' => $scope];
                    $resolved = [
                        'number' => count($footnotes),
                        'scope' => $scope,
                    ];
                    $footnoteNumbers[$lookupKey] = $resolved;
                }

                $number = $resolved['number'];
                $resolvedScope = $resolved['scope'];
            }

            // Write by original offsets so generated markup is never fed back into marker parsing.
            // This is what keeps nested or incomplete input from restarting the replacement work.
            $output .= substr($line, $copyOffset, $openingStart - $copyOffset);
            $output .= $this->_renderFootnoteMarker($number, $resolvedScope, $options);

            $copyOffset = $closingStart + $closingLength;
            $searchOffset = $copyOffset;
        }

        return $output . substr($line, $copyOffset);
    }

    /**
     * @param array<string, mixed> $options
     */
    private function _renderFootnoteMarker(int $number, string $scope, array $options): string
    {
        $replaceWith = (string) $number;

        if ($this->settings->enableAnchorLinks) {
            $anchorAttributes = $options['anchorAttributes'] ?? [];
            $refId = $this->referenceAnchorId($scope, $number);
            $listFragment = $this->listAnchorId($scope, $number);
            $anchorAttrs = array_merge_recursive($anchorAttributes, ['id' => $refId, 'href' => '#' . $listFragment]);

            $replaceWith = Html::tag('a', $replaceWith, $anchorAttrs);
        }

        $superscriptAttributes = $options['superscriptAttributes'] ?? [];
        $superscriptAttrs = array_merge_recursive($superscriptAttributes, ['class' => 'footnote']);

        return Html::tag('sup', $replaceWith, $superscriptAttrs);
    }

    /**
     * @param list<array{text: string, scope: string}> $footnotes
     * @param array<string, mixed> $options
     */
    private function footnoteListNumberMarkup(string $itemScope, int $number, array $options): ?string
    {
        if (!$this->settings->enableAnchorLinks) {
            return null;
        }

        $anchorAttributes = $options['anchorAttributes'] ?? [];
        $listId = $this->listAnchorId($itemScope, $number);
        $anchorAttrs = array_merge_recursive($anchorAttributes, ['name' => $listId]);

        return Html::tag('a', (string) $number, $anchorAttrs);
    }

    /**
     * @param list<array{text: string, scope: string}> $footnotes
     * @return array{number: int, scope: string}
     */
    private function addFootnoteTo(array &$footnotes, string $footnote, string $scope): array
    {
        if ($this->settings->enableDuplicateFootnotes) {
            $footnotes[] = ['text' => $footnote, 'scope' => $scope];

            return ['number' => count($footnotes), 'scope' => $scope];
        }

        foreach ($footnotes as $key => $row) {
            if ($row['text'] === $footnote) {
                return ['number' => $key + 1, 'scope' => $row['scope']];
            }
        }

        $footnotes[] = ['text' => $footnote, 'scope' => $scope];

        return ['number' => count($footnotes), 'scope' => $scope];
    }

    /**
     * Adds the given footnote text and returns its number.
     *
     * @param string $scope Fragment scope (empty string for legacy `footnote-1` style).
     */
    public function add(string $footnote, string $scope = ''): int
    {
        return $this->addFootnoteTo($this->footnotes, $footnote, $scope)['number'];
    }

    /**
     * Checks if any footnote exists.
     *
     * In other words: The method returns if the collection
     * of footnotes is non-empty.
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
     * @param array<string, mixed> $options
     * @return array<string, string> Map of number HTML (or plain number) to footnote text
     *
     * @see getItems()
     * @see filter()
     */
    public function get(array $options = []): array
    {
        $result = [];

        foreach ($this->footnotes as $key => $footnote) {
            $number = $key + 1;
            $scope = $footnote['scope'];
            $text = $footnote['text'];

            if ($this->settings->enableAnchorLinks) {
                $anchorAttributes = $options['anchorAttributes'] ?? [];
                $listId = $this->listAnchorId($scope, $number);
                $anchorAttrs = array_merge_recursive($anchorAttributes, ['name' => $listId]);

                $number = Html::tag('a', (string) $number, $anchorAttrs);
            }

            $result[$number] = $text;
        }

        return $result;
    }
}
