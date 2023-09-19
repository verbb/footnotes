# Usage
Firstly, you'll need to add the Footnotes plugin to your Redactor config. These are located at `config/redactor/*.json`. Add a `footnotebutton` value to the `plugins` array in the config.

```json
{
    "buttons": ["format", "bold", "italic"],
    "plugins": ["source", "fullscreen", "footnotebutton"]
}
```

With this in place, a new button for adding footnotes should appear in Redactor fields (for the Redactor fields that use this config).

## Render your content
When rendering the Redactor field containing footnotes, you'll need to use the `footnotes` Twig filter.

```twig
{{ entry.myRedactorHandle | footnotes }}
```

## Render footnotes
Then, you'll likely want to render the footnotes at the bottom of the page, or neat your content. Using the Twig function `footnotes()` will return an array of footnotes (those that have been collected using the `footnotes` filter above). Each footnote is indexed by its `number`.

```twig
<ul>
    {% for number, footnote in footnotes() %}
        <li>{{ number }} {{ footnote }}</li>
    {% endfor %}
</ul>
```

### Anchor links
When activating the [Enable anchor links](#enable-anchor-links) option on the plugin's settings page, the `number` variable will contain a link like `<a href="#footnote-1">1</a>`. You can get the plain footnote number with [Twig’s `loop` variable](https://twig.symfony.com/doc/2.x/tags/for.html) for usage in the `<li>` element's `id` attribute:

```twig
<ul>
    {% for number, footnote in footnotes() %}
        <li id="footnote-{{ loop.index }}">
            {{ number | raw }} {{ footnote }}
        </li>
    {% endfor %}
</ul>
```

Don't forget to use the `raw` filter for printing the `number` because it contains HTML.

### Add a back button
From here, you might want to add a link that jumps readers back to their position they just came from. Each footnote reference in your text content already comes with an ID, e.g. `fnref:1`, so you can link back to that ID from your footnote.

```twig
<ul>
    {% for number, footnote in footnotes() %}
        <li id="footnote-{{ loop.index }}">
            {{ number | raw }} {{ footnote }}
            <a href="#fnref:{{ loop.index }}">back</a>
        </li>
    {% endfor %}
</ul>
```
