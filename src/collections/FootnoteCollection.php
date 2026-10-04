<?php
namespace verbb\footnotes\collections;

use verbb\footnotes\models\Footnote;
use verbb\footnotes\models\FootnoteReference;
use verbb\footnotes\services\Documents;

use Craft;
use craft\helpers\Html;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;
use Twig\Markup;

class FootnoteCollection implements Countable, IteratorAggregate
{
    // Properties
    // =========================================================================

    private Documents $documents;
    private array $options;
    private array $items = [];
    private array $itemIndexes = [];
    private array $usedAnchorIds = [];
    private int $instance = 0;


    // Public Methods
    // =========================================================================

    public function __construct(Documents $documents, array $options = [])
    {
        $this->documents = $documents;
        $this->options = $options;
    }

    public function add(mixed $value): Markup
    {
        $html = $this->documents->normalizeValue($value);

        if ($html === '') {
            return new Markup('', 'UTF-8');
        }

        $this->instance++;
        $fragment = $this->documents->split($html);
        $body = $this->_replaceReferences($fragment['body'], $fragment['definitions']);

        return new Markup($body, 'UTF-8');
    }

    public function render(array $options = []): Markup
    {
        if (!$this->items) {
            return new Markup('', 'UTF-8');
        }

        $listAttributes = $this->_mergeAttributes([
            'class' => ['footnotes-list'],
        ], $options['listAttributes'] ?? []);
        $itemAttributes = $options['itemAttributes'] ?? [];
        $backlinkAttributes = $options['backlinkAttributes'] ?? [];
        $items = '';

        foreach ($this->items as $footnote) {
            $backlinks = [];
            $references = $footnote->getReferences();

            foreach ($references as $key => $reference) {
                $label = count($references) > 1
                    ? Craft::t('footnotes', 'Back to footnote {number}, reference {reference}', [
                        'number' => $footnote->number,
                        'reference' => $key + 1,
                    ])
                    : Craft::t('footnotes', 'Back to footnote {number}', ['number' => $footnote->number]);
                $attributes = $this->_mergeAttributes([
                    'class' => ['footnote-backlink'],
                    'data-footnote-backlink' => true,
                    'href' => '#' . $reference->anchorId,
                    'role' => 'doc-backlink',
                    'aria-label' => $label,
                ], $backlinkAttributes);
                $indicator = count($references) > 1 ? '↑' . $reference->label : '↑';
                $backlinks[] = Html::tag('a', $indicator, $attributes);
            }

            $backlinkGroup = Html::tag('div', implode(' ', $backlinks), [
                'class' => ['footnote-backlinks'],
                'data-footnote-backlinks' => true,
            ]);

            $attributes = $this->_mergeAttributes([
                'class' => ['footnote-item'],
                'data-footnote-id' => $footnote->id,
                'id' => $footnote->anchorId,
                'role' => 'doc-endnote',
            ], $itemAttributes);
            $items .= Html::tag('li', $backlinkGroup . (string)$footnote->html, $attributes);
        }

        $list = Html::tag('ol', $items, $listAttributes);
        $section = Html::tag('section', $list, [
            'class' => ['footnotes'],
            'data-footnotes' => true,
            'role' => 'doc-endnotes',
            'aria-label' => Craft::t('footnotes', 'Footnotes'),
        ]);

        return new Markup($section, 'UTF-8');
    }

    public function getHasNotes(): bool
    {
        return !empty($this->items);
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }


    // Private Methods
    // =========================================================================

