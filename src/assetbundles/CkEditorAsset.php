<?php
namespace verbb\footnotes\assetbundles;

use craft\web\AssetBundle;

use craft\ckeditor\web\assets\BaseCkeditorPackageAsset;

class CkEditorAsset extends BaseCkeditorPackageAsset
{
    private static bool $_packageRegistered = false;

    // Properties
    // =========================================================================

    public string $namespace = '@verbb/ckeditor5-footnotes';


    // Public Methods
    // =========================================================================

    public function init(): void
    {
        $this->sourcePath = '@verbb/footnotes/resources/ckeditor/dist/browser';

        $this->pluginNames = [
            'Footnotes',
        ];

        $this->toolbarItems = [
            'footnotes',
        ];

        $this->js = [
            ['index.js', 'type' => 'module'],
        ];

        $this->css = [
            'index.css',
        ];

        parent::init();
    }

    public function registerPackage(): void
    {
        if (self::$_packageRegistered) {
            return;
        }

        self::$_packageRegistered = true;

        parent::registerPackage();
    }
}
