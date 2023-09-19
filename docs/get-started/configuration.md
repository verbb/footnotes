# Configuration
Create a `footnotes.php` file under your `/config` directory with the following options available to you. You can also use multi-environment options to change these per environment.

The below shows the defaults already used by Footnotes, so you don't need to add these options unless you want to modify the values.

```php
<?php

return [
    '*' => [
        'enableAnchorLinks' => false,
        'enableDuplicateFootnotes' => false,
    ],
];
```

## Configuration options
- `enableAnchorLinks` - Whether to enable `<a>` tags for footnotes.
- `enableDuplicateFootnotes` - Whether duplicate footnotes should combine, or keep separate.

## Control Panel
You can also manage configuration settings through the Control Panel by visiting Settings → Footnotes.
