<?php
namespace verbb\footnotes;

use verbb\footnotes\assetbundles\CkEditorAsset;
use verbb\footnotes\base\PluginTrait;
use verbb\footnotes\helpers\Plugin as PluginHelper;
use verbb\footnotes\models\Settings;
use verbb\footnotes\gql\types\FootnotesProcessedType;
use verbb\footnotes\twigextensions\Extension;

use Craft;
use craft\base\Plugin;
use craft\ckeditor\data\FieldData as CkeditorFieldData;
use craft\ckeditor\Plugin as CkEditor;
use craft\events\DefineGqlTypeFieldsEvent;
use craft\events\RegisterGqlQueriesEvent;
use craft\events\RegisterGqlSchemaComponentsEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\gql\TypeManager;
use craft\helpers\Gql as GqlHelper;
use craft\helpers\UrlHelper;
use craft\redactor\events\RegisterPluginPathsEvent;
use craft\redactor\Field;
use craft\services\Gql;
use craft\web\UrlManager;

use GraphQL\Type\Definition\Type;

use yii\base\Event;

class Footnotes extends Plugin
{
    // Properties
    // =========================================================================

    public bool $hasCpSettings = true;
    public string $schemaVersion = '1.0.0';


    // Traits
    // =========================================================================

    use PluginTrait;


    // Public Methods
    // =========================================================================

    public function init(): void
    {
        parent::init();

        self::$plugin = $this;

        $this->_registerTwigExtensions();
        $this->_registerRedactorPlugins();
        $this->_registerCkEditorPlugins();
        $this->_registerGraphql();

        if (Craft::$app->getRequest()->getIsCpRequest()) {
            $this->_registerCpRoutes();
        }
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
        if (PluginHelper::isPluginInstalledAndEnabled('ckeditor') && Craft::$app->getRequest()->getIsCpRequest()) {
            CkEditor::registerCkeditorPackage(CkEditorAsset::class, 'index.js');

            $view = Craft::$app->getView();
            $assetManager = $view->getAssetManager();
            $bundle = $assetManager->getBundle(CkEditorAsset::class);

            // Ensure the package alias exists in the import map, regardless of plugin init order.
            $view->registerJsImport($bundle->namespace, $assetManager->getAssetUrl($bundle, 'index.js', false));
        }
    }

    private function _registerGraphql(): void
    {
        if (!Craft::$app->getConfig()->getGeneral()->enableGql) {
            return;
        }

        Event::on(TypeManager::class, TypeManager::EVENT_DEFINE_GQL_TYPE_FIELDS, function(DefineGqlTypeFieldsEvent $event): void {
            if (!$this->_isCkeditorFieldGqlType($event->typeName)) {
                return;
            }

            $event->fields['footnotesProcessed'] = [
                'name' => 'footnotesProcessed',
                'type' => Type::nonNull(FootnotesProcessedType::getType()),
                'description' => 'Body HTML and footnote list, equivalent to the Twig `footnotes` filter plus `footnotes()` function.',
                'resolve' => function(mixed $source): array {
                    $html = $source instanceof CkeditorFieldData ? $source->getParsedContent() : '';

                    return Footnotes::$plugin->getService()->parseForGraphql($html);
                },
            ];
        });

        Event::on(Gql::class, Gql::EVENT_REGISTER_GQL_QUERIES, function(RegisterGqlQueriesEvent $event): void {
            if (!GqlHelper::isSchemaAwareOf('footnotes')) {
                return;
            }

            $event->queries['parseFootnotesFromHtml'] = [
                'type' => FootnotesProcessedType::getType(),
                'args' => [
                    'html' => [
                        'name' => 'html',
                        'type' => Type::nonNull(Type::string()),
                        'description' => 'Raw or parsed rich text HTML containing `<sup class="footnote">` markers.',
                    ],
                ],
                'description' => 'Process arbitrary HTML for footnotes (useful when the field is exposed as a plain string in GraphQL).',
                'resolve' => fn(mixed $_root, array $args): array => Footnotes::$plugin->getService()->parseForGraphql($args['html']),
            ];
        });

        Event::on(Gql::class, Gql::EVENT_REGISTER_GQL_SCHEMA_COMPONENTS, function(RegisterGqlSchemaComponentsEvent $event): void {
            $label = Craft::t('footnotes', 'Footnotes');
            
            $event->queries[$label] = ($event->queries[$label] ?? []) + [
                'footnotes:read' => [
                    'label' => Craft::t('footnotes', 'Process footnotes via GraphQL (root query and CKEditor fields)'),
                ],
            ];
        });
    }

    private function _isCkeditorFieldGqlType(string $typeName): bool
    {
        return str_ends_with($typeName, '_CkeditorField');
    }
}
