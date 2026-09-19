/** Seed a Craft entry with Footnotes enabled on a genuine CKEditor field. */

use craft\ckeditor\Field as CkeditorField;
use craft\elements\Entry;
use craft\fieldlayoutelements\CustomField;
use craft\fieldlayoutelements\entries\EntryTitleField;
use craft\helpers\Json;
use craft\models\EntryType;
use craft\models\FieldLayout;
use craft\models\FieldLayoutTab;
use craft\models\Section;
use craft\models\Section_SiteSettings;

$fields = Craft::$app->getFields();
$entries = Craft::$app->getEntries();
$elements = Craft::$app->getElements();
$site = Craft::$app->getSites()->getPrimarySite();
$fieldHandle = 'footnotesArticleBody';
$sectionHandle = 'footnotesArticles';

$field = $fields->getFieldByHandle($fieldHandle);

if (!$field instanceof CkeditorField) {
    $field = new CkeditorField([
        'name' => 'Article body',
        'handle' => $fieldHandle,
        'toolbar' => ['heading', '|', 'bold', 'italic', 'link', 'footnotes'],
    ]);

    if (!$fields->saveField($field)) {
        throw new RuntimeException('Unable to save Footnotes CKEditor field: ' . Json::encode($field->getErrors()));
    }
}

$section = $entries->getSectionByHandle($sectionHandle);

if (!$section) {
    $entryType = new EntryType([
        'name' => 'Articles',
        'handle' => $sectionHandle . 'Type',
    ]);
    $layout = new FieldLayout(['type' => Entry::class]);
    $tab = new FieldLayoutTab(['name' => Craft::t('app', 'Content'), 'layout' => $layout]);
    $tab->setElements([new EntryTitleField(), new CustomField($field)]);
    $layout->setTabs([$tab]);
    $entryType->setFieldLayout($layout);

    if (!$entries->saveEntryType($entryType)) {
        throw new RuntimeException('Unable to save Footnotes entry type: ' . Json::encode($entryType->getErrors()));
    }

    $section = new Section([
        'name' => 'Articles',
        'handle' => $sectionHandle,
        'type' => Section::TYPE_CHANNEL,
    ]);
    $section->setEntryTypes([$entryType]);
    $section->setSiteSettings([new Section_SiteSettings([
        'siteId' => $site->id,
        'enabledByDefault' => true,
        'hasUrls' => false,
    ])]);

    if (!$entries->saveSection($section)) {
        throw new RuntimeException('Unable to save Footnotes section: ' . Json::encode($section->getErrors()));
    }
}

$entryType = $entries->getEntryTypesBySectionId($section->id)[0] ?? null;

if (!$entryType) {
    throw new RuntimeException('Footnotes section has no entry type.');
}

$entry = Entry::find()
    ->sectionId($section->id)
    ->slug('the-details-that-matter')
    ->siteId($site->id)
    ->status(null)
    ->one();

if (!$entry) {
    $entry = new Entry([
        'sectionId' => $section->id,
        'typeId' => $entryType->id,
        'siteId' => $site->id,
        'slug' => 'the-details-that-matter',
        'enabled' => true,
    ]);
}

$entry->title = 'The details that matter';
$entry->setFieldValue($fieldHandle, <<<'HTML'
<h2>Designing for the long read</h2>
<p>Good editorial design keeps the main argument moving while giving curious readers somewhere to find the supporting detail.<sup class="footnote" data-footnote-id="editorial-note">Background research and source notes belong here.</sup></p>
<p>Footnotes stay inside the same familiar editing workflow as the rest of the article.</p>
HTML);

if (!$elements->saveElement($entry)) {
    throw new RuntimeException('Unable to save Footnotes entry: ' . Json::encode($entry->getErrors()));
}

echo Json::encode([
    'entryEditRoute' => parse_url((string)$entry->getCpEditUrl(), PHP_URL_PATH),
], JSON_THROW_ON_ERROR);
