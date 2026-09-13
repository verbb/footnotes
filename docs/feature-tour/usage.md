# Usage
Once Footnotes has been installed, in the Craft CMS control panel, navigate to any CKEditor field in your Craft settings (Settings > Fields).

For the **CKEditor Config** setting, either create a new config, or edit an existing one. You'll see a button with the Footnotes icon (an asterisk) as an available button to add to your toolbar. Drag and drop it to the appropriate position in your toolbar.

## Render Your Content
When rendering the CKEditor field containing footnotes, you'll need to use the `footnotes` Twig filter.

```twig
{{ entry.myContentField | footnotes }}
```

## Render Footnotes
Then, you'll likely want to render the footnotes at the bottom of the page, or near your content. Using the Twig function `footnotes()` will return an array of footnotes (those that have been collected using the `footnotes` filter above). Each footnote is indexed by its `number`.

```twig
<ul>
    {% for number, footnote in footnotes() %}
        <li>{{ number }} {{ footnote }}</li>
    {% endfor %}
</ul>
```

### Anchor Links
When activating the [Enable anchor links](#enable-anchor-links) option on the plugin's settings page, the `number` variable will contain a link whose `href` targets the list row (for example a scoped fragment such as `#footnote-a1b2c3d4e5f6g7h8.1`, or `#footnote-1` when you explicitly pass an empty `anchorScope`—see below).

Use the **`footnotes_items()`** function so each row gets the correct `id` and back-link target (recommended when scoped anchors are enabled):

```twig
<ul>
    {% for item in footnotes_items() %}
        <li id="{{ item.listAnchorId }}">
            {{ item.numberHtml | raw }} {{ item.text }}
        </li>
    {% endfor %}
</ul>
```

The following loop uses unscoped IDs and requires an empty `anchorScope` (see below): `loop.index` matches `footnote-1`, `footnote-2`, etc.

```twig
<ul>
    {% for number, footnote in footnotes() %}
        <li id="footnote-{{ loop.index }}">
            {{ number | raw }} {{ footnote }}
        </li>
    {% endfor %}
</ul>
```

Don't forget to use the `raw` filter for printing the `number` / `numberHtml` because they contain HTML.

### Scoped Anchors (Multiple Bodies on One Page)
By default, each time you run the `footnotes` filter on a field, footnote fragments get a unique prefix so two entries (or main content plus a modal) do not both emit `id="fnref:1"` / `#footnote-1`.

For **stable** URLs (caching, shareable links), pass an explicit scope—often the entry id:

```twig
{{ entry.body | footnotes({ anchorScope: 'entry-' ~ entry.id }) }}
```

To use unscoped `footnote-1` / `fnref:1` behaviour for a single filter call, pass an empty scope:

```twig
{{ entry.body | footnotes({ anchorScope: '' }) }}
```

### Add a Back Button
From here, you might want to add a link that jumps readers back to their position they just came from. Each footnote reference in your text content has an `id` matching `referenceAnchorId` from `footnotes_items()` (for example `fnref:1` or a scoped id).

```twig
<ul>
    {% for item in footnotes_items() %}
        <li id="{{ item.listAnchorId }}">
            {{ item.numberHtml | raw }} {{ item.text }}
            <a href="#{{ item.referenceAnchorId }}">back</a>
        </li>
    {% endfor %}
</ul>
```

### Attributes
You can also include a range of options to assist with rendering:

```twig
{{ entry.myContentField | footnotes({ anchorAttributes: { class: 'some-class' }, superscriptAttributes: { class: 'another-class' } }) }}
```

## Render an Article with Return Links

For an entry with a CKEditor field called `body`, add two footnotes in the editor and save the entry. Enable anchor links in the plugin settings. Place this assembled example in the entry's Twig template:

```twig
<article>
    {{ entry.body | footnotes({ anchorScope: 'entry-' ~ entry.id }) }}
</article>

<ol>
    {% for item in footnotes_items() %}
        <li id="{{ item.listAnchorId }}">
            {{ item.text }}
            <a href="#{{ item.referenceAnchorId }}">Return to Reference</a>
        </li>
    {% endfor %}
</ol>
```

The filter collects footnotes while rendering the article, so it must run before the list. Click each reference in the article and then its return link. Each pair should lead to the corresponding note and its original position in the text.
