# Configuration

You can customise Footnotes’s settings using a PHP configuration file. This is optional: each setting has a default, so you only need to include the values you want to change.

To override a setting, create `footnotes.php` in your Craft project’s `/config` directory and return an array of setting names and values. For example, the following will enable footnote anchor links:

```php
<?php

return [
    'enableAnchorLinks' => true,
];
```

All other settings keep their defaults. Add any further settings you want to change to the same array. The options below explain the available settings and their defaults.

## Configuration Options

::: reference
### `enableAnchorLinks`

**Type:** `bool` · **Default:** `false`

Whether to enable `<a>` tags for footnotes.
:::

::: reference
### `enableDuplicateFootnotes`

**Type:** `bool` · **Default:** `false`

Whether duplicate footnotes should combine, or keep separate.
:::


## Control Panel
You can also manage configuration settings through the Control Panel by visiting Settings → Footnotes.