    private function _replaceReferences(string $body, array $definitions): string
    {
        $references = $this->documents->findReferences($body);

        if (!$references) {
            return $body;
        }

        $output = '';
        $offset = 0;

        foreach ($references as $referenceIndex => $reference) {
            $output .= substr($body, $offset, $reference['start'] - $offset);

            $attributes = $reference['attributes'];
            $noteId = trim((string)($attributes['data-footnote-id'] ?? ''));
            $referenceId = trim((string)($attributes['data-footnote-reference-id'] ?? ''));

            if ($noteId === '') {
                $noteId = 'legacy-' . $this->instance . '-' . ($referenceIndex + 1);
            }

            if ($referenceId === '') {
                $referenceId = $noteId . '-' . ($referenceIndex + 1);
            }

            $itemKey = $this->instance . ':' . $noteId;
            $definition = $definitions[$noteId] ?? [
                'html' => (string)($attributes['data-footnote-text'] ?? ''),
                'text' => (string)($attributes['data-footnote-text'] ?? ''),
            ];
            $footnote = $this->_footnote($itemKey, $noteId, $definition);
            $referenceAnchorId = $this->_uniqueAnchorId($this->_anchorId('fnref', $referenceId));
            $referenceModel = new FootnoteReference(
                $referenceId,
                $referenceAnchorId,
                $footnote->anchorId,
                $this->_referenceLabel(count($footnote->getReferences()) + 1),
            );
            $footnote->addReference($referenceModel);

            $superscriptAttributes = $this->_mergeAttributes($attributes, [
                'class' => ['footnote', 'footnote-reference'],
                'data-footnote-reference' => true,
                'data-footnote-id' => $noteId,
                'data-footnote-reference-id' => $referenceId,
            ]);
            unset($superscriptAttributes['id'], $superscriptAttributes['title']);

            $anchorAttributes = $this->_mergeAttributes([
                'id' => $referenceAnchorId,
                'href' => '#' . $footnote->anchorId,
                'role' => 'doc-noteref',
                'aria-label' => Craft::t('footnotes', 'Footnote {number}', ['number' => $footnote->number]),
            ], $this->options['referenceAttributes'] ?? []);
            $anchor = Html::tag('a', (string)$footnote->number, $anchorAttributes);
            $output .= Html::tag('sup', $anchor, $superscriptAttributes);
            $offset = $reference['start'] + $reference['length'];
        }

        return $output . substr($body, $offset);
    }

    private function _footnote(string $itemKey, string $noteId, array $definition): Footnote
    {
        if (isset($this->itemIndexes[$itemKey])) {
            return $this->items[$this->itemIndexes[$itemKey]];
        }

        $number = count($this->items) + 1;
        $anchorId = $this->_uniqueAnchorId($this->_anchorId('fn', $noteId));
        $footnote = new Footnote(
            $noteId,
            $number,
            (string)($definition['html'] ?? ''),
            (string)($definition['text'] ?? ''),
            $anchorId,
        );
        $this->itemIndexes[$itemKey] = count($this->items);
        $this->items[] = $footnote;

        return $footnote;
    }

    private function _anchorId(string $prefix, string $id): string
    {
        $scope = trim((string)($this->options['scope'] ?? ''));
        $value = $scope !== '' ? $scope . '-' . $id : $id;
        $value = preg_replace('/[^a-zA-Z0-9_.:-]+/', '-', $value) ?? '';

        return $prefix . '-' . trim($value, '-');
    }

    private function _uniqueAnchorId(string $anchorId): string
    {
        $candidate = $anchorId;
        $suffix = 2;

        while (isset($this->usedAnchorIds[$candidate])) {
            $candidate = $anchorId . '-' . $suffix++;
        }

        $this->usedAnchorIds[$candidate] = true;

        return $candidate;
    }

    private function _referenceLabel(int $number): string
    {
        $label = '';

        while ($number > 0) {
            $number--;
            $label = chr(97 + ($number % 26)) . $label;
            $number = intdiv($number, 26);
        }

        return $label;
    }

    private function _mergeAttributes(array $base, mixed $custom): array
    {
        if (!is_array($custom)) {
            return $base;
        }

        foreach ($custom as $name => $value) {
            if ($name === 'class') {
                $base['class'] = array_values(array_unique(array_filter(array_merge(
                    preg_split('/\s+/', trim(implode(' ', (array)($base['class'] ?? [])))) ?: [],
                    preg_split('/\s+/', trim(implode(' ', (array)$value))) ?: [],
                ))));
            } else {
                $base[$name] = $value;
            }
        }

        return $base;
    }
}
