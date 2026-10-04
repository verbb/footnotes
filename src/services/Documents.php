<?php
namespace verbb\footnotes\services;

use verbb\footnotes\collections\FootnoteCollection;

use craft\base\Component;
use craft\htmlfield\HtmlFieldData;
use craft\redactor\FieldData;

use InvalidArgumentException;

class Documents extends Component
{
    // Public Methods
    // =========================================================================

    public function collection(array $options = []): FootnoteCollection
    {
        return new FootnoteCollection($this, $options);
    }

    public function normalizeValue(mixed $value): string
    {
        if ($value instanceof FieldData || $value instanceof HtmlFieldData) {
            $value = $value->getParsedContent();
        }

        if ($value === null || $value === '') {
            return '';
        }

        if (!is_string($value)) {
            throw new InvalidArgumentException('Expected a string or rich-text field value, but ' . get_debug_type($value) . ' was given.');
        }

        return $value;
    }

    public function split(string $html): array
    {
        $section = $this->_findElement($html, 'ol', 'data-footnotes')
            ?? $this->_findElement($html, 'section', 'data-footnotes');

        if (!$section) {
            return $this->_splitLegacy($html);
        }

        $definitions = [];
        $offset = 0;

        while ($item = $this->_findElement($section['html'], 'li', 'data-footnote-id', $offset)) {
            $attributes = $this->_parseAttributes($item['opening']);
            $id = trim((string)($attributes['data-footnote-id'] ?? ''));

            if ($id !== '') {
                $content = preg_replace('/<a\b(?=[^>]*\bdata-footnote-backlink(?:\s|=|>))[^>]*>.*?<\/a>/is', '', $item['inner']) ?? $item['inner'];
                $definitions[$id] = [
                    'html' => trim($content),
                    'text' => $this->_plainText($content),
                ];
            }

            $offset = $item['start'] + $item['length'];
        }

        $body = substr($html, 0, $section['start']) . substr($html, $section['start'] + $section['length']);

        return [
            'body' => $body,
            'definitions' => $definitions,
        ];
    }

    public function findReferences(string $html): array
    {
        $references = [];
        $offset = 0;

        while ($reference = $this->_findElement($html, 'sup', 'data-footnote-reference', $offset)) {
            $reference['attributes'] = $this->_parseAttributes($reference['opening']);
            $references[] = $reference;
            $offset = $reference['start'] + $reference['length'];
        }

        return $references;
    }


    // Private Methods
    // =========================================================================

    private function _splitLegacy(string $html): array
    {
        $definitions = [];
        $output = '';
        $copyOffset = 0;
        $searchOffset = 0;
        $number = 0;

        while ($footnote = $this->_findElement($html, 'sup', 'class', $searchOffset, 'footnote')) {
            $attributes = $this->_parseAttributes($footnote['opening']);

            if (array_key_exists('data-footnote-reference', $attributes)) {
                $searchOffset = $footnote['start'] + $footnote['length'];
                continue;
            }

            $number++;
            $id = trim((string)($attributes['data-footnote-id'] ?? ''));

            if ($id === '') {
                $id = 'legacy-' . substr(hash('sha256', $number . ':' . $footnote['inner']), 0, 16);
            }

            $referenceId = $id . '-1';
            $definitions[$id] = [
                'html' => trim($footnote['inner']),
                'text' => $this->_plainText($footnote['inner']),
            ];
            $output .= substr($html, $copyOffset, $footnote['start'] - $copyOffset);
            $output .= '<sup class="footnote footnote-reference" data-footnote-reference data-footnote-id="' . htmlspecialchars($id, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" data-footnote-reference-id="' . htmlspecialchars($referenceId, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"></sup>';
            $copyOffset = $footnote['start'] + $footnote['length'];
            $searchOffset = $copyOffset;
        }

        return [
            'body' => $output . substr($html, $copyOffset),
            'definitions' => $definitions,
        ];
    }

    private function _findElement(string $html, string $tag, string $attribute, int $offset = 0, ?string $classToken = null): ?array
    {
        $openingPattern = '/<' . preg_quote($tag, '/') . '\b[^>]*>/i';

        while (preg_match($openingPattern, $html, $openingMatch, PREG_OFFSET_CAPTURE, $offset)) {
            $opening = $openingMatch[0][0];
            $start = $openingMatch[0][1];
            $attributes = $this->_parseAttributes($opening);
            $matchesAttribute = array_key_exists($attribute, $attributes);

            if ($matchesAttribute && $classToken !== null) {
                $classes = preg_split('/\s+/', trim((string)($attributes['class'] ?? ''))) ?: [];
                $matchesAttribute = in_array($classToken, $classes, true);
            }

            if (!$matchesAttribute) {
                $offset = $start + strlen($opening);
                continue;
            }

            $tokenPattern = '/<\/?' . preg_quote($tag, '/') . '\b[^>]*>/i';
            $depth = 1;
            $cursor = $start + strlen($opening);

            while (preg_match($tokenPattern, $html, $tokenMatch, PREG_OFFSET_CAPTURE, $cursor)) {
                $token = $tokenMatch[0][0];
                $tokenStart = $tokenMatch[0][1];
                $depth += str_starts_with($token, '</') ? -1 : 1;
                $cursor = $tokenStart + strlen($token);

                if ($depth === 0) {
                    $innerStart = $start + strlen($opening);

                    return [
                        'start' => $start,
                        'length' => $cursor - $start,
                        'html' => substr($html, $start, $cursor - $start),
                        'opening' => $opening,
                        'inner' => substr($html, $innerStart, $tokenStart - $innerStart),
                    ];
                }
            }

            return null;
        }

        return null;
    }

    private function _parseAttributes(string $tag): array
    {
        $attributes = [];

        if (!preg_match('/^<[^\s>]+\s*(.*?)\/?\s*>$/s', $tag, $tagMatch)) {
            return $attributes;
        }

        preg_match_all('/([^\s=\/>]+)(?:\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+)))?/', $tagMatch[1], $matches, PREG_SET_ORDER | PREG_UNMATCHED_AS_NULL);

        foreach ($matches as $match) {
            $name = strtolower($match[1]);
            $value = $match[2] ?? $match[3] ?? $match[4] ?? true;
            $attributes[$name] = is_string($value) ? html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8') : $value;
        }

        return $attributes;
    }

    private function _plainText(string $html): string
    {
        $text = preg_replace('/\s+/u', ' ', strip_tags($html)) ?? strip_tags($html);

        return trim(html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
}
