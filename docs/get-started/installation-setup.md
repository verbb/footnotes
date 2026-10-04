# Installation & Setup

You can install Footnotes via the plugin store, or through Composer.

## Craft Plugin Store

To install **Footnotes**, navigate to the **Plugin Store** section of your Craft control panel, search for `Footnotes`, and select **Try**.

## Composer

You can also add the package to your project using Composer and the command line.

1. Open your terminal and go to your Craft project:

```shell
cd /path/to/project
```

2. Then tell Composer to require the plugin, and Craft to install it:

```shell
composer require verbb/footnotes && php craft plugin/install footnotes
```

After installation, add the Footnote button to the CKEditor configurations that need it. The [Footnotes in CKEditor](../feature-tour/usage.md) page takes you through the editor workflow and its supported content.
