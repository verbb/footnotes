# Configuration

You can customise Footnotes’ settings using a PHP configuration file. This is optional: each setting has a default, so you only need to include the values you want to change.

To override a setting, create `footnotes.php` in your Craft project’s `/config` directory and return an array of setting names and values. For example, the following will display reference numbers in brackets:

```php
<?php

return [
    'referenceStyle' => 'brackets',
];
```

All other settings keep their defaults. Add any further settings you want to change to the same array. The options below explain the available settings and their defaults. You can also manage these settings in the control panel under **Settings → Footnotes**.

## Configuration Options

::: reference
### `referenceStyle`

**Type:** `string` · **Default:** `'plain'`

Controls how in-text reference numbers appear in the control panel and frontend output. Use `'plain'` for `1` or `'brackets'` for `[1]`.

Footnotes stores the number itself and adds brackets when the field is displayed. Changing the setting therefore updates existing entries without resaving them. A collection can override the plugin default with its own `referenceStyle` option.
:::

::: reference
### `enableAnchorLinks`

**Type:** `bool` · **Default:** `false`

Whether to enable `<a>` tags for footnotes.

This setting applies to inline notes processed for GraphQL's `numberMarkup` value and to the Twig helpers kept for [upgrading templates](upgrading-from-v6.md#deprecated-twig-helpers). CKEditor footnotes and the collection API always produce linked, accessible references and backlinks.
:::

::: reference
### `enableDuplicateFootnotes`

**Type:** `bool` · **Default:** `false`

Whether inline notes with identical content share one footnote when processed for GraphQL or by the Twig helpers kept for [upgrading templates](upgrading-from-v6.md#deprecated-twig-helpers). CKEditor footnotes use identity instead: two separately created notes remain separate even when their content matches, while copied references can point to the same definition.
:::
