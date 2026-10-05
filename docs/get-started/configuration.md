# Configuration

You can customise Footnotes’s settings using a PHP configuration file. This is optional: each setting has a default, so you only need to include the values you want to change.

To override a setting, create `footnotes.php` in your Craft project’s `/config` directory and return an array of setting names and values. For example, the following will enable footnote anchor links:

```php
<?php

return [
    'enableAnchorLinks' => true,
    'referenceStyle' => 'brackets',
];
```

All other settings keep their defaults. Add any further settings you want to change to the same array. The options below explain the available settings and their defaults.

## Configuration Options

::: reference
### `referenceStyle`

**Type:** `string` · **Default:** `'plain'`

Controls how in-text reference numbers appear in the control panel and frontend output. Use `'plain'` for `1` or `'brackets'` for `[1]`.

Footnotes keeps the stored CKEditor anchor text as the semantic number. When `'brackets'` is selected, it adds only the bracket punctuation at display time, so changing the setting updates existing entries without resaving them. A collection can override the plugin default with its own `referenceStyle` option.
:::

::: reference
### `enableAnchorLinks`

**Type:** `bool` · **Default:** `false`

Whether to enable `<a>` tags for footnotes.

This setting applies to the classic `footnotes` Twig filter and GraphQL's `numberMarkup` value. Document-native content and the collection API always produce linked, accessible references and backlinks.
:::

::: reference
### `enableDuplicateFootnotes`

**Type:** `bool` · **Default:** `false`

Whether classic Twig processing combines footnotes with identical content. Document-native footnotes use identity instead: two separately created notes remain separate even when their content matches, while copied references can point to the same definition.
:::

## Control Panel

You can also manage configuration settings through the control panel by visiting **Settings → Footnotes**. Values in `config/footnotes.php` take precedence over control-panel settings.
