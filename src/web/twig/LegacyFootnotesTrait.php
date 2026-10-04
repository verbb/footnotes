<?php
namespace verbb\footnotes\web\twig;

use verbb\footnotes\Footnotes;

use Craft;

trait LegacyFootnotesTrait
{
    // Public Methods
    // =========================================================================

    public function filterFootnotes(?string $value = null, array $options = []): string
    {
        $this->_deprecate('filter', 'The `footnotes` filter has been deprecated. Use `craft.footnotes.collection().add()` for relocated output, or render canonical field content directly.');

        return Footnotes::$plugin->getService()->filter($value, $options);
    }

    public function footnotesExist(): bool
    {
        $this->_deprecate('exist', 'The `footnotes_exist()` function has been deprecated. Use a local collection’s `hasNotes` property.');

        return Footnotes::$plugin->getService()->exist();
    }

    public function getFootnotes(array $options = []): array
    {
        $this->_deprecate('get', 'The `footnotes()` function has been deprecated. Use a local collection’s `render()` method or iterate over it.');

        return Footnotes::$plugin->getService()->get($options);
    }

    public function getFootnotesItems(array $options = []): array
    {
        $this->_deprecate('items', 'The `footnotes_items()` function has been deprecated. Iterate over a local collection instead.');

        return Footnotes::$plugin->getService()->getItems($options);
    }

    public function setFootnotes(array $footnotes = []): void
    {
        $this->_deprecate('set', 'The `footnotes_set()` function has been deprecated. Create a new local collection for an independent footnote group.');
        Footnotes::$plugin->getService()->set($footnotes);
    }


    // Private Methods
    // =========================================================================

    private function _deprecate(string $name, string $message): void
    {
        Craft::$app->getDeprecator()->log('verbb.footnotes.' . $name, $message);
    }
}
