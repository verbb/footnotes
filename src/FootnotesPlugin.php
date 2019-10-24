<?php

namespace vierbeuter\footnotes;

use Craft;
use craft\base\Model;
use craft\base\Plugin;
use craft\helpers\UrlHelper;
use craft\web\View;
use vierbeuter\footnotes\assetbundles\redactor\FootnotesRedactorBundle;
use vierbeuter\footnotes\models\FootnotesSettings;
use vierbeuter\footnotes\services\FootnotesService;
use vierbeuter\footnotes\twigextensions\FootnotesTwigExtension;

/**
 * The FootnotesPlugin class represents the Craft CMS plugin for enabling footnotes in rich-text fields.
 *
 * @property \vierbeuter\footnotes\services\FootnotesService $footnotes
 *
 * @package vierbeuter\footnotes
 */
class FootnotesPlugin extends Plugin
{

    /**
     * Initializes the plugin.
     *
     * If you override this method, please make sure you call the parent implementation.
     *
     * @throws \yii\base\InvalidConfigException
     */
    public function init()
    {
        parent::init();

        //  we don't only have settings, we also have a settings page --> tell Craft CMS we wanna use the plugin settings page
        /** @see \vierbeuter\footnotes\FootnotesPlugin::settingsHtml() */
        $this->hasCpSettings = true;

        //  register services
        $this->setComponents([
            'footnotes' => FootnotesService::class,
        ]);

        //  register Twig extensions (usable in site templates as well as in CP templates like field templates or dashboard widgets etc.)
        Craft::$app->getView()->registerTwigExtension(new FootnotesTwigExtension());

        //  load JS file to add the footnote plugin to Redactor
        if (Craft::$app->getRequest()->getIsCpRequest() && !empty(Craft::$app->getUser()->getIdentity())) {
            Craft::$app->getView()->registerAssetBundle(FootnotesRedactorBundle::class);
        }
    }

    /**
     * Creates and returns the model used to store the plugin’s settings.
     *
     * @return \craft\base\Model|null
     */
    protected function createSettingsModel(): Model
    {
        return new FootnotesSettings();
    }

    /**
     * Returns the rendered settings HTML, which will be inserted into the content block on the settings page.
     *
     * @return string|null The rendered settings HTML
     *
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     */
    protected function settingsHtml(): string
    {
        return Craft::$app->getView()->renderTemplate('footnotes/settings', [
            'settings' => $this->getSettings(),
        ]);
    }
}
