# Rendering Footnotes

Ordinary CKEditor field output contains its references and definitions, so `{{ entry.body }}` is the right starting point for most templates. Use a footnote collection when the page design needs to separate those parts, combine several content bodies into one numbered list, or render custom markup.

A collection belongs to the local Twig variable that receives it. It has no request-global state, so separate collections form separate numbered groups and several `add()` calls on one collection deliberately share a sequence.

## Move Definitions below an Article

Suppose an entry has a CKEditor field with the handle `body`. Create the collection before rendering that field, then pass the field value to `add()` where the article body should appear:

```twig
{% set footnotes = craft.footnotes.collection({
    scope: 'article-' ~ entry.id,
}) %}

<article>
    {{ footnotes.add(entry.body) }}
</article>

{% if footnotes.hasNotes %}
    {{ footnotes.render() }}
{% endif %}
```

`add()` returns safe body HTML with the stored definitions region removed. It also adds that body's notes to the collection. `render()` returns the accessible ordered definitions markup, including every backlink for a definition used more than once.

The `scope` option prefixes generated IDs so references remain unique when the same entry appears elsewhere on the page, such as in a modal or related-content card. Use a stable value based on the surrounding component or entry when fragment URLs or cached HTML need deterministic IDs.

Footnotes keeps full UUIDs in its `data-footnote-id` and `data-footnote-reference-id` attributes as stable internal identities. Public fragment IDs use only a deterministic 10-character UUID token, producing readable targets such as `#fn-0000000001` and `#fnref-0000000002`. A collection scope prefixes that compact token, for example `#fn-article-42-0000000001`. Existing non-UUID and legacy fragment identities remain supported unchanged.

## Combine Several Content Bodies

Call `add()` for each field or entry that should contribute to the same list. The following page has a main article and a curator's sidebar, then renders one list after both:

```twig
{% set footnotes = craft.footnotes.collection({
    scope: 'exhibition-' ~ entry.id,
}) %}

<article>
    {{ footnotes.add(entry.body) }}
</article>

<aside>
    {{ footnotes.add(entry.curatorNotes) }}
</aside>

{% if footnotes.hasNotes %}
    {{ footnotes.render() }}
{% endif %}
```

Numbering follows the order of the `add()` calls and the reference order within each value. Note identities are local to each added body, so two fields cannot accidentally share a definition because their stored identifiers happen to match.

To create independent groups, create independent collections:

```twig
{% set articleFootnotes = craft.footnotes.collection({ scope: 'article-' ~ entry.id }) %}
{% set sidebarFootnotes = craft.footnotes.collection({ scope: 'sidebar-' ~ entry.id }) %}

{{ articleFootnotes.add(entry.body) }}
{{ articleFootnotes.render() }}

{{ sidebarFootnotes.add(entry.curatorNotes) }}
{{ sidebarFootnotes.render() }}
```

Each group starts at 1 and uses its own anchor scope.

## Customise Generated Attributes

Pass `referenceAttributes` when creating the collection to add encoded attributes to each in-text reference link. Pass list, item, and backlink attributes to `render()` when producing the definitions:

```twig
{% set footnotes = craft.footnotes.collection({
    scope: 'article-' ~ entry.id,
    referenceAttributes: {
        class: 'js-footnote-reference',
        'data-scroll': 'smooth',
    },
}) %}

{{ footnotes.add(entry.body) }}

{{ footnotes.render({
    listAttributes: {
        class: 'article-footnotes',
    },
    itemAttributes: {
        class: 'article-footnote',
    },
    backlinkAttributes: {
        class: 'js-footnote-backlink',
        'data-scroll': 'smooth',
    },
}) }}
```

Custom classes are added to Footnotes' semantic classes rather than replacing them. Attribute values are HTML-encoded. Stable Footnotes classes, data markers, roles, accessible labels, IDs, and link targets remain present so the result keeps its document structure.

## Render a Custom List

The collection is iterable when the design needs complete control over the definitions markup. Add every content body before starting the loop:

```twig
{% set footnotes = craft.footnotes.collection({ scope: 'article-' ~ entry.id }) %}

<article>
    {{ footnotes.add(entry.body) }}
</article>

{% if footnotes.hasNotes %}
    <ol class="source-notes">
        {% for note in footnotes %}
            <li id="{{ note.anchorId }}">
                <span class="source-notes__number">{{ note.number }}</span>
                <span class="source-notes__backlinks">
                    {% if note.references|length > 1 %}↑{% endif %}
                    {% for reference in note.references %}
                        <a href="#{{ reference.anchorId }}" aria-label="Return to reference {{ loop.index }}">{{ note.references|length > 1 ? reference.label : '↑' }}</a>
                    {% endfor %}
                </span>
                <div class="source-notes__definition">{{ note.html }}</div>
            </li>
        {% endfor %}
    </ol>
{% endif %}
```

`note.html` is safe rich-definition markup. It doesn't need Twig's `raw` filter. The other values are plain strings or numbers and remain escaped through normal Twig output.

The default renderer follows the familiar Wikipedia backlink placement: a compact `.footnote-backlinks` group appears before each rich definition, using a linked `↑` for one reference or a shared arrow followed by lettered return links (`↑ a b`) when several references share the note. The ordered-list marker remains the note's only visible number, while the letters distinguish the places a reader can return to. The links retain `role="doc-backlink"` and descriptive accessible labels.

Footnotes doesn't add frontend styles. A minimal treatment can keep the backlink group beside the first line while allowing the rest of a rich, multi-block definition to flow normally:

```css
.footnote-item {
    padding-inline-start: 1em;
}

.footnote-item::after {
    clear: both;
    content: '';
    display: block;
}

.footnote-backlinks {
    float: inline-start;
    font-size: 0.75em;
    font-weight: 700;
    margin-inline-end: 0.5em;
    white-space: nowrap;
}

.footnote-backlink {
    text-decoration: none;
}
```

## Collection API

::: reference
### `craft.footnotes.collection(options = {})`

**Returns:** `FootnoteCollection`

Creates an empty local collection. `options.scope` sets a deterministic anchor prefix, while `options.referenceAttributes` adds encoded attributes to generated reference links. The collection has no seed argument; call `add()` for every value you want it to process.
:::

::: reference
### `collection.add(value)`

**Accepts:** CKEditor or Redactor field data, an HTML string, `null`, or an empty string · **Returns:** safe Twig markup

Returns the body's reference-bearing HTML without its definitions region and adds the discovered notes to the collection. `null` and an empty string return empty markup without changing the collection. Other value types raise an error so an incorrect template input does not silently disappear.
:::

::: reference
### `collection.hasNotes`

**Type:** `bool` · **Read-only**

Reports whether any previous `add()` call contributed a note. Use it to avoid rendering an empty list wrapper.
:::

::: reference
### `collection.render(options = {})`

**Returns:** safe Twig markup

Renders the collected notes with accessible references and backlinks. The optional `listAttributes`, `itemAttributes`, and `backlinkAttributes` arrays add encoded attributes to their respective elements. Calling `render()` on an empty collection returns empty markup.
:::

::: reference
### `note`

Each iterated note exposes `id` (stored identity), `number`, `html` (safe rich markup), `text` (plain text), `anchorId` (the definition target), and `references` (the ordered occurrences that point to it).
:::

::: reference
### `reference`

Each item in `note.references` exposes `id` (stored occurrence identity), `anchorId` (the in-text reference target used by a backlink), `backlinkTarget` (the note definition target), and `label` (`a`, `b`, through `aa` and beyond) for distinguishing repeated-reference backlinks.
:::
