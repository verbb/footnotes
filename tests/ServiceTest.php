<?php

declare(strict_types=1);

use verbb\footnotes\models\Settings;
use verbb\footnotes\services\Service;
use verbb\footnotes\web\twig\Extension;

function legacyFootnotesService(array $settings = []): Service
{
    $reflection = new ReflectionClass(Service::class);
    $service = $reflection->newInstanceWithoutConstructor();
    $settingsProperty = $reflection->getProperty('settings');
    $settingsProperty->setValue($service, new Settings($settings));
    $service->set();

    return $service;
}

it('keeps every legacy Twig helper registered', function() {
    $extension = new Extension();
    $filters = array_map(fn($filter) => $filter->getName(), $extension->getFilters());
    $functions = array_map(fn($function) => $function->getName(), $extension->getFunctions());

    expect($filters)
        ->toBe(['footnotes'])
        ->and($functions)
        ->toBe([
            'footnotes_exist',
            'footnotes_set',
            'footnotes',
            'footnotes_items',
        ]);
});

it('keeps seeded notes and several legacy filter calls in one sequence', function() {
    $service = legacyFootnotesService();
    $service->set(['Seeded definition']);

    $first = $service->filter('<p>First<sup class="footnote">First definition</sup></p>', ['anchorScope' => 'first']);
    $second = $service->filter('<p>Second<sup class="footnote">Second definition</sup></p>', ['anchorScope' => 'second']);
    $items = $service->getItems();

    expect($first)
        ->toContain('<sup class="footnote">2</sup>')
        ->and($second)
        ->toContain('<sup class="footnote">3</sup>')
        ->and($service->exist())
        ->toBeTrue()
        ->and($items)
        ->toHaveCount(3)
        ->and($items[0]['text'])
        ->toBe('Seeded definition')
        ->and($items[1]['referenceAnchorId'])
        ->toBe('fnref:first.2')
        ->and($items[2]['listAnchorId'])
        ->toBe('footnote-second.3');
});

it('keeps GraphQL parsing isolated from the legacy request collection', function() {
    $service = legacyFootnotesService([
        'enableAnchorLinks' => true,
        'referenceStyle' => Settings::REFERENCE_STYLE_BRACKETS,
    ]);
    $service->set(['Existing definition']);

    $result = $service->parseForGraphql(
        '<p>Body<sup class="footnote"><em>GraphQL definition</em></sup></p>',
        ['anchorScope' => 'article'],
    );

    expect($result['html'])
        ->toContain('id="fnref:article.1"')
        ->toContain('>[1]</a>')
        ->and($result['items'])
        ->toHaveCount(1)
        ->and($result['items'][0]['text'])
        ->toBe('<em>GraphQL definition</em>')
        ->and($result['items'][0]['numberMarkup'])
        ->toContain('>[1]</a>')
        ->and($service->getItems())
        ->toHaveCount(1)
        ->and($service->getItems()[0]['text'])
        ->toBe('Existing definition');
});

it('keeps duplicate compatibility behaviour configurable', function() {
    $combined = legacyFootnotesService();
    $separate = legacyFootnotesService(['enableDuplicateFootnotes' => true]);
    $html = '<p>One<sup class="footnote">Same source</sup> two<sup class="footnote">Same source</sup></p>';

    expect($combined->filter($html, ['anchorScope' => 'combined']))
        ->toContain('>1</sup> two<sup class="footnote">1</sup>')
        ->and($combined->getItems())
        ->toHaveCount(1)
        ->and($separate->filter($html, ['anchorScope' => 'separate']))
        ->toContain('>1</sup> two<sup class="footnote">2</sup>')
        ->and($separate->getItems())
        ->toHaveCount(2);
});
