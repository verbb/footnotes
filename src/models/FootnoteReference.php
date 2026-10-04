<?php
namespace verbb\footnotes\models;

class FootnoteReference
{
    // Properties
    // =========================================================================

    public string $id;
    public string $anchorId;
    public string $backlinkTarget;


    // Public Methods
    // =========================================================================

    public function __construct(string $id, string $anchorId, string $backlinkTarget)
    {
        $this->id = $id;
        $this->anchorId = $anchorId;
        $this->backlinkTarget = $backlinkTarget;
    }
}
