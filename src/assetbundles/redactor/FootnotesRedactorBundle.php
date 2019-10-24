<?php

namespace vierbeuter\footnotes\assetbundles\redactor;

use craft\redactor\assets\field\FieldAsset;
use craft\web\AssetBundle;

/**
 * The FootnotesRedactorBundle class represents the Redactor plugin which adds the footnote button to rich-text fields.
 *
 * @package vierbeuter\footnotes\assetbundles\redactor
 */
class FootnotesRedactorBundle extends AssetBundle
{

    /**
     * Initializes the bundle.
     *
     * If you override this method, make sure you call the parent implementation in the last.
     */
    public function init()
    {
        //  define the path that your publishable resources live
        $this->sourcePath = "@vierbeuter/footnotes/assetbundles/redactor/dist";

        //  define the dependencies
        $this->depends = [
            //  FIXME: it's either the wrong dependency or it's just not everything we have to do to register the Redactor plugin --> currently we get an error: `Redactor plugin not found: footnotebutton` because of an Invalid Configuration – yii\base\InvalidConfigException
            FieldAsset::class,
        ];

        //  define the relative path to CSS/JS files that should be registered with the page
        //  when this asset bundle is registered
        $this->js = [
            'footnotebutton.js',
        ];

        parent::init();
    }
}
