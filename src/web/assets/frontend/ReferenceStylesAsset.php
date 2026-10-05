<?php
namespace verbb\footnotes\web\assets\frontend;

use craft\web\AssetBundle;

class ReferenceStylesAsset extends AssetBundle
{
    // Public Methods
    // =========================================================================

    public function init(): void
    {
        $this->sourcePath = '@verbb/footnotes/resources/frontend';

        $this->css = [
            'reference-brackets.css',
        ];

        parent::init();
    }
}
