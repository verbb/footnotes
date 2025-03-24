<?php
namespace verbb\footnotes;

use verbb\footnotes\assetbundles\CkEditorAsset;
use verbb\footnotes\base\PluginTrait;
use verbb\footnotes\helpers\Plugin as PluginHelper;
use verbb\footnotes\models\Settings;
use verbb\footnotes\twigextensions\Extension;

use Craft;
use craft\base\Plugin;
use craft\events\RegisterUrlRulesEvent;
use craft\helpers\UrlHelper;
use craft\web\UrlManager;

use yii\base\Event;

use craft\redactor\events\RegisterPluginPathsEvent;
use craft\redactor\Field;

use craft\ckeditor\Plugin as CkEditor;

class Footnotes extends Plugin
{
    // Properties
    // =========================================================================

    public string $schemaVersion = '1.0.0';
    public bool $hasCpSettings = true;


    // Traits
    // =========================================================================

    use PluginTrait;


    // Public Methods
    // =========================================================================

    public function init(): void
    {
        parent::init();

        self::$plugin = $this;

        $this->_setPluginComponents();
        $this->_setLogging();
        $this->_registerTwigExtensions();
        $this->_registerCpRoutes();

        // Defer most setup tasks until Craft is fully initialized:
        Craft::$app->onInit(function() {
            $this->_registerRedactorPlugins();
            $this->_registerCkEditorPlugins();
        });
    }

    public function getSettingsResponse(): mixed
    {
        return Craft::$app->getResponse()->redirect(UrlHelper::cpUrl('footnotes/settings'));
    }


    // Protected Methods
    // =========================================================================

    protected function createSettingsModel(): Settings
    {
        return new Settings();
    }


    // Private Methods
    // =========================================================================

    private function _registerCpRoutes(): void
    {
        Event::on(UrlManager::class, UrlManager::EVENT_REGISTER_CP_URL_RULES, function(RegisterUrlRulesEvent $event) {
            $event->rules = array_merge($event->rules, [
                'footnotes/settings' => 'footnotes/base/settings',
            ]);
        });
    }

    private function _registerTwigExtensions(): void
    {
        Craft::$app->getView()->registerTwigExtension(new Extension);
    }

    private function _registerRedactorPlugins(): void
    {
        if (PluginHelper::isPluginInstalledAndEnabled('redactor')) {
            Event::on(Field::class, Field::EVENT_REGISTER_PLUGIN_PATHS, function (RegisterPluginPathsEvent $event) {
                $event->paths[] = Craft::getAlias('@verbb/footnotes/resources/redactor/');
            });
        }
    }

    private function _registerCkEditorPlugins(): void
    {
        if (PluginHelper::isPluginInstalledAndEnabled('ckeditor')) {
            CkEditor::registerCkeditorPackage(CkEditorAsset::class);
        }
    }
}
