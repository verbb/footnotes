<?php
namespace verbb\footnotes\web\assets\ckeditor;

use verbb\footnotes\Footnotes;

use craft\helpers\Json;
use craft\web\AssetBundle;
use craft\web\View;

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

    public function registerAssetFiles($view): void
    {
        parent::registerAssetFiles($view);

        $settings = Json::encode([
            'referenceStyle' => Footnotes::$plugin->getSettings()->getReferenceStyle(),
        ]);
        $js = <<<JS
            window.Craft = window.Craft || {};
            window.Craft.Footnotes = $settings;
        JS;

        $view->registerJs($js, View::POS_HEAD, 'verbb-footnotes-settings');
    }
}
