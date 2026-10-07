# Upgrading from Footnotes 6

Footnotes keeps existing content and Twig templates working while adding rich editing and flexible Twig rendering. The release is non-breaking, so you can update the plugin before changing templates. The changes below explain what to review and which optional improvements are available.

## Breaking Changes

There are no intended breaking changes. Existing CKEditor and Redactor footnote markup remains readable, and the `footnotes` filter plus the `footnotes()`, `footnotes_items()`, `footnotes_exist()`, and `footnotes_set()` functions remain available. These helpers now record Craft deprecation warnings when used.

## Editor Behaviour

Opening a field with inline Footnotes content presents each marker as a numbered reference and moves its definition into the footnotes region at the end of the same CKEditor document. Saving writes the references and definitions as self-contained HTML. Supported formatting, links, paragraphs, and simple lists remain part of the definition rather than being flattened to plain text.

Review an existing entry by opening its CKEditor field, selecting a reference, and confirming that the caret moves to the expected definition. Save and reload the entry, then check that the ordinary field output still contains the reference, definition, and return link.

Generated fragment IDs use compact targets such as `#fn-0000000001`. Existing Footnotes 6 content and fragment links remain readable. Custom scripts and styles should target Footnotes classes or data attributes rather than relying on the exact fragment format.

## Deprecated Twig Helpers

The older Twig API shares one footnote list across the request. It still works but records Craft deprecation warnings when called, and there is no scheduled removal date. New and revised templates should either render the complete field value directly or use `craft.footnotes.collection()` for relocated or combined output.

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

If the design places definitions outside the article, replace the global helpers with a collection:

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
    <ol>
        {% for item in footnotes %}
            {% set reference = item.references | first %}

            <li id="{{ item.anchorId }}">
                {{ item.html }}
                <a href="#{{ reference.anchorId }}">Return</a>
            </li>
        {% endfor %}
    </ol>
{% endif %}
```
:::

The collection is deliberately local to the Twig variable. Several `add()` calls on one collection share numbering, while separate collection instances remain independent. It has no seed operation; templates that previously called `footnotes_set([...])` can keep doing so until their seeded values are represented by actual content passed to `add()`.

## Settings Behaviour

`enableAnchorLinks` and `enableDuplicateFootnotes` continue to control the classic Twig and GraphQL compatibility processing. The new `referenceStyle` setting defaults to `'plain'`, preserving the existing `1` marker, while `'brackets'` displays `[1]` in CKEditor and frontend output without rewriting stored field values. Collections can override that default locally. Current CKEditor notes and collection output always use linked references, stable note identities, and a backlink for every occurrence. Identical text does not merge separately created notes.

After adopting the collection API, check **Utilities → Deprecation Warnings** for remaining `verbb.footnotes.*` entries. Each warning identifies a compatibility helper that can be updated independently.
