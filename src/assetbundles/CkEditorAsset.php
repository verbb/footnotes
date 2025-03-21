<?php
namespace verbb\footnotes\assetbundles;

use craft\web\AssetBundle;

use craft\ckeditor\web\assets\BaseCkeditorPackageAsset;

class CkEditorAsset extends BaseCkeditorPackageAsset
{
    // Public Methods
    // =========================================================================

    public function init(): void
    {
        $this->sourcePath = '@verbb/footnotes/resources/ckeditor/build';

        $this->pluginNames = [
            'Footnotes',
        ];

        $this->toolbarItems = [
            'footnotes',
        ];

        $this->js = [
            'footnotes.js',
        ];

        parent::init();
    }
}
