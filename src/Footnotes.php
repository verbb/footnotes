<?php
namespace verbb\footnotes;

use verbb\footnotes\web\assets\ckeditor\CkEditorAsset;
use verbb\footnotes\base\PluginTrait;
use verbb\footnotes\helpers\Plugin as PluginHelper;
use verbb\footnotes\models\Settings;
use verbb\footnotes\gql\types\FootnotesProcessedType;
use verbb\footnotes\web\twig\Extension;
use verbb\footnotes\web\twig\variables\FootnotesVariable;

use Craft;
use craft\base\Plugin;
use craft\ckeditor\data\FieldData as CkeditorFieldData;
use craft\ckeditor\Field as CkeditorField;
use craft\ckeditor\Plugin as CkEditor;
use craft\events\DefineGqlTypeFieldsEvent;
use craft\events\RegisterGqlQueriesEvent;
use craft\events\RegisterGqlSchemaComponentsEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\gql\TypeManager;
use craft\helpers\Gql as GqlHelper;
use craft\helpers\UrlHelper;
use craft\htmlfield\events\ModifyPurifierConfigEvent;
use craft\redactor\events\RegisterPluginPathsEvent;
use craft\redactor\Field;
use craft\services\Gql;
use craft\web\UrlManager;
use craft\web\twig\variables\CraftVariable;

use GraphQL\Error\UserError;
use GraphQL\Type\Definition\Type;

use yii\base\Application;
use yii\base\Event;

class Footnotes extends Plugin
{
    // Constants
    // =========================================================================

    private const MAX_GRAPHQL_ANCHOR_SCOPE_BYTES = 255;
    private const MAX_GRAPHQL_FOOTNOTE_MARKERS = 1000;
    private const MAX_GRAPHQL_HTML_BYTES = 1048576;


    // Properties
    // =========================================================================

    public bool $hasCpSettings = true;
    public string $schemaVersion = '1.0.0';

    private bool $graphqlBudgetExceeded = false;
    private int $graphqlFootnoteMarkersProcessed = 0;
    private int $graphqlHtmlBytesProcessed = 0;


    // Traits
    // =========================================================================

    use PluginTrait;


    // Public Methods
    // =========================================================================

    public function init(): void
    {
        parent::init();

        self::$plugin = $this;

        // A normal PHP-FPM process creates one application per request, but this also keeps the
        // counters request-scoped on supported long-running hosts that reuse the plugin instance.
        Event::on(Application::class, Application::EVENT_BEFORE_REQUEST, function(): void {
            $this->_resetGraphqlProcessingBudget();
        });

        $this->_registerTwigExtensions();
        $this->_registerVariables();
        $this->_registerRedactorPlugins();
        $this->_registerCkEditorPlugins();
        $this->_registerCkEditorPurifierConfig();
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
        Craft::$app->getView()->registerTwigExtension(new Extension());
    }

    private function _registerVariables(): void
    {
        Event::on(CraftVariable::class, CraftVariable::EVENT_INIT, function(Event $event): void {
            $event->sender->set('footnotes', FootnotesVariable::class);
        });
    }

