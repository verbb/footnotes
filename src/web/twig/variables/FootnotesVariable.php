<?php
namespace verbb\footnotes\web\twig\variables;

use verbb\footnotes\collections\FootnoteCollection;
use verbb\footnotes\Footnotes;

class FootnotesVariable
{
    // Public Methods
    // =========================================================================

    public function collection(array $options = []): FootnoteCollection
    {
        return Footnotes::$plugin->getDocuments()->collection($options);
    }
}
