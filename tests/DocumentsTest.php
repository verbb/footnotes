<?php

declare(strict_types=1);

use verbb\footnotes\services\Documents;

function canonicalDocument(string $body = 'Body'): string
{
    return '<p>' . $body . '<sup class="footnote footnote-reference" data-footnote-reference data-footnote-id="note-a" data-footnote-reference-id="reference-a"><a href="#fn-note-a">1</a></sup></p>'
        . '<section class="footnotes" data-footnotes role="doc-endnotes"><ol><li class="footnote-item" data-footnote-id="note-a"><p><em>Rich</em> definition</p><ul><li>Nested item</li></ul><a data-footnote-backlink href="#fnref-reference-a">↩</a></li></ol></section>';
}

it('collects legacy inline footnotes', function() {
    $collection = (new Documents())->collection(['scope' => 'article']);
    $body = $collection->add('<p>Body<sup class="footnote"><em>Source</em></sup></p>');

    expect((string)$body)
        ->toContain('role="doc-noteref"')
        ->not->toContain('<em>Source</em>')
        ->and($collection)->toHaveCount(1)
        ->and((string)$collection->render())
        ->toContain('<em>Source</em>')
        ->toContain('role="doc-backlink"');
});

it('splits canonical definitions without flattening nested lists', function() {
    $collection = (new Documents())->collection(['scope' => 'article']);
    $body = $collection->add(canonicalDocument());
    $rendered = (string)$collection->render();

    expect((string)$body)
        ->toContain('<p>Body')
        ->not->toContain('data-footnotes')
        ->and($rendered)
        ->toContain('<p><em>Rich</em> definition</p>')
        ->toContain('<ul><li>Nested item</li></ul>')
        ->and($collection->getIterator()->current()->text)
        ->toBe('Rich definition Nested item');
});

it('recognizes canonical content after restrictive purifier settings remove data attributes', function() {
    $html = '<p>Body<sup class="footnote footnote-reference"><a id="fnref-reference-a" href="#fn-note-a">1</a></sup></p>'
        . '<ol class="footnotes"><li class="footnote-item" id="fn-note-a"><p><em>Rich</em> definition</p><a class="footnote-backlink" href="#fnref-reference-a">↩</a></li></ol>';
    $collection = (new Documents())->collection(['scope' => 'article']);
    $body = $collection->add($html);
    $rendered = $collection->render();

    expect((string)$body)
        ->toContain('id="fnref-article-reference-a"')
        ->not->toContain('<ol class="footnotes">')
        ->and((string)$rendered)
        ->toContain('<em>Rich</em> definition')
        ->not->toContain('footnote-backlink" href="#fnref-reference-a"');
});

it('uses anchor fallback text when a copied canonical definition is unavailable', function() {
    $html = '<p>Body<sup class="footnote footnote-reference"><a id="fnref-reference-a" href="#fn-note-a" data-footnote-text="Copied definition">1</a></sup></p>';
    $collection = (new Documents())->collection(['scope' => 'article']);
    $body = $collection->add($html);

    expect((string)$body)
        ->toContain('id="fnref-article-reference-a"')
        ->and((string)$collection->render())
        ->toContain('Copied definition');
});

it('uses one numbering sequence across added bodies', function() {
    $collection = (new Documents())->collection();
    $first = $collection->add('<p>One<sup class="footnote">First</sup></p>');
    $second = $collection->add('<p>Two<sup class="footnote">Second</sup></p>');

    expect((string)$first)->toContain('>1</a>')
        ->and((string)$second)->toContain('>2</a>')
        ->and($collection)->toHaveCount(2);
});

it('keeps repeated canonical references attached to one definition', function() {
    $html = '<p>First<sup class="footnote footnote-reference"><a id="fnref-reference-a" href="#fn-note-a">1</a></sup> and again<sup class="footnote footnote-reference"><a id="fnref-reference-b" href="#fn-note-a">1</a></sup>.</p>'
        . '<ol class="footnotes"><li class="footnote-item" id="fn-note-a"><p>Shared definition</p></li></ol>';
    $collection = (new Documents())->collection(['scope' => 'article']);
    $collection->add($html);
    $footnote = $collection->getIterator()->current();

    expect($collection)->toHaveCount(1)
        ->and($footnote->getReferences())->toHaveCount(2)
        ->and((string)$collection->render())
        ->toContain('reference 1')
        ->toContain('reference 2');
});

it('keeps matching identities from separate bodies independent', function() {
    $collection = (new Documents())->collection(['scope' => 'page']);
    $collection->add(canonicalDocument('First'));
    $collection->add(canonicalDocument('Second'));

    expect($collection)->toHaveCount(2)
        ->and((string)$collection->render())
        ->toContain('id="fn-page-note-a"')
        ->toContain('id="fn-page-note-a-2"');
});

it('gives repeated render instances unique deterministic anchors', function() {
    $collection = (new Documents())->collection(['scope' => 'modal']);
    $first = (string)$collection->add(canonicalDocument());
    $second = (string)$collection->add(canonicalDocument());

    expect($first)->toContain('id="fnref-modal-reference-a"')
        ->and($second)->toContain('id="fnref-modal-reference-a-2"')
        ->and($collection)->toHaveCount(2);
});

it('keeps identical legacy text as separate canonical notes', function() {
    $collection = (new Documents())->collection();
    $collection->add('<p>One<sup class="footnote">Ibid.</sup> Two<sup class="footnote">Ibid.</sup></p>');

    expect($collection)->toHaveCount(2);
});

it('applies encoded rendering attributes while retaining semantic defaults', function() {
    $collection = (new Documents())->collection([
        'referenceAttributes' => ['class' => 'scroll-link', 'data-scroll' => '<enabled>'],
    ]);
    $body = $collection->add(canonicalDocument());
    $rendered = $collection->render([
        'listAttributes' => ['class' => 'custom-list'],
        'backlinkAttributes' => ['data-scroll' => '<back>'],
    ]);

    expect((string)$body)
        ->toContain('class="scroll-link"')
        ->toContain('data-scroll="&lt;enabled&gt;"')
        ->and((string)$rendered)
        ->toContain('class="footnotes-list custom-list"')
        ->toContain('data-scroll="&lt;back&gt;"');
});

it('leaves malformed canonical markup unchanged', function() {
    $documents = new Documents();
    $html = '<p>Body<sup data-footnote-reference data-footnote-id="broken">1</p>';
    $fragment = $documents->split($html);

    expect($fragment['body'])->toBe($html)
        ->and($fragment['definitions'])->toBe([]);
});

it('handles a large legacy document in one collection', function() {
    $html = '<p>' . implode(' ', array_map(
        fn(int $number) => 'Reference ' . $number . '<sup class="footnote">Definition ' . $number . '</sup>',
        range(1, 1000),
    )) . '</p>';
    $collection = (new Documents())->collection(['scope' => 'large']);
    $body = $collection->add($html);

    expect($collection)->toHaveCount(1000)
        ->and((string)$body)
        ->toContain('id="fnref-large-legacy-')
        ->toContain('>1000</a>');
});
