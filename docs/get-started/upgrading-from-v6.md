# Upgrading from Footnotes 6

Footnotes keeps existing content and Twig templates working while adding document-native editing and a local collection API. The release is non-breaking, so you can update the plugin before changing templates. The changes below explain what to review and which optional improvements are available.

## Breaking Changes

There are no intended breaking changes. Existing CKEditor and Redactor footnote markup remains readable, and the `footnotes` filter plus the `footnotes()`, `footnotes_items()`, `footnotes_exist()`, and `footnotes_set()` functions remain available through the compatibility layer.

## Editor Behaviour

Opening a field with inline Footnotes content presents each marker as a numbered reference and moves its definition into the footnotes region at the end of the same CKEditor document. Saving writes the references and definitions as self-contained HTML. Supported formatting, links, paragraphs, and simple lists remain part of the definition rather than being flattened to plain text.

Review an existing entry by opening its CKEditor field, selecting a reference, and confirming that the caret moves to the expected definition. Save and reload the entry, then check that the ordinary field output still contains the reference, definition, and return link.

Canonical notes keep their full UUIDs in `data-footnote-id` and `data-footnote-reference-id`, but generated public fragment IDs use a compact stable token such as `#fn-0000000001`. Existing Footnotes 6 content and non-UUID fragment identities remain readable. If pre-release 6.1 templates or scripts were written against full UUID-shaped fragments, target the semantic classes or data attributes instead of the fragment format.

## Deprecated Twig Helpers

The request-global Twig API still works but records Craft deprecation warnings when called. There is no scheduled removal date. New and revised templates should either render the complete field value directly or use `craft.footnotes.collection()` for relocated or combined output.

For a normal article, the template can render the field without Footnotes-specific processing:

::: code-group
```twig [Footnotes 6]
<article>
    {{ entry.body | footnotes }}
</article>

{% if footnotes_exist() %}
    <ol>
        {% for number, footnote in footnotes() %}
            <li>{{ number | raw }} {{ footnote | raw }}</li>
        {% endfor %}
    </ol>
{% endif %}
```

```twig [Footnotes 6.1]
<article>
    {{ entry.body }}
</article>
```
:::

If the design places definitions outside the article, replace the global helpers with a local collection:

::: code-group
```twig [Footnotes 6]
<article>
    {{ entry.body | footnotes({ anchorScope: 'article-' ~ entry.id }) }}
</article>

{% if footnotes_exist() %}
    <ol>
        {% for item in footnotes_items() %}
            <li id="{{ item.listAnchorId }}">
                {{ item.text | raw }}
                <a href="#{{ item.referenceAnchorId }}">Return</a>
            </li>
        {% endfor %}
    </ol>
{% endif %}
```

```twig [Footnotes 6.1]
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
:::

The collection is deliberately local to the Twig variable. Several `add()` calls on one collection share numbering, while separate collection instances remain independent. It has no seed operation; templates that previously called `footnotes_set([...])` can keep doing so until their seeded values are represented by actual content passed to `add()`.

## Settings Behaviour

`enableAnchorLinks` and `enableDuplicateFootnotes` continue to control the classic Twig and GraphQL compatibility processing. Document-native notes and collection output always use linked references, stable note identities, and a backlink for every occurrence. Identical text does not merge separately created canonical notes.

After adopting the collection API, check **Utilities → Deprecation Warnings** for remaining `verbb.footnotes.*` entries. Each warning identifies a compatibility helper that can be updated independently.
