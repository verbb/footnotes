<?php

namespace vierbeuter\footnotes;

use Craft;
use craft\base\Model;
use craft\base\Plugin;
use craft\redactor\events\RegisterPluginPathsEvent;
use craft\redactor\Field;
use vierbeuter\footnotes\models\FootnotesSettings;
use vierbeuter\footnotes\services\FootnotesService;
use vierbeuter\footnotes\twigextensions\FootnotesTwigExtension;
use yii\base\Event;

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

        //  register new plugin source for Redactor using one of Redactor's events
        Event::on(Field::class, Field::EVENT_REGISTER_PLUGIN_PATHS, function (RegisterPluginPathsEvent $event) {
            $event->paths[] = Craft::getAlias('@vierbeuter/footnotes/resources/');
        });
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