    private function _registerRedactorPlugins(): void
    {
        Event::on(Field::class, Field::EVENT_REGISTER_PLUGIN_PATHS, function(RegisterPluginPathsEvent $event) {
            $event->paths[] = Craft::getAlias('@verbb/footnotes/resources/redactor/');
        });
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

    private function _registerCkEditorPurifierConfig(): void
    {
        if (!PluginHelper::isPluginInstalledAndEnabled('ckeditor')) {
            return;
        }

        Event::on(CkeditorField::class, CkeditorField::EVENT_MODIFY_PURIFIER_CONFIG, function(ModifyPurifierConfigEvent $event): void {
            $definition = $event->config->getHTMLDefinition(true);

            foreach ([
                'sup' => ['data-footnote-reference', 'data-footnote-id', 'data-footnote-reference-id', 'data-footnote-text'],
                'ol' => ['data-footnotes', 'role', 'aria-label'],
                'li' => ['data-footnote-id', 'data-footnote-number', 'data-footnote-reference-ids', 'role'],
                'div' => ['data-footnote-backlinks'],
                'a' => ['data-footnote-backlink', 'data-footnote-id', 'data-footnote-reference-id', 'data-footnote-text', 'role', 'aria-label'],
            ] as $element => $attributes) {
                foreach ($attributes as $attribute) {
                    $definition->addAttribute($element, $attribute, 'CDATA');
                }
            }
        });
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
                'args' => [
                    'anchorScope' => [
                        'name' => 'anchorScope',
                        'type' => Type::string(),
                        'description' => 'Optional stable anchor prefix for this field (same as the Twig filter `anchorScope` option). Omit to generate a scoped token per resolve. Pass an empty string for legacy `footnote-1` / `fnref:1` fragments.',
                    ],
                ],
                'description' => 'Body HTML and footnote list, equivalent to the Twig `footnotes` filter plus `footnotes()` function.',
                'resolve' => function(mixed $source, array $arguments): array {
                    $html = $source instanceof CkeditorFieldData ? $source->getParsedContent() : '';

                    $options = $this->_graphqlAnchorScopeOptions($arguments);
                    $this->_claimGraphqlProcessingBudget($html);

                    return Footnotes::$plugin->getService()->parseForGraphql($html, $options);
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
                    'anchorScope' => [
                        'name' => 'anchorScope',
                        'type' => Type::string(),
                        'description' => 'Optional stable anchor prefix for this HTML block. Omit to generate a scoped token per resolve; pass an empty string for legacy `footnote-1` / `fnref:1` fragments.',
                    ],
                ],
                'description' => 'Process arbitrary HTML for footnotes (useful when the field is exposed as a plain string in GraphQL).',
                'resolve' => function(mixed $_root, array $args): array {
                    $options = $this->_graphqlAnchorScopeOptions($args);
                    $this->_claimGraphqlProcessingBudget($args['html']);

                    return Footnotes::$plugin->getService()->parseForGraphql($args['html'], $options);
                },
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

    private function _claimGraphqlProcessingBudget(string $html): void
    {
        if ($this->graphqlBudgetExceeded) {
            throw $this->_graphqlProcessingLimitError();
        }

        $htmlBytes = strlen($html);

        // The budget is cumulative for the whole request. GraphQL aliases and batched operations
        // otherwise turn a safe per-resolver limit into an effectively unbounded amount of work.
        if ($htmlBytes > self::MAX_GRAPHQL_HTML_BYTES - $this->graphqlHtmlBytesProcessed) {
            $this->graphqlBudgetExceeded = true;

            throw $this->_graphqlProcessingLimitError();
        }

        // Count the parser's exact, case-sensitive opening token instead of running a second regex.
        // This includes incomplete markers, so malformed input cannot bypass the work budget.
        $footnoteMarkers = substr_count($html, '<sup class="footnote"');

        if ($footnoteMarkers > self::MAX_GRAPHQL_FOOTNOTE_MARKERS - $this->graphqlFootnoteMarkersProcessed) {
            $this->graphqlBudgetExceeded = true;

            throw $this->_graphqlProcessingLimitError();
        }

        $this->graphqlHtmlBytesProcessed += $htmlBytes;
        $this->graphqlFootnoteMarkersProcessed += $footnoteMarkers;
    }

    private function _graphqlProcessingLimitError(): UserError
    {
        return new UserError('The Footnotes GraphQL processing limit has been exceeded (1 MiB of HTML or 1,000 footnote markers per request).');
    }

    private function _resetGraphqlProcessingBudget(): void
    {
        $this->graphqlBudgetExceeded = false;
        $this->graphqlFootnoteMarkersProcessed = 0;
        $this->graphqlHtmlBytesProcessed = 0;
    }

    /**
     * @param array<string, mixed> $arguments
     * @return array<string, string>
     */
    private function _graphqlAnchorScopeOptions(array $arguments): array
    {
        if (!array_key_exists('anchorScope', $arguments) || $arguments['anchorScope'] === null) {
            return [];
        }

        $anchorScope = (string) $arguments['anchorScope'];

        // The scope is repeated in every generated reference and list ID. Bounding it prevents a
        // small marker document from expanding into a disproportionately large GraphQL response.
        if (strlen($anchorScope) > self::MAX_GRAPHQL_ANCHOR_SCOPE_BYTES) {
            throw new UserError('The Footnotes GraphQL anchorScope argument must not exceed 255 bytes.');
        }

        return ['anchorScope' => $anchorScope];
    }
}
